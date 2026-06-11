<template>
    <div class="su-root">
        <div class="su-scanline" />

        <!-- ── Red neon border frame ──────────────────────────────────────── -->
        <div class="su-frame">

            <!-- ── Header ────────────────────────────────────────────────── -->
            <div class="su-header">
                <div class="su-header-left">
                    <span class="su-title">SYSTEM UPDATE</span>
                </div>
                <div class="su-header-right">
                    <!-- Wifi-style signal icon (SVG) -->
                    <svg class="su-signal" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M2 9 Q12 0 22 9"  stroke="currentColor" stroke-width="1.8" fill="none" opacity="0.4" />
                        <path d="M5 12 Q12 5 19 12" stroke="currentColor" stroke-width="1.8" fill="none" opacity="0.7" />
                        <path d="M8 15 Q12 10 16 15" stroke="currentColor" stroke-width="1.8" fill="none" />
                        <circle cx="12" cy="17" r="1.5" fill="currentColor" />
                    </svg>
                </div>
            </div>

            <div class="su-rule" />

            <!-- ── Body — two columns ─────────────────────────────────────── -->
            <div class="su-body">

                <!-- Left column ─────────────────────────────────────────── -->
                <div class="su-col-left">

                    <!-- Download progress bar -->
                    <div class="su-progress-section">
                        <div class="su-progress-label">
                            <span>DOWNLOAD_PROGRESS:</span>
                            <span class="su-progress-pct">{{ displayPct }}%</span>
                        </div>
                        <div class="su-bar-outer">
                            <div
                                v-for="seg in BAR_SEGMENTS"
                                :key="seg"
                                class="su-bar-seg"
                                :class="getSegClass(seg)"
                            />
                        </div>
                    </div>

                    <!-- Notification block -->
                    <div class="su-notif-block">
                        <div class="su-notif-bracket-top">┌──────────────────────────────────────────────┐</div>
                        <div class="su-notif-body">
                            <div class="su-notif-tag">[SYSTEM_NOTIFICATION // URGENT]</div>
                            <div class="su-notif-name">Critical Patch: {{ patchDisplayName }}</div>
                        </div>
                        <div class="su-notif-bracket-bot">└──────────────────────────────────────────────┘</div>
                    </div>

                    <!-- Update log -->
                    <div class="su-log-section">
                        <div class="su-log-header">UPDATE LOG</div>
                        <div class="su-log-lines" ref="logEl">
                            <div
                                v-for="(line, i) in visibleLogLines"
                                :key="i"
                                class="su-log-line"
                                :class="{
                                    'su-log-line--warn':     line.type === 'warn',
                                    'su-log-line--error':    line.type === 'error',
                                    'su-log-line--complete': line.type === 'complete',
                                }"
                            >{{ line.text }}</div>
                        </div>
                    </div>

                </div>

                <!-- Right column ─────────────────────────────────────────── -->
                <div class="su-col-right">

                    <!-- Circular installer ring -->
                    <div class="su-ring-wrap">
                        <svg class="su-ring-svg" viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                            <!-- Tick marks -->
                            <circle cx="80" cy="80" r="72"
                                fill="none"
                                stroke="rgba(0,255,200,0.1)"
                                stroke-width="1"
                                stroke-dasharray="2 4"
                            />
                            <!-- Progress arc -->
                            <circle
                                class="su-ring-arc"
                                cx="80" cy="80" r="64"
                                fill="none"
                                stroke-width="5"
                                :stroke-dashoffset="ringOffset"
                            />
                            <!-- Inner tick ring -->
                            <circle cx="80" cy="80" r="55"
                                fill="none"
                                stroke="rgba(0,255,200,0.06)"
                                stroke-width="1"
                                stroke-dasharray="1 6"
                            />
                        </svg>

                        <div class="su-ring-center">
                            <div class="su-ring-status" :class="{ 'su-ring-status--done': phase === 'complete' }">
                                {{ phase === 'complete' ? 'COMPLETE' : 'INSTALLING...' }}
                            </div>
                            <div class="su-ring-eta">ETA: {{ etaDisplay }}</div>
                        </div>
                    </div>

                    <!-- Warning area -->
                    <div v-if="warningText" class="su-warning">
                        <span class="su-warning-icon">⚠</span>
                        {{ warningText }}
                    </div>

                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onUnmounted, nextTick } from 'vue';
import { SPLICE } from '@/components/browser/SpliceRouter.js';

const props = defineProps({
    url: { type: String, default: '' },
});

const spliceNavigate = inject('spliceNavigate', () => {});

// ── Parse URL params ──────────────────────────────────────────────────────────
// e.g. splice://sys.tacat/cortex-patch?patch=SPLICE_CONTACT_RELAY&sector=BROWNES_ADDITION
const urlParams = computed(() => {
    try {
        // Build a parseable URL by replacing the splice:// scheme
        const fake = props.url.replace(/^splice:\/\//, 'https://x.x/');
        const u    = new URL(fake);
        return Object.fromEntries(u.searchParams.entries());
    } catch {
        return {};
    }
});

const patchDisplayName = computed(() =>
    (urlParams.value.patch ?? 'SPLICE_CONTACT_RELAY_v2.1.4').replaceAll('_', ' ')
);

// ── Progress bar ──────────────────────────────────────────────────────────────
const BAR_SEGMENTS = 24;   // total segments in the bar
const progress     = ref(0);  // 0–100

const displayPct = computed(() => Math.round(progress.value));

function getSegClass(seg) {
    const threshold = (seg / BAR_SEGMENTS) * 100;
    if (progress.value >= threshold) {
        // Colour gradient: red → orange → yellow-orange across the bar
        const t = seg / BAR_SEGMENTS;
        if (t < 0.4) return 'su-bar-seg--red';
        if (t < 0.7) return 'su-bar-seg--orange';
        return 'su-bar-seg--amber';
    }
    return 'su-bar-seg--empty';
}

// ── Circular ring ─────────────────────────────────────────────────────────────
const RING_CIRC = 2 * Math.PI * 64;   // 2πr, r=64 ≈ 402.1

const ringOffset = computed(() =>
    RING_CIRC - (progress.value / 100) * RING_CIRC
);

// ── ETA countdown ─────────────────────────────────────────────────────────────
const etaSeconds = ref(198);   // starts at 3:18

const etaDisplay = computed(() => {
    const m = Math.floor(etaSeconds.value / 60).toString().padStart(2, '0');
    const s = (etaSeconds.value % 60).toString().padStart(2, '0');
    return `00:${m}:${s}`;
});

// ── Update log lines ──────────────────────────────────────────────────────────
const visibleLogLines = ref([]);
const warningText     = ref('');
const logEl           = ref(null);
const phase           = ref('running');   // running | stall | complete

// ── Sequence definition ───────────────────────────────────────────────────────
//
// Each entry fires at `at` ms from mount.
// progress: target progress value (animates smoothly)
// log:      { text, type } to append to log
// eta:      jump ETA to this value (seconds)
// warning:  string to show in warning area
// stall:    true = freeze ETA and pulse the stall warning
// done:     navigate away
//
const SEQUENCE = [
    { at:  200,  progress: 0,   log: { text: '>> Connecting to SPLICE relay...',                           type: 'default' } },
    { at:  700,  progress: 8                                                                                                  },
    { at: 1000,  progress: 18,  log: { text: '>> Fetching data from: [NIGHTGATE_DISTANT_UPLINK_B1]',       type: 'default' } },
    { at: 1600,  progress: 32                                                                                                 },
    { at: 2000,  progress: 38,  log: { text: '>> Decrypting core modules...',                              type: 'default' } },
    { at: 2600,  progress: 44                                                                                                 },
    // First stall
    { at: 3000,  progress: 44,  warning: 'WARNING: Connection Unstable — Retrying Segment 7...', stall: true               },
    { at: 3200,  progress: 44,  log: { text: '>> Warning: Anomalous data packet detected.',                type: 'warn'    } },
    { at: 3900,  progress: 52,  warning: '',                                                                                  },
    { at: 4300,  progress: 62,  log: { text: '>> Patching: [KERNEL.SYS]',                                 type: 'default' } },
    { at: 4900,  progress: 70                                                                                                 },
    { at: 5300,  progress: 72,  log: { text: '>> Analyzing: [NET_LAYER_EXPLOIT_PATCH.EXE]',               type: 'default' } },
    // Second stall
    { at: 5900,  progress: 72,  warning: 'WARNING: Connection Unstable — Retrying Segment 14...', stall: true              },
    { at: 6600,  progress: 84,  warning: ''                                                                                  },
    { at: 7000,  progress: 91,  log: { text: '>> Installing: SPLICE_CONTACT_RELAY_v2.1.4',                type: 'default' } },
    { at: 7500,  progress: 100, log: { text: '>> INSTALL COMPLETE — RELAY ACTIVE',                        type: 'complete' } },
    { at: 7800,  done: true                                                                                                   },
];

let _timers     = [];
let _etaTimer   = null;
let _progTimer  = null;

function _smoothProgress(target, duration) {
    const start     = progress.value;
    const delta     = target - start;
    const steps     = Math.ceil(duration / 40);
    const increment = delta / steps;
    let   step      = 0;

    clearInterval(_progTimer);
    _progTimer = setInterval(() => {
        step++;
        progress.value = Math.min(target, start + increment * step);
        if (step >= steps) clearInterval(_progTimer);
    }, 40);
    _timers.push(_progTimer);
}

onMounted(() => {
    // ETA counts down — faster during stalls (pretends to estimate recovery)
    _etaTimer = setInterval(() => {
        if (etaSeconds.value > 0) etaSeconds.value -= 1;
    }, 1000);
    _timers.push(_etaTimer);

    let lastProgress = 0;

    SEQUENCE.forEach((entry, i) => {
        const t = setTimeout(() => {
            // Progress
            if (entry.progress !== undefined && entry.progress !== lastProgress) {
                const dur = i > 0 ? (entry.at - (SEQUENCE[i - 1]?.at ?? 0)) * 0.8 : 400;
                _smoothProgress(entry.progress, Math.max(dur, 200));
                lastProgress = entry.progress;
            }

            // Log line
            if (entry.log) {
                visibleLogLines.value.push(entry.log);
                nextTick(() => {
                    if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight;
                });
            }

            // Warning
            if (entry.warning !== undefined) {
                warningText.value = entry.warning;
                if (entry.stall) phase.value = 'stall';
                else             phase.value = 'running';
            }

            // Done — brief hold then navigate
            if (entry.done) {
                phase.value = 'complete';
                const nav = setTimeout(() => {
                    spliceNavigate(SPLICE.TERMINAL);
                }, 1800);
                _timers.push(nav);
            }
        }, entry.at);

        _timers.push(t);
    });
});

onUnmounted(() => {
    _timers.forEach(id => { clearTimeout(id); clearInterval(id); });
    clearInterval(_etaTimer);
    clearInterval(_progTimer);
});
</script>

<style scoped>
/* ── Root ─────────────────────────────────────────────────────────────────── */
.su-root {
    position: relative;
    width: 100%;
    height: 100%;
    background: rgba(4, 2, 8, 0.98);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

.su-scanline {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,                   transparent                   2px,
        rgba(200, 0, 30, 0.012) 2px,  rgba(200, 0, 30, 0.012) 4px
    );
    animation: su-scan 0.3s linear infinite;
}
@keyframes su-scan {
    from { background-position-y: 0; }
    to   { background-position-y: 4px; }
}

/* ── Neon red frame ───────────────────────────────────────────────────────── */
.su-frame {
    position: relative;
    width: min(720px, 96%);
    background: rgba(8, 2, 4, 0.97);
    border: 1px solid rgba(220, 20, 50, 0.6);
    box-shadow:
        0 0 0 1px rgba(220, 20, 50, 0.15),
        0 0 40px rgba(220, 20, 50, 0.15),
        inset 0 0 60px rgba(0, 0, 0, 0.6);
    padding: 0;
    overflow: hidden;
}

/* Glow pulse on the border */
.su-frame::before {
    content: '';
    position: absolute;
    inset: 0;
    border: 1px solid rgba(220, 20, 50, 0.25);
    pointer-events: none;
    animation: su-border-glow 2s ease-in-out infinite;
}
@keyframes su-border-glow {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.su-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px 12px;
    background: rgba(220, 20, 50, 0.06);
}

.su-title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.24em;
    color: #FF2040;
    text-shadow: 0 0 20px rgba(255, 20, 60, 0.7), 0 0 40px rgba(255, 20, 60, 0.3);
    animation: su-title-flicker 3s ease-in-out infinite;
}
@keyframes su-title-flicker {
    0%, 96%, 100% { opacity: 1; }
    97%            { opacity: 0.6; }
    98%            { opacity: 1; }
    99%            { opacity: 0.4; }
}

.su-signal {
    width: 28px;
    height: 22px;
    color: #FF2040;
}

.su-rule {
    border: none;
    border-top: 1px solid rgba(220, 20, 50, 0.35);
}

/* ── Body ─────────────────────────────────────────────────────────────────── */
.su-body {
    display: grid;
    grid-template-columns: 1fr 220px;
    gap: 0;
    padding: 0;
    min-height: 360px;
}

/* ── Left column ──────────────────────────────────────────────────────────── */
.su-col-left {
    padding: 18px 20px 18px;
    border-right: 1px solid rgba(220, 20, 50, 0.15);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Progress section */
.su-progress-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.su-progress-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    letter-spacing: 0.12em;
    color: rgba(255, 180, 190, 0.7);
}

.su-progress-pct {
    color: #FF2040;
    text-shadow: 0 0 8px rgba(255, 20, 50, 0.5);
    min-width: 40px;
}

/* Segmented bar */
.su-bar-outer {
    display: flex;
    gap: 3px;
    height: 22px;
}

.su-bar-seg {
    flex: 1;
    border-radius: 1px;
    transition: background 0.2s ease;
}

.su-bar-seg--empty  { background: rgba(255, 255, 255, 0.06); }
.su-bar-seg--red    {
    background: #CC1030;
    box-shadow: 0 0 6px rgba(220, 10, 40, 0.6);
}
.su-bar-seg--orange {
    background: #E84010;
    box-shadow: 0 0 6px rgba(232, 64, 16, 0.5);
}
.su-bar-seg--amber  {
    background: #C06010;
    box-shadow: 0 0 4px rgba(180, 80, 10, 0.4);
}

/* Notification block */
.su-notif-block {
    font-size: 10px;
    color: rgba(180, 160, 170, 0.6);
    letter-spacing: 0.06em;
    line-height: 1.4;
}

.su-notif-bracket-top,
.su-notif-bracket-bot {
    color: rgba(220, 20, 50, 0.2);
    font-size: 9px;
    overflow: hidden;
    white-space: nowrap;
}

.su-notif-body {
    padding: 8px 10px;
}

.su-notif-tag {
    font-size: 10px;
    color: rgba(255, 80, 100, 0.85);
    letter-spacing: 0.12em;
    margin-bottom: 4px;
}

.su-notif-name {
    font-size: 11px;
    color: rgba(0, 255, 200, 0.75);
    letter-spacing: 0.08em;
    text-shadow: 0 0 8px rgba(0, 255, 180, 0.3);
}

/* Update log */
.su-log-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
}

.su-log-header {
    font-size: 9px;
    letter-spacing: 0.2em;
    color: rgba(220, 20, 50, 0.5);
    border-bottom: 1px solid rgba(220, 20, 50, 0.1);
    padding-bottom: 4px;
}

.su-log-lines {
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(220, 20, 50, 0.12);
    padding: 8px 10px;
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.su-log-line {
    font-size: 10px;
    letter-spacing: 0.05em;
    color: rgba(160, 140, 150, 0.75);
    line-height: 1.5;
    animation: su-line-in 0.1s ease forwards;
}
@keyframes su-line-in {
    from { opacity: 0; transform: translateX(-4px); }
    to   { opacity: 1; transform: none; }
}

.su-log-line--warn     { color: rgba(255, 160, 40, 0.9); }
.su-log-line--error    { color: rgba(255, 60, 60, 0.9); }
.su-log-line--complete { color: rgba(60, 255, 140, 0.95); text-shadow: 0 0 6px rgba(40,255,120,0.4); }

/* ── Right column ─────────────────────────────────────────────────────────── */
.su-col-right {
    padding: 22px 16px 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

/* Circular ring */
.su-ring-wrap {
    position: relative;
    width: 160px;
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.su-ring-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.su-ring-arc {
    stroke: #00FFC8;
    stroke-dasharray: 402.1;
    stroke-linecap: round;
    filter: drop-shadow(0 0 6px rgba(0, 255, 200, 0.7));
    transition: stroke-dashoffset 0.35s ease;
}

.su-ring-center {
    position: relative;
    text-align: center;
    z-index: 1;
}

.su-ring-status {
    font-size: 11px;
    letter-spacing: 0.16em;
    color: #00FFC8;
    text-shadow: 0 0 12px rgba(0, 255, 200, 0.6);
    animation: su-installing-pulse 1.2s ease-in-out infinite;
}
.su-ring-status--done {
    animation: none;
    color: rgba(60, 255, 140, 0.95);
    text-shadow: 0 0 14px rgba(40, 255, 120, 0.6);
}
@keyframes su-installing-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.55; }
}

.su-ring-eta {
    font-size: 12px;
    letter-spacing: 0.1em;
    color: rgba(0, 220, 180, 0.6);
    margin-top: 4px;
    font-variant-numeric: tabular-nums;
}

/* Warning line */
.su-warning {
    font-size: 9px;
    letter-spacing: 0.08em;
    color: rgba(255, 140, 30, 0.9);
    text-align: center;
    line-height: 1.5;
    display: flex;
    gap: 6px;
    align-items: flex-start;
    text-shadow: 0 0 6px rgba(255, 120, 20, 0.4);
    animation: su-warn-blink 0.9s ease-in-out infinite;
    min-height: 30px;
}
@keyframes su-warn-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

.su-warning-icon { flex-shrink: 0; }
</style>
