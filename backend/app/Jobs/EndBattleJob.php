<?php
namespace App\Jobs;

use App\Models\LiveBattle;
use App\Services\RealtimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EndBattleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $battleId) {}

    public function handle(): void
    {
        $battle = LiveBattle::with('participants.host.communityProfile')->find($this->battleId);
        if (!$battle || $battle->status !== 'active') return;

        // Determine winner (highest score)
        $winner = $battle->participants->sortByDesc('score')->first();

        $battle->update([
            'status'          => 'ended',
            'winner_host_id'  => $winner?->host_id,
        ]);

        // Update final ranks
        $ranked = $battle->participants->sortByDesc('score')->values();
        foreach ($ranked as $i => $p) {
            $p->update(['rank' => $i + 1]);
        }

        $scores = $ranked->map(fn($p, $i) => [
            'host_id'      => $p->host_id,
            'live_room_id' => $p->live_room_id,
            'name'         => $p->host?->name ?? '',
            'score'        => $p->score,
            'rank'         => $i + 1,
            'is_winner'    => $p->host_id === $winner?->host_id,
        ])->values()->all();

        $battleData = [
            'id'               => $battle->id,
            'status'           => 'ended',
            'duration_seconds' => $battle->duration_seconds,
            'ends_at'          => $battle->ends_at?->toIso8601String(),
            'winner_host_id'   => $winner?->host_id,
            'participants'     => $ranked->map(fn($p, $i) => [
                'host_id'      => $p->host_id,
                'live_room_id' => $p->live_room_id,
                'name'         => $p->host?->name ?? '',
                'username'     => $p->host?->communityProfile?->username ?? '',
                'avatar'       => $p->host?->communityProfile?->avatar ?? '',
                'score'        => $p->score,
                'rank'         => $i + 1,
            ])->values()->all(),
        ];

        // Broadcast battle ended to all participant rooms
        foreach ($battle->participants as $p) {
            RealtimeService::toPublic("live.{$p->live_room_id}", 'battle.ended', [
                'battle'         => $battleData,
                'winner_host_id' => $winner?->host_id,
                'winner_name'    => $winner?->host?->name ?? '',
                'scores'         => $scores,
            ]);
        }
    }
}
