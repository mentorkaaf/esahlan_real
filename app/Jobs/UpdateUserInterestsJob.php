<?php
namespace App\Jobs;

use App\Services\FeedRankingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUserInterestsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;
    public int $uniqueFor = 60; // seconds

    public function __construct(public int $userId) {}

    /**
     * Collapse duplicate jobs for the same user already queued —
     * one interest recompute per user is enough even if they liked 5 posts in a row.
     */
    public function uniqueId(): string
    {
        return "update-interests-{$this->userId}";
    }

    public function handle(): void
    {
        FeedRankingService::updateUserInterests($this->userId);
    }
}
