<template>
    <div class="panel">
        <div class="panel-header">
            <span>[SCAN_AREA]</span>
            <div class="tab-row">
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': activeTab === 'bounties' }"
                    @click="activeTab = 'bounties'"
                >[ACTIVE_BOUNTIES]</button>
                <button
                    class="tab-btn"
                    :class="{ 'tab-btn--active': activeTab === 'hof' }"
                    @click="activeTab = 'hof'"
                >[OPEN_SEASON_RECORDS]</button>
            </div>
            <button class="refresh-btn" :class="{ 'refresh-btn--spinning': loading }" @click="refresh">↻</button>
        </div>

        <!-- ── Active Bounties ── -->
        <div v-if="activeTab === 'bounties'" class="panel-body">
            <div class="board-header">[PRIORITY TARGETS — ICE SANCTIONED]</div>

            <div v-if="loading && !bounties.length" class="loading-msg">// QUERYING ICE DATABASE...</div>

            <div v-else-if="!bounties.length" class="empty-msg">// NO ACTIVE BOUNTY TARGETS</div>

            <div v-else class="entry-list">
                <div
                    v-for="(entry, i) in bounties"
                    :key="entry.player_id"
                    class="bounty-entry"
                    :class="{ 'bounty-entry--open-season': entry.is_open_season, 'bounty-entry--expanded': expandedId === entry.player_id }"
                    @click="toggleExpand(entry.player_id)"
                >
                    <div class="entry-main">
                        <span class="entry-rank">{{ String(i + 1).padStart(2, '0') }}.</span>
                        <span class="entry-handle" :class="{ 'handle--os': entry.is_open_season }">
                            {{ entry.handle }}
                        </span>
                        <span v-if="entry.is_open_season" class="os-tag">[OPEN SEASON]</span>
                        <span class="entry-district">// {{ (entry.current_district ?? 'UNKNOWN').toUpperCase() }}</span>
                        <span class="entry-nodes">// {{ (entry.nodes_hacked ?? 0).toLocaleString('en-US') }} NODES</span>
                        <span class="entry-bounty">// ₢ {{ bountyValue(entry).toLocaleString('en-US') }}</span>
                    </div>

                    <!-- Expanded detail -->
                    <div v-if="expandedId === entry.player_id" class="entry-detail">
                        <div class="detail-row">
                            <span class="detail-label">PVP WIN STREAK</span>
                            <span class="detail-val">{{ entry.pvp_wins_this_run }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">BOUNTY LEVEL</span>
                            <span class="detail-val">{{ entry.bounty_level }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">RUN MULTIPLIER</span>
                            <span class="detail-val">{{ entry.bounty_multiplier }}×</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">STATUS</span>
                            <span class="detail-val" :class="entry.is_open_season ? 'val--os' : ''">
                                {{ entry.is_open_season ? '⚡ OPEN SEASON' : 'BOUNTY TARGET' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="board-footer">[ELIMINATE TARGET FOR BOUNTY BONUS]</div>
        </div>

        <!-- ── Hall of Fame ── -->
        <div v-else class="panel-body">
            <div class="board-header">[OPEN SEASON // HALL OF INFAMY]</div>
            <div class="hof-sub">ALL-TIME RECORDS:</div>

            <div v-if="loading && !hallOfFame.length" class="loading-msg">// QUERYING RECORDS...</div>

            <div v-else-if="!hallOfFame.length" class="empty-msg">// NO RECORDS YET</div>

            <div v-else class="entry-list">
                <div
                    v-for="(entry, i) in hallOfFame"
                    :key="entry.player_id"
                    class="hof-entry"
                >
                    <span class="entry-rank">{{ String(i + 1).padStart(2, '0') }}.</span>
                    <span class="hof-handle">{{ entry.handle }}</span>
                    <span class="hof-wins">// {{ entry.best_open_season_wins }} WINS</span>
                </div>
            </div>
        </div>

        <button class="panel-close" @click="$emit('close')">[CLOSE]</button>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

defineEmits(['close']);

// ─── State ────────────────────────────────────────────────────────────────────
const activeTab  = ref('bounties');
const bounties   = ref([]);
const hallOfFame = ref([]);
const loading    = ref(false);
const expandedId = ref(null);

// ─── Mock fallback ────────────────────────────────────────────────────────────
const MOCK_BOUNTIES = [
    { player_id: '1', handle: 'GHOST_NULL',  current_district: 'HILLYARD',         nodes_hacked: 847, pvp_wins_this_run: 12, bounty_level: 25, bounty_multiplier: 3.50, is_open_season: true  },
    { player_id: '2', handle: 'KODY_REF',    current_district: 'DOWNTOWN',          nodes_hacked: 612, pvp_wins_this_run: 8,  bounty_level: 20, bounty_multiplier: 2.75, is_open_season: false },
    { player_id: '3', handle: 'VIPER_7',     current_district: 'SOUTH INDUSTRIAL',  nodes_hacked: 401, pvp_wins_this_run: 5,  bounty_level: 15, bounty_multiplier: 2.00, is_open_season: false },
    { player_id: '4', handle: 'NULL_PTR',    current_district: 'NORTH SPOKANE',     nodes_hacked: 289, pvp_wins_this_run: 3,  bounty_level: 12, bounty_multiplier: 1.75, is_open_season: false },
    { player_id: '5', handle: 'REDLINE_99',  current_district: 'UNIVERSITY',        nodes_hacked: 203, pvp_wins_this_run: 2,  bounty_level: 10, bounty_multiplier: 1.50, is_open_season: false },
];

const MOCK_HOF = [
    { player_id: '1', handle: 'GHOST_NULL', best_open_season_wins: 23 },
    { player_id: '6', handle: 'NULL_PTR',   best_open_season_wins: 18 },
    { player_id: '2', handle: 'KODY_REF',   best_open_season_wins: 14 },
    { player_id: '7', handle: 'VIPER_7',    best_open_season_wins: 11 },
    { player_id: '8', handle: 'CRASH_0X',   best_open_season_wins: 9  },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function bountyValue(entry) {
    // Approximate bounty value: bounty_level × 100,000 × multiplier
    return Math.round((entry.bounty_level ?? 1) * 100000 * (entry.bounty_multiplier ?? 1));
}

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ─── Fetch ────────────────────────────────────────────────────────────────────
async function fetchBounties() {
    try {
        const res = await axios.get('/api/leaderboard/bounty');
        bounties.value = res.data.leaderboard ?? [];
    } catch {
        bounties.value = MOCK_BOUNTIES;
    }
}

async function fetchHof() {
    try {
        const res = await axios.get('/api/leaderboard/open-season');
        hallOfFame.value = res.data.hall_of_fame ?? [];
    } catch {
        hallOfFame.value = MOCK_HOF;
    }
}

async function refresh() {
    loading.value = true;
    await Promise.all([fetchBounties(), fetchHof()]);
    loading.value = false;
}

// ─── Auto-refresh every 30s ───────────────────────────────────────────────────
let refreshTimer = null;

onMounted(() => {
    refresh();
    refreshTimer = setInterval(refresh, 30000);
});

onUnmounted(() => clearInterval(refreshTimer));
</script>

<style scoped>
.panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: rgba(5, 5, 5, 0.97);
    font-family: 'JetBrains Mono', monospace;
    color: #00FFFF;
}

.panel-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px 10px;
    font-size: 11px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.2);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.tab-row {
    display: flex;
    gap: 4px;
    flex: 1;
}

.tab-btn {
    padding: 4px 10px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.2);
    color: rgba(0, 255, 255, 0.45);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.tab-btn:hover         { color: #00FFFF; border-color: rgba(0,255,255,0.4); }
.tab-btn--active       { color: #00FFFF; border-color: #00FFFF; background: rgba(0,255,255,0.06); }

.refresh-btn {
    background: transparent;
    border: none;
    color: rgba(0, 255, 255, 0.4);
    font-size: 14px;
    cursor: pointer;
    padding: 2px 6px;
    transition: color 0.15s;
    line-height: 1;
}
.refresh-btn:hover          { color: #00FFFF; }
.refresh-btn--spinning      { animation: spin 0.8s linear infinite; }

@keyframes spin { to { transform: rotate(360deg); } }

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.board-header {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    padding-bottom: 8px;
}

.hof-sub {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.06em;
}

.loading-msg, .empty-msg {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.25);
    letter-spacing: 0.04em;
    margin: 10px 0;
}

.entry-list { display: flex; flex-direction: column; gap: 2px; }

/* ── Bounty entries ─────────────────────────────────────────────────────────── */
.bounty-entry {
    border: 1px solid rgba(0, 255, 255, 0.1);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.bounty-entry:hover           { border-color: rgba(0,255,255,0.3); background: rgba(0,255,255,0.03); }
.bounty-entry--expanded       { border-color: rgba(0,255,255,0.35); background: rgba(0,255,255,0.04); }
.bounty-entry--open-season    { border-color: rgba(255,51,51,0.3); animation: os-border-pulse 2s ease-in-out infinite; }

@keyframes os-border-pulse {
    0%, 100% { border-color: rgba(255,51,51,0.3); box-shadow: none; }
    50%       { border-color: rgba(255,51,51,0.7); box-shadow: 0 0 8px rgba(255,51,51,0.15); }
}

.entry-main {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    font-size: 10px;
    flex-wrap: wrap;
}

.entry-rank    { color: rgba(0,255,255,0.35); min-width: 22px; flex-shrink: 0; }
.entry-handle  { color: #00FFFF; letter-spacing: 0.04em; min-width: 90px; }
.handle--os    { color: #FF3333; text-shadow: 0 0 6px rgba(255,51,51,0.5); animation: os-handle-flicker 3s steps(1) infinite; }
.entry-district { color: rgba(0,255,255,0.45); font-size: 9px; }
.entry-nodes    { color: rgba(0,255,255,0.45); font-size: 9px; }
.entry-bounty   { color: #FFB300; font-size: 9px; margin-left: auto; }

.os-tag {
    font-size: 8px;
    color: #FF3333;
    border: 1px solid rgba(255,51,51,0.5);
    padding: 1px 5px;
    letter-spacing: 0.04em;
    animation: os-tag-pulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes os-tag-pulse {
    0%, 100% { opacity: 1;   box-shadow: 0 0 4px rgba(255,51,51,0.3); }
    50%       { opacity: 0.6; box-shadow: 0 0 10px rgba(255,51,51,0.5); }
}

@keyframes os-handle-flicker {
    0%   { opacity: 1;   }
    5%   { opacity: 0.3; }
    7%   { opacity: 1;   }
    60%  { opacity: 1;   }
    63%  { opacity: 0.2; }
    65%  { opacity: 1;   }
    100% { opacity: 1;   }
}

/* Expanded detail */
.entry-detail {
    border-top: 1px solid rgba(0,255,255,0.12);
    padding: 8px 14px 10px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-row {
    display: flex;
    gap: 12px;
    font-size: 9px;
}

.detail-label { color: rgba(0,255,255,0.35); min-width: 120px; letter-spacing: 0.04em; }
.detail-val   { color: rgba(0,255,255,0.8); }
.val--os      { color: #FF3333; }

.board-footer {
    font-size: 9px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.06em;
    text-align: center;
    border-top: 1px solid rgba(0,255,255,0.08);
    padding-top: 8px;
    margin-top: auto;
}

/* ── Hall of Fame entries ────────────────────────────────────────────────────── */
.hof-entry {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    font-size: 10px;
    border: 1px solid rgba(255,0,204,0.1);
    transition: border-color 0.15s, background 0.15s;
}
.hof-entry:hover { border-color: rgba(255,0,204,0.3); background: rgba(255,0,204,0.03); }

.hof-handle { color: #FF00CC; letter-spacing: 0.04em; flex: 1; }
.hof-wins   { color: rgba(255,0,204,0.6); font-size: 9px; }

.panel-close {
    margin: 10px 16px;
    align-self: flex-start;
    padding: 7px 14px;
    background: transparent;
    border: 1px solid rgba(0,255,255,0.35);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}
.panel-close:hover { background: rgba(0,255,255,0.07); }
</style>
