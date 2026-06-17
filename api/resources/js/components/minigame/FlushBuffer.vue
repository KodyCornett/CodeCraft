<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- ── CAPTURE SECTION ──────────────────────────────────────────── -->
            <div class="fb-capture-section">

                <div class="fb-status-bar">
                    <button
                        class="fb-scan-btn"
                        :class="isScanning ? 'fb-scan--active' : 'fb-scan--paused'"
                        @click="toggleScan"
                    >{{ isScanning ? '[ ■ SCANNING ]' : '[ ▶ SCAN PAUSED ]' }}</button>
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
                                <div class="fb-window-fill" :style="{ width: rowWindowPct(row) + '%' }" />
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── SIGNAL INSPECT PANEL ─────────────────────────────────────── -->
            <Transition name="fb-inspect">
                <div v-if="inspectedSignal" class="fb-inspect-panel">

                    <div class="fb-inspect-header">
                        <span class="fb-inspect-id">SIGNAL_INSPECT // {{ inspectedSignal.id }}</span>
                        <span
                            class="fb-inspect-verdict"
                            :class="anyDeviation ? 'fb-verdict--warn' : 'fb-verdict--clean'"
                        >{{ anyDeviation ? '[ ANOMALY INDICATORS DETECTED ]' : '[ WITHIN BASELINE TOLERANCE ]' }}</span>
                        <button class="fb-inspect-close" @click="clearInspect">[ × ]</button>
                    </div>

                    <div class="fb-inspect-body">
                        <!-- Reference waveform -->
                        <div class="fb-inspect-wave">
                            <div class="fb-inspect-wave-label fb-label--ref">REF BASELINE</div>
                            <svg class="fb-inspect-svg" viewBox="0 0 240 44" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                                <path :d="refWavePath" class="fb-inspect-path fb-path--ref" />
                            </svg>
                            <div class="fb-inspect-metrics">
                                <span class="fb-metric">SRC&nbsp;&nbsp;{{ sessionSource }}</span>
                                <span class="fb-metric">FREQ {{ freqMinDisplay }}–{{ freqMaxDisplay }} GHz</span>
                                <span class="fb-metric">LOAD {{ loadMinDisplay }}–{{ loadMaxDisplay }} KB</span>
                            </div>
                        </div>

                        <div class="fb-inspect-divider">
                            <div class="fb-divider-line" />
                            <span class="fb-divider-label">VS</span>
                            <div class="fb-divider-line" />
                        </div>

                        <!-- Captured signal waveform -->
                        <div class="fb-inspect-wave">
                            <div class="fb-inspect-wave-label fb-label--sig">CAPTURED: {{ inspectedSignal.id }}</div>
                            <svg class="fb-inspect-svg" viewBox="0 0 240 44" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                                <path :d="refWavePath"  class="fb-inspect-path fb-path--ref-ghost" />
                                <path :d="sigWavePath"  class="fb-inspect-path fb-path--sig" />
                            </svg>
                            <div class="fb-inspect-metrics">
                                <span class="fb-metric" :class="sourceDeviation ? 'fb-metric--dev' : ''">
                                    SRC&nbsp;&nbsp;{{ inspectedSignal.source }}{{ sourceDeviation ? ' ⚠' : '' }}
                                </span>
                                <span class="fb-metric" :class="freqDeviation ? 'fb-metric--dev' : ''">
                                    FREQ {{ inspectedSignal.freq }} GHz{{ freqDeviation ? ' ⚠' : '' }}
                                </span>
                                <span class="fb-metric" :class="loadDeviation ? 'fb-metric--dev' : ''">
                                    LOAD {{ inspectedSignal.load }} KB{{ loadDeviation ? ' ⚠' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </Transition>

            <!-- ── AUDIT SECTION ────────────────────────────────────────────── -->
            <div class="fb-audit-section">

                <div class="fb-audit-main">

                    <div class="fb-reference">
                        <span class="fb-ref-tag">REFERENCE BASELINE</span>
                        <span class="fb-ref-val">SRC: {{ sessionSource }} · FREQ: {{ freqMinDisplay }}–{{ freqMaxDisplay }} GHz · LOAD: {{ loadMinDisplay }}–{{ loadMaxDisplay }} KB</span>
                    </div>

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
                                    :class="{
                                        'fb-row--selected':  selectedIds.includes(signal.id),
                                        'fb-row--inspected': inspectedSignal?.id === signal.id,
                                    }"
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

                    <div class="fb-actions">
                        <button
                            class="fb-action-btn fb-btn--flush"
                            :disabled="selectedIds.length === 0 || !!result"
                            @click="onFlush"
                        >[ FLUSH ] <span class="fb-hotkey">SPACE</span></button>
                        <button
                            class="fb-action-btn fb-btn--clear"
                            :disabled="selectedIds.length === 0 || !!result"
                            @click="onClear"
                        >[ CLEAR ]</button>
                    </div>

                </div>

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

            <!-- CRT overlay — scanlines + vignette, pointer-events: none -->
            <div class="fb-crt-overlay" aria-hidden="true" />

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

const BLOCKS     = 10;
const BLOCK_W    = 96;
const SVG_W      = 960;
const MAX_BUFFER = 8;
const BASE_AMP   = 14;
const SPIKE_AMP  = 24;

// ── Session baseline — randomised fresh each game ─────────────────────────────
// The player is shown these values in the reference panel and must compare
// all captured signals against them. Nothing is hardcoded.

const FREQ_POOL   = [1.20, 2.42, 3.60, 4.80, 7.20, 9.60];
const LOAD_POOL   = [64, 128, 256, 512];
const SRC_POOL    = ['[INTERNAL]', 'SYS.CORE', 'INT.NODE.A', 'NET.CORE.01', 'SYS.BUS.A'];

function pickRand(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

// Reactive session baseline — regenerated after each successful anomalous flush
const sessionFreq   = ref(pickRand(FREQ_POOL));
const sessionLoad   = ref(pickRand(LOAD_POOL));
const sessionSource = ref(pickRand(SRC_POOL));

// Tolerance window around baseline — what counts as "valid"
const FREQ_TOL = 0.05;      // ±0.05 GHz
const LOAD_TOL = 0.12;      // ±12%

// Display values derived from the reactive baseline — update automatically on shift
const freqMinDisplay = computed(() => (sessionFreq.value - FREQ_TOL).toFixed(2));
const freqMaxDisplay = computed(() => (sessionFreq.value + FREQ_TOL).toFixed(2));
const loadMinDisplay = computed(() => Math.floor(sessionLoad.value * (1 - LOAD_TOL)));
const loadMaxDisplay = computed(() => Math.floor(sessionLoad.value * (1 + LOAD_TOL)));

// Source pools for anomalous signals by difficulty
const ANOM_SRC_OBVIOUS = ['[EXTERNAL]', '[UNKNOWN]', 'EXT.NODE.B', 'UNK.RELAY', '[UNREGISTERED]'];
const ANOM_SRC_SUBTLE  = ['[INT-RELAY]', 'INT.NODE.B', 'SYS.RELAY', 'NET.RELAY.02'];

// ── Difficulty config ─────────────────────────────────────────────────────────

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

// ── Scan toggle ───────────────────────────────────────────────────────────────

const isScanning = ref(true);

function toggleScan() {
    if (result.value) return;
    isScanning.value = !isScanning.value;
    addLog(isScanning.value ? '> SCAN_RESUME: live feed active' : '> SCAN_PAUSE: feed suspended');
}

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

// ── Comparison waveform paths ─────────────────────────────────────────────────
// Reference baseline is static — precomputed once.
// Signal wave is computed from the inspected signal's freq + load values.

const CMP_W = 240;
const CMP_H = 44;

function buildStaticWave(cycleCount, amplitude) {
    const cy    = CMP_H / 2;
    const steps = 80;
    let d = '';
    for (let i = 0; i <= steps; i++) {
        const x = (i / steps) * CMP_W;
        const t = (i / steps) * Math.PI * 2 * cycleCount;
        const y = cy + amplitude * Math.sin(t);
        d += i === 0 ? `M ${x.toFixed(1)} ${y.toFixed(2)}` : ` L ${x.toFixed(1)} ${y.toFixed(2)}`;
    }
    return d;
}

// 2 cycles, amplitude 10 — clean baseline
const refWavePath = buildStaticWave(2, 10);

// Audit grid state ─────────────────────────────────────────────────────────────

const capturedSignals  = ref([]);
const selectedIds      = ref([]);
const inspectedSignal  = ref(null);
const anomalousFlushed = ref(0);
const isBufferFull     = computed(() => capturedSignals.value.length >= MAX_BUFFER);
const emptySlots       = computed(() => Math.max(0, MAX_BUFFER - capturedSignals.value.length));

// Comparison waveform — cycles and amplitude normalised against session baseline
const sigWavePath = computed(() => {
    if (!inspectedSignal.value) return '';
    const sig    = inspectedSignal.value;
    const cycles = (parseFloat(sig.freq) / sessionFreq.value) * 2;
    const amp    = Math.min(20, (sig.load / sessionLoad.value) * 10);
    return buildStaticWave(cycles, amp);
});

// Deviation indicators — compare against current session baseline
const freqDeviation   = computed(() => {
    if (!inspectedSignal.value) return false;
    return Math.abs(parseFloat(inspectedSignal.value.freq) - sessionFreq.value) > FREQ_TOL;
});
const loadDeviation   = computed(() => {
    if (!inspectedSignal.value) return false;
    return inspectedSignal.value.load < loadMinDisplay.value || inspectedSignal.value.load > loadMaxDisplay.value;
});
const sourceDeviation = computed(() => {
    if (!inspectedSignal.value) return false;
    return inspectedSignal.value.source !== sessionSource.value;
});
const anyDeviation = computed(() => freqDeviation.value || loadDeviation.value || sourceDeviation.value);

// ── Signal generation — all values relative to session baseline ───────────────

let signalCounter = 0;

function sign() { return Math.random() < 0.5 ? 1 : -1; }

function generateSignal() {
    const id          = `SIG_${String(++signalCounter).padStart(3, '0')}`;
    const isAnomalous = Math.random() < config.anomalyChance;

    if (!isAnomalous) {
        return {
            id,
            isAnomalous: false,
            source: sessionSource.value,
            freq:   (sessionFreq.value + (Math.random() * 2 - 1) * FREQ_TOL).toFixed(2),
            load:   Math.floor(sessionLoad.value * (1 - LOAD_TOL + Math.random() * 2 * LOAD_TOL)),
        };
    }

    switch (config.anomalyProfile) {

        case 'obvious':
            return {
                id,
                isAnomalous: true,
                source: pickRand(ANOM_SRC_OBVIOUS),
                freq:   (sessionFreq.value + sign() * (1.5 + Math.random() * 2.5)).toFixed(2),
                load:   Math.floor(sessionLoad.value * (3.0 + Math.random() * 5.0)),
            };

        case 'subtle':
            return {
                id,
                isAnomalous: true,
                source: Math.random() < 0.5 ? sessionSource.value : pickRand(ANOM_SRC_SUBTLE),
                freq:   (sessionFreq.value + sign() * (0.15 + Math.random() * 0.25)).toFixed(2),
                load:   Math.floor(sessionLoad.value * (1.30 + Math.random() * 0.70)),
            };

        case 'obscure':
        default:
            return {
                id,
                isAnomalous: true,
                source: sessionSource.value,
                freq:   (sessionFreq.value + sign() * (0.03 + Math.random() * 0.05)).toFixed(2),
                load:   Math.floor(sessionLoad.value * (1.04 + Math.random() * 0.10)),
            };
    }
}

// ── Terminal log ──────────────────────────────────────────────────────────────

const terminalLog = ref([]);
const logEl       = ref(null);
const MAX_LOG     = 16;

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

    // Toggle flag
    const idx = selectedIds.value.indexOf(id);
    if (idx >= 0) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);

    // Always open inspect panel for the clicked signal
    const signal = capturedSignals.value.find(s => s.id === id);
    if (signal) {
        inspectedSignal.value = signal;
        addLog(`> INSPECT: ${id}`);
    }
}

function clearInspect() {
    inspectedSignal.value = null;
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

    // Clear inspect if flushed signal was being inspected
    if (inspectedSignal.value && selectedIds.value.includes(inspectedSignal.value.id)) {
        inspectedSignal.value = null;
    }

    capturedSignals.value = capturedSignals.value.filter(s => !selectedIds.value.includes(s.id));
    selectedIds.value = [];

    if (validCount > 0) {
        applyHit(config.validFlushHit * validCount);
        primaryProgress.value = Math.min(1, primaryProgress.value + config.traceFlushBump * validCount);
        addLog(`> ERROR: ${validCount} VALID signal(s) flushed — TRACE UPDATED`, 'error');
    }

    if (anomCount > 0) {
        anomalousFlushed.value += anomCount;
        addLog(`> FLUSH_COMPLETE: ${anomCount} anomalous signal(s) purged [${anomalousFlushed.value}/${locksRequired}]`);

        if (anomalousFlushed.value >= locksRequired) {
            addLog('> BUFFER CLEAN — all ghost signals eliminated');
            endGame('success');
            setTimeout(() => emit('complete'), 2200);
        } else {
            // More signals needed — roll a fresh baseline for the next round
            generateNewBaseline();
        }
    }
}

function generateNewBaseline() {
    // Avoid repeating the same frequency back-to-back
    let newFreq;
    do { newFreq = pickRand(FREQ_POOL); } while (newFreq === sessionFreq.value && FREQ_POOL.length > 1);
    sessionFreq.value   = newFreq;
    sessionLoad.value   = pickRand(LOAD_POOL);
    sessionSource.value = pickRand(SRC_POOL);

    // Clear the buffer — captured signals belong to the previous baseline
    capturedSignals.value = [];
    selectedIds.value     = [];
    inspectedSignal.value = null;

    addLog('> BASELINE_SHIFT: reference parameters updated');
    addLog(`> NEW_REF: SRC=${sessionSource.value} FREQ=${freqMinDisplay.value}–${freqMaxDisplay.value} GHz LOAD=${loadMinDisplay.value}–${loadMaxDisplay.value} KB`);
}

function onClear() {
    selectedIds.value = [];
    addLog('> SELECTION CLEARED');
}

function onKeyDown(e) {
    if (e.code === 'Space') {
        e.preventDefault();
        onFlush();
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

    // Trace ticks at full speed when scanning, 50% when paused (cost of analysis time)
    const traceDt = isScanning.value ? dt : dt * 0.5;
    const failCause = tickShared(traceDt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Waveform and spike logic only when scanning
    if (isScanning.value) {
        scrollT -= dt * config.scrollSpeed;

        for (let ri = 0; ri < rows.value.length; ri++) {
            const row = rows.value[ri];

            for (let bi = 0; bi < BLOCKS; bi++) {
                const block = row.blocks[bi];
                if (!block.active) continue;
                block.windowLeft -= dt;
                if (block.windowLeft <= 0) {
                    block.active     = false;
                    block.windowLeft = 0;
                }
            }

            row.spawnTimer -= dt;
            if (row.spawnTimer <= 0) {
                row.spawnTimer = randSpawn();
                const hasActive = row.blocks.some(b => b.active);
                if (!hasActive && !result.value) {
                    const bi = Math.floor(Math.random() * BLOCKS);
                    row.blocks[bi].active     = true;
                    row.blocks[bi].windowMax  = config.windowDuration;
                    row.blocks[bi].windowLeft = config.windowDuration;
                }
            }

            row.path = buildPath(ri);
        }
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

    window.addEventListener('keydown', onKeyDown);
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
/*
 * Color palette
 * L1  Bright Cyan  #00e5ff                — key titles, inspect header, CTAs
 * L2  Bright Green #00ff9d                — live data, status labels
 * L3  Dim Green    rgba(0,160,70,0.38)    — de-emphasized (ref baseline, headers)
 * AL  Orange       #ff6600 / #ff9933      — anomaly, spike, flush, deviation
 */

/* ── Root wrapper ─────────────────────────────────────────────────────────── */

.fb-wrap {
    position: relative;
    overflow: hidden;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    box-sizing: border-box;
    background: rgba(1,8,3,0.50);
}

/* ── CRT overlay ─────────────────────────────────────────────────────────── */

.fb-crt-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 50;
    background: repeating-linear-gradient(
        to bottom,
        transparent       0px,
        transparent       2px,
        rgba(0,0,0,0.055) 2px,
        rgba(0,0,0,0.055) 4px
    );
    box-shadow:
        inset 0 0  60px 20px rgba(0,0,0,0.55),
        inset 0 0 120px 40px rgba(0,0,0,0.28);
}

/* ── CAPTURE SECTION ──────────────────────────────────────────────────────── */

.fb-capture-section {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,200,80,0.14);
    padding: 0 0 6px;
}

.fb-status-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 14px;
    background: rgba(0,255,100,0.025);
    border-bottom: 1px solid rgba(0,200,80,0.09);
    flex-shrink: 0;
}

/* Scan toggle button */
.fb-scan-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.18em;
    background: transparent;
    padding: 3px 10px;
    cursor: pointer;
    transition: all 0.12s;
    flex-shrink: 0;
}

.fb-scan--active {
    border: 1px solid rgba(0,229,255,0.45);
    color: #00e5ff;
    text-shadow: 0 0 6px rgba(0,229,255,0.5);
    box-shadow: 0 0 6px rgba(0,229,255,0.1);
}

.fb-scan--paused {
    border: 1px solid rgba(255,102,0,0.45);
    color: #ff9933;
    text-shadow: 0 0 6px rgba(255,153,51,0.45);
    animation: fb-scan-blink 1.2s ease infinite;
}

@keyframes fb-scan-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.55; }
}

.fb-status-item {
    font-size: 8px;
    color: #00ff9d;
    letter-spacing: 0.13em;
    text-shadow: 0 0 6px rgba(0,255,157,0.4);
}

.fb-status-item--mode {
    margin-left: auto;
    color: #00e5ff;
    text-shadow: 0 0 8px rgba(0,229,255,0.55), 0 0 18px rgba(0,229,255,0.2);
}

.fb-status--warn {
    color: #ff6600 !important;
    text-shadow: 0 0 8px rgba(255,102,0,0.6) !important;
}

.fb-status-sep {
    color: rgba(0,180,80,0.2);
    font-size: 8px;
}

.fb-waveforms {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 7px 14px 0;
}

.fb-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.fb-row-label {
    font-size: 9px;
    color: rgba(0,180,70,0.35);
    letter-spacing: 0.14em;
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
    background: rgba(0,8,3,0.6);
    border: 1px solid rgba(0,180,70,0.10);
}

.fb-wave-path {
    fill: none;
    stroke: #00ff9d;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter:
        drop-shadow(0 0 1.5px rgba(0,255,157,0.9))
        drop-shadow(0 0 4px   rgba(0,255,157,0.4))
        drop-shadow(0 0 10px  rgba(0,255,157,0.15));
}

.fb-block-div {
    stroke: rgba(0,200,80,0.06);
    stroke-width: 1;
}

.fb-block-rect {
    fill: transparent;
    stroke: none;
    cursor: default;
}

.fb-block--spike {
    fill: rgba(255,102,0,0.10);
    stroke: rgba(255,102,0,0.55);
    stroke-width: 1;
    cursor: pointer;
    animation: fb-spike-pulse 0.38s ease infinite alternate;
    filter: drop-shadow(0 0 4px rgba(255,102,0,0.35));
}

.fb-block--lock {
    fill: rgba(0,255,157,0.10);
    stroke: rgba(0,255,157,0.45);
    stroke-width: 1;
    filter: drop-shadow(0 0 3px rgba(0,255,157,0.3));
}

.fb-block--full {
    fill: rgba(255,102,0,0.025);
    cursor: not-allowed;
}

.fb-window-track {
    height: 2px;
    background: rgba(255,102,0,0.05);
    overflow: hidden;
    margin-top: 1px;
}

.fb-window-fill {
    height: 100%;
    background: #ff6600;
    box-shadow: 0 0 5px rgba(255,102,0,0.6);
    transition: width 0.05s linear;
}

/* ── SIGNAL INSPECT PANEL ─────────────────────────────────────────────────── */

.fb-inspect-panel {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,229,255,0.14);
    background: rgba(0,12,8,0.70);
    overflow: hidden;
}

.fb-inspect-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 5px 14px;
    background: rgba(0,229,255,0.03);
    border-bottom: 1px solid rgba(0,229,255,0.10);
    flex-shrink: 0;
}

.fb-inspect-id {
    font-size: 8px;
    color: #00e5ff;
    letter-spacing: 0.18em;
    text-shadow: 0 0 7px rgba(0,229,255,0.55);
}

.fb-inspect-verdict {
    font-size: 8px;
    letter-spacing: 0.12em;
}

.fb-verdict--warn  {
    color: #ff9933;
    text-shadow: 0 0 6px rgba(255,153,51,0.45);
}

.fb-verdict--clean {
    color: rgba(0,200,80,0.50);
}

.fb-inspect-close {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.14em;
    background: transparent;
    border: 1px solid rgba(0,200,80,0.16);
    color: rgba(0,200,80,0.38);
    padding: 2px 8px;
    cursor: pointer;
    transition: all 0.1s;
}

.fb-inspect-close:hover {
    border-color: rgba(0,200,80,0.4);
    color: #00ff9d;
}

.fb-inspect-body {
    display: flex;
    align-items: stretch;
    padding: 8px 14px;
    gap: 0;
}

/* Each waveform side */
.fb-inspect-wave {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.fb-inspect-wave-label {
    font-size: 7px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.fb-label--ref { color: rgba(0,160,70,0.40); }
.fb-label--sig { color: #00e5ff; text-shadow: 0 0 5px rgba(0,229,255,0.4); }

.fb-inspect-svg {
    width: 100%;
    height: 44px;
    display: block;
    background: rgba(0,6,3,0.70);
    border: 1px solid rgba(0,180,70,0.10);
}

/* Reference baseline wave — dim green */
.fb-path--ref {
    fill: none;
    stroke: rgba(0,200,80,0.30);
    stroke-width: 1.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* Ghost of ref overlaid on signal panel — very dim, for visual comparison */
.fb-path--ref-ghost {
    fill: none;
    stroke: rgba(0,200,80,0.18);
    stroke-width: 1;
    stroke-dasharray: 4 3;
    stroke-linecap: round;
}

/* Captured signal wave — cyan/orange depending on deviation */
.fb-path--sig {
    fill: none;
    stroke: #00e5ff;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter:
        drop-shadow(0 0 1.5px rgba(0,229,255,0.8))
        drop-shadow(0 0 5px   rgba(0,229,255,0.3));
}

/* Metrics row under each wave */
.fb-inspect-metrics {
    display: flex;
    gap: 14px;
}

.fb-metric {
    font-size: 8px;
    color: rgba(0,160,70,0.40);   /* L3 default */
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.fb-metric--dev {
    color: #ff9933;
    text-shadow: 0 0 5px rgba(255,153,51,0.40);
}

/* Divider between ref and signal */
.fb-inspect-divider {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 0 14px;
    flex-shrink: 0;
}

.fb-divider-line {
    flex: 1;
    width: 1px;
    background: rgba(0,180,70,0.12);
}

.fb-divider-label {
    font-size: 7px;
    color: rgba(0,160,70,0.28);
    letter-spacing: 0.16em;
}

/* Inspect panel transition */
.fb-inspect-enter-active,
.fb-inspect-leave-active {
    transition: max-height 0.18s ease, opacity 0.18s ease;
    max-height: 160px;
    overflow: hidden;
}
.fb-inspect-enter-from,
.fb-inspect-leave-to {
    max-height: 0;
    opacity: 0;
}

/* ── AUDIT SECTION ────────────────────────────────────────────────────────── */

.fb-audit-section {
    flex: 1;
    display: flex;
    min-height: 0;
}

.fb-audit-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 8px 14px;
    border-right: 1px solid rgba(0,180,70,0.10);
}

.fb-reference {
    display: flex;
    align-items: baseline;
    gap: 12px;
    padding: 5px 10px;
    background: rgba(0,100,40,0.05);
    border: 1px solid rgba(0,160,60,0.10);
    margin-bottom: 6px;
    flex-shrink: 0;
}

.fb-ref-tag {
    font-size: 7px;
    color: rgba(0,160,70,0.38);
    letter-spacing: 0.20em;
    flex-shrink: 0;
}

.fb-ref-val {
    font-size: 8px;
    color: rgba(0,160,70,0.38);
    letter-spacing: 0.05em;
}

.fb-grid-wrap {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    border: 1px solid rgba(0,180,70,0.12);
}

.fb-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10px;
    letter-spacing: 0.05em;
}

.fb-grid th:nth-child(1), .fb-grid td:nth-child(1) { width: 82px;  }
.fb-grid th:nth-child(2), .fb-grid td:nth-child(2) { width: 120px; }
.fb-grid th:nth-child(3), .fb-grid td:nth-child(3) { width: 90px;  }
.fb-grid th:nth-child(4), .fb-grid td:nth-child(4) { width: 74px;  }
.fb-grid th:nth-child(5), .fb-grid td:nth-child(5) { width: auto;  }

.fb-grid thead tr {
    background: rgba(0,200,80,0.035);
    border-bottom: 1px solid rgba(0,180,70,0.15);
}

.fb-grid th {
    text-align: left;
    padding: 6px 10px;
    font-size: 8px;
    color: rgba(0,160,70,0.40);
    letter-spacing: 0.18em;
    font-weight: normal;
    white-space: nowrap;
    overflow: hidden;
}

.fb-grid-row {
    border-bottom: 1px solid rgba(0,180,70,0.07);
    cursor: pointer;
    transition: background 0.08s;
}

.fb-grid-row:hover:not(.fb-grid-row--empty) {
    background: rgba(0,255,100,0.03);
}

.fb-grid-row--empty {
    cursor: default;
    border-bottom: 1px solid rgba(0,180,70,0.05);
}

.fb-grid td {
    padding: 6px 10px;
    color: #00ff9d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0 4px rgba(0,255,157,0.22);
}

.fb-grid-row--empty td {
    color: rgba(0,140,60,0.18);
    font-size: 8px;
    text-shadow: none;
}

/* Selected (flagged) */
.fb-row--selected {
    background: rgba(255,102,0,0.07) !important;
    border-left: 2px solid rgba(255,102,0,0.65);
}

.fb-row--selected td {
    color: #ff9933;
    text-shadow: 0 0 6px rgba(255,153,51,0.40);
}

/* Inspected (waveform shown in panel above) */
.fb-row--inspected {
    background: rgba(0,229,255,0.04) !important;
    border-left: 2px solid rgba(0,229,255,0.40);
}

.fb-row--inspected td {
    color: #00e5ff;
    text-shadow: 0 0 5px rgba(0,229,255,0.30);
}

/* Selected + inspected combined */
.fb-row--selected.fb-row--inspected {
    background: rgba(255,102,0,0.09) !important;
    border-left: 2px solid #ff6600;
}

.fb-row--selected.fb-row--inspected td {
    color: #ff9933;
    text-shadow: 0 0 6px rgba(255,153,51,0.45);
}

.fb-status-col {
    font-size: 9px;
    letter-spacing: 0.12em;
}

/* Action buttons */

.fb-actions {
    display: flex;
    gap: 10px;
    padding-top: 8px;
    flex-shrink: 0;
    border-top: 1px solid rgba(0,180,70,0.08);
    margin-top: 6px;
    align-items: center;
}

.fb-action-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.20em;
    background: transparent;
    padding: 7px 22px;
    cursor: pointer;
    transition: all 0.1s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.fb-action-btn:disabled {
    opacity: 0.22;
    cursor: not-allowed;
}

.fb-hotkey {
    font-size: 7px;
    letter-spacing: 0.14em;
    color: rgba(255,153,51,0.45);
    border: 1px solid rgba(255,102,0,0.20);
    padding: 1px 5px;
    line-height: 1.4;
}

.fb-btn--flush {
    border: 1px solid rgba(255,102,0,0.45);
    color: rgba(255,120,0,0.80);
}

.fb-btn--flush:hover:not(:disabled) {
    background: rgba(255,102,0,0.07);
    border-color: #ff6600;
    color: #ff9933;
    box-shadow: 0 0 8px rgba(255,102,0,0.22);
}

.fb-btn--clear {
    border: 1px solid rgba(0,200,80,0.18);
    color: rgba(0,200,80,0.40);
}

.fb-btn--clear:hover:not(:disabled) {
    background: rgba(0,255,100,0.03);
    border-color: rgba(0,200,80,0.45);
    color: #00ff9d;
}

/* ── TERMINAL LOG ─────────────────────────────────────────────────────────── */

.fb-terminal-log {
    width: 220px;
    flex-shrink: 0;
    background: rgba(0,4,2,0.55);
    border-left: 1px solid rgba(0,180,70,0.10);
    padding: 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.fb-log-header {
    font-size: 7px;
    color: #00e5ff;
    letter-spacing: 0.22em;
    padding: 7px 10px 6px;
    border-bottom: 1px solid rgba(0,180,70,0.10);
    flex-shrink: 0;
    background: rgba(0,229,255,0.025);
    text-shadow: 0 0 7px rgba(0,229,255,0.55), 0 0 16px rgba(0,229,255,0.2);
}

.fb-log-line {
    font-size: 8px;
    color: rgba(0,200,80,0.50);
    letter-spacing: 0.04em;
    line-height: 1.55;
    word-break: break-all;
    padding: 2px 10px;
}

.fb-log--warn  {
    color: rgba(255,153,0,0.75);
    text-shadow: 0 0 5px rgba(255,153,0,0.30);
}
.fb-log--error {
    color: rgba(255,70,70,0.85);
    text-shadow: 0 0 6px rgba(255,70,70,0.35);
}

/* ── Animations ───────────────────────────────────────────────────────────── */

@keyframes fb-spike-pulse {
    from { fill: rgba(255,102,0,0.05); stroke: rgba(255,102,0,0.30); }
    to   { fill: rgba(255,102,0,0.16); stroke: rgba(255,102,0,0.80); }
}
</style>
