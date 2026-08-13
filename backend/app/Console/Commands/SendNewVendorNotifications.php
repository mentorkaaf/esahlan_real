<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\FcmService;

class SendNewVendorNotifications extends Command
{
    protected $signature = 'marketing:new-vendor
                            {--vendor= : Send notification for a specific vendor ID only}
                            {--dry-run : Show what would be sent without actually sending}';

    protected $description = 'Notify district users when a new vendor joins eSahlan nearby';

    private const SLUG = 'new_vendor_district';

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $vendorId = $this->option('vendor');

        $template = DB::table('auto_notification_templates')->where('slug', self::SLUG)->first();

        if ($template && !$template->is_active) {
            $this->info('new_vendor_district notifications are DISABLED by admin.');
            return 0;
        }

        $language  = $template?->language ?? 'en';
        $titleTpl  = ($language === 'so' && $template?->title_so)
            ? $template->title_so
            : ($template?->title_template ?? '🏪 New {module} opened near you!');
        $bodyTpl   = ($language === 'so' && $template?->body_so)
            ? $template->body_so
            : ($template?->body_template ?? '{store_name} just joined eSahlan in {district}. Check them out!');

        try {
            $query = DB::table('vendors as v')
                ->leftJoin('modules as m', 'm.id', '=', 'v.module_id')
                ->select(
                    'v.id', 'v.name as store_name', 'v.district_id',
                    'v.logo', 'm.name as module_name',
                )
                ->where('v.is_approved', 1)
                ->where('v.updated_at', '>=', now()->subHour());

            if ($vendorId) {
                $query->where('v.id', $vendorId);
            }

            $vendors = $query->get();
        } catch (\Throwable $e) {
            Log::warning('[NewVendorNotif] Could not query vendors: ' . $e->getMessage());
            $this->error('Failed to query vendors: ' . $e->getMessage());
            return 0;
        }

        if ($vendors->isEmpty()) {
            $this->info('No new vendors found in the last hour.');
            return 0;
        }

        $this->info("Found {$vendors->count()} new vendor(s).");
        $totalSent = 0;

        foreach ($vendors as $vendor) {
            $districtName = 'your area';
            try {
                $district = DB::table('districts')->where('id', $vendor->district_id)->first();
                if ($district) $districtName = $district->name ?? $districtName;
            } catch (\Throwable) {}

            $moduleName = $vendor->module_name ?? 'Store';
            $replace = [
                '{store_name}' => $vendor->store_name ?? 'A new store',
                '{module}'     => $moduleName,
                '{district}'   => $districtName,
            ];
            $title = str_replace(array_keys($replace), array_values($replace), $titleTpl);
            $body  = str_replace(array_keys($replace), array_values($replace), $bodyTpl);
            $data  = [
                'type'      => 'new_vendor',
                'vendor_id' => (string) $vendor->id,
                'module'    => strtolower(str_replace(' ', '', $moduleName)),
                'deep_link' => '/vendor/' . $vendor->id,
            ];

            try {
                $users = User::where('district_id', $vendor->district_id)
                    ->whereNotNull('fcm_token')->where('fcm_token', '!=', '')
                    ->where('status', '!=', 'banned')
                    ->select('id', 'fcm_token')->get();
            } catch (\Throwable) {
                $users = User::whereNotNull('fcm_token')->where('fcm_token', '!=', '')
                    ->where('status', '!=', 'banned')
                    ->select('id', 'fcm_token')->limit(500)->get();
            }

            if ($users->isEmpty()) {
                $this->line("  Vendor #{$vendor->id}: no users in district.");
                continue;
            }

            $sent = 0;
            foreach ($users as $user) {
                $cacheKey = "new_vendor:{$vendor->id}:{$user->id}";
                if (Cache::has($cacheKey)) continue;

                if ($dryRun) {
                    $this->line("    [DRY] → user#{$user->id} | {$title}");
                } else {
                    $ok = FcmService::sendToToken(
                        fcmToken:  $user->fcm_token,
                        title:     $title,
                        body:      $body,
                        data:      $data,
                        imageUrl:  $vendor->logo ?? null,
                        channelId: $template?->channel_id ?? 'esahlan_promo',
                    );
                    if ($ok) { Cache::put($cacheKey, 1, 72 * 3600); $sent++; }
                }
            }

            $totalSent += $sent;
            $this->info("  Vendor #{$vendor->id}: sent {$sent}" . ($dryRun ? ' [DRY]' : ''));

            if (!$dryRun && $sent > 0) {
                DB::table('auto_notification_logs')->insert([
                    'template_slug'  => self::SLUG,
                    'reference_id'   => $vendor->id,
                    'reference_type' => 'vendor',
                    'title'          => $title,
                    'body'           => $body,
                    'sent_count'     => $sent,
                    'created_at'     => now(),
                ]);
            }
        }

        if (!$dryRun) {
            DB::table('auto_notification_templates')
                ->where('slug', self::SLUG)->update(['last_sent_at' => now()]);
        }

        $this->info("Done. Total sent: {$totalSent}");
        return 0;
    }
}
