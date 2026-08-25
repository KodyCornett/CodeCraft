<template>
    <div class="bhv-overlay">
        <div class="bhv-shell">

            <BankHeistStatusBar
                :node-name="bankName"
                :staged-creds="stagedCreds"
                :staged-tech="stagedTech"
                :trace-percent="globalTrace"
                :active="true"
            />

            <div class="bhv-panels">

                <!-- PANEL 1 — Live Packet Telemetry & Queue Stream -->
                <div class="bhv-panel bhv-panel1">
                    <div class="bhv-panel-label">PANEL 1: LIVE PACKET TELEMETRY & QUEUE STREAM</div>

                    <template v-if="subStep === 'QUEUE_SELECT'">
                        <div class="bhv-label">[ LIVE TRANSACTION QUEUE ]{{ filterLabel }}</div>
                        <div class="bhv-queue">
                            <div class="bhv-queue-head">
                                <span>TX_ID</span><span>YIELD</span><span>CURR</span><span>DIFFICULTY</span><span>EXPIRES</span>
                            </div>
                            <div v-for="tx in filteredQueue" :key="tx.id" class="bhv-queue-row">
                                <span class="bhv-mono">{{ tx.id }}</span>
                                <span class="bhv-mono">{{ tx.previewYield }}</span>
                                <span class="bhv-mono">{{ tx.currency }}</span>
                                <span class="bhv-mono">{{ tx.band === 'easy' ? 'EASY' : 'HARD' }} ({{ tx.requiredFragments }})</span>
                                <span class="bhv-mono">
                                    [<div class="bhv-tx-timer-bar"><div class="bhv-tx-timer-fill" :style="{ width: (tx.timeLeft / tx.timerTotal * 100) + '%' }" /></div>]
                                    {{ tx.timeLeft.toFixed(1) }}s
                                </span>
                            </div>
                            <div v-if="!filteredQueue.length" class="bhv-hint">No transactions match that filter right now.</div>
                        </div>
                    </template>

                    <template v-else-if="subStep === 'TOKEN_BUILD'">
                        <div class="bhv-label">[ INTERCEPTED: {{ activeTx.id }} ] :: YIELD: {{ activeTx.previewYield }} {{ activeTx.currency }}</div>
                        <div class="bhv-timer-row">
                            <span class="bhv-timer-label">TX TIMER</span>
                            [<div class="bhv-timer-bar"><div class="bhv-timer-fill" :class="txTimerClass" :style="{ width: (activeTx.timeLeft / activeTx.timerTotal * 100) + '%' }" /></div>]
                            <span class="bhv-timer-val" :class="txTimerClass">{{ activeTx.timeLeft.toFixed(1) }}s</span>
                        </div>
                    </template>

                    <template v-else-if="subStep === 'HARVEST_SCREEN'">
                        <div class="bhv-label">[ HARVEST SUMMARY ]</div>
                        <div class="bhv-harvest">
                            <div>LAST HARVESTED : <span class="bhv-good">+{{ lastHarvest.creds || lastHarvest.tech }} {{ lastHarvest.currency }}</span></div>
                            <div>TOTAL HARVEST  : <span class="bhv-good">{{ stagedCreds }} CRED{{ stagedTech > 0 ? ` / ${stagedTech.toFixed(2)} TECH` : '' }} (STAGED)</span></div>
                        </div>
                    </template>
                </div>

                <!-- PANEL 2 — Inspector & Token Matrix -->
                <div class="bhv-panel bhv-panel2">
                    <div class="bhv-panel-label">PANEL 2: INSPECTOR & TOKEN MATRIX</div>

                    <template v-if="subStep === 'QUEUE_SELECT'">
                        <div class="bhv-label">[ QUEUE CONTROLS ]</div>
                        <p class="bhv-hint">
                            <code>intercept -tx &lt;TX_ID&gt;</code> to hook one · <code>tx-filter -curr CRED|TECH_PT --min &lt;val&gt;</code> to narrow the feed
                        </p>
                    </template>

                    <template v-else-if="subStep === 'TOKEN_BUILD'">
                        <div class="bhv-label">[ PHASE 2 VIEW: TOKEN BUILDER ({{ puzzle.slots.length }} FRAGMENTS REQUIRED) ]</div>
                        <div class="bhv-mono">TARGET LAYOUT: {{ puzzle.slots.map(s => `[${s}]`).join(' -> ') }}</div>
                        <div class="bhv-mono">SALT = {{ saltPreview }} | YOUR_ID = {{ playerTag }}</div>

                        <div class="bhv-label bhv-label--sub">[ AVAILABLE FRAGMENTS ]</div>
                        <div class="bhv-fragments">
                            <span v-for="f in puzzle.fragments" :key="f.id" class="bhv-fragment">[{{ f.id }}] {{ f.tag }}={{ f.value }}</span>
                        </div>
                        <p class="bhv-hint">Order the fragments into the layout above, then <code>inject -token F6-F1-F2-...</code></p>
                    </template>

                    <template v-else-if="subStep === 'HARVEST_SCREEN'">
                        <div class="bhv-label">[ RISK & HARVEST DECISION ]</div>
                        <p class="bhv-hint">Continuing increases trace risk. If Global Trace hits 100%, ALL STAGED FUNDS ARE WIPED.</p>
                        <p class="bhv-hint"><code>extract</code> — bank it safely and end the run · <code>continue</code> — back to the queue for more</p>
                    </template>
                </div>

                <!-- PANEL 3 — Interactive CLI Command Console -->
                <div class="bhv-panel bhv-panel3">
                    <div class="bhv-panel-label">PANEL 3: INTERACTIVE CLI COMMAND CONSOLE</div>
                    <div v-if="lastStatus" class="bhv-status-line" :class="lastStatus.kind">{{ lastStatus.text }}</div>
                    <div class="bhv-cli-row">
                        <span class="bhv-prompt">&gt;</span>
                        <input
                            ref="cliInputEl"
                            v-model="cliText"
                            class="bhv-cli-input"
                            placeholder="type a command…"
                            autocomplete="off"
                            spellcheck="false"
                            @keydown="onKeydown"
                        />
                    </div>
                </div>

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
    playerOs:  { type: Number, default: 2 },
});

// 'complete' fires only on a successful EXTRACT — the run banked something
// (or nothing, if extracted with an empty buffer) and ends cleanly. 'failed'
// fires only on a Global Trace overrun, forwarded straight up to
// BankHeist.vue exactly like BankHeistHandshake's 'failed' — the actual
// server call and cost stack live in BankHeist.vue's resolveEntryFailure so
// there's exactly one place that ever calls gate1-failed.
const emit = defineEmits(['complete', 'failed']);

const bh = useBankHeist();
const playerTag = `PL_${Math.random().toString(16).slice(2, 6).toUpperCase()}`;

// ── Global state ─────────────────────────────────────────────────────────────
const subStep       = ref('QUEUE_SELECT'); // 'QUEUE_SELECT' | 'TOKEN_BUILD' | 'HARVEST_SCREEN'
const globalTrace   = ref(0);
const stagedCreds   = ref(0);
const stagedTech    = ref(0);
const queue         = ref([]);
const activeTx      = ref(null);
const puzzle        = ref(null);
const saltPreview   = ref('');
const lastHarvest   = ref({});
const filter        = ref(null); // { currency, min } | null

const txTimerClass = computed(() => {
    if (!activeTx.value) return '';
    const ratio = activeTx.value.timeLeft / activeTx.value.timerTotal;
    if (ratio <= 0.3) return 'bhv-crit';
    if (ratio <= 0.6) return 'bhv-warn';
    return '';
});
const filterLabel = computed(() => {
    if (!filter.value) return '';
    return ` — filtered: ${filter.value.currency}${filter.value.min ? ` ≥ ${filter.value.min}` : ''}`;
});
const filteredQueue = computed(() => {
    if (!filter.value) return queue.value;
    return queue.value.filter(tx =>
        tx.currency === filter.value.currency && (!filter.value.min || tx.previewYield >= filter.value.min)
    );
});

function refillQueue() {
    while (queue.value.length < bh.PHASE2_QUEUE_SIZE) {
        queue.value.push(bh.generateTransaction(props.bankIce));
    }
}

// ── Global tick — trace meter + queue timers, always running regardless of sub-step ──
let tickInterval = null;
function startTick() {
    tickInterval = setInterval(() => {
        globalTrace.value = Math.min(100, globalTrace.value + (bh.phase2TraceRate(props.bankIce) * 200) / 1000);
        if (globalTrace.value >= 100) { triggerOverrun(); return; }

        if (subStep.value === 'QUEUE_SELECT') {
            queue.value.forEach(tx => { tx.timeLeft = Math.max(0, tx.timeLeft - 0.2); });
            queue.value = queue.value.filter(tx => tx.timeLeft > 0);
            refillQueue();
        } else if (subStep.value === 'TOKEN_BUILD' && activeTx.value) {
            activeTx.value.timeLeft = Math.max(0, activeTx.value.timeLeft - 0.2);
            if (activeTx.value.timeLeft <= 0) failInjection('TRANSACTION TIMER EXPIRED');
        }
    }, 200);
}

function triggerOverrun() {
    if (tickInterval) clearInterval(tickInterval);
    setStatus('[!!!] CRITICAL ALARM: GLOBAL SYSTEM TRACE 100% COMPLETE — STAGED BUFFER PURGED', 'bad');
    emit('failed', 'phase2_overrun');
}

// ── CLI ──────────────────────────────────────────────────────────────────────
const cliText      = ref('');
const cliInputEl   = ref(null);
const lastStatus   = ref(null); // { text, kind } | null — single transient line, no scrolling log
const history      = ref([]);
const historyIndex = ref(-1);

const STEP_COMMANDS = {
    QUEUE_SELECT:   ['intercept', 'tx-filter'],
    TOKEN_BUILD:    ['inject'],
    HARVEST_SCREEN: ['extract', 'continue'],
};

function setStatus(text, kind = '') {
    lastStatus.value = { text, kind };
}

function onKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        submitCommand();
    } else if (e.key === 'Tab') {
        e.preventDefault();
        const typed = cliText.value.trim().toLowerCase();
        if (!typed) return;
        const candidates = STEP_COMMANDS[subStep.value].filter(c => c.startsWith(typed));
        if (candidates.length === 1) cliText.value = candidates[0] + ' ';
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

    if (subStep.value === 'QUEUE_SELECT') handleQueueSelect(raw);
    else if (subStep.value === 'TOKEN_BUILD') handleTokenBuild(raw);
    else if (subStep.value === 'HARVEST_SCREEN') handleHarvestScreen(raw);
}

// ── QUEUE_SELECT ─────────────────────────────────────────────────────────────
function handleQueueSelect(raw) {
    const lower = raw.toLowerCase();

    const interceptMatch = lower.match(/^intercept\s+-tx\s+(\S+)/);
    if (interceptMatch) {
        const txId = interceptMatch[1].toUpperCase();
        const tx = queue.value.find(t => t.id === txId);
        if (!tx) { setStatus(`[-] NO SUCH TRANSACTION IN FEED: ${txId}`, 'bad'); return; }
        queue.value = queue.value.filter(t => t.id !== txId);
        activeTx.value = tx;
        puzzle.value = bh.generateFragmentPuzzle(tx, playerTag);
        saltPreview.value = puzzle.value.fragments.find(f => f.tag === 'SALT')?.value ?? `0x${Math.random().toString(16).slice(2, 6).toUpperCase()}`;
        setStatus(`[+] HOOKED ${txId} — TOKEN BUILDER OPEN`, 'good');
        subStep.value = 'TOKEN_BUILD';
        return;
    }

    const filterMatch = lower.match(/^tx-filter\s+-curr\s+(cred|tech_pt)(?:\s+--min\s+(\d+))?/);
    if (filterMatch) {
        filter.value = { currency: filterMatch[1].toUpperCase(), min: filterMatch[2] ? Number(filterMatch[2]) : null };
        setStatus(`[+] FEED FILTERED`, 'good');
        return;
    }

    if (lower === 'tx-filter' || lower === 'tx-filter clear' || lower === 'tx-filter -clear') {
        filter.value = null;
        setStatus('[+] FILTER CLEARED', 'good');
        return;
    }

    setStatus('[-] UNKNOWN COMMAND — expected: intercept -tx <TX_ID> or tx-filter -curr <CRED|TECH_PT> --min <val>', 'bad');
}

// ── TOKEN_BUILD ──────────────────────────────────────────────────────────────
function handleTokenBuild(raw) {
    const m = raw.toLowerCase().match(/^inject\s+-token\s+([a-z0-9]+(?:-[a-z0-9]+)*)/i);
    if (!m) {
        setStatus('[-] MALFORMED COMMAND — expected: inject -token F1-F2-...', 'bad');
        return;
    }
    const guess = m[1].toUpperCase().split('-');
    const correct = puzzle.value.correctSequence;
    const isMatch = guess.length === correct.length && guess.every((v, i) => v === correct[i]);

    if (isMatch) {
        resolveInjection();
    } else {
        failInjection('CHECKSUM INVALID (RECIPIENT MISMATCH)');
    }
}

async function resolveInjection() {
    const tx = activeTx.value;
    setStatus('[+] CHECKSUM VERIFIED — TRANSACTION SPOOF SUCCESSFUL!', 'good');
    const res = await bh.phase2Inject(props.canvasId, tx.band, tx.currency);
    if (res) {
        stagedCreds.value = res.staged_creds ?? stagedCreds.value;
        stagedTech.value  = res.staged_tech ?? stagedTech.value;
        lastHarvest.value = { creds: res.reward?.creds ?? 0, tech: res.reward?.tech ?? 0, currency: tx.currency };
    } else {
        lastHarvest.value = { creds: 0, tech: 0, currency: tx.currency };
    }
    activeTx.value = null;
    puzzle.value = null;
    subStep.value = 'HARVEST_SCREEN';
}

function failInjection(reason) {
    const spike = bh.phase2TraceSpike(props.bankIce);
    globalTrace.value = Math.min(100, globalTrace.value + spike);
    setStatus(`[-] ${reason} — TX ${activeTx.value.id} DROPPED :: GLOBAL TRACE +${spike.toFixed(0)}%`, 'bad');
    activeTx.value = null;
    puzzle.value = null;
    if (globalTrace.value >= 100) { triggerOverrun(); return; }
    subStep.value = 'QUEUE_SELECT';
}

// ── HARVEST_SCREEN ───────────────────────────────────────────────────────────
async function handleHarvestScreen(raw) {
    const lower = raw.toLowerCase();
    if (lower === 'extract') {
        await extract();
    } else if (lower === 'continue') {
        subStep.value = 'QUEUE_SELECT';
    } else {
        setStatus('[-] UNKNOWN COMMAND — expected: extract or continue', 'bad');
    }
}

async function extract() {
    if (tickInterval) clearInterval(tickInterval);
    const res = await bh.phase2Extract(props.canvasId);
    const playerSync = {};
    if (res?.pocket_creds !== undefined) playerSync.pocketCreds = res.pocket_creds;
    if (res?.tech_points !== undefined)  playerSync.techPoints  = res.tech_points;
    emit('complete', {
        totalCreds: res?.creds_extracted ?? stagedCreds.value,
        totalTech: res?.tech_extracted ?? stagedTech.value,
        lockdown: false,
        playerSync,
    });
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    refillQueue();
    startTick();
    nextTick(() => cliInputEl.value?.focus());
});

onBeforeUnmount(() => {
    if (tickInterval) clearInterval(tickInterval);
});
</script>

<style scoped>
.bhv-overlay { position: fixed; inset: 0; z-index: 200; background: rgba(2, 4, 8, 0.55); display: flex; align-items: center; justify-content: center; }
.bhv-shell {
    width: 90vw; height: 90vh; max-width: 1400px;
    background: rgba(5, 11, 20, 0.95); border: 1px solid #00F0FF; box-shadow: 0 0 24px rgba(0, 240, 255, 0.2);
    font-family: 'JetBrains Mono', monospace; color: #00F0FF;
    display: flex; flex-direction: column; padding: 14px 18px;
}

.bhv-panels { flex: 1; display: flex; flex-direction: column; gap: 10px; margin-top: 10px; min-height: 0; }
.bhv-panel { border: 1px solid rgba(0, 240, 255, 0.35); padding: 10px 14px; overflow-y: auto; }
.bhv-panel1 { flex: 0 0 40%; }
.bhv-panel2 { flex: 0 0 45%; }
.bhv-panel3 { flex: 0 0 15%; display: flex; flex-direction: column; justify-content: flex-end; overflow: visible; }
.bhv-panel-label { font-size: 9px; letter-spacing: 0.1em; color: #FFB000; margin-bottom: 8px; opacity: 0.85; }

.bhv-label { font-size: 10px; letter-spacing: 0.08em; color: #00F0FF; margin-bottom: 8px; }
.bhv-label--sub { margin-top: 12px; }
.bhv-mono { font-size: 11px; line-height: 1.7; }
.bhv-hint { font-size: 10px; opacity: 0.7; line-height: 1.6; margin: 10px 0 0; }
.bhv-hint code { color: #FFB000; }

.bhv-queue { display: flex; flex-direction: column; gap: 4px; font-size: 10px; }
.bhv-queue-head, .bhv-queue-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1.3fr 1.6fr; gap: 8px; align-items: center; }
.bhv-queue-head { color: #6a8aa0; letter-spacing: 0.05em; padding-bottom: 4px; border-bottom: 1px solid rgba(0, 240, 255, 0.2); }
.bhv-queue-row { padding: 4px 0; }
.bhv-tx-timer-bar { display: inline-block; width: 40px; height: 6px; background: #0a0f16; vertical-align: middle; margin-right: 4px; }
.bhv-tx-timer-fill { height: 100%; background: #00F0FF; }

.bhv-timer-row { display: flex; align-items: center; gap: 6px; margin-bottom: 10px; }
.bhv-timer-label { font-size: 9px; color: #6a8aa0; letter-spacing: 0.08em; }
.bhv-timer-bar { display: inline-block; width: 160px; height: 8px; background: #0a0f16; vertical-align: middle; }
.bhv-timer-fill { display: block; height: 100%; background: #00F0FF; transition: width 0.1s linear; }
.bhv-timer-fill.bhv-warn { background: #FFB000; }
.bhv-timer-fill.bhv-crit { background: #FF2244; }
.bhv-timer-val { font-size: 11px; width: 42px; text-align: right; color: #00F0FF; }
.bhv-timer-val.bhv-warn { color: #FFB000; }
.bhv-timer-val.bhv-crit { color: #FF2244; }

.bhv-fragments { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
.bhv-fragment { font-size: 10px; padding: 6px 10px; background: #0a0f16; border: 1px solid #00F0FF; }

.bhv-harvest { font-size: 11px; line-height: 2; margin-bottom: 8px; }
.bhv-good { color: #2ed88a; }

.bhv-status-line { font-size: 10px; margin-bottom: 6px; opacity: 0.9; }
.bhv-status-line.good { color: #2ed88a; }
.bhv-status-line.bad { color: #FF2244; }
.bhv-cli-row { display: flex; align-items: center; gap: 8px; }
.bhv-prompt { color: #00F0FF; font-size: 13px; }
.bhv-cli-input { flex: 1; font-family: inherit; font-size: 12px; background: #0a0f16; border: 1px solid #00F0FF; color: #00F0FF; padding: 7px 9px; }
.bhv-cli-input:focus { outline: none; box-shadow: 0 0 6px rgba(0, 240, 255, 0.4); }
</style>
