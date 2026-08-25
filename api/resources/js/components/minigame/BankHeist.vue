<template>
    <div class="bh-overlay">

        <!-- ── Gate 1 — MitM Handshake Hijack ──────────────────────────────────── -->
        <BankHeistHandshake
            v-if="stage === 'gate1'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            @success="onGate1Success"
            @failed="onGate1Failed"
            @abort="$emit('abort')"
        />

        <!-- ── Gate 2 — Ledger Reconstruction & Harvest ────────────────────────── -->
        <BankHeistHarvest
            v-else-if="stage === 'gate2'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            :player-os="playerOs"
            @complete="onGate2Complete"
            @failed="onGate2Failed"
        />

    </div>
</template>

<script setup>
import { ref } from 'vue';
import BankHeistHandshake from '@/components/minigame/BankHeistHandshake.vue';
import BankHeistHarvest from '@/components/minigame/BankHeistHarvest.vue';
import { useBankHeist } from '@/composables/useBankHeist.js';

const props = defineProps({
    canvasId:         { type: String, required: true },
    bankName:         { type: String, default: 'UNKNOWN TARGET' },
    bankIce:          { type: Number, required: true },
    bankTier:         { type: Number, required: true }, // no longer used client-side (Ledger sizing is gone) — kept so Game.vue's mount doesn't need to change
    playerCpu:        { type: Number, default: 3 },
    playerRam:        { type: Number, default: 2 },
    playerOs:         { type: Number, default: 2 },
    bountyMultiplier: { type: Number, default: 1.0 }, // reward multiplier is applied server-side in phase2Reward(); kept for the same reason as bankTier above
});

// 'complete' forwards Gate 2's final { totalCreds, totalTech, lockdown } payload up to
// whoever mounted us (Game.vue), which is responsible for applying rewards/SS/bounty
// exactly as BankHeistController's responses already did server-side per event — this
// component itself never touches player state directly.
const emit = defineEmits(['complete', 'abort']);

const bh = useBankHeist();

const stage = ref('gate1'); // 'gate1' | 'gate2' — Gate 1 IS the MitM Handshake Hijack itself; there is no separate screen in front of it

function onGate1Success() {
    stage.value = 'gate2';
}

// Shared "denied at the door" resolution — Gate 1's (MitM Handshake) timeout
// and Gate 2's (Harvest) Global Trace overrun both cost the same thing (see
// BankHeistService::resolveGate1Failure's docblock), so every failure path
// below funnels through this one call.
async function resolveEntryFailure(failureApproach) {
    const res = await bh.gate1Failed(props.canvasId, failureApproach);
    // Same field-merge shape as Gate 2's mergePlayerSync — an entry failure
    // never touches creds/tech, only SS/bounty/cooldown, but keeping the
    // shape consistent lets Game.vue apply both through one path.
    const playerSync = {};
    if (res?.bounty_level !== undefined)      playerSync.bountyLevel      = res.bounty_level;
    if (res?.bounty_multiplier !== undefined) playerSync.bountyMultiplier = res.bounty_multiplier;
    if (res?.current_ss !== undefined)        playerSync.currentSS        = res.current_ss;
    if (res?.max_ss !== undefined)            playerSync.maxSS            = res.max_ss;
    if (res?.event)                           playerSync.event            = res.event;

    emit('complete', {
        totalCreds: 0, totalTech: 0, lockdown: false, gate1Failed: true,
        canvasId: props.canvasId, cooldownUntil: res?.cooldown_until ?? null,
        playerSync,
    });
}

function onGate1Failed() {
    return resolveEntryFailure('mitm_handshake');
}

/** @param {'phase2_overrun'} failureApproach forwarded by BankHeistHarvest's 'failed' emit */
function onGate2Failed(failureApproach) {
    return resolveEntryFailure(failureApproach);
}

function onGate2Complete(payload) {
    emit('complete', { ...payload, canvasId: props.canvasId });
}
</script>

<style scoped>
.bh-overlay { position: fixed; inset: 0; z-index: 200; }
</style>
