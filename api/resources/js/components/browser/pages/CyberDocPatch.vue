<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// PATCH — North Spokane
// Voice: Dry, deadpan, warm underneath. Short lines with real comic timing.
//        Delivers hard truths with a shrug and a joke rather than solemnity.
const NPC = {
    handle:    'PATCH',
    storeName: "Patch's Clinic",
    district:  'North Spokane',
    tagline:   "Field-grade repairs. Terrible system. I've filed several complaints.",
    canvasId:  'NS-hub',
    theme: {
        color:      '#EF5350',
        dimColor:   'rgba(239,83,80,0.18)',
        faintColor: 'rgba(239,83,80,0.03)',
        terminalLines: [
            '// PATCH CLINIC :: NORTH SPOKANE',
            '// TERMINAL ACTIVE — SOMEONE IS PROBABLY UPSIDE DOWN',
            '// MOST THINGS MAKE SENSE EVENTUALLY. THIS ONE DOESN\'T.',
        ],
        quote: "Oh. You survived. Good. Sit down before you fall down.",
    },
};
</script>
