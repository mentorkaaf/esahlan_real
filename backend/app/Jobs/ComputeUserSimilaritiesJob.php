<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Collaborative filtering — computes user similarity scores from shared engagement.
 *
 * Algorithm: Jaccard similarity on the set of posts each user engaged with
 * (liked, saved, commented) in the last 30 days. Runs weekly via scheduler.
 *
 * Output: user_similarities table — top-10 similar users per user, scored 0-1.
 */
class ComputeUserSimilaritiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function handle(): void
    {
        Log::info('[CollabFilter] Starting similarity computation');

        // Fetch all significant engagements (last 30 days, strong signals only)
        $engagements = DB::table('feed_interactions')
            ->whereIn('type', ['like', 'save', 'comment'])
            ->where('created_at', '>', now()->subDays(30))
            ->select('user_id', 'post_id')
            ->get();

        if ($engagements->isEmpty()) {
            Log::info('[CollabFilter] No engagements found, skipping');
            return;
        }

        // Build inverted index: post_id → [user_ids]
        $postUsers = [];
        $userPosts = [];
        foreach ($engagements as $e) {
            $postUsers[$e->post_id][] = $e->user_id;
            $userPosts[$e->user_id][] = $e->post_id;
        }

        $rows = [];
        $users = array_keys($userPosts);

        foreach ($users as $i => $userA) {
            $setA = array_flip($userPosts[$userA]);
            $topSimilar = [];

            foreach ($users as $userB) {
                if ($userA === $userB) continue;
                $setB = array_flip($userPosts[$userB]);

                $intersection = count(array_intersect_key($setA, $setB));
                if ($intersection === 0) continue;

                $union = count($setA) + count($setB) - $intersection;
                $jaccard = $union > 0 ? $intersection / $union : 0;

                if ($jaccard > 0.05) { // minimum threshold
                    $topSimilar[$userB] = $jaccard;
                }
            }

            arsort($topSimilar);
            $top10 = array_slice($topSimilar, 0, 10, true);

            foreach ($top10 as $userB => $score) {
                $rows[] = [
                    'user_a'      => $userA,
                    'user_b'      => $userB,
                    'score'       => round($score, 4),
                    'computed_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            // Replace all similarities in bulk
            DB::table('user_similarities')->truncate();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('user_similarities')->insert($chunk);
            }
            Log::info('[CollabFilter] Computed ' . count($rows) . ' similarity pairs for ' . count($users) . ' users');
        }
    }
}
