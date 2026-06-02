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
        // Common
        public readonly bool    $phaseAdvanced = false,      // true when phase advances (inject, auth success)
        public readonly ?string $lockUntil = null,           // ISO-8601 if honeypot lock applied
        // Phase 1
        public readonly ?array  $updatedSuspects = null,     // full suspect board after netstat
        public readonly ?array  $suspectUpdate = null,       // single suspect attribute reveal
        public readonly ?array  $arpScanResult = null,       // full arp timestamp sweep
        public readonly ?string $octetClue = null,           // sniff clue e.g. '.4.'
        public readonly ?string $flushedIp = null,           // ip marked as flushed
        // Phase 2 — redesigned exploit chain
        public readonly ?array  $portScanResult = null,      // scan: full port board (public view)
        public readonly ?int    $portProbed = null,          // probe: port number now marked probed
        public readonly ?array  $traceConfirmed = null,      // trace: [port1, port2] if chain link confirmed; null if no correlation
        public readonly ?int    $traceAttempts = null,       // trace: remaining attempt count after this command
        public readonly ?int    $portShattered = null,       // exploit: port number now shattered
        public readonly ?array  $credentialState = null,     // exploit/auth-fail: updated { hostname, os } display
        public readonly bool    $awaitingAuth = false,       // breach: open auth prompt
        public readonly bool    $authFailed = false,         // auth: rejected — dismiss prompt
        // Phase 2 legacy / rig commands
        public readonly ?array  $updatedPorts = null,        // rig commands that mutate opponent port state
        public readonly ?array  $fingerprintUpdate = null,   // kept for rig-command compat (phase_shift etc.)
        // Phase 3
        public readonly ?array  $filesystemUpdate = null,    // Phase 3 filesystem state
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
            'match_id'          => $this->matchId,
            'command'           => $this->command,
            'output_lines'      => $this->outputLines,
            // Common
            'phase_advanced'    => $this->phaseAdvanced,
            'lock_until'        => $this->lockUntil,
            // Phase 1
            'updated_suspects'  => $this->updatedSuspects,
            'suspect_update'    => $this->suspectUpdate,
            'arp_scan_result'   => $this->arpScanResult,
            'octet_clue'        => $this->octetClue,
            'flushed_ip'        => $this->flushedIp,
            // Phase 2 — redesigned
            'port_scan_result'  => $this->portScanResult,
            'port_probed'       => $this->portProbed,
            'trace_confirmed'   => $this->traceConfirmed,
            'trace_attempts'    => $this->traceAttempts,
            'port_shattered'    => $this->portShattered,
            'credential_state'  => $this->credentialState,
            'awaiting_auth'     => $this->awaitingAuth,
            'auth_failed'       => $this->authFailed,
            // Phase 2 legacy / rig commands
            'updated_ports'     => $this->updatedPorts,
            'fingerprint_update'=> $this->fingerprintUpdate,
            // Phase 3
            'filesystem_update' => $this->filesystemUpdate,
        ];
    }
}
