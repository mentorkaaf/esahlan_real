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
            'review_all_media' => true,
            'block_threshold'  => 0.65,
            'review_threshold' => 0.15,
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

    // ── Image: Google Vision SafeSearch + GD skin detection (dual-layer) ────────

    public static function scanImage(string $filePath): float
    {
        $apiKey = config('services.google.vision_key');

        // Always run GD skin detection as a baseline
        $gdScore = self::analyzeImageGd($filePath);

        if (!$apiKey) {
            Log::warning('[Moderation] Google Vision API key missing — using GD scan only');
            return $gdScore;
        }

        $visionScore = 0.0;
        try {
            $imageData = @file_get_contents($filePath);
            if (!$imageData) {
                Log::warning('[Moderation] Cannot read image file: ' . $filePath);
                // GD already ran; fall through to max()
            } else {
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
                } else {
                    $annotation = $response->json('responses.0.safeSearchAnnotation') ?? [];
                    $visionScore = self::safeSearchScore($annotation);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[Moderation] Google Vision exception: ' . $e->getMessage());
        }

        // Take the worst-case score from both detectors
        $finalScore = max($visionScore, $gdScore);
        Log::info('[Moderation] dual-scan result', [
            'vision' => $visionScore,
            'gd'     => $gdScore,
            'final'  => $finalScore,
        ]);
        return $finalScore;
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

        $score = max($adult, $racy, $violence * 0.75);

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
            // Get video duration first so we can sample evenly
            $probeOut = shell_exec(sprintf(
                'ffprobe -v error -show_entries format=duration -of csv=p=0 %s 2>/dev/null',
                escapeshellarg($videoPath)
            ));
            $duration = (float) trim($probeOut ?? '0');

            // Sample every 2 seconds, max 15 frames — covers the full video
            // including mid-section where explicit content usually appears
            $interval = max(1, $duration > 0 ? min(2, (int)($duration / 15)) : 2);
            $maxFrames = min(15, $duration > 0 ? (int)($duration / $interval) + 1 : 15);

            $framePattern = $tmpDir . '/frame_%03d.jpg';
            $cmd = sprintf(
                'ffmpeg -i %s -vf "fps=1/%d" -q:v 3 -frames:v %d %s -loglevel error 2>&1',
                escapeshellarg($videoPath),
                $interval,
                $maxFrames,
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
                Log::warning('[Moderation] No frames extracted from video — defaulting to review: ' . $videoPath);
                return 0.55; // fail-safe: no frames → flag for review
            }

            Log::info('[Moderation] video frames extracted', ['count' => count($frames), 'duration' => $duration, 'interval' => $interval]);

            $maxScore = 0;
            foreach ($frames as $frame) {
                $score = self::scanImage($frame);
                Log::info('[Moderation] video frame scan', ['frame' => basename($frame), 'score' => $score]);
                if ($score > $maxScore) $maxScore = $score;
                // Short-circuit: clearly explicit
                if ($maxScore >= 0.68) break;
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

    // GD skin detection — full body + upper-body region + dark skin tone support
    private static function analyzeImageGd(string $filePath): float
    {
        if (!function_exists('imagecreatefromstring')) return 0;
        $data = @file_get_contents($filePath);
        if (!$data) return 0;
        $img = @imagecreatefromstring($data);
        if (!$img) return 0;

        $w = imagesx($img); $h = imagesy($img);
        $stepX = max(1, (int)($w / 100)); $stepY = max(1, (int)($h / 100));

        $skinTotal = $skinFace = $skinTorso = $skinLower = $skinCenter = 0;
        $total = $faceTotal = $torsoTotal = $lowerTotal = $centerTotal = 0;

        // Face zone: top 28% (head/neck in portrait)
        // Torso zone: 28%–72% (chest/stomach — bikini area)
        // Lower zone: 72%–100% (thighs/legs)
        $faceBottom  = (int)($h * 0.28);
        $torsoBottom = (int)($h * 0.72);
        $cxMin = (int)($w * 0.20); $cxMax = (int)($w * 0.80);

        for ($x = 0; $x < $w; $x += $stepX) {
            for ($y = 0; $y < $h; $y += $stepY) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8)  & 0xFF;
                $b = $rgb         & 0xFF;

                $isSkin = self::isSkinPixel($r, $g, $b);
                $total++;
                if ($isSkin) $skinTotal++;

                if ($y < $faceBottom) {
                    $faceTotal++;
                    if ($isSkin) $skinFace++;
                } elseif ($y < $torsoBottom) {
                    $torsoTotal++;
                    if ($isSkin) $skinTorso++;
                } else {
                    $lowerTotal++;
                    if ($isSkin) $skinLower++;
                }

                if ($x >= $cxMin && $x <= $cxMax && $y >= $faceBottom && $y < $torsoBottom) {
                    $centerTotal++;
                    if ($isSkin) $skinCenter++;
                }
            }
        }

        imagedestroy($img);
        if ($total === 0) return 0;

        $ratioFull   = $skinTotal / $total;
        $ratioFace   = $faceTotal   > 0 ? $skinFace   / $faceTotal   : 0;
        $ratioTorso  = $torsoTotal  > 0 ? $skinTorso  / $torsoTotal  : 0;
        $ratioLower  = $lowerTotal  > 0 ? $skinLower  / $lowerTotal  : 0;
        $ratioCenter = $centerTotal > 0 ? $skinCenter / $centerTotal : 0;

        // Portrait/face detection: hijab or clothed portraits have face skin
        // but very LOW torso skin. Bikini/nude has HIGH torso + lower skin.
        // Suppress score heavily when face dominates and torso is low.
        $bodyScore = max($ratioTorso, $ratioLower * 0.90, $ratioCenter * 0.95);
        $isFacePortrait = ($ratioFace >= 0.22 && $ratioTorso < $ratioFace * 1.20 && $ratioTorso < 0.32);
        if ($isFacePortrait) {
            $bodyScore *= 0.35; // face portrait — heavily discount
        }

        Log::info('[Moderation] GD skin ratios', [
            'face'    => round($ratioFace,   3),
            'torso'   => round($ratioTorso,  3),
            'lower'   => round($ratioLower,  3),
            'center'  => round($ratioCenter, 3),
            'body'    => round($bodyScore,   3),
            'portrait'=> $isFacePortrait,
        ]);

        if ($bodyScore > 0.65) return 0.97; // explicit/nude — block
        if ($bodyScore > 0.48) return 0.85; // very revealing — block
        if ($bodyScore > 0.30) return 0.75; // bikini/revealing — block
        if ($bodyScore > 0.18) return 0.22; // borderline — allow
        return 0.05;                    // normal (hijab, clothed) — safe
    }

    // Returns true for a wide range of human skin tones (light → dark)
    private static function isSkinPixel(int $r, int $g, int $b): bool
    {
        // Exclude near-white, near-black, near-grey pixels
        if ($r < 40 || $g < 30 || $b < 20) return false;
        if ($r > 250 && $g > 250 && $b > 250) return false;
        if (abs($r - $g) < 8 && abs($g - $b) < 8) return false; // grey

        // HSV-based skin check (covers light to dark skin tones)
        $maxC = max($r, $g, $b);
        $minC = min($r, $g, $b);
        if ($maxC === 0) return false;

        $h = 0;
        $s = ($maxC - $minC) / $maxC;
        $v = $maxC / 255.0;

        if ($maxC === $r) $h = 60 * (($g - $b) / ($maxC - $minC + 0.001));
        elseif ($maxC === $g) $h = 60 * (2 + ($b - $r) / ($maxC - $minC + 0.001));
        else $h = 60 * (4 + ($r - $g) / ($maxC - $minC + 0.001));
        if ($h < 0) $h += 360;

        // Skin hue range: ~0°–25° (peach/brown/tan) with enough saturation
        return ($h >= 0 && $h <= 25 && $s >= 0.15 && $s <= 0.90 && $v >= 0.20);
    }
}
