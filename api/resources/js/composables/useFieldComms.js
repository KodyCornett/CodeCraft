/**
 * useFieldComms
 *
 * Call queue for DOC field comms — the in-field voice-call check-ins that
 * play while a player is working a mission stage, distinct from the
 * CyberDoc terminal dialogue (useDialogue) and the DOC hub chat rooms
 * (useDocChat). This composable only owns the queue; all reveal timing,
 * audio playback, and the 3-line scroll-off ticker live in
 * FieldCommsWindow.vue, which receives activeCall and calls
 * onCallComplete() when done — mirrors the useWatcher.js / WatcherSignal.vue
 * split (queue composable + timing-engine component).
 *
 * Call shape:
 * {
 *   stageId:     string,               // guards against re-triggering the same stage
 *   docHandle:   string,               // e.g. 'KNUCKLE'
 *   accentColor: string,
 *   lines:       [{ text: string, audio?: string, speaker?: string, fx?: { type: string, duration?: number } }],
 * }
 */

import { ref, readonly } from 'vue';

export function useFieldComms() {
    const activeCall = ref(null);   // call currently showing in FieldCommsWindow.vue
    const queue      = ref([]);     // calls waiting to play (if triggered back-to-back)

    // Stage ids that have already placed a call this session — arrival can
    // re-fire (walking on/off the node) but the call should only play once.
    const _playedStageIds = new Set();

    function _processQueue() {
        if (activeCall.value !== null) return;
        if (queue.value.length === 0) return;
        activeCall.value = queue.value[0];
    }

    /**
     * Enqueue a call. No-ops if this stage has already placed its call,
     * is already queued, or is the one currently playing.
     */
    function triggerCall(call) {
        if (!call?.stageId || _playedStageIds.has(call.stageId)) return;
        if (activeCall.value?.stageId === call.stageId) return;
        if (queue.value.find(c => c.stageId === call.stageId)) return;

        _playedStageIds.add(call.stageId);
        queue.value.push(call);
        _processQueue();
    }

    /**
     * Called by FieldCommsWindow.vue when a call finishes, naturally or skipped.
     * Small delay before the next queued call so back-to-back calls don't
     * slam into each other.
     */
    function onCallComplete() {
        activeCall.value = null;
        queue.value.shift();
        if (queue.value.length > 0) {
            setTimeout(_processQueue, 800);
        }
    }

    return {
        activeCall: readonly(activeCall),
        triggerCall,
        onCallComplete,
    };
}
