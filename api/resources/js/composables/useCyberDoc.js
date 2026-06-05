/**
 * useCyberDoc
 *
 * API calls for all CyberDoc NPC interactions.
 * Returns raw server responses — callers own all reactive state updates.
 */

import axios from 'axios';

export function useCyberDoc() {

    /**
     * Bank all pocket_creds into the safe wallet and reset bounty.
     * @returns {Promise<object|null>}
     */
    async function bank(playerId, cyberdocCanvasId = null) {
        try {
            const res = await axios.post('/api/cyberdoc/bank', {
                player_id:          playerId,
                ...(cyberdocCanvasId ? { cyberdoc_canvas_id: cyberdocCanvasId } : {}),
            });
            return res.data;
        } catch (e) {
            console.error('[CYBERDOC] Bank failed:', e?.response?.data?.message ?? e.message);
            return null;
        }
    }

    return { bank };
}
