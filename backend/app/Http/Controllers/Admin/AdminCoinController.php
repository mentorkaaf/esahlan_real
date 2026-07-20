<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\UserCoin;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminCoinController extends Controller
{
    public function index()
    {
        $week  = Carbon::now()->subDays(7);
        $month = Carbon::now()->subDays(30);

        $stats = [
            'packages_active'   => CoinPackage::where('is_active', true)->count(),
            'purchases_today'   => CoinPurchase::whereDate('created_at', today())->where('status', 'completed')->count(),
            'revenue_today'     => CoinPurchase::whereDate('created_at', today())->where('status', 'completed')->sum('amount_paid'),
            'revenue_week'      => CoinPurchase::where('created_at', '>=', $week)->where('status', 'completed')->sum('amount_paid'),
            'revenue_month'     => CoinPurchase::where('created_at', '>=', $month)->where('status', 'completed')->sum('amount_paid'),
            'revenue_total'     => CoinPurchase::where('status', 'completed')->sum('amount_paid'),
            'coins_sold_total'  => CoinPurchase::where('status', 'completed')->sum('coins_received'),
            'top_spender_coins' => UserCoin::max('balance') ?? 0,
        ];

        $packages = CoinPackage::orderBy('sort_order')->get();

        $recentPurchases = CoinPurchase::with(['user.communityProfile', 'package'])
            ->where('status', 'completed')
            ->latest()
            ->limit(20)
            ->get();

        $dailyRevenue = CoinPurchase::selectRaw('DATE(created_at) as date, SUM(amount_paid) as total')
            ->where('created_at', '>=', $week)
            ->where('status', 'completed')
            ->groupBy('date')->orderBy('date')
            ->pluck('total', 'date');

        return view('admin.coins.index', compact('stats', 'packages', 'recentPurchases', 'dailyRevenue'));
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:60',
            'coins'       => 'required|integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
            'price'       => 'required|numeric|min:0.01',
            'currency'    => 'required|string|size:3',
            'badge_label' => 'nullable|string|max:20',
            'is_featured' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);

        CoinPackage::create(array_merge($data, [
            'is_active'   => true,
            'bonus_coins' => $data['bonus_coins'] ?? 0,
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_featured' => $data['is_featured'] ?? false,
        ]));

        return back()->with('success', 'Package created.');
    }

    public function updatePackage(Request $request, int $id)
    {
        $package = CoinPackage::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:60',
            'coins'       => 'required|integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
            'price'       => 'required|numeric|min:0.01',
            'currency'    => 'required|string|size:3',
            'badge_label' => 'nullable|string|max:20',
            'is_featured' => 'nullable|boolean',
            'sort_order'  => 'nullable|integer|min:0',
        ]);
        $package->update($data);

        return back()->with('success', 'Package updated.');
    }

    public function togglePackage(int $id)
    {
        $package = CoinPackage::findOrFail($id);
        $package->update(['is_active' => !$package->is_active]);
        return back()->with('success', 'Package status toggled.');
    }

    public function destroyPackage(int $id)
    {
        CoinPackage::findOrFail($id)->delete();
        return back()->with('success', 'Package deleted.');
    }

    public function purchases(Request $request)
    {
        $q = CoinPurchase::with(['user.communityProfile', 'package']);

        if ($s = $request->search) {
            $q->whereHas('user', fn($sq) => $sq->where('name', 'like', "%$s%"));
        }
        if ($status = $request->status) $q->where('status', $status);
        if ($from = $request->from) $q->whereDate('created_at', '>=', $from);
        if ($to   = $request->to)   $q->whereDate('created_at', '<=', $to);

        $purchases = $q->orderByDesc('created_at')->paginate(30)->withQueryString();
        $totalRevenue = CoinPurchase::where('status', 'completed')->sum('amount_paid');

        return view('admin.coins.purchases', compact('purchases', 'totalRevenue'));
    }
}
