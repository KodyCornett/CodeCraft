/**
 * useCodexFind
 *
 * The "how does a player stumble onto Archive Extraction" piece of the
 * Codex system. While a codex thread is active, every routine node hack
 * that completes successfully (see useHackFlow::onHackComplete) rolls a
 * chance to surface a "Codex — Found" prompt. Accepting launches Archive
 * Extraction on the spot, outside the quest-minigame pipeline (no stage to
 * complete — see Game.vue's onQuestMinigameComplete null-stageId guard).
 * Declining costs nothing: the prompt just closes, and since no specific
 * document was drawn yet (that only happens later, at Codex Archive
 * resolve), nothing was ever "lost" by passing — the next routine hack can
 * roll it again.
 *
 * Module-level singleton, same pattern as useQuestMinigame/useCodex, so
 * Game.vue (which renders the popup) and useHackFlow (which rolls it) share
 * one instance without threading refs through prop chains.
 */

import { ref, readonly } from 'vue';
import { useCodex } from './useCodex.js';
import { useQuestMinigame } from './useQuestMinigame.js';

// Chance a successful routine hack surfaces the prompt, while a thread is
// active. Tunable — starting point, not a balanced number yet.
const FIND_CHANCE = 0.15;

const pendingFind = ref(false);

export function useCodexFind() {
    const { hasActiveCodex } = useCodex();
    const { launch } = useQuestMinigame();

    /**
     * Called after a routine hack completes. No-op unless a codex thread is
     * active, and never stacks a second prompt on top of one already showing.
     */
    function rollForFind() {
        if (!hasActiveCodex.value || pendingFind.value) return;
        if (Math.random() < FIND_CHANCE) pendingFind.value = true;
    }

    /** Player chose Play — launch Archive Extraction with no stakes attached. */
    function accept() {
        pendingFind.value = false;
        launch(null, 'archive_extraction', {
            gameType:          'archive_extraction',
            fileName:          'UNTRACED_FRAGMENT.sys',
            nodeCanvasId:      null,
            objectiveText:     'Extract the fragment before the trace resolves.',
            successText:       'Fragment extracted.',
            failText:          'Trace resolved — fragment lost. No harm done, it\'ll turn up again.',
            primaryBarLabel:   'TRACE',
            stabilityLabel:    'STABILITY',
            hideBars:          false,
            dealsDamageOnFail: false, // opportunistic bonus find — no stakes, ever
            difficulty:        1,
        });
    }

    /** Player chose Pass — the prompt just closes, nothing consumed. */
    function decline() {
        pendingFind.value = false;
    }

    return {
        pendingFind: readonly(pendingFind),
        rollForFind,
        accept,
        decline,
    };
}
