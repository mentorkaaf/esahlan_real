<?php
namespace App\Jobs;

use App\Models\CommunityFollow;
use App\Models\LiveRoom;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLiveNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public int $roomId) {}

    public function handle(): void
    {
        $room = LiveRoom::with('host.communityProfile')->find($this->roomId);
        if (!$room || $room->status !== 'live') return;

        $host   = $room->host;
        $avatar = $host?->communityProfile?->avatar ?? '';
        $name   = $host?->name ?? 'Someone';

        // Get all followers' FCM tokens in chunks to avoid memory issues
        CommunityFollow::where('following_id', $room->host_id)
            ->whereNotNull('follower_id')
            ->with('follower:id,fcm_token')
            ->chunkById(200, function ($follows) use ($room, $name, $avatar) {
                foreach ($follows as $follow) {
                    $token = $follow->follower?->fcm_token;
                    if (empty($token)) continue;

                    FcmService::sendToToken(
                        $token,
                        "{$name} is LIVE now! 🔴",
                        $room->title,
                        [
                            'type'      => 'live_started',
                            'room_id'   => (string) $room->id,
                            'host_name' => $name,
                            'avatar'    => $avatar,
                            'deep_link' => "/live/view?room_id={$room->id}",
                        ],
                        $avatar ?: null,
                    );
                }
            });
    }
}
