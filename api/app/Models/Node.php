<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Node extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'district_id',
        'business_name',
        'latitude',
        'longitude',
        'tier',
        'cred_value_base',
        'cred_resource_depleted',
        'cred_last_hacked_at',
        'movement_resource_depleted',
        'movement_last_hacked_at',
    ];

    protected $casts = [
        'tier'                       => 'integer',
        'cred_value_base'            => 'integer',
        'cred_resource_depleted'     => 'boolean',
        'movement_resource_depleted' => 'boolean',
        'cred_last_hacked_at'        => 'datetime',
        'movement_last_hacked_at'    => 'datetime',
        'latitude'                   => 'decimal:7',
        'longitude'                  => 'decimal:7',
    ];

    public function streetDoc(): HasOne
    {
        return $this->hasOne(StreetDoc::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * All nodes directly reachable from this node.
     * Stored bidirectionally in node_connections, so querying node_id is enough.
     */
    public function adjacentNodes(): BelongsToMany
    {
        return $this->belongsToMany(
            Node::class,
            'node_connections',
            'node_id',
            'connected_node_id',
        );
    }
}
