<template>
    <div class="sit-overlay">
        <div class="sit-window">
            <div class="sit-topbar">
                <span class="sit-label">SIT</span>
                <span class="sit-timer" :class="{ 'sit-timer--low': timeLeft <= 20 }">{{ timeLeft }}s</span>
            </div>

            <div class="sit-objective">{{ scenario.objective }}</div>

            <TerminalShell
                v-if="!outcome"
                :lines="lines"
                prompt-label="~"
                :paused="paused"
                @submit="onSubmit"
            />

            <div v-if="outcome" class="sit-outcome">
                <div class="sit-outcome-title" :class="outcome.success ? 'sit-outcome-title--win' : 'sit-outcome-title--fail'">
                    {{ outcome.title }}
                </div>
                <div class="sit-outcome-detail">{{ outcome.detail }}</div>
                <button type="button" class="sit-outcome-btn" @click="onClose">[ CLOSE ]</button>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * SIT — Splice Interface Terminal. Proof-of-concept host for a fully typed
 * terminal minigame.
 *
 * Owns the session state a real terminal needs (current directory, whether
 * the objective is solved) and the scrollback log TerminalShell.vue
 * renders. On every submitted line it calls fsInterpreter.runCommand(),
 * appends whatever comes back, and watches for `solved` flipping true.
 *
 * Deliberately separate from composer/ and generator/ — this is a new,
 * different shape of minigame (explore + read + combine facts, not
 * pick-from-a-fixed-set), reached only through the dev-only
 * splice://dev/sit-lab route. Nothing here touches the reward endpoint or
 * the live hack flow, same as ComposedMinigame.vue's own dev lab. Never
 * imports from ArchiveExtraction.vue, PacketHijack.vue, BankHeist.vue, or
 * their Codex/browser-pages system.
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import TerminalShell from './TerminalShell.vue';
import { runCommand } from './fsInterpreter.js';
import { buildProofScenario } from './proofScenario.js';

const emit = defineEmits(['complete', 'failed', 'abort']);

const scenario = buildProofScenario();

const state = ref({ cwd: [], solved: false });
const lines = ref([
    { text: 'connection established.', kind: 'output' },
    { text: 'type "help" to list available commands.', kind: 'output' },
]);

const paused   = ref(false);
const outcome  = ref(null);
const timeLeft = ref(scenario.timeLimitSec);
let timerId = null;

onMounted(() => {
    timerId = setInterval(() => {
        if (paused.value || outcome.value) return;
        timeLeft.value -= 1;
        if (timeLeft.value <= 0) {
            clearInterval(timerId);
            onTimeout();
        }
    }, 1000);
});

onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});

function onSubmit(rawLine) {
    if (outcome.value) return;
    lines.value.push({ text: rawLine, kind: 'input' });

    const result = runCommand(scenario.root, state.value, rawLine, scenario);
    state.value = result.state;

    const justSolved = state.value.solved;
    result.output.forEach(text => {
        lines.value.push({ text, kind: justSolved && text === 'ACCESS GRANTED.' ? 'success' : 'output' });
    });

    if (justSolved) {
        clearInterval(timerId);
        outcome.value = {
            success: true,
            title:   'ACCESS GRANTED',
            detail:  'Correctly assembled the credentials and authenticated.',
        };
        emit('complete', { completionPct: 1.0, detail: { timeLeftSec: timeLeft.value } });
    }
}

function onTimeout() {
    outcome.value = {
        success: false,
        title:   'CONNECTION LOST',
        detail:  'Session timed out before valid credentials were found.',
    };
    emit('failed', { detail: { reason: 'timeout' } });
}

function onClose() {
    emit('abort');
}
</script>

<style scoped>
.sit-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 500;
}

.sit-window {
    width: 720px;
    max-width: 94vw;
    background: #050807;
    border: 1px solid rgba(0,255,100,0.3);
    box-shadow: 0 0 40px rgba(0,255,100,0.08);
    padding: 24px 28px 28px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.sit-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.15);
}

.sit-label {
    font-size: 13px;
    color: #00ff9d;
    letter-spacing: 0.1em;
    font-weight: 700;
}

.sit-timer {
    font-size: 13px;
    color: rgba(0,200,240,0.75);
    letter-spacing: 0.05em;
}
.sit-timer--low { color: rgba(255,80,80,0.9); }

.sit-objective {
    font-size: 13px;
    color: rgba(0,255,100,0.7);
    line-height: 1.5;
}

.sit-outcome {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
    padding: 24px 0;
}

.sit-outcome-title {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.12em;
}
.sit-outcome-title--win  { color: #00ff9d; }
.sit-outcome-title--fail { color: rgba(255,80,80,0.85); }

.sit-outcome-detail {
    font-size: 13px;
    color: rgba(0,255,100,0.65);
    text-align: center;
    line-height: 1.5;
}

.sit-outcome-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
    padding: 8px 24px;
    cursor: pointer;
    transition: all 0.1s;
}
.sit-outcome-btn:hover {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}
</style>
