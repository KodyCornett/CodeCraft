<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStageProgress extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'quest_stage_id',
        'status',
        'turned_into_doc_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(QuestStage::class, 'quest_stage_id');
    }

    public function turnedIntoDoc(): BelongsTo
    {
        return $this->belongsTo(CyberDoc::class, 'turned_into_doc_id');
    }
}
