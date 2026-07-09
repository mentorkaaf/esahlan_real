<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Multi-signal AI content risk scorer.
 *
 * Aggregates image (Sightengine), text toxicity, user behaviour history,
 * and context signals into a single 0-1 risk score, then persists it to
 * ai_content_scores so the Trust & Safety dashboard can show breakdowns.
 */
class ContentRiskScorer
{
    // ── Signal weights (must sum to 1.0) ──────────────────────────────────────
    private const W_IMAGE    = 0.45;
    private const W_TEXT     = 0.25;
    private const W_BEHAVIOR = 0.20;
    private const W_CONTEXT  = 0.10;

    // Toxic keywords (Somali + English) — extend as needed
    private const TOXIC_KEYWORDS = [
        // English
        'fuck','shit','bitch','nigger','faggot','retard','kill yourself','kys',
        'rape','terrorist','bomb','jihad','nude','naked','porn','sex','xxx',
        // Somali
        'wasakh','xoolo','gabar fuley','gaalad','cadaan gaajo',
    ];

    private const TOXIC_PATTERNS = [
        '/\b(onlyfans\.com|pornhub|xvideos|xnxx)\b/i',
        '/\b(t\.me|telegram)\s*[\/:]?\s*\w+/i', // telegram spam links
        '/(\d[\s\-]{0,2}){10,}/',               // phone number spam
        '/buy\s+(followers|likes|views)/i',
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Score a post. Returns the final 0-1 risk score and persists detailed
     * breakdown to ai_content_scores.
     */
    public static function scorePost(int $postId, int $userId, float $imageScore = 0, string $textContent = ''): float
    {
        $text    = self::scoreText($textContent);
        $behavior = self::scoreBehavior($userId);
        $context  = self::scoreContext($userId, $postId);

        // Weighted aggregate
        $final = ($imageScore  * self::W_IMAGE)
               + ($text        * self::W_TEXT)
               + ($behavior    * self::W_BEHAVIOR)
               + ($context     * self::W_CONTEXT);

        $final = min(1.0, $final);

        // Escalate: if any single signal is critical, lift floor
        if ($imageScore >= 0.95 || $text >= 0.95) {
            $final = max($final, 0.90);
        }

        $signals = [
            'image_score'    => round($imageScore, 4),
            'text_score'     => round($text, 4),
            'behavior_score' => round($behavior, 4),
            'context_score'  => round($context, 4),
            'final_score'    => round($final, 4),
        ];

        // Persist — upsert by scoreable
        DB::table('ai_content_scores')->updateOrInsert(
            ['scoreable_type' => 'App\\Models\\CommunityPost', 'scoreable_id' => $postId],
            array_merge($signals, [
                'model_version' => 'v1',
                'created_at'    => now(),
                'updated_at'    => now(),
            ])
        );

        Log::info('ContentRiskScorer', array_merge(['post_id' => $postId, 'user_id' => $userId], $signals));

        return $final;
    }

    // ── Text toxicity ─────────────────────────────────────────────────────────

    public static function scoreText(string $text): float
    {
        if (empty($text)) return 0.0;

        $lower  = mb_strtolower($text);
        $score  = 0.0;
        $words  = preg_split('/\s+/', $lower);
        $wCount = max(1, count($words));

        // Keyword hits — weighted by density
        $hits = 0;
        foreach (self::TOXIC_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) $hits++;
        }
        $score += min(0.9, ($hits / $wCount) * 8.0);

        // Regex pattern hits
        foreach (self::TOXIC_PATTERNS as $pat) {
            if (preg_match($pat, $text)) $score += 0.35;
        }

        // ALL-CAPS ratio (shouting / aggression signal)
        $upperCount = preg_match_all('/[A-Z]/', $text);
        $letterCount = preg_match_all('/[A-Za-z]/', $text);
        if ($letterCount > 10 && ($upperCount / max(1, $letterCount)) > 0.6) {
            $score += 0.15;
        }

        // Repeated characters (bypassing: fuuuuck, s3x)
        if (preg_match('/(.)\1{4,}/', $lower)) $score += 0.10;

        // Leet-speak substitution check (s3x, p0rn, @ss)
        $leet = strtr($lower, ['3'=>'e','0'=>'o','1'=>'i','@'=>'a','$'=>'s','!'=>'i']);
        foreach (['sex','porn','ass','nude'] as $kw) {
            if (str_contains($leet, $kw)) $score += 0.25;
        }

        return min(1.0, $score);
    }

    // ── User behaviour history ────────────────────────────────────────────────

    public static function scoreBehavior(int $userId): float
    {
        // Active strike points (normalised to 0-1, cap at 10 pts = 1.0)
        $activePoints = DB::table('ts_strikes')
            ->where('user_id', $userId)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('points');

        // Prior blocked posts (recent 30 days)
        $blockedRecent = DB::table('community_posts')
            ->where('user_id', $userId)
            ->where('moderation_status', 'blocked')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Reports received (recent 7 days)
        $reportsReceived = DB::table('community_reports')
            ->where('reportable_type', 'App\\Models\\CommunityPost')
            ->whereIn('reportable_id', fn($q) => $q->select('id')->from('community_posts')->where('user_id', $userId))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $score = min(1.0,
            ($activePoints   / 10.0) * 0.50 +
            ($blockedRecent  /  5.0) * 0.30 +
            ($reportsReceived / 5.0) * 0.20
        );

        return $score;
    }

    // ── Context / account signals ─────────────────────────────────────────────

    public static function scoreContext(int $userId, int $postId): float
    {
        $user = DB::table('users')->where('id', $userId)->first(['created_at','email_verified_at']);
        if (!$user) return 0.5;

        $score = 0.0;

        // New account (< 7 days) → higher suspicion
        $ageInDays = now()->diffInDays($user->created_at);
        if ($ageInDays < 1)  $score += 0.50;
        elseif ($ageInDays < 7)  $score += 0.30;
        elseif ($ageInDays < 30) $score += 0.10;

        // Unverified email
        if (!$user->email_verified_at) $score += 0.20;

        // Posting velocity (many posts in last hour)
        $postsThisHour = DB::table('community_posts')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($postsThisHour >= 10) $score += 0.30;
        elseif ($postsThisHour >= 5) $score += 0.15;

        // No community profile (ghost account)
        $hasProfile = DB::table('community_profiles')->where('user_id', $userId)->exists();
        if (!$hasProfile) $score += 0.10;

        return min(1.0, $score);
    }
}
