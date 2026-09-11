<template>
    <div class="sgl-overlay">
        <div class="sgl-terminal">

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="sgl-topbar">
                <span>NODE: {{ nodeLabel }}</span>
                <span class="sgl-tally">
                    ROUND {{ Math.min(roundsPlayed + 1, matchConfig.rounds) }}/{{ matchConfig.rounds }}
                    &nbsp;·&nbsp;
                    <span class="tally--correct">{{ correctCount }} RIGHT</span>
                    /
                    <span class="tally--wrong">{{ wrongCount }} WRONG</span>
                </span>
                <button class="sgl-abort-btn" @click="onAbort">[ ABORT ]</button>
            </div>
            <div class="sgl-rule" />

            <!-- ── Standing directive + target signature — persists all match ── -->
            <div class="sgl-objective">
                <span class="sgl-objective-label">STANDING DIRECTIVE</span>
                <span class="sgl-objective-text">
                    STABLE SIGNAL &rarr; {{ standingDirective?.STABLE?.toUpperCase() }}.
                    UNSTABLE SIGNAL &rarr; {{ standingDirective?.UNSTABLE?.toUpperCase() }}.
                </span>
                <span class="sgl-objective-label" style="margin-top: 4px;">SIGNATURE MATCH</span>
                <span class="sgl-objective-text sgl-objective-text--sig">{{ currentRound?.targetPrefixLabel }}</span>
            </div>

            <!-- ── Round outcome dots ───────────────────────────────────────── -->
            <div class="sgl-progress-row">
                <span class="sgl-progress-caption">
                    NEED {{ matchConfig.need }} TO CLEAR &middot; FAILS AT {{ matchConfig.loseAt }} WRONG
                </span>
                <div class="sgl-progress-dots">
                    <span
                        v-for="i in matchConfig.rounds"
                        :key="i"
                        class="sgl-dot"
                        :class="dotClass(i - 1)"
                    />
                </div>
            </div>

            <div class="sgl-rule sgl-rule--light" />

            <!-- ── Body: active signals table + side reference panel ───────── -->
            <div class="sgl-body">

                <div class="sgl-candidates">
                    <div class="sgl-cand-head">
                        <span class="sgl-col sgl-col--num">ID</span>
                        <span class="sgl-col sgl-col--addr">MAC</span>
                        <span class="sgl-col">SIG HISTORY</span>
                        <span class="sgl-col sgl-col--ping">PING</span>
                    </div>
                    <div
                        v-for="row in currentRound?.rows"
                        :key="row.label"
                        class="sgl-cand-row"
                        :class="rowFlashClass(row.label)"
                    >
                        <span class="sgl-col sgl-col--num">{{ row.label }}</span>
                        <span class="sgl-col sgl-col--addr" :class="{ 'val--unscanned': !row.macRevealed }">
                            {{ row.macRevealed ? row.mac : '░░:░░:░░:░░:░░:░░' }}
                        </span>
                        <span class="sgl-col" :class="{ 'val--unscanned': !row.sigHistory.length }">
                            {{ row.sigHistory.length ? row.sigHistory.join(' → ') : '--' }}
                        </span>
                        <span class="sgl-col sgl-col--ping" :class="{ 'val--unscanned': row.pingValue === null }">
                            {{ row.pingValue !== null ? `${row.pingValue}ms` : '--' }}
                        </span>
                    </div>
                </div>

                <div class="sgl-side">
                    <div class="sgl-side-title">COLUMN LEGEND</div>
                    <div class="sgl-legend">
                        <div class="sgl-legend-line">MAC — hardware address. <b>scan mac</b> reveals it for every row.</div>
                        <div class="sgl-legend-line">SIG HISTORY — recent signal samples. <b>scan sig</b> takes a reading; run it again to watch the trend continue.</div>
                        <div class="sgl-legend-line">PING — round-trip latency. <b>scan ping</b> reveals it.</div>
                    </div>

                    <div class="sgl-panel-rule" />

                    <div class="sgl-side-title">COMMANDS</div>
                    <div class="sgl-legend">
                        <div class="sgl-legend-line"><b>scan</b> mac / sig / ping</div>
                        <div class="sgl-legend-line"><b>log</b> &lt;id&gt; — benign, record and move on</div>
                        <div class="sgl-legend-line"><b>jam</b> &lt;id&gt; — disrupt an active threat</div>
                        <div class="sgl-legend-line" style="opacity: 0.6;">Committing is final — no changing a call once made.</div>
                    </div>
                </div>

            </div>

            <!-- ── Feedback flash ───────────────────────────────────────────── -->
            <div class="sgl-flash-row">
                <Transition name="sgl-flash-fade">
                    <span v-if="flashMsg" class="sgl-flash" :class="`flash--${flashType}`">{{ flashMsg }}</span>
                </Transition>
            </div>

            <!-- ── Command input — typed, with arrow-navigable autocomplete ─── -->
            <div class="sgl-cmdbar">
                <span class="sgl-cmd-prompt">&gt;</span>
                <div class="sgl-cmd-input-wrap">
                    <input
                        ref="cmdInputEl"
                        v-model="inputText"
                        class="sgl-cmd-input"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="scan mac / scan sig / scan ping / log &lt;id&gt; / jam &lt;id&gt;"
                        :disabled="status !== 'playing'"
                        @keydown="onInputKeydown"
                    />
                    <div v-if="suggestions.length" class="sgl-suggest-list">
                        <div
                            v-for="(s, i) in suggestions"
                            :key="s"
                            class="sgl-suggest-item"
                            :class="{ 'suggest--active': i === highlightedIndex }"
                            @mousedown.prevent="acceptSuggestion(s)"
                        >{{ s }}</div>
                    </div>
                </div>
            </div>

            <!-- ── Outcome overlay ──────────────────────────────────────────── -->
            <div v-if="status !== 'playing'" class="sgl-outcome-overlay" :class="`outcome--${status}`">
                <div class="sgl-outcome-title">{{ status === 'success' ? 'SIGNAL RESOLVED' : 'MISCALL — LOST THE THREAD' }}</div>
                <div class="sgl-outcome-sub">{{ status === 'success' ? outcomeSuccessMsg : outcomeFailMsg }}</div>
                <button class="sgl-outcome-btn" @click="onDismiss">[ CONTINUE ]</button>
            </div>

        </div>
    </div>
</template>

<script setup>
/**
 * SIGNAL LOCK — candidate node-hack pool template (would-be generator key:
 * 'signal_lock'). NOT YET REGISTERED in generator/pool.js — reachable only
 * through splice://dev/minigames (DevMinigameLauncher.vue) via
 * useDevSignalLock.js, same isolation pattern as the composer/ and sit/
 * experiments.
 *
 * ── REBUILD NOTE (v2) ──────────────────────────────────────────────────────
 * v1 was a rule-matching table: read one rule line, scan a fully-visible
 * candidate table, click the row that satisfies it. Every field was handed
 * to the player up front — mechanically that's a multiple-choice quiz with
 * a hacking skin, not an investigation. This rebuild replaces that shape
 * entirely, keeping only the pool-template contract (props/emits/reward
 * math) and the terminal aesthetic.
 *
 * THE NEW LOOP — "isolate a WiFi device and call its intent":
 *   1. Each round shows a table of devices (rows), IDs only. Every column
 *      (MAC / SIG HISTORY / PING) starts blank — nothing is free. The
 *      player types scan commands (`scan mac`, `scan sig`, `scan ping`) to
 *      reveal one column at a time, for every row at once. Commands are
 *      unlimited/free to re-run — the friction is procedural (you must
 *      type to see anything), not resource-metered.
 *   2. The round's target is identified by two independent, partial clues
 *      shown in the top panel: a partial MAC prefix ("SIGNATURE MATCH")
 *      and — at higher ICE — a shared-prefix decoy that forces reading
 *      PING as a tiebreaker, since one clue alone stops being enough.
 *      SIG HISTORY isn't for finding the target — it's read on whichever
 *      row you believe is real, to decide the response.
 *   3. A "STANDING DIRECTIVE" (fixed for the whole match, randomized each
 *      time this component mounts) maps STABLE/UNSTABLE signal behavior to
 *      LOG or JAM. The player commits with `log <id>` or `jam <id>` —
 *      irreversible, and wrong either on the device or the verb both count
 *      as a miss the same way (picking the wrong row is exactly as wrong
 *      as picking the right row and calling it backwards).
 *   4. Best-of-N per match instead of one shot: ICE sets the round count
 *      and win/lose thresholds (see MATCH_CONFIG below), capped at 5 rounds
 *      — past that, ICE 6-8 scale difficulty through decoy density and how
 *      close the tiebreak PING ranges sit, not more rounds. There is no
 *      timer anywhere in this template; a round only ends when the player
 *      commits a call.
 *
 * COST/EFFECT — deliberately NOT wired to playerCpu/playerRam/playerOs in
 * this version. Every other pool template uses those stats to modulate a
 * timer that no longer exists here. They're still accepted as props (contract
 * parity with the rest of the pool) but currently unused — flagged as an
 * open question for whoever approves this for the live pool: should a
 * stronger rig shrink decoy count / shared-prefix count instead? Left out
 * for now rather than inventing an un-discussed mechanic.
 *
 * Reward math is the same shared computeRewardAmount()/outcomeSuccessMessage()
 * every other pool template uses, unmodified — full reward on a match win,
 * nothing on a loss, no partial credit for a 3-2 win vs a 3-0 win.
 */
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { computeRewardAmount, outcomeSuccessMessage } from '../rewardFormula.js';
import {
    clampIce,
    matchConfigForIce,
    generateSignalRound,
    genSigSeed,
    genSigNext,
    randInt,
    parseCommandState,
} from './signalLockLogic.js';

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
    paused:           { type: Boolean, default: false   }, // no timer in this template — accepted for contract parity, currently unused
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const nodeLabel = computed(() => props.node?.canvasId ?? props.node?.id ?? 'UNKNOWN');

// ─── ICE — this game's actual ceiling is 8, not 10 (see signalLockLogic.js) ──
const iceLevel   = computed(() => clampIce(props.node?.ice));
const matchConfig = computed(() => matchConfigForIce(iceLevel.value));

// ─── Game state ───────────────────────────────────────────────────────────────
const status            = ref('playing'); // 'playing' | 'success' | 'failed'
const currentRound      = ref(null);
const roundsPlayed      = ref(0);
const correctCount      = ref(0);
const wrongCount        = ref(0);
const roundOutcomes     = ref([]); // 'correct' | 'wrong', one per resolved round
const standingDirective = ref(null); // { STABLE: 'log'|'jam', UNSTABLE: the other }
const flashMsg          = ref('');
const flashType         = ref('');
const flashRow          = ref(null); // row label most recently flashed, or null
let flashTimer          = null;

function dotClass(i) {
    if (roundOutcomes.value[i] === 'correct') return 'dot--correct';
    if (roundOutcomes.value[i] === 'wrong')    return 'dot--wrong';
    if (i === roundsPlayed.value && status.value === 'playing') return 'dot--current';
    return '';
}
function rowFlashClass(label) {
    return flashRow.value === label ? [`row--flash-${flashType.value}`] : [];
}
function showFlash(msg, type, rowLabel = null) {
    clearTimeout(flashTimer);
    flashMsg.value  = msg;
    flashType.value = type;
    flashRow.value  = rowLabel;
    flashTimer = setTimeout(() => { flashMsg.value = ''; flashRow.value = null; }, 2200);
}

// ─── Reward — shared with every other binary-outcome pool template ───────────
const rewardAmount = computed(() => computeRewardAmount({
    resource:         props.resource,
    ice:              status.value === 'success' ? iceLevel.value : 0,
    bountyMultiplier: props.bountyMultiplier,
    playerMaxUplink:  props.playerMaxUplink,
}));
const outcomeSuccessMsg = computed(() => outcomeSuccessMessage(props.resource, rewardAmount.value));
const outcomeFailMsg    = 'IDENTIFICATION FAILED — TOO MANY BAD CALLS — NO YIELD';

// ─── Command input + autocomplete ────────────────────────────────────────────
// Token/suggestion rules live in parseCommandState() (signalLockLogic.js) so
// they're unit-tested directly — this just feeds it the live input text and
// this round's row labels.
const inputText        = ref('');
const highlightedIndex = ref(0);
const cmdInputEl       = ref(null);

const commandState = computed(() => parseCommandState(
    inputText.value,
    currentRound.value?.rows.map(r => r.label) ?? [],
));
const phase       = computed(() => commandState.value.phase);
const verbToken   = computed(() => commandState.value.verbToken);
const argToken    = computed(() => commandState.value.argToken);
const suggestions = computed(() => commandState.value.suggestions);

watch(suggestions, () => { highlightedIndex.value = 0; });
watch(status, async (s) => {
    if (s === 'playing') { await nextTick(); cmdInputEl.value?.focus(); }
});

function acceptSuggestion(sug) {
    if (phase.value === 'verb') {
        inputText.value = `${sug} `;
    } else {
        executeCommand(verbToken.value, sug);
        inputText.value = '';
    }
    cmdInputEl.value?.focus();
}

function onInputKeydown(e) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (suggestions.value.length) highlightedIndex.value = (highlightedIndex.value + 1) % suggestions.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (suggestions.value.length) highlightedIndex.value = (highlightedIndex.value - 1 + suggestions.value.length) % suggestions.value.length;
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (suggestions.value.length) {
            acceptSuggestion(suggestions.value[highlightedIndex.value]);
        } else if (phase.value === 'arg' && verbToken.value && argToken.value) {
            executeCommand(verbToken.value, argToken.value);
            inputText.value = '';
        } else if (commandState.value.tokens.length) {
            showFlash('UNKNOWN COMMAND', 'wrong');
        }
    } else if (e.key === 'Escape') {
        inputText.value = '';
    }
}

function executeCommand(verb, argRaw) {
    if (status.value !== 'playing' || !currentRound.value) return;
    if (verb === 'scan') {
        const sub = (argRaw || '').toLowerCase();
        if (sub === 'mac') revealMac();
        else if (sub === 'sig') revealSig();
        else if (sub === 'ping') revealPing();
        else showFlash('UNKNOWN SCAN TARGET', 'wrong');
        return;
    }
    if (verb === 'log' || verb === 'jam') {
        const target = (argRaw || '').toUpperCase();
        const row = currentRound.value.rows.find(r => r.label === target);
        if (!row) { showFlash(`NO SUCH TARGET: ${target}`, 'wrong'); return; }
        commit(verb, row);
        return;
    }
    showFlash('UNKNOWN COMMAND', 'wrong');
}

// ─── Scans — free/unlimited; reveal one column across every row at once ─────
function revealMac() {
    currentRound.value.rows.forEach(r => { r.macRevealed = true; });
}
function revealSig() {
    currentRound.value.rows.forEach(r => {
        if (r.sigHistory.length === 0) {
            r.sigHistory = genSigSeed(r.sigCategory);
        } else {
            r.sigHistory.push(genSigNext(r.sigCategory, r.sigHistory[r.sigHistory.length - 1]));
            if (r.sigHistory.length > 4) r.sigHistory.shift();
        }
    });
}
function revealPing() {
    currentRound.value.rows.forEach(r => {
        r.pingValue = r.pingBase + randInt(-r.pingJitter, r.pingJitter);
    });
}

// ─── Commit — irreversible; wrong device and wrong verb are equally a miss ──
function commit(verb, row) {
    const correctDevice = row.isTarget;
    const expectedVerb  = correctDevice ? standingDirective.value[row.sigCategory] : null;
    const correctVerb   = correctDevice && verb === expectedVerb;
    const roundCorrect  = correctDevice && correctVerb;

    if (roundCorrect) {
        correctCount.value++;
        roundOutcomes.value.push('correct');
        showFlash(`${verb.toUpperCase()} CONFIRMED — CORRECT CALL ON ${row.label}`, 'correct', row.label);
    } else if (!correctDevice) {
        wrongCount.value++;
        roundOutcomes.value.push('wrong');
        showFlash(`WRONG TARGET — ${row.label} DID NOT MATCH THE SIGNATURE`, 'wrong', row.label);
    } else {
        wrongCount.value++;
        roundOutcomes.value.push('wrong');
        showFlash(`WRONG CALL — ${row.label} WAS ${row.sigCategory}, SHOULD HAVE BEEN ${expectedVerb.toUpperCase()}`, 'wrong', row.label);
    }

    roundsPlayed.value++;

    if (correctCount.value >= matchConfig.value.need) {
        status.value = 'success';
    } else if (wrongCount.value >= matchConfig.value.loseAt) {
        status.value = 'failed';
    } else {
        currentRound.value = generateSignalRound(iceLevel.value);
    }
}

// ─── Outcome ──────────────────────────────────────────────────────────────────
function onDismiss() {
    if (status.value === 'success') {
        emit('complete', { resource: props.resource, amount: rewardAmount.value, completionPct: 1.0 });
    } else {
        emit('failed', { resource: props.resource, amount: 0 });
    }
}
function onAbort() { emit('abort'); }

onMounted(() => {
    const stableVerb = Math.random() < 0.5 ? 'log' : 'jam';
    standingDirective.value = { STABLE: stableVerb, UNSTABLE: stableVerb === 'log' ? 'jam' : 'log' };
    currentRound.value = generateSignalRound(iceLevel.value);
    nextTick(() => cmdInputEl.value?.focus());
});

onUnmounted(() => {
    clearTimeout(flashTimer);
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
.sgl-tally { font-size: 11px; }
.tally--correct { color: #00ff9d; }
.tally--wrong   { color: #ff3333; }

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

/* ── Standing directive / signature banner ────────────────────────────────── */
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
.sgl-objective-text  { font-size: 13px; letter-spacing: 0.03em; color: #e4d6ff; font-weight: 600; }
.sgl-objective-text--sig { font-size: 15px; letter-spacing: 0.06em; }

/* ── Round outcome dots ────────────────────────────────────────────────────── */
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
.dot--correct { background: #00ff9d; border-color: #00ff9d; }
.dot--wrong   { background: #ff3333; border-color: #ff3333; }
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
    border-bottom: 1px solid rgba(183,148,246,0.07);
    transition: background 0.1s;
}
.sgl-cand-row:last-child { border-bottom: none; }

.sgl-col { flex: 1; min-width: 0; }
.sgl-col--num  { flex: 0 0 30px; color: rgba(196,166,255,0.35); }
.sgl-col--addr { flex: 0 0 150px; color: #e4d6ff; letter-spacing: 0.03em; }
.sgl-col--ping { flex: 0 0 70px; }

.val--unscanned { color: rgba(196,166,255,0.25); }

.row--flash-correct { background: rgba(0,255,157,0.15) !important; }
.row--flash-wrong    { background: rgba(255,51,51,0.15) !important; }

/* ── Side panel ────────────────────────────────────────────────────────────── */
.sgl-side {
    width: 250px;
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

.sgl-panel-rule { height: 1px; background: rgba(183,148,246,0.12); }

.sgl-legend { display: flex; flex-direction: column; gap: 6px; }
.sgl-legend-line { font-size: 9.5px; line-height: 1.6; color: rgba(196,166,255,0.55); }
.sgl-legend-line b { color: #e4d6ff; }

/* ── Feedback flash ────────────────────────────────────────────────────────── */
.sgl-flash-row { min-height: 18px; flex-shrink: 0; }
.sgl-flash { font-size: 11px; letter-spacing: 0.06em; }
.flash--correct { color: #00ff9d; }
.flash--wrong   { color: #ff3333; }

.sgl-flash-fade-enter-active, .sgl-flash-fade-leave-active { transition: opacity 0.25s; }
.sgl-flash-fade-enter-from,   .sgl-flash-fade-leave-to     { opacity: 0; }

/* ── Command input + autocomplete ─────────────────────────────────────────── */
.sgl-cmdbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    border: 1px solid rgba(183,148,246,0.3);
    background: rgba(183,148,246,0.04);
    padding: 8px 12px;
}
.sgl-cmd-prompt { color: #00ff9d; font-size: 13px; }
.sgl-cmd-input-wrap { flex: 1; position: relative; }
.sgl-cmd-input {
    width: 100%;
    background: transparent;
    border: none;
    outline: none;
    color: #e4d6ff;
    font-family: 'JetBrains Mono', monospace;
    font-size: 13px;
    letter-spacing: 0.03em;
}
.sgl-cmd-input::placeholder { color: rgba(196,166,255,0.3); }
.sgl-cmd-input:disabled { opacity: 0.4; }

.sgl-suggest-list {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    min-width: 160px;
    background: #150f24;
    border: 1px solid rgba(183,148,246,0.35);
    box-shadow: 0 -4px 16px rgba(0,0,0,0.4);
    z-index: 10;
}
.sgl-suggest-item {
    padding: 6px 12px;
    font-size: 12px;
    color: rgba(196,166,255,0.7);
    cursor: pointer;
}
.suggest--active { background: rgba(183,148,246,0.18); color: #e4d6ff; }

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

@keyframes sgl-dot-pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
</style>
