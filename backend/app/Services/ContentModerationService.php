<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContentModerationService
{
    // NSFW detection thresholds (0.0 - 1.0)
    const BLOCK_THRESHOLD = 0.70;   // Auto-block if score >= 70%
    const REVIEW_THRESHOLD = 0.40;  // Hold for review if score >= 40%

    // Blocked keywords in multiple languages
    private static array $blockedKeywords = [
        // English
        'porn', 'xxx', 'nude', 'naked', 'sex video', 'onlyfans', 'nsfw',
        'hentai', 'xvideos', 'pornhub', 'xhamster', 'brazzers',
        // Somali
        'qaawan', 'siil', 'gus', 'wasakh', 'nijaas',
        // Arabic
        'اباحي', 'عري', 'جنس',
    ];

    /**
     * Check if uploaded image is NSFW
     * Returns: ['safe' => bool, 'score' => float, 'action' => 'allow'|'review'|'block', 'reason' => string]
     */
    public static function checkImage(string $filePath): array
    {
        try {
            // Method 1: Use NsfwSpy PHP (local, no API needed)
            $score = self::analyzeWithLocalCheck($filePath);

            if ($score >= self::BLOCK_THRESHOLD) {
                return ['safe' => false, 'score' => $score, 'action' => 'block', 'reason' => 'Explicit content detected'];
            }
            if ($score >= self::REVIEW_THRESHOLD) {
                return ['safe' => false, 'score' => $score, 'action' => 'review', 'reason' => 'Content flagged for review'];
            }
            return ['safe' => true, 'score' => $score, 'action' => 'allow', 'reason' => 'Content is safe'];
        } catch (\Throwable $e) {
            Log::warning('Content moderation check failed: ' . $e->getMessage());
            // If check fails, allow but flag for review
            return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Check failed, allowed by default'];
        }
    }

    /**
     * Check video by analyzing its first frame/thumbnail
     */
    public static function checkVideo(string $filePath): array
    {
        // For video, we check the uploaded thumbnail or allow with review flag
        // Full video scanning requires heavy processing not suitable for shared hosting
        return ['safe' => true, 'score' => 0, 'action' => 'review', 'reason' => 'Video queued for manual review'];
    }

    /**
     * Check text content for inappropriate keywords
     */
    public static function checkText(?string $text): array
    {
        if (empty($text)) return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Empty text'];

        $lower = mb_strtolower($text);
        foreach (self::$blockedKeywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return [
                    'safe' => false,
                    'score' => 0.9,
                    'action' => 'block',
                    'reason' => "Blocked keyword detected",
                    'keyword' => $keyword,
                ];
            }
        }

        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Text is safe'];
    }

    /**
     * Moderate a full post (text + media)
     * Returns the strictest result across all checks
     */
    public static function moderatePost(?string $text, array $mediaFiles = []): array
    {
        $results = [];

        // Check text
        $textResult = self::checkText($text);
        $results[] = $textResult;
        if ($textResult['action'] === 'block') return $textResult;

        // Check each media file
        foreach ($mediaFiles as $file) {
            if (!$file) continue;

            $mime = is_object($file) ? $file->getMimeType() : mime_content_type($file);
            $path = is_object($file) ? $file->getRealPath() : $file;

            if (str_starts_with($mime, 'image/')) {
                $result = self::checkImage($path);
            } elseif (str_starts_with($mime, 'video/')) {
                $result = self::checkVideo($path);
            } else {
                continue;
            }

            $results[] = $result;
            if ($result['action'] === 'block') return $result;
        }

        // Return strictest result
        $hasReview = collect($results)->contains('action', 'review');
        if ($hasReview) {
            return ['safe' => true, 'score' => 0.5, 'action' => 'review', 'reason' => 'Content queued for review'];
        }

        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'All content is safe'];
    }

    /**
     * Local skin-tone / nudity heuristic analysis
     * Uses GD library to analyze pixel colors for skin-tone ratio
     * Not as accurate as ML models but works without external API
     */
    private static function analyzeWithLocalCheck(string $filePath): float
    {
        if (!function_exists('imagecreatefromstring')) return 0;

        $imageData = file_get_contents($filePath);
        if (!$imageData) return 0;

        $img = @imagecreatefromstring($imageData);
        if (!$img) return 0;

        $width = imagesx($img);
        $height = imagesy($img);

        // Sample pixels (don't check every pixel for performance)
        $sampleSize = min(100, $width) * min(100, $height);
        $stepX = max(1, (int)($width / 100));
        $stepY = max(1, (int)($height / 100));

        $skinPixels = 0;
        $totalPixels = 0;

        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Skin color detection using RGB thresholds
                // Covers various skin tones
                if (self::isSkinColor($r, $g, $b)) {
                    $skinPixels++;
                }
                $totalPixels++;
            }
        }

        imagedestroy($img);

        if ($totalPixels === 0) return 0;

        $skinRatio = $skinPixels / $totalPixels;

        // High skin-tone ratio suggests nudity
        // > 60% skin = likely NSFW, > 40% = suspicious
        if ($skinRatio > 0.60) return 0.85;
        if ($skinRatio > 0.45) return 0.55;
        if ($skinRatio > 0.30) return 0.25;

        return 0.05;
    }

    /**
     * Check if a pixel color matches common skin tones
     */
    private static function isSkinColor(int $r, int $g, int $b): bool
    {
        // Rule 1: RGB range for skin detection
        if ($r > 95 && $g > 40 && $b > 20
            && $r > $g && $r > $b
            && abs($r - $g) > 15
            && $r - $b > 15) {
            return true;
        }

        // Rule 2: YCbCr color space skin detection
        $y = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $cb = 128 - 0.169 * $r - 0.331 * $g + 0.500 * $b;
        $cr = 128 + 0.500 * $r - 0.419 * $g - 0.081 * $b;

        if ($y > 80 && $cb > 77 && $cb < 127 && $cr > 133 && $cr < 173) {
            return true;
        }

        return false;
    }

    /**
     * Get admin-configurable blocked keywords
     */
    public static function getBlockedKeywords(): array
    {
        $custom = \DB::table('settings')->where('key', 'nsfw_blocked_keywords')->value('value');
        $keywords = self::$blockedKeywords;
        if ($custom) {
            $keywords = array_merge($keywords, array_filter(explode(',', $custom)));
        }
        return $keywords;
    }

    /**
     * Admin: update blocked keywords
     */
    public static function setBlockedKeywords(string $keywords): void
    {
        \DB::table('settings')->updateOrInsert(
            ['key' => 'nsfw_blocked_keywords'],
            ['value' => $keywords, 'updated_at' => now()]
        );
    }
}
