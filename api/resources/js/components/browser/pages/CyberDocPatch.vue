<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

const NPC = {
    handle:    'PATCH',
    storeName: "Patch's Clinic",
    district:  'North Spokane',
    tagline:   'Field-grade repairs. No anesthetic, no records.',
};
</script>
