/**
 * correlateTraceRule — second win rule for valueType: 'artifacts'.
 *
 * Where spotAnomalyRule judges each artifact in isolation (is THIS one
 * field wrong), this rule judges a relationship BETWEEN two artifacts: in
 * every session but one, a log entry's claimed target host matches its
 * own session's certificate; in exactly one session it claims a different
 * (but still real, still valid-looking) host instead. The only way to
 * catch it is to reveal both halves of a session and read the hostname
 * each one names — this is the "prove it multiplies" pairing for the
 * artifacts family, the same move that added closest_under alongside
 * exact_sum for the numeric family.
 *
 * Pure logic, no Vue. Reads only the generic `artifacts` shape
 * composeMinigame() attaches to content — specifically each artifact's
 * `correlationMismatch` flag, which composeCorrelateTrace() stamps on
 * exactly one log entry per generated set. Never imports dataFeed.js or
 * ArtifactInspectInput.vue directly.
 */

/**
 * @param {string[]} flaggedIds  Artifact ids the player flagged.
 * @param {Object}   content     Must carry `artifacts` (each with `id`, `correlationMismatch`).
 * @returns {{ success: boolean, detail: Object }}
 */
export function evaluate(flaggedIds, content) {
    const trueMismatchIds = content.artifacts.filter(a => a.correlationMismatch).map(a => a.id);
    const flaggedSet = new Set(flaggedIds);
    const trueSet    = new Set(trueMismatchIds);

    const falsePositives = flaggedIds.filter(id => !trueSet.has(id));
    const missed          = trueMismatchIds.filter(id => !flaggedSet.has(id));
    const success = falsePositives.length === 0 && missed.length === 0;

    return {
        success,
        detail: {
            flaggedCount:       flaggedIds.length,
            trueMismatchCount:  trueMismatchIds.length,
            falsePositiveCount: falsePositives.length,
            missedCount:        missed.length,
        },
    };
}

export function describeTarget(content) {
    const noun       = content.theme?.noun ?? 'record';
    const nounPlural = content.theme?.nounPlural ?? 'records';
    return `Each session pairs one certificate with one connection log for the same host. Run the right command to pull both halves of a session, then compare the host each one names. In every session but one they agree — find the log entry that names the wrong host and flag it. ${content.artifacts.length} ${nounPlural} total, exactly one is mismatched.`;
}

export function describeOutcome(result, content) {
    const label = content.theme?.valueLabel ?? 'TRACE';
    if (result.success) {
        return { title: `${label} VERIFIED`, detail: 'Correctly traced the mismatched session.' };
    }
    if (result.detail.falsePositiveCount > 0 && result.detail.missedCount > 0) {
        return { title: `${label} FAILED`, detail: 'Flagged a correctly-correlated record AND missed the real mismatch.' };
    }
    if (result.detail.falsePositiveCount > 0) {
        return { title: `${label} FAILED`, detail: 'Flagged a record that actually correlates correctly.' };
    }
    return { title: `${label} FAILED`, detail: "Missed the session that doesn't correlate." };
}

export const verbLabel = 'CORRELATION TRACE';
