<template>
    <!-- Breach phase — full-assault glitch before the box forces in -->
    <GlitchEffect
        type="full"
        :intensity="1.0"
        :active="phase === 'breach'"
        overlay
    />

    <!-- Red strobe overlay during breach — separate from glitch so it sits on top -->
    <Transition name="ws-strobe">
        <div v-if="phase === 'breach'" class="ws-strobe" />
    </Transition>

    <!-- Phase 1: Override block + fragmented signal reveal (override → intrusion) -->
    <Transition name="ws-slam">
        <div
            v-if="phase === 'override' || phase === 'intrusion'"
            class="ws-overlay"
        >
            <div class="ws-scanline" />

            <div class="ws-box" :class="{ 'ws-box--jitter': phase === 'override' }">

                <!-- ── ASCII OVERRIDE BLOCK ─────────────────────────────────── -->
                <div class="ws-override-block">
                    <div class="ws-override-bar">
                        ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓
                    </div>
                    <div class="ws-override-title">
                        [SYSTEM_OVERRIDE // CRITICAL_INTRUSION]
                    </div>
                    <div class="ws-override-meta">
                        <span>[SOURCE: NULL]</span>
                        <span>[PID: {{ corruptPid }}]</span>
                        <span>[USER: NAME_CORRUPTED]</span>
                    </div>
                    <div class="ws-override-bar">
                        ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓
                    </div>
                </div>

                <!-- ── MESSAGE BODY ─────────────────────────────────────────── -->
                <Transition name="ws-body-in">
                    <div v-if="phase === 'intrusion'" class="ws-body-wrap">

                        <!-- Token-based scramble→snap reveal -->
                        <div class="ws-body">
                            <template v-for="(token, i) in renderedTokens" :key="i">
                                <br v-if="token.type === 'newline'" />
                                <span v-else-if="token.type === 'space'"> </span>
                                <span
                                    v-else
                                    class="ws-token"
                                    :class="{
                                        'ws-token--scrambling': token.state === 'scrambling',
                                        'ws-token--flash':      token.flash,
                                        'ws-token--revealed':   token.state === 'revealed',
                                    }"
                                >{{ token.display }}</span>
                            </template>
                            <span v-if="!textComplete" class="ws-cursor">█</span>
                        </div>

                    </div>
                </Transition>

            </div>
        </div>
    </Transition>

    <!-- Phase 2: Blackout — hard cut to pure black, holds in silence -->
    <div v-if="phase === 'blackout'" class="ws-blackout" />

    <!-- Phase 3: Reboot — clinical typewriter text on black, then game fades back in -->
    <Transition name="ws-reboot-fade">
        <div v-if="phase === 'reboot'" class="ws-reboot">
            <div class="ws-reboot-lines">
                <div
                    v-for="(line, i) in rebootLines"
                    :key="i"
                    class="ws-reboot-line"
                    :class="{ 'ws-reboot-line--visible': i < rebootRevealCount }"
                >{{ line }}</div>
                <span v-if="rebootRevealCount < rebootLines.length" class="ws-reboot-cursor">█</span>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import GlitchEffect from './GlitchEffect.vue';

const props = defineProps({
    signal: { type: Object, default: null },
    player: { type: Object, default: null },
});
const emit = defineEmits(['complete']);

// ── Phase machine: idle → breach → override → intrusion → blackout → reboot → idle ──
const phase             = ref('idle');
const renderedTokens    = ref([]);
const textComplete      = ref(false);
const rebootRevealCount = ref(0);

// Corruption artifact — scrambles during override, freezes once text starts
const corruptPid = ref(_newPid());
function _newPid() {
    return '0x' + Math.floor(Math.random() * 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
}

// Build reboot lines from player data (Phase 3)
const rebootLines = computed(() => {
    const handle  = props.player?.handle       ?? 'UNKNOWN';
    const persona = props.player?.persona      ?? '---';
    const status  = props.player?.persona_desc ?? '---';
    return [
        '[SYSTEM RECOVERING...]',
        `HANDLE ........... ${handle}`,
        `PERSONA .......... ${persona}`,
        `STATUS ........... ${status}`,
        '[CORE IDENTITY: INTACT]',
        '[REBOOTING...]',
    ];
});

// All timer IDs pooled for cleanup
let _timers = [];

// ── Intrusion audio — plays once at breach start, disposed after ──────────────
let _intrusionAudio = null;

function _playIntrusionSfx() {
    try {
        _intrusionAudio = new Audio('/audio/Sound/Intrusion.mp3');
        _intrusionAudio.volume = 0.85;
        _intrusionAudio.play().catch(() => {});
    } catch (_) {}
}

function _stopIntrusionSfx() {
    if (_intrusionAudio) {
        _intrusionAudio.pause();
        _intrusionAudio.src  = '';
        _intrusionAudio      = null;
    }
}

watch(() => props.signal, (sig) => {
    if (sig && phase.value === 'idle') _startBreach();
}, { immediate: true });

// ── Phase 1a: breach — 0.8 s full glitch + red strobe ────────────────────────
function _startBreach() {
    phase.value             = 'breach';
    _playIntrusionSfx();
    textComplete.value      = false;
    renderedTokens.value    = [];
    rebootRevealCount.value = 0;
    corruptPid.value        = _newPid();

    const pidInterval = setInterval(() => { corruptPid.value = _newPid(); }, 100);
    _timers.push(pidInterval);

    _timers.push(setTimeout(() => {
        phase.value = 'override';
        _stopIntrusionSfx(); // noise cuts as the override box slams in
        _timers.push(setTimeout(() => {
            clearInterval(pidInterval); // PID freezes when text starts
            _startTokenReveal();
        }, 1000));
    }, 800));
}

// ── Phase 1b: intrusion — two-pass token engine ───────────────────────────────
//
// Pass 1 (corrupt reveal): token scrambles in → snaps to a zalgo-corrupted version
// Pass 2 (cleanup):        2 s later, token re-scrambles → snaps to the clean word
//
// The rolling offset means early words are already cleaning up while later words
// are still fighting through their corrupt reveal — the signal is pushing through.
//
const GLITCH_CHARS   = '!@#$%^&*<>{}|\\?~`01░▒▓█▌▐■□▸◈×÷';
const CLEAN_DELAY_MS = 2000; // ms after corrupt snap before cleanup scramble fires

// Light combining diacritics — readable but visually disturbed
const COMBINING = ['̀', '́', '̂', '̈', '̧', '̰', '̲', '̶'];

function _corruptWord(word) {
    // Structural tokens stay clean — corruption targets only narrative words
    if (/^\[/.test(word) || /^\*/.test(word) || /^[▓░·]+$/.test(word)) return word;
    return [...word].map(ch => {
        if (!/[a-zA-Z]/.test(ch)) return ch;
        const n     = Math.floor(Math.random() * 2) + 1;
        const marks = COMBINING.slice().sort(() => Math.random() - 0.5).slice(0, n).join('');
        return ch + marks;
    }).join('');
}

function _startTokenReveal() {
    phase.value = 'intrusion';

    const text   = props.signal?.signal_text ?? props.signal?.body ?? '';
    const tokens = _tokenize(text);

    // Each token carries both the corrupted first-snap and the clean final-snap
    renderedTokens.value = tokens.map(t => ({
        clean:    t.clean,
        corrupt:  t.corrupt,
        raw:      t.corrupt,   // _scrambleToken snaps to token.raw — starts pointing at corrupt
        type:     t.type,
        display:  (t.type === 'space' || t.type === 'newline') ? t.clean : '',
        state:    (t.type === 'space' || t.type === 'newline') ? 'revealed' : 'pending',
        flash:    false,
    }));

    let cumDelay   = 250;
    let lastCleanAt = 0;

    tokens.forEach((token, i) => {
        if (token.type === 'space' || token.type === 'newline') return;

        const corruptDur = _scrambleDuration(token);
        const cleanDur   = _cleanDuration(token);
        const startAt    = cumDelay;

        // Pass 1: scramble → snap corrupted
        _timers.push(setTimeout(() => _scrambleToken(i, corruptDur), startAt));

        // Pass 2: CLEAN_DELAY_MS after the corrupt snap → re-scramble → snap clean
        const cleanAt = startAt + corruptDur + CLEAN_DELAY_MS;
        _timers.push(setTimeout(() => {
            const tok = renderedTokens.value[i];
            if (tok) {
                tok.raw = tok.clean;        // switch snap target to the clean word
                _scrambleToken(i, cleanDur);
            }
        }, cleanAt));

        if (cleanAt + cleanDur > lastCleanAt) lastCleanAt = cleanAt + cleanDur;

        cumDelay += corruptDur + _gapAfter(token);
    });

    // textComplete fires after every word has finished its cleanup pass
    _timers.push(setTimeout(() => {
        textComplete.value = true;
        _timers.push(setTimeout(_startBlackout, 2500));
    }, lastCleanAt + 300));
}

// ── Phase 2: blackout — pure black, 2.5 s ────────────────────────────────────
function _startBlackout() {
    phase.value = 'blackout';
    _timers.push(setTimeout(_startReboot, 2500));
}

// ── Phase 3: reboot — clinical typewriter on black, then emit complete ────────
function _startReboot() {
    phase.value             = 'reboot';
    rebootRevealCount.value = 0;

    const LINE_DELAY = 420; // ms between each line appearing

    rebootLines.value.forEach((_, i) => {
        _timers.push(setTimeout(() => {
            rebootRevealCount.value = i + 1;
        }, 300 + i * LINE_DELAY));
    });

    // Hold 1.5 s after all lines, then fade the reboot screen and emit complete
    const totalDuration = 300 + rebootLines.value.length * LINE_DELAY + 1500;
    _timers.push(setTimeout(() => {
        phase.value = 'idle';
        emit('complete');
    }, totalDuration));
}

// ── Token helpers ─────────────────────────────────────────────────────────────

function _tokenize(text) {
    const tokens = [];
    let buf = '';
    for (const ch of text) {
        if (ch === '\n') {
            if (buf) { tokens.push(_buildToken(buf)); buf = ''; }
            tokens.push({ clean: '\n', corrupt: '\n', raw: '\n', type: 'newline' });
        } else if (ch === ' ') {
            if (buf) { tokens.push(_buildToken(buf)); buf = ''; }
            tokens.push({ clean: ' ', corrupt: ' ', raw: ' ', type: 'space' });
        } else {
            buf += ch;
        }
    }
    if (buf) tokens.push(_buildToken(buf));
    return tokens;
}

function _buildToken(word) {
    return { clean: word, corrupt: _corruptWord(word), raw: word, type: 'word' };
}

function _scrambleDuration(token) {
    const t = token.clean;
    if (/^\[/.test(t) || /^\.{2,}/.test(t) || /^\*/.test(t)) return 60 + Math.random() * 80;
    if (t.length <= 2) return 80 + Math.random() * 100;
    return 140 + Math.random() * 180;
}

// Cleanup pass is faster — the signal is clarifying, not fighting
function _cleanDuration(token) {
    const t = token.clean;
    if (t.length <= 2) return 60 + Math.random() * 60;
    return 100 + Math.random() * 120;
}

function _gapAfter(token) {
    if (token.type === 'newline') return 110 + Math.random() * 120;
    return 35 + Math.random() * 55;
}

function _scrambleToken(idx, duration) {
    const token = renderedTokens.value[idx];
    if (!token) return;

    token.state = 'scrambling';
    // Always base glitch char count on the clean word length —
    // combining chars inflate token.raw.length when corrupt
    const len = token.clean.length;
    const interval = setInterval(() => {
        token.display = Array.from({ length: len }, () =>
            GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)]
        ).join('');
    }, 55);
    _timers.push(interval);

    _timers.push(setTimeout(() => {
        clearInterval(interval);
        token.display = token.raw;   // snaps to whatever raw currently points at
        token.state   = 'revealed';
        token.flash   = true;
        _timers.push(setTimeout(() => { token.flash = false; }, 160));
    }, duration));
}

// ── Cleanup ───────────────────────────────────────────────────────────────────
onUnmounted(() => {
    _timers.forEach(id => {
        clearTimeout(id);
        clearInterval(id);
    });
    _stopIntrusionSfx();
});
</script>

<style scoped>
/* ── Red strobe during breach phase ───────────────────────────────────────── */
.ws-strobe {
    position: fixed;
    inset: 0;
    z-index: 10001;
    background: rgba(180, 10, 30, 0.28);
    pointer-events: none;
    animation: ws-strobe-pulse 0.08s steps(1) infinite;
}
@keyframes ws-strobe-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.1; }
}
.ws-strobe-enter-active,
.ws-strobe-leave-active { transition: opacity 0.1s; }
.ws-strobe-enter-from,
.ws-strobe-leave-to     { opacity: 0; }

/* ── Phase 1: Intrusion overlay ───────────────────────────────────────────── */
.ws-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(10, 0, 2, 0.97);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: default;
}

/* Drifting red scanlines — the screen is sick */
.ws-scanline {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,                    transparent                    3px,
        rgba(200, 10, 30, 0.02) 3px,   rgba(200, 10, 30, 0.02) 6px
    );
    animation: ws-scan-drift 0.25s linear infinite;
}
@keyframes ws-scan-drift {
    from { background-position-y: 0; }
    to   { background-position-y: 6px; }
}

/* ── Box shell ────────────────────────────────────────────────────────────── */
.ws-box {
    position: relative;
    width: min(600px, 94vw);
    background: rgba(14, 2, 4, 0.99);
    border: 1px solid rgba(220, 20, 50, 0.35);
    box-shadow:
        0 0 80px rgba(200, 10, 30, 0.08),
        inset 0 0 40px rgba(0, 0, 0, 0.7);
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* Jitter while override block is forcing itself in */
.ws-box--jitter {
    animation: ws-jitter 0.08s steps(1) infinite;
}
@keyframes ws-jitter {
    0%   { transform: translate(0,     0); }
    25%  { transform: translate(-3px,  1px); }
    50%  { transform: translate(2px,  -2px); }
    75%  { transform: translate(-1px,  2px); }
    100% { transform: translate(0,     0); }
}

/* ── Override block ───────────────────────────────────────────────────────── */
.ws-override-block {
    padding: 14px 18px 12px;
    border-bottom: 1px solid rgba(220, 20, 50, 0.2);
    background: rgba(220, 20, 50, 0.04);
}

.ws-override-bar {
    font-size: 7px;
    color: rgba(220, 20, 50, 0.3);
    letter-spacing: 0.02em;
    overflow: hidden;
    white-space: nowrap;
    line-height: 1.4;
}

.ws-override-title {
    font-size: 11px;
    letter-spacing: 0.18em;
    color: rgba(255, 70, 90, 0.95);
    text-align: center;
    padding: 7px 0;
    text-shadow: 0 0 16px rgba(255, 40, 70, 0.6);
    animation: ws-title-strobe 1.6s ease-in-out infinite;
}
@keyframes ws-title-strobe {
    0%, 100% { opacity: 1; }
    48%       { opacity: 0.85; }
    50%       { opacity: 0.3; }
    52%       { opacity: 0.85; }
}

.ws-override-meta {
    display: flex;
    justify-content: space-between;
    padding: 3px 0 6px;
    font-size: 8px;
    color: rgba(200, 50, 70, 0.45);
    letter-spacing: 0.1em;
}

/* ── Message body ─────────────────────────────────────────────────────────── */
.ws-body-wrap {
    padding: 20px 22px 18px;
}

.ws-body {
    font-size: 13px;
    line-height: 1.8;
    letter-spacing: 0.04em;
    white-space: pre-wrap;
    min-height: 60px;
}

/* ── Token states ─────────────────────────────────────────────────────────── */
.ws-token { display: inline-block; }

.ws-token--scrambling {
    color: rgba(200, 40, 60, 0.55);
    animation: ws-token-jitter 0.07s steps(1) infinite;
}
@keyframes ws-token-jitter {
    0%   { transform: translateY(0px) skewX(0deg); }
    25%  { transform: translateY(-2px) skewX(1deg); }
    50%  { transform: translateY(1px)  skewX(-1deg); }
    75%  { transform: translateY(-1px) skewX(0.5deg); }
    100% { transform: translateY(0px) skewX(0deg); }
}

.ws-token--flash {
    color: #FF2040 !important;
    text-shadow: 0 0 14px rgba(255, 20, 40, 0.9), 0 0 30px rgba(255, 20, 40, 0.4);
    transform: none !important;
    animation: ws-flash-snap 0.16s ease-out forwards;
}
@keyframes ws-flash-snap {
    0%   { letter-spacing: 0.12em; opacity: 1; }
    100% { letter-spacing: 0.04em; opacity: 1; }
}

.ws-token--revealed {
    color: rgba(255, 195, 205, 0.88);
    transform: none;
    animation: none;
}

.ws-cursor {
    display: inline-block;
    color: rgba(255, 70, 90, 0.8);
    animation: ws-blink 0.5s steps(1) infinite;
    margin-left: 2px;
}
@keyframes ws-blink { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }

/* ── Phase 2: Blackout ────────────────────────────────────────────────────── */
.ws-blackout {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: #000;
    pointer-events: all;
}

/* ── Phase 3: Reboot ──────────────────────────────────────────────────────── */
.ws-reboot {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: all;
}

.ws-reboot-lines {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 2.2;
    letter-spacing: 0.06em;
    width: min(480px, 90vw);
}

.ws-reboot-line {
    opacity: 0;
    transform: translateY(3px);
    transition: opacity 0.22s ease, transform 0.22s ease;
    color: rgba(0, 255, 140, 0.75);
}

.ws-reboot-line--visible {
    opacity: 1;
    transform: translateY(0);
}

.ws-reboot-cursor {
    display: inline-block;
    color: rgba(0, 255, 140, 0.75);
    animation: ws-blink 0.5s steps(1) infinite;
    margin-left: 2px;
}

/* Reboot screen fades away to reveal the game world */
.ws-reboot-fade-leave-active { transition: opacity 0.9s ease; }
.ws-reboot-fade-leave-to     { opacity: 0; }

/* ── Transitions ──────────────────────────────────────────────────────────── */

/* Box slams in — no gentle fade, a snap at 2 steps */
.ws-slam-enter-active { animation: ws-slam-in 0.12s steps(2) forwards; }
.ws-slam-leave-active { transition: opacity 0.15s ease; }
.ws-slam-leave-to     { opacity: 0; }

@keyframes ws-slam-in {
    0%   { opacity: 0; transform: scale(1.05) translateY(-4px); }
    50%  { opacity: 1; transform: scale(0.98) translateY(1px); }
    100% { opacity: 1; transform: scale(1)    translateY(0); }
}

/* Body fades in after override hold */
.ws-body-in-enter-active { transition: opacity 0.18s ease; }
.ws-body-in-enter-from   { opacity: 0; }
</style>
