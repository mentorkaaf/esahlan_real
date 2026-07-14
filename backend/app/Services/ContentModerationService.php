<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentModerationService
{
    // Google Vision SafeSearch likelihood → numeric score
    private static array $LIKELIHOOD = [
        'UNKNOWN'       => 0.0,
        'VERY_UNLIKELY' => 0.05,
        'UNLIKELY'      => 0.15,
        'POSSIBLE'      => 0.50,
        'LIKELY'        => 0.80,
        'VERY_LIKELY'   => 0.97,
    ];

    private static array $defaultKeywords = [
        'porn', 'xxx', 'nude', 'naked', 'sex video', 'onlyfans', 'nsfw',
        'hentai', 'xvideos', 'pornhub', 'xhamster', 'brazzers',
        'qaawan', 'siil', 'gus', 'wasakh', 'nijaas',
        'اباحي', 'عري', 'جنس',
    ];

    // ── Settings ───────────────────────────────────────────────────────────────

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
            'enabled'          => true,
            'keyword_filter'   => true,
            'image_scan'       => true,
            'video_scan'       => true,
            'auto_block'       => false,
            'review_all_media' => false,
            'block_threshold'  => 0.80,
            'review_threshold' => 0.50,
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

    // ── Public entry-point ─────────────────────────────────────────────────────

    public static function moderatePost(?string $text, array $mediaFiles = []): array
    {
        $settings = self::getSettings();

        if (!$settings['enabled']) {
            return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Moderation disabled'];
        }

        // 1. Keyword check
        if ($settings['keyword_filter'] && $text) {
            $result = self::checkText($text);
            if ($result['action'] === 'block') {
                return $settings['auto_block']
                    ? $result
                    : ['safe' => false, 'score' => $result['score'], 'action' => 'review', 'reason' => $result['reason']];
            }
        }

        // 2. Media scan (images + videos)
        if (!empty($mediaFiles)) {
            foreach ($mediaFiles as $file) {
                if (!$file) continue;
                $mime = is_object($file) ? $file->getMimeType() : (mime_content_type($file) ?: '');
                $path = is_object($file) ? $file->getRealPath() : $file;

                if ($settings['image_scan'] && str_starts_with($mime, 'image/')) {
                    $score = self::scanImage($path);
                    Log::info('[Moderation] image scan', ['mime' => $mime, 'score' => $score]);
                    $res = self::scoreToResult($score, $settings);
                    if ($res['action'] !== 'allow') return $res;
                }

                if ($settings['video_scan'] && str_starts_with($mime, 'video/')) {
                    $score = self::scanVideo($path);
                    Log::info('[Moderation] video scan', ['mime' => $mime, 'score' => $score]);
                    $res = self::scoreToResult($score, $settings);
                    if ($res['action'] !== 'allow') return $res;
                }
            }
        }

        if ($settings['review_all_media'] && !empty($mediaFiles)) {
            return ['safe' => true, 'score' => 0.3, 'action' => 'review', 'reason' => 'Media queued for review'];
        }

        return ['safe' => true, 'score' => 0, 'action' => 'allow', 'reason' => 'Content is safe'];
    }

    // ── Text ───────────────────────────────────────────────────────────────────

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

    // ── Image: Google Vision SafeSearch ───────────────────────────────────────

    public static function scanImage(string $filePath): float
    {
        $apiKey = config('services.google.vision_key');
        if (!$apiKey) {
            Log::warning('[Moderation] Google Vision API key missing — falling back to GD scan');
            return self::analyzeImageGd($filePath);
        }

        try {
            $imageData = @file_get_contents($filePath);
            if (!$imageData) {
                Log::warning('[Moderation] Cannot read image file: ' . $filePath);
                return 0;
            }

            $response = Http::timeout(20)->post(
                'https://vision.googleapis.com/v1/images:annotate?key=' . $apiKey,
                [
                    'requests' => [[
                        'image'    => ['content' => base64_encode($imageData)],
                        'features' => [['type' => 'SAFE_SEARCH_DETECTION']],
                    ]],
                ]
            );

            if (!$response->successful()) {
                Log::warning('[Moderation] Google Vision API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return 0;
            }

            $annotation = $response->json('responses.0.safeSearchAnnotation') ?? [];
            return self::safeSearchScore($annotation);

        } catch (\Throwable $e) {
            Log::warning('[Moderation] Google Vision exception: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Convert SafeSearch annotation to a single 0–1 score.
     * adult + racy are the primary NSFW signals.
     * violence is weighted lower but still flagged.
     */
    private static function safeSearchScore(array $annotation): float
    {
        $adult    = self::$LIKELIHOOD[$annotation['adult']    ?? 'UNKNOWN'] ?? 0;
        $racy     = self::$LIKELIHOOD[$annotation['racy']     ?? 'UNKNOWN'] ?? 0;
        $violence = self::$LIKELIHOOD[$annotation['violence'] ?? 'UNKNOWN'] ?? 0;

        $score = max($adult, $racy * 0.85, $violence * 0.70);

        Log::info('[Moderation] SafeSearch', [
            'adult'    => $annotation['adult']    ?? '?',
            'racy'     => $annotation['racy']     ?? '?',
            'violence' => $annotation['violence'] ?? '?',
            'score'    => $score,
        ]);

        return $score;
    }

    // ── Video: extract frames → scan each with Vision ─────────────────────────

    public static function scanVideo(string $videoPath): float
    {
        if (!file_exists($videoPath)) return 0;

        $tmpDir = sys_get_temp_dir() . '/moderation_frames_' . uniqid();
        @mkdir($tmpDir, 0755, true);

        try {
            // Extract 5 evenly-spaced frames (at 10s intervals, max 5)
            $framePattern = $tmpDir . '/frame_%02d.jpg';
            $cmd = sprintf(
                'ffmpeg -i %s -vf "select=\'not(mod(n,120))\'" -vsync vfr -frames:v 5 %s -loglevel error 2>&1',
                escapeshellarg($videoPath),
                escapeshellarg($framePattern)
            );
            exec($cmd, $out, $code);

            $frames = glob($tmpDir . '/frame_*.jpg');
            if (empty($frames)) {
                // Fallback: grab just the first frame
                $firstFrame = $tmpDir . '/thumb.jpg';
                exec(sprintf(
                    'ffmpeg -i %s -vframes 1 -q:v 2 %s -loglevel error 2>&1',
                    escapeshellarg($videoPath),
                    escapeshellarg($firstFrame)
                ));
                $frames = file_exists($firstFrame) ? [$firstFrame] : [];
            }

            if (empty($frames)) {
                Log::warning('[Moderation] No frames extracted from video: ' . $videoPath);
                return 0;
            }

            $maxScore = 0;
            foreach ($frames as $frame) {
                $score = self::scanImage($frame);
                Log::info('[Moderation] video frame scan', ['frame' => basename($frame), 'score' => $score]);
                if ($score > $maxScore) $maxScore = $score;
                // Short-circuit: already flagged
                if ($maxScore >= 0.80) break;
            }

            return $maxScore;

        } finally {
            // Clean up temp frames
            foreach (glob($tmpDir . '/*') as $f) @unlink($f);
            @rmdir($tmpDir);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function scoreToResult(float $score, array $settings): array
    {
        if ($score >= $settings['block_threshold']) {
            return $settings['auto_block']
                ? ['safe' => false, 'score' => $score, 'action' => 'block',  'reason' => 'Explicit content detected']
                : ['safe' => false, 'score' => $score, 'action' => 'review', 'reason' => 'Content flagged for review'];
        }
        if ($score >= $settings['review_threshold']) {
            return ['safe' => false, 'score' => $score, 'action' => 'review', 'reason' => 'Content flagged for review'];
        }
        return ['safe' => true, 'score' => $score, 'action' => 'allow', 'reason' => 'Content is safe'];
    }

    // GD fallback — only used when Vision key is missing
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
