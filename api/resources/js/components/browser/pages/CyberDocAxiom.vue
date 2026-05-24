<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

const NPC = {
    handle:    'AXIOM',
    storeName: 'Axiom Systems',
    district:  'University District',
    tagline:   'Research-grade hardware. Academic pricing.',
};
</script>
