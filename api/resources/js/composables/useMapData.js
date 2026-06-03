import { ref, readonly } from 'vue';
import axios from 'axios';

/**
 * Derive visual state from API node data.
 * Priority: cyberdoc > scorched > hacked > active
 */
function deriveState(node) {
    if (node.type === 'cyberdoc')                                                return 'cyberdoc';
    if (node.cred_resource_depleted && node.movement_resource_depleted)          return 'scorched';
    if (node.cred_resource_depleted || node.movement_resource_depleted)          return 'hacked';
    return 'active';
}

/**
 * Map a raw API node to the canvas-ready format Game.vue / HexMapCanvas expects.
 *
 * canvas_id is the key used by HexMapCanvas.vue's ALL_NODES Map — the frontend
 * uses this to associate DB records with rendered geometry.
 */
function toCanvasNode(apiNode) {
    return {
        id:        apiNode.id,          // UUID — used for all API calls (deplete, etc.)
        canvasId:  apiNode.canvas_id,   // 'DT-hub', 'NS-v3', 'wp_3_-7' — map lookup key
        x:         apiNode.x  ?? 0,    // SVG canvas x coordinate (used for false ping targeting)
        y:         apiNode.y  ?? 0,    // SVG canvas y coordinate
        type:      apiNode.type,        // 'action' | 'cyberdoc'
        district:  apiNode.district,    // string district name or null for waypoints
        tier:      apiNode.tier,
        ice:       apiNode.ice ?? 3,    // ICE rating — floor 3 (BlackHat v1.0 CPU)
        isSpawn:   apiNode.is_spawn   ?? false,
        isSafeZone: apiNode.is_safe_zone ?? false,
        npcHandle: apiNode.npc_handle ?? null,  // set on CyberDoc hubs — quest-giver handle
        state:     deriveState(apiNode),
        // Resource state for game logic
        credDepleted:       apiNode.cred_resource_depleted,
        movementDepleted:   apiNode.movement_resource_depleted,
        credValueBase:      apiNode.cred_value_base     ?? 100,
        credLastHackedAt:   apiNode.cred_last_hacked_at ?? null,
        zoneType:           apiNode.zone_type  ?? 'netlink',
        zoneGroup:          apiNode.zone_group ?? null,
    };
}

export function useMapData() {
    const nodes         = ref([]);   // toCanvasNode records keyed by canvasId
    const nodeByCanvas  = ref({});   // canvasId → node (fast lookup for HexMapCanvas events)
    const loading       = ref(false);
    const error         = ref(null);

    /**
     * Fetch all 228 canvas nodes from GET /api/nodes (single request).
     * The adjacency data is included in the response but not stored here —
     * HexMapCanvas.vue builds its own graph from geometry; the DB adjacency
     * is only used by the backend for graph validation.
     */
    async function fetchAll() {
        loading.value = true;
        error.value   = null;

        try {
            const res = await axios.get('/api/nodes');

            const byCanvas = {};
            const mapped   = res.data.nodes.map(apiNode => {
                const n = toCanvasNode(apiNode);
                byCanvas[n.canvasId] = n;
                return n;
            });

            nodes.value        = mapped;
            nodeByCanvas.value = byCanvas;

            console.log(`[useMapData] Loaded ${mapped.length} nodes from DB`);
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to load map data';
            console.warn('[useMapData] API error — map will run without DB state:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Look up a node by its canvas ID (the string key HexMapCanvas uses).
     * Returns undefined if the DB hasn't loaded yet or the node doesn't exist.
     */
    function getByCanvasId(canvasId) {
        return nodeByCanvas.value[canvasId];
    }

    /**
     * Pick a valid spawn node for a new player session.
     *
     * Spawn rules:
     *   - type === 'action'  (not a CyberDoc hub)
     *   - tier === 3         (mid-tier zones only — not beginner backstreets or elite areas)
     *
     * Only Downtown qualifies under the current seeding, which intentionally
     * makes the city centre the common entry point. Future districts can be
     * added to the spawn pool by raising their tier to 3 in the NodeSeeder.
     *
     * Returns the canvasId of the chosen node, or null if the DB hasn't
     * loaded yet (Game.vue falls back to the canvas default start node).
     */
    function getSpawnNode() {
        const pool = nodes.value.filter(n => n.isSpawn && n.type === 'action');
        if (!pool.length) return null;
        // Pick randomly from the 6 designated spawn points
        return pool[Math.floor(Math.random() * pool.length)].canvasId;
    }

    /**
     * Update a single node's state in-place (called on NODE_STATE_CHANGED events).
     * Accepts either UUID or canvas_id.
     */
    function updateNodeState(nodeId, newState) {
        const node = nodes.value.find(n => n.id === nodeId || n.canvasId === nodeId);
        if (node) {
            node.state = newState;
            nodeByCanvas.value[node.canvasId] = node;
        }
    }

    /**
     * Patch resource fields on a node after a hack (called on deplete API response).
     */
    function updateNodeResources(nodeId, patch) {
        const node = nodes.value.find(n => n.id === nodeId || n.canvasId === nodeId);
        if (!node) return;
        if (patch.credDepleted         !== undefined) node.credDepleted         = patch.credDepleted;
        if (patch.movementDepleted     !== undefined) node.movementDepleted     = patch.movementDepleted;
        if (patch.credLastHackedAt     !== undefined) node.credLastHackedAt     = patch.credLastHackedAt;
        if (patch.movementLastHackedAt !== undefined) node.movementLastHackedAt = patch.movementLastHackedAt;
        // Re-derive visual state
        node.state = deriveState({
            type:                       node.type,
            cred_resource_depleted:     node.credDepleted,
            movement_resource_depleted: node.movementDepleted,
        });
        nodeByCanvas.value[node.canvasId] = node;
    }

    /**
     * Return all nodes within a pixel distance band from a canvas coordinate.
     * Used by Signal Noise / Decoy to pick false-ping targets.
     *
     * minPx  — skip nodes that are too close (avoids pinging the current node)
     * maxPx  — cap to feel "nearby" on the map
     * count  — shuffle and return at most this many
     */
    function getNodesNear(x, y, { minPx = 60, maxPx = 280, count = 4 } = {}) {
        const candidates = nodes.value.filter(n => {
            if (!n.x && !n.y) return false;
            const dx   = n.x - x;
            const dy   = n.y - y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            return dist >= minPx && dist <= maxPx;
        });
        // Shuffle so repeated calls vary the targets
        for (let i = candidates.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [candidates[i], candidates[j]] = [candidates[j], candidates[i]];
        }
        return candidates.slice(0, count);
    }

    return {
        nodes:        readonly(nodes),
        nodeByCanvas: readonly(nodeByCanvas),
        loading:      readonly(loading),
        error:        readonly(error),
        fetchAll,
        getSpawnNode,
        getByCanvasId,
        getNodesNear,
        updateNodeState,
        updateNodeResources,
    };
}
