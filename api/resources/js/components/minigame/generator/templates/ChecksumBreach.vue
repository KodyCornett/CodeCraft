<template>
    <div class="cb-overlay">
        <div class="cb-terminal">

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="cb-topbar">
                <span>NODE: {{ nodeLabel }}</span>
                <span class="cb-timer" :class="timerClass">TIME REMAINING: {{ Math.ceil(timeLeft) }}s</span>
                <button class="cb-abort-btn" @click="onAbort">[ ABORT ]</button>
            </div>
            <div class="cb-rule" />

            <!-- ── Info bar — target checksum, running sum, attempts, submit ──── -->
            <div class="cb-infobar">
                <div class="cb-info-block">
                    <span class="cb-info-label">ICE_CHECKSUM</span>
                    <span class="cb-info-val cb-info-val--target">{{ displayTarget }}</span>
                </div>
                <div class="cb-info-block">
                    <span class="cb-info-label">PATH_SUM</span>
                    <span class="cb-info-val" :class="sumClass">{{ displaySum }}</span>
                </div>
                <div class="cb-info-block">
                    <span class="cb-info-label">ATTEMPTS</span>
                    <span class="cb-att-pips">
                        <span
                            v-for="i in maxAttempts"
                            :key="i"
                            class="cb-att-pip"
                            :class="i <= wrongAttempts ? 'cb-att-pip--used' : 'cb-att-pip--free'"
                        >◉</span>
                    </span>
                </div>
                <button
                    class="cb-decrypt-btn"
                    :class="{ 'cb-decrypt-btn--ready': pathComplete && status === 'playing' && !resetting }"
                    :disabled="!pathComplete || status !== 'playing' || resetting"
                    @click="onDecrypt"
                >[ DECRYPT // ]</button>
            </div>

            <!-- ── Grid ─────────────────────────────────────────────────────── -->
            <svg
                class="cb-svg"
                :class="{ 'cb-svg--resetting': resetting }"
                viewBox="0 0 820 360"
                xmlns="http://www.w3.org/2000/svg"
            >
                <line
                    v-for="seg in pathSegments" :key="seg.key"
                    :x1="seg.x1" :y1="seg.y1" :x2="seg.x2" :y2="seg.y2"
                    class="cb-path-line"
                />

                <text x="15" y="178" class="cb-side-label cb-side-label--in">IN</text>
                <text x="806" y="178" class="cb-side-label cb-side-label--out">ICE</text>

                <g v-for="(rowArr, r) in grid" :key="`row-${r}`">
                    <g
                        v-for="(val, c) in rowArr" :key="`node-${r}-${c}`"
                        class="cb-node-g"
                        @click="onNodeClick(r, c)"
                    >
                        <circle :cx="nx(c)" :cy="ny(r)" :r="NODE_R" v-bind="nodeCircleAttrs(r, c)" />
                        <text :x="nx(c)" :y="ny(r) + 4" class="cb-node-text">{{ displayVal(val) }}</text>
                    </g>
                </g>
            </svg>

            <!-- Wrong-attempt error flash -->
            <Transition name="cb-err">
                <div v-if="showError" class="cb-error-banner">⚠ CHECKSUM REJECTED — GRID RANDOMIZED</div>
            </Transition>

            <!-- ── Outcome overlay ──────────────────────────────────────────── -->
            <div v-if="status !== 'playing'" class="cb-outcome-overlay" :class="`outcome--${status}`">
                <div class="cb-outcome-title">{{ status === 'success' ? 'CHECKSUM MATCHED' : 'BREACH REJECTED' }}</div>
                <div class="cb-outcome-sub">{{ status === 'success' ? outcomeSuccessMsg : outcomeFailMsg }}</div>
                <button class="cb-outcome-btn" @click="onDismiss">[ CONTINUE ]</button>
            </div>

        </div>
    </div>
</template>

<script setup>
/**
 * CHECKSUM BREACH — node-hack pool template (generator key: 'checksum_breach').
 *
 * A logic puzzle, not a reflex game: select one node value per grid column
 * to build a path whose sum matches a target ICE checksum before either the
 * clock runs out or a limited number of wrong submissions are used up.
 * Ported from the quest minigame DisconnectLayer's grid-path-sum mechanic —
 * same proven combinatorics, reflavored as a checksum match instead of a
 * power-load match, and rebuilt against the node-hack pool's contract
 * (matches GridBreach's props/emits exactly) instead of the quest
 * emit('complete'/'fail') + useQuestMinigameState contract DisconnectLayer
 * itself uses. Self-contained UI, no shared chrome — same as GridBreach.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { computeRewardAmount, outcomeSuccessMessage } from '../rewardFormula.js';

// ─── Props & emits — identical contract to every other pool entry ────────────
const props = defineProps({
    node:             { type: Object,  default: null    },
    resource:         { type: String,  default: 'creds' },  // 'creds' | 'tech' | 'uplink'
    playerCpu:        { type: Number,  default: 3       },
    playerRam:        { type: Number,  default: 2       },
    playerOs:         { type: Number,  default: 2       },
    playerFirewall:   { type: Number,  default: 1       },
    playerMaxUplink:  { type: Number,  default: 3       },
    bountyMultiplier: { type: Number,  default: 1.0     },
    paused:           { type: Boolean, default: false   },
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const nodeLabel = computed(() => props.node?.canvasId ?? props.node?.id ?? 'UNKNOWN');

// ─── Grid constants ────────────────────────────────────────────────────────────
const ROWS         = 7;
const COLS         = 14;
const NODE_R       = 16;
const COL_SPACING  = 52;
const ROW_SPACING  = 46;
const GRID_START_X = 58;
const GRID_START_Y = 36;

function nx(col) { return GRID_START_X + col * COL_SPACING; }
function ny(row) { return GRID_START_Y + row * ROW_SPACING; }

// ─── Difficulty — same ICE tiering GridBreach uses, for a consistent feel
// across the pool. Target range widens (harder to land exactly) and the
// wrong-attempt allowance tightens as ICE climbs.
//   ICE 3–4  →  Tier 1
//   ICE 5–6  →  Tier 2
//   ICE 7–8  →  Tier 3
//   ICE 9–10 →  Tier 4
const MIN_ICE = 3;
const iceLevel = computed(() => Math.max(MIN_ICE, props.node?.ice ?? MIN_ICE));

const TIER_CONFIG = {
    1: { targetMin: 100, targetMax: 399,  maxAttempts: 3 },
    2: { targetMin: 300, targetMax: 799,  maxAttempts: 3 },
    3: { targetMin: 600, targetMax: 1100, maxAttempts: 2 },
    4: { targetMin: 900, targetMax: 1380, maxAttempts: 2 },
};

function tierForIce(ice) {
    if (ice <= 4) return 1;
    if (ice <= 6) return 2;
    if (ice <= 8) return 3;
    return 4;
}

const tierCfg      = computed(() => TIER_CONFIG[tierForIce(iceLevel.value)]);
const maxAttempts  = computed(() => tierCfg.value.maxAttempts);

// Timer — same shape as GridBreach's own formula: RAM widens the overall
// clock, CPU-vs-ICE is asymmetric (being under-geared compounds as a
// penalty, being over-geared gives a modest bonus). Captured once at setup
// so it doesn't shift mid-run if props change.
const baseTimer = 40 + props.playerRam * 6 + Math.round(props.playerOs * 0.4);
const cpuDiff   = props.playerCpu - iceLevel.value;
const timerMod  = cpuDiff >= 0 ? cpuDiff * 3 : -(cpuDiff * cpuDiff) * 2;
const totalTime = Math.max(15, baseTimer + timerMod);

// ─── Grid/target state ─────────────────────────────────────────────────────────

const grid          = ref([]);
const govTarget     = ref(0);   // integer 10ths, e.g. 1055 → displayed "105.5"
const selectedPath  = ref(Array(COLS).fill(null));
const wrongAttempts = ref(0);
const showError     = ref(false);
const resetting     = ref(false);
const status        = ref('playing'); // 'playing' | 'success' | 'failed'
const timeLeft      = ref(totalTime);

function randomIntVal() { return Math.floor(Math.random() * 99) + 1; }
function displayVal(v)  { return (v / 10).toFixed(1); }

function generateGrid() {
    grid.value = Array.from({ length: ROWS }, () =>
        Array.from({ length: COLS }, () => randomIntVal())
    );
}

function generateTarget() {
    const { targetMin, targetMax } = tierCfg.value;
    let t;
    do {
        const path = Array.from({ length: COLS }, () => Math.floor(Math.random() * ROWS));
        t = path.reduce((s, row, col) => s + grid.value[row][col], 0);
    } while (t < targetMin || t > targetMax);
    govTarget.value = t;
}

function resetGrid() {
    generateGrid();
    generateTarget();
    selectedPath.value = Array(COLS).fill(null);
}

// Generate initial grid in setup so there's no flash before onMounted.
generateGrid();
generateTarget();

// ─── Display computed ──────────────────────────────────────────────────────────

const displayTarget = computed(() => (govTarget.value / 10).toFixed(1));

const runningSum = computed(() =>
    selectedPath.value.reduce((s, row, col) => {
        if (row === null) return s;
        return s + grid.value[row][col];
    }, 0)
);

const displaySum = computed(() => (runningSum.value / 10).toFixed(1));

const pathComplete = computed(() => selectedPath.value.every(r => r !== null));

const sumClass = computed(() => {
    if (!pathComplete.value)                  return 'cb-sum--neutral';
    if (runningSum.value === govTarget.value) return 'cb-sum--match';
    if (runningSum.value > govTarget.value)   return 'cb-sum--over';
    return 'cb-sum--under';
});

const timerClass = computed(() => {
    const pct = timeLeft.value / totalTime;
    if (pct <= 0.10) return 'timer--critical';
    if (pct <= 0.25) return 'timer--warn';
    return '';
});

// ─── Path segments ─────────────────────────────────────────────────────────────

const pathSegments = computed(() => {
    const segs = [];
    for (let c = 0; c < COLS - 1; c++) {
        const r1 = selectedPath.value[c];
        const r2 = selectedPath.value[c + 1];
        if (r1 !== null && r2 !== null) {
            segs.push({
                key: `seg-${c}`,
                x1: nx(c),     y1: ny(r1),
                x2: nx(c + 1), y2: ny(r2),
            });
        }
    }
    return segs;
});

// ─── Node interaction ──────────────────────────────────────────────────────────

function onNodeClick(row, col) {
    if (status.value !== 'playing' || resetting.value) return;
    const next = selectedPath.value[col] === row ? null : row;
    selectedPath.value = selectedPath.value.map((r, c) => c === col ? next : r);
}

function nodeCircleAttrs(row, col) {
    if (selectedPath.value[col] === row) {
        return { fill: '#0a2018', stroke: '#00ff9d', 'stroke-width': 2 };
    }
    return { fill: '#040c07', stroke: 'rgba(0,255,100,0.18)', 'stroke-width': 1 };
}

// ─── Reward formula ─────────────────────────────────────────────────────────────
// Binary win/lose puzzle — no partial credit mid-run — so completionPct is
// always 1.0 on success. Shared with every other binary-outcome pool
// template via rewardFormula.js instead of each one carrying its own copy.
const rewardAmount = computed(() => computeRewardAmount({
    resource:         props.resource,
    ice:              iceLevel.value,
    bountyMultiplier: props.bountyMultiplier,
    playerMaxUplink:  props.playerMaxUplink,
}));

const outcomeSuccessMsg = computed(() => outcomeSuccessMessage(props.resource, rewardAmount.value));

const outcomeFailMsg = 'CHECKSUM NOT MATCHED — ICE HELD — NO YIELD';

// ─── Submit / outcome ───────────────────────────────────────────────────────────

function onDecrypt() {
    if (!pathComplete.value || status.value !== 'playing' || resetting.value) return;

    if (runningSum.value === govTarget.value) {
        status.value = 'success';
        return;
    }

    wrongAttempts.value++;
    showError.value = true;
    resetting.value = true;

    if (wrongAttempts.value >= maxAttempts.value) {
        status.value = 'failed';
        return;
    }

    setTimeout(() => {
        resetGrid();
        resetting.value = false;
        showError.value = false;
    }, 1200);
}

function onDismiss() {
    if (status.value === 'success') {
        emit('complete', { resource: props.resource, amount: rewardAmount.value, completionPct: 1.0 });
    } else {
        emit('failed', { resource: props.resource, amount: 0 });
    }
}

function onAbort() {
    emit('abort');
}

// ─── Game loop — timer only; grid interaction is event-driven ─────────────────

let animFrame = null;
let lastTs    = null;

function tick(ts) {
    if (status.value !== 'playing') return;

    if (props.paused) {
        lastTs = null;
        animFrame = requestAnimationFrame(tick);
        return;
    }

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    timeLeft.value = Math.max(0, timeLeft.value - dt);

    if (timeLeft.value <= 0) {
        status.value = 'failed';
        return;
    }

    animFrame = requestAnimationFrame(tick);
}

onMounted(() => {
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.cb-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: rgba(1, 5, 3, 0.94);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
}

.cb-terminal {
    width: min(1000px, 92vw);
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    padding: 24px 32px 28px;
    box-sizing: border-box;
    background: #040c07;
    border: 1px solid rgba(0,255,100,0.25);
    box-shadow: 0 0 40px rgba(0,255,100,0.08);
    position: relative;
}

/* ── Top bar ───────────────────────────────────────────────────────────────── */

.cb-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 13px;
    letter-spacing: 0.12em;
    color: rgba(0,255,100,0.55);
    flex-shrink: 0;
}

.cb-timer { color: #00ff9d; }
.cb-timer.timer--warn     { color: #FFB300; }
.cb-timer.timer--critical { color: #ff3333; animation: cb-blink 0.6s ease infinite alternate; }

.cb-abort-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.35);
    color: rgba(255,51,51,0.75);
    padding: 7px 18px;
    cursor: pointer;
    transition: all 0.15s;
}
.cb-abort-btn:hover { background: rgba(255,51,51,0.08); border-color: #ff3333; color: #ff3333; }

.cb-rule {
    height: 1px;
    background: rgba(0,255,100,0.12);
    margin: 10px 0 12px;
    flex-shrink: 0;
}

/* ── Info bar ──────────────────────────────────────────────────────────────── */

.cb-infobar {
    display: flex;
    align-items: center;
    gap: 36px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,255,100,0.07);
    margin-bottom: 12px;
    flex-shrink: 0;
}

.cb-info-block { display: flex; flex-direction: column; gap: 3px; }

.cb-info-label {
    font-size: 10px;
    color: rgba(0,255,100,0.4);
    letter-spacing: 0.15em;
}

.cb-info-val { font-size: 19px; font-weight: 700; letter-spacing: 0.06em; }

.cb-info-val--target { color: #ff6600; }
.cb-sum--neutral      { color: rgba(0,255,100,0.35); }
.cb-sum--match        { color: #00ff9d; animation: cb-pulse-text 0.9s ease infinite alternate; }
.cb-sum--over         { color: #ff3333; }
.cb-sum--under        { color: #FFB300; }

.cb-att-pips { display: flex; gap: 9px; margin-top: 3px; }
.cb-att-pip  { font-size: 16px; transition: color 0.25s; }
.cb-att-pip--free { color: rgba(0,255,100,0.25); }
.cb-att-pip--used { color: #ff3333; text-shadow: 0 0 6px rgba(255,51,51,0.5); }

.cb-decrypt-btn {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.12);
    color: rgba(0,255,100,0.2);
    padding: 10px 28px;
    cursor: not-allowed;
    transition: all 0.15s;
    flex-shrink: 0;
}

.cb-decrypt-btn--ready {
    border-color: rgba(0,255,100,0.55);
    color: #00ff9d;
    cursor: pointer;
    box-shadow: 0 0 14px rgba(0,255,100,0.1);
}
.cb-decrypt-btn--ready:hover {
    background: rgba(0,255,100,0.07);
    box-shadow: 0 0 22px rgba(0,255,100,0.2);
}

/* ── SVG grid ──────────────────────────────────────────────────────────────── */

.cb-svg {
    width: 100%;
    height: auto;
    flex: 1;
    display: block;
    transition: opacity 0.25s;
}

.cb-svg--resetting { opacity: 0.2; }

.cb-path-line {
    stroke: #00ff9d;
    stroke-width: 1.5;
    stroke-opacity: 0.65;
    filter: drop-shadow(0 0 3px rgba(0,255,100,0.55));
    pointer-events: none;
}

.cb-node-g { cursor: pointer; }
.cb-node-g:hover circle { stroke: rgba(0,255,100,0.6); stroke-width: 1.5; }
.cb-node-g:hover text   { fill: rgba(0,255,100,0.9); }

.cb-node-text {
    fill: rgba(0,255,100,0.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    text-anchor: middle;
    pointer-events: none;
    letter-spacing: 0.03em;
}

.cb-side-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    text-anchor: middle;
    letter-spacing: 0.12em;
    pointer-events: none;
}
.cb-side-label--in  { fill: rgba(0,255,100,0.35); }
.cb-side-label--out { fill: rgba(255,102,0,0.65); }

/* ── Error banner ──────────────────────────────────────────────────────────── */

.cb-error-banner {
    position: absolute;
    bottom: 14px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #ff3333;
    letter-spacing: 0.15em;
    background: rgba(20,0,0,0.92);
    border: 1px solid rgba(255,51,51,0.3);
    padding: 6px 18px;
    white-space: nowrap;
    pointer-events: none;
}

.cb-err-enter-active, .cb-err-leave-active { transition: opacity 0.2s; }
.cb-err-enter-from,   .cb-err-leave-to     { opacity: 0; }

/* ── Outcome overlay ──────────────────────────────────────────────────────── */

.cb-outcome-overlay {
    position: absolute;
    inset: 0;
    background: rgba(1,5,3,0.96);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-align: center;
    padding: 0 24px;
}

.cb-outcome-title { font-size: 28px; letter-spacing: 0.15em; }
.outcome--success .cb-outcome-title { color: #00FF88; text-shadow: 0 0 24px rgba(0,255,136,0.55); }
.outcome--failed  .cb-outcome-title { color: #FF3333; text-shadow: 0 0 24px rgba(255,51,51,0.55); }

.cb-outcome-sub {
    font-size: 14px;
    letter-spacing: 0.08em;
    color: rgba(0,255,100,0.7);
}

.cb-outcome-btn {
    margin-top: 12px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(0,255,255,0.4);
    color: rgba(0,255,255,0.85);
    padding: 10px 32px;
    cursor: pointer;
    transition: all 0.15s;
}
.cb-outcome-btn:hover { background: rgba(0,255,255,0.07); border-color: rgba(0,255,255,0.65); }

/* ── Animations ────────────────────────────────────────────────────────────── */

@keyframes cb-pulse-text {
    from { text-shadow: 0 0 6px rgba(0,255,100,0.3); }
    to   { text-shadow: 0 0 18px rgba(0,255,100,0.75); }
}

@keyframes cb-blink {
    from { opacity: 1; }
    to   { opacity: 0.4; }
}
</style>
