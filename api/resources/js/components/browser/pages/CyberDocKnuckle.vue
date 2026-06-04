<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

const NPC = {
    handle:    'KNUCKLE',
    storeName: "Knuckle's Med-Wagon",
    district:  "Browne's Addition",
    tagline:   "Roll up, get patched. Questions cost extra.",
    canvasId:  'BA-hub',
};
</script>
