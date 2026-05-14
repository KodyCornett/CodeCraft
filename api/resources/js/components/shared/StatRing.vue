<template>
    <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="stat-ring">
        <!-- Background pentagon (max extent) -->
        <polygon
            :points="maxPoints"
            fill="none"
            stroke="#1A1A1A"
            stroke-width="1.5"
        />

        <!-- Axis lines from centre to each vertex -->
        <line
            v-for="(pt, i) in maxPointsArray"
            :key="`axis-${i}`"
            :x1="cx" :y1="cy"
            :x2="pt.x" :y2="pt.y"
            stroke="#1A1A1A"
            stroke-width="0.8"
        />

        <!-- Filled stat polygon -->
        <polygon
            :points="statPoints"
            fill="rgba(0,255,255,0.12)"
            stroke="#00FFFF"
            stroke-width="1.5"
            stroke-linejoin="round"
        />

        <!-- Vertex dots -->
        <circle
            v-for="(pt, i) in statPointsArray"
            :key="`dot-${i}`"
            :cx="pt.x" :cy="pt.y"
            r="2.5"
            fill="#00FFFF"
        />

        <!-- Labels -->
        <text
            v-for="(stat, i) in STATS"
            :key="`label-${i}`"
            :x="labelPos(i).x"
            :y="labelPos(i).y"
            :text-anchor="labelAnchor(i)"
            dominant-baseline="middle"
            class="stat-label"
        >{{ stat.abbr }}</text>

        <!-- Level values -->
        <text
            v-for="(stat, i) in STATS"
            :key="`val-${i}`"
            :x="labelPos(i).x"
            :y="labelPos(i).y + 10"
            :text-anchor="labelAnchor(i)"
            dominant-baseline="middle"
            class="stat-value"
        >{{ props.stats[stat.key] ?? 0 }}</text>
    </svg>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // { os, ram, cpu, storage, firewall } — current effective levels
    stats:  { type: Object, default: () => ({ os: 1, ram: 1, cpu: 1, storage: 1, firewall: 1 }) },
    maxVal: { type: Number, default: 10 },
    size:   { type: Number, default: 160 },
});

// Stat order clockwise from top
const STATS = [
    { key: 'os',       abbr: 'OS'  },
    { key: 'ram',      abbr: 'RAM' },
    { key: 'cpu',      abbr: 'CPU' },
    { key: 'storage',  abbr: 'STR' },
    { key: 'firewall', abbr: 'FW'  },
];

const cx = computed(() => props.size / 2);
const cy = computed(() => props.size / 2);
const R  = computed(() => props.size * 0.36); // max radius
const LR = computed(() => props.size * 0.47); // label radius (outside the ring)

// Pentagon vertex angle: start at -90° (top), step 72°
function angleForIndex(i) {
    return (Math.PI / 180) * (-90 + i * 72);
}

const maxPointsArray = computed(() =>
    STATS.map((_, i) => ({
        x: cx.value + R.value * Math.cos(angleForIndex(i)),
        y: cy.value + R.value * Math.sin(angleForIndex(i)),
    })),
);

const maxPoints = computed(() =>
    maxPointsArray.value.map(p => `${p.x},${p.y}`).join(' '),
);

const statPointsArray = computed(() =>
    STATS.map((stat, i) => {
        const ratio = Math.min(1, (props.stats[stat.key] ?? 0) / props.maxVal);
        return {
            x: cx.value + R.value * ratio * Math.cos(angleForIndex(i)),
            y: cy.value + R.value * ratio * Math.sin(angleForIndex(i)),
        };
    }),
);

const statPoints = computed(() =>
    statPointsArray.value.map(p => `${p.x},${p.y}`).join(' '),
);

function labelPos(i) {
    return {
        x: cx.value + LR.value * Math.cos(angleForIndex(i)),
        y: cy.value + LR.value * Math.sin(angleForIndex(i)),
    };
}

// Left-side stats get end anchor, right-side get start, top gets middle
function labelAnchor(i) {
    const angle = angleForIndex(i) * (180 / Math.PI);
    if (Math.abs(angle + 90) < 10) return 'middle'; // top
    return (angle > -90 && angle < 90) ? 'start' : 'end';
}
</script>

<style scoped>
.stat-ring {
    display: block;
    overflow: visible;
}

.stat-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    fill: #00FFFF;
    opacity: 0.8;
    letter-spacing: 0.05em;
}

.stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    fill: #00FFFF;
    opacity: 0.5;
}
</style>
