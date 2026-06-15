<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ts-wrap">

            <div class="ts-node-label">SINK NODE // SV-v9 // TOXIC ABSORPTION ACTIVE</div>

            <div class="ts-vectors">
                <div
                    v-for="(vec, i) in vectors"
                    :key="vec.id"
                    class="ts-vector"
                    :class="{
                        'ts-vector--warn':     vec.pressure > 0.60 && vec.pressure <= 0.85,
                        'ts-vector--critical': vec.pressure > 0.85,
                    }"
                >
                    <div class="ts-vec-header">
                        <span class="ts-vec-id">[{{ vec.id }}]</span>
                        <span class="ts-vec-type">{{ vec.type }}</span>
                        <span class="ts-vec-pct" :class="pctClass(vec.pressure)">
                            {{ Math.round(vec.pressure * 100) }}%
                        </span>
                    </div>

                    <div class="ts-vec-controls">
                        <div class="ts-pressure-track">
                            <div
                                class="ts-pressure-fill"
                                :class="fillClass(vec.pressure)"
                                :style="{ width: (vec.pressure * 100) + '%' }"
                            />
                        </div>
                        <button
                            class="ts-vent-btn"
                            :class="{ 'ts-vent--ready': !vec.venting }"
                            :disabled="vec.venting || !!result"
                            @click="onVent(i)"
                        >{{ vec.venting ? '[ VENTING... ]' : '[ VENT ]' }}</button>
                    </div>

                    <Transition name="ts-of">
                        <span v-if="vec.overflowed" class="ts-overflow-tag">⚠ OVERFLOW DETECTED</span>
                    </Transition>
                </div>
            </div>

            <div class="ts-footer">HOLD POSITION — FLOAT IS MONITORING YOUR ARCHITECTURE</div>

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
// ventRate     — pressure drained per second while venting
// overflowHit  — stability penalty when a vector hits 100%
// overflowReset — pressure value after an overflow (not zero — punishes neglect)
// volatile     — PROC_NULL on D3 spikes unpredictably between normal ticks

const CONFIGS = {
    1: {
        ventRate:      0.80,
        overflowHit:   0.18,
        overflowReset: 0.20,
        vectors: [
            { id: 'PROC_DELTA', type: 'DATA_BLEED',  buildRate: 0.075 },
            { id: 'PROC_SIGMA', type: 'CACHE_FLOOD', buildRate: 0.060 },
        ],
    },
    2: {
        ventRate:      0.70,
        overflowHit:   0.24,
        overflowReset: 0.28,
        vectors: [
            { id: 'PROC_DELTA', type: 'DATA_BLEED',  buildRate: 0.095 },
            { id: 'PROC_SIGMA', type: 'CACHE_FLOOD', buildRate: 0.080 },
            { id: 'PROC_OMEGA', type: 'STACK_LEAK',  buildRate: 0.110 },
        ],
    },
    3: {
        ventRate:      0.65,
        overflowHit:   0.30,
        overflowReset: 0.32,
        vectors: [
            { id: 'PROC_DELTA', type: 'DATA_BLEED',  buildRate: 0.115 },
            { id: 'PROC_SIGMA', type: 'CACHE_FLOOD', buildRate: 0.100 },
            { id: 'PROC_OMEGA', type: 'STACK_LEAK',  buildRate: 0.130 },
            { id: 'PROC_NULL',  type: 'VOLATILE',    buildRate: 0.085, volatile: true },
        ],
    },
};

const diff   = props.skin.difficulty ?? 1;
const config = CONFIGS[diff] ?? CONFIGS[1];
const { ventRate, overflowHit, overflowReset } = config;

// ── Shared minigame state ─────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Vector reactive state ─────────────────────────────────────────────────────
// pressure: 0–1 float  |  venting: bool  |  overflowed: bool (flash)

const vectors = ref(
    config.vectors.map(v => ({
        id:         v.id,
        type:       v.type,
        buildRate:  v.buildRate,
        volatile:   v.volatile ?? false,
        pressure:   0,
        venting:    false,
        overflowed: false,
    }))
);

// Non-reactive spike timers for volatile vectors — no need to drive the template.
// Index matches vectors array; only the VOLATILE entry is meaningfully used.
const spikeTimers = config.vectors.map(() => 2 + Math.random() * 3);

// ── Style helpers ─────────────────────────────────────────────────────────────

function fillClass(p) {
    if (p > 0.85) return 'ts-fill--critical';
    if (p > 0.60) return 'ts-fill--warn';
    return 'ts-fill--safe';
}

function pctClass(p) {
    if (p > 0.85) return 'ts-pct--critical';
    if (p > 0.60) return 'ts-pct--warn';
    return '';
}

// ── Interaction ───────────────────────────────────────────────────────────────

function onVent(i) {
    if (result.value || vectors.value[i].venting) return;
    vectors.value[i].venting = true;
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

    const dt  = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    // Shared bars.
    // ToxicSoak: 'trace' = ABSORPTION complete = WIN (not a fail like other games).
    // 'stability' = OVERLOAD critical = FAIL as normal.
    const cause = tickShared(dt);
    if (cause === 'trace') {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
        return;
    }
    if (cause === 'stability') {
        endGame('fail', '[OVERLOAD CRITICAL] — Architecture saturated. Ejected from sink.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Update vectors
    const vecs = vectors.value;
    for (let i = 0; i < vecs.length; i++) {
        const v = vecs[i];

        // Volatile spike — fires every 3–7s for PROC_NULL on D3
        if (v.volatile) {
            spikeTimers[i] -= dt;
            if (spikeTimers[i] <= 0) {
                v.pressure      = Math.min(1, v.pressure + 0.28 + Math.random() * 0.15);
                spikeTimers[i]  = 3 + Math.random() * 4;
            }
        }

        if (v.venting) {
            v.pressure = Math.max(0, v.pressure - ventRate * dt);
            if (v.pressure === 0) v.venting = false;
        } else {
            v.pressure = Math.min(1, v.pressure + v.buildRate * dt);
        }

        // Overflow — stability hit, partial reset, brief flash
        if (v.pressure >= 1) {
            applyHit(overflowHit);
            v.pressure   = overflowReset;
            v.venting    = false;
            v.overflowed = true;
            setTimeout(() => { vectors.value[i].overflowed = false; }, 900);
        }
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
.ts-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    padding: 12px 20px;
    box-sizing: border-box;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
}

.ts-node-label {
    font-size: 9px;
    color: rgba(0,255,100,0.25);
    letter-spacing: 0.18em;
}

/* ── Vectors ──────────────────────────────────────────────────────────────── */

.ts-vectors {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
    justify-content: center;
}

.ts-vector {
    border: 1px solid rgba(0,255,100,0.12);
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.2s;
    position: relative;
}

.ts-vector--warn     { border-color: rgba(255,179,0,0.35); }
.ts-vector--critical { border-color: rgba(255,51,51,0.5); animation: ts-pulse-border 0.5s ease infinite alternate; }

.ts-vec-header {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ts-vec-id   { font-size: 10px; font-weight: 700; color: rgba(0,255,100,0.6); letter-spacing: 0.1em; }
.ts-vec-type { font-size: 9px;  color: rgba(0,255,100,0.3); letter-spacing: 0.15em; flex: 1; }

.ts-vec-pct          { font-size: 11px; font-weight: 700; color: rgba(0,255,100,0.45); letter-spacing: 0.05em; }
.ts-pct--warn        { color: #FFB300; }
.ts-pct--critical    { color: #ff3333; }

/* ── Pressure bar + vent button row ───────────────────────────────────────── */

.ts-vec-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ts-pressure-track {
    flex: 1;
    height: 6px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
}

.ts-pressure-fill {
    height: 100%;
    transition: width 0.05s linear, background 0.3s;
}

.ts-fill--safe     { background: rgba(0,255,100,0.55); }
.ts-fill--warn     { background: #FFB300; box-shadow: 0 0 6px rgba(255,179,0,0.4); }
.ts-fill--critical { background: #ff3333; box-shadow: 0 0 8px rgba(255,51,51,0.5); }

.ts-vent-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.15);
    color: rgba(0,255,100,0.25);
    padding: 5px 14px;
    cursor: not-allowed;
    white-space: nowrap;
    transition: all 0.1s;
    flex-shrink: 0;
}

.ts-vent--ready {
    border-color: rgba(0,255,100,0.45);
    color: rgba(0,255,100,0.7);
    cursor: pointer;
}

.ts-vent--ready:hover {
    background: rgba(0,255,100,0.07);
    border-color: #00ff9d;
    color: #00ff9d;
}

/* ── Overflow flash ────────────────────────────────────────────────────────── */

.ts-overflow-tag {
    font-size: 8px;
    color: #ff3333;
    letter-spacing: 0.18em;
    pointer-events: none;
}

.ts-of-enter-active, .ts-of-leave-active { transition: opacity 0.15s; }
.ts-of-enter-from,   .ts-of-leave-to     { opacity: 0; }

/* ── Footer ────────────────────────────────────────────────────────────────── */

.ts-footer {
    font-size: 8px;
    color: rgba(0,255,100,0.18);
    letter-spacing: 0.18em;
    text-align: center;
    padding-top: 4px;
    border-top: 1px solid rgba(0,255,100,0.05);
}

/* ── Animations ────────────────────────────────────────────────────────────── */

@keyframes ts-pulse-border {
    from { border-color: rgba(255,51,51,0.3); }
    to   { border-color: rgba(255,51,51,0.7); }
}
</style>
