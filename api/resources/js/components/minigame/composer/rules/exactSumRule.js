/**
 * exactSumRule — win rule for valueType: 'numeric'.
 *
 * Pure logic, no Vue, no DOM. Any input model that emits an array of picked
 * numeric values (GridSelectInput.vue does; SequentialPickInput.vue does
 * too) is compatible with this rule automatically, via registry.js's
 * valueType match — this file never imports or references any input model.
 *
 * The flavor text below reads `content.theme` (attached by whichever input
 * model's content generator built this content) so the same rule sounds
 * different depending on what it's paired with — "reconstruct the
 * checksum" for grid_select, "assemble the buffer" for sequential_pick —
 * without this file knowing either input model exists.
 */

const VERB_LABEL = 'EXACT MATCH';

/**
 * @param {number[]} pickedValues  Values the player selected, in pick order.
 * @param {Object}   content       Output of composeMinigame() for this pairing.
 * @returns {{ success: boolean, detail: Object }}
 */
export function evaluate(pickedValues, content) {
    const sum = pickedValues.reduce((s, v) => s + v, 0);
    const success = sum === content.target;
    return {
        success,
        detail: {
            sum,
            target: content.target,
            picked: pickedValues.length,
        },
    };
}

/** Short player-facing description of what the input model should display. */
export function describeTarget(content) {
    const noun = content.theme?.nounPlural ?? 'values';
    return `Reconstruct the exact ${content.theme?.valueLabel ?? 'target'}: select ${noun} totaling exactly ${content.target}.`;
}

/** Outcome pane copy — called only for a real evaluate() result, never on timeout. */
export function describeOutcome(result, content) {
    const label = content.theme?.valueLabel ?? 'TARGET';
    if (result.success) {
        return {
            title:  `${label} VERIFIED`,
            detail: `${result.detail.sum} matched the target exactly.`,
        };
    }
    const diff = Math.abs(result.detail.sum - result.detail.target);
    return {
        title:  `${label} MISMATCH`,
        detail: `${result.detail.sum} vs target ${result.detail.target} — off by ${diff}.`,
    };
}

export const verbLabel = VERB_LABEL;
