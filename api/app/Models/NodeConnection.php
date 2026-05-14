<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one directed edge in the node adjacency list.
 * Connections are stored bidirectionally — (A→B) and (B→A) both exist.
 * Composite primary key: (node_id, connected_node_id).
 *
 * Prefer using Node::adjacentNodes() for queries; use this model (or
 * DB::table) for seeding/mutating connections directly.
 */
class NodeConnection extends Model
{
    public $timestamps    = false;
    public $incrementing  = false;
    protected $primaryKey = null;

    protected $fillable = ['node_id', 'connected_node_id'];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function connectedNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'connected_node_id');
    }
}
