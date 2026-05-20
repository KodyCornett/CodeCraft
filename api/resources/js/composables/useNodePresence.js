/**
 * useNodePresence
 *
 * Polls GET /api/nodes/{canvasId}/players every 3 seconds when the player
 * is standing on a node, returning other players present at the same location.
 *
 * Used by NodeInfoBlock to show the PLAYERS section and [HACK] button.
 * Polling stops when canvasId is null or the composable is torn down.
 */

import { ref, watch, onUnmounted } from 'vue';
import axios from 'axios';

export function useNodePresence(currentNodeIdRef, playerIdRef) {
    const nodePlayers = ref([]);   // other players at the current node
    const polling     = ref(false);

    let _timer = null;

    async function fetchPresence(canvasId) {
        if (!canvasId) {
            nodePlayers.value = [];
            return;
        }
        // Guard: skip until the player is authenticated (playerId is set after
        // login() resolves). Session auth uses cookies — no Authorization header.
        if (!playerIdRef?.value) return;

        try {
            const res = await axios.get(`/api/nodes/${canvasId}/players`);
            nodePlayers.value = res.data.players ?? [];
        } catch {
            // Silent — presence is best-effort
        }
    }

    function startPolling(canvasId) {
        stopPolling();
        if (!canvasId) return;

        polling.value = true;
        fetchPresence(canvasId);   // immediate first fetch
        _timer = setInterval(() => fetchPresence(canvasId), 3_000);
    }

    function stopPolling() {
        clearInterval(_timer);
        _timer = null;
        polling.value     = false;
        nodePlayers.value = [];
    }

    // Restart polling whenever the player moves to a different node.
    // NOT immediate — avoids a 401 from the pre-auth geometry default that is
    // set before login() resolves. The post-login spawn placement or first
    // movement will trigger the watch and kick off the first poll.
    watch(currentNodeIdRef, (newId) => {
        if (newId) startPolling(newId);
        else       stopPolling();
    });

    onUnmounted(stopPolling);

    return { nodePlayers, polling };
}
