<template>
    <Teleport to="body">
        <Transition name="dcw">
            <div v-if="visible" class="dcw-root">
                <button class="dcw-close" aria-label="Close" @click="emit('close')">✕</button>
                <DocChatPanel
                    :messages="messages"
                    :loading="loading"
                    :sending="sending"
                    :error="error"
                    :current-player-id="currentPlayerId"
                    :accent-color="accentColor"
                    :room-label="roomLabel"
                    @send="(body) => emit('send', body)"
                />
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
/**
 * DocChatWindow
 *
 * Fixed floating shell around DocChatPanel — opened from the FREQUENCY hotkey
 * in NavBar rather than buried inside a SPLICE browser page. Purely a
 * positioning/chrome wrapper; all chat state still lives in useDocChat() at
 * the Game.vue level and is passed straight through as props, per the
 * "components render + emit, composables own data" split used elsewhere.
 */
import DocChatPanel from './DocChatPanel.vue';

defineProps({
    visible:         { type: Boolean, default: false },
    messages:        { type: Array,   default: () => [] },
    loading:         { type: Boolean, default: false },
    sending:         { type: Boolean, default: false },
    error:           { type: String,  default: null },
    currentPlayerId: { type: String,  default: null },
    accentColor:     { type: String,  default: '#00FFC8' },
    roomLabel:       { type: String,  default: 'CHANNEL' },
});

const emit = defineEmits(['close', 'send']);
</script>

<style scoped>
.dcw-root {
    position: fixed;
    left: 12px;
    bottom: 56px;   /* clears the NavBar taskbar */
    width: 320px;
    height: 380px;
    z-index: 400;   /* above HUD/map chrome, below Watcher + minigame overlays */
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
}

.dcw-close {
    position: absolute;
    top: -22px;
    right: 0;
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.4);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    cursor: pointer;
    padding: 4px;
}
.dcw-close:hover { color: #fff; }

.dcw-enter-active,
.dcw-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.dcw-enter-from,
.dcw-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
