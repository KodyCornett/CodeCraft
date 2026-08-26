/**
 * useDevComposer
 *
 * Module-level singleton bridging DevGeneratorLab.vue (splice://dev/generator-lab)
 * and Game.vue, mirroring useDevBankHeist.js's launch/clear shape exactly.
 *
 * The composer (input models x win rules, see components/minigame/composer/)
 * is a separate experiment from the live node-hack generator
 * (components/minigame/generator/) — it never runs during a real hack.
 * This composable exists only to hand Game.vue a spec to mount
 * ComposedMinigame.vue with, purely for in-game visual/feel testing while
 * the composer is being built out. No reward endpoint is ever called from
 * this path.
 */

import { ref } from 'vue';

const activeComposedSpec = ref(null); // { inputKey, ruleKey, ice } | null

export function useDevComposer() {

    function launch(spec) {
        activeComposedSpec.value = spec;
    }

    function clear() {
        activeComposedSpec.value = null;
    }

    return { activeComposedSpec, launch, clear };
}
