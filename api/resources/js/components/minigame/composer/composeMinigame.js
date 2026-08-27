/**
 * composeMinigame — given a chosen (input, rule) pairing and an ICE value,
 * produces the actual playable content for it.
 *
 * Content generation is scoped per-pairing (keyed by "inputKey:ruleKey"),
 * because the shape of content a pairing needs is primarily an INPUT MODEL
 * concern (a grid needs rows/cols/values, a sequential feed needs a flat
 * list) rather than a win-rule concern. In practice that means a single
 * content generator often serves every win rule a given input model is
 * compatible with — both grid_select pairings below point at the same
 * function, and both sequential_pick pairings point at the other — since
 * exact_sum and closest_under both just read whichever of `target` /
 * `tolerance` they individually care about off the same content object.
 * That won't always hold (a future input/rule combo might genuinely need
 * different content per rule), which is exactly why this stays keyed by
 * the full pairing instead of by input model alone.
 */
import { tierForIce } from './difficultyScaling.js';
import { generateArtifactSet, randomHostname, generateCorrelatedSet } from './dataFeed.js';
import { selectCommands } from './commandPalette.js';

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

// Shared by every pairing below that needs "a pool of distinct numbers" —
// wide range (1-99), no repeats. Distinct wide-range values are what
// actually make a numeric target/tolerance puzzle require real arithmetic
// instead of pattern-spotting duplicate digits (learned the hard way from
// the first playtest of grid_select:exact_sum).
function distinctNumberPool(count) {
    const pool = [];
    for (let n = 1; n <= 99; n++) pool.push(n);
    shuffle(pool);
    return pool.slice(0, count);
}

// Reachable target: sum a real random subset of the values actually in
// play, so at least one valid solution always exists regardless of which
// numeric win rule ends up reading it.
function pickReachableTarget(values, minPick, maxPick) {
    const indices = values.map((_, i) => i);
    shuffle(indices);
    const pickCount = minPick + Math.floor(Math.random() * (maxPick - minPick + 1));
    return indices.slice(0, pickCount).reduce((sum, i) => sum + values[i], 0);
}

// grid_select — feeds both exact_sum and closest_under. Player selects any
// subset of cells; target/tolerance are read by whichever rule is paired.
function composeGridSelect(ice) {
    const tier = tierForIce(ice);

    const rows    = 5;
    const cols    = 6 + tier * 2; // Tier 1: 8, Tier 2: 10, Tier 3: 12, Tier 4: 14
    const minPick = 2 + tier;     // Tier 1: 3 .. Tier 4: 6
    const maxPick = minPick + 2;  // Tier 1: 5 .. Tier 4: 8

    const flat = distinctNumberPool(rows * cols);
    const values = [];
    for (let r = 0; r < rows; r++) values.push(flat.slice(r * cols, (r + 1) * cols));

    const target    = pickReachableTarget(flat, minPick, maxPick);
    // Tolerance band shrinks as ICE rises — Tier 1: wide (~26), Tier 4: thin (~10).
    const tolerance = Math.max(10, 34 - tier * 6);

    // Steeper time drop-off than the very first cut of this pairing — with
    // real arithmetic now required instead of pattern-spotting, tier should
    // cost meaningfully more time pressure: Tier 1: 90s .. Tier 4: 30s.
    const timeLimitSec = Math.max(30, 110 - tier * 20);

    // theme — the flavor layer. Attached by the INPUT MODEL's content
    // generator (not the win rule), same split as everything else here: a
    // win rule's describeTarget()/describeOutcome() reads these generic
    // fields to phrase itself, without ever needing to know it's looking
    // at a grid specifically. Mirrors QuestMinigame's skin object, just
    // generated instead of hand-authored per quest.
    const theme = {
        systemLabel: 'MEMORY SECTOR MAP',
        noun:        'fragment',
        nounPlural:  'fragments',
        valueLabel:  'CHECKSUM',
    };

    return { rows, cols, values, target, tolerance, timeLimitSec, theme };
}

// sequential_pick — feeds both exact_sum and closest_under. Player steps
// through a one-way feed of numbers, committing to TAKE or SKIP each one.
function composeSequentialPick(ice) {
    const tier = tierForIce(ice);

    const length  = 10 + tier * 4; // Tier 1: 14 .. Tier 4: 26 numbers in the feed
    const minPick = 2 + tier;
    const maxPick = minPick + 2;

    const sequence = distinctNumberPool(length);
    const target    = pickReachableTarget(sequence, minPick, maxPick);
    const tolerance = Math.max(10, 34 - tier * 6);

    // The one-way, no-undo feed is this pairing's own difficulty lever
    // (distinct from grid_select's fully-visible search space), so the
    // time budget doesn't need to drop as hard per tier — length already
    // does most of the scaling.
    const timeLimitSec = Math.max(30, 100 - tier * 14);

    // See composeGridSelect's theme comment — same idea, different system.
    const theme = {
        systemLabel: 'PACKET STREAM',
        noun:        'packet',
        nounPlural:  'packets',
        valueLabel:  'BUFFER TOTAL',
    };

    return { sequence, target, tolerance, timeLimitSec, theme };
}

// pair_match — feeds all_matched (valueType: 'pairs'). Concept ported from
// ArchiveExtraction.vue: a small fixed number of target "cipher slots",
// each with exactly one correct candidate, buried among more decoy
// candidates than there are slots. Reuses that system's own vocabulary
// (slots, candidates, hex-string labels) so it reads consistent with the
// rest of the game rather than inventing new terms — but this function
// never imports ArchiveExtraction.vue or shares any of its state.
//
// TUNING NOTE (v2): the first cut assigned each slot's correct candidate
// with no signal the player could ever discover — ArchiveExtraction's real
// version gives players something to deduce (plaintext found elsewhere in
// the world), which the composer has no equivalent clue system for yet.
// Without ANY deducible signal it was pure guessing dressed up as a
// puzzle. Fix: each slot displays a TARGET that is the correct candidate's
// label reversed — a small, honestly-solvable fingerprint puzzle (reverse
// each candidate, see if it matches a slot's target) instead of a coin
// flip. Reads consistent with the checksum/fingerprint framing already in
// the theme, and decoys are checked to never accidentally reverse into a
// real target.
function hexLabel(len = 6) {
    const chars = '0123456789abcdef';
    let out = '';
    for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
    return out;
}

function reverseStr(s) {
    return s.split('').reverse().join('');
}

function composePairMatch(ice) {
    const tier = tierForIce(ice);

    const slotCount  = 3; // mirrors ArchiveExtraction's fixed 3 target cipher slots
    // ArchiveExtraction scales decoyPairCount 2->4 across its 3 quest
    // tiers; carried a bit further here since composer ICE spans a wider
    // 3-10 band. Tier 1: 3 decoys .. Tier 4: 6 decoys.
    const decoyCount = 2 + tier;
    const labelLen   = 6; // short enough to reverse-check by eye

    const slots = [];
    const correctCandidates = [];
    for (let i = 0; i < slotCount; i++) {
        const slotId    = `slot_${i}`;
        const candLabel = hexLabel(labelLen);
        slots.push({ id: slotId, label: `CIPHER SLOT ${i + 1}`, target: reverseStr(candLabel) });
        correctCandidates.push({ id: `cand_correct_${slotId}`, label: candLabel, forSlot: slotId });
    }

    // Decoys: random labels, regenerated on the rare collision where a
    // decoy's own reversal happens to match a real target — otherwise the
    // puzzle would have two "correct-looking" candidates for one slot.
    const targets = new Set(slots.map(s => s.target));
    const decoyCandidates = [];
    for (let i = 0; i < decoyCount; i++) {
        let label;
        do {
            label = hexLabel(labelLen);
        } while (targets.has(reverseStr(label)));
        decoyCandidates.push({ id: `cand_decoy_${i}`, label, forSlot: null });
    }

    const candidates = shuffle([...correctCandidates, ...decoyCandidates])
        .map(({ id, label }) => ({ id, label }));

    const correctMap = {};
    correctCandidates.forEach(c => { correctMap[c.forSlot] = c.id; });

    // Tier 1: 125s .. Tier 4: 50s — same order of magnitude as
    // ArchiveExtraction's own 95-150s trace budget.
    const timeLimitSec = Math.max(45, 150 - tier * 25);

    const theme = {
        systemLabel: 'CIPHER SLOT MATRIX',
        noun:        'slot',
        nounPlural:  'slots',
        valueLabel:  'DECRYPTION',
    };

    return { slots, candidates, correctMap, timeLimitSec, theme };
}

// artifact_inspect — feeds spot_anomaly (valueType: 'artifacts'). Uses
// dataFeed.js's generator directly: a MIXED set of fake certs and log
// lines (kind: 'mixed') with exactly one deliberately flawed, real fields
// underneath so the flaw is something a player can actually reason about
// (CN mismatch, expired cert, suspicious port) rather than an arbitrary
// transform. Mixed kinds is what makes command selection matter at all —
// a single-kind set only ever needed one real command, so there was
// nothing to choose between.
//
// Command palette is chosen per instance too, not the fixed global list:
// selectCommands() guarantees the real command for every kind actually
// present is on the palette (the puzzle always stays solvable), then adds
// a handful of decoys so picking the right tool per target is itself part
// of the challenge.
function composeArtifactInspect(ice) {
    const tier = tierForIce(ice);

    const count       = 3 + tier; // Tier 1: 4 .. Tier 4: 7 artifacts to inspect
    const flawedCount = 1;        // exactly one compromised entry — keeps judgment binary

    const hostname  = randomHostname(); // shared target host across every cert in the set
    const artifacts = generateArtifactSet({ kind: 'mixed', count, flawedCount, hostname });

    const kindsPresent = [...new Set(artifacts.map(a => a.kind))];
    // Decoy count grows a little with tier — more noise to sift through
    // at higher difficulty. Tier 1: 1 decoy .. Tier 4: 3 decoys.
    const commands = selectCommands(kindsPresent, 1 + Math.floor(tier / 2));

    // Tier 1: 85s .. Tier 4: 40s
    const timeLimitSec = Math.max(30, 100 - tier * 15);

    // Generic theme — a mixed set can't claim to be just a "certificate
    // audit" anymore, so this reads as a general intel sweep instead.
    const theme = {
        systemLabel: 'MIXED INTEL SWEEP',
        noun:        'target',
        nounPlural:  'targets',
        valueLabel:  'INTEGRITY',
    };

    return { hostname, artifacts, commands, timeLimitSec, theme };
}

// artifact_inspect + correlate_trace — sessions of (cert, log) pairs for
// valueType: 'artifacts'. Where spot_anomaly judges a single artifact in
// isolation, this pairing requires revealing BOTH halves of a session and
// comparing them: the compromise here isn't a broken field on one
// artifact, it's a log entry naming a DIFFERENT host than its own
// session's certificate — a real correlation problem, not a lone typo.
// This is the "prove it multiplies" pairing for the artifacts family, the
// same move that added closest_under alongside exact_sum for numeric.
function composeCorrelateTrace(ice) {
    const tier = tierForIce(ice);

    const pairCount = 2 + tier; // Tier 1: 3 sessions (6 artifacts) .. Tier 4: 6 sessions (12 artifacts)
    const artifacts = generateCorrelatedSet({ pairCount });

    // Both kinds are always present here (every session is one cert + one
    // log), so both real commands are always required on the palette —
    // decoys still keep picking the right tool per target meaningful.
    const commands = selectCommands(['cert', 'log'], 1 + Math.floor(tier / 2));

    // A bit more generous than spot_anomaly's budget at the same tier —
    // this pairing requires revealing TWO artifacts per session instead of
    // judging one in isolation, so there's simply more reading to do.
    // Tier 1: 110s .. Tier 4: 55s
    const timeLimitSec = Math.max(40, 130 - tier * 20);

    const theme = {
        systemLabel: 'SESSION CORRELATION',
        noun:        'record',
        nounPlural:  'records',
        valueLabel:  'CHAIN INTEGRITY',
    };

    return { artifacts, commands, timeLimitSec, theme };
}

const CONTENT_GENERATORS = {
    'grid_select:exact_sum':          composeGridSelect,
    'grid_select:closest_under':      composeGridSelect,
    'sequential_pick:exact_sum':      composeSequentialPick,
    'sequential_pick:closest_under':  composeSequentialPick,
    'pair_match:all_matched':         composePairMatch,
    'artifact_inspect:spot_anomaly':  composeArtifactInspect,
    'artifact_inspect:correlate_trace': composeCorrelateTrace,
};

export function composeMinigame({ inputKey, ruleKey, ice }) {
    const genKey    = `${inputKey}:${ruleKey}`;
    const generator = CONTENT_GENERATORS[genKey];
    if (!generator) {
        throw new Error(`[composer] No content generator registered for pairing "${genKey}".`);
    }
    return { inputKey, ruleKey, ice, content: generator(ice) };
}

/** Pairing keys that currently have a content generator wired up. */
export function availablePairingKeys() {
    return Object.keys(CONTENT_GENERATORS);
}
