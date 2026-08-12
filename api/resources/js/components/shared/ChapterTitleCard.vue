<template>
    <Teleport to="body">
        <Transition name="ctc-fade">
            <div v-if="phase !== 'idle'" class="ctc-root">
                <GlitchEffect type="static" :intensity="staticIntensity" overlay :active="true" />

                <div class="ctc-content">
                    <div class="ctc-chapter" :class="{ 'ctc-chapter--visible': phase !== 'noise' }">
                        CHAPTER {{ chapterNumber }}
                    </div>
                    <div class="ctc-title">
                        <span
                            v-for="(ch, i) in renderedChars"
                            :key="i"
                            class="ctc-char"
                            :class="{ 'ctc-char--revealed': ch.revealed }"
                        >{{ ch.display }}</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
/**
 * ChapterTitleCard
 *
 * Full-screen chapter title reveal — the title resolves letter by letter out
 * of static noise (GlitchEffect type="static", overlay), background
 * intensity dropping as each letter locks in. Reusable across chapters:
 * pass chapterNumber + title; a future chapter with a different signature
 * feel can extend this with more props rather than a new component.
 *
 * NOT WIRED INTO ANY TRIGGER YET. Fires only when a parent sets :active to
 * true — nothing in Game.vue calls this today. Emits 'complete' once the
 * hold period after the reveal ends, same contract as WatcherSignal.vue /
 * FieldCommsWindow.vue (queue/trigger composable + timing-engine component
 * split), so wiring it in later is just: hold a ref, set it true, listen
 * for @complete.
 *
 * audio: optional path (relative to /audio/Sound/) to a static/noise SFX
 * played under the reveal. Omit it and the card plays silently — same
 * graceful-fallback pattern as everywhere else in this project. Drop a real
 * clip in later with no other changes needed here.
 */
import { ref, watch, onUnmounted } from 'vue';
import GlitchEffect from './GlitchEffect.vue';

const props = defineProps({
    chapterNumber: { type: [Number, String], required: true },
    title:         { type: String, required: true },
    active:        { type: Boolean, default: false },
    audio:         { type: String, default: null },
    holdMs:        { type: Number, default: 2200 },
});
const emit = defineEmits(['complete']);

const phase           = ref('idle');  // idle | noise | resolving | held
const renderedChars   = ref([]);
const staticIntensity = ref(1.0);

const GLITCH_CHARS = '!@#$%^&*<>{}|\\?~`01░▒▓█▌▐■□▸◈×÷';

let _timers  = [];
let _audioEl = null;

function _clearTimers() {
    _timers.forEach(t => { clearTimeout(t); clearInterval(t); });
    _timers = [];
}

function _rand() {
    return GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)];
}

function _playAudio() {
    if (!props.audio) return;
    try {
        _audioEl = new Audio('/audio/Sound/' + props.audio);
        _audioEl.volume = 0.8;
        _audioEl.play().catch(() => {});
    } catch (_) {}
}

function _stopAudio() {
    if (_audioEl) {
        _audioEl.pause();
        _audioEl.src = '';
        _audioEl = null;
    }
}

function _start() {
    _clearTimers();
    phase.value            = 'noise';
    staticIntensity.value  = 1.0;
    _playAudio();

    const title = props.title.toUpperCase();
    renderedChars.value = [...title].map(ch => ({
        char:     ch,
        display:  ch === ' ' ? ' ' : _rand(),
        revealed: false,
    }));

    // Brief pure-noise hold before letters start resolving out of it
    _timers.push(setTimeout(() => {
        phase.value = 'resolving';
        _resolveChars(title);
    }, 500));
}

function _resolveChars(title) {
    const chars      = [...title];
    const perCharMs  = 70;
    let   cumDelay   = 0;

    chars.forEach((ch, i) => {
        if (ch === ' ') return;
        const startAt     = cumDelay;
        const scrambleFor = 220 + Math.random() * 160;

        const interval = setInterval(() => {
            if (renderedChars.value[i].revealed) return;
            renderedChars.value[i].display = _rand();
        }, 45);
        _timers.push(interval);

        _timers.push(setTimeout(() => {
            clearInterval(interval);
            renderedChars.value[i].display  = ch;
            renderedChars.value[i].revealed = true;
        }, startAt + scrambleFor));

        cumDelay += perCharMs;
    });

    // Static intensity eases down over the same span as the letter reveal
    const totalReveal = cumDelay + 400;
    const fadeSteps    = 20;
    for (let s = 0; s <= fadeSteps; s++) {
        _timers.push(setTimeout(() => {
            staticIntensity.value = 1.0 - (s / fadeSteps) * 0.85;
        }, (totalReveal / fadeSteps) * s));
    }

    _timers.push(setTimeout(() => {
        phase.value = 'held';
        _timers.push(setTimeout(_finish, props.holdMs));
    }, totalReveal));
}

function _finish() {
    _clearTimers();
    _stopAudio();
    phase.value = 'idle';
    emit('complete');
}

watch(() => props.active, (v) => {
    if (v) _start();
}, { immediate: true });

onUnmounted(() => {
    _clearTimers();
    _stopAudio();
});
</script>

<style scoped>
.ctc-root {
    position: fixed;
    inset: 0;
    z-index: 10500;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: all;
}

.ctc-content {
    position: relative;
    z-index: 1;
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
}

.ctc-chapter {
    font-size: 13px;
    letter-spacing: 0.3em;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 14px;
    opacity: 0;
    transition: opacity 0.6s ease;
}
.ctc-chapter--visible { opacity: 1; }

.ctc-title {
    font-size: clamp(40px, 8vw, 96px);
    letter-spacing: 0.08em;
    color: #fff;
    text-shadow: 0 0 24px rgba(255, 255, 255, 0.15);
}

.ctc-char {
    display: inline-block;
    color: rgba(255, 255, 255, 0.4);
}
.ctc-char--revealed {
    color: #fff;
    text-shadow: 0 0 20px rgba(255, 255, 255, 0.4);
}

.ctc-fade-enter-active,
.ctc-fade-leave-active { transition: opacity 0.5s ease; }
.ctc-fade-enter-from,
.ctc-fade-leave-to     { opacity: 0; }
</style>
