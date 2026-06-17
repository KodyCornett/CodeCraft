<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ae-wrap">

            <!-- Archive header -->
            <div class="ae-header-row">
                <span class="ae-node-label">ARCHIVE NODE // UD-v17 // PACKET EXTRACTION</span>
                <span class="ae-block-counter">{{ blocksExtracted }}/{{ TOTAL_BLOCKS }} BLOCKS</span>
            </div>

            <!-- Extraction progress bar -->
            <div class="ae-extraction-row">
                <span class="ae-ext-label">EXTRACTION</span>
                <div class="ae-ext-track">
                    <div class="ae-ext-fill" :style="{ width: (extractionProgress * 100) + '%' }" />
                </div>
                <span class="ae-ext-pct">{{ Math.round(extractionProgress * 100) }}%</span>
            </div>

            <!-- Data block grid -->
            <div class="ae-blocks">
                <div
                    v-for="i in TOTAL_BLOCKS"
                    :key="i"
                    class="ae-block"
                    :class="i <= blocksExtracted ? 'ae-block--pulled' : 'ae-block--pending'"
                >{{ blockLabels[i - 1] }}</div>
            </div>

            <!-- ICE monitor header -->
            <div class="ae-monitor-row">
                <span class="ae-monitor-label">ICE MONITOR</span>
                <span class="ae-monitor-count" :class="probes.length > 0 ? 'ae-count--active' : ''">
                    {{ probes.length }} ACTIVE
                </span>
                <span v-if="diffLevel === 2" class="ae-monitor-hint">// READ FULL SUFFIX TO CLASSIFY</span>
                <span v-if="diffLevel === 3" class="ae-monitor-hint">// SRC FORMAT IDENTIFIES ORIGIN</span>
            </div>

            <!-- Probe feed -->
            <div class="ae-probe-feed">
                <TransitionGroup name="ae-probe">
                    <div
                        v-for="probe in probes"
                        :key="probe.id"
                        class="ae-probe"
                        :class="{ 'ae-probe--urgent': probe.ttlFraction < 0.30 }"
                    >
                        <div class="ae-probe-top">
                            <span class="ae-probe-id">{{ probe.id }}</span>
                            <span class="ae-probe-class">{{ probe.classLabel }}</span>
                            <span
                                v-if="diffLevel === 1"
                                class="ae-probe-tag"
                                :class="probe.isReal ? 'ae-tag--real' : 'ae-tag--ghost'"
                            >{{ probe.isReal ? '[ ICE ]' : '[ GHOST ]' }}</span>
                        </div>
                        <div class="ae-probe-sig">
                            SRC: <span class="ae-src-val">{{ probe.src }}</span>
                            &nbsp;// VEC: <span class="ae-vec-val">{{ probe.vec }}</span>
                        </div>
                        <div class="ae-probe-controls">
                            <div class="ae-ttl-track">
                                <div
                                    class="ae-ttl-fill"
                                    :class="probe.ttlFraction < 0.30 ? 'ae-ttl--urgent' : ''"
                                    :style="{ width: (probe.ttlFraction * 100) + '%' }"
                                />
                            </div>
                            <button class="ae-suppress-btn" @click="onSuppress(probe.id)">[ SUPPRESS ]</button>
                        </div>
                    </div>
                </TransitionGroup>

                <div v-if="probes.length === 0" class="ae-clear-msg">
                    // NO ACTIVE SIGNALS — EXTRACTION WINDOW CLEAR
                </div>
            </div>

            <!-- Penalty banners -->
            <Transition name="ae-err">
                <div v-if="ghostFlash" class="ae-err-banner">
                    ⚠ GHOST SIGNAL SUPPRESSED — NOISE SPIKE GENERATED
                </div>
            </Transition>
            <Transition name="ae-err">
                <div v-if="realMissFlash" class="ae-err-banner ae-err-banner--trace">
                    ⚠ ICE PROBE COMPLETED — TRACE VECTOR UPDATED
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

// ── Difficulty config ─────────────────────────────────────────────────────────

const CONFIGS = {
    1: {
        extractionDuration: 45,
        spawnInterval:      { min: 3.0, max: 5.0 },
        ttlDuration:        { min: 4.5, max: 6.5 },
        maxActive:          2,
        traceSpikeReal:     0.18,
        stabilityHitGhost:  0.15,
        traceBaseRate:      0.004,
        stabilityDrain:     0.004,
        classStyle:         'distinct',
    },
    2: {
        extractionDuration: 50,
        spawnInterval:      { min: 2.0, max: 3.5 },
        ttlDuration:        { min: 2.5, max: 4.0 },
        maxActive:          3,
        traceSpikeReal:     0.22,
        stabilityHitGhost:  0.18,
        traceBaseRate:      0.006,
        stabilityDrain:     0.006,
        classStyle:         'similar',
    },
    3: {
        extractionDuration: 55,
        spawnInterval:      { min: 1.2, max: 2.2 },
        ttlDuration:        { min: 1.0, max: 1.6 },
        maxActive:          4,
        traceSpikeReal:     0.28,
        stabilityHitGhost:  0.22,
        traceBaseRate:      0.009,
        stabilityDrain:     0.008,
        classStyle:         'src_only',
    },
};

// D1 class labels: clearly distinct words
const CLASS_REAL_DISTINCT  = ['ICE_SWEEP', 'ICE_PING', 'ICE_TRACE'];
const CLASS_GHOST_DISTINCT = ['GHOST_ECHO', 'GHOST_ARTIFACT', 'GHOST_BLEED'];

// D2 class labels: share prefix, differ by suffix — must read carefully
const CLASS_REAL_SIMILAR   = ['SWEEP_ACTIVE', 'PING_ACTIVE', 'TRACE_ACTIVE'];
const CLASS_GHOST_SIMILAR  = ['SWEEP_ECHO', 'PING_RESIDUAL', 'TRACE_DECAY'];

// D3: all identical — distinguish solely by SRC format
const CLASS_SRC_ONLY = 'PROBE_SIGNAL';

const diffLevel = props.skin.difficulty ?? 1;
const config    = CONFIGS[diffLevel] ?? CONFIGS[1];

// ── Shared minigame state ─────────────────────────────────────────────────────
// We drive stability and primaryProgress manually — no tickShared.

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    applyHit, endGame,
} = useQuestMinigameState(props.skin);

// Override timeLeft to extraction duration (not skin.timeLimit which is the trace bar timer)
timeLeft.value = config.extractionDuration;

// ── Extraction state ──────────────────────────────────────────────────────────

const TOTAL_BLOCKS      = 24;
const extractionProgress = ref(0);

const blocksExtracted = computed(() =>
    Math.min(TOTAL_BLOCKS, Math.floor(extractionProgress.value * TOTAL_BLOCKS))
);

// Pre-generate random hex labels for the data block grid
const blockLabels = Array.from({ length: TOTAL_BLOCKS }, () =>
    Math.random().toString(16).substr(2, 4).toUpperCase()
);

// ── Probe state ───────────────────────────────────────────────────────────────

let probeCounter = 0;
const probes     = ref([]);
const ghostFlash     = ref(false);
const realMissFlash  = ref(false);

function randByte() { return String(Math.floor(Math.random() * 256)).padStart(3, '0'); }
function randHex(n) {
    return Math.random().toString(16).substr(2, n).toUpperCase().padStart(n, '0');
}
function randRange(min, max) { return min + Math.random() * (max - min); }
function pick(arr) { return arr[Math.floor(Math.random() * arr.length)]; }

function generateProbe() {
    const isReal = Math.random() < 0.55; // slight majority real so player has decisions to make
    const id     = `SIG_${(++probeCounter).toString().padStart(3, '0')}`;
    const ttl    = randRange(config.ttlDuration.min, config.ttlDuration.max);

    let classLabel;
    if (config.classStyle === 'distinct') {
        classLabel = isReal ? pick(CLASS_REAL_DISTINCT) : pick(CLASS_GHOST_DISTINCT);
    } else if (config.classStyle === 'similar') {
        classLabel = isReal ? pick(CLASS_REAL_SIMILAR) : pick(CLASS_GHOST_SIMILAR);
    } else {
        classLabel = CLASS_SRC_ONLY;
    }

    // SRC — real: corporate ARC.10.xxx.xxx format; ghost: GHK.00.xxx.xxx
    const src = isReal
        ? `ARC.10.${randByte()}.${randByte()}`
        : `GHK.00.${randByte()}.${randByte()}`;

    const vec = `${randHex(2)}.${randHex(2)}`;

    return { id, isReal, classLabel, src, vec, ttlMax: ttl, ttlLeft: ttl, ttlFraction: 1.0 };
}

// ── Player interaction ────────────────────────────────────────────────────────

function onSuppress(id) {
    if (result.value) return;
    const idx = probes.value.findIndex(p => p.id === id);
    if (idx === -1) return;

    const probe = probes.value[idx];
    probes.value.splice(idx, 1);

    if (!probe.isReal) {
        // Suppressing a ghost — stability hit (noise spike)
        applyHit(config.stabilityHitGhost);
        ghostFlash.value = true;
        setTimeout(() => { ghostFlash.value = false; }, 1200);
    }
    // Suppressing a real probe — correct, no penalty
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

let animFrame     = null;
let lastTs        = null;
let spawnTimer    = randRange(config.spawnInterval.min, config.spawnInterval.max);

function tick(ts) {
    if (result.value) return;

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    // Extraction progress — advances over extractionDuration
    extractionProgress.value = Math.min(
        1,
        extractionProgress.value + dt / config.extractionDuration
    );

    // Win — archive fully extracted
    if (extractionProgress.value >= 1) {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
        return;
    }

    // Countdown timer (drives the header display)
    timeLeft.value = Math.max(0, timeLeft.value - dt);

    // Passive stability drain
    stability.value = Math.max(0, stability.value - config.stabilityDrain * dt);

    // Passive trace (ICE detection) advance
    primaryProgress.value = Math.min(1, primaryProgress.value + config.traceBaseRate * dt);

    // Fail — trace complete
    if (primaryProgress.value >= 1) {
        endGame('fail', '[ICE LOCK] — Trace vector resolved. Location compromised.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Fail — stability gone
    if (stability.value <= 0) {
        endGame('fail', '[STABILITY CRITICAL] — Architecture saturated. Extraction aborted.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Tick active probes — remove expired ones
    const expired = [];
    for (const p of probes.value) {
        p.ttlLeft     = Math.max(0, p.ttlLeft - dt);
        p.ttlFraction = p.ttlLeft / p.ttlMax;
        if (p.ttlLeft <= 0) expired.push(p.id);
    }

    for (const id of expired) {
        const idx   = probes.value.findIndex(p => p.id === id);
        const probe = probes.value[idx];
        probes.value.splice(idx, 1);

        if (probe.isReal) {
            // Real probe expired unhandled — ICE trace spikes
            primaryProgress.value = Math.min(1, primaryProgress.value + config.traceSpikeReal);
            realMissFlash.value   = true;
            setTimeout(() => { realMissFlash.value = false; }, 1200);
        }
        // Ghost probe expired — correct, no penalty
    }

    // Spawn new probe
    spawnTimer -= dt;
    if (spawnTimer <= 0 && probes.value.length < config.maxActive) {
        probes.value.push(generateProbe());
        spawnTimer = randRange(config.spawnInterval.min, config.spawnInterval.max);
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.ae-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 12px 20px;
    box-sizing: border-box;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
    position: relative;
}

/* ── Archive header ──────────────────────────────────────────────────────────── */

.ae-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ae-node-label    { font-size: 9px; color: rgba(0,255,100,0.25); letter-spacing: 0.18em; }
.ae-block-counter { font-size: 9px; color: rgba(0,255,100,0.4);  letter-spacing: 0.1em; }

/* ── Extraction bar ──────────────────────────────────────────────────────────── */

.ae-extraction-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ae-ext-label {
    font-size: 8px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.15em;
    width: 90px;
    flex-shrink: 0;
}

.ae-ext-track {
    flex: 1;
    height: 5px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
}

.ae-ext-fill {
    height: 100%;
    background: linear-gradient(90deg, #003322, #00ff9d);
    box-shadow: 0 0 8px rgba(0,255,100,0.3);
    transition: width 0.2s linear;
}

.ae-ext-pct {
    font-size: 9px;
    color: rgba(0,255,100,0.5);
    width: 36px;
    text-align: right;
    flex-shrink: 0;
}

/* ── Data block grid ─────────────────────────────────────────────────────────── */

.ae-blocks {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 8px 0;
    border-top: 1px solid rgba(0,255,100,0.06);
    border-bottom: 1px solid rgba(0,255,100,0.06);
}

.ae-block {
    font-size: 8px;
    letter-spacing: 0.05em;
    padding: 3px 5px;
    border: 1px solid;
    transition: all 0.3s ease;
    min-width: 36px;
    text-align: center;
}

.ae-block--pending {
    color: rgba(0,255,100,0.12);
    border-color: rgba(0,255,100,0.08);
    background: transparent;
}

.ae-block--pulled {
    color: rgba(0,255,100,0.55);
    border-color: rgba(0,255,100,0.25);
    background: rgba(0,255,100,0.04);
}

/* ── ICE monitor header ──────────────────────────────────────────────────────── */

.ae-monitor-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 2px;
}

.ae-monitor-label { font-size: 8px; color: rgba(255,50,50,0.4); letter-spacing: 0.18em; }

.ae-monitor-count {
    font-size: 9px;
    color: rgba(0,255,100,0.25);
    letter-spacing: 0.1em;
}

.ae-count--active { color: #ff3333; text-shadow: 0 0 6px rgba(255,51,51,0.4); }

.ae-monitor-hint {
    font-size: 8px;
    color: rgba(0,255,100,0.18);
    letter-spacing: 0.1em;
    margin-left: auto;
}

/* ── Probe feed ──────────────────────────────────────────────────────────────── */

.ae-probe-feed {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 7px;
    overflow-y: auto;
    min-height: 0;
}

.ae-clear-msg {
    font-size: 9px;
    color: rgba(0,255,100,0.18);
    letter-spacing: 0.12em;
    padding: 16px 0;
    text-align: center;
}

/* ── Individual probe ────────────────────────────────────────────────────────── */

.ae-probe {
    border: 1px solid rgba(255,50,50,0.2);
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    background: rgba(255,0,0,0.02);
    transition: border-color 0.2s;
}

.ae-probe--urgent {
    border-color: rgba(255,50,50,0.6);
    background: rgba(255,0,0,0.05);
    animation: ae-pulse-border 0.4s ease infinite alternate;
}

.ae-probe-top {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ae-probe-id    { font-size: 9px;  color: rgba(255,100,100,0.6);  letter-spacing: 0.1em; }
.ae-probe-class { font-size: 10px; color: rgba(255,160,160,0.75); letter-spacing: 0.08em; font-weight: 700; flex: 1; }

.ae-probe-tag {
    font-size: 8px;
    letter-spacing: 0.15em;
    padding: 2px 6px;
    border: 1px solid;
}

.ae-tag--real  { color: #ff3333; border-color: rgba(255,51,51,0.4);  }
.ae-tag--ghost { color: #4a9a7a; border-color: rgba(74,154,122,0.4); }

.ae-probe-sig {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.06em;
}

.ae-src-val { color: rgba(0,255,100,0.55); }
.ae-vec-val { color: rgba(0,255,100,0.35); }

.ae-probe-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ae-ttl-track {
    flex: 1;
    height: 3px;
    background: rgba(255,50,50,0.1);
    overflow: hidden;
}

.ae-ttl-fill {
    height: 100%;
    background: rgba(255,100,100,0.5);
    transition: width 0.05s linear, background 0.2s;
}

.ae-ttl--urgent {
    background: #ff3333;
    box-shadow: 0 0 6px rgba(255,51,51,0.5);
}

.ae-suppress-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(255,100,100,0.3);
    color: rgba(255,100,100,0.6);
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.1s;
}

.ae-suppress-btn:hover {
    background: rgba(255,50,50,0.08);
    border-color: #ff3333;
    color: #ff6666;
}

/* ── Penalty banners ─────────────────────────────────────────────────────────── */

.ae-err-banner {
    position: absolute;
    bottom: 40px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #4a9a7a;
    letter-spacing: 0.15em;
    background: rgba(0,20,10,0.95);
    border: 1px solid rgba(74,154,122,0.4);
    padding: 6px 18px;
    white-space: nowrap;
    pointer-events: none;
}

.ae-err-banner--trace {
    color: #ff6666;
    border-color: rgba(255,100,100,0.4);
    background: rgba(20,0,0,0.95);
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */

.ae-probe-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.ae-probe-leave-active { transition: opacity 0.12s ease; }
.ae-probe-enter-from   { opacity: 0; transform: translateY(-4px); }
.ae-probe-leave-to     { opacity: 0; }

.ae-err-enter-active, .ae-err-leave-active { transition: opacity 0.2s; }
.ae-err-enter-from,   .ae-err-leave-to     { opacity: 0; }

/* ── Animations ──────────────────────────────────────────────────────────────── */

@keyframes ae-pulse-border {
    from { border-color: rgba(255,50,50,0.35); }
    to   { border-color: rgba(255,50,50,0.75); }
}
</style>
