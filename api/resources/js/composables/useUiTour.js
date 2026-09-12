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
// 'splice', 'pocket-wallet', and 'terminal' all target '#start-menu-btn' —
// there's no longer a persistent per-program taskbar button to point at
// individually (NavBar's taskbar only shows programs that are actually
// open now); the Start Menu is the one stable, always-visible launcher for
// all of them, so that's what these three stops point to instead.
//
const STEPS = [
    {
        id:        'splice',
        target:    '#start-menu-btn',
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
        target:    '#start-menu-btn',
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
        target:    '#start-menu-btn',
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
     *
     * Returns whether the tour actually activated — several stops point at
     * Map-only elements (HUD, side panel) that don't exist until the Map
     * program is open, so Game.vue uses this to open Map for the tour's
     * duration without auto-opening it on every ordinary boot.
     */
    function start(fromStep = 0) {
        if (localStorage.getItem(LS_KEY)) return false;
        _stepIndex.value = fromStep;
        _active.value    = true;
        return true;
    }

    /**
     * Force-start the tour, ignoring the seen flag. Useful for dev testing
     * or if the player manually re-opens the tour from a menu.
     */
    function forceStart(fromStep = 0) {
        _stepIndex.value = fromStep;
        _active.value    = true;
        return true;
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
