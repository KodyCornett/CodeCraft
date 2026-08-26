/**
 * useHackFlow
 *
 * Owns the full generic node-hack PvE lifecycle:
 *   onHackSelected → onHackComplete / onHackFailed / onHackAbort
 *
 * onHackSelected no longer hardcodes GridBreach — it calls generateMinigame()
 * (components/minigame/generator/generateMinigame.js), which picks a template
 * from the pool and hands back a generation spec. activeHack IS that spec;
 * HackMinigame.vue resolves its `key` against the pool and mounts whatever
 * got picked. Reward math (deplete()) never looks at which template ran —
 * only `resource` and the completionPct it reports — so this stays a plain
 * PvE minigame lifecycle regardless of which pool entry is in play.
 *
 * Also owns per-session ICE escalation tracking (nodeHackCounts, effectiveNodeIce).
 * Every successful breach on a node raises its effective ICE by 1 per ICE_ESCALATION
 * hacks, pushing players to explore rather than farm the same spot.
 *
 * applyCriticalFailure is a closure defined in Game.vue (after all state refs are
 * declared) and passed in so the single definition handles all CF reset paths.
 */

import { ref } from 'vue';
import { useCodexFind } from './useCodexFind.js';
import { generateMinigame } from '@/components/minigame/generator/generateMinigame.js';

const MIN_ICE        = 3;
const ICE_ESCALATION = 2;  // successful hacks per +1 effective ICE on a node

export function useHackFlow({
    player, playerId,
    deplete, applyDamage,
    storeTrace, refreshTraces,
    updateNodeResources,
    firePing,
    activeEffects,
    hackCount, checkBountyEscalation,
    currentNodeId, selectedNode,
    tutorial, gbTour,
    applyCriticalFailure,
}) {
    const codexFind = useCodexFind();

    // Track successful hacks per node UUID this session — drives effective ICE escalation.
    const nodeHackCounts = ref(new Map());

    // Currently active hack: a generation spec from generateMinigame(),
    // { key, node: { ...selectedNode, ice }, resource }.
    const activeHack = ref(null);

    /** Returns the effective ICE for a node, escalating by 1 per ICE_ESCALATION hacks. */
    function effectiveNodeIce(node) {
        if (!node) return MIN_ICE;
        const baseIce = Math.max(MIN_ICE, node.ice ?? MIN_ICE);
        const hacks   = nodeHackCounts.value.get(node.id) ?? 0;
        return Math.min(10, baseIce + Math.floor(hacks / ICE_ESCALATION));
    }

    /**
     * Initiate a hack — called when player clicks [HACK] in NodeInfoBlock.
     * Guard: player must be standing on the node (inspecting a remote node shows
     * the panel, so we re-check here rather than relying on the panel to gate it).
     */
    function onHackSelected(resource) {
        if (!selectedNode.value) return;
        if (selectedNode.value.canvasId !== currentNodeId.value) return;

        const ice  = effectiveNodeIce(selectedNode.value);
        const node = { ...selectedNode.value, ice };

        // The generator decides WHICH template plays this hack — see
        // components/minigame/generator/generateMinigame.js. activeHack is
        // now a generation spec ({ key, node, resource }), not just context;
        // HackMinigame.vue resolves `key` against the pool and mounts it.
        activeHack.value = generateMinigame({ node, resource });

        // First-time breach during tutorial — start the GridBreach orientation
        // tour, but only when GridBreach is actually the template that got
        // picked. gbTour.start() is a no-op once the player has already seen
        // it (localStorage flag). Once more templates exist this'll want its
        // own per-template onboarding hook instead of a single hardcoded key.
        if (tutorial.isTutorialActive.value && activeHack.value.key === 'grid_breach') {
            gbTour.start();
        }
    }

    function applyHackReward(resource, amount) {
        // Cred hacks fill the pocket (at-risk) — not the safe wallet.
        // The wallet only grows when pocket is banked at Street Doc.
        if (resource === 'creds')  player.value.pocketCreds = (player.value.pocketCreds ?? 0) + amount;
        if (resource === 'tech')   player.value.techPoints  = (player.value.techPoints  ?? 0) + amount;
        if (resource === 'uplink') player.value.uplink      = player.value.maxUplink    ?? 3;
    }

    async function onHackComplete({ resource, amount, completionPct = 1.0 }) {
        const node    = selectedNode.value;
        const nodeId  = node?.id;
        const nodeIce = effectiveNodeIce(node);
        activeHack.value = null;

        // Quest trigger: first breach (success counts)
        tutorial.markStepDone('hack');

        // Escalate this node's effective ICE for future attempts this session
        if (nodeId) {
            nodeHackCounts.value.set(nodeId, (nodeHackCounts.value.get(nodeId) ?? 0) + 1);
        }

        // Increment session hack counter then check for bounty level-up.
        // Mirror into nodesHackedThisRun so the STATUS page stays live.
        hackCount.value += 1;
        player.value.nodesHackedThisRun = hackCount.value;
        checkBountyEscalation(nodeIce, firePing);

        // Fire a hack ping — suppressed by Ghost Protocol and Dark Mode
        const ghostActive = (activeEffects.value.ghost_protocol ?? 0) > 0;
        const darkActive  = (activeEffects.value.dark_mode      ?? 0) > 0;
        if (player.value.bountyLevel >= 1 && !ghostActive && !darkActive) {
            firePing('hack', nodeIce);
        }

        applyHackReward(resource, amount);
        console.log(
            `[HACK] ${resource.toUpperCase()} +${amount}` +
            ` | bounty LVL ${player.value.bountyLevel}` +
            ` | hacks #${hackCount.value}`
        );

        // Roll for a "Codex — Found" prompt — no-op unless a thread is active.
        codexFind.rollForFind();

        // Tell the backend — server computes the authoritative reward from completion_pct.
        // amount is used for the optimistic local update above; server value synced below.
        if (nodeId) {
            const patch = await deplete(nodeId, resource, resource !== 'uplink' ? completionPct : 0);

            if (patch) {
                updateNodeResources(nodeId, patch);

                // Sync server-authoritative balances
                if (patch.player?.tech_points !== undefined)
                    player.value.techPoints = patch.player.tech_points;
                if (patch.player?.pocket_creds !== undefined)
                    player.value.pocketCreds = patch.player.pocket_creds;
                if (patch.currentUplink != null)
                    player.value.uplink = patch.currentUplink;
                // bounty_multiplier and is_open_season are server-authoritative
                if (patch.player?.bounty_multiplier !== undefined)
                    player.value.bountyMultiplier = patch.player.bounty_multiplier;
                if (patch.player?.is_open_season !== undefined)
                    player.value.isOpenSeason = patch.player.is_open_season;
                // nodes_hacked_this_run is the raw count — mirror back
                if (patch.player?.nodes_hacked_this_run !== undefined) {
                    hackCount.value = patch.player.nodes_hacked_this_run;
                    player.value.nodesHackedThisRun = patch.player.nodes_hacked_this_run;
                }
                if (patch.bountyEvent?.type === 'bounty_marked') {
                    console.log(`[BOUNTY] Server confirmed: on the board at hack ${patch.player?.nodes_hacked_this_run}`);
                }
                if (patch.bountyEvent?.type === 'open_season_triggered') {
                    console.warn('[BOUNTY] ⚡ OPEN SEASON — server confirmed');
                }

                // Pull the fresh trace immediately so the panel shows it without polling
                refreshTraces();
            }
        }
    }

    async function onHackFailed({ resource, amount }) {
        // Capture node before clearing activeHack — needed for ICE + SS damage
        const failedNode = activeHack.value?.node ?? selectedNode.value;
        activeHack.value = null;

        // Quest trigger: first breach (failure counts — you learn either way)
        tutorial.markStepDone('hack');

        // If uplink is already 0, grant exactly 1 escape move so the player
        // can reach a node they can actually breach for full recovery.
        if ((player.value.uplink ?? 0) === 0) {
            player.value.uplink = 1;
        }

        // SS damage: server computes max(1, nodeICE − effectiveFirewall).
        // The client no longer calculates or sends the damage amount.
        const nodeCanvasId = failedNode?.canvasId ?? failedNode?.id ?? null;
        console.log(`[HACK] Breach failed on ${nodeCanvasId}`);

        if (playerId.value && nodeCanvasId) {
            const res = await applyDamage(nodeCanvasId, 'pve');
            if (res) {
                player.value.currentSS = res.current_ss;
                player.value.maxSS     = res.max_ss;

                if (res.event === 'critical_failure') {
                    applyCriticalFailure(res.critical_failure ?? {});
                }
            }
        }

        // Leave a data fragment even on failure — the attempt is detectable by other runners.
        const nodeId = failedNode?.id;
        if (nodeId && playerId.value) {
            await storeTrace(nodeId, playerId.value);
            refreshTraces();
        }
    }

    function onHackAbort() {
        activeHack.value = null;
    }

    return {
        activeHack,
        effectiveNodeIce,
        onHackSelected,
        onHackComplete,
        onHackFailed,
        onHackAbort,
    };
}
