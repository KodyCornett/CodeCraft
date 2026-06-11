<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerWatcherMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'player_id',
        'watcher_message_id',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function watcherMessage(): BelongsTo
    {
        return $this->belongsTo(WatcherMessage::class);
    }
}
