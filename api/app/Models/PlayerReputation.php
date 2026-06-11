<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerReputation extends Model
{
    use HasUuids;

    protected $table = 'player_reputation';

    protected $fillable = ['player_id', 'cyber_doc_id', 'score'];

    protected $casts = ['score' => 'integer'];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function cyberDoc(): BelongsTo
    {
        return $this->belongsTo(CyberDoc::class);
    }
}
