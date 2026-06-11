<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerArcProgress extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'quest_arc_id',
        'status',
        'unlocked_at',
        'completed_at',
    ];

    protected $casts = [
        'unlocked_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function arc(): BelongsTo
    {
        return $this->belongsTo(QuestArc::class, 'quest_arc_id');
    }
}
