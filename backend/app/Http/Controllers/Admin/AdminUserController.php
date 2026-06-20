<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['role', 'district'])
            ->when($request->role, fn($q) => $q->whereHas('role', fn($r) => $r->where('slug', $request->role)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($s) =>
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            ))
            ->latest();

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function liveLocations()
    {
        $users = User::with('district')
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'phone', 'latitude', 'longitude', 'location_updated_at', 'district_id']);

        $data = $users->filter(function ($u) {
            return ($u->latitude && $u->longitude) || ($u->district && $u->district->latitude && $u->district->longitude);
        })->map(function ($u) {
            $hasGps = $u->latitude && $u->longitude;
            return [
                'id'      => $u->id,
                'name'    => $u->name,
                'phone'   => $u->phone ?? '',
                'lat'     => (float) ($hasGps ? $u->latitude  : $u->district?->latitude),
                'lng'     => (float) ($hasGps ? $u->longitude : $u->district?->longitude),
                'url'     => route('admin.users.show', $u->id),
                'updated' => $hasGps ? (optional($u->location_updated_at)->diffForHumans() ?? 'Unknown') : ('District: ' . ($u->district?->name ?? '—')),
                'hasGps'  => $hasGps,
            ];
        })->values();

        return response()->json($data);
    }

    public function show(User $user)
    {
        $user->load(['role', 'wallet', 'district', 'orders' => fn($q) => $q->latest()->limit(10)]);
        return view('admin.users.show', compact('user'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate(['status' => 'required|in:active,inactive,banned']);
        $user->update(['status' => $request->status]);
        return back()->with('success', 'User status updated.');
    }

    public function destroy(User $user)
    {
        if ($user->role?->slug === 'super_admin') {
            return back()->with('error', 'Cannot delete super admin.');
        }
        self::purgeUser($user->id);
        return redirect()->route('admin.users.index')->with('success', 'User permanently deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:users,id']);

        $users = User::whereIn('id', $request->ids)
            ->whereDoesntHave('role', fn($q) => $q->where('slug', 'super_admin'))
            ->get();

        foreach ($users as $user) {
            self::purgeUser($user->id);
        }

        return back()->with('success', count($users) . ' user(s) permanently deleted.');
    }

    private static function purgeUser(int $userId): void
    {
        $walletIds = DB::table('wallets')
            ->where('owner_type', 'App\\Models\\User')
            ->where('owner_id', $userId)
            ->pluck('id');

        DB::table('transactions')->whereIn('wallet_id', $walletIds)->delete();
        DB::table('withdrawal_requests')->where('owner_type', 'App\\Models\\User')->where('owner_id', $userId)->delete();
        DB::table('wallets')->whereIn('id', $walletIds)->delete();
        DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->where('tokenable_id', $userId)->delete();

        $orderIds = DB::table('orders')->where('user_id', $userId)->pluck('id');
        if ($orderIds->isNotEmpty()) {
            DB::table('order_status_history')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();
        }

        if (DB::getSchemaBuilder()->hasTable('payment_transactions')) {
            DB::table('payment_transactions')->where('user_id', $userId)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('notifications')) {
            DB::table('notifications')->where('notifiable_type', 'App\\Models\\User')->where('notifiable_id', $userId)->delete();
        }
        foreach (['community_comments', 'community_likes', 'community_posts'] as $tbl) {
            if (DB::getSchemaBuilder()->hasTable($tbl)) {
                DB::table($tbl)->where('user_id', $userId)->delete();
            }
        }

        DB::table('users')->where('id', $userId)->delete();
    }

    public function resetPin(Request $request, User $user)
    {
        $request->validate(['pin' => 'required|digits:4']);

        $user->update([
            'password'   => Hash::make($request->pin),
            'wallet_pin' => Hash::make($request->pin),
        ]);
        $user->tokens()->delete();

        return back()->with('success', 'PIN reset successfully. User will need to log in again.');
    }

    // API methods for datatables
    public function apiIndex(Request $request)
    {
        $users = User::with('role')
            ->when($request->role, fn($q) => $q->whereHas('role', fn($r) => $r->where('slug', $request->role)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($s) =>
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            ))
            ->paginate($request->per_page ?? 15);

        return $this->paginated($users, fn($u) => [
            'id'     => $u->id,
            'name'   => $u->name,
            'phone'  => $u->phone,
            'email'  => $u->email,
            'role'   => $u->role?->name,
            'status' => $u->status,
            'created_at' => $u->created_at->toDateString(),
        ]);
    }
}
