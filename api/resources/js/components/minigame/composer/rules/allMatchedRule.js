/**
 * allMatchedRule — first win rule for valueType: 'pairs'.
 *
 * Pure logic, no Vue. Compatible with any input model that emits an array
 * of { slotId, candidateId } assignments (pair_match does) — this file
 * never imports PairMatchInput.vue or any other input model.
 *
 * Different judgment shape again: exact_sum/closest_under score a single
 * summed number, this scores a SET of assignments against a hidden
 * correct-answer map. Success requires every slot correctly filled, no
 * partial credit — decoys make partial credit meaningless here (a decoy
 * assigned to an empty slot vs. left empty are both just "not done yet").
 */

/**
 * @param {Array<{slotId: string, candidateId: string}>} assignments
 * @param {Object} content   Must carry `slots` and `correctMap`.
 * @returns {{ success: boolean, detail: Object }}
 */
export function evaluate(assignments, content) {
    const total = content.slots.length;
    let correct = 0;
    for (const { slotId, candidateId } of assignments) {
        if (content.correctMap[slotId] === candidateId) correct++;
    }
    const success = correct === total && assignments.length === total;
    return {
        success,
        detail: {
            correct,
            total,
            submitted: assignments.length,
        },
    };
}

export function describeTarget(content) {
    const noun       = content.theme?.noun ?? 'slot';
    const nounPlural = content.theme?.nounPlural ?? 'slots';
    return `Each ${noun}'s TARGET is a candidate's fingerprint reversed — reverse a candidate's digits and see if they match a target, then assign it. ${content.slots.length} ${nounPlural} total, decoys included.`;
}

export function describeOutcome(result, content) {
    const label = content.theme?.valueLabel ?? 'DECRYPTION';
    if (result.success) {
        return {
            title:  `${label} COMPLETE`,
            detail: `All ${result.detail.total} slots matched correctly.`,
        };
    }
    return {
        title:  `${label} FAILED`,
        detail: `${result.detail.correct}/${result.detail.total} slots correct — decoys or mismatches slipped through.`,
    };
}

export const verbLabel = 'FULL DECRYPT';
