/**
 * useCombat
 *
 * Manages the PvP combat challenge handshake and result submission.
 *
 * Challenge flow:
 *   1. Challenger calls challenge(targetId, nodeCanvasId)
 *      → POST /api/combat/challenge
 *      → Reverb broadcasts CombatChallengeReceived to the target's private channel
 *   2. Target receives the challenge instantly via Echo private channel listener
 *      (replaces the former 2s GET /api/combat/pending poll)
 *   3. Target calls accept(challengeId) or decline(challengeId)
 *   4. Both clients enter GridBreach PvP mode
 *   5. Winner calls submitResult(winnerId, loserId, nodeId)
 *      → POST /api/combat/result
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useCombat(playerId) {

    const busy              = ref(false);
    const error             = ref(null);
    const incomingChallenge = ref(null);

    const pid = () => typeof playerId === 'object' ? playerId.value : playerId;

    // ── Challenger side ───────────────────────────────────────────────────────

    async function challenge(targetId, nodeCanvasId) {
        busy.value  = true;
        error.value = null;
        try {
            const res = await axios.post('/api/combat/challenge', {
                target_id:      targetId,
                node_canvas_id: nodeCanvasId,
            });
            return res.data;   // { challenge_id, expires_in }
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Challenge failed';
            return null;
        } finally {
            busy.value = false;
        }
    }

    // ── Target side ───────────────────────────────────────────────────────────

    /**
     * Subscribe to the player's private Reverb channel.
     * Incoming challenge.received events populate incomingChallenge instantly.
     * The intervalMs param is kept for API compatibility but is no longer used.
     */
    function startPendingPoll(intervalMs = 2_000) { // eslint-disable-line no-unused-vars
        stopPendingPoll();
        if (!pid() || !window.Echo) return;

        window.Echo.private(`player.${pid()}`)
            .listen('.challenge.received', (data) => {
                incomingChallenge.value = data.challenge ?? null;
            });
    }

    function stopPendingPoll() {
        // Use stopListening rather than leave() so other listeners on the same
        // player.{id} channel (packet-hijack.started, etc.) are not destroyed.
        if (pid() && window.Echo) {
            window.Echo.private(`player.${pid()}`)
                .stopListening('.challenge.received');
        }
    }

    // checkPending kept for any callers that may invoke it directly — no-op now.
    async function checkPending() {}

    async function accept(challengeId) {
        busy.value  = true;
        error.value = null;
        try {
            const res = await axios.post(`/api/combat/challenge/${challengeId}/accept`);
            incomingChallenge.value = null;
            return res.data;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Accept failed';
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function decline(challengeId) {
        let data = null;
        try {
            const res = await axios.post(`/api/combat/challenge/${challengeId}/decline`);
            data = res.data;
        } catch { /* silent */ }
        incomingChallenge.value = null;
        return data;
    }

    // ── Result submission ─────────────────────────────────────────────────────

    async function submitResult(challengeId, score, nodeCanvasId) {
        busy.value  = true;
        error.value = null;
        try {
            const res = await axios.post('/api/combat/result', {
                challenge_id:   challengeId,
                score,
                node_canvas_id: nodeCanvasId,
            });
            return res.data;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Result submit failed';
            return null;
        } finally {
            busy.value = false;
        }
    }

    async function pollResult(challengeId) {
        try {
            const res = await axios.get(`/api/combat/result/${challengeId}`);
            return res.data;
        } catch {
            return { resolved: false };
        }
    }

    return {
        busy,
        error,
        incomingChallenge,
        challenge,
        checkPending,
        startPendingPoll,
        stopPendingPoll,
        accept,
        decline,
        submitResult,
        pollResult,
    };
}
