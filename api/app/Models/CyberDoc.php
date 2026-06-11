<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CyberDoc extends Model
{
    use HasUuids;

    protected $table = 'cyber_docs';

    protected $fillable = ['node_id', 'district', 'name'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function questArcs(): HasMany
    {
        return $this->hasMany(QuestArc::class)->orderBy('sequence_order');
    }

    public function playerReputations(): HasMany
    {
        return $this->hasMany(PlayerReputation::class);
    }
}
