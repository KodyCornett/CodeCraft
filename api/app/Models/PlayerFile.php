<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PlayerFile
 *
 * A single node (folder or file) in a player's virtual file system, browsed
 * via the File Explorer OS program. Self-referencing tree via parent_id;
 * null parent_id is the player's root folder.
 *
 * Phase 1 is read-only (see CONTRACTS_AND_OS_REWORK_PLAN.md) — content is
 * seeded once per player by FileService and never mutated from the client.
 * Kept generic (plain text content, no schema coupling to any other system)
 * so a later mission can insert a file into a specific player's tree.
 */
class PlayerFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'parent_id',
        'name',
        'type',
        'extension',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PlayerFile::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PlayerFile::class, 'parent_id');
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }
}
