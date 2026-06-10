<template>
    <CyberDocStore :npc="NPC" />
</template>

<script setup>
import CyberDocStore from './CyberDocStore.vue';

// FLOAT — Layer 4: Historical Ledger / Spokane Valley (University District)
// Philosophy: The past is being edited. Float archives what is being deleted.
// Voice: Clinical. Archival. Flat. The emotional weight lives in what they show — not how they speak.
const NPC = {
    handle:    'FLOAT',
    storeName: "Float's Repair Bay",
    district:  'Spokane Valley',
    tagline:   'All transactions logged. All records preserved.',
    canvasId:  'SV-hub',
    theme: {
        color:      '#90A4AE',
        dimColor:   'rgba(144,164,174,0.18)',
        faintColor: 'rgba(144,164,174,0.03)',
        terminalLines: [
            '// FLOAT REPAIR BAY :: SPOKANE VALLEY',
            '// ARCHIVE NODE — READ/WRITE ACCESS GRANTED',
            '// NOTE: THIS TRANSACTION WILL NOT BE DELETED.',
        ],
        quote: "Your visit is on record. Unlike some visits to this district — this one will remain.",
    },
};
</script>
