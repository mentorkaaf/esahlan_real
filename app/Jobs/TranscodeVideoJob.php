<?php

namespace App\Jobs;

use App\Models\CommunityPostMedia;
use App\Services\VideoProcessingService;
use App\Services\RealtimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TranscodeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 14400;
    public int $tries      = 1;
    public int $maxExceptions = 1;

    public function __construct(
        private int $mediaId,
        private string $rawStoragePath,
        private int $postOwnerId
    ) {
        $this->onQueue('transcoding');
    }

    public function handle(): void
    {
        $media = CommunityPostMedia::find($this->mediaId);
        if (!$media) return;

        $media->update(['transcoding_status' => 'processing', 'transcoding_progress' => 5]);

        try {
            $result = VideoProcessingService::process($this->rawStoragePath, function (int $pct) use ($media) {
                $media->update(['transcoding_progress' => $pct]);
            });

            if (!empty($result['error'])) {
                $this->markFailed($media, $result['error']);
                return;
            }

            $updates = ['transcoding_status' => 'ready', 'transcoding_progress' => 100];

            if (!empty($result['thumbnail']))                  $updates['thumbnail'] = cdn_url($result['thumbnail']);
            if (!empty($result['qualities']['hls']['url']))    $updates['hls_url']   = $result['qualities']['hls']['url'];
            if (!empty($result['qualities']['optimized']['url'])) $updates['url']    = $result['qualities']['optimized']['url'];
            if (!empty($result['duration']))  $updates['duration'] = (int) $result['duration'];
            if (!empty($result['width']))     $updates['width']    = (int) $result['width'];
            if (!empty($result['height']))    $updates['height']   = (int) $result['height'];

            $media->update($updates);

            \App\Models\CommunityPost::where('id', $media->post_id)->update(['video_ready' => true]);

            RealtimeService::toUser($this->postOwnerId, 'post.media_ready', [
                'media_id'  => $this->mediaId,
                'post_id'   => $media->post_id,
                'hls_url'   => $updates['hls_url'] ?? null,
                'thumbnail' => $updates['thumbnail'] ?? null,
            ]);

            RealtimeService::toPublic('community.feed', 'feed.new_post', [
                'post_id' => $media->post_id,
            ]);

        } catch (\Throwable $e) {
            $this->markFailed($media, $e->getMessage());
        }
    }

    private function markFailed(CommunityPostMedia $media, string $reason): void
    {
        Log::error("[TranscodeVideoJob] Media #{$this->mediaId} failed: {$reason}");
        $media->update(['transcoding_status' => 'failed', 'transcoding_progress' => 0]);
    }

    public function failed(\Throwable $e): void
    {
        $media = CommunityPostMedia::find($this->mediaId);
        $media?->update(['transcoding_status' => 'failed', 'transcoding_progress' => 0]);
        Log::error("[TranscodeVideoJob] Job failed for media #{$this->mediaId}: " . $e->getMessage());
    }
}
