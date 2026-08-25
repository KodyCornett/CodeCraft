/**
 * useBankHeist
 *
 * Cross-gate state + server calls for the Bank Heist minigame. Mirrors
 * useRigDamage.js's thin axios-wrapper style — the countertrace/Anomaly
 * timers and the puzzles themselves are client-computed (same trust model
 * as GridBreach), this composable only reports discrete outcomes to
 * BankHeistController and returns the server-authoritative result.
 *
 * All formulas below are duplicated from BankHeistService.php on purpose —
 * they're locked, non-secret numbers (see BANK_HEIST_BUILD_PLAN.md), and
 * duplicating them here lets the client compute its own timer/queue the
 * same way GridBreach computes `difficulty` from node.ice, with zero
 * round-trips needed just to start playing. Phase 2's REWARD amount is the
 * one exception — a queue transaction's yield is invented client-side (no
 * server-known account ICE to recompute it from), so this file's yield
 * ranges are a display PREVIEW only; the server rolls the real number on
 * each successful inject and that's the only figure ever credited.
 */

import { ref } from 'vue';
import axios   from 'axios';

// ── Locked formula constants — keep in sync with BankHeistService.php ──────
const TIMER_BASE          = 45;
const TIMER_RAM_MULT      = 3;
const TIMER_BONUS_MULT    = 3;
const TIMER_PENALTY_MULT  = 2;
const TIMER_FLOOR         = 15;
const WRONG_ACTION_BASE   = 5;
const WRONG_ACTION_OS_MULT = 0.4;

// Gate 2 Phase 1 — MitM Handshake Spoof
// Set flat to 90s (1.5 min) per step after two rounds of doubling
// (5.0/6.5/3.5 -> 10.0/13.0/7.0 -> 20.0/26.0/14.0) still read as too fast in
// dev testing. Floor == base so a bad CPU-vs-ICE roll can never cut below
// the guaranteed 90s; a good roll's positive modifier still adds on top.
const HANDSHAKE_TIMER_BASE         = { syn: 90.0, syn_ack: 90.0, ack: 90.0 };
const HANDSHAKE_TIMER_FLOOR        = { syn: 90.0, syn_ack: 90.0, ack: 90.0 };
const HANDSHAKE_TIMER_RAM_MULT     = 0.3;
const HANDSHAKE_TIMER_BONUS_MULT   = 0.5;
const HANDSHAKE_TIMER_PENALTY_MULT = 0.3;
const HANDSHAKE_RETRY_RATCHET      = 0.8;
const HANDSHAKE_RETRY_FLOOR        = 2.0;
const HANDSHAKE_ICE_THRESHOLD        = 7;
const HANDSHAKE_CHUNK_COUNT_LOW_ICE  = 4;
const HANDSHAKE_CHUNK_COUNT_HIGH_ICE = 6;
const HANDSHAKE_COMBO_SIZE_LOW_ICE   = 2;
const HANDSHAKE_COMBO_SIZE_HIGH_ICE  = 3;

/** Base countertrace/Anomaly timer (seconds) — same shape for Gate 1 and every Gate 2 account crack. */
export function baseTimer(cpu, ram, ice) {
    const base = TIMER_BASE + ram * TIMER_RAM_MULT;
    const diff = cpu - ice;
    const mod  = diff >= 0 ? diff * TIMER_BONUS_MULT : -(diff ** 2) * TIMER_PENALTY_MULT;
    return Math.max(TIMER_FLOOR, base + mod);
}

/** Seconds docked off the running timer for one wrong probe/decrypt/key attempt. */
export function wrongActionPenalty(os) {
    return Math.max(1, WRONG_ACTION_BASE - Math.round(os * WRONG_ACTION_OS_MULT));
}

export function decoyCount(bankIce) {
    return bankIce;
}

// ── Gate 2 Phase 1 — MitM Handshake Spoof ───────────────────────────────────
// Three-step CLI puzzle (SYN → SYN-ACK → ACK). Timers/pool below mirror
// BankHeistService.php's handshake* methods exactly. The final timeout
// failure (any step) is reported via the same gate1Failed() call below,
// with approach: 'mitm_handshake' — see that PHP method's docblock for why
// it's shared across gates.

/** Base timer (seconds) for one handshake step. Same asymmetric CPU-vs-ICE shape as baseTimer(), much smaller constants — these are snappy few-second steps. */
export function handshakeStepTimer(step, cpu, ram, ice) {
    const base = HANDSHAKE_TIMER_BASE[step] + ram * HANDSHAKE_TIMER_RAM_MULT;
    const diff = cpu - ice;
    const mod  = diff >= 0 ? diff * HANDSHAKE_TIMER_BONUS_MULT : -(diff ** 2) * HANDSHAKE_TIMER_PENALTY_MULT;
    return Math.max(HANDSHAKE_TIMER_FLOOR[step], base + mod);
}

/** Timer for the Nth SYN-ACK attempt (1-indexed) — ratchets 0.8x per prior wrong-sum failure, floored at 2s. */
export function handshakeRetryTimer(baseSeconds, attemptNumber) {
    const ratcheted = baseSeconds * (HANDSHAKE_RETRY_RATCHET ** Math.max(0, attemptNumber - 1));
    return Math.max(HANDSHAKE_RETRY_FLOOR, ratcheted);
}

export function handshakeChunkCount(bankIce) {
    return bankIce >= HANDSHAKE_ICE_THRESHOLD ? HANDSHAKE_CHUNK_COUNT_HIGH_ICE : HANDSHAKE_CHUNK_COUNT_LOW_ICE;
}

export function handshakeComboSize(bankIce) {
    return bankIce >= HANDSHAKE_ICE_THRESHOLD ? HANDSHAKE_COMBO_SIZE_HIGH_ICE : HANDSHAKE_COMBO_SIZE_LOW_ICE;
}

function randInt(n) {
    return Math.floor(Math.random() * n);
}

/** All combinations of `comboSize` indices into `values` whose sum equals `target`. Small search space by design (max 6 choose 3 = 20), safe to brute-force. */
function findCombosSum(values, comboSize, target) {
    const results = [];
    function recurse(start, chosen) {
        if (chosen.length === comboSize) {
            if (chosen.reduce((s, i) => s + values[i], 0) === target) results.push([...chosen]);
            return;
        }
        for (let i = start; i < values.length; i++) {
            chosen.push(i);
            recurse(i + 1, chosen);
            chosen.pop();
        }
    }
    recurse(0, []);
    return results;
}

function buildChunkValues(targetAck, chunkCount, comboSize) {
    // Split targetAck into comboSize positive random shares (the "real" combo).
    const parts = [];
    let remaining = targetAck;
    for (let i = 0; i < comboSize - 1; i++) {
        const share = Math.max(1, Math.floor(remaining * (0.2 + Math.random() * 0.4)));
        parts.push(share);
        remaining -= share;
    }
    parts.push(Math.max(1, remaining));

    // Decoys sit in a similar numeric range to the real shares so they aren't
    // obviously wrong by magnitude alone.
    const avgShare = Math.round(targetAck / comboSize);
    const decoys = [];
    for (let i = 0; i < chunkCount - comboSize; i++) {
        decoys.push(Math.max(1, Math.round(avgShare * (0.4 + Math.random() * 1.2))));
    }

    const all = [...parts, ...decoys];
    for (let i = all.length - 1; i > 0; i--) {
        const j = randInt(i + 1);
        [all[i], all[j]] = [all[j], all[i]];
    }
    return all;
}

const CHUNK_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

/**
 * Generates one fresh handshake puzzle: a random SEQ number, its target ACK
 * (SEQ+1), and a labeled cipher chunk pool where exactly one combination of
 * `comboSize` chunks sums to the target ACK (regenerates internally until
 * the solution is unique, so there's never an accidental second answer).
 */
export function generateHandshakePuzzle(bankIce) {
    const chunkCount = handshakeChunkCount(bankIce);
    const comboSize  = handshakeComboSize(bankIce);
    const seq        = 1000 + randInt(9000);
    const targetAck  = seq + 1;

    let values = null;
    let correctIndices = null;
    for (let attempt = 0; attempt < 50 && !values; attempt++) {
        const candidate = buildChunkValues(targetAck, chunkCount, comboSize);
        const solutions = findCombosSum(candidate, comboSize, targetAck);
        if (solutions.length === 1) {
            values = candidate;
            correctIndices = solutions[0];
        }
    }
    // Fallback — vanishingly unlikely after 50 tries, but never leave the puzzle unsolved.
    if (!values) {
        values = buildChunkValues(targetAck, chunkCount, comboSize);
        correctIndices = Array.from({ length: comboSize }, (_, i) => i);
    }

    const chunks = values.map((value, i) => ({ label: CHUNK_LETTERS[i], value }));
    const correctLabels = correctIndices.map(i => CHUNK_LETTERS[i]).sort();

    return { seq, targetAck, chunks, comboSize, correctLabels };
}

// ── Gate 2 Phase 2 — Token Reconstruction & Risk Harvest ────────────────────
// Live transaction queue: intercept a TX, reconstruct its forged token from
// fragment candidates before its own timer expires, then EXTRACT to bank the
// running total or CONTINUE to push your luck. Mirrors
// BankHeistService.php's phase2* methods exactly for timers/pool sizing;
// the actual reward is rolled server-side per inject (see phase2Inject()
// below) — the ranges here are a display preview only.

const PHASE2_ICE_THRESHOLD        = 7;
const PHASE2_EASY_FRAGMENTS       = 4;
const PHASE2_HARD_FRAGMENTS_LOW   = 6;
const PHASE2_HARD_FRAGMENTS_HIGH  = 8;
const PHASE2_DECOY_COUNT          = 2;
const PHASE2_EASY_TIMER_RANGE     = [3.0, 5.0];
const PHASE2_HARD_TIMER_RANGE     = [6.0, 9.0];
const PHASE2_TIMER_ICE_CUT        = 0.75;
const PHASE2_TRACE_RATE_LOW_ICE   = 0.5;
const PHASE2_TRACE_RATE_HIGH_ICE  = 0.8;
const PHASE2_TRACE_SPIKE_LOW_ICE  = [20, 30];
const PHASE2_TRACE_SPIKE_HIGH_ICE = [30, 40];
const PHASE2_EASY_CRED_RANGE      = [75, 150];
const PHASE2_EASY_TECH_RANGE      = [15, 40];
const PHASE2_HARD_CRED_RANGE      = [350, 550];
const PHASE2_HARD_TECH_RANGE      = [70, 130];
const PHASE2_QUEUE_SIZE           = 4;

function randRange([min, max]) {
    return min + Math.random() * (max - min);
}
function randIntRange([min, max]) {
    return min + Math.floor(Math.random() * (max - min + 1));
}
function genHexStr(len) {
    const h = '0123456789ABCDEF';
    let out = '';
    for (let i = 0; i < len; i++) out += h[randInt(16)];
    return out;
}

/** Required fragment count for a HARD transaction — steps up at ICE 7+. EASY is always 4. */
export function phase2RequiredFragments(band, bankIce) {
    if (band === 'easy') return PHASE2_EASY_FRAGMENTS;
    return bankIce >= PHASE2_ICE_THRESHOLD ? PHASE2_HARD_FRAGMENTS_HIGH : PHASE2_HARD_FRAGMENTS_LOW;
}

export function phase2DecoyCount() {
    return PHASE2_DECOY_COUNT;
}

/** One randomized TX timer (seconds), already ICE-cut. */
export function phase2TxTimer(band, bankIce) {
    const rolled = randRange(band === 'easy' ? PHASE2_EASY_TIMER_RANGE : PHASE2_HARD_TIMER_RANGE);
    return bankIce >= PHASE2_ICE_THRESHOLD ? rolled * PHASE2_TIMER_ICE_CUT : rolled;
}

/** Global Trace Meter tick rate, %/sec — always running regardless of sub-step. */
export function phase2TraceRate(bankIce) {
    return bankIce >= PHASE2_ICE_THRESHOLD ? PHASE2_TRACE_RATE_HIGH_ICE : PHASE2_TRACE_RATE_LOW_ICE;
}

/** Global Trace spike on a wrong sequence / TX timeout. */
export function phase2TraceSpike(bankIce) {
    return randRange(bankIce >= PHASE2_ICE_THRESHOLD ? PHASE2_TRACE_SPIKE_HIGH_ICE : PHASE2_TRACE_SPIKE_LOW_ICE);
}

/** UI-preview yield only — the server rolls the real reward on inject; this never gets credited directly. */
export function previewPhase2Reward(band, currency) {
    if (band === 'easy') return randIntRange(currency === 'CRED' ? PHASE2_EASY_CRED_RANGE : PHASE2_EASY_TECH_RANGE);
    return randIntRange(currency === 'CRED' ? PHASE2_HARD_CRED_RANGE : PHASE2_HARD_TECH_RANGE);
}

let txCounter = 8800;

/** Generates one fresh queue transaction — band/currency/yield preview/required fragments/timer. */
export function generateTransaction(bankIce) {
    const band = Math.random() < 0.5 ? 'easy' : 'hard';
    const currency = Math.random() < 0.8 ? 'CRED' : 'TECH_PT';
    const timer = phase2TxTimer(band, bankIce);
    return {
        id: `TX-${++txCounter}`,
        band,
        currency,
        previewYield: previewPhase2Reward(band, currency),
        requiredFragments: phase2RequiredFragments(band, bankIce),
        timerTotal: timer,
        timeLeft: timer,
    };
}

const SLOT_DEFS = [
    { key: 'ORIG_SRC',    tag: 'SRC' },
    { key: 'ORIG_AMT',    tag: 'AMT' },
    { key: 'PLAYER_ID',   tag: 'ID' },
    { key: 'SALT_KEY',    tag: 'SALT' },
    { key: 'ORIG_CURR',   tag: 'CURR' },
    { key: 'CHECKSUM',    tag: 'CHK' },
    { key: 'TIMESTAMP',   tag: 'TS' },
    { key: 'SESSION_KEY', tag: 'SESS' },
];

function decoyValueFor(slotKey, correctValue, currency) {
    switch (slotKey) {
        case 'ORIG_SRC':    return `NODE_${randIntRange([1000, 9999])}`;
        case 'ORIG_AMT':    return String(Math.max(10, Math.round(Number(correctValue) * 0.2)));
        case 'PLAYER_ID':   return 'VAULT_01';
        case 'SALT_KEY':    return `0x${genHexStr(4)}`;
        case 'ORIG_CURR':   return currency === 'CRED' ? 'TECH_PT' : 'CRED';
        case 'CHECKSUM':    return 'MD5';
        case 'TIMESTAMP':   return String(randIntRange([1000000, 9999999]));
        case 'SESSION_KEY': return `0x${genHexStr(4)}`;
        default:            return '???';
    }
}

/**
 * Generates a fresh Token Builder puzzle for an intercepted transaction:
 * the ordered slot sequence it must be reassembled into, plus a shuffled
 * candidate fragment pool (required fragments + decoys). Exactly one
 * fragment per slot is correct; decoys reuse an existing slot's TAG with a
 * plausible-wrong VALUE, matching the design doc's F3/F4 example exactly.
 */
export function generateFragmentPuzzle(tx, playerTag) {
    const slots = SLOT_DEFS.slice(0, tx.requiredFragments);
    const values = {
        ORIG_SRC:    `NODE_${randIntRange([1000, 9999])}`,
        ORIG_AMT:    String(tx.previewYield),
        PLAYER_ID:   playerTag,
        SALT_KEY:    `0x${genHexStr(4)}`,
        ORIG_CURR:   tx.currency,
        CHECKSUM:    'SHA256',
        TIMESTAMP:   String(randIntRange([1000000, 9999999])),
        SESSION_KEY: `0x${genHexStr(4)}`,
    };

    const correctFragments = slots.map((slot, i) => ({
        id: `F${i + 1}`, tag: slot.tag, value: values[slot.key], hexPreview: genHexStr(2).toLowerCase(), correct: true,
    }));

    const decoySlots = [...slots].sort(() => Math.random() - 0.5).slice(0, PHASE2_DECOY_COUNT);
    const decoyFragments = decoySlots.map((slot, i) => ({
        id: `F${slots.length + i + 1}`,
        tag: slot.tag,
        value: decoyValueFor(slot.key, values[slot.key], tx.currency),
        hexPreview: genHexStr(2).toLowerCase(),
        correct: false,
    }));

    const displayOrder = [...correctFragments, ...decoyFragments].sort(() => Math.random() - 0.5);

    return {
        slots: slots.map(s => s.key),
        fragments: displayOrder,
        correctSequence: correctFragments.map(f => f.id),
    };
}

export function useBankHeist() {
    const busy = ref(false);

    async function post(url, body) {
        busy.value = true;
        try {
            const res = await axios.post(url, body);
            return res.data;
        } catch {
            return null;
        } finally {
            busy.value = false;
        }
    }

    /** @param {string} canvasId @param {'mitm_handshake'|'phase2_overrun'} approach */
    function gate1Failed(canvasId, approach) {
        return post(`/api/bank-heist/${canvasId}/gate1-failed`, { approach });
    }

    /** @param {string} canvasId @param {'easy'|'hard'} band @param {'CRED'|'TECH_PT'} currency */
    function phase2Inject(canvasId, band, currency) {
        return post(`/api/bank-heist/${canvasId}/phase2-inject`, { band, currency });
    }

    function phase2Extract(canvasId) {
        return post(`/api/bank-heist/${canvasId}/phase2-extract`, {});
    }

    return {
        busy,
        gate1Failed,
        phase2Inject,
        phase2Extract,
        // Pure helpers re-exported for convenience so components only import one module
        baseTimer, wrongActionPenalty, decoyCount,
        handshakeStepTimer, handshakeRetryTimer, handshakeChunkCount, handshakeComboSize,
        generateHandshakePuzzle,
        phase2RequiredFragments, phase2DecoyCount, phase2TxTimer, phase2TraceRate,
        phase2TraceSpike, previewPhase2Reward, generateTransaction, generateFragmentPuzzle,
        PHASE2_QUEUE_SIZE,
    };
}
