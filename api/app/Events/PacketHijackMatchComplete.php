<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired the moment a verified malware inject payload wins the match.
 *
 * Dispatched TWICE by the controller — once per participant — so both
 * players receive an immediate terminal abort on their private channels.
 * The `is_winner` flag lets each client render the appropriate outcome screen.
 */
class PacketHijackMatchComplete implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $matchId,
        public readonly string $playerId,
        public readonly bool   $isWinner,
        public readonly string $winnerId,
        public readonly string $loserId,
        public readonly int    $credsStolen,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->playerId);
    }

    public function broadcastAs(): string
    {
        return 'packet-hijack.complete';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id'     => $this->matchId,
            'is_winner'    => $this->isWinner,
            'winner_id'    => $this->winnerId,
            'loser_id'     => $this->loserId,
            'creds_stolen' => $this->credsStolen,
        ];
    }
}
