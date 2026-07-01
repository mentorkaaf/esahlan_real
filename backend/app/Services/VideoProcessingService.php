<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Converts a raw upload into:
 *   • Thumbnail (JPEG, 640px wide)
 *   • Adaptive HLS master playlist — up to 3 renditions:
 *       360p  ~600 Kbps   ← slow mobile connections
 *       720p  ~2 Mbps     ← normal 4G
 *       1080p ~4.5 Mbps   ← WiFi / fast connection
 *   • Optimised MP4 fallback (for older video players)
 *
 * FFmpeg runs with `nice -n 15 ionice -c 3` so transcoding never
 * starves the web server or queue workers of CPU/IO.
 *
 * The optional $progressFn callback receives int 0-100 so callers
 * can persist progress to the DB without polling FFmpeg.
 */
class VideoProcessingService
{
    private const HLS_SEGMENT = 4;            // segment length in seconds
    private const NICE        = 'nice -n 15 ionice -c 3';

    public static function process(string $storagePath, ?callable $progressFn = null): array
    {
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
            '%s ffmpeg -ss %s -i %s -vframes 1 -q:v 4 -vf scale=640:-2 -y %s 2>/dev/null',
            self::NICE, $seekAt, escapeshellarg($inputPath), escapeshellarg($thumbFull)
        ));
        if (file_exists($thumbFull)) $results['thumbnail'] = $thumbPath;

        $progressFn && $progressFn(12);

        // ── HLS renditions ──────────────────────────────────────────────
        $renditions = self::buildRenditions($width, $height, $maxDim);
        $hlsDir     = "{$outDir}/hls";
        @mkdir($hlsDir, 0755, true);

        $masterLines  = ['#EXTM3U', '#EXT-X-VERSION:3'];
        $successCount = 0;
        $total        = count($renditions);

        foreach ($renditions as $idx => $r) {
            $code = self::transcodeHls($inputPath, $hlsDir, $r);
            if ($code === 0) {
                $masterLines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['resolution']},NAME=\"{$r['name']}\"";
                $masterLines[] = "{$r['name']}.m3u8";
                $successCount++;
            }
            $pct = 12 + (int)(($idx + 1) / $total * 72);
            $progressFn && $progressFn($pct);
        }

        if ($successCount > 0) {
            file_put_contents("{$hlsDir}/master.m3u8", implode("\n", $masterLines) . "\n");
            $results['qualities']['hls'] = [
                'path' => "{$dir}/{$name}/hls/master.m3u8",
                'url'  => url("/hls/{$dir}/{$name}/hls/master.m3u8"),
            ];
        }

        $progressFn && $progressFn(86);

        // ── Optimised MP4 fallback ──────────────────────────────────────
        $optPath = "{$dir}/{$name}/optimized.mp4";
        $optFull = storage_path('app/public/' . $optPath);
        [$scaleFilter, $crf] = self::optimalMp4Params($maxDim, $width, $height);

        exec(sprintf(
            '%s ffmpeg -i %s %s -c:v libx264 -preset fast -crf %d -c:a aac -b:a 96k -movflags +faststart -y %s 2>/dev/null',
            self::NICE, escapeshellarg($inputPath), $scaleFilter, $crf, escapeshellarg($optFull)
        ), $_, $code);

        if ($code === 0 && file_exists($optFull)) {
            $results['qualities']['optimized'] = [
                'path' => $optPath,
                'url'  => url('/api/v1/media?f=' . $optPath),
                'size' => filesize($optFull),
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
            [
                'name'       => '1080p',
                'scale'      => $land ? 'scale=1920:-2' : 'scale=-2:1920',
                'crf'        => 23,
                'audiobr'    => '192k',
                'bandwidth'  => 4500000,
                'resolution' => $land ? '1920x1080' : '1080x1920',
                'minDim'     => 900,
            ],
        ];
        // Never upscale — only produce renditions the source can support
        return array_values(array_filter($all, fn($r) => $maxDim >= $r['minDim']));
    }

    private static function transcodeHls(string $input, string $hlsDir, array $r): int
    {
        $gop     = self::HLS_SEGMENT * 30; // keyframe interval (assuming ≤30fps)
        $segPat  = escapeshellarg("{$hlsDir}/{$r['name']}_%04d.ts");
        $m3u8    = escapeshellarg("{$hlsDir}/{$r['name']}.m3u8");

        $cmd = sprintf(
            '%s ffmpeg -i %s -vf %s -c:v libx264 -preset fast -crf %d '
            . '-sc_threshold 0 -g %d -keyint_min %d '
            . '-c:a aac -b:a %s '
            . '-hls_time %d -hls_list_size 0 -hls_segment_type mpegts '
            . '-hls_segment_filename %s -y %s 2>/dev/null',
            self::NICE,
            escapeshellarg($input),
            $r['scale'], $r['crf'],
            $gop, $gop,
            $r['audiobr'],
            self::HLS_SEGMENT,
            $segPat, $m3u8
        );
        exec($cmd, $_, $code);
        return (int) $code;
    }

    private static function optimalMp4Params(int $maxDim, int $w, int $h): array
    {
        $land = $w >= $h;
        if ($maxDim > 1280) return [$land ? '-vf scale=1280:-2' : '-vf scale=-2:1280', 26];
        if ($maxDim > 720)  return [$land ? '-vf scale=1280:-2' : '-vf scale=-2:1280', 27];
        if ($maxDim > 480)  return [$land ? '-vf scale=720:-2'  : '-vf scale=-2:720',  28];
        return ['', 28];
    }

    /**
     * Re-transcode HLS segments only from an already-optimized MP4.
     * Used to re-segment existing videos after changing HLS_SEGMENT.
     * Returns ['hls_url' => string] on success or ['error' => string] on failure.
     */
    public static function retranscodeHlsOnly(string $optimizedStoragePath): array
    {
        $inputPath = storage_path('app/public/' . $optimizedStoragePath);
        if (!file_exists($inputPath)) {
            return ['error' => "File not found: {$optimizedStoragePath}"];
        }

        $dir  = pathinfo($optimizedStoragePath, PATHINFO_DIRNAME);
        $name = pathinfo($optimizedStoragePath, PATHINFO_FILENAME); // 'optimized'
        // Output dir is the parent of the optimized.mp4 (e.g. community/abc/optimized → community/abc)
        $baseDir  = dirname($optimizedStoragePath);                 // e.g. community/videos/abc
        $baseName = basename($baseDir);                             // e.g. abc
        $parentDir = dirname($baseDir);                             // e.g. community/videos

        $hlsStorageDir = "{$baseDir}/hls";
        $hlsFullDir    = storage_path("app/public/{$hlsStorageDir}");
        @mkdir($hlsFullDir, 0755, true);

        // Remove old segments so stale 10s chunks don't linger
        foreach (glob("{$hlsFullDir}/*.ts") ?: [] as $old) @unlink($old);
        foreach (glob("{$hlsFullDir}/*.m3u8") ?: [] as $old) @unlink($old);

        // Probe the optimized MP4 for dimensions
        $probe  = shell_exec('ffprobe -v quiet -print_format json -show_streams '
            . escapeshellarg($inputPath) . ' 2>/dev/null');
        $info   = json_decode($probe, true) ?? [];
        $vStream = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        $width  = (int)($vStream['width']  ?? 1280);
        $height = (int)($vStream['height'] ?? 720);
        $maxDim = max($width, $height);

        $renditions   = self::buildRenditions($width, $height, $maxDim);
        $masterLines  = ['#EXTM3U', '#EXT-X-VERSION:3'];
        $successCount = 0;

        foreach ($renditions as $r) {
            $code = self::transcodeHls($inputPath, $hlsFullDir, $r);
            if ($code === 0) {
                $masterLines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['resolution']},NAME=\"{$r['name']}\"";
                $masterLines[] = "{$r['name']}.m3u8";
                $successCount++;
            }
        }

        if ($successCount === 0) {
            return ['error' => 'All renditions failed'];
        }

        file_put_contents("{$hlsFullDir}/master.m3u8", implode("\n", $masterLines) . "\n");

        return [
            'hls_url' => url("/hls/{$baseDir}/hls/master.m3u8"),
        ];
    }

    public static function getOptimalUrl(array $qualities): ?string
    {
        return $qualities['optimized']['url'] ?? null;
    }

    public static function getOptimalQuality(array $qualities): ?string
    {
        return self::getOptimalUrl($qualities);
    }
}
