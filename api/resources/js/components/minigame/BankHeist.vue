<template>
    <div class="bh-overlay">

        <!-- ── Gate 1 — Authentication Handshake ───────────────────────────────── -->
        <BankHeistHandshake
            v-if="stage === 'gate1'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            :time-left="masterTimeLeft"
            :time-total="masterTimerTotal"
            @success="onGate1Success"
            @terminal-entered="startMasterTimer"
            @miss="applyMissPenalty"
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
            :time-left="masterTimeLeft"
            :time-total="masterTimerTotal"
            @complete="onGate2Complete"
            @miss="applyMissPenalty"
        />

    </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';
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

const stage = ref('gate1'); // 'gate1' | 'gate2' — Gate 1 IS the Authentication Handshake itself; there is no separate screen in front of it

// ── Master Timer — ONE shared clock for the whole run, lifted up here so it
// survives the Gate 1 -> Gate 2 component swap below. Starts on Gate 1's
// 'terminal-entered' (pressing ENTER at the Gateway Entry screen) and runs
// continuously through both gates; a 'miss' from either child docks a flat
// MISS_PENALTY off it; reaching 0 anywhere is a full failure, resolved the
// same way regardless of which gate the player was in when it happened. ──
const masterTimeLeft   = ref(bh.MASTER_TIMER_TOTAL);
const masterTimerTotal = ref(bh.MASTER_TIMER_TOTAL);
let masterInterval = null;
let masterStarted  = false;

function startMasterTimer() {
    if (masterStarted) return; // guard — only Gate 1's Gateway Entry ever fires this, but never twice
    masterStarted = true;
    masterInterval = setInterval(() => {
        masterTimeLeft.value = Math.max(0, masterTimeLeft.value - 0.1);
        if (masterTimeLeft.value <= 0) {
            clearInterval(masterInterval);
            masterInterval = null;
            resolveEntryFailure(stage.value === 'gate2' ? 'phase2_overrun' : 'mitm_handshake');
        }
    }, 100);
}

/** A miss (wrong SYN-ACK guess, or a bad/expired token injection) — costs time only, never stats. */
function applyMissPenalty() {
    masterTimeLeft.value = Math.max(0, masterTimeLeft.value - bh.MISS_PENALTY);
    if (masterTimeLeft.value <= 0 && masterInterval) {
        clearInterval(masterInterval);
        masterInterval = null;
        resolveEntryFailure(stage.value === 'gate2' ? 'phase2_overrun' : 'mitm_handshake');
    }
}

function onGate1Success() {
    stage.value = 'gate2';
}

// Shared "denied at the door" resolution — the Master Timer reaching 0
// costs the same thing regardless of which gate the player was in (see
// BankHeistService::resolveGate1Failure's docblock), so both the timer
// callbacks above funnel through this one call.
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

function onGate2Complete(payload) {
    if (masterInterval) { clearInterval(masterInterval); masterInterval = null; }
    emit('complete', { ...payload, canvasId: props.canvasId });
}

onBeforeUnmount(() => {
    if (masterInterval) clearInterval(masterInterval);
});
</script>

<style scoped>
.bh-overlay { position: fixed; inset: 0; z-index: 200; }
</style>
