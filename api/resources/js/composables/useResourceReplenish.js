/**
 * useResourceReplenish
 *
 * Drives the per-second countdown tick for node resource replenishment.
 * Exposes nodeResources (computed) so NodeInfoBlock can show button states
 * and time-until-ready without needing to poll the server.
 *
 * Call start() in onMounted and stop() in onUnmounted.
 * REPLENISH_MS must match NodeService::CRED_REPLENISH_MINUTES (10 min).
 */

import { ref, computed } from 'vue';

const REPLENISH_MS = 10 * 60 * 1000;

export function useResourceReplenish({ player, selectedNode, getByCanvasId }) {
    const _now     = ref(Date.now());
    let   _nowTick = null;

    /** Seconds until lastHackedAt + REPLENISH_MS, or 0 if already ready. */
    function secsUntilReplenish(lastHackedAt) {
        if (!lastHackedAt) return 0;
        // Laravel may serialize without a timezone offset ("Y-m-d H:i:s").
        // Normalize to unambiguous UTC so JS doesn't shift by the user's offset.
        const ts      = String(lastHackedAt).replace(' ', 'T').replace(/([+-]\d{2}:\d{2}|Z)$/, '') + 'Z';
        const readyAt = new Date(ts).getTime() + REPLENISH_MS;
        return Math.max(0, Math.ceil((readyAt - _now.value) / 1000));
    }

    // Drives NodeInfoBlock [HACK] button enabled states. Merges:
    //   • node resource depletion flags from the DB (server-authoritative)
    //   • client-side replenish countdown so the button re-enables without polling
    //   • display values for the panel (what the player could earn)
    const nodeResources = computed(() => {
        // Always read from the live store — selectedNode.value is a spread copy
        // made at click time, so depletion patches would be missed without this.
        const node    = selectedNode.value?.canvasId
            ? (getByCanvasId(selectedNode.value.canvasId) ?? selectedNode.value)
            : null;
        const ssEmpty = (player.value.currentSS ?? 1) <= 0;

        const credSecsLeft     = secsUntilReplenish(node?.credLastHackedAt);
        const movementSecsLeft = secsUntilReplenish(node?.movementLastHackedAt);

        // A resource is available if the DB says undepleted, OR the client
        // countdown has reached zero (server confirms on next deplete call).
        const credReady     = !node?.credDepleted     || credSecsLeft     === 0;
        const movementReady = !node?.movementDepleted || movementSecsLeft === 0;

        return {
            creds: {
                available:     !!node && !ssEmpty && credReady,
                value:         node?.credValueBase ?? 750,
                replenishesIn: credReady ? 0 : credSecsLeft,
            },
            tech: {
                // Tech hacks draw from the same cred pool — share the depletion flag.
                available:     !!node && !ssEmpty && credReady,
                value:         Math.max(1, Math.floor((node?.credValueBase ?? 100) / 100)),
                replenishesIn: credReady ? 0 : credSecsLeft,
            },
            uplink: {
                available:     !!node && !ssEmpty && movementReady,
                value:         player.value.maxUplink ?? 3,
                replenishesIn: movementReady ? 0 : movementSecsLeft,
            },
        };
    });

    function start() {
        _nowTick = setInterval(() => { _now.value = Date.now(); }, 1000);
    }

    function stop() {
        clearInterval(_nowTick);
    }

    return { nodeResources, start, stop };
}
