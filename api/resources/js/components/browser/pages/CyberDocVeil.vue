<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// VEIL — Layer 2: Structural Equilibrium / Downtown Core
// Philosophy: Load-bearing systems cannot be crashed without a replacement plan.
// Voice: Measured. Institutional. Precise. Occasionally exhausted. Thinks in systems.
const NPC = {
    handle:    'VEIL',
    storeName: "Veil's Parlour",
    district:  'Downtown Core',
    tagline:   'Precision installs. Infrastructure-grade discretion.',
    canvasId:  'DT-hub',
    theme: {
        color:      '#4FC3F7',
        dimColor:   'rgba(79,195,247,0.18)',
        faintColor: 'rgba(79,195,247,0.03)',
        terminalLines: [
            '// VEIL PARLOUR :: DOWNTOWN CORE',
            '// ACCESS TERMINAL — AUTHENTICATED',
            '// PROCEDURE: STANDARD MAINTENANCE PROTOCOL',
        ],
        quote: "I keep the lights on. That includes yours. Make your selections and let me get back to work.",
    },
};
</script>
