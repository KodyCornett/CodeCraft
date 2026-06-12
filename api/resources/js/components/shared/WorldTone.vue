<template>
    <div class="wt-root" :class="{ 'wt-fading': fading }">
        <div class="wt-viewport">
            <div class="wt-lines" :style="{ transform: `translateY(${scrollOffset}px)` }">
                <p
                    v-for="(line, i) in completedLines"
                    :key="'c' + i"
                    class="wt-line"
                    :style="{ opacity: lineOpacity(i) }"
                >{{ line.text }}</p>

                <p v-if="active" class="wt-line wt-active">
                    {{ active.typed }}<span class="wt-cursor">█</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * WorldTone.vue — Opening cinematic narration scene.
 *
 * Lines are triggered by audio.currentTime so text stays locked to the voice.
 *
 * startSec values are computed from:
 *   • speaking duration estimate at ~150 WPM per line
 *   • 1.5 s SSML break after every line
 *
 * If a line feels early/late after hearing the audio, nudge its startSec.
 * All values are cumulative from t=0 of the audio file.
 *
 * Script source (SSML): each <break time="1.5s" /> marks the boundary.
 *
 * Emits @done when cinematic completes.
 */

import { ref, computed, onUnmounted } from 'vue';

const emit = defineEmits(['done']);

// ── Script — 41 lines, startSec computed from word count + 1.5 s breaks ───────
// To recalibrate: open the audio in Audacity, find when each line starts, update.
// startSec values are computed from the SSML break times + estimated speaking
// duration per line at ~145 WPM narrator rate. If a line feels early or late
// after generating the audio, nudge its startSec by ±0.5 until it locks.
const LINES = [
    { text: "Nobody remembers exactly when the physical world stopped being enough.",                                                                             startSec:   0.0 },
    { text: "It happened slowly at first.",                                                                                                                       startSec:   6.7 },
    { text: "A few hours a day.",                                                                                                                                 startSec:  10.8 },
    { text: "Then a few more.",                                                                                                                                   startSec:  14.1 },
    { text: "Then most.",                                                                                                                                         startSec:  16.8 },
    { text: "The consoles came cheap. The connection came easy. And The Splice Frequency offered something the concrete world never could.",                       startSec:  20.6 },
    { text: "A place where you could be exactly who you decided to be.",                                                                                          startSec:  30.6 },
    { text: "Feel exactly what you chose to feel.",                                                                                                               startSec:  37.6 },
    { text: "Build something that mattered in a world that answered back.",                                                                                       startSec:  42.0 },
    { text: "The cities didn't empty.",                                                                                                                           startSec:  48.7 },
    { text: "They just went quiet.",                                                                                                                              startSec:  52.4 },
    { text: "People are still out there.",                                                                                                                        startSec:  56.6 },
    { text: "Bodies in chairs.",                                                                                                                                  startSec:  60.2 },
    { text: "Bodies in beds.",                                                                                                                                    startSec:  62.5 },
    { text: "Bodies kept alive by systems that ask no questions and make no demands.",                                                                            startSec:  64.8 },
    { text: "But the part of them that thinks...",                                                                                                                startSec:  72.8 },
    { text: "The part that wants...",                                                                                                                             startSec:  77.4 },
    { text: "The part that acts...",                                                                                                                              startSec:  80.6 },
    { text: "That part lives here.",                                                                                                                              startSec:  83.8 },
    { text: "On the Frequency.",                                                                                                                                  startSec:  88.0 },
    { text: "In the network beneath the network, where corporations built their towers and runners carved out their tunnels.",                                     startSec:  92.3 },
    { text: "The Splice Frequency is real.",                                                                                                                      startSec: 101.4 },
    { text: "More real than the room your body is sitting in right now.",                                                                                         startSec: 105.5 },
    { text: "You can smell it.",                                                                                                                                  startSec: 112.5 },
    { text: "Feel it.",                                                                                                                                           startSec: 115.0 },
    { text: "The heat of a node under load.",                                                                                                                     startSec: 116.6 },
    { text: "The sting of static when ICE gets close.",                                                                                                          startSec: 120.3 },
    { text: "The strange silence of a district at three in the morning, when the traffic fades and the grid itself seems to breathe.",                            startSec: 124.7 },
    { text: "The corporations own the upper layers.",                                                                                                             startSec: 136.9 },
    { text: "Clean.",                                                                                                                                             startSec: 140.9 },
    { text: "Bright.",                                                                                                                                            startSec: 142.3 },
    { text: "Controlled.",                                                                                                                                        startSec: 143.7 },
    { text: "They sell access.",                                                                                                                                  startSec: 146.3 },
    { text: "They sell safety.",                                                                                                                                  startSec: 148.4 },
    { text: "They sell the version of the Frequency they want you to see.",                                                                                       startSec: 150.5 },
    { text: "Everything beneath it belongs to everyone else.",                                                                                                    startSec: 158.0 },
    { text: "That's where you live.",                                                                                                                             startSec: 162.4 },
    { text: "That's where you work.",                                                                                                                             startSec: 164.9 },
    { text: "That's where you run.",                                                                                                                              startSec: 167.4 },
    { text: "Welcome to the Frequency.",                                                                                                                          startSec: 171.1 },
    { text: "Try not to flatline.",                                                                                                                               startSec: 175.3 },
];

// ── Config ────────────────────────────────────────────────────────────────────
const CHAR_DELAY_MS  = 14;   // keep fast — text must finish before narrator does
const LINE_HEIGHT_PX = 56;   // must match CSS (.wt-line line-height + margin-bottom)
const HOLD_AFTER_MS  = 2000; // pause after audio ends before fade
const FADE_MS        = 1800; // must match CSS transition on .wt-root

// ── State ─────────────────────────────────────────────────────────────────────
const completedLines = ref([]);
const active         = ref(null);
const fading         = ref(false);

const scrollOffset = computed(() =>
    completedLines.value.length * -LINE_HEIGHT_PX
);

function lineOpacity(i) {
    const distFromEnd = completedLines.value.length - 1 - i;
    return Math.max(0.10, 1 - distFromEnd * 0.10);
}

// ── Typewriter ────────────────────────────────────────────────────────────────
let _typingTimer   = null;
let _typingResolve = null;
let _typingBusy    = false;

function _typeText(text) {
    return new Promise(resolve => {
        _typingResolve = resolve;
        active.value   = { text, typed: '' };
        let i = 0;

        function tick() {
            if (i >= text.length) {
                completedLines.value.push({ text });
                active.value   = null;
                _typingResolve = null;
                resolve();
                return;
            }
            active.value = { text, typed: text.slice(0, ++i) };
            _typingTimer = setTimeout(tick, CHAR_DELAY_MS);
        }
        tick();
    });
}

function _snapCurrent() {
    if (_typingTimer)   { clearTimeout(_typingTimer); _typingTimer = null; }
    if (active.value)   { completedLines.value.push({ text: active.value.text }); active.value = null; }
    if (_typingResolve) { _typingResolve(); _typingResolve = null; }
}

// ── Audio-time poll ───────────────────────────────────────────────────────────
let _audio       = null;
let _pollInterval = null;
let _nextIdx      = 0;

function _startPoll() {
    _pollInterval = setInterval(() => {
        if (!_audio) return;
        const nowSec = _audio.currentTime;

        while (_nextIdx < LINES.length && nowSec >= LINES[_nextIdx].startSec) {
            const line = LINES[_nextIdx++];
            if (_typingBusy) _snapCurrent();
            _typingBusy = true;
            _typeText(line.text).then(() => { _typingBusy = false; });
        }
    }, 80);
}

// ── Cinematic entry ───────────────────────────────────────────────────────────
function runCinematic() {
    _audio = new Audio('/audio/Sound/narrator/world_tone.mp3');
    _audio.volume = 0.9;

    _audio.addEventListener('ended', async () => {
        clearInterval(_pollInterval);
        if (_typingBusy) _snapCurrent();
        // Snap any lines the poll missed (shouldn't happen with correct startSec values)
        while (_nextIdx < LINES.length) {
            completedLines.value.push({ text: LINES[_nextIdx++].text });
        }
        await new Promise(r => setTimeout(r, HOLD_AFTER_MS));
        fading.value = true;
        await new Promise(r => setTimeout(r, FADE_MS));
        emit('done');
    });

    _audio.play()
        .then(() => _startPoll())
        .catch(() => {
            console.warn('[WORLDTONE] Audio blocked or missing — running fallback timer.');
            _runFallback();
        });
}

// ── Fallback — no audio available ─────────────────────────────────────────────
async function _runFallback() {
    for (const line of LINES) {
        _typingBusy = true;
        await _typeText(line.text);
        _typingBusy = false;
        await new Promise(r => setTimeout(r, 2500));
    }
    await new Promise(r => setTimeout(r, HOLD_AFTER_MS));
    fading.value = true;
    await new Promise(r => setTimeout(r, FADE_MS));
    emit('done');
}

runCinematic();

// ── Cleanup ───────────────────────────────────────────────────────────────────
onUnmounted(() => {
    clearInterval(_pollInterval);
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
    max-width: 620px;
    height: 60vh;
    overflow: hidden;
    position: relative;
    padding: 0 48px;
    box-sizing: border-box;

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

.wt-lines {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0 48px;
    box-sizing: border-box;
    transition: transform 0.9s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

/* LINE_HEIGHT_PX = line-height (~24px at 13px/1.65) + margin-bottom (32px) = ~56px */
.wt-line {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.65;
    letter-spacing: 0.04em;
    color: rgba(220, 215, 235, 0.9);
    margin: 0 0 32px 0;
    padding: 0;
    white-space: pre-wrap;
    transition: opacity 0.6s ease;
}

.wt-active {
    color: rgba(235, 230, 250, 1);
    opacity: 1 !important;
}

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
