<?php

namespace App\Console\Commands;

use App\Jobs\EmbedPostJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill: embed all approved posts that don't have an embedding yet.
 * Run: php artisan posts:embed-backfill [--limit=500]
 */
class EmbedExistingPosts extends Command
{
    protected $signature   = 'posts:embed-backfill {--limit=200}';
    protected $description = 'Dispatch EmbedPostJob for posts missing embeddings';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $ids = DB::table('community_posts')
            ->where('moderation_status', 'approved')
            ->whereNull('embedding')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('All posts already have embeddings.');
            return;
        }

        // Stagger jobs 2 seconds apart to avoid OpenAI rate limits
        foreach ($ids as $i => $id) {
            EmbedPostJob::dispatch($id)->onQueue('default')->delay(now()->addSeconds($i * 2));
        }

        $this->info("Dispatched {$ids->count()} EmbedPostJob(s) (staggered 2s apart). Monitor with: php artisan queue:work");
    }
}
