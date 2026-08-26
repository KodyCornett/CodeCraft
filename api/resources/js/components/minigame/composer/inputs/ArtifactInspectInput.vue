<template>
    <div class="aii-wrap">
        <div class="aii-status">
            <span class="aii-progress">{{ revealed.size }} / {{ artifacts.length }} REVEALED · {{ flagged.size }} FLAGGED</span>
            <span class="aii-timer" :class="{ 'aii-timer--low': timeLeft <= 10 }">{{ timeLeft }}s</span>
        </div>

        <div class="aii-terminal">
            <div class="aii-terminal-hint">
                <span class="aii-palette-label">AVAILABLE</span>
                <span v-for="cmd in commands" :key="cmd.key" class="aii-hint-cmd">{{ cmd.label }}</span>
            </div>
            <div class="aii-terminal-line">
                <span class="aii-prompt">&gt;</span>
                <input
                    ref="commandInputEl"
                    v-model="commandInput"
                    type="text"
                    class="aii-terminal-input"
                    placeholder="type a command..."
                    :disabled="paused"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    @keydown.enter="runTypedCommand"
                />
            </div>
            <div v-if="activeCommandKey" class="aii-active-cmd">
                ACTIVE: {{ activeCommandLabel }} — select a locked target to run it against.
            </div>
        </div>

        <div class="aii-log" v-if="log.length">
            <div v-for="(line, idx) in log" :key="idx" class="aii-log-line">{{ line }}</div>
        </div>

        <div class="aii-feed">
            <div
                v-for="artifact in artifacts"
                :key="artifact.id"
                class="aii-card"
                :class="{ 'aii-card--flagged': flagged.has(artifact.id), 'aii-card--locked': !revealed.has(artifact.id) }"
            >
                <template v-if="revealed.has(artifact.id)">
                    <pre class="aii-card-text">{{ artifact.text }}</pre>
                    <button
                        type="button"
                        class="aii-flag-btn"
                        :disabled="paused"
                        @click="toggleFlag(artifact.id)"
                    >{{ flagged.has(artifact.id) ? '[ FLAGGED ]' : '[ FLAG AS COMPROMISED ]' }}</button>
                </template>
                <template v-else>
                    <div class="aii-locked-label">{{ artifact.id.toUpperCase() }} — [ ENCRYPTED ]</div>
                    <button
                        type="button"
                        class="aii-run-btn"
                        :disabled="!activeCommandKey || paused"
                        @click="attemptReveal(artifact.id)"
                    >[ RUN SELECTED COMMAND ]</button>
                </template>
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
 * V2: artifacts start LOCKED. The player runs a command against a locked
 * target — the right command for that target's kind reveals it, the wrong
 * one (or a decoy command like whois/ping) burns a small time penalty and
 * reveals nothing. Only once revealed can a card be flagged.
 *
 * V3: command entry is now a typed terminal line instead of a row of
 * clickable buttons — a player types the command's name (matched loosely
 * against `content.commands`, case-insensitive, either the short key like
 * "openssl" or the full displayed syntax) and hits Enter to arm it, then
 * clicks a locked card to run the armed command against that target. The
 * available syntax is still listed above the input as reference text (not
 * buttons) so the player knows what to type without having to guess or
 * memorize real tool syntax. This is what actually makes the "commands,
 * not rings" feel land — the mechanic underneath (arm a command, run it
 * against a target, right command for the right kind reveals it) is
 * unchanged from V2.
 *
 * Still has no idea which flags are correct, and still emits the same
 * flagged-id-array contract on submit as before. Never imports
 * dataFeed.js directly; commandPalette.js is the only new dependency, and
 * it only knows about the generic `kind` string dataFeed.js stamps on
 * every artifact.
 *
 * Command palette comes from `content.commands` — the per-instance subset
 * composeMinigame.js chose via commandPalette.js's selectCommands(), not
 * the full fixed vocabulary. Falls back to the full palette only if a
 * caller forgets to attach one, so this component never renders empty.
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { getCommandPalette, findCommand, commandReveals } from '../commandPalette.js';

const props = defineProps({
    content: { type: Object,  required: true }, // { artifacts, commands, timeLimitSec, ... }
    paused:  { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'timeout']);

const artifacts = computed(() => props.content.artifacts);
const commands  = computed(() => props.content.commands ?? getCommandPalette());

const revealed         = ref(new Set());
const flagged          = ref(new Set());
const activeCommandKey = ref(null);
const log              = ref([]);
const commandInput     = ref('');
const commandInputEl   = ref(null);

const activeCommandLabel = computed(() => {
    const cmd = activeCommandKey.value ? findCommand(activeCommandKey.value) : null;
    return cmd ? cmd.label : '';
});

const PENALTY_SECONDS = 5;

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
    nextTick(() => commandInputEl.value?.focus());
});

onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});

// Loose match so a player can type either the short key ("openssl") or the
// full displayed syntax ("openssl s_client -connect <target>:443") and
// land on the same command — real terminal muscle memory shouldn't be
// punished for typing more or less of the line.
function matchCommand(text) {
    const normalized = text.trim().toLowerCase();
    if (!normalized) return null;
    return commands.value.find(cmd => {
        if (cmd.key.toLowerCase() === normalized) return true;
        const firstWord = cmd.label.toLowerCase().split(' ')[0];
        return firstWord === normalized || cmd.label.toLowerCase() === normalized;
    }) ?? null;
}

function runTypedCommand() {
    if (props.paused) return;
    const raw = commandInput.value;
    if (!raw.trim()) return;

    log.value.push(`> ${raw.trim()}`);
    const match = matchCommand(raw);
    if (match) {
        activeCommandKey.value = match.key;
        log.value.push(`  [${match.label}] armed — select a target.`);
    } else {
        log.value.push(`  command not recognized: "${raw.trim()}"`);
    }
    commandInput.value = '';
}

function attemptReveal(artifactId) {
    if (props.paused || !activeCommandKey.value) return;
    const artifact = artifacts.value.find(a => a.id === artifactId);
    if (!artifact) return;

    const cmd = findCommand(activeCommandKey.value);
    const label = cmd ? cmd.label : activeCommandKey.value;

    if (commandReveals(activeCommandKey.value, artifact.kind)) {
        revealed.value.add(artifactId);
        log.value.push(`> ${label} — response received.`);
    } else {
        log.value.push(`> ${label} — no data returned.`);
        applyPenalty();
    }
}

function applyPenalty() {
    timeLeft.value = Math.max(0, timeLeft.value - PENALTY_SECONDS);
    if (timeLeft.value <= 0) {
        clearInterval(timerId);
        emit('timeout');
    }
}

function toggleFlag(id) {
    if (props.paused || !revealed.value.has(id)) return;
    if (flagged.value.has(id)) flagged.value.delete(id);
    else flagged.value.add(id);
}

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

.aii-terminal {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid rgba(0,200,240,0.25);
    background: rgba(0,200,240,0.03);
    padding: 10px 12px;
}

.aii-terminal-hint {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.aii-palette-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
    margin-right: 4px;
}

.aii-hint-cmd {
    font-size: 11px;
    color: rgba(0,200,240,0.55);
}

.aii-terminal-line {
    display: flex;
    align-items: center;
    gap: 8px;
}

.aii-prompt {
    color: rgba(0,255,100,0.6);
    font-size: 14px;
}

.aii-terminal-input {
    flex: 1;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    background: transparent;
    border: none;
    outline: none;
    color: #00ff9d;
    caret-color: #00ff9d;
}

.aii-terminal-input::placeholder {
    color: rgba(0,255,100,0.25);
}

.aii-terminal-input:disabled {
    opacity: 0.4;
}

.aii-active-cmd {
    font-size: 11px;
    color: #00d9ff;
    letter-spacing: 0.05em;
}

.aii-log {
    max-height: 70px;
    overflow-y: auto;
    font-size: 11px;
    color: rgba(0,255,100,0.4);
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.aii-feed {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 340px;
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

.aii-card--locked {
    border-style: dashed;
    opacity: 0.75;
}

.aii-card--flagged {
    border-color: rgba(255,140,60,0.6);
    border-style: solid;
    background: rgba(255,140,60,0.06);
}

.aii-locked-label {
    font-size: 12px;
    color: rgba(0,255,100,0.4);
    letter-spacing: 0.1em;
}

.aii-card-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    line-height: 1.5;
    color: rgba(0,255,100,0.8);
    white-space: pre-wrap;
    margin: 0;
}

.aii-flag-btn,
.aii-run-btn {
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

.aii-run-btn {
    border-color: rgba(0,200,240,0.35);
    color: rgba(0,200,240,0.8);
}

.aii-flag-btn:hover:not(:disabled) {
    border-color: rgba(255,140,60,0.9);
    background: rgba(255,140,60,0.1);
    color: rgba(255,140,60,1);
}

.aii-run-btn:hover:not(:disabled) {
    border-color: rgba(0,200,240,0.9);
    background: rgba(0,200,240,0.1);
    color: rgba(0,200,240,1);
}

.aii-card--flagged .aii-flag-btn {
    border-color: rgba(255,140,60,0.9);
    background: rgba(255,140,60,0.12);
    color: rgba(255,140,60,1);
}

.aii-flag-btn:disabled,
.aii-run-btn:disabled {
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
