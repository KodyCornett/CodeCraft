/**
 * useAudio
 *
 * Background music + story audio player for CodeCraft.
 *
 * Music tracks live at /audio/music/track1.mp3 – track4.mp3.
 * Story clips are one-shot files played via playStory(url).
 *
 * Two independent volume channels:
 *   musicVolume  — background ambient tracks (0–1, default 0.35)
 *   storyVolume  — dialogue / NPC narration clips (0–1, default 0.80)
 *
 * Both are persisted to localStorage (cc_music_vol / cc_story_vol)
 * so settings survive reload.
 *
 * Behaviour:
 *   • Music shuffles on start, re-shuffles when all tracks have played.
 *   • Fades between music tracks using setInterval (reliable across tab states).
 *   • Mute/unmute preserves both volumes and restores them on unmute.
 *   • Autoplay unlock: tries to play immediately on startAudio(); if the
 *     browser blocks it, waits for the first user click then starts automatically.
 *
 * Singleton pattern — module-level state is shared across all component
 * instances so GameMenu and Game.vue see the same state.
 */

import { ref, readonly } from 'vue';

// ── Playlist ──────────────────────────────────────────────────────────────────
const TRACKS = [
    '/audio/music/track1.mp3',
    '/audio/music/track2.mp3',
    '/audio/music/track3.mp3',
    '/audio/music/track4.mp3',
];

// ── Volume defaults + localStorage persistence ────────────────────────────────
const LS_MUSIC_VOL = 'cc_music_vol';
const LS_STORY_VOL = 'cc_story_vol';

function loadVol(key, fallback) {
    const v = parseFloat(localStorage.getItem(key));
    return isNaN(v) ? fallback : Math.min(1, Math.max(0, v));
}

let MUSIC_VOL = loadVol(LS_MUSIC_VOL, 0.35);
let STORY_VOL = loadVol(LS_STORY_VOL, 0.80);

// ── Fade constants ────────────────────────────────────────────────────────────
const FADE_IN_MS   = 1500;
const FADE_OUT_MS  = 1000;
const FADE_TICK_MS = 50;

// ── Module-level singleton state ──────────────────────────────────────────────
const muted       = ref(false);
const playing     = ref(false);
const musicVolume = ref(MUSIC_VOL);
const storyVolume = ref(STORY_VOL);

let _audio        = null;   // active music HTMLAudioElement
let _fadeInterval = null;
let _playlist     = [];
let _trackIndex   = 0;
let _started      = false;
let _unlocked     = false;
let _clickHandler = null;

// ── Helpers ───────────────────────────────────────────────────────────────────
function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function nextTrackUrl() {
    if (_trackIndex >= _playlist.length) {
        _playlist   = shuffle(TRACKS);
        _trackIndex = 0;
    }
    return _playlist[_trackIndex++];
}

function clearFade() {
    if (_fadeInterval !== null) {
        clearInterval(_fadeInterval);
        _fadeInterval = null;
    }
}

function fadeTo(audioEl, targetVol, durationMs, onComplete) {
    clearFade();

    const steps     = Math.max(1, Math.round(durationMs / FADE_TICK_MS));
    const startVol  = audioEl.volume;
    const delta     = (targetVol - startVol) / steps;
    let   stepsDone = 0;

    _fadeInterval = setInterval(() => {
        stepsDone++;
        audioEl.volume = Math.min(1, Math.max(0, startVol + delta * stepsDone));

        if (stepsDone >= steps) {
            audioEl.volume = targetVol;
            clearFade();
            onComplete?.();
        }
    }, FADE_TICK_MS);
}

// ── Core music playback ───────────────────────────────────────────────────────
function playNext() {
    const url   = nextTrackUrl();
    const audio = new Audio(url);

    audio.volume  = 0;
    audio.preload = 'auto';
    audio.addEventListener('ended', onTrackEnded);

    audio.play()
        .then(() => {
            _audio        = audio;
            _unlocked     = true;
            playing.value = true;

            if (!muted.value) {
                fadeTo(audio, MUSIC_VOL, FADE_IN_MS, null);
            }
        })
        .catch((err) => {
            console.warn('[AUDIO] Blocked by browser — waiting for first click.', err.message);
            audio.removeEventListener('ended', onTrackEnded);
            _trackIndex--;
            attachUnlockListener();
        });
}

function onTrackEnded() {
    if (_audio === null) { playNext(); return; }

    const outgoing = _audio;
    _audio = null;
    playing.value = false;

    fadeTo(outgoing, 0, FADE_OUT_MS, () => {
        outgoing.pause();
        outgoing.removeEventListener('ended', onTrackEnded);
        playNext();
    });
}

// ── Autoplay unlock ───────────────────────────────────────────────────────────
function attachUnlockListener() {
    if (_clickHandler) return;

    _clickHandler = () => {
        if (_unlocked) return;
        window.removeEventListener('click', _clickHandler, { capture: true });
        _clickHandler = null;
        if (_started && !playing.value) playNext();
    };

    window.addEventListener('click', _clickHandler, { capture: true });
}

// ── Public API ────────────────────────────────────────────────────────────────

function startAudio() {
    if (_started) return;
    _started    = true;
    _playlist   = shuffle(TRACKS);
    _trackIndex = 0;
    playNext();
}

function stopAudio() {
    _started  = false;
    _unlocked = false;
    clearFade();

    if (_audio) {
        _audio.pause();
        _audio.removeEventListener('ended', onTrackEnded);
        _audio.src = '';
        _audio = null;
    }

    if (_clickHandler) {
        window.removeEventListener('click', _clickHandler, { capture: true });
        _clickHandler = null;
    }

    playing.value = false;
}

/**
 * Play a one-shot story / dialogue audio clip at the current story volume.
 * Returns the HTMLAudioElement so the caller can stop it early if needed.
 */
function playStory(url) {
    const el = new Audio(url);
    el.volume = muted.value ? 0 : STORY_VOL;
    el.play().catch((e) => console.warn('[AUDIO] Story clip blocked:', e.message));
    return el;
}

/**
 * Fade music out for a dialogue scene (1.5s), then pause.
 * Preserves playback position so fadeInAfterDialogue() resumes the same track.
 */
function fadeOutForDialogue() {
    clearFade();
    if (!_audio) return;
    fadeTo(_audio, 0, 1500, () => {
        if (_audio) {
            _audio.pause();
            playing.value = false;
        }
    });
}

/**
 * Fade music back in after a dialogue scene (1.5s).
 */
function fadeInAfterDialogue() {
    if (!_started || !_unlocked) return;
    if (!_audio) {
        playNext();
        return;
    }
    clearFade();
    _audio.play().catch(() => {});
    playing.value = true;
    fadeTo(_audio, muted.value ? 0 : MUSIC_VOL, 1500, null);
}

/**
 * Partial duck — for field comms calls, which run alongside live gameplay
 * rather than taking over a scene. Fades music down to 35% of its current
 * volume instead of fadeOutForDialogue()'s full silence, so the game still
 * feels "on" underneath the call.
 */
function duckForCall() {
    clearFade();
    if (!_audio || muted.value) return;
    fadeTo(_audio, MUSIC_VOL * 0.35, 500, null);
}

/**
 * Restore full music volume after a field comms call ends.
 */
function unduckAfterCall() {
    if (!_started || !_unlocked) return;
    clearFade();
    if (!_audio) return;
    fadeTo(_audio, muted.value ? 0 : MUSIC_VOL, 700, null);
}

/**
 * Instantly silence and pause — for dramatic story interrupts.
 */
function cutAudio() {
    clearFade();
    if (_audio) {
        _audio.volume = 0;
        _audio.pause();
    }
    playing.value = false;
}

/**
 * Resume after cutAudio().
 */
function resumeAudio() {
    if (!_started || !_unlocked) return;
    if (playing.value) return;
    playNext();
}

/**
 * Master mute toggle — silences both music and story channels.
 * Restores volumes on unmute.
 */
function toggleMute() {
    muted.value = !muted.value;

    if (muted.value) {
        if (_audio) fadeTo(_audio, 0, 600, null);
    } else {
        if (_audio && playing.value) {
            fadeTo(_audio, MUSIC_VOL, 600, null);
        } else if (_started && _unlocked && !playing.value) {
            playNext();
        }
    }
}

/**
 * Set music volume (0–1). Persists to localStorage.
 */
function setMusicVolume(val) {
    const v   = Math.min(1, Math.max(0, val));
    MUSIC_VOL         = v;
    musicVolume.value = v;
    localStorage.setItem(LS_MUSIC_VOL, String(v));

    if (_audio && playing.value && !muted.value) {
        _audio.volume = v;
    }
}

/**
 * Set story volume (0–1). Persists to localStorage.
 * Affects all future playStory() calls.
 */
function setStoryVolume(val) {
    const v   = Math.min(1, Math.max(0, val));
    STORY_VOL         = v;
    storyVolume.value = v;
    localStorage.setItem(LS_STORY_VOL, String(v));
}

// ── Legacy alias — kept so existing callers of setVolume() don't break ────────
function setVolume(val) { setMusicVolume(val); }

export function useAudio() {
    return {
        muted:            readonly(muted),
        playing:          readonly(playing),
        musicVolume:      readonly(musicVolume),
        storyVolume:      readonly(storyVolume),
        /** @deprecated use musicVolume */ volume: readonly(musicVolume),
        startAudio,
        stopAudio,
        cutAudio,
        resumeAudio,
        fadeOutForDialogue,
        fadeInAfterDialogue,
        duckForCall,
        unduckAfterCall,
        toggleMute,
        setMusicVolume,
        setStoryVolume,
        /** @deprecated use setMusicVolume */ setVolume,
        playStory,
    };
}
