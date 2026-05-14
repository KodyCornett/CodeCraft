<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChassisTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'base_cpu',
        'base_ram',
        'base_firewall',
        'base_storage',
        'base_os',
        'peripheral_slots',
        'total_point_cap',
    ];

    protected $casts = [
        'base_cpu'        => 'integer',
        'base_ram'        => 'integer',
        'base_firewall'   => 'integer',
        'base_storage'    => 'integer',
        'peripheral_slots' => 'integer',
        'total_point_cap' => 'integer',
    ];

    public function playerRigs(): HasMany
    {
        return $this->hasMany(PlayerRig::class, 'chassis_template_id');
    }
}
