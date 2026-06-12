<template>
    <div class="wt-root" :class="{ 'wt-fading': fading }">
        <div class="wt-viewport">
            <div class="wt-lines" :style="{ transform: `translateY(${scrollOffset}px)` }">
                <p
                    v-for="(line, i) in completedLines"
                    :key="'c' + i"
                    class="wt-line"
                    :style="{ opacity: lineOpacity(i) }"
                >{{ line }}</p>

                <p v-if="currentText !== null" class="wt-line wt-active">
                    {{ currentText }}<span class="wt-cursor">█</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * WorldTone.vue — Opening cinematic narration scene.
 *
 * Plays once on first login, after PersonaSelect and before BootSequence.
 * Narrator audio: /audio/Sound/narrator/world_tone.mp3
 *
 * TIMING GUIDE — Each LINES entry has { text, pauseMs }.
 * pauseMs is the silence AFTER the typewriter finishes the line.
 * Tune these values so the visual roughly tracks your audio recording.
 *
 * Emits @done when the cinematic is complete.
 */

import { ref, computed, onUnmounted } from 'vue';

const emit = defineEmits(['done']);

// ── Script ────────────────────────────────────────────────────────────────────
// pauseMs = silence held after this line finishes typing, before the next begins.
// Adjust to match your narrator audio recording.
const LINES = [
    { text: "Nobody remembers exactly when the physical world stopped being enough.", pauseMs: 3000 },
    { text: "It happened slowly at first.",                                           pauseMs: 2200 },
    { text: "A few hours a day.",                                                     pauseMs: 1800 },
    { text: "Then a few more.",                                                       pauseMs: 1800 },
    { text: "Then most.",                                                             pauseMs: 3200 },
    { text: "The rigs came first. Console systems. Crude things.",                    pauseMs: 2600 },
    { text: "Then came full sensory integration.",                                    pauseMs: 2200 },
    { text: "Smell. Taste. Touch.",                                                   pauseMs: 2800 },
    { text: "If your rig is good enough, you stop noticing the difference.",         pauseMs: 3000 },
    { text: "Most people stopped trying.",                                            pauseMs: 3400 },
    { text: "The Splice Frequency runs beneath everything.",                          pauseMs: 2600 },
    { text: "A hidden channel carved into the city's infrastructure.",               pauseMs: 2800 },
    { text: "People don't log into SPLICE.",                                          pauseMs: 2000 },
    { text: "They live there.",                                                       pauseMs: 3600 },
    { text: "The ones who work the underground call themselves runners.",             pauseMs: 2600 },
    { text: "They move between nodes. Hack data. Stay invisible.",                   pauseMs: 2800 },
    { text: "Or they try to.",                                                        pauseMs: 3400 },
    { text: "Your rig is old. Your signal bleeds all over the network.",             pauseMs: 2800 },
    { text: "Someone noticed.",                                                       pauseMs: 4000 },
    { text: "Welcome to the Frequency.",                                              pauseMs: 2600 },
    { text: "Try not to flatline.",                                                   pauseMs: 0    },
];

// ── Typewriter config ─────────────────────────────────────────────────────────
const CHAR_DELAY_MS  = 32;   // ms per character while typing
const LINE_HEIGHT_PX = 56;   // px per line (font + margin) — matches CSS below
const FADE_HOLD_MS   = 2000; // pause after last line before fade begins
const FADE_MS        = 1800; // fade-to-black duration (matches CSS transition)

// ── State ─────────────────────────────────────────────────────────────────────
const completedLines = ref([]);
const currentText    = ref(null);
const fading         = ref(false);

// Derived translateY — shifts the block up by one line height per completed line.
// CSS transition on .wt-lines animates this smoothly.
const scrollOffset = computed(() =>
    completedLines.value.length * -LINE_HEIGHT_PX
);

// Older lines fade to dim so the active line always reads as foreground.
function lineOpacity(i) {
    const distFromEnd = completedLines.value.length - 1 - i;
    return Math.max(0.12, 1 - distFromEnd * 0.13);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const _timers = [];

function sleep(ms) {
    return new Promise(resolve => {
        const id = setTimeout(resolve, ms);
        _timers.push(id);
    });
}

// ── Audio ─────────────────────────────────────────────────────────────────────
let _audio = null;

function _startAudio() {
    _audio = new Audio('/audio/Sound/narrator/world_tone.mp3');
    _audio.volume = 0.9;
    _audio.play().catch(() => {
        console.warn('[WORLDTONE] Narrator audio blocked or missing — visual will run without audio.');
    });
}

// ── Cinematic loop ────────────────────────────────────────────────────────────
async function runCinematic() {
    _startAudio();

    for (let i = 0; i < LINES.length; i++) {
        const line = LINES[i];
        currentText.value = '';

        // Typewriter — one character at a time
        for (const char of line.text) {
            await sleep(CHAR_DELAY_MS);
            currentText.value += char;
        }

        // Brief hold so the cursor shows on the completed line before moving on
        await sleep(280);

        // Promote to completed (triggers scroll animation)
        completedLines.value.push(line.text);
        currentText.value = null;

        // Inter-line pause
        if (line.pauseMs > 0) {
            await sleep(line.pauseMs);
        }
    }

    // Final hold — let the last line breathe
    await sleep(FADE_HOLD_MS);

    // Fade to black
    fading.value = true;
    await sleep(FADE_MS);

    emit('done');
}

// Start immediately on mount — this is called from Game.vue which gates it
// behind first-login state, so it never fires for returning players.
runCinematic();

// ── Cleanup ───────────────────────────────────────────────────────────────────
onUnmounted(() => {
    _timers.forEach(clearTimeout);
    if (_audio) {
        _audio.pause();
        _audio.src = '';
        _audio = null;
    }
});
</script>

<style scoped>
/* ── Root — full-screen black overlay ──────────────────────────────────────── */
.wt-root {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: #05030c;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 1.8s ease;
}

/* Fade-to-black on completion */
.wt-fading {
    opacity: 0;
}

/* ── Viewport — clips overflow so lines drift upward cleanly ───────────────── */
.wt-viewport {
    width: 100%;
    max-width: 620px;
    height: 60vh;
    overflow: hidden;
    position: relative;
    padding: 0 48px;
    box-sizing: border-box;

    /* Gradient mask: lines fade in at top, sharp at bottom */
    -webkit-mask-image: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(0,0,0,0.15) 12%,
        rgba(0,0,0,0.6) 28%,
        black 45%,
        black 100%
    );
    mask-image: linear-gradient(
        to bottom,
        transparent 0%,
        rgba(0,0,0,0.15) 12%,
        rgba(0,0,0,0.6) 28%,
        black 45%,
        black 100%
    );
}

/* ── Lines container — animated upward drift ───────────────────────────────── */
.wt-lines {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0 48px;
    box-sizing: border-box;
    transition: transform 0.9s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* ── Individual line ────────────────────────────────────────────────────────── */
.wt-line {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.65;
    letter-spacing: 0.04em;
    color: rgba(220, 215, 235, 0.9);
    margin: 0 0 28px 0;     /* 28px gap + ~24px line-height ≈ LINE_HEIGHT_PX 56px */
    padding: 0;
    white-space: pre-wrap;
    transition: opacity 0.6s ease;
}

/* Active (currently typing) line — full brightness */
.wt-active {
    color: rgba(230, 225, 245, 1);
    opacity: 1 !important;
}

/* ── Blinking cursor ────────────────────────────────────────────────────────── */
.wt-cursor {
    display: inline-block;
    color: rgba(0, 255, 200, 0.85);
    animation: wt-blink 0.6s steps(1) infinite;
    margin-left: 2px;
    font-size: 11px;
    vertical-align: baseline;
}

@keyframes wt-blink {
    0%, 49%  { opacity: 1; }
    50%, 100% { opacity: 0; }
}
</style>
