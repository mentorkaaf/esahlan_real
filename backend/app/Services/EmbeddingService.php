<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    // text-embedding-3-small: 1536 dimensions, cheapest, great quality
    private const MODEL = 'text-embedding-3-small';
    private const MAX_TOKENS = 512; // cap input to keep cost minimal

    /**
     * Get embedding vector for a post.
     * Returns array of 1536 floats, or null on failure.
     */
    public static function embedPost(int $postId): ?array
    {
        $post = \DB::table('community_posts')
            ->leftJoin('community_hashtags', 'community_posts.id', '=', 'community_hashtags.post_id')
            ->where('community_posts.id', $postId)
            ->select('community_posts.content', \DB::raw('GROUP_CONCAT(community_hashtags.name SEPARATOR " ") as hashtags'))
            ->groupBy('community_posts.id', 'community_posts.content')
            ->first();

        if (!$post) return null;

        $text = self::buildText((string)($post->content ?? ''), (string)($post->hashtags ?? ''));
        if (empty($text)) return null;

        return self::embed($text);
    }

    /**
     * Get embedding vector for a user's interest profile.
     * Averages embeddings of posts the user liked/saved/commented recently.
     */
    public static function getUserInterestVector(int $userId): ?array
    {
        $embeddings = \DB::table('feed_interactions')
            ->join('community_posts', 'feed_interactions.post_id', '=', 'community_posts.id')
            ->where('feed_interactions.user_id', $userId)
            ->whereIn('feed_interactions.type', ['like', 'save', 'comment'])
            ->where('feed_interactions.created_at', '>=', now()->subDays(30))
            ->whereNotNull('community_posts.embedding')
            ->orderByDesc('feed_interactions.created_at')
            ->limit(50)
            ->pluck('community_posts.embedding')
            ->map(fn($e) => json_decode($e, true))
            ->filter()
            ->values();

        if ($embeddings->isEmpty()) return null;

        // Average the vectors
        $dim = count($embeddings[0]);
        $avg = array_fill(0, $dim, 0.0);
        foreach ($embeddings as $vec) {
            for ($i = 0; $i < $dim; $i++) $avg[$i] += $vec[$i];
        }
        $n = $embeddings->count();
        for ($i = 0; $i < $dim; $i++) $avg[$i] /= $n;

        return $avg;
    }

    /**
     * Cosine similarity between two vectors. Returns 0.0–1.0.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na == 0 || $nb == 0) return 0.0;
        return (float)($dot / (sqrt($na) * sqrt($nb)));
    }

    /**
     * Embed a raw text string. Returns float[] or null.
     */
    public static function embed(string $text): ?array
    {
        try {
            $text = self::truncate($text);
            $response = OpenAI::embeddings()->create([
                'model' => self::MODEL,
                'input' => $text,
            ]);
            return $response->embeddings[0]->embedding;
        } catch (\Throwable $e) {
            Log::warning('[EmbeddingService] embed failed: ' . $e->getMessage());
            return null;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function buildText(string $content, string $hashtags): string
    {
        $parts = [];
        if (!empty(trim($content)))   $parts[] = trim($content);
        if (!empty(trim($hashtags)))  $parts[] = trim($hashtags);
        return implode(' ', $parts);
    }

    private static function truncate(string $text): string
    {
        // Rough token estimate: 1 token ≈ 4 chars. Cap at MAX_TOKENS.
        $maxChars = self::MAX_TOKENS * 4;
        return mb_strlen($text) > $maxChars ? mb_substr($text, 0, $maxChars) : $text;
    }
}
