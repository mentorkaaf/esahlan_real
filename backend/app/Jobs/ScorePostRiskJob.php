<?php
namespace App\Jobs;

use App\Services\AutoRestrictService;
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
            $newStatus = $finalScore >= 0.85 ? 'blocked' : 'pending';
            DB::table('community_posts')->where('id', $this->postId)->update([
                'moderation_status' => $newStatus,
                'moderation_score'  => $finalScore,
                'updated_at'        => now(),
            ]);

            // AI-blocked posts: auto-issue strike + evaluate restriction
            if ($newStatus === 'blocked') {
                $strikeSeverity = $finalScore >= 0.95 ? 'critical' : 'high';
                DB::table('ts_strikes')->insertOrIgnore([
                    'user_id'       => $this->userId,
                    'violation_type'=> 'ai_flagged',
                    'severity'      => $strikeSeverity,
                    'points'        => $finalScore >= 0.95 ? 3 : 2,
                    'reason'        => 'AI risk score: ' . round($finalScore, 3),
                    'content_type'  => 'App\\Models\\CommunityPost',
                    'content_id'    => $this->postId,
                    'issued_by'     => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                AutoRestrictService::evaluate($this->userId, null, $strikeSeverity);
            }

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
