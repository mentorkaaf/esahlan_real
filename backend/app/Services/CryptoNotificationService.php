<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Centralized FCM notification service for all crypto events.
 * Every transaction, deposit, withdrawal, transfer, order, and P2P event
 * sends a push notification to the relevant user(s).
 */
class CryptoNotificationService
{
    // ── Deposits ──────────────────────────────────────────────────────────────

    public static function depositDetected(User $user, float $amount, string $symbol, string $network, string $txHash): void
    {
        self::send($user, '🔍 Lacag La Ogaaday', [
            'en' => "Deposit detected: {$amount} {$symbol} ({$network}) — awaiting confirmation",
            'so' => "{$amount} {$symbol} ayaa la ogaaday — xaqiijinta la sugayaa",
        ], [
            'type'     => 'crypto_deposit_pending',
            'amount'   => (string) $amount,
            'symbol'   => $symbol,
            'network'  => $network,
            'tx_hash'  => $txHash,
        ]);
    }

    public static function depositConfirmed(User $user, float $amount, string $symbol, string $network, string $txHash): void
    {
        self::send($user, "✅ {$amount} {$symbol} Waa La Helay!", [
            'en' => "{$amount} {$symbol} deposit confirmed and credited to your wallet",
            'so' => "{$amount} {$symbol} waa la xaqiijiyay oo walletkaaga ayaa lagu daray",
        ], [
            'type'    => 'crypto_deposit_confirmed',
            'amount'  => (string) $amount,
            'symbol'  => $symbol,
            'network' => $network,
            'tx_hash' => $txHash,
        ]);
    }

    // ── Buy / Sell ────────────────────────────────────────────────────────────

    public static function buyCompleted(User $user, float $cryptoAmount, string $symbol, float $totalUsd): void
    {
        self::send($user, "🟢 Waxaad Iibsatay {$cryptoAmount} {$symbol}", [
            'en' => "Buy order completed: {$cryptoAmount} {$symbol} for \${$totalUsd}",
            'so' => "Iibsiga waa la dhammaystiray: {$cryptoAmount} {$symbol} oo qiimaheedu yahay \${$totalUsd}",
        ], [
            'type'          => 'crypto_buy_completed',
            'crypto_amount' => (string) $cryptoAmount,
            'symbol'        => $symbol,
            'total_usd'     => (string) $totalUsd,
        ]);
    }

    public static function buyPending(User $user, float $cryptoAmount, string $symbol, float $totalUsd): void
    {
        self::send($user, "⏳ Iibsiga {$symbol} La Sugayaa", [
            'en' => "Buy order placed for {$cryptoAmount} {$symbol} — awaiting payment confirmation",
            'so' => "Iibsiga {$cryptoAmount} {$symbol} waa la qaaday — lacagta la sugayaa",
        ], [
            'type'   => 'crypto_buy_pending',
            'symbol' => $symbol,
        ]);
    }

    public static function sellCompleted(User $user, float $cryptoAmount, string $symbol, float $netUsd): void
    {
        self::send($user, "🔴 Waxaad Iibisay {$cryptoAmount} {$symbol}", [
            'en' => "Sell order completed: {$cryptoAmount} {$symbol} → \${$netUsd}",
            'so' => "Iibinta waa la dhammaystiray: {$cryptoAmount} {$symbol} → \${$netUsd}",
        ], [
            'type'          => 'crypto_sell_completed',
            'crypto_amount' => (string) $cryptoAmount,
            'symbol'        => $symbol,
            'net_usd'       => (string) $netUsd,
        ]);
    }

    public static function sellPending(User $user, float $cryptoAmount, string $symbol): void
    {
        self::send($user, "⏳ Iibinta {$symbol} La Sugayaa", [
            'en' => "Sell order placed for {$cryptoAmount} {$symbol} — awaiting admin processing",
            'so' => "Iibinta {$cryptoAmount} {$symbol} waa la qaaday — maamulka la sugayaa",
        ], ['type' => 'crypto_sell_pending', 'symbol' => $symbol]);
    }

    // ── Withdrawals ───────────────────────────────────────────────────────────

    public static function withdrawalSubmitted(User $user, float $amount, string $symbol, string $toAddress): void
    {
        self::send($user, "📤 Lacag Bixinta {$symbol} La Qaatay", [
            'en' => "Withdrawal of {$amount} {$symbol} submitted — processing within 24 hours",
            'so' => "Lacag bixinta {$amount} {$symbol} waa la qaatay — 24 saac gudahood la diri doonaa",
        ], [
            'type'       => 'crypto_withdrawal_submitted',
            'amount'     => (string) $amount,
            'symbol'     => $symbol,
            'to_address' => substr($toAddress, 0, 10) . '...' . substr($toAddress, -6),
        ]);
    }

    public static function withdrawalApproved(User $user, float $amount, string $symbol, string $txHash): void
    {
        self::send($user, "✅ Lacag Bixinta {$amount} {$symbol} Waa La Diray", [
            'en' => "Withdrawal of {$amount} {$symbol} has been sent to blockchain",
            'so' => "{$amount} {$symbol} blockchain-ka ayaa lagu diray — 1-30 daqiiqo gudahood la heli doonaa",
        ], [
            'type'    => 'crypto_withdrawal_approved',
            'amount'  => (string) $amount,
            'symbol'  => $symbol,
            'tx_hash' => $txHash,
        ]);
    }

    public static function withdrawalRejected(User $user, float $amount, string $symbol, string $reason): void
    {
        self::send($user, "❌ Lacag Bixinta La Diiday", [
            'en' => "Withdrawal of {$amount} {$symbol} was rejected: {$reason}. Funds returned to your wallet.",
            'so' => "Lacag bixinta {$amount} {$symbol} waa la diiday: {$reason}. Lacagtu walletkaaga ayay ku laabatay.",
        ], [
            'type'   => 'crypto_withdrawal_rejected',
            'amount' => (string) $amount,
            'symbol' => $symbol,
        ]);
    }

    // ── Transfers ─────────────────────────────────────────────────────────────

    public static function transferSent(User $sender, float $amount, string $symbol, string $recipientName): void
    {
        self::send($sender, "↗️ {$amount} {$symbol} La Diray", [
            'en' => "You sent {$amount} {$symbol} to {$recipientName}",
            'so' => "Waxaad u diray {$recipientName}: {$amount} {$symbol}",
        ], ['type' => 'crypto_transfer_sent', 'amount' => (string) $amount, 'symbol' => $symbol]);
    }

    public static function transferReceived(User $recipient, float $amount, string $symbol, string $senderName): void
    {
        self::send($recipient, "↙️ Waxaad Heshay {$amount} {$symbol}!", [
            'en' => "{$senderName} sent you {$amount} {$symbol}",
            'so' => "{$senderName} ayaa kuu diray {$amount} {$symbol}",
        ], ['type' => 'crypto_transfer_received', 'amount' => (string) $amount, 'symbol' => $symbol]);
    }

    // ── P2P ──────────────────────────────────────────────────────────────────

    public static function p2pOrderMatched(User $user, float $amount, string $symbol, string $side): void
    {
        $action = $side === 'buy' ? 'iibsiga' : 'iibinta';
        self::send($user, "🤝 P2P {$amount} {$symbol} La Waafaqay", [
            'en' => "P2P {$side} order matched: {$amount} {$symbol}",
            'so' => "P2P {$action} {$amount} {$symbol} waa la waafaqay — wax ka qabso",
        ], ['type' => 'p2p_order_matched', 'amount' => (string) $amount, 'symbol' => $symbol, 'side' => $side]);
    }

    public static function p2pPaymentMarked(User $seller, string $buyerName, float $amount, string $symbol): void
    {
        self::send($seller, "💳 Lacagta La Bixiyay — Xaqiiji", [
            'en' => "{$buyerName} marked payment of {$amount} {$symbol} as sent — verify and release crypto",
            'so' => "{$buyerName} wuxuu sheegay inuu lacagta diray — hubi oo crypto sii daaya",
        ], ['type' => 'p2p_payment_marked', 'amount' => (string) $amount, 'symbol' => $symbol]);
    }

    public static function p2pCryptoReleased(User $buyer, float $amount, string $symbol): void
    {
        self::send($buyer, "✅ P2P Waa La Dhammaystiray — {$amount} {$symbol} Waa Laheed!", [
            'en' => "P2P trade complete: {$amount} {$symbol} credited to your wallet",
            'so' => "P2P ganacsigii waa dhamaaday: {$amount} {$symbol} walletkaaga ayaa lagu daray",
        ], ['type' => 'p2p_completed', 'amount' => (string) $amount, 'symbol' => $symbol]);
    }

    public static function p2pOrderCancelled(User $user, float $amount, string $symbol): void
    {
        self::send($user, "❌ P2P Order Waa La Joojiyay", [
            'en' => "P2P order for {$amount} {$symbol} was cancelled",
            'so' => "P2P {$amount} {$symbol} waa la joojiyay",
        ], ['type' => 'p2p_cancelled', 'symbol' => $symbol]);
    }

    // ── Price alerts ──────────────────────────────────────────────────────────

    public static function priceAlert(User $user, string $symbol, float $currentPrice, float $changePercent): void
    {
        $dir  = $changePercent > 0 ? '📈' : '📉';
        $sign = $changePercent > 0 ? '+' : '';
        self::send($user, "{$dir} {$symbol} {$sign}" . number_format($changePercent, 1) . '% 24H', [
            'en' => "{$symbol} price: \${$currentPrice} ({$sign}{$changePercent}% in 24h)",
            'so' => "{$symbol} qiimihiisa: \${$currentPrice} ({$sign}{$changePercent}% 24 saac)",
        ], ['type' => 'price_alert', 'symbol' => $symbol, 'price' => (string) $currentPrice]);
    }

    // ── Core send ─────────────────────────────────────────────────────────────

    private static function send(User $user, string $title, array $body, array $data = []): void
    {
        if (!$user->fcm_token) return;
        try {
            // Pick English body as default (app-level i18n handles the rest)
            FcmService::sendToToken(
                $user->fcm_token,
                $title,
                $body['en'],
                array_merge($data, ['body_so' => $body['so'] ?? '']),
                null
            );
        } catch (\Throwable $e) {
            Log::warning('[CryptoNotif] FCM failed for user ' . $user->id . ': ' . $e->getMessage());
        }
    }
}
