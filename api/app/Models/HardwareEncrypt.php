<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A loot item in a player's inventory.
 * Installing it creates a PlayerPeripheral record on their rig.
 */
class HardwareEncrypt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['player_id', 'peripheral_id', 'is_installed'];

    protected $casts = ['is_installed' => 'boolean'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function peripheral(): BelongsTo
    {
        return $this->belongsTo(Peripheral::class);
    }
}
