/**
 * useWatcher
 *
 * Manages the Watcher signal interrupt system.
 *
 * On boot, polls GET /api/watcher/unread.
 * When unread signals exist, fires triggerSignal() which:
 *   1. Sets the active signal for WatcherSignal.vue to render
 *   2. The component handles the glitch → message → collapse sequence
 *   3. After the sequence, hasUnread stays true until the player
 *      opens the Watcher channel page (which calls markAllRead)
 *
 * External API:
 *   triggerSignal(signal)  — fire the interrupt manually (called by WatcherSignal.vue after fetch)
 *   fetchUnread()          — poll for pending signals
 *   markAllRead()          — called when player opens the Watcher channel
 *   activeSignal           — the signal currently being displayed (null = none)
 *   hasUnread              — true if any unread signals exist (drives HUD glitch indicator)
 *   allSignals             — full signal log for the Watcher channel page
 *   fetchAll()             — fetch the full log
 */

import { ref, readonly } from 'vue';
import axios from 'axios';

export function useWatcher() {
    const activeSignal = ref(null);   // signal currently showing in WatcherSignal.vue
    const hasUnread    = ref(false);
    const allSignals   = ref([]);
    const queue        = ref([]);     // signals waiting to display (if multiple pending)

    // ── Fetch ─────────────────────────────────────────────────────────────────

    async function fetchUnread() {
        try {
            const res = await axios.get('/api/watcher/unread');
            hasUnread.value = res.data.has_unread ?? false;
            const signals   = res.data.signals    ?? [];

            // Queue any signals not already queued
            signals.forEach(sig => {
                if (!queue.value.find(q => q.id === sig.id)) {
                    queue.value.push(sig);
                }
            });

            _processQueue();
        } catch (e) {
            console.warn('[WATCHER] fetchUnread failed:', e?.message);
        }
    }

    async function fetchAll() {
        try {
            const res  = await axios.get('/api/watcher/all');
            allSignals.value = res.data.signals ?? [];
        } catch (e) {
            console.warn('[WATCHER] fetchAll failed:', e?.message);
        }
    }

    async function markAllRead() {
        try {
            await axios.post('/api/watcher/read-all');
            hasUnread.value = false;
            // Update local read state
            allSignals.value = allSignals.value.map(s => ({
                ...s,
                read_at: s.read_at ?? new Date().toISOString(),
            }));
        } catch (e) {
            console.warn('[WATCHER] markAllRead failed:', e?.message);
        }
    }

    // ── Signal display ────────────────────────────────────────────────────────

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
        activeSignal:    readonly(activeSignal),
        hasUnread:       readonly(hasUnread),
        allSignals:      readonly(allSignals),
        fetchUnread,
        fetchAll,
        markAllRead,
        triggerSignal,
        onSignalComplete,
    };
}
