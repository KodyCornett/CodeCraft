<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SplicePage extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'type',
        'title',
        'body',
        'unlocked_body',
        'thread_key',
        'login_username',
        'credentials',
        'lead_slugs',
        'reward_creds',
        'reward_tech_points',
    ];

    protected $casts = [
        'credentials'         => 'array',
        'lead_slugs'          => 'array',
        'reward_creds'        => 'integer',
        'reward_tech_points'  => 'decimal:2',
    ];

    public function isCodex(): bool
    {
        return $this->type === 'codex';
    }
}
