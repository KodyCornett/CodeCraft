<?php

namespace App\Events;

use App\Models\CombatChallenge;
use App\Services\RigService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired the moment a PvP challenge is created.
 * Broadcasts on the target player's private channel so they receive the
 * incoming challenge overlay instantly — replacing the 2s pending poll.
 */
class CombatChallengeReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly CombatChallenge $challenge) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->challenge->target_id);
    }

    public function broadcastAs(): string
    {
        return 'challenge.received';
    }

    public function broadcastWith(): array
    {
        $challenger = $this->challenge->challenger()->with(['rig.chassis', 'playerPeripherals.peripheral'])->first();

        if ($challenger === null) {
            // Challenger record missing — suppress payload so the frontend sees null
            return ['challenge' => null];
        }

        $effectiveFirewall = 1;
        if ($challenger->rig) {
            $effectiveFirewall = app(RigService::class)
                ->effectiveStats($challenger->rig, $challenger)['firewall']['effective'];
        }

        return [
            'challenge' => [
                'id'             => $this->challenge->id,
                'challenger'     => [
                    'id'                 => $challenger->id,
                    'handle'             => $challenger->handle,
                    'bounty_level'       => (int)  $challenger->bounty_level,
                    'is_open_season'     => (bool) $challenger->is_open_season,
                    'pocket_creds'       => (int)  ($challenger->pocket_creds ?? 0),
                    'effective_firewall' => $effectiveFirewall,
                ],
                'node_canvas_id' => $this->challenge->node_canvas_id,
                'expires_at'     => $this->challenge->expires_at->toIso8601String(),
            ],
        ];
    }
}
