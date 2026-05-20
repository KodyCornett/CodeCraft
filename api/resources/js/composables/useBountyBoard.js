/**
 * useBountyBoard
 *
 * Polls GET /api/leaderboard/bounty and maps the raw backend response
 * (where bounty_level = nodes_hacked_this_run) into the star-level format
 * BountyBlock.vue expects.
 *
 * Excludes the current player — they see their own status in the
 * "Your Bounty" section at the bottom of BountyBlock, not in the target list.
 *
 * Star level mapping (mirrors BOUNTY_THRESHOLDS in Game.vue):
 *   10–14 hacks → ★ 1
 *   15–19 hacks → ★★ 2
 *   20–24 hacks → ★★★ 3
 *   25–29 hacks → ★★★★ 4  (Open Season)
 *   30+   hacks → ★★★★★ 5
 *
 * Reward shown = floor(pocket_creds × steal_pct)
 * so hunters can see what they stand to gain at a glance.
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

// Must mirror BountyService::STEAL_TIERS exactly (CLAUDE.md thresholds 10/15/20/25/30)
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
    // API returns nodes_hacked (aliased from nodes_hacked_this_run by BountyController)
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
        stars,                          // 1–5 used by BountyBlock for star icons
        level:        stars,            // kept for levelClass / accuracyPips compat
        hackCount:    hacks,
        reward,                         // creds the hunter earns on successful extract
        pocketCreds:  pocket,           // total the target is carrying
        stealPct:     pct,
        multiplier,
        bonusPct:     Math.round((multiplier - 1.0) * 100),  // "+25%" style
        district:     api.current_district ?? 'UNKNOWN',
        lastPing:     api.current_district ?? null,          // real pings come via WebSocket
        isOpenSeason: os,
    };
}

export function useBountyBoard(currentPlayerId) {
    const entries = ref([]);
    const loading = ref(false);
    const error   = ref(null);

    let _timer = null;

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

    /** Fetch once then poll every `intervalMs` (default 30 s). */
    function startPolling(intervalMs = 30_000) {
        fetchBoard();
        _timer = setInterval(fetchBoard, intervalMs);
    }

    function stopPolling() {
        if (_timer) { clearInterval(_timer); _timer = null; }
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
