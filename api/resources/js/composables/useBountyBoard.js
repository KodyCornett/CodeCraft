/**
 * useBountyBoard
 *
 * Fetches GET /api/leaderboard/bounty on startup, then re-fetches whenever
 * Reverb broadcasts a BountyBoardUpdated event on the 'bounty-board' channel.
 * The former 30-second setInterval is replaced by this event-driven refresh.
 *
 * External API is unchanged — Game.vue still calls startPolling() / stopPolling().
 *
 * Star level mapping (mirrors BOUNTY_THRESHOLDS in Game.vue):
 *   10–14 hacks → ★ 1
 *   15–19 hacks → ★★ 2
 *   20–24 hacks → ★★★ 3
 *   25–29 hacks → ★★★★ 4  (Open Season)
 *   30+   hacks → ★★★★★ 5
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

function stealPct(hackCount, isOpenSeason) {
    if (isOpenSeason || hackCount >= 30) return 75;
    if (hackCount >= 25) return 60;
    if (hackCount >= 20) return 50;
    if (hackCount >= 15) return 40;
    if (hackCount >= 10) return 30;
    return 20;
}

function hackCountToStarLevel(hackCount, isOpenSeason) {
    if (hackCount >= 30) return 5;
    if (hackCount >= 25 || isOpenSeason) return 4;
    if (hackCount >= 20) return 3;
    if (hackCount >= 15) return 2;
    if (hackCount >= 10) return 1;
    return 0;
}

function toEntry(api) {
    const hacks      = api.nodes_hacked ?? 0;
    const os         = api.is_open_season ?? false;
    const stars      = hackCountToStarLevel(hacks, os);
    const pocket     = api.pocket_creds ?? 0;
    const pct        = stealPct(hacks, os);
    const reward     = Math.floor(pocket * pct / 100);
    const multiplier = parseFloat(api.bounty_multiplier ?? 1.0);

    return {
        playerId:     api.player_id,
        handle:       api.handle,
        stars,
        level:        stars,
        hackCount:    hacks,
        reward,
        pocketCreds:  pocket,
        stealPct:     pct,
        multiplier,
        bonusPct:     Math.round((multiplier - 1.0) * 100),
        district:     api.current_district ?? 'UNKNOWN',
        lastPing:     api.current_district ?? null,
        canvasNodeId: api.canvas_node_id ?? null,
        isOpenSeason: os,
    };
}

export function useBountyBoard(currentPlayerId) {
    const entries = ref([]);
    const loading = ref(false);
    const error   = ref(null);

    async function fetchBoard() {
        try {
            loading.value = true;
            const res = await axios.get('/api/leaderboard/bounty');
            const pid = typeof currentPlayerId === 'object'
                ? currentPlayerId.value
                : currentPlayerId;

            entries.value = (res.data.leaderboard ?? [])
                .filter(p => p.player_id !== pid)
                .map(toEntry);
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Board unavailable';
            console.warn('[BOUNTY BOARD] Fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /** Fetch once then subscribe to Reverb for real-time updates. */
    function startPolling() {
        stopPolling();   // prevent duplicate listeners if called more than once
        fetchBoard();
        if (!window.Echo) return;
        window.Echo.channel('bounty-board')
            .listen('.board.updated', () => fetchBoard());
    }

    function stopPolling() {
        if (!window.Echo) return;
        window.Echo.leave('bounty-board');
    }

    return {
        entries:      readonly(entries),
        loading:      readonly(loading),
        error:        readonly(error),
        fetchBoard,
        startPolling,
        stopPolling,
    };
}
