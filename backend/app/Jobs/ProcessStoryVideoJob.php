<?php

namespace App\Jobs;

use App\Models\CommunityStory;
use App\Services\VideoProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStoryVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout    = 1800; // 30 min max for stories
    public int $tries      = 1;
    public int $maxExceptions = 1;

    public function __construct(
        private int    $storyId,
        private string $rawPath
    ) {
        $this->onQueue('transcoding');
    }

    public function handle(): void
    {
        $story = CommunityStory::find($this->storyId);
        if (!$story) return;

        try {
            $result = VideoProcessingService::process($this->rawPath);

            if (!empty($result['qualities']['optimized']['url'])) {
                $story->update(['media_url' => $result['qualities']['optimized']['url']]);
            }
        } catch (\Throwable $e) {
            Log::warning("[ProcessStoryVideoJob] Story #{$this->storyId} processing failed: " . $e->getMessage());
            // Keep the original raw URL — story remains watchable, just uncompressed
        }
    }
}
