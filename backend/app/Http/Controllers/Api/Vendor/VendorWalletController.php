<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Models\Commission;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorWalletController extends Controller
{
    public function index(): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $wallet = Wallet::getOrCreateFor(Vendor::class, $vendor->id);

        $stats = [
            'balance'           => $wallet->balance,
            'total_earned'      => Commission::where('vendor_id', $vendor->id)->sum('vendor_earning'),
            'pending_commission'=> Commission::where('vendor_id', $vendor->id)->where('status', 'pending')->sum('vendor_earning'),
            'total_withdrawn'   => WithdrawalRequest::where('owner_type', Vendor::class)
                ->where('owner_id', $vendor->id)->where('status', 'completed')->sum('amount'),
        ];

        return $this->success(['stats' => $stats, 'wallet' => $wallet]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $wallet = Wallet::getOrCreateFor(Vendor::class, $vendor->id);

        $transactions = $wallet->transactions()
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->paginated($transactions);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount'          => 'required|numeric|min:10',
            'payment_method'  => 'required|in:waafi,bank_transfer',
            'account_number'  => 'required|string',
            'account_name'    => 'required|string',
        ]);

        $vendor = auth()->user()->vendor;
        $wallet = Wallet::getOrCreateFor(Vendor::class, $vendor->id);

        if ($wallet->balance < $request->amount) {
            return $this->error('Insufficient balance.', 422);
        }

        $pending = WithdrawalRequest::where('owner_type', Vendor::class)
            ->where('owner_id', $vendor->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return $this->error('You already have a pending withdrawal request.', 422);
        }

        $withdrawal = WithdrawalRequest::create([
            'owner_type'     => Vendor::class,
            'owner_id'       => $vendor->id,
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'account_number' => $request->account_number,
            'account_name'   => $request->account_name,
            'status'         => 'pending',
        ]);

        return $this->success(['withdrawal' => $withdrawal, 'message' => 'Withdrawal request submitted.'], 201);
    }
}
