<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Node;
use App\Services\NodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function __construct(private readonly NodeService $nodeService) {}

    /**
     * GET /api/nodes/{district}
     *
     * Returns all nodes in a district and their adjacency lists.
     * The Kotlin engine calls this on startup (once per district) to build
     * its local map cache. The {district} segment is the district name,
     * case-insensitive (e.g., "downtown", "South Hill").
     *
     * Each node includes an `adjacent_node_ids` array of UUIDs so the engine
     * can reconstruct the graph without a second round-trip.
     */
    public function byDistrict(string $district): JsonResponse
    {
        $found = District::whereRaw('LOWER(name) = ?', [strtolower($district)])->first();

        if ($found === null) {
            return response()->json(['message' => "District '{$district}' not found."], 404);
        }

        $nodes = $found->nodes()
            ->with(['adjacentNodes:id', 'streetDoc:id,node_id'])
            ->get()
            ->map(fn ($node) => [
                'id'                         => $node->id,
                'business_name'              => $node->business_name,
                'latitude'                   => $node->latitude,
                'longitude'                  => $node->longitude,
                'tier'                       => $node->tier,
                'cred_value_base'            => $node->cred_value_base,
                'cred_resource_depleted'     => $node->cred_resource_depleted,
                'cred_last_hacked_at'        => $node->cred_last_hacked_at,
                'movement_resource_depleted' => $node->movement_resource_depleted,
                'movement_last_hacked_at'    => $node->movement_last_hacked_at,
                'is_street_doc'              => $node->streetDoc !== null,
                'adjacent_node_ids'          => $node->adjacentNodes->pluck('id'),
            ]);

        return response()->json([
            'district'    => $found->name,
            'district_id' => $found->id,
            'nodes'       => $nodes,
        ]);
    }

    /**
     * POST /api/nodes/{nodeId}/deplete
     *
     * Marks a node resource as depleted after a successful hack.
     * Runs a replenish-check first so stale depletion is cleared before depleting again.
     */
    public function deplete(Request $request, string $nodeId): JsonResponse
    {
        $request->validate([
            'resource'  => ['required', 'in:creds,movement'],
            'player_id' => ['required', 'string'],
        ]);

        $node = Node::find($nodeId);

        if ($node === null) {
            return response()->json(['message' => "Node '{$nodeId}' not found."], 404);
        }

        $this->nodeService->replenishCheck($node);
        $this->nodeService->depleteResource($node, $request->input('resource'));

        return response()->json([
            'id'                         => $node->id,
            'cred_resource_depleted'     => $node->cred_resource_depleted,
            'cred_last_hacked_at'        => $node->cred_last_hacked_at,
            'movement_resource_depleted' => $node->movement_resource_depleted,
            'movement_last_hacked_at'    => $node->movement_last_hacked_at,
            'cred_value_current'         => $this->nodeService->currentCredValue($node),
        ]);
    }
}
