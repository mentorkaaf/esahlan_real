<?php
namespace App\Jobs;

use App\Services\ContentRiskScorer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ScorePostRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private int    $postId,
        private int    $userId,
        private string $textContent,
        private float  $imageScore = 0.0,
    ) {}

    public function handle(): void
    {
        $finalScore = ContentRiskScorer::scorePost(
            $this->postId,
            $this->userId,
            $this->imageScore,
            $this->textContent,
        );

        // Escalate moderation_status based on AI score
        $current = DB::table('community_posts')
            ->where('id', $this->postId)
            ->value('moderation_status');

        // Only escalate — never downgrade what ContentModerationService already decided
        if ($current === 'approved' && $finalScore >= 0.50) {
            DB::table('community_posts')->where('id', $this->postId)->update([
                'moderation_status' => $finalScore >= 0.85 ? 'blocked' : 'pending',
                'moderation_score'  => $finalScore,
                'updated_at'        => now(),
            ]);

            if ($finalScore >= 0.50) {
                DB::table('community_reports')->insertOrIgnore([
                    'reporter_id'     => $this->userId,
                    'reportable_type' => 'App\\Models\\CommunityPost',
                    'reportable_id'   => $this->postId,
                    'reason'          => 'auto_moderation',
                    'description'     => 'AI risk score: ' . round($finalScore, 3),
                    'status'          => 'pending',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
}
