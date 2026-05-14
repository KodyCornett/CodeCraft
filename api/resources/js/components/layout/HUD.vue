<template>
    <div class="hud-wrapper" :class="{ 'hud-minimized': minimized }">

        <!-- ── Minimized tab ──────────────────────────────────────── -->
        <NeonBorder v-if="minimized" color="#00FFFF" :opacity="0.25">
            <button class="hud-tab" @click="minimized = false">[CC]</button>
        </NeonBorder>

        <!-- ── Expanded panel ────────────────────────────────────── -->
        <template v-else>
        <button class="hud-toggle" @click="minimized = true" title="Minimize">[−]</button>
        <NeonBorder color="#00FFFF" :opacity="0.35">
            <div class="hud-panel">

                <!-- Handle -->
                <div class="hud-row">
                    <span class="hud-key">[HANDLE</span>
                    <span class="hud-sep">:</span>
                    <span
                        class="hud-val"
                        :class="{ 'open-season-handle': player.isOpenSeason }"
                        :style="{ color: player.isOpenSeason ? '#FF3333' : '#00FFFF' }"
                    >{{ player.handle }}</span>
                    <span class="hud-close">]</span>
                </div>

                <!-- SS bar -->
                <div class="hud-row">
                    <span class="hud-key">[SS&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span class="hud-sep">:</span>
                    <span class="hud-val">
                        <SSBar :current="player.currentSS" :max="player.maxSS" :is-limping="player.isLimping" />
                    </span>
                    <Transition name="flash">
                        <span v-if="ssFlash" class="stat-delta" :class="ssFlash.positive ? 'delta-pos' : 'delta-neg'">
                            {{ formatDelta(ssFlash.value) }}
                        </span>
                    </Transition>
                    <span class="hud-close">]</span>
                </div>

                <!-- Limp mode warning -->
                <div v-if="player.isLimping" class="hud-row limp-warning">
                    <span>[! LIMP MODE ACTIVE !]</span>
                </div>

                <!-- Creds -->
                <div class="hud-row">
                    <span class="hud-key">[CREDS&nbsp;</span>
                    <span class="hud-sep">:</span>
                    <span class="hud-val">{{ formattedCreds }}</span>
                    <Transition name="flash">
                        <span v-if="credsFlash" class="stat-delta" :class="credsFlash.positive ? 'delta-pos' : 'delta-neg'">
                            {{ formatDelta(credsFlash.value) }}
                        </span>
                    </Transition>
                    <span class="hud-close">]</span>
                </div>

                <!-- CPU cycles -->
                <div class="hud-row">
                    <span class="hud-key">[CPU&nbsp;&nbsp;&nbsp;</span>
                    <span class="hud-sep">:</span>
                    <span class="hud-val">{{ player.cpuCycles }}/{{ player.maxCpu }} cycles</span>
                    <Transition name="flash">
                        <span v-if="cpuFlash" class="stat-delta" :class="cpuFlash.positive ? 'delta-pos' : 'delta-neg'">
                            {{ formatDelta(cpuFlash.value) }}
                        </span>
                    </Transition>
                    <span class="hud-close">]</span>
                </div>

                <!-- District -->
                <div class="hud-row">
                    <span class="hud-key">[DIST&nbsp;&nbsp;</span>
                    <span class="hud-sep">:</span>
                    <span class="hud-val district-val">{{ player.district ?? 'UNKNOWN' }}</span>
                    <span class="hud-close">]</span>
                </div>

                <!-- Bounty -->
                <div class="hud-row">
                    <span class="hud-key">[BOUNTY</span>
                    <span class="hud-sep">:</span>
                    <span
                        class="hud-val"
                        :class="{ 'bounty-active': player.bountyLevel > 0 }"
                        :style="{ color: player.bountyLevel > 0 ? '#FFB300' : '#444444' }"
                    >{{ player.bountyLevel > 0 ? `LVL ${player.bountyLevel}` : 'NONE' }}</span>
                    <span class="hud-close">]</span>
                </div>

                <!-- Open Season badge -->
                <div v-if="player.isOpenSeason" class="hud-row open-season-badge">
                    <span>[⚡ OPEN SEASON ACTIVE ]</span>
                </div>

                <!-- ── Node Interface Section ──────────────────────────────── -->
                <template v-if="currentNode">
                    <div class="hud-divider" />

                    <!-- Section header -->
                    <div class="hud-row hud-section-head" :class="{ 'node-street-doc': isStreetDoc }">
                        [CURRENT NODE]
                    </div>

                    <!-- Node name — truncates on overflow -->
                    <div class="hud-node-name" :class="{ 'node-street-doc': isStreetDoc }">
                        {{ (currentNode.label ?? currentNode.id)?.toUpperCase() }}
                    </div>

                    <!-- Street Doc mode -->
                    <template v-if="isStreetDoc">
                        <div class="hud-row hud-node-sub node-street-doc">STREET DOC // SECURE CHANNEL</div>
                        <div class="hud-divider" />
                        <div v-for="svc in STREET_DOC_SERVICES" :key="svc" class="hud-node-row node-street-doc">
                            <span class="hud-nkey">{{ svc.key }}</span>
                            <span class="hud-sep">:</span>
                            <span class="hud-nval hud-available">▶ AVAILABLE</span>
                        </div>
                    </template>

                    <!-- Standard node mode -->
                    <template v-else>
                        <div class="hud-row hud-node-sub">
                            TIER: {{ currentNode.tier ?? '?' }}&nbsp;&nbsp;//&nbsp;&nbsp;{{ (currentNode.district ?? '?')?.toUpperCase() }}
                        </div>
                        <div class="hud-divider" />

                        <!-- Creds -->
                        <div class="hud-node-row">
                            <span class="hud-nkey">[CREDS&nbsp;&nbsp;&nbsp;</span>
                            <span class="hud-sep">:</span>
                            <template v-if="!currentNode.credDepleted && !isScorched">
                                <span class="hud-nval">{{ credValueDisplay }} ₡&nbsp;</span>
                                <span class="hud-available">▶ AVAILABLE</span>
                            </template>
                            <span v-else class="hud-nval hud-depleted">DEPLETED</span>
                            <span class="hud-close">]</span>
                        </div>

                        <!-- Movement -->
                        <div class="hud-node-row">
                            <span class="hud-nkey">[MOVEMENT</span>
                            <span class="hud-sep">:</span>
                            <template v-if="!currentNode.movementDepleted && !isScorched">
                                <span class="hud-nval">+{{ currentNode.movementPts }} PTS&nbsp;</span>
                                <span class="hud-available">▶ AVAILABLE</span>
                            </template>
                            <span v-else class="hud-nval hud-depleted">DEPLETED</span>
                            <span class="hud-close">]</span>
                        </div>

                        <!-- State -->
                        <div class="hud-node-row">
                            <span class="hud-nkey">[STATE&nbsp;&nbsp;&nbsp;</span>
                            <span class="hud-sep">:</span>
                            <span class="hud-nval" :class="`hud-state--${currentNode.state}`">{{ nodeStateLabel }}</span>
                            <span class="hud-close">]</span>
                        </div>

                        <!-- Links -->
                        <div class="hud-node-row">
                            <span class="hud-nkey">[LINKS&nbsp;&nbsp;&nbsp;</span>
                            <span class="hud-sep">:</span>
                            <span class="hud-nval">{{ currentNode.adjacentCount }} ADJACENT NODES</span>
                            <span class="hud-close">]</span>
                        </div>
                    </template>

                    <div class="hud-divider" />
                </template>

            </div>
        </NeonBorder>
        </template><!-- end expanded -->

    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import NeonBorder from '@/components/shared/NeonBorder.vue';
import SSBar      from '@/components/shared/SSBar.vue';

const minimized = ref(false);

const STREET_DOC_SERVICES = [
    { key: '[REPAIR SS  ' },
    { key: '[UPGRADE RIG' },
    { key: '[SET LOADOUT' },
    { key: '[INSTALL HW ' },
];

const props = defineProps({
    player: {
        type: Object,
        default: () => ({
            handle: 'UNKNOWN', currentSS: 100, maxSS: 100, creds: 0,
            cpuCycles: 0, maxCpu: 3, district: null, bountyLevel: 0,
            isOpenSeason: false, isLimping: false,
        }),
    },
    currentNode: {
        type: Object,
        default: null,
        // { id, label, tier, district, state, credDepleted, movementDepleted,
        //   credValueBase, credLastHackedAt, adjacentCount, movementPts, isStreetDoc }
    },
});

// ── Player stat formatting ─────────────────────────────────────────────────────
const formattedCreds = computed(() => (props.player.creds ?? 0).toLocaleString('en-US'));

// ── Node section derived state ─────────────────────────────────────────────────
const isStreetDoc = computed(() => props.currentNode?.state === 'street_doc');
const isScorched  = computed(() => props.currentNode?.state === 'scorched');

const credValueDisplay = computed(() => {
    if (!props.currentNode) return 0;
    const base     = props.currentNode.credValueBase ?? 100;
    const hackedAt = props.currentNode.credLastHackedAt;
    if (!hackedAt) return base;
    const mins = (Date.now() - new Date(hackedAt).getTime()) / 60_000;
    return base + Math.floor(mins / 2.5) * 25;
});

const nodeStateLabel = computed(() => {
    const map = { active: 'ACTIVE', hacked: 'COMPROMISED', scorched: 'SCORCHED', street_doc: 'STREET DOC' };
    return map[props.currentNode?.state] ?? props.currentNode?.state?.toUpperCase() ?? '';
});

// ── Stat delta flashes ─────────────────────────────────────────────────────────
const ssFlash    = ref(null);
const credsFlash = ref(null);
const cpuFlash   = ref(null);

const ssTH    = { t: null };
const credsTH = { t: null };
const cpuTH   = { t: null };

function showFlash(flashRef, th, delta) {
    if (delta === 0) return;
    clearTimeout(th.t);
    flashRef.value = { value: delta, positive: delta > 0 };
    th.t = setTimeout(() => { flashRef.value = null; }, 1600);
}

watch(() => props.player.currentSS,  (n, o) => { if (o !== undefined) showFlash(ssFlash,    ssTH,    n - o); });
watch(() => props.player.creds,      (n, o) => { if (o !== undefined) showFlash(credsFlash, credsTH, n - o); });
watch(() => props.player.cpuCycles,  (n, o) => { if (o !== undefined) showFlash(cpuFlash,   cpuTH,   n - o); });

function formatDelta(delta) {
    return (delta > 0 ? '+' : '') + delta.toLocaleString('en-US');
}
</script>

<style scoped>
.hud-wrapper {
    position: absolute;
    top: 20px;
    left: 20px;
    z-index: 20;
    width: 272px;
}

.hud-minimized {
    width: auto;
}

/* ── Minimized tab ───────────────────────────────────────────────────────────── */
.hud-tab {
    display: block;
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: rgba(0, 255, 255, 0.45);
    padding: 6px 10px;
    line-height: 1;
    letter-spacing: 0.1em;
    white-space: nowrap;
    transition: color 0.15s, text-shadow 0.15s;
}

.hud-tab:hover {
    color: #00FFFF;
    text-shadow: 0 0 8px rgba(0, 255, 255, 0.55);
}

/* ── Expand / collapse toggle ────────────────────────────────────────────────── */
.hud-toggle {
    position: absolute;
    top: 0;
    right: 0;
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: rgba(0, 255, 255, 0.35);
    padding: 5px 7px;
    line-height: 1;
    z-index: 1;
    transition: color 0.15s, text-shadow 0.15s;
}

.hud-toggle:hover {
    color: #00FFFF;
    text-shadow: 0 0 6px rgba(0, 255, 255, 0.5);
}

.hud-panel {
    background: rgba(5, 5, 5, 0.84);
    padding: 10px 12px 8px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    backdrop-filter: blur(2px);
}

/* ── Base row ────────────────────────────────────────────────────────────────── */
.hud-row {
    display: flex;
    align-items: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #00FFFF;
    white-space: nowrap;
    line-height: 1.6;
    overflow: hidden;
}

.hud-key   { color: rgba(0, 255, 255, 0.5); letter-spacing: 0.04em; flex-shrink: 0; }
.hud-sep   { color: rgba(0, 255, 255, 0.35); margin: 0 4px; flex-shrink: 0; }
.hud-val   { color: #00FFFF; letter-spacing: 0.03em; flex: 1; min-width: 0; overflow: hidden; }
.hud-close { color: rgba(0, 255, 255, 0.5); margin-left: 2px; flex-shrink: 0; }

.district-val { color: rgba(0, 255, 255, 0.7); font-size: 10px; letter-spacing: 0.08em; }

/* ── Divider ─────────────────────────────────────────────────────────────────── */
.hud-divider {
    height: 1px;
    background: rgba(0, 255, 255, 0.12);
    margin: 4px 0 2px;
    flex-shrink: 0;
}

/* ── Node section ────────────────────────────────────────────────────────────── */
.hud-section-head {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.45);
    letter-spacing: 0.1em;
}

.hud-node-name {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #00FFFF;
    letter-spacing: 0.05em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 1px 0;
    line-height: 1.5;
}

.hud-node-sub {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.06em;
    margin-bottom: 1px;
}

.hud-node-row {
    display: flex;
    align-items: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: #00FFFF;
    white-space: nowrap;
    line-height: 1.65;
    overflow: hidden;
}

.hud-nkey  { color: rgba(0, 255, 255, 0.45); flex-shrink: 0; letter-spacing: 0.04em; }
.hud-nval  { flex: 1; min-width: 0; overflow: hidden; color: rgba(0, 255, 255, 0.85); }

.hud-available { color: #00FF88; font-size: 9px; letter-spacing: 0.03em; }
.hud-depleted  { color: rgba(100, 100, 100, 0.65) !important; letter-spacing: 0.04em; }

/* State colors */
.hud-state--active   { color: #00FFFF; }
.hud-state--hacked   { color: rgba(255, 0, 204, 0.7); }
.hud-state--scorched { color: rgba(180, 30, 30, 0.85); }

/* Street Doc amber treatment */
.node-street-doc,
.hud-node-name.node-street-doc,
.hud-node-row.node-street-doc .hud-nkey,
.hud-node-row.node-street-doc .hud-nval {
    color: #FFB300 !important;
}
.hud-node-row.node-street-doc .hud-sep   { color: rgba(255, 179, 0, 0.35) !important; }
.hud-node-row.node-street-doc .hud-close { color: rgba(255, 179, 0, 0.5)  !important; }
.hud-section-head.node-street-doc        { color: rgba(255, 179, 0, 0.5)  !important; }

/* ── Limp warning ────────────────────────────────────────────────────────────── */
.limp-warning {
    color: #FF3333;
    font-size: 10px;
    letter-spacing: 0.06em;
    animation: limp-warn-pulse 1s ease-in-out infinite;
    justify-content: center;
}

@keyframes limp-warn-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

/* ── Bounty ──────────────────────────────────────────────────────────────────── */
.bounty-active { animation: bounty-pulse 2s ease-in-out infinite; }

@keyframes bounty-pulse {
    0%, 100% { text-shadow: 0 0 4px #FFB300; opacity: 1; }
    50%       { text-shadow: 0 0 12px #FFB300, 0 0 20px rgba(255,179,0,0.4); opacity: 0.85; }
}

/* ── Open Season ─────────────────────────────────────────────────────────────── */
@keyframes os-glitch {
    0%   { transform: translateX(0);    clip-path: none; }
    3%   { transform: translateX(-2px); clip-path: inset(15% 0 70% 0); }
    5%   { transform: translateX(2px);  clip-path: inset(55% 0 20% 0); }
    7%   { transform: translateX(0);    clip-path: none; }
    100% { transform: translateX(0); }
}

.open-season-handle {
    text-shadow: 0 0 8px #FF3333;
    animation: os-glitch 4s steps(1) infinite;
}

.open-season-badge {
    color: #FF3333;
    font-size: 10px;
    letter-spacing: 0.06em;
    justify-content: center;
    margin-top: 2px;
    animation: bounty-pulse 1.5s ease-in-out infinite;
}

/* ── Stat delta flash ────────────────────────────────────────────────────────── */
.stat-delta {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.04em;
    margin-left: 6px;
    white-space: nowrap;
    flex-shrink: 0;
    animation: delta-rise 1.6s ease forwards;
}

.delta-pos { color: #00FF88; }
.delta-neg { color: #FF3333; }

@keyframes delta-rise {
    0%   { opacity: 1;   transform: translateY(0);    }
    30%  { opacity: 1;   transform: translateY(-4px); }
    70%  { opacity: 0.8; transform: translateY(-8px); }
    100% { opacity: 0;   transform: translateY(-12px); }
}

.flash-enter-active { animation: delta-rise 1.6s ease forwards; }
.flash-leave-active { transition: opacity 0.1s; }
.flash-leave-to     { opacity: 0; }
</style>
