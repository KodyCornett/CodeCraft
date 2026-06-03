/**
 * usePosition
 *
 * Persists the player's current map position to the backend on every move.
 * Required for node presence detection (PvP initiation) and district tracking.
 *
 * POST /api/player/position  { canvas_node_id, district }
 *
 * Calls are fire-and-forget — a failure just logs a warning and doesn't
 * block movement. Debounced to avoid flooding on rapid moves.
 */

import axios from 'axios';

export function usePosition(playerId) {

    let _debounceTimer = null;

    async function updatePosition(canvasNodeId, district = null, onSuccess = null, uplinkCost = 1) {
        const pid = typeof playerId === 'object' ? playerId.value : playerId;
        if (!pid || !canvasNodeId) return;

        // Debounce — only fire if the player has stayed on this node for 300ms
        clearTimeout(_debounceTimer);
        _debounceTimer = setTimeout(async () => {
            try {
                const res = await axios.post('/api/player/position', {
                    canvas_node_id: canvasNodeId,
                    district:       district ?? null,
                    uplink_cost:    uplinkCost,
                });
                // Notify caller with server-authoritative remaining uplink
                if (onSuccess && res.data.remaining_uplink != null) {
                    onSuccess(res.data);
                }
            } catch (e) {
                console.warn('[POSITION] Failed to persist position:', e?.response?.data?.message ?? e.message);
            }
        }, 300);
    }

    return { updatePosition };
}
