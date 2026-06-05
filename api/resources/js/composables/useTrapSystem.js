/**
 * useTrapSystem
 *
 * API calls and reactive state for node traps and decoys.
 *
 * API layer (Section 2 — axios extraction):
 *   placeTrap(nodeId, commandId)     POST /api/nodes/{id}/place-trap
 *   placeDecoy(canvasId, commandId)  POST /api/nodes/{id}/place-decoy
 *   fetchMyTraps()                   GET  /api/player/traps
 *
 * Reactive state (Section 3.3 — to be migrated from Game.vue):
 *   myTraps, trapTargetMode, trapHitNotification, trapFiredNotification
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useTrapSystem() {
    const myTraps = ref([]);

    async function placeTrap(nodeId, commandId) {
        try {
            const res = await axios.post(`/api/nodes/${nodeId}/place-trap`, { command_id: commandId });
            return res.data;
        } catch (e) {
            if (import.meta.env.DEV) {
                console.warn('[TRAP] Server placement failed:', e?.response?.data);
            }
            return null;
        }
    }

    async function placeDecoy(canvasId, commandId) {
        try {
            const res = await axios.post(`/api/nodes/${canvasId}/place-decoy`, { command_id: commandId });
            return res.data;
        } catch (e) {
            if (import.meta.env.DEV) {
                console.warn('[DECOY] Server call failed:', e?.response?.data);
            }
            return null;
        }
    }

    async function fetchMyTraps() {
        try {
            const res = await axios.get('/api/player/traps');
            myTraps.value = res.data.traps ?? [];
        } catch {
            // Non-critical — map markers are cosmetic only
        }
    }

    return { myTraps, placeTrap, placeDecoy, fetchMyTraps };
}
