/**
 * usePacketHijackTour
 *
 * Two-phase orientation tour for the Packet Hijack practice match.
 *
 * Phase 1 tour fires when the practice match opens.
 * Phase 2 tour fires automatically when the player transitions to Phase 2.
 *
 * Each phase has its own localStorage seen-flag so replaying one doesn't
 * reset the other. Shape mirrors useUiTour / useGridBreachTour.
 */

import { ref, computed } from 'vue';

// ── Step definitions ───────────────────────────────────────────────────────────

const PHASE1_STEPS = [
    {
        id:        'ph1-topbar',
        target:    '#ph-topbar',
        title:     'PACKET HIJACK',
        placement: 'bottom',
    },
    {
        id:        'ph1-data-zone',
        target:    '#ph-data-zone',
        title:     'SUSPECT BOARD',
        placement: 'bottom',
    },
    {
        id:        'ph1-ref',
        target:    '#ph-ref-panel',
        title:     'COMMAND REFERENCE',
        placement: 'left',
    },
    {
        id:        'ph1-terminal',
        target:    '#ph-terminal-col',
        title:     'TERMINAL',
        placement: 'right',
    },
];

const PHASE2_STEPS = [
    {
        id:        'ph2-data-zone',
        target:    '#ph-data-zone',
        title:     'PORT BOARD',
        placement: 'bottom',
    },
    {
        id:        'ph2-cred-strip',
        target:    '#ph-cred-strip',
        title:     'CREDENTIAL STRIP',
        placement: 'bottom',
    },
    {
        id:        'ph2-ref',
        target:    '#ph-ref-panel',
        title:     'PHASE 2 COMMANDS',
        placement: 'left',
    },
];

const LS_KEY_P1 = 'cc_ph_tour_p1_seen';
const LS_KEY_P2 = 'cc_ph_tour_p2_seen';

// ── Singleton state ────────────────────────────────────────────────────────────
const _active    = ref(false);
const _steps     = ref([]);
const _stepIndex = ref(0);
const _lsKey     = ref(LS_KEY_P1);

// ── Composable ─────────────────────────────────────────────────────────────────
export function usePacketHijackTour() {

    const currentStep = computed(() =>
        _active.value ? (_steps.value[_stepIndex.value] ?? null) : null
    );

    const stepNumber = computed(() => _stepIndex.value + 1);
    const isLast     = computed(() => _stepIndex.value >= _steps.value.length - 1);
    const totalSteps = computed(() => _steps.value.length);

    function startPhase1() {
        if (localStorage.getItem(LS_KEY_P1)) return;
        _steps.value     = PHASE1_STEPS;
        _lsKey.value     = LS_KEY_P1;
        _stepIndex.value = 0;
        _active.value    = true;
    }

    function startPhase2() {
        if (localStorage.getItem(LS_KEY_P2)) return;
        _steps.value     = PHASE2_STEPS;
        _lsKey.value     = LS_KEY_P2;
        _stepIndex.value = 0;
        _active.value    = true;
    }

    function forceStartPhase1() {
        _steps.value     = PHASE1_STEPS;
        _lsKey.value     = LS_KEY_P1;
        _stepIndex.value = 0;
        _active.value    = true;
    }

    function forceStartPhase2() {
        _steps.value     = PHASE2_STEPS;
        _lsKey.value     = LS_KEY_P2;
        _stepIndex.value = 0;
        _active.value    = true;
    }

    function next() {
        if (isLast.value) {
            _complete();
        } else {
            _stepIndex.value++;
        }
    }

    function skip() {
        _complete();
    }

    function _complete() {
        localStorage.setItem(_lsKey.value, '1');
        _active.value = false;
    }

    return {
        active: _active,
        currentStep,
        stepNumber,
        isLast,
        totalSteps,
        startPhase1,
        startPhase2,
        forceStartPhase1,
        forceStartPhase2,
        next,
        skip,
    };
}
