<template>
    <QuestMinigameChrome v-bind="chrome">

        <div class="cl-canvas">

            <!-- Ambient scanline/noise texture — purely decorative -->
            <div class="cl-noise" />

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
                <div class="cl-wrong-readout">
                    <span class="cl-wrong-lbl">WRONG GUESSES</span>
                    <div class="cl-wrong-pips">
                        <span v-for="i in WRONG_PIP_CAP" :key="i"
                              class="cl-wrong-pip"
                              :class="{ 'cl-wrong-pip--used': i <= wrongGuesses }">●</span>
                    </div>
                    <span class="cl-wrong-val" :class="{ 'cl-wrong-val--hot': wrongGuesses > 0 }">{{ wrongGuesses }}</span>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 Main area — blanked phrase, legend, input
            ══════════════════════════════════════════════════════════════ -->
            <div class="cl-middle">

                <!-- Cipher key / letter bank — hangman-style guess tracker.
                     Every letter A-Z is shown up front; guessing a code marks
                     its letter here green (in the phrase) or red (ruled out). -->
                <div class="cl-legend-wrap">
                    <span class="cl-legend-title">CIPHER KEY // A&ndash;Z</span>
                    <div class="cl-frame">
                        <span class="cl-corner cl-corner--tl" />
                        <span class="cl-corner cl-corner--tr" />
                        <span class="cl-corner cl-corner--bl" />
                        <span class="cl-corner cl-corner--br" />
                        <div class="cl-legend">
                            <div v-for="entry in legendEntries" :key="entry.letter"
                                 class="cl-legend-cell"
                                 :class="{ 'cl-legend-cell--solved': entry.solved, 'cl-legend-cell--wrong': entry.wrong }">
                                <span class="cl-legend-letter">{{ entry.letter }}</span>
                                <span class="cl-legend-code">{{ entry.code }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blanked phrase -->
                <div class="cl-frame cl-frame--phrase">
                    <span class="cl-corner cl-corner--tl" />
                    <span class="cl-corner cl-corner--tr" />
                    <span class="cl-corner cl-corner--bl" />
                    <span class="cl-corner cl-corner--br" />
                    <div class="cl-phrase-row">
                        <template v-for="(cell, i) in displayCells" :key="i">
                            <span v-if="cell.type === 'space'" class="cl-space" />
                            <span v-else class="cl-cell" :class="{ 'cl-cell--revealed': cell.revealed }">
                                {{ cell.revealed ? cell.ch : '_' }}
                            </span>
                        </template>
                    </div>
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

                <!-- Terminal attempt log -->
                <div class="cl-log">
                    <span class="cl-log-title">ATTEMPT LOG</span>
                    <div class="cl-log-body">
                        <span v-if="!attemptLog.length" class="cl-log-empty">// no attempts yet</span>
                        <div v-for="entry in attemptLog" :key="entry.id"
                             class="cl-log-line" :class="`cl-log-line--${entry.kind}`">
                            <span class="cl-log-prompt">&gt;</span> {{ entry.text }}
                        </div>
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
const WRONG_PIP_CAP       = 8;   // pip slots shown in the top bar before they just stay lit
const LOG_MAX_ENTRIES     = 7;   // attempt log keeps the most recent N entries

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

// Free letters revealed before the timer starts — eases lower ICE tiers,
// nothing given away at the top end.
function freeLettersForIce(ice) {
    if (ice <= 4) return 3;
    if (ice <= 6) return 2;
    return 0;
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
const ruledOutLetters = ref(new Set()); // legend letters confirmed NOT in this phrase
const guessCode      = ref('');
const inputEl        = ref(null);
const timeLeft        = ref(timeForIce(iceLevel.value));
const gameResult      = ref(null);   // null | 'success' | 'fail'
const failReason      = ref('');
const feedbackText    = ref('');
const feedbackClass   = ref('');
const wrongGuesses    = ref(0);
const attemptLog      = ref([]); // most recent first — [{ id, code, kind, text }]
let logIdCounter      = 0;
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
            wrong:  ruledOutLetters.value.has(letter),
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

function logAttempt(kind, code, text) {
    attemptLog.value = [{ id: logIdCounter++, code, kind, text }, ...attemptLog.value].slice(0, LOG_MAX_ENTRIES);
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
        logAttempt('repeat', code, `${code} :: already decrypted (${letter})`);
        return;
    }

    if (letter && ruledOutLetters.value.has(letter)) {
        // Already confirmed dead — no penalty, just acknowledge.
        flashFeedback('repeat', `${code} :: ALREADY RULED OUT`);
        logAttempt('repeat', code, `${code} :: already ruled out (${letter})`);
        return;
    }

    if (letter && uniqueLetters.value.has(letter)) {
        solvedLetters.value = new Set([...solvedLetters.value, letter]);
        flashFeedback('correct', `${code} :: ${letter}`);
        logAttempt('correct', code, `${code} :: MATCH — ${letter}`);
        if (allSolved.value) endGame('success', '');
        return;
    }

    // Wrong: invalid code, or a valid code for a letter not in this phrase.
    // When it resolves to a real letter that's just not in this phrase,
    // flag that legend cell so it's not retried.
    if (letter) {
        ruledOutLetters.value = new Set([...ruledOutLetters.value, letter]);
    }
    wrongGuesses.value++;
    timeLeft.value = Math.max(0, timeLeft.value - WRONG_GUESS_PENALTY);
    flashFeedback('wrong', `${code} :: REJECTED (-${WRONG_GUESS_PENALTY}s)`);
    logAttempt('wrong', code, `${code} :: REJECTED (-${WRONG_GUESS_PENALTY}s)`);
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
    ruledOutLetters.value = new Set();

    // Reveal a few free letters up front based on ICE tier, capped so at
    // least one letter is always left for the player to actually crack.
    const pool     = shuffle([...uniqueLetters.value]);
    const freeCount = Math.min(freeLettersForIce(iceLevel.value), Math.max(0, pool.length - 1));
    solvedLetters.value = new Set(pool.slice(0, freeCount));

    timeLeft.value    = timeForIce(iceLevel.value);
    wrongGuesses.value = 0;
    attemptLog.value = [];
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
    position: relative;
    width: 100%;
    height: 100%;
    display: grid;
    grid-template-rows: 64px 1fr;
    font-family: 'JetBrains Mono', monospace;
    background: #04090e;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Ambient noise layer ──────────────────────────────────────────────────── */

.cl-noise {
    position: absolute;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    background-image:
        repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,200,240,0.02) 2px, rgba(0,200,240,0.02) 4px),
        radial-gradient(ellipse at 50% 30%, rgba(0,200,240,0.05), transparent 65%);
    background-size: 100% 4px, 100% 100%;
    animation: cl-noise-drift 9s linear infinite;
}

@keyframes cl-noise-drift {
    0%   { background-position: 0 0, 0 0; }
    100% { background-position: 0 400px, 0 0; }
}

/* ── Top bar ──────────────────────────────────────────────────────────────── */

.cl-top {
    position: relative;
    z-index: 1;
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

.cl-wrong-readout {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    margin-left: auto;
}

.cl-wrong-lbl {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.4);
}

.cl-wrong-pips {
    display: flex;
    gap: 3px;
}

.cl-wrong-pip {
    font-size: 9px;
    color: rgba(255,51,51,0.15);
    transition: color 0.15s;
}

.cl-wrong-pip--used {
    color: #ff3333;
    text-shadow: 0 0 6px rgba(255,51,51,0.6);
}

.cl-wrong-val {
    font-size: 13px;
    font-weight: 700;
    min-width: 16px;
    color: rgba(255,51,51,0.5);
}

.cl-wrong-val--hot {
    color: #ff3333;
}

/* ── Middle area ──────────────────────────────────────────────────────────── */

.cl-middle {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 26px;
    padding: 28px 32px;
    height: 100%;
    box-sizing: border-box;
    overflow-y: auto;
}

/* ── Cipher key / letter bank ────────────────────────────────────────────── */

.cl-legend-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.cl-legend-title {
    font-size: 10px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.4);
}

/* ── HUD corner-bracket frame ─────────────────────────────────────────────── */

.cl-frame {
    position: relative;
    padding: 18px 22px;
    border: 1px solid rgba(0,200,240,0.12);
    background: rgba(0,10,18,0.35);
}

.cl-frame--phrase {
    display: flex;
    justify-content: center;
}

.cl-corner {
    position: absolute;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0,200,240,0.55);
    pointer-events: none;
}

.cl-corner--tl { top: -1px;    left: -1px;  border-right: none;  border-bottom: none; }
.cl-corner--tr { top: -1px;    right: -1px; border-left: none;   border-bottom: none; }
.cl-corner--bl { bottom: -1px; left: -1px;  border-right: none;  border-top: none; }
.cl-corner--br { bottom: -1px; right: -1px; border-left: none;   border-top: none; }

/* ── Phrase display ───────────────────────────────────────────────────────── */

.cl-phrase-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
    max-width: 1300px;
}

.cl-cell {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 54px;
    border-bottom: 2px solid rgba(0,200,240,0.4);
    font-size: 28px;
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

/* ── Terminal attempt log ─────────────────────────────────────────────────── */

.cl-log {
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
    max-width: 560px;
    padding: 12px 16px;
    border: 1px solid rgba(0,200,240,0.12);
    background: rgba(0,10,18,0.35);
}

.cl-log-title {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.35);
}

.cl-log-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cl-log-empty {
    font-size: 11px;
    color: rgba(0,200,240,0.2);
}

.cl-log-line {
    font-size: 11px;
    letter-spacing: 0.03em;
    color: rgba(0,200,240,0.45);
}

.cl-log-prompt {
    color: rgba(0,200,240,0.3);
    margin-right: 4px;
}

.cl-log-line--correct { color: rgba(0,255,157,0.8); }
.cl-log-line--wrong    { color: rgba(255,51,51,0.75); }
.cl-log-line--repeat   { color: rgba(0,200,240,0.35); }

/* ── Legend grid ──────────────────────────────────────────────────────────── */

.cl-legend {
    display: grid;
    grid-template-columns: repeat(13, minmax(68px, 1fr));
    gap: 8px;
    max-width: 1200px;
}

.cl-legend-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 6px;
    border: 1px solid rgba(0,200,240,0.18);
    background: rgba(0,10,18,0.6);
}

.cl-legend-letter {
    font-size: 12px;
    font-weight: 600;
    color: rgba(0,200,240,0.5);
    letter-spacing: 0.04em;
}

.cl-legend-code {
    font-size: 19px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: #00c8f0;
    text-shadow: 0 0 6px rgba(0,200,240,0.35);
}

.cl-legend-cell--solved {
    border-color: rgba(0,255,100,0.5);
    background: rgba(0,20,12,0.6);
    box-shadow: 0 0 12px rgba(0,255,100,0.15);
}

.cl-legend-cell--solved .cl-legend-letter {
    color: rgba(0,255,100,0.6);
    text-decoration: line-through;
}

.cl-legend-cell--solved .cl-legend-code {
    color: #00ff9d;
    text-shadow: 0 0 8px rgba(0,255,100,0.6);
    text-decoration: line-through;
}

.cl-legend-cell--wrong {
    border-color: rgba(255,51,51,0.5);
    background: rgba(20,0,0,0.5);
    box-shadow: 0 0 12px rgba(255,51,51,0.15);
}

.cl-legend-cell--wrong .cl-legend-letter {
    color: rgba(255,51,51,0.65);
    text-decoration: line-through;
}

.cl-legend-cell--wrong .cl-legend-code {
    color: #ff3333;
    text-shadow: 0 0 8px rgba(255,51,51,0.6);
    text-decoration: line-through;
}
</style>
