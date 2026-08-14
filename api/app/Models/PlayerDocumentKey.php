<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDocumentKey extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'status',
        'resolved_splice_page_id',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function resolvedPage(): BelongsTo
    {
        return $this->belongsTo(SplicePage::class, 'resolved_splice_page_id');
    }
}
