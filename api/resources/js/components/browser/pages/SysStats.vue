<template>
    <div class="ss-page">

        <!-- ── Identity bar ─────────────────────────────────────────────── -->
        <div class="ss-ident">
            <div class="ss-ident-left">
                <span class="ss-online-dot" />
                <span class="ss-ident-handle">{{ player.handle ?? 'UNKNOWN' }}</span>
                <span class="ss-ident-sep">//</span>
                <span class="ss-ident-sub">RUNNER.STATUS</span>
            </div>
            <span class="ss-ident-time">{{ time }}</span>
        </div>

        <!-- ── System stability ──────────────────────────────────────────── -->
        <div class="ss-section">
            <div class="ss-sect-head">// SYSTEM</div>
            <div class="ss-stab-row">
                <span class="ss-stab-key">STABILITY</span>
                <div class="ss-stab-track">
                    <div
                        class="ss-stab-fill"
                        :class="ssClass"
                        :style="{ width: ssPercent + '%' }"
                    />
                    <div class="ss-stab-marks">
                        <span /><span /><span /><span />
                    </div>
                </div>
                <span class="ss-stab-val" :class="ssClass">
                    {{ ssPercent }}<span class="ss-stab-max">%</span>
                </span>
                <span v-if="player.isLimping" class="ss-limp-tag">LIMP</span>
            </div>
        </div>

        <div class="ss-div" />

        <!-- ── Economy ───────────────────────────────────────────────────── -->
        <div class="ss-section">
            <div class="ss-sect-head">// ECONOMY</div>
            <div class="ss-2col">
                <div class="ss-cell">
                    <span class="ss-cell-key">WALLET</span>
                    <span class="ss-cell-val ss-creds">◈ {{ (player.creds ?? 0).toLocaleString() }}</span>
                </div>
                <div class="ss-cell" :class="{ 'ss-cell--hot': (player.pocketCreds ?? 0) > 0 }">
                    <span class="ss-cell-key">POCKET
                        <span v-if="(player.pocketCreds ?? 0) > 0" class="ss-risk-tag">AT RISK</span>
                    </span>
                    <span class="ss-cell-val" :class="(player.pocketCreds ?? 0) > 0 ? 'ss-pocket' : 'ss-dim'">
                        ◈ {{ (player.pocketCreds ?? 0).toLocaleString() }}
                    </span>
                </div>
                <div class="ss-cell">
                    <span class="ss-cell-key">UPLINK</span>
                    <span class="ss-cell-val" :class="uplinkClass">
                        {{ player.uplink ?? 0 }}<span class="ss-unit">/{{ player.maxUplink ?? 3 }} LNK</span>
                    </span>
                </div>
                <div class="ss-cell">
                    <span class="ss-cell-key">TECH PTS</span>
                    <span class="ss-cell-val ss-tech">{{ player.techPoints ?? 0 }}<span class="ss-unit"> PTS</span></span>
                </div>
            </div>
        </div>

        <div class="ss-div" />

        <!-- ── Current run ───────────────────────────────────────────────── -->
        <div class="ss-section">
            <div class="ss-sect-head">// CURRENT RUN</div>
            <div class="ss-2col">
                <div class="ss-cell ss-cell--wide">
                    <span class="ss-cell-key">DISTRICT</span>
                    <span class="ss-cell-val ss-district">{{ (player.district ?? 'UNKNOWN').toUpperCase() }}</span>
                </div>
                <div class="ss-cell">
                    <span class="ss-cell-key">NODES</span>
                    <span class="ss-cell-val">{{ player.nodesHackedThisRun ?? 0 }}</span>
                </div>
                <div class="ss-cell">
                    <span class="ss-cell-key">PVP WINS</span>
                    <span class="ss-cell-val">{{ player.pvpWinsThisRun ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="ss-div" />

        <!-- ── Threat ─────────────────────────────────────────────────────── -->
        <div class="ss-section">
            <div class="ss-sect-head">// THREAT INDEX</div>

            <!-- Star rating -->
            <div class="ss-threat-stars">
                <span
                    v-for="i in 5"
                    :key="i"
                    class="ss-star"
                    :class="i <= (player.bountyLevel ?? 0) ? 'ss-star--on' : 'ss-star--off'"
                >★</span>
                <span class="ss-threat-mult" v-if="(player.bountyLevel ?? 0) > 0">
                    ×{{ (player.bountyMultiplier ?? 1).toFixed(2) }}
                </span>
                <span class="ss-threat-clean" v-else>CLEAN</span>
            </div>

            <!-- Heat bar — fills left→right as bounty increases 0→5 -->
            <div class="ss-heat-wrap">
                <div class="ss-heat-track">
                    <div class="ss-heat-fill" :style="{ width: heatPct + '%' }" :class="heatClass" />
                    <div class="ss-heat-marks">
                        <span v-for="n in 4" :key="n" />
                    </div>
                </div>
                <span class="ss-heat-label" :class="heatClass">{{ heatLabel }}</span>
            </div>

            <!-- Open Season banner -->
            <div v-if="player.isOpenSeason" class="ss-os-banner">
                <span class="ss-os-icon">⚡</span>
                OPEN SEASON — ALL RUNNERS ARE HOSTILE
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed, inject, ref, onMounted, onUnmounted } from 'vue';

defineProps({ url: { type: String, default: '' } });

const gameState = inject('gameState', null);
const player    = gameState?.player ?? ref({});

// Live clock
const time = ref('');
let _tick;
onMounted(() => {
    const update = () => {
        time.value = new Date().toLocaleTimeString('en-US', { hour12: false });
    };
    update();
    _tick = setInterval(update, 1000);
});
onUnmounted(() => clearInterval(_tick));

// System stability
const ssPercent = computed(() =>
    Math.min(100, ((player.value.currentSS ?? 0) / (player.value.maxSS ?? 100)) * 100)
);
const ssClass = computed(() => {
    if (ssPercent.value > 50) return 'ss-ok';
    if (ssPercent.value > 25) return 'ss-low';
    return 'ss-crit';
});

// Uplink colour
const uplinkClass = computed(() => {
    const pct = (player.value.uplink ?? 0) / (player.value.maxUplink ?? 3);
    if (pct > 0.5)  return 'ss-uplink-ok';
    if (pct > 0)    return 'ss-uplink-low';
    return 'ss-uplink-crit';
});

// Threat / heat bar
const heatPct = computed(() => ((player.value.bountyLevel ?? 0) / 5) * 100);
const heatClass = computed(() => {
    const lvl = player.value.bountyLevel ?? 0;
    if (lvl === 0) return 'heat-none';
    if (lvl <= 2)  return 'heat-low';
    if (lvl <= 3)  return 'heat-mid';
    return 'heat-high';
});
const heatLabel = computed(() => {
    const lvl = player.value.bountyLevel ?? 0;
    if (lvl === 0) return 'NOMINAL';
    if (lvl === 1) return 'ELEVATED';
    if (lvl === 2) return 'TRACKED';
    if (lvl === 3) return 'PRIORITY';
    if (lvl === 4) return 'CRITICAL';
    return 'MAXIMUM';
});
</script>

<style scoped>
.ss-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow-y: auto;
    overflow-x: hidden;
}
.ss-page::-webkit-scrollbar       { width: 2px; }
.ss-page::-webkit-scrollbar-track { background: transparent; }
.ss-page::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Identity bar ─────────────────────────────────────────────────────────── */
.ss-ident {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 9px 16px;
    background: rgba(0,255,255,0.02);
    border-bottom: 1px solid rgba(0,255,255,0.08);
    flex-shrink: 0;
}
.ss-ident-left   { display: flex; align-items: center; gap: 7px; }
.ss-online-dot   {
    width: 5px; height: 5px; border-radius: 50%;
    background: #00FF88;
    box-shadow: 0 0 6px rgba(0,255,136,0.7);
    flex-shrink: 0;
    animation: dot-pulse 3s ease-in-out infinite;
}
@keyframes dot-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
.ss-ident-handle { font-size: 10px; color: #00FFFF; letter-spacing: 0.1em; }
.ss-ident-sep    { font-size: 8px;  color: rgba(0,255,255,0.2); }
.ss-ident-sub    { font-size: 8px;  color: rgba(0,255,255,0.35); letter-spacing: 0.14em; }
.ss-ident-time   { font-size: 9px;  color: rgba(0,255,255,0.3); letter-spacing: 0.1em; }

/* ── Section wrapper ──────────────────────────────────────────────────────── */
.ss-section { padding: 10px 16px 12px; }
.ss-sect-head {
    font-size: 7px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.2em;
    margin-bottom: 10px;
}
.ss-div { height: 1px; background: rgba(0,255,255,0.06); }

/* ── Stability bar ────────────────────────────────────────────────────────── */
.ss-stab-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ss-stab-key {
    font-size: 7px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.1em;
    width: 72px;
    flex-shrink: 0;
}
.ss-stab-track {
    flex: 1;
    height: 6px;
    background: rgba(0,255,255,0.06);
    position: relative;
}
.ss-stab-fill { height: 100%; transition: width 0.4s ease; }
.ss-stab-marks {
    position: absolute;
    inset: 0;
    display: flex;
    justify-content: space-evenly;
    pointer-events: none;
}
.ss-stab-marks span {
    width: 1px;
    background: rgba(6,6,13,0.8);
}
.ss-stab-val {
    font-size: 9px;
    letter-spacing: 0.06em;
    flex-shrink: 0;
    width: 46px;
    text-align: right;
}
.ss-stab-max { font-size: 7px; color: rgba(0,255,255,0.25); }
.ss-limp-tag {
    font-size: 6px;
    color: #FF3333;
    border: 1px solid rgba(255,51,51,0.4);
    padding: 1px 4px;
    letter-spacing: 0.1em;
    animation: blink-tag 0.8s steps(1) infinite;
    flex-shrink: 0;
}
@keyframes blink-tag { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }

/* bar + text colour tokens */
.ss-ok   { background: #00FF88; color: #00FF88; }
.ss-low  { background: #FFB300; color: #FFB300; }
.ss-crit { background: #FF3333; color: #FF3333; }

/* ── 2-column grid ────────────────────────────────────────────────────────── */
.ss-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1px;
    background: rgba(0,255,255,0.05);
    border: 1px solid rgba(0,255,255,0.05);
}
.ss-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 12px;
    background: #06060d;
    position: relative;
}
.ss-cell--wide { grid-column: span 2; }
.ss-cell--hot  { background: rgba(255,179,0,0.025); }

.ss-cell-key {
    font-size: 7px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.12em;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ss-cell-val {
    font-size: 13px;
    color: rgba(0,255,255,0.7);
    letter-spacing: 0.04em;
    line-height: 1;
}
.ss-unit { font-size: 8px; color: rgba(0,255,255,0.3); }

.ss-risk-tag {
    font-size: 6px;
    color: rgba(255,179,0,0.7);
    border: 1px solid rgba(255,179,0,0.3);
    padding: 1px 4px;
    letter-spacing: 0.08em;
}

/* value colours */
.ss-creds       { color: #00FF88; }
.ss-pocket      { color: #FFB300; }
.ss-dim         { color: rgba(0,255,255,0.2); }
.ss-tech        { color: #7DF9FF; }
.ss-district    { color: rgba(0,255,255,0.75); font-size: 11px; }
.ss-uplink-ok   { color: #00FF88; }
.ss-uplink-low  { color: #FFB300; }
.ss-uplink-crit { color: #FF3333; }

/* ── Threat section ───────────────────────────────────────────────────────── */
.ss-threat-stars {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 10px;
}
.ss-star { font-size: 14px; transition: color 0.2s; }
.ss-star--on  { color: #FFB300; text-shadow: 0 0 8px rgba(255,179,0,0.5); }
.ss-star--off { color: rgba(255,179,0,0.12); }
.ss-threat-mult {
    margin-left: 8px;
    font-size: 10px;
    color: #FFB300;
    letter-spacing: 0.06em;
}
.ss-threat-clean {
    margin-left: 8px;
    font-size: 8px;
    color: rgba(0,255,136,0.4);
    letter-spacing: 0.12em;
}

/* Heat bar */
.ss-heat-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.ss-heat-track {
    flex: 1;
    height: 4px;
    background: rgba(0,255,255,0.06);
    position: relative;
}
.ss-heat-fill {
    height: 100%;
    transition: width 0.5s ease;
}
.ss-heat-marks {
    position: absolute;
    inset: 0;
    display: flex;
    justify-content: space-evenly;
    pointer-events: none;
}
.ss-heat-marks span { width: 1px; background: rgba(6,6,13,0.8); }
.ss-heat-label {
    font-size: 8px;
    letter-spacing: 0.12em;
    flex-shrink: 0;
    width: 74px;
    text-align: right;
}

/* heat colour variants */
.heat-none { background: rgba(0,255,136,0.2); color: rgba(0,255,136,0.35); }
.heat-low  { background: #FFB300;              color: #FFB300; }
.heat-mid  { background: #FF6B00;              color: #FF6B00; }
.heat-high { background: #FF3333;              color: #FF3333; box-shadow: 0 0 8px rgba(255,51,51,0.4); }

/* Open Season banner */
.ss-os-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding: 7px 10px;
    background: rgba(255,51,51,0.06);
    border: 1px solid rgba(255,51,51,0.35);
    font-size: 8px;
    color: #FF3333;
    letter-spacing: 0.12em;
    animation: os-flash 1.4s ease-in-out infinite;
}
.ss-os-icon { font-size: 10px; }
@keyframes os-flash {
    0%,100% { border-color: rgba(255,51,51,0.35); }
    50%      { border-color: rgba(255,51,51,0.8); }
}
</style>
