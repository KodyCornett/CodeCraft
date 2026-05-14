<template>
    <span class="terminal-text" :class="sizeClass" :style="textStyle">
        <slot />
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    color:  { type: String,  default: '#00FFFF' },
    size:   { type: String,  default: 'md', validator: v => ['sm', 'md', 'lg', 'xl'].includes(v) },
    glitch: { type: Boolean, default: false },
});

const sizeClass = computed(() => ({
    'text-xs':   props.size === 'sm',
    'text-sm':   props.size === 'md',
    'text-base': props.size === 'lg',
    'text-2xl':  props.size === 'xl',
    'glitch-active': props.glitch,
}));

const textStyle = computed(() => ({
    color: props.color,
    fontFamily: "'JetBrains Mono', ui-monospace, monospace",
    letterSpacing: '0.05em',
}));
</script>

<style scoped>
.terminal-text {
    display: inline-block;
}

@keyframes glitch {
    0%  { transform: translateX(0); }
    2%  { transform: translateX(-3px); clip-path: inset(10% 0 80% 0); }
    4%  { transform: translateX(3px);  clip-path: inset(60% 0 20% 0); }
    6%  { transform: translateX(0);    clip-path: none; }
    100% { transform: translateX(0); }
}

.glitch-active {
    animation: glitch 6s steps(1) infinite;
}
</style>
