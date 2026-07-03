<?php

namespace App\Jobs;

use App\Models\CommunityPost;
use App\Services\ContentModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ModeratePostMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        $post = CommunityPost::find($this->postId);
        if (!$post || $post->moderation_status === 'blocked') return;

        $mediaUrls = $post->media()->pluck('url')->toArray();
        if (empty($mediaUrls)) {
            $post->update(['moderation_status' => 'approved']);
            return;
        }

        $settings = ContentModerationService::getSettings();
        $blockThreshold  = $settings['block_threshold']  ?? 0.85;
        $reviewThreshold = $settings['review_threshold'] ?? 0.60;

        $maxScore = 0;
        $reason   = '';

        foreach ($mediaUrls as $url) {
            $score = ContentModerationService::scanMediaUrl($url);
            if ($score > $maxScore) {
                $maxScore = $score;
                $reason   = "Media score: {$score}";
            }
        }

        if ($maxScore >= $blockThreshold) {
            $post->update(['moderation_status' => 'blocked', 'moderation_score' => $maxScore]);
            DB::table('community_reports')->insert([
                'reportable_type' => 'App\Models\CommunityPost',
                'reportable_id'   => $post->id,
                'reporter_id'     => $post->user_id,
                'reason'          => 'auto_moderation',
                'description'     => 'Auto-blocked: ' . $reason,
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            Log::info("[Moderation] Post {$post->id} AUTO-BLOCKED score={$maxScore}");
        } elseif ($maxScore >= $reviewThreshold) {
            $post->update(['moderation_status' => 'pending', 'moderation_score' => $maxScore]);
            DB::table('community_reports')->insert([
                'reportable_type' => 'App\Models\CommunityPost',
                'reportable_id'   => $post->id,
                'reporter_id'     => $post->user_id,
                'reason'          => 'auto_moderation',
                'description'     => 'Auto-flagged: ' . $reason,
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            Log::info("[Moderation] Post {$post->id} FLAGGED FOR REVIEW score={$maxScore}");
        } else {
            $post->update(['moderation_status' => 'approved', 'moderation_score' => $maxScore]);
        }
    }

    public function failed(\Throwable $e): void
    {
        // On job failure, approve so post isn't stuck in pending forever
        CommunityPost::find($this->postId)?->update(['moderation_status' => 'approved']);
        Log::error("[Moderation] Job failed for post {$this->postId}: " . $e->getMessage());
    }
}
