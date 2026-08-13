<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAlertSettingsController extends Controller
{
    /** Show the alert settings page */
    public function index()
    {
        $settings = DB::table('admin_alert_settings')
            ->orderByRaw("FIELD(category, 'orders','vendors','users','security','server')")
            ->orderBy('key')
            ->get()
            ->groupBy('category');

        $adminEmail = DB::table('settings')->where('key', 'admin_alert_email')->value('value') ?? '';

        $logs = DB::table('admin_alert_logs')
            ->orderByDesc('sent_at')
            ->limit(50)
            ->get();

        $stats = [
            'total_sent'   => DB::table('admin_alert_logs')->where('status', 'sent')->count(),
            'total_failed' => DB::table('admin_alert_logs')->where('status', 'failed')->count(),
            'today'        => DB::table('admin_alert_logs')->whereDate('sent_at', today())->count(),
        ];

        return view('admin.alerts.index', compact('settings', 'adminEmail', 'logs', 'stats'));
    }

    /** Toggle a single alert on/off (AJAX) */
    public function toggle(Request $request, string $key)
    {
        $setting = DB::table('admin_alert_settings')->where('key', $key)->first();
        if (!$setting) return response()->json(['error' => 'Not found'], 404);

        $newState = !$setting->is_enabled;
        DB::table('admin_alert_settings')->where('key', $key)->update([
            'is_enabled' => $newState,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'enabled' => $newState]);
    }

    /** Save admin email address */
    public function saveEmail(Request $request)
    {
        $request->validate(['admin_alert_email' => 'required|string|max:500']);

        $exists = DB::table('settings')->where('key', 'admin_alert_email')->exists();
        if ($exists) {
            DB::table('settings')->where('key', 'admin_alert_email')->update([
                'value'      => $request->admin_alert_email,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('settings')->insert([
                'key'        => 'admin_alert_email',
                'value'      => $request->admin_alert_email,
                'type'       => 'string',
                'group'      => 'alerts',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Alert email saved successfully.');
    }

    /** Send a test alert email */
    public function test(Request $request)
    {
        AdminAlertService::send('new_order', '✅ Test Alert — eSahlan Admin Emails Working!', [
            'Test Type'   => 'Manual Test',
            'Triggered By'=> 'Admin Panel',
            'Server'      => gethostname(),
            'Time'        => now()->format('d M Y H:i') . ' UTC',
            'Message'     => 'If you received this email, your admin alerts are configured correctly.',
        ]);

        return back()->with('success', 'Test alert sent! Check your inbox.');
    }

    /** Clear alert logs */
    public function clearLogs()
    {
        DB::table('admin_alert_logs')->truncate();
        return back()->with('success', 'Alert logs cleared.');
    }
}
