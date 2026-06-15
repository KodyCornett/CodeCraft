<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PacketHijackMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'challenger_id',
        'defender_id',
        'status',
        'is_practice',
        'winner_id',
        'challenger_target_ip',
        'defender_target_ip',
        'challenger_ip_pool',
        'challenger_suspects',
        'challenger_fingerprint',
        'challenger_filesystem',
        'defender_ip_pool',
        'defender_suspects',
        'defender_fingerprint',
        'defender_filesystem',
        'challenger_ports',
        'defender_ports',
        'challenger_phase',
        'defender_phase',
        'challenger_locked_until',
        'defender_locked_until',
        'challenger_overclock_active',
        'defender_overclock_active',
        'challenger_mirror_active',
        'defender_mirror_active',
        'challenger_corrupt_ports',
        'defender_corrupt_ports',
        'challenger_bait_ports',
        'defender_bait_ports',
        'challenger_used_commands',
        'defender_used_commands',
        'challenger_exploit_chain',
        'defender_exploit_chain',
        'challenger_trace_attempts',
        'defender_trace_attempts',
        'challenger_chain_progress',
        'defender_chain_progress',
        'challenger_credential_state',
        'defender_credential_state',
        'challenger_bank_balance',
        'defender_bank_balance',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'challenger_ip_pool'          => 'array',
        'challenger_suspects'         => 'array',
        'challenger_fingerprint'      => 'array',
        'challenger_filesystem'       => 'array',
        'defender_ip_pool'            => 'array',
        'defender_suspects'           => 'array',
        'defender_fingerprint'        => 'array',
        'defender_filesystem'         => 'array',
        'challenger_ports'            => 'array',
        'defender_ports'              => 'array',
        'challenger_phase'            => 'integer',
        'defender_phase'              => 'integer',
        'challenger_locked_until'     => 'datetime',
        'defender_locked_until'       => 'datetime',
        'challenger_overclock_active' => 'boolean',
        'defender_overclock_active'   => 'boolean',
        'challenger_mirror_active'    => 'boolean',
        'defender_mirror_active'      => 'boolean',
        'challenger_corrupt_ports'    => 'array',
        'defender_corrupt_ports'      => 'array',
        'challenger_bait_ports'       => 'array',
        'defender_bait_ports'         => 'array',
        'challenger_used_commands'    => 'array',
        'defender_used_commands'      => 'array',
        'challenger_exploit_chain'    => 'array',
        'defender_exploit_chain'      => 'array',
        'challenger_trace_attempts'   => 'integer',
        'defender_trace_attempts'     => 'integer',
        'challenger_chain_progress'   => 'integer',
        'defender_chain_progress'     => 'integer',
        'challenger_credential_state' => 'array',
        'defender_credential_state'   => 'array',
        'challenger_bank_balance'     => 'integer',
        'defender_bank_balance'       => 'integer',
        'started_at'                  => 'datetime',
        'completed_at'                => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'challenger_id');
    }

    public function defender(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'defender_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    /**
     * Return 'challenger' or 'defender' for the given player ID,
     * or null if the player is not a participant.
     */
    public function roleOf(string $playerId): ?string
    {
        if ($this->challenger_id === $playerId) return 'challenger';
        if ($this->defender_id   === $playerId) return 'defender';
        return null;
    }

    /**
     * Return the opponent player ID for a given participant.
     */
    public function opponentIdOf(string $playerId): ?string
    {
        if ($this->challenger_id === $playerId) return $this->defender_id;
        if ($this->defender_id   === $playerId) return $this->challenger_id;
        return null;
    }

    /**
     * Check whether a participant's input is currently honeypot-locked.
     */
    public function isLocked(string $role): bool
    {
        $lockedUntil = $role === 'challenger'
            ? $this->challenger_locked_until
            : $this->defender_locked_until;

        return $lockedUntil !== null && $lockedUntil->isFuture();
    }

    /**
     * Get the port array for a given role.
     */
    public function portsFor(string $role): array
    {
        return $role === 'challenger' ? ($this->challenger_ports ?? []) : ($this->defender_ports ?? []);
    }

    /**
     * Get the full suspect list for a given role (includes is_target — server only).
     */
    public function suspectsFor(string $role): array
    {
        $key = "{$role}_suspects";
        return $this->$key ?? [];
    }

    /**
     * Get the suspect list stripped of is_target for broadcasting to the client.
     * Only reveals attributes that have been populated — the raw object is safe
     * to send as-is since is_target is removed here.
     */
    public function suspectsPublicView(string $role): array
    {
        return array_map(function ($s) {
            $copy = $s;
            unset($copy['is_target']);
            return $copy;
        }, $this->suspectsFor($role));
    }

    /**
     * Get the target IP a given role must find (the opponent's rig IP).
     */
    public function targetIpFor(string $role): string
    {
        return $role === 'challenger' ? $this->challenger_target_ip : $this->defender_target_ip;
    }

    /**
     * Get the current phase for a given role.
     */
    public function phaseOf(string $role): int
    {
        return $role === 'challenger' ? $this->challenger_phase : $this->defender_phase;
    }

    /**
     * Get the full fingerprint for a given role (includes banner fragments — server only).
     */
    public function fingerprintFor(string $role): array
    {
        $key = "{$role}_fingerprint";
        return $this->$key ?? [];
    }

    /**
     * Get a safe public view of the fingerprint — strips server-only fragment data.
     * Sends port entries with banner lines but never the raw fragment value mapping.
     */
    public function fingerprintPublicView(string $role): array
    {
        $fp = $this->fingerprintFor($role);
        if (empty($fp)) return [];

        $safe        = $fp;
        $safe['ports'] = array_map(function ($p) {
            $entry = $p;
            unset($entry['fragment']); // never send to client
            return $entry;
        }, $fp['ports'] ?? []);

        return $safe;
    }

    /**
     * Get the filesystem for a given role.
     */
    public function filesystemFor(string $role): array
    {
        $key = "{$role}_filesystem";
        return $this->$key ?? [];
    }

    /**
     * Save fingerprint back to the match after mutation.
     */
    public function saveFingerprintFor(string $role, array $fingerprint): void
    {
        $key = "{$role}_fingerprint";
        // Use forceFill to guarantee Eloquent marks the JSON column dirty
        $this->forceFill([$key => $fingerprint]);
    }

    /**
     * Save filesystem back to the match after mutation.
     */
    public function saveFilesystemFor(string $role, array $filesystem): void
    {
        $key = "{$role}_filesystem";
        $this->forceFill([$key => $filesystem]);
    }

    // ── Phase 7 — Command state helpers ──────────────────────────────────────

    /** Whether the role's overclock buff is currently active (raised exploit threshold). */
    public function overclockActiveFor(string $role): bool
    {
        $key = "{$role}_overclock_active";
        return (bool) ($this->$key ?? false);
    }

    /** Whether the role has mirror protocol active (next opponent rig command is reflected). */
    public function mirrorActiveFor(string $role): bool
    {
        $key = "{$role}_mirror_active";
        return (bool) ($this->$key ?? false);
    }

    /**
     * Active sector-corrupt entries for a given role's port view.
     * Schema per entry: { port, fake_bias, expires_at (ISO-8601) }
     */
    public function corruptPortsFor(string $role): array
    {
        $key = "{$role}_corrupt_ports";
        return $this->$key ?? [];
    }

    /**
     * Active bait traps set against a given role's port exploits.
     * Schema per entry: { port, fake_bias, lock_seconds }
     */
    public function baitPortsFor(string $role): array
    {
        $key = "{$role}_bait_ports";
        return $this->$key ?? [];
    }

    /**
     * Slugs of rig commands already deployed by a given role this match
     * (one-use-per-match guard).
     */
    public function usedCommandsFor(string $role): array
    {
        $key = "{$role}_used_commands";
        return $this->$key ?? [];
    }

    // ── Phase 2 redesign — Exploit chain helpers ──────────────────────────────

    /**
     * The ordered exploit chain for a given role (port numbers, ending with 8080).
     */
    public function exploitChainFor(string $role): array
    {
        $key = "{$role}_exploit_chain";
        return $this->$key ?? [];
    }

    /**
     * How many chain ports have been shattered so far (0-indexed progress counter).
     */
    public function chainProgressFor(string $role): int
    {
        $key = "{$role}_chain_progress";
        return (int) ($this->$key ?? 0);
    }

    /**
     * The next port number the player must exploit in the chain.
     * Returns null if the chain is fully complete.
     */
    public function nextChainPortFor(string $role): ?int
    {
        $chain    = $this->exploitChainFor($role);
        $progress = $this->chainProgressFor($role);
        return $chain[$progress] ?? null;
    }

    /**
     * Whether all chain ports (including 8080) have been shattered for a role.
     */
    public function chainCompleteFor(string $role): bool
    {
        $chain    = $this->exploitChainFor($role);
        $progress = $this->chainProgressFor($role);
        return !empty($chain) && $progress >= count($chain);
    }

    /**
     * Remaining trace attempts for a given role.
     */
    public function traceAttemptsFor(string $role): int
    {
        $key = "{$role}_trace_attempts";
        return (int) ($this->$key ?? 0);
    }

    /**
     * The assembled credential state for a given role.
     * Schema: { hostname: string, os: string } — fills progressively.
     */
    public function credentialStateFor(string $role): array
    {
        $key = "{$role}_credential_state";
        return $this->$key ?? ['hostname' => null, 'os' => null];
    }

    /**
     * The opponent's pocket_creds snapshot taken at the moment of successful auth.
     * Displayed on the bank screen as the available balance.
     */
    public function bankBalanceFor(string $role): int
    {
        $key = "{$role}_bank_balance";
        return (int) ($this->$key ?? 0);
    }
}
