/**
 * useDepletion
 *
 * Fires POST /api/nodes/{nodeId}/deplete after a successful GridBreach hack.
 *
 * Resource mapping (GridBreach → API):
 *   'creds'  → 'creds'     (credits extracted from node)
 *   'tech'   → 'tech'      (tech hacks deplete the node's cred pool but award tech_points, not pocket_creds)
 *   'uplink' → 'movement'  (uplink refill taps the node's movement resource)
 *
 * The API returns the updated node state so the map can reflect depletion
 * visually without a full reload.
 */

import { ref } from 'vue';
import axios   from 'axios';

// GridBreach resource string → API resource string
const RESOURCE_MAP = {
    creds:  'creds',
    tech:   'tech',     // tech hacks deplete the cred pool but award tech_points server-side
    uplink: 'movement',
};

export function useDepletion(playerId) {
    const depleting = ref(false);
    const error     = ref(null);

    /**
     * deplete(nodeId, gameResource, completionPct)
     *
     * @param {string} nodeId        - UUID from the enriched node (not canvasId)
     * @param {string} gameResource  - 'creds' | 'tech' | 'uplink'
     * @param {number} completionPct - fraction of GridBreach sequences completed (0.0–1.0).
     *                                 Pass 0 for uplink hacks (no monetary reward).
     *                                 Server computes the actual cred/tech award from this.
     *
     * @returns {object|null} patch object ready for updateNodeResources(), or null on failure
     *   {
     *     credDepleted:     boolean,
     *     movementDepleted: boolean,
     *     credLastHackedAt: string|null,
     *     player:           { pocket_creds, bounty_level, is_open_season, ... } | null,
     *     bountyEvent:      { type, data } | null,
     *   }
     */
    async function deplete(nodeId, gameResource, completionPct = 1.0) {
        if (!nodeId) {
            console.warn('[DEPLETE] No nodeId — skipping API call');
            return null;
        }

        const apiResource = RESOURCE_MAP[gameResource];
        if (!apiResource) {
            console.warn(`[DEPLETE] Unknown game resource: ${gameResource}`);
            return null;
        }

        const pid = typeof playerId === 'object' ? playerId.value : playerId;
        if (!pid) {
            console.warn('[DEPLETE] No player ID — skipping API call');
            return null;
        }

        depleting.value = true;
        error.value     = null;

        try {
            const res = await axios.post(`/api/nodes/${nodeId}/deplete`, {
                resource:       apiResource,
                player_id:      pid,
                completion_pct: completionPct > 0 ? completionPct : undefined,
            });

            const node = res.data.node ?? res.data;

            return {
                credDepleted:         node.cred_resource_depleted     ?? false,
                movementDepleted:     node.movement_resource_depleted ?? false,
                credLastHackedAt:     node.cred_last_hacked_at        ?? null,
                movementLastHackedAt: node.movement_last_hacked_at    ?? null,
                player:               res.data.player       ?? null,
                bountyEvent:          res.data.bounty_event ?? null,
                // current_uplink is only set on movement (uplink) hacks; null otherwise
                currentUplink:        res.data.player?.current_uplink ?? null,
            };
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Deplete failed';
            console.error('[DEPLETE] API error:', error.value);
            return null;
        } finally {
            depleting.value = false;
        }
    }

    return { deplete, depleting: depleting, error };
}
