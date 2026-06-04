<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreetDocCatalog extends Model
{
    use HasUuids;

    protected $table = 'street_doc_catalog';

    protected $fillable = [
        'street_doc_id',
        'item_type',
        'item_id',
        'is_exclusive',
        'stock_limit',
        'source',
        'available_until',
    ];

    protected $casts = [
        'is_exclusive'    => 'boolean',
        'stock_limit'     => 'integer',
        'available_until' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function streetDoc(): BelongsTo
    {
        return $this->belongsTo(StreetDoc::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Items that are currently available (not expired).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('available_until')
              ->orWhere('available_until', '>', now());
        });
    }

    /**
     * Items that belong to a specific doc OR are global (street_doc_id IS NULL).
     */
    public function scopeForDoc($query, string $streetDocId)
    {
        return $query->where(function ($q) use ($streetDocId) {
            $q->where('street_doc_id', $streetDocId)
              ->orWhereNull('street_doc_id');
        });
    }
}
