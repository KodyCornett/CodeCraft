<template>
    <div class="dp-root" :style="{ '--dp-accent': accentColor }">
        <div class="dp-scanline" />

        <!-- ── Header ────────────────────────────────────────────────────── -->
        <div class="dp-header">
            <div class="dp-header-left">
                <span class="dp-npc-handle">{{ npcHandle }}</span>
                <span class="dp-npc-sub">{{ npcSubtitle }}</span>
            </div>
            <div class="dp-header-right">
                <span class="dp-loc">{{ locationLabel }}</span>
            </div>
        </div>
        <div class="dp-rule" />

        <!-- ── Dialogue entries ───────────────────────────────────────────── -->
        <div class="dp-scroll" ref="scrollEl">
            <div
                v-for="(entry, i) in revealedEntries"
                :key="i"
                class="dp-entry"
                :class="`dp-entry--${entry.speaker.toLowerCase().replace('_', '-')}`"
            >
                <!-- NARRATOR -->
                <template v-if="entry.speaker === 'NARRATOR'">
                    <p class="dp-narrator" v-html="formatText(entry.text)" />
                </template>

                <!-- NPC line -->
                <template v-else-if="entry.speaker !== 'PLAYER_CHOICE' && entry.speaker !== 'PLAYER_SAID'">
                    <div class="dp-speaker-row">
                        <span class="dp-speaker-tag">{{ entry.speaker }}</span>
                        <span class="dp-speaker-sep">▸</span>
                    </div>
                    <p class="dp-npc-line" v-html="formatText(entry.text)" />
                </template>

                <!-- Player's chosen line (echoed back) -->
                <template v-else-if="entry.speaker === 'PLAYER_SAID'">
                    <div class="dp-player-said">
                        <span class="dp-player-tag">YOU</span>
                        <span class="dp-player-said-text" v-html="formatText(entry.text)" />
                    </div>
                </template>
            </div>

            <!-- Typing indicator — shows while next entry is being revealed -->
            <div v-if="isTyping && !choicesReady && !complete" class="dp-typing">
                <span class="dp-cursor">█</span>
            </div>

            <!-- Player choice options -->
            <Transition name="dp-choices-fade">
                <div v-if="choicesReady && !choiceMade" class="dp-choices">
                    <div class="dp-choices-label">[ SELECT RESPONSE ]</div>
                    <button
                        v-for="opt in currentChoices"
                        :key="opt.tone"
                        class="dp-choice-btn"
                        @click="selectChoice(opt)"
                    >
                        <span class="dp-choice-tone">{{ opt.tone }}</span>
                        <span class="dp-choice-text">{{ opt.text }}</span>
                    </button>
                </div>
            </Transition>

            <!-- End state -->
            <Transition name="dp-choices-fade">
                <div v-if="complete" class="dp-end">
                    <div class="dp-end-rule" />
                    <button class="dp-end-btn" @click="$emit('complete', selectedChoice)">
                        [ CLOSE TRANSMISSION ]
                    </button>
                </div>
            </Transition>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';
import { useAudio } from '../../../composables/useAudio.js';

const props = defineProps({
    entries:       { type: Array,  required: true },
    npcHandle:     { type: String, default: 'UNKNOWN' },
    npcSubtitle:   { type: String, default: '' },
    locationLabel: { type: String, default: '' },
    accentColor:   { type: String, default: '#00FFC8' },
    ambientSrc:    { type: String, default: null },
});

const emit = defineEmits(['complete', 'reached-end']);

const { storyVolume, muted } = useAudio();

// ── State ─────────────────────────────────────────────────────────────────────
const revealedEntries = ref([]);   // entries visible so far (excluding PLAYER_CHOICE)
const currentChoices  = ref([]);   // options from the current PLAYER_CHOICE entry
const choicesReady    = ref(false);
const choiceMade      = ref(false);
const complete        = ref(false);
const isTyping        = ref(false);
const selectedChoice  = ref(null);
const scrollEl        = ref(null);

let _entryIdx   = 0;
let _timers     = [];
let _lineAudio  = null;

// ── Ambient audio ─────────────────────────────────────────────────────────────
// Loops under NPC lines only. Fades out for narrator + player choice sections.
let _ambient        = null;
let _ambientFade    = null;
const AMBIENT_VOL   = 0.3;
const AMBIENT_FADE  = 800; // ms

function _ambientFadeTo(targetVol) {
    if (_ambientFade) { clearInterval(_ambientFade); _ambientFade = null; }
    if (!_ambient) return;
    const steps    = Math.max(1, Math.round(AMBIENT_FADE / 50));
    const startVol = _ambient.volume;
    const delta    = (targetVol - startVol) / steps;
    let   done     = 0;
    _ambientFade = setInterval(() => {
        done++;
        _ambient.volume = Math.min(1, Math.max(0, startVol + delta * done));
        if (done >= steps) {
            _ambient.volume = targetVol;
            clearInterval(_ambientFade);
            _ambientFade = null;
            if (targetVol === 0) _ambient.pause();
        }
    }, 50);
}

function _ambientIn() {
    if (!props.ambientSrc) return;
    if (!_ambient) {
        _ambient          = new Audio('/audio/Sound/ambient/' + props.ambientSrc);
        _ambient.loop     = true;
        _ambient.volume   = 0;
        _ambient.play().catch(() => {});
    } else {
        _ambient.play().catch(() => {});
    }
    _ambientFadeTo(AMBIENT_VOL);
}

function _ambientOut() {
    if (!_ambient) return;
    _ambientFadeTo(0);
}

function _ambientStop() {
    if (_ambientFade) { clearInterval(_ambientFade); _ambientFade = null; }
    if (_ambient) {
        _ambient.pause();
        _ambient.src = '';
        _ambient     = null;
    }
}

// Plays a line's audio file. Calls onEnded when the clip finishes.
// Guards against double-firing (_fired flag) so error + rejected play
// can't both trigger onEnded. Missing files fall back to a reading-time
// pause so the story keeps moving without stampeding into the next line.
function _playLineAudio(relativePath, onEnded) {
    if (_lineAudio) {
        _lineAudio.pause();
        _lineAudio.src = '';
        _lineAudio = null;
    }

    const url = '/audio/Sound/' + relativePath;
    const el  = new Audio(url);
    el.volume  = muted.value ? 0 : storyVolume.value;
    _lineAudio = el;

    let _fired = false;
    const done = () => {
        if (_fired) return;
        _fired = true;
        if (_lineAudio === el) _lineAudio = null;
        onEnded?.();
    };

    // Clean finish
    el.addEventListener('ended', done);

    // File missing or corrupt — hold for a reading-time estimate, then advance
    el.addEventListener('error', () => {
        console.warn('[DIALOGUE] Audio file not found:', url);
        const holdMs = Math.max(3000, (el.textContent?.length ?? 0) * 40) ;
        const t = setTimeout(done, holdMs);
        _timers.push(t);
    });

    // Autoplay blocked by browser — hold 4s then advance
    el.play().catch(() => {
        console.warn('[DIALOGUE] Audio blocked by browser:', url);
        const t = setTimeout(done, 4000);
        _timers.push(t);
    });
}

// ── Format text — newlines → <br> ────────────────────────────────────────────
function formatText(text) {
    return (text ?? '').replace(/\n/g, '<br>');
}

// ── Scroll to bottom ──────────────────────────────────────────────────────────
function _scrollBottom() {
    nextTick(() => {
        if (scrollEl.value) {
            scrollEl.value.scrollTo({ top: scrollEl.value.scrollHeight, behavior: 'smooth' });
        }
    });
}

// ── Reveal engine ─────────────────────────────────────────────────────────────
// Delays between entry types (ms)
const DELAY = {
    NARRATOR:     900,
    NPC:          700,
    PLAYER_CHOICE: 400,
    PLAYER_SAID:  500,
    AFTER_CHOICE: 600,
};

function _revealNext() {
    if (_entryIdx >= props.entries.length) {
        complete.value = true;
        isTyping.value = false;
        _ambientOut();
        _scrollBottom();
        emit('reached-end');
        return;
    }

    const entry = props.entries[_entryIdx];
    _entryIdx++;

    if (entry.speaker === 'PLAYER_CHOICE') {
        currentChoices.value = entry.options ?? [];
        isTyping.value = false;
        _ambientOut();
        const t = setTimeout(() => {
            choicesReady.value = true;
            _scrollBottom();
        }, DELAY.PLAYER_CHOICE);
        _timers.push(t);
        return;
    }

    // Ambient: fade in for NPC lines, fade out for narrator
    if (entry.speaker === 'NARRATOR') {
        _ambientOut();
    } else {
        _ambientIn();
    }

    // Regular entry — show typing cursor, then reveal line and play audio.
    // If the entry has audio, the next line is held until the clip finishes.
    // If no audio, a short reading-time pause is used instead.
    isTyping.value = true;
    const delay = entry.speaker === 'NARRATOR' ? DELAY.NARRATOR : DELAY.NPC;

    const t = setTimeout(() => {
        revealedEntries.value.push(entry);
        isTyping.value = false;
        _scrollBottom();

        if (entry.audio) {
            // Show cursor again while audio plays, advance when clip ends
            isTyping.value = true;
            _playLineAudio(entry.audio, () => {
                isTyping.value = false;
                const next = setTimeout(_revealNext, 400);
                _timers.push(next);
            });
        } else {
            // No audio — estimate reading time from text length (min 1.5s)
            const readMs = Math.max(1500, (entry.text?.length ?? 60) * 35);
            const next = setTimeout(_revealNext, readMs);
            _timers.push(next);
        }
    }, delay);
    _timers.push(t);
}

function selectChoice(opt) {
    choiceMade.value  = true;
    selectedChoice.value = opt;

    // Echo the player's line into the dialogue stream
    const t1 = setTimeout(() => {
        revealedEntries.value.push({ speaker: 'PLAYER_SAID', text: opt.text });
        _scrollBottom();

        const t2 = setTimeout(() => {
            _revealNext();
        }, DELAY.AFTER_CHOICE);
        _timers.push(t2);
    }, DELAY.PLAYER_SAID);
    _timers.push(t1);
}

// ── Keep audio in sync with volume/mute changes made during playback ──────────
// storyVolume change — update line audio only (ambient is fixed at AMBIENT_VOL)
watch(storyVolume, (vol) => {
    if (_lineAudio && !muted.value) _lineAudio.volume = vol;
});
// muted toggle — update both line audio and ambient in one pass
watch(muted, (isMuted) => {
    if (_lineAudio) _lineAudio.volume = isMuted ? 0 : storyVolume.value;
    if (_ambient) {
        if (isMuted) _ambient.volume = 0;
        else if (!_ambient.paused) _ambient.volume = AMBIENT_VOL;
    }
});

// ── Start when entries are provided ───────────────────────────────────────────
watch(() => props.entries, (val) => {
    if (val && val.length > 0) {
        _entryIdx = 0;
        revealedEntries.value = [];
        choicesReady.value    = false;
        choiceMade.value      = false;
        complete.value        = false;
        selectedChoice.value  = null;

        const t = setTimeout(_revealNext, 300);
        _timers.push(t);
    }
}, { immediate: true });

// ── Stop all playback — called on unmount and by parent when browser closes ───
function _stopAll() {
    _timers.forEach(id => clearTimeout(id));
    _timers.length = 0;
    if (_lineAudio) {
        _lineAudio.pause();
        _lineAudio.src = '';
        _lineAudio = null;
    }
    _ambientStop();
}

onUnmounted(_stopAll);

// Expose stop() so DocDialoguePage can kill audio the moment the browser
// signals it is closing — before the leave-transition fires onUnmounted.
defineExpose({ stop: _stopAll });
</script>

<style scoped>
.dp-root {
    position: relative;
    width: 100%;
    height: 100%;
    background: rgba(4, 2, 10, 0.99);
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    --dp-accent: #00FFC8;
}

.dp-scanline {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,                   transparent                   2px,
        rgba(0, 255, 200, 0.008) 2px,  rgba(0, 255, 200, 0.008) 4px
    );
    z-index: 0;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.dp-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 14px 20px 12px;
    background: rgba(0, 0, 0, 0.3);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}

.dp-header-left {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.dp-npc-handle {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0.2em;
    color: var(--dp-accent);
    text-shadow: 0 0 16px color-mix(in srgb, var(--dp-accent) 60%, transparent);
}

.dp-npc-sub {
    font-size: 9px;
    letter-spacing: 0.14em;
    color: color-mix(in srgb, var(--dp-accent) 50%, transparent);
}

.dp-loc {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.35);
    padding-top: 4px;
}

.dp-rule {
    border: none;
    border-top: 1px solid color-mix(in srgb, var(--dp-accent) 20%, transparent);
    flex-shrink: 0;
}

/* ── Scroll area ──────────────────────────────────────────────────────────── */
.dp-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    position: relative;
    z-index: 1;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--dp-accent) 25%, transparent) transparent;
}

/* ── Entry types ──────────────────────────────────────────────────────────── */
.dp-narrator {
    font-size: 11px;
    line-height: 1.9;
    color: rgba(200, 195, 210, 0.7);
    letter-spacing: 0.05em;
    font-style: italic;
    margin: 0;
    animation: dp-fade-in 0.4s ease forwards;
}

.dp-speaker-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.dp-speaker-tag {
    font-size: 9px;
    letter-spacing: 0.2em;
    color: var(--dp-accent);
    text-shadow: 0 0 8px color-mix(in srgb, var(--dp-accent) 50%, transparent);
}

.dp-speaker-sep {
    font-size: 9px;
    color: color-mix(in srgb, var(--dp-accent) 40%, transparent);
}

.dp-npc-line {
    font-size: 12px;
    line-height: 1.8;
    color: rgba(240, 235, 245, 0.88);
    letter-spacing: 0.04em;
    margin: 0;
    animation: dp-fade-in 0.3s ease forwards;
}

/* Player's echoed response */
.dp-player-said {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 8px 12px;
    border-left: 2px solid rgba(255, 255, 255, 0.15);
    background: rgba(255, 255, 255, 0.03);
    animation: dp-fade-in 0.25s ease forwards;
}

.dp-player-tag {
    font-size: 8px;
    letter-spacing: 0.2em;
    color: rgba(255, 255, 255, 0.45);
    flex-shrink: 0;
    padding-top: 2px;
}

.dp-player-said-text {
    font-size: 11px;
    line-height: 1.7;
    color: rgba(220, 215, 230, 0.75);
    letter-spacing: 0.04em;
    font-style: italic;
}

/* ── Typing indicator ─────────────────────────────────────────────────────── */
.dp-typing {
    display: flex;
    padding: 4px 0;
}

.dp-cursor {
    font-size: 13px;
    color: var(--dp-accent);
    opacity: 0.7;
    animation: dp-blink 0.55s steps(1) infinite;
}

@keyframes dp-blink {
    0%, 49% { opacity: 0.7; }
    50%, 100% { opacity: 0; }
}

/* ── Player choices ───────────────────────────────────────────────────────── */
.dp-choices {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.dp-choices-label {
    font-size: 8px;
    letter-spacing: 0.2em;
    color: rgba(255, 255, 255, 0.3);
    margin-bottom: 4px;
}

.dp-choice-btn {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: transparent;
    border: 1px solid color-mix(in srgb, var(--dp-accent) 18%, transparent);
    padding: 10px 14px;
    cursor: pointer;
    text-align: left;
    transition: all 0.15s;
    font-family: 'JetBrains Mono', monospace;
}

.dp-choice-btn:hover {
    background: color-mix(in srgb, var(--dp-accent) 6%, transparent);
    border-color: color-mix(in srgb, var(--dp-accent) 45%, transparent);
}

.dp-choice-tone {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: var(--dp-accent);
    flex-shrink: 0;
    padding-top: 1px;
    min-width: 72px;
    opacity: 0.8;
}

.dp-choice-text {
    font-size: 11px;
    line-height: 1.6;
    color: rgba(220, 215, 230, 0.8);
    letter-spacing: 0.04em;
}

/* ── End state ────────────────────────────────────────────────────────────── */
.dp-end {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding-top: 10px;
}

.dp-end-rule {
    width: 100%;
    height: 1px;
    background: color-mix(in srgb, var(--dp-accent) 15%, transparent);
}

.dp-end-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid color-mix(in srgb, var(--dp-accent) 35%, transparent);
    color: var(--dp-accent);
    padding: 9px 24px;
    cursor: pointer;
    transition: all 0.15s;
    text-shadow: 0 0 8px color-mix(in srgb, var(--dp-accent) 40%, transparent);
}

.dp-end-btn:hover {
    background: color-mix(in srgb, var(--dp-accent) 08%, transparent);
    border-color: var(--dp-accent);
    box-shadow: 0 0 12px color-mix(in srgb, var(--dp-accent) 20%, transparent);
}

/* ── Transitions ──────────────────────────────────────────────────────────── */
@keyframes dp-fade-in {
    from { opacity: 0; transform: translateY(4px); }
    to   { opacity: 1; transform: none; }
}

.dp-choices-fade-enter-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.dp-choices-fade-leave-active { transition: opacity 0.15s ease; }
.dp-choices-fade-enter-from   { opacity: 0; transform: translateY(6px); }
.dp-choices-fade-leave-to     { opacity: 0; }
</style>
