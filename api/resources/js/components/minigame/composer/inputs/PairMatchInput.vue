<template>
    <div class="pmi-wrap">
        <div class="pmi-status">
            <span class="pmi-progress">{{ assignedCount }} / {{ slots.length }} ASSIGNED</span>
            <span class="pmi-hint-line" v-if="activeSlotId">Select a candidate for {{ activeSlotLabel }}...</span>
            <span class="pmi-timer" :class="{ 'pmi-timer--low': timeLeft <= 10 }">{{ timeLeft }}s</span>
        </div>

        <div class="pmi-columns">
            <div class="pmi-column">
                <div class="pmi-col-label">TARGET SLOTS</div>
                <button
                    v-for="slot in slots"
                    :key="slot.id"
                    type="button"
                    class="pmi-slot"
                    :class="{ 'pmi-slot--active': activeSlotId === slot.id, 'pmi-slot--filled': !!assignments[slot.id] }"
                    :disabled="paused"
                    @click="selectSlot(slot.id)"
                >
                    <span class="pmi-slot-label">{{ slot.label }}</span>
                    <span class="pmi-slot-target">TARGET: {{ slot.target }}</span>
                    <span class="pmi-slot-value">{{ assignments[slot.id] ? candidateLabel(assignments[slot.id]) : '— unassigned —' }}</span>
                </button>
            </div>

            <div class="pmi-column">
                <div class="pmi-col-label">CANDIDATES</div>
                <button
                    v-for="cand in candidates"
                    :key="cand.id"
                    type="button"
                    class="pmi-candidate"
                    :class="{ 'pmi-candidate--used': isUsed(cand.id) }"
                    :disabled="paused"
                    @click="selectCandidate(cand.id)"
                >{{ cand.label }}</button>
            </div>
        </div>

        <button
            type="button"
            class="pmi-submit"
            :disabled="assignedCount === 0 || paused"
            @click="submit"
        >[ SUBMIT SELECTION ]</button>
    </div>
</template>

<script setup>
/**
 * PairMatchInput — third input model, first for valueType: 'pairs'.
 *
 * Concept ported from ArchiveExtraction.vue's real/decoy matching loop
 * (plaintext/ciphertext pairs vs. decoy files, matched into a small number
 * of target cipher slots) — generalized as a fresh, standalone component.
 * Never imports ArchiveExtraction.vue or shares any state with it; it only
 * borrows the shape of the mechanic (a few real targets buried among more
 * decoys than targets) and the vocabulary (slots, candidates) already
 * established by that system, so this reads consistent with the rest of
 * the game.
 *
 * Click a slot to make it active, then click a candidate to assign it —
 * candidates already assigned elsewhere get reassigned, not duplicated.
 * Emits an array of { slotId, candidateId } on submit; has no idea which
 * assignments are actually correct, same as every other input model here.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    content: { type: Object,  required: true }, // { slots, candidates, timeLimitSec, ... }
    paused:  { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'timeout']);

const slots      = computed(() => props.content.slots);
const candidates = computed(() => props.content.candidates);

const assignments  = ref({}); // slotId -> candidateId
const activeSlotId = ref(null);

const activeSlotLabel = computed(() => {
    const slot = slots.value.find(s => s.id === activeSlotId.value);
    return slot ? slot.label : '';
});

function candidateLabel(id) {
    const cand = candidates.value.find(c => c.id === id);
    return cand ? cand.label : '';
}

function isUsed(candId) {
    return Object.values(assignments.value).includes(candId);
}

function selectSlot(slotId) {
    if (props.paused) return;
    activeSlotId.value = activeSlotId.value === slotId ? null : slotId;
}

function selectCandidate(candId) {
    if (props.paused) return;

    // A candidate can only serve one slot at a time — clear any prior
    // assignment for it before applying the new one.
    for (const [slotId, assignedId] of Object.entries(assignments.value)) {
        if (assignedId === candId) delete assignments.value[slotId];
    }

    if (activeSlotId.value) {
        assignments.value[activeSlotId.value] = candId;
        activeSlotId.value = null;
    }
}

const assignedCount = computed(() => Object.keys(assignments.value).length);

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
    const payload = Object.entries(assignments.value).map(([slotId, candidateId]) => ({ slotId, candidateId }));
    emit('submit', payload);
}
</script>

<style scoped>
.pmi-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
    font-family: 'JetBrains Mono', monospace;
}

.pmi-status {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 13px;
    color: rgba(0,255,100,0.6);
    letter-spacing: 0.08em;
}

.pmi-hint-line {
    color: rgba(0,200,240,0.7);
    font-size: 11px;
    flex: 1;
}

.pmi-timer {
    color: rgba(0,200,240,0.7);
    font-size: 15px;
    margin-left: auto;
}

.pmi-timer--low {
    color: rgba(255,80,80,0.85);
}

.pmi-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.pmi-column {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pmi-col-label {
    font-size: 10px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.18em;
    margin-bottom: 2px;
}

.pmi-slot {
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    background: rgba(0,255,100,0.03);
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.75);
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.1s;
    text-align: left;
}

.pmi-slot:hover:not(:disabled) {
    border-color: rgba(0,255,100,0.5);
}

.pmi-slot--active {
    border-color: #00d9ff;
    box-shadow: 0 0 0 1px rgba(0,217,255,0.4);
}

.pmi-slot--filled {
    border-color: rgba(0,255,100,0.5);
    background: rgba(0,255,100,0.07);
}

.pmi-slot-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.pmi-slot-target {
    font-size: 13px;
    color: #00d9ff;
    letter-spacing: 0.04em;
}

.pmi-slot-value {
    font-size: 13px;
    color: #00ff9d;
}

.pmi-slot--filled .pmi-slot-value {
    color: #00ff9d;
}

.pmi-candidate {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    background: rgba(0,255,100,0.03);
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.7);
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.1s;
    text-align: left;
}

.pmi-candidate:hover:not(:disabled) {
    border-color: rgba(0,255,100,0.5);
    background: rgba(0,255,100,0.06);
}

.pmi-candidate--used {
    border-color: rgba(0,200,240,0.4);
    color: rgba(0,200,240,0.8);
    opacity: 0.7;
}

.pmi-candidate:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.pmi-submit {
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

.pmi-submit:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.pmi-submit:disabled {
    border-color: rgba(0,255,100,0.1);
    color: rgba(0,255,100,0.25);
    cursor: not-allowed;
}
</style>
