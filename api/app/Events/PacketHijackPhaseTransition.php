<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a player completes Phase 1 and advances to Phase 2.
 *
 * Dispatched TWICE by the controller with different payloads:
 *   1. To the advancing player  → port topology for Phase 2.
 *   2. To the opponent          → critical alert only (no topology data).
 *
 * The `alert_only` flag tells the frontend which path to render.
 */
class PacketHijackPhaseTransition implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $matchId,
        public readonly string  $playerId,
        public readonly bool    $alertOnly,  // true = opponent alert; false = own phase-2 start
        public readonly ?array  $ports = null,
        public readonly ?string $targetIp = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->playerId);
    }

    public function broadcastAs(): string
    {
        return 'packet-hijack.phase-transition';
    }

    public function broadcastWith(): array
    {
        if ($this->alertOnly) {
            return [
                'match_id'   => $this->matchId,
                'alert_only' => true,
                'alert'      => 'CRITICAL ALERT: ACTIVE INTRUSION IMMINENT',
            ];
        }

        return [
            'match_id'   => $this->matchId,
            'alert_only' => false,
            'ports'      => $this->ports,
            'target_ip'  => $this->targetIp,
        ];
    }
}
