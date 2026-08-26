/**
 * closestUnderRule — second win rule for valueType: 'numeric'.
 *
 * Deliberately a different judgment shape than exactSumRule: instead of
 * matching a target exactly, the player must land AT OR UNDER the target
 * and within a tolerance band beneath it — a "get as close as you can
 * without going over" read. Never imports exactSumRule.js or either input
 * model — it only agrees with them on what a "numeric" value stream and a
 * `content.theme` look like.
 */

const VERB_LABEL = 'THRESHOLD LOCK';

/**
 * @param {number[]} pickedValues
 * @param {Object}   content   Must carry `target` and `tolerance`.
 * @returns {{ success: boolean, detail: Object }}
 */
export function evaluate(pickedValues, content) {
    const sum  = pickedValues.reduce((s, v) => s + v, 0);
    const over = sum > content.target;
    const withinBand = !over && sum >= (content.target - content.tolerance);
    return {
        success: withinBand,
        detail: {
            sum,
            target:    content.target,
            tolerance: content.tolerance,
            over,
        },
    };
}

export function describeTarget(content) {
    const noun = content.theme?.nounPlural ?? 'values';
    return `Lock the ${content.theme?.valueLabel ?? 'threshold'} without overflow: total ${noun} as close to ${content.target} as possible WITHOUT exceeding it — within ${content.tolerance}.`;
}

/** Outcome pane copy — called only for a real evaluate() result, never on timeout. */
export function describeOutcome(result, content) {
    if (result.success) {
        return {
            title:  'THRESHOLD LOCKED',
            detail: `${result.detail.sum} landed inside the ${result.detail.tolerance}-point margin under ${result.detail.target}.`,
        };
    }
    if (result.detail.over) {
        return {
            title:  'OVERFLOW TRIGGERED',
            detail: `${result.detail.sum} exceeded the ${result.detail.target} ceiling.`,
        };
    }
    return {
        title:  'MARGIN MISSED',
        detail: `${result.detail.sum} fell outside the ${result.detail.tolerance}-point margin under ${result.detail.target}.`,
    };
}

export const verbLabel = VERB_LABEL;
