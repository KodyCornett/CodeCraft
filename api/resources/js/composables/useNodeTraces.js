/**
 * useNodeTraces
 *
 * Polls GET /api/nodes/{canvasId}/traces every 10 seconds for the
 * currently selected node, returning the active "data fragments" left
 * behind by recent hackers.
 *
 * The server gives us absolute `expires_at` ISO timestamps. A separate
 * 1-second tick refreshes a reactive `now` ref so the per-trace countdown
 * recomputes smoothly without re-fetching. Expired entries are pruned
 * locally between polls.
 *
 * The composable mirrors useNodePresence's shape:
 *   - watch selectedCanvasIdRef → start/stop polling
 *   - guard on playerIdRef so we don't fire before auth resolves
 *
 * Returns:
 *   traces        — ref to array of { id, player_id, handle, seconds_remaining, expires_at }
 *   refreshNow()  — force an immediate re-fetch (use after own hacks complete)
 */

import { ref, watch, onUnmounted, computed } from 'vue';
import axios from 'axios';

const POLL_MS = 10_000;
const TICK_MS = 1_000;

export function useNodeTraces(selectedCanvasIdRef, playerIdRef) {
    const rawTraces = ref([]);  // last server payload — expires_at as Date
    const now       = ref(Date.now());

    let _pollTimer = null;
    let _tickTimer = null;

    // Reactive view: recompute seconds_remaining from expires_at vs now,
    // drop anything that has aged past zero. Re-runs on every tick.
    const traces = computed(() =>
        rawTraces.value
            .map(t => ({
                ...t,
                seconds_remaining: Math.max(
                    0,
                    Math.ceil((t.expires_at_ms - now.value) / 1000),
                ),
            }))
            .filter(t => t.seconds_remaining > 0)
    );

    async function fetchTraces(canvasId) {
        if (!canvasId) { rawTraces.value = []; return; }
        if (!playerIdRef?.value) return;   // pre-auth guard

        try {
            const res = await axios.get(`/api/nodes/${canvasId}/traces`);
            const list = res.data.traces ?? [];
            rawTraces.value = list.map(t => ({
                id:            t.id,
                player_id:     t.player_id,
                handle:        t.handle,
                expires_at:    t.expires_at,
                expires_at_ms: new Date(t.expires_at).getTime(),
            }));
            // Snap `now` so the first frame after a fetch reflects fresh data
            now.value = Date.now();
        } catch {
            // Silent — traces are best-effort, like presence
        }
    }

    function startPolling(canvasId) {
        stopPolling();
        if (!canvasId) return;

        fetchTraces(canvasId);   // immediate first fetch
        _pollTimer = setInterval(() => fetchTraces(canvasId), POLL_MS);
        _tickTimer = setInterval(() => { now.value = Date.now(); }, TICK_MS);
    }

    function stopPolling() {
        clearInterval(_pollTimer); _pollTimer = null;
        clearInterval(_tickTimer); _tickTimer = null;
        rawTraces.value = [];
    }

    function refreshNow() {
        const canvasId = selectedCanvasIdRef?.value;
        if (canvasId) fetchTraces(canvasId);
    }

    watch(selectedCanvasIdRef, (id) => {
        if (id) startPolling(id);
        else    stopPolling();
    });

    onUnmounted(stopPolling);

    return { traces, refreshNow };
}
