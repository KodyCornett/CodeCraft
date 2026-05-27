import { ref, readonly } from 'vue';

/**
 * Thin wrapper around window.Echo (Laravel Echo → Reverb).
 *
 * Provides the same interface the rest of the app expects so no call sites
 * change. `connected` is now live — it reflects the real Pusher/Reverb socket
 * state. `joinChannel` is a convenience helper for public channels.
 *
 * Private and presence channels (combat, packet-hijack, node presence, bounty
 * board) are managed directly by their own composables via window.Echo, not
 * through this wrapper.
 *
 * The game-level events registered here (PLAYER_MOVED, PING_EXPIRED,
 * NODE_STATE_CHANGED) will remain silent until the server-side broadcast events
 * for those actions are implemented.
 */
export function useWebSocket() {
    const handlers  = new Map();
    const connected = ref(false);
    const lastError = ref(null);

    // Reflect Pusher/Reverb socket connection state
    if (window.Echo?.connector?.pusher) {
        const pusher = window.Echo.connector.pusher;

        pusher.connection.bind('connected',    () => { connected.value = true;  lastError.value = null; });
        pusher.connection.bind('disconnected', () => { connected.value = false; });
        pusher.connection.bind('error',        (err) => { lastError.value = err; });

        // Reflect current state immediately if already connected
        if (pusher.connection.state === 'connected') connected.value = true;
    }

    /**
     * Subscribe to a named game event arriving on any channel this wrapper
     * manages. Returns an unsubscribe function.
     */
    function onMessage(action, handler) {
        if (!handlers.has(action)) handlers.set(action, new Set());
        handlers.get(action).add(handler);
        return () => handlers.get(action)?.delete(handler);
    }

    /**
     * Dispatch a game event to all registered handlers for that action.
     * Called internally by channel listeners.
     */
    function dispatch(action, payload) {
        handlers.get(action)?.forEach(fn => fn(payload));
    }

    /**
     * Join a public Echo channel and route its events through onMessage().
     * Returns the Echo channel instance.
     */
    function joinChannel(name) {
        if (!window.Echo) return null;
        const channel = window.Echo.channel(name);
        channel.listenToAll((event, data) => dispatch(event.replace(/^\./, ''), data));
        return channel;
    }

    function disconnect() {
        // Individual composables manage their own channel subscriptions.
        // Leaving them here would break other composables that hold their own refs.
    }

    // send() is intentionally a no-op — all mutations go through HTTP endpoints,
    // not raw WebSocket frames.
    function send() {}

    return {
        connected: readonly(connected),
        lastError:  readonly(lastError),
        send,
        onMessage,
        joinChannel,
        disconnect,
    };
}
