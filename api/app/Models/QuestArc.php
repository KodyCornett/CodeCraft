<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestArc extends Model
{
    use HasUuids;

    protected $fillable = [
        'cyber_doc_id',
        'sequence_order',
        'title',
        'rep_required',
        'is_entry_arc',
    ];

    protected $casts = [
        'sequence_order' => 'integer',
        'rep_required'   => 'integer',
        'is_entry_arc'   => 'boolean',
    ];

    public function cyberDoc(): BelongsTo
    {
        return $this->belongsTo(CyberDoc::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(QuestStage::class)->orderBy('stage_number');
    }
}
