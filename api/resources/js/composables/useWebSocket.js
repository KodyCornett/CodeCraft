import { ref, readonly } from 'vue';

const ENGINE_PORT    = import.meta.env.VITE_ENGINE_PORT    ?? 8085;
const ENGINE_ENABLED = import.meta.env.VITE_ENGINE_ENABLED === 'true';
const WS_URL         = `ws://localhost:${ENGINE_PORT}/ws/game`;

const RECONNECT_DELAY_MS  = 5000;
const MAX_RECONNECT_DELAY = 60000;

// ── Stub returned when the engine flag is off ──────────────────────────────
// All the same API surface — connect/send/onMessage/disconnect — but silent.
function createStub() {
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

export function useWebSocket() {
    // Engine not enabled — return a silent stub so Game.vue wires up normally
    // but no WS connection is attempted. Set VITE_ENGINE_ENABLED=true in .env
    // when the real-time engine server is running.
    if (!ENGINE_ENABLED) return createStub();

    const connected       = ref(false);
    const lastError       = ref(null);

    let ws               = null;
    let reconnectTimer   = null;
    let reconnectDelay   = RECONNECT_DELAY_MS;
    let destroyed        = false;
    const handlers       = new Map();

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

            const all = handlers.get('*');
            if (all) all.forEach(fn => fn(msg));
        };

        ws.onerror = () => {
            lastError.value = 'WebSocket error';
        };

        ws.onclose = () => {
            connected.value = false;
            if (!destroyed) {
                if (reconnectDelay <= RECONNECT_DELAY_MS) {
                    console.info('[useWebSocket] Engine not reachable — set VITE_ENGINE_ENABLED=true when ready');
                }
                scheduleReconnect();
            }
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
