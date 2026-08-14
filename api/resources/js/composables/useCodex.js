/**
 * useCodex
 *
 * Backs the Decrypter SPLICE page, plus anything elsewhere that needs to
 * know whether a codex thread is active (e.g. useCodexFind's routine-hack
 * roll). State is module-level and shared across every caller — same
 * pattern as useQuestMinigame — so a fetchState() triggered from one place
 * (Decrypter.vue on mount, Game.vue on boot, useQuestLog after a stage
 * completes) is visible everywhere else without a redundant round trip.
 *
 * Fetches GET /api/codex/state (active-thread flag, unresolved keys, and
 * the full History tracking list), resolves earned keys, reads individual
 * SPLICE pages by slug, and submits login solves for Codex-tier pages.
 * See CodexService.php for the full system.
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

const hasActiveCodex = ref(false);
const unresolvedKeys = ref([]);
const history         = ref([]);
const loading         = ref(false);
const error           = ref(null);

export function useCodex() {
    async function fetchState() {
        try {
            loading.value = true;
            error.value   = null;
            const res = await axios.get('/api/codex/state');
            hasActiveCodex.value = res.data.has_active_codex ?? false;
            unresolvedKeys.value = res.data.unresolved_keys ?? [];
            history.value         = res.data.history ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Codex Archive unavailable';
            console.warn('[CODEX] state fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Report an Archive Extraction win, played anywhere while a codex
     * thread is active. Returns { dropped: bool, key_id: string|null } —
     * a miss (dropped: false) is a normal outcome, not an error.
     */
    async function reportArchiveWin() {
        const res = await axios.post('/api/codex/archive-win');
        return res.data;
    }

    /**
     * Resolve one earned key. Returns { outcome: 'nothing_left' } or
     * { outcome: 'document', page: {...} } — a resolve always produces a
     * document unless the player's active threads are fully explored.
     * Caller should re-run fetchState() afterward to refresh the
     * unresolved/history lists.
     */
    async function resolveKey(keyId) {
        const res = await axios.post('/api/codex/resolve', { key_id: keyId });
        return res.data;
    }

    /**
     * Fetch one SPLICE page by slug for reading. Throws (via axios) if the
     * page is Codex-tier and not yet unlocked — caller should surface the
     * error message rather than treat it as an empty state.
     */
    async function fetchPage(slug) {
        const res = await axios.get(`/api/codex/page/${encodeURIComponent(slug)}`);
        return res.data;
    }

    /**
     * Attempt to solve a Codex page's login. `answers` is an object keyed
     * by credential label (see page.credential_labels from fetchPage).
     * Returns { solved: bool, ... }. A wrong guess is never an error —
     * just solved: false, retry freely.
     */
    async function solvePage(splicePageId, answers) {
        const res = await axios.post(`/api/codex/page/${splicePageId}/solve`, { answers });
        return res.data;
    }

    return {
        hasActiveCodex: readonly(hasActiveCodex),
        unresolvedKeys: readonly(unresolvedKeys),
        history:         readonly(history),
        loading:         readonly(loading),
        error:           readonly(error),
        fetchState,
        reportArchiveWin,
        resolveKey,
        fetchPage,
        solvePage,
    };
}
