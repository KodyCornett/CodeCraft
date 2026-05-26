import { ref, readonly } from 'vue';

/**
 * WebSocket stub — replaced by Laravel Reverb / Echo when real-time is wired up.
 * All composables that call useWebSocket() receive the same silent interface
 * so no call sites need to change when Reverb is installed.
 */
export function useWebSocket() {
    const handlers = new Map();

    return {
        connected: readonly(ref(false)),
        lastError:  readonly(ref(null)),
        send:       () => {},
        onMessage:  (action, handler) => {
            if (!handlers.has(action)) handlers.set(action, new Set());
            handlers.get(action).add(handler);
            return () => handlers.get(action)?.delete(handler);
        },
        disconnect: () => {},
    };
}
