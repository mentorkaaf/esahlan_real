<?php
namespace App\Http\Controllers\Admin\Global;
use App\Http\Controllers\Controller;
use App\Models\Global\GlobalUser;
use App\Models\Global\GlobalOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminGlobalUsersController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalUser::withCount('orders');
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('name','like',"%$s%")
                  ->orWhere('email','like',"%$s%")
                  ->orWhere('phone','like',"%$s%");
            });
        }
        if ($request->filled('status')) {
            if ($request->status === 'banned')   $query->where('is_banned', true);
            if ($request->status === 'active')   $query->where('is_banned', false)->where('is_active', true);
            if ($request->status === 'inactive') $query->where('is_active', false);
        }
        if ($request->filled('country')) $query->where('country_code', $request->country);

        $users = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        $stats = [
            'total'    => GlobalUser::count(),
            'active'   => GlobalUser::where('is_active', true)->where('is_banned', false)->count(),
            'banned'   => GlobalUser::where('is_banned', true)->count(),
            'with_fcm' => GlobalUser::whereNotNull('fcm_token')->count(),
        ];
        return view('admin.global.users.index', compact('users','stats'));
    }

    public function show(GlobalUser $user)
    {
        $user->loadCount('orders');
        $orders = GlobalOrder::where('global_user_id', $user->id)
            ->orderByDesc('created_at')->limit(10)->get();
        $totalSpent = GlobalOrder::where('global_user_id', $user->id)
            ->where('payment_status','paid')->sum('total');
        return view('admin.global.users.show', compact('user','orders','totalSpent'));
    }

    public function ban(Request $request, GlobalUser $user)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        $user->update([
            'is_banned'  => true,
            'banned_at'  => now(),
            'ban_reason' => $request->reason,
        ]);
        return back()->with('success', 'User banned.');
    }

    public function unban(GlobalUser $user)
    {
        $user->update(['is_banned' => false, 'banned_at' => null, 'ban_reason' => null]);
        return back()->with('success', 'User unbanned.');
    }

    public function destroy(GlobalUser $user)
    {
        $user->delete();
        return redirect()->route('admin.global.users.index')->with('success', 'User deleted.');
    }

    public function sendNotification(Request $request, GlobalUser $user)
    {
        $request->validate(['title'=>'required|string','body'=>'required|string']);
        if ($user->fcm_token) {
            \App\Services\FcmService::sendToToken($user->fcm_token, $request->title, $request->body, ['type'=>'admin_message']);
            return back()->with('success', 'Notification sent.');
        }
        return back()->with('error', 'User has no FCM token.');
    }

    /** POST /admin/global/users/bulk — bulk delete */
    public function bulk(Request $request)
    {
        $request->validate([
            'action' => 'required|in:delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $ids   = $request->ids;
        $count = GlobalUser::whereIn('id', $ids)->count();
        GlobalUser::whereIn('id', $ids)->delete();

        return back()->with('success', "$count user(s) deleted.");
    }
}
