<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// KNUCKLE — Layer 1: Autonomic Dependency / Browne's Addition
// Philosophy: The body forgets how to survive. Convenience is the cage.
// Voice: Street-level. Blunt. Short declaratives. Controlled anger. No theorizing.
const NPC = {
    handle:    'KNUCKLE',
    storeName: "Knuckle's Med-Wagon",
    district:  "Browne's Addition",
    tagline:   "Cash. No questions. No records. Roll up.",
    canvasId:  'BA-hub',
    theme: {
        color:      '#FF6B35',
        dimColor:   'rgba(255,107,53,0.18)',
        faintColor: 'rgba(255,107,53,0.04)',
        terminalLines: [
            '// KNUCKLE MED-WAGON :: BROWNE\'S ADDITION',
            '// STATUS: OPEN — WALK-INS ONLY',
            '// POLICY: CASH. NO QUESTIONS. NO EXCEPTIONS.',
        ],
        quote: "Get patched. Get what you need. Get back out. This isn't a conversation.",
    },
};
</script>
