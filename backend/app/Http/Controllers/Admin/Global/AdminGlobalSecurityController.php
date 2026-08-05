<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGlobalSecurityController extends Controller
{
    public function index(Request $request)
    {
        // Security logs
        $logs = DB::table('global_security_logs')
            ->leftJoin('global_users', 'global_security_logs.global_user_id', '=', 'global_users.id')
            ->select('global_security_logs.*', 'global_users.name as user_name', 'global_users.email as user_email')
            ->orderByDesc('global_security_logs.created_at')
            ->limit(50)
            ->get();

        // Blocked IPs
        $blockedIps = DB::table('global_blocked_ips')->orderByDesc('created_at')->get();

        // Suspicious users (many failed payments or flagged orders)
        $suspiciousUsers = GlobalUser::where('is_banned', true)
            ->orWhereNotNull('banned_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Stats
        $stats = [
            'blocked_ips'    => DB::table('global_blocked_ips')->count(),
            'banned_users'   => GlobalUser::where('is_banned', true)->count(),
            'high_severity'  => DB::table('global_security_logs')->whereIn('severity', ['high','critical'])->whereDate('created_at', '>=', now()->subDays(7))->count(),
            'events_today'   => DB::table('global_security_logs')->whereDate('created_at', today())->count(),
        ];

        return view('admin.global.security.index', compact('logs','blockedIps','suspiciousUsers','stats'));
    }

    public function blockUser(Request $request, GlobalUser $user)
    {
        $request->validate(['reason' => 'nullable|string']);

        $user->update([
            'is_banned'   => true,
            'banned_at'   => now(),
            'ban_reason'  => $request->reason ?? 'Blocked by admin',
        ]);

        DB::table('global_security_logs')->insert([
            'global_user_id' => $user->id,
            'event'          => 'user_banned',
            'severity'       => 'high',
            'metadata'       => json_encode(['reason' => $request->reason, 'admin' => auth()->user()?->name]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return back()->with('success', "User \"{$user->email}\" blocked.");
    }

    public function unblockUser(GlobalUser $user)
    {
        $user->update(['is_banned' => false, 'banned_at' => null, 'ban_reason' => null]);
        return back()->with('success', "User \"{$user->email}\" unblocked.");
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address'    => 'required|ip',
            'reason'        => 'nullable|string',
            'blocked_until' => 'nullable|date',
        ]);

        DB::table('global_blocked_ips')->updateOrInsert(
            ['ip_address' => $request->ip_address],
            [
                'reason'        => $request->reason,
                'blocked_until' => $request->blocked_until,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );

        return back()->with('success', "IP {$request->ip_address} blocked.");
    }
}
