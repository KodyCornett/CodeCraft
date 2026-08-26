<template>
    <div class="spi-wrap">
        <div class="spi-status">
            <span class="spi-sum">SUM <strong>{{ selectedSum }}</strong></span>
            <span class="spi-progress">{{ pointer }} / {{ sequence.length }}</span>
            <span class="spi-timer" :class="{ 'spi-timer--low': timeLeft <= 10 }">{{ timeLeft }}s</span>
        </div>

        <div v-if="currentValue !== null" class="spi-current">
            <div class="spi-current-value">{{ currentValue }}</div>
            <div class="spi-current-actions">
                <button type="button" class="spi-btn spi-btn--take" :disabled="paused" @click="take">[ TAKE ]</button>
                <button type="button" class="spi-btn spi-btn--skip" :disabled="paused" @click="skip">[ SKIP ]</button>
            </div>
        </div>
        <div v-else class="spi-done">-- END OF FEED --</div>

        <div class="spi-taken">
            <span class="spi-taken-label">TAKEN</span>
            <span class="spi-taken-values">{{ takenValues.length ? takenValues.join(', ') : '(none yet)' }}</span>
        </div>

        <button
            type="button"
            class="spi-submit"
            :disabled="takenValues.length === 0 || paused"
            @click="submit"
        >[ SUBMIT SELECTION ]</button>
    </div>
</template>

<script setup>
/**
 * SequentialPickInput — second input model for valueType: 'numeric'.
 *
 * Different feel than GridSelectInput's spatial free-selection: values
 * arrive one at a time from a fixed one-way feed, and TAKE/SKIP is a
 * permanent decision — no going back to reconsider a skipped number, and
 * no seeing what's further down the feed. That's the actual difficulty
 * lever here (sequential commitment under uncertainty), distinct from
 * grid_select's difficulty lever (arithmetic search over a fully visible
 * space). Emits the same plain array-of-picked-values contract on submit,
 * which is the only reason it can plug into exact_sum / closest_under
 * (or any future numeric win rule) without either side changing.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    content: { type: Object,  required: true }, // { sequence, timeLimitSec, ... }
    paused:  { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'timeout']);

const sequence = computed(() => props.content.sequence);
const pointer  = ref(0);
const takenValues = ref([]);

const currentValue = computed(() =>
    pointer.value < sequence.value.length ? sequence.value[pointer.value] : null
);
const selectedSum = computed(() => takenValues.value.reduce((s, v) => s + v, 0));

function take() {
    if (currentValue.value === null || props.paused) return;
    takenValues.value.push(currentValue.value);
    pointer.value += 1;
}

function skip() {
    if (currentValue.value === null || props.paused) return;
    pointer.value += 1;
}

const timeLeft = ref(props.content.timeLimitSec);
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
    emit('submit', [...takenValues.value]);
}
</script>

<style scoped>
.spi-wrap {
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: 'JetBrains Mono', monospace;
}

.spi-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 15px;
    color: rgba(0,255,100,0.7);
    letter-spacing: 0.1em;
}

.spi-sum strong {
    color: #00ff9d;
    font-size: 18px;
}

.spi-progress {
    color: rgba(0,255,100,0.4);
    font-size: 12px;
}

.spi-timer {
    color: rgba(0,200,240,0.7);
    font-size: 16px;
}

.spi-timer--low {
    color: rgba(255,80,80,0.85);
}

.spi-current {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 28px 0;
    border: 1px solid rgba(0,255,100,0.15);
    background: rgba(0,255,100,0.03);
}

.spi-current-value {
    font-size: 42px;
    font-weight: 700;
    color: #00ff9d;
}

.spi-current-actions {
    display: flex;
    gap: 12px;
}

.spi-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.12em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
    padding: 10px 22px;
    cursor: pointer;
    transition: all 0.1s;
}

.spi-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.spi-btn--skip {
    border-color: rgba(255,140,60,0.3);
    color: rgba(255,140,60,0.6);
}

.spi-btn--skip:hover:not(:disabled) {
    border-color: rgba(255,140,60,0.7);
    color: rgba(255,140,60,0.9);
    background: rgba(255,140,60,0.08);
}

.spi-btn:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

.spi-done {
    text-align: center;
    padding: 28px 0;
    color: rgba(255,80,80,0.6);
    font-size: 14px;
    letter-spacing: 0.15em;
}

.spi-taken {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
}

.spi-taken-label {
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.15em;
}

.spi-taken-values {
    color: rgba(0,255,100,0.65);
    word-break: break-word;
}

.spi-submit {
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

.spi-submit:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.spi-submit:disabled {
    border-color: rgba(0,255,100,0.1);
    color: rgba(0,255,100,0.25);
    cursor: not-allowed;
}
</style>
