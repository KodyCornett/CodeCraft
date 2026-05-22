<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\NodeTrace;
use App\Models\Player;
use App\Services\BountyService;
use App\Services\NodeService;
use App\Services\RigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NodeController extends Controller
{
    public function __construct(
        private readonly NodeService    $nodeService,
        private readonly BountyService  $bountyService,
        private readonly RigService     $rigService,
    ) {}

    /**
     * GET /api/nodes
     *
     * Returns every node in the database with its adjacency list.
     * Called once on game boot by useMapData.js to hydrate the canvas.
     *
     * Each node carries the canvas_id that HexMapCanvas.vue uses as
     * its ALL_NODES key, so the frontend can match DB records to
     * rendered geometry by canvas_id rather than UUID.
     */
    public function all(): JsonResponse
    {
        $nodes = Node::with(['adjacentNodes:id,canvas_id'])
            ->get()
            ->map(fn ($node) => [
                'id'                         => $node->id,
                'canvas_id'                  => $node->canvas_id,
                'x'                          => $node->x,
                'y'                          => $node->y,
                'type'                       => $node->type,
                'district'                   => $node->district,
                'ice'                        => $node->ice,
                'tier'                       => $node->tier,
                'cred_value_base'            => $node->cred_value_base,
                'cred_resource_depleted'     => $node->cred_resource_depleted,
                'cred_last_hacked_at'        => $node->cred_last_hacked_at,
                'movement_resource_depleted' => $node->movement_resource_depleted,
                'movement_last_hacked_at'    => $node->movement_last_hacked_at,
                'is_spawn'                   => $node->is_spawn,
                'is_safe_zone'               => $node->is_safe_zone,
                // Adjacent node IDs as UUIDs (for DB ops) and canvas_ids (for map matching)
                'adjacent_node_ids'          => $node->adjacentNodes->pluck('id'),
                'adjacent_canvas_ids'        => $node->adjacentNodes->pluck('canvas_id'),
            ]);

        return response()->json(['nodes' => $nodes]);
    }

    /**
     * GET /api/nodes/{canvasId}/players
     *
     * Returns players currently on this node (excluding the requesting player).
     * canvasId is the canvas string key — e.g. 'DT-hub', 'NS-v3'.
     */
    public function players(Request $request, string $canvasId): JsonResponse
    {
        $node = Node::where('canvas_id', $canvasId)->first();
        if ($node === null) {
            return response()->json(['players' => []]);
        }

        $currentPlayerId = null;
        if ($request->user()) {
            $me = Player::where('user_id', $request->user()->id)->first();
            $currentPlayerId = $me?->id;
        }

        $players = Player::with(['rig.chassis', 'playerPeripherals.peripheral'])
            ->where('current_node_id', $node->id)
            ->when($currentPlayerId, fn ($q) => $q->where('id', '!=', $currentPlayerId))
            ->get(['id', 'handle', 'bounty_level', 'is_open_season', 'pocket_creds', 'bounty_multiplier']);

        // Flag players who are already in an active combat so the client can
        // grey out the [HACK] button and show IN COMBAT state.
        $playerIds   = $players->pluck('id')->toArray();
        $inCombatIds = \App\Models\CombatChallenge::whereIn('status', ['pending', 'accepted'])
            ->where(function ($q) use ($playerIds) {
                $q->whereIn('challenger_id', $playerIds)
                  ->orWhereIn('target_id', $playerIds);
            })
            ->get(['challenger_id', 'target_id'])
            ->flatMap(fn ($c) => [$c->challenger_id, $c->target_id])
            ->unique()
            ->flip()
            ->toArray();   // id → true map for O(1) lookup

        $players = $players->map(fn ($p) => [
                'id'                 => $p->id,
                'handle'             => $p->handle,
                'bounty_level'       => (int)   $p->bounty_level,
                'is_open_season'     => (bool)  $p->is_open_season,
                'pocket_creds'       => (int)   ($p->pocket_creds   ?? 0),
                'bounty_multiplier'  => (float) ($p->bounty_multiplier ?? 1.0),
                'in_combat'          => isset($inCombatIds[$p->id]),
                'effective_firewall' => $p->rig
                    ? $this->rigService->effectiveStats($p->rig, $p)['firewall']['effective']
                    : 1,
            ]);

        return response()->json(['players' => $players]);
    }

    /**
     * POST /api/nodes/{nodeId}/trace
     *
     * Stamps a data fragment on a node for a player who attempted a breach —
     * success or failure. The deplete endpoint handles successful hacks; this
     * endpoint is called explicitly for failed breaches so traces are always
     * left behind regardless of outcome.
     *
     * Body:
     *   player_id  string (UUID)
     *
     * Re-hacking the same node resets the TTL (upsert on node_id + player_id).
     */
    public function storeTrace(Request $request, string $nodeId): JsonResponse
    {
        $node   = Node::find($nodeId);
        $player = Player::where('user_id', $request->user()->id)->first();

        if ($node === null || $player === null) {
            return response()->json(['ok' => false], 404);
        }

        NodeTrace::updateOrCreate(
            ['node_id' => $node->id, 'player_id' => $player->id],
            ['expires_at' => Carbon::now()->addSeconds(NodeTrace::DEFAULT_TTL_SECONDS)],
        );

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/nodes/{canvasId}/traces
     *
     * Returns the active "data fragments" left on this node by recent
     * hackers. Each row carries the player's handle and how many seconds
     * remain before the trace fades (server-authoritative — clients still
     * tick down locally for smooth rendering).
     *
     * canvasId is the canvas string key — e.g. 'DT-hub', 'NS-v3'.
     */
    public function traces(string $canvasId): JsonResponse
    {
        $node = Node::where('canvas_id', $canvasId)->first();
        if ($node === null) {
            return response()->json(['traces' => []]);
        }

        $now = Carbon::now();

        $traces = NodeTrace::with('player:id,handle')
            ->where('node_id', $node->id)
            ->where('expires_at', '>', $now)
            ->orderBy('expires_at', 'desc')   // freshest first
            ->get()
            ->map(fn (NodeTrace $t) => [
                'id'                => $t->id,
                'player_id'         => $t->player_id,
                'handle'            => $t->player?->handle ?? '???',
                'expires_at'        => $t->expires_at->toIso8601String(),
                'seconds_remaining' => max(0, $now->diffInSeconds($t->expires_at, false)),
            ]);

        return response()->json(['traces' => $traces]);
    }

    /**
     * POST /api/nodes/{nodeId}/deplete
     *
     * Called by the client after a successful GridBreach hack.
     *
     * Body:
     *   resource       string  'creds' | 'movement'
     *   player_id      string  UUID of the hacking player
     *   reward_amount  int     (optional) creds actually awarded by GridBreach
     *
     * Side effects:
     *   • Marks the node resource as depleted
     *   • If resource === 'creds' and reward_amount > 0, credits player's pocket_creds
     *   • Records the hack on the player's bounty run counter (bounty escalation)
     *
     * The reward_amount comes from GridBreach — it accounts for partial completion,
     * bounty multiplier, and command effects, so we trust the client value here.
     * (Server-authoritative validation is a future hardening step.)
     */
    public function deplete(Request $request, string $nodeId): JsonResponse
    {
        $data = $request->validate([
            'resource'      => ['required', 'in:creds,movement,tech'],
            'reward_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Pre-flight: node must exist before we acquire any lock
        if (Node::find($nodeId) === null) {
            return response()->json(['message' => "Node '{$nodeId}' not found."], 404);
        }

        $player = Player::where('user_id', $request->user()->id)->first();

        // All mutations run in a transaction with a pessimistic lock on the
        // node row, preventing double-deplete from a rapid double-tap or retry.
        [
            'node'        => $node,
            'bountyEvent' => $bountyEvent,
            'pocketAfter' => $pocketAfter,
            'techAfter'   => $techAfter,
            'currentUplink' => $currentUplink,
        ] = DB::transaction(function () use ($nodeId, $data, $player) {
            $node = Node::lockForUpdate()->find($nodeId);

            $this->nodeService->replenishCheck($node);

            // 'tech' hacks deplete the same cred pool as 'creds' hacks
            $nodeResource = $data['resource'] === 'tech' ? 'creds' : $data['resource'];
            $this->nodeService->depleteResource($node, $nodeResource);

            $bountyEvent   = null;
            $pocketAfter   = 0;
            $techAfter     = 0;
            $currentUplink = null;

            if ($player !== null) {
                $pocketBefore = (int) ($player->pocket_creds ?? 0);

                // Clamp the client-supplied reward to a server-authoritative ceiling so
                // a crafted request cannot mint unlimited creds or tech points.
                // Ceiling = node's current cred value × the player's bounty multiplier,
                // rounded up. Tech hacks use the same node cred pool so the same cap applies.
                // Movement hacks carry no monetary reward so the cap is irrelevant there.
                $clientReward = (float) ($data['reward_amount'] ?? 0);
                if ($data['resource'] !== 'movement' && $clientReward > 0) {
                    $nodeCredCeiling  = $this->nodeService->currentCredValue($node);
                    $multiplier       = max(1.0, (float) ($player->bounty_multiplier ?? 1.0));
                    $rewardCeiling    = round($nodeCredCeiling * $multiplier * 2.25, 2); // 2.25 = max bounty multiplier
                    $clientReward     = min($clientReward, $rewardCeiling);
                }

                // reward_amount supports fractional values for tech hacks
                $reward = $data['resource'] === 'tech'
                    ? round($clientReward, 2)
                    : (int) $clientReward;

                if ($reward > 0) {
                    if ($data['resource'] === 'creds') {
                        $player->pocket_creds = $pocketBefore + $reward;
                        $player->save();
                    } elseif ($data['resource'] === 'tech') {
                        $player->tech_points = round((float) ($player->tech_points ?? 0) + $reward, 2);
                        $player->save();
                    }
                }

                // Movement (uplink) hacks → restore current_uplink to chassis base
                if ($data['resource'] === 'movement') {
                    $rig = $player->rig()->with('chassis')->first();
                    if ($rig !== null) {
                        $currentUplink = $this->rigService->restoreUplinkToFull($rig);
                    }
                }

                $pocketAfter = (int)   ($player->pocket_creds ?? 0);
                $techAfter   = (float) ($player->tech_points  ?? 0);

                // Record hack for bounty board / open-season tracking
                $event       = $this->bountyService->recordNodeHack($player);
                $bountyEvent = ['type' => $event->type, 'data' => $event->data];

                // Ghost Protocol suppresses the trace for its move duration
                $ghostMovesLeft = (int) ((($player->active_effects ?? [])['ghost_protocol'] ?? 0));
                if ($ghostMovesLeft <= 0) {
                    NodeTrace::updateOrCreate(
                        ['node_id'   => $node->id, 'player_id' => $player->id],
                        ['expires_at' => Carbon::now()->addSeconds(NodeTrace::DEFAULT_TTL_SECONDS)],
                    );
                }
            }

            return compact('node', 'bountyEvent', 'pocketAfter', 'techAfter', 'currentUplink');
        });

        return response()->json([
            'node' => [
                'id'                         => $node->id,
                'canvas_id'                  => $node->canvas_id,
                'cred_resource_depleted'     => $node->cred_resource_depleted,
                'cred_last_hacked_at'        => $node->cred_last_hacked_at,
                'movement_resource_depleted' => $node->movement_resource_depleted,
                'movement_last_hacked_at'    => $node->movement_last_hacked_at,
                'cred_value_current'         => $this->nodeService->currentCredValue($node),
            ],
            'player' => $player ? [
                'pocket_creds'          => $pocketAfter,
                'tech_points'           => $techAfter,
                'current_uplink'        => $currentUplink,
                'nodes_hacked_this_run' => $player->nodes_hacked_this_run,
                'bounty_level'          => $player->bounty_level,
                'is_open_season'        => $player->is_open_season,
            ] : null,
            'bounty_event' => $bountyEvent,
        ]);
    }
}
