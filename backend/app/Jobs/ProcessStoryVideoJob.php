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

    public int $timeout    = 300; // 5 min max — stories are short and compressed on upload
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
            $result = VideoProcessingService::processStory($this->rawPath);

            $update = [];
            if (!empty($result['url']))       $update['media_url'] = $result['url'];
            if (!empty($result['thumbnail'])) $update['thumbnail'] = $result['thumbnail_path'];
            if ($update) $story->update($update);
        } catch (\Throwable $e) {
            Log::warning("[ProcessStoryVideoJob] Story #{$this->storyId} failed: " . $e->getMessage());
            // Keep original raw URL — story remains watchable, just uncompressed
        }
    }
}
