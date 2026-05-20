<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'handle',
        'current_node_id',
        'current_district',
        'bounty_level',
        'nodes_hacked_this_run',
        'pvp_wins_this_run',
        'bounty_multiplier',
        'bounty_district_snapshot',
        'is_open_season',
        'open_season_wins',
        'open_season_current_wins',
        'open_season_best_wins',
        'is_limping',
        'post_combat_silent_moves',
        'active_effects',
        'pocket_creds',
        'wallet_creds',
        'cache',
        'tech_points',
    ];

    protected $casts = [
        'bounty_level'             => 'integer',
        'nodes_hacked_this_run'    => 'integer',
        'pvp_wins_this_run'        => 'integer',
        'bounty_multiplier'        => 'decimal:2',
        'is_open_season'           => 'boolean',
        'open_season_wins'         => 'integer',
        'open_season_current_wins' => 'integer',
        'open_season_best_wins'    => 'integer',
        'is_limping'               => 'boolean',
        'post_combat_silent_moves' => 'integer',
        'active_effects'           => 'array',
        'pocket_creds'             => 'integer',
        'wallet_creds'             => 'integer',
        'cache'                    => 'integer',
        'tech_points'              => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rig(): HasOne
    {
        return $this->hasOne(PlayerRig::class, 'player_id');
    }

    public function playerPeripherals(): HasMany
    {
        return $this->hasMany(PlayerPeripheral::class);
    }

    public function commands(): BelongsToMany
    {
        return $this->belongsToMany(Command::class, 'player_commands')
            ->withPivot('is_active', 'level', 'loadout_slot');
    }

    public function hardwareEncrypts(): HasMany
    {
        return $this->hasMany(HardwareEncrypt::class);
    }

    public function playerConsumables(): HasMany
    {
        return $this->hasMany(PlayerConsumable::class);
    }
}
