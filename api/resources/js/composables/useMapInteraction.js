/**
 * useMapInteraction
 *
 * Manages all map-layer UI state:
 * - Which node the player is standing on
 * - Which node is currently selected in the side panel
 * - Active pings visible on the map
 * - Boot sequence completion flag
 *
 * Handlers are passed back to Game.vue and wired to HexMapCanvas events.
 *
 * Node ID bridging
 * ─────────────────
 * HexMapCanvas.vue identifies nodes by their canvas string IDs (e.g. 'NS-v3',
 * 'DT-hub'). The DB knows the same nodes by UUID. When a node-clicked event
 * arrives we merge the canvas geometry with the DB record so downstream code
 * (NodeInfoBlock, GridBreach, deplete API) always gets a unified object with:
 *   - id        → DB UUID          (used for API calls)
 *   - canvasId  → canvas string    (used for map canvas lookups)
 *   - ice, tier, credDepleted, …   (live DB state)
 *   - x, y, type, district         (canvas geometry)
 *
 * Pass `getByCanvasId` from useMapData to enable the merge.
 * If the DB hasn't loaded yet the canvas-only node is used as fallback.
 */

import { ref } from 'vue';

export function useMapInteraction(player, getByCanvasId = null) {

    const mapCanvasRef          = ref(null);
    const currentNodeId         = ref(null);   // canvas ID of player's current node
    const currentNode           = ref(null);
    const selectedNode          = ref(null);   // merged canvas+DB node object
    const selectedNodeIsAdjacent = ref(false); // true when selectedNode is one step from player
    const pings                 = ref([]);
    const booted                = ref(false);

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Merge a canvas geometry node with its DB record.
     * Canvas node: { id: 'NS-v3', x, y, type, district, areaColor }
     * DB node:     { id: uuid, canvasId: 'NS-v3', ice, tier, credDepleted, … }
     * Result:      { id: uuid, canvasId: 'NS-v3', x, y, type, district, ice, … }
     */
    function enrichNode(canvasNode) {
        const dbNode = getByCanvasId?.(canvasNode.id);
        if (!dbNode) {
            // DB not loaded yet — use canvas data with canvas id as both ids
            return { ...canvasNode, canvasId: canvasNode.id, ice: 3 };
        }
        return {
            ...canvasNode,          // x, y, type, district, areaColor
            ...dbNode,              // id (UUID), canvasId, ice, tier, resources…
        };
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    function onPlayerMoved({ nodeId, district, x, y }) {
        currentNodeId.value = nodeId;   // canvas ID

        // Store position for ping generation and other callers
        currentNode.value = {
            canvasId: nodeId,
            x: x ?? 0,
            y: y ?? 0,
        };

        // Right panel always reflects the node the player is standing on
        const canvasNode = { id: nodeId, x: x ?? 0, y: y ?? 0, district };
        selectedNode.value          = enrichNode(canvasNode);
        selectedNodeIsAdjacent.value = false;

        if (district != null) {
            player.value.district = district.toUpperCase();
        }
        // Each move costs exactly 1 uplink
        player.value.uplink = Math.max(0, (player.value.uplink ?? 0) - 1);
    }

    function onNodeClicked({ node, isAdjacent = false }) {
        selectedNode.value          = enrichNode(node);
        selectedNodeIsAdjacent.value = isAdjacent;
    }

    return {
        mapCanvasRef,
        currentNodeId,
        currentNode,
        selectedNode,
        selectedNodeIsAdjacent,
        pings,
        booted,
        onPlayerMoved,
        onNodeClicked,
    };
}
