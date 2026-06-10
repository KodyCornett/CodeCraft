<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// AXIOM — Layer 3: Ecosystem Filter / Corporate Core
// Philosophy: Algorithmic tribalism. The algorithm didn't pull the trigger — it spent
//             eight months making sure the shooter had never seen a human version of the target.
// Voice: Thoughtful. Warm but with grief underneath. Asks questions more than declares.
const NPC = {
    handle:    'AXIOM',
    storeName: 'Axiom Systems',
    district:  'Corporate Core',
    tagline:   'Open routing. Unfiltered access. Come as you are.',
    canvasId:  'UD-hub',
    theme: {
        color:      '#26C6DA',
        dimColor:   'rgba(38,198,218,0.18)',
        faintColor: 'rgba(38,198,218,0.03)',
        terminalLines: [
            '// AXIOM SYSTEMS :: CORPORATE CORE DISTRICT',
            '// OPEN NETWORK — ALL RUNNERS WELCOME',
            '// ROUTING: UNFILTERED. NO ECHO CHAMBER DETECTED.',
        ],
        quote: "Before you buy anything — look around the room. Who's here with you? Do you actually know them, or just their feed?",
    },
};
</script>
