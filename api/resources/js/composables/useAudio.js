/**
 * useAudio
 *
 * Background music player for CodeCraft.
 *
 * Tracks live at /audio/music/track1.mp3 – track4.mp3
 * (drop files into api/public/audio/music/).
 *
 * Behaviour:
 *   • Shuffles the playlist on start, re-shuffles when all tracks have played.
 *   • Fades between tracks using setInterval (reliable across tab states).
 *   • Mute/unmute preserves playback position.
 *   • Autoplay unlock: tries to play immediately on startAudio(); if the
 *     browser blocks it, waits for the first user click then starts automatically.
 *
 * Singleton pattern — module-level state is shared across all component
 * instances so GameMenu and Game.vue see the same muted/playing state.
 */

import { ref, readonly } from 'vue';

// ── Playlist ──────────────────────────────────────────────────────────────────
const TRACKS = [
    '/audio/music/track1.mp3',
    '/audio/music/track2.mp3',
    '/audio/music/track3.mp3',
    '/audio/music/track4.mp3',
];

let VOLUME            = 0.35;  // master volume 0–1 — adjustable via setVolume()
const FADE_IN_MS      = 1500;  // fade-in duration when a track starts
const FADE_OUT_MS     = 1000;  // fade-out duration before switching tracks
const FADE_TICK_MS    = 50;    // setInterval tick — 20 steps/sec, smooth enough

// ── Module-level singleton state ──────────────────────────────────────────────
const muted   = ref(false);
const playing = ref(false);
const volume  = ref(VOLUME);

let _audio        = null;   // active HTMLAudioElement
let _fadeInterval = null;   // setInterval id for current fade
let _playlist     = [];     // shuffled track list
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
        console.log('[AUDIO] Playlist reshuffled');
    }
    const url = _playlist[_trackIndex++];
    console.log('[AUDIO] Next track:', url);
    return url;
}

function clearFade() {
    if (_fadeInterval !== null) {
        clearInterval(_fadeInterval);
        _fadeInterval = null;
    }
}

/**
 * Smoothly move audioEl.volume toward targetVol over durationMs,
 * then call onComplete. Uses setInterval so it works even in
 * background tabs where requestAnimationFrame is throttled.
 */
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

// ── Core playback ─────────────────────────────────────────────────────────────
function playNext() {
    const url   = nextTrackUrl();
    const audio = new Audio(url);

    audio.volume  = 0;
    audio.preload = 'auto';
    audio.addEventListener('ended', onTrackEnded);

    console.log('[AUDIO] Attempting play...');

    audio.play()
        .then(() => {
            console.log('[AUDIO] Playing:', url);
            _audio        = audio;
            _unlocked     = true;
            playing.value = true;

            if (!muted.value) {
                fadeTo(audio, VOLUME, FADE_IN_MS, null);
            }
        })
        .catch((err) => {
            console.warn('[AUDIO] Blocked by browser — waiting for first click.', err.message);
            audio.removeEventListener('ended', onTrackEnded);
            // Re-attach unlock listener so the SAME url plays on next click
            _trackIndex--;   // step back so nextTrackUrl() returns the same track
            attachUnlockListener();
        });
}

function onTrackEnded() {
    console.log('[AUDIO] Track ended — fading to next');
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

    console.log('[AUDIO] Waiting for first user interaction...');

    _clickHandler = () => {
        if (_unlocked) return;
        console.log('[AUDIO] User interaction detected — starting music');
        window.removeEventListener('click', _clickHandler, { capture: true });
        _clickHandler = null;
        if (_started && !playing.value) playNext();
    };

    window.addEventListener('click', _clickHandler, { capture: true });
}

// ── Public API ────────────────────────────────────────────────────────────────

function startAudio() {
    if (_started) return;
    _started  = true;
    _playlist = shuffle(TRACKS);
    _trackIndex = 0;

    console.log('[AUDIO] startAudio() called — attempting immediate play');
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
    console.log('[AUDIO] Stopped');
}

function toggleMute() {
    muted.value = !muted.value;
    console.log('[AUDIO] Mute toggled —', muted.value ? 'MUTED' : 'UNMUTED');

    if (muted.value) {
        if (_audio) fadeTo(_audio, 0, 600, null);
    } else {
        if (_audio && playing.value) {
            fadeTo(_audio, VOLUME, 600, null);
        } else if (_started && _unlocked && !playing.value) {
            playNext();
        }
    }
}

/**
 * Set master volume (0–1). Takes effect immediately on the playing track.
 * Unmutes automatically if the player was muted.
 */
function setVolume(val) {
    const v   = Math.min(1, Math.max(0, val));
    VOLUME        = v;
    volume.value  = v;

    if (v > 0 && muted.value) {
        muted.value = false;
    }

    if (_audio && playing.value) {
        _audio.volume = muted.value ? 0 : v;
    }
}

export function useAudio() {
    return {
        muted:      readonly(muted),
        playing:    readonly(playing),
        volume:     readonly(volume),
        startAudio,
        stopAudio,
        toggleMute,
        setVolume,
    };
}
