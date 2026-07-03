<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            'image_scan'          => true,
            'auto_block'          => true,
            'review_all_media'    => false,
            'block_threshold'     => 0.85,
            'review_threshold'    => 0.60,
            'sightengine_user'    => '',
            'sightengine_secret'  => '',
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
     * Synchronous check — text only. Called during post creation.
     * Media moderation is async (ModeratePostMediaJob).
     */
    public static function moderatePost(?string $text, array $mediaFiles = []): array
    {
        $settings = self::getSettings();

        if (!$settings['enabled']) {
            return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Moderation disabled'];
        }

        // Keyword check (synchronous, fast)
        if ($settings['keyword_filter'] && $text) {
            $result = self::checkText($text);
            if ($result['action'] === 'block') {
                return $result;
            }
        }

        // Media posts → always start as pending; ModeratePostMediaJob will approve/block
        if (!empty($mediaFiles)) {
            return ['safe' => true, 'score' => 0, 'action' => 'media_pending', 'reason' => 'Media queued for moderation'];
        }

        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Content is safe'];
    }

    public static function checkText(?string $text): array
    {
        if (empty($text)) return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => ''];
        $lower = mb_strtolower($text);
        foreach (self::getBlockedKeywords() as $kw) {
            if (str_contains($lower, mb_strtolower(trim($kw)))) {
                return ['safe' => false, 'score' => 0.9, 'action' => 'block', 'reason' => 'Blocked keyword detected'];
            }
        }
        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => ''];
    }

    /**
     * Scan a media URL for explicit content.
     * Uses Sightengine API if credentials are configured, otherwise local skin-tone analysis.
     * Returns a score 0.0–1.0 (higher = more explicit).
     */
    public static function scanMediaUrl(string $url): float
    {
        $settings = self::getSettings();
        $apiUser   = $settings['sightengine_user']   ?? '';
        $apiSecret = $settings['sightengine_secret'] ?? '';

        if ($apiUser && $apiSecret) {
            return self::sightengineCheck($url, $apiUser, $apiSecret);
        }

        // Fallback: download and run local analysis
        return self::localImageAnalysis($url);
    }

    // ─── Sightengine API ─────────────────────────────────────────────────────

    private static function sightengineCheck(string $url, string $apiUser, string $apiSecret): float
    {
        try {
            $response = Http::timeout(20)->get('https://api.sightengine.com/1.0/check.json', [
                'url'        => $url,
                'models'     => 'nudity-2.0',
                'api_user'   => $apiUser,
                'api_secret' => $apiSecret,
            ]);

            if (!$response->successful()) {
                Log::warning('[Moderation] Sightengine API error: ' . $response->body());
                return self::localImageAnalysis($url);
            }

            $data = $response->json();

            // nudity-2.0 model fields
            $nudity = $data['nudity'] ?? [];
            $score  = max(
                (float)($nudity['sexual_activity']  ?? 0),
                (float)($nudity['sexual_display']   ?? 0),
                (float)($nudity['erotica']          ?? 0),
                (float)($nudity['very_suggestive']  ?? 0) * 0.8,
                (float)($nudity['suggestive']       ?? 0) * 0.5,
            );

            Log::info("[Moderation] Sightengine score={$score} url={$url}");
            return $score;
        } catch (\Throwable $e) {
            Log::warning('[Moderation] Sightengine exception: ' . $e->getMessage());
            return self::localImageAnalysis($url);
        }
    }

    // ─── Local fallback: improved skin-tone + texture analysis ───────────────

    private static function localImageAnalysis(string $url): float
    {
        if (!function_exists('imagecreatefromstring')) return 0;

        try {
            // Try to download, timeout 10s, max 10MB
            $ctx  = stream_context_create(['http' => ['timeout' => 10]]);
            $data = @file_get_contents($url, false, $ctx);
            if (!$data || strlen($data) > 10 * 1024 * 1024) return 0;

            $img = @imagecreatefromstring($data);
            if (!$img) return 0;

            $w = imagesx($img);
            $h = imagesy($img);
            $stepX = max(1, (int)($w / 100));
            $stepY = max(1, (int)($h / 100));

            $skin = 0; $total = 0;
            $skinRegions = 0; $regionSize = 0; $maxRegion = 0;

            for ($x = 0; $x < $w; $x += $stepX) {
                $colSkin = 0;
                for ($y = 0; $y < $h; $y += $stepY) {
                    $rgb = imagecolorat($img, $x, $y);
                    $r   = ($rgb >> 16) & 0xFF;
                    $g   = ($rgb >> 8)  & 0xFF;
                    $b   = $rgb & 0xFF;

                    if (self::isSkinPixel($r, $g, $b)) {
                        $skin++;
                        $colSkin++;
                    }
                    $total++;
                }
                if ($colSkin > ($h / $stepY * 0.4)) $skinRegions++;
            }

            imagedestroy($img);
            if ($total === 0) return 0;

            $ratio         = $skin / $total;
            $regionRatio   = $skinRegions / max(1, $w / $stepX);

            // High skin ratio + contiguous skin regions = nudity indicator
            if ($ratio > 0.65 && $regionRatio > 0.5) return 0.92;
            if ($ratio > 0.50 && $regionRatio > 0.4) return 0.75;
            if ($ratio > 0.40) return 0.55;
            return $ratio * 0.5;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function isSkinPixel(int $r, int $g, int $b): bool
    {
        // Rule 1: Uniform color spaces skin detection (RGB + YCbCr-like)
        if ($r < 60 || $g < 40 || $b < 20) return false;
        if ($r < $g || $r < $b) return false;
        if (abs($r - $g) < 10) return false;

        $cb = 128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
        $cr = 128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;

        return ($cb >= 77 && $cb <= 127 && $cr >= 133 && $cr <= 173);
    }
}
