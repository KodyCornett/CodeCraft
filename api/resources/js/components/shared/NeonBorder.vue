<template>
    <div class="neon-border-wrapper" :style="wrapperStyle">
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    color:   { type: String,  default: '#00FFFF' },
    opacity: { type: Number,  default: 0.4 },
    pulse:   { type: Boolean, default: false },
});

const wrapperStyle = computed(() => ({
    border: `1px solid ${props.color}${Math.round(props.opacity * 255).toString(16).padStart(2, '0')}`,
    boxShadow: `0 0 6px 0 ${props.color}${Math.round(props.opacity * 0.6 * 255).toString(16).padStart(2, '0')} inset`,
    animation: props.pulse ? 'neon-pulse 3s ease-in-out infinite' : 'none',
}));
</script>

<style scoped>
@keyframes neon-pulse {
    0%, 100% { box-shadow: 0 0 4px 0 v-bind(color) inset, 0 0 4px 0 v-bind(color); opacity: 0.7; }
    50%       { box-shadow: 0 0 14px 2px v-bind(color) inset, 0 0 14px 2px v-bind(color); opacity: 1; }
}
</style>
