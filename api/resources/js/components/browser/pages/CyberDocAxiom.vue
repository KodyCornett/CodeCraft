<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// AXIOM — Archivist, University District
// Voice: Formal, unhurried, dry. Short clipped lines, long implied pauses ("Mm.", "Well.").
//        Warmth expressed as attentiveness and wit, not overt comfort.
const NPC = {
    handle:    'AXIOM',
    storeName: 'Axiom Systems',
    district:  'University District',
    tagline:   'Understanding a thing and repairing a thing are not always the same profession.',
    canvasId:  'UD-hub',
    theme: {
        color:      '#FFD700',
        dimColor:   'rgba(255,215,0,0.18)',
        faintColor: 'rgba(255,215,0,0.03)',
        terminalLines: [
            '// AXIOM SYSTEMS :: UNIVERSITY DISTRICT',
            '// ARCHIVE ACCESS — ELEVEN YEARS AND COUNTING',
            '// CATALOGUING: PRE-COLLAPSE ARCHITECTURE',
        ],
        quote: "Mm. Quite curious. You do leave rather a trail behind you.",
    },
};
</script>
