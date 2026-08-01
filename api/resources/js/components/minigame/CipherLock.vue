<template>
    <QuestMinigameChrome v-bind="chrome">

        <div class="cl-canvas">

            <!-- ══════════════════════════════════════════════════════════════
                 Top bar — TIME | LETTERS CRACKED
            ══════════════════════════════════════════════════════════════ -->
            <div class="cl-top">
                <div class="cl-meter-group">
                    <span class="cl-meter-lbl">TIME</span>
                    <div class="cl-meter-track">
                        <div class="cl-meter-fill cl-fill--time"
                             :style="{ width: timePct + '%' }"
                             :class="timeClass" />
                    </div>
                    <span class="cl-meter-val" :class="timeClass">{{ Math.ceil(timeLeft) }}s</span>
                </div>
                <div class="cl-codes-readout">
                    <span class="cl-codes-lbl">LETTERS CRACKED</span>
                    <span class="cl-codes-val">{{ lettersCracked }} / {{ lettersRequired }}</span>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 Main area — blanked phrase, legend, input
            ══════════════════════════════════════════════════════════════ -->
            <div class="cl-middle">

                <!-- Blanked phrase -->
                <div class="cl-phrase-row">
                    <template v-for="(cell, i) in displayCells" :key="i">
                        <span v-if="cell.type === 'space'" class="cl-space" />
                        <span v-else class="cl-cell" :class="{ 'cl-cell--revealed': cell.revealed }">
                            {{ cell.revealed ? cell.ch : '_' }}
                        </span>
                    </template>
                </div>

                <!-- Feedback flash -->
                <div class="cl-feedback" :class="feedbackClass">{{ feedbackText }}</div>

                <!-- Input row -->
                <div class="cl-input-row">
                    <span class="cl-input-lbl">DECRYPT KEY ::</span>
                    <input
                        ref="inputEl"
                        v-model="guessCode"
                        class="cl-input"
                        type="text"
                        maxlength="2"
                        placeholder="__"
                        :disabled="!!gameResult"
                        @keyup.enter="submitGuess"
                    />
                    <button class="cl-submit-btn" :disabled="!!gameResult || !guessCode" @click="submitGuess">
                        [ SUBMIT ]
                    </button>
                </div>

                <!-- Cipher legend -->
                <div class="cl-legend">
                    <div v-for="entry in legendEntries" :key="entry.letter"
                         class="cl-legend-cell"
                         :class="{ 'cl-legend-cell--solved': entry.solved }">
                        <span class="cl-legend-letter">{{ entry.letter }}</span>
                        <span class="cl-legend-code">{{ entry.code }}</span>
                    </div>
                </div>

            </div>

        </div>

    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { pickNextPhrase } from '@/data/cipherLockPhrases.js';

// ── Constants ────────────────────────────────────────────────────────────────

const WRONG_GUESS_PENALTY = 15; // seconds lost on a wrong guess
const TIME_AT_ICE_3       = 240; // seconds
const TIME_STEP_PER_TIER  = 30;  // seconds shaved off per ICE tier above 3

// Characters used to build the 2-char decrypt codes. Deliberately excludes
// 0/O and 1/I/L so codes stay readable at a glance.
const CODE_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'.split('');

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── ICE level — drives the timer budget (3-8) ───────────────────────────────

const iceLevel = computed(() =>
    Math.min(8, Math.max(3, props.skin.iceLevel ?? props.skin.difficulty ?? 3))
);

function timeForIce(ice) {
    return TIME_AT_ICE_3 - (ice - 3) * TIME_STEP_PER_TIER;
}

// ── Utilities ────────────────────────────────────────────────────────────────────

function shuffle(arr) {
    const copy = [...arr];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

function makeCipherKey() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    const codes = new Set();
    while (codes.size < letters.length) {
        const c1 = CODE_CHARS[Math.floor(Math.random() * CODE_CHARS.length)];
        const c2 = CODE_CHARS[Math.floor(Math.random() * CODE_CHARS.length)];
        codes.add(c1 + c2);
    }
    const codeList        = [...codes];
    const shuffledLetters = shuffle(letters);
    const key        = {}; // letter -> code
    const reverseKey = {}; // code -> letter
    shuffledLetters.forEach((letter, i) => {
        key[letter] = codeList[i];
        reverseKey[codeList[i]] = letter;
    });
    return { key, reverseKey };
}

// ── Game state ───────────────────────────────────────────────────────────────────

const phrase       = ref('');
const cipherKey     = ref({ key: {}, reverseKey: {} });
const solvedLetters = ref(new Set());
const guessCode      = ref('');
const inputEl        = ref(null);
const timeLeft        = ref(timeForIce(iceLevel.value));
const gameResult      = ref(null);   // null | 'success' | 'fail'
const failReason      = ref('');
const feedbackText    = ref('');
const feedbackClass   = ref('');
let feedbackTimer     = null;

// ── Derived ────────────────────────────────────────────────────────────────────

const uniqueLetters = computed(() =>
    new Set(phrase.value.replace(/[^A-Z]/g, '').split(''))
);

const lettersRequired = computed(() => uniqueLetters.value.size);
const lettersCracked  = computed(() => solvedLetters.value.size);

const allSolved = computed(() =>
    lettersRequired.value > 0 && lettersCracked.value >= lettersRequired.value
);

const displayCells = computed(() =>
    phrase.value.split('').map(ch => {
        if (ch === ' ') return { type: 'space' };
        return { type: 'letter', ch, revealed: solvedLetters.value.has(ch) };
    })
);

const legendEntries = computed(() =>
    Object.keys(cipherKey.value.key)
        .sort()
        .map(letter => ({
            letter,
            code:   cipherKey.value.key[letter],
            solved: solvedLetters.value.has(letter),
        }))
);

const timePct = computed(() => {
    const total = timeForIce(iceLevel.value);
    return Math.max(0, Math.min(100, (timeLeft.value / total) * 100));
});

const timeClass = computed(() => {
    if (timeLeft.value <= 20) return 'cl-val--crit';
    if (timeLeft.value <= 45) return 'cl-val--warn';
    return '';
});

// ── Feedback flash ──────────────────────────────────────────────────────────────

function flashFeedback(kind, text) {
    feedbackClass.value = `cl-feedback--${kind}`;
    feedbackText.value  = text;
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
        feedbackText.value  = '';
        feedbackClass.value = '';
    }, 1100);
}

// ── Guess handling ───────────────────────────────────────────────────────────────

function submitGuess() {
    if (gameResult.value) return;
    const code = guessCode.value.trim().toUpperCase();
    guessCode.value = '';
    nextTick(() => inputEl.value?.focus());
    if (!code) return;

    const letter = cipherKey.value.reverseKey[code];

    if (letter && solvedLetters.value.has(letter)) {
        // Already-cracked letter — no penalty, just acknowledge.
        flashFeedback('repeat', `${code} :: ALREADY DECRYPTED`);
        return;
    }

    if (letter && uniqueLetters.value.has(letter)) {
        solvedLetters.value = new Set([...solvedLetters.value, letter]);
        flashFeedback('correct', `${code} :: ${letter}`);
        if (allSolved.value) endGame('success', '');
        return;
    }

    // Wrong: invalid code, or a valid code for a letter not in this phrase.
    timeLeft.value = Math.max(0, timeLeft.value - WRONG_GUESS_PENALTY);
    flashFeedback('wrong', `${code} :: REJECTED (-${WRONG_GUESS_PENALTY}s)`);
    if (timeLeft.value <= 0) {
        endGame('fail', '[TIMER EXPIRED] — Cipher could not be cracked in time.');
    }
}

// ── Win / fail ─────────────────────────────────────────────────────────────────

function endGame(result, reason) {
    if (gameResult.value) return;
    gameResult.value = result;
    failReason.value = reason ?? '';
    clearInterval(tickHandle);
    if (result === 'success') {
        setTimeout(() => emit('complete'), 2200);
    } else {
        setTimeout(() => emit('fail'), 2200);
    }
}

// ── Tick system ────────────────────────────────────────────────────────────────

let tickHandle = null;

function startTimer() {
    tickHandle = setInterval(() => {
        if (gameResult.value) return;
        timeLeft.value = Math.max(0, timeLeft.value - 1);
        if (timeLeft.value <= 0) {
            endGame('fail', '[TIMER EXPIRED] — Cipher could not be cracked in time.');
        }
    }, 1000);
}

// ── Chrome ─────────────────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: 0,
    stability:       1,
    stabilityClass:  '',
    timerClass:      timeLeft.value <= 20 ? 'timer--critical' : timeLeft.value <= 45 ? 'timer--warn' : '',
    glitchActive:    feedbackClass.value === 'cl-feedback--wrong',
    glitchType:      'static',
    glitchIntensity: 0.15,
    result:          gameResult.value,
    failReason:      failReason.value,
    hideBars:        true,
}));

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    phrase.value      = pickNextPhrase();
    cipherKey.value   = makeCipherKey();
    solvedLetters.value = new Set();
    timeLeft.value    = timeForIce(iceLevel.value);
    startTimer();
    nextTick(() => inputEl.value?.focus());
});

onUnmounted(() => {
    clearInterval(tickHandle);
    if (feedbackTimer) clearTimeout(feedbackTimer);
});
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════════════════
   Canvas
══════════════════════════════════════════════════════════════════════════════ */

.cl-canvas {
    width: 1920px;
    height: 100%;
    display: grid;
    grid-template-rows: 64px 1fr;
    font-family: 'JetBrains Mono', monospace;
    background: #04090e;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Top bar ──────────────────────────────────────────────────────────────── */

.cl-top {
    display: flex;
    align-items: center;
    gap: 32px;
    padding: 0 24px;
    background: rgba(0,0,0,0.5);
    border-bottom: 1px solid rgba(0,200,240,0.15);
}

.cl-meter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    max-width: 420px;
}

.cl-meter-lbl {
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.5);
    flex-shrink: 0;
}

.cl-meter-track {
    position: relative;
    flex: 1;
    height: 8px;
    background: rgba(0,0,0,0.6);
    border: 1px solid rgba(0,200,240,0.2);
}

.cl-meter-fill {
    height: 100%;
    transition: width 0.2s linear;
}

.cl-fill--time {
    background: #00c8f0;
}

.cl-meter-val {
    font-size: 11px;
    font-weight: 700;
    min-width: 42px;
    text-align: right;
}

.cl-val--warn { color: #fb923c; }
.cl-val--crit { color: #ff3333; }

.cl-fill--time.cl-val--warn { background: #fb923c; }
.cl-fill--time.cl-val--crit { background: #ff3333; }

.cl-codes-readout {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    flex-shrink: 0;
}

.cl-codes-lbl {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.4);
}

.cl-codes-val {
    font-size: 13px;
    font-weight: 700;
    color: #00ff9d;
}

/* ── Middle area ──────────────────────────────────────────────────────────── */

.cl-middle {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    padding: 32px;
}

/* ── Phrase display ───────────────────────────────────────────────────────── */

.cl-phrase-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 6px;
    max-width: 1200px;
}

.cl-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 42px;
    border-bottom: 2px solid rgba(0,200,240,0.4);
    font-size: 22px;
    font-weight: 700;
    color: rgba(0,200,240,0.35);
    transition: color 0.15s, border-color 0.15s;
}

.cl-cell--revealed {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.6);
    text-shadow: 0 0 10px rgba(0,255,100,0.5);
}

.cl-space {
    width: 20px;
}

/* ── Feedback ─────────────────────────────────────────────────────────────── */

.cl-feedback {
    min-height: 18px;
    font-size: 13px;
    letter-spacing: 0.1em;
    font-weight: 700;
}

.cl-feedback--correct { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.5); }
.cl-feedback--wrong    { color: #ff3333; text-shadow: 0 0 8px rgba(255,51,51,0.5); }
.cl-feedback--repeat   { color: rgba(0,200,240,0.5); }

/* ── Input row ────────────────────────────────────────────────────────────── */

.cl-input-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cl-input-lbl {
    font-size: 11px;
    letter-spacing: 0.14em;
    color: rgba(0,200,240,0.6);
}

.cl-input {
    width: 70px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    text-align: center;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    background: rgba(0,10,18,0.9);
    border: 1px solid rgba(0,200,240,0.4);
    color: #00c8f0;
    padding: 8px 4px;
}

.cl-input:focus {
    outline: none;
    border-color: #00c8f0;
    box-shadow: 0 0 10px rgba(0,200,240,0.3);
}

.cl-submit-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.12em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.4);
    color: rgba(0,255,100,0.8);
    padding: 8px 18px;
    cursor: pointer;
    transition: all 0.12s;
}

.cl-submit-btn:hover:not(:disabled) {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,255,100,0.08);
    box-shadow: 0 0 10px rgba(0,255,100,0.2);
}

.cl-submit-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

/* ── Legend grid ──────────────────────────────────────────────────────────── */

.cl-legend {
    display: grid;
    grid-template-columns: repeat(13, minmax(56px, 1fr));
    gap: 6px;
    max-width: 1000px;
}

.cl-legend-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 6px 4px;
    border: 1px solid rgba(0,200,240,0.18);
    background: rgba(0,10,18,0.6);
}

.cl-legend-letter {
    font-size: 12px;
    font-weight: 700;
    color: rgba(0,200,240,0.7);
}

.cl-legend-code {
    font-size: 10px;
    letter-spacing: 0.06em;
    color: rgba(0,200,240,0.4);
}

.cl-legend-cell--solved {
    border-color: rgba(0,255,100,0.4);
    background: rgba(0,20,12,0.6);
}

.cl-legend-cell--solved .cl-legend-letter,
.cl-legend-cell--solved .cl-legend-code {
    color: rgba(0,255,100,0.5);
    text-decoration: line-through;
}
</style>
