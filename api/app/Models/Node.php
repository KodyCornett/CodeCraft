<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Node extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'canvas_id',
        'x',
        'y',
        'type',
        'district',
        'ice',
        'tier',
        'cred_value_base',
        'cred_resource_depleted',
        'cred_last_hacked_at',
        'movement_resource_depleted',
        'is_spawn',
        'movement_last_hacked_at',
        'zone_type',
        'zone_group',
    ];

    protected $casts = [
        'x'                          => 'float',
        'y'                          => 'float',
        'ice'                        => 'integer',
        'tier'                       => 'integer',
        'cred_value_base'            => 'integer',
        'cred_resource_depleted'     => 'boolean',
        'movement_resource_depleted' => 'boolean',
        'is_spawn'                   => 'boolean',
        'cred_last_hacked_at'        => 'datetime',
        'movement_last_hacked_at'    => 'datetime',
    ];

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
