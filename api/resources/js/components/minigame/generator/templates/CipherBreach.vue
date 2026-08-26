<template>
    <div class="cph-overlay">
        <div class="cph-terminal">

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="cph-topbar">
                <span>NODE: {{ nodeLabel }}</span>
                <span class="cph-timer" :class="timerClass">TIME REMAINING: {{ Math.ceil(timeLeft) }}s</span>
                <button class="cph-abort-btn" @click="onAbort">[ ABORT ]</button>
            </div>
            <div class="cph-rule" />

            <!-- ── Info bar ─────────────────────────────────────────────────── -->
            <div class="cph-infobar">
                <div class="cph-info-block">
                    <span class="cph-info-label">LETTERS CRACKED</span>
                    <span class="cph-info-val">{{ lettersCracked }} / {{ lettersRequired }}</span>
                </div>
                <div class="cph-info-block">
                    <span class="cph-info-label">WRONG GUESSES</span>
                    <span class="cph-att-pips">
                        <span
                            v-for="i in WRONG_PIP_CAP" :key="i"
                            class="cph-att-pip"
                            :class="{ 'cph-att-pip--used': i <= wrongGuesses }"
                        >●</span>
                    </span>
                </div>
            </div>

            <!-- ── Cipher key legend — A–Z ──────────────────────────────────── -->
            <div class="cph-legend-wrap">
                <span class="cph-legend-title">CIPHER KEY // A&ndash;Z</span>
                <div class="cph-legend">
                    <div
                        v-for="entry in legendEntries" :key="entry.letter"
                        class="cph-legend-cell"
                        :class="{ 'cph-legend-cell--solved': entry.solved, 'cph-legend-cell--wrong': entry.wrong }"
                    >
                        <span class="cph-legend-letter">{{ entry.letter }}</span>
                        <span class="cph-legend-code">{{ entry.code }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Blanked phrase ───────────────────────────────────────────── -->
            <div class="cph-phrase-row">
                <template v-for="(cell, i) in displayCells" :key="i">
                    <span v-if="cell.type === 'space'" class="cph-space" />
                    <span v-else class="cph-cell" :class="{ 'cph-cell--revealed': cell.revealed }">
                        {{ cell.revealed ? cell.ch : '_' }}
                    </span>
                </template>
            </div>

            <!-- Feedback flash -->
            <div class="cph-feedback" :class="feedbackClass">{{ feedbackText }}</div>

            <!-- ── Input row ─────────────────────────────────────────────────── -->
            <div class="cph-input-row">
                <span class="cph-input-lbl">DECRYPT KEY ::</span>
                <input
                    ref="inputEl"
                    v-model="guessCode"
                    class="cph-input"
                    type="text"
                    maxlength="2"
                    placeholder="__"
                    :disabled="status !== 'playing'"
                    @keyup.enter="submitGuess"
                />
                <button class="cph-submit-btn" :disabled="status !== 'playing' || !guessCode" @click="submitGuess">
                    [ SUBMIT ]
                </button>
            </div>

            <!-- ── Attempt log ───────────────────────────────────────────────── -->
            <div class="cph-log">
                <span class="cph-log-title">ATTEMPT LOG</span>
                <div class="cph-log-body">
                    <span v-if="!attemptLog.length" class="cph-log-empty">// no attempts yet</span>
                    <div
                        v-for="entry in attemptLog" :key="entry.id"
                        class="cph-log-line" :class="`cph-log-line--${entry.kind}`"
                    >
                        <span class="cph-log-prompt">&gt;</span> {{ entry.text }}
                    </div>
                </div>
            </div>

            <!-- ── Outcome overlay ──────────────────────────────────────────── -->
            <div v-if="status !== 'playing'" class="cph-outcome-overlay" :class="`outcome--${status}`">
                <div class="cph-outcome-title">{{ status === 'success' ? 'CIPHER CRACKED' : 'BREACH REJECTED' }}</div>
                <div class="cph-outcome-sub">{{ status === 'success' ? outcomeSuccessMsg : outcomeFailMsg }}</div>
                <button class="cph-outcome-btn" @click="onDismiss">[ CONTINUE ]</button>
            </div>

        </div>
    </div>
</template>

<script setup>
/**
 * CIPHER BREACH — node-hack pool template (generator key: 'cipher_breach').
 *
 * Ported from the quest minigame CipherLock: a hangman-style substitution
 * cipher — submit 2-character decrypt codes to reveal letters in a blanked
 * phrase before the clock runs out. Wrong guesses cost time rather than an
 * attempt slot, giving the pool a genuinely different failure feel from
 * ChecksumBreach's attempt-capped puzzle. Reuses CipherLock's phrase bank
 * directly (@/data/cipherLockPhrases.js) — it's already generic hacker
 * flavor text, not tied to any quest, so no new content was needed. Rebuilt
 * against the pool's contract (matches GridBreach's props/emits) instead of
 * the quest emit('complete'/'fail') + QuestMinigameChrome contract
 * CipherLock itself uses. Self-contained UI, no shared chrome.
 */
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { pickNextPhrase } from '@/data/cipherLockPhrases.js';
import { computeRewardAmount, outcomeSuccessMessage } from '../rewardFormula.js';

// ─── Props & emits — identical contract to every other pool entry ────────────
const props = defineProps({
    node:             { type: Object,  default: null    },
    resource:         { type: String,  default: 'creds' },  // 'creds' | 'tech' | 'uplink'
    playerCpu:        { type: Number,  default: 3       },
    playerRam:        { type: Number,  default: 2       },
    playerOs:         { type: Number,  default: 2       },
    playerFirewall:   { type: Number,  default: 1       },
    playerMaxUplink:  { type: Number,  default: 3       },
    bountyMultiplier: { type: Number,  default: 1.0     },
    paused:           { type: Boolean, default: false   },
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const nodeLabel = computed(() => props.node?.canvasId ?? props.node?.id ?? 'UNKNOWN');

// ─── Constants ─────────────────────────────────────────────────────────────────

const WRONG_GUESS_PENALTY = 15; // seconds lost on a wrong guess
const WRONG_PIP_CAP       = 8;  // pip slots shown before they just stay lit
const LOG_MAX_ENTRIES     = 7;  // attempt log keeps the most recent N entries

// Excludes 0/O and 1/I/L so codes stay readable at a glance.
const CODE_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'.split('');

// ─── Difficulty — ICE drives the timer budget and free-letter head start.
// Extended to the full ICE 3–10 range GridBreach itself uses (CipherLock's
// original only went to ICE 8, quest content never needed the top tiers).
const MIN_ICE = 3;
const MAX_ICE = 10;
const iceLevel = computed(() => Math.min(MAX_ICE, Math.max(MIN_ICE, props.node?.ice ?? MIN_ICE)));

function timeForIce(ice) { return Math.max(45, 240 - (ice - 3) * 30); }

function freeLettersForIce(ice) {
    if (ice <= 4) return 3;
    if (ice <= 6) return 2;
    if (ice <= 8) return 1;
    return 0;
}

// ─── Utilities ──────────────────────────────────────────────────────────────────

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

// ─── Game state ───────────────────────────────────────────────────────────────

const phrase           = ref('');
const cipherKey        = ref({ key: {}, reverseKey: {} });
const solvedLetters    = ref(new Set());
const ruledOutLetters  = ref(new Set());
const guessCode        = ref('');
const inputEl          = ref(null);
const timeLeft         = ref(timeForIce(iceLevel.value));
const status           = ref('playing'); // 'playing' | 'success' | 'failed'
const feedbackText     = ref('');
const feedbackClass    = ref('');
const wrongGuesses     = ref(0);
const attemptLog       = ref([]);
let logIdCounter       = 0;
let feedbackTimer      = null;

// ─── Derived ────────────────────────────────────────────────────────────────────

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

const timerClass = computed(() => {
    const total = timeForIce(iceLevel.value);
    const pct   = timeLeft.value / total;
    if (pct <= 0.10) return 'timer--critical';
    if (pct <= 0.25) return 'timer--warn';
    return '';
});

// ─── Reward — binary win/lose (either the whole phrase cracks or the clock
// runs out and it's a plain 'failed' with no reward), so completionPct is
// always 1.0 on success. Shared with ChecksumBreach via rewardFormula.js.
const rewardAmount = computed(() => computeRewardAmount({
    resource:         props.resource,
    ice:              iceLevel.value,
    bountyMultiplier: props.bountyMultiplier,
    playerMaxUplink:  props.playerMaxUplink,
}));

const outcomeSuccessMsg = computed(() => outcomeSuccessMessage(props.resource, rewardAmount.value));
const outcomeFailMsg    = 'CIPHER NOT CRACKED — ICE HELD — NO YIELD';

// ─── Feedback flash ──────────────────────────────────────────────────────────────

function flashFeedback(kind, text) {
    feedbackClass.value = `cph-feedback--${kind}`;
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

// ─── Guess handling ───────────────────────────────────────────────────────────────

function submitGuess() {
    if (status.value !== 'playing') return;
    const code = guessCode.value.trim().toUpperCase();
    guessCode.value = '';
    nextTick(() => inputEl.value?.focus());
    if (!code) return;

    const letter = cipherKey.value.reverseKey[code];

    if (letter && solvedLetters.value.has(letter)) {
        flashFeedback('repeat', `${code} :: ALREADY DECRYPTED`);
        logAttempt('repeat', code, `${code} :: already decrypted (${letter})`);
        return;
    }

    if (letter && ruledOutLetters.value.has(letter)) {
        flashFeedback('repeat', `${code} :: ALREADY RULED OUT`);
        logAttempt('repeat', code, `${code} :: already ruled out (${letter})`);
        return;
    }

    if (letter && uniqueLetters.value.has(letter)) {
        solvedLetters.value = new Set([...solvedLetters.value, letter]);
        flashFeedback('correct', `${code} :: ${letter}`);
        logAttempt('correct', code, `${code} :: MATCH — ${letter}`);
        if (allSolved.value) status.value = 'success';
        return;
    }

    // Wrong: invalid code, or a valid code for a letter not in this phrase.
    if (letter) {
        ruledOutLetters.value = new Set([...ruledOutLetters.value, letter]);
    }
    wrongGuesses.value++;
    timeLeft.value = Math.max(0, timeLeft.value - WRONG_GUESS_PENALTY);
    flashFeedback('wrong', `${code} :: REJECTED (-${WRONG_GUESS_PENALTY}s)`);
    logAttempt('wrong', code, `${code} :: REJECTED (-${WRONG_GUESS_PENALTY}s)`);
    if (timeLeft.value <= 0) {
        status.value = 'failed';
    }
}

// ─── Outcome ────────────────────────────────────────────────────────────────────

function onDismiss() {
    if (status.value === 'success') {
        emit('complete', { resource: props.resource, amount: rewardAmount.value, completionPct: 1.0 });
    } else {
        emit('failed', { resource: props.resource, amount: 0 });
    }
}

function onAbort() {
    emit('abort');
}

// ─── Tick system ────────────────────────────────────────────────────────────────

let tickHandle = null;

function startTimer() {
    tickHandle = setInterval(() => {
        if (status.value !== 'playing' || props.paused) return;
        timeLeft.value = Math.max(0, timeLeft.value - 1);
        if (timeLeft.value <= 0) {
            status.value = 'failed';
        }
    }, 1000);
}

// ─── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    phrase.value           = pickNextPhrase();
    cipherKey.value        = makeCipherKey();
    solvedLetters.value    = new Set();
    ruledOutLetters.value  = new Set();

    // Reveal a few free letters up front based on ICE tier, capped so at
    // least one letter is always left for the player to actually crack.
    const pool      = shuffle([...uniqueLetters.value]);
    const freeCount = Math.min(freeLettersForIce(iceLevel.value), Math.max(0, pool.length - 1));
    solvedLetters.value = new Set(pool.slice(0, freeCount));

    timeLeft.value      = timeForIce(iceLevel.value);
    wrongGuesses.value  = 0;
    attemptLog.value    = [];
    startTimer();
    nextTick(() => inputEl.value?.focus());
});

onUnmounted(() => {
    clearInterval(tickHandle);
    if (feedbackTimer) clearTimeout(feedbackTimer);
});
</script>

<style scoped>
.cph-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: rgba(4, 9, 14, 0.94);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
}

.cph-terminal {
    width: min(720px, 94vw);
    max-height: 92vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px 24px 22px;
    box-sizing: border-box;
    background: #04090e;
    color: #00c8f0;
    border: 1px solid rgba(0,200,240,0.25);
    box-shadow: 0 0 40px rgba(0,200,240,0.08);
    position: relative;
}

/* ── Top bar ───────────────────────────────────────────────────────────────── */

.cph-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 10px;
    letter-spacing: 0.12em;
    color: rgba(0,200,240,0.55);
    flex-shrink: 0;
}

.cph-timer { color: #00c8f0; }
.cph-timer.timer--warn     { color: #FFB300; }
.cph-timer.timer--critical { color: #ff3333; animation: cph-blink 0.6s ease infinite alternate; }

.cph-abort-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.35);
    color: rgba(255,51,51,0.75);
    padding: 5px 14px;
    cursor: pointer;
    transition: all 0.15s;
}
.cph-abort-btn:hover { background: rgba(255,51,51,0.08); border-color: #ff3333; color: #ff3333; }

.cph-rule {
    height: 1px;
    background: rgba(0,200,240,0.12);
    flex-shrink: 0;
}

/* ── Info bar ──────────────────────────────────────────────────────────────── */

.cph-infobar {
    display: flex;
    align-items: center;
    gap: 28px;
    flex-shrink: 0;
}

.cph-info-block { display: flex; flex-direction: column; gap: 2px; }
.cph-info-label { font-size: 8px; color: rgba(0,200,240,0.35); letter-spacing: 0.15em; }
.cph-info-val   { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; color: #00c8f0; }

.cph-att-pips { display: flex; gap: 5px; margin-top: 2px; }
.cph-att-pip  { font-size: 11px; color: rgba(0,200,240,0.25); transition: color 0.25s; }
.cph-att-pip--used { color: #ff3333; text-shadow: 0 0 6px rgba(255,51,51,0.5); }

/* ── Cipher legend ─────────────────────────────────────────────────────────── */

.cph-legend-wrap { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; }
.cph-legend-title { font-size: 8px; letter-spacing: 0.14em; color: rgba(0,200,240,0.35); }

.cph-legend {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
}

.cph-legend-cell {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    padding: 4px 0;
    border: 1px solid rgba(0,200,240,0.12);
    font-size: 8px;
}

.cph-legend-cell--solved { border-color: rgba(0,200,240,0.6); background: rgba(0,200,240,0.06); }
.cph-legend-cell--wrong  { border-color: rgba(255,51,51,0.35); opacity: 0.4; }

.cph-legend-letter { color: rgba(0,200,240,0.5); font-weight: 700; }
.cph-legend-code   { color: rgba(0,200,240,0.3); font-size: 7px; }

/* ── Blanked phrase ───────────────────────────────────────────────────────── */

.cph-phrase-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 0;
    flex-shrink: 0;
}

.cph-cell {
    min-width: 14px;
    text-align: center;
    font-size: 15px;
    letter-spacing: 0.05em;
    color: rgba(0,200,240,0.25);
    border-bottom: 1px solid rgba(0,200,240,0.25);
}

.cph-cell--revealed { color: #00c8f0; border-color: #00c8f0; }
.cph-space { width: 10px; }

/* ── Feedback ──────────────────────────────────────────────────────────────── */

.cph-feedback {
    min-height: 14px;
    font-size: 10px;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.cph-feedback--correct { color: #00c8f0; }
.cph-feedback--wrong   { color: #ff3333; }
.cph-feedback--repeat  { color: rgba(0,200,240,0.4); }

/* ── Input row ─────────────────────────────────────────────────────────────── */

.cph-input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.cph-input-lbl { font-size: 9px; letter-spacing: 0.1em; color: rgba(0,200,240,0.4); }

.cph-input {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    letter-spacing: 0.2em;
    text-align: center;
    width: 48px;
    background: rgba(0,200,240,0.05);
    border: 1px solid rgba(0,200,240,0.3);
    color: #00c8f0;
    padding: 4px 0;
    text-transform: uppercase;
}
.cph-input:focus { outline: none; border-color: #00c8f0; }

.cph-submit-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,200,240,0.35);
    color: rgba(0,200,240,0.75);
    padding: 6px 16px;
    cursor: pointer;
    transition: all 0.15s;
}
.cph-submit-btn:disabled { opacity: 0.25; cursor: not-allowed; }
.cph-submit-btn:hover:not(:disabled) { background: rgba(0,200,240,0.08); border-color: #00c8f0; color: #00c8f0; }

/* ── Attempt log ───────────────────────────────────────────────────────────── */

.cph-log { display: flex; flex-direction: column; gap: 4px; flex: 1; min-height: 0; }
.cph-log-title { font-size: 8px; letter-spacing: 0.14em; color: rgba(0,200,240,0.3); }

.cph-log-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 9px;
    overflow-y: auto;
}

.cph-log-empty { color: rgba(0,200,240,0.2); }
.cph-log-line  { color: rgba(0,200,240,0.4); }
.cph-log-line--correct { color: rgba(0,200,240,0.75); }
.cph-log-line--wrong   { color: rgba(255,51,51,0.65); }
.cph-log-prompt { color: rgba(0,200,240,0.3); }

/* ── Outcome overlay ──────────────────────────────────────────────────────── */

.cph-outcome-overlay {
    position: absolute;
    inset: 0;
    background: rgba(4,9,14,0.96);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-align: center;
    padding: 0 24px;
}

.cph-outcome-title { font-size: 20px; letter-spacing: 0.15em; }
.outcome--success .cph-outcome-title { color: #00c8f0; text-shadow: 0 0 24px rgba(0,200,240,0.55); }
.outcome--failed  .cph-outcome-title { color: #FF3333; text-shadow: 0 0 24px rgba(255,51,51,0.55); }

.cph-outcome-sub { font-size: 11px; letter-spacing: 0.08em; color: rgba(0,200,240,0.6); }

.cph-outcome-btn {
    margin-top: 10px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(0,200,240,0.4);
    color: rgba(0,200,240,0.85);
    padding: 8px 26px;
    cursor: pointer;
    transition: all 0.15s;
}
.cph-outcome-btn:hover { background: rgba(0,200,240,0.07); border-color: rgba(0,200,240,0.65); }

/* ── Animations ────────────────────────────────────────────────────────────── */

@keyframes cph-blink {
    from { opacity: 1; }
    to   { opacity: 0.4; }
}
</style>
