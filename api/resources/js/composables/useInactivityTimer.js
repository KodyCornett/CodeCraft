/**
 * useInactivityTimer
 *
 * Tracks user activity. After IDLE_MS of inactivity:
 *   - Sets warningActive = true and starts a WARN_MS countdown.
 *
 * If the player does not cancel within WARN_MS:
 *   - Calls POST /logout and redirects to /login.
 *
 * cancel() resets both timers (call when player clicks "Keep Session").
 * destroy() cleans up listeners (call in Game.vue onUnmounted).
 */

import { ref, computed } from 'vue';
import axios from 'axios';

const IDLE_MS = 20 * 60 * 1000;   // 20 minutes idle before warning
const WARN_MS =  2 * 60 * 1000;   // 2 minutes to cancel before auto-logout
const TICK_MS = 1000;              // countdown resolution

export function useInactivityTimer() {

    const warningActive   = ref(false);
    const secondsLeft     = ref(Math.floor(WARN_MS / 1000));

    // MM:SS string for display
    const countdown = computed(() => {
        const m = Math.floor(secondsLeft.value / 60).toString().padStart(2, '0');
        const s = (secondsLeft.value % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    });

    let idleTimer      = null;
    let tickInterval   = null;
    let _beforeLogout  = null;   // optional async hook — set via setBeforeLogout()

    // ── Activity listeners ────────────────────────────────────────────────────

    const ACTIVITY_EVENTS = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];

    function _onActivity() {
        if (warningActive.value) return;  // player is in the warning window — don't reset
        _resetIdleTimer();
    }

    function _resetIdleTimer() {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(_onIdle, IDLE_MS);
    }

    function _onIdle() {
        warningActive.value = true;
        secondsLeft.value   = Math.floor(WARN_MS / 1000);

        // Start the 2-minute countdown tick
        tickInterval = setInterval(() => {
            secondsLeft.value -= 1;
            if (secondsLeft.value <= 0) {
                _logout();
            }
        }, TICK_MS);
    }

    async function _logout() {
        _clearAll();
        if (_beforeLogout) {
            try { await _beforeLogout(); } catch {}
        }
        try {
            await axios.post('/logout');
        } catch {
            // Session may already be gone — proceed to redirect regardless
        }
        window.location.href = '/login';
    }

    /** Register an async callback that runs before session destruction. */
    function setBeforeLogout(fn) {
        _beforeLogout = fn;
    }

    function _clearAll() {
        clearTimeout(idleTimer);
        clearInterval(tickInterval);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /** Cancel the warning and reset the full 20-minute idle timer. */
    function cancel() {
        warningActive.value = false;
        secondsLeft.value   = Math.floor(WARN_MS / 1000);
        clearInterval(tickInterval);
        _resetIdleTimer();
    }

    /** Start tracking. Call once on Game.vue mount. */
    function start() {
        ACTIVITY_EVENTS.forEach(e => window.addEventListener(e, _onActivity, { passive: true }));
        _resetIdleTimer();
    }

    /** Remove listeners and clear all timers. Call on Game.vue unmount. */
    function destroy() {
        _clearAll();
        ACTIVITY_EVENTS.forEach(e => window.removeEventListener(e, _onActivity));
    }

    return {
        warningActive,
        secondsLeft,
        countdown,
        start,
        cancel,
        destroy,
        setBeforeLogout,
    };
}
