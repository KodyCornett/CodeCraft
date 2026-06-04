<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

const NPC = {
    handle:    'VEIL',
    storeName: "Veil's Parlour",
    district:  'Downtown',
    tagline:   'Premium installs. Discretion guaranteed.',
    canvasId:  'DT-hub',
};
</script>
