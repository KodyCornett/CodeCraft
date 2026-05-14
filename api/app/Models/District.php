<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name'];

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }
}
