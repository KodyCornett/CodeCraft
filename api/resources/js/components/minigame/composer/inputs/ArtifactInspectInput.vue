<template>
    <div class="aii-wrap">
        <div class="aii-status">
            <span class="aii-progress">{{ flagged.size }} FLAGGED</span>
            <span class="aii-timer" :class="{ 'aii-timer--low': timeLeft <= 10 }">{{ timeLeft }}s</span>
        </div>

        <div class="aii-feed">
            <div
                v-for="artifact in artifacts"
                :key="artifact.id"
                class="aii-card"
                :class="{ 'aii-card--flagged': flagged.has(artifact.id) }"
            >
                <pre class="aii-card-text">{{ artifact.text }}</pre>
                <button
                    type="button"
                    class="aii-flag-btn"
                    :disabled="paused"
                    @click="toggleFlag(artifact.id)"
                >{{ flagged.has(artifact.id) ? '[ FLAGGED ]' : '[ FLAG AS COMPROMISED ]' }}</button>
            </div>
        </div>

        <button
            type="button"
            class="aii-submit"
            :disabled="flagged.size === 0 || paused"
            @click="submit"
        >[ SUBMIT SELECTION ]</button>
    </div>
</template>

<script setup>
/**
 * ArtifactInspectInput — fourth input model, first for valueType: 'artifacts'.
 *
 * Displays a terminal-styled feed of generated artifacts (dataFeed.js's
 * certs / log lines) and lets the player flag whichever ones they believe
 * are compromised. Has no idea which flags are actually correct — it just
 * renders whatever `content.artifacts` it's handed and emits the flagged
 * id set on submit, same contract shape every other input model here
 * follows. All the real content (the fake certs/logs, which one is
 * actually flawed) comes from dataFeed.js via composeMinigame.js; this
 * component only knows how to display an artifact's `text` and track
 * flags — it never imports dataFeed.js directly.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    content: { type: Object,  required: true }, // { artifacts, timeLimitSec, ... }
    paused:  { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'timeout']);

const artifacts = computed(() => props.content.artifacts);
const flagged    = ref(new Set());

function toggleFlag(id) {
    if (props.paused) return;
    if (flagged.value.has(id)) flagged.value.delete(id);
    else flagged.value.add(id);
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
    emit('submit', [...flagged.value]);
}
</script>

<style scoped>
.aii-wrap {
    display: flex;
    flex-direction: column;
    gap: 14px;
    font-family: 'JetBrains Mono', monospace;
}

.aii-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: rgba(0,255,100,0.6);
    letter-spacing: 0.08em;
}

.aii-timer {
    color: rgba(0,200,240,0.7);
    font-size: 15px;
}

.aii-timer--low {
    color: rgba(255,80,80,0.85);
}

.aii-feed {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 360px;
    overflow-y: auto;
    padding-right: 4px;
}

.aii-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: rgba(0,255,100,0.03);
    border: 1px solid rgba(0,255,100,0.18);
    padding: 12px 14px;
}

.aii-card--flagged {
    border-color: rgba(255,140,60,0.6);
    background: rgba(255,140,60,0.06);
}

.aii-card-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    color: rgba(0,255,100,0.8);
    white-space: pre-wrap;
    margin: 0;
}

.aii-flag-btn {
    align-self: flex-start;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.1em;
    background: transparent;
    border: 1px solid rgba(255,140,60,0.35);
    color: rgba(255,140,60,0.75);
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.1s;
}

.aii-flag-btn:hover:not(:disabled) {
    border-color: rgba(255,140,60,0.9);
    background: rgba(255,140,60,0.1);
    color: rgba(255,140,60,1);
}

.aii-card--flagged .aii-flag-btn {
    border-color: rgba(255,140,60,0.9);
    background: rgba(255,140,60,0.12);
    color: rgba(255,140,60,1);
}

.aii-flag-btn:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}

.aii-submit {
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

.aii-submit:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}

.aii-submit:disabled {
    border-color: rgba(0,255,100,0.1);
    color: rgba(0,255,100,0.25);
    cursor: not-allowed;
}
</style>
