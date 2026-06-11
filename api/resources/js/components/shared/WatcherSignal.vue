<template>
    <!-- Glitch phase -->
    <Transition name="ws-glitch" @after-leave="onGlitchDone">
        <div v-if="phase === 'glitch'" class="ws-glitch-overlay" aria-hidden="true">
            <div class="ws-glitch-layer ws-glitch-layer--r" />
            <div class="ws-glitch-layer ws-glitch-layer--g" />
            <div class="ws-glitch-layer ws-glitch-layer--b" />
            <div class="ws-glitch-scanlines" />
            <div class="ws-glitch-bars">
                <div v-for="i in 8" :key="i" class="ws-glitch-bar" :style="glitchBarStyle(i)" />
            </div>
            <div class="ws-glitch-text">
                <span v-for="ch in scrambledChars" :key="ch.id" class="ws-scramble-ch">{{ ch.c }}</span>
            </div>
        </div>
    </Transition>

    <!-- Signal phase -->
    <Transition name="ws-signal">
        <div v-if="phase === 'signal'" class="ws-signal-overlay" @click="onDismiss">
            <div class="ws-signal-scanline" />
            <div class="ws-signal-box">
                <div class="ws-signal-header">
                    <span class="ws-sig-tag">[ENCRYPTED_SIGNAL // SOURCE: UNKNOWN]</span>
                    <span class="ws-sig-blink">▌</span>
                </div>
                <div class="ws-signal-rule" />
                <div class="ws-signal-label">[MESSAGE]:</div>
                <div class="ws-signal-body">
                    <span
                        v-for="(ch, i) in revealedChars"
                        :key="i"
                        class="ws-signal-ch"
                        :class="{ 'ws-signal-ch--glitch': ch.glitch }"
                    >{{ ch.c }}</span>
                    <span v-if="!textComplete" class="ws-signal-cursor">█</span>
                </div>
                <div v-if="textComplete" class="ws-signal-rule ws-signal-rule--end" />
                <div v-if="textComplete" class="ws-signal-footer">
                    <span class="ws-end-tag">[END_SIGNAL]</span>
                    <span class="ws-dismiss">// TOUCH TO CLOSE</span>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';

const props = defineProps({
    signal: { type: Object, default: null }, // { id, signal_text, delivered_at }
});

const emit = defineEmits(['complete']);

// ── State machine: idle → glitch → signal → idle ─────────────────────────────
const phase         = ref('idle');
const revealedChars = ref([]);
const scrambledChars = ref([]);
const textComplete  = ref(false);

let glitchTimer = null;
let typeTimer   = null;

// Watch for an incoming signal
watch(() => props.signal, (sig) => {
    if (sig && phase.value === 'idle') {
        startGlitch();
    }
}, { immediate: true });

// ── Glitch phase ──────────────────────────────────────────────────────────────

const GLITCH_CHARS = '!@#$%^&*<>{}[]|\\/?~`01';

function randomGlitchChar() {
    return GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)];
}

function startGlitch() {
    phase.value = 'glitch';
    textComplete.value = false;
    revealedChars.value = [];

    // Animate scrambled characters during glitch
    let frame = 0;
    const scrambleInterval = setInterval(() => {
        scrambledChars.value = Array.from({ length: 24 }, (_, i) => ({
            id: i,
            c: randomGlitchChar(),
        }));
        frame++;
        if (frame > 12) clearInterval(scrambleInterval);
    }, 80);

    // Glitch lasts ~1.2s then transitions to signal
    glitchTimer = setTimeout(() => {
        phase.value = 'signal';
        startTypewriter();
    }, 1200);
}

function glitchBarStyle(i) {
    const top  = Math.random() * 100;
    const h    = 2 + Math.random() * 8;
    const left = (Math.random() - 0.5) * 6;
    return {
        top:       `${top}%`,
        height:    `${h}px`,
        transform: `translateX(${left}%)`,
        opacity:   0.3 + Math.random() * 0.5,
        animationDelay: `${i * 0.07}s`,
    };
}

// ── Signal / typewriter phase ─────────────────────────────────────────────────

function startTypewriter() {
    const text  = props.signal?.signal_text ?? '';
    const chars = text.split('');
    let   idx   = 0;

    // Occasionally corrupt a character then self-correct
    function nextChar() {
        if (idx >= chars.length) {
            textComplete.value = true;
            return;
        }

        const realChar  = chars[idx];
        const willGlitch = Math.random() < 0.04;

        if (willGlitch) {
            // Show corrupt char first
            revealedChars.value.push({ c: randomGlitchChar(), glitch: true });
            setTimeout(() => {
                // Replace with the real char
                revealedChars.value[idx] = { c: realChar, glitch: false };
                idx++;
                typeTimer = setTimeout(nextChar, charDelay());
            }, 120);
        } else {
            revealedChars.value.push({ c: realChar, glitch: false });
            idx++;
            typeTimer = setTimeout(nextChar, charDelay());
        }
    }

    typeTimer = setTimeout(nextChar, 300);
}

function charDelay() {
    // Newlines pause longer, spaces slightly, regular chars fast
    const last = revealedChars.value[revealedChars.value.length - 1]?.c;
    if (last === '\n') return 60 + Math.random() * 80;
    if (last === ' ')  return 18 + Math.random() * 12;
    return 12 + Math.random() * 18;
}

// ── Collapse ──────────────────────────────────────────────────────────────────

function onGlitchDone() {
    // Nothing — handled by the glitchTimer
}

function onDismiss() {
    if (!textComplete.value) return; // don't allow early dismiss
    phase.value = 'idle';
    emit('complete');
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

onUnmounted(() => {
    clearTimeout(glitchTimer);
    clearTimeout(typeTimer);
});
</script>

<style scoped>
/* ── Glitch overlay ──────────────────────────────────────────────────────────── */
.ws-glitch-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: #000;
    overflow: hidden;
}

.ws-glitch-layer {
    position: absolute;
    inset: 0;
    mix-blend-mode: screen;
    animation: ws-glitch-shift 0.1s steps(1) infinite;
}
.ws-glitch-layer--r {
    background: rgba(255, 0, 60, 0.15);
    animation-delay: 0s;
    transform: translateX(3px);
}
.ws-glitch-layer--g {
    background: rgba(0, 255, 100, 0.08);
    animation-delay: 0.03s;
    transform: translateX(-2px);
}
.ws-glitch-layer--b {
    background: rgba(0, 100, 255, 0.12);
    animation-delay: 0.06s;
}
@keyframes ws-glitch-shift {
    0%   { transform: translateX(0); opacity: 1; }
    25%  { transform: translateX(4px) scaleY(1.01); opacity: 0.9; }
    50%  { transform: translateX(-3px); opacity: 1; }
    75%  { transform: translateX(2px); opacity: 0.85; }
    100% { transform: translateX(0); opacity: 1; }
}

.ws-glitch-scanlines {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent, transparent 2px,
        rgba(0,255,100,0.04) 2px, rgba(0,255,100,0.04) 4px
    );
    animation: ws-scan-move 0.3s linear infinite;
}
@keyframes ws-scan-move {
    from { background-position-y: 0; }
    to   { background-position-y: 8px; }
}

.ws-glitch-bars {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.ws-glitch-bar {
    position: absolute;
    left: 0; right: 0;
    background: rgba(0, 255, 100, 0.25);
    animation: ws-bar-flicker 0.15s steps(1) infinite;
}
@keyframes ws-bar-flicker {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
}

.ws-glitch-text {
    position: absolute;
    inset: 0;
    display: flex;
    flex-wrap: wrap;
    align-content: center;
    justify-content: center;
    gap: 4px;
    padding: 40px;
}
.ws-scramble-ch {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    color: rgba(0, 255, 100, 0.4);
    animation: ws-ch-flicker 0.08s steps(1) infinite;
}
@keyframes ws-ch-flicker {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.2; }
}

/* ── Signal overlay ───────────────────────────────────────────────────────────── */
.ws-signal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 2, 4, 0.97);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: default;
}

.ws-signal-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent, transparent 3px,
        rgba(0,255,100,0.015) 3px, rgba(0,255,100,0.015) 6px
    );
    pointer-events: none;
}

.ws-signal-box {
    position: relative;
    width: min(560px, 92vw);
    border: 1px solid rgba(0, 255, 100, 0.15);
    background: rgba(0, 4, 2, 0.98);
    padding: 24px 28px;
    font-family: 'JetBrains Mono', monospace;
    box-shadow: 0 0 60px rgba(0, 255, 100, 0.04);
}

.ws-signal-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.ws-sig-tag {
    font-size: 9px;
    color: rgba(0, 255, 100, 0.35);
    letter-spacing: 0.1em;
}
.ws-sig-blink {
    color: rgba(0, 255, 100, 0.4);
    font-size: 10px;
    animation: ws-blink 1s steps(1) infinite;
}
@keyframes ws-blink { 0%,49%{opacity:1} 50%,100%{opacity:0} }

.ws-signal-rule {
    border: none;
    border-top: 1px solid rgba(0, 255, 100, 0.08);
    margin: 8px 0;
}
.ws-signal-rule--end { margin-top: 16px; }

.ws-signal-label {
    font-size: 9px;
    color: rgba(0, 255, 100, 0.25);
    letter-spacing: 0.08em;
    margin-bottom: 10px;
}

.ws-signal-body {
    font-size: 12px;
    color: #b0f0c0;
    line-height: 1.7;
    white-space: pre-wrap;
    min-height: 60px;
}
.ws-signal-ch--glitch {
    color: rgba(255, 50, 80, 0.7);
}
.ws-signal-cursor {
    color: rgba(0, 255, 100, 0.7);
    animation: ws-blink 0.6s steps(1) infinite;
}

.ws-signal-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
}
.ws-end-tag {
    font-size: 9px;
    color: rgba(0, 255, 100, 0.25);
    letter-spacing: 0.1em;
}
.ws-dismiss {
    font-size: 8px;
    color: rgba(0, 255, 100, 0.2);
    letter-spacing: 0.1em;
    animation: ws-blink 1.5s ease-in-out infinite;
    cursor: pointer;
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */
.ws-glitch-enter-active { transition: opacity 0.05s; }
.ws-glitch-leave-active { transition: opacity 0.15s; }
.ws-glitch-enter-from,
.ws-glitch-leave-to     { opacity: 0; }

.ws-signal-enter-active { transition: opacity 0.2s ease; }
.ws-signal-leave-active { transition: opacity 0.3s ease; }
.ws-signal-enter-from,
.ws-signal-leave-to     { opacity: 0; }
</style>
