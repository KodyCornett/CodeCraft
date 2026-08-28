<template>
    <div class="sgl-overlay">
        <div class="sgl-terminal">

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="sgl-topbar">
                <span>NODE: {{ nodeLabel }}</span>
                <span class="sgl-timer" :class="timerClass">TIME REMAINING: {{ timeDisplay }}</span>
                <button class="sgl-abort-btn" @click="onAbort">[ ABORT ]</button>
            </div>
            <div class="sgl-rule" />

            <!-- ── Objective banner — the rule the player has to apply this round ── -->
            <div class="sgl-objective">
                <span class="sgl-objective-label">OBJECTIVE</span>
                <span class="sgl-objective-text">{{ currentRound?.ruleText }}</span>
            </div>

            <!-- ── Trace progress dots ──────────────────────────────────────── -->
            <div class="sgl-progress-row">
                <span class="sgl-progress-caption">TRACE {{ Math.min(roundIndex + 1, difficulty.roundCount) }} / {{ difficulty.roundCount }}</span>
                <div class="sgl-progress-dots">
                    <span
                        v-for="i in difficulty.roundCount"
                        :key="i"
                        class="sgl-dot"
                        :class="{ 'dot--done': i - 1 < roundIndex, 'dot--current': i - 1 === roundIndex && status === 'playing' }"
                    />
                </div>
            </div>

            <div class="sgl-rule sgl-rule--light" />

            <!-- ── Body: candidate table + side reference panel ────────────── -->
            <div class="sgl-body">

                <div class="sgl-candidates">
                    <div class="sgl-cand-head">
                        <span class="sgl-col sgl-col--num">#</span>
                        <span class="sgl-col sgl-col--addr">ADDRESS</span>
                        <span v-for="f in currentRound?.fields" :key="f" class="sgl-col">{{ fieldLabel(f) }}</span>
                    </div>
                    <div
                        v-for="(c, idx) in currentRound?.candidates"
                        :key="c.id"
                        class="sgl-cand-row"
                        :class="rowClass(idx)"
                        @click="submitGuess(idx)"
                    >
                        <span class="sgl-col sgl-col--num">{{ idx + 1 }}</span>
                        <span class="sgl-col sgl-col--addr">{{ c.addr }}</span>
                        <span
                            v-for="f in currentRound?.fields"
                            :key="f"
                            class="sgl-col"
                            :class="fieldClass(c, f)"
                        >{{ fieldValue(c, f) }}</span>
                    </div>
                </div>

                <div class="sgl-side">
                    <div class="sgl-side-title">CMD REF</div>
                    <div class="sgl-side-hint">
                        PRESS <span class="sgl-side-key">[1&ndash;{{ currentRound?.candidates.length ?? '' }}]</span>
                        OR CLICK A ROW TO LOCK A TARGET.
                    </div>

                    <div class="sgl-panel-rule" />

                    <div class="sgl-legend">
                        <div class="sgl-legend-title">FIELD LEGEND</div>
                        <div v-for="f in currentRound?.fields" :key="f" class="sgl-legend-line">{{ fieldLegend(f) }}</div>
                    </div>

                    <div class="sgl-panel-rule" />

                    <div class="sgl-heat-block">
                        <span class="sgl-heat-label">TRACE HEAT</span>
                        <div class="sgl-heat-bar">
                            <div class="sgl-heat-fill" :class="heatClass" :style="{ width: traceHeat + '%' }" />
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Feedback flash ───────────────────────────────────────────── -->
            <div class="sgl-flash-row">
                <Transition name="sgl-flash-fade">
                    <span v-if="flashMsg" class="sgl-flash" :class="`flash--${flashType}`">{{ flashMsg }}</span>
                </Transition>
            </div>

            <!-- ── Outcome overlay ──────────────────────────────────────────── -->
            <div v-if="status !== 'playing'" class="sgl-outcome-overlay" :class="`outcome--${status}`">
                <div class="sgl-outcome-title">{{ status === 'success' ? 'TRACE COMPLETE' : 'CONNECTION LOST' }}</div>
                <div class="sgl-outcome-sub">{{ status === 'success' ? outcomeSuccessMsg : outcomeFailMsg }}</div>
                <button class="sgl-outcome-btn" @click="onDismiss">[ CONTINUE ]</button>
            </div>

        </div>
    </div>
</template>

<script setup>
/**
 * SIGNAL LOCK — candidate node-hack pool template (would-be generator key:
 * 'signal_lock'). NOT YET REGISTERED in generator/pool.js — this file is
 * built against the pool's exact contract (props/emits/reward formula
 * identical to ChecksumBreach.vue and CipherBreach.vue) so it's a one-line
 * addition to MINIGAME_POOL whenever it's actually approved for live
 * rotation, but for now it's reachable only through the dev-only
 * splice://dev/signal-lock-lab route via useDevSignalLock.js — same
 * isolation pattern already used for the composer/ and sit/ experiments.
 *
 * WHY THIS EXISTS: GridBreach, ChecksumBreach, and CipherBreach are all
 * "solve a puzzle that's entirely visible on screen" — scan-and-type,
 * arithmetic path-sum, and substitution guessing respectively. None of them
 * ask the player to read a few partial, real-feeling signals and reason
 * about which one is genuine versus a decoy the way PacketHijack's Phase 1
 * suspect grid does. SIGNAL LOCK brings that same investigative DNA into
 * the pool, compressed down to something resolvable in a few seconds per
 * round — because unlike PacketHijack this has to survive being played on
 * every node in the game, not just as an occasional set piece.
 *
 * THE LOOP: each round shows a short list of 4-7 candidate entries with 2-3
 * visible fields, plus one rule line describing what makes the real target
 * real. The player reads the rule, scans the rows, and picks the ONE
 * candidate that actually satisfies it — everything else fails on at least
 * one field. At higher ICE the rule becomes compound instead of the list
 * just getting longer, and one candidate becomes a genuine decoy that
 * satisfies the rule's surface but fails a flagged detail — the same
 * "looks right if you don't read carefully" trick ArchiveExtraction's fake
 * token/cipher pairs use. All data is shown up front; there's no typed
 * probe command to run and wait on, unlike PacketHijack — the friction
 * budget here has to be near zero.
 *
 * COST/EFFECT: reuses GridBreach's own timer formula verbatim (RAM widens
 * the total clock, OS widens the per-round grace baked into that base, CPU
 * vs ICE is asymmetric — a modest bonus for being over-geared, a compounding
 * penalty for being under). The one deliberate departure from GridBreach's
 * shape is round count: GridBreach maps sequence length 1:1 to ICE because
 * each of its steps is a fast visual scan; SIGNAL LOCK's rounds carry more
 * to read and reason about per round, so round count scales gentler
 * (roughly half of ICE, plus one) to land in a similar total playtime.
 * That's the one tuning knob this design pitch flagged as needing real
 * playtesting to calibrate — see ROUND_COUNT below. Reward math is the
 * shared computeRewardAmount()/outcomeSuccessMessage() every other pool
 * template already uses, completely unmodified.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { computeRewardAmount, outcomeSuccessMessage } from '../rewardFormula.js';

// ─── Props & emits — identical contract to every other pool entry ────────────
const props = defineProps({
    node:             { type: Object,  default: null    },
    resource:         { type: String,  default: 'creds' },  // 'creds' | 'tech' | 'uplink'
    playerCpu:        { type: Number,  default: 3       },
    playerRam:        { type: Number,  default: 2       },
    playerOs:         { type: Number,  default: 2       },
    playerFirewall:   { type: Number,  default: 1       },
    playerMaxUplink:  { type: Number,  default: 3       },
    bountyMultiplier: { type: Number,  default: 1.0     },
    paused:           { type: Boolean, default: false   },
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const nodeLabel = computed(() => props.node?.canvasId ?? props.node?.id ?? 'UNKNOWN');

// ─── Utilities ────────────────────────────────────────────────────────────────
function randInt(min, max) { return Math.floor(min + Math.random() * (max - min + 1)); }
function pick(arr)         { return arr[randInt(0, arr.length - 1)]; }
function parityOf(n)       { return n % 2 === 0 ? 'EVEN' : 'ODD'; }

function genAddr(lastOctet) {
    const octet = lastOctet ?? randInt(2, 253);
    return `10.${randInt(10, 99)}.${randInt(10, 99)}.${octet}`;
}

// ─── ICE tiering — same convention as every other pool template ──────────────
//   ICE 3-4  -> Tier 1     ICE 7-8  -> Tier 3
//   ICE 5-6  -> Tier 2     ICE 9-10 -> Tier 4
const MIN_ICE = 3;
const MAX_ICE = 10;
const iceLevel = computed(() => Math.min(MAX_ICE, Math.max(MIN_ICE, props.node?.ice ?? MIN_ICE)));

function tierForIce(ice) {
    if (ice <= 4) return 1;
    if (ice <= 6) return 2;
    if (ice <= 8) return 3;
    return 4;
}
const tier = computed(() => tierForIce(iceLevel.value));

const CANDIDATE_COUNT = { 1: 4, 2: 5, 3: 6, 4: 7 };
const WRONG_PENALTY_S = 4;

// ─── Difficulty — GridBreach's exact timer formula; round count scaled
// gentler than 1:1 with ICE (see docblock above). Threshold is implicitly
// "complete every round" — there's no partial-credit mid-run, matching how
// every other pool template behaves.
const difficulty = computed(() => {
    const ice = iceLevel.value;
    const roundCount = Math.max(2, Math.ceil(ice / 2) + 1);

    const baseTimer = 30 + (props.playerRam * 5) + Math.round(props.playerOs * 0.3);
    const diff      = props.playerCpu - ice;
    const timerMod  = diff >= 0 ? diff * 3 : -(diff * diff) * 2;

    return { roundCount, timer: Math.max(8, baseTimer + timerMod) };
});

// ─── Round generation ─────────────────────────────────────────────────────────
//
// Two rule templates, rotated randomly per round (parity only unlocks at
// Tier 2+, and only grows a flagged decoy at Tier 3+ — same escalation
// shape GridBreach uses for its locked/glitch row modifiers).

function buildExtremalRound(count) {
    const useSignal = Math.random() < 0.5;
    const candidates = Array.from({ length: count }, (_, i) => ({
        id: i,
        addr: genAddr(),
        session: Math.random() < 0.55 ? 'ACTIVE' : 'IDLE',
        latency: randInt(6, 58),
        signal: randInt(35, 98),
        flag: null,
    }));

    // Guarantee at least 2 ACTIVE candidates so the condition is meaningful.
    let activeCount = candidates.filter(c => c.session === 'ACTIVE').length;
    for (const c of candidates) {
        if (activeCount >= 2) break;
        if (c.session === 'IDLE') { c.session = 'ACTIVE'; activeCount++; }
    }

    const activeOnes = candidates.filter(c => c.session === 'ACTIVE');
    let correct;
    if (useSignal) {
        correct = activeOnes.reduce((a, b) => (b.signal > a.signal ? b : a));
        activeOnes.forEach(c => { if (c !== correct && c.signal === correct.signal) c.signal -= randInt(1, 4); });
    } else {
        correct = activeOnes.reduce((a, b) => (b.latency < a.latency ? b : a));
        activeOnes.forEach(c => { if (c !== correct && c.latency === correct.latency) c.latency += randInt(1, 4); });
    }

    return {
        ruleText: useSignal
            ? 'TARGET = HIGHEST SIGNAL STRENGTH, ACTIVE SESSION'
            : 'TARGET = LOWEST LATENCY, ACTIVE SESSION',
        fields: useSignal ? ['signal', 'session'] : ['latency', 'session'],
        candidates,
        correctIndex: candidates.indexOf(correct),
        explainFail(c) {
            if (c.session !== 'ACTIVE') return `SESSION: ${c.session}`;
            return useSignal ? `SIGNAL ${c.signal}% NOT HIGHEST` : `LATENCY ${c.latency}ms NOT LOWEST`;
        },
    };
}

function buildParityRound(count, withSpoof) {
    const candidates = Array.from({ length: count }, (_, i) => {
        const octet = randInt(2, 253);
        return {
            id: i,
            addr: genAddr(octet),
            octet,
            octetParity: parityOf(octet),
            checksumVal: randInt(0, 255),
            flag: null,
        };
    });

    // Force exactly one true parity match by construction, not by chance.
    const correctIndex = randInt(0, count - 1);
    candidates.forEach((c, i) => {
        const shouldMatch = i === correctIndex;
        let val = c.checksumVal;
        let guard = 0;
        while ((parityOf(val) === c.octetParity) !== shouldMatch && guard < 50) {
            val = randInt(0, 255);
            guard++;
        }
        c.checksumVal = val;
    });

    let ruleText = 'TARGET = CHECKSUM PARITY MATCHES OCTET PARITY';

    if (withSpoof) {
        // Pick a different, currently-mismatching candidate and force its
        // checksum to ALSO match parity, then flag it — a candidate that
        // looks right if you only check the numbers and skip the flag.
        const spoofPool = candidates
            .map((c, i) => i)
            .filter(i => i !== correctIndex && parityOf(candidates[i].checksumVal) !== candidates[i].octetParity);
        if (spoofPool.length) {
            const spoofIdx = pick(spoofPool);
            const target = candidates[spoofIdx];
            let val = target.checksumVal;
            let guard = 0;
            while (parityOf(val) !== target.octetParity && guard < 50) { val = randInt(0, 255); guard++; }
            target.checksumVal = val;
            target.flag = 'SPOOFED';
        }
        ruleText += ', AND NOT FLAGGED';
    }

    return {
        ruleText,
        fields: ['octet', 'checksum', 'flag'],
        candidates,
        correctIndex,
        explainFail(c) {
            if (c.flag) return `FLAGGED: ${c.flag}`;
            return `PARITY MISMATCH (CHK ${parityOf(c.checksumVal)} vs OCTET ${c.octetParity})`;
        },
    };
}

function generateRound() {
    const t = tier.value;
    const count = CANDIDATE_COUNT[t];
    const pool = t >= 2 ? ['extremal', 'extremal', 'parity'] : ['extremal'];
    const template = pick(pool);
    if (template === 'parity') return buildParityRound(count, t >= 3);
    return buildExtremalRound(count);
}

// ─── Field display helpers ────────────────────────────────────────────────────
function fieldLabel(f) {
    return { latency: 'LATENCY', signal: 'SIGNAL', session: 'SESSION', octet: 'OCTET', checksum: 'CHECKSUM', flag: 'FLAG' }[f] ?? f.toUpperCase();
}
function fieldValue(c, f) {
    if (f === 'latency')  return `${c.latency}ms`;
    if (f === 'signal')   return `${c.signal}%`;
    if (f === 'session')  return c.session;
    if (f === 'octet')    return `${c.octet} (${c.octetParity})`;
    if (f === 'checksum') return `0x${c.checksumVal.toString(16).toUpperCase().padStart(2, '0')} (${parityOf(c.checksumVal)})`;
    if (f === 'flag')     return c.flag ?? '—';
    return '';
}
function fieldClass(c, f) {
    if (f === 'session') return c.session === 'ACTIVE' ? 'val--active' : 'val--idle';
    if (f === 'flag' && c.flag) return 'val--flagged';
    return '';
}
function fieldLegend(f) {
    return {
        latency:  'LATENCY — response time in ms, lower is fresher',
        signal:   'SIGNAL — link strength, 0–98%',
        session:  'SESSION — ACTIVE or IDLE',
        octet:    'OCTET — last address segment + its parity',
        checksum: 'CHECKSUM — packet hash + its parity',
        flag:     'FLAG — integrity marker; SPOOFED disqualifies',
    }[f] ?? '';
}

// ─── Game state ───────────────────────────────────────────────────────────────
const status       = ref('playing'); // 'playing' | 'success' | 'failed'
const roundIndex    = ref(0);
const currentRound  = ref(null);
const timeLeft      = ref(0);
const traceHeat     = ref(15); // 0-100, cosmetic tension dressing only — never gates success/failure
const rowFlash      = ref(null); // { idx, type } | null
const flashMsg      = ref('');
const flashType     = ref('');
let flashTimer      = null;

const timeDisplay = computed(() => {
    const t = Math.max(0, Math.ceil(timeLeft.value));
    const m = Math.floor(t / 60).toString().padStart(2, '0');
    const s = (t % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const timerClass = computed(() => {
    const pct = timeLeft.value / difficulty.value.timer;
    if (pct <= 0.15) return 'timer--critical';
    if (pct <= 0.35) return 'timer--warn';
    return '';
});

const heatClass = computed(() => {
    if (traceHeat.value >= 75) return 'heat--critical';
    if (traceHeat.value >= 45) return 'heat--warn';
    return '';
});

function bumpHeat(amount) { traceHeat.value = Math.min(97, traceHeat.value + amount); }
function coolHeat(amount) { traceHeat.value = Math.max(5, traceHeat.value - amount); }

function setRowFlash(idx, type) {
    rowFlash.value = { idx, type };
    setTimeout(() => { rowFlash.value = null; }, 450);
}
function rowClass(idx) {
    return rowFlash.value?.idx === idx ? [`row--flash-${rowFlash.value.type}`] : [];
}
function showFlash(msg, type) {
    clearTimeout(flashTimer);
    flashMsg.value  = msg;
    flashType.value = type;
    flashTimer = setTimeout(() => { flashMsg.value = ''; }, 1800);
}

// ─── Reward — shared with every other binary-outcome pool template ───────────
const rewardAmount = computed(() => computeRewardAmount({
    resource:         props.resource,
    ice:              status.value === 'success' ? iceLevel.value : 0,
    bountyMultiplier: props.bountyMultiplier,
    playerMaxUplink:  props.playerMaxUplink,
}));
const outcomeSuccessMsg = computed(() => outcomeSuccessMessage(props.resource, rewardAmount.value));
const outcomeFailMsg    = 'TRACE NOT COMPLETED — ICE HELD — NO YIELD';

// ─── Submit ───────────────────────────────────────────────────────────────────
function submitGuess(idx) {
    if (status.value !== 'playing' || !currentRound.value) return;
    const round     = currentRound.value;
    const candidate = round.candidates[idx];
    if (!candidate) return;

    if (idx === round.correctIndex) {
        setRowFlash(idx, 'correct');
        coolHeat(4);
        roundIndex.value++;
        if (roundIndex.value >= difficulty.value.roundCount) {
            showFlash(`TARGET CONFIRMED — TRACE COMPLETE`, 'correct');
            triggerSuccess();
        } else {
            showFlash(`TARGET CONFIRMED — TRACE ${roundIndex.value}/${difficulty.value.roundCount} LOCKED`, 'correct');
            currentRound.value = generateRound();
        }
    } else {
        setRowFlash(idx, 'wrong');
        bumpHeat(8);
        showFlash(`REJECTED — ${round.explainFail(candidate)}`, 'wrong');
        timeLeft.value = Math.max(0, timeLeft.value - WRONG_PENALTY_S);
        if (timeLeft.value <= 0) triggerFail();
    }
}

// ─── Outcome ──────────────────────────────────────────────────────────────────
function triggerSuccess() { status.value = 'success'; }
function triggerFail()    { status.value = 'failed'; }

function onDismiss() {
    if (status.value === 'success') {
        emit('complete', { resource: props.resource, amount: rewardAmount.value, completionPct: 1.0 });
    } else {
        emit('failed', { resource: props.resource, amount: 0 });
    }
}
function onAbort() { emit('abort'); }

// ─── Keyboard — digit keys 1..N select a row; no typing required anywhere
// in this template, deliberately lower-friction than GridBreach's typed
// coordinates since this has to be comfortable to run dozens of times. ────
function onKeydown(e) {
    if (status.value !== 'playing' || !currentRound.value) return;
    const n = parseInt(e.key, 10);
    if (!Number.isNaN(n) && n >= 1 && n <= currentRound.value.candidates.length) {
        submitGuess(n - 1);
    }
}

// ─── Timer tick ───────────────────────────────────────────────────────────────
let tickHandle = null;

onMounted(() => {
    timeLeft.value     = difficulty.value.timer;
    currentRound.value = generateRound();
    tickHandle = setInterval(() => {
        if (status.value !== 'playing' || props.paused) return;
        timeLeft.value--;
        if (timeLeft.value <= 0) triggerFail();
    }, 1000);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    clearInterval(tickHandle);
    clearTimeout(flashTimer);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<style scoped>
.sgl-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: rgba(4, 2, 10, 0.94);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
}

.sgl-terminal {
    width: min(980px, 94vw);
    max-height: 92vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 22px 28px 26px;
    box-sizing: border-box;
    background: #0a0714;
    color: #c4a6ff;
    border: 1px solid rgba(183,148,246,0.28);
    box-shadow: 0 0 40px rgba(183,148,246,0.08);
    position: relative;
}

/* ── Top bar ───────────────────────────────────────────────────────────────── */
.sgl-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-size: 12px;
    letter-spacing: 0.1em;
    color: rgba(196,166,255,0.6);
    flex-shrink: 0;
}
.sgl-timer { color: #b794f6; }
.sgl-timer.timer--warn     { color: #FFB300; }
.sgl-timer.timer--critical { color: #ff3333; animation: sgl-blink 0.6s ease infinite alternate; }

.sgl-abort-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.14em;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.35);
    color: rgba(255,51,51,0.75);
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.15s;
}
.sgl-abort-btn:hover { background: rgba(255,51,51,0.08); border-color: #ff3333; color: #ff3333; }

.sgl-rule       { height: 1px; background: rgba(183,148,246,0.18); flex-shrink: 0; }
.sgl-rule--light{ height: 1px; background: rgba(183,148,246,0.08); flex-shrink: 0; }

/* ── Objective banner ─────────────────────────────────────────────────────── */
.sgl-objective {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 14px;
    border: 1px solid rgba(183,148,246,0.35);
    background: rgba(183,148,246,0.06);
    flex-shrink: 0;
}
.sgl-objective-label { font-size: 9px; letter-spacing: 0.18em; color: rgba(196,166,255,0.5); }
.sgl-objective-text  { font-size: 15px; letter-spacing: 0.04em; color: #e4d6ff; font-weight: 600; }

/* ── Progress dots ─────────────────────────────────────────────────────────── */
.sgl-progress-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.sgl-progress-caption { font-size: 9px; letter-spacing: 0.12em; color: rgba(196,166,255,0.45); }
.sgl-progress-dots { display: flex; gap: 6px; }
.sgl-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    background: rgba(183,148,246,0.15);
    border: 1px solid rgba(183,148,246,0.3);
}
.dot--done    { background: #00ff9d; border-color: #00ff9d; }
.dot--current { background: #b794f6; border-color: #e4d6ff; box-shadow: 0 0 8px rgba(183,148,246,0.7); animation: sgl-dot-pulse 1s ease-in-out infinite; }

/* ── Body ──────────────────────────────────────────────────────────────────── */
.sgl-body {
    flex: 1;
    display: flex;
    gap: 14px;
    min-height: 0;
}

.sgl-candidates {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    border: 1px solid rgba(183,148,246,0.18);
    background: rgba(183,148,246,0.02);
}

.sgl-cand-head, .sgl-cand-row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 7px 12px;
}
.sgl-cand-head {
    border-bottom: 1px solid rgba(183,148,246,0.18);
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(196,166,255,0.4);
}
.sgl-cand-row {
    font-size: 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(183,148,246,0.07);
    transition: background 0.1s;
}
.sgl-cand-row:last-child { border-bottom: none; }
.sgl-cand-row:hover { background: rgba(183,148,246,0.08); }

.sgl-col { flex: 1; min-width: 0; }
.sgl-col--num  { flex: 0 0 26px; color: rgba(196,166,255,0.35); }
.sgl-col--addr { flex: 0 0 150px; color: #e4d6ff; letter-spacing: 0.03em; }

.val--active  { color: #00ff9d; }
.val--idle    { color: rgba(196,166,255,0.35); }
.val--flagged { color: #ff3333; font-weight: 700; }

.row--flash-correct { background: rgba(0,255,157,0.15) !important; }
.row--flash-wrong    { background: rgba(255,51,51,0.15) !important; }

/* ── Side panel ────────────────────────────────────────────────────────────── */
.sgl-side {
    width: 230px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(183,148,246,0.18);
    background: rgba(183,148,246,0.02);
    overflow-y: auto;
}
.sgl-side-title { font-size: 10px; letter-spacing: 0.14em; color: rgba(196,166,255,0.5); }
.sgl-side-hint  { font-size: 10px; line-height: 1.6; color: rgba(196,166,255,0.55); }
.sgl-side-key   { color: #e4d6ff; }

.sgl-panel-rule { height: 1px; background: rgba(183,148,246,0.12); }

.sgl-legend-title { font-size: 9px; letter-spacing: 0.12em; color: rgba(196,166,255,0.4); margin-bottom: 5px; }
.sgl-legend-line  { font-size: 9px; line-height: 1.6; color: rgba(196,166,255,0.45); }

.sgl-heat-block { display: flex; flex-direction: column; gap: 5px; }
.sgl-heat-label { font-size: 9px; letter-spacing: 0.12em; color: rgba(196,166,255,0.4); }
.sgl-heat-bar { height: 6px; background: rgba(183,148,246,0.08); overflow: hidden; }
.sgl-heat-fill { height: 100%; background: #b794f6; transition: width 0.25s ease, background 0.25s; }
.sgl-heat-fill.heat--warn     { background: #FFB300; }
.sgl-heat-fill.heat--critical { background: #ff3333; }

/* ── Feedback flash ────────────────────────────────────────────────────────── */
.sgl-flash-row { min-height: 18px; flex-shrink: 0; }
.sgl-flash { font-size: 11px; letter-spacing: 0.06em; }
.flash--correct { color: #00ff9d; }
.flash--wrong   { color: #ff3333; }

.sgl-flash-fade-enter-active, .sgl-flash-fade-leave-active { transition: opacity 0.25s; }
.sgl-flash-fade-enter-from,   .sgl-flash-fade-leave-to     { opacity: 0; }

/* ── Outcome overlay ───────────────────────────────────────────────────────── */
.sgl-outcome-overlay {
    position: absolute;
    inset: 0;
    background: rgba(10,7,20,0.96);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    text-align: center;
    padding: 0 24px;
}
.sgl-outcome-title { font-size: 26px; letter-spacing: 0.14em; }
.outcome--success .sgl-outcome-title { color: #00ff9d; text-shadow: 0 0 24px rgba(0,255,157,0.5); }
.outcome--failed  .sgl-outcome-title { color: #ff3333; text-shadow: 0 0 24px rgba(255,51,51,0.5); }
.sgl-outcome-sub { font-size: 13px; letter-spacing: 0.06em; color: rgba(196,166,255,0.7); }

.sgl-outcome-btn {
    margin-top: 10px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(183,148,246,0.4);
    color: #c4a6ff;
    padding: 9px 28px;
    cursor: pointer;
    transition: all 0.15s;
}
.sgl-outcome-btn:hover { background: rgba(183,148,246,0.08); border-color: #e4d6ff; color: #e4d6ff; }

@keyframes sgl-blink { from { opacity: 1; } to { opacity: 0.4; } }
@keyframes sgl-dot-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>
