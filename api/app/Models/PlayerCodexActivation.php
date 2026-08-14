<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCodexActivation extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'thread_key',
        'source_quest_stage_id',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function sourceStage(): BelongsTo
    {
        return $this->belongsTo(QuestStage::class, 'source_quest_stage_id');
    }
}
