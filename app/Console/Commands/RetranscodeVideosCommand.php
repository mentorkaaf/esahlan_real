<?php

namespace App\Console\Commands;

use App\Models\CommunityPostMedia;
use App\Services\VideoProcessingService;
use Illuminate\Console\Command;

/**
 * Re-segments all existing HLS videos using the current HLS_SEGMENT value.
 * Safe to run while the site is live — skips in-progress videos.
 *
 * Usage:
 *   php artisan community:retranscode-videos          # all ready videos
 *   php artisan community:retranscode-videos --dry-run
 *   php artisan community:retranscode-videos --id=42  # single media record
 */
class RetranscodeVideosCommand extends Command
{
    protected $signature = 'community:retranscode-videos
                            {--dry-run : Show what would be done without touching files}
                            {--id=    : Re-transcode a single media ID only}';

    protected $description = 'Re-generate HLS segments for existing videos (use after changing HLS_SEGMENT)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $onlyId = $this->option('id');

        $query = CommunityPostMedia::where('type', 'video')
            ->whereIn('transcoding_status', ['ready', 'none'])
            ->whereNotNull('hls_url');

        if ($onlyId) {
            $query->where('id', (int) $onlyId);
        }

        $media = $query->get();

        if ($media->isEmpty()) {
            $this->info('No videos found to re-transcode.');
            return 0;
        }

        $this->info("Found {$media->count()} video(s) to re-transcode." . ($dryRun ? ' [DRY RUN]' : ''));

        $ok = 0;
        $fail = 0;

        $bar = $this->output->createProgressBar($media->count());
        $bar->start();

        foreach ($media as $m) {
            // Derive the optimized.mp4 storage path from the hls_url.
            // hls_url in DB = https://domain.com/hls/{dir}/{name}/hls/master.m3u8
            // optimized.mp4 = storage/app/public/{dir}/{name}/optimized.mp4
            $rawHlsUrl = $m->getRawOriginal('hls_url') ?? '';
            $optimizedPath = $this->resolveOptimizedPath($rawHlsUrl);

            if (!$optimizedPath) {
                $this->newLine();
                $this->warn("  [#{$m->id}] Cannot resolve path from hls_url: {$rawHlsUrl}");
                $fail++;
                $bar->advance();
                continue;
            }

            $fullPath = storage_path('app/public/' . $optimizedPath);
            if (!file_exists($fullPath)) {
                $this->newLine();
                $this->warn("  [#{$m->id}] optimized.mp4 missing: {$optimizedPath}");
                $fail++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [#{$m->id}] Would re-transcode: {$optimizedPath}");
                $ok++;
                $bar->advance();
                continue;
            }

            $result = VideoProcessingService::retranscodeHlsOnly($optimizedPath);

            if (isset($result['error'])) {
                $this->newLine();
                $this->error("  [#{$m->id}] Failed: {$result['error']}");
                $fail++;
            } else {
                $m->update(['hls_url' => $result['hls_url']]);
                $ok++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Success: {$ok}  Failed: {$fail}");

        return $fail > 0 ? 1 : 0;
    }

    /**
     * Extract the optimized.mp4 relative storage path from the stored hls_url.
     *
     * Stored hls_url patterns:
     *   https://esahlan.com/hls/community/videos/abc123/hls/master.m3u8
     *   /hls/community/videos/abc123/hls/master.m3u8
     */
    private function resolveOptimizedPath(string $hlsUrl): ?string
    {
        if (empty($hlsUrl)) return null;

        // Strip scheme+host if present
        $path = parse_url($hlsUrl, PHP_URL_PATH) ?? $hlsUrl;

        // Expect: /hls/{storagePath}/hls/master.m3u8
        if (!preg_match('#^/hls/(.+)/hls/master\.m3u8$#', $path, $m)) {
            return null;
        }

        // $m[1] = community/videos/abc123
        return $m[1] . '/optimized.mp4';
    }
}
