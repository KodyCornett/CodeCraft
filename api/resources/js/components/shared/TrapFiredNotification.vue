<template>
    <Transition name="trap-fired">
        <div v-if="visible" class="trap-fired-wrap" @click="dismiss">
            <div class="trap-fired-bar">
                <span class="trap-fired-prefix">// TRAP DETONATED</span>
                <span class="trap-fired-body">{{ label }}</span>
                <span class="trap-fired-dismiss">[ × ]</span>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    commandName:  { type: String, required: true },
    victimHandle: { type: String, required: true },
});

const emit = defineEmits(['done']);

const visible = ref(true);
let timer = null;

const label = computed(() =>
    `${props.commandName} — ${props.victimHandle} neutralised`
);

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
.trap-fired-wrap {
    position: absolute;
    bottom: 60px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 70;
    cursor: pointer;
    pointer-events: auto;
}

.trap-fired-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 14px;
    background: rgba(5, 5, 8, 0.93);
    border: 1px solid rgba(0, 255, 180, 0.45);
    border-left: 3px solid #00FFB4;
    white-space: nowrap;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.05em;
    box-shadow:
        0 0 12px rgba(0, 255, 180, 0.2),
        inset 0 0 20px rgba(0, 255, 180, 0.03);
}

.trap-fired-prefix {
    color: #00FFB4;
    opacity: 0.8;
    font-size: 10px;
    flex-shrink: 0;
}

.trap-fired-body {
    color: #E0FFD0;
    text-shadow: 0 0 8px rgba(0, 255, 180, 0.4);
}

.trap-fired-dismiss {
    color: rgba(255, 255, 255, 0.25);
    font-size: 10px;
    margin-left: 6px;
    flex-shrink: 0;
}

/* Transition */
.trap-fired-enter-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.trap-fired-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.trap-fired-enter-from {
    opacity: 0;
    transform: translateX(-50%) translateY(8px);
}
.trap-fired-leave-to {
    opacity: 0;
    transform: translateX(-50%) translateY(-6px);
}
</style>
