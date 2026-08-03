<?php

namespace App\Jobs;

use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Generates and stores an OpenAI embedding for one post.
 * Dispatched after post creation/update (caption or hashtags changed).
 */
class EmbedPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 30;

    public function __construct(private readonly int $postId) {}

    public function handle(): void
    {
        $embedding = EmbeddingService::embedPost($this->postId);
        if ($embedding === null) return;

        DB::table('community_posts')
            ->where('id', $this->postId)
            ->update([
                'embedding'            => json_encode($embedding),
                'embedding_updated_at' => now(),
            ]);
    }
}
