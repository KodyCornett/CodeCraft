<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NodeTrace
 *
 * A "data fragment" left on a node by a hacking player. Visible to all
 * players who inspect that node's info, surfacing the hacker's handle and
 * a countdown until the trace fades.
 *
 * Lifecycle: written/refreshed by NodeController::deplete on every
 * successful hack (unless suppressed by a stealth command). Read by
 * NodeController::traces, which filters out rows where expires_at <= now().
 */
class NodeTrace extends Model
{
    use HasFactory, HasUuids;

    /** Default trace lifetime — used by callers when creating/refreshing. */
    public const DEFAULT_TTL_SECONDS = 300; // 5 minutes

    protected $fillable = [
        'node_id',
        'player_id',
        'expires_at',
        'is_decoy',
        'fake_handle',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
