<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CyberDoc extends Model
{
    use HasUuids;

    protected $table = 'cyber_docs';

    protected $fillable = ['node_id', 'district', 'name'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
