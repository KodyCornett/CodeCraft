<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatChallenge extends Model
{
    use HasUuids;

    protected $fillable = [
        'challenger_id',
        'target_id',
        'node_canvas_id',
        'status',
        'expires_at',
        'challenger_score',
        'target_score',
        'result_payload',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'challenger_score' => 'integer',
        'target_score'     => 'integer',
        'result_payload'   => 'array',
    ];

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'challenger_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'target_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
