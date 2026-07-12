<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Converts a raw upload into:
 *   • Thumbnail  (JPEG, 640px wide)
 *   • Optimised MP4 — generated FIRST so video appears in feed quickly
 *   • Adaptive HLS — up to 2 renditions (360p + 720p); generated after MP4
 *
 * Order: thumbnail → optimized MP4 → [mp4ReadyFn callback] → HLS 360p → HLS 720p
 *
 * The optional $mp4ReadyFn callback fires right after the MP4 is ready,
 * allowing the caller (TranscodeVideoJob) to mark video_ready=true and
 * notify the user before HLS finishes — feed shows the video in ~2 min.
 *
 * FFmpeg runs with `nice -n 10 ionice -c 2 -n 5` — background priority
 * but not idle-only, so transcoding completes in reasonable time.
 */
class VideoProcessingService
{
    private const HLS_SEGMENT = 2;
    private const NICE        = 'nice -n 10 ionice -c 2 -n 5';

    public static function process(
        string $storagePath,
        ?callable $progressFn = null,
        ?callable $mp4ReadyFn = null   // fires right after MP4 is ready
    ): array {
        $inputPath = storage_path('app/public/' . $storagePath);
        if (!file_exists($inputPath)) return ['error' => 'File not found'];

        $dir    = pathinfo($storagePath, PATHINFO_DIRNAME);
        $name   = pathinfo($storagePath, PATHINFO_FILENAME);
        $outDir = storage_path("app/public/{$dir}/{$name}");
        @mkdir($outDir, 0755, true);

        // ── Probe source ────────────────────────────────────────────────
        $probe = shell_exec(
            'ffprobe -v quiet -print_format json -show_streams -show_format '
            . escapeshellarg($inputPath) . ' 2>/dev/null'
        );
        $info    = json_decode($probe, true) ?? [];
        $vStream = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        $width   = (int)($vStream['width']  ?? 1280);
        $height  = (int)($vStream['height'] ?? 720);
        $duration = (float)($info['format']['duration'] ?? 0);
        $maxDim  = max($width, $height);

        $results = [
            'original' => $storagePath,
            'duration' => $duration,
            'width'    => $width,
            'height'   => $height,
            'qualities' => [],
        ];

        $progressFn && $progressFn(8);

        // ── Thumbnail ───────────────────────────────────────────────────
        $thumbPath = "{$dir}/{$name}/thumb.jpg";
        $thumbFull = storage_path('app/public/' . $thumbPath);
        $seekAt    = $duration > 5 ? '00:00:03' : '00:00:01';
        exec(sprintf(
            '%s ffmpeg -threads 0 -ss %s -i %s -vframes 1 -q:v 4 -vf scale=640:-2 -y %s 2>/dev/null',
            self::NICE, $seekAt, escapeshellarg($inputPath), escapeshellarg($thumbFull)
        ));
        if (file_exists($thumbFull)) $results['thumbnail'] = $thumbPath;

        $progressFn && $progressFn(12);

        // ── Optimised MP4 — FIRST so feed can show the video quickly ───
        // Generated before HLS; mp4ReadyFn fires here so video_ready=true
        // is set before the slower HLS pass begins.
        $optPath = "{$dir}/{$name}/optimized.mp4";
        $optFull = storage_path('app/public/' . $optPath);
        [$scaleFilter, $crf] = self::optimalMp4Params($maxDim, $width, $height);

        // -maxrate 1600k -bufsize 3200k: bitrate ceiling so complex scenes don't
        // balloon the file. On slow mobile networks the first 3s of a 1-min reel
        // goes from ~4 MB → ~600 KB, starting playback 5-6× faster.
        exec(sprintf(
            '%s ffmpeg -threads 0 -i %s %s -c:v libx264 -preset veryfast -crf %d -maxrate 1600k -bufsize 3200k -c:a aac -b:a 96k -movflags +faststart -y %s 2>/dev/null',
            self::NICE, escapeshellarg($inputPath), $scaleFilter, $crf, escapeshellarg($optFull)
        ), $_, $code);

        if ($code === 0 && file_exists($optFull)) {
            $results['qualities']['optimized'] = [
                'path' => $optPath,
                'url'  => url('/api/v1/media?f=' . $optPath),
                'size' => filesize($optFull),
            ];
            $progressFn && $progressFn(50);

            // Notify caller that MP4 is ready — video can now appear in feed.
            $mp4ReadyFn && $mp4ReadyFn($results);
        }

        $progressFn && $progressFn(52);

        // ── HLS renditions (360p + 720p only — 1080p not needed for mobile) ─
        $renditions = self::buildRenditions($width, $height, $maxDim);
        $hlsDir     = "{$outDir}/hls";
        @mkdir($hlsDir, 0755, true);

        $masterLines  = ['#EXTM3U', '#EXT-X-VERSION:3', '#EXT-X-INDEPENDENT-SEGMENTS'];
        $successCount = 0;
        $total        = count($renditions);
        $hlsBaseUrl   = url("/hls/{$dir}/{$name}/hls") . '/';

        foreach ($renditions as $idx => $r) {
            $code = self::transcodeHls($inputPath, $hlsDir, $r, $hlsBaseUrl);
            if ($code === 0) {
                $masterLines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['resolution']},NAME=\"{$r['name']}\"";
                $masterLines[] = url("/hls/{$dir}/{$name}/hls/{$r['name']}.m3u8");
                $successCount++;
            }
            $pct = 52 + (int)(($idx + 1) / $total * 43);
            $progressFn && $progressFn($pct);
        }

        if ($successCount > 0) {
            file_put_contents("{$hlsDir}/master.m3u8", implode("\n", $masterLines) . "\n");
            $results['qualities']['hls'] = [
                'path' => "{$dir}/{$name}/hls/master.m3u8",
                'url'  => url("/hls/{$dir}/{$name}/hls/master.m3u8"),
            ];
        }

        $progressFn && $progressFn(97);

        // ── Delete raw upload (saves disk space) ────────────────────────
        if (file_exists($optFull) && $inputPath !== $optFull) {
            @unlink($inputPath);
        }

        Log::info("[VideoProcessingService] Done: {$storagePath} {$width}x{$height} {$duration}s renditions={$successCount}");
        return $results;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Mobile-first: 360p always, 720p for anything >= 480p source.
     * 1080p removed — unnecessary for social media on mobile.
     */
    private static function buildRenditions(int $w, int $h, int $maxDim): array
    {
        $land = $w >= $h;
        $all = [
            [
                'name'       => '360p',
                'scale'      => $land ? 'scale=640:-2' : 'scale=-2:640',
                'crf'        => 30,
                'audiobr'    => '80k',
                'bandwidth'  => 600000,
                'resolution' => $land ? '640x360' : '360x640',
                'minDim'     => 0,
            ],
            [
                'name'       => '720p',
                'scale'      => $land ? 'scale=1280:-2' : 'scale=-2:1280',
                'crf'        => 26,
                'audiobr'    => '128k',
                'bandwidth'  => 2000000,
                'resolution' => $land ? '1280x720' : '720x1280',
                'minDim'     => 480,
            ],
        ];
        return array_values(array_filter($all, fn($r) => $maxDim >= $r['minDim']));
    }

    private static function transcodeHls(string $input, string $hlsDir, array $r, string $hlsBaseUrl = ''): int
    {
        $seg      = self::HLS_SEGMENT;
        $segPat   = escapeshellarg("{$hlsDir}/{$r['name']}_%04d.ts");
        $m3u8     = escapeshellarg("{$hlsDir}/{$r['name']}.m3u8");
        $baseFlag = $hlsBaseUrl ? ' -hls_base_url ' . escapeshellarg($hlsBaseUrl) : '';

        $cmd = sprintf(
            '%s ffmpeg -threads 0 -i %s -vf %s -c:v libx264 -preset veryfast -crf %d '
            . '-sc_threshold 0 -force_key_frames "expr:gte(t,n_forced*%d)" '
            . '-c:a aac -b:a %s '
            . '-hls_time %d -hls_list_size 0 -hls_segment_type mpegts'
            . '%s -hls_segment_filename %s -y %s 2>/dev/null',
            self::NICE,
            escapeshellarg($input),
            $r['scale'], $r['crf'],
            $seg,
            $r['audiobr'],
            $seg,
            $baseFlag, $segPat, $m3u8
        );
        exec($cmd, $_, $code);
        return (int) $code;
    }

    private static function optimalMp4Params(int $maxDim, int $w, int $h): array
    {
        $land = $w >= $h;
        // Resolution caps: 720p max for mobile feed (1280px on long side).
        // Bitrate ceiling handled in the exec() call (-maxrate 1600k).
        // CRF 28 across all tiers: visually transparent on mobile at ≤720p.
        if ($maxDim > 720) return [$land ? '-vf scale=1280:-2' : '-vf scale=-2:1280', 28];
        if ($maxDim > 480) return [$land ? '-vf scale=720:-2'  : '-vf scale=-2:720',  28];
        return ['', 28];
    }

    /**
     * Re-transcode HLS segments only from an already-optimized MP4.
     */
    public static function retranscodeHlsOnly(string $optimizedStoragePath): array
    {
        $inputPath = storage_path('app/public/' . $optimizedStoragePath);
        if (!file_exists($inputPath)) {
            return ['error' => "File not found: {$optimizedStoragePath}"];
        }

        $baseDir  = dirname($optimizedStoragePath);
        $hlsStorageDir = "{$baseDir}/hls";
        $hlsFullDir    = storage_path("app/public/{$hlsStorageDir}");
        @mkdir($hlsFullDir, 0755, true);

        foreach (glob("{$hlsFullDir}/*.ts") ?: [] as $old) @unlink($old);
        foreach (glob("{$hlsFullDir}/*.m3u8") ?: [] as $old) @unlink($old);

        $probe  = shell_exec('ffprobe -v quiet -print_format json -show_streams '
            . escapeshellarg($inputPath) . ' 2>/dev/null');
        $info   = json_decode($probe, true) ?? [];
        $vStream = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        $width  = (int)($vStream['width']  ?? 1280);
        $height = (int)($vStream['height'] ?? 720);
        $maxDim = max($width, $height);

        $renditions   = self::buildRenditions($width, $height, $maxDim);
        $masterLines  = ['#EXTM3U', '#EXT-X-VERSION:3', '#EXT-X-INDEPENDENT-SEGMENTS'];
        $successCount = 0;
        $hlsBaseUrl   = url("/hls/{$baseDir}/hls") . '/';

        foreach ($renditions as $r) {
            $code = self::transcodeHls($inputPath, $hlsFullDir, $r, $hlsBaseUrl);
            if ($code === 0) {
                $masterLines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['resolution']},NAME=\"{$r['name']}\"";
                $masterLines[] = url("/hls/{$baseDir}/hls/{$r['name']}.m3u8");
                $successCount++;
            }
        }

        if ($successCount === 0) return ['error' => 'All renditions failed'];

        file_put_contents("{$hlsFullDir}/master.m3u8", implode("\n", $masterLines) . "\n");
        return ['hls_url' => url("/hls/{$baseDir}/hls/master.m3u8")];
    }

    public static function getOptimalUrl(array $qualities): ?string
    {
        return $qualities['optimized']['url'] ?? null;
    }

    public static function getOptimalQuality(array $qualities): ?string
    {
        return self::getOptimalUrl($qualities);
    }

    /**
     * Story-specific processing: aggressive compression, no HLS.
     * Stories are short (≤60s) and viewed full-screen on mobile.
     * Target: 480p portrait, CRF 30, maxrate 600k → ~1-3 MB per story.
     * Runs in ~15-30s on the server vs 2-5 min for full post transcoding.
     *
     * Returns: ['url' => ..., 'thumbnail' => ..., 'thumbnail_path' => ...]
     */
    public static function processStory(string $storagePath): array
    {
        $inputPath = storage_path('app/public/' . $storagePath);
        if (!file_exists($inputPath)) return ['error' => 'File not found'];

        $dir  = pathinfo($storagePath, PATHINFO_DIRNAME);
        $name = pathinfo($storagePath, PATHINFO_FILENAME);
        $outDir = storage_path("app/public/{$dir}/{$name}");
        @mkdir($outDir, 0755, true);

        // Probe source dimensions
        $probe = shell_exec(
            'ffprobe -v quiet -print_format json -show_streams -show_format '
            . escapeshellarg($inputPath) . ' 2>/dev/null'
        );
        $info     = json_decode($probe, true) ?? [];
        $vStream  = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        $width    = (int)($vStream['width']  ?? 1080);
        $height   = (int)($vStream['height'] ?? 1920);
        $duration = (float)($info['format']['duration'] ?? 0);

        // Scale: limit short side to 480px → portrait 480×854, landscape 854×480
        // Most stories are portrait (9:16). ceil to even numbers for libx264.
        $land        = $width >= $height;
        $scaleFilter = $land ? 'scale=-2:480' : 'scale=480:-2';

        // Trim to 60 s max
        $durationFlag = $duration > 60 ? '-t 60' : '';

        $outRelPath = "{$dir}/{$name}/story.mp4";
        $outFull    = storage_path('app/public/' . $outRelPath);

        exec(sprintf(
            '%s ffmpeg -threads 0 -i %s %s -vf %s '
            . '-c:v libx264 -preset veryfast -crf 30 -maxrate 600k -bufsize 1200k '
            . '-c:a aac -b:a 64k -movflags +faststart -y %s 2>/dev/null',
            self::NICE,
            escapeshellarg($inputPath),
            $durationFlag,
            $scaleFilter,
            escapeshellarg($outFull)
        ), $_, $code);

        if ($code !== 0 || !file_exists($outFull)) {
            // Fallback: just copy original so the story stays watchable
            if ($inputPath !== $outFull) @copy($inputPath, $outFull);
        }

        $result = [
            'url' => url('/api/v1/media?f=' . $outRelPath),
        ];

        // Thumbnail (360px wide, from 1s mark)
        $thumbRelPath = "{$dir}/{$name}/thumb.jpg";
        $thumbFull    = storage_path('app/public/' . $thumbRelPath);
        exec(sprintf(
            '%s ffmpeg -threads 0 -ss 1 -i %s -vframes 1 -q:v 5 -vf scale=360:-2 -y %s 2>/dev/null',
            self::NICE,
            escapeshellarg($outFull),
            escapeshellarg($thumbFull)
        ));
        if (file_exists($thumbFull)) {
            $result['thumbnail']      = url('/api/v1/media?f=' . $thumbRelPath);
            $result['thumbnail_path'] = $thumbRelPath;
        }

        // Delete raw upload
        if ($inputPath !== $outFull) @unlink($inputPath);

        Log::info("[VideoProcessingService::processStory] {$storagePath} {$width}x{$height} {$duration}s → {$outRelPath}");
        return $result;
    }
}
