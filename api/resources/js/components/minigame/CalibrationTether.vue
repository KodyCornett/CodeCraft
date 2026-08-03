<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ct-wrap">

            <div class="ct-status-row">
                <span class="ct-status-item">DELIVERED: {{ deliveredCount }}/{{ TOTAL }}</span>
                <span class="ct-status-item">TETHER SLOTS: {{ occupiedSlotCount }}/{{ config.slotCount }}</span>
            </div>

            <!-- Tether slots -->
            <div class="ct-slots">
                <div
                    v-for="(slot, i) in slotViews"
                    :key="i"
                    class="ct-slot"
                    :class="{ 'ct-slot--stable': slot && slot.status === 'stable', 'ct-slot--danger': slot && slot.dangerTime > 0 }"
                >
                    <template v-if="slot">
                        <div class="ct-slot-top">
                            <span class="ct-slot-id">{{ slot.label }}</span>
                            <span v-if="slot.status === 'stable'" class="ct-slot-badge">STABLE</span>
                        </div>

                        <svg class="ct-gauge" viewBox="0 0 200 40" preserveAspectRatio="none">
                            <rect x="10" y="14" width="180" height="12" class="ct-gauge-track" />
                            <rect :x="gaugeDangerLeftOuter" y="14" :width="gaugeDangerLeftInner - gaugeDangerLeftOuter" height="12" class="ct-gauge-danger" />
                            <rect :x="gaugeDangerRightInner" y="14" :width="gaugeDangerRightOuter - gaugeDangerRightInner" height="12" class="ct-gauge-danger" />
                            <rect :x="gaugeBandLeft" y="14" :width="gaugeBandRight - gaugeBandLeft" height="12" class="ct-gauge-band" />
                            <line x1="100" y1="9" x2="100" y2="31" class="ct-gauge-center" />
                            <line
                                :x1="needleX(slot)" y1="3" :x2="needleX(slot)" y2="37"
                                class="ct-gauge-needle"
                                :class="needleClass(slot)"
                            />
                        </svg>

                        <div class="ct-settle-row">
                            <span class="ct-settle-lbl">CONTAINMENT</span>
                            <div class="ct-settle-track">
                                <div class="ct-settle-fill" :class="{ 'ct-settle-fill--done': slot.status === 'stable' }" :style="{ width: settlePct(slot) + '%' }" />
                            </div>
                        </div>

                        <div class="ct-slot-actions">
                            <button
                                class="ct-nudge-btn"
                                :disabled="slot.status !== 'tethered'"
                                @click="onNudge(slot.id)"
                            >[ NUDGE ]</button>
                            <button
                                class="ct-route-btn"
                                :disabled="slot.status !== 'stable'"
                                @click="onRoute(slot.id)"
                            >[ ROUTE ]</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="ct-slot-empty">EMPTY SLOT</div>
                    </template>
                </div>
            </div>

            <!-- Holding pool -->
            <div class="ct-pool-label">HOLDING POOL // click to tether</div>
            <div class="ct-pool">
                <div
                    v-for="item in poolItems"
                    :key="item.id"
                    class="ct-pool-chip"
                    :class="{ 'ct-pool-chip--disabled': !hasFreeSlot }"
                    @click="onTether(item.id)"
                >{{ item.label }}</div>
                <div v-if="poolItems.length === 0" class="ct-pool-empty">// pool clear</div>
            </div>

            <!-- Feedback banners -->
            <Transition name="ct-err">
                <div v-if="destabilizeFlash" class="ct-banner">⚠ CONTAINMENT LOST — {{ lostLabel }} EJECTED</div>
            </Transition>
            <Transition name="ct-err">
                <div v-if="cascadeFlash" class="ct-banner ct-banner--cascade">⚠⚠ CASCADE FAILURE — CONTAINMENT CHAIN BROKE</div>
            </Transition>
            <Transition name="ct-ok">
                <div v-if="routeFlash" class="ct-banner ct-banner--ok">✔ ROUTED — {{ routedLabel }} DELIVERED</div>
            </Transition>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

/* ════════════════════════════════════════════════════════════════════════
   CALIBRATION TETHER — split-attention containment game.
   Five volatile sub-routines need tethering and hauling to the drop-box.
   Each tethered item drifts on its own clock and must be nudged back into
   a safe band; leave it past the danger threshold too long and it destabilizes,
   ejecting back to the pool and hitting INTEGRITY. Two destabilizations close
   together in time cascade into a bigger combined hit — the real danger is
   letting several things lapse at once, not any single wandering gauge.
   ════════════════════════════════════════════════════════════════════════ */

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Difficulty config ─────────────────────────────────────────────────────────
// D1: 3 slots, wide band, slow drift, forgiving cascade window.
// D2: 3 slots, tighter band, faster drift, tighter cascade window.
// D3: only 2 slots — narrowest band, fastest drift, longest settle required,
//     easiest to trigger a cascade. Every slot is a live problem almost always.
const CONFIGS = {
    1: {
        slotCount: 3, bandWidth: 0.40, dangerThreshold: 0.85, graceThreshold: 2.0,
        settleThreshold: 2.5, driftMax: 0.20, driftRerollS: 1.5,
        nudgeStrength: 0.35, nudgeCooldown: 0.3,
        cascadeWindow: 2.5, cascadeMultiplier: 1.5, integrityBaseHit: 0.16,
        integrityBaseDrain: 0.010, integrityPerTetherDrain: 0.008,
        payloadBaseRate: 1 / 140, payloadPerOutOfBandRate: 0.020,
        duration: 110,
    },
    2: {
        slotCount: 3, bandWidth: 0.32, dangerThreshold: 0.82, graceThreshold: 1.6,
        settleThreshold: 3.5, driftMax: 0.28, driftRerollS: 1.3,
        nudgeStrength: 0.35, nudgeCooldown: 0.3,
        cascadeWindow: 3.0, cascadeMultiplier: 1.75, integrityBaseHit: 0.20,
        integrityBaseDrain: 0.014, integrityPerTetherDrain: 0.011,
        payloadBaseRate: 1 / 110, payloadPerOutOfBandRate: 0.030,
        duration: 100,
    },
    3: {
        slotCount: 2, bandWidth: 0.25, dangerThreshold: 0.78, graceThreshold: 1.2,
        settleThreshold: 4.5, driftMax: 0.38, driftRerollS: 1.1,
        nudgeStrength: 0.35, nudgeCooldown: 0.3,
        cascadeWindow: 3.5, cascadeMultiplier: 2.0, integrityBaseHit: 0.24,
        integrityBaseDrain: 0.018, integrityPerTetherDrain: 0.015,
        payloadBaseRate: 1 / 85, payloadPerOutOfBandRate: 0.040,
        duration: 90,
    },
};

const diffLevel = props.skin.difficulty ?? 1;
const config    = CONFIGS[diffLevel] ?? CONFIGS[1];

const TOTAL = 5;

// ── Shared minigame state ─────────────────────────────────────────────────────
// PAYLOAD (primaryProgress) is an ambient risk meter — rises passively, faster
// while tethers are out of band — maxing out is its own fail state. INTEGRITY
// (stability) is direct health, hit by destabilize/cascade events plus a small
// passive drain per active tether ("every packet carried drains your stability").

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    applyHit, endGame,
} = useQuestMinigameState(props.skin);

timeLeft.value = config.duration;

// ── Sub-routine state ─────────────────────────────────────────────────────────

function randomDrift() {
    const mag  = 0.4 + Math.random() * 0.6;
    const sign = Math.random() < 0.5 ? -1 : 1;
    return sign * mag * config.driftMax;
}
function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

const subroutines = ref(
    Array.from({ length: TOTAL }, (_, i) => ({
        id: i,
        label: `GK-0${i + 1}`,
        status: 'pool', // 'pool' | 'tethered' | 'stable' | 'delivered'
        slotIndex: null,
        calibration: 0,
        driftVel: 0,
        driftTimer: 0,
        settledTime: 0,
        dangerTime: 0,
        lastNudgeAt: -Infinity,
    }))
);

const poolItems      = computed(() => subroutines.value.filter(s => s.status === 'pool'));
const deliveredCount = computed(() => subroutines.value.filter(s => s.status === 'delivered').length);

const slotViews = computed(() => {
    const arr = Array.from({ length: config.slotCount }, () => null);
    for (const s of subroutines.value) {
        if (s.slotIndex !== null) arr[s.slotIndex] = s;
    }
    return arr;
});

const occupiedSlotCount = computed(() => slotViews.value.filter(v => v !== null).length);
const hasFreeSlot       = computed(() => slotViews.value.some(v => v === null));

// ── Gauge geometry (fixed per game instance — derived once from config) ──────

const gaugeBandLeft         = 100 - config.bandWidth * 90;
const gaugeBandRight        = 100 + config.bandWidth * 90;
const gaugeDangerLeftOuter  = 10;
const gaugeDangerLeftInner  = 100 - config.dangerThreshold * 90;
const gaugeDangerRightInner = 100 + config.dangerThreshold * 90;
const gaugeDangerRightOuter = 190;

function needleX(item) { return 100 + clamp(item.calibration, -1, 1) * 90; }

function needleClass(item) {
    if (item.status === 'stable') return 'ct-needle--stable';
    const abs = Math.abs(item.calibration);
    if (abs > config.dangerThreshold) return 'ct-needle--danger';
    if (abs > config.bandWidth) return 'ct-needle--warn';
    return 'ct-needle--safe';
}

function settlePct(item) {
    if (item.status === 'stable') return 100;
    return Math.min(100, (item.settledTime / config.settleThreshold) * 100);
}

// ── Feedback flashes ───────────────────────────────────────────────────────────

const destabilizeFlash = ref(false);
const cascadeFlash     = ref(false);
const routeFlash       = ref(false);
const lostLabel        = ref('');
const routedLabel      = ref('');

// ── Player actions ─────────────────────────────────────────────────────────────

function onTether(id) {
    if (result.value) return;
    const item = subroutines.value.find(s => s.id === id);
    if (!item || item.status !== 'pool') return;
    const freeIndex = slotViews.value.findIndex(v => v === null);
    if (freeIndex === -1) return;

    item.status      = 'tethered';
    item.slotIndex    = freeIndex;
    item.calibration = (Math.random() * 2 - 1) * 0.15;
    item.driftVel     = randomDrift();
    item.driftTimer   = config.driftRerollS;
    item.settledTime  = 0;
    item.dangerTime    = 0;
    item.lastNudgeAt   = -Infinity;
}

function onNudge(id) {
    if (result.value) return;
    const item = subroutines.value.find(s => s.id === id);
    if (!item || item.status !== 'tethered') return;
    if (clock - item.lastNudgeAt < config.nudgeCooldown) return;
    item.lastNudgeAt = clock;
    const dir = item.calibration >= 0 ? -1 : 1;
    item.calibration = clamp(item.calibration + dir * config.nudgeStrength, -1, 1);
}

function onRoute(id) {
    if (result.value) return;
    const item = subroutines.value.find(s => s.id === id);
    if (!item || item.status !== 'stable') return;

    item.status    = 'delivered';
    item.slotIndex = null;

    routedLabel.value = item.label;
    routeFlash.value   = true;
    setTimeout(() => { routeFlash.value = false; }, 1000);

    if (deliveredCount.value >= TOTAL) {
        endGame('success', '');
        setTimeout(() => emit('complete'), 2200);
    }
}

function destabilize(item) {
    item.status      = 'pool';
    item.slotIndex    = null;
    item.calibration = 0;
    item.settledTime  = 0;
    item.dangerTime    = 0;

    const cascading = (clock - lastDestabilizeAt) <= config.cascadeWindow;
    const hit       = config.integrityBaseHit * (cascading ? config.cascadeMultiplier : 1);
    applyHit(hit);
    lastDestabilizeAt = clock;

    lostLabel.value = item.label;
    if (cascading) {
        cascadeFlash.value = true;
        setTimeout(() => { cascadeFlash.value = false; }, 1400);
    } else {
        destabilizeFlash.value = true;
        setTimeout(() => { destabilizeFlash.value = false; }, 1200);
    }
}

// ── Chrome passthrough ─────────────────────────────────────────────────────────
// Standard dual-bar layout fits here — PAYLOAD and INTEGRITY are both genuine
// "rising/falling = danger" meters, same semantics FlushBuffer already uses.

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

// ── Game loop ──────────────────────────────────────────────────────────────────

let animFrame          = null;
let lastTs             = null;
let clock              = 0;
let lastDestabilizeAt  = -Infinity;

function tick(ts) {
    if (result.value) return;

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;
    clock += dt;

    // Hard deadline
    timeLeft.value = Math.max(0, timeLeft.value - dt);
    if (timeLeft.value <= 0) {
        endGame('fail', '[WINDOW CLOSED] — Delivery window expired before all packages landed.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    let tetheredCount  = 0;
    let outOfBandCount = 0;

    for (const item of subroutines.value) {
        if (item.status !== 'tethered') continue;
        tetheredCount++;

        item.driftTimer -= dt;
        if (item.driftTimer <= 0) {
            item.driftVel   = randomDrift();
            item.driftTimer = config.driftRerollS;
        }
        item.calibration = clamp(item.calibration + item.driftVel * dt, -1, 1);

        const abs = Math.abs(item.calibration);

        if (abs <= config.bandWidth) {
            item.settledTime += dt;
            item.dangerTime   = 0;
            if (item.settledTime >= config.settleThreshold) {
                item.status = 'stable';
            }
        } else {
            item.settledTime = 0;
            outOfBandCount++;
            if (abs > config.dangerThreshold) {
                item.dangerTime += dt;
                if (item.dangerTime >= config.graceThreshold) {
                    destabilize(item);
                }
            } else {
                item.dangerTime = 0;
            }
        }
    }

    // Ambient PAYLOAD rise — faster the more tethers are currently out of band
    primaryProgress.value = Math.min(
        1,
        primaryProgress.value + (config.payloadBaseRate + outOfBandCount * config.payloadPerOutOfBandRate) * dt
    );
    if (primaryProgress.value >= 1) {
        endGame('fail', '[PAYLOAD OVERFLOW] — Volatile signatures spiked past containment. ICE noticed.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Passive INTEGRITY drain — scales with how many packets you're currently carrying
    stability.value = Math.max(
        0,
        stability.value - (config.integrityBaseDrain + tetheredCount * config.integrityPerTetherDrain) * dt
    );
    if (stability.value <= 0) {
        endGame('fail', '[INTEGRITY COLLAPSE] — Containment failure. Rig destabilized.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.ct-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 12px 20px;
    box-sizing: border-box;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
    position: relative;
    overflow-y: auto;
}

/* ── Status row ───────────────────────────────────────────────────────────────── */

.ct-status-row {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
}

.ct-status-item {
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(0,255,100,0.45);
}

/* ── Tether slots ─────────────────────────────────────────────────────────────── */

.ct-slots {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.ct-slot {
    flex: 1;
    min-width: 0;
    border: 1px solid rgba(0,255,100,0.18);
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.ct-slot--stable {
    border-color: rgba(0,255,100,0.55);
    background: rgba(0,255,100,0.05);
}

.ct-slot--danger {
    border-color: rgba(255,51,51,0.55);
    animation: ct-danger-pulse 0.5s ease infinite alternate;
}

.ct-slot-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 9px;
    color: rgba(0,255,100,0.15);
    letter-spacing: 0.1em;
    min-height: 96px;
}

.ct-slot-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ct-slot-id { font-size: 10px; letter-spacing: 0.1em; color: rgba(0,255,100,0.7); }

.ct-slot-badge {
    font-size: 8px;
    letter-spacing: 0.12em;
    color: #00ff9d;
    border: 1px solid rgba(0,255,100,0.4);
    padding: 1px 6px;
}

/* ── Calibration gauge ────────────────────────────────────────────────────────── */

.ct-gauge {
    width: 100%;
    height: 32px;
    display: block;
}

.ct-gauge-track  { fill: rgba(0,255,100,0.05); }
.ct-gauge-danger { fill: rgba(255,51,51,0.10); }
.ct-gauge-band   { fill: rgba(0,255,100,0.12); }
.ct-gauge-center { stroke: rgba(0,255,100,0.25); stroke-width: 1; }

.ct-gauge-needle { stroke-width: 2.5; transition: stroke 0.15s; }
.ct-needle--safe    { stroke: #00ff9d; }
.ct-needle--warn    { stroke: #fb923c; }
.ct-needle--danger  { stroke: #ff3333; }
.ct-needle--stable  { stroke: #00ff9d; stroke-width: 3.5; }

/* ── Settle / containment bar ─────────────────────────────────────────────────── */

.ct-settle-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ct-settle-lbl {
    font-size: 7px;
    letter-spacing: 0.14em;
    color: rgba(0,255,100,0.3);
    flex-shrink: 0;
}

.ct-settle-track {
    flex: 1;
    height: 4px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
}

.ct-settle-fill {
    height: 100%;
    background: linear-gradient(90deg, #003322, #00ff9d);
    transition: width 0.15s linear;
}

.ct-settle-fill--done {
    background: #00ff9d;
    box-shadow: 0 0 6px rgba(0,255,100,0.5);
}

/* ── Slot action buttons ──────────────────────────────────────────────────────── */

.ct-slot-actions {
    display: flex;
    gap: 8px;
}

.ct-nudge-btn, .ct-route-btn {
    flex: 1;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    background: transparent;
    padding: 5px 0;
    cursor: pointer;
    transition: all 0.1s;
}

.ct-nudge-btn {
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
}
.ct-nudge-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.ct-route-btn {
    border: 1px solid rgba(251,146,60,0.35);
    color: rgba(251,146,60,0.75);
}
.ct-route-btn:hover:not(:disabled) {
    background: rgba(251,146,60,0.08);
    border-color: #fb923c;
    color: #fdba74;
}

.ct-nudge-btn:disabled, .ct-route-btn:disabled {
    opacity: 0.22;
    cursor: not-allowed;
}

/* ── Holding pool ─────────────────────────────────────────────────────────────── */

.ct-pool-label {
    font-size: 8px;
    letter-spacing: 0.15em;
    color: rgba(0,255,100,0.3);
    flex-shrink: 0;
}

.ct-pool {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    flex-shrink: 0;
}

.ct-pool-chip {
    font-size: 10px;
    letter-spacing: 0.08em;
    color: rgba(0,255,100,0.65);
    border: 1px solid rgba(0,255,100,0.3);
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.1s;
}
.ct-pool-chip:hover {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}
.ct-pool-chip--disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.ct-pool-empty {
    font-size: 9px;
    color: rgba(0,255,100,0.2);
    padding: 4px 0;
}

/* ── Feedback banners ─────────────────────────────────────────────────────────── */

.ct-banner {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    letter-spacing: 0.12em;
    color: #ff6666;
    background: rgba(20,0,0,0.95);
    border: 1px solid rgba(255,100,100,0.4);
    padding: 6px 18px;
    white-space: nowrap;
    pointer-events: none;
}

.ct-banner--cascade {
    color: #ff3333;
    border-color: #ff3333;
    box-shadow: 0 0 16px rgba(255,51,51,0.35);
}

.ct-banner--ok {
    color: #00ff9d;
    background: rgba(0,20,10,0.95);
    border-color: rgba(0,255,100,0.4);
}

.ct-err-enter-active, .ct-err-leave-active,
.ct-ok-enter-active,  .ct-ok-leave-active { transition: opacity 0.2s; }
.ct-err-enter-from,   .ct-err-leave-to,
.ct-ok-enter-from,    .ct-ok-leave-to     { opacity: 0; }

/* ── Animations ───────────────────────────────────────────────────────────────── */

@keyframes ct-danger-pulse {
    from { box-shadow: 0 0 6px rgba(255,51,51,0.25); }
    to   { box-shadow: 0 0 16px rgba(255,51,51,0.6); }
}
</style>
