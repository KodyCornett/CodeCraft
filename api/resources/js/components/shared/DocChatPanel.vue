<template>
    <div class="dcp-root" :style="{ '--doc-accent': accentColor }">

        <div class="dcp-header">
            <span class="dcp-dot" />
            <span class="dcp-title">COMMS // {{ roomLabel }}</span>
        </div>

        <div ref="logRef" class="dcp-log">
            <div v-if="loading" class="dcp-empty">CONNECTING…</div>
            <div v-else-if="messages.length === 0" class="dcp-empty">NO TRANSMISSIONS YET — SAY SOMETHING.</div>
            <div
                v-for="m in messages"
                :key="m.id"
                class="dcp-line"
                :class="{ 'dcp-line--self': m.player_id === currentPlayerId }"
            >
                <span class="dcp-time">{{ formatTime(m.created_at) }}</span>
                <span class="dcp-handle" :class="{ 'dcp-handle--self': m.player_id === currentPlayerId }">{{ m.handle }}</span>
                <span class="dcp-sep">›</span>
                <span class="dcp-body">{{ m.body }}</span>
            </div>
        </div>

        <div v-if="error" class="dcp-error">{{ error }}</div>

        <form class="dcp-input-row" @submit.prevent="onSend">
            <input
                v-model="draft"
                class="dcp-input"
                type="text"
                maxlength="240"
                placeholder="Transmit…"
                :disabled="sending"
            />
            <button class="dcp-send" type="submit" :disabled="sending || !draft.trim()">SEND</button>
        </form>

    </div>
</template>

<script setup>
/**
 * DocChatPanel
 *
 * Presentational only — renders whatever message list it's given and emits
 * 'send' on submit. Owns no API/Echo calls itself; the page component (e.g.
 * CyberDocStore.vue) wires this up to useDocChat() and passes the results
 * down as props, per the "components render + emit, composables own data"
 * split used throughout the rest of the app.
 */
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    messages:        { type: Array,   default: () => [] },
    loading:         { type: Boolean, default: false },
    sending:         { type: Boolean, default: false },
    error:           { type: String,  default: null },
    currentPlayerId: { type: String,  default: null },
    accentColor:     { type: String,  default: '#00FFC8' },
    roomLabel:       { type: String,  default: 'CHANNEL' },
});

const emit = defineEmits(['send']);

const draft  = ref('');
const logRef = ref(null);

function onSend() {
    const body = draft.value.trim();
    if (!body) return;
    emit('send', body);
    draft.value = '';
}

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Auto-scroll to the newest message whenever the log grows.
watch(() => props.messages.length, async () => {
    await nextTick();
    if (logRef.value) logRef.value.scrollTop = logRef.value.scrollHeight;
});
</script>

<style scoped>
.dcp-root {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    font-family: 'JetBrains Mono', monospace;
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid var(--doc-accent-dim, color-mix(in srgb, var(--doc-accent) 20%, transparent));
}

.dcp-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid color-mix(in srgb, var(--doc-accent) 15%, transparent);
    flex-shrink: 0;
}

.dcp-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--doc-accent);
    box-shadow: 0 0 6px var(--doc-accent);
    flex-shrink: 0;
    animation: dcp-pulse 1.5s ease-in-out infinite;
}
@keyframes dcp-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.dcp-title {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: var(--doc-accent);
    text-shadow: 0 0 8px color-mix(in srgb, var(--doc-accent) 40%, transparent);
}

.dcp-log {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    scrollbar-width: thin;
    scrollbar-color: color-mix(in srgb, var(--doc-accent) 20%, transparent) transparent;
}
.dcp-log::-webkit-scrollbar       { width: 3px; }
.dcp-log::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--doc-accent) 20%, transparent); }

.dcp-empty {
    margin: auto;
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.25);
    text-align: center;
}

.dcp-line {
    font-size: 10px;
    line-height: 1.5;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: baseline;
}

.dcp-time  { color: rgba(255, 255, 255, 0.2); font-size: 8px; flex-shrink: 0; }
.dcp-handle {
    color: var(--doc-accent);
    letter-spacing: 0.06em;
    font-weight: 700;
    flex-shrink: 0;
}
.dcp-handle--self { color: #00FFC8; }
.dcp-sep  { color: rgba(255, 255, 255, 0.15); flex-shrink: 0; }
.dcp-body { color: rgba(255, 255, 255, 0.78); word-break: break-word; }

.dcp-error {
    padding: 5px 12px;
    font-size: 9px;
    color: #ff6666;
    border-top: 1px solid rgba(255, 51, 51, 0.15);
    flex-shrink: 0;
}

.dcp-input-row {
    display: flex;
    gap: 6px;
    padding: 8px;
    border-top: 1px solid color-mix(in srgb, var(--doc-accent) 15%, transparent);
    flex-shrink: 0;
}

.dcp-input {
    flex: 1;
    min-width: 0;
    background: rgba(0, 0, 0, 0.35);
    border: 1px solid color-mix(in srgb, var(--doc-accent) 20%, transparent);
    color: rgba(255, 255, 255, 0.85);
    font-family: inherit;
    font-size: 10px;
    padding: 6px 8px;
}
.dcp-input:focus  { outline: none; border-color: var(--doc-accent); }
.dcp-input:disabled { opacity: 0.5; }

.dcp-send {
    background: transparent;
    border: 1px solid var(--doc-accent);
    color: var(--doc-accent);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 0 14px;
    cursor: pointer;
    transition: background 0.12s;
    flex-shrink: 0;
}
.dcp-send:hover:not(:disabled) { background: color-mix(in srgb, var(--doc-accent) 12%, transparent); }
.dcp-send:disabled { opacity: 0.3; cursor: not-allowed; }
</style>
