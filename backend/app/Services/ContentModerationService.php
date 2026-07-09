<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    private static array $defaultKeywords = [
        'porn', 'xxx', 'nude', 'naked', 'sex video', 'onlyfans', 'nsfw',
        'hentai', 'xvideos', 'pornhub', 'xhamster', 'brazzers',
        'qaawan', 'siil', 'gus', 'wasakh', 'nijaas',
        'اباحي', 'عري', 'جنس',
    ];

    public static function getSettings(): array
    {
        $row = DB::table('settings')->where('key', 'content_moderation')->first();
        if ($row) {
            return json_decode($row->value, true) ?? self::defaultSettings();
        }
        return self::defaultSettings();
    }

    public static function defaultSettings(): array
    {
        return [
            'enabled'             => true,
            'keyword_filter'      => true,
            'image_scan'          => true,    // Google Vision SafeSearch
            'auto_block'          => false,   // Don't auto-block, just flag for review
            'review_all_media'    => false,   // Flag all media posts for review
            'block_threshold'     => 0.85,
            'review_threshold'    => 0.60,
        ];
    }

    public static function saveSettings(array $settings): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'content_moderation'],
            ['value' => json_encode($settings), 'updated_at' => now()]
        );
    }

    public static function getBlockedKeywords(): array
    {
        $custom = DB::table('settings')->where('key', 'nsfw_blocked_keywords')->value('value');
        if ($custom) return array_filter(array_map('trim', explode(',', $custom)));
        return self::$defaultKeywords;
    }

    public static function saveBlockedKeywords(string $keywords): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'nsfw_blocked_keywords'],
            ['value' => $keywords, 'updated_at' => now()]
        );
    }

    /**
     * Moderate a post — only keyword filtering by default
     */
    public static function moderatePost(?string $text, array $mediaFiles = []): array
    {
        $settings = self::getSettings();

        if (!$settings['enabled']) {
            return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Moderation disabled'];
        }

        // Keyword check
        if ($settings['keyword_filter'] && $text) {
            $result = self::checkText($text);
            if ($result['action'] === 'block') {
                return $settings['auto_block'] ? $result : ['safe' => false, 'score' => $result['score'], 'action' => 'review', 'reason' => $result['reason']];
            }
        }

        // Image scan (only if enabled by admin)
        if ($settings['image_scan'] && !empty($mediaFiles)) {
            foreach ($mediaFiles as $file) {
                if (!$file) continue;
                $mime = is_object($file) ? $file->getMimeType() : (function_exists('mime_content_type') ? mime_content_type($file) : '');
                if (str_starts_with($mime, 'image/')) {
                    $path = is_object($file) ? $file->getRealPath() : $file;
                    $score = self::analyzeImage($path);
                    if ($score >= $settings['block_threshold']) {
                        return $settings['auto_block']
                            ? ['safe' => false, 'score' => $score, 'action' => 'block', 'reason' => 'Explicit image detected']
                            : ['safe' => false, 'score' => $score, 'action' => 'review', 'reason' => 'Image flagged for review'];
                    }
                    if ($score >= $settings['review_threshold']) {
                        return ['safe' => false, 'score' => $score, 'action' => 'review', 'reason' => 'Image flagged for review'];
                    }
                }
            }
        }

        // Flag all media for review if setting enabled
        if ($settings['review_all_media'] && !empty($mediaFiles)) {
            return ['safe' => true, 'score' => 0.3, 'action' => 'review', 'reason' => 'Media post queued for review'];
        }

        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Content is safe'];
    }

    public static function checkText(?string $text): array
    {
        if (empty($text)) return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => ''];
        $lower = mb_strtolower($text);
        foreach (self::getBlockedKeywords() as $kw) {
            if (str_contains($lower, mb_strtolower(trim($kw)))) {
                return ['safe' => false, 'score' => 0.9, 'action' => 'block', 'reason' => "Blocked keyword detected"];
            }
        }
        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => ''];
    }

    private static function analyzeImage(string $filePath): float
    {
        $user   = config('services.sightengine.api_user');
        $secret = config('services.sightengine.api_secret');
        if ($user && $secret) {
            return self::analyzeImageWithSightengine($filePath, $user, $secret);
        }
        // Fallback: GD pixel analysis (less reliable)
        return self::analyzeImageGd($filePath);
    }

    private static function analyzeImageWithSightengine(string $filePath, string $user, string $secret): float
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->attach('media', fopen($filePath, 'r'), basename($filePath))
                ->post('https://api.sightengine.com/1.0/check.json', [
                    'models'     => 'nudity-2.1',
                    'api_user'   => $user,
                    'api_secret' => $secret,
                ]);

            if (!$response->successful()) {
                Log::warning('Sightengine API error', ['status' => $response->status()]);
                return 0;
            }

            $nudity = $response->json('nudity') ?? [];
            // sexual_activity = explicit sex, sexual_display = nudity, erotica = suggestive
            $score = max(
                (float)($nudity['sexual_activity'] ?? 0),
                (float)($nudity['sexual_display']  ?? 0),
                (float)($nudity['erotica']          ?? 0) * 0.7,
            );

            Log::info('Sightengine nudity', [
                'sexual_activity' => $nudity['sexual_activity'] ?? 0,
                'sexual_display'  => $nudity['sexual_display']  ?? 0,
                'erotica'         => $nudity['erotica']          ?? 0,
                'score'           => $score,
            ]);

            return $score;
        } catch (\Throwable $e) {
            Log::warning('Sightengine exception: ' . $e->getMessage());
            return 0;
        }
    }

    private static function analyzeImageGd(string $filePath): float
    {
        if (!function_exists('imagecreatefromstring')) return 0;
        $data = @file_get_contents($filePath);
        if (!$data) return 0;
        $img = @imagecreatefromstring($data);
        if (!$img) return 0;

        $w = imagesx($img); $h = imagesy($img);
        $stepX = max(1, (int)($w / 80)); $stepY = max(1, (int)($h / 80));
        $skin = 0; $total = 0;

        for ($x = 0; $x < $w; $x += $stepX) {
            for ($y = 0; $y < $h; $y += $stepY) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
                if ($r > 95 && $g > 40 && $b > 20 && $r > $g && $r > $b && abs($r - $g) > 15 && $r - $b > 15) $skin++;
                $total++;
            }
        }
        imagedestroy($img);
        if ($total === 0) return 0;
        $ratio = $skin / $total;
        if ($ratio > 0.65) return 0.93;
        if ($ratio > 0.50) return 0.82;
        if ($ratio > 0.35) return 0.55;
        return 0.05;
    }
}
