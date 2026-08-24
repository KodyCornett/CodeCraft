<template>
    <div class="bh-overlay">

        <!-- ── Approach selection ──────────────────────────────────────────────── -->
        <div v-if="stage === 'select'" class="bh-select-terminal">
            <div class="bh-select-topbar">
                <span>TARGET: {{ bankName }}</span>
                <span>BANK ICE {{ bankIce }}</span>
            </div>
            <div class="bh-select-rule" />
            <div class="bh-select-label">[ CHOOSE YOUR APPROACH — this cannot be changed once you commit ]</div>

            <div class="bh-select-options">
                <button class="bh-select-card" @click="commit('spoofed_handshake')">
                    <div class="bh-select-card-title">SPOOFED HANDSHAKE</div>
                    <div class="bh-select-card-copy">
                        Puzzle-based entry. Probe the intercepted readout for the real fragments, slot them before the
                        countertrace clock runs out. Wrong picks cost time, not the run. Clean success opens the full ledger.
                    </div>
                </button>
                <button class="bh-select-card" @click="commit('brute_force')">
                    <div class="bh-select-card-title">BRUTE FORCE</div>
                    <div class="bh-select-card-copy">
                        No puzzle — just survive the countertrace clock while detection climbs fast. Loud and unmissable.
                        Success restricts you to a single ledger account, but skips the handshake entirely.
                    </div>
                </button>
            </div>

            <div class="bh-select-footer">
                <button class="bh-select-cancel" @click="$emit('abort')">[ CANCEL ]</button>
            </div>
        </div>

        <!-- ── Gate 1 ───────────────────────────────────────────────────────────── -->
        <BankHeistGate1
            v-else-if="stage === 'gate1'"
            :canvas-id="canvasId"
            :bank-name="bankName"
            :bank-ice="bankIce"
            :approach="approach"
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
            :approach="approach"
            :restricted-to-one-account="restrictedToOneAccount"
            :player-cpu="playerCpu"
            :player-ram="playerRam"
            :player-os="playerOs"
            :bounty-multiplier="bountyMultiplier"
            @complete="onGate2Complete"
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

const stage = ref('select'); // 'select' | 'gate1' | 'gate2'
const approach = ref('spoofed_handshake');
const restrictedToOneAccount = ref(false);

function commit(chosenApproach) {
    approach.value = chosenApproach;
    stage.value = 'gate1';
}

function onGate1Success({ restrictedToOneAccount: restricted }) {
    restrictedToOneAccount.value = restricted;
    stage.value = 'gate2';
}

async function onGate1Failed() {
    const res = await bh.gate1Failed(props.canvasId, approach.value);
    // Same field-merge shape as BankHeistGate2.vue's mergePlayerSync — Gate 1
    // failure never touches creds/tech, only SS/bounty/cooldown, but keeping
    // the shape consistent lets Game.vue apply both through one code path.
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
    emit('complete', { ...payload, canvasId: props.canvasId });
}
</script>

<style scoped>
.bh-overlay { position: fixed; inset: 0; z-index: 200; }

.bh-select-terminal {
    position: fixed; inset: 0; background: rgba(4, 6, 10, 0.92);
    display: flex; align-items: center; justify-content: center; flex-direction: column;
}
.bh-select-terminal > * { width: min(680px, 92vw); }
.bh-select-topbar { display: flex; justify-content: space-between; font-size: 10px; letter-spacing: 0.05em; color: #6a8aa0; font-family: 'JetBrains Mono', monospace; }
.bh-select-rule { border-top: 1px solid #1e2a36; margin: 10px 0 16px; }
.bh-select-label { font-size: 10px; letter-spacing: 0.08em; color: #4a90d8; margin-bottom: 16px; font-family: 'JetBrains Mono', monospace; }

.bh-select-options { display: flex; gap: 14px; }
.bh-select-card {
    flex: 1; text-align: left; background: #0a0f16; border: 1px solid #2a3a4a; color: #a8c4d8;
    padding: 16px; cursor: pointer; font-family: 'JetBrains Mono', monospace;
}
.bh-select-card:hover { border-color: #4a90d8; }
.bh-select-card-title { font-size: 12px; letter-spacing: 0.06em; color: #4a90d8; margin-bottom: 8px; }
.bh-select-card-copy { font-size: 10px; line-height: 1.6; opacity: 0.85; }

.bh-select-footer { margin-top: 16px; text-align: right; }
.bh-select-cancel {
    font-family: 'JetBrains Mono', monospace; font-size: 9px; color: #6a8aa0; background: transparent;
    border: 1px solid #2a3a4a; padding: 6px 12px; cursor: pointer;
}
.bh-select-cancel:hover { border-color: #e04848; color: #e04848; }
</style>
