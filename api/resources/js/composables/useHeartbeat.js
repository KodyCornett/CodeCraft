/**
 * useHeartbeat
 *
 * Keeps the player's last_seen_at timestamp fresh on the server so
 * NodeController::players() can exclude ghost players from node presence.
 *
 * Two mechanisms:
 *
 *   1. Interval ping  — POST /api/player/heartbeat every INTERVAL_MS.
 *      Covers normal play sessions; 45s gives two missed cycles before
 *      the 2-minute stale window expires server-side.
 *
 *   2. sendBeacon on beforeunload — fires when the tab is closed, the
 *      browser closes, or the user navigates away. navigator.sendBeacon()
 *      is guaranteed to complete even while the page is tearing down,
 *      unlike a regular axios call which would be aborted.
 *      This handles the common case instantly rather than waiting up to
 *      2 minutes for the interval to expire.
 *
 * Usage (Game.vue, after auth is confirmed):
 *   const { startHeartbeat, stopHeartbeat } = useHeartbeat();
 *   startHeartbeat();
 *   onUnmounted(stopHeartbeat);
 */

import axios from 'axios';

const INTERVAL_MS = 45_000;   // 45 seconds — two missed = stale on server

export function useHeartbeat() {
    let _timer = null;

    async function ping() {
        try {
            await axios.post('/api/player/heartbeat');
        } catch {
            // Fire-and-forget — a missed ping just means a slightly longer
            // stale window; it's non-critical.
        }
    }

    function onBeforeUnload() {
        // sendBeacon guarantees delivery during page teardown.
        // The empty string body satisfies the Content-Type requirement;
        // the server only needs to see the authenticated request arrive.
        navigator.sendBeacon('/api/player/heartbeat', '');
    }

    function startHeartbeat() {
        if (_timer !== null) return;   // idempotent

        // Fire immediately so last_seen_at is stamped on login, not 45s later.
        ping();

        _timer = setInterval(ping, INTERVAL_MS);

        // Instant cleanup on tab/browser close.
        window.addEventListener('beforeunload', onBeforeUnload);
    }

    function stopHeartbeat() {
        if (_timer !== null) {
            clearInterval(_timer);
            _timer = null;
        }
        window.removeEventListener('beforeunload', onBeforeUnload);
    }

    return { startHeartbeat, stopHeartbeat };
}
