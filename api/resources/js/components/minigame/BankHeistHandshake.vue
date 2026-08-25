<template>
    <div class="bhh-overlay">
        <div class="bhh-terminal">

            <!-- Top panel — network stream + step timer -->
            <div class="bhh-panel bhh-top">
                <div class="bhh-stream">
                    <div
                        v-for="(line, i) in streamLines"
                        :key="i"
                        class="bhh-stream-line"
                        :class="{ 'bhh-stream-line--target': line.isTarget }"
                    >
                        {{ line.text }}
                    </div>
                </div>
                <div class="bhh-timer-row">
                    <span class="bhh-timer-label">TIMEOUT</span>
                    <div class="bhh-timer-bar">
                        <div class="bhh-timer-fill" :class="timerClass" :style="{ width: timerPct + '%' }" />
                    </div>
                    <span class="bhh-timer-val" :class="timerClass">{{ timeLeft.toFixed(1) }}s</span>
                </div>
            </div>

            <!-- Middle panel — packet / buffer inspector -->
            <div class="bhh-panel bhh-middle">
                <template v-if="step === 'syn'">
                    <div class="bhh-label">[ AWAITING SYN LOCK ]</div>
                    <div class="bhh-mono">SRC_IP : {{ targetIp }}</div>
                    <div class="bhh-mono">PORT   : 443</div>
                    <p class="bhh-hint">Lock on with <code>sniff -target {{ targetIp }}</code>, or hit SPACE when the target line lights up above.</p>
                </template>
                <template v-else-if="step === 'syn_ack'">
                    <div class="bhh-label">[ CAPTURED SYN METADATA ]{{ attemptLabel }}</div>
                    <div class="bhh-mono">SRC_IP     : {{ targetIp }}</div>
                    <div class="bhh-mono">SEQ_NUM    : {{ puzzle.seq }}</div>
                    <div class="bhh-mono bhh-mono--target">TARGET ACK : {{ puzzle.targetAck }}</div>
                    <div class="bhh-label bhh-label--sub">[ CIPHER BUFFER CHUNKS ]</div>
                    <div class="bhh-chunks">
                        <span v-for="c in puzzle.chunks" :key="c.label" class="bhh-chunk">[{{ c.label }}] {{ c.value }}</span>
                    </div>
                    <p class="bhh-hint">Find the {{ puzzle.comboSize }} chunks that sum to the target ACK, then <code>respond -syn -ack &lt;letters joined by +&gt;</code>.</p>
                </template>
                <template v-else-if="step === 'ack'">
                    <div class="bhh-label">[ FINAL ACK RECEIVED FROM CLIENT ]</div>
                    <div class="bhh-mono">STATUS     : HANDSHAKE READY</div>
                    <div class="bhh-mono bhh-mono--target">TOKEN_HASH : 0x{{ tokenHash }}</div>
                    <p class="bhh-hint">Bind it before the session drops: <code>bind-session -token 0x{{ tokenHash }}</code>.</p>
                </template>
            </div>

            <!-- Bottom panel — CLI terminal -->
            <div class="bhh-panel bhh-bottom">
                <div ref="logEl" class="bhh-log">
                    <div v-for="(entry, i) in log" :key="i" class="bhh-log-line" :class="entry.kind">{{ entry.text }}</div>
                </div>
                <div class="bhh-cli-row">
                    <span class="bhh-prompt">&gt;</span>
                    <input
                        ref="cliInputEl"
                        v-model="cliText"
                        class="bhh-cli-input"
                        placeholder="type a command…"
                        autocomplete="off"
                        spellcheck="false"
                        @keydown="onKeydown"
                    />
                </div>
            </div>

            <div class="bhh-footer">
                <button class="bhh-abort" @click="$emit('abort')">[ ABORT — no cost, but no entry either ]</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useBankHeist } from '@/composables/useBankHeist.js';

const props = defineProps({
    canvasId:  { type: String, required: true },
    bankIce:   { type: Number, required: true },
    playerCpu: { type: Number, default: 3 },
    playerRam: { type: Number, default: 2 },
});

const emit = defineEmits(['success', 'failed', 'abort']);

const bh = useBankHeist();

// ── Step state ───────────────────────────────────────────────────────────────
const step          = ref('syn'); // 'syn' | 'syn_ack' | 'ack'
const targetIp      = ref(genIp());
const tokenHash     = ref(genHex(4));
const puzzle        = ref(null);
const synAckAttempt = ref(1);

const attemptLabel = computed(() => synAckAttempt.value > 1 ? ` — RETRY ${synAckAttempt.value}` : '');

function genIp() {
    return `10.0.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)}`;
}
function genHex(len) {
    const h = '0123456789ABCDEF';
    let out = '';
    for (let i = 0; i < len; i++) out += h[Math.floor(Math.random() * 16)];
    return out;
}

// ── Timer ────────────────────────────────────────────────────────────────────
const timeLeft    = ref(0);
const timerTotal  = ref(0);
let tickInterval  = null;

function startTimer(seconds) {
    if (tickInterval) clearInterval(tickInterval);
    timerTotal.value = seconds;
    timeLeft.value   = seconds;
    tickInterval = setInterval(() => {
        timeLeft.value = Math.max(0, timeLeft.value - 0.1);
        if (timeLeft.value <= 0) {
            clearInterval(tickInterval);
            emit('failed');
        }
    }, 100);
}

const timerPct   = computed(() => timerTotal.value ? (timeLeft.value / timerTotal.value) * 100 : 0);
const timerClass = computed(() => {
    if (timerTotal.value === 0) return '';
    const ratio = timeLeft.value / timerTotal.value;
    if (ratio <= 0.3) return 'bhh-timer--crit';
    if (ratio <= 0.6) return 'bhh-timer--warn';
    return '';
});

// ── Traffic stream (Step 1 visual — also gates the spacebar shortcut) ───────
const streamLines = ref([]);
const targetLineActive = ref(false);
let streamInterval = null;
let flashTimer = null;

const FLAVOR_LINES = [
    () => `[STREAM] TCP RETRANSMIT :: PORT 8443 :: LEN ${100 + Math.floor(Math.random() * 900)}`,
    () => `[STREAM] KEEPALIVE :: SRC 10.0.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)} :: PORT 22`,
    () => `[STREAM] ACK :: SEQ ${1000 + Math.floor(Math.random() * 9000)} :: WIN 64240`,
    () => `[STREAM] DNS QUERY :: bank-internal.local`,
];

function pushStreamLine(text, isTarget = false) {
    streamLines.value.push({ text, isTarget });
    if (streamLines.value.length > 6) streamLines.value.shift();
}

function startStream() {
    streamInterval = setInterval(() => {
        if (step.value !== 'syn') { clearInterval(streamInterval); return; }
        pushStreamLine(FLAVOR_LINES[Math.floor(Math.random() * FLAVOR_LINES.length)]());
    }, 900);
    scheduleTargetFlash();
}

function scheduleTargetFlash() {
    flashTimer = setTimeout(() => {
        if (step.value !== 'syn') return;
        targetLineActive.value = true;
        pushStreamLine(`[STREAM] INCOMING SYN DETECTED :: SRC_IP: ${targetIp.value} :: PORT: 443`, true);
        flashTimer = setTimeout(() => {
            targetLineActive.value = false;
            if (step.value === 'syn') scheduleTargetFlash();
        }, 900);
    }, 1600);
}

function stopStream() {
    if (streamInterval) clearInterval(streamInterval);
    if (flashTimer) clearTimeout(flashTimer);
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const cliText       = ref('');
const cliInputEl    = ref(null);
const logEl         = ref(null);
const log           = ref([]);
const history       = ref([]);
const historyIndex  = ref(-1);

const STEP_COMMANDS = { syn: 'sniff', syn_ack: 'respond', ack: 'bind-session' };

function pushLog(text, kind = '') {
    log.value.push({ text, kind });
    if (log.value.length > 10) log.value.shift();
    nextTick(() => { if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight; });
}

function onKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        submitCommand();
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const want = STEP_COMMANDS[step.value];
        const typed = cliText.value.trim().toLowerCase();
        if (typed.length > 0 && want.startsWith(typed)) cliText.value = want + ' ';
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!history.value.length) return;
        historyIndex.value = Math.min(history.value.length - 1, historyIndex.value + 1);
        cliText.value = history.value[history.value.length - 1 - historyIndex.value];
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (historyIndex.value <= 0) { historyIndex.value = -1; cliText.value = ''; return; }
        historyIndex.value -= 1;
        cliText.value = history.value[history.value.length - 1 - historyIndex.value];
    }
}

function submitCommand() {
    const raw = cliText.value.trim();
    if (!raw) return;
    history.value.push(raw);
    historyIndex.value = -1;
    pushLog('> ' + raw);
    cliText.value = '';

    if (step.value === 'syn') handleSyn(raw);
    else if (step.value === 'syn_ack') handleSynAck(raw);
    else if (step.value === 'ack') handleAck(raw);
}

// ── Step 1: SYN ──────────────────────────────────────────────────────────────
function handleSyn(raw) {
    if (raw.toLowerCase().split(/\s+/)[0] === 'sniff') {
        pushLog('[+] SYN INTERCEPTED — METADATA CAPTURED', 'good');
        advanceToSynAck();
    } else {
        pushLog('[-] UNKNOWN COMMAND', 'bad');
    }
}

function onGlobalKeydown(e) {
    if (e.code !== 'Space') return;
    if (document.activeElement === cliInputEl.value) return; // let normal typing use spaces
    if (step.value === 'syn' && targetLineActive.value) {
        e.preventDefault();
        pushLog('[+] SYN INTERCEPTED — SPACEBAR CAPTURE', 'good');
        advanceToSynAck();
    }
}

// ── Step 2: SYN-ACK ──────────────────────────────────────────────────────────
function advanceToSynAck() {
    stopStream();
    step.value = 'syn_ack';
    synAckAttempt.value = 1;
    newSynAckPuzzle();
}

function newSynAckPuzzle() {
    puzzle.value = bh.generateHandshakePuzzle(props.bankIce);
    const base = bh.handshakeStepTimer('syn_ack', props.playerCpu, props.playerRam, props.bankIce);
    startTimer(bh.handshakeRetryTimer(base, synAckAttempt.value));
}

function handleSynAck(raw) {
    const m = raw.toLowerCase().match(/-ack\s+([a-f](?:\s*\+\s*[a-f])*)/i);
    if (!m) {
        pushLog('[-] MALFORMED COMMAND — expected: respond -syn -ack <letters>', 'bad');
        return;
    }
    const guess = m[1].toUpperCase().split('+').map(s => s.trim()).sort();
    const correct = puzzle.value.correctLabels.slice().sort();
    const isMatch = guess.length === correct.length && guess.every((v, i) => v === correct[i]);

    if (isMatch) {
        pushLog(`[+] SYN-ACK ACKNOWLEDGED (ACK=${puzzle.value.targetAck} VERIFIED)`, 'good');
        advanceToAck();
    } else {
        pushLog('[-] ACK MISMATCH. CONNECTION RESET.', 'bad');
        synAckAttempt.value += 1;
        newSynAckPuzzle(); // fresh handshake, ratcheted-down timer — failures cost time
    }
}

// ── Step 3: ACK ──────────────────────────────────────────────────────────────
function advanceToAck() {
    step.value = 'ack';
    startTimer(bh.handshakeStepTimer('ack', props.playerCpu, props.playerRam, props.bankIce));
}

function handleAck(raw) {
    const m = raw.toLowerCase().match(/-token\s+(0x)?([0-9a-f]+)/i);
    if (!m) {
        pushLog('[-] MALFORMED COMMAND — expected: bind-session -token 0x<hash>', 'bad');
        return;
    }
    if (m[2].toUpperCase() === tokenHash.value) {
        pushLog('[+] SESSION BOUND :: CONNECTION ESTABLISHED :: ACCESS GRANTED', 'good');
        if (tickInterval) clearInterval(tickInterval);
        emit('success');
    } else {
        pushLog('[-] TOKEN MISMATCH', 'bad');
    }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    startTimer(bh.handshakeStepTimer('syn', props.playerCpu, props.playerRam, props.bankIce));
    startStream();
    window.addEventListener('keydown', onGlobalKeydown);
    nextTick(() => cliInputEl.value?.focus());
});

onBeforeUnmount(() => {
    if (tickInterval) clearInterval(tickInterval);
    stopStream();
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<style scoped>
.bhh-overlay { position: fixed; inset: 0; background: rgba(4, 6, 10, 0.92); z-index: 200; display: flex; align-items: center; justify-content: center; }
.bhh-terminal { width: min(720px, 94vw); background: #0a0f16; border: 1px solid #2a3a4a; font-family: 'JetBrains Mono', monospace; color: #a8c4d8; padding: 16px 18px; }

.bhh-panel + .bhh-panel { border-top: 1px solid #1e2a36; margin-top: 14px; padding-top: 14px; }

/* Top — stream + timer */
.bhh-stream { min-height: 110px; max-height: 130px; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-end; gap: 3px; margin-bottom: 10px; }
.bhh-stream-line { font-size: 10px; opacity: 0.55; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bhh-stream-line--target { color: #d8a83c; opacity: 1; font-weight: 600; animation: bhh-flash 0.5s ease-in-out infinite alternate; }
@keyframes bhh-flash { from { opacity: 0.7; } to { opacity: 1; } }

.bhh-timer-row { display: flex; align-items: center; gap: 10px; }
.bhh-timer-label { font-size: 9px; color: #6a8aa0; letter-spacing: 0.08em; }
.bhh-timer-bar { flex: 1; height: 8px; background: #101822; border: 1px solid #2a3a4a; }
.bhh-timer-fill { height: 100%; background: #4a90d8; transition: width 0.1s linear; }
.bhh-timer-fill.bhh-timer--warn { background: #d8a83c; }
.bhh-timer-fill.bhh-timer--crit { background: #e04848; }
.bhh-timer-val { font-size: 11px; width: 42px; text-align: right; color: #4a90d8; }
.bhh-timer-val.bhh-timer--warn { color: #d8a83c; }
.bhh-timer-val.bhh-timer--crit { color: #e04848; }

/* Middle — inspector */
.bhh-label { font-size: 10px; letter-spacing: 0.08em; color: #4a90d8; margin-bottom: 8px; }
.bhh-label--sub { margin-top: 10px; }
.bhh-mono { font-size: 12px; line-height: 1.7; }
.bhh-mono--target { color: #2ed88a; }
.bhh-chunks { display: flex; flex-wrap: wrap; gap: 10px; margin: 6px 0; }
.bhh-chunk { font-size: 12px; padding: 4px 8px; background: #101822; border: 1px solid #2a3a4a; }
.bhh-hint { font-size: 10px; opacity: 0.7; line-height: 1.6; margin: 8px 0 0; }
.bhh-hint code { color: #d8a83c; }

/* Bottom — CLI */
.bhh-log { max-height: 100px; overflow-y: auto; margin-bottom: 8px; display: flex; flex-direction: column; gap: 2px; }
.bhh-log-line { font-size: 10px; opacity: 0.85; }
.bhh-log-line.good { color: #2ed88a; }
.bhh-log-line.bad { color: #e04848; }
.bhh-cli-row { display: flex; align-items: center; gap: 8px; }
.bhh-prompt { color: #4a90d8; font-size: 13px; }
.bhh-cli-input { flex: 1; font-family: inherit; font-size: 12px; background: #101822; border: 1px solid #2a3a4a; color: #a8c4d8; padding: 7px 9px; }
.bhh-cli-input:focus { outline: none; border-color: #4a90d8; }

.bhh-footer { margin-top: 14px; text-align: right; }
.bhh-abort { font-family: inherit; font-size: 9px; color: #6a8aa0; background: transparent; border: 1px solid #2a3a4a; padding: 6px 12px; cursor: pointer; }
.bhh-abort:hover { border-color: #e04848; color: #e04848; }
</style>
