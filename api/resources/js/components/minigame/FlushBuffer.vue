<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <div class="fb-status-bar">
                    <span class="fb-status-chip" :class="isScanning ? 'fb-chip--live' : 'fb-chip--dead'">
                        <span class="fb-cite-tag">CITE</span> {{ isScanning ? 'LIVE FEED: ACTIVE' : 'LIVE FEED: DE-ACTIVATED' }}
                    </span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item"><span class="fb-cite-tag fb-cite-tag--dim">CITE</span> ACTIVE STREAM: MONITOR_ONLY</span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item" :class="isBufferFull ? 'fb-status--warn' : ''">BUFFER: {{ capturedSignals.length }}/{{ MAX_BUFFER }}</span>
                    <span class="fb-status-sep">|</span>
                    <span class="fb-status-item">SIGNAL_LOCKED: {{ anomalousFlushed }}/{{ locksRequired }}</span>
                    <div class="fb-scan-dot" :class="isScanning ? 'fb-dot--live' : 'fb-dot--dead'" />
                </div>

                <div class="fb-sig-panel">
                    <span class="fb-sig-label">SIGNAL LVL</span>
                    <div class="fb-sig-bar"><div class="fb-sig-fill fb-fill--warn fb-sig-anim"></div></div>
                    <span class="fb-sig-val fb-val--warn">~20%</span>
                </div>
                <div class="fb-sig-panel">
                    <span class="fb-sig-label">STABILITY</span>
                    <div class="fb-sig-bar">
                        <div class="fb-sig-fill"
                            :class="stability > 50 ? 'fb-fill--ok' : stability > 25 ? 'fb-fill--warn' : 'fb-fill--crit'"
                            :style="{ width: stability + '%' }"></div>
                    </div>
                    <span class="fb-sig-val" :class="stability > 50 ? '' : stability > 25 ? 'fb-val--warn' : 'fb-val--crit'">{{ Math.round(stability) }}%</span>
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
                                <div class="fb-metric-row">
                                    <span class="fb-metric-key">SRC</span>
                                    <span class="fb-metric-val">{{ sessionSource }}</span>
                                </div>
                                <div class="fb-metric-row">
                                    <span class="fb-metric-key">FREQ</span>
                                    <span class="fb-metric-val">{{ freqMinDisplay }}–{{ freqMaxDisplay }} <em>GHz</em></span>
                                </div>
                                <div class="fb-metric-row">
                                    <span class="fb-metric-key">LOAD</span>
                                    <span class="fb-metric-val">{{ loadMinDisplay }}–{{ loadMaxDisplay }} <em>KB</em></span>
                                </div>
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
                                <div class="fb-metric-row" :class="sourceDeviation ? 'fb-metric-row--dev' : ''">
                                    <span class="fb-metric-key">SRC</span>
                                    <span class="fb-metric-val">{{ inspectedSignal.source }}<span v-if="sourceDeviation" class="fb-metric-warn"> ⚠</span></span>
                                </div>
                                <div class="fb-metric-row" :class="freqDeviation ? 'fb-metric-row--dev' : ''">
                                    <span class="fb-metric-key">FREQ</span>
                                    <span class="fb-metric-val">{{ inspectedSignal.freq }} <em>GHz</em><span v-if="freqDeviation" class="fb-metric-warn"> ⚠</span></span>
                                </div>
                                <div class="fb-metric-row" :class="loadDeviation ? 'fb-metric-row--dev' : ''">
                                    <span class="fb-metric-key">LOAD</span>
                                    <span class="fb-metric-val">{{ inspectedSignal.load }} <em>KB</em><span v-if="loadDeviation" class="fb-metric-warn"> ⚠</span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </Transition>

            <div class="fb-body">
                <div class="fb-audit-main">

                    <div class="fb-audit-banner">
                        <span class="fb-audit-title">FORENSIC AUDIT</span>
                        <span class="fb-audit-chip"><span class="fb-cite-tag">CITE</span> SYSTEM_DIAGNOSTIC_MODE: AUDIT</span>
                        <span class="fb-audit-chip" :class="isBufferFull ? 'fb-chip--hi' : ''">
                            <span class="fb-cite-tag">CITE</span> BUFFER: {{ capturedSignals.filter(s => !s.flushed).length }}/{{ MAX_BUFFER }}
                        </span>
                        <span class="fb-audit-chip"><span class="fb-cite-tag">CITE</span> STABILITY: {{ Math.round(stability) }}%</span>
                        <span class="fb-audit-phase">LOCKED: {{ anomalousFlushed }}/{{ locksRequired }}</span>
                    </div>

                    <div class="fb-reference">
                        <span class="fb-ref-text">REFERENCE BASELINE: <b class="fb-ref-value">[cite: REF: Source: {{ sessionSource }}, Freq: {{ freqMinDisplay }}–{{ freqMaxDisplay }}, Load: &lt;{{ loadMaxDisplay }}KB]</b></span>
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
                                        'fb-row--selected':  selectedIds.includes(signal.id) && !signal.flushed,
                                        'fb-row--inspected': inspectedSignal?.id === signal.id && !signal.flushed,
                                        'fb-row--anom':      signal.anomaly && !signal.flushed && !selectedIds.includes(signal.id),
                                        'fb-row--flushed':   signal.flushed,
                                    }"
                                    @click="signal.flushed ? null : onRowClick(signal.id)"
                                >
                                    <td>{{ signal.id }}</td>
                                    <td>{{ signal.source }}</td>
                                    <td>{{ signal.freq }}</td>
                                    <td>{{ signal.load }}</td>
                                    <td class="fb-status-col">
                                        <span v-if="signal.flushed"                                        class="fb-st-purged">[PURGED]</span>
                                        <span v-else-if="signal.anomaly && !selectedIds.includes(signal.id)" class="fb-st-anom">[!] ANOMALY_DETECTED</span>
                                        <span v-else-if="selectedIds.includes(signal.id)"                  class="fb-st-flag">[!] SIGNAL_FLAGGED</span>
                                        <span v-else                                                        class="fb-st-valid">[VALID]]</span>
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
                        >{{ isScanning ? '[!: STOP CAPTURE]]' : '[!: RESUME CAPTURE]]' }}</button>
                        <button
                            class="fb-action-btn fb-btn--flush"
                            :class="selectedIds.length > 0 && !isScanning && !result ? 'fb-flush--armed' : ''"
                            :disabled="selectedIds.length === 0 || !!result || isScanning"
                            @click="onFlush"
                        >[!: FLUSH]] <span class="fb-hotkey">SPACE</span></button>
                        <button
                            class="fb-action-btn fb-btn--clear"
                            :disabled="selectedIds.length === 0 || !!result"
                            @click="onClear"
                        >[!: CLEAR]]</button>
                        <div class="fb-ss-bar-wrap">
                            <span class="fb-ss-label">SS:</span>
                            <div class="fb-ss-bar">
                                <div class="fb-ss-fill"
                                    :class="stability > 50 ? 'fb-fill--ok' : stability > 25 ? 'fb-fill--warn' : 'fb-fill--crit'"
                                    :style="{ width: stability + '%' }"></div>
                            </div>
                            <span class="fb-ss-val" :class="stability > 50 ? '' : stability > 25 ? 'fb-val--warn' : 'fb-val--crit'">{{ Math.round(stability) }}%</span>
                        </div>
                        <button
                            class="fb-action-btn fb-btn--exit"
                            :disabled="!!result"
                            @click="onEarlyExit"
                        >[!: BACK_TO_CAPTURE]]</button>
                    </div>

                </div>

                <div class="fb-terminal-log" ref="logEl">
                    <div class="fb-log-header">TRANSIVAL_LOG</div>
                    <div
                        v-for="(line, i) in terminalLog"
                        :key="i"
                        class="fb-log-line"
                        :class="{
                            'fb-log--warn':  line.type === 'warn',
                            'fb-log--error': line.type === 'error',
                            'fb-log--hi':    line.type === 'hi',
                            'fb-log--good':  line.type === 'good',
                        }"
                    >{{ line.text }}</div>
                </div>

            </div><!-- /fb-body -->

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
    hideTimer:       true,
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
const isBufferFull     = computed(() => capturedSignals.value.filter(s => !s.flushed).length >= MAX_BUFFER);
const emptySlots       = computed(() => Math.max(0, MAX_BUFFER - capturedSignals.value.filter(s => !s.flushed).length));

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
        addLog(`> FLUSH_ERROR: ${cleanCount} clean signal(s) incorrectly flagged — trace spiked`, 'warn');
    }

    // Mark flushed signals — keep rows visible with [PURGED] styling
    const flushedIds = [...selectedIds.value];
    capturedSignals.value = capturedSignals.value.map(s =>
        flushedIds.includes(s.id) ? { ...s, flushed: true } : s
    );
    selectedIds.value = [];
    if (inspectedSignal.value && flushedIds.includes(inspectedSignal.value.id)) {
        inspectedSignal.value = null;
    }

    if (anomCount > 0) {
        anomalousFlushed.value += anomCount;
        addLog(`> FLUSH_COMPLETE: ${anomCount} anomalous signal(s) purged [${anomalousFlushed.value}/${locksRequired}]`, 'good');
        if (anomalousFlushed.value >= locksRequired) {
            addLog('> BUFFER CLEAN — all ghost signals eliminated', 'good');
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

function onEarlyExit() {
    if (result.value) return;
    addLog('> ABORT: operator initiated early exit — feed abandoned', 'warn');
    endGame('fail', 'OPERATOR ABORT');
    setTimeout(() => emit('fail'), 1200);
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
    // Stability drains passively; ICE trace filling = lose; stability emptying = lose.
    const traceDt = isScanning.value ? dt : dt * 0.5;
    const failCause = tickShared(traceDt);
    if (failCause === 'trace') {
        addLog('> ICE_TRACE_LOCKED: connection terminated by defensive layer', 'error');
        endGame('fail', 'ICE TRACE LOCKED — ABORT');
        setTimeout(() => emit('fail'), 1800);
        return;
    }
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

    // Buffer overflow — log only; stability is a passive timer, not hit by gameplay
    if (isBufferFull.value && Math.floor(scrollT * 2) % 90 === 0) {
        addLog('> BUFFER_OVERFLOW: capture stalled — flush required', 'warn');
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
 * FLUSH_BUFFER — INSTRUMENT PANEL AESTHETIC
 * One continuous machined surface. No cards. No shadows. No padding waste.
 * Phosphor green CRT. Hierarchy via brightness + density. Separators over containers.
 * Every pixel is functional.
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
    background: #020802;
}

/* ── Body row: table + log ────────────────────────────────────────────────── */

.fb-body {
    flex: 1;
    display: flex;
    flex-direction: row;
    min-height: 0;
    overflow: hidden;
}

/* ── CRT overlay ──────────────────────────────────────────────────────────── */

.fb-crt-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 50;
    background: repeating-linear-gradient(
        to bottom,
        transparent      0px,
        transparent      2px,
        rgba(0,0,0,0.07) 2px,
        rgba(0,0,0,0.07) 4px
    );
    box-shadow: inset 0 0 80px 30px rgba(0,0,0,0.55);
}

/* ── STATUS BAR ───────────────────────────────────────────────────────────── */

.fb-status-bar {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 2px 6px;
    border-bottom: 1px solid rgba(0,180,0,0.08);
    background: #011001;
    flex-shrink: 0;
    flex-wrap: nowrap;
}

.fb-status-chip {
    font-size: 8px;
    letter-spacing: 0.08em;
    padding: 1px 3px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 3px;
}

.fb-cite-tag {
    display: inline-block;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #002800;
    background: #00cc00;
    padding: 0 3px;
    line-height: 1.4;
    flex-shrink: 0;
    border-radius: 0;
}

.fb-cite-tag--dim {
    background: rgba(0,160,0,0.30);
    color: #001800;
}

.fb-chip--live {
    color: #00cc00;
    border: 1px solid rgba(0,200,0,0.25);
}

.fb-chip--dead {
    color: #ff8800;
    border: 1px solid rgba(255,136,0,0.30);
    animation: fb-scan-blink 1.0s ease infinite;
}

.fb-status-item {
    font-size: 8px;
    color: rgba(0,170,0,0.40);
    letter-spacing: 0.06em;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 3px;
}

.fb-status--warn {
    color: #ff8800 !important;
}

.fb-status-sep {
    color: rgba(0,140,0,0.16);
    font-size: 8px;
    flex-shrink: 0;
}

.fb-scan-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    flex-shrink: 0;
}

.fb-dot--live {
    background: #00cc00;
    animation: fb-dot-pulse 1.6s ease infinite;
}

.fb-dot--dead {
    background: rgba(255,136,0,0.35);
}

/* ── SIGNAL / STABILITY BARS ─────────────────────────────────────────────── */

.fb-sig-panel {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 2px 6px;
    border-bottom: 1px solid rgba(0,180,0,0.06);
    flex-shrink: 0;
    background: #010e01;
}

.fb-sig-label {
    font-size: 6px;
    color: rgba(0,140,0,0.38);
    letter-spacing: 0.16em;
    width: 64px;
    flex-shrink: 0;
}

.fb-sig-bar {
    flex: 1;
    height: 5px;
    background: rgba(0,160,0,0.06);
    border: 1px solid rgba(0,160,0,0.10);
    overflow: hidden;
}

.fb-sig-fill {
    height: 100%;
    transition: width 0.5s linear;
}

.fb-fill--ok   { background: #00cc00; box-shadow: 0 0 4px rgba(0,204,0,0.50); }
.fb-fill--warn { background: #ff8800; box-shadow: 0 0 4px rgba(255,136,0,0.50); }
.fb-fill--crit { background: #ff2200; box-shadow: 0 0 4px rgba(255,34,0,0.50); }

.fb-sig-val {
    font-size: 8px;
    font-weight: 700;
    color: #00cc00;
    letter-spacing: 0.06em;
    width: 32px;
    text-align: right;
    flex-shrink: 0;
}

.fb-val--warn { color: #ff8800; }
.fb-val--crit { color: #ff2200; }

.fb-sig-anim { animation: fb-sig-oscillate 4.8s ease-in-out infinite; }

/* ── WAVEFORMS ────────────────────────────────────────────────────────────── */

.fb-waveforms {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    height: 144px;
    border-bottom: 1px solid rgba(0,180,0,0.10);
    padding: 0;
    gap: 0;
}

.fb-row {
    display: flex;
    align-items: stretch;
    flex: 1;
    border-bottom: 1px solid rgba(0,180,0,0.06);
    min-height: 0;
    max-height: 48px;
}

.fb-row-label {
    font-size: 6px;
    color: rgba(0,120,0,0.30);
    letter-spacing: 0.10em;
    width: 14px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid rgba(0,180,0,0.06);
    background: #010601;
    writing-mode: vertical-rl;
}

.fb-wave-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}

.fb-svg {
    flex: 1;
    width: 100%;
    height: 100%;
    display: block;
    min-height: 0;
}

.fb-grid-h {
    stroke: rgba(0,180,0,0.05);
    stroke-width: 0.5;
}

.fb-grid-center {
    stroke: rgba(0,180,0,0.10);
    stroke-width: 0.5;
    stroke-dasharray: 6 4;
}

.fb-wave-path {
    fill: none;
    stroke: #00cc00;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter:
        drop-shadow(0 0 1px   rgba(0,204,0,1.0))
        drop-shadow(0 0 3px   rgba(0,204,0,0.65))
        drop-shadow(0 0 8px   rgba(0,204,0,0.30))
        drop-shadow(0 0 18px  rgba(0,204,0,0.10));
}

.fb-block-div {
    stroke: rgba(0,180,0,0.05);
    stroke-width: 0.5;
}

.fb-block-rect {
    fill: transparent;
    stroke: none;
    cursor: default;
}

.fb-block--spike {
    fill: rgba(255,136,0,0.09);
    stroke: rgba(255,136,0,0.60);
    stroke-width: 1;
    cursor: pointer;
    animation: fb-spike-pulse 0.36s ease infinite alternate;
    filter: drop-shadow(0 0 5px rgba(255,136,0,0.45));
}

.fb-block--lock {
    fill: rgba(0,204,0,0.06);
    stroke: rgba(0,204,0,0.35);
    stroke-width: 1;
}

.fb-block--full {
    fill: rgba(255,136,0,0.02);
    cursor: not-allowed;
}

.fb-window-track {
    height: 1px;
    background: rgba(255,136,0,0.05);
    overflow: hidden;
    flex-shrink: 0;
}

.fb-window-fill {
    height: 100%;
    background: #ff8800;
    transition: width 0.05s linear;
}

/* ── SIGNAL INSPECT PANEL ─────────────────────────────────────────────────── */

.fb-inspect-panel {
    flex: 0 0 auto;
    display: flex;
    flex-direction: column;
    border-top: 1px solid rgba(0,180,0,0.10);
    background: rgba(0,5,0,0.92);
    overflow: hidden;
}

.fb-inspect-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 2px 6px;
    border-bottom: 1px solid rgba(0,160,0,0.08);
    flex-shrink: 0;
}

.fb-inspect-id {
    font-size: 6px;
    color: #00cc00;
    letter-spacing: 0.14em;
}

.fb-inspect-verdict {
    font-size: 6px;
    letter-spacing: 0.08em;
}

.fb-verdict--warn  { color: #ff8800; }
.fb-verdict--clean { color: rgba(0,180,0,0.40); }

.fb-inspect-close {
    margin-left: auto;
    font-family: 'JetBrains Mono', monospace;
    font-size: 6px;
    letter-spacing: 0.10em;
    background: transparent;
    border: 1px solid rgba(0,160,0,0.14);
    color: rgba(0,160,0,0.30);
    padding: 1px 4px;
    cursor: pointer;
    transition: all 0.1s;
}

.fb-inspect-close:hover { border-color: rgba(0,200,0,0.35); color: #00cc00; }

.fb-inspect-body {
    display: flex;
    align-items: stretch;
    padding: 3px 6px;
    gap: 0;
}

.fb-inspect-wave {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.fb-inspect-wave-label {
    font-size: 5.5px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.fb-label--ref { color: rgba(0,140,0,0.32); }
.fb-label--sig { color: rgba(255,136,0,0.70); }

.fb-inspect-svg {
    width: 100%;
    height: 28px;
    display: block;
    background: rgba(0,3,0,0.80);
    border: 1px solid rgba(0,160,0,0.07);
}

.fb-path--ref {
    fill: none;
    stroke: rgba(0,180,0,0.28);
    stroke-width: 1;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.fb-path--ref-ghost {
    fill: none;
    stroke: rgba(0,180,0,0.12);
    stroke-width: 0.8;
    stroke-dasharray: 4 3;
}

.fb-path--sig {
    fill: none;
    stroke: #ff8800;
    stroke-width: 1.3;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 0 2px rgba(255,136,0,0.45));
}

.fb-inspect-metrics {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding-top: 2px;
}

.fb-metric-row {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.fb-metric-key {
    font-size: 6px;
    color: rgba(0,140,0,0.35);
    letter-spacing: 0.16em;
    width: 30px;
    flex-shrink: 0;
}

.fb-metric-val {
    font-size: 10px;
    font-weight: 700;
    color: rgba(0,200,0,0.72);
    letter-spacing: 0.04em;
}

.fb-metric-val em {
    font-style: normal;
    font-size: 7px;
    font-weight: normal;
    color: rgba(0,160,0,0.40);
    margin-left: 1px;
}

.fb-metric-row--dev .fb-metric-key { color: rgba(255,136,0,0.50); }
.fb-metric-row--dev .fb-metric-val  { color: #ff8800; }
.fb-metric-warn { color: #ff6600; font-size: 8px; }

.fb-inspect-divider {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 0 6px;
    flex-shrink: 0;
}

.fb-divider-line  { flex: 1; width: 1px; background: rgba(0,160,0,0.08); }
.fb-divider-label { font-size: 5.5px; color: rgba(0,140,0,0.22); letter-spacing: 0.12em; }

.fb-inspect-enter-active,
.fb-inspect-leave-active {
    transition: max-height 0.14s ease, opacity 0.14s ease;
    max-height: 110px;
    overflow: hidden;
}
.fb-inspect-enter-from,
.fb-inspect-leave-to { max-height: 0; opacity: 0; }

/* ── AUDIT MAIN ───────────────────────────────────────────────────────────── */

.fb-audit-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    border-right: 1px solid rgba(0,180,0,0.07);
}

.fb-audit-banner {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 6px;
    background: #001a00;
    border-bottom: 1px solid rgba(0,200,0,0.12);
    flex-shrink: 0;
}

.fb-audit-title {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #00ff00;
}

.fb-audit-chip {
    color: #005500;
    border: 1px solid #003300;
    padding: 0 4px;
    font-size: 9px;
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
}

.fb-chip--hi {
    color: #ff8800;
    border-color: #3a2000;
}

.fb-audit-phase {
    font-size: 9px;
    color: #005500;
    letter-spacing: 0.10em;
    border: 1px solid #003300;
    padding: 0 4px;
    margin-left: auto;
    flex-shrink: 0;
}

/* ── REFERENCE BASELINE ───────────────────────────────────────────────────── */

.fb-reference {
    padding: 2px 6px;
    border-bottom: 1px solid rgba(0,160,0,0.08);
    background: #010e01;
    flex-shrink: 0;
}

.fb-ref-text {
    font-size: 9px;
    color: #004400;
    letter-spacing: 0.02em;
}

.fb-ref-value {
    color: #006600;
    font-weight: bold;
}

/* ── DATA GRID ────────────────────────────────────────────────────────────── */

.fb-grid-wrap {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.fb-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10px;
    letter-spacing: 0.03em;
}

.fb-grid th:nth-child(1), .fb-grid td:nth-child(1) { width: 68px;  }
.fb-grid th:nth-child(2), .fb-grid td:nth-child(2) { width: 100px; }
.fb-grid th:nth-child(3), .fb-grid td:nth-child(3) { width: 72px;  }
.fb-grid th:nth-child(4), .fb-grid td:nth-child(4) { width: 60px;  }
.fb-grid th:nth-child(5), .fb-grid td:nth-child(5) { width: auto;  }

.fb-grid thead tr {
    border-bottom: 1px solid rgba(0,180,0,0.08);
}

.fb-grid th {
    text-align: left;
    padding: 2px 5px;
    font-size: 6px;
    color: rgba(0,140,0,0.32);
    letter-spacing: 0.18em;
    font-weight: normal;
    white-space: nowrap;
    overflow: hidden;
    background: #010e01;
}

.fb-grid-row {
    border-bottom: 1px solid rgba(0,160,0,0.04);
    cursor: pointer;
    transition: background 0.05s;
}

.fb-grid-row:hover:not(.fb-grid-row--empty) {
    background: rgba(0,200,0,0.03);
}

.fb-grid-row--empty {
    cursor: default;
    border-bottom: 1px solid rgba(0,160,0,0.03);
}

.fb-grid td {
    padding: 3px 6px;
    color: #00b800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fb-grid-row--empty td {
    color: rgba(0,100,0,0.14);
}

/* Anomalous row — amber pre-label */
.fb-row--anom td {
    color: #ff8800;
    background: #0a0400;
}

.fb-row--anom:hover:not(.fb-grid-row--empty) {
    background: rgba(255,100,0,0.07) !important;
}

/* Flushed row — struck through */
.fb-row--flushed {
    cursor: default !important;
}

.fb-row--flushed td {
    color: #003300;
    text-decoration: line-through;
    background: transparent;
}

.fb-row--selected {
    background: #011e01 !important;
    outline: 1px solid #006600;
}

.fb-row--selected td {
    color: #00ff00;
}

.fb-row--anom.fb-row--selected {
    background: #160700 !important;
    outline: 1px solid #ff8800;
}

.fb-row--anom.fb-row--selected td {
    color: #ffaa00;
}

.fb-row--inspected {
    background: rgba(0,200,0,0.04) !important;
    border-left: 2px solid rgba(0,200,0,0.28);
}

.fb-row--inspected td {
    color: #00cc00;
}

.fb-status-col {
    font-size: 7px;
    letter-spacing: 0.08em;
}

/* Status span variants */
.fb-st-valid  { color: #00aa00; }
.fb-st-flag   { color: #ff8800; }
.fb-st-purged { color: #003300; }
.fb-st-anom   { color: #ff6600; font-weight: bold; animation: fb-scan-blink 1.0s step-end infinite; }

/* ── ACTION BUTTONS ───────────────────────────────────────────────────────── */

.fb-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    border-top: 1px solid rgba(0,180,0,0.12);
    align-items: center;
    padding: 4px 6px;
    background: #010901;
    min-height: 32px;
}

.fb-action-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    font-weight: normal;
    letter-spacing: 0.10em;
    background: #010e01;
    padding: 4px 10px;
    cursor: pointer;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #0a3a0a;
    border-radius: 0;
    color: #00aa00;
    text-transform: uppercase;
}

.fb-action-btn:hover:not(:disabled) {
    background: #001a00;
    color: #00ff00;
    border-color: #00aa00;
}

.fb-action-btn:active:not(:disabled) {
    background: #002800;
}

.fb-action-btn:disabled {
    opacity: 0.30;
    cursor: not-allowed;
}

.fb-action-btn:disabled {
    opacity: 0.18;
    cursor: not-allowed;
}

.fb-hotkey {
    font-size: 7px;
    font-weight: normal;
    letter-spacing: 0.08em;
    color: rgba(255,136,0,0.55);
    border: 1px solid rgba(255,136,0,0.25);
    padding: 1px 3px;
}

.fb-btn--scan.fb-scan--paused {
    color: #ff8800;
    border-color: #3a2000;
    animation: fb-scan-blink 1.0s ease infinite;
}

.fb-btn--scan.fb-scan--paused:hover:not(:disabled) {
    animation: none;
    background: #1a0800;
    color: #ff8800;
}

.fb-btn--flush {
    color: #cc6600;
    border-color: #3a2000;
}

.fb-btn--flush:hover:not(:disabled) {
    background: #1a0800;
    color: #ff8800;
    border-color: #cc5500;
}

.fb-flush--armed {
    border-color: #ff8800;
    color: #ff8800;
    animation: fb-flush-pulse 0.6s ease-in-out infinite alternate;
}

.fb-btn--exit {
    margin-left: auto;
    color: rgba(160,50,50,0.70);
    border-color: rgba(120,30,30,0.40);
}

.fb-btn--exit:hover:not(:disabled) {
    background: #100000;
    color: rgba(220,80,80,0.90);
    border-color: rgba(180,50,50,0.60);
}

/* ── SS BAR (in button row) ───────────────────────────────────────────────── */

.fb-ss-bar-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    padding: 0 8px;
}

.fb-ss-label {
    font-size: 9px;
    color: #004400;
    letter-spacing: 0.06em;
    flex-shrink: 0;
}

.fb-ss-bar {
    flex: 1;
    height: 4px;
    background: #010901;
    border: 1px solid #051005;
    overflow: hidden;
}

.fb-ss-fill {
    height: 100%;
    transition: width 0.4s;
}

.fb-ss-val {
    font-size: 9px;
    color: #00aa00;
    min-width: 28px;
    text-align: right;
    flex-shrink: 0;
    font-family: 'JetBrains Mono', monospace;
}

/* ── TERMINAL LOG ─────────────────────────────────────────────────────────── */

.fb-terminal-log {
    width: 190px;
    flex-shrink: 0;
    background: rgba(0,2,0,0.55);
    border-left: 1px solid rgba(0,180,0,0.06);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

.fb-log-header {
    font-size: 6px;
    color: rgba(0,160,0,0.50);
    letter-spacing: 0.22em;
    padding: 2px 5px;
    border-bottom: 1px solid rgba(0,160,0,0.07);
    flex-shrink: 0;
}

.fb-log-line {
    font-size: 8.5px;
    color: #004d00;
    letter-spacing: 0.02em;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0px 4px;
}

.fb-log--warn  { color: #cc6600; }
.fb-log--error { color: #ff3333; }
.fb-log--hi    { color: #00aa00; }
.fb-log--good  { color: #00ff00; }

/* ── ANIMATIONS ───────────────────────────────────────────────────────────── */

@keyframes fb-spike-pulse {
    from { fill: rgba(255,136,0,0.04); stroke: rgba(255,136,0,0.28); }
    to   { fill: rgba(255,136,0,0.15); stroke: rgba(255,136,0,0.85); }
}

@keyframes fb-scan-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.38; }
}

@keyframes fb-dot-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.25; }
}

@keyframes fb-flush-pulse {
    from { box-shadow: none; }
    to   { box-shadow: 0 0 0 1px rgba(255,136,0,0.40); }
}

@keyframes fb-sig-oscillate {
    0%   { width: 20%; }
    12%  { width: 22%; }
    28%  { width: 17%; }
    44%  { width: 24%; }
    60%  { width: 18%; }
    76%  { width: 23%; }
    100% { width: 20%; }
}
</style>
