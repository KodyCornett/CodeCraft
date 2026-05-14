<template>
    <div class="panel">
        <div class="panel-header">[PLAYER_STATS]</div>

        <div class="panel-body">

            <!-- Identity -->
            <div class="section section--first">
                <div class="row">
                    <span class="label">HANDLE</span>
                    <span class="val">{{ player.handle }}</span>
                </div>
                <div class="row">
                    <span class="label">DISTRICT</span>
                    <span class="val">{{ (player.district ?? 'UNKNOWN').toUpperCase() }}</span>
                </div>
                <div class="row">
                    <span class="label">STATUS</span>
                    <span class="val" :class="statusClass">{{ statusLabel }}</span>
                </div>
            </div>

            <!-- System Stability -->
            <div class="section">
                <div class="section-title">[SYSTEM STABILITY]</div>
                <div class="ss-row">
                    <SSBar :current="player.currentSS" :max="player.maxSS" :is-limping="player.isLimping" />
                </div>
                <div class="ss-numbers">
                    <span class="val-big" :class="ssNumClass">{{ player.currentSS }}</span>
                    <span class="val-sep"> / </span>
                    <span class="val-big">{{ player.maxSS }}</span>
                    <span class="val-unit"> SS</span>
                </div>
            </div>

            <!-- Resources -->
            <div class="section">
                <div class="section-title">[RESOURCES]</div>
                <div class="row">
                    <span class="label">CPU CYCLES</span>
                    <span class="cpu-bar-wrap">
                        <span class="cpu-bar-track">
                            <span class="cpu-bar-fill" :style="{ width: cpuPct + '%' }" />
                        </span>
                        <span class="cpu-val">{{ player.cpuCycles }} / {{ player.maxCpu }}</span>
                    </span>
                </div>
                <div class="row">
                    <span class="label">CREDS</span>
                    <span class="val val--amber">₢ {{ formattedCreds }}</span>
                </div>
            </div>

            <!-- Run Stats -->
            <div class="section">
                <div class="section-title">[RUN STATS]</div>
                <div class="row">
                    <span class="label">NODES HACKED</span>
                    <span class="val">{{ player.nodesHackedThisRun ?? 0 }}</span>
                </div>
                <div class="row">
                    <span class="label">PVP WINS</span>
                    <span class="val">{{ player.pvpWinsThisRun ?? 0 }}</span>
                </div>
                <div class="row">
                    <span class="label">BOUNTY LVL</span>
                    <span class="val" :class="bountyActive ? 'val--amber' : ''">{{ player.bountyLevel ?? 0 }}</span>
                </div>
                <div class="row">
                    <span class="label">MULTIPLIER</span>
                    <span class="val val--amber">{{ Number(player.bountyMultiplier ?? 1).toFixed(2) }}×</span>
                </div>
            </div>

            <!-- Threat Status -->
            <div class="section">
                <div class="section-title">[THREAT STATUS]</div>
                <div class="row">
                    <span class="label">BOUNTY BOARD</span>
                    <span class="val" :class="bountyActive ? 'val--amber' : 'val--inactive'">
                        {{ bountyActive ? 'TARGET MARKED' : 'INACTIVE' }}
                    </span>
                </div>
                <div class="row">
                    <span class="label">OPEN SEASON</span>
                    <span class="val" :class="player.isOpenSeason ? 'val--os' : 'val--inactive'">
                        {{ player.isOpenSeason ? '⚡ ACTIVE' : 'INACTIVE' }}
                    </span>
                </div>
                <div class="row">
                    <span class="label">LAST ST.DOC</span>
                    <span class="val val--dim">{{ player.lastStreetDocName ?? 'NONE' }}</span>
                </div>
                <div v-if="(player.postCombatSilentMoves ?? 0) > 0" class="row">
                    <span class="label">SILENT MOVES</span>
                    <span class="val val--amber">{{ player.postCombatSilentMoves }} REMAINING</span>
                </div>
            </div>

        </div>

        <button class="panel-close" @click="$emit('close')">[CLOSE]</button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import SSBar from '@/components/shared/SSBar.vue';

const props = defineProps({
    player:    { type: Object, required: true },
    rig:       { type: Object, default: () => ({}) },
    commands:  { type: Array,  default: () => [] },
    inventory: { type: Array,  default: () => [] },
});

defineEmits(['close']);

const statusLabel = computed(() => props.player.isLimping ? 'LIMP MODE' : 'ACTIVE');
const statusClass = computed(() => props.player.isLimping ? 'val--limp' : 'val--active');

const ssNumClass = computed(() => {
    const pct = props.player.maxSS > 0 ? props.player.currentSS / props.player.maxSS : 0;
    if (pct > 0.6) return 'val-big--green';
    if (pct > 0.3) return 'val-big--amber';
    return 'val-big--red';
});

const cpuPct = computed(() =>
    props.player.maxCpu > 0
        ? Math.min(100, (props.player.cpuCycles / props.player.maxCpu) * 100)
        : 0,
);

const formattedCreds = computed(() => (props.player.creds ?? 0).toLocaleString('en-US'));

const bountyActive = computed(() => (props.player.bountyLevel ?? 0) >= 15);
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
    padding: 14px 18px;
    font-size: 12px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.2);
    flex-shrink: 0;
}

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
}

/* ── Sections ─────────────────────────────────────────────────────────────────── */
.section {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid rgba(0, 255, 255, 0.1);
}

.section--first {
    padding-top: 0;
    margin-top: 0;
    border-top: none;
}

.section-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.08em;
    margin-bottom: 9px;
}

/* ── Rows ─────────────────────────────────────────────────────────────────────── */
.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    margin-bottom: 5px;
}

.label {
    color: rgba(0, 255, 255, 0.45);
    letter-spacing: 0.04em;
}

.val         { color: #00FFFF; letter-spacing: 0.03em; }
.val--active { color: #00FF88; }
.val--amber  { color: #FFB300; }
.val--dim    { color: rgba(0, 255, 255, 0.5); font-size: 10px; }
.val--inactive { color: rgba(0, 255, 255, 0.25); }

.val--limp {
    color: #FF3333;
    animation: threat-pulse 1.5s ease-in-out infinite;
}

.val--os {
    color: #FF3333;
    animation: threat-pulse 1.5s ease-in-out infinite;
}

@keyframes threat-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

/* ── System Stability ─────────────────────────────────────────────────────────── */
.ss-row {
    font-size: 12px;
    margin-bottom: 6px;
}

.ss-numbers {
    font-size: 11px;
    color: rgba(0, 255, 255, 0.5);
}

.val-big        { font-size: 18px; letter-spacing: 0; color: #00FFFF; }
.val-big--green { color: #00FF88; }
.val-big--amber { color: #FFB300; }
.val-big--red   { color: #FF3333; }
.val-sep        { color: rgba(0, 255, 255, 0.3); font-size: 14px; }
.val-unit       { color: rgba(0, 255, 255, 0.35); font-size: 10px; }

/* ── CPU bar ──────────────────────────────────────────────────────────────────── */
.cpu-bar-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
}

.cpu-bar-track {
    display: inline-block;
    width: 80px;
    height: 4px;
    background: rgba(0, 255, 255, 0.12);
    position: relative;
    flex-shrink: 0;
}

.cpu-bar-fill {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    background: #00FFFF;
    transition: width 0.3s ease;
}

.cpu-val {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.7);
}

/* ── Close ────────────────────────────────────────────────────────────────────── */
.panel-close {
    margin: 10px 18px;
    padding: 7px 14px;
    align-self: flex-start;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.35);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}

.panel-close:hover {
    background: rgba(0, 255, 255, 0.07);
}
</style>
