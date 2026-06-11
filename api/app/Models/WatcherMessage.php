<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatcherMessage extends Model
{
    use HasUuids;

    protected $fillable = ['trigger_stage_id', 'signal_text'];

    public function triggerStage(): BelongsTo
    {
        return $this->belongsTo(QuestStage::class, 'trigger_stage_id');
    }
}
