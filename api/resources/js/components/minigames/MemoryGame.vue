<template>
    <div class="mg-overlay">
        <!-- Header -->
        <div class="mg-header">
            <span class="mg-title">// MEMORY MATRIX —</span>
            <span class="mg-phase-label" :class="`mg-phase--${phase}`">{{ phaseLabel }}</span>
            <span v-if="phase === 'flash'" class="mg-countdown">{{ flashCountdown.toFixed(1) }}s</span>
        </div>

        <!-- Grid -->
        <div class="mg-grid">
            <div
                v-for="i in TOTAL"
                :key="i - 1"
                class="mg-cell"
                :class="cellClass(i - 1)"
                @click="toggleCell(i - 1)"
            />
        </div>

        <!-- Footer actions -->
        <div class="mg-footer">
            <template v-if="phase === 'flash'">
                <span class="mg-hint">MEMORIZE THE PATTERN</span>
            </template>
            <template v-else-if="phase === 'recall'">
                <span class="mg-hint">SELECT {{ litCount }} SQUARES — {{ selectedCount }}/{{ litCount }}</span>
                <button class="mg-submit" @click="submit">SUBMIT</button>
            </template>
            <template v-else-if="phase === 'revealed'">
                <span class="mg-result" :class="lastSuccess ? 'mg-result--pass' : 'mg-result--fail'">
                    {{ lastSuccess ? 'MATCH CONFIRMED' : 'PATTERN MISMATCH' }}
                </span>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    tier: { type: Number, default: 1 },
});
const emit = defineEmits(['done']);

// ── Config ────────────────────────────────────────────────────────────────────
const ROWS  = 5;
const COLS  = 4;
const TOTAL = ROWS * COLS; // 20

const LIT_PER_TIER   = { 1: 5, 2: 8, 3: 12 };
const THRESHOLD_PCT  = 0.70; // 70% correct to succeed
const FLASH_DURATION = 2.0;  // seconds

const litCount = computed(() => LIT_PER_TIER[props.tier] ?? 5);

// ── State ─────────────────────────────────────────────────────────────────────
const litSquares       = ref(new Set()); // indices lit during flash
const selectedSquares  = ref(new Set()); // indices player clicked
const phase            = ref('flash');   // 'flash' | 'recall' | 'revealed'
const flashCountdown   = ref(FLASH_DURATION);
const lastSuccess      = ref(false);

let flashTimer    = null;
let countdownTimer = null;

const selectedCount = computed(() => selectedSquares.value.size);

const phaseLabel = computed(() => ({
    flash:    '[ PATTERN FLASH ]',
    recall:   '[ SELECT SQUARES ]',
    revealed: '[ RESULT ]',
}[phase.value] ?? ''));

// ── Cell class helper ─────────────────────────────────────────────────────────
function cellClass(i) {
    if (phase.value === 'flash') {
        return litSquares.value.has(i) ? 'mg-cell--lit' : '';
    }
    if (phase.value === 'recall') {
        return selectedSquares.value.has(i) ? 'mg-cell--selected' : '';
    }
    if (phase.value === 'revealed') {
        const isLit      = litSquares.value.has(i);
        const isSelected = selectedSquares.value.has(i);
        if (isLit  &&  isSelected) return 'mg-cell--correct';
        if (isLit  && !isSelected) return 'mg-cell--missed';
        if (!isLit &&  isSelected) return 'mg-cell--wrong';
        return '';
    }
    return '';
}

// ── Toggle selection ──────────────────────────────────────────────────────────
function toggleCell(i) {
    if (phase.value !== 'recall') return;
    const s = new Set(selectedSquares.value);
    if (s.has(i)) s.delete(i);
    else          s.add(i);
    selectedSquares.value = s;
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submit() {
    if (phase.value !== 'recall') return;
    phase.value = 'revealed';

    let correct = 0;
    for (const i of litSquares.value) {
        if (selectedSquares.value.has(i)) correct++;
    }

    const score = correct / litCount.value;
    lastSuccess.value = score >= THRESHOLD_PCT;

    setTimeout(() => emit('done', { success: lastSuccess.value }), 1800);
}

// ── Init flash phase ──────────────────────────────────────────────────────────
function startFlash() {
    // Pick random lit squares
    const indices = Array.from({ length: TOTAL }, (_, i) => i);
    // Shuffle and take first litCount
    for (let i = indices.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [indices[i], indices[j]] = [indices[j], indices[i]];
    }
    litSquares.value = new Set(indices.slice(0, litCount.value));

    flashCountdown.value = FLASH_DURATION;

    // Countdown tick
    countdownTimer = setInterval(() => {
        flashCountdown.value = Math.max(0, flashCountdown.value - 0.1);
    }, 100);

    // Strict 2s flash
    flashTimer = setTimeout(() => {
        clearInterval(countdownTimer);
        phase.value = 'recall';
    }, FLASH_DURATION * 1000);
}

onMounted(startFlash);

onUnmounted(() => {
    clearTimeout(flashTimer);
    clearInterval(countdownTimer);
});
</script>

<style scoped>
.mg-overlay {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: #020202;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.mg-header {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    letter-spacing: 0.06em;
}

.mg-title { color: rgba(0, 255, 255, 0.4); }

.mg-phase-label { font-size: 11px; }
.mg-phase--flash    { color: #00FFFF; }
.mg-phase--recall   { color: #FF00CC; }
.mg-phase--revealed { color: #00FF88; }

.mg-countdown {
    font-size: 11px;
    color: #FFB300;
    min-width: 36px;
}

/* ── Grid ────────────────────────────────────────────────────────────────────── */
.mg-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    grid-template-rows: repeat(5, 1fr);
    gap: 6px;
    padding: 20px;
    border: 1px solid rgba(0, 255, 255, 0.12);
    background: rgba(5, 5, 5, 0.8);
}

.mg-cell {
    width: 60px;
    height: 52px;
    background: rgba(10, 10, 10, 0.9);
    border: 1px solid rgba(50, 50, 50, 0.8);
    cursor: default;
    transition: background 0.12s, border-color 0.12s, box-shadow 0.12s;
}

.mg-cell--lit {
    background: rgba(0, 255, 255, 0.35);
    border-color: #00FFFF;
    box-shadow: 0 0 12px rgba(0, 255, 255, 0.5);
}

.mg-cell--selected {
    background: rgba(255, 0, 204, 0.25);
    border-color: #FF00CC;
    box-shadow: 0 0 8px rgba(255, 0, 204, 0.3);
    cursor: pointer;
}
.mg-cell:not(.mg-cell--selected):not(.mg-cell--lit):not(.mg-cell--correct):not(.mg-cell--missed):not(.mg-cell--wrong) {
    cursor: pointer;
}
.mg-cell:not([class*='mg-cell--']):hover,
.mg-cell.mg-cell:hover:not(.mg-cell--lit) {
    border-color: rgba(255, 0, 204, 0.3);
}

.mg-cell--correct {
    background: rgba(0, 255, 136, 0.3);
    border-color: #00FF88;
    box-shadow: 0 0 10px rgba(0, 255, 136, 0.4);
}

.mg-cell--missed {
    background: rgba(0, 255, 136, 0.1);
    border-color: rgba(0, 255, 136, 0.4);
    animation: missed-pulse 0.6s ease-out;
}

.mg-cell--wrong {
    background: rgba(255, 51, 51, 0.25);
    border-color: #FF3333;
    box-shadow: 0 0 8px rgba(255, 51, 51, 0.3);
}

@keyframes missed-pulse {
    0%   { box-shadow: 0 0 0 rgba(0, 255, 136, 0); }
    50%  { box-shadow: 0 0 16px rgba(0, 255, 136, 0.5); }
    100% { box-shadow: none; }
}

/* ── Footer ──────────────────────────────────────────────────────────────────── */
.mg-footer {
    display: flex;
    align-items: center;
    gap: 20px;
    min-height: 36px;
}

.mg-hint {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.06em;
}

.mg-submit {
    background: none;
    border: 1px solid rgba(255, 0, 204, 0.5);
    color: #FF00CC;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.06em;
    padding: 7px 18px;
    cursor: pointer;
    transition: background 0.1s;
}
.mg-submit:hover { background: rgba(255, 0, 204, 0.08); }

.mg-result { font-size: 14px; letter-spacing: 0.1em; }
.mg-result--pass { color: #00FF88; text-shadow: 0 0 12px rgba(0, 255, 136, 0.6); }
.mg-result--fail { color: #FF3333; text-shadow: 0 0 12px rgba(255, 51, 51, 0.5); }
</style>
