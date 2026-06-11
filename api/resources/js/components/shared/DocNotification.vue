<template>
    <TransitionGroup name="dn-list" tag="div" class="dn-stack">
        <div
            v-for="note in queue"
            :key="note.id"
            class="dn-item"
            :style="{ '--doc-color': note.color }"
            @click="emit('dismiss', note.id)"
        >
            <span class="dn-dot" />
            <div class="dn-body">
                <span class="dn-handle">{{ note.handle }}</span>
                <span class="dn-sep">//</span>
                <span class="dn-msg">{{ note.message }}</span>
            </div>
            <span class="dn-close">✕</span>
        </div>
    </TransitionGroup>
</template>

<script setup>
defineProps({
    queue: { type: Array, default: () => [] },
});
const emit = defineEmits(['dismiss']);
</script>

<style scoped>
.dn-stack {
    position: fixed;
    top: 48px;
    right: 14px;
    z-index: 500;
    display: flex;
    flex-direction: column;
    gap: 6px;
    pointer-events: none;
    width: 320px;
}

.dn-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: rgba(2, 8, 6, 0.95);
    border: 1px solid color-mix(in srgb, var(--doc-color) 30%, transparent);
    border-left: 3px solid var(--doc-color);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    pointer-events: all;
    box-shadow: 0 2px 12px rgba(0,0,0,0.4);
    transition: opacity 0.15s;
}
.dn-item:hover { opacity: 0.8; }

.dn-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--doc-color);
    box-shadow: 0 0 6px var(--doc-color);
    flex-shrink: 0;
    animation: dn-pulse 1.5s ease-in-out infinite;
}
@keyframes dn-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

.dn-body { display: flex; align-items: baseline; gap: 6px; flex: 1; min-width: 0; }
.dn-handle {
    color: var(--doc-color);
    font-weight: 700;
    letter-spacing: 0.08em;
    flex-shrink: 0;
    font-size: 10px;
}
.dn-sep  { color: rgba(160,196,184,0.3); flex-shrink: 0; }
.dn-msg  {
    color: rgba(160,196,184,0.7);
    font-size: 9px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dn-close { color: rgba(160,196,184,0.2); font-size: 9px; flex-shrink: 0; }

/* Transition */
.dn-list-enter-active { transition: all 0.25s ease; }
.dn-list-leave-active { transition: all 0.2s ease; }
.dn-list-enter-from   { opacity: 0; transform: translateX(20px); }
.dn-list-leave-to     { opacity: 0; transform: translateX(20px); }
.dn-list-move         { transition: transform 0.2s ease; }
</style>
