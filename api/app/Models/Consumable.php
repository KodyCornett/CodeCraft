<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'category',
        'stat',
        'boost_amount',
        'duration_moves',
        'rarity',
        'price_creds',
        'description',
    ];

    protected $casts = [
        'boost_amount'   => 'integer',
        'duration_moves' => 'integer',
        'price_creds'    => 'integer',
    ];

    public function playerConsumables(): HasMany
    {
        return $this->hasMany(PlayerConsumable::class);
    }
}
