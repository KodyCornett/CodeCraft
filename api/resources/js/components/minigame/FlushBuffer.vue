<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- ── CAPTURE SECTION ──────────────────────────────────────────── -->
            <div class="fb-capture-section">

                <div class="fb-status-bar">
                    <span class="fb-status-chip" :class="isScanning ? 'fb-chip--live' : 'fb-chip--dead'">
                        {{ isScanning ? '[■ LIVE_FEED: ACTIVE ]' : '[▶ LIVE_FEED: DE-ACTIVATED ]' }}
                    </span>
                    <span class="fb-status-sep">//</span>
                    <span class="fb-status-item">ACTIVE_STREAM: MONITOR_ONLY</span>
                    <span class="fb-status-sep">//</span>
                    <span class="fb-status-item" :class="isBufferFull ? 'fb-status--warn' : ''">BUFFER_STATUS: {{ capturedSignals.length }}/{{ MAX_BUFFER }}</span>
                    <span class="fb-status-sep">//</span>
                    <span class="fb-status-item">SIGNAL_LOCKED: {{ anomalousFlushed }}/{{ locksRequired }}</span>
                    <div class="fb-scan-dot" :class="isScanning ? 'fb-dot--live' : 'fb-dot--dead'" />
                </div>

                <div class="fb-waveforms">
                    <div v-for="(row, ri) in rows" :key="ri" class="fb-row">
                        <span class="fb-row-label">W{{ ri + 1 }}</span>
                        <div class="fb-wave-area">
                            <svg
                                class="fb-svg"
                                viewBox="0 0 960 80"
                                preserveAspectRatio="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <!-- Horizontal grid lines -->
                                <line x1="0" y1="20" x2="960" y2="20" class="fb-grid-h" />
                                <line x1="0" y1="40" x2="960" y2="40" class="fb-grid-center" />
                                <line x1="0" y1="60" x2="960" y2="60" class="fb-grid-h" />
                                <!-- Vertical block dividers -->
                                <line
                                    v-for="b in BLOCKS - 1" :key="`d${b}`"
                                    :x1="b * BLOCK_W" y1="0"
                                    :x2="b * BLOCK_W" y2="80"
                                    class="fb-block-div"
                                />
                                <!-- Wave path rendered above grid -->
                                <path :d="row.path" class="fb-wave-path" />
                                <!-- Interaction rects -->
                                <rect
                                    v-for="b in BLOCKS" :key="`b${b}`"
                                    :x="(b - 1) * BLOCK_W + 1" y="1"
                                    :width="BLOCK_W - 2" height="78"
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

                    <div class="fb-audit-banner">
                        <span class="fb-audit-title">FORENSIC_AUDIT</span>
                        <span class="fb-audit-sep">//</span>
                        <span class="fb-audit-meta">SYSTEM_DIAGNOSTIC_MODE: AUDIT</span>
                        <span class="fb-audit-sep">//</span>
                        <span class="fb-audit-meta" :class="isBufferFull ? 'fb-status--warn' : ''">BUFFER: {{ capturedSignals.length }}/{{ MAX_BUFFER }}</span>
                        <span class="fb-audit-sep">//</span>
                        <span class="fb-audit-meta">SIGNAL_LOCKED: {{ anomalousFlushed }}/{{ locksRequired }}</span>
                    </div>

                    <div class="fb-reference">
                        <span class="fb-ref-tag">REFERENCE_CAPTURE:</span>
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
                                        {{ selectedIds.includes(signal.id) ? '[!] SIGNAL_FLAGGED' : '[--] PENDING' }}
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
                            class="fb-action-btn fb-btn--scan"
                            :class="isScanning ? 'fb-scan--active' : 'fb-scan--paused'"
                            :disabled="!!result"
                            @click="toggleScan"
                        >{{ isScanning ? '[ ■ STOP CAPTURE ]' : '[ ▶ RESUME CAPTURE ]' }}</button>
                        <button
                            class="fb-action-btn fb-btn--flush"
                            :disabled="selectedIds.length === 0 || !!result || isScanning"
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
                    <div class="fb-log-header">TRANSAXIAL_LOG</div>
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
const SVG_H      = 80;
const MAX_BUFFER = 8;
const BASE_AMP   = 26;
const SPIKE_AMP  = 40;
// Per-row amplitude multiplier — gives each channel a distinct visual weight
const ROW_AMP_MULT = [1.0, 0.80, 0.91];

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
        const mult = ROW_AMP_MULT[ri] ?? 1.0;
        const amp  = (row.blocks[b].active ? SPIKE_AMP : BASE_AMP) * mult;
        for (let s = 0; s <= STEPS_PER_BLOCK; s++) {
            const x = (b + s / STEPS_PER_BLOCK) * BLOCK_W;
            const t = scrollT + (x / SVG_W) * Math.PI * 2 * config.freq;
            const y = (SVG_H / 2) + amp * Math.sin(t);
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

// Deviation indicators
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
const anyDeviation    = computed(() => freqDeviation.value || loadDeviation.value || sourceDeviation.value);

// ── Terminal log ──────────────────────────────────────────────────────────────

const terminalLog = ref([]);
const logEl       = ref(null);
let   logCounter  = 0;

function addLog(text, type = 'info') {
    terminalLog.value.push({ text, type });
    if (terminalLog.value.length > 80) terminalLog.value.shift();
    nextTick(() => {
        if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight;
    });
}

// ── Baseline regeneration ─────────────────────────────────────────────────────

function generateNewBaseline() {
    let newFreq;
    do { newFreq = pickRand(FREQ_POOL); } while (newFreq === sessionFreq.value && FREQ_POOL.length > 1);
    sessionFreq.value   = newFreq;
    sessionLoad.value   = pickRand(LOAD_POOL);
    sessionSource.value = pickRand(SRC_POOL);
    capturedSignals.value = [];
    selectedIds.value     = [];
    inspectedSignal.value = null;
    addLog('> BASELINE_SHIFT: reference parameters updated');
    addLog(`> NEW_REF: SRC=${sessionSource.value} FREQ=${freqMinDisplay.value}–${freqMaxDisplay.value} GHz LOAD=${loadMinDisplay.value}–${loadMaxDisplay.value} KB`);
}

// ── Signal generation ─────────────────────────────────────────────────────────

let sigIdCounter = 0;

function generateSignal(anomaly) {
    sigIdCounter++;
    const id = `PKT-${String(sigIdCounter).padStart(4, '0')}`;
    const profile = config.anomalyProfile;
    let freq, load, source;

    if (!anomaly) {
        // Clean signal — within tolerance
        const freqJitter = (Math.random() * 2 - 1) * FREQ_TOL * 0.8;
        freq   = (sessionFreq.value + freqJitter).toFixed(2);
        load   = Math.floor(sessionLoad.value * (1 + (Math.random() * 2 - 1) * LOAD_TOL * 0.8));
        source = sessionSource.value;
    } else {
        // Anomalous — at least one deviation
        const type = Math.floor(Math.random() * 3);
        if (profile === 'obvious') {
            source = pickRand(ANOM_SRC_OBVIOUS);
            freq   = (sessionFreq.value * (1.4 + Math.random() * 0.6)).toFixed(2);
            load   = Math.floor(sessionLoad.value * (1.5 + Math.random() * 0.5));
        } else if (profile === 'subtle') {
            source = pickRand(ANOM_SRC_SUBTLE);
            freq   = (sessionFreq.value + FREQ_TOL * (1.3 + Math.random() * 0.5)).toFixed(2);
            load   = Math.floor(sessionLoad.value * (1 + LOAD_TOL * (1.3 + Math.random() * 0.4)));
        } else {
            // obscure — source may match session, freq barely off
            source = Math.random() > 0.5 ? sessionSource.value : pickRand(ANOM_SRC_SUBTLE);
            freq   = (sessionFreq.value + FREQ_TOL * (1.05 + Math.random() * 0.3)).toFixed(2);
            load   = Math.floor(sessionLoad.value * (1 + LOAD_TOL * (1.05 + Math.random() * 0.2)));
        }
    }

    return { id, source, freq: parseFloat(freq).toFixed(2), load, anomaly };
}

// ── Interaction handlers ──────────────────────────────────────────────────────

function onRowClick(signalId) {
    const sig = capturedSignals.value.find(s => s.id === signalId);
    if (!sig) return;

    // Toggle selection for flush
    const idx = selectedIds.value.indexOf(signalId);
    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(signalId);
    }

    // Set inspect to this signal (always show waveform on click)
    inspectedSignal.value = (inspectedSignal.value?.id === signalId && idx >= 0) ? null : sig;
}

function onBlockClick(ri, bi) {
    if (result.value || isBufferFull.value) return;
    const block = rows.value[ri].blocks[bi];
    if (!block.active) return;

    // Capture the signal
    const isAnom = Math.random() < config.anomalyChance;
    const signal  = generateSignal(isAnom);
    capturedSignals.value.push(signal);

    // Mark as locking, clear active
    block.locking      = true;
    block.active       = false;
    block.windowLeft   = 0;

    addLog(`> CAPTURE: ${signal.id} SRC=${signal.source} FREQ=${signal.freq} LOAD=${signal.load}`);
}

function clearInspect() {
    inspectedSignal.value = null;
}

function onFlush() {
    if (selectedIds.value.length === 0 || result.value || isScanning.value) return;

    const anomCount  = selectedIds.value.filter(id =>
        capturedSignals.value.find(s => s.id === id)?.anomaly
    ).length;
    const cleanCount = selectedIds.value.length - anomCount;

    if (cleanCount > 0) {
        applyHit(config.validFlushHit);
        addLog(`> FLUSH_ERROR: ${cleanCount} clean signal(s) incorrectly flagged — stability hit`, 'warn');
    }

    // Remove flushed signals from buffer
    capturedSignals.value = capturedSignals.value.filter(s => !selectedIds.value.includes(s.id));
    const flushedIds = [...selectedIds.value];
    selectedIds.value = [];
    if (inspectedSignal.value && flushedIds.includes(inspectedSignal.value.id)) {
        inspectedSignal.value = null;
    }

    if (anomCount > 0) {
        anomalousFlushed.value += anomCount;
        addLog(`> FLUSH_COMPLETE: ${anomCount} anomalous signal(s) purged [${anomalousFlushed.value}/${locksRequired}]`);
        if (anomalousFlushed.value >= locksRequired) {
            addLog('> BUFFER CLEAN — all ghost signals eliminated');
            endGame('success');
            setTimeout(() => emit('complete'), 2200);
            return;
        }
        generateNewBaseline();
    }

    // Auto-resume feed
    isScanning.value = true;
    addLog('> FEED_RESUME: live data stream restarted');
}

function onClear() {
    selectedIds.value = [];
}

// ── Space key ─────────────────────────────────────────────────────────────────

function onKeyDown(e) {
    if (e.code === 'Space') {
        e.preventDefault();
        if (!isScanning.value) onFlush();
    }
}

// ── Main game loop ────────────────────────────────────────────────────────────

let animFrame = null;
let lastTime  = null;

function tick(ts) {
    animFrame = requestAnimationFrame(tick);
    if (!lastTime) { lastTime = ts; return; }
    const dt = Math.min((ts - lastTime) / 1000, 0.05);
    lastTime  = ts;

    if (result.value) return;

    // Shared tick — slower when feed paused.
    // FlushBuffer only fails on stability collapse; the trace/timer bar
    // is atmosphere only and does NOT end this game.
    const traceDt = isScanning.value ? dt : dt * 0.5;
    const failCause = tickShared(traceDt);
    if (failCause === 'stability') {
        addLog('> SYSTEM_FAILURE: stability collapse — ICE locked the channel', 'error');
        endGame('fail', 'STABILITY COLLAPSE');
        setTimeout(() => emit('fail'), 1800);
        return;
    }

    if (!isScanning.value) return;

    scrollT -= dt * config.scrollSpeed;

    for (let ri = 0; ri < rows.value.length; ri++) {
        const row = rows.value[ri];

        // Tick spawn timer
        row.spawnTimer -= dt;

        // Tick active block windows
        for (let bi = 0; bi < row.blocks.length; bi++) {
            const block = row.blocks[bi];
            if (block.active) {
                block.windowLeft = Math.max(0, block.windowLeft - dt);
                if (block.windowLeft <= 0) {
                    // Missed — expired
                    block.active   = false;
                    block.locking  = false;
                }
            }
            if (block.locking) {
                block.locking = false;
            }
        }

        // Spawn new spike
        if (row.spawnTimer <= 0) {
            const bi = Math.floor(Math.random() * BLOCKS);
            if (!row.blocks[bi].active && !isBufferFull.value) {
                row.blocks[bi].active     = true;
                row.blocks[bi].windowLeft = config.windowDuration;
                row.blocks[bi].windowMax  = config.windowDuration;
                row.blocks[bi].locking    = false;
            }
            row.spawnTimer = randSpawn();
        }

        row.path = buildPath(ri);
    }

    // Buffer overflow penalty
    if (isBufferFull.value) {
        applyHit(config.bufferOverflowHit * dt);
    }
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
 * FORENSIC WORKSTATION — CRT OSCILLOSCOPE AESTHETIC
 * BG:     #010804  near-black, slight green phosphor tint
 * P-GRN:  #00ff9d  bright phosphor green — primary data
 * CYAN:   #00e5ff  — section titles, system headers
 * ORANGE: #ff6600  — warnings, anomalies, buttons
 * DIM:    rgba(0,180,70,0.25)  — de-emphasized labels
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
    background: #010804;
}

/* ── CRT overlay — scanlines + vignette ──────────────────────────────────── */

.fb-crt-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 50;
    background:
        repeating-linear-gradient(
            to bottom,
            transparent        0px,
            transparent        2px,
            rgba(0,0,0,0.09)   2px,
            rgba(0,0,0,0.09)   4px
        ),
        repeating-linear-gradient(
            to right,
            transparent              0px,
            transparent              3px,
            rgba(0,255,100,0.008)    3px,
            rgba(0,255,100,0.008)    4px
        );
    box-shadow:
        inset 0 0  50px 24px rgba(0,0,0,0.70),
        inset 0 0 120px 50px rgba(0,0,0,0.40);
}

/* ── CAPTURE SECTION ──────────────────────────────────────────────────────── */

.fb-capture-section {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,255,157,0.07);
}

/* ── STATUS BAR ───────────────────────────────────────────────────────────── */

.fb-status-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 3px 10px;
    background: rgba(0,255,100,0.018);
    border-bottom: 1px solid rgba(0,255,157,0.06);
    flex-shrink: 0;
    flex-wrap: nowrap;
}

.fb-status-chip {
    font-size: 7.5px;
    letter-spacing: 0.14em;
    padding: 2px 7px;
    flex-shrink: 0;
}

.fb-chip--live {
    color: #00ff9d;
    border: 1px solid rgba(0,255,157,0.35);
    text-shadow: 0 0 7px rgba(0,255,157,0.55);
}

.fb-chip--dead {
    color: #ff6600;
    border: 1px solid rgba(255,102,0,0.40);
    text-shadow: 0 0 7px rgba(255,102,0,0.55);
    animation: fb-scan-blink 1.0s ease infinite;
}

.fb-status-item {
    font-size: 7.5px;
    color: rgba(0,220,130,0.55);
    letter-spacing: 0.10em;
    white-space: nowrap;
}

.fb-status--warn {
    color: #ff6600 !important;
    text-shadow: 0 0 6px rgba(255,102,0,0.5) !important;
}

.fb-status-sep {
    color: rgba(0,180,80,0.18);
    font-size: 7px;
    flex-shrink: 0;
}

/* scan button styling handled in .fb-btn--scan below */

.fb-scan-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}

.fb-dot--live {
    background: #00ff9d;
    box-shadow: 0 0 5px rgba(0,255,157,0.9);
    animation: fb-dot-pulse 1.6s ease infinite;
}

.fb-dot--dead {
    background: rgba(255,102,0,0.4);
}

/* ── WAVEFORMS ────────────────────────────────────────────────────────────── */

.fb-waveforms {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 4px 10px 4px;
}

.fb-row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.fb-row-label {
    font-size: 7.5px;
    color: rgba(0,180,70,0.30);
    letter-spacing: 0.14em;
    width: 20px;
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
    background: rgba(0,6,2,0.80);
    border: 1px solid rgba(0,255,157,0.07);
}

.fb-grid-h {
    stroke: rgba(0,255,157,0.055);
    stroke-width: 0.5;
}

.fb-grid-center {
    stroke: rgba(0,255,157,0.11);
    stroke-width: 0.5;
    stroke-dasharray: 6 4;
}

.fb-wave-path {
    fill: none;
    stroke: #00ff9d;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter:
        drop-shadow(0 0 1px   rgba(0,255,157,1.0))
        drop-shadow(0 0 3px   rgba(0,255,157,0.7))
        drop-shadow(0 0 8px   rgba(0,255,157,0.35))
        drop-shadow(0 0 18px  rgba(0,255,157,0.12));
}

.fb-block-div {
    stroke: rgba(0,255,157,0.05);
    stroke-width: 0.5;
}

.fb-block-rect {
    fill: transparent;
    stroke: none;
    cursor: default;
}

.fb-block--spike {
    fill: rgba(255,102,0,0.09);
    stroke: rgba(255,102,0,0.60);
    stroke-width: 1;
    cursor: pointer;
    animation: fb-spike-pulse 0.36s ease infinite alternate;
    filter: drop-shadow(0 0 5px rgba(255,102,0,0.45));
}

.fb-block--lock {
    fill: rgba(0,255,157,0.08);
    stroke: rgba(0,255,157,0.40);
    stroke-width: 1;
}

.fb-block--full {
    fill: rgba(255,102,0,0.02);
    cursor: not-allowed;
}

.fb-window-track {
    height: 2px;
    background: rgba(255,102,0,0.04);
    overflow: hidden;
    margin-top: 1px;
}

.fb-window-fill {
    height: 100%;
    background: #ff6600;
    box-shadow: 0 0 4px rgba(255,102,0,0.7);
    transition: width 0.05s linear;
}

/* ── SIGNAL INSPECT PANEL ─────────────────────────────────────────────────── */

.fb-inspect-panel {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid rgba(0,229,255,0.10);
    background: rgba(0,10,6,0.85);
    overflow: hidden;
}

.fb-inspect-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 3px 10px;
    background: rgba(0,229,255,0.025);
    border-bottom: 1px solid rgba(0,229,255,0.08);
    flex-shrink: 0;
}

.fb-inspect-id {
    font-size: 7.5px;
    color: #00e5ff;
    letter-spacing: 0.16em;
    text-shadow: 0 0 6px rgba(0,229,255,0.55);
}

.fb-inspect-verdict {
    font-size: 7.5px;
    letter-spacing: 0.10em;
}

.fb-verdict--warn  { color: #ff9933; text-shadow: 0 0 5px rgba(255,153,51,0.45); }
.fb-verdict--clean { color: rgba(0,200,80,0.45); }

.fb-inspect-close {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 7.5px;
    letter-spacing: 0.12em;
    background: transparent;
    border: 1px solid rgba(0,200,80,0.14);
    color: rgba(0,200,80,0.35);
    padding: 1px 7px;
    cursor: pointer;
    transition: all 0.1s;
}

.fb-inspect-close:hover { border-color: rgba(0,200,80,0.4); color: #00ff9d; }

.fb-inspect-body {
    display: flex;
    align-items: stretch;
    padding: 5px 10px;
    gap: 0;
}

.fb-inspect-wave {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.fb-inspect-wave-label {
    font-size: 6.5px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.fb-label--ref { color: rgba(0,160,70,0.35); }
.fb-label--sig { color: #00e5ff; text-shadow: 0 0 4px rgba(0,229,255,0.4); }

.fb-inspect-svg {
    width: 100%;
    height: 38px;
    display: block;
    background: rgba(0,4,2,0.80);
    border: 1px solid rgba(0,180,70,0.08);
}

.fb-path--ref {
    fill: none;
    stroke: rgba(0,200,80,0.28);
    stroke-width: 1;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.fb-path--ref-ghost {
    fill: none;
    stroke: rgba(0,200,80,0.14);
    stroke-width: 0.8;
    stroke-dasharray: 4 3;
}

.fb-path--sig {
    fill: none;
    stroke: #00e5ff;
    stroke-width: 1.4;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter:
        drop-shadow(0 0 1px rgba(0,229,255,0.9))
        drop-shadow(0 0 4px rgba(0,229,255,0.3));
}

.fb-inspect-metrics {
    display: flex;
    gap: 12px;
}

.fb-metric {
    font-size: 7px;
    color: rgba(0,160,70,0.35);
    letter-spacing: 0.06em;
    white-space: nowrap;
}

.fb-metric--dev {
    color: #ff9933;
    text-shadow: 0 0 4px rgba(255,153,51,0.40);
}

.fb-inspect-divider {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    padding: 0 10px;
    flex-shrink: 0;
}

.fb-divider-line  { flex: 1; width: 1px; background: rgba(0,180,70,0.10); }
.fb-divider-label { font-size: 6.5px; color: rgba(0,160,70,0.25); letter-spacing: 0.14em; }

.fb-inspect-enter-active,
.fb-inspect-leave-active {
    transition: max-height 0.16s ease, opacity 0.16s ease;
    max-height: 130px;
    overflow: hidden;
}
.fb-inspect-enter-from,
.fb-inspect-leave-to { max-height: 0; opacity: 0; }

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
    padding: 0;
    border-right: 1px solid rgba(0,255,157,0.07);
}

.fb-audit-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px;
    background: rgba(0,229,255,0.045);
    border-bottom: 1px solid rgba(0,229,255,0.12);
    flex-shrink: 0;
}

.fb-audit-title {
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.20em;
    color: #00e5ff;
    text-shadow: 0 0 8px rgba(0,229,255,0.60), 0 0 20px rgba(0,229,255,0.20);
}

.fb-audit-sep {
    color: rgba(0,229,255,0.20);
    font-size: 7px;
}

.fb-audit-meta {
    font-size: 7px;
    color: rgba(0,200,180,0.50);
    letter-spacing: 0.10em;
}

.fb-reference {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 3px 10px;
    background: rgba(0,80,30,0.04);
    border-bottom: 1px solid rgba(0,180,70,0.08);
    flex-shrink: 0;
}

.fb-ref-tag {
    font-size: 6.5px;
    color: rgba(0,160,70,0.30);
    letter-spacing: 0.18em;
    flex-shrink: 0;
}

.fb-ref-val {
    font-size: 7px;
    color: rgba(0,160,70,0.30);
    letter-spacing: 0.04em;
}

.fb-grid-wrap {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.fb-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 9px;
    letter-spacing: 0.06em;
}

.fb-grid th:nth-child(1), .fb-grid td:nth-child(1) { width: 78px;  }
.fb-grid th:nth-child(2), .fb-grid td:nth-child(2) { width: 112px; }
.fb-grid th:nth-child(3), .fb-grid td:nth-child(3) { width: 84px;  }
.fb-grid th:nth-child(4), .fb-grid td:nth-child(4) { width: 70px;  }
.fb-grid th:nth-child(5), .fb-grid td:nth-child(5) { width: auto;  }

.fb-grid thead tr {
    background: rgba(0,180,70,0.025);
    border-bottom: 1px solid rgba(0,200,80,0.10);
}

.fb-grid th {
    text-align: left;
    padding: 4px 8px;
    font-size: 7px;
    color: rgba(0,160,70,0.32);
    letter-spacing: 0.18em;
    font-weight: normal;
    white-space: nowrap;
    overflow: hidden;
}

.fb-grid-row {
    border-bottom: 1px solid rgba(0,180,70,0.05);
    cursor: pointer;
    transition: background 0.06s;
}

.fb-grid-row:hover:not(.fb-grid-row--empty) {
    background: rgba(0,255,100,0.025);
}

.fb-grid-row--empty {
    cursor: default;
    border-bottom: 1px solid rgba(0,180,70,0.04);
}

.fb-grid td {
    padding: 4px 8px;
    color: #00ff9d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0 3px rgba(0,255,157,0.20);
}

.fb-grid-row--empty td {
    color: rgba(0,120,50,0.14);
    text-shadow: none;
}

.fb-row--selected {
    background: rgba(255,102,0,0.08) !important;
    border-left: 2px solid rgba(255,102,0,0.70);
}

.fb-row--selected td {
    color: #ff9933;
    text-shadow: 0 0 5px rgba(255,153,51,0.45);
}

.fb-row--inspected {
    background: rgba(0,229,255,0.04) !important;
    border-left: 2px solid rgba(0,229,255,0.35);
}

.fb-row--inspected td {
    color: #00e5ff;
    text-shadow: 0 0 4px rgba(0,229,255,0.28);
}

.fb-row--selected.fb-row--inspected {
    background: rgba(255,102,0,0.09) !important;
    border-left: 2px solid #ff6600;
}

.fb-row--selected.fb-row--inspected td {
    color: #ff9933;
    text-shadow: 0 0 5px rgba(255,153,51,0.45);
}

.fb-status-col {
    font-size: 7.5px;
    letter-spacing: 0.10em;
}

/* ── ACTION BUTTONS ───────────────────────────────────────────────────────── */

.fb-actions {
    display: flex;
    gap: 0;
    flex-shrink: 0;
    border-top: 1px solid rgba(0,255,157,0.10);
    align-items: stretch;
    min-height: 40px;
}

.fb-action-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.18em;
    background: transparent;
    padding: 10px 22px;
    cursor: pointer;
    transition: all 0.10s;
    display: flex;
    align-items: center;
    gap: 8px;
    border: none;
    border-right: 1px solid rgba(0,255,157,0.08);
}

.fb-action-btn:disabled {
    opacity: 0.20;
    cursor: not-allowed;
}

.fb-hotkey {
    font-size: 8px;
    font-weight: normal;
    letter-spacing: 0.12em;
    color: rgba(255,153,51,0.50);
    border: 1px solid rgba(255,102,0,0.25);
    padding: 2px 5px;
}

/* Scan toggle — left-most, cyan themed */
.fb-btn--scan {
    min-width: 190px;
    border-right: 1px solid rgba(0,255,157,0.12);
}

.fb-btn--scan.fb-scan--active {
    color: #00e5ff;
    border-left: 3px solid rgba(0,229,255,0.60);
    text-shadow: 0 0 8px rgba(0,229,255,0.55);
    background: rgba(0,229,255,0.04);
}

.fb-btn--scan.fb-scan--active:hover:not(:disabled) {
    background: rgba(0,229,255,0.08);
    text-shadow: 0 0 12px rgba(0,229,255,0.70);
}

.fb-btn--scan.fb-scan--paused {
    color: #ff9933;
    border-left: 3px solid rgba(255,102,0,0.70);
    text-shadow: 0 0 8px rgba(255,153,51,0.60);
    background: rgba(255,102,0,0.06);
    animation: fb-scan-blink 1.0s ease infinite;
}

.fb-btn--scan.fb-scan--paused:hover:not(:disabled) {
    animation: none;
    background: rgba(255,102,0,0.10);
    text-shadow: 0 0 12px rgba(255,153,51,0.80);
}

/* Flush — orange, most prominent */
.fb-btn--flush {
    flex: 1;
    color: #ff9933;
    border-left: 3px solid rgba(255,102,0,0.50);
    background: rgba(255,102,0,0.05);
    text-shadow: 0 0 6px rgba(255,153,51,0.35);
}

.fb-btn--flush:hover:not(:disabled) {
    background: rgba(255,102,0,0.10);
    color: #ffbb55;
    text-shadow: 0 0 10px rgba(255,153,51,0.60);
    box-shadow: inset 0 0 20px rgba(255,102,0,0.06);
}

/* Clear — dim green */
.fb-btn--clear {
    color: rgba(0,200,80,0.50);
    border-left: 3px solid rgba(0,200,80,0.20);
}

.fb-btn--clear:hover:not(:disabled) {
    background: rgba(0,255,100,0.04);
    color: #00ff9d;
    text-shadow: 0 0 6px rgba(0,255,157,0.40);
}

/* ── TERMINAL LOG ─────────────────────────────────────────────────────────── */

.fb-terminal-log {
    width: 200px;
    flex-shrink: 0;
    background: rgba(0,3,1,0.70);
    border-left: 1px solid rgba(0,255,157,0.07);
    padding: 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.fb-log-header {
    font-size: 6.5px;
    color: rgba(0,200,180,0.55);
    letter-spacing: 0.24em;
    padding: 4px 8px;
    border-bottom: 1px solid rgba(0,180,70,0.08);
    flex-shrink: 0;
    background: rgba(0,229,255,0.018);
    text-shadow: 0 0 5px rgba(0,229,255,0.35);
}

.fb-log-line {
    font-size: 7px;
    color: rgba(0,200,80,0.42);
    letter-spacing: 0.03em;
    line-height: 1.50;
    word-break: break-all;
    padding: 1px 8px;
}

.fb-log--warn  { color: rgba(255,153,0,0.70); }
.fb-log--error { color: rgba(255,60,60,0.80); text-shadow: 0 0 4px rgba(255,60,60,0.30); }

/* ── ANIMATIONS ───────────────────────────────────────────────────────────── */

@keyframes fb-spike-pulse {
    from { fill: rgba(255,102,0,0.04); stroke: rgba(255,102,0,0.28); }
    to   { fill: rgba(255,102,0,0.15); stroke: rgba(255,102,0,0.85); }
}

@keyframes fb-scan-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.40; }
}

@keyframes fb-dot-pulse {
    0%, 100% { opacity: 1;    box-shadow: 0 0 4px rgba(0,255,157,0.9); }
    50%       { opacity: 0.3; box-shadow: 0 0 2px rgba(0,255,157,0.3); }
}
</style>
