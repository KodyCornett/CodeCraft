<template>
    <div class="wt-root" :class="{ 'wt-fading': fading }">
        <div class="wt-viewport" ref="viewportEl">
            <p class="wt-text">{{ typed }}<span v-if="!done" class="wt-cursor">█</span></p>
            <span ref="anchorEl" />
        </div>
    </div>
</template>

<script setup>
/**
 * WorldTone.vue — Opening cinematic narration scene.
 *
 * Types the full narration as one paragraph. Text stays ahead of the narrator
 * audio — the typewriter always finishes before the audio ends.
 *
 * CHAR_DELAY_MS controls speed. Lower = faster. Tune it so the text finishes
 * a few seconds before the audio ends, never after.
 *
 * Emits @done when the audio ends and the hold period finishes.
 */

import { ref, nextTick, onUnmounted } from 'vue';

const emit = defineEmits(['done']);

// ── Full narration — one continuous paragraph ─────────────────────────────────
const FULL_TEXT =
    "Nobody remembers exactly when the physical world stopped being enough. " +
    "It happened slowly at first. " +
    "A few hours a day. " +
    "Then a few more. " +
    "Then most. " +
    "The consoles came cheap. The connection came easy. And The Splice Frequency offered something the concrete world never could. " +
    "A place where you could be exactly who you decided to be. " +
    "Feel exactly what you chose to feel. " +
    "Build something that mattered in a world that answered back. " +
    "The cities didn't empty. " +
    "They just went quiet. " +
    "People are still out there. " +
    "Bodies in chairs. " +
    "Bodies in beds. " +
    "Bodies kept alive by systems that ask no questions and make no demands. " +
    "But the part of them that thinks... " +
    "The part that wants... " +
    "The part that acts... " +
    "That part lives here. " +
    "On the Frequency. " +
    "In the network beneath the network, where corporations built their towers and runners carved out their tunnels. " +
    "The Splice Frequency is real. " +
    "More real than the room your body is sitting in right now. " +
    "You can smell it. " +
    "Feel it. " +
    "The heat of a node under load. " +
    "The sting of static when ICE gets close. " +
    "The strange silence of a district at three in the morning, when the traffic fades and the grid itself seems to breathe. " +
    "The corporations own the upper layers. " +
    "Clean. " +
    "Bright. " +
    "Controlled. " +
    "They sell access. " +
    "They sell safety. " +
    "They sell the version of the Frequency they want you to see. " +
    "Everything beneath it belongs to everyone else. " +
    "That's where you live. " +
    "That's where you work. " +
    "That's where you run. " +
    "Welcome to the Frequency. " +
    "Try not to flatline.";

// ── Config ────────────────────────────────────────────────────────────────────
// Tune CHAR_DELAY_MS so text finishes a few seconds BEFORE audio ends.
// Lower the value if text is falling behind the narrator.
const CHAR_DELAY_MS = 50;
const HOLD_AFTER_MS = 2000;
const FADE_MS       = 1800;

// ── State ─────────────────────────────────────────────────────────────────────
const typed      = ref('');
const done       = ref(false);
const fading     = ref(false);
const viewportEl = ref(null);
const anchorEl   = ref(null);

// ── Audio ─────────────────────────────────────────────────────────────────────
let _audio = null;

// ── Typewriter ────────────────────────────────────────────────────────────────
let _typingTimer = null;

function _startTyping() {
    let i = 0;
    function tick() {
        if (i >= FULL_TEXT.length) {
            done.value = true;
            return;
        }
        typed.value += FULL_TEXT[i++];
        nextTick(() => {
            anchorEl.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
        _typingTimer = setTimeout(tick, CHAR_DELAY_MS);
    }
    tick();
}

// ── Cinematic entry ───────────────────────────────────────────────────────────
function runCinematic() {
    _audio = new Audio('/audio/Sound/narrator/world_tone.mp3');
    _audio.volume = 0.9;

    _audio.addEventListener('ended', async () => {
        // Snap any remaining text instantly so nothing's missing
        if (!done.value) {
            if (_typingTimer) { clearTimeout(_typingTimer); _typingTimer = null; }
            typed.value = FULL_TEXT;
            done.value  = true;
        }
        await new Promise(r => setTimeout(r, HOLD_AFTER_MS));
        fading.value = true;
        await new Promise(r => setTimeout(r, FADE_MS));
        emit('done');
    });

    // Start typing immediately — text leads the audio
    _startTyping();

    _audio.play().catch(() => {
        console.warn('[WORLDTONE] Audio blocked or missing — running text only.');
        // Fallback: emit done when typing finishes + hold
        const checkDone = setInterval(async () => {
            if (done.value) {
                clearInterval(checkDone);
                await new Promise(r => setTimeout(r, HOLD_AFTER_MS));
                fading.value = true;
                await new Promise(r => setTimeout(r, FADE_MS));
                emit('done');
            }
        }, 500);
    });
}

runCinematic();

// ── Cleanup ───────────────────────────────────────────────────────────────────
onUnmounted(() => {
    if (_typingTimer) clearTimeout(_typingTimer);
    if (_audio) { _audio.pause(); _audio.src = ''; _audio = null; }
});
</script>

<style scoped>
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

.wt-fading {
    opacity: 0;
}

.wt-viewport {
    width: 100%;
    max-width: 580px;
    max-height: 70vh;
    overflow-y: scroll;
    scrollbar-width: none;       /* Firefox */
    padding: 0 48px 80px;
    box-sizing: border-box;

    /* Fade top edge so old text dissolves upward */
    -webkit-mask-image: linear-gradient(to bottom, transparent 0%, black 18%, black 100%);
    mask-image: linear-gradient(to bottom, transparent 0%, black 18%, black 100%);
}

.wt-viewport::-webkit-scrollbar {
    display: none;               /* Chrome/Safari */
}

.wt-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 2.0;
    letter-spacing: 0.04em;
    color: rgba(220, 215, 235, 0.88);
    margin: 0;
    padding: 0;
    white-space: pre-wrap;
    word-break: break-word;
}

.wt-cursor {
    display: inline-block;
    color: rgba(0, 255, 200, 0.85);
    animation: wt-blink 0.6s steps(1) infinite;
    margin-left: 1px;
    font-size: 11px;
    vertical-align: baseline;
}

@keyframes wt-blink {
    0%, 49%  { opacity: 1; }
    50%, 100% { opacity: 0; }
}
</style>
