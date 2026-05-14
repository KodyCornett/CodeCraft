<template>
    <div class="bp-overlay">
        <!-- HUD bar -->
        <div class="bp-hud">
            <div class="bp-progress-track">
                <div class="bp-progress-fill" :style="{ width: progressPct + '%' }" />
                <div class="bp-progress-target" :style="{ left: TARGET_PCT + '%' }" />
                <span class="bp-progress-label">{{ poppedCount }}/{{ targetPops }}</span>
            </div>
            <div class="bp-timer" :class="{ 'bp-timer--low': timeLeft <= 5 }">
                {{ timeLeft.toString().padStart(2, '0') }}s
            </div>
        </div>

        <!-- Play area -->
        <div ref="playArea" class="bp-play-area">
            <div
                v-for="b in bubbles"
                :key="b.id"
                class="bp-bubble"
                :class="{ 'bp-bubble--threat': b.isThreat }"
                :style="{
                    left:   b.x + 'px',
                    bottom: b.bottomPx + 'px',
                    width:  b.r * 2 + 'px',
                    height: b.r * 2 + 'px',
                }"
                @click.stop="popBubble(b.id)"
            />
        </div>

        <div class="bp-label">TAP BUBBLES — POP DATA PACKETS</div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    tier: { type: Number, default: 1 },
});
const emit = defineEmits(['done']);

// ── Config per tier ───────────────────────────────────────────────────────────
const TIER_CONFIG = {
    1: { duration: 20, targetPops: 10, spawnMs: 1000, speedMin: 1.2, speedMax: 2.0, threatEvery: 5000 },
    2: { duration: 15, targetPops: 12, spawnMs:  800, speedMin: 1.8, speedMax: 3.0, threatEvery: 3500 },
    3: { duration: 10, targetPops: 15, spawnMs:  600, speedMin: 2.5, speedMax: 4.2, threatEvery: 2200 },
};
const cfg       = computed(() => TIER_CONFIG[props.tier] ?? TIER_CONFIG[1]);
const TARGET_PCT = 100; // progress bar is full at target

// ── State ─────────────────────────────────────────────────────────────────────
const playArea   = ref(null);
const bubbles    = ref([]);
const poppedCount = ref(0);
const timeLeft   = ref(0);
const done       = ref(false);
let   nextId     = 0;
let   tickTimer  = null;
let   spawnTimer = null;
let   threatTimer = null;
let   gameTimer  = null;

const targetPops  = computed(() => cfg.value.targetPops);
const progressPct = computed(() => Math.min(100, (poppedCount.value / targetPops.value) * 100));

// ── Helpers ───────────────────────────────────────────────────────────────────
function areaWidth()  { return playArea.value?.clientWidth  ?? 400; }
function areaHeight() { return playArea.value?.clientHeight ?? 600; }

function spawnBubble(isThreat = false) {
    if (done.value) return;
    const r = isThreat ? 26 : (16 + Math.random() * 10);
    const x = r + Math.random() * (areaWidth() - r * 2);
    const id = ++nextId;
    const speed = cfg.value.speedMin + Math.random() * (cfg.value.speedMax - cfg.value.speedMin);

    bubbles.value.push({ id, x, r, isThreat, speed, bottomPx: -(r * 2) });

    // Escape timeout: time = (areaHeight + r*2) / (speed * 30fps * px_per_tick)
    // Each tick advances by `speed` pixels at 30fps
    const ticksNeeded = (areaHeight() + r * 2 + 40) / speed;
    const escapeMs = (ticksNeeded / 30) * 1000;

    setTimeout(() => escapeBubble(id, isThreat), escapeMs);
}

function escapeBubble(id, isThreat) {
    if (done.value) return;
    bubbles.value = bubbles.value.filter(b => b.id !== id);
    if (isThreat) {
        timeLeft.value = Math.max(0, timeLeft.value - 4);
    }
}

function popBubble(id) {
    if (done.value) return;
    const b = bubbles.value.find(b => b.id === id);
    if (!b) return;
    bubbles.value = bubbles.value.filter(b => b.id !== id);
    if (!b.isThreat) {
        poppedCount.value++;
        if (poppedCount.value >= targetPops.value) finish(true);
    }
}

function finish(success) {
    if (done.value) return;
    done.value = true;
    clearAllTimers();
    setTimeout(() => emit('done', { success }), 300);
}

function clearAllTimers() {
    clearInterval(tickTimer);
    clearInterval(spawnTimer);
    clearInterval(threatTimer);
    clearInterval(gameTimer);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────
onMounted(() => {
    timeLeft.value = cfg.value.duration;

    // Physics tick: move all bubbles up by their speed
    tickTimer = setInterval(() => {
        if (done.value) return;
        for (const b of bubbles.value) b.bottomPx += b.speed;
    }, 1000 / 30);

    // Standard bubble spawner
    spawnTimer = setInterval(() => {
        if (!done.value) spawnBubble(false);
    }, cfg.value.spawnMs);

    // Threat bubble spawner
    threatTimer = setInterval(() => {
        if (!done.value) spawnBubble(true);
    }, cfg.value.threatEvery);

    // Countdown timer
    gameTimer = setInterval(() => {
        if (done.value) return;
        timeLeft.value--;
        if (timeLeft.value <= 0) finish(false);
    }, 1000);

    // Initial burst of bubbles
    for (let i = 0; i < 4; i++) {
        setTimeout(() => spawnBubble(false), i * 300);
    }
});

onUnmounted(clearAllTimers);
</script>

<style scoped>
.bp-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: #020202;
    display: flex;
    flex-direction: column;
    align-items: center;
    overflow: hidden;
    font-family: 'JetBrains Mono', monospace;
}

/* ── HUD ─────────────────────────────────────────────────────────────────────── */
.bp-hud {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 16px 24px 10px;
    width: 100%;
    box-sizing: border-box;
    z-index: 2;
}

.bp-progress-track {
    flex: 1;
    position: relative;
    height: 14px;
    background: rgba(0, 255, 255, 0.05);
    border: 1px solid rgba(0, 255, 255, 0.2);
}

.bp-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #00CC66, #00FF88);
    transition: width 0.15s ease;
    box-shadow: 0 0 8px rgba(0, 255, 136, 0.5);
}

.bp-progress-target {
    position: absolute;
    top: -3px;
    bottom: -3px;
    width: 2px;
    background: #FFB300;
    opacity: 0.7;
    transform: translateX(-50%);
}

.bp-progress-label {
    position: absolute;
    right: 6px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 8px;
    color: rgba(0, 255, 255, 0.5);
    letter-spacing: 0.04em;
}

.bp-timer {
    font-size: 22px;
    letter-spacing: 0.06em;
    color: #00FFFF;
    min-width: 56px;
    text-align: right;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
}
.bp-timer--low {
    color: #FF3333;
    animation: timer-pulse 0.5s ease-in-out infinite;
    text-shadow: 0 0 12px rgba(255, 51, 51, 0.6);
}

@keyframes timer-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.55; }
}

/* ── Play area ───────────────────────────────────────────────────────────────── */
.bp-play-area {
    flex: 1;
    width: 100%;
    position: relative;
    overflow: hidden;
}

.bp-bubble {
    position: absolute;
    border-radius: 50%;
    cursor: crosshair;
    background: radial-gradient(circle at 35% 35%, rgba(0, 255, 255, 0.9), rgba(0, 180, 200, 0.5));
    border: 1px solid rgba(0, 255, 255, 0.6);
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3), inset 0 0 8px rgba(0, 255, 255, 0.1);
    transition: transform 0.06s;
}
.bp-bubble:hover { transform: scale(1.08); }
.bp-bubble:active { transform: scale(0.9); }

.bp-bubble--threat {
    background: radial-gradient(circle at 35% 35%, rgba(255, 0, 204, 0.9), rgba(180, 0, 140, 0.5));
    border-color: rgba(255, 0, 204, 0.7);
    box-shadow: 0 0 14px rgba(255, 0, 204, 0.4), inset 0 0 10px rgba(255, 0, 204, 0.15);
    animation: threat-pulse 0.8s ease-in-out infinite;
}

@keyframes threat-pulse {
    0%, 100% { box-shadow: 0 0 14px rgba(255, 0, 204, 0.4), inset 0 0 10px rgba(255, 0, 204, 0.15); }
    50%       { box-shadow: 0 0 24px rgba(255, 0, 204, 0.7), inset 0 0 16px rgba(255, 0, 204, 0.3); }
}

/* ── Label ───────────────────────────────────────────────────────────────────── */
.bp-label {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.25);
    letter-spacing: 0.1em;
    padding: 8px 0 14px;
}
</style>
