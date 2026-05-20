/**
 * useCombat
 *
 * Manages the PvP combat challenge handshake and result submission.
 *
 * Challenge flow:
 *   1. Challenger calls challenge(targetId, nodeCanvasId)
 *      → POST /api/combat/challenge
 *   2. Target polls checkPending() every 2s
 *      → GET /api/combat/pending
 *   3. Target calls accept(challengeId) or decline(challengeId)
 *   4. Both clients enter GridBreach PvP mode
 *   5. Winner calls submitResult(winnerId, loserId, nodeId)
 *      → POST /api/combat/result
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useCombat(playerId) {

    const busy            = ref(false);
    const error           = ref(null);
    const incomingChallenge = ref(null);   // set when a pending challenge arrives
    let   _pendingTimer   = null;

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

    async function checkPending() {
        try {
            const res = await axios.get('/api/combat/pending');
            incomingChallenge.value = res.data.challenge ?? null;
        } catch {
            // Silent
        }
    }

    function startPendingPoll(intervalMs = 2_000) {
        stopPendingPoll();
        checkPending();
        _pendingTimer = setInterval(checkPending, intervalMs);
    }

    function stopPendingPoll() {
        clearInterval(_pendingTimer);
        _pendingTimer = null;
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
        return data;   // caller uses penalty + critical_failure fields
    }

    // ── Result submission ─────────────────────────────────────────────────────

    /**
     * Submit this player's GridBreach score for a PvP challenge.
     * Returns { resolved: true, ...payload } if both scores are now in,
     * or { resolved: false } if the opponent hasn't submitted yet.
     */
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

    /**
     * Poll for a resolved result after submitting first.
     * Returns { resolved: true, ...payload } when the opponent's score is in,
     * or { resolved: false } while still waiting.
     */
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
