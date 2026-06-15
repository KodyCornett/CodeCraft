<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- Signal monitor -->
            <div class="fb-monitor-label">SIGNAL MONITOR // DT-v8.ghost</div>
            <div class="fb-waveform">{{ signalDisplay }}</div>
            <div class="fb-state-row">
                <span class="fb-state-badge" :class="phase === 'cycling' ? 'fb-badge--cycling' : 'fb-badge--transmit'">
                    {{ phase === 'cycling' ? '[ CYCLING ]' : '[ TRANSMITTING ]' }}
                </span>
                <span class="fb-amp">
                    SIG_AMP: <span class="fb-amp-val" :class="phase === 'cycling' ? 'fb-amp--lock' : 'fb-amp--noise'">
                        0x{{ phase === 'cycling' ? sigAmp : noiseVal }}
                    </span>
                </span>
            </div>

            <!-- Recursion depth -->
            <div class="fb-depth-row">
                <span class="fb-depth-label">RECURSION DEPTH</span>
                <span class="fb-depth-pips">
                    <span
                        v-for="i in totalLayers"
                        :key="i"
                        class="fb-pip"
                        :class="i <= currentLayer ? 'fb-pip--flushed' : (i === currentLayer + 1 ? 'fb-pip--active' : '')"
                    >█</span>
                </span>
                <span class="fb-depth-count">{{ currentLayer }} / {{ totalLayers }}</span>
            </div>

            <div class="fb-instances">
                ACTIVE INSTANCES&nbsp;&nbsp;<span class="fb-inst-val">{{ instanceDisplay }}</span>
            </div>

            <!-- Cycling panel — only visible during CYCLING phase -->
            <Transition name="fb-cycle">
                <div v-if="phase === 'cycling'" class="fb-cycle-panel">
                    <div class="fb-cycle-title">ECHO TO FLUSH — SELECT MATCHING VALUE</div>
                    <div class="fb-target-amp">0x{{ sigAmp }}</div>
                    <div class="fb-options">
                        <button
                            v-for="opt in options"
                            :key="opt"
                            class="fb-opt-btn"
                            @click="onSelect(opt)"
                        >0x{{ opt }}</button>
                    </div>
                    <div class="fb-window-row">
                        <span class="fb-window-label">WINDOW</span>
                        <div class="fb-window-track">
                            <div
                                class="fb-window-fill"
                                :class="{ 'fb-window--critical': windowProgress < 0.3 }"
                                :style="{ width: (windowProgress * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Layer flush confirmation flash -->
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
// totalLayers  — recursion depth to unwind
// baseWindow   — initial CYCLING window in seconds
// windowDecay  — seconds lost per layer (window shrinks as depth collapses)
// optionCount  — total echo options shown (1 correct + decoys)
// transmitBase — initial TRANSMITTING duration; shrinks each layer
// hitAmount    — stability penalty per miss (wrong select or timeout)

const LAYER_CONFIG = {
    1: { totalLayers: 3, baseWindow: 2.0, windowDecay: 0.15, optionCount: 2, transmitBase: 3.0, hitAmount: 0.25 },
    2: { totalLayers: 5, baseWindow: 1.6, windowDecay: 0.12, optionCount: 3, transmitBase: 2.5, hitAmount: 0.22 },
    3: { totalLayers: 7, baseWindow: 1.3, windowDecay: 0.10, optionCount: 4, transmitBase: 2.0, hitAmount: 0.20 },
};

const WAVEFORM_CHARS = ['▁','▁','▂','▃','▄','▅','▆','▇','█'];

const diff   = props.skin.difficulty ?? 1;
const config = LAYER_CONFIG[diff] ?? LAYER_CONFIG[1];
const { totalLayers, baseWindow, windowDecay, optionCount, transmitBase, hitAmount } = config;

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Game state ────────────────────────────────────────────────────────────────

const phase            = ref('transmitting');   // 'transmitting' | 'cycling'
const currentLayer     = ref(0);               // layers flushed so far
const sigAmp           = ref('00');            // correct hex value to echo
const options          = ref([]);              // shuffled hex options (correct + decoys)
const noiseVal         = ref('00');            // flickering noise display during transmit
const amplitudeHistory = ref(Array(32).fill(0));
const layerFlash       = ref(false);
const windowFraction   = ref(1);   // 1.0 = full window, 0 = expired; reactive so bar updates

// Raw timers and animation state — not reactive, updated in RAF loop
let transmitTimer    = 0;
let windowTimer      = 0;
let currentWindowDur = 0;
let layerCooldown    = 0;
let noiseTimer       = 0;
let historyTimer     = 0;
let signalPhase      = 0;
let amplitude        = 0;
let animFrame        = null;
let lastTs           = null;

// ── Computed ──────────────────────────────────────────────────────────────────

const signalDisplay = computed(() =>
    amplitudeHistory.value.map(v =>
        WAVEFORM_CHARS[Math.min(8, Math.max(0, v))]
    ).join('')
);

// windowFraction is set reactively in the RAF loop; computed not used because
// windowTimer / currentWindowDur are plain vars Vue cannot track.
const windowProgress = computed(() => windowFraction.value);

// Active instances halve with each flushed layer — visualises recursion collapse
const instanceDisplay = computed(() => {
    const val = Math.floor(128 * Math.pow(2, totalLayers - currentLayer.value));
    return val.toLocaleString();
});

// ── Hex helpers ───────────────────────────────────────────────────────────────

function randHex() {
    return Math.floor(Math.random() * 256).toString(16).toUpperCase().padStart(2, '0');
}

// Generates the correct value + difficulty-appropriate decoys, shuffled.
function generateOptions() {
    const correct    = randHex();
    const correctVal = parseInt(correct, 16);
    const needed     = optionCount - 1;
    const decoys     = new Set();
    let   attempts   = 0;

    while (decoys.size < needed && attempts < 100) {
        attempts++;
        let candidate;
        if (diff === 1) {
            // Completely different values — easy to distinguish
            candidate = Math.floor(Math.random() * 256);
        } else if (diff === 2) {
            // Flip one nibble — visually similar first or second digit
            const shift = Math.random() < 0.5 ? 0 : 4;
            const mask  = (Math.floor(Math.random() * 15) + 1) << shift;
            candidate   = (correctVal ^ mask) & 0xFF;
        } else {
            // Flip 1–2 bits — near-identical under pressure
            const b1  = 1 << Math.floor(Math.random() * 8);
            const b2  = Math.random() < 0.6 ? (1 << Math.floor(Math.random() * 8)) : 0;
            candidate = (correctVal ^ b1 ^ b2) & 0xFF;
        }
        const hex = candidate.toString(16).toUpperCase().padStart(2, '0');
        if (hex !== correct) decoys.add(hex);
    }

    const all = [correct, ...decoys];
    for (let i = all.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [all[i], all[j]] = [all[j], all[i]];
    }
    return { correct, options: all };
}

// Duration helpers — each layer the signal gets tighter
function getTransmitDuration(layer) { return Math.max(1.2, transmitBase - layer * 0.15); }
function getWindowDuration(layer)   { return Math.max(0.5, baseWindow   - layer * windowDecay); }

// ── Phase transitions ─────────────────────────────────────────────────────────

function startTransmitting() {
    phase.value   = 'transmitting';
    transmitTimer = getTransmitDuration(currentLayer.value);
    signalPhase   = 0;
}

function startCycling() {
    const { correct, options: opts } = generateOptions();
    sigAmp.value     = correct;
    options.value    = opts;
    currentWindowDur = getWindowDuration(currentLayer.value);
    windowTimer      = currentWindowDur;
    windowFraction.value = 1;
    phase.value      = 'cycling';
}

function onLayerFlushed() {
    currentLayer.value++;
    layerFlash.value = true;
    layerCooldown    = 0.9;
    phase.value      = 'transmitting'; // hide cycling panel immediately

    if (currentLayer.value >= totalLayers) {
        // Final layer — win immediately; cooldown skipped
        layerFlash.value = false;
        layerCooldown    = 0;
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
    }
}

// ── Interaction ───────────────────────────────────────────────────────────────

function onSelect(value) {
    if (phase.value !== 'cycling' || result.value) return;
    if (value === sigAmp.value) {
        onLayerFlushed();
    } else {
        applyHit(hitAmount);
        startTransmitting();
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

function tick(ts) {
    if (result.value) return;

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    // Shared trace + stability drain
    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Layer cooldown — brief pause between flush and next transmit cycle
    // Returns early so no transmit/window timers tick during the pause.
    if (layerCooldown > 0) {
        layerCooldown -= dt;
        if (layerCooldown <= 0 && !result.value) {
            layerFlash.value = false;
            startTransmitting();
        }
        animFrame = requestAnimationFrame(tick);
        return;
    }

    // Amplitude animation
    if (phase.value === 'transmitting') {
        const speed = 1.5 + currentLayer.value * 0.2 + (diff - 1) * 0.3;
        signalPhase += dt * speed;
        amplitude    = (Math.sin(signalPhase * Math.PI * 2) * 0.5 + 0.5) * 8;
    } else {
        // CYCLING — amplitude drops to near zero (gap between recursive calls)
        amplitude = Math.max(0, amplitude - dt * 14);
    }

    // Push amplitude sample to scrolling history
    historyTimer -= dt;
    if (historyTimer <= 0) {
        amplitudeHistory.value = [...amplitudeHistory.value.slice(-31), Math.round(amplitude)];
        historyTimer = 0.06;
    }

    // Flicker the noise display during transmit
    if (phase.value === 'transmitting') {
        noiseTimer -= dt;
        if (noiseTimer <= 0) {
            noiseVal.value = randHex();
            noiseTimer     = 0.07 + Math.random() * 0.05;
        }

        // Transition to CYCLING when transmit timer expires
        transmitTimer -= dt;
        if (transmitTimer <= 0) startCycling();
    }

    // Count down the CYCLING window — timeout = missed = stability hit
    if (phase.value === 'cycling') {
        windowTimer -= dt;
        windowFraction.value = currentWindowDur > 0
            ? Math.max(0, windowTimer / currentWindowDur)
            : 0;
        if (windowTimer <= 0) {
            applyHit(hitAmount);
            startTransmitting();
        }
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    startTransmitting();
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
    align-items: center;
    justify-content: flex-start;
    padding: 14px 20px;
    box-sizing: border-box;
    gap: 10px;
    position: relative;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Monitor header ──────────────────────────────────────────────────────────── */

.fb-monitor-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
    align-self: flex-start;
}

.fb-waveform {
    font-size: 13px;
    color: rgba(0,255,100,0.55);
    letter-spacing: 0.05em;
    line-height: 1;
    align-self: stretch;
    overflow: hidden;
    white-space: nowrap;
}

.fb-state-row {
    display: flex;
    align-items: center;
    gap: 20px;
    align-self: flex-start;
}

.fb-state-badge {
    font-size: 10px;
    letter-spacing: 0.15em;
    padding: 3px 8px;
    border: 1px solid;
}

.fb-badge--transmit {
    color: rgba(255,102,0,0.7);
    border-color: rgba(255,102,0,0.25);
}

.fb-badge--cycling {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.5);
    animation: fb-blink 0.6s steps(1) infinite;
}

.fb-amp { font-size: 10px; color: rgba(0,255,100,0.4); letter-spacing: 0.1em; }

.fb-amp-val { font-weight: 700; }
.fb-amp--noise { color: rgba(255,102,0,0.5); animation: fb-flicker 0.08s steps(1) infinite; }
.fb-amp--lock  { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.5); }

/* ── Recursion depth ──────────────────────────────────────────────────────────── */

.fb-depth-row {
    display: flex;
    align-items: center;
    gap: 12px;
    align-self: flex-start;
    border-top: 1px solid rgba(0,255,100,0.07);
    padding-top: 10px;
    width: 100%;
}

.fb-depth-label { font-size: 8px; color: rgba(0,255,100,0.25); letter-spacing: 0.15em; }

.fb-depth-pips  { display: flex; gap: 5px; }

.fb-pip { font-size: 11px; color: rgba(0,255,100,0.12); transition: color 0.3s; }
.fb-pip--active  { color: #ff6600; text-shadow: 0 0 6px rgba(255,102,0,0.6); }
.fb-pip--flushed { color: rgba(0,255,100,0.2); }

.fb-depth-count { font-size: 9px; color: rgba(0,255,100,0.3); margin-left: auto; }

.fb-instances {
    font-size: 9px;
    color: rgba(0,255,100,0.22);
    letter-spacing: 0.12em;
    align-self: flex-start;
}

.fb-inst-val { color: rgba(0,255,100,0.45); }

/* ── Cycling panel ──────────────────────────────────────────────────────────── */

.fb-cycle-panel {
    width: 100%;
    max-width: 480px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border: 1px solid rgba(0,255,100,0.2);
    background: rgba(0,255,100,0.03);
    margin-top: 6px;
    box-sizing: border-box;
}

.fb-cycle-title {
    font-size: 9px;
    color: rgba(0,255,100,0.4);
    letter-spacing: 0.15em;
}

.fb-target-amp {
    font-size: 32px;
    font-weight: 700;
    color: #00ff9d;
    letter-spacing: 0.12em;
    text-shadow: 0 0 20px rgba(0,255,100,0.4);
}

.fb-options {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

.fb-opt-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.1em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
    padding: 10px 22px;
    cursor: pointer;
    transition: all 0.1s;
    min-width: 90px;
    text-align: center;
}

.fb-opt-btn:hover {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
    box-shadow: 0 0 14px rgba(0,255,100,0.2);
}

.fb-window-row {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.fb-window-label { font-size: 8px; color: rgba(0,255,100,0.25); letter-spacing: 0.15em; flex-shrink: 0; }

.fb-window-track {
    flex: 1;
    height: 3px;
    background: rgba(0,255,100,0.08);
    overflow: hidden;
}

.fb-window-fill {
    height: 100%;
    background: #00ff9d;
    box-shadow: 0 0 6px rgba(0,255,100,0.5);
    transition: background 0.2s;
}

.fb-window--critical { background: #ff3333; box-shadow: 0 0 6px rgba(255,51,51,0.5); }

/* ── Layer flush flash ──────────────────────────────────────────────────────── */

.fb-flash-msg {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    color: #00ff9d;
    letter-spacing: 0.15em;
    background: rgba(0,20,10,0.92);
    border: 1px solid rgba(0,255,100,0.3);
    padding: 6px 18px;
    white-space: nowrap;
    pointer-events: none;
    text-shadow: 0 0 10px rgba(0,255,100,0.5);
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */

.fb-cycle-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.fb-cycle-leave-active { transition: opacity 0.12s ease; }
.fb-cycle-enter-from   { opacity: 0; transform: translateY(6px); }
.fb-cycle-leave-to     { opacity: 0; }

.fb-flash-enter-active, .fb-flash-leave-active { transition: opacity 0.2s; }
.fb-flash-enter-from,   .fb-flash-leave-to     { opacity: 0; }

/* ── Animations ─────────────────────────────────────────────────────────────── */

@keyframes fb-blink   { 0%,49%{opacity:1} 50%,100%{opacity:0.4} }
@keyframes fb-flicker { 0%,49%{opacity:1} 50%,100%{opacity:0.5} }
</style>
