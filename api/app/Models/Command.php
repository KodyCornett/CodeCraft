<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Command extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'type', 'description'];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_commands')
            ->withPivot('is_active');
    }
}
