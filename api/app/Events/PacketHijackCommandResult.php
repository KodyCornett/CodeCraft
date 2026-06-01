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
        public readonly ?array  $updatedPorts = null,        // Phase 2 port state after exploit/decode
        public readonly bool    $phaseAdvanced = false,      // true when inject succeeded
        public readonly ?string $lockUntil = null,           // ISO-8601 if honeypot lock applied
        public readonly ?array  $updatedSuspects = null,       // full suspect board after netstat
        public readonly ?array  $suspectUpdate = null,        // single suspect attribute reveal
        public readonly ?array  $arpScanResult = null,        // full arp timestamp sweep
        public readonly ?string $octetClue = null,            // sniff clue e.g. '.4.'
        public readonly ?string $flushedIp = null,            // ip marked as flushed
        public readonly ?array  $fingerprintUpdate = null,    // Phase 2 fingerprint state
        public readonly ?array  $portScanResult = null,       // Phase 2 scan result (port list)
        public readonly bool    $awaitingAuth = false,        // breach succeeded, show auth prompt
        public readonly ?array  $filesystemUpdate = null,     // Phase 3 filesystem state
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
            'match_id'        => $this->matchId,
            'command'         => $this->command,
            'output_lines'    => $this->outputLines,
            'updated_ports'   => $this->updatedPorts,
            'phase_advanced'  => $this->phaseAdvanced,
            'lock_until'      => $this->lockUntil,
            'updated_suspects'  => $this->updatedSuspects,
            'suspect_update'    => $this->suspectUpdate,
            'arp_scan_result'   => $this->arpScanResult,
            'octet_clue'        => $this->octetClue,
            'flushed_ip'        => $this->flushedIp,
            'fingerprint_update'=> $this->fingerprintUpdate,
            'port_scan_result'  => $this->portScanResult,
            'awaiting_auth'     => $this->awaitingAuth,
            'filesystem_update' => $this->filesystemUpdate,
        ];
    }
}
