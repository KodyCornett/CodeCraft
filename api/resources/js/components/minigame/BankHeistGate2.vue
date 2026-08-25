<template>
    <!-- ── Phase 1 — MitM Handshake Spoof ───────────────────────────────────── -->
    <!-- Its own full-screen CLI overlay (see BankHeistHandshake.vue) rather than
         nested inside a shared wrapper — Phase 1 and Phase 2 are fundamentally
         different layouts (3-panel handshake terminal vs. the live queue/
         token-builder/harvest terminal), and only one is ever mounted at a time. -->
    <BankHeistHandshake
        v-if="phase === 'phase1'"
        :canvas-id="canvasId"
        :bank-ice="bankIce"
        :player-cpu="playerCpu"
        :player-ram="playerRam"
        @success="onHandshakeSuccess"
        @failed="onHandshakeFailed"
        @abort="$emit('abort')"
    />

    <!-- ── Phase 2 — Token Reconstruction & Risk Harvest ────────────────────── -->
    <BankHeistHarvest
        v-else
        :canvas-id="canvasId"
        :bank-name="bankName"
        :bank-ice="bankIce"
        :player-cpu="playerCpu"
        :player-ram="playerRam"
        :player-os="playerOs"
        @complete="onHarvestComplete"
        @failed="onHarvestFailed"
    />
</template>

<script setup>
import { ref } from 'vue';
import BankHeistHandshake from './BankHeistHandshake.vue';
import BankHeistHarvest from './BankHeistHarvest.vue';

const props = defineProps({
    canvasId:         { type: String, required: true },
    bankName:         { type: String, default: 'UNKNOWN TARGET' },
    bankIce:          { type: Number, required: true },
    bankTier:         { type: Number, required: true }, // no longer used client-side (Ledger sizing is gone) — kept so callers don't need to change, avoids a fallthrough-attrs warning
    playerCpu:        { type: Number, default: 3 },
    playerRam:        { type: Number, default: 2 },
    playerOs:         { type: Number, default: 2 },
    bountyMultiplier: { type: Number, default: 1.0 }, // reward multiplier is now applied server-side in phase2Reward(); kept for the same reason as bankTier above
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const phase = ref('phase1'); // 'phase1' | 'phase2'

// ── Phase 1 — MitM Handshake Spoof ──────────────────────────────────────────
// All the puzzle/timer logic lives in BankHeistHandshake.vue — this only
// reacts to its two outcomes.
function onHandshakeSuccess() {
    phase.value = 'phase2';
}

function onHandshakeFailed() {
    // Same "denied at the door" cost stack as a Gate 1 failure — handled
    // up in BankHeist.vue's resolveEntryFailure, which is where the
    // canvasId and resulting player-state sync are already assembled for
    // the 'complete' payload Game.vue expects.
    emit('failed', 'mitm_handshake');
}

// ── Phase 2 — Token Reconstruction & Risk Harvest ───────────────────────────
// All the queue/puzzle/trace-meter logic lives in BankHeistHarvest.vue —
// this only forwards its two outcomes upward.
function onHarvestComplete(payload) {
    emit('complete', payload);
}

function onHarvestFailed(failureApproach) {
    emit('failed', failureApproach);
}
</script>
