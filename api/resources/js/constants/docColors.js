/**
 * Accent colours for each CyberDoc, keyed by canvas hub ID.
 * Must stay in sync with the theme.color values in each CyberDoc*.vue page.
 */
export const DOC_COLORS = {
    'BA-hub': '#FF6B35',   // Knuckle — orange
    'NS-hub': '#00BFFF',   // Patch   — sky blue
    'DT-hub': '#CC44FF',   // Veil    — purple
    'UD-hub': '#FFD700',   // Axiom   — gold
    'SV-hub': '#00FF9D',   // Float   — green
};

/** Returns the accent colour for a doc's hub canvas ID, or a neutral fallback. */
export function docColor(hubCanvasId) {
    return DOC_COLORS[hubCanvasId] ?? '#a0c4b8';
}

/**
 * Returns the accent colour by matching the first word of a doc name to a handle.
 * e.g. "Knuckle's Med-Wagon" → '#FF6B35'
 */
const NAME_TO_HUB = {
    'knuckle': 'BA-hub',
    'patch':   'NS-hub',
    'veil':    'DT-hub',
    'axiom':   'UD-hub',
    'float':   'SV-hub',
};

export function docColorByName(docName) {
    const handle = docName?.match(/^([A-Za-z]+)/)?.[1]?.toLowerCase() ?? '';
    const hub    = NAME_TO_HUB[handle];
    return hub ? DOC_COLORS[hub] : '#a0c4b8';
}

/** Returns the hub canvas ID for a doc name — used to key colour lookups from quest state. */
export function docHubByName(docName) {
    const handle = docName?.match(/^([A-Za-z]+)/)?.[1]?.toLowerCase() ?? '';
    return NAME_TO_HUB[handle] ?? null;
}
