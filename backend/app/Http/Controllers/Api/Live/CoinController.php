<?php
namespace App\Http\Controllers\Api\Live;

use App\Http\Controllers\Controller;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\UserCoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoinController extends Controller
{
    /** GET /live/coins/balance */
    public function balance()
    {
        $wallet = UserCoin::firstOrCreate(
            ['user_id' => auth()->id()],
            ['balance' => 0]
        );

        return response()->json([
            'status' => 'success',
            'data'   => ['balance' => (int) $wallet->balance],
        ]);
    }

    /** GET /live/coins/packages */
    public function packages()
    {
        $packages = CoinPackage::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('coins')
            ->get()
            ->map(fn($p) => $this->transformPackage($p));

        return response()->json(['status' => 'success', 'data' => $packages]);
    }

    /** POST /live/coins/buy */
    public function buy(Request $request)
    {
        $request->validate([
            'package_id'        => 'required|exists:coin_packages,id',
            'payment_method'    => 'required|in:waafi_pay,epay',
            'payment_reference' => 'required|string|max:200',
        ]);

        $package = CoinPackage::where('id', $request->package_id)
            ->where('is_active', true)
            ->firstOrFail();

        // Prevent duplicate payment_reference
        if (CoinPurchase::where('payment_reference', $request->payment_reference)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Duplicate payment reference'], 422);
        }

        $totalCoins = $package->coins + $package->bonus_coins;

        DB::transaction(function () use ($request, $package, $totalCoins) {
            CoinPurchase::create([
                'user_id'           => auth()->id(),
                'package_id'        => $package->id,
                'coins_received'    => $totalCoins,
                'amount_paid'       => $package->price,
                'currency'          => $package->currency,
                'payment_method'    => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'status'            => 'completed',
                'payment_metadata'  => $request->only(['phone', 'transaction_id', 'raw']),
            ]);

            UserCoin::updateOrCreate(
                ['user_id' => auth()->id()],
                ['balance' => 0]
            );
            UserCoin::where('user_id', auth()->id())
                ->increment('balance', $totalCoins);
        });

        $newBalance = UserCoin::where('user_id', auth()->id())->value('balance') ?? 0;

        return response()->json([
            'status'  => 'success',
            'data'    => [
                'coins_received' => $totalCoins,
                'new_balance'    => (int) $newBalance,
            ],
        ]);
    }

    /** GET /live/coins/history */
    public function history()
    {
        $purchases = CoinPurchase::with('package')
            ->where('user_id', auth()->id())
            ->where('status', 'completed')
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $purchases->map(fn($p) => [
                'id'             => $p->id,
                'coins_received' => $p->coins_received,
                'amount_paid'    => $p->amount_paid,
                'currency'       => $p->currency,
                'payment_method' => $p->payment_method,
                'package_name'   => $p->package?->name,
                'created_at'     => $p->created_at->toISOString(),
            ]),
        ]);
    }

    private function transformPackage(CoinPackage $p): array
    {
        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'coins'       => $p->coins,
            'bonus_coins' => $p->bonus_coins,
            'total_coins' => $p->coins + $p->bonus_coins,
            'price'       => $p->price,
            'currency'    => $p->currency,
            'badge_label' => $p->badge_label,
            'is_featured' => $p->is_featured,
        ];
    }
}
