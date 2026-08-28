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
            <div class="tsh-inputwrap">
                <ul v-if="suggestions.length" class="tsh-suggestions">
                    <li
                        v-for="(s, idx) in suggestions"
                        :key="s"
                        class="tsh-suggestion"
                        :class="{ 'tsh-suggestion--active': idx === highlightedIndex }"
                        @mousedown.prevent="applySuggestion(s)"
                    >{{ s }}</li>
                </ul>
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
                    @keydown.tab.prevent="onTab"
                    @keydown.down.prevent="onArrowDown"
                    @keydown.up.prevent="onArrowUp"
                    @keydown.esc="onEscape"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * TerminalShell — the shared interaction surface for SIT (Splice Interface Terminal).
 * Pure presentation: a scrolling log of lines and a single input line.
 * No buttons or cards for the game content itself — every action still
 * happens by typing a line and hitting enter.
 *
 * The one assist on top of that: an autocomplete dropdown, navigable with
 * Up/Down and accepted with Tab (or a click). It exists purely for
 * accessibility — a player who's never touched a real terminal shouldn't
 * need to already know `ls`/`cd`/`cat` syntax by heart to play. This
 * component stays filesystem-agnostic though: it has no idea what a
 * directory is. The parent recomputes `suggestions` (a flat list of full
 * replacement values for whatever token is currently being typed) on
 * every `input` event and hands it back down as a prop; TerminalShell
 * just renders that list and knows how to swap the last token with
 * whichever entry gets picked.
 *
 * Emits `lines` unchanged (each { text, kind } — kind picks a color:
 * 'input' for the echoed command, 'output' for normal results, 'error'
 * for failures, 'success' for a win) and `submit` with the raw typed text
 * on enter, same contract as before. SIT.vue still owns the actual
 * filesystem/interpreter.
 */
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    lines:       { type: Array,   required: true }, // [{ text, kind }]
    promptLabel: { type: String,  default: '' },
    paused:      { type: Boolean, default: false },
    suggestions: { type: Array,   default: () => [] }, // full replacement values for the last token
});

const emit = defineEmits(['submit', 'input']);

const typed           = ref('');
const scrollEl         = ref(null);
const inputEl          = ref(null);
const highlightedIndex = ref(-1);

function onEnter() {
    if (props.paused) return;
    const line = typed.value;
    if (!line.trim()) return;
    emit('submit', line);
    typed.value = '';
    highlightedIndex.value = -1;
}

function applySuggestion(replacement) {
    const trimmed  = typed.value.replace(/\s+$/, '');
    const spaceIdx = trimmed.lastIndexOf(' ');
    const prefix   = spaceIdx >= 0 ? trimmed.slice(0, spaceIdx + 1) : '';
    typed.value = prefix + replacement + ' ';
    nextTick(() => inputEl.value?.focus());
}

function onTab() {
    if (props.paused || !props.suggestions.length) return;
    const idx = highlightedIndex.value >= 0 ? highlightedIndex.value : 0;
    applySuggestion(props.suggestions[idx]);
}

function onArrowDown() {
    if (!props.suggestions.length) return;
    highlightedIndex.value = (highlightedIndex.value + 1) % props.suggestions.length;
}

function onArrowUp() {
    if (!props.suggestions.length) return;
    highlightedIndex.value = highlightedIndex.value <= 0
        ? props.suggestions.length - 1
        : highlightedIndex.value - 1;
}

function onEscape() {
    highlightedIndex.value = -1;
}

// Every keystroke change is reported up so the parent (which owns the
// filesystem) can recompute what's completable right now.
watch(typed, (val) => {
    emit('input', val);
});

// New candidate list arrived — pre-highlight the first entry so Tab works
// immediately without needing an Up/Down press first.
watch(() => props.suggestions, (list) => {
    highlightedIndex.value = list.length ? 0 : -1;
});

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
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
}

.tsh-scrollback {
    flex: 1;
    min-height: 0;
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

.tsh-inputwrap {
    position: relative;
    flex: 1;
}

.tsh-input {
    width: 100%;
    box-sizing: border-box;
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

.tsh-suggestions {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    min-width: 180px;
    max-width: 420px;
    max-height: 160px;
    overflow-y: auto;
    margin: 0;
    padding: 4px 0;
    list-style: none;
    background: #050807;
    border: 1px solid rgba(0,255,100,0.3);
    box-shadow: 0 4px 20px rgba(0,0,0,0.6);
    z-index: 10;
}

.tsh-suggestion {
    padding: 5px 12px;
    font-size: 12.5px;
    color: rgba(0,255,100,0.65);
    cursor: pointer;
    white-space: nowrap;
}

.tsh-suggestion--active {
    background: rgba(0,255,100,0.1);
    color: #00ff9d;
}
</style>
