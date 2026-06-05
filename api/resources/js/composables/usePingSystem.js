/**
 * usePingSystem
 *
 * Owns all ICE ping state and logic — real pings, false pings (Signal Noise /
 * Decoy), opponent Open Season pings, and the per-move TTL counter.
 *
 * Receives reactive refs from the caller so it can read current game state
 * without duplicating any reactive declarations.
 *
 * Server-side source of truth for the ping formula:
 *   BountyService::STAR_TIERS — effective_ice multiplier per star level
 *   NodeController::deplete   — ICE value stored on each node
 *
 * @param {Ref} pings          — the pings array ref (owned by useMapInteraction)
 * @param {Ref} player         — player ref from useGameState
 * @param {Ref} rig            — rig ref from useGameState
 * @param {Ref} currentNode    — current standing node ref from useMapInteraction
 * @param {Ref} selectedNode   — currently selected node ref from useMapInteraction
 * @param {Function} getByCanvasId — node lookup from useMapData
 * @param {Ref} bounties       — bounty board array from useBountyBoard
 */

import { watch } from 'vue';

// ── Ping formula constants ─────────────────────────────────────────────────────
// Server source of truth: BountyService::STAR_TIERS (multiplier per star level)

const PING_BASE_RANGE    = 8;
const PING_TTL_MS        = 30_000;

// Max radius (in abstract "node hops") per bounty star level.
// ★4–5 (Open Season) caps at 2 — high-bounty players can't escape to within 2 nodes.
const PING_MAX_RANGE     = [8, 5, 4, 3, 2, 2];   // index = bountyLevel (0–5)

// SVG pixel radius per node-hop — roughly matches hex node spacing on the canvas
const PING_PX_PER_HOP    = 38;
const PING_MIN_RADIUS_PX = 18;   // tight ring for a range-0 exact ping

// OS dampening by star level — at ★4–5 the bounty signal overwhelms evasion,
// making high OS builds unable to stay hidden. Full OS at ★0–3; halved at ★4; quartered at ★5.
const OS_WEIGHT = [1.0, 1.0, 1.0, 1.0, 0.5, 0.25];

// False ping TTL: expires after this many player moves.
const FALSE_PING_MOVE_TTL = 3;

export function usePingSystem({ pings, player, rig, currentNode, selectedNode, getByCanvasId, bounties }) {

    // ── Module-level mutable vars (not reactive — ping counters don't need watchers) ──
    let _falsePingMovesLeft = 0;
    let _falsePingIds       = [];   // tracks all active false pings (Signal Noise plants 2)

    // ── Core formula helpers ────────────────────────────────────────────────────

    function calcPingRange(nodeIce, playerOs, bountyLevel) {
        const lvl          = Math.min(bountyLevel, 5);
        const effectiveIce = nodeIce * (1 + lvl);
        const effectiveOs  = Math.floor(playerOs * OS_WEIGHT[lvl]);
        const raw          = PING_BASE_RANGE + effectiveOs - effectiveIce;
        const cap          = PING_MAX_RANGE[lvl] ?? 1;
        return Math.max(0, Math.min(cap, raw));
    }

    function pingRadiusPx(range) {
        return range === 0
            ? PING_MIN_RADIUS_PX
            : PING_MIN_RADIUS_PX + range * PING_PX_PER_HOP;
    }

    // ── Real ping ──────────────────────────────────────────────────────────────
    //
    // nodeIce  — ICE rating of the node the player just hacked (0 = use current node)
    // reason   — 'hack' | 'threshold' | 'movement' | 'false' | 'false_expired'
    // type     — 'real' | 'false'  (false pings come from Signal Noise command)
    //
    function firePing(reason = 'movement', nodeIce = null, type = 'real') {
        const node = currentNode.value;
        if (!node || (!node.x && !node.y)) return;

        // Resolve ICE: provided > selected node > current standing node > default 3
        const ice = nodeIce
            ?? selectedNode.value?.ice
            ?? getByCanvasId(node.canvasId)?.ice
            ?? 3;

        const os    = rig.value?.os ?? 2;
        const range = calcPingRange(ice, os, player.value.bountyLevel);

        const ping = {
            pingId:       Math.random().toString(36).slice(2),
            canvasId:     node.canvasId,
            x:            node.x,
            y:            node.y,
            range,                              // abstract node-hop radius
            radiusPx:     pingRadiusPx(range),  // pre-computed SVG radius
            bountyLevel:  player.value.bountyLevel,
            isOpenSeason: player.value.isOpenSeason,
            type,
            reason,
            createdAt: Date.now(),
        };
        pings.value.push(ping);

        setTimeout(() => {
            pings.value = pings.value.filter(p => p.pingId !== ping.pingId);
        }, PING_TTL_MS);

        if (import.meta.env.DEV) {
            console.log(
                `[ICE PING] ★${ping.bountyLevel} — ICE ${ice} × (1+${ping.bountyLevel}) ` +
                `vs OS ${os} → range ${range} (${ping.radiusPx}px) — ${reason}`
            );
        }
    }

    // ── False ping (Signal Noise / Decoy command) ──────────────────────────────
    //
    // Drops a fake ping ring at a chosen node for FALSE_PING_MOVE_TTL player moves.
    // Callers call clearFalsePings() first when replacing an existing set (Decoy),
    // vs. accumulating (Signal Noise plants 2 with one clearFalsePings() call).
    //
    function clearFalsePings() {
        pings.value = pings.value.filter(p => !_falsePingIds.includes(p.pingId));
        _falsePingIds = [];
    }

    function fireFalsePing(targetNode) {
        if (!targetNode?.x) return;

        const range = calcPingRange(
            targetNode.ice ?? 3,
            rig.value?.os ?? 2,
            player.value.bountyLevel,
        );

        const ping = {
            pingId:       Math.random().toString(36).slice(2),
            canvasId:     targetNode.canvasId ?? targetNode.id,
            x:            targetNode.x,
            y:            targetNode.y,
            range,
            radiusPx:     pingRadiusPx(range),
            bountyLevel:  player.value.bountyLevel,
            isOpenSeason: player.value.isOpenSeason,
            type:         'false',
            reason:       'signal_noise',
            createdAt:    Date.now(),
        };
        pings.value.push(ping);
        _falsePingIds.push(ping.pingId);
        _falsePingMovesLeft = FALSE_PING_MOVE_TTL;

        if (import.meta.env.DEV) {
            console.log(`[SIGNAL NOISE] False ping planted at ${ping.canvasId} — ${FALSE_PING_MOVE_TTL} moves`);
        }
    }

    // ── Opponent Open Season pings ─────────────────────────────────────────────
    //
    // For every ★4+ player on the bounty board, fires a red ping ring at their
    // last known canvas node. Called on every bounty board refresh.
    // OS 2 (base minimum) is used for targets so the ring is always tight (≤2 nodes).
    //
    function fireOpponentPings() {
        const targets = bounties.value.filter(b => b.isOpenSeason && b.canvasNodeId);
        for (const target of targets) {
            const node = getByCanvasId(target.canvasNodeId);
            if (!node?.x && !node?.y) continue;

            const ice    = node.ice ?? 3;
            const lvl    = Math.min(target.stars, 5);
            const range  = calcPingRange(ice, 2, lvl);
            const pingId = Math.random().toString(36).slice(2);

            pings.value.push({
                pingId,
                canvasId:     target.canvasNodeId,
                x:            node.x,
                y:            node.y,
                range,
                radiusPx:     pingRadiusPx(range),
                isOpenSeason: true,
                type:         'real',
                handle:       target.handle,
            });

            setTimeout(() => {
                pings.value = pings.value.filter(p => p.pingId !== pingId);
            }, PING_TTL_MS);
        }
    }

    // Trigger on every board refresh — no interval needed since pings are hack-driven.
    // useBountyBoard replaces the entire array ref on each poll, so shallow watch is correct.
    watch(bounties, () => {
        fireOpponentPings();
    });

    // ── Per-move tick (call from handlePlayerMoved) ────────────────────────────
    //
    // Decrements the false-ping TTL counter. When it hits 0, false pings are
    // cleared and a real re-ping fires so ICE snaps back on.
    //
    function onMoveTick() {
        if (_falsePingIds.length > 0 && _falsePingMovesLeft > 0) {
            _falsePingMovesLeft--;
            if (_falsePingMovesLeft <= 0) {
                clearFalsePings();
                // Re-ping real location so ICE snaps back on
                setTimeout(() => firePing('false_expired'), 0);
            }
        }
    }

    // ── Full reset (call on CyberDoc bank / critical failure) ─────────────────
    function reset() {
        pings.value         = [];
        _falsePingIds       = [];
        _falsePingMovesLeft = 0;
    }

    return {
        firePing,
        fireFalsePing,
        clearFalsePings,
        onMoveTick,
        reset,
    };
}
