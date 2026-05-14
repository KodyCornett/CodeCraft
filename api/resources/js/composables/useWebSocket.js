import { ref, readonly } from 'vue';

const ENGINE_PORT = import.meta.env.VITE_ENGINE_PORT ?? 8085;
const WS_URL      = `ws://localhost:${ENGINE_PORT}/ws/game`;

const RECONNECT_DELAY_MS  = 3000;
const MAX_RECONNECT_DELAY = 30000;

export function useWebSocket() {
    const connected       = ref(false);
    const lastError       = ref(null);

    let ws               = null;
    let reconnectTimer   = null;
    let reconnectDelay   = RECONNECT_DELAY_MS;
    let destroyed        = false;
    const handlers       = new Map(); // action → Set<handler>

    function connect() {
        if (destroyed) return;
        try {
            ws = new WebSocket(WS_URL);
        } catch (e) {
            console.warn('[useWebSocket] Could not create WebSocket:', e.message);
            scheduleReconnect();
            return;
        }

        ws.onopen = () => {
            connected.value = true;
            lastError.value  = null;
            reconnectDelay   = RECONNECT_DELAY_MS;
            console.info('[useWebSocket] Connected to engine');
        };

        ws.onmessage = (event) => {
            let msg;
            try { msg = JSON.parse(event.data); } catch { return; }

            const action = msg.action ?? msg.type;
            if (!action) return;

            const set = handlers.get(action);
            if (set) set.forEach(fn => fn(msg));

            // Also fire wildcard handlers
            const all = handlers.get('*');
            if (all) all.forEach(fn => fn(msg));
        };

        ws.onerror = (e) => {
            lastError.value = 'WebSocket error';
        };

        ws.onclose = () => {
            connected.value = false;
            if (!destroyed) scheduleReconnect();
        };
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            reconnectDelay = Math.min(reconnectDelay * 1.5, MAX_RECONNECT_DELAY);
            connect();
        }, reconnectDelay);
    }

    /**
     * Send an action + payload to the engine.
     * Silently drops if not connected.
     */
    function send(action, payload = {}) {
        if (ws?.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action, ...payload }));
        } else {
            console.warn('[useWebSocket] send() called while disconnected — dropped:', action);
        }
    }

    /**
     * Register a handler for a specific action name (or '*' for all).
     * Returns an unsubscribe function.
     */
    function onMessage(action, handler) {
        if (!handlers.has(action)) handlers.set(action, new Set());
        handlers.get(action).add(handler);
        return () => handlers.get(action)?.delete(handler);
    }

    function disconnect() {
        destroyed = true;
        if (reconnectTimer) { clearTimeout(reconnectTimer); reconnectTimer = null; }
        ws?.close();
    }

    // Start connecting immediately
    connect();

    return {
        connected: readonly(connected),
        lastError: readonly(lastError),
        send,
        onMessage,
        disconnect,
    };
}
