<template>
    <div class="gb-overlay">
        <div class="gb-terminal">

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="gb-topbar">
                <span>MATCH ID: #{{ matchId }}</span>
                <span class="gb-timer" :class="{ 'timer--critical': timeLeft <= 5, 'timer--warn': timeLeft <= 10 && timeLeft > 5 }">
                    TIME REMAINING: {{ timeDisplay }}
                </span>
                <span v-if="pvpMode && pvpOpponent" class="gb-topbar-pvp">
                    VS // <span class="gb-topbar-handle">{{ pvpOpponent.handle?.toUpperCase() ?? 'UNKNOWN' }}</span>
                </span>
                <span v-else>NODE: {{ nodeName }}</span>
            </div>
            <div class="gb-rule" />

            <!-- ── Target sequence ───────────────────────────────────────────── -->
            <div class="gb-seq-section">
                <div class="gb-seq-row">
                    <span class="gb-seq-label">[ TARGET SEQUENCE ] &gt;&gt;&gt;</span>
                    <template v-for="(val, i) in sequence" :key="i">
                        <div class="gb-seq-slot" :class="seqSlotClass(i)">
                            <span class="gb-seq-bracket">[ </span>
                            <span class="gb-seq-val">{{ val }}</span>
                            <span class="gb-seq-bracket"> ]</span>
                            <div v-if="i === currentStep && status === 'playing'" class="gb-seq-cursor">◄ LOCATE</div>
                        </div>
                        <span v-if="i < sequence.length - 1" class="gb-seq-arrow"> --&gt; </span>
                    </template>
                </div>

                <!-- Score / threshold bar -->
                <div class="gb-score-row">
                    <span class="gb-score-label">{{ pvpMode ? 'SEQUENCES BREACHED:' : 'BREACH SCORE:' }}</span>
                    <div v-if="!pvpMode" class="gb-score-bar-wrap">
                        <div class="gb-score-bar-fill" :style="{ width: Math.min(100, (score / threshold) * 100) + '%' }" />
                    </div>
                    <span class="gb-score-val" :class="{ 'score--met': !pvpMode && score >= threshold }">
                        {{ pvpMode ? score : `${score} / ${threshold}` }}
                    </span>
                    <span v-if="!pvpMode" class="gb-score-status" :class="{ 'threshold--met': score >= threshold }">
                        {{ score >= threshold ? '[ THRESHOLD MET ]' : '[ THRESHOLD REQUIRED ]' }}
                    </span>
                    <span v-else class="gb-score-status">[ MAXIMIZE BEFORE TIME EXPIRES ]</span>
                </div>
            </div>

            <div class="gb-rule gb-rule--light" />

            <!-- ── Grid ─────────────────────────────────────────────────────── -->
            <div class="gb-grid-section">

                <!-- Column headers -->
                <div class="gb-col-row">
                    <span class="gb-spacer" />
                    <span v-for="col in COLS" :key="col" class="gb-col-label">{{ col }}</span>
                    <span class="gb-meta-spacer" />
                </div>

                <!-- Data rows -->
                <div v-for="(row, rIdx) in grid" :key="rIdx" class="gb-row">
                    <span class="gb-row-num">{{ rIdx + 1 }}</span>
                    <span class="gb-row-pipe">|</span>
                    <span
                        v-for="(cell, cIdx) in row"
                        :key="cIdx"
                        class="gb-cell"
                        :class="cellClass(rIdx, cIdx, cell)"
                    >{{ cell }}</span>
                    <span class="gb-row-pipe">|</span>
                    <span class="gb-row-num">{{ rIdx + 1 }}</span>
                    <span class="gb-row-dir" :class="rowDirClass(rIdx)">{{ rowDir(rIdx) }}</span>
                    <span v-if="rowMeta[rIdx]?.modifier" class="gb-row-tag" :class="`tag--${rowMeta[rIdx].modifier}`">
                        {{ rowMeta[rIdx].modifier.toUpperCase() }}!
                    </span>
                </div>

                <!-- Column footers -->
                <div class="gb-col-row">
                    <span class="gb-spacer" />
                    <span v-for="col in COLS" :key="col" class="gb-col-label">{{ col }}</span>
                </div>

            </div>

            <div class="gb-rule gb-rule--light" />

            <!-- ── Coordinate input ──────────────────────────────────────────── -->
            <div class="gb-input-section">
                <span class="gb-input-prompt">&gt;&gt;</span>
                <span class="gb-input-label">ENTER COORDINATE:</span>
                <input
                    ref="coordInputRef"
                    v-model="coordInput"
                    class="gb-coord-input"
                    type="text"
                    maxlength="3"
                    placeholder="e.g. F6"
                    autocomplete="off"
                    spellcheck="false"
                    :disabled="status !== 'playing'"
                    @keydown.enter="submitCoord"
                    @input="onCoordInput"
                />
                <button class="gb-submit-btn" :disabled="status !== 'playing'" @click="submitCoord">
                    [ SUBMIT ]
                </button>
                <Transition name="scramble-flash">
                    <span v-if="scrambleFlash" class="gb-scramble-warn">// BOARD SCRAMBLE</span>
                </Transition>
            </div>

            <!-- ── Flash message ─────────────────────────────────────────────── -->
            <div class="gb-flash-row">
                <Transition name="flash-fade">
                    <span v-if="flashMsg" class="gb-flash" :class="`flash--${flashType}`">
                        {{ flashMsg }}
                    </span>
                </Transition>
            </div>

            <!-- ── Pressure bars ─────────────────────────────────────────────── -->
            <div class="gb-pressure-section">
                <div class="gb-pressure-row">
                    <span class="gb-p-label">RIVAL BREACH PRESSURE:</span>
                    <div class="gb-p-bar">
                        <div class="gb-p-fill gb-p-fill--rival" :style="{ width: rivalPressure + '%' }" />
                    </div>
                    <span class="gb-p-pct">{{ rivalPressure }}% ({{ rivalStatus }})</span>
                </div>
                <div class="gb-pressure-row">
                    <span class="gb-p-label">YOUR NET PRESSURE:</span>
                    <div class="gb-p-bar">
                        <div class="gb-p-fill gb-p-fill--self" :style="{ width: selfPressure + '%' }" />
                    </div>
                    <span class="gb-p-pct">{{ Math.round(selfPressure) }}% {{ gainDisplay }}</span>
                </div>
            </div>

            <!-- ── Status bar ────────────────────────────────────────────────── -->
            <div class="gb-statusbar">
                <span>SYSTEM STATUS:</span>
                <span class="gb-status-chip">[ FIREWALL: {{ firewallPct }}% ]</span>
                <span class="gb-status-chip">[ BREACH MULTIPLIER: {{ bountyMultiplier }}x ]</span>
                <span class="gb-status-chip">[ {{ resourceLabel }} HACK ]</span>
                <button class="gb-abort-btn" @click="onAbort">[ ABORT ]</button>
            </div>

            <!-- ── Outcome overlay ───────────────────────────────────────────── -->
            <Transition name="outcome-fade">
                <div v-if="status !== 'playing'" class="gb-outcome-overlay" :class="`outcome--${status}`">
                    <div class="gb-outcome-title">
                        <template v-if="pvpMode">// DUEL COMPLETE //</template>
                        <template v-else>{{ status === 'success' ? '// BREACH SUCCESSFUL //' : '// BREACH FAILED //' }}</template>
                    </div>
                    <div class="gb-outcome-sub">
                        <template v-if="pvpMode">{{ score }} SEQUENCE{{ score !== 1 ? 'S' : '' }} BREACHED — SUBMITTING SCORE</template>
                        <template v-else>{{ status === 'success' ? outcomeSuccessMsg : outcomeFailMsg }}</template>
                    </div>
                    <div class="gb-outcome-score">
                        <template v-if="pvpMode">YOUR SCORE: {{ score }} SEQUENCES</template>
                        <template v-else>FINAL SCORE: {{ score }} / {{ threshold }} SEQUENCES</template>
                    </div>
                    <button class="gb-outcome-btn" @click="onDismiss">[ CONTINUE ]</button>
                </div>
            </Transition>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

// ─── Props & emits ────────────────────────────────────────────────────────────
const props = defineProps({
    node:             { type: Object,  default: null    },
    resource:         { type: String,  default: 'creds' },  // 'creds' | 'tech' | 'uplink'
    playerCpu:        { type: Number,  default: 3       },
    playerRam:        { type: Number,  default: 2       },
    playerOs:         { type: Number,  default: 2       },
    playerFirewall:   { type: Number,  default: 1       },
    playerMaxUplink:  { type: Number,  default: 3       },
    bountyMultiplier: { type: Number,  default: 1.0     },
    pvpMode:          { type: Boolean, default: false   },
    pvpOpponent:      { type: Object,  default: null    },
});

const emit = defineEmits(['complete', 'failed', 'abort']);

// ─── Constants ────────────────────────────────────────────────────────────────
const COLS = ['A','B','C','D','E','F','G','H','I','J'];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function randHex() {
    const h = '0123456789ABCDEF';
    return h[~~(Math.random() * 16)] + h[~~(Math.random() * 16)];
}

function randId() {
    return Math.random().toString(36).slice(2, 6).toUpperCase();
}

// ─── ICE floor — equal to BlackHat v1.0 CPU, the starting rig ────────────────
// No node can be easier than a fresh player expects on day one.
// Nodes escalate ABOVE this floor as they get hacked more (handled in Game.vue).
const MIN_ICE = 3;

const iceLevel = computed(() => Math.max(MIN_ICE, props.node?.ice ?? MIN_ICE));

// ─── Difficulty — ICE tier sets board complexity; RAM/OS/CPU tune timers ──────
//
// ICE sets sequence length and threshold (gear can't remove these).
// RAM widens the game clock — more RAM = more time overall.
// OS widens the per-move input window — higher OS = more breathing room per key.
// CPU vs ICE is asymmetric: being below ICE compounds as a penalty (diff² × 2),
// being above gives a modest bonus (diff × 3). Floor is always 8 s.
//
// ICE 3–4  →  Tier 1  (starter rig content)
// ICE 5–6  →  Tier 2  (mid-tier gear recommended)
// ICE 7–8  →  Tier 3  (high-sec, serious difficulty)
// ICE 9–10 →  Tier 4  (black site — punishing for anyone)
const difficulty = computed(() => {
    const ice = iceLevel.value;
    const cpu = props.playerCpu;
    const ram = props.playerRam;
    const os  = props.playerOs;

    // Sequence length and threshold — ICE-driven only
    let seqLen, threshold;
    if      (ice <= 4)  { seqLen = 3; threshold = 2; }
    else if (ice <= 6)  { seqLen = 4; threshold = 3; }
    else if (ice <= 8)  { seqLen = 5; threshold = 4; }
    else                { seqLen = 6; threshold = 5; }

    // Base game timer: RAM drives total length; OS adjusts per-move input window
    const baseTimer = 30 + (ram * 5) + Math.round(os * 0.3);

    // CPU vs ICE asymmetric modifier: bonus small, penalty compounds
    const diff      = cpu - ice;
    const timerMod  = diff >= 0 ? diff * 3 : -(diff * diff) * 2;

    // Threshold only shifts by 1 at extreme gaps (4+ above or below)
    const threshMod = diff >= 4 ? -1 : diff <= -4 ? 1 : 0;

    return {
        seqLen,
        timer:     Math.max(8, baseTimer + timerMod),
        threshold: Math.max(1, threshold + threshMod),
    };
});

// ─── Row meta (direction + modifier) ─────────────────────────────────────────
const rowMeta = computed(() => {
    return Array.from({ length: 10 }, (_, i) => {
        const ice = iceLevel.value;
        let modifier = null;
        if (ice >= 5 && i === 3) modifier = 'locked';
        if (ice >= 5 && i === 7) modifier = 'glitch';
        if (ice >= 7 && i === 1) modifier = 'locked';
        if (ice >= 8 && i === 5) modifier = 'glitch';
        return { direction: i % 2 === 0 ? 'forward' : 'backward', modifier };
    });
});

function rowDir(rIdx)      { return rowMeta.value[rIdx].direction === 'forward' ? '[>>>]' : '[<<<]'; }
function rowDirClass(rIdx) { return rowMeta.value[rIdx].direction === 'forward' ? 'dir--fwd' : 'dir--bwd'; }

// ─── Game state ───────────────────────────────────────────────────────────────
const matchId        = ref(randId() + '-' + randId());
const nodeName       = computed(() => props.node?.name?.toUpperCase() ?? 'UNKNOWN_NODE');
const grid           = ref([]);
const sequence       = ref([]);
const currentStep    = ref(0);
const timeLeft       = ref(0);
const status         = ref('playing');   // 'playing' | 'success' | 'failed'
const score          = ref(0);
const threshold      = computed(() => difficulty.value.threshold);

// lockedCells: key = "rIdx,cIdx" — cells confirmed by player, frozen during scrambles
const lockedCells    = ref(new Map());

// flash state
const flashCell      = ref(null);   // { row, col, type: 'correct'|'wrong' }
const scrambleFlash  = ref(false);
const flashMsg       = ref('');
const flashType      = ref('');
const flashTimerRef  = ref(null);

// input
const coordInput     = ref('');
const coordInputRef  = ref(null);

// pressure bars
const rivalPressure  = ref(Math.floor(Math.random() * 20) + 35);

// ─── Build sequence ───────────────────────────────────────────────────────────
function buildSequence() {
    const len = difficulty.value.seqLen;
    sequence.value = Array.from({ length: len }, () => randHex());
    currentStep.value = 0;
}

// ─── Build full grid + seed sequence values ───────────────────────────────────
function buildGrid() {
    const g = Array.from({ length: 10 }, () =>
        Array.from({ length: 10 }, () => randHex())
    );
    seedAllTargets(g, sequence.value);
    grid.value = g;
}

function seedAllTargets(g, seq) {
    const safeRows = getSafeRows();
    seq.forEach((val, idx) => {
        // Place 2 copies so the player has options
        for (let copy = 0; copy < 2; copy++) {
            const row = safeRows[(idx * 3 + copy) % safeRows.length];
            const col = Math.floor(Math.random() * 10);
            if (!lockedCells.value.has(`${row},${col}`)) {
                g[row][col] = val;
            }
        }
    });
}

function getSafeRows() {
    return Array.from({ length: 10 }, (_, i) => i)
        .filter(i => rowMeta.value[i]?.modifier !== 'locked');
}

// ─── Scramble — fires every 2 seconds ─────────────────────────────────────────
function scrambleGrid() {
    if (status.value !== 'playing') return;

    const g = grid.value.map(row => [...row]);

    // Regenerate all non-locked cells
    for (let r = 0; r < 10; r++) {
        for (let c = 0; c < 10; c++) {
            if (!lockedCells.value.has(`${r},${c}`)) {
                g[r][c] = randHex();
            }
        }
    }

    // Always guarantee the current target is findable after scramble
    seedCurrentTarget(g);

    grid.value = g;

    // Brief scramble flash
    scrambleFlash.value = true;
    setTimeout(() => { scrambleFlash.value = false; }, 700);
}

function seedCurrentTarget(g) {
    const target = sequence.value[currentStep.value];
    if (!target) return;

    const safeRows = getSafeRows();
    for (let attempt = 0; attempt < 30; attempt++) {
        const row = safeRows[Math.floor(Math.random() * safeRows.length)];
        const col = Math.floor(Math.random() * 10);
        if (!lockedCells.value.has(`${row},${col}`)) {
            g[row][col] = target;
            return;
        }
    }
}

// ─── Coordinate parsing ───────────────────────────────────────────────────────
// Accepts "A1", "F6", "J10" — col letter then row number
function parseCoord(raw) {
    const str = raw.trim().toUpperCase();
    if (str.length < 2 || str.length > 3) return null;
    const col = COLS.indexOf(str[0]);
    const row = parseInt(str.slice(1), 10) - 1;    // convert 1-index to 0-index
    if (col === -1 || isNaN(row) || row < 0 || row > 9) return null;
    return { row, col };
}

// ─── Coordinate submit ────────────────────────────────────────────────────────
function submitCoord() {
    if (status.value !== 'playing') return;

    const raw = coordInput.value;
    coordInput.value = '';
    nextTick(() => coordInputRef.value?.focus());

    const parsed = parseCoord(raw);
    if (!parsed) {
        showFlash('INVALID COORDINATE — FORMAT: A1 THROUGH J10', 'wrong');
        return;
    }

    const { row, col } = parsed;
    const meta   = rowMeta.value[row];
    const cellKey = `${row},${col}`;

    // Locked row — ICE barrier
    if (meta.modifier === 'locked') {
        showFlash(`ROW ${row + 1} LOCKED — ICE BARRIER ACTIVE`, 'wrong');
        return;
    }

    // Already confirmed cell
    if (lockedCells.value.has(cellKey)) {
        showFlash(`${COLS[col]}${row + 1} ALREADY CONFIRMED`, 'wrong');
        return;
    }

    const cellVal = grid.value[row][col];
    const target  = sequence.value[currentStep.value];

    if (cellVal === target) {
        // ✓ Correct coordinate
        setFlashCell(row, col, 'correct');
        lockedCells.value = new Map([...lockedCells.value, [cellKey, cellVal]]);

        if (meta.modifier === 'glitch') {
            showFlash(`GLITCH TRAVERSE: ${cellVal} @ ${COLS[col]}${row + 1} — TIMER -2s`, 'glitch');
            timeLeft.value = Math.max(0, timeLeft.value - 2);
        } else {
            showFlash(`LOCKED: ${cellVal} @ ${COLS[col]}${row + 1} — SEQUENCE ADVANCING`, 'correct');
        }

        currentStep.value++;
        rivalPressure.value = Math.min(99, rivalPressure.value + Math.floor(Math.random() * 5) + 2);

        if (currentStep.value >= sequence.value.length) {
            // Full sequence complete — increment score
            score.value++;

            if (props.pvpMode) {
                // PvP: always keep playing to maximise score — time is the only limit
                buildSequence();
                scrambleGrid();
                showFlash(`SEQUENCE ${score.value} BREACHED — KEEP GOING`, 'correct');
            } else if (score.value >= threshold.value) {
                // PvE: threshold met — player wins immediately
                triggerSuccess();
            } else {
                // PvE: threshold not yet met — next sequence
                buildSequence();
                scrambleGrid();
                showFlash(`SEQUENCE COMPLETE — SCORE: ${score.value}/${threshold.value} — NEXT SEQUENCE LOADED`, 'correct');
            }
        } else {
            // Mid-sequence: re-scramble immediately so the next hexakey appears
            // at once. The scramble interval keeps its existing schedule — no reset.
            scrambleGrid();
        }

    } else {
        // ✗ Wrong coordinate
        setFlashCell(row, col, 'wrong');
        if (meta.modifier === 'glitch') {
            showFlash(`GLITCH ROW PENALTY — NO MATCH: ${cellVal} — TIMER -3s`, 'wrong');
            timeLeft.value = Math.max(0, timeLeft.value - 3);
        } else {
            showFlash(`NO MATCH: ${cellVal} @ ${COLS[col]}${row + 1} — LOOKING FOR ${target}`, 'wrong');
        }
    }
}

function onCoordInput(e) {
    coordInput.value = e.target.value.toUpperCase();
}

// ─── Flash helpers ────────────────────────────────────────────────────────────
function setFlashCell(row, col, type) {
    flashCell.value = { row, col, type };
    setTimeout(() => { flashCell.value = null; }, 500);
}

function showFlash(msg, type) {
    clearTimeout(flashTimerRef.value);
    flashMsg.value   = msg;
    flashType.value  = type;
    flashTimerRef.value = setTimeout(() => { flashMsg.value = ''; }, 1800);
}

// ─── Cell classes ─────────────────────────────────────────────────────────────
function cellClass(rIdx, cIdx, cell) {
    const meta    = rowMeta.value[rIdx];
    const cellKey = `${rIdx},${cIdx}`;
    const classes = [];

    if (meta.modifier === 'locked') classes.push('cell--row-locked');
    if (meta.modifier === 'glitch') classes.push('cell--glitch-row');

    if (lockedCells.value.has(cellKey)) {
        classes.push('cell--confirmed');
    } else {
        // Only pulse cells matching the current target (not in locked rows)
        const isTarget = status.value === 'playing'
            && cell === sequence.value[currentStep.value]
            && meta.modifier !== 'locked';
        if (isTarget) classes.push('cell--target');
    }

    if (flashCell.value?.row === rIdx && flashCell.value?.col === cIdx) {
        classes.push(`cell--flash-${flashCell.value.type}`);
    }

    return classes;
}

// ─── Sequence slot classes ────────────────────────────────────────────────────
function seqSlotClass(i) {
    if (i < currentStep.value)   return 'seq--found';
    if (i === currentStep.value) return 'seq--current';
    return 'seq--pending';
}

// ─── Computed display ─────────────────────────────────────────────────────────
const timeDisplay = computed(() => {
    const m = Math.floor(timeLeft.value / 60).toString().padStart(2, '0');
    const s = (timeLeft.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const selfPressure = computed(() => {
    if (threshold.value === 0) return 0;
    return Math.min(100, (score.value / threshold.value) * 100);
});

const rivalStatus = computed(() => {
    if (rivalPressure.value < 40) return 'LOW';
    if (rivalPressure.value < 65) return 'STABLE';
    return 'RISING';
});

const firewallPct   = computed(() => (props.playerFirewall ?? 1) * 12);
const resourceLabel = computed(() => props.resource.toUpperCase());

const gainDisplay = computed(() => {
    if (score.value === 0) return '';
    return `(${score.value} SEQ COMPLETE)`;
});

// ─── Reward formula ───────────────────────────────────────────────────────────
//
// Rewards are intentionally lean — skill favoured over gear grinding.
// Full payout on success; zero on failure.
// Payout scales with ICE (harder node = more value).
// Tech Points scale with ICE level — 1 TP at ICE 3, +1 per ICE tier above that.
// All action nodes reward tech; harder nodes are more efficient to farm.
//
// Creds per full successful hack at each ICE tier:
//   ICE 3  →  ~75 creds    (2–3 hacks to afford T1 command)
//   ICE 5  →  ~125 creds   (mid-tier content)
//   ICE 7  →  ~175 creds   (high-sec content)
//   ICE 9  →  ~225 creds   (black site — plus bounty multiplier bonus)
const scoreRatio = computed(() => {
    if (threshold.value === 0) return 0;
    return score.value / threshold.value;
});

const rewardAmount = computed(() => {
    const ice   = iceLevel.value;
    const ratio = scoreRatio.value;
    const mult  = props.bountyMultiplier ?? 1;

    if (props.resource === 'creds') {
        return Math.round(ice * 25 * ratio * mult);
    }
    if (props.resource === 'tech') {
        // Tech point reward by ICE tier — flat per node, no ratio.
        // Breach must be completed; failed attempts earn nothing.
        //
        // ICE 1 → 0.25 | ICE 2 → 0.5 | ICE 3 → 1 | ICE 4 → 2 | ICE 5 → 3 …
        //
        // Bounty multiplier is applied to the full amount so running hot
        // visibly accelerates TP gain. Result is rounded to 2 decimal places
        // and floored at 0.25 so no successful hack ever gives nothing.
        let base;
        if (ice <= 1)       base = 0.25;
        else if (ice === 2) base = 0.5;
        else                base = Math.max(1, ice - 2);

        return Math.max(0.25, Math.round(base * mult * 4) / 4);
    }
    if (props.resource === 'uplink') {
        // Uplink restore is always full — no partial on this one
        return props.playerMaxUplink;
    }
    return 0;
});

const outcomeSuccessMsg = computed(() => {
    if (props.resource === 'creds')  return `${rewardAmount.value} CREDS EXTRACTED — BALANCE UPDATED`;
    if (props.resource === 'tech')   return `${rewardAmount.value} TECH POINTS HARVESTED — RIG QUEUE UPDATED`;
    if (props.resource === 'uplink') return `UPLINK RESTORED TO ${rewardAmount.value} — MOVEMENT AVAILABLE`;
    return 'RESOURCE EXTRACTED';
});

const outcomeFailMsg = computed(() => {
    return 'THRESHOLD NOT MET — ICE HELD — NO YIELD';
});

// ─── Outcome ──────────────────────────────────────────────────────────────────
function triggerSuccess() {
    status.value = 'success';
    clearInterval(tickInterval);
    clearInterval(scrambleInterval);
}

function triggerFail() {
    status.value = 'failed';
    clearInterval(tickInterval);
    clearInterval(scrambleInterval);
}

// PvP only — time expired, neutral end state.
// Winner is determined server-side by comparing both players' scores.
function triggerDuelEnd() {
    status.value = 'duel_end';
    clearInterval(tickInterval);
    clearInterval(scrambleInterval);
}

function onDismiss() {
    if (props.pvpMode) {
        // PvP: emit score only — server compares both players' scores to determine winner.
        // Loot is calculated server-side; amount is irrelevant here.
        emit('complete', { resource: props.resource, amount: 0, score: score.value });
        return;
    }
    if (status.value === 'success') {
        emit('complete', { resource: props.resource, amount: rewardAmount.value });
    } else {
        emit('failed', { resource: props.resource, amount: 0 });
    }
}

function onAbort() {
    clearInterval(tickInterval);
    clearInterval(scrambleInterval);
    emit('abort');
}

// ─── Timers ───────────────────────────────────────────────────────────────────
let tickInterval;
let scrambleInterval;

onMounted(() => {
    timeLeft.value = difficulty.value.timer;
    buildSequence();
    buildGrid();
    nextTick(() => coordInputRef.value?.focus());

    // Countdown
    tickInterval = setInterval(() => {
        if (status.value !== 'playing') return;
        timeLeft.value--;
        if (timeLeft.value <= 0) {
            if (props.pvpMode) {
                triggerDuelEnd();
            } else if (score.value >= threshold.value) {
                triggerSuccess();
            } else {
                triggerFail();
            }
        }
    }, 1000);

    // Board scramble every 5 seconds
    scrambleInterval = setInterval(() => {
        scrambleGrid();
    }, 5000);
});

onUnmounted(() => {
    clearInterval(tickInterval);
    clearInterval(scrambleInterval);
    clearTimeout(flashTimerRef.value);
});
</script>

<style scoped>
/* ── Overlay ────────────────────────────────────────────────────────────────── */
.gb-overlay {
    position: absolute;
    inset: 0;
    z-index: 60;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

/* ── Terminal window ────────────────────────────────────────────────────────── */
.gb-terminal {
    position: relative;
    background: #08080f;
    border: 1px solid rgba(0, 255, 255, 0.25);
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 11px;
    color: rgba(0, 255, 255, 0.85);
    width: min(700px, 96vw);
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 0 48px rgba(0, 255, 255, 0.07), inset 0 0 80px rgba(0, 0, 0, 0.5);
}
.gb-terminal::-webkit-scrollbar { display: none; }

/* ── Top bar ────────────────────────────────────────────────────────────────── */
.gb-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 14px;
    background: rgba(0, 255, 255, 0.03);
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    font-size: 10px;
    letter-spacing: 0.06em;
    flex-wrap: wrap;
    gap: 4px;
}

.gb-timer          { color: #00FFFF; }
.gb-topbar-pvp     { color: rgba(255,179,0,0.7); letter-spacing: 0.1em; font-size: 10px; }
.gb-topbar-handle  { color: #FF69B4; letter-spacing: 0.12em; }
.timer--warn     { color: #FFB300; animation: timer-pulse 0.8s ease-in-out infinite; }
.timer--critical { color: #FF3333; animation: timer-pulse 0.4s ease-in-out infinite; }
@keyframes timer-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

/* ── Rules ──────────────────────────────────────────────────────────────────── */
.gb-rule       { height: 1px; background: rgba(0,255,255,0.2); }
.gb-rule--light{ height: 1px; background: rgba(0,255,255,0.07); }

/* ── Sequence section ───────────────────────────────────────────────────────── */
.gb-seq-section {
    padding: 10px 14px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.gb-seq-row {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0;
}

.gb-seq-label {
    font-size: 9px;
    color: rgba(0,255,255,0.4);
    margin-right: 10px;
    white-space: nowrap;
    padding-top: 5px;
}

.gb-seq-slot {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 2px;
}

.gb-seq-bracket { color: rgba(0,255,255,0.35); font-size: 11px; }
.gb-seq-val     { font-size: 12px; letter-spacing: 0.12em; }
.gb-seq-arrow   { color: rgba(0,255,255,0.25); padding-top: 5px; font-size: 10px; }
.gb-seq-cursor  { font-size: 7px; color: rgba(0,255,255,0.4); letter-spacing: 0.1em; margin-top: 2px; white-space: nowrap; }

/* Slot states */
.seq--found   .gb-seq-val     { color: rgba(0,255,136,0.45); text-decoration: line-through; }
.seq--found   .gb-seq-bracket { color: rgba(0,255,136,0.2); }
.seq--current .gb-seq-val     { color: #00FFFF; text-shadow: 0 0 10px rgba(0,255,255,0.55); animation: seq-current 1.4s ease-in-out infinite; }
.seq--current .gb-seq-bracket { color: #00FFFF; }
.seq--pending .gb-seq-val     { color: rgba(0,255,255,0.3); }
.seq--pending .gb-seq-bracket { color: rgba(0,255,255,0.15); }
@keyframes seq-current { 0%,100%{opacity:1} 50%{opacity:.55} }

/* ── Score bar ──────────────────────────────────────────────────────────────── */
.gb-score-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 9px;
}
.gb-score-label   { color: rgba(0,255,255,0.4); letter-spacing: 0.06em; flex-shrink: 0; }
.gb-score-bar-wrap{
    flex: 1;
    height: 8px;
    background: rgba(0,255,255,0.06);
    border: 1px solid rgba(0,255,255,0.12);
    overflow: hidden;
}
.gb-score-bar-fill {
    height: 100%;
    background: rgba(0,255,136,0.55);
    transition: width 0.35s ease;
}
.gb-score-val      { color: #00FFFF; letter-spacing: 0.08em; flex-shrink: 0; }
.gb-score-val.score--met { color: #00FF88; text-shadow: 0 0 8px rgba(0,255,136,0.5); }
.gb-score-status   { color: rgba(0,255,255,0.35); font-size: 8px; letter-spacing: 0.06em; flex-shrink: 0; }
.threshold--met    { color: rgba(0,255,136,0.7); animation: threshold-pulse 1.2s ease-in-out infinite; }
@keyframes threshold-pulse { 0%,100%{opacity:1} 50%{opacity:.55} }

/* ── Grid ───────────────────────────────────────────────────────────────────── */
.gb-grid-section {
    padding: 4px 14px;
    overflow-x: auto;
}

.gb-col-row {
    display: flex;
    align-items: center;
    padding: 2px 0;
}
.gb-spacer      { width: 28px; flex-shrink: 0; }
.gb-meta-spacer { width: 80px; flex-shrink: 0; }
.gb-col-label {
    width: 36px;
    text-align: center;
    font-size: 10px;
    color: rgba(0,255,255,0.3);
    flex-shrink: 0;
}

.gb-row {
    display: flex;
    align-items: center;
}
.gb-row-num {
    width: 18px;
    font-size: 9px;
    color: rgba(0,255,255,0.28);
    text-align: right;
    flex-shrink: 0;
}
.gb-row-pipe {
    width: 10px;
    color: rgba(0,255,255,0.18);
    text-align: center;
    flex-shrink: 0;
}

/* ── Cells ──────────────────────────────────────────────────────────────────── */
.gb-cell {
    width: 36px;
    text-align: center;
    font-size: 10px;
    color: rgba(0,255,255,0.55);
    padding: 2px 0;
    flex-shrink: 0;
    letter-spacing: 0.04em;
    transition: color 0.08s, background 0.08s;
    user-select: none;
}

/* Rows locked by ICE */
.cell--row-locked {
    color: rgba(255,51,51,0.22);
    text-decoration: line-through;
}

/* Glitch row */
.cell--glitch-row {
    color: rgba(255,179,0,0.5);
}

/* Matches current target — pulses */
.cell--target {
    color: #00FFFF;
    text-shadow: 0 0 6px rgba(0,255,255,0.55);
    animation: target-pulse 1.1s ease-in-out infinite;
}
@keyframes target-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

/* Confirmed / locked in by player */
.cell--confirmed {
    color: rgba(0,255,136,0.65);
    text-shadow: 0 0 4px rgba(0,255,136,0.3);
}

/* Flash states */
.cell--flash-correct { color: #00FF88 !important; background: rgba(0,255,136,0.18) !important; transition: none !important; }
.cell--flash-wrong   { color: #FF3333 !important; background: rgba(255,51,51,0.18) !important; transition: none !important; }

/* ── Row direction labels ────────────────────────────────────────────────────── */
.gb-row-dir {
    margin-left: 8px;
    font-size: 9px;
    flex-shrink: 0;
}
.dir--fwd { color: rgba(0,255,136,0.35); }
.dir--bwd { color: rgba(125,249,255,0.3); }

.gb-row-tag {
    margin-left: 6px;
    font-size: 8px;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}
.tag--locked { color: rgba(255,51,51,0.5); }
.tag--glitch { color: rgba(255,179,0,0.6); animation: glitch-tag 2s step-end infinite; }
@keyframes glitch-tag { 0%,100%{opacity:1} 45%{opacity:.25} 50%{opacity:1} 75%{opacity:.45} }

/* ── Coordinate input ───────────────────────────────────────────────────────── */
.gb-input-section {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px;
    background: rgba(0,255,255,0.025);
    border-top: 1px solid rgba(0,255,255,0.08);
    flex-wrap: wrap;
}

.gb-input-prompt {
    color: rgba(0,255,136,0.7);
    font-size: 12px;
    flex-shrink: 0;
}

.gb-input-label {
    font-size: 10px;
    color: rgba(0,255,255,0.45);
    letter-spacing: 0.06em;
    flex-shrink: 0;
}

.gb-coord-input {
    background: rgba(0,255,255,0.04);
    border: 1px solid rgba(0,255,255,0.3);
    color: #00FFFF;
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 14px;
    letter-spacing: 0.18em;
    padding: 4px 10px;
    width: 72px;
    text-align: center;
    text-transform: uppercase;
    outline: none;
    transition: border-color 0.12s, box-shadow 0.12s;
}
.gb-coord-input::placeholder {
    color: rgba(0,255,255,0.2);
    font-size: 10px;
    letter-spacing: 0.08em;
}
.gb-coord-input:focus {
    border-color: rgba(0,255,255,0.7);
    box-shadow: 0 0 12px rgba(0,255,255,0.12);
}
.gb-coord-input:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.gb-submit-btn {
    background: transparent;
    border: 1px solid rgba(0,255,255,0.25);
    color: rgba(0,255,255,0.6);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 5px 12px;
    cursor: pointer;
    transition: all 0.12s;
    flex-shrink: 0;
}
.gb-submit-btn:hover:not(:disabled) {
    background: rgba(0,255,255,0.07);
    border-color: rgba(0,255,255,0.6);
    color: #00FFFF;
}
.gb-submit-btn:disabled { opacity: 0.35; cursor: not-allowed; }

.gb-scramble-warn {
    font-size: 9px;
    color: rgba(255,179,0,0.75);
    letter-spacing: 0.1em;
    margin-left: 4px;
    flex-shrink: 0;
}
.scramble-flash-enter-active { transition: opacity 0.1s; }
.scramble-flash-leave-active { transition: opacity 0.5s ease; }
.scramble-flash-enter-from,
.scramble-flash-leave-to     { opacity: 0; }

/* ── Flash message ──────────────────────────────────────────────────────────── */
.gb-flash-row {
    min-height: 22px;
    padding: 2px 14px;
    display: flex;
    align-items: center;
}
.gb-flash        { font-size: 9px; letter-spacing: 0.08em; }
.flash--correct  { color: #00FF88; }
.flash--wrong    { color: #FF3333; }
.flash--glitch   { color: #FFB300; }

.flash-fade-enter-active, .flash-fade-leave-active { transition: opacity 0.2s; }
.flash-fade-enter-from,   .flash-fade-leave-to     { opacity: 0; }

/* ── Pressure bars ──────────────────────────────────────────────────────────── */
.gb-pressure-section {
    padding: 7px 14px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    border-top: 1px solid rgba(0,255,255,0.07);
}
.gb-pressure-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 9px;
}
.gb-p-label {
    width: 160px;
    flex-shrink: 0;
    color: rgba(0,255,255,0.4);
    letter-spacing: 0.04em;
}
.gb-p-bar {
    flex: 1;
    height: 9px;
    background: rgba(0,255,255,0.06);
    border: 1px solid rgba(0,255,255,0.1);
    overflow: hidden;
}
.gb-p-fill         { height: 100%; transition: width 0.4s ease; }
.gb-p-fill--rival  { background: rgba(255,51,51,0.45); }
.gb-p-fill--self   { background: rgba(0,255,136,0.5); }
.gb-p-pct {
    width: 148px;
    flex-shrink: 0;
    color: rgba(0,255,255,0.45);
    font-size: 9px;
    text-align: right;
}

/* ── Status bar ─────────────────────────────────────────────────────────────── */
.gb-statusbar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 7px 14px 9px;
    border-top: 1px solid rgba(0,255,255,0.1);
    background: rgba(0,255,255,0.02);
    font-size: 9px;
    flex-wrap: wrap;
    color: rgba(0,255,255,0.4);
}
.gb-status-chip {
    color: rgba(0,255,255,0.55);
    letter-spacing: 0.06em;
}
.gb-abort-btn {
    margin-left: auto;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.22);
    color: rgba(255,51,51,0.45);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 3px 10px;
    cursor: pointer;
    transition: all 0.12s;
}
.gb-abort-btn:hover { border-color: rgba(255,51,51,0.65); color: #FF3333; }

/* ── Outcome overlay ────────────────────────────────────────────────────────── */
.gb-outcome-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    background: rgba(8,8,15,0.94);
}

.outcome--success   .gb-outcome-title { color: #00FF88; text-shadow: 0 0 24px rgba(0,255,136,0.55); }
.outcome--failed    .gb-outcome-title { color: #FF3333; text-shadow: 0 0 24px rgba(255,51,51,0.55); }
.outcome--duel_end  .gb-outcome-title { color: #FFB300; text-shadow: 0 0 24px rgba(255,179,0,0.55); }

.gb-outcome-title {
    font-size: 18px;
    letter-spacing: 0.14em;
    animation: outcome-appear 0.3s ease;
}
.gb-outcome-sub {
    font-size: 10px;
    color: rgba(0,255,255,0.4);
    letter-spacing: 0.08em;
}
.gb-outcome-score {
    font-size: 11px;
    color: rgba(0,255,255,0.6);
    letter-spacing: 0.1em;
}
.gb-outcome-btn {
    margin-top: 8px;
    background: transparent;
    border: 1px solid rgba(0,255,255,0.28);
    color: #00FFFF;
    font-family: inherit;
    font-size: 11px;
    letter-spacing: 0.14em;
    padding: 8px 28px;
    cursor: pointer;
    transition: all 0.15s;
}
.gb-outcome-btn:hover { background: rgba(0,255,255,0.07); border-color: rgba(0,255,255,0.65); }

@keyframes outcome-appear { from { opacity:0; transform: scale(0.96); } to { opacity:1; transform: scale(1); } }

.outcome-fade-enter-active { transition: opacity 0.25s ease; }
.outcome-fade-enter-from   { opacity: 0; }
</style>
