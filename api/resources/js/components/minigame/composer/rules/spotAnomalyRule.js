/**
 * spotAnomalyRule — first win rule for valueType: 'artifacts'.
 *
 * Pure logic, no Vue. Compatible with any input model that emits an array
 * of flagged artifact ids (artifact_inspect does) — never imports
 * ArtifactInspectInput.vue or dataFeed.js directly; it only reads the
 * `artifacts`/`flawed` shape that composeMinigame() attaches to content.
 *
 * Judgment is exact: every truly flawed artifact must be flagged, and
 * nothing clean can be flagged by mistake. No partial credit — a real
 * security sweep doesn't get credit for "close."
 */

/**
 * @param {string[]} flaggedIds  Artifact ids the player flagged as compromised.
 * @param {Object}   content     Must carry `artifacts` (each with `id`, `flawed`).
 * @returns {{ success: boolean, detail: Object }}
 */
export function evaluate(flaggedIds, content) {
    const trueFlawedIds = content.artifacts.filter(a => a.flawed).map(a => a.id);
    const flaggedSet    = new Set(flaggedIds);
    const trueSet       = new Set(trueFlawedIds);

    const falsePositives = flaggedIds.filter(id => !trueSet.has(id));
    const missed          = trueFlawedIds.filter(id => !flaggedSet.has(id));
    const success = falsePositives.length === 0 && missed.length === 0;

    return {
        success,
        detail: {
            flaggedCount:       flaggedIds.length,
            trueFlawedCount:    trueFlawedIds.length,
            falsePositiveCount: falsePositives.length,
            missedCount:        missed.length,
        },
    };
}

export function describeTarget(content) {
    const noun       = content.theme?.noun ?? 'entry';
    const nounPlural = content.theme?.nounPlural ?? 'entries';
    // Generic on purpose — a mixed set can hold certs and log lines
    // together, which don't share fields like "issuer" or "hostname", so
    // this can't name specific attributes anymore. Every legitimate
    // artifact of a given kind still shares one baseline profile; the
    // player has to run the right command to see each one before they can
    // compare it.
    return `Run the right command against each locked ${noun} to pull its data, then compare it against the others of its kind. Every legitimate ${noun} shares the same baseline profile — find the one whose data deviates and flag it. ${content.artifacts.length} ${nounPlural} total, exactly one is compromised.`;
}

export function describeOutcome(result, content) {
    const label = content.theme?.valueLabel ?? 'AUDIT';
    if (result.success) {
        return { title: `${label} CLEARED`, detail: 'Correctly isolated the compromised entry.' };
    }
    if (result.detail.falsePositiveCount > 0 && result.detail.missedCount > 0) {
        return { title: `${label} FAILED`, detail: 'Flagged a clean entry AND missed the real one.' };
    }
    if (result.detail.falsePositiveCount > 0) {
        return { title: `${label} FAILED`, detail: 'Flagged a clean entry by mistake.' };
    }
    return { title: `${label} FAILED`, detail: 'Missed the compromised entry.' };
}

export const verbLabel = 'ANOMALY SWEEP';
