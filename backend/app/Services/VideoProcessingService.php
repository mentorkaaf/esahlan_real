<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class VideoProcessingService
{
    public static function process(string $storagePath): array
    {
        $inputPath = storage_path("app/public/" . $storagePath);
        if (!file_exists($inputPath)) return ["error" => "File not found"];

        $dir = pathinfo($storagePath, PATHINFO_DIRNAME);
        $name = pathinfo($storagePath, PATHINFO_FILENAME);
        $outputDir = storage_path("app/public/{$dir}/{$name}");
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

        $probe = shell_exec("ffprobe -v quiet -print_format json -show_streams -show_format " . escapeshellarg($inputPath) . " 2>/dev/null");
        $info = json_decode($probe, true);
        $videoStream = collect($info["streams"] ?? [])->firstWhere("codec_type", "video");
        $width = (int)($videoStream["width"] ?? 1280);
        $height = (int)($videoStream["height"] ?? 720);
        $duration = (float)($info["format"]["duration"] ?? 0);
        $fileSizeMB = filesize($inputPath) / 1048576;
        $maxDim = max($width, $height);

        $results = ["original" => $storagePath, "duration" => $duration, "width" => $width, "height" => $height, "qualities" => []];

        // Thumbnail
        $thumbPath = "{$dir}/{$name}/thumb.jpg";
        shell_exec("ffmpeg -i " . escapeshellarg($inputPath) . " -ss 00:00:01 -vframes 1 -q:v 5 -vf scale=480:-2 " . escapeshellarg(storage_path("app/public/" . $thumbPath)) . " -y 2>/dev/null");
        if (file_exists(storage_path("app/public/" . $thumbPath))) $results["thumbnail"] = $thumbPath;

        // === Optimized MP4 ===
        // Scale down: if > 720p → 720p, if 480-720p → 480p, if <=480p → keep resolution
        $optPath = "{$dir}/{$name}/optimized.mp4";
        $optFull = storage_path("app/public/" . $optPath);
        
        $scaleFilter = "";
        $crf = 28;
        if ($maxDim > 1280) {
            $scaleFilter = $width > $height ? "-vf scale=1280:-2" : "-vf scale=-2:1280";
            $crf = 26;
        } elseif ($maxDim > 720) {
            $scaleFilter = $width > $height ? "-vf scale=720:-2" : "-vf scale=-2:720";
            $crf = 27;
        } elseif ($maxDim > 480 && $fileSizeMB > 5) {
            $scaleFilter = $width > $height ? "-vf scale=480:-2" : "-vf scale=-2:480";
            $crf = 28;
        }
        // Skip re-encode if already small (< 2MB) and <=480p
        if ($fileSizeMB < 2 && $maxDim <= 480) {
            copy($inputPath, $optFull);
            // Just add faststart
            $tmpPath = $optFull . '.tmp.mp4';
            exec(sprintf("ffmpeg -i %s -c copy -movflags +faststart -y %s 2>/dev/null", escapeshellarg($optFull), escapeshellarg($tmpPath)), $out, $code);
            if ($code === 0 && file_exists($tmpPath)) { rename($tmpPath, $optFull); } else { @unlink($tmpPath); }
        } else {
            $cmd = sprintf("ffmpeg -i %s %s -c:v libx264 -preset fast -crf %d -c:a aac -b:a 96k -movflags +faststart -y %s 2>/dev/null",
                escapeshellarg($inputPath), $scaleFilter, $crf, escapeshellarg($optFull));
            exec($cmd, $out, $code);
        }
        
        if (file_exists($optFull)) {
            $results["qualities"]["optimized"] = [
                "path" => $optPath,
                "size" => filesize($optFull),
                "url" => url("/api/v1/media?f=" . $optPath),
            ];
        }

        // === HLS Adaptive Streaming ===
        $hlsDir = "{$outputDir}/hls";
        if (!is_dir($hlsDir)) mkdir($hlsDir, 0755, true);

        // 480p HLS
        $scale480 = $width > $height ? "scale=480:-2" : "scale=-2:480";
        if ($maxDim <= 480) $scale480 = "scale={$width}:{$height}"; // Keep original if already small
        $cmd480 = sprintf(
            "ffmpeg -i %s -vf %s -c:v libx264 -preset fast -crf 28 -c:a aac -b:a 96k -hls_time 6 -hls_list_size 0 -hls_segment_filename %s -y %s 2>&1",
            escapeshellarg($inputPath), $scale480,
            escapeshellarg($hlsDir . "/480p_%03d.ts"),
            escapeshellarg($hlsDir . "/480p.m3u8")
        );
        exec($cmd480, $out480, $code480);

        // 720p HLS (only if source is 720p+)
        $has720 = false;
        if ($maxDim >= 720) {
            $scale720 = $width > $height ? "scale=1280:-2" : "scale=-2:1280";
            if ($maxDim < 1280) $scale720 = "scale={$width}:{$height}";
            $cmd720 = sprintf(
                "ffmpeg -i %s -vf %s -c:v libx264 -preset fast -crf 26 -c:a aac -b:a 128k -hls_time 6 -hls_list_size 0 -hls_segment_filename %s -y %s 2>&1",
                escapeshellarg($inputPath), $scale720,
                escapeshellarg($hlsDir . "/720p_%03d.ts"),
                escapeshellarg($hlsDir . "/720p.m3u8")
            );
            exec($cmd720, $out720, $code720);
            $has720 = ($code720 === 0);
        }

        // Master playlist
        $master = "#EXTM3U\n#EXT-X-VERSION:3\n";
        if ($code480 === 0) {
            $res480 = $width > $height ? "480x" . round(480 * $height / $width) : round(480 * $width / $height) . "x480";
            $master .= "#EXT-X-STREAM-INF:BANDWIDTH=800000,RESOLUTION={$res480}\n480p.m3u8\n";
        }
        if ($has720) {
            $res720 = $width > $height ? "1280x720" : "720x1280";
            $master .= "#EXT-X-STREAM-INF:BANDWIDTH=2000000,RESOLUTION={$res720}\n720p.m3u8\n";
        }
        file_put_contents($hlsDir . "/master.m3u8", $master);

        if ($code480 === 0 || $has720) {
            $results["qualities"]["hls"] = [
                "path" => "{$dir}/{$name}/hls/master.m3u8",
                "url" => url("/hls/{$dir}/{$name}/hls/master.m3u8"),
            ];
        }

        // Delete original upload to save disk space
        if (file_exists($optFull) && $inputPath !== $optFull) {
            @unlink($inputPath);
        }

        Log::info("Video processed: {$storagePath} ({$width}x{$height}, {}MB)", $results);
        return $results;
    }

    public static function getOptimalUrl(array $qualities): ?string
    {
        return $qualities["optimized"]["url"] ?? null;
    }

    public static function getOptimalQuality(array $qualities): ?string
    {
        return self::getOptimalUrl($qualities);
    }
}
