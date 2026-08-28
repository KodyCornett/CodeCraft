
/**
 * SIT — Splice Interface Terminal. Proof-of-concept host for a fully typed
 * terminal minigame. Accepts a `scenarioKey` prop and resolves the active
 * scenario from scenarios/index.js — this component itself has no
 * knowledge of any one scenario's content or objective verb.
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
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import TerminalShell from './TerminalShell.vue';
import { runCommand, getSuggestions } from './fsInterpreter.js';
import { buildScenario, DEFAULT_SCENARIO_KEY } from './scenarios/index.js';

const props = defineProps({
    scenarioKey: { type: String, default: DEFAULT_SCENARIO_KEY },
});
const emit = defineEmits(['complete', 'failed', 'abort']);

const scenario = buildScenario(props.scenarioKey);

const state = ref({ cwd: [], solved: false });
const lines = ref([
    { text: 'connection established.', kind: 'output' },
    { text: 'type "help" to list available commands.', kind: 'output' },
]);

// Autocomplete — recomputed against the CURRENT directory every time the
// player's input changes, so suggestions always reflect where they
// actually are in the tree. See TerminalShell.vue's docblock for why this
// lives here rather than inside the (filesystem-agnostic) shell itself.
const currentTyped = ref('');
const suggestions  = computed(() => getSuggestions(scenario.root, state.value, currentTyped.value, scenario));

function onShellInput(val) {
    currentTyped.value = val;
}

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
    result.output.forEach((text, idx) => {
        const isSuccessLine = justSolved && idx === result.output.length - 1;
        lines.value.push({ text, kind: isSuccessLine ? 'success' : 'output' });
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
