<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a player executes any terminal command.
 * Broadcast only to the executing player on their private channel.
 * The opponent never sees raw command output — only the phase-transition
 * alert and the match-complete event.
 */
class PacketHijackCommandResult implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $matchId,
        public readonly string  $playerId,
        public readonly string  $command,
        public readonly array   $outputLines,
        public readonly ?array  $updatedPorts = null,    // Phase 2 port state after exploit
        public readonly bool    $phaseAdvanced = false,  // true when inject succeeded
        public readonly ?string $lockUntil = null,       // ISO-8601 if honeypot lock applied
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('player.' . $this->playerId);
    }

    public function broadcastAs(): string
    {
        return 'packet-hijack.command-result';
    }

    public function broadcastWith(): array
    {
        return [
            'match_id'      => $this->matchId,
            'command'       => $this->command,
            'output_lines'  => $this->outputLines,
            'updated_ports' => $this->updatedPorts,
            'phase_advanced'=> $this->phaseAdvanced,
            'lock_until'    => $this->lockUntil,
        ];
    }
}
