<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Jobs\TranscodeVideoJob;

class RetryStuckVideos extends Command
{
    protected $signature   = 'community:retry-stuck-videos {--dry-run : Show what would be retried without doing it}';
    protected $description = 'Re-dispatch TranscodeVideoJob for video posts stuck at video_ready=false';

    public function handle(): int
    {
        $stuck = CommunityPost::whereIn('type', ['video', 'reel'])
            ->where('video_ready', false)
            ->with(['media' => fn ($q) => $q->where('type', 'video')])
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck videos found.');
            return 0;
        }

        $this->info("Found {$stuck->count()} stuck video post(s):");

        foreach ($stuck as $post) {
            $media = $post->media->first();
            if (!$media) {
                $this->warn("  Post #{$post->id}: no video media — skipping");
                continue;
            }

            $this->line("  Post #{$post->id} | media #{$media->id} | status: {$media->transcoding_status}");

            if ($this->option('dry-run')) continue;

            // Reset status so the job doesn't find a stale 'processing' state
            $media->update(['transcoding_status' => 'pending', 'transcoding_progress' => 0]);

            // Derive the storage path from the stored URL (strips CDN prefix)
            $path = ltrim(parse_url($media->url, PHP_URL_PATH), '/');
            $path = preg_replace('#^storage/#', '', $path);

            TranscodeVideoJob::dispatch($media->id, $path, $post->user_id);
            $this->info("  → Re-dispatched TranscodeVideoJob for post #{$post->id}");
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode — no jobs were dispatched.');
        } else {
            $this->info('Done. Run `php artisan queue:work --queue=transcoding` if the worker is not running.');
        }

        return 0;
    }
}
