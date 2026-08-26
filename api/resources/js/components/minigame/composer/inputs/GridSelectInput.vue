<template>
    <div class="gsi-wrap">
        <div class="gsi-status">
            <span class="gsi-sum">SUM <strong>{{ selectedSum }}</strong></span>
            <span class="gsi-timer" :class="{ 'gsi-timer--low': timeLeft <= 10 }">{{ timeLeft }}s</span>
        </div>

        <div class="gsi-grid" :style="{ gridTemplateColumns: `repeat(${cols}, 1fr)` }">
            <button
                v-for="(cell, idx) in flatCells"
                :key="idx"
                type="button"
                class="gsi-cell"
                :class="{ 'gsi-cell--picked': picked.has(idx) }"
                :disabled="paused"
                @click="toggle(idx)"
            >{{ cell.value }}</button>
        </div>

        <button
            type="button"
            class="gsi-submit"
            :disabled="picked.size === 0 || paused"
            @click="submit"
        >[ SUBMIT SELECTION ]</button>
    </div>
</template>

<script setup>
/**
 * GridSelectInput — rule-agnostic input model for valueType: 'numeric'.
 *
 * Renders a grid of numeric cells the player can toggle on/off, and on
 * submit emits the plain array of currently-selected values. It has no
 * concept of "target", "win", or "correct" — that judgment belongs entirely
 * to whichever win rule the composer paired it with. This is what lets the
 * same component serve any future numeric win rule without changes here.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    rows:         { type: Number,  required: true },
    cols:         { type: Number,  required: true },
    values:       { type: Array,   required: true }, // rows x cols, numeric
    timeLimitSec: { type: Number,  default: 60 },
    paused:       { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'timeout']);

const flatCells = computed(() => {
    const out = [];
    for (let r = 0; r < props.rows; r++) {
        for (let c = 0; c < props.cols; c++) {
            out.push({ value: props.values[r][c] });
        }
    }
    return out;
});

const picked = ref(new Set());

function toggle(idx) {
    if (picked.value.has(idx)) picked.value.delete(idx);
    else picked.value.add(idx);
}

const pickedValues = computed(() =>
    flatCells.value.filter((_, i) => picked.value.has(i)).map(c => c.value)
);
const selectedSum = computed(() => pickedValues.value.reduce((s, v) => s + v, 0));

const timeLeft = ref(props.timeLimitSec);
let timerId = null;

onMounted(() => {
    timerId = setInterval(() => {
        if (props.paused) return;
        timeLeft.value -= 1;
        if (timeLeft.value <= 0) {
            clearInterval(timerId);
            emit('timeout');
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});

function submit() {
    emit('submit', [...pickedValues.value]);
}
</script>

<style scoped>
.gsi-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: 'JetBrains Mono', monospace;
}

.gsi-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 15px;
    color: rgba(0,255,100,0.7);
    letter-spacing: 0.1em;
}

.gsi-sum strong {
    color: #00ff9d;
    font-size: 18px;
}

.gsi-timer {
    color: rgba(0,200,240,0.7);
    font-size: 16px;
}

.gsi-timer--low {
    color: rgba(255,80,80,0.85);
}

.gsi-grid {
    display: grid;
    gap: 6px;
}

.gsi-cell {
    font-family: 'JetBrains Mono', monospace;
    font-size: 15px;
    font-weight: 600;
    aspect-ratio: 1;
    background: rgba(0,255,100,0.04);
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.75);
    cursor: pointer;
    transition: all 0.1s;
}

.gsi-cell:hover:not(:disabled) {
    border-color: rgba(0,255,100,0.5);
    background: rgba(0,255,100,0.08);
}

.gsi-cell--picked {
    border-color: #00ff9d;
    background: rgba(0,255,100,0.22);
    color: #00ff9d;
}

.gsi-cell:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.gsi-submit {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
    padding: 10px 0;
    cursor: pointer;
    transition: all 0.1s;
}

.gsi-submit:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.gsi-submit:disabled {
    border-color: rgba(0,255,100,0.1);
    color: rgba(0,255,100,0.25);
    cursor: not-allowed;
}
</style>
