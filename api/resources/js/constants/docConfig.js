/**
 * Per-CyberDoc display config — handle, subtitle, location label, accent
 * colour, and ambient bed track. Keyed by doc slug (knuckle/patch/veil/axiom/float).
 *
 * Shared by DocDialoguePage.vue (live dialogue) and DevSceneLauncher.vue
 * (splice://dev/scenes scene splicer) so both render NPC dialogue identically.
 */
export const DOC_CONFIG = {
    knuckle: {
        npcHandle:     'KNUCKLE',
        npcSubtitle:   'STREET DOC // BROWNE\'S ADDITION',
        locationLabel: 'BA-HUB // KNUCKLE\'S MED-WAGON',
        accentColor:   '#FF6B35',
        district:      "Browne's Addition",
        ambientSrc:    'k_node_BG.mp3',
    },
    patch: {
        npcHandle:     'PATCH',
        npcSubtitle:   'FIELD MEDIC // NORTH SPOKANE',
        locationLabel: 'NS-HUB // PATCH\'S CLINIC',
        accentColor:   '#00FFC8',
        district:      'North Spokane',
        ambientSrc:    'p_node_BG.mp3',
    },
    veil: {
        npcHandle:     'VEIL',
        npcSubtitle:   'IMPLANT ARTIST // DOWNTOWN',
        locationLabel: 'DT-HUB // VEIL\'S PARLOUR',
        accentColor:   '#B06FFF',
        district:      'Downtown',
        ambientSrc:    'v_node_BG.mp3',
    },
    axiom: {
        npcHandle:     'AXIOM',
        npcSubtitle:   'SYSTEMS TECH // UNIVERSITY DISTRICT',
        locationLabel: 'UD-HUB // AXIOM SYSTEMS',
        accentColor:   '#FFD700',
        district:      'University District',
        ambientSrc:    null,
    },
    float: {
        npcHandle:     'FLOAT',
        npcSubtitle:   'REPAIR SPECIALIST // SPOKANE VALLEY',
        locationLabel: 'SV-HUB // FLOAT\'S REPAIR BAY',
        accentColor:   '#00BFFF',
        district:      'Spokane Valley',
        ambientSrc:    'f_node_BG.mp3',
    },
};
