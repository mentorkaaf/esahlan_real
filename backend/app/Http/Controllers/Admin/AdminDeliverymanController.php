<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliveryman;
use App\Models\DeliverymanDocument;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminDeliverymanController extends Controller
{
    public function index(Request $request)
    {
        $query = Deliveryman::with(['user', 'district'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->driver_type, fn($q) => $q->where('driver_type', $request->driver_type))
            ->when($request->approval, fn($q) => $q->where('is_approved', $request->approval === 'approved'))
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            ))
            ->latest();

        $deliverymen = $query->paginate(20);
        return view('admin.deliverymen.index', compact('deliverymen'));
    }

    public function show(Deliveryman $deliveryman)
    {
        $deliveryman->load(['user', 'district', 'documents', 'orders' => fn($q) => $q->latest()->limit(10)]);
        return view('admin.deliverymen.show', compact('deliveryman'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'phone'        => 'required|string|unique:users,phone',
            'password'     => 'required|string|min:4',
            'driver_type'  => 'required|in:normal,truck',
            'vehicle_type' => 'required|string',
        ]);

        $role = DB::table('roles')->where('slug', 'deliveryman')->first();

        DB::transaction(function () use ($request, $role) {
            $user = User::create([
                'uuid'          => (string) Str::uuid(),
                'name'          => $request->name,
                'phone'         => $request->phone,
                'password'      => Hash::make($request->password),
                'wallet_pin'    => Hash::make($request->password),
                'role_id'       => $role?->id,
                'status'        => 'active',
                'referral_code' => strtoupper(Str::random(8)),
            ]);

            Deliveryman::create([
                'user_id'       => $user->id,
                'driver_type'   => $request->driver_type,
                'vehicle_type'  => $request->vehicle_type,
                'vehicle_plate' => $request->plate_number,
                'status'        => 'pending',
                'is_approved'   => $request->boolean('auto_approve'),
            ]);

            Wallet::getOrCreateFor('App\\Models\\User', $user->id);
        });

        return back()->with('success', 'Driver created.');
    }

    public function approve(Deliveryman $deliveryman)
    {
        $deliveryman->update(['is_approved' => true, 'status' => 'offline']);
        $deliveryman->user?->update(['status' => 'active']);

        // Notify driver
        try {
            $token = $deliveryman->fcm_token ?? $deliveryman->user?->fcm_token;
            if ($token) {
                \App\Services\FcmService::sendToToken($token, '✅ Application Approved!',
                    'Your driver application has been approved. You can now go online and start earning!');
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Driver approved and notified.');
    }

    public function reject(Request $request, Deliveryman $deliveryman)
    {
        $deliveryman->update(['is_approved' => false]);
        return back()->with('success', 'Driver rejected.');
    }

    public function toggleBlock(Deliveryman $deliveryman)
    {
        $newStatus = $deliveryman->user->status === 'active' ? 'banned' : 'active';
        $deliveryman->user->update(['status' => $newStatus]);
        if ($newStatus === 'banned') $deliveryman->update(['is_online' => false, 'is_available' => false, 'status' => 'offline']);
        return back()->with('success', $newStatus === 'banned' ? 'Driver suspended.' : 'Driver reactivated.');
    }

    public function destroy(Deliveryman $deliveryman)
    {
        $userId = $deliveryman->user_id;

        // Clean up related data
        DB::table('deliveryman_earnings')->where('deliveryman_id', $deliveryman->id)->delete();
        DB::table('deliveryman_documents')->where('deliveryman_id', $deliveryman->id)->delete();
        DB::table('order_tracking')->where('deliveryman_id', $deliveryman->id)->delete();
        $deliveryman->forceDelete();

        // Delete user + wallet
        if ($userId) {
            $walletIds = DB::table('wallets')->where('owner_type', 'App\\Models\\User')->where('owner_id', $userId)->pluck('id');
            DB::table('transactions')->whereIn('wallet_id', $walletIds)->delete();
            DB::table('wallets')->whereIn('id', $walletIds)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->where('tokenable_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        }

        return back()->with('success', 'Driver permanently deleted.');
    }

    public function earnings(Request $request)
    {
        $query = DB::table('deliveryman_earnings')
            ->join('deliverymen', 'deliverymen.id', '=', 'deliveryman_earnings.deliveryman_id')
            ->join('users', 'users.id', '=', 'deliverymen.user_id')
            ->leftJoin('orders', 'orders.id', '=', 'deliveryman_earnings.order_id')
            ->select(
                'deliveryman_earnings.*',
                'users.name as driver_name',
                'users.phone as driver_phone',
                'deliverymen.vehicle_type',
                'deliverymen.driver_type',
                'orders.order_number',
                'orders.module_slug'
            );

        if ($request->filled('driver_id')) {
            $query->where('deliveryman_earnings.deliveryman_id', $request->driver_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('deliveryman_earnings.created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('deliveryman_earnings.created_at', '<=', $request->to);
        }

        $earnings = $query->orderByDesc('deliveryman_earnings.created_at')->paginate(30)->withQueryString();

        // Summary stats
        $summaryQuery = DB::table('deliveryman_earnings');
        if ($request->filled('driver_id')) $summaryQuery->where('deliveryman_id', $request->driver_id);
        if ($request->filled('from')) $summaryQuery->whereDate('created_at', '>=', $request->from);
        if ($request->filled('to')) $summaryQuery->whereDate('created_at', '<=', $request->to);

        $summary = [
            'total'       => (float) (clone $summaryQuery)->sum('amount'),
            'today'       => (float) (clone $summaryQuery)->whereDate('created_at', today())->sum('amount'),
            'this_month'  => (float) (clone $summaryQuery)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
            'total_count' => (clone $summaryQuery)->count(),
        ];

        // Driver list for filter dropdown
        $drivers = Deliveryman::with('user:id,name')->whereHas('earnings')->get();

        // Per-driver summary
        $perDriver = DB::table('deliveryman_earnings')
            ->join('deliverymen', 'deliverymen.id', '=', 'deliveryman_earnings.deliveryman_id')
            ->join('users', 'users.id', '=', 'deliverymen.user_id')
            ->select('deliveryman_earnings.deliveryman_id', 'users.name', 'deliverymen.vehicle_type',
                DB::raw('SUM(deliveryman_earnings.amount) as total_earned'),
                DB::raw('COUNT(*) as total_deliveries'))
            ->groupBy('deliveryman_earnings.deliveryman_id', 'users.name', 'deliverymen.vehicle_type')
            ->orderByDesc('total_earned')
            ->get();

        return view('admin.deliverymen.earnings', compact('earnings', 'summary', 'drivers', 'perDriver'));
    }

    public function resetEarning(Request $request, Deliveryman $deliveryman)
    {
        DB::table('deliveryman_earnings')->where('deliveryman_id', $deliveryman->id)->delete();
        $deliveryman->update(['total_deliveries' => 0]);
        return back()->with('success', "Earnings reset for {$deliveryman->user?->name}.");
    }

    public function bulkResetEarnings()
    {
        $count = DB::table('deliveryman_earnings')->count();
        DB::table('deliveryman_earnings')->delete();
        Deliveryman::query()->update(['total_deliveries' => 0]);
        return back()->with('success', "{$count} earning records deleted. All driver stats reset.");
    }

    public function saveSettings(Request $request)
    {
        $max = $request->filled('max_orders_custom') && $request->max_orders_custom > 0
            ? (int) $request->max_orders_custom
            : (int) ($request->max_orders_per_driver ?? 5);

        \App\Helpers\AppSettings::set('max_orders_per_driver', $max);

        return back()->with('success', "Max orders per driver set to {$max}.");
    }

    public function approveDocument(DeliverymanDocument $document)
    {
        $document->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        return back()->with('success', 'Document approved.');
    }

    public function rejectDocument(DeliverymanDocument $document)
    {
        $document->update(['status' => 'rejected', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        return back()->with('success', 'Document rejected.');
    }
}
