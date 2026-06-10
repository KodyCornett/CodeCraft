<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// PATCH — Layer 5: Sensory Cradle / North Spokane
// Philosophy: The simulation is a more perfect stimulus than reality. The only fix is removal.
// Voice: Quiet. Intense. Deliberate. No warmth. No celebration. No uncertainty admitted.
const NPC = {
    handle:    'PATCH',
    storeName: "Patch's Clinic",
    district:  'North Spokane',
    tagline:   'Field-grade repairs. No anesthetic. No records.',
    canvasId:  'NS-hub',
    theme: {
        color:      '#EF5350',
        dimColor:   'rgba(239,83,80,0.18)',
        faintColor: 'rgba(239,83,80,0.03)',
        terminalLines: [
            '// PATCH CLINIC :: NORTH SPOKANE',
            '// TERMINAL ACTIVE — SESSION UNMONITORED',
            '// THE BODY REMEMBERS WHAT THE MIND PREFERS TO FORGET.',
        ],
        quote: "You came here. That means something. Most don't bother.",
    },
};
</script>
