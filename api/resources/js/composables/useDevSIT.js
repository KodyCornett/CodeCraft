/**
 * useDevSIT
 *
 * Module-level singleton bridging DevSITLab.vue (splice://dev/sit-lab)
 * and Game.vue, mirroring useDevComposer.js's launch/clear shape exactly.
 *
 * SIT — Splice Interface Terminal (components/minigame/sit/) — is a
 * separate experiment from both the live node-hack generator
 * (components/minigame/generator/) and the composer
 * (components/minigame/composer/) — it never runs during a real hack.
 * This composable exists only to hand Game.vue a signal to mount SIT.vue
 * with, purely for in-game feel testing. No reward endpoint is ever
 * called from this path.
 *
 * `active` holds either null (no session requested) or a scenario key
 * string (see components/minigame/sit/scenarios/index.js) — now that SIT
 * has more than one hand-written scenario, DevSITLab.vue needs to say
 * which one to launch.
 */

import { ref } from 'vue';
import { DEFAULT_SCENARIO_KEY } from '@/components/minigame/sit/scenarios/index.js';

const active = ref(null); // null | scenario key string

export function useDevSIT() {

    function launch(scenarioKey) {
        active.value = scenarioKey ?? DEFAULT_SCENARIO_KEY;
    }

    function clear() {
        active.value = null;
    }

    return { active, launch, clear };
}
