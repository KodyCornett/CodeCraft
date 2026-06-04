<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

const NPC = {
    handle:    'FLOAT',
    storeName: "Float's Repair Bay",
    district:  'Spokane Valley',
    tagline:   'East-side tech. Competitive rates, fast hands.',
    canvasId:  'SV-hub',
};
</script>
