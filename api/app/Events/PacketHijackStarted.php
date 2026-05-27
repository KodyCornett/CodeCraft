<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per participant when a Packet Hijack match initialises.
 * Dispatched twice by the controller — once for each player — so each
 * receives their own role and starting IP pool on their private channel.
 */
class PacketHijackStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $matchId,
        public readonly string $playerId,
        public readonly string $role,           // 'challenger' | 'defender'
        public readonly array  $ipPoolSample,   // 15-item opening sample
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->playerId);
    }

    public function broadcastAs(): string
    {
        return 'packet-hijack.started';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id'       => $this->matchId,
            'role'           => $this->role,
            'ip_pool_sample' => $this->ipPoolSample,
        ];
    }
}
