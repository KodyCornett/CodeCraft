<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRig extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'player_id',
        'chassis_template_id',
        'cpu_level',
        'ram_level',
        'firewall_level',
        'storage_level',
        'os_level',
        'current_ss',
        'current_uplink',
        'is_limping',
    ];

    protected $casts = [
        'cpu_level'      => 'integer',
        'ram_level'      => 'integer',
        'firewall_level' => 'integer',
        'storage_level'  => 'integer',
        'os_level'       => 'integer',
        'current_ss'     => 'integer',
        'current_uplink' => 'integer',
        'is_limping'     => 'boolean',
    ];

    public function chassis(): BelongsTo
    {
        return $this->belongsTo(ChassisTemplate::class, 'chassis_template_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
