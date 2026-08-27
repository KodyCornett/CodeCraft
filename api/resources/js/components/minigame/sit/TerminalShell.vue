<template>
    <div class="tsh-wrap">
        <div ref="scrollEl" class="tsh-scrollback">
            <div
                v-for="(line, idx) in lines"
                :key="idx"
                class="tsh-line"
                :class="`tsh-line--${line.kind || 'output'}`"
            >{{ line.text }}</div>
        </div>
        <div class="tsh-inputrow">
            <span class="tsh-prompt">{{ promptLabel }}&gt;</span>
            <input
                ref="inputEl"
                v-model="typed"
                type="text"
                class="tsh-input"
                :disabled="paused"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                @keydown.enter="onEnter"
            />
        </div>
    </div>
</template>

<script setup>
/**
 * TerminalShell — the shared interaction surface for SIT (Splice Interface Terminal).
 * Pure presentation: a scrolling log of lines and a single input line.
 * No buttons, no cards, nothing clickable other than the text field
 * itself — every action happens by typing a line and hitting enter.
 *
 * Owns nothing about what commands mean. It just renders whatever `lines`
 * it's given (each { text, kind } — kind picks a color: 'input' for the
 * echoed command, 'output' for normal results, 'error' for command
 * failures, 'success' for a win) and emits `submit` with the raw typed
 * text when the player hits enter. SIT.vue owns the
 * actual filesystem/interpreter and decides what gets appended to `lines`
 * in response.
 */
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    lines:       { type: Array,   required: true }, // [{ text, kind }]
    promptLabel: { type: String,  default: '' },
    paused:      { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const typed    = ref('');
const scrollEl = ref(null);
const inputEl  = ref(null);

function onEnter() {
    if (props.paused) return;
    const line = typed.value;
    if (!line.trim()) return;
    emit('submit', line);
    typed.value = '';
}

watch(() => props.lines.length, async () => {
    await nextTick();
    if (scrollEl.value) scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
});

onMounted(() => {
    nextTick(() => inputEl.value?.focus());
});

defineExpose({ focus: () => inputEl.value?.focus() });
</script>

<style scoped>
.tsh-wrap {
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
}

.tsh-scrollback {
    height: 320px;
    overflow-y: auto;
    padding: 4px 2px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,255,100,0.2) transparent;
}
.tsh-scrollback::-webkit-scrollbar       { width: 4px; }
.tsh-scrollback::-webkit-scrollbar-thumb { background: rgba(0,255,100,0.2); }

.tsh-line {
    font-size: 12.5px;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
}

.tsh-line--input   { color: rgba(0,200,240,0.85); }
.tsh-line--output  { color: rgba(0,255,100,0.75); }
.tsh-line--error   { color: rgba(255,90,90,0.85); }
.tsh-line--success { color: #00ff9d; font-weight: 700; }

.tsh-inputrow {
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid rgba(0,255,100,0.18);
    padding-top: 8px;
}

.tsh-prompt {
    color: rgba(0,255,100,0.7);
    font-size: 13px;
    flex-shrink: 0;
}

.tsh-input {
    flex: 1;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    background: transparent;
    border: none;
    outline: none;
    color: #00ff9d;
    caret-color: #00ff9d;
}

.tsh-input::placeholder { color: rgba(0,255,100,0.4); }
.tsh-input:disabled     { opacity: 0.4; }
</style>
