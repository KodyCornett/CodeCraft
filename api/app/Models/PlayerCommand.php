<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model for player_commands (composite PK — no id column).
 */
class PlayerCommand extends Model
{
    protected $table      = 'player_commands';
    protected $primaryKey = null;
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ['player_id', 'command_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }
}
