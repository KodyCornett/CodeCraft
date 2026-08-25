/**
 * useDevBankHeist
 *
 * Module-level singleton bridging DevMinigameLauncher.vue (splice://dev/minigames)
 * and Game.vue, mirroring useQuestMinigame.js's launch/clear shape exactly.
 *
 * Bank Heist can't go through useQuestMinigame's generic activeMinigame/skin
 * system — BankHeist.vue takes canvasId/bankName/bankIce/bankTier, not a skin
 * object, and its player stats (CPU/RAM/OS/bounty multiplier) always come
 * from the real rig/player state rather than a per-launch override. This
 * composable exists only to hand Game.vue a real, roster-backed canvasId to
 * populate its existing `activeBankHeist` ref with — every server round-trip
 * (gate1-failed, phase2-inject, phase2-extract) still resolves against a
 * genuine Node row exactly as it would from the live map trigger, so a dev
 * launch exercises the full real flow, not a mocked shortcut.
 */

import { ref } from 'vue';

const activeDevBankHeist = ref(null); // { canvasId, bankName, bankIce, bankTier } | null

export function useDevBankHeist() {

    function launch(payload) {
        activeDevBankHeist.value = payload;
    }

    function clear() {
        activeDevBankHeist.value = null;
    }

    return { activeDevBankHeist, launch, clear };
}
