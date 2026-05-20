/**
 * useAuth
 *
 * Resolves the authenticated player from the Laravel session.
 *
 * Flow:
 *   1. Laravel web auth guards the game route — unauthenticated users are
 *      redirected to /login before this composable ever runs.
 *   2. Sanctum's EnsureFrontendRequestsAreStateful middleware lets the
 *      session cookie authenticate /api/* requests — no Bearer token needed.
 *   3. GET /api/player/me → player UUID + rig snapshot.
 *
 * To log out: POST /logout (wired in NavBar or a future settings page).
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

export function useAuth() {
    const ready    = ref(false);
    const playerId = ref(null);
    const player   = ref(null);
    const rig      = ref(null);
    const error    = ref(null);

    /**
     * Fetch the authenticated player record.
     * The session cookie is sent automatically — no credentials needed here.
     * Returns true on success, false if the session is somehow invalid.
     */
    async function login() {
        try {
            const meRes    = await axios.get('/api/player/me');
            player.value   = meRes.data.player;
            rig.value      = meRes.data.rig;
            playerId.value = meRes.data.player.id;
            ready.value    = true;

            console.log(`[AUTH] Playing as ${player.value.handle} (${playerId.value})`);
            return true;
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Session lookup failed';
            console.error('[AUTH] Could not resolve player:', error.value);
            return false;
        }
    }

    return {
        ready:    readonly(ready),
        playerId: readonly(playerId),
        player:   readonly(player),
        rig:      readonly(rig),
        error:    readonly(error),
        login,
    };
}
