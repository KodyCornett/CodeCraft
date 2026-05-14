<template>
    <span class="ss-bar" :style="{ fontFamily: '\'JetBrains Mono\', monospace' }">
        <span class="ss-bracket">[</span>
        <span class="ss-filled" :style="filledStyle">{{ filledChars }}</span>
        <span class="ss-empty"  :style="emptyStyle">{{ emptyChars }}</span>
        <span class="ss-bracket">]</span>
        <span class="ss-pct" :style="pctStyle" :class="{ 'limp-flicker': isLimping }">
            &nbsp;{{ pct }}%
        </span>
    </span>
</template>

<script setup>
import { computed } from 'vue';

const BAR_WIDTH = 16;

const props = defineProps({
    current:   { type: Number, default: 100 },
    max:       { type: Number, default: 100 },
    isLimping: { type: Boolean, default: false },
});

const pct = computed(() =>
    props.max > 0 ? Math.round((props.current / props.max) * 100) : 0,
);

const filledCount = computed(() =>
    Math.round((props.current / props.max) * BAR_WIDTH),
);

const filledChars = computed(() => '█'.repeat(filledCount.value));
const emptyChars  = computed(() => '░'.repeat(BAR_WIDTH - filledCount.value));

// Green > 60%, amber 30–60%, red < 30%
const barColor = computed(() => {
    if (pct.value > 60) return '#00FF88';
    if (pct.value > 30) return '#FFB300';
    return '#FF3333';
});

const filledStyle = computed(() => ({
    color: barColor.value,
    transition: 'color 0.6s ease',
}));

const emptyStyle = computed(() => ({
    color: '#333333',
}));

const pctStyle = computed(() => ({
    color: barColor.value,
    transition: 'color 0.6s ease',
}));
</script>

<style scoped>
.ss-bar {
    display: inline-flex;
    align-items: center;
    font-size: inherit;
    letter-spacing: 0;
}

.ss-bracket {
    color: #00FFFF;
    opacity: 0.6;
}

@keyframes limp-flicker {
    0%   { opacity: 1;   }
    15%  { opacity: 0.1; }
    17%  { opacity: 0.9; }
    60%  { opacity: 0.9; }
    62%  { opacity: 0.15;}
    64%  { opacity: 1;   }
    100% { opacity: 1;   }
}

.limp-flicker {
    animation: limp-flicker 1.6s steps(1) infinite;
}
</style>
