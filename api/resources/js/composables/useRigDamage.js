/**
 * useRigDamage
 *
 * Sends PvE or PvP SS damage to the server and returns the updated rig state.
 * Server computes the actual damage amount (max(1, nodeICE − effectiveFirewall))
 * so the client never needs to know the formula.
 *
 * Returns null on network failure — callers should treat that as a silent sync
 * miss (client-side SS is already decremented by the game logic that called this).
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useRigDamage() {
    const busy = ref(false);

    /**
     * @param {string|number} nodeCanvasId
     * @param {'pve'|'pvp'} source
     * @returns {Promise<{current_ss, max_ss, event, critical_failure?}|null>}
     */
    async function applyDamage(nodeCanvasId, source = 'pve') {
        busy.value = true;
        try {
            const res = await axios.post('/api/rig/damage', { node_canvas_id: nodeCanvasId, source });
            return res.data;
        } catch {
            return null;
        } finally {
            busy.value = false;
        }
    }

    return { busy, applyDamage };
}
