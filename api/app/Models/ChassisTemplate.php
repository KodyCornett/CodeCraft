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
        'tier',
        'base_cpu',
        'base_ram',
        'base_firewall',
        'base_storage',
        'base_uplink',
        'base_os',
        'base_os_level',
        'peripheral_slots',
        'total_point_cap',
        'cap_cpu',
        'cap_ram',
        'cap_firewall',
        'cap_storage',
        'cap_os',
    ];

    protected $casts = [
        'tier'             => 'integer',
        'base_cpu'         => 'integer',
        'base_ram'         => 'integer',
        'base_firewall'    => 'integer',
        'base_storage'     => 'integer',
        'base_uplink'      => 'integer',
        'base_os_level'    => 'integer',
        'peripheral_slots' => 'integer',
        'total_point_cap'  => 'integer',
        'cap_cpu'          => 'integer',
        'cap_ram'          => 'integer',
        'cap_firewall'     => 'integer',
        'cap_storage'      => 'integer',
        'cap_os'           => 'integer',
    ];

    public function playerRigs(): HasMany
    {
        return $this->hasMany(PlayerRig::class, 'chassis_template_id');
    }
}
