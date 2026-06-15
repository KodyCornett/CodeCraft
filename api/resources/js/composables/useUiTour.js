/**
 * useUiTour
 *
 * Manages the post-tutorial UI orientation tour.
 * Each step points FloatingTerminalWindow at a HUD or map element
 * and explains that part of the interface.
 *
 * Add new stops by appending to STEPS below.
 * Seen state is persisted to localStorage so the tour doesn't
 * repeat on every reload, but can be replayed via forceStart().
 */

import { ref, computed } from 'vue';

// ── Step definitions ───────────────────────────────────────────────────────────
//
// id        — unique key, referenced by UiTour.vue to render the right content
// target    — CSS selector; FloatingTerminalWindow points its leader line here
// title     — shown in the window header bar
// placement — 'auto' | 'top' | 'right' | 'bottom' | 'left'
//
const STEPS = [
    {
        id:        'splice',
        target:    '#nav-splice',
        title:     'SPLICE BROWSER',
        placement: 'top',
    },
    {
        id:        'uplink',
        target:    '#hud-uplink',
        title:     'UPLINK',
        placement: 'bottom',
    },
    {
        id:        'bounty',
        target:    '#hud-bounty',
        title:     'BOUNTY SYSTEM',
        placement: 'bottom',
    },
    {
        id:        'pocket-wallet',
        target:    '#nav-status',
        title:     'POCKET vs WALLET',
        placement: 'top',
    },
    {
        id:        'ss',
        target:    '#panel-ss',
        title:     'SYS.STABILITY',
        placement: 'left',
    },
    {
        id:        'node-info',
        target:    '#panel-node-info',
        title:     'NODE INFO',
        placement: 'left',
    },
    {
        id:        'loadout',
        target:    '#panel-loadout',
        title:     'LOADOUT',
        placement: 'left',
    },
    {
        id:        'terminal',
        target:    '#nav-terminal',
        title:     'MISSION TERMINAL',
        placement: 'top',
    },
    // More stops will be added here as the tour expands.
];

const LS_KEY = 'cc_ui_tour_seen';

// ── Singleton state ───────────────────────────────────────────────────────────
// All callers share the same refs so any component can trigger or advance the tour.
const _active     = ref(false);
const _stepIndex  = ref(0);

// ── Composable ────────────────────────────────────────────────────────────────
export function useUiTour() {

    const currentStep = computed(() =>
        _active.value ? (STEPS[_stepIndex.value] ?? null) : null
    );

    const stepNumber  = computed(() => _stepIndex.value + 1);
    const isLast      = computed(() => _stepIndex.value >= STEPS.length - 1);
    const totalSteps  = STEPS.length;

    /**
     * Start the tour from a given step index.
     * No-op if the player has already seen the tour (localStorage flag set).
     * Use forceStart() to replay regardless.
     */
    function start(fromStep = 0) {
        if (localStorage.getItem(LS_KEY)) return;
        _stepIndex.value = fromStep;
        _active.value    = true;
    }

    /**
     * Force-start the tour, ignoring the seen flag. Useful for dev testing
     * or if the player manually re-opens the tour from a menu.
     */
    function forceStart(fromStep = 0) {
        _stepIndex.value = fromStep;
        _active.value    = true;
    }

    /** Advance to the next step, or complete the tour if on the last step. */
    function next() {
        if (isLast.value) {
            _complete();
        } else {
            _stepIndex.value++;
        }
    }

    /** Dismiss the tour without completing all steps. */
    function skip() {
        _complete();
    }

    function _complete() {
        _active.value = false;
        localStorage.setItem(LS_KEY, '1');
    }

    return {
        active:      _active,
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
