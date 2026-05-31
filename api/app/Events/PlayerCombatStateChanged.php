<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a player enters or exits active PvP combat on a node.
 * Broadcasts on the node's presence channel so any third party standing
 * on the same node sees the [HACK] button appear/disappear in real time.
 *
 * Dispatched:
 *   in_combat: true  — from CombatChallengeController::accept() for both players
 *   in_combat: false — from CombatController::resolve() for both players
 */
class PlayerCombatStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $playerId,
        public readonly string $canvasId,
        public readonly bool   $inCombat,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('node.' . $this->canvasId);
    }

    public function broadcastAs(): string
    {
        return 'combat.state.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->playerId,
            'in_combat' => $this->inCombat,
        ];
    }
}
