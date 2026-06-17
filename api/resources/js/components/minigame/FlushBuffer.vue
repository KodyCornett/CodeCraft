<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- Wave lane labels -->
            <div class="fb-lane-labels">
                <span class="fb-lane-label fb-lane-label--ghost">GHOST SIGNAL</span>
                <span class="fb-lane-label fb-lane-label--composite"
                    :class="syncing ? 'fb-composite-label--syncing' : ''">
                    COMPOSITE {{ syncing ? '// CANCELLATION LOCKED' : '' }}
                </span>
                <span class="fb-lane-label fb-lane-label--carrier">FLUSH CARRIER</span>
            </div>

            <!-- Full-width waveform display -->
            <svg class="fb-svg" viewBox="0 0 960 270" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Lane dividers -->
                <line x1="0" y1="90"  x2="960" y2="90"  class="fb-lane-div" />
                <line x1="0" y1="180" x2="960" y2="180" class="fb-lane-div" />

                <!-- Zero-axis lines -->
                <line x1="0" y1="45"  x2="960" y2="45"  class="fb-zero" />
                <line x1="0" y1="135" x2="960" y2="135" class="fb-zero fb-zero--composite" />
                <line x1="0" y1="225" x2="960" y2="225" class="fb-zero" />

                <!-- Ghost wave — top lane, centre 45 -->
                <path :d="ghostPath"     class="fb-wave fb-wave--ghost" />

                <!-- Composite wave — middle lane, centre 135 -->
                <path :d="compositePath" class="fb-wave fb-wave--composite"
                    :class="syncing ? 'fb-wave--syncing' : ''" />

                <!-- Carrier wave — bottom lane, centre 225 -->
                <path :d="carrierPath"   class="fb-wave fb-wave--carrier" />
            </svg>

            <!-- Controls row -->
            <div class="fb-controls">

                <!-- Phase display -->
                <div class="fb-phase-block">
                    <span class="fb-ctrl-label">CARRIER PHASE</span>
                    <span class="fb-phase-val">{{ phaseDisplay }}°</span>
                </div>

                <!-- Flush sync meter -->
                <div class="fb-sync-block">
                    <span class="fb-ctrl-label">FLUSH SYNC</span>
                    <div class="fb-sync-track">
                        <div
                            class="fb-sync-fill"
                            :class="syncing ? 'fb-sync--locked' : ''"
                            :style="{ width: (flushMeter * 100) + '%' }"
                        />
                    </div>
                    <span class="fb-sync-pct" :class="syncing ? 'fb-sync-pct--locked' : ''">
                        {{ Math.round(flushMeter * 100) }}%
                    </span>
                </div>

                <!-- Recursion depth pips -->
                <div class="fb-layer-block">
                    <span class="fb-ctrl-label">RECURSION DEPTH</span>
                    <div class="fb-layer-pips">
                        <span
                            v-for="i in totalLayers"
                            :key="i"
                            class="fb-pip"
                            :class="i <= currentLayer ? 'fb-pip--flushed' : (i === currentLayer + 1 ? 'fb-pip--active' : '')"
                        >█</span>
                    </div>
                    <span class="fb-layer-count">{{ currentLayer }} / {{ totalLayers }}</span>
                </div>

                <!-- Phase advance button -->
                <button
                    class="fb-advance-btn"
                    :class="{ 'fb-advance--held': advancing }"
                    @mousedown="advancing = true"
                    @mouseup="advancing = false"
                    @mouseleave="advancing = false"
                    @touchstart.prevent="advancing = true"
                    @touchend.prevent="advancing = false"
                >[ ADVANCE PHASE ]</button>

            </div>

            <!-- Layer flush confirmation -->
            <Transition name="fb-flash">
                <div v-if="layerFlash" class="fb-flash-msg">
                    ✓ LAYER FLUSHED — RECURSION COLLAPSING
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
//
// advanceRate   — phase radians advanced per second while button is held
// syncThreshold — alignment (0–1) required to start filling the flush meter
// fillRate      — flush meter fill per second while syncing
// drainRate     — flush meter drain per second while out of sync
// freqBase      — ghost signal base frequency (cycles visible on screen)
// freqStep      — frequency added per layer (signal gets faster each collapse)
// totalLayers   — recursion depth to unwind

const CONFIGS = {
    1: { advanceRate: 2.2,  syncThreshold: 0.78, fillRate: 0.30, drainRate: 0.12, freqBase: 1.0, freqStep: 0.10, totalLayers: 3 },
    2: { advanceRate: 1.8,  syncThreshold: 0.86, fillRate: 0.22, drainRate: 0.18, freqBase: 1.3, freqStep: 0.18, totalLayers: 5 },
    3: { advanceRate: 1.4,  syncThreshold: 0.93, fillRate: 0.16, drainRate: 0.25, freqBase: 1.7, freqStep: 0.25, totalLayers: 7 },
};

const diffLevel = props.skin.difficulty ?? 1;
const config    = CONFIGS[diffLevel] ?? CONFIGS[1];

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Wave / phase state ────────────────────────────────────────────────────────

// Phase offset of carrier relative to ghost (radians).
// Player needs to push this toward π for destructive interference.
// Starts at a random value far from π so there's always something to do.
const phase        = ref(randomStartPhase());
const advancing    = ref(false);
const flushMeter   = ref(0);
const currentLayer = ref(0);
const layerFlash   = ref(false);
let   layerCooldown = 0;

// Current ghost frequency — increases each layer
let ghostFreq = config.freqBase;

// Scroll offset drives the waveforms scrolling in real time
let scrollT = 0;

// SVG path strings — updated directly each frame
const ghostPath     = ref('');
const carrierPath   = ref('');
const compositePath = ref('');

const totalLayers = config.totalLayers;

function randomStartPhase() {
    // Pick a random phase that is at least π/3 away from the target (π)
    // to ensure the player always has to do real work.
    const candidates = [
        Math.random() * (Math.PI * 0.6),                      // 0 – 0.6π
        Math.PI * 1.4 + Math.random() * (Math.PI * 0.6),      // 1.4π – 2π
    ];
    return candidates[Math.floor(Math.random() * candidates.length)];
}

// ── Alignment ─────────────────────────────────────────────────────────────────
// Peaks at 1.0 when phase = π (perfect destructive interference).
// Formula: (1 − cos(phase)) / 2

function computeAlignment(p) {
    const norm = ((p % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2);
    return (1 - Math.cos(norm)) / 2;
}

const syncing = computed(() => computeAlignment(phase.value) >= config.syncThreshold);

const phaseDisplay = computed(() =>
    Math.round(((phase.value % (Math.PI * 2) + Math.PI * 2) % (Math.PI * 2)) * (180 / Math.PI))
);

// ── Waveform path generation ──────────────────────────────────────────────────
// viewBox is 960 × 270. Three lanes, each 90px tall.
// Centre lines: ghost=45, composite=135, carrier=225.

const W         = 960;
const STEPS     = 160;   // sample points — smooth without killing performance
const GHOST_CY  = 45;
const COMP_CY   = 135;
const CARRIER_CY = 225;
const GHOST_AMP  = 32;
const COMP_AMP   = 64;   // 2× when fully in phase, 0 when cancelled
const CARRIER_AMP = 32;

function buildPath(centerY, amplitude, freq, phaseOffset) {
    let d = '';
    for (let i = 0; i <= STEPS; i++) {
        const x = (i / STEPS) * W;
        // scrollT drives the leftward scroll; freq controls how many cycles show
        const t = scrollT + (i / STEPS) * Math.PI * 2 * freq;
        const y = centerY + amplitude * Math.sin(t + phaseOffset);
        d += i === 0 ? `M ${x.toFixed(1)} ${y.toFixed(2)}` : ` L ${x.toFixed(1)} ${y.toFixed(2)}`;
    }
    return d;
}

function updatePaths() {
    const p = phase.value;
    const f = ghostFreq;

    // Ghost: reference signal, phase 0
    ghostPath.value = buildPath(GHOST_CY, GHOST_AMP, f, 0);

    // Carrier: same frequency, player-controlled phase offset
    carrierPath.value = buildPath(CARRIER_CY, CARRIER_AMP, f, p);

    // Composite: sum of both — amplitude shrinks as p approaches π
    // sin(t) + sin(t + p) = 2·cos(p/2)·sin(t + p/2)
    // We render it as a wave with amplitude scaled by |2·cos(p/2)|
    const compAmpScale = Math.abs(2 * Math.cos(p / 2));
    const compPhase    = p / 2; // composite peak sits halfway between
    compositePath.value = buildPath(COMP_CY, COMP_AMP * (compAmpScale / 2), f, compPhase);
}

// ── Layer management ──────────────────────────────────────────────────────────

function onLayerFlushed() {
    currentLayer.value++;

    if (currentLayer.value >= totalLayers) {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
        return;
    }

    // Flash, then reset for next layer
    layerFlash.value = true;
    layerCooldown    = 1.0;
    flushMeter.value = 0;
    ghostFreq       += config.freqStep;

    // Randomise starting phase for next layer so player can't just sit at π
    phase.value = randomStartPhase();
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

    // Scroll the waveforms left
    scrollT -= dt * 1.4;

    // Shared trace + stability
    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Layer cooldown — pause between flush and next layer starting
    if (layerCooldown > 0) {
        layerCooldown -= dt;
        if (layerCooldown <= 0) layerFlash.value = false;
        updatePaths();
        animFrame = requestAnimationFrame(tick);
        return;
    }

    // Phase advance while button held
    if (advancing.value) {
        phase.value += config.advanceRate * dt;
    }

    // Alignment drives the flush meter
    const alignment = computeAlignment(phase.value);
    if (alignment >= config.syncThreshold) {
        flushMeter.value = Math.min(1, flushMeter.value + config.fillRate * dt);
    } else {
        flushMeter.value = Math.max(0, flushMeter.value - config.drainRate * dt);
    }

    if (flushMeter.value >= 1) {
        flushMeter.value = 0;
        onLayerFlushed();
    }

    // Update SVG paths
    updatePaths();

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    updatePaths();
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
    box-sizing: border-box;
    font-family: 'JetBrains Mono', monospace;
    gap: 0;
}

/* ── Lane labels ─────────────────────────────────────────────────────────────── */

.fb-lane-labels {
    display: flex;
    flex-direction: column;
    position: absolute;
    left: 14px;
    top: 0;
    bottom: 0;
    justify-content: space-around;
    pointer-events: none;
    z-index: 2;
    padding: 8px 0;
}

.fb-lane-label {
    font-size: 8px;
    letter-spacing: 0.18em;
    opacity: 0.5;
}

.fb-lane-label--ghost     { color: #ff6600; }
.fb-lane-label--composite { color: rgba(0,255,100,0.6); transition: color 0.3s; }
.fb-lane-label--carrier   { color: #00ff9d; }

.fb-composite-label--syncing {
    color: #00ff9d;
    opacity: 1;
    animation: fb-label-pulse 0.8s ease infinite alternate;
}

/* ── SVG waveform ────────────────────────────────────────────────────────────── */

.fb-svg {
    flex: 1;
    width: 100%;
    display: block;
    min-height: 0;
}

.fb-lane-div {
    stroke: rgba(0,255,100,0.06);
    stroke-width: 1;
}

.fb-zero {
    stroke: rgba(0,255,100,0.08);
    stroke-width: 1;
    stroke-dasharray: 4 8;
}

.fb-zero--composite {
    stroke: rgba(0,255,100,0.12);
}

.fb-wave {
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.fb-wave--ghost {
    stroke: rgba(255,102,0,0.65);
    filter: drop-shadow(0 0 3px rgba(255,102,0,0.3));
}

.fb-wave--composite {
    stroke: rgba(0,255,100,0.35);
    stroke-width: 2.5;
    transition: stroke 0.2s;
}

.fb-wave--syncing {
    stroke: #00ff9d;
    stroke-width: 3;
    filter: drop-shadow(0 0 6px rgba(0,255,100,0.5));
}

.fb-wave--carrier {
    stroke: rgba(0,200,255,0.7);
    filter: drop-shadow(0 0 3px rgba(0,200,255,0.3));
}

/* ── Controls row ────────────────────────────────────────────────────────────── */

.fb-controls {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 10px 16px;
    border-top: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
}

.fb-ctrl-label {
    font-size: 7px;
    color: rgba(0,255,100,0.25);
    letter-spacing: 0.18em;
    display: block;
    margin-bottom: 4px;
}

/* Phase display */

.fb-phase-block { display: flex; flex-direction: column; min-width: 90px; }

.fb-phase-val {
    font-size: 18px;
    font-weight: 700;
    color: rgba(0,200,255,0.8);
    letter-spacing: 0.08em;
}

/* Sync meter */

.fb-sync-block {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.fb-sync-track {
    height: 6px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
    margin-bottom: 3px;
}

.fb-sync-fill {
    height: 100%;
    background: rgba(0,255,100,0.3);
    transition: background 0.2s;
}

.fb-sync--locked {
    background: #00ff9d;
    box-shadow: 0 0 10px rgba(0,255,100,0.5);
    animation: fb-sync-pulse 0.6s ease infinite alternate;
}

.fb-sync-pct {
    font-size: 9px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.1em;
}

.fb-sync-pct--locked {
    color: #00ff9d;
}

/* Layer pips */

.fb-layer-block { display: flex; flex-direction: column; }

.fb-layer-pips  { display: flex; gap: 5px; margin-bottom: 3px; }

.fb-pip { font-size: 10px; color: rgba(0,255,100,0.12); transition: color 0.3s; }
.fb-pip--active  { color: #ff6600; text-shadow: 0 0 6px rgba(255,102,0,0.5); }
.fb-pip--flushed { color: rgba(0,255,100,0.22); }

.fb-layer-count { font-size: 8px; color: rgba(0,255,100,0.25); letter-spacing: 0.1em; }

/* Advance button */

.fb-advance-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(0,200,255,0.3);
    color: rgba(0,200,255,0.6);
    padding: 10px 22px;
    cursor: pointer;
    transition: all 0.08s;
    user-select: none;
    flex-shrink: 0;
}

.fb-advance-btn:hover {
    border-color: rgba(0,200,255,0.6);
    color: rgba(0,200,255,0.9);
}

.fb-advance--held {
    background: rgba(0,200,255,0.08);
    border-color: #00ccff;
    color: #00ccff;
    box-shadow: 0 0 14px rgba(0,200,255,0.2);
}

/* ── Flash message ───────────────────────────────────────────────────────────── */

.fb-flash-msg {
    position: absolute;
    bottom: 70px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #00ff9d;
    letter-spacing: 0.15em;
    background: rgba(0,20,10,0.95);
    border: 1px solid rgba(0,255,100,0.35);
    padding: 7px 20px;
    white-space: nowrap;
    pointer-events: none;
    text-shadow: 0 0 10px rgba(0,255,100,0.5);
    z-index: 10;
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */

.fb-flash-enter-active, .fb-flash-leave-active { transition: opacity 0.2s; }
.fb-flash-enter-from,   .fb-flash-leave-to     { opacity: 0; }

/* ── Animations ──────────────────────────────────────────────────────────────── */

@keyframes fb-sync-pulse  { from { box-shadow: 0 0 6px rgba(0,255,100,0.3);  } to { box-shadow: 0 0 16px rgba(0,255,100,0.7); } }
@keyframes fb-label-pulse { from { opacity: 0.7; } to { opacity: 1; } }
</style>
