<template>
    <div class="bhh-overlay">
        <div class="bhh-shell">

            <BankHeistStatusBar
                :node-name="bankName"
                :staged-creds="0"
                :staged-tech="0"
                :trace-percent="0"
                :active="false"
            />

            <div class="bhh-panels">

                <!-- PANEL 1 — Live Packet Telemetry & Queue Stream -->
                <div class="bhh-panel bhh-panel1">
                    <div class="bhh-panel-label">PANEL 1: LIVE PACKET TELEMETRY & QUEUE STREAM</div>
                    <div class="bhh-stream">
                        <div
                            v-for="(line, i) in streamLines"
                            :key="i"
                            class="bhh-stream-line"
                            :class="{ 'bhh-stream-line--target': line.isTarget }"
                        >
                            {{ line.text }}<span v-if="line.isTarget" class="bhh-lock-tag"> &lt;-- [GOLD LOCK]</span>
                        </div>
                    </div>
                    <div class="bhh-timer-row">
                        <span class="bhh-timer-label">TIMEOUT</span>
                        [<div class="bhh-timer-bar"><div class="bhh-timer-fill" :class="timerClass" :style="{ width: timerPct + '%' }" /></div>]
                        <span class="bhh-timer-val" :class="timerClass">{{ timeLeft.toFixed(1) }}s</span>
                    </div>
                </div>

                <!-- PANEL 2 — Inspector & Cipher Matrix -->
                <div class="bhh-panel bhh-panel2">
                    <div class="bhh-panel-label">PANEL 2: INSPECTOR & CIPHER MATRIX</div>

                    <template v-if="step === 'TARGET_LOCK'">
                        <div class="bhh-label">[ AWAITING TARGET LOCK ]</div>
                        <div class="bhh-mono">SRC_IP : {{ targetIp }}</div>
                        <div class="bhh-mono">PORT   : 443</div>
                        <p class="bhh-hint">No command required — press <code>SPACEBAR</code> or <code>ENTER</code> the instant the target line lights up gold above.</p>
                    </template>

                    <template v-else-if="step === 'SYN_ACK_MATH'">
                        <div class="bhh-label">[ PHASE 1 VIEW: SYN-ACK MATH ]{{ attemptLabel }}</div>
                        <div class="bhh-mono">SRC_IP     : {{ targetIp }}</div>
                        <div class="bhh-mono">SEQ NUMBER : {{ puzzle.seq }}</div>
                        <div class="bhh-mono bhh-mono--target">TARGET ACK : {{ puzzle.targetAck }} (SEQ + 1)</div>

                        <div class="bhh-label bhh-label--sub">[ CIPHER POOL ]</div>
                        <div class="bhh-chunks">
                            <span v-for="c in puzzle.chunks" :key="c.label" class="bhh-chunk">[{{ c.label }}] {{ c.value }}</span>
                        </div>

                        <div v-if="selectedMatchPreview" class="bhh-match-preview">
                            SELECTED MATCH: {{ selectedMatchPreview.parts.map(c => `[${c.label}] (${c.value})`).join(' + ') }} = {{ selectedMatchPreview.sum }}
                        </div>

                        <p class="bhh-hint">Find the {{ puzzle.comboSize }} chunks that sum to the target ACK, then <code>respond -syn -ack &lt;letters joined by +&gt;</code>.</p>
                    </template>

                    <template v-else-if="step === 'ACK_BIND'">
                        <div class="bhh-label">[ SESSION LOCK ]</div>
                        <div class="bhh-mono">STATUS     : FINAL ACK RECEIVED FROM CLIENT</div>
                        <div class="bhh-mono bhh-mono--target">TOKEN_HASH : 0x{{ tokenHash }}</div>
                        <p class="bhh-hint">Bind it before the session drops: <code>bind-session -token 0x{{ tokenHash }}</code>.</p>
                    </template>
                </div>

                <!-- PANEL 3 — Interactive CLI Command Console -->
                <div class="bhh-panel bhh-panel3">
                    <div class="bhh-panel-label">PANEL 3: INTERACTIVE CLI COMMAND CONSOLE</div>
                    <div v-if="lastStatus" class="bhh-status-line" :class="lastStatus.kind">{{ lastStatus.text }}</div>
                    <div class="bhh-cli-row">
                        <span class="bhh-prompt">&gt;</span>
                        <input
                            ref="cliInputEl"
                            v-model="cliText"
                            class="bhh-cli-input"
                            :disabled="step === 'TARGET_LOCK'"
                            :placeholder="step === 'TARGET_LOCK' ? 'no input required — SPACE / ENTER to lock' : 'type a command…'"
                            autocomplete="off"
                            spellcheck="false"
                            @keydown="onKeydown"
                        />
                    </div>
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
import BankHeistStatusBar from '@/components/minigame/BankHeistStatusBar.vue';

const props = defineProps({
    canvasId:  { type: String, required: true },
    bankName:  { type: String, default: 'UNKNOWN TARGET' },
    bankIce:   { type: Number, required: true },
    playerCpu: { type: Number, default: 3 },
    playerRam: { type: Number, default: 2 },
});

const emit = defineEmits(['success', 'failed', 'abort']);

const bh = useBankHeist();

// ── Step state ───────────────────────────────────────────────────────────────
const step          = ref('TARGET_LOCK'); // 'TARGET_LOCK' | 'SYN_ACK_MATH' | 'ACK_BIND'
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

// ── Live "SELECTED MATCH" preview — recomputed on every keystroke while
// solving SYN-ACK, mirroring the design doc's running-sum readout. ─────────
const selectedMatchPreview = computed(() => {
    if (step.value !== 'SYN_ACK_MATH' || !puzzle.value) return null;
    const m = cliText.value.match(/-ack\s+([a-zA-Z](?:\s*\+\s*[a-zA-Z])*\+?)/);
    if (!m) return null;
    const letters = m[1].toUpperCase().split('+').map(s => s.trim()).filter(Boolean);
    if (!letters.length) return null;
    const parts = letters.map(l => puzzle.value.chunks.find(c => c.label === l)).filter(Boolean);
    if (!parts.length) return null;
    return { parts, sum: parts.reduce((a, c) => a + c.value, 0) };
});

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

// ── Traffic stream (Step 1 visual — also gates the spacebar/enter shortcut) ─
const streamLines = ref([]);
const targetLineActive = ref(false);
let streamInterval = null;
let flashTimer = null;

function stamp() {
    const d = new Date(2026, 0, 1, 12, 4, Math.floor(Math.random() * 60), Math.floor(Math.random() * 1000));
    const pad = (n, l = 2) => String(n).padStart(l, '0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}.${pad(d.getMilliseconds(), 3)}`;
}

const FLAVOR_LINES = [
    () => `${stamp()} [STREAM] TCP RETRANSMIT :: PORT 8443 :: LEN ${100 + Math.floor(Math.random() * 900)}`,
    () => `${stamp()} [STREAM] KEEPALIVE :: SRC 10.0.${Math.floor(Math.random() * 255)}.${Math.floor(Math.random() * 255)} :: PORT 22`,
    () => `${stamp()} [STREAM] ACK :: SEQ ${1000 + Math.floor(Math.random() * 9000)} :: WIN 64240`,
    () => `${stamp()} [STREAM] DNS QUERY :: bank-internal.local`,
];

function pushStreamLine(text, isTarget = false) {
    streamLines.value.push({ text, isTarget });
    if (streamLines.value.length > 6) streamLines.value.shift();
}

function startStream() {
    streamInterval = setInterval(() => {
        if (step.value !== 'TARGET_LOCK') { clearInterval(streamInterval); return; }
        pushStreamLine(FLAVOR_LINES[Math.floor(Math.random() * FLAVOR_LINES.length)]());
    }, 900);
    scheduleTargetFlash();
}

function scheduleTargetFlash() {
    flashTimer = setTimeout(() => {
        if (step.value !== 'TARGET_LOCK') return;
        targetLineActive.value = true;
        pushStreamLine(`${stamp()} [STREAM] INCOMING SYN DETECTED :: SRC_IP: ${targetIp.value} :: PORT 443`, true);
        flashTimer = setTimeout(() => {
            targetLineActive.value = false;
            if (step.value === 'TARGET_LOCK') scheduleTargetFlash();
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
const lastStatus    = ref(null); // { text, kind } | null — single transient line, no scrolling log
const history       = ref([]);
const historyIndex  = ref(-1);

const STEP_COMMANDS = { SYN_ACK_MATH: 'respond', ACK_BIND: 'bind-session' };

function setStatus(text, kind = '') {
    lastStatus.value = { text, kind };
}

function onKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        submitCommand();
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const want = STEP_COMMANDS[step.value];
        if (!want) return;
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
    cliText.value = '';

    if (step.value === 'SYN_ACK_MATH') handleSynAck(raw);
    else if (step.value === 'ACK_BIND') handleAck(raw);
}

// ── Step 1: TARGET_LOCK — spacebar/enter only, no CLI command ───────────────
function onGlobalKeydown(e) {
    if (e.key !== ' ' && e.key !== 'Enter') return;
    if (document.activeElement === cliInputEl.value) return; // TARGET_LOCK disables the input, but guard anyway
    if (step.value === 'TARGET_LOCK' && targetLineActive.value) {
        e.preventDefault();
        setStatus('[+] TARGET LOCKED — SYN INTERCEPTED', 'good');
        advanceToSynAck();
    }
}

// ── Step 2: SYN-ACK ──────────────────────────────────────────────────────────
function advanceToSynAck() {
    stopStream();
    step.value = 'SYN_ACK_MATH';
    synAckAttempt.value = 1;
    newSynAckPuzzle();
    nextTick(() => cliInputEl.value?.focus());
}

function newSynAckPuzzle() {
    puzzle.value = bh.generateHandshakePuzzle(props.bankIce);
    const base = bh.handshakeStepTimer('syn_ack', props.playerCpu, props.playerRam, props.bankIce);
    startTimer(bh.handshakeRetryTimer(base, synAckAttempt.value));
}

function handleSynAck(raw) {
    const m = raw.toLowerCase().match(/-ack\s+([a-f](?:\s*\+\s*[a-f])*)/i);
    if (!m) {
        setStatus('[-] MALFORMED COMMAND — expected: respond -syn -ack <letters>', 'bad');
        return;
    }
    const guess = m[1].toUpperCase().split('+').map(s => s.trim()).sort();
    const correct = puzzle.value.correctLabels.slice().sort();
    const isMatch = guess.length === correct.length && guess.every((v, i) => v === correct[i]);

    if (isMatch) {
        setStatus(`[+] SYN-ACK ACKNOWLEDGED (ACK=${puzzle.value.targetAck} VERIFIED)`, 'good');
        advanceToAck();
    } else {
        setStatus('[-] ACK MISMATCH. CONNECTION RESET.', 'bad');
        synAckAttempt.value += 1;
        newSynAckPuzzle(); // fresh handshake, ratcheted-down timer — failures cost time
    }
}

// ── Step 3: ACK ──────────────────────────────────────────────────────────────
function advanceToAck() {
    step.value = 'ACK_BIND';
    startTimer(bh.handshakeStepTimer('ack', props.playerCpu, props.playerRam, props.bankIce));
}

function handleAck(raw) {
    const m = raw.toLowerCase().match(/-token\s+(0x)?([0-9a-f]+)/i);
    if (!m) {
        setStatus('[-] MALFORMED COMMAND — expected: bind-session -token 0x<hash>', 'bad');
        return;
    }
    if (m[2].toUpperCase() === tokenHash.value) {
        setStatus('[+] SESSION BOUND :: CONNECTION ESTABLISHED :: ACCESS GRANTED', 'good');
        if (tickInterval) clearInterval(tickInterval);
        emit('success');
    } else {
        setStatus('[-] TOKEN MISMATCH', 'bad');
    }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    startTimer(bh.handshakeStepTimer('syn', props.playerCpu, props.playerRam, props.bankIce));
    startStream();
    window.addEventListener('keydown', onGlobalKeydown);
});

onBeforeUnmount(() => {
    if (tickInterval) clearInterval(tickInterval);
    stopStream();
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<style scoped>
.bhh-overlay { position: fixed; inset: 0; z-index: 200; background: rgba(2, 4, 8, 0.55); display: flex; align-items: center; justify-content: center; }
.bhh-shell {
    width: 90vw; height: 90vh; max-width: 1400px;
    background: rgba(5, 11, 20, 0.95); border: 1px solid #00F0FF; box-shadow: 0 0 24px rgba(0, 240, 255, 0.2);
    font-family: 'JetBrains Mono', monospace; color: #00F0FF;
    display: flex; flex-direction: column; padding: 14px 18px;
}

.bhh-panels { flex: 1; display: flex; flex-direction: column; gap: 10px; margin-top: 10px; min-height: 0; }
.bhh-panel { border: 1px solid rgba(0, 240, 255, 0.35); padding: 10px 14px; overflow-y: auto; }
.bhh-panel1 { flex: 0 0 40%; }
.bhh-panel2 { flex: 0 0 45%; }
.bhh-panel3 { flex: 0 0 15%; display: flex; flex-direction: column; justify-content: flex-end; overflow: visible; }
.bhh-panel-label { font-size: 9px; letter-spacing: 0.1em; color: #FFB000; margin-bottom: 8px; opacity: 0.85; }

/* Panel 1 — stream + timer */
.bhh-stream { min-height: 80px; display: flex; flex-direction: column; gap: 3px; margin-bottom: 10px; }
.bhh-stream-line { font-size: 10px; opacity: 0.6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bhh-stream-line--target { color: #FFB000; opacity: 1; font-weight: 600; animation: bhh-flash 0.5s ease-in-out infinite alternate; }
.bhh-lock-tag { color: #FFB000; }
@keyframes bhh-flash { from { opacity: 0.7; } to { opacity: 1; } }

.bhh-timer-row { display: flex; align-items: center; gap: 6px; font-size: 11px; }
.bhh-timer-label { font-size: 9px; color: #6a8aa0; letter-spacing: 0.08em; }
.bhh-timer-bar { display: inline-block; width: 160px; height: 8px; background: #0a0f16; vertical-align: middle; }
.bhh-timer-fill { display: block; height: 100%; background: #00F0FF; transition: width 0.1s linear; }
.bhh-timer-fill.bhh-timer--warn { background: #FFB000; }
.bhh-timer-fill.bhh-timer--crit { background: #FF2244; }
.bhh-timer-val { font-size: 11px; width: 42px; text-align: right; color: #00F0FF; }
.bhh-timer-val.bhh-timer--warn { color: #FFB000; }
.bhh-timer-val.bhh-timer--crit { color: #FF2244; }

/* Panel 2 — inspector */
.bhh-label { font-size: 10px; letter-spacing: 0.08em; color: #00F0FF; margin-bottom: 8px; }
.bhh-label--sub { margin-top: 12px; }
.bhh-mono { font-size: 12px; line-height: 1.7; }
.bhh-mono--target { color: #FFB000; }
.bhh-chunks { display: flex; flex-wrap: wrap; gap: 10px; margin: 6px 0; }
.bhh-chunk { font-size: 12px; padding: 6px 10px; background: #0a0f16; border: 1px solid #00F0FF; }
.bhh-match-preview { font-size: 11px; color: #FFB000; margin: 10px 0; }
.bhh-hint { font-size: 10px; opacity: 0.7; line-height: 1.6; margin: 8px 0 0; }
.bhh-hint code { color: #FFB000; }

/* Panel 3 — CLI */
.bhh-status-line { font-size: 10px; margin-bottom: 6px; opacity: 0.9; }
.bhh-status-line.good { color: #2ed88a; }
.bhh-status-line.bad { color: #FF2244; }
.bhh-cli-row { display: flex; align-items: center; gap: 8px; }
.bhh-prompt { color: #00F0FF; font-size: 13px; }
.bhh-cli-input { flex: 1; font-family: inherit; font-size: 12px; background: #0a0f16; border: 1px solid #00F0FF; color: #00F0FF; padding: 7px 9px; }
.bhh-cli-input:focus { outline: none; box-shadow: 0 0 6px rgba(0, 240, 255, 0.4); }
.bhh-cli-input:disabled { opacity: 0.4; }

.bhh-footer { margin-top: 10px; text-align: right; }
.bhh-abort { font-family: inherit; font-size: 9px; color: #6a8aa0; background: transparent; border: 1px solid #2a3a4a; padding: 6px 12px; cursor: pointer; }
.bhh-abort:hover { border-color: #FF2244; color: #FF2244; }
</style>
