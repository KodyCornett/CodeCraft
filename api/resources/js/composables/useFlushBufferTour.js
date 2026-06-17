/**
 * useFlushBufferTour
 *
 * First-time orientation tour for the Flush_Buffer minigame.
 * Fires automatically when the player encounters it during Veil's prologue quest.
 * Subsequent runs skip the tour (localStorage seen-flag).
 *
 * Shape mirrors useGridBreachTour — singleton state, same start/next/skip API.
 *
 * Steps point FloatingTerminalWindow at IDs added to FlushBuffer.vue:
 *   #fb-signals        — the three-waveform array
 *   #fb-catch-track-0  — the vertical catch track on the first waveform
 *   #fb-space-row      — the [ SPACE ] prompt at the bottom
 *   #fb-miss-pips-0    — the miss pip row on the first waveform
 *   #fb-dump-header    — the BUFFER DUMP counter at the top
 */

import { ref, computed } from 'vue';

// ── Step definitions ───────────────────────────────────────────────────────────

const STEPS = [
    {
        id:        'signals',
        target:    '#fb-signals',
        title:     'SIGNAL ARRAY',
        placement: 'left',
    },
    {
        id:        'catch-track',
        target:    '#fb-catch-track-0',
        title:     'CATCH TRACK',
        placement: 'left',
    },
    {
        id:        'space',
        target:    '#fb-space-row',
        title:     'CATCHING A PEAK',
        placement: 'top',
    },
    {
        id:        'misses',
        target:    '#fb-miss-pips-0',
        title:     'MISS TOLERANCE',
        placement: 'right',
    },
    {
        id:        'dump',
        target:    '#fb-dump-header',
        title:     'BUFFER DUMP',
        placement: 'bottom',
    },
];

const LS_KEY = 'cc_fb_tour_seen';

// ── Singleton state ────────────────────────────────────────────────────────────

const _active    = ref(false);
const _stepIndex = ref(0);

// ── Composable ─────────────────────────────────────────────────────────────────

export function useFlushBufferTour() {

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

    /** Force-start regardless of the seen flag — for dev / replay. */
    function forceStart() {
        _stepIndex.value = 0;
        _active.value    = true;
    }

    /** Advance to the next step, or complete on the last. */
    function next() {
        if (isLast.value) {
            _complete();
        } else {
            _stepIndex.value++;
        }
    }

    /** Dismiss the tour without finishing all steps. */
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
