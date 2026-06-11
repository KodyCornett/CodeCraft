/**
 * useQuestArchive
 *
 * Fetches GET /api/quests/archive — the full chronological story log.
 * Used exclusively by the Archive Splice page.
 *
 * Event shape:
 * {
 *   id:          string,
 *   event_type:  'stage_complete' | 'branch_choice' | 'watcher_signal' | 'arc_unlocked' | 'referral',
 *   payload:     object,   // event-specific data — see QuestLogService for payload shapes
 *   occurred_at: string,   // ISO datetime
 * }
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

export function useQuestArchive() {
    const events  = ref([]);
    const loading = ref(false);
    const error   = ref(null);

    async function fetchArchive() {
        try {
            loading.value = true;
            error.value   = null;
            const res = await axios.get('/api/quests/archive');
            events.value  = res.data.events ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Archive unavailable';
            console.warn('[ARCHIVE] fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    return {
        events:       readonly(events),
        loading:      readonly(loading),
        error:        readonly(error),
        fetchArchive,
    };
}
