<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- ── CAPTURE SECTION ──────────────────────────────────────────── -->
            <div class="fb-capture-section">

                <div class="fb-status-bar">
                    <span class="fb-status-item">LIVE_FEED [ACTIVE]</span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item" :class="isBufferFull ? 'fb-status--warn' : ''">
                        BUFFER: {{ capturedSignals.length }}/{{ MAX_BUFFER }}
                    </span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item">PURGED: {{ anomalousFlushed }}/{{ locksRequired }}</span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item fb-status-item--mode">SYSTEM_DIAGNOSTIC_MODE: AUDIT</span>
                </div>

                <div class="fb-waveforms">
                    <div v-for="(row, ri) in rows" :key="ri" class="fb-row">
                        <span class="fb-row-label">W{{ ri + 1 }}</span>
                        <div class="fb-wave-area">
                            <svg
                                class="fb-svg"
                                viewBox="0 0 960 58"
                                preserveAspectRatio="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path :d="row.path" class="fb-wave-path" />
                                <line
                                    v-for="b in BLOCKS - 1" :key="`d${b}`"
                                    :x1="b * BLOCK_W" y1="0"
                                    :x2="b * BLOCK_W" y2="58"
                                    class="fb-block-div"
                                />
                                <rect
                                    v-for="b in BLOCKS" :key="`b${b}`"
                                    :x="(b - 1) * BLOCK_W + 1" y="1"
                                    :width="BLOCK_W - 2" height="56"
                                    class="fb-block-rect"
                                    :class="{
                                        'fb-block--spike': row.blocks[b-1].active,
                                        'fb-block--lock':  row.blocks[b-1].locking,
                                        'fb-block--full':  isBufferFull && !row.blocks[b-1].active,
                                    }"
                                    @click="onBlockClick(ri, b - 1)"
                                />
                            </svg>
                            <div class="fb-window-track">
                                <div
                                    class="fb-window-fill"
                                    :style="{ width: rowWindowPct(row) + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── AUDIT SECTION ────────────────────────────────────────────── -->
            <div class="fb-audit-section">

                <div class="fb-audit-main">

                    <!-- Reference baseline -->
                    <div class="fb-reference">
                        <span class="fb-ref-tag">REFERENCE BASELINE</span>
                        <span class="fb-ref-val">
                            REF: [cite: Source: [INTERNAL], Freq: 2.40–2.45 GHz, Load: &lt;130 KB]
                        </span>
                    </div>

                    <!-- Audit grid -->
                    <div class="fb-grid-wrap">
                        <table class="fb-grid">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SOURCE</th>
                                    <th>FREQ(GHz)</th>
                                    <th>LOAD(KB)</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="signal in capturedSignals"
                                    :key="signal.id"
                                    class="fb-grid-row"
                                    :class="{ 'fb-row--selected': selectedIds.includes(signal.id) }"
                                    @click="onRowClick(signal.id)"
                                >
                                    <td>{{ signal.id }}</td>
                                    <td>{{ signal.source }}</td>
                                    <td>{{ signal.freq }}</td>
                                    <td>{{ signal.load }}</td>
                                    <td class="fb-status-col">
                                        {{ selectedIds.includes(signal.id) ? '[FLAGGED]' : '[PENDING]' }}
                                    </td>
                                </tr>
                                <tr
                                    v-for="i in emptySlots"
                                    :key="`empty-${i}`"
                                    class="fb-grid-row fb-grid-row--empty"
                                >
                                    <td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Action buttons -->
                    <div class="fb-actions">
                        <button
                            class="fb-action-btn fb-btn--flush"
                            :disabled="selectedIds.length === 0 || !!result"
                            @click="onFlush"
                        >[ FLUSH ]</button>
                        <button
                            class="fb-action-btn fb-btn--clear"
                            :disabled="selectedIds.length === 0 || !!result"
                            @click="onClear"
                        >[ CLEAR ]</button>
                    </div>

                </div>

                <!-- Terminal log -->
                <div class="fb-terminal-log" ref="logEl">
                    <div class="fb-log-header">TERMINAL_LOG</div>
                    <div
                        v-for="(line, i) in terminalLog"
                        :key="i"
                        class="fb-log-line"
                        :class="{
                            'fb-log--warn':  line.type === 'warn',
                            'fb-log--error': line.type === 'error',
                        }"
                    >{{ line.text }}</div>
                </div>

            </div>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Constants ─────────────────────────────────────────────────────────────────

const BLOCKS    = 10;
const BLOCK_W   = 96;
const SVG_W     = 960;
const MAX_BUFFER = 8;
const BASE_AMP  = 14;
const SPIKE_AMP = 24;

// ── Difficulty config ─────────────────────────────────────────────────────────
// Tune after test run. All values are starting points.

const CONFIGS = {
    1: {
        spawnInterval:     { min: 3.5, max: 5.5 },
        windowDuration:    2.5,
        anomalyChance:     0.50,
        traceFlushBump:    0.12,
        validFlushHit:     0.20,
        bufferOverflowHit: 0.12,
        scrollSpeed:       1.2,
        freq:              1.2,
        anomalyProfile:    'obvious',
    },
    2: {
        spawnInterval:     { min: 2.2, max: 3.8 },
        windowDuration:    1.8,
        anomalyChance:     0.50,
        traceFlushBump:    0.16,
        validFlushHit:     0.22,
        bufferOverflowHit: 0.15,
        scrollSpeed:       1.5,
        freq:              1.5,
        anomalyProfile:    'subtle',
    },
    3: {
        spawnInterval:     { min: 1.0, max: 2.0 },
        windowDuration:    1.2,
        anomalyChance:     0.50,
        traceFlushBump:    0.20,
        validFlushHit:     0.25,
        bufferOverflowHit: 0.18,
        scrollSpeed:       1.8,
        freq:              1.8,
        anomalyProfile:    'obscure',
    },
};

const diffLevel     = props.skin.difficulty ?? 1;
const config        = CONFIGS[diffLevel] ?? CONFIGS[1];
const locksRequired = props.skin.locksRequired ?? 5;

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Waveform state ────────────────────────────────────────────────────────────

function makeBlock() {
    return { active: false, windowLeft: 0, windowMax: 0, locking: false };
}

function randSpawn() {
    return config.spawnInterval.min +
        Math.random() * (config.spawnInterval.max - config.spawnInterval.min);
}

const rows = ref([0, 1, 2].map(() => ({
    path:       '',
    blocks:     Array.from({ length: BLOCKS }, makeBlock),
    spawnTimer: randSpawn(),
})));

let scrollT = 0;

const STEPS_PER_BLOCK = 12;

function buildPath(ri) {
    const row = rows.value[ri];
    let d = '';
    for (let b = 0; b < BLOCKS; b++) {
        const amp = row.blocks[b].active ? SPIKE_AMP : BASE_AMP;
        for (let s = 0; s <= STEPS_PER_BLOCK; s++) {
            const x = (b + s / STEPS_PER_BLOCK) * BLOCK_W;
            const t = scrollT + (x / SVG_W) * Math.PI * 2 * config.freq;
            const y = 29 + amp * Math.sin(t);
            d += (b === 0 && s === 0)
                ? `M ${x.toFixed(1)} ${y.toFixed(2)}`
                : ` L ${x.toFixed(1)} ${y.toFixed(2)}`;
        }
    }
    return d;
}

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

// ── Audit grid state ──────────────────────────────────────────────────────────

const capturedSignals  = ref([]);
const selectedIds      = ref([]);
const anomalousFlushed = ref(0);
const isBufferFull     = computed(() => capturedSignals.value.length >= MAX_BUFFER);
const emptySlots       = computed(() => Math.max(0, MAX_BUFFER - capturedSignals.value.length));

let signalCounter = 0;

function generateSignal() {
    const id          = `SIG_${String(++signalCounter).padStart(3, '0')}`;
    const isAnomalous = Math.random() < config.anomalyChance;

    if (!isAnomalous) {
        return {
            id,
            isAnomalous: false,
            source: '[INTERNAL]',
            freq:   (2.40 + Math.random() * 0.05).toFixed(2),
            load:   Math.floor(100 + Math.random() * 30),
        };
    }

    switch (config.anomalyProfile) {
        case 'obvious':
            return {
                id,
                isAnomalous: true,
                source: Math.random() < 0.7 ? '[UNKNOWN]' : '[EXTERNAL]',
                freq:   (6.0  + Math.random() * 6.0).toFixed(2),
                load:   Math.floor(400 + Math.random() * 400),
            };
        case 'subtle':
            return {
                id,
                isAnomalous: true,
                source: Math.random() < 0.5 ? '[INTERNAL]' : '[INT-RELAY]',
                freq:   (2.58 + Math.random() * 0.30).toFixed(2),
                load:   Math.floor(148 + Math.random() * 70),
            };
        case 'obscure':
        default:
            return {
                id,
                isAnomalous: true,
                source: Math.random() < 0.7 ? '[INTERNAL]' : '[INT-RELAY]',
                freq:   (2.46 + Math.random() * 0.06).toFixed(2),
                load:   Math.floor(131 + Math.random() * 15),
            };
    }
}

// ── Terminal log ──────────────────────────────────────────────────────────────

const terminalLog = ref([]);
const logEl       = ref(null);
const MAX_LOG     = 14;

function addLog(text, type = 'info') {
    terminalLog.value.push({ text, type });
    if (terminalLog.value.length > MAX_LOG) terminalLog.value.shift();
    nextTick(() => {
        if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight;
    });
}

// ── Interaction ───────────────────────────────────────────────────────────────

function onBlockClick(ri, bi) {
    if (result.value) return;
    const block = rows.value[ri].blocks[bi];
    if (!block.active) return;

    if (isBufferFull.value) {
        addLog('> OVERFLOW: buffer full — flush required', 'warn');
        applyHit(config.bufferOverflowHit);
        return;
    }

    block.active     = false;
    block.windowLeft = 0;
    block.locking    = true;
    setTimeout(() => { rows.value[ri].blocks[bi].locking = false; }, 500);

    const signal = generateSignal();
    capturedSignals.value.push(signal);
    addLog(`> CAPTURE_LOCKED: ${signal.id} // W${ri + 1}`);

    if (capturedSignals.value.length >= MAX_BUFFER) {
        addLog('> WARNING: BUFFER FULL — flush to continue', 'warn');
    }
}

function onRowClick(id) {
    if (result.value) return;
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
}

function onFlush() {
    if (result.value || selectedIds.value.length === 0) return;

    const selected = capturedSignals.value.filter(s => selectedIds.value.includes(s.id));
    let validCount = 0;
    let anomCount  = 0;

    for (const s of selected) {
        if (s.isAnomalous) anomCount++;
        else validCount++;
    }

    // Remove selected signals from grid
    capturedSignals.value = capturedSignals.value.filter(s => !selectedIds.value.includes(s.id));
    selectedIds.value = [];

    if (validCount > 0) {
        applyHit(config.validFlushHit * validCount);
        primaryProgress.value = Math.min(1, primaryProgress.value + config.traceFlushBump * validCount);
        addLog(`> ERROR: ${validCount} VALID signal(s) flushed — TRACE UPDATED`, 'error');
    }

    if (anomCount > 0) {
        anomalousFlushed.value += anomCount;
        addLog(`> FLUSH_COMPLETE: ${anomCount} anomalous signal(s) purged`);
    }

    if (anomalousFlushed.value >= locksRequired) {
        addLog('> BUFFER CLEAN — ghost signal eliminated');
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
    }
}

function onClear() {
    selectedIds.value = [];
    addLog('> SELECTION CLEARED');
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

    scrollT -= dt * config.scrollSpeed;

    // Shared trace + stability — both are fail conditions
    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    for (let ri = 0; ri < rows.value.length; ri++) {
        const row = rows.value[ri];

        // Tick active block windows
        for (let bi = 0; bi < BLOCKS; bi++) {
            const block = row.blocks[bi];
            if (!block.active) continue;
            block.windowLeft -= dt;
            if (block.windowLeft <= 0) {
                block.active     = false;
                block.windowLeft = 0;
                // Missed spike — no penalty here, signal just wasn't captured
            }
        }

        // Spawn new spike
        row.spawnTimer -= dt;
        if (row.spawnTimer <= 0) {
            row.spawnTimer = randSpawn();
            const hasActive = row.blocks.some(b => b.active);
            if (!hasActive && !result.value) {
                const bi         = Math.floor(Math.random() * BLOCKS);
                row.blocks[bi].active     = true;
                row.blocks[bi].windowMax  = config.windowDuration;
                row.blocks[bi].windowLeft = config.windowDuration;
            }
        }

        row.path = buildPath(ri);
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    rows.value[1].spawnTimer += 1.4;
    rows.value[2].spawnTimer += 2.8;

    for (let ri = 0; ri < rows.value.length; ri++) {
        rows.value[ri].path = buildPath(ri);
    }

    addLog('> SYSTEM_DIAGNOSTIC_MODE: AUDIT');
    addLog('> LIVE_FEED: ACTIVE — monitoring 3 channels');
    addLog('> REFERENCE_BASELINE: loaded');
    addLog('> AWAITING CAPTURE...');

    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.fb-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    box-sizing: border-box;
}

/* ── CAPTURE SECTION ──────────────────────────────────────────────────────── */

.fb-capture-section {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,255,100,0.12);
    padding: 0 0 6px;
}

.fb-status-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 14px;
    background: rgba(0,255,100,0.03);
    border-bottom: 1px solid rgba(0,255,100,0.07);
    flex-shrink: 0;
}

.fb-status-item {
    font-size: 8px;
    color: rgba(0,255,100,0.45);
    letter-spacing: 0.12em;
}

.fb-status-item--mode { margin-left: auto; color: rgba(0,255,100,0.25); }
.fb-status--warn      { color: #ff6600; }
.fb-status-sep        { color: rgba(0,255,100,0.15); font-size: 8px; }

.fb-waveforms {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 6px 14px 0;
}

.fb-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.fb-row-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.12em;
    width: 22px;
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
    background: rgba(0,10,5,0.5);
    border: 1px solid rgba(0,255,100,0.07);
}

.fb-wave-path {
    fill: none;
    stroke: #00ff9d;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 2px rgba(0,255,100,0.3));
}

.fb-block-div {
    stroke: rgba(0,255,100,0.06);
    stroke-width: 1;
}

.fb-block-rect {
    fill: transparent;
    stroke: none;
    cursor: default;
}

.fb-block--spike {
    fill: rgba(255,102,0,0.10);
    stroke: rgba(255,102,0,0.5);
    stroke-width: 1;
    cursor: pointer;
    animation: fb-spike-pulse 0.4s ease infinite alternate;
}

.fb-block--lock {
    fill: rgba(0,255,100,0.15);
    stroke: rgba(0,255,100,0.5);
    stroke-width: 1;
}

.fb-block--full {
    fill: rgba(255,102,0,0.03);
    cursor: not-allowed;
}

.fb-window-track {
    height: 2px;
    background: rgba(255,102,0,0.06);
    overflow: hidden;
    margin-top: 1px;
}

.fb-window-fill {
    height: 100%;
    background: #ff6600;
    transition: width 0.05s linear;
}

/* ── AUDIT SECTION ────────────────────────────────────────────────────────── */

.fb-audit-section {
    flex: 1;
    display: flex;
    min-height: 0;
    gap: 0;
}

.fb-audit-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 8px 14px;
    border-right: 1px solid rgba(0,255,100,0.08);
}

/* Reference baseline */

.fb-reference {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 5px 8px;
    background: rgba(0,255,100,0.03);
    border: 1px solid rgba(0,255,100,0.10);
    margin-bottom: 6px;
    flex-shrink: 0;
}

.fb-ref-tag {
    font-size: 7px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.18em;
    flex-shrink: 0;
}

.fb-ref-val {
    font-size: 8px;
    color: rgba(0,255,100,0.55);
    letter-spacing: 0.06em;
}

/* Audit grid */

.fb-grid-wrap {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.fb-grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    letter-spacing: 0.06em;
}

.fb-grid thead tr {
    background: rgba(0,255,100,0.04);
}

.fb-grid th {
    text-align: left;
    padding: 5px 10px;
    font-size: 8px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.15em;
    border-bottom: 1px solid rgba(0,255,100,0.10);
    font-weight: normal;
}

.fb-grid-row {
    border-bottom: 1px solid rgba(0,255,100,0.05);
    cursor: pointer;
    transition: background 0.1s;
}

.fb-grid-row:hover:not(.fb-grid-row--empty) {
    background: rgba(0,255,100,0.04);
}

.fb-grid-row--empty {
    cursor: default;
    opacity: 0.2;
}

.fb-grid td {
    padding: 5px 10px;
    color: rgba(0,255,100,0.65);
}

.fb-grid-row--empty td {
    color: rgba(0,255,100,0.15);
}

.fb-row--selected {
    background: rgba(255,102,0,0.08) !important;
    border-left: 2px solid #ff6600;
}

.fb-row--selected td {
    color: #ff9933;
}

.fb-status-col {
    font-size: 9px;
    letter-spacing: 0.1em;
}

/* Action buttons */

.fb-actions {
    display: flex;
    gap: 10px;
    padding-top: 8px;
    flex-shrink: 0;
    border-top: 1px solid rgba(0,255,100,0.06);
    margin-top: 6px;
}

.fb-action-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.18em;
    background: transparent;
    padding: 7px 20px;
    cursor: pointer;
    transition: all 0.1s;
}

.fb-action-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.fb-btn--flush {
    border: 1px solid rgba(255,102,0,0.4);
    color: rgba(255,102,0,0.7);
}

.fb-btn--flush:hover:not(:disabled) {
    background: rgba(255,102,0,0.08);
    border-color: #ff6600;
    color: #ff9933;
}

.fb-btn--clear {
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.4);
}

.fb-btn--clear:hover:not(:disabled) {
    background: rgba(0,255,100,0.04);
    border-color: rgba(0,255,100,0.5);
    color: rgba(0,255,100,0.7);
}

/* ── TERMINAL LOG ─────────────────────────────────────────────────────────── */

.fb-terminal-log {
    width: 220px;
    flex-shrink: 0;
    background: rgba(0,5,2,0.6);
    border-left: 1px solid rgba(0,255,100,0.08);
    padding: 8px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    box-sizing: border-box;
}

.fb-log-header {
    font-size: 7px;
    color: rgba(0,255,100,0.25);
    letter-spacing: 0.2em;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(0,255,100,0.07);
    margin-bottom: 4px;
    flex-shrink: 0;
}

.fb-log-line {
    font-size: 8px;
    color: rgba(0,255,100,0.45);
    letter-spacing: 0.04em;
    line-height: 1.5;
    word-break: break-all;
}

.fb-log--warn  { color: rgba(255,153,0,0.7); }
.fb-log--error { color: rgba(255,80,80,0.8); }

/* ── Animations ───────────────────────────────────────────────────────────── */

@keyframes fb-spike-pulse {
    from { fill: rgba(255,102,0,0.06); stroke: rgba(255,102,0,0.3); }
    to   { fill: rgba(255,102,0,0.18); stroke: rgba(255,102,0,0.7); }
}
</style>
