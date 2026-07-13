/**
 * useCombat
 *
 * Manages the PvP combat challenge handshake and result submission.
 *
 * Challenge flow:
 *   1. Challenger calls challenge(targetId, nodeCanvasId)
 *      -> POST /api/combat/challenge
 *      -> Reverb broadcasts CombatChallengeReceived to the target's private channel
 *   2. Target receives the challenge instantly via Echo private channel listener
 *      (replaces the former 2s GET /api/combat/pending poll)
 *   3. Target calls accept(challengeId) or decline(challengeId)
 *   4. On accept: server creates PacketHijackMatch and broadcasts PacketHijackStarted to both players
 *   5. PacketHijackController resolves the match when the winner submits a verified breach payload
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
            console.warn('[COMBAT] Challenge failed:', error.value);
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

    async function accept(challengeId) {
        busy.value  = true;
        error.value = null;
        try {
            const res = await axios.post(`/api/combat/challenge/${challengeId}/accept`);
            incomingChallenge.value = null;
            return res.data;
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Accept failed';
            incomingChallenge.value = null;
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

    /**
     * Poll challenge status until it resolves (accepted / declined / expired).
     * Handles the setInterval internally and resolves a Promise with the outcome.
     * Caller owns all reactive state updates (awaitingChallenge, pocketCreds, etc).
     *
     * @returns {Promise<{status: 'accepted'|'declined'|'expired'}>}
     */
    function pollChallengeStatus(challengeId) {
        return new Promise((resolve) => {
            let attempts = 0;
            const maxAttempts = 15;   // 15 x 2s = 30s TTL

            const poll = setInterval(async () => {
                attempts++;
                if (attempts > maxAttempts) {
                    clearInterval(poll);
                    resolve({ status: 'expired' });
                    return;
                }
                try {
                    const res    = await axios.get(`/api/combat/challenge/${challengeId}/status`);
                    const status = res.data.status;

                    if (status === 'accepted') {
                        clearInterval(poll);
                        resolve({ status: 'accepted' });
                    } else if (status === 'declined') {
                        clearInterval(poll);
                        resolve({ status: 'declined' });
                    } else if (status === 'expired' || status === 'not_found') {
                        clearInterval(poll);
                        resolve({ status: 'expired' });
                    }
                    // 'pending' -> keep polling
                } catch { /* silent — keep polling */ }
            }, 2_000);
        });
    }

    return {
        busy,
        error,
        incomingChallenge,
        challenge,
        startPendingPoll,
        stopPendingPoll,
        accept,
        decline,
        pollChallengeStatus,
    };
}
