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
            <div class="gb-grid-section" :class="{ 'gb-grid-section--flicker': gridFlicker }">

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

            <!-- ── PvP Command Panel — only rendered in pvpMode with equipped commands ── -->
            <div v-if="pvpMode && pvpCommands.length > 0" class="gb-cmd-panel">
                <div class="gb-cmd-panel-label">// HACK COMMANDS — ONE USE PER DUEL</div>
                <div class="gb-cmd-slots">
                    <button
                        v-for="cmd in pvpCommands"
                        :key="cmd.id"
                        class="gb-cmd-slot"
                        :class="{
                            'gb-cmd-slot--ready':  !pvpCommandsUsed.has(cmd.id) && !cmd.cooldown && status === 'playing',
                            'gb-cmd-slot--used':   pvpCommandsUsed.has(cmd.id),
                            'gb-cmd-slot--cd':     cmd.cooldown && !pvpCommandsUsed.has(cmd.id),
                            [`gb-cmd-slot--${cmd.type}`]: true,
                        }"
                        :disabled="status !== 'playing' || pvpCommandsUsed.has(cmd.id) || cmd.cooldown"
                        @click="activatePvpCommand(cmd)"
                        @mouseenter="selectedPvpCmd = cmd"
                        @mouseleave="selectedPvpCmd = null"
                    >
                        <span class="gb-cmd-slot-name">{{ cmd.name.toUpperCase() }}</span>
                        <span class="gb-cmd-slot-tier">T{{ cmd.tier }}</span>
                        <span
                            class="gb-cmd-slot-state"
                            :class="{
                                'state--used': pvpCommandsUsed.has(cmd.id),
                                'state--cd':   cmd.cooldown && !pvpCommandsUsed.has(cmd.id),
                                'state--rdy':  !pvpCommandsUsed.has(cmd.id) && !cmd.cooldown,
                            }"
                        >
                            {{ pvpCommandsUsed.has(cmd.id) ? 'USED' : cmd.cooldown ? 'CD' : 'RDY' }}
                        </span>
                    </button>
                </div>
                <div class="gb-cmd-hint">
                    <template v-if="selectedPvpCmd">
                        <span class="gb-cmd-hint-name">{{ selectedPvpCmd.name.toUpperCase() }}</span>
                        <span class="gb-cmd-hint-sep">//</span>
                        <span class="gb-cmd-hint-effect">{{ selectedPvpCmd.gridbreachEffect }}</span>
                    </template>
                    <template v-else>
                        <span class="gb-cmd-hint-idle">Hover a command to preview its breach effect</span>
                    </template>
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
    pvpCommands:      { type: Array,   default: () => [] },  // equipped commands, passed only in pvpMode
});

const emit = defineEmits(['complete', 'failed', 'abort', 'pvp-command-used']);

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

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
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

    // Sequence length = ICE level directly (ICE 3 → 3 hexakeys, ICE 9 → 9 hexakeys).
    // Threshold = 1: one sequence completion wins the node.
    // Difficulty comes from sequence length + timer pressure, not repeated sequences.
    const seqLen = ice;

    // Base game timer: RAM drives total length; OS adjusts per-move input window
    const baseTimer = 30 + (ram * 5) + Math.round(os * 0.3);

    // CPU vs ICE asymmetric modifier: bonus small, penalty compounds
    const diff     = cpu - ice;
    const timerMod = diff >= 0 ? diff * 3 : -(diff * diff) * 2;

    return {
        seqLen,
        timer:     Math.max(8, baseTimer + timerMod),
        threshold: 1,
    };
});

// ─── Row modifiers — randomised once per match in buildRowModifiers() ─────────
// Map<rowIndex, 'locked'|'glitch'>. Populated at mount before buildGrid().
const rowModifiers = ref(new Map());

function buildRowModifiers() {
    const ice  = iceLevel.value;
    const mods = new Map();

    if (ice < 5) {
        rowModifiers.value = mods;
        return;
    }

    // ICE 5-6 → 1 locked + 1 glitch
    // ICE 7   → 2 locked + 1 glitch
    // ICE 8+  → 2 locked + 2 glitch
    const lockedCount = ice >= 7 ? 2 : 1;
    const glitchCount = ice >= 8 ? 2 : 1;

    const rows = shuffle([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
    let pick = 0;
    for (let i = 0; i < lockedCount; i++) mods.set(rows[pick++], 'locked');
    for (let i = 0; i < glitchCount; i++) mods.set(rows[pick++], 'glitch');

    rowModifiers.value = mods;
}

// ─── Row meta (direction + modifier) ─────────────────────────────────────────
const rowMeta = computed(() => {
    return Array.from({ length: 10 }, (_, i) => ({
        direction: i % 2 === 0 ? 'forward' : 'backward',
        modifier:  rowModifiers.value.get(i) ?? null,
    }));
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
    if (isScrambleSuppressed()) return;   // PvP: Firewall Patch / Blackout / DDOS suppress this

    const g = grid.value.map(row => [...row]);

    // Regenerate all non-locked, non-decoy cells.
    // Decoy cells are kept stable so they continue to display the fake target value
    // for the remainder of their 2.5 s window.
    for (let r = 0; r < 10; r++) {
        for (let c = 0; c < 10; c++) {
            const key = `${r},${c}`;
            if (!lockedCells.value.has(key) && !decoyCoords.value.has(key)) {
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
        const key = `${row},${col}`;
        // Never place the real target on a decoy cell — the player must be able
        // to distinguish by position, not just by finding any cell with the value.
        if (!lockedCells.value.has(key) && !decoyCoords.value.has(key)) {
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

    // Decoy intercept — must run before the value comparison because decoy cells
    // deliberately display the target value and would otherwise register as correct.
    if (decoyCoords.value.has(cellKey)) {
        setFlashCell(row, col, 'wrong');
        showFlash(`DECOY TRIGGERED — FALSE TARGET @ ${COLS[col]}${row + 1} — KEEP SEARCHING`, 'wrong');
        // Remove this individual decoy so the same trap can't fire twice
        const trimmed = new Set(decoyCoords.value);
        trimmed.delete(cellKey);
        decoyCoords.value = trimmed;
        return;
    }

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

        // PvP OS Exploit: flash ALL remaining sequence targets on the board
        if (highlightAllTargets.value && props.pvpMode && status.value === 'playing'
                && sequence.value.slice(currentStep.value).includes(cell)
                && meta.modifier !== 'locked') {
            classes.push('cell--pvp-reveal');
        }
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

// ─── PvP command system ───────────────────────────────────────────────────────
//
// Commands are only available when pvpMode === true and pvpCommands.length > 0.
// Each command may be used at most once per match (tracked in pvpCommandsUsed).
// Emitting 'pvp-command-used' tells Game.vue to apply the cooldown to the
// command in the shared commands ref so it appears on-cooldown after the duel.
//
// Because PvP GridBreach is not real-time between players (both run solo, winner
// determined by score comparison on the server), every "opponent" effect from
// the hackEffect descriptions is translated into a self-benefit for this player:
//
//   Crash / Signal Noise / Decoy  → seed extra target copies into your grid
//   Firewall Patch                → suppress board scrambles for 8 s
//   Ghost Protocol / Scramble / Fork Bomb → seed all remaining targets into grid
//   Packet Flood                  → +12 s on timer
//   Dark Mode                     → +10 s on timer
//   Blackout                      → suppress scrambles 10 s + add 5 s
//   Trojan / RootKit              → auto-advance 1 sequence step
//   OS Exploit                    → flash all remaining targets for 3 s
//   Buffer Overflow               → unlock all locked rows for 15 s
//   DDOS                          → suppress scrambles 3 s (board freeze)
//

// Tracks IDs of commands already used this match (Set inside ref — replaced on each use
// so Vue reactivity fires correctly without needing reactive()).
const pvpCommandsUsed = ref(new Set());

// Scramble suppression: scrambleGrid() returns early while Date.now() < this value.
const scrambleSuppressUntil = ref(0);

// OS Exploit reveal: all remaining-sequence cells glow when this is true.
const highlightAllTargets = ref(false);

// Hovered command in the command panel — drives the hint line at the bottom.
const selectedPvpCmd = ref(null);

// Crash command: brief visual flicker on the entire grid section.
const gridFlicker = ref(false);

// Decoy command: tracks "row,col" keys whose cells display the current target
// value but are fake — intercepted in submitCoord before the value check passes.
// Also respected by scrambleGrid (keeps fakes stable) and seedCurrentTarget
// (never places the real target on a decoy cell).
const decoyCoords = ref(new Set());

// Cleanup handles for timed PvP effects.
let _highlightTimer      = null;
let _rowUnlockTimer      = null;
let _flickerTimer        = null;
let _signalNoiseTimer    = null;
let _decoyTimer          = null;
let _savedRowModifiers   = null;   // holds the pre-unlock modifier map

/** Returns true if scramble suppression is currently active, pruning expired entries. */
function isScrambleSuppressed() {
    if (scrambleSuppressUntil.value <= 0) return false;
    if (Date.now() < scrambleSuppressUntil.value) return true;
    scrambleSuppressUntil.value = 0;
    return false;
}

// ── Per-effect helpers ────────────────────────────────────────────────────────

function pvpAddTime(seconds) {
    timeLeft.value += seconds;
    showFlash(`+${seconds}s INJECTED INTO TIMER`, 'correct');
}

function pvpSuppressScramble(seconds) {
    scrambleSuppressUntil.value = Date.now() + seconds * 1_000;
    showFlash(`SCRAMBLE SUPPRESSED — ${seconds}s`, 'correct');
}

/** Seeds N extra copies of the current sequence target into safe, unlocked cells. */
function pvpSeedExtraTargets(count) {
    const target = sequence.value[currentStep.value];
    if (!target) return;
    const g        = grid.value.map(row => [...row]);
    const safeRows = getSafeRows();
    let   seeded   = 0;
    for (let attempt = 0; attempt < 40 && seeded < count; attempt++) {
        const row = safeRows[Math.floor(Math.random() * safeRows.length)];
        const col = Math.floor(Math.random() * 10);
        if (!lockedCells.value.has(`${row},${col}`)) {
            g[row][col] = target;
            seeded++;
        }
    }
    grid.value = g;
    showFlash(`${seeded} EXTRA TARGET${seeded !== 1 ? 'S' : ''} SEEDED INTO GRID`, 'correct');
}

/** Seeds 2 copies of every remaining sequence target into safe cells. */
function pvpSeedAllRemainingTargets() {
    const g        = grid.value.map(row => [...row]);
    const safeRows = getSafeRows();
    const remaining = sequence.value.slice(currentStep.value);
    remaining.forEach((val, i) => {
        for (let copy = 0; copy < 2; copy++) {
            const row = safeRows[(i * 3 + copy) % safeRows.length];
            const col = Math.floor(Math.random() * 10);
            if (!lockedCells.value.has(`${row},${col}`)) {
                g[row][col] = val;
            }
        }
    });
    grid.value = g;
    showFlash('FULL SEQUENCE SEEDED — ALL TARGETS VISIBLE', 'correct');
}

/**
 * Finds the first unlocked cell matching the current target and locks it in,
 * advancing the sequence. If no match exists on the current board the target
 * is injected into the first available safe cell then confirmed.
 */
function pvpAutoAdvanceStep() {
    if (status.value !== 'playing') return;
    const target   = sequence.value[currentStep.value];
    if (!target) return;
    const safeRows = getSafeRows();

    for (const row of safeRows) {
        for (let col = 0; col < 10; col++) {
            const key = `${row},${col}`;
            if (!lockedCells.value.has(key) && grid.value[row][col] === target) {
                setFlashCell(row, col, 'correct');
                lockedCells.value = new Map([...lockedCells.value, [key, target]]);
                currentStep.value++;
                rivalPressure.value = Math.min(99, rivalPressure.value + 3);
                if (currentStep.value >= sequence.value.length) {
                    score.value++;
                    buildSequence();
                    scrambleGrid();
                    showFlash(`SEQUENCE AUTO-BREACHED — SCORE: ${score.value}`, 'correct');
                } else {
                    scrambleGrid();
                    showFlash(`AUTO-ADVANCE: ${target} CONFIRMED`, 'correct');
                }
                return;
            }
        }
    }
    // Target not visible — inject it then confirm
    const g = grid.value.map(row => [...row]);
    seedCurrentTarget(g);
    grid.value = g;
    currentStep.value++;
    rivalPressure.value = Math.min(99, rivalPressure.value + 3);
    if (currentStep.value >= sequence.value.length) {
        score.value++;
        buildSequence();
        scrambleGrid();
        showFlash(`SEQUENCE AUTO-BREACHED — SCORE: ${score.value}`, 'correct');
    } else {
        showFlash(`AUTO-ADVANCE: ${target} INJECTED + CONFIRMED`, 'correct');
    }
}

/** Highlights all remaining-sequence matching cells for N seconds (OS Exploit). */
function pvpFlashAllTargets(seconds) {
    clearTimeout(_highlightTimer);
    highlightAllTargets.value = true;
    const current = sequence.value[currentStep.value];
    showFlash(`TARGET LOCK — ALL [${current}] HIGHLIGHTED FOR ${seconds}s`, 'correct');
    _highlightTimer = setTimeout(() => { highlightAllTargets.value = false; }, seconds * 1_000);
}

/**
 * Clears all row modifiers (locked / glitch) for 15 s, re-seeding the board so
 * every row is accessible. Restores original modifiers after the timer expires.
 * (Buffer Overflow — bypasses ICE-locked rows for 1 sequence duration.)
 */
function pvpUnlockAllRows() {
    if (_savedRowModifiers === null) {
        _savedRowModifiers = new Map(rowModifiers.value);
    }
    rowModifiers.value = new Map();
    // Re-seed all remaining targets now that all rows are accessible
    const g = grid.value.map(row => [...row]);
    seedAllTargets(g, sequence.value);
    grid.value = g;
    showFlash('ICE ROWS BYPASSED — ALL ROWS ACCESSIBLE', 'correct');
    clearTimeout(_rowUnlockTimer);
    _rowUnlockTimer = setTimeout(() => {
        if (_savedRowModifiers !== null) {
            rowModifiers.value = _savedRowModifiers;
            _savedRowModifiers = null;
        }
    }, 15_000);
}

/**
 * Crash — flickers the entire grid section for 1.5 s.
 * Pure visual disruption; no cell values are changed.
 */
function pvpFlickerGrid() {
    clearTimeout(_flickerTimer);
    gridFlicker.value = true;
    showFlash('CRASH PULSE — GRID INTERFERENCE', 'wrong');
    _flickerTimer = setTimeout(() => { gridFlicker.value = false; }, 1_500);
}

/**
 * Signal Noise — temporarily marks one random unmodified row as 'glitch' for 1.5 s.
 * Uses the same rules as a seeded glitch row: correct pick costs −2 s, wrong pick
 * costs −3 s, and the row displays in amber with the GLITCH! tag.
 */
function pvpTempGlitchRow() {
    const available = Array.from({ length: 10 }, (_, i) => i)
        .filter(i => !rowModifiers.value.has(i));
    if (available.length === 0) return;

    const row    = available[Math.floor(Math.random() * available.length)];
    const newMap = new Map(rowModifiers.value);
    newMap.set(row, 'glitch');
    rowModifiers.value = newMap;

    showFlash(`SIGNAL NOISE — ROW ${row + 1} CORRUPTED (1.5s)`, 'glitch');

    clearTimeout(_signalNoiseTimer);
    _signalNoiseTimer = setTimeout(() => {
        const restored = new Map(rowModifiers.value);
        restored.delete(row);
        rowModifiers.value = restored;
    }, 1_500);
}

/**
 * Decoy — plants 3 fake copies of the current target hexakey in safe, unlocked
 * cells for 2.5 s. They look identical to the real target (same value, same
 * cell--target pulse). Hitting one triggers a wrong-answer penalty and removes
 * that individual fake. On expiry the board refreshes to clear all remaining fakes.
 */
function pvpDecoy() {
    const target = sequence.value[currentStep.value];
    if (!target) return;

    const safeRows  = getSafeRows();
    const g         = grid.value.map(row => [...row]);
    const planted   = [];

    for (let attempt = 0; attempt < 60 && planted.length < 3; attempt++) {
        const row = safeRows[Math.floor(Math.random() * safeRows.length)];
        const col = Math.floor(Math.random() * 10);
        const key = `${row},${col}`;
        if (!lockedCells.value.has(key) && !decoyCoords.value.has(key)) {
            g[row][col] = target;
            planted.push(key);
        }
    }

    grid.value = g;
    decoyCoords.value = new Set([...decoyCoords.value, ...planted]);

    showFlash(`DECOY — ${planted.length} FALSE TARGET${planted.length !== 1 ? 'S' : ''} PLANTED (2.5s)`, 'glitch');

    clearTimeout(_decoyTimer);
    _decoyTimer = setTimeout(() => {
        if (status.value !== 'playing') return;
        // Wipe remaining decoy values and refresh the board
        const restored = grid.value.map(row => [...row]);
        for (const key of decoyCoords.value) {
            const [r, c] = key.split(',').map(Number);
            if (!lockedCells.value.has(key)) restored[r][c] = randHex();
        }
        seedCurrentTarget(restored);
        grid.value = restored;
        decoyCoords.value = new Set();
    }, 2_500);
}

// ── Main PvP command dispatcher ───────────────────────────────────────────────

function activatePvpCommand(cmd) {
    if (status.value !== 'playing') return;
    if (pvpCommandsUsed.value.has(cmd.id)) return;
    if (cmd.cooldown) return;

    // Mark used — replace the Set so Vue reactivity fires
    pvpCommandsUsed.value = new Set([...pvpCommandsUsed.value, cmd.id]);
    // Tell Game.vue to apply cooldown to this command
    emit('pvp-command-used', { commandId: cmd.id });

    switch (cmd.name) {
        case 'Crash':
            pvpFlickerGrid();
            break;

        case 'Signal Noise':
            pvpTempGlitchRow();
            break;

        case 'Decoy':
            pvpDecoy();
            break;

        case 'Firewall Patch':
            pvpSuppressScramble(8);
            break;

        case 'Ghost Protocol':
        case 'Scramble':
            pvpSeedAllRemainingTargets();
            break;

        case 'Packet Flood':
            pvpAddTime(12);
            break;

        case 'Dark Mode':
            pvpAddTime(10);
            break;

        case 'Blackout':
            pvpSuppressScramble(10);
            pvpAddTime(5);
            break;

        case 'Trojan':
        case 'RootKit':
            pvpAutoAdvanceStep();
            break;

        case 'OS Exploit':
            pvpFlashAllTargets(3);
            break;

        case 'Buffer Overflow':
            pvpUnlockAllRows();
            break;

        case 'DDOS':
            pvpSuppressScramble(3);
            break;

        case 'Fork Bomb':
            pvpSeedAllRemainingTargets();
            pvpAddTime(8);
            break;

        default:
            pvpSeedExtraTargets(3);
            break;
    }

    console.log(`[PVP CMD] ${cmd.name.toUpperCase()} activated inside GridBreach`);
}

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
    buildRowModifiers();
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
    clearTimeout(_highlightTimer);
    clearTimeout(_rowUnlockTimer);
    clearTimeout(_flickerTimer);
    clearTimeout(_signalNoiseTimer);
    clearTimeout(_decoyTimer);
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

/* ── Crash grid flicker ─────────────────────────────────────────────────────── */
.gb-grid-section--flicker {
    animation: grid-flicker 1.5s ease forwards;
    pointer-events: none;   /* block input for the duration */
}
@keyframes grid-flicker {
    0%   { opacity: 1;   filter: none; }
    8%   { opacity: 0.1; filter: brightness(3) saturate(0); }
    15%  { opacity: 0.9; filter: none; }
    28%  { opacity: 0.2; filter: brightness(2) hue-rotate(160deg); }
    35%  { opacity: 1;   filter: none; }
    50%  { opacity: 0.4; filter: brightness(2.5) saturate(0); }
    58%  { opacity: 0.9; filter: none; }
    72%  { opacity: 0.1; filter: brightness(3); }
    78%  { opacity: 1;   filter: none; }
    90%  { opacity: 0.7; filter: brightness(1.4); }
    100% { opacity: 1;   filter: none; }
}

/* ── PvP reveal cell (OS Exploit) ───────────────────────────────────────────── */
.cell--pvp-reveal {
    color: #FF69B4 !important;
    text-shadow: 0 0 8px rgba(255,105,180,0.65);
    animation: pvp-reveal-pulse 0.6s ease-in-out infinite;
}
@keyframes pvp-reveal-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── PvP Command Panel ──────────────────────────────────────────────────────── */
.gb-cmd-panel {
    border-top: 1px solid rgba(255,105,180,0.15);
    border-bottom: 1px solid rgba(255,105,180,0.08);
    background: rgba(255,105,180,0.025);
    padding: 7px 14px 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.gb-cmd-panel-label {
    font-size: 8px;
    color: rgba(255,105,180,0.55);
    letter-spacing: 0.14em;
}

.gb-cmd-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.gb-cmd-slot {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    background: transparent;
    border: 1px solid rgba(255,105,180,0.18);
    color: rgba(255,105,180,0.5);
    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 8px;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: all 0.1s;
}
.gb-cmd-slot--ready {
    border-color: rgba(255,105,180,0.4);
    color: rgba(255,105,180,0.85);
}
.gb-cmd-slot--ready:hover:not(:disabled) {
    border-color: #FF69B4;
    color: #FF69B4;
    background: rgba(255,105,180,0.07);
    box-shadow: 0 0 8px rgba(255,105,180,0.15);
}
.gb-cmd-slot--used {
    opacity: 0.3;
    cursor: not-allowed;
    border-color: rgba(255,105,180,0.08);
}
.gb-cmd-slot--cd {
    opacity: 0.28;
    cursor: not-allowed;
    border-color: rgba(255,51,51,0.15);
    color: rgba(255,51,51,0.4);
}

/* Per command-type accent colours */
.gb-cmd-slot--offensive.gb-cmd-slot--ready { border-color: rgba(255,51,51,0.45); color: rgba(255,51,51,0.85); }
.gb-cmd-slot--offensive.gb-cmd-slot--ready:hover:not(:disabled) { border-color: #FF3333; color: #FF3333; background: rgba(255,51,51,0.07); box-shadow: 0 0 8px rgba(255,51,51,0.15); }
.gb-cmd-slot--stealth.gb-cmd-slot--ready  { border-color: rgba(125,249,255,0.4); color: rgba(125,249,255,0.85); }
.gb-cmd-slot--stealth.gb-cmd-slot--ready:hover:not(:disabled)  { border-color: #7DF9FF; color: #7DF9FF; background: rgba(125,249,255,0.07); box-shadow: 0 0 8px rgba(125,249,255,0.15); }
.gb-cmd-slot--defensive.gb-cmd-slot--ready{ border-color: rgba(0,255,136,0.4);  color: rgba(0,255,136,0.85); }
.gb-cmd-slot--defensive.gb-cmd-slot--ready:hover:not(:disabled){ border-color: #00FF88; color: #00FF88; background: rgba(0,255,136,0.07); box-shadow: 0 0 8px rgba(0,255,136,0.15); }
.gb-cmd-slot--trap.gb-cmd-slot--ready     { border-color: rgba(255,179,0,0.4);  color: rgba(255,179,0,0.85); }
.gb-cmd-slot--trap.gb-cmd-slot--ready:hover:not(:disabled)     { border-color: #FFB300; color: #FFB300; background: rgba(255,179,0,0.07); box-shadow: 0 0 8px rgba(255,179,0,0.15); }

.gb-cmd-slot-name { font-size: 8px; letter-spacing: 0.06em; }
.gb-cmd-slot-tier { font-size: 7px; opacity: 0.55; }

.gb-cmd-slot-state {
    font-size: 6px;
    letter-spacing: 0.1em;
    margin-left: 2px;
}
.state--rdy  { color: rgba(0,255,136,0.7); }
.state--used { color: rgba(255,255,255,0.2); }
.state--cd   { color: rgba(255,51,51,0.5); }

.gb-cmd-hint {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 8px;
    min-height: 14px;
}
.gb-cmd-hint-name   { color: #FF69B4; letter-spacing: 0.06em; flex-shrink: 0; }
.gb-cmd-hint-sep    { color: rgba(255,105,180,0.3); flex-shrink: 0; }
.gb-cmd-hint-effect { color: rgba(0,255,255,0.55); letter-spacing: 0.03em; }
.gb-cmd-hint-idle   { color: rgba(255,105,180,0.25); letter-spacing: 0.04em; font-style: italic; }
</style>
