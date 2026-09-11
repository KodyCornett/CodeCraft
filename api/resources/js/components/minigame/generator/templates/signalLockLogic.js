/**
 * signalLockLogic
 *
 * Pure, Vue-free game logic for SignalLock.vue's rebuilt "isolate a WiFi
 * device and call its intent" loop — round/match generation, difficulty
 * tables, and signal-history sampling. Split out from the component itself
 * (same reasoning as rewardFormula.js being its own module) so the actual
 * puzzle-generation logic can be unit-tested directly with plain Node,
 * without mounting a Vue component.
 *
 * See SignalLock.vue's script setup docblock for the full design rationale
 * (why this replaced the v1 rule-matching table, what each clue means).
 */

export function randInt(min, max) { return Math.floor(min + Math.random() * (max - min + 1)); }
export function randHexByte()     { return randInt(0, 255).toString(16).toUpperCase().padStart(2, '0'); }
export function randTriplet()     { return [randHexByte(), randHexByte(), randHexByte()]; }
export function macString(bytes)  { return bytes.join(':'); }

export function nearMissTriplet(triplet) {
    const idx = randInt(0, 2);
    const clone = [...triplet];
    let next;
    do { next = randHexByte(); } while (next === clone[idx]);
    clone[idx] = next;
    return clone;
}

export function shuffleInPlace(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = randInt(0, i);
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

// ─── ICE — this game's actual ceiling is 8, not 10 ────────────────────────────
export const MIN_ICE = 3;
export const MAX_ICE = 8;
export function clampIce(ice) {
    return Math.min(MAX_ICE, Math.max(MIN_ICE, ice ?? MIN_ICE));
}

// ─── Match structure — round count + win/lose thresholds by ICE ─────────────
// Capped at 5 rounds. Losing is "wrong answers make a win mathematically
// impossible" — the standard best-of-N elimination point:
//   loseAt = rounds - need + 1
export function matchConfigForIce(ice) {
    if (ice <= 3) return { rounds: 3, need: 2, loseAt: 2 };
    if (ice === 4) return { rounds: 4, need: 3, loseAt: 2 };
    return { rounds: 5, need: 3, loseAt: 3 }; // ICE 5-8
}

// ─── Round device/decoy density by ICE ───────────────────────────────────────
export function deviceConfigForIce(ice) {
    if (ice <= 4) return { deviceCount: 4, sharedPrefixCount: 0 };
    if (ice <= 6) return { deviceCount: 5, sharedPrefixCount: 1 };
    return { deviceCount: 6, sharedPrefixCount: 2 }; // ICE 7-8
}

// How far a shared-prefix decoy's ping range sits from the target's own
// range — shrinks as ICE rises, but revealPing()'s jitter is always small
// enough relative to this that the ranges never actually touch (verified in
// _sgl_logic_test.mjs), so there's always exactly one right answer even
// when the gap "feels" tight.
export function pingGapForIce(ice) {
    return ice <= 6 ? 15 : 8;
}

// ─── Signal-strength history generation ──────────────────────────────────────
// STABLE holds tight to a base value. UNSTABLE decays/swings noticeably.
// genSigSeed is called once per row on the FIRST `scan sig` — it returns a
// 3-point history so the trend reads immediately instead of needing several
// manual re-scans before it means anything. genSigNext appends one more
// sample for a later `scan sig` call (free/unlimited, per the design brief).
export function genSigSeed(category) {
    if (category === 'STABLE') {
        const base = randInt(45, 75);
        return [base, base + randInt(-2, 2), base + randInt(-2, 2)];
    }
    const base = randInt(55, 90);
    return [base, base - randInt(8, 18), base - randInt(15, 30)];
}
export function genSigNext(category, last) {
    if (category === 'STABLE') return Math.max(1, last + randInt(-2, 2));
    return Math.max(1, last - randInt(4, 14));
}

// ─── Round generation ─────────────────────────────────────────────────────────
// One target device + N decoys. Two independent clues identify the target:
//   - a partial MAC prefix (targetPrefixLabel), always shown
//   - PING, needed only when a decoy shares the exact prefix (ICE 5+)
// SIG HISTORY plays no role in *finding* the target — it's read on whichever
// row the player commits to, to decide the correct verb (see
// SignalLock.vue's standingDirective).
export function generateSignalRound(ice) {
    const { deviceCount, sharedPrefixCount } = deviceConfigForIce(ice);
    const pingGap = pingGapForIce(ice);

    const targetPrefix = randTriplet();
    const targetSuffix  = randTriplet();
    const targetMac     = macString([...targetPrefix, ...targetSuffix]);
    const targetSigCategory = Math.random() < 0.5 ? 'STABLE' : 'UNSTABLE';
    const targetPingBase   = randInt(24, 40);
    const targetPingJitter = 2;

    const rows = [{
        label: null,
        mac: targetMac,
        isTarget: true,
        sigCategory: targetSigCategory,
        pingBase: targetPingBase,
        pingJitter: targetPingJitter,
        macRevealed: false,
        sigHistory: [],
        pingValue: null,
    }];

    const decoyCount = deviceCount - 1;
    for (let i = 0; i < decoyCount; i++) {
        const shared = i < sharedPrefixCount;
        const prefix = shared ? targetPrefix : nearMissTriplet(targetPrefix);
        const suffix = randTriplet();
        const mac = macString([...prefix, ...suffix]);

        let pingBase;
        let pingJitter = 2;
        if (shared) {
            pingJitter = ice <= 6 ? 2 : 3;
            const above = Math.random() < 0.5;
            const clearance = pingGap + pingJitter + targetPingJitter;
            pingBase = above ? targetPingBase + clearance : targetPingBase - clearance;
        } else {
            pingBase = randInt(15, 70);
        }

        rows.push({
            label: null,
            mac,
            isTarget: false,
            sigCategory: Math.random() < 0.5 ? 'STABLE' : 'UNSTABLE',
            pingBase,
            pingJitter,
            macRevealed: false,
            sigHistory: [],
            pingValue: null,
        });
    }

    shuffleInPlace(rows);
    rows.forEach((r, i) => { r.label = `R${i + 1}`; });

    return {
        rows,
        targetPrefixLabel: `${macString(targetPrefix)}:??:??:??`,
    };
}

// ─── Command input parsing — pure so the fiddly token/autocomplete rules ────
// can be unit-tested directly instead of only trusted by inspection inside
// the component. See SignalLock.vue's onInputKeydown for how this drives
// arrow-key navigation and Enter-to-accept.
export const VERBS      = ['scan', 'log', 'jam'];
export const SCAN_ARGS   = ['mac', 'sig', 'ping'];

/**
 * Given the raw text currently in the command input and the list of row
 * labels valid this round (e.g. ['R1','R2','R3']), work out which token the
 * player is currently typing ('verb' or 'arg') and what the autocomplete
 * dropdown should offer for it.
 */
export function parseCommandState(inputText, rowLabels) {
    const tokens        = inputText.split(/\s+/).filter(Boolean);
    const endsWithSpace = /\s$/.test(inputText);
    const phase = (tokens.length === 0 || (tokens.length === 1 && !endsWithSpace)) ? 'verb' : 'arg';

    const verbToken = (tokens[0] ?? '').toLowerCase();
    const argToken  = (phase === 'arg' ? (tokens[1] ?? '') : '').toLowerCase();

    let suggestions;
    if (phase === 'verb') {
        suggestions = VERBS.filter(v => v.startsWith(verbToken));
    } else if (verbToken === 'scan') {
        suggestions = SCAN_ARGS.filter(a => a.startsWith(argToken));
    } else if (verbToken === 'log' || verbToken === 'jam') {
        suggestions = rowLabels.map(l => l.toLowerCase()).filter(l => l.startsWith(argToken));
    } else {
        suggestions = [];
    }

    return { tokens, phase, verbToken, argToken, suggestions };
}
