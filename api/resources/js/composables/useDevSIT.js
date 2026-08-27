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
 */

import { ref } from 'vue';

const active = ref(false); // true | false — only one proof scenario exists so far, no spec needed yet

export function useDevSIT() {

    function launch() {
        active.value = true;
    }

    function clear() {
        active.value = false;
    }

    return { active, launch, clear };
}
