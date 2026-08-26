/**
 * exactSumRule — win rule for valueType: 'numeric'.
 *
 * Pure logic, no Vue, no DOM. Any input model that emits an array of picked
 * numeric values (GridSelectInput.vue does; a future one could too) is
 * compatible with this rule automatically, via registry.js's valueType
 * match — this file never imports or references any input model.
 */

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
    return `Select cells summing to exactly ${content.target}.`;
}
