/**
 * useDialogue
 *
 * Manages NPC/scene dialogue state for CodeCraft.
 *
 * Persists the active dialogue position (id, line index, audio time) to
 * localStorage so closing the dialogue panel or the browser mid-conversation
 * resumes from exactly where the player left off.
 *
 * Integrates with useAudio: fades music out on open, fades back in on close.
 * Story audio is paused on panel close and seeked back on resume.
 *
 * Singleton pattern — module-level state is shared across all component
 * instances so the dialogue panel and any trigger button see the same state.
 */

import { ref, readonly } from 'vue';
import { useAudio } from './useAudio.js';

// ── Storage key ───────────────────────────────────────────────────────────────
const LS_KEY = 'cc_dialogue_state';

// ── Module-level singleton state ──────────────────────────────────────────────
const dialogueId = ref(null);  // string key identifying the active dialogue tree
const lineIndex  = ref(0);     // current line position within the tree
const audioTime  = ref(0);     // story clip playback position in seconds
const isOpen     = ref(false);

let _storyEl = null;           // active HTMLAudioElement from useAudio().playStory()

// ── Persistence helpers ───────────────────────────────────────────────────────
function _persist() {
    if (!dialogueId.value) {
        localStorage.removeItem(LS_KEY);
        return;
    }
    localStorage.setItem(LS_KEY, JSON.stringify({
        dialogueId: dialogueId.value,
        lineIndex:  lineIndex.value,
        audioTime:  audioTime.value,
    }));
}

function _restore(definitions) {
    const raw = localStorage.getItem(LS_KEY);
    if (!raw) return;

    try {
        const s = JSON.parse(raw);
        if (s.dialogueId && definitions[s.dialogueId]) {
            dialogueId.value = s.dialogueId;
            lineIndex.value  = s.lineIndex ?? 0;
            audioTime.value  = s.audioTime ?? 0;
        } else {
            localStorage.removeItem(LS_KEY);
        }
    } catch {
        localStorage.removeItem(LS_KEY);
    }
}

// ── Public API ────────────────────────────────────────────────────────────────

/**
 * Call once on Game.vue mount with the dialogue definitions map
 * ({ [id: string]: DialogueTree }) so stale IDs are discarded on restore.
 */
function initDialogue(definitions) {
    _restore(definitions);
}

/**
 * Start a new dialogue from line 0.
 * Fades music out and begins story audio if a clip URL is provided.
 *
 * @param {string}      id        - Dialogue tree key
 * @param {string|null} audioUrl  - Optional story clip URL
 */
function openDialogue(id, audioUrl = null) {
    const { fadeOutForDialogue, playStory } = useAudio();

    dialogueId.value = id;
    lineIndex.value  = 0;
    audioTime.value  = 0;
    isOpen.value     = true;

    _persist();
    fadeOutForDialogue();

    if (audioUrl) {
        _storyEl = playStory(audioUrl);
    }
}

/**
 * Close the panel mid-dialogue. Saves audio position for resume.
 * Fades music back in.
 */
function closeDialogue() {
    const { fadeInAfterDialogue } = useAudio();

    if (_storyEl) {
        audioTime.value = _storyEl.currentTime;
        _storyEl.pause();
    }

    isOpen.value = false;
    _persist();
    fadeInAfterDialogue();
}

/**
 * Reopen a paused dialogue and resume story audio from the saved position.
 * No-op if no saved dialogue exists.
 *
 * @param {string|null} audioUrl - The same clip URL originally passed to openDialogue()
 */
function resumeDialogue(audioUrl = null) {
    const { fadeOutForDialogue, playStory } = useAudio();

    if (!dialogueId.value) return;

    isOpen.value = true;
    fadeOutForDialogue();

    if (audioUrl) {
        _storyEl = playStory(audioUrl);

        if (audioTime.value > 0) {
            _storyEl.addEventListener('canplay', () => {
                _storyEl.currentTime = audioTime.value;
            }, { once: true });
        }
    }
}

/**
 * Advance to the next dialogue line. Persists position automatically.
 */
function advanceLine() {
    lineIndex.value++;
    _persist();
}

/**
 * Mark dialogue as fully complete. Clears saved state and fades music back in.
 */
function completeDialogue() {
    const { fadeInAfterDialogue } = useAudio();

    if (_storyEl) {
        _storyEl.pause();
        _storyEl = null;
    }

    dialogueId.value = null;
    lineIndex.value  = 0;
    audioTime.value  = 0;
    isOpen.value     = false;

    _persist(); // dialogueId is null — removes the key
    fadeInAfterDialogue();
}

export function useDialogue() {
    return {
        dialogueId: readonly(dialogueId),
        lineIndex:  readonly(lineIndex),
        audioTime:  readonly(audioTime),
        isOpen:     readonly(isOpen),
        initDialogue,
        openDialogue,
        closeDialogue,
        resumeDialogue,
        advanceLine,
        completeDialogue,
    };
}
