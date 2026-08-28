/**
 * useDevSignalLock
 *
 * Module-level singleton bridging DevSignalLockLab.vue
 * (splice://dev/signal-lock-lab) and Game.vue, mirroring useDevComposer.js
 * and useDevSIT.js's launch/clear shape exactly.
 *
 * SIGNAL LOCK (components/minigame/generator/templates/SignalLock.vue) is a
 * candidate addition to the live node-hack pool (generator/pool.js) — built
 * against that pool's exact contract so it's a one-line promotion later,
 * but NOT registered there yet. This composable exists only to hand
 * Game.vue a signal to mount SignalLock.vue with, for feel-testing at a
 * chosen ICE level and rig stat combo, completely separate from real node
 * hacks. No reward endpoint is ever called from this path — Game.vue's
 * handler for the dev mount just logs the outcome.
 */

import { ref } from 'vue';

// null, or a { ice, cpu, ram, os } test config to launch with.
const active = ref(null);

export function useDevSignalLock() {

    function launch(config) {
        active.value = {
            ice: config?.ice ?? 3,
            cpu: config?.cpu ?? 3,
            ram: config?.ram ?? 2,
            os:  config?.os  ?? 2,
        };
    }

    function clear() {
        active.value = null;
    }

    return { active, launch, clear };
}
