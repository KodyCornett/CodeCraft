<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- Waveform rows -->
            <div
                v-for="(row, ri) in rows"
                :key="ri"
                class="fb-row"
            >
                <span class="fb-row-label">W{{ ri + 1 }}</span>

                <div class="fb-wave-area">
                    <svg
                        class="fb-svg"
                        viewBox="0 0 960 80"
                        preserveAspectRatio="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <!-- Wave path -->
                        <path :d="row.path" class="fb-wave-path" />

                        <!-- Block dividers -->
                        <line
                            v-for="b in BLOCKS - 1"
                            :key="`div-${b}`"
                            :x1="b * BLOCK_W" y1="0"
                            :x2="b * BLOCK_W" y2="80"
                            class="fb-block-div"
                        />

                        <!-- Block overlays — clickable, highlighted when spiking -->
                        <rect
                            v-for="b in BLOCKS"
                            :key="`block-${b}`"
                            :x="(b - 1) * BLOCK_W + 1"
                            y="1"
                            :width="BLOCK_W - 2"
                            height="78"
                            class="fb-block-rect"
                            :class="{
                                'fb-block--spike': row.blocks[b - 1].active,
                                'fb-block--lock':  row.blocks[b - 1].locking,
                            }"
                            @click="onBlockClick(ri, b - 1)"
                        />
                    </svg>

                    <!-- Spike window bar — runs under each waveform -->
                    <div class="fb-window-track">
                        <div
                            class="fb-window-fill"
                            :style="{ width: rowWindowPct(row) + '%', opacity: rowWindowPct(row) > 0 ? 1 : 0 }"
                        />
                    </div>
                </div>
            </div>

            <!-- Locks progress footer -->
            <div class="fb-footer">
                <span class="fb-footer-label">SIGNALS ISOLATED</span>
                <div class="fb-lock-pips">
                    <span
                        v-for="i in locksRequired"
                        :key="i"
                        class="fb-lock-pip"
                        :class="i <= locksCount ? 'fb-lock-pip--done' : ''"
                    >◆</span>
                </div>
                <span class="fb-lock-count">{{ locksCount }} / {{ locksRequired }}</span>
            </div>

            <!-- Lock confirmation flash -->
            <Transition name="fb-confirm">
                <div v-if="confirmFlash" class="fb-confirm-msg">
                    ✓ SIGNAL ISOLATED
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

// ── Constants ─────────────────────────────────────────────────────────────────

const BLOCKS  = 10;
const BLOCK_W = 96;    // 960 / 10
const SVG_W   = 960;
const BASE_AMP  = 18;  // normal wave amplitude (px in viewBox)
const SPIKE_AMP = 36;  // spiked wave amplitude

// ── Difficulty config ─────────────────────────────────────────────────────────
// Tune these after test run. Higher diff = shorter window, faster spawn, harder miss penalty.

const CONFIGS = {
    1: { spawnInterval: { min: 3.5, max: 5.5 }, windowDuration: 2.5, missHit: 0.18, scrollSpeed: 1.2, freq: 1.2 },
    2: { spawnInterval: { min: 2.0, max: 3.5 }, windowDuration: 1.8, missHit: 0.22, scrollSpeed: 1.5, freq: 1.5 },
    3: { spawnInterval: { min: 1.0, max: 2.0 }, windowDuration: 1.2, missHit: 0.26, scrollSpeed: 1.8, freq: 1.8 },
};

const diffLevel    = props.skin.difficulty ?? 1;
const config       = CONFIGS[diffLevel] ?? CONFIGS[1];
const locksRequired = props.skin.locksRequired ?? 5;

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Game state ────────────────────────────────────────────────────────────────

const locksCount   = ref(0);
const confirmFlash = ref(false);
let   confirmTimer = null;

function makeBlock() {
    return {
        active:     false,   // spike is currently firing
        windowLeft: 0,       // seconds remaining in window
        windowMax:  0,       // window duration for this spike
        locking:    false,   // brief green flash after successful lock
    };
}

function randSpawnTimer() {
    return config.spawnInterval.min +
        Math.random() * (config.spawnInterval.max - config.spawnInterval.min);
}

// Rows — one per waveform (W1/W2/W3)
const rows = ref([0, 1, 2].map(() => ({
    path:        '',
    blocks:      Array.from({ length: BLOCKS }, makeBlock),
    spawnTimer:  randSpawnTimer(),
})));

let scrollT = 0;

// ── Waveform path builder ─────────────────────────────────────────────────────
// Draws the wave block-by-block so each block's amplitude can differ independently.

const STEPS_PER_BLOCK = 14;

function buildPath(rowIdx) {
    const row = rows.value[rowIdx];
    let d = '';

    for (let b = 0; b < BLOCKS; b++) {
        const amp = row.blocks[b].active ? SPIKE_AMP : BASE_AMP;

        for (let s = 0; s <= STEPS_PER_BLOCK; s++) {
            const x = (b + s / STEPS_PER_BLOCK) * BLOCK_W;
            const t = scrollT + (x / SVG_W) * Math.PI * 2 * config.freq;
            const y = 40 + amp * Math.sin(t);
            const isFirst = b === 0 && s === 0;
            d += isFirst
                ? `M ${x.toFixed(1)} ${y.toFixed(2)}`
                : ` L ${x.toFixed(1)} ${y.toFixed(2)}`;
        }
    }

    return d;
}

// ── Window bar helper ─────────────────────────────────────────────────────────
// Returns the most urgent active block's window percentage for a row.

function rowWindowPct(row) {
    let best = 0;
    for (const block of row.blocks) {
        if (block.active && block.windowMax > 0) {
            const pct = (block.windowLeft / block.windowMax) * 100;
            if (pct > best) best = pct;
        }
    }
    return best;
}

// ── Interaction ───────────────────────────────────────────────────────────────

function onBlockClick(rowIdx, blockIdx) {
    if (result.value) return;
    const block = rows.value[rowIdx].blocks[blockIdx];
    if (!block.active) return;

    // Successful lock
    block.active     = false;
    block.windowLeft = 0;
    block.locking    = true;
    setTimeout(() => { rows.value[rowIdx].blocks[blockIdx].locking = false; }, 500);

    locksCount.value++;

    // Brief global confirm flash
    if (confirmTimer) clearTimeout(confirmTimer);
    confirmFlash.value = true;
    confirmTimer = setTimeout(() => { confirmFlash.value = false; }, 700);

    if (locksCount.value >= locksRequired) {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
    }
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

// ── Game loop ─────────────────────────────────────────────────────────────────

let animFrame = null;
let lastTs    = null;

function tick(ts) {
    if (result.value) return;

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    // Scroll waveform
    scrollT -= dt * config.scrollSpeed;

    // Shared trace + stability — treat both as fail conditions
    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Update each row
    for (let ri = 0; ri < rows.value.length; ri++) {
        const row = rows.value[ri];

        // Tick active block windows — handle expiry
        for (let b = 0; b < BLOCKS; b++) {
            const block = row.blocks[b];
            if (!block.active) continue;

            block.windowLeft -= dt;
            if (block.windowLeft <= 0) {
                // Missed — stability hit
                block.active     = false;
                block.windowLeft = 0;
                applyHit(config.missHit);
            }
        }

        // Spawn timer — fire a new spike if no block is already active in this row
        row.spawnTimer -= dt;
        if (row.spawnTimer <= 0) {
            row.spawnTimer = randSpawnTimer();

            // Only spawn if no active spike in this row (one per row at a time)
            const hasActive = row.blocks.some(b => b.active);
            if (!hasActive && !result.value) {
                const targetBlock        = Math.floor(Math.random() * BLOCKS);
                const block              = row.blocks[targetBlock];
                block.active             = true;
                block.windowMax          = config.windowDuration;
                block.windowLeft         = config.windowDuration;
            }
        }

        // Rebuild wave path
        row.path = buildPath(ri);
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    // Stagger initial spawn timers so all 3 rows don't fire simultaneously on load
    rows.value[1].spawnTimer += 1.2;
    rows.value[2].spawnTimer += 2.4;

    // Build initial paths
    for (let ri = 0; ri < rows.value.length; ri++) {
        rows.value[ri].path = buildPath(ri);
    }

    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
    if (confirmTimer) clearTimeout(confirmTimer);
});
</script>

<style scoped>
.fb-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    padding: 10px 16px;
    box-sizing: border-box;
    font-family: 'JetBrains Mono', monospace;
    gap: 6px;
}

/* ── Waveform row ─────────────────────────────────────────────────────────── */

.fb-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-height: 0;
}

.fb-row-label {
    font-size: 10px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.15em;
    width: 24px;
    flex-shrink: 0;
}

.fb-wave-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.fb-svg {
    width: 100%;
    display: block;
    border: 1px solid rgba(0,255,100,0.08);
    background: rgba(0,10,5,0.6);
    cursor: default;
}

/* ── Wave path ────────────────────────────────────────────────────────────── */

.fb-wave-path {
    fill: none;
    stroke: #00ff9d;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 3px rgba(0,255,100,0.35));
}

/* ── Block dividers ───────────────────────────────────────────────────────── */

.fb-block-div {
    stroke: rgba(0,255,100,0.07);
    stroke-width: 1;
}

/* ── Block overlays ───────────────────────────────────────────────────────── */

.fb-block-rect {
    fill: transparent;
    stroke: none;
    cursor: default;
    transition: fill 0.1s;
}

.fb-block--spike {
    fill: rgba(255,102,0,0.10);
    stroke: rgba(255,102,0,0.45);
    stroke-width: 1;
    cursor: pointer;
    animation: fb-spike-pulse 0.45s ease infinite alternate;
}

.fb-block--lock {
    fill: rgba(0,255,100,0.18);
    stroke: rgba(0,255,100,0.6);
    stroke-width: 1;
    animation: none;
}

/* ── Window bar ───────────────────────────────────────────────────────────── */

.fb-window-track {
    height: 3px;
    background: rgba(255,102,0,0.06);
    overflow: hidden;
    margin-top: 2px;
}

.fb-window-fill {
    height: 100%;
    background: #ff6600;
    box-shadow: 0 0 6px rgba(255,102,0,0.5);
    transition: width 0.05s linear, opacity 0.2s;
}

/* ── Footer ───────────────────────────────────────────────────────────────── */

.fb-footer {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-top: 6px;
    border-top: 1px solid rgba(0,255,100,0.06);
    flex-shrink: 0;
}

.fb-footer-label {
    font-size: 8px;
    color: rgba(0,255,100,0.25);
    letter-spacing: 0.18em;
}

.fb-lock-pips {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.fb-lock-pip {
    font-size: 10px;
    color: rgba(0,255,100,0.12);
    transition: color 0.2s, text-shadow 0.2s;
}

.fb-lock-pip--done {
    color: #00ff9d;
    text-shadow: 0 0 8px rgba(0,255,100,0.5);
}

.fb-lock-count {
    font-size: 11px;
    font-weight: 700;
    color: rgba(0,255,100,0.5);
    letter-spacing: 0.08em;
    margin-left: auto;
}

/* ── Confirm flash ────────────────────────────────────────────────────────── */

.fb-confirm-msg {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    color: #00ff9d;
    letter-spacing: 0.2em;
    background: rgba(0,20,10,0.95);
    border: 1px solid rgba(0,255,100,0.35);
    padding: 7px 22px;
    white-space: nowrap;
    pointer-events: none;
    text-shadow: 0 0 10px rgba(0,255,100,0.5);
    z-index: 10;
}

/* ── Transitions ──────────────────────────────────────────────────────────── */

.fb-confirm-enter-active, .fb-confirm-leave-active { transition: opacity 0.15s; }
.fb-confirm-enter-from,   .fb-confirm-leave-to     { opacity: 0; }

/* ── Animations ───────────────────────────────────────────────────────────── */

@keyframes fb-spike-pulse {
    from { fill: rgba(255,102,0,0.06); stroke: rgba(255,102,0,0.30); }
    to   { fill: rgba(255,102,0,0.16); stroke: rgba(255,102,0,0.65); }
}
</style>
