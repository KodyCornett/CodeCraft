<template>
    <div class="bh-overlay">

        <!-- ── Gate 1 — Spoofed Handshake ───────────────────────────────────────── -->
        <BankHeistGate1
            v-if="stage === 'gate1'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            :player-os="playerOs"
            @success="onGate1Success"
            @failed="onGate1Failed"
            @abort="$emit('abort')"
        />

        <!-- ── Gate 2 ───────────────────────────────────────────────────────────── -->
        <BankHeistGate2
            v-else-if="stage === 'gate2'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :bank-tier="bankTier"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            :player-os="playerOs"
            :bounty-multiplier="bountyMultiplier"
            @complete="onGate2Complete"
            @failed="onGate2Failed"
            @abort="$emit('abort')"
        />

    </div>
</template>

<script setup>
import { ref } from 'vue';
import BankHeistGate1 from '@/components/minigame/BankHeistGate1.vue';
import BankHeistGate2 from '@/components/minigame/BankHeistGate2.vue';
import { useBankHeist } from '@/composables/useBankHeist.js';

const props = defineProps({
    canvasId:         { type: String, required: true },
    bankName:         { type: String, default: 'UNKNOWN TARGET' },
    bankIce:          { type: Number, required: true },
    bankTier:         { type: Number, required: true },
    playerCpu:        { type: Number, default: 3 },
    playerRam:        { type: Number, default: 2 },
    playerOs:         { type: Number, default: 2 },
    bountyMultiplier: { type: Number, default: 1.0 },
});

// 'complete' forwards Gate 2's final { totalCreds, totalTech, lockdown } payload up to
// whoever mounted us (Game.vue), which is responsible for applying rewards/SS/bounty
// exactly as BankHeistController's responses already did server-side per event — this
// component itself never touches player state directly.
const emit = defineEmits(['complete', 'abort']);

const bh = useBankHeist();

const stage = ref('gate1'); // 'gate1' | 'gate2' — no approach choice anymore, Spoofed Handshake is the only way in

function onGate1Success() {
    stage.value = 'gate2';
}

// Shared "denied at the door" resolution — Gate 1's countertrace timeout,
// Gate 2 Phase 1's MitM Handshake timeout, and Gate 2 Phase 2's Global
// Trace overrun all cost the same thing (see
// BankHeistService::resolveGate1Failure's docblock), so every failure path
// below funnels through this one call.
async function resolveEntryFailure(failureApproach) {
    const res = await bh.gate1Failed(props.canvasId, failureApproach);
    // Same field-merge shape as BankHeistGate2.vue's mergePlayerSync — an
    // entry failure never touches creds/tech, only SS/bounty/cooldown, but
    // keeping the shape consistent lets Game.vue apply both through one path.
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
    return resolveEntryFailure('spoofed_handshake');
}

/** @param {'mitm_handshake'|'phase2_overrun'} failureApproach forwarded by BankHeistGate2's 'failed' emit */
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
