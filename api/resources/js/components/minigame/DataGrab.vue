<template>
    <div class="dg-overlay">
        <div class="dg-scanline" />

        <!-- Header -->
        <div class="dg-header">
            <span class="dg-logo">◈ DATA_GRAB</span>
            <span class="dg-file">{{ skin.fileName }}</span>
            <span class="dg-timer" :class="timerClass">{{ timeLeft.toFixed(1) }}s</span>
        </div>

        <!-- Trace bar -->
        <div class="dg-trace-wrap">
            <div class="dg-trace-label">TRACE</div>
            <div class="dg-trace-track">
                <div class="dg-trace-fill" :style="{ width: (traceProgress * 100) + '%' }" />
            </div>
        </div>

        <!-- Stream area — packets move left across here -->
        <div class="dg-stream" ref="streamEl">
            <div
                v-for="pkt in packets"
                :key="pkt.id"
                class="dg-packet"
                :class="{
                    'dg-packet--target':   pkt.isTarget,
                    'dg-packet--captured': pkt.captured,
                    'dg-packet--decoy':    !pkt.isTarget && pkt.revealed,
                }"
                :style="{ left: pkt.x + 'px', top: pkt.y + 'px' }"
                @click="onPacketClick(pkt)"
            >
                <span class="dg-pkt-icon">{{ pkt.isTarget ? '▣' : '▢' }}</span>
                <span class="dg-pkt-label">{{ pkt.label }}</span>
            </div>

            <!-- Capture zone — right side, fixed -->
            <div class="dg-capture-zone">
                <div class="dg-cz-label">BUFFER</div>
                <div class="dg-cz-slots">
                    <div
                        v-for="i in skin.targetsNeeded"
                        :key="i"
                        class="dg-cz-slot"
                        :class="{ 'dg-cz-slot--filled': capturedCount >= i }"
                    >{{ capturedCount >= i ? '▣' : '□' }}</div>
                </div>
                <div class="dg-cz-count">{{ capturedCount }} / {{ skin.targetsNeeded }}</div>
            </div>
        </div>

        <!-- Objective -->
        <div class="dg-objective">
            <span class="dg-obj-label">OBJECTIVE //</span>
            {{ skin.objectiveText }}
        </div>

        <!-- Result overlays -->
        <Transition name="dg-result">
            <div v-if="result" class="dg-result" :class="`dg-result--${result}`">
                <div class="dg-result-title">{{ result === 'success' ? 'TRANSFER COMPLETE' : 'TRACE LOCKED — ABORT' }}</div>
                <div class="dg-result-sub">{{ result === 'success' ? skin.successText : skin.failText }}</div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * skin — quest-specific flavour data.
     * {
     *   fileName:     string,  // e.g. "V09_VULN_MAP.dat"
     *   objectiveText: string, // brief display line
     *   successText:  string,
     *   failText:     string,
     *   targetsNeeded: number, // how many target packets to capture (1–4)
     *   timeLimit:    number,  // seconds (default 30)
     *   difficulty:   number,  // 1–3, controls trace speed and decoy ratio
     * }
     */
    skin: {
        type: Object,
        default: () => ({
            fileName:      'UNKNOWN.dat',
            objectiveText: 'Capture the target data packets before the trace locks in.',
            successText:   'Data secured.',
            failText:      'Trace complete. Data lost.',
            targetsNeeded: 3,
            timeLimit:     30,
            difficulty:    1,
        }),
    },
});

const emit = defineEmits(['complete', 'fail']);

// ── State ─────────────────────────────────────────────────────────────────────

const packets      = ref([]);
const capturedCount = ref(0);
const timeLeft     = ref(props.skin.timeLimit ?? 30);
const traceProgress = ref(0);
const result       = ref(null);   // null | 'success' | 'fail'
const streamEl     = ref(null);

let animFrame   = null;
let lastTs      = null;
let pktIdSeq    = 0;
let streamW     = 600;
let streamH     = 200;

// Difficulty settings
const DIFF = {
    1: { traceSpeed: 0.018, decoyRatio: 0.3, packetSpeed: 90,  spawnInterval: 1.8 },
    2: { traceSpeed: 0.025, decoyRatio: 0.5, packetSpeed: 120, spawnInterval: 1.4 },
    3: { traceSpeed: 0.040, decoyRatio: 0.6, packetSpeed: 150, spawnInterval: 1.0 },
};
const diff = DIFF[props.skin.difficulty ?? 1];

let spawnTimer = 0;

// ── Timer class ───────────────────────────────────────────────────────────────
const timerClass = computed(() => {
    if (timeLeft.value <= 5)  return 'dg-timer--critical';
    if (timeLeft.value <= 10) return 'dg-timer--warn';
    return '';
});

// ── Packet labels ─────────────────────────────────────────────────────────────
const TARGET_LABELS = ['PKT_DATA', 'PAYLOAD', 'CHUNK', 'FRAGMENT', 'BLOCK'];
const DECOY_LABELS  = ['NOISE', 'DECOY', 'NULL', 'JUNK', 'FILLER', 'EMPTY'];

function spawnPacket() {
    const h          = streamEl.value?.clientHeight ?? streamH;
    const isTarget   = Math.random() > diff.decoyRatio;
    const label      = isTarget
        ? TARGET_LABELS[Math.floor(Math.random() * TARGET_LABELS.length)]
        : DECOY_LABELS[Math.floor(Math.random() * DECOY_LABELS.length)];

    packets.value.push({
        id:       pktIdSeq++,
        isTarget,
        label,
        x:        -60,
        y:        20 + Math.random() * (h - 60),
        speed:    diff.packetSpeed * (0.8 + Math.random() * 0.4),
        captured: false,
        revealed: false,
    });
}

// ── Click handler ─────────────────────────────────────────────────────────────
function onPacketClick(pkt) {
    if (pkt.captured || result.value) return;

    pkt.revealed = true;

    if (pkt.isTarget) {
        pkt.captured = true;
        capturedCount.value++;

        if (capturedCount.value >= props.skin.targetsNeeded) {
            endGame('success');
        }
    }
    // Decoy clicks do nothing except reveal the packet as decoy
}

// ── Game loop ─────────────────────────────────────────────────────────────────
function tick(ts) {
    if (result.value) return;

    const dt = lastTs ? (ts - lastTs) / 1000 : 0;
    lastTs = ts;

    // Move packets
    const maxX = (streamEl.value?.clientWidth ?? streamW) - 80;
    packets.value = packets.value.filter(pkt => {
        if (pkt.captured) return true;
        pkt.x += pkt.speed * dt;
        return pkt.x < maxX;  // remove when off screen
    });

    // Spawn new packets
    spawnTimer -= dt;
    if (spawnTimer <= 0) {
        spawnPacket();
        spawnTimer = diff.spawnInterval;
    }

    // Advance trace
    traceProgress.value = Math.min(1, traceProgress.value + diff.traceSpeed * dt);
    timeLeft.value      = Math.max(0, timeLeft.value - dt);

    if (traceProgress.value >= 1 || timeLeft.value <= 0) {
        endGame('fail');
        return;
    }

    animFrame = requestAnimationFrame(tick);
}

function endGame(outcome) {
    result.value = outcome;
    if (animFrame) cancelAnimationFrame(animFrame);

    setTimeout(() => {
        if (outcome === 'success') emit('complete');
        else                       emit('fail');
    }, 2000);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
    streamW = streamEl.value?.clientWidth  ?? 600;
    streamH = streamEl.value?.clientHeight ?? 200;
    spawnPacket(); // seed one immediately
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
/* ── Overlay ─────────────────────────────────────────────────────────────────── */
.dg-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: #010a06;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}
.dg-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px,
        rgba(0,255,100,0.01) 2px, rgba(0,255,100,0.01) 4px);
    pointer-events: none;
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.dg-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(0,255,100,0.1);
    position: relative;
    z-index: 1;
}
.dg-logo  { color: #00ff9d; font-size: 13px; font-weight: 700; letter-spacing: 0.15em; }
.dg-file  { color: #4a9a7a; font-size: 11px; flex: 1; }
.dg-timer { font-size: 18px; font-weight: 700; color: #00ff9d; letter-spacing: 0.1em; }
.dg-timer--warn     { color: #FFB300; }
.dg-timer--critical { color: #FF3333; animation: dg-blink 0.5s steps(1) infinite; }
@keyframes dg-blink { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }

/* ── Trace bar ────────────────────────────────────────────────────────────────── */
.dg-trace-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 20px;
    border-bottom: 1px solid rgba(255,0,0,0.1);
    position: relative;
    z-index: 1;
}
.dg-trace-label { font-size: 8px; color: rgba(255,50,50,0.5); letter-spacing: 0.15em; width: 40px; }
.dg-trace-track {
    flex: 1;
    height: 4px;
    background: rgba(255,0,0,0.08);
    position: relative;
    overflow: hidden;
}
.dg-trace-fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    background: linear-gradient(90deg, #660000, #ff3333);
    transition: width 0.1s linear;
    box-shadow: 0 0 8px rgba(255,0,0,0.4);
}

/* ── Stream ───────────────────────────────────────────────────────────────────── */
.dg-stream {
    flex: 1;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid rgba(0,255,100,0.06);
}

/* ── Packets ──────────────────────────────────────────────────────────────────── */
.dg-packet {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border: 1px solid rgba(0,255,100,0.12);
    background: rgba(0,10,6,0.9);
    cursor: pointer;
    transition: border-color 0.1s, background 0.1s;
    user-select: none;
}
.dg-packet:hover          { border-color: rgba(0,255,100,0.4); }
.dg-packet--target        { border-color: rgba(0,255,100,0.25); }
.dg-packet--target:hover  { background: rgba(0,255,100,0.08); }
.dg-packet--captured      { border-color: #00ff9d; background: rgba(0,255,100,0.12); opacity: 0.5; cursor: default; }
.dg-packet--decoy         { border-color: rgba(255,50,50,0.3); background: rgba(255,0,0,0.04); cursor: default; }

.dg-pkt-icon { font-size: 10px; color: rgba(0,255,100,0.5); }
.dg-packet--target  .dg-pkt-icon { color: #00ff9d; }
.dg-packet--captured .dg-pkt-icon { color: #00ff9d; }
.dg-packet--decoy   .dg-pkt-icon { color: rgba(255,50,50,0.4); }

.dg-pkt-label { font-size: 9px; color: rgba(0,255,100,0.4); letter-spacing: 0.08em; }
.dg-packet--target .dg-pkt-label { color: rgba(0,255,100,0.7); }

/* ── Capture zone ─────────────────────────────────────────────────────────────── */
.dg-capture-zone {
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
.dg-cz-label { font-size: 8px; color: rgba(0,255,100,0.3); letter-spacing: 0.1em; }
.dg-cz-slots { display: flex; flex-direction: column; gap: 4px; }
.dg-cz-slot  { font-size: 14px; color: rgba(0,255,100,0.2); text-align: center; }
.dg-cz-slot--filled { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.6); }
.dg-cz-count { font-size: 9px; color: rgba(0,255,100,0.3); }

/* ── Objective ───────────────────────────────────────────────────────────────── */
.dg-objective {
    padding: 8px 20px;
    font-size: 10px;
    color: rgba(0,255,100,0.3);
    position: relative; z-index: 1;
}
.dg-obj-label { color: rgba(0,255,100,0.15); letter-spacing: 0.1em; margin-right: 6px; }

/* ── Result overlay ──────────────────────────────────────────────────────────── */
.dg-result {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    z-index: 100;
}
.dg-result--success { background: rgba(0,20,10,0.95); }
.dg-result--fail    { background: rgba(20,0,0,0.95); }

.dg-result-title {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.2em;
}
.dg-result--success .dg-result-title { color: #00ff9d; text-shadow: 0 0 30px rgba(0,255,100,0.4); }
.dg-result--fail    .dg-result-title { color: #ff3333; text-shadow: 0 0 30px rgba(255,0,0,0.3); }

.dg-result-sub { font-size: 11px; color: rgba(160,200,180,0.5); }

.dg-result-enter-active { transition: opacity 0.3s ease; }
.dg-result-enter-from   { opacity: 0; }
</style>
