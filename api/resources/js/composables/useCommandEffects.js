/**
 * useCommandEffects
 *
 * Owns active timed command effects and all map-mode command dispatch.
 *
 * activeEffects tracks moves remaining per slug (e.g. { ghost_protocol: 3 }).
 * applyEffectDecrement() is called once per player move from handlePlayerMoved.
 * applyTrapEffects() handles server-returned trap_triggered payloads.
 * onUseCommand() dispatches the full [USE ON MAP] switch per command name.
 *
 * Parameters (all are reactive refs or functions from the caller's scope):
 *   player, rig, commands — game state refs
 *   activateCommand       — POST /api/player/activate-command (from useGameState)
 *   fireFalsePing, clearFalsePings — false-ping helpers (from usePingSystem)
 *   placeDecoy            — fn(canvasId, cmdId) (from useTrapSystem)
 *   trapTargetMode        — ref; set to { cmd, match } to enter targeting mode
 *   pings                 — ref; Dark Mode filters real pings from this array
 *   currentNode           — ref; position used by Signal Noise / Decoy
 *   currentNodeId         — ref; used by Decoy to check selected vs current
 *   selectedNode          — ref; Decoy prefers the inspected node if it differs
 *   getNodesNear          — fn({ x, y, minPx, maxPx, count }) from useMapData
 *   hudFlash              — ref; RootKit block writes the flash message here
 */

import { ref } from 'vue';

export function useCommandEffects({
    player, rig, commands,
    activateCommand,
    fireFalsePing, clearFalsePings,
    placeDecoy,
    trapTargetMode,
    pings,
    currentNode,
    currentNodeId,
    selectedNode,
    getNodesNear,
    hudFlash,
}) {
    const activeEffects = ref({});

    // Magnitude of OS reduction applied by an OS Exploit trap — restored on expiry.
    let _osExploitReduction = 0;
    // ID of the command locked by a Buffer Overflow trap — re-enabled on expiry.
    let _bufferOverflowCmdId = null;
    // Timer for the HUD flash message — managed internally.
    let _flashTimer = null;

    /** "Ghost Protocol" → "ghost_protocol" */
    function cmdSlug(name) {
        return name.toLowerCase().replace(/\s+/g, '_');
    }

    /**
     * Decrement every active timed effect by 1 move.
     * Prunes expired effects and reverts any stat boosts they applied.
     * Call once per player move from handlePlayerMoved.
     */
    function applyEffectDecrement() {
        for (const slug of Object.keys(activeEffects.value)) {
            activeEffects.value[slug] = Math.max(0, activeEffects.value[slug] - 1);

            // Reflect countdown in the commands list so LoadoutBlock shows the badge
            const cmd = commands.value.find(c => cmdSlug(c.name) === slug);
            if (cmd) cmd.movesLeft = activeEffects.value[slug];

            if (activeEffects.value[slug] === 0) {
                delete activeEffects.value[slug];
                if (cmd) cmd.movesLeft = 0;

                // Firewall Patch applied +2 on activation — undo on expiry
                if (slug === 'firewall_patch') {
                    rig.value.firewall = Math.max(0, (rig.value.firewall ?? 0) - 2);
                }
                // OS Exploit — restore the OS stat that was reduced on trap hit
                if (slug === 'os_exploit') {
                    rig.value.os = (rig.value.os ?? 0) + _osExploitReduction;
                    _osExploitReduction = 0;
                }
                // Buffer Overflow — re-enable the command that was randomly locked
                if (slug === 'buffer_overflow' && _bufferOverflowCmdId) {
                    const locked = commands.value.find(c => c.id === _bufferOverflowCmdId);
                    if (locked) { locked.cooldown = false; locked.movesLeft = 0; }
                    _bufferOverflowCmdId = null;
                }
            }
        }
    }

    /**
     * Apply effects from a server trap_triggered response.
     * Merges timed effects into activeEffects, syncs SS, applies immediate stat changes.
     * Called from handlePlayerMoved when position() returns trap_triggered.
     */
    function applyTrapEffects(trapData, serverActiveEffects, currentSsFromServer) {
        const { command_name, effect } = trapData;

        // Merge server-applied timed effects (os_exploit, buffer_overflow, rootkit)
        if (serverActiveEffects) {
            Object.assign(activeEffects.value, serverActiveEffects);
        }
        // Sync SS if the server applied damage (Packet Flood)
        if (currentSsFromServer != null) {
            player.value.currentSS = currentSsFromServer;
        }
        // OS Exploit — reduce OS immediately; restore on effect expiry
        if (effect.os_reduction) {
            _osExploitReduction = effect.os_reduction;
            rig.value.os = Math.max(0, (rig.value.os ?? 0) - _osExploitReduction);
        }
        // Buffer Overflow — randomly lock one equipped, ready command
        if (command_name === 'Buffer Overflow') {
            const ready = commands.value.filter(c => c.equipped && !c.cooldown);
            if (ready.length > 0) {
                const target = ready[Math.floor(Math.random() * ready.length)];
                _bufferOverflowCmdId = target.id;
                target.cooldown  = true;
                target.movesLeft = effect.moves ?? 2;
            }
        }
    }

    /**
     * Dispatch a player-activated map command from [USE ON MAP].
     * Trap commands enter node-targeting mode.
     * Self-targeted timed commands are registered server-side and mirrored in activeEffects.
     */
    async function onUseCommand(cmd) {
        const match = commands.value.find(c => c.id === cmd.id);
        if (!match || match.cooldown) return;

        // RootKit trap effect — all commands locked while active
        if ((activeEffects.value.rootkit ?? 0) > 0) {
            hudFlash.value = 'ROOTKIT ACTIVE — command systems locked';
            clearTimeout(_flashTimer);
            _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
            return;
        }

        // Mark cooldown immediately — cleared on Street Doc visit (bankCreds)
        match.cooldown  = true;
        match.movesLeft = 0;

        const slug         = cmdSlug(cmd.name);
        const moveDuration = cmd.duration?.moves ?? 0;

        console.log(`[CMD] ${cmd.name.toUpperCase()} activated`);

        // Register server-side for commands needing backend enforcement
        // (Ghost Protocol trace suppression, Blackout PvP block, Firewall Patch stat boost)
        if (cmd.targetType === 'self' && moveDuration > 0) {
            await activateCommand(cmd.id);
            activeEffects.value[slug] = moveDuration;
            match.movesLeft           = moveDuration;
        }

        switch (cmd.name) {
            case 'Crash':
            case 'Packet Flood':
            case 'OS Exploit':
            case 'Buffer Overflow':
            case 'RootKit':
                // Node-targeted traps — enter targeting mode. Cooldown is applied
                // once the player picks a node and the server confirms placement.
                match.cooldown  = false;
                match.movesLeft = 0;
                trapTargetMode.value = { cmd, match };
                console.log(`[TRAP] ${cmd.name} targeting mode active — awaiting node selection.`);
                break;

            case 'Ghost Protocol':
                // Trace suppression: server checks active_effects['ghost_protocol'] > 0.
                // Ping suppression: handled in handlePlayerMoved + onHackComplete.
                console.log(`[GHOST PROTOCOL] Active — ${moveDuration} moves. Traces suppressed. Pings masked.`);
                break;

            case 'Dark Mode':
                // Suppresses ALL ICE pings for the duration. Fades current pings immediately.
                pings.value = pings.value.filter(p => p.type !== 'real');
                console.log(`[DARK MODE] Active — ${moveDuration} moves. All ICE pings suppressed.`);
                break;

            case 'Signal Noise': {
                // Plants 2 false pings at nearby nodes to confuse ICE tracking.
                const node   = currentNode.value;
                const nearby = node ? getNodesNear(node.x, node.y, { minPx: 80, maxPx: 260, count: 2 }) : [];
                if (nearby.length === 0) console.warn('[SIGNAL NOISE] No nearby nodes found for false pings.');
                clearFalsePings();
                for (const target of nearby) fireFalsePing(target);
                console.log(`[SIGNAL NOISE] ${nearby.length} false ping(s) planted.`);
                break;
            }

            case 'Decoy': {
                // Plants a false trace on the target node + a local false ping.
                const decoyTarget = (selectedNode.value && selectedNode.value.canvasId !== currentNodeId.value)
                    ? selectedNode.value
                    : getNodesNear(currentNode.value?.x ?? 0, currentNode.value?.y ?? 0, { minPx: 100, maxPx: 400, count: 1 })[0];

                if (decoyTarget) {
                    placeDecoy(decoyTarget.canvasId, cmd.id);
                    clearFalsePings();
                    fireFalsePing(decoyTarget);
                    console.log(`[DECOY] False trace + ping planted at ${decoyTarget.canvasId}.`);
                } else {
                    console.warn('[DECOY] No valid target node found.');
                }
                break;
            }

            case 'Firewall Patch':
                // +2 effective Firewall for moveDuration moves (cosmetic/PvP).
                rig.value.firewall = (rig.value.firewall ?? 0) + 2;
                console.log(`[FIREWALL PATCH] Firewall boosted to ${rig.value.firewall} for ${moveDuration} moves.`);
                break;

            case 'Blackout':
                // Blocks incoming PvP challenges for moveDuration moves — enforced server-side.
                console.log(`[BLACKOUT] Incoming challenges blocked for ${moveDuration} moves.`);
                break;

            default:
                console.log(`[CMD] ${cmd.name} — target selection UI not yet implemented.`);
        }
    }

    return {
        activeEffects,
        cmdSlug,
        applyEffectDecrement,
        applyTrapEffects,
        onUseCommand,
    };
}
