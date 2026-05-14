<template>
    <div class="trace-bar-wrapper">
        <div class="trace-label trace-label--left">[YOUR_TRACE]</div>

        <div class="trace-track">
            <!-- Player fill (cyan, grows from left) -->
            <div
                class="trace-fill trace-fill--player"
                :style="{ width: playerPct + '%' }"
            />
            <!-- Opponent fill (magenta, grows from right) -->
            <div
                class="trace-fill trace-fill--opponent"
                :style="{ width: opponentPct + '%' }"
            />
            <!-- Centre divider -->
            <div class="trace-divider" />
            <!-- Numeric readout -->
            <div class="trace-readout">
                <span class="trace-pct trace-pct--player">{{ Math.round(playerPct) }}%</span>
                <span class="trace-sep">//</span>
                <span class="trace-pct trace-pct--opponent">{{ Math.round(opponentPct) }}%</span>
            </div>
        </div>

        <div class="trace-label trace-label--right">[ENEMY_TRACE]</div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    // 0.0 = opponent winning, 0.5 = even, 1.0 = player winning
    traceValue: { type: Number, default: 0.5 },
});

const playerPct   = computed(() => props.traceValue * 100);
const opponentPct = computed(() => (1 - props.traceValue) * 100);
</script>

<style scoped>
.trace-bar-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 8px 16px;
    background: rgba(0, 0, 0, 0.6);
    border-bottom: 1px solid rgba(0, 255, 255, 0.2);
    flex-shrink: 0;
}

.trace-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.06em;
    white-space: nowrap;
    flex-shrink: 0;
}

.trace-label--left  { color: #00FFFF; }
.trace-label--right { color: #FF00CC; }

.trace-track {
    position: relative;
    flex: 1;
    height: 10px;
    background: #111111;
    border: 1px solid rgba(255, 255, 255, 0.08);
    overflow: hidden;
}

.trace-fill {
    position: absolute;
    top: 0;
    height: 100%;
    transition: width 0.4s ease;
}

.trace-fill--player {
    left: 0;
    background: linear-gradient(to right, rgba(0, 255, 255, 0.6), rgba(0, 255, 255, 0.25));
    box-shadow: 0 0 8px rgba(0, 255, 255, 0.4);
}

.trace-fill--opponent {
    right: 0;
    background: linear-gradient(to left, rgba(255, 0, 204, 0.6), rgba(255, 0, 204, 0.25));
    box-shadow: 0 0 8px rgba(255, 0, 204, 0.4);
}

.trace-divider {
    position: absolute;
    left: 50%;
    top: 0;
    width: 1px;
    height: 100%;
    background: rgba(255, 255, 255, 0.2);
    transform: translateX(-50%);
}

.trace-readout {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    pointer-events: none;
}

.trace-pct {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.trace-pct--player   { color: #00FFFF; text-shadow: 0 0 6px rgba(0,255,255,0.8); }
.trace-pct--opponent { color: #FF00CC; text-shadow: 0 0 6px rgba(255,0,204,0.8); }
.trace-sep           { color: rgba(255,255,255,0.3); font-family: 'JetBrains Mono', monospace; font-size: 8px; }
</style>
