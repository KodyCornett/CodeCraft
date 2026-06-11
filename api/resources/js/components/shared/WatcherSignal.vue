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

    <!-- Override block + signal -->
    <Transition name="ws-slam">
        <div
            v-if="phase === 'override' || phase === 'signal'"
            class="ws-overlay"
            @click="onDismiss"
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
                    <div v-if="phase === 'signal'" class="ws-body-wrap">

                        <!-- Token-based scramble→snap reveal -->
                        <div class="ws-body">
                            <template v-for="(token, i) in renderedTokens" :key="i">
                                <!-- Newlines -->
                                <br v-if="token.type === 'newline'" />

                                <!-- Spaces — always revealed, no classes -->
                                <span v-else-if="token.type === 'space'"> </span>

                                <!-- Word tokens — scramble then snap -->
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

                            <!-- Blinking cursor while in progress -->
                            <span v-if="!textComplete" class="ws-cursor">█</span>
                        </div>

                        <!-- Footer after full reveal -->
                        <template v-if="textComplete">
                            <div class="ws-rule" />
                            <div class="ws-footer">
                                <span class="ws-end-tag">[SIGNAL_END // {{ corruptPid }}]</span>
                                <span class="ws-dismiss">▸ TOUCH TO CLOSE — SIGNAL DEGRADES ◂</span>
                            </div>
                        </template>

                    </div>
                </Transition>

            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import GlitchEffect from './GlitchEffect.vue';

const props = defineProps({
    signal: { type: Object, default: null }, // { id, signal_text, delivered_at }
});
const emit = defineEmits(['complete']);

// ── State machine: idle → breach → override → signal → idle ──────────────────
const phase          = ref('idle');
const renderedTokens = ref([]);
const textComplete   = ref(false);

// Corruption artifact — scrambles during override, freezes once text starts
const corruptPid = ref(_newPid());
function _newPid() {
    return '0x' + Math.floor(Math.random() * 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
}

// All timer IDs pooled for cleanup — setTimeout and setInterval IDs share a pool
let _timers = [];

watch(() => props.signal, (sig) => {
    if (sig && phase.value === 'idle') _startBreach();
}, { immediate: true });

// ── Phase 1: breach — 0.8 s full glitch + red strobe ─────────────────────────
function _startBreach() {
    phase.value         = 'breach';
    textComplete.value  = false;
    renderedTokens.value = [];
    corruptPid.value    = _newPid();

    // Scramble the PID rapidly to sell the hijack
    const pidInterval = setInterval(() => { corruptPid.value = _newPid(); }, 100);
    _timers.push(pidInterval);

    _timers.push(setTimeout(() => {
        phase.value = 'override';
        // Hold the override block + jitter for 1 s before text starts
        _timers.push(setTimeout(() => {
            clearInterval(pidInterval); // PID freezes when text starts
            _startTokenReveal();
        }, 1000));
    }, 800));
}

// ── Phase 2: override — handled above (jitter on ws-box via CSS class) ────────

// ── Phase 3: signal — token-scramble reveal ───────────────────────────────────
const GLITCH_CHARS = '!@#$%^&*<>{}|\\?~`01░▒▓█▌▐■□▸◈×÷';

function _startTokenReveal() {
    phase.value = 'signal';

    const text   = props.signal?.signal_text ?? '';
    const tokens = _tokenize(text);

    // Build reactive token objects
    renderedTokens.value = tokens.map(t => ({
        raw:     t.raw,
        type:    t.type,
        display: (t.type === 'space' || t.type === 'newline') ? t.raw : '',
        state:   (t.type === 'space' || t.type === 'newline') ? 'revealed' : 'pending',
        flash:   false,
    }));

    let cumDelay = 250;

    tokens.forEach((token, i) => {
        if (token.type === 'space' || token.type === 'newline') return;

        const scrambleDur = _scrambleDuration(token);
        const startAt     = cumDelay;

        _timers.push(setTimeout(() => _scrambleToken(i, scrambleDur), startAt));

        cumDelay += scrambleDur + _gapAfter(token);
    });

    // Complete when all tokens resolved
    _timers.push(setTimeout(() => { textComplete.value = true; }, cumDelay + 150));
}

// Split text into word / space / newline tokens
function _tokenize(text) {
    const tokens = [];
    let buf = '';

    for (const ch of text) {
        if (ch === '\n') {
            if (buf) { tokens.push({ raw: buf, type: 'word' }); buf = ''; }
            tokens.push({ raw: '\n', type: 'newline' });
        } else if (ch === ' ') {
            if (buf) { tokens.push({ raw: buf, type: 'word' }); buf = ''; }
            tokens.push({ raw: ' ', type: 'space' });
        } else {
            buf += ch;
        }
    }
    if (buf) tokens.push({ raw: buf, type: 'word' });
    return tokens;
}

// Scramble duration per token type:
//   Bracket/ellipsis tokens → fight briefly (they're structure, not message)
//   Real words → fight longer — the signal is struggling to push through
function _scrambleDuration(token) {
    const t = token.raw;
    if (/^\[/.test(t) || /^\.{2,}/.test(t)) return 80 + Math.random() * 100;
    if (t.length <= 2)                        return 100 + Math.random() * 120;
    return 200 + Math.random() * 380;
}

// Gap between token reveals — newlines breathe longer
function _gapAfter(token) {
    if (token.type === 'newline') return 70 + Math.random() * 100;
    return 18 + Math.random() * 45;
}

// Scramble a token: cycle noise chars → snap to real text → brief flash
function _scrambleToken(idx, duration) {
    const token = renderedTokens.value[idx];
    if (!token) return;

    token.state = 'scrambling';

    // Show random noise at the same char count as the real word
    const len      = token.raw.length;
    const interval = setInterval(() => {
        token.display = Array.from({ length: len }, () =>
            GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)]
        ).join('');
    }, 55);
    _timers.push(interval);

    // After duration: SNAP to real text
    _timers.push(setTimeout(() => {
        clearInterval(interval);
        token.display = token.raw;
        token.state   = 'revealed';
        token.flash   = true;

        // Flash fades after 160 ms
        _timers.push(setTimeout(() => { token.flash = false; }, 160));
    }, duration));
}

// ── Dismiss ───────────────────────────────────────────────────────────────────
function onDismiss() {
    if (!textComplete.value) return;
    phase.value = 'idle';
    emit('complete');
}

// ── Cleanup ───────────────────────────────────────────────────────────────────
onUnmounted(() => {
    _timers.forEach(id => {
        clearTimeout(id);
        clearInterval(id);
    });
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

/* ── Main overlay ─────────────────────────────────────────────────────────── */
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
    /* pre-wrap preserves newlines from signal_text */
    white-space: pre-wrap;
    min-height: 60px;
}

/* ── Token states ─────────────────────────────────────────────────────────── */

/* Base token — inline-block so transform works */
.ws-token {
    display: inline-block;
}

/* Scrambling: noisy red characters juddering in place */
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

/* Flash: violent white-red snap when the word locks in */
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

/* Revealed: settled, readable */
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

/* ── Footer ───────────────────────────────────────────────────────────────── */
.ws-rule {
    border: none;
    border-top: 1px solid rgba(220, 20, 50, 0.14);
    margin: 14px 0 10px;
}

.ws-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.ws-end-tag {
    font-size: 8px;
    color: rgba(200, 50, 70, 0.35);
    letter-spacing: 0.1em;
}

.ws-dismiss {
    font-size: 9px;
    color: rgba(255, 90, 110, 0.8);
    letter-spacing: 0.14em;
    cursor: pointer;
    text-shadow: 0 0 8px rgba(255, 60, 80, 0.4);
    animation: ws-dismiss-urgent 0.8s ease-in-out infinite;
}
@keyframes ws-dismiss-urgent {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.35; }
}

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
