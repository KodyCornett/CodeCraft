<template>
    <div class="qm-overlay">
        <!-- Watcher interference — intensity scales with stability loss -->
        <GlitchEffect
            :type="glitchType"
            :intensity="glitchIntensity"
            :active="glitchActive"
            overlay
        />

        <div class="qm-scanline" />

        <!-- Header -->
        <div class="qm-header">
            <span class="qm-logo">◈ {{ gameTypeLabel }}</span>
            <span class="qm-file">{{ skin.fileName }}</span>
            <span class="qm-timer" :class="timerClass">{{ timeLeft.toFixed(1) }}s</span>
        </div>

        <!-- Primary bar — trace / signal load / absorption / etc. -->
        <div class="qm-bar-wrap">
            <div class="qm-bar-label">{{ skin.primaryBarLabel ?? 'TRACE' }}</div>
            <div class="qm-bar-track">
                <div class="qm-bar-fill qm-bar-fill--primary" :style="{ width: (primaryProgress * 100) + '%' }" />
            </div>
        </div>

        <!-- Stability bar — system heat / overload / integrity / etc. -->
        <div class="qm-bar-wrap qm-bar-wrap--stab">
            <div class="qm-bar-label qm-bar-label--stab">{{ skin.stabilityLabel ?? 'STABILITY' }}</div>
            <div class="qm-bar-track qm-bar-track--stab">
                <div
                    class="qm-bar-fill qm-bar-fill--stab"
                    :class="stabilityFillClass"
                    :style="{ width: (stability * 100) + '%' }"
                />
            </div>
            <div class="qm-stab-pct" :class="stabilityFillClass">{{ Math.round(stability * 100) }}%</div>
        </div>

        <!-- Stream — packets move left to right -->
        <div class="qm-stream" ref="streamEl">
            <template v-if="skin.winCondition !== 'endure'">
                <div
                    v-for="pkt in packets"
                    :key="pkt.id"
                    class="qm-packet"
                    :class="{
                        'qm-packet--target':   pkt.isTarget,
                        'qm-packet--captured': pkt.captured,
                        'qm-packet--decoy':    !pkt.isTarget && pkt.revealed,
                        'qm-packet--hostile':  pkt.hostile,
                    }"
                    :style="{ left: pkt.x + 'px', top: pkt.y + 'px' }"
                    @click="onPacketClick(pkt)"
                >
                    <span class="qm-pkt-icon">{{ pkt.isTarget ? '▣' : '▢' }}</span>
                    <span class="qm-pkt-label">{{ pkt.label }}</span>
                </div>
            </template>

            <!-- Endure mode: ambient noise particles instead of packets -->
            <template v-else>
                <div
                    v-for="n in noiseParticles"
                    :key="n.id"
                    class="qm-noise"
                    :style="{ left: n.x + 'px', top: n.y + 'px', opacity: n.opacity }"
                >{{ n.c }}</div>
            </template>

            <!-- Capture zone — shown for capture mode only -->
            <div v-if="skin.winCondition !== 'endure'" class="qm-capture-zone">
                <div class="qm-cz-label">BUFFER</div>
                <div class="qm-cz-slots">
                    <div
                        v-for="n in skin.targetsNeeded"
                        :key="n"
                        class="qm-cz-slot"
                        :class="{ 'qm-cz-slot--filled': capturedCount >= n }"
                    >{{ capturedCount >= n ? '▣' : '□' }}</div>
                </div>
                <div class="qm-cz-count">{{ capturedCount }} / {{ skin.targetsNeeded }}</div>
            </div>

            <!-- Endure mode: anchor progress ring -->
            <div v-else class="qm-anchor">
                <svg class="qm-anchor-ring" viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="34" class="qm-ring-track" />
                    <circle
                        cx="40" cy="40" r="34"
                        class="qm-ring-fill"
                        :style="{ strokeDasharray: RING_CIRCUMFERENCE, strokeDashoffset: ringOffset }"
                    />
                </svg>
                <div class="qm-anchor-label">ANCHOR</div>
                <div class="qm-anchor-pct">{{ Math.round(endureProgress * 100) }}%</div>
            </div>
        </div>

        <!-- Objective -->
        <div class="qm-objective">
            <span class="qm-obj-label">OBJECTIVE //</span>
            {{ skin.objectiveText }}
        </div>

        <!-- Result overlay -->
        <Transition name="qm-result">
            <div v-if="result" class="qm-result" :class="`qm-result--${result}`">
                <div class="qm-result-title">{{ resultTitle }}</div>
                <div class="qm-result-sub">{{ result === 'success' ? skin.successText : failReason }}</div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import GlitchEffect from '@/components/shared/GlitchEffect.vue';

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * skin — quest-specific configuration.
     *
     * Required fields:
     *   gameType:       'disconnect_layer' | 'flush_buffer' | 'toxic_soak'
     *                   | 'archive_extraction' | 'calibration_tether'
     *   fileName:       string   — displayed in header
     *   objectiveText:  string   — displayed at bottom
     *   successText:    string
     *   failText:       string
     *
     * Optional bar labels (defaults shown):
     *   primaryBarLabel:   string  (default: 'TRACE')
     *   stabilityLabel:    string  (default: 'STABILITY')
     *
     * Mechanic options:
     *   winCondition:   'capture' | 'endure'  (default: 'capture')
     *   targetsNeeded:  number  (capture mode, default: 3)
     *   endureDuration: number  (endure mode seconds, default: 20)
     *   timeLimit:      number  (total seconds, default: 30)
     *   difficulty:     1 | 2 | 3  (default: 1)
     */
    skin: {
        type: Object,
        default: () => ({
            gameType:        'disconnect_layer',
            fileName:        'UNKNOWN.dat',
            objectiveText:   'Capture the target data packets before the trace locks in.',
            successText:     'Data secured.',
            failText:        'Connection lost.',
            primaryBarLabel: 'TRACE',
            stabilityLabel:  'STABILITY',
            winCondition:    'capture',
            targetsNeeded:   3,
            timeLimit:       30,
            difficulty:      1,
        }),
    },
});

const emit = defineEmits(['complete', 'fail']);

// ── Difficulty tables ─────────────────────────────────────────────────────────

const DIFF = {
    1: { traceSpeed: 0.018, decoyRatio: 0.3, packetSpeed: 90,  spawnInterval: 1.8 },
    2: { traceSpeed: 0.025, decoyRatio: 0.5, packetSpeed: 120, spawnInterval: 1.4 },
    3: { traceSpeed: 0.040, decoyRatio: 0.6, packetSpeed: 150, spawnInterval: 1.0 },
};

// Per game type: base stability drain per second + per-event penalties
const STAB_CONFIG = {
    disconnect_layer:   { drainRate: 0.008, decoyPenalty: 0.05, missedPenalty: 0.0,  capturedDrain: false },
    flush_buffer:       { drainRate: 0.014, decoyPenalty: 0.03, missedPenalty: 0.02, capturedDrain: false },
    toxic_soak:         { drainRate: 0.022, decoyPenalty: 0.0,  missedPenalty: 0.0,  capturedDrain: false },
    archive_extraction: { drainRate: 0.006, decoyPenalty: 0.08, missedPenalty: 0.04, capturedDrain: false },
    calibration_tether: { drainRate: 0.004, decoyPenalty: 0.02, missedPenalty: 0.0,  capturedDrain: true  },
};

const diff     = computed(() => DIFF[props.skin.difficulty ?? 1]);
const stabConf = computed(() => STAB_CONFIG[props.skin.gameType ?? 'disconnect_layer']);

// ── Game state ────────────────────────────────────────────────────────────────

const packets        = ref([]);
const capturedCount  = ref(0);
const timeLeft       = ref(props.skin.timeLimit ?? 30);
const primaryProgress = ref(0);   // 0→1, fills to fail
const stability      = ref(1.0);  // 1→0, empties to fail
const endureProgress = ref(0);    // endure mode: 0→1, fills to win
const result         = ref(null); // null | 'success' | 'fail'
const failReason     = ref('');
const streamEl       = ref(null);
const noiseParticles = ref([]);

let animFrame  = null;
let lastTs     = null;
let pktIdSeq   = 0;
let noiseIdSeq = 0;
let spawnTimer = 0;

// ── Glitch state — driven by stability ───────────────────────────────────────

const glitchActive = computed(() => stability.value < 0.5 && !result.value);

const glitchType = computed(() => {
    if (stability.value < 0.15) return 'chromatic,bars,static';
    if (stability.value < 0.30) return 'chromatic,bars';
    return 'scan';
});

const glitchIntensity = computed(() => {
    if (stability.value >= 0.5) return 0;
    // 0.5 → 0 maps to intensity 0.1 → 1.0
    return 0.1 + (0.5 - stability.value) * 1.8;
});

// ── Stability fill class ──────────────────────────────────────────────────────

const stabilityFillClass = computed(() => {
    if (stability.value < 0.15) return 'qm-stab--critical';
    if (stability.value < 0.30) return 'qm-stab--warn';
    return '';
});

// ── Timer class ───────────────────────────────────────────────────────────────

const timerClass = computed(() => {
    if (timeLeft.value <= 5)  return 'qm-timer--critical';
    if (timeLeft.value <= 10) return 'qm-timer--warn';
    return '';
});

// ── Game type display label ───────────────────────────────────────────────────

const GAME_TYPE_LABELS = {
    disconnect_layer:   'DISCONNECT_LAYER',
    flush_buffer:       'FLUSH_BUFFER',
    toxic_soak:         'TOXIC_SOAK',
    archive_extraction: 'ARCHIVE_EXTRACTION',
    calibration_tether: 'CALIBRATION_TETHER',
};

const gameTypeLabel = computed(() => GAME_TYPE_LABELS[props.skin.gameType] ?? 'HACK_SEQUENCE');

// ── Result title ──────────────────────────────────────────────────────────────

const resultTitle = computed(() => {
    if (result.value === 'success') return 'SEQUENCE COMPLETE';
    return failReason.value.includes('STABILITY') ? 'SYSTEM CRITICAL — ABORT' : 'TRACE LOCKED — ABORT';
});

// ── Endure: anchor ring ───────────────────────────────────────────────────────

const RING_CIRCUMFERENCE = 2 * Math.PI * 34; // r=34 from SVG

const ringOffset = computed(() => {
    return RING_CIRCUMFERENCE * (1 - endureProgress.value);
});

// ── Packet labels ─────────────────────────────────────────────────────────────

const TARGET_LABELS = ['PKT_DATA', 'PAYLOAD', 'CHUNK', 'FRAGMENT', 'BLOCK'];
const DECOY_LABELS  = ['NOISE', 'DECOY', 'NULL', 'JUNK', 'FILLER', 'EMPTY'];

// Game-type-specific target labels
const GAME_TARGET_LABELS = {
    flush_buffer:       ['CORRUPT_PKT', 'GHOST_SIG', 'OVERFLOW', 'BAD_DATA', 'LEAKAGE'],
    archive_extraction: ['ARCHIVE', 'DEEP_PKT', 'RESTRICTED', 'CLASSIFIED', 'SEALED'],
    calibration_tether: ['SUB_ROUTINE', 'VOLATILE', 'UNSTABLE', 'HAZARD', 'PAYLOAD'],
};

function getTargetLabel() {
    const labels = GAME_TARGET_LABELS[props.skin.gameType] ?? TARGET_LABELS;
    return labels[Math.floor(Math.random() * labels.length)];
}

// ── Spawn packet ──────────────────────────────────────────────────────────────

function spawnPacket() {
    const h        = streamEl.value?.clientHeight ?? 200;
    const isTarget = Math.random() > diff.value.decoyRatio;
    packets.value.push({
        id:       pktIdSeq++,
        isTarget,
        label:    isTarget ? getTargetLabel() : DECOY_LABELS[Math.floor(Math.random() * DECOY_LABELS.length)],
        x:        -60,
        y:        20 + Math.random() * (h - 80),
        speed:    diff.value.packetSpeed * (0.8 + Math.random() * 0.4),
        captured: false,
        revealed: false,
    });
}

// ── Spawn noise particles (endure mode) ───────────────────────────────────────

const NOISE_CHARS = '01░▒▓▄▀█▌▐';

function spawnNoise() {
    const w = streamEl.value?.clientWidth  ?? 600;
    const h = streamEl.value?.clientHeight ?? 200;
    noiseParticles.value.push({
        id:      noiseIdSeq++,
        x:       Math.random() * w,
        y:       Math.random() * h,
        c:       NOISE_CHARS[Math.floor(Math.random() * NOISE_CHARS.length)],
        opacity: 0.1 + Math.random() * 0.4,
        life:    0.6 + Math.random() * 0.8,
    });
    // keep pool small
    if (noiseParticles.value.length > 40) noiseParticles.value.shift();
}

// ── Click handler ─────────────────────────────────────────────────────────────

function onPacketClick(pkt) {
    if (pkt.captured || result.value) return;

    pkt.revealed = true;

    if (pkt.isTarget) {
        pkt.captured = true;
        capturedCount.value++;

        if (capturedCount.value >= (props.skin.targetsNeeded ?? 3)) {
            endGame('success');
        }
    } else {
        // Decoy click drains stability
        applyStabilityHit(stabConf.value.decoyPenalty);
    }
}

function applyStabilityHit(amount) {
    stability.value = Math.max(0, stability.value - amount);
}

// ── Game loop ─────────────────────────────────────────────────────────────────

function tick(ts) {
    if (result.value) return;

    const dt  = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    const w = (streamEl.value?.clientWidth  ?? 600) - 80;
    const h = streamEl.value?.clientHeight ?? 200;

    if (props.skin.winCondition === 'endure') {
        // ── Endure mode ────────────────────────────────────────────────────────
        endureProgress.value = Math.min(1, endureProgress.value + dt / (props.skin.endureDuration ?? 20));

        // Tick noise particles
        spawnTimer -= dt;
        if (spawnTimer <= 0) {
            spawnNoise();
            spawnTimer = 0.12;
        }
        // Decay noise particle life
        noiseParticles.value.forEach(n => { n.life -= dt; });
        noiseParticles.value = noiseParticles.value.filter(n => n.life > 0);

        if (endureProgress.value >= 1) {
            endGame('success');
            return;
        }
    } else {
        // ── Capture mode ───────────────────────────────────────────────────────
        packets.value = packets.value.filter(pkt => {
            if (pkt.captured) return true;
            pkt.x += pkt.speed * dt;
            if (pkt.x >= w) {
                // Packet escaped — penalise if it was a target
                if (pkt.isTarget && stabConf.value.missedPenalty > 0) {
                    applyStabilityHit(stabConf.value.missedPenalty);
                }
                return false;
            }
            return true;
        });

        spawnTimer -= dt;
        if (spawnTimer <= 0) {
            spawnPacket();
            spawnTimer = diff.value.spawnInterval * (0.8 + Math.random() * 0.4);
        }
    }

    // ── Primary progress bar (trace / signal / etc.) ──────────────────────────
    primaryProgress.value = Math.min(1, primaryProgress.value + diff.value.traceSpeed * dt);

    // ── Stability drain ────────────────────────────────────────────────────────
    let drain = stabConf.value.drainRate;
    // calibration_tether: drain scales with items in buffer
    if (stabConf.value.capturedDrain) {
        drain += stabConf.value.drainRate * capturedCount.value;
    }
    stability.value = Math.max(0, stability.value - drain * dt);

    // ── Timer ──────────────────────────────────────────────────────────────────
    timeLeft.value = Math.max(0, timeLeft.value - dt);

    // ── Fail conditions ────────────────────────────────────────────────────────
    if (primaryProgress.value >= 1 || timeLeft.value <= 0) {
        failReason.value = props.skin.failText ?? 'Trace complete. Connection lost.';
        endGame('fail');
        return;
    }
    if (stability.value <= 0) {
        failReason.value = '[STABILITY CRITICAL] — ' + (props.skin.failText ?? 'System failure.');
        endGame('fail');
        return;
    }

    animFrame = requestAnimationFrame(tick);
}

// ── End game ──────────────────────────────────────────────────────────────────

function endGame(outcome) {
    result.value = outcome;
    cancelAnimationFrame(animFrame);

    setTimeout(() => {
        if (outcome === 'success') emit('complete');
        else                       emit('fail');
    }, 2200);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    if (props.skin.winCondition !== 'endure') {
        spawnPacket();
    }
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
/* ── Overlay ─────────────────────────────────────────────────────────────────── */
.qm-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: #010a06;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}
.qm-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg, transparent, transparent 2px,
        rgba(0,255,100,0.01) 2px, rgba(0,255,100,0.01) 4px
    );
    pointer-events: none;
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.qm-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(0,255,100,0.1);
    position: relative;
    z-index: 1;
}
.qm-logo  { color: #00ff9d; font-size: 13px; font-weight: 700; letter-spacing: 0.15em; }
.qm-file  { color: #4a9a7a; font-size: 11px; flex: 1; }
.qm-timer { font-size: 18px; font-weight: 700; color: #00ff9d; letter-spacing: 0.1em; }
.qm-timer--warn     { color: #FFB300; }
.qm-timer--critical { color: #FF3333; animation: qm-blink 0.5s steps(1) infinite; }
@keyframes qm-blink { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }

/* ── Bars ────────────────────────────────────────────────────────────────────── */
.qm-bar-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 20px;
    border-bottom: 1px solid rgba(255,0,0,0.08);
    position: relative;
    z-index: 1;
}
.qm-bar-wrap--stab {
    border-bottom-color: rgba(0,255,100,0.06);
}
.qm-bar-label {
    font-size: 8px;
    color: rgba(255,50,50,0.5);
    letter-spacing: 0.15em;
    width: 80px;
    flex-shrink: 0;
}
.qm-bar-label--stab {
    color: rgba(0,255,100,0.35);
}
.qm-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(255,0,0,0.08);
    position: relative;
    overflow: hidden;
}
.qm-bar-track--stab {
    background: rgba(0,255,100,0.06);
}
.qm-bar-fill--primary {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    background: linear-gradient(90deg, #660000, #ff3333);
    transition: width 0.1s linear;
    box-shadow: 0 0 8px rgba(255,0,0,0.4);
}
.qm-bar-fill--stab {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    background: linear-gradient(90deg, #003322, #00ff9d);
    transition: width 0.1s linear;
    box-shadow: 0 0 6px rgba(0,255,100,0.3);
}
.qm-bar-fill--stab.qm-stab--warn     { background: linear-gradient(90deg, #332200, #FFB300); box-shadow: 0 0 6px rgba(255,179,0,0.4); }
.qm-bar-fill--stab.qm-stab--critical { background: linear-gradient(90deg, #330000, #ff3333); box-shadow: 0 0 8px rgba(255,0,0,0.5); animation: qm-blink 0.4s steps(1) infinite; }

.qm-stab-pct {
    font-size: 9px;
    color: rgba(0,255,100,0.4);
    width: 32px;
    text-align: right;
    letter-spacing: 0.05em;
    flex-shrink: 0;
}
.qm-stab-pct.qm-stab--warn     { color: rgba(255,179,0,0.7); }
.qm-stab-pct.qm-stab--critical { color: rgba(255,50,50,0.9); }

/* ── Stream ───────────────────────────────────────────────────────────────────── */
.qm-stream {
    flex: 1;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid rgba(0,255,100,0.06);
}

/* ── Packets ──────────────────────────────────────────────────────────────────── */
.qm-packet {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border: 1px solid rgba(0,255,100,0.12);
    background: rgba(0,10,6,0.9);
    cursor: pointer;
    user-select: none;
    transition: border-color 0.1s, background 0.1s;
}
.qm-packet:hover             { border-color: rgba(0,255,100,0.4); }
.qm-packet--target           { border-color: rgba(0,255,100,0.25); }
.qm-packet--target:hover     { background: rgba(0,255,100,0.08); }
.qm-packet--captured         { border-color: #00ff9d; background: rgba(0,255,100,0.12); opacity: 0.5; cursor: default; }
.qm-packet--decoy            { border-color: rgba(255,50,50,0.3); background: rgba(255,0,0,0.04); cursor: default; }
.qm-packet--hostile          { border-color: rgba(255,120,0,0.4); }

.qm-pkt-icon { font-size: 10px; color: rgba(0,255,100,0.5); }
.qm-packet--target   .qm-pkt-icon { color: #00ff9d; }
.qm-packet--captured .qm-pkt-icon { color: #00ff9d; }
.qm-packet--decoy    .qm-pkt-icon { color: rgba(255,50,50,0.4); }
.qm-packet--hostile  .qm-pkt-icon { color: rgba(255,120,0,0.6); }

.qm-pkt-label { font-size: 9px; color: rgba(0,255,100,0.4); letter-spacing: 0.08em; }
.qm-packet--target   .qm-pkt-label { color: rgba(0,255,100,0.7); }
.qm-packet--hostile  .qm-pkt-label { color: rgba(255,120,0,0.5); }

/* ── Capture zone ─────────────────────────────────────────────────────────────── */
.qm-capture-zone {
    position: absolute;
    right: 0; top: 0; bottom: 0;
    width: 90px;
    background: rgba(0,255,100,0.03);
    border-left: 1px solid rgba(0,255,100,0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.qm-cz-label { font-size: 8px; color: rgba(0,255,100,0.3); letter-spacing: 0.1em; }
.qm-cz-slots { display: flex; flex-direction: column; gap: 4px; }
.qm-cz-slot  { font-size: 14px; color: rgba(0,255,100,0.2); text-align: center; transition: color 0.2s; }
.qm-cz-slot--filled { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.6); }
.qm-cz-count { font-size: 9px; color: rgba(0,255,100,0.3); }

/* ── Endure: noise particles ──────────────────────────────────────────────────── */
.qm-noise {
    position: absolute;
    font-size: 11px;
    color: rgba(0,255,100,0.5);
    pointer-events: none;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Endure: anchor ring ──────────────────────────────────────────────────────── */
.qm-anchor {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.qm-anchor-ring {
    width: 80px;
    height: 80px;
}
.qm-ring-track {
    fill: none;
    stroke: rgba(0,255,100,0.08);
    stroke-width: 4;
}
.qm-ring-fill {
    fill: none;
    stroke: #00ff9d;
    stroke-width: 4;
    stroke-linecap: round;

    transition: stroke-dashoffset 0.2s linear;
    filter: drop-shadow(0 0 4px rgba(0,255,100,0.5));
    transform: rotate(-90deg);
    transform-origin: center;
}
.qm-anchor-label { font-size: 8px; color: rgba(0,255,100,0.3); letter-spacing: 0.1em; }
.qm-anchor-pct   { font-size: 11px; color: rgba(0,255,100,0.6); }

/* ── Objective ───────────────────────────────────────────────────────────────── */
.qm-objective {
    padding: 8px 20px;
    font-size: 10px;
    color: rgba(0,255,100,0.3);
    position: relative;
    z-index: 1;
}
.qm-obj-label { color: rgba(0,255,100,0.15); letter-spacing: 0.1em; margin-right: 6px; }

/* ── Result overlay ──────────────────────────────────────────────────────────── */
.qm-result {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    z-index: 100;
}
.qm-result--success { background: rgba(0,20,10,0.95); }
.qm-result--fail    { background: rgba(20,0,0,0.95); }

.qm-result-title {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.2em;
}
.qm-result--success .qm-result-title { color: #00ff9d; text-shadow: 0 0 30px rgba(0,255,100,0.4); }
.qm-result--fail    .qm-result-title { color: #ff3333; text-shadow: 0 0 30px rgba(255,0,0,0.3); }

.qm-result-sub {
    font-size: 11px;
    color: rgba(160,200,180,0.5);
    max-width: 400px;
    text-align: center;
    white-space: pre-wrap;
}

.qm-result-enter-active { transition: opacity 0.3s ease; }
.qm-result-enter-from   { opacity: 0; }
</style>
