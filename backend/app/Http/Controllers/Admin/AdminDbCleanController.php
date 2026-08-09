<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\DbCleanLog;

class AdminDbCleanController extends Controller
{
    /**
     * Tables the admin is ALLOWED to clean.
     * Grouped by category for UI display.
     */
    public const ALLOWED = [
        'Orders & Delivery' => [
            'orders', 'order_details', 'delivery_histories',
            'order_transactions', 'dispatch_requests',
        ],
        'Cart & Sessions' => [
            'carts', 'cart_details', 'guest_carts',
        ],
        'Notifications' => [
            'push_notification_logs', 'notification_logs',
            'order_notifications',
        ],
        'Community' => [
            'community_posts', 'community_post_media',
            'community_stories', 'community_chats', 'community_messages',
            'community_notifications', 'feed_seen_posts', 'feed_interactions',
        ],
        'Analytics & Cache' => [
            'user_interests', 'post_scores',
            'activity_logs', 'admin_logs',
        ],
        'Finance' => [
            'account_transactions', 'wallet_transactions',
            'loyalty_transactions',
        ],
        'Reviews & Reports' => [
            'reviews', 'review_replies', 'reported_posts',
        ],
        'Other' => [
            'search_histories', 'fcm_tokens_log',
            'failed_jobs', 'job_batches',
        ],
    ];

    /**
     * HARD-BLOCKED — never touch, ever.
     */
    private const BLOCKED = [
        'users', 'vendors', 'deliverymen', 'admins',
        'migrations', 'personal_access_tokens',
        'business_settings', 'module_configs',
        'categories', 'products', 'add_ons', 'addon_categories',
        'zones', 'currencies',
        'db_clean_logs',
    ];

    // ── GET /admin/db-clean ────────────────────────────────────────────────────
    public function index()
    {
        $existingTables = $this->getExistingTables();
        $groups         = [];
        $totalRows      = 0;

        foreach (self::ALLOWED as $group => $tables) {
            $items = [];
            foreach ($tables as $table) {
                if (!in_array($table, $existingTables)) continue;
                $count = (int) DB::table($table)->count();
                $totalRows += $count;
                $items[] = ['table' => $table, 'count' => $count];
            }
            if (!empty($items)) {
                $groups[$group] = $items;
            }
        }

        $logs = DbCleanLog::with('admin')->latest()->limit(10)->get();

        return view('admin.db_clean.index', compact('groups', 'totalRows', 'logs'));
    }

    // ── POST /admin/db-clean/preview ──────────────────────────────────────────
    public function preview(Request $request)
    {
        $tables  = $this->validateTables($request->input('tables', []));
        $preview = [];
        foreach ($tables as $t) {
            $preview[$t] = (int) DB::table($t)->count();
        }
        return response()->json(['preview' => $preview, 'total' => array_sum($preview)]);
    }

    // ── POST /admin/db-clean/run ──────────────────────────────────────────────
    public function run(Request $request)
    {
        $tables = $this->validateTables($request->input('tables', []));
        if (empty($tables)) {
            return back()->with('error', 'No valid tables selected.');
        }

        $rowsDeleted = [];
        $errors      = [];

        foreach ($tables as $table) {
            try {
                DB::beginTransaction();
                $count = (int) DB::table($table)->count();
                DB::statement("SET FOREIGN_KEY_CHECKS=0;");
                DB::table($table)->truncate();
                DB::statement("SET FOREIGN_KEY_CHECKS=1;");
                DB::commit();
                $rowsDeleted[$table] = $count;
            } catch (\Throwable $e) {
                DB::rollBack();
                DB::statement("SET FOREIGN_KEY_CHECKS=1;");
                $errors[] = "$table: " . $e->getMessage();
            }
        }

        // Log the action
        DbCleanLog::create([
            'admin_id'     => Auth::id(),
            'tables_cleaned' => array_keys($rowsDeleted),
            'rows_deleted'   => $rowsDeleted,
            'status'         => empty($errors) ? 'completed' : 'partial',
            'notes'          => empty($errors) ? null : implode('; ', $errors),
        ]);

        $totalDeleted = array_sum($rowsDeleted);
        $msg = "✅ Cleaned " . count($rowsDeleted) . " table(s), deleted $totalDeleted rows.";
        if (!empty($errors)) {
            $msg .= " ⚠️ " . count($errors) . " error(s): " . implode(', ', $errors);
        }

        return back()->with('success', $msg);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────
    private function validateTables(array $input): array
    {
        $flat    = collect(self::ALLOWED)->flatten()->toArray();
        $blocked = self::BLOCKED;

        return array_values(array_filter($input, function ($t) use ($flat, $blocked) {
            return in_array($t, $flat) && !in_array($t, $blocked);
        }));
    }

    private function getExistingTables(): array
    {
        return array_map(
            fn($r) => reset($r),
            DB::select('SHOW TABLES')
        );
    }
}
