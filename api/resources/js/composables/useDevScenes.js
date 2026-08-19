/**
 * useDevScenes — DEV ONLY, remove before release alongside DevSceneLauncher.vue.
 *
 * Fetches every quest stage that has dialogue and/or field_comms content,
 * for the splice://dev/scenes scene splicer.
 *
 * Unlike useQuestLog.js, this is NOT gated by player progress — it returns
 * every stage regardless of locked/active/complete status, so any scene can
 * be previewed on demand without fast-forwarding a real save.
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

export function useDevScenes() {
    const scenes  = ref([]);   // flat list — see QuestController::devScenes() for shape
    const loading = ref(false);
    const error   = ref(null);

    async function fetchScenes() {
        try {
            loading.value = true;
            error.value   = null;
            const res = await axios.get('/api/dev/quest-scenes');
            scenes.value = res.data.scenes ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Scene list unavailable';
            console.warn('[DEV SCENES] Fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    return {
        scenes:  readonly(scenes),
        loading: readonly(loading),
        error:   readonly(error),
        fetchScenes,
    };
}
