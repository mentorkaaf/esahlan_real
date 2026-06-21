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
