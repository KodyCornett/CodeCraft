<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreetDoc extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['node_id', 'district_id', 'name'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
