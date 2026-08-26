/**
 * closestUnderRule — second win rule for valueType: 'numeric'.
 *
 * Deliberately a different judgment shape than exactSumRule: instead of
 * matching a target exactly, the player must land AT OR UNDER the target
 * and within a tolerance band beneath it — a "get as close as you can
 * without going over" read. This is the proof that a second numeric win
 * rule can plug into an input model (or two) it was never written
 * alongside: it never imports exactSumRule.js or either input model, it
 * only agrees with them on what a "numeric" value stream looks like.
 */

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
    return `Total as close to ${content.target} as possible WITHOUT going over — within ${content.tolerance}.`;
}
