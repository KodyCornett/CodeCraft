/**
 * difficultyScaling — standalone ICE-tier difficulty config for the
 * composer/ subsystem ONLY.
 *
 * Deliberately NOT shared with generator/templates' own tier configs
 * (ChecksumBreach.vue, CipherBreach.vue, GridBreach.vue all keep their own
 * copies). The composer is an isolated experiment sitting behind a dev-only
 * SPLICE route — it must not create a dependency edge that could ripple into
 * the working, live-traffic pool if this file's shape changes later. Some
 * duplication now is the price of that isolation guarantee; if/when a
 * composer pairing gets promoted into generator/pool.js, its content
 * generator can be re-pointed at whichever tier config makes sense then.
 */

const MIN_ICE = 3;
const MAX_ICE = 10;

// Tier boundaries mirror the convention already established by GridBreach /
// the generator templates: ICE 3-4 -> Tier 1, 5-6 -> Tier 2, 7-8 -> Tier 3,
// 9-10 -> Tier 4.
function tierForIce(ice) {
    const clamped = Math.max(MIN_ICE, Math.min(MAX_ICE, ice));
    if (clamped <= 4) return 1;
    if (clamped <= 6) return 2;
    if (clamped <= 8) return 3;
    return 4;
}

/**
 * Normalized 0..1 difficulty value for a given ICE — for input models / win
 * rules to scale grid size, time limits, target ranges, etc. off of, without
 * every pairing having to re-derive its own tier math from raw ICE.
 */
function difficultyFactor(ice) {
    const clamped = Math.max(MIN_ICE, Math.min(MAX_ICE, ice));
    return (clamped - MIN_ICE) / (MAX_ICE - MIN_ICE); // 0 at ICE 3, 1 at ICE 10
}

export { MIN_ICE, MAX_ICE, tierForIce, difficultyFactor };
