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
 * `protected` marks the system-seeded structure (Documents/Downloads
 * folders and the starter reference docs — see FileService::ensureSeeded())
 * that the player can't move or delete. Anything else — added by the player
 * from inside a folder, or dropped in by a game system via
 * FileService::depositDownload() — is unprotected and deletable.
 *
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
        'protected',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'protected'  => 'boolean',
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
