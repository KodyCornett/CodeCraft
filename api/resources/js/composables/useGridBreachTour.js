/**
 * useGridBreachTour
 *
 * Manages the first-time Grid-Breach orientation tour shown when the player
 * opens GridBreach during the q3_hack tutorial quest.
 *
 * Shape mirrors useUiTour — singleton state, localStorage seen-flag, same
 * start / next / skip / forceStart API — so GridBreachTour.vue stays simple.
 *
 * Steps point FloatingTerminalWindow at IDs added to GridBreach.vue:
 *   #gb-timer, #gb-seq-section, #gb-grid-section, #gb-input-section, #gb-score-row
 */

import { ref, computed } from 'vue';

// ── Step definitions ───────────────────────────────────────────────────────────
const STEPS = [
    {
        id:        'timer',
        target:    '#gb-timer',
        title:     'TIME REMAINING',
        placement: 'bottom',
    },
    {
        id:        'sequence',
        target:    '#gb-seq-section',
        title:     'TARGET SEQUENCE',
        placement: 'bottom',
    },
    {
        id:        'grid',
        target:    '#gb-grid-section',
        title:     'THE GRID',
        placement: 'top',
    },
    {
        id:        'input',
        target:    '#gb-input-section',
        title:     'COORDINATE INPUT',
        placement: 'top',
    },
    {
        id:        'score',
        target:    '#gb-score-row',
        title:     'BREACH SCORE',
        placement: 'bottom',
    },
];

const LS_KEY = 'cc_gb_tour_seen';

// ── Singleton state ────────────────────────────────────────────────────────────
const _active    = ref(false);
const _stepIndex = ref(0);

// ── Composable ─────────────────────────────────────────────────────────────────
export function useGridBreachTour() {

    const currentStep = computed(() =>
        _active.value ? (STEPS[_stepIndex.value] ?? null) : null
    );

    const stepNumber = computed(() => _stepIndex.value + 1);
    const isLast     = computed(() => _stepIndex.value >= STEPS.length - 1);
    const totalSteps = STEPS.length;

    /** Start the tour from step 0. No-op if the player has already seen it. */
    function start() {
        if (localStorage.getItem(LS_KEY)) return;
        _stepIndex.value = 0;
        _active.value    = true;
    }

    /** Force-start regardless of the seen flag — useful for dev / replays. */
    function forceStart() {
        _stepIndex.value = 0;
        _active.value    = true;
    }

    /** Advance to the next step, or complete on the last step. */
    function next() {
        if (isLast.value) {
            _complete();
        } else {
            _stepIndex.value++;
        }
    }

    /** Dismiss without finishing all steps. */
    function skip() {
        _complete();
    }

    function _complete() {
        _active.value = false;
        localStorage.setItem(LS_KEY, '1');
    }

    return {
        active: _active,
        currentStep,
        stepNumber,
        isLast,
        totalSteps,
        start,
        forceStart,
        next,
        skip,
    };
}
