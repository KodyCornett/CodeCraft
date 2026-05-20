<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Command extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'tier', 'max_level', 'upgrade_cost_tp',
        'type', 'description',
        'price_creds', 'price_tp', 'target_type',
        'map_effect', 'hack_effect', 'duration',
    ];

    protected $casts = [
        'tier'            => 'integer',
        'max_level'       => 'integer',
        'upgrade_cost_tp' => 'integer',
        'price_creds'     => 'integer',
        'price_tp'        => 'integer',
        'duration'        => 'array',
    ];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_commands')
            ->withPivot('is_active', 'level');
    }
}
