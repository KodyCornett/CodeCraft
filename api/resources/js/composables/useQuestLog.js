/**
 * useQuestLog
 *
 * Fetches GET /api/quests and exposes reactive quest + reputation state
 * for the Splice QuestLog terminal page.
 *
 * Shape of `docs`:
 * [
 *   {
 *     cyber_doc_id: string,
 *     name:         string,
 *     district:     string,
 *     met:          boolean,     // true once the player has visited this doc
 *     referral:     string|null, // pending intro text before first visit
 *     rep: {
 *       score:          number,
 *       label:          string,  // NULL / RESOLVED / ROUTED / ENCRYPTED / ROOT
 *       tier_index:     number,  // 0–4
 *       next_threshold: number|null,
 *       bar_pct:        number,  // 0.0–1.0 progress within current tier
 *     },
 *     arcs: [
 *       {
 *         id:             string,
 *         sequence_order: number,
 *         title:          string,
 *         rep_required:   number,
 *         status:         'locked'|'active'|'complete',
 *         stages: [
 *           {
 *             id:               string,
 *             stage_number:     number,
 *             title:            string,
 *             objective_text:   string|null,  // null when locked
 *             status:           'locked'|'active'|'complete',
 *             rep_reward:       number,
 *             is_branch:        boolean,
 *             branch_options:   array|null,
 *             turned_into_doc_id: string|null,
 *             completed_at:     string|null,
 *           }
 *         ]
 *       }
 *     ]
 *   }
 * ]
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

export function useQuestLog() {
    const docs    = ref([]);
    const loading = ref(false);
    const error   = ref(null);

    async function fetchQuestLog() {
        try {
            loading.value = true;
            error.value   = null;
            const res = await axios.get('/api/quests');
            docs.value = res.data.docs ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Quest log unavailable';
            console.warn('[QUEST LOG] Fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Complete a quest stage.
     * For branch stages pass turnedIntoDocId (UUID of the chosen doc).
     * Re-fetches the full log after completion so the UI reflects updated state.
     */
    async function completeStage(stageId, turnedIntoDocId = null) {
        try {
            const payload = turnedIntoDocId ? { turned_into_doc_id: turnedIntoDocId } : {};
            const res = await axios.post(`/api/quests/stage/${stageId}/complete`, payload);
            await fetchQuestLog();
            return res.data;
        } catch (e) {
            const msg = e?.response?.data?.message ?? e.message ?? 'Failed to complete stage';
            console.warn('[QUEST LOG] completeStage failed:', msg);
            throw e;
        }
    }

    return {
        docs:          readonly(docs),
        loading:       readonly(loading),
        error:         readonly(error),
        fetchQuestLog,
        completeStage,
    };
}
