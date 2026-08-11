<template>
    <Teleport to="body">
        <Transition name="fcw">
            <div v-if="call" class="fcw-root" :style="{ '--doc-accent': call.accentColor }">

                <div class="fcw-header">
                    <span class="fcw-dot" :class="{ 'fcw-dot--ringing': phase === 'ringing' }" />
                    <span class="fcw-title">
                        {{ phase === 'ringing' ? 'INCOMING TRANSMISSION' : `${call.docHandle} — LIVE` }}
                    </span>
                    <button class="fcw-skip" aria-label="Dismiss" title="Dismiss" @click="_finish">✕</button>
                </div>

                <TransitionGroup tag="div" name="fcw-line" class="fcw-lines">
                    <div
                        v-for="line in visibleLines"
                        :key="line.key"
                        class="fcw-line"
                        :class="{ 'fcw-line--player': line.speaker === 'player' }"
                    >
                        <span v-if="line.speaker === 'player'" class="fcw-you-tag">YOU</span>
                        {{ line.text }}
                    </div>
                </TransitionGroup>

            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
/**
 * FieldCommsWindow
 *
 * Renders the DOC's in-field voice-call check-ins — non-blocking, corner
 * overlay, capped to the last 3 lines with older ones scrolling off. Owns
 * the full ringing -> live -> ending phase machine and per-line reveal
 * timing (mirrors WatcherSignal.vue's phase machine and DialoguePage.vue's
 * audio/reading-time-fallback logic), receiving only the queued call from
 * useFieldComms() and emitting 'complete' when done — same split as
 * useWatcher.js / WatcherSignal.vue.
 *
 * Props.call shape:
 *   { stageId, docHandle, accentColor, lines: [{ text, audio?, speaker? }] }
 * speaker defaults to 'doc' when omitted. A trailing entry with
 * speaker: 'player' renders as the call's closing acknowledgment — written
 * as part of that stage's story beat, not a player-selectable choice.
 *
 * Audio is optional per line — omit it and the line holds for a reading-time
 * estimate instead, same graceful fallback DialoguePage.vue uses for a
 * missing or not-yet-recorded clip. Nothing here breaks if no lines carry
 * audio yet.
 */
import { ref, watch, onUnmounted } from 'vue';
import { useAudio } from '@/composables/useAudio.js';

const props = defineProps({
    call: { type: Object, default: null },
});
const emit = defineEmits(['complete']);

const MAX_VISIBLE        = 3;
const RING_MS             = 700;
const HOLD_AFTER_LAST_MS  = 2500;

const phase        = ref('idle');   // idle | ringing | live
const visibleLines = ref([]);       // capped to MAX_VISIBLE, oldest drops first

const { duckForCall, unduckAfterCall, storyVolume, muted } = useAudio();

let _timers    = [];
let _lineAudio = null;
let _lineIdx   = 0;
let _keySeq    = 0;

function _clearTimers() {
    _timers.forEach(t => clearTimeout(t));
    _timers = [];
}

function _pushLine(text, speaker) {
    _keySeq++;
    const next = [...visibleLines.value, { key: _keySeq, text, speaker: speaker ?? 'doc' }];
    visibleLines.value = next.length > MAX_VISIBLE ? next.slice(next.length - MAX_VISIBLE) : next;
}

// Plays one line's audio clip if present. Missing/blocked audio falls back
// to a reading-time pause estimated from the line's own text length, rather
// than stalling the call.
function _playLineAudio(relativePath, text, onDone) {
    if (_lineAudio) {
        _lineAudio.pause();
        _lineAudio.src = '';
        _lineAudio = null;
    }

    const el = new Audio('/audio/Sound/' + relativePath);
    el.volume = muted.value ? 0 : storyVolume.value;
    _lineAudio = el;

    let fired = false;
    const done = () => {
        if (fired) return;
        fired = true;
        if (_lineAudio === el) _lineAudio = null;
        onDone();
    };

    const fallbackMs = Math.max(1800, (text?.length ?? 40) * 45);

    el.addEventListener('ended', done);
    el.addEventListener('error', () => {
        console.warn('[FIELD COMMS] Audio file not found:', relativePath);
        _timers.push(setTimeout(done, fallbackMs));
    });
    el.play().catch(() => {
        _timers.push(setTimeout(done, fallbackMs));
    });
}

function _revealNext() {
    const lines = props.call?.lines ?? [];
    if (_lineIdx >= lines.length) {
        _timers.push(setTimeout(_finish, HOLD_AFTER_LAST_MS));
        return;
    }

    const line = lines[_lineIdx];
    _lineIdx++;
    _pushLine(line.text, line.speaker);

    if (line.audio) {
        _playLineAudio(line.audio, line.text, _revealNext);
    } else {
        const readMs = Math.max(1800, (line.text?.length ?? 40) * 45);
        _timers.push(setTimeout(_revealNext, readMs));
    }
}

function _startCall() {
    _clearTimers();
    _lineIdx            = 0;
    visibleLines.value  = [];
    phase.value         = 'ringing';
    duckForCall();

    _timers.push(setTimeout(() => {
        phase.value = 'live';
        _revealNext();
    }, RING_MS));
}

function _finish() {
    _clearTimers();
    if (_lineAudio) {
        _lineAudio.pause();
        _lineAudio.src = '';
        _lineAudio = null;
    }
    phase.value        = 'idle';
    visibleLines.value = [];
    unduckAfterCall();
    emit('complete');
}

watch(() => props.call, (call) => {
    if (call) _startCall();
}, { immediate: true });

onUnmounted(() => {
    _clearTimers();
    if (_lineAudio) {
        _lineAudio.pause();
        _lineAudio.src = '';
        _lineAudio = null;
    }
});
</script>

<style scoped>
.fcw-root {
    position: fixed;
    right: 12px;
    bottom: 56px;   /* clears the NavBar taskbar */
    width: 300px;
    z-index: 9500;  /* above QuestMinigameChrome (9000), below Watcher (10000+) */
    font-family: 'JetBrains Mono', monospace;
    background: rgba(4, 4, 14, 0.94);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    border: 1px solid color-mix(in srgb, var(--doc-accent) 35%, transparent);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    pointer-events: auto;
}

.fcw-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px 8px 12px;
    border-bottom: 1px solid color-mix(in srgb, var(--doc-accent) 15%, transparent);
}

.fcw-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--doc-accent);
    box-shadow: 0 0 6px var(--doc-accent);
    flex-shrink: 0;
    animation: fcw-pulse 1.5s ease-in-out infinite;
}
.fcw-dot--ringing { animation: fcw-ring 0.5s ease-in-out infinite; }
@keyframes fcw-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
@keyframes fcw-ring  { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(1.4); } }

.fcw-title {
    flex: 1;
    font-size: 9px;
    letter-spacing: 0.14em;
    color: var(--doc-accent);
    text-shadow: 0 0 8px color-mix(in srgb, var(--doc-accent) 40%, transparent);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fcw-skip {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.3);
    font-family: inherit;
    font-size: 10px;
    cursor: pointer;
    padding: 2px 4px;
    flex-shrink: 0;
}
.fcw-skip:hover { color: #fff; }

.fcw-lines {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px 12px 12px;
    min-height: 48px;
}

.fcw-line {
    font-size: 11px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.85);
    letter-spacing: 0.03em;
}

/* Newest line reads slightly brighter than the ones scrolling toward the top */
.fcw-lines .fcw-line:last-child { color: #fff; }

/* Player's closing acknowledgment — distinct voice, not doc-colored */
.fcw-line--player {
    display: flex;
    align-items: baseline;
    gap: 7px;
    padding-left: 8px;
    border-left: 2px solid rgba(255, 255, 255, 0.2);
    color: rgba(220, 235, 255, 0.85) !important;
    font-style: italic;
}

.fcw-you-tag {
    font-style: normal;
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(255, 255, 255, 0.4);
    flex-shrink: 0;
}

.fcw-line-move      { transition: transform 0.25s ease; }
.fcw-line-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.fcw-line-leave-active { transition: opacity 0.2s ease; position: absolute; }
.fcw-line-enter-from   { opacity: 0; transform: translateY(6px); }
.fcw-line-leave-to     { opacity: 0; }

.fcw-enter-active,
.fcw-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.fcw-enter-from,
.fcw-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
