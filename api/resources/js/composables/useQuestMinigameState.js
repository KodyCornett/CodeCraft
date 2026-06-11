/**
 * useQuestMinigameState
 *
 * Per-instance state shared by all quest minigame components via composition.
 * Each game component calls this with its skin prop and gets back reactive bars,
 * glitch state, a shared tick function, and the endGame helper.
 *
 * Usage:
 *   const { stability, primaryProgress, ... tickShared, applyHit, endGame }
 *       = useQuestMinigameState(props.skin)
 */

import { ref, computed } from 'vue';

const DIFFICULTY = {
    1: { traceSpeed: 0.016, stabilityDrain: 0.006 },
    2: { traceSpeed: 0.024, stabilityDrain: 0.010 },
    3: { traceSpeed: 0.036, stabilityDrain: 0.016 },
};

export function useQuestMinigameState(skin) {
    const stability       = ref(1.0);
    const primaryProgress = ref(0);
    const timeLeft        = ref(skin.timeLimit ?? 30);
    const result          = ref(null);    // null | 'success' | 'fail'
    const failReason      = ref('');

    const diff = DIFFICULTY[skin.difficulty ?? 1];

    // ── Glitch — driven by stability loss ─────────────────────────────────────

    const glitchActive = computed(() => stability.value < 0.5 && !result.value);

    const glitchType = computed(() => {
        if (stability.value < 0.15) return 'chromatic,bars,static';
        if (stability.value < 0.30) return 'chromatic,bars';
        return 'scan';
    });

    const glitchIntensity = computed(() => {
        if (stability.value >= 0.5) return 0;
        return 0.1 + (0.5 - stability.value) * 1.8;
    });

    // ── CSS state helpers ─────────────────────────────────────────────────────

    const stabilityClass = computed(() => {
        if (stability.value < 0.15) return 'stab--critical';
        if (stability.value < 0.30) return 'stab--warn';
        return '';
    });

    const timerClass = computed(() => {
        if (timeLeft.value <= 5)  return 'timer--critical';
        if (timeLeft.value <= 10) return 'timer--warn';
        return '';
    });

    // ── Shared tick — call every frame from the game loop ────────────────────
    // Returns 'trace' | 'stability' | null so the game can handle fail condition.

    function tickShared(dt) {
        primaryProgress.value = Math.min(1, primaryProgress.value + diff.traceSpeed * dt);
        stability.value       = Math.max(0, stability.value - diff.stabilityDrain * dt);
        timeLeft.value        = Math.max(0, timeLeft.value - dt);

        if (primaryProgress.value >= 1 || timeLeft.value <= 0) return 'trace';
        if (stability.value <= 0)                               return 'stability';
        return null;
    }

    function applyHit(amount) {
        stability.value = Math.max(0, stability.value - amount);
    }

    function endGame(outcome, reason) {
        result.value     = outcome;
        failReason.value = reason ?? '';
    }

    return {
        stability, primaryProgress, timeLeft, result, failReason,
        glitchActive, glitchType, glitchIntensity,
        stabilityClass, timerClass,
        diff,
        tickShared, applyHit, endGame,
    };
}
