<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalUser;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGlobalPushNotificationsController extends Controller
{
    public function index()
    {
        $logs = DB::table('global_push_logs')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $stats = [
            'total_users'  => GlobalUser::count(),
            'with_token'   => GlobalUser::whereNotNull('fcm_token')->count(),
            'sent_today'   => DB::table('global_push_logs')->whereDate('created_at', today())->sum('sent_count'),
        ];

        return view('admin.global.push-notifications.index', compact('logs', 'stats'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'title'    => 'required|string|max:100',
            'body'     => 'required|string|max:500',
            'target'   => 'required|in:all,segment',
            'image_url'=> 'nullable|url',
            'click_url'=> 'nullable|url',
        ]);

        // Get FCM tokens for global users
        $query = GlobalUser::whereNotNull('fcm_token');

        if ($request->target === 'segment' && $request->filled('country')) {
            // Filter by country via their orders
            $query->whereHas('orders', fn($q) => $q->where('shipping_country', $request->country));
        }

        $tokens = $query->pluck('fcm_token')->toArray();

        if (empty($tokens)) {
            return back()->with('error', 'No users with push tokens found.');
        }

        try {
            $fcm  = new FcmService();
            $sent = 0;
            $data = array_filter([
                'click_url' => $request->click_url,
                'image'     => $request->image_url,
            ]);

            // Send in batches of 500
            foreach (array_chunk($tokens, 500) as $batch) {
                $fcm->sendToMultiple(
                    $batch,
                    $request->title,
                    $request->body,
                    $data,
                    $request->image_url
                );
                $sent += count($batch);
            }

            // Log it
            DB::table('global_push_logs')->insert([
                'title'      => $request->title,
                'body'       => $request->body,
                'target'     => $request->target,
                'sent_count' => $sent,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('success', "Push notification sent to {$sent} users.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }
}
