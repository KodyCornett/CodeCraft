<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NodeTrap
 *
 * A server-persisted trap placed on a node by a player via a map command
 * (Crash, Packet Flood, OS Exploit, Buffer Overflow, RootKit).
 *
 * Lifecycle:
 *   • Created by PlayerController::placeTrap() when a player fires a trap command.
 *   • placer_moves_left decrements each time the PLACER moves (position() call).
 *   • consumed is set to true when the first OTHER player steps on the node.
 *   • Expired rows (consumed OR expires_at past OR placer_moves_left = 0) are
 *     pruned lazily in position().
 *
 * Only the placer can see their own trap markers (via GET /api/player/traps).
 * Victims have no warning — stepping on a node triggers the effect silently.
 */
class NodeTrap extends Model
{
    use HasUuids;

    public const DEFAULT_TTL_MINUTES = 5;

    protected $fillable = [
        'placer_id',
        'node_id',
        'command_name',
        'effect_data',
        'placer_moves_left',
        'consumed',
        'expires_at',
    ];

    protected $casts = [
        'effect_data'       => 'array',
        'consumed'          => 'boolean',
        'expires_at'        => 'datetime',
        'placer_moves_left' => 'integer',
    ];

    public function placer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'placer_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /** True if this trap can still fire. */
    public function isActive(): bool
    {
        return ! $this->consumed
            && $this->placer_moves_left > 0
            && $this->expires_at->isFuture();
    }
}
