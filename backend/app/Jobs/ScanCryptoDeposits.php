<?php

namespace App\Jobs;

use App\Models\CryptoDeposit;
use App\Models\CryptoWallet;
use App\Models\ExchangeCoin;
use App\Services\CryptoNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Blockchain deposit scanner — runs every minute via scheduler.
 *
 * Supports:
 *  - Tron (USDT TRC20)  via TronGrid API (free, no key needed for basic use)
 *  - Ethereum / BNB (USDT ERC20/BEP20) via Alchemy/Infura
 *
 * Required .env:
 *   TRONGRID_API_KEY=your_key          (get free at trongrid.io)
 *   ALCHEMY_API_KEY=your_key           (get free at alchemy.com)
 *   USDT_TRC20_CONTRACT=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t
 *   USDT_ERC20_CONTRACT=0xdAC17F958D2ee523a2206206994597C13D831ec7
 */
class ScanCryptoDeposits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        $this->scanTron();
        $this->scanEth();
    }

    // ── Tron / TRC20 ─────────────────────────────────────────────────────────

    private function scanTron(): void
    {
        // Get all wallets with Tron-style addresses (start with 'T')
        $wallets = CryptoWallet::with(['user', 'coin', 'network'])
            ->whereHas('network', fn($q) => $q->where('chain', 'like', '%TRC%')->orWhere('chain', 'TRON'))
            ->whereNotNull('address')
            ->where('address', 'like', 'T%')
            ->get();

        foreach ($wallets as $wallet) {
            try {
                $this->checkTronWallet($wallet);
            } catch (\Throwable $e) {
                Log::warning("[TronScan] wallet#{$wallet->id}: " . $e->getMessage());
            }
        }
    }

    private function checkTronWallet(CryptoWallet $wallet): void
    {
        $apiKey   = config('crypto.trongrid_api_key', env('TRONGRID_API_KEY', ''));
        $contract = env('USDT_TRC20_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $headers = $apiKey ? ['TRON-PRO-API-KEY' => $apiKey] : [];

        $res = Http::withHeaders($headers)
            ->timeout(15)
            ->get("https://api.trongrid.io/v1/accounts/{$wallet->address}/transactions/trc20", [
                'only_confirmed' => true,
                'limit'          => 20,
                'contract_address' => $contract,
            ]);

        if (!$res->successful()) return;

        $txs = $res->json('data') ?? [];
        foreach ($txs as $tx) {
            $this->processTronTx($wallet, $tx);
        }
    }

    private function processTronTx(CryptoWallet $wallet, array $tx): void
    {
        $txHash = $tx['transaction_id'] ?? null;
        if (!$txHash) return;

        // Already processed?
        if (CryptoDeposit::where('txhash', $txHash)->exists()) return;

        // Only process incoming transactions (to = wallet address)
        $toAddr = $tx['to'] ?? '';
        if (strtolower($toAddr) !== strtolower($wallet->address)) return;

        // Amount (TRC20 has 6 decimals for USDT)
        $rawAmount = (float) ($tx['value'] ?? 0);
        $amount    = $rawAmount / 1_000_000; // TRC20 USDT has 6 decimals

        if ($amount < ($wallet->coin->min_deposit ?? 0.01)) return;

        // Credit wallet
        $this->creditDeposit($wallet, $amount, $txHash, 'trc20', 1);
    }

    // ── Ethereum / ERC20 / BEP20 ─────────────────────────────────────────────

    private function scanEth(): void
    {
        $wallets = CryptoWallet::with(['user', 'coin', 'network'])
            ->whereHas('network', fn($q) => $q->where('chain', 'like', '%ERC%')->orWhere('chain', 'like', '%BEP%'))
            ->whereNotNull('address')
            ->where('address', 'like', '0x%')
            ->get();

        foreach ($wallets as $wallet) {
            try {
                $this->checkEthWallet($wallet);
            } catch (\Throwable $e) {
                Log::warning("[EthScan] wallet#{$wallet->id}: " . $e->getMessage());
            }
        }
    }

    private function checkEthWallet(CryptoWallet $wallet): void
    {
        $apiKey   = config('crypto.alchemy_api_key', env('ALCHEMY_API_KEY', ''));
        if (!$apiKey) return; // skip if no API key

        $chain = strtoupper($wallet->network?->chain ?? '');
        $url   = match(true) {
            str_contains($chain, 'BEP') => "https://bnb-mainnet.g.alchemy.com/v2/{$apiKey}",
            default => "https://eth-mainnet.g.alchemy.com/v2/{$apiKey}",
        };

        $contract = str_contains($chain, 'BEP')
            ? env('USDT_BEP20_CONTRACT', '0x55d398326f99059fF775485246999027B3197955')
            : env('USDT_ERC20_CONTRACT', '0xdAC17F958D2ee523a2206206994597C13D831ec7');

        // Use Alchemy's getAssetTransfers
        $res = Http::timeout(15)->post($url, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'alchemy_getAssetTransfers',
            'params'  => [[
                'fromBlock'       => 'latest',
                'toAddress'       => $wallet->address,
                'contractAddresses' => [$contract],
                'category'        => ['erc20'],
                'withMetadata'    => false,
                'excludeZeroValue'=> true,
                'maxCount'        => '0x14',
            ]],
        ]);

        if (!$res->successful()) return;

        $transfers = $res->json('result.transfers') ?? [];
        foreach ($transfers as $tx) {
            $this->processEthTx($wallet, $tx);
        }
    }

    private function processEthTx(CryptoWallet $wallet, array $tx): void
    {
        $txHash = $tx['hash'] ?? null;
        if (!$txHash) return;

        if (CryptoDeposit::where('tx_hash', $txHash)->exists()) return;

        $toAddr = $tx['to'] ?? '';
        if (strtolower($toAddr) !== strtolower($wallet->address)) return;

        $amount = floatval($tx['value'] ?? 0);
        if ($amount < ($wallet->coin->min_deposit ?? 0.01)) return;

        $this->creditDeposit($wallet, $amount, $txHash, strtolower($wallet->network?->chain ?? 'erc20'), 12);
    }

    // ── Shared credit logic ───────────────────────────────────────────────────

    private function creditDeposit(CryptoWallet $wallet, float $amount, string $txHash, string $network, int $requiredConfs): void
    {
        $deposit = CryptoDeposit::create([
            'uuid'                  => (string) \Illuminate\Support\Str::uuid(),
            'user_id'               => $wallet->user_id,
            'coin_id'               => $wallet->coin_id,
            'network_id'            => $wallet->network_id,
            'wallet_id'             => $wallet->id,
            'txhash'                => $txHash,
            'amount'                => $amount,
            'fee'                   => 0,
            'confirmations'         => $requiredConfs,
            'required_confirmations'=> $requiredConfs,
            'status'                => 'confirmed',
            'confirmed_at'          => now(),
        ]);

        // Credit the wallet balance
        $wallet->credit($amount, 'deposit', "Blockchain deposit: {$txHash}", 'crypto_deposit', $deposit->id);

        // FCM notification
        $user = $wallet->user;
        if ($user) {
            CryptoNotificationService::depositConfirmed(
                $user,
                $amount,
                $wallet->coin?->symbol ?? '?',
                strtoupper($network),
                $txHash
            );
        }

        Log::info("[DepositScan] Credited {$amount} {$wallet->coin?->symbol} to user #{$wallet->user_id} tx:{$txHash}");
    }
}
