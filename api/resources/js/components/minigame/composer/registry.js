/**
 * composer/registry.js — declares the two independent axes the composer
 * combines: INPUT MODELS (how the player interacts) and WIN RULES (what
 * counts as success). Each declares a `valueType`; the composer only ever
 * pairs an input model with a win rule when those match, so a new input or
 * a new rule is automatically compatible with everything already registered
 * on the other axis, without either side importing or knowing about the
 * other. That's the whole point of the split — it's what lets a pairing
 * that was never explicitly hand-assembled turn out to actually work.
 *
 * This mirrors generator/pool.js's registry role but across two axes
 * instead of one flat list, and stays fully separate from that file —
 * nothing here is wired into the live hack flow. It's reached only through
 * the dev-only splice://dev/generator-lab route.
 */

export const INPUT_MODELS = [
    {
        key:       'grid_select',
        label:     'Grid Select',
        valueType: 'numeric',
        component: () => import('./inputs/GridSelectInput.vue'),
    },
    {
        key:       'sequential_pick',
        label:     'Sequential Pick',
        valueType: 'numeric',
        component: () => import('./inputs/SequentialPickInput.vue'),
    },
    {
        key:       'pair_match',
        label:     'Pair Match',
        valueType: 'pairs',
        component: () => import('./inputs/PairMatchInput.vue'),
    },
    {
        key:       'artifact_inspect',
        label:     'Artifact Inspect',
        valueType: 'artifacts',
        component: () => import('./inputs/ArtifactInspectInput.vue'),
    },
];

export const WIN_RULES = [
    {
        key:       'exact_sum',
        label:     'Exact Sum',
        valueType: 'numeric',
        module:    () => import('./rules/exactSumRule.js'),
    },
    {
        key:       'closest_under',
        label:     'Closest Under',
        valueType: 'numeric',
        module:    () => import('./rules/closestUnderRule.js'),
    },
    {
        key:       'all_matched',
        label:     'All Matched',
        valueType: 'pairs',
        module:    () => import('./rules/allMatchedRule.js'),
    },
    {
        key:       'spot_anomaly',
        label:     'Spot Anomaly',
        valueType: 'artifacts',
        module:    () => import('./rules/spotAnomalyRule.js'),
    },
];

export function findInputModel(key) {
    return INPUT_MODELS.find(m => m.key === key) ?? null;
}

export function findWinRule(key) {
    return WIN_RULES.find(r => r.key === key) ?? null;
}

/** Win rules whose valueType matches the given input model's valueType. */
export function compatibleRulesFor(inputKey) {
    const input = findInputModel(inputKey);
    if (!input) return [];
    return WIN_RULES.filter(rule => rule.valueType === input.valueType);
}

/** Input models whose valueType matches the given win rule's valueType. */
export function compatibleInputsFor(ruleKey) {
    const rule = findWinRule(ruleKey);
    if (!rule) return [];
    return INPUT_MODELS.filter(input => input.valueType === rule.valueType);
}
