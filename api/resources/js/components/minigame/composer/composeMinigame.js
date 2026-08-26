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

const CONTENT_GENERATORS = {
    'grid_select:exact_sum':          composeGridSelect,
    'grid_select:closest_under':      composeGridSelect,
    'sequential_pick:exact_sum':      composeSequentialPick,
    'sequential_pick:closest_under':  composeSequentialPick,
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
