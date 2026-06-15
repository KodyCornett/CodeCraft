<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="dl-wrap">
            <div class="dl-content">

                <!-- Info bar -->
                <div class="dl-infobar">
                    <div class="dl-info-block">
                        <span class="dl-info-label">GOV_TARGET</span>
                        <span class="dl-info-val dl-info-val--target">{{ displayTarget }}mah</span>
                    </div>
                    <div class="dl-info-block">
                        <span class="dl-info-label">LOAD_SUM</span>
                        <span class="dl-info-val" :class="sumClass">{{ displaySum }}mah</span>
                    </div>
                    <div class="dl-info-block">
                        <span class="dl-info-label">ATTEMPTS</span>
                        <span class="dl-att-pips">
                            <span
                                v-for="i in MAX_ATTEMPTS"
                                :key="i"
                                class="dl-att-pip"
                                :class="i <= wrongAttempts ? 'dl-att-pip--used' : 'dl-att-pip--free'"
                            >◉</span>
                        </span>
                    </div>
                    <button
                        class="dl-inject-btn"
                        :class="{ 'dl-inject-btn--ready': pathComplete && !result && !resetting }"
                        :disabled="!pathComplete || !!result || resetting"
                        @click="onInject"
                    >[ INJECT // ]</button>
                </div>

                <!-- Grid SVG -->
                <svg
                    class="dl-svg"
                    :class="{ 'dl-svg--resetting': resetting }"
                    viewBox="0 0 820 360"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <!-- Path segments between consecutive selected nodes -->
                    <line
                        v-for="seg in pathSegments"
                        :key="seg.key"
                        :x1="seg.x1" :y1="seg.y1"
                        :x2="seg.x2" :y2="seg.y2"
                        class="dl-path-line"
                    />

                    <!-- STATE label -->
                    <text x="15" y="178" class="dl-side-label dl-side-label--state">STATE</text>

                    <!-- GOV label -->
                    <text x="806" y="178" class="dl-side-label dl-side-label--gov">GOV</text>

                    <!-- Nodes -->
                    <g v-for="(rowArr, r) in grid" :key="`row-${r}`">
                        <g
                            v-for="(val, c) in rowArr"
                            :key="`node-${r}-${c}`"
                            class="dl-node-g"
                            @click="onNodeClick(r, c)"
                        >
                            <circle
                                :cx="nx(c)"
                                :cy="ny(r)"
                                :r="NODE_R"
                                v-bind="nodeCircleAttrs(r, c)"
                            />
                            <text :x="nx(c)" :y="ny(r) + 4" class="dl-node-text">{{ displayVal(val) }}</text>
                        </g>
                    </g>
                </svg>

            </div>

            <!-- Error flash banner -->
            <Transition name="dl-err">
                <div v-if="showError" class="dl-error-banner">
                    ⚠ INCORRECT SEQUENCE — GRID RANDOMIZED
                </div>
            </Transition>
        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Grid constants ────────────────────────────────────────────────────────────

const ROWS         = 7;
const COLS         = 14;
const NODE_R       = 16;
const COL_SPACING  = 52;
const ROW_SPACING  = 46;
const GRID_START_X = 58;
const GRID_START_Y = 36;
const MAX_ATTEMPTS = 3;

function nx(col) { return GRID_START_X + col * COL_SPACING; }
function ny(row) { return GRID_START_Y + row * ROW_SPACING; }

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Grid state ────────────────────────────────────────────────────────────────

// Node values are stored as integers 1–99 representing 0.1–9.9mah.
// govTarget is the integer sum of one valid path (naturally a double-digit display value).

const grid          = ref([]);
const govTarget     = ref(0);
const selectedPath  = ref(Array(COLS).fill(null));  // row index per column, or null
const wrongAttempts = ref(0);
const showError     = ref(false);
const resetting     = ref(false);

function randomIntVal() { return Math.floor(Math.random() * 99) + 1; }
function displayVal(v)  { return (v / 10).toFixed(1); }

function generateGrid() {
    grid.value = Array.from({ length: ROWS }, () =>
        Array.from({ length: COLS }, () => randomIntVal())
    );
}

// Target range by difficulty — higher cap = harder match.
// D1: 10.0–39.9mah  D2: 30.0–79.9mah  D3: 50.0–138.6mah (triple digits possible)
const TARGET_RANGE = {
    1: { min: 100, max: 399 },
    2: { min: 300, max: 799 },
    3: { min: 500, max: 1386 },
};

function generateTarget() {
    const { min, max } = TARGET_RANGE[props.skin.difficulty ?? 1];
    let t;
    do {
        const path = Array.from({ length: COLS }, () => Math.floor(Math.random() * ROWS));
        t = path.reduce((s, row, col) => s + grid.value[row][col], 0);
    } while (t < min || t > max);
    govTarget.value = t;
}

function resetGrid() {
    generateGrid();
    generateTarget();
    selectedPath.value = Array(COLS).fill(null);
}

// ── Display computed ──────────────────────────────────────────────────────────

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
    if (!pathComplete.value)                    return 'dl-sum--neutral';
    if (runningSum.value === govTarget.value)   return 'dl-sum--match';
    if (runningSum.value > govTarget.value)     return 'dl-sum--over';
    return 'dl-sum--under';
});

// ── Path segments ─────────────────────────────────────────────────────────────

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

// ── Node interaction ──────────────────────────────────────────────────────────

function onNodeClick(row, col) {
    if (result.value || resetting.value) return;
    const next = selectedPath.value[col] === row ? null : row;
    selectedPath.value = selectedPath.value.map((r, c) => c === col ? next : r);
}

function nodeCircleAttrs(row, col) {
    if (selectedPath.value[col] === row) {
        return { fill: '#0a2018', stroke: '#00ff9d', 'stroke-width': 2 };
    }
    return { fill: '#040c07', stroke: 'rgba(0,255,100,0.18)', 'stroke-width': 1 };
}

// ── Inject / submit ───────────────────────────────────────────────────────────

function onInject() {
    if (!pathComplete.value || result.value || resetting.value) return;

    if (runningSum.value === govTarget.value) {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
        return;
    }

    // Wrong answer
    wrongAttempts.value++;
    applyHit(0.33);
    showError.value = true;
    resetting.value = true;

    if (wrongAttempts.value >= MAX_ATTEMPTS) {
        endGame('fail', '[SEQUENCE REJECTED] — Inject threshold exceeded. Connection terminated.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    setTimeout(() => {
        resetGrid();
        resetting.value = false;
        showError.value = false;
    }, 1200);
}

// ── Chrome passthrough ────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: primaryProgress.value,
    stability:       stability.value,
    stabilityClass:  stabilityClass.value,
    timerClass:      timerClass.value,
    glitchActive:    glitchActive.value,
    glitchType:      glitchType.value,
    glitchIntensity: glitchIntensity.value,
    result:          result.value,
    failReason:      failReason.value,
}));

// ── Game loop (shared bars tick) ──────────────────────────────────────────────

let animFrame = null;
let lastTs    = null;

function tick(ts) {
    if (result.value) return;
    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

// Generate initial grid in setup so there's no flash before onMounted.
generateGrid();
generateTarget();

onMounted(() => {
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.dl-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    position: relative;
    padding: 6px 0;
    box-sizing: border-box;
}

.dl-content {
    width: 100%;
    max-width: 880px;
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 0 12px;
    box-sizing: border-box;
}

/* ── Info bar ──────────────────────────────────────────────────────────────── */

.dl-infobar {
    display: flex;
    align-items: center;
    gap: 28px;
    padding: 6px 0 8px;
    border-bottom: 1px solid rgba(0,255,100,0.07);
    margin-bottom: 6px;
    flex-shrink: 0;
}

.dl-info-block {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.dl-info-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    color: rgba(0,255,100,0.28);
    letter-spacing: 0.15em;
}

.dl-info-val {
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.06em;
}

.dl-info-val--target { color: #ff6600; }
.dl-sum--neutral     { color: rgba(0,255,100,0.35); }
.dl-sum--match       { color: #00ff9d; animation: dl-pulse-text 0.9s ease infinite alternate; }
.dl-sum--over        { color: #ff3333; }
.dl-sum--under       { color: #FFB300; }

/* Attempt pips */
.dl-att-pips { display: flex; gap: 7px; margin-top: 2px; }
.dl-att-pip  { font-size: 13px; transition: color 0.25s; }
.dl-att-pip--free { color: rgba(0,255,100,0.25); }
.dl-att-pip--used { color: #ff3333; text-shadow: 0 0 6px rgba(255,51,51,0.5); }

/* Inject button */
.dl-inject-btn {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.12);
    color: rgba(0,255,100,0.2);
    padding: 8px 22px;
    cursor: not-allowed;
    transition: all 0.15s;
    flex-shrink: 0;
}

.dl-inject-btn--ready {
    border-color: rgba(0,255,100,0.55);
    color: #00ff9d;
    cursor: pointer;
    box-shadow: 0 0 14px rgba(0,255,100,0.1);
}

.dl-inject-btn--ready:hover {
    background: rgba(0,255,100,0.07);
    box-shadow: 0 0 22px rgba(0,255,100,0.2);
}

/* ── SVG grid ──────────────────────────────────────────────────────────────── */

.dl-svg {
    width: 100%;
    height: auto;
    flex: 1;
    display: block;
    transition: opacity 0.25s;
}

.dl-svg--resetting {
    opacity: 0.2;
}

/* Path connection lines between selected nodes */
.dl-path-line {
    stroke: #00ff9d;
    stroke-width: 1.5;
    stroke-opacity: 0.65;
    filter: drop-shadow(0 0 3px rgba(0,255,100,0.55));
    pointer-events: none;
}

/* Node groups */
.dl-node-g {
    cursor: pointer;
}

.dl-node-g:hover circle {
    stroke: rgba(0,255,100,0.6);
    stroke-width: 1.5;
}

.dl-node-g:hover text {
    fill: rgba(0,255,100,0.9);
}

/* Node value text */
.dl-node-text {
    fill: rgba(0,255,100,0.55);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    text-anchor: middle;
    pointer-events: none;
    letter-spacing: 0.03em;
}

/* STATE / GOV labels */
.dl-side-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 7px;
    text-anchor: middle;
    letter-spacing: 0.12em;
    pointer-events: none;
}

.dl-side-label--state { fill: rgba(0,255,100,0.35); }
.dl-side-label--gov   { fill: rgba(255,102,0,0.65); }

/* ── Error banner ──────────────────────────────────────────────────────────── */

.dl-error-banner {
    position: absolute;
    bottom: 14px;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: #ff3333;
    letter-spacing: 0.15em;
    background: rgba(20,0,0,0.92);
    border: 1px solid rgba(255,51,51,0.3);
    padding: 6px 18px;
    white-space: nowrap;
    pointer-events: none;
}

.dl-err-enter-active, .dl-err-leave-active { transition: opacity 0.2s; }
.dl-err-enter-from,   .dl-err-leave-to     { opacity: 0; }

/* ── Animations ────────────────────────────────────────────────────────────── */

@keyframes dl-pulse-text {
    from { text-shadow: 0 0 6px rgba(0,255,100,0.3); }
    to   { text-shadow: 0 0 18px rgba(0,255,100,0.75); }
}
</style>
