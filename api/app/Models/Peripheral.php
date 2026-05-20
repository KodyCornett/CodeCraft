<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peripheral extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'stat_boosted',
        'boost_amount',
        'rarity',
        'port_cost',
        'price_creds',
    ];

    protected $casts = [
        'boost_amount' => 'integer',
        'port_cost'    => 'integer',
        'price_creds'  => 'integer',
    ];

    public function playerPeripherals(): HasMany
    {
        return $this->hasMany(PlayerPeripheral::class);
    }
}
