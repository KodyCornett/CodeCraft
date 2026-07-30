<template>
    <QuestMinigameChrome v-bind="chrome">

        <div class="ts-canvas">

            <!-- ══════════════════════════════════════════════════════════════
                 Top bar — TRACE meter | CODES CRACKED
            ══════════════════════════════════════════════════════════════ -->
            <div class="ts-top">
                <div class="ts-meter-group">
                    <span class="ts-meter-lbl">TRACE</span>
                    <div class="ts-meter-track">
                        <div class="ts-meter-fill ts-fill--trace"
                             :style="{ width: trace + '%' }"
                             :class="traceClass" />
                        <div v-for="t in [25,50,75]" :key="t"
                             class="ts-thresh-mark"
                             :style="{ left: t + '%' }" />
                    </div>
                    <span class="ts-meter-val" :class="traceClass">{{ Math.round(trace) }}%</span>
                </div>
                <div class="ts-codes-readout">
                    <span class="ts-codes-lbl">CODES CRACKED</span>
                    <span class="ts-codes-val">{{ codesCracked }} / {{ codesRequired }}</span>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 Main area — cipher wheel + ring controls
            ══════════════════════════════════════════════════════════════ -->
            <div class="ts-middle">

                <!-- Target code readout -->
                <div class="ts-target-bar">
                    <span class="ts-target-lbl">TARGET CIPHER ::</span>
                    <span v-for="(digit, i) in targetCode" :key="i"
                          class="ts-target-digit"
                          :class="{ 'ts-target-digit--aligned': alignedRings[i] }">
                        {{ RING_SYMBOLS[digit] }}
                    </span>
                </div>

                <div class="ts-wheel-wrap">

                    <!-- SVG cipher wheel -->
                    <svg class="ts-wheel-svg" viewBox="0 0 640 640">
                        <!-- Fixed read marker -->
                        <line class="ts-marker-line" x1="320" y1="14" :y2="markerOuterY" x2="320" />
                        <polygon class="ts-marker-tri" points="320,10 312,26 328,26" />

                        <g v-for="(ring, i) in ringCount" :key="'ring' + i">
                            <circle class="ts-ring-track"
                                    :class="{ 'ts-ring-track--aligned': alignedRings[i] }"
                                    cx="320" cy="320" :r="ringRadius(i)" />
                            <g class="ts-ring-symbols"
                               :style="{ transform: 'rotate(' + (-(ringPositions[i] * 36)) + 'deg)' }">
                                <text v-for="s in 10" :key="'sym' + i + '-' + s"
                                      class="ts-ring-sym"
                                      :class="{ 'ts-ring-sym--aligned': alignedRings[i] }"
                                      :x="symbolX(i, s - 1)" :y="symbolY(i, s - 1)">
                                    {{ RING_SYMBOLS[s - 1] }}
                                </text>
                            </g>
                        </g>
                    </svg>

                    <!-- Ring controls -->
                    <div class="ts-ring-controls">
                        <div v-for="(ring, i) in ringCount" :key="'ctrl' + i"
                             class="ts-ring-ctrl-row"
                             :class="{ 'ts-ring-ctrl-row--aligned': alignedRings[i] }">
                            <span class="ts-ring-ctrl-lbl">RING {{ i + 1 }}</span>
                            <button class="ts-ring-btn" @click="rotateRing(i, -1)">‹</button>
                            <span class="ts-ring-ctrl-val">{{ RING_SYMBOLS[ringPositions[i]] }}</span>
                            <button class="ts-ring-btn" @click="rotateRing(i, 1)">›</button>
                            <span class="ts-ring-ctrl-flag">{{ alignedRings[i] ? '✓' : '' }}</span>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';

// ── Cipher constants ────────────────────────────────────────────────────────────

const RING_SYMBOLS = ['0','1','2','3','4','5','6','7','8','9'];

const TRACE_PER_TICK_RATES = [
    { threshold: 90, rate: 4.0 },
    { threshold: 75, rate: 3.5 },
    { threshold: 60, rate: 3.0 },
    { threshold: 45, rate: 2.5 },
    { threshold: 30, rate: 2.0 },
    { threshold: 15, rate: 1.5 },
    { threshold: 0,  rate: 1.0 },
];
const TICK_INTERVAL_MS = 3000; // ms between trace ticks
const BASE_TIME        = 210;  // seconds (3.5 minutes)

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── ICE level — drives ring count 1:1 (3-8) ─────────────────────────────────────

const iceLevel = computed(() =>
    Math.min(8, Math.max(3, props.skin.iceLevel ?? props.skin.difficulty ?? 3))
);

const ringCount     = computed(() => iceLevel.value);
const codesRequired = computed(() => ringCount.value);

// ── Game state ───────────────────────────────────────────────────────────────────

const trace        = ref(0);
const codesCracked = ref(0);
const timeLeft      = ref(BASE_TIME + (props.skin.ramBonus ?? 0));
const gameResult    = ref(null);   // null | 'success' | 'fail'
const failReason    = ref('');

// ── Utilities ────────────────────────────────────────────────────────────────────

function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

function freshRingSet() {
    return Array.from({ length: ringCount.value }, () => randInt(0, 9));
}

const ringPositions = ref(freshRingSet());   // number[] — current index (0-9) shown at marker per ring
const targetCode    = ref(freshRingSet());   // number[] — target index (0-9) per ring

function scrambleRings()  { ringPositions.value = freshRingSet(); }
function generateTarget() { targetCode.value    = freshRingSet(); }

// ── Ring geometry (SVG) ────────────────────────────────────────────────────────

const RING_GAP    = 34;
const RING_INNER  = 50;

function ringRadius(i) { return RING_INNER + i * RING_GAP; }

function symbolAngleRad(s) {
    return (-90 + s * 36) * (Math.PI / 180);
}

function symbolX(i, s) {
    return 320 + ringRadius(i) * Math.cos(symbolAngleRad(s));
}

function symbolY(i, s) {
    return 320 + ringRadius(i) * Math.sin(symbolAngleRad(s)) + 4; // +4 to visually center text
}

const markerOuterY = computed(() => 40 + ringRadius(ringCount.value - 1) + 16);

// ── Ring interaction ─────────────────────────────────────────────────────────────

const alignedRings = computed(() =>
    ringPositions.value.map((v, i) => v === targetCode.value[i])
);

const allAligned = computed(() =>
    alignedRings.value.length > 0 && alignedRings.value.every(Boolean)
);

function rotateRing(i, dir) {
    if (gameResult.value) return;
    ringPositions.value[i] = (ringPositions.value[i] + dir + 10) % 10;
    if (allAligned.value) crackCode();
}

function crackCode() {
    codesCracked.value++;
    if (codesCracked.value >= codesRequired.value) {
        endGame('success', '');
        return;
    }
    generateTarget();
    scrambleRings();
}

// ── CSS state classes ────────────────────────────────────────────────────────────

const traceClass = computed(() => {
    if (trace.value >= 90) return 'ts-val--crit';
    if (trace.value >= 60) return 'ts-val--warn';
    return '';
});

// ── Win / fail ─────────────────────────────────────────────────────────────────

function checkFailConditions() {
    if (gameResult.value) return;
    if (trace.value >= 100) {
        endGame('fail', '[TRACE LOCKED] — ICE has pinpointed your signal.');
    }
}

function endGame(result, reason) {
    if (gameResult.value) return;
    gameResult.value = result;
    failReason.value = reason ?? '';
    clearAllIntervals();
    if (result === 'success') {
        setTimeout(() => emit('complete'), 2200);
    } else {
        setTimeout(() => emit('fail'), 2200);
    }
}

// ── Tick system ────────────────────────────────────────────────────────────────

function getTraceTickRate() {
    for (const { threshold, rate } of TRACE_PER_TICK_RATES) {
        if (trace.value >= threshold) return rate;
    }
    return 1.0;
}

const _intervals = [];

function startAllIntervals() {
    // Trace climbs every 3s
    _intervals.push(setInterval(() => {
        if (gameResult.value) return;
        trace.value = Math.min(100, trace.value + getTraceTickRate());
        checkFailConditions();
    }, TICK_INTERVAL_MS));

    // Timer countdown — 1s
    _intervals.push(setInterval(() => {
        if (gameResult.value) return;
        timeLeft.value = Math.max(0, timeLeft.value - 1);
        if (timeLeft.value <= 0) {
            endGame('fail', '[TIMER EXPIRED] — Cipher could not be cracked in time.');
        }
    }, 1000));
}

function clearAllIntervals() {
    _intervals.forEach(clearInterval);
    _intervals.length = 0;
}

// ── Chrome ─────────────────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: 0,
    stability:       1,
    stabilityClass:  '',
    timerClass:      timeLeft.value < 30 ? 'timer--critical' : timeLeft.value < 60 ? 'timer--warn' : '',
    glitchActive:    trace.value > 55 && !gameResult.value,
    glitchType:      trace.value > 75 ? 'static,bars' : 'scan',
    glitchIntensity: trace.value / 300,
    result:          gameResult.value,
    failReason:      failReason.value,
    hideBars:        true,
}));

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    startAllIntervals();
});

onUnmounted(() => {
    clearAllIntervals();
});
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════════════════
   Canvas
══════════════════════════════════════════════════════════════════════════════ */

.ts-canvas {
    width: 1920px;
    height: 100%;
    display: grid;
    grid-template-rows: 64px 1fr;
    font-family: 'JetBrains Mono', monospace;
    background: #04090e;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Top bar ──────────────────────────────────────────────────────────────── */

.ts-top {
    display: flex;
    align-items: center;
    gap: 32px;
    padding: 0 24px;
    background: rgba(0,0,0,0.5);
    border-bottom: 1px solid rgba(0,200,240,0.15);
}

.ts-meter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-width: 420px;
}

.ts-meter-lbl {
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.5);
    white-space: nowrap;
    flex-shrink: 0;
}

.ts-meter-track {
    flex: 1;
    height: 8px;
    background: rgba(0,200,240,0.08);
    border: 1px solid rgba(0,200,240,0.15);
    position: relative;
    overflow: visible;
}

.ts-meter-fill {
    height: 100%;
    transition: width 0.4s ease;
}

.ts-fill--trace { background: rgba(255,60,60,0.7); }

.ts-thresh-mark {
    position: absolute;
    top: -3px;
    bottom: -3px;
    width: 1px;
    background: rgba(255,255,255,0.2);
    pointer-events: none;
}

.ts-meter-val {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    min-width: 38px;
    text-align: right;
    color: rgba(0,200,240,0.8);
}

.ts-val--warn { color: #ffaa00 !important; }
.ts-val--crit { color: #ff3333 !important; animation: ts-blink 0.6s step-start infinite; }

.ts-codes-readout {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
}

.ts-codes-lbl {
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.5);
}

.ts-codes-val {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #00ff9d;
}

/* ── Main area ────────────────────────────────────────────────────────────── */

.ts-middle {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 18px;
    overflow: hidden;
    padding: 20px;
}

.ts-target-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    letter-spacing: 0.12em;
}

.ts-target-lbl {
    color: rgba(0,200,240,0.45);
    font-size: 10px;
    letter-spacing: 0.16em;
}

.ts-target-digit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 30px;
    border: 1px solid rgba(0,200,240,0.25);
    color: rgba(0,200,240,0.6);
    font-weight: 700;
}

.ts-target-digit--aligned {
    color: #00ff9d;
    border-color: rgba(0,255,157,0.6);
    text-shadow: 0 0 8px rgba(0,255,157,0.5);
}

.ts-wheel-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 40px;
    flex: 1;
    min-height: 0;
}

.ts-wheel-svg {
    width: 560px;
    height: 560px;
    flex-shrink: 0;
}

.ts-marker-line {
    stroke: rgba(0,255,157,0.6);
    stroke-width: 1.5;
}

.ts-marker-tri {
    fill: rgba(0,255,157,0.8);
}

.ts-ring-track {
    fill: none;
    stroke: rgba(0,200,240,0.15);
    stroke-width: 1;
    transition: stroke 0.3s ease;
}

.ts-ring-track--aligned {
    stroke: rgba(0,255,157,0.45);
}

.ts-ring-symbols {
    transform-origin: 320px 320px;
    transition: transform 0.2s ease;
}

.ts-ring-sym {
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    fill: rgba(0,200,240,0.55);
    text-anchor: middle;
    transition: fill 0.3s ease;
}

.ts-ring-sym--aligned {
    fill: #00ff9d;
}

.ts-ring-controls {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 560px;
    overflow-y: auto;
    padding: 4px;
}

.ts-ring-ctrl-row {
    display: grid;
    grid-template-columns: 60px 26px 30px 26px 16px;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid rgba(0,200,240,0.15);
    background: rgba(0,0,0,0.3);
    transition: border-color 0.3s ease;
}

.ts-ring-ctrl-row--aligned {
    border-color: rgba(0,255,157,0.5);
    background: rgba(0,255,157,0.04);
}

.ts-ring-ctrl-lbl {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(0,200,240,0.5);
}

.ts-ring-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    background: transparent;
    border: 1px solid rgba(0,200,240,0.3);
    color: #00c8f0;
    cursor: pointer;
    padding: 2px 6px;
    line-height: 1;
    transition: all 0.15s;
}

.ts-ring-btn:hover {
    background: rgba(0,200,240,0.1);
    border-color: rgba(0,200,240,0.6);
}

.ts-ring-ctrl-val {
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    color: #00c8f0;
}

.ts-ring-ctrl-flag {
    color: #00ff9d;
    font-size: 12px;
    text-align: center;
}

/* ── Animations ───────────────────────────────────────────────────────────── */

@keyframes ts-blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.3; }
}
</style>
