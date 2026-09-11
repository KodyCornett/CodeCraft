/**
 * useTrapSystem
 *
 * API calls and all reactive state for node traps and decoys, including the
 * node-targeting-mode flow triggered when a trap-type command is activated.
 *
 * API layer:
 *   placeTrap(nodeId, commandId)     POST /api/nodes/{id}/place-trap
 *   placeDecoy(canvasId, commandId)  POST /api/nodes/{id}/place-decoy
 *   fetchMyTraps()                   GET  /api/player/traps
 *
 * Reactive state:
 *   myTraps               — server-persisted list of the player's active traps
 *   trapTargetMode        — { cmd, match } | null; set by useCommandEffects
 *                            when a trap-type command (Crash/Packet Flood/OS
 *                            Exploit/Buffer Overflow/RootKit) is activated
 *   trapHitNotification   — set via handleTrapHit() when the player walks
 *                            into someone else's trap
 *   trapFiredNotification — set via handleTrapFired() when one of the
 *                            player's own traps catches a victim (Echo push)
 *
 * hudFlash — passed in so attemptPlaceTrap() can show an out-of-range
 * message; owns its own flash timer, same pattern as useCommandEffects.js's
 * RootKit-lock flash.
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useTrapSystem({ hudFlash } = {}) {
    const myTraps               = ref([]);
    const trapTargetMode        = ref(null);
    const trapHitNotification   = ref(null);
    const trapFiredNotification = ref(null);

    let _flashTimer = null;

    async function placeTrap(nodeId, commandId) {
        try {
            const res = await axios.post(`/api/nodes/${nodeId}/place-trap`, { command_id: commandId });
            return res.data;
        } catch (e) {
            if (import.meta.env.DEV) {
                console.warn('[TRAP] Server placement failed:', e?.response?.data);
            }
            return null;
        }
    }

    async function placeDecoy(canvasId, commandId) {
        try {
            const res = await axios.post(`/api/nodes/${canvasId}/place-decoy`, { command_id: commandId });
            return res.data;
        } catch (e) {
            if (import.meta.env.DEV) {
                console.warn('[DECOY] Server call failed:', e?.response?.data);
            }
            return null;
        }
    }

    async function fetchMyTraps() {
        try {
            const res = await axios.get('/api/player/traps');
            myTraps.value = res.data.traps ?? [];
        } catch {
            // Non-critical — map markers are cosmetic only
        }
    }

    /**
     * Node-click handler for trap targeting mode. Call from the map's node-click
     * handler before falling through to normal node selection/movement.
     * Returns true if the click was consumed by trap targeting.
     */
    function attemptPlaceTrap(event) {
        if (!trapTargetMode.value) return false;

        if (!event.isAdjacent) {
            clearTimeout(_flashTimer);
            hudFlash.value = `OUT OF RANGE — select an adjacent node to plant ${trapTargetMode.value.cmd.name}`;
            _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
            return true;
        }

        const { cmd, match } = trapTargetMode.value;
        const ttl = cmd.duration?.moves ?? 5;

        placeTrap(event.node.id, cmd.id).then(res => {
            if (res) fetchMyTraps();
            else { match.cooldown = false; match.movesLeft = 0; }
        });

        match.cooldown       = true;
        match.movesLeft      = ttl;
        trapTargetMode.value = null;
        return true;
    }

    /** Cancel out of targeting mode — revert the premature cooldown so the command stays ready. */
    function cancelTrapTarget() {
        if (!trapTargetMode.value) return;
        trapTargetMode.value.match.cooldown = false;
        trapTargetMode.value = null;
    }

    /** Called from handlePlayerMoved when position() returns a trap_triggered payload. */
    function handleTrapHit(trapData) {
        trapHitNotification.value = {
            commandName: trapData.command_name,
            effect:      trapData.effect,
        };
    }

    /** Called from the player.{id} Echo channel's .trap.triggered event. */
    function handleTrapFired(data) {
        trapFiredNotification.value = {
            commandName:  data.command_name,
            victimHandle: data.victim_handle,
        };
    }

    return {
        myTraps, trapTargetMode, trapHitNotification, trapFiredNotification,
        placeTrap, placeDecoy, fetchMyTraps,
        attemptPlaceTrap, cancelTrapTarget, handleTrapHit, handleTrapFired,
    };
}
