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
    return `Inspect each ${noun} and flag the one that's compromised. ${content.artifacts.length} ${nounPlural} total — exactly one is wrong.`;
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
