<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a trap placed by a player is consumed by a victim stepping on the node.
 *
 * Broadcasts on the placer's private channel so they receive real-time confirmation
 * that their trap fired, who triggered it, and on which node.
 *
 * Dispatched from: PlayerController::position() after $activeTrap->consumed = true.
 */
class TrapTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $placerId,
        public readonly string $commandName,
        public readonly string $victimHandle,
        public readonly string $nodeCanvasId,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->placerId);
    }

    public function broadcastAs(): string
    {
        return 'trap.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'command_name'  => $this->commandName,
            'victim_handle' => $this->victimHandle,
            'canvas_id'     => $this->nodeCanvasId,
        ];
    }
}
