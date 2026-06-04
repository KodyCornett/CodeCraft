<template>
    <Transition name="cmd-hit">
        <div v-if="visible" class="cmd-hit-wrap" @click="dismiss">
            <div class="cmd-hit-bar">
                <span class="cmd-hit-prefix">// SPLICE ALERT</span>
                <span class="cmd-hit-body">{{ label }}</span>
                <span class="cmd-hit-dismiss">[ × ]</span>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    commandName: { type: String, required: true },
    effect:      { type: Object, default: () => ({}) },
});

defineEmits(['done']);
const emit = defineEmits(['done']);

const visible = ref(true);
let timer = null;

// Build a human-readable one-liner from the effect payload
const label = computed(() => {
    const name = props.commandName;
    const e    = props.effect ?? {};

    if (e.uplink_drain)         return `${name} — −${e.uplink_drain} Uplink`;
    if (e.ss_damage)            return `${name} — −${e.ss_damage} SS damage`;
    if (e.os_reduction)         return `${name} — −${e.os_reduction} OS for ${e.moves ?? '?'} moves`;
if (e.moves && name === 'Buffer Overflow') return `${name} — 1 command disabled for ${e.moves} moves`;
    if (e.moves && name === 'RootKit') return `${name} — all commands locked for ${e.moves} moves`;
    return `${name} — trap triggered`;
});

function dismiss() {
    visible.value = false;
    clearTimeout(timer);
    setTimeout(() => emit('done'), 350);
}

onMounted(() => {
    timer = setTimeout(dismiss, 3500);
});

onUnmounted(() => clearTimeout(timer));
</script>

<style scoped>
.cmd-hit-wrap {
    position: absolute;
    bottom: 60px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 70;
    cursor: pointer;
    pointer-events: auto;
}

.cmd-hit-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
    background: rgba(5, 5, 8, 0.93);
    border: 1px solid rgba(255, 51, 51, 0.55);
    border-left: 3px solid #FF3333;
    white-space: nowrap;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.05em;
    box-shadow:
        0 0 12px rgba(255, 51, 51, 0.25),
        inset 0 0 20px rgba(255, 51, 51, 0.04);
}

.cmd-hit-prefix {
    color: #FF3333;
    opacity: 0.8;
    font-size: 10px;
    flex-shrink: 0;
}

.cmd-hit-body {
    color: #FFB300;
    text-shadow: 0 0 8px rgba(255, 179, 0, 0.5);
}

.cmd-hit-dismiss {
    color: rgba(255, 255, 255, 0.25);
    font-size: 10px;
    margin-left: 6px;
    flex-shrink: 0;
}

/* Transition */
.cmd-hit-enter-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.cmd-hit-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.cmd-hit-enter-from {
    opacity: 0;
    transform: translateX(-50%) translateY(8px);
}
.cmd-hit-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-6px);
}
</style>
