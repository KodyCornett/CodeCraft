<?php

namespace App\Models;

use App\Support\SpliceAddress;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Node extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'canvas_id',
        'splice_address',
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

    /**
     * Auto-generate a node's SPLICE address on creation if one wasn't
     * explicitly set. Registered in booted() (not boot()) so it runs after
     * HasUuids's own creating listener — that trait's boot() call is what
     * assigns $node->id, and it's registered first, so by the time this
     * listener fires the UUID is already in place. A writer can still
     * hand-set splice_address on a specific node (e.g. in a seeder) to
     * override the generated value entirely.
     */
    protected static function booted(): void
    {
        static::creating(function (Node $node) {
            if (empty($node->splice_address)) {
                $node->splice_address = SpliceAddress::generate($node->id, $node->district);
            }
        });
    }

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
