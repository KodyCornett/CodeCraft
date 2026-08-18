/**
 * useWatcher
 *
 * Manages the Watcher signal interrupt cinematic — the corrupted broadcast
 * overlay (WatcherSignal.vue) that plays the tutorial/prologue intrusion
 * signals. Story-triggered only: callers push a signal object directly via
 * triggerSignal(), there is no server-backed message feed or archive here.
 * (An earlier DB-backed archive/unread system was removed — it was never
 * populated by any content and had no reachable UI. See WATCHER_TRANSITIONS
 * in constants/watcherTransitions.js for how the prologue interrupts are
 * triggered and made reload-safe.)
 *
 * External API:
 *   triggerSignal(signal)  — queue a signal for display: { id, signal_text }
 *   activeSignal           — the signal currently being displayed (null = none)
 *   onSignalComplete()     — called by the interrupt component when its
 *                            glitch → message → collapse sequence finishes;
 *                            advances to the next queued signal, if any
 */

import { ref, readonly } from 'vue';

export function useWatcher() {
    const activeSignal = ref(null);   // signal currently showing in WatcherSignal.vue
    const queue        = ref([]);     // signals waiting to display (if multiple pending)

    /**
     * Called by the interrupt component when it has finished its sequence.
     * Moves to the next queued signal if any.
     */
    function onSignalComplete() {
        activeSignal.value = null;
        queue.value.shift();
        // Small delay between back-to-back signals
        if (queue.value.length > 0) {
            setTimeout(_processQueue, 800);
        }
    }

    function _processQueue() {
        if (activeSignal.value !== null) return;  // already showing one
        if (queue.value.length === 0) return;
        activeSignal.value = queue.value[0];
    }

    /**
     * Push a signal object directly into the queue without an API round-trip.
     * Used for story-triggered signals (e.g. post-install Watcher interrupt).
     */
    function triggerSignal(signal) {
        if (!queue.value.find(q => q.id === signal.id)) {
            queue.value.push(signal);
        }
        _processQueue();
    }

    return {
        activeSignal: readonly(activeSignal),
        triggerSignal,
        onSignalComplete,
    };
}
