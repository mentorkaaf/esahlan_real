<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Global\GlobalSetting;

class AdminAppLinkController extends Controller
{
    // ── Analytics Dashboard ───────────────────────────────────────────────────

    public function index(Request $request)
    {
        $range = $request->get('range', '7'); // days

        $since = now()->subDays((int) $range);

        // ── KPI Cards
        $total      = DB::table('app_link_clicks')->count();
        $today      = DB::table('app_link_clicks')->whereDate('created_at', today())->count();
        $thisWeek   = DB::table('app_link_clicks')->where('created_at', '>=', now()->startOfWeek())->count();
        $thisMonth  = DB::table('app_link_clicks')->where('created_at', '>=', now()->startOfMonth())->count();

        // ── Device breakdown
        $devices = DB::table('app_link_clicks')
            ->selectRaw('device_type, COUNT(*) as cnt')
            ->where('created_at', '>=', $since)
            ->groupBy('device_type')
            ->pluck('cnt', 'device_type');

        $androidCnt = $devices['android'] ?? 0;
        $iosCnt     = $devices['ios']     ?? 0;
        $desktopCnt = $devices['desktop'] ?? 0;
        $totalRange = $androidCnt + $iosCnt + $desktopCnt;

        // ── Daily chart (range days)
        $chart = collect(range((int)$range - 1, 0))->map(function ($i) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = DB::table('app_link_clicks')
                ->selectRaw('device_type, COUNT(*) as cnt')
                ->whereDate('created_at', $date)
                ->groupBy('device_type')
                ->get()
                ->pluck('cnt', 'device_type');
            return [
                'date'    => now()->subDays($i)->format('M j'),
                'android' => $row['android'] ?? 0,
                'ios'     => $row['ios']     ?? 0,
                'desktop' => $row['desktop'] ?? 0,
                'total'   => ($row['android'] ?? 0) + ($row['ios'] ?? 0) + ($row['desktop'] ?? 0),
            ];
        });

        // ── Hourly today
        $hourly = collect(range(0, 23))->map(function ($h) {
            return [
                'hour' => $h,
                'cnt'  => DB::table('app_link_clicks')
                    ->whereDate('created_at', today())
                    ->whereRaw('HOUR(created_at) = ?', [$h])
                    ->count(),
            ];
        });

        // ── Top countries
        $countries = DB::table('app_link_clicks')
            ->selectRaw('country_name, country_code, COUNT(*) as cnt')
            ->whereNotNull('country_name')
            ->where('created_at', '>=', $since)
            ->groupBy('country_name', 'country_code')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ── Recent clicks
        $recent = DB::table('app_link_clicks')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // ── Current store URLs
        $iosUrl     = GlobalSetting::get('app_link_ios_url',     'https://apps.apple.com/app/id000000000');
        $androidUrl = GlobalSetting::get('app_link_android_url', 'https://play.google.com/store/apps/details?id=com.esahlan.app');

        return view('admin.app-link', compact(
            'total', 'today', 'thisWeek', 'thisMonth',
            'androidCnt', 'iosCnt', 'desktopCnt', 'totalRange',
            'chart', 'hourly', 'countries', 'recent', 'range',
            'iosUrl', 'androidUrl'
        ));
    }

    // ── Save Store URLs ───────────────────────────────────────────────────────

    public function saveUrls(Request $request)
    {
        $request->validate([
            'ios_url'     => 'required|url',
            'android_url' => 'required|url',
        ]);

        GlobalSetting::set('app_link_ios_url',     $request->ios_url);
        GlobalSetting::set('app_link_android_url', $request->android_url);

        return back()->with('success', 'Store URLs updated. The download page will use these links immediately.');
    }

    // ── Clear old data ────────────────────────────────────────────────────────

    public function clearData(Request $request)
    {
        $days = (int) $request->get('older_than', 90);
        $deleted = DB::table('app_link_clicks')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        return back()->with('success', "Deleted {$deleted} click records older than {$days} days.");
    }

    // ── JSON API — for charts (AJAX refresh) ─────────────────────────────────

    public function chartData(Request $request)
    {
        $range = (int) $request->get('range', 7);
        $since = now()->subDays($range);

        $data = collect(range($range - 1, 0))->map(function ($i) {
            $date = now()->subDays($i)->format('Y-m-d');
            $row  = DB::table('app_link_clicks')
                ->selectRaw('device_type, COUNT(*) as cnt')
                ->whereDate('created_at', $date)
                ->groupBy('device_type')
                ->get()->pluck('cnt', 'device_type');
            return [
                'date'    => now()->subDays($i)->format('M j'),
                'android' => $row['android'] ?? 0,
                'ios'     => $row['ios']     ?? 0,
                'desktop' => $row['desktop'] ?? 0,
            ];
        });

        return response()->json($data);
    }
}
