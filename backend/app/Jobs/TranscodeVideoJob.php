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

    public int $timeout    = 14400; // 4 hours max (supports very long videos)
    public int $tries      = 1;     // Don't retry — if FFmpeg fails, fail fast
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
            $result = VideoProcessingService::process(
                $this->rawStoragePath,
                // Progress callback
                function (int $pct) use ($media) {
                    $media->update(['transcoding_progress' => $pct]);
                },
                // MP4 ready callback — fires BEFORE HLS starts.
                // Mark video_ready=true immediately so it appears in the feed
                // while HLS continues generating in the background.
                function (array $partial) use ($media) {
                    $earlyUpdates = ['transcoding_status' => 'processing', 'transcoding_progress' => 50];
                    if (!empty($partial['qualities']['optimized']['url'])) {
                        $earlyUpdates['url'] = $partial['qualities']['optimized']['url'];
                    }
                    if (!empty($partial['thumbnail'])) {
                        $earlyUpdates['thumbnail'] = cdn_url($partial['thumbnail']);
                    }
                    if (!empty($partial['duration'])) $earlyUpdates['duration'] = (int) $partial['duration'];
                    if (!empty($partial['width']))    $earlyUpdates['width']    = (int) $partial['width'];
                    if (!empty($partial['height']))   $earlyUpdates['height']   = (int) $partial['height'];
                    $media->update($earlyUpdates);

                    \App\Models\CommunityPost::where('id', $media->post_id)->update(['video_ready' => true]);

                    try {
                        RealtimeService::toUser($this->postOwnerId, 'post.media_ready', [
                            'media_id'  => $this->mediaId,
                            'post_id'   => $media->post_id,
                            'hls_url'   => null, // HLS not ready yet
                            'thumbnail' => $earlyUpdates['thumbnail'] ?? null,
                        ]);
                        RealtimeService::toPublic('community.feed', 'feed.new_post', [
                            'post_id' => $media->post_id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("[TranscodeVideoJob] Early realtime notify failed: " . $e->getMessage());
                    }
                }
            );

            if (!empty($result['error'])) {
                $this->markFailed($media, $result['error']);
                return;
            }

            // Final update — adds HLS URL now that it is ready
            $updates = ['transcoding_status' => 'ready', 'transcoding_progress' => 100];
            if (!empty($result['qualities']['hls']['url'])) {
                $updates['hls_url'] = $result['qualities']['hls']['url'];
            }
            $media->update($updates);

            // video_ready was already set in mp4ReadyFn; set again to be safe
            \App\Models\CommunityPost::where('id', $media->post_id)->update(['video_ready' => true]);

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
