/**
 * usePvpFlow
 *
 * Owns the full PvP challenge handshake and Packet Hijack result handling:
 *   onHackPlayer   — challenger sends a combat challenge to a target player
 *   onAcceptChallenge / onDeclineChallenge — target responds to an incoming challenge
 *   onPacketHijackMatchComplete — syncs economy state after a PH match ends
 *
 * applyCriticalFailure is a closure defined in Game.vue after all state refs are
 * declared and passed here so a single definition handles all CF reset paths.
 *
 * Outcome state mutations (applyWin, applyLoss, applyDeclinePenalty) are delegated
 * to usePvpOutcomes for clear naming and testability.
 */

import { ref } from 'vue';
import { usePvpOutcomes } from './usePvpOutcomes.js';

export function usePvpFlow({
    player, playerId,
    ph,
    incomingChallenge,
    sendChallenge, acceptChallenge, declineChallenge,
    pollChallengeStatus,
    resyncPlayer,
    currentNodeId,
    activePacketHijack,
    tutorial,
    applyCriticalFailure,
}) {
    const pvpResult         = ref(null);   // { won, opponentHandle, loot } — shown after combat
    const awaitingChallenge = ref(false);  // true while waiting for target to accept

    const { applyWin, applyLoss, applyDeclinePenalty } = usePvpOutcomes({
        player, resyncPlayer, applyCriticalFailure,
    });

    // ── Challenger ────────────────────────────────────────────────────────────────

    /** Called when [HACK] is clicked next to a player in NodeInfoBlock. */
    async function onHackPlayer(targetPlayer) {
        if (!currentNodeId.value) return;
        if (!playerId.value || targetPlayer.id === playerId.value) return;

        awaitingChallenge.value = true;
        const result = await sendChallenge(targetPlayer.id, currentNodeId.value);
        if (!result) {
            awaitingChallenge.value = false;
            return;
        }

        console.log(`[PVP] Challenge sent to ${targetPlayer.handle} — waiting for response`);

        // Poll until the target accepts, declines, or the 30s TTL expires.
        // PacketHijackStarted WS event fires on accept — ph.init() handles the launch.
        const challengeId = result.challenge_id;
        const { status }  = await pollChallengeStatus(challengeId);
        awaitingChallenge.value = false;

        if (status === 'declined') {
            // Re-fetch pocket_creds — server may have credited the challenger (decline penalty)
            const data = await resyncPlayer();
            if (data?.player) {
                player.value.pocketCreds = data.player.pocket_creds ?? player.value.pocketCreds;
            }
        }
    }

    // ── Target ────────────────────────────────────────────────────────────────────

    /**
     * Called when the target accepts an incoming challenge.
     * acceptChallenge() triggers server-side PacketHijackMatch creation and broadcasts
     * PacketHijackStarted to both players. The Echo listener on mount calls ph.init().
     */
    async function onAcceptChallenge() {
        const c = incomingChallenge.value;
        if (!c) return;

        // Guard: we must be the target, not the challenger.
        if (c.target_id && c.target_id !== playerId.value) {
            console.warn('[COMBAT] Discarding stale/misrouted challenge — not the target');
            incomingChallenge.value = null;
            return;
        }

        const result = await acceptChallenge(c.id);
        if (!result) return;
        // WS event (PacketHijackStarted) handles the terminal launch — nothing else needed here.
    }

    async function onDeclineChallenge() {
        const c = incomingChallenge.value;
        if (!c) return;

        if (c.target_id && c.target_id !== playerId.value) {
            incomingChallenge.value = null;
            return;
        }

        const result = await declineChallenge(c.id);
        await applyDeclinePenalty(result);
    }

    // ── Packet Hijack ─────────────────────────────────────────────────────────────

    /**
     * Called when PacketHijack.vue emits 'match-complete' (player clicked DISCONNECT).
     * Syncs local economy state from the match result payload.
     */
    function onPacketHijackMatchComplete(result) {
        if (ph.isPractice) {
            tutorial.markStepDone('ph_practice');
        }

        activePacketHijack.value = false;
        ph.destroy();

        if (result.isWinner) {
            applyWin(result);
        } else {
            applyLoss();
        }

        // Sync full state from server — picks up bounty escalation, SS changes, limp flag,
        // and pvpWinsThisRun. bountyLevel drives the HUD star display and must be included.
        resyncPlayer().then(data => {
            if (data?.player) {
                player.value.bountyLevel      = data.player.bounty_level      ?? player.value.bountyLevel;
                player.value.bountyMultiplier = data.player.bounty_multiplier ?? player.value.bountyMultiplier;
                player.value.isOpenSeason     = data.player.is_open_season    ?? player.value.isOpenSeason;
                player.value.isLimping        = data.player.is_limping        ?? player.value.isLimping;
                player.value.pvpWinsThisRun   = data.player.pvp_wins_this_run ?? player.value.pvpWinsThisRun;
            }
            if (data?.rig) {
                player.value.currentSS = data.rig.current_ss ?? player.value.currentSS;
            }
        });
    }

    return {
        pvpResult,
        awaitingChallenge,
        onHackPlayer,
        onAcceptChallenge,
        onDeclineChallenge,
        onPacketHijackMatchComplete,
    };
}
