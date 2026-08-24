<template>
    <div class="bh2-overlay">
        <div class="bh2-terminal">

            <div class="bh2-topbar">
                <span>TARGET: {{ bankName }}</span>
                <span class="bh2-bar-row">
                    <span class="bh2-bar-label">DETECTION</span>
                    <div class="bh2-bar-wrap">
                        <div class="bh2-bar-fill" :class="detectionClass" :style="{ width: detection + '%' }" />
                    </div>
                    <span>{{ detection.toFixed(0) }}%</span>
                </span>
            </div>
            <div class="bh2-rule" />

            <!-- ── Phase 1 — Traffic Interception ──────────────────────────────── -->
            <template v-if="phase === 'phase1'">
                <div class="bh2-label">[ TRAFFIC INTERCEPTION ] — catch the login handshake packet</div>
                <p class="bh2-copy">Standard traffic streams past. A gold packet is the target — hit CATCH while it's in the crosshair. No penalty for missing; it cycles back around.</p>
                <div class="bh2-stream">
                    <div class="bh2-crosshair" :class="{ 'bh2-crosshair--gold': packetInWindow }">
                        {{ packetInWindow ? '[ GOLD PACKET — LOGIN HANDSHAKE ]' : '···traffic passing···' }}
                    </div>
                    <button class="bh2-catch" @click="catchPacket">[ CATCH ]</button>
                </div>
            </template>

            <!-- ── Ledger ───────────────────────────────────────────────────────── -->
            <template v-else-if="phase === 'ledger'">
                <div class="bh2-label">[ LEDGER ] — {{ restrictedToOneAccount ? 'Brute Force: 1 account only' : `${accounts.length} accounts revealed` }}</div>
                <div class="bh2-ledger">
                    <button
                        v-for="acct in accounts"
                        :key="acct.id"
                        class="bh2-account"
                        :class="[`bh2-account--${acct.status}`, `bh2-account--${acct.type}`]"
                        :disabled="acct.status !== 'available'"
                        @click="selectAccount(acct)"
                    >
                        <span class="bh2-account-type">{{ acct.type === 'investment' ? 'INVESTMENT' : 'NORMAL' }}</span>
                        <span class="bh2-account-ice">ICE {{ acct.ice }}</span>
                        <span class="bh2-account-reward">{{ rewardLabel(acct) }}</span>
                        <span class="bh2-account-status">{{ acct.status.toUpperCase() }}</span>
                    </button>
                </div>
                <div class="bh2-totals">SECURED: {{ totalCreds }} creds · {{ totalTech.toFixed(2) }} tech</div>
                <button class="bh2-extract" @click="extract">[ EXTRACT — bank everything secured ]</button>
            </template>

            <!-- ── Phase 2 — Payload Tampering & Hash Spoofing ─────────────────── -->
            <template v-else-if="phase === 'phase2'">
                <div class="bh2-label">
                    [ {{ activeAccount.type === 'investment' ? 'INVESTMENT' : 'NORMAL' }} ACCOUNT — ICE {{ activeAccount.ice }} ]
                    <span class="bh2-timer" :class="{ 'bh2-timer--warn': crackTimeLeft <= 8, 'bh2-timer--crit': crackTimeLeft <= 3 }">
                        ANOMALY COUNTDOWN: {{ crackTimeLeft.toFixed(1) }}s
                    </span>
                </div>

                <div class="bh2-payload">
                    <div>RECIPIENT: <span class="bh2-mono">{{ spoofed ? 'YOUR_WALLET_TAG' : 'original_owner' }}</span></div>
                    <div>AMOUNT: <span class="bh2-mono">{{ spoofed ? 'INFLATED' : 'original' }}</span></div>
                    <div>HASH_STATUS: <span :class="hashValid ? 'bh2-good' : 'bh2-bad'">{{ hashValid ? 'VERIFIED' : (spoofed ? 'INVALID (CHECKSUM_MISMATCH)' : 'VALID') }}</span></div>
                </div>

                <button v-if="!spoofed" class="bh2-spoof" @click="spoof">[ SPOOF TRANSACTION ]</button>

                <template v-else>
                    <div class="bh2-cli">
                        <span class="bh2-cli-prompt">NODE_SALT: 0x{{ correctKey }} &gt;</span>
                        <input
                            v-model="keyInput"
                            class="bh2-cli-input"
                            placeholder="spoof-hash -k &lt;key&gt;"
                            @keyup.enter="submitKey"
                        />
                        <button class="bh2-cli-submit" @click="submitKey">RUN</button>
                    </div>
                    <div v-if="cliFlash" class="bh2-flash" :class="cliFlash.kind">{{ cliFlash.text }}</div>
                    <button class="bh2-inject" :disabled="!hashValid" @click="injectPayload">[ INJECT PAYLOAD ]</button>
                </template>

                <button class="bh2-backout" @click="backOut">[ BACK OUT — costlier than a clean failure ]</button>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useBankHeist } from '@/composables/useBankHeist.js';

const props = defineProps({
    canvasId:              { type: String, required: true },
    bankName:              { type: String, default: 'UNKNOWN TARGET' },
    bankIce:               { type: Number, required: true },
    bankTier:              { type: Number, required: true },
    approach:              { type: String, required: true }, // 'spoofed_handshake' | 'brute_force'
    restrictedToOneAccount: { type: Boolean, default: false },
    playerCpu:             { type: Number, default: 3 },
    playerRam:             { type: Number, default: 2 },
    playerOs:              { type: Number, default: 2 },
    bountyMultiplier:      { type: Number, default: 1.0 },
});

const emit = defineEmits(['complete', 'abort']);

const bh = useBankHeist();

const phase = ref('phase1'); // 'phase1' | 'ledger' | 'phase2'
const detection = ref(0);
const totalCreds = ref(0);
const totalTech = ref(0);
let detectionInterval = null;

// Every server response below is authoritative (see BankHeistService — SS,
// bounty, and credit/tech balances are all saved server-side regardless of
// whether the client syncs this back into Game.vue's live player ref). This
// merges only the fields present on each response, mirroring
// useHackFlow.js's onHackComplete sync block, so Game.vue can apply it in
// one place after 'complete' fires instead of each caller re-deriving it.
const playerSync = ref({});
function mergePlayerSync(res) {
    if (!res) return;
    if (res.pocket_creds !== undefined)      playerSync.value.pocketCreds     = res.pocket_creds;
    if (res.tech_points !== undefined)       playerSync.value.techPoints      = res.tech_points;
    if (res.bounty_level !== undefined)      playerSync.value.bountyLevel     = res.bounty_level;
    if (res.bounty_multiplier !== undefined) playerSync.value.bountyMultiplier = res.bounty_multiplier;
    if (res.current_ss !== undefined)        playerSync.value.currentSS       = res.current_ss;
    if (res.max_ss !== undefined)            playerSync.value.maxSS           = res.max_ss;
    if (res.event !== undefined && res.event !== null) playerSync.value.event = res.event;
}

const detectionClass = computed(() => {
    const b = bh.detectionBand(detection.value);
    return ['bh2-bar--clean', 'bh2-bar--warn', 'bh2-bar--engaged', 'bh2-bar--heavy', 'bh2-bar--lockdown'][b];
});

function startDetectionTicker() {
    detectionInterval = setInterval(() => {
        const rate = bh.passiveTickRate(props.bankIce); // %/sec
        detection.value = Math.min(100, detection.value + (rate * 200) / 1000);
        if (detection.value >= 100) triggerLockdown();
    }, 200);
}

async function triggerLockdown() {
    if (detectionInterval) clearInterval(detectionInterval);
    // Server treats detection_band 4 as an authoritative Lockdown override
    // regardless of account_type/outcome — see BankHeistService::resolveAccountEvent.
    const res = await bh.accountResult(props.canvasId, 'normal', 'abandoned', 4);
    mergePlayerSync(res);
    emit('complete', { totalCreds: totalCreds.value, totalTech: totalTech.value, lockdown: true, playerSync: playerSync.value });
}

// ── Phase 1 — Traffic Interception ──────────────────────────────────────────
const packetInWindow = ref(false);
let packetTimer = null;

function schedulePacketCycle() {
    packetInWindow.value = false;
    packetTimer = setTimeout(() => {
        packetInWindow.value = true;
        packetTimer = setTimeout(schedulePacketCycle, 700);
    }, 2400);
}

function catchPacket() {
    if (!packetInWindow.value) return;
    clearTimeout(packetTimer);
    accounts.value = bh.buildLedger(props.bankIce, props.bankTier);
    if (props.restrictedToOneAccount) accounts.value = accounts.value.slice(0, 1);
    phase.value = 'ledger';
}

// ── Ledger ───────────────────────────────────────────────────────────────────
const accounts = ref([]);
const activeAccount = ref(null);

function rewardLabel(acct) {
    const r = bh.previewAccountReward(acct.ice, acct.type, props.bountyMultiplier);
    return acct.type === 'investment' ? `~${r.tech.toFixed(2)} tech` : `~${r.creds} creds`;
}

function selectAccount(acct) {
    activeAccount.value = acct;
    spoofed.value = false;
    hashValid.value = false;
    keyInput.value = '';
    cliFlash.value = null;
    const cutBand = bh.detectionBand(detection.value);
    const cut = bh.timerCutFraction(Math.min(cutBand, 3));
    const base = bh.baseTimer(props.playerCpu, props.playerRam, acct.ice);
    crackTimeLeft.value = base * (1 - cut);
    correctKey.value = bh.generateSaltKey(acct.saltKeyLength);
    phase.value = 'phase2';
    startCrackTimer();
}

function extract() {
    if (detectionInterval) clearInterval(detectionInterval);
    const finish = () => emit('complete', { totalCreds: totalCreds.value, totalTech: totalTech.value, lockdown: false, playerSync: playerSync.value });
    if (props.approach === 'brute_force') {
        bh.bruteForceCleanExit(props.canvasId).then(mergePlayerSync).finally(finish);
    } else {
        finish();
    }
}

// ── Phase 2 — Payload Tampering & Hash Spoofing ─────────────────────────────
const spoofed = ref(false);
const hashValid = ref(false);
const keyInput = ref('');
const correctKey = ref('');
const crackTimeLeft = ref(0);
const cliFlash = ref(null);
let crackInterval = null;

function spoof() {
    spoofed.value = true;
}

function submitKey() {
    const guess = keyInput.value.trim().toUpperCase();
    keyInput.value = '';
    if (guess === correctKey.value) {
        hashValid.value = true;
        cliFlash.value = { text: 'HASH VERIFIED', kind: 'good' };
        return;
    }
    const penalty = bh.wrongActionPenalty(props.playerOs);
    crackTimeLeft.value = Math.max(0, crackTimeLeft.value - penalty);
    cliFlash.value = { text: `INVALID KEY — TRACE ADVANCES −${penalty}s`, kind: 'bad' };
}

function startCrackTimer() {
    if (crackInterval) clearInterval(crackInterval);
    crackInterval = setInterval(() => {
        crackTimeLeft.value = Math.max(0, crackTimeLeft.value - 0.2);
        if (crackTimeLeft.value <= 0) resolveAccount('clean_failed');
    }, 200);
}

async function resolveAccount(outcome) {
    if (crackInterval) clearInterval(crackInterval);
    const band = bh.detectionBand(detection.value);
    const res = await bh.accountResult(props.canvasId, activeAccount.value.type, outcome, band);
    mergePlayerSync(res);

    if (res?.outcome === 'lockdown') {
        if (detectionInterval) clearInterval(detectionInterval);
        emit('complete', { totalCreds: totalCreds.value, totalTech: totalTech.value, lockdown: true, playerSync: playerSync.value });
        return;
    }

    if (outcome === 'success' && res) {
        totalCreds.value += res.reward?.creds ?? 0;
        totalTech.value += res.reward?.tech ?? 0;
        activeAccount.value.status = 'looted';
    } else {
        activeAccount.value.status = 'locked';
        if (outcome === 'clean_failed' && res) {
            detection.value = Math.min(100, detection.value + (res.failure_jump ?? 0));
        }
    }

    activeAccount.value = null;
    phase.value = 'ledger';
}

function injectPayload() {
    if (!hashValid.value) return;
    resolveAccount('success');
}

function backOut() {
    resolveAccount('abandoned');
}

onMounted(() => {
    startDetectionTicker();
    schedulePacketCycle();
});

onBeforeUnmount(() => {
    if (detectionInterval) clearInterval(detectionInterval);
    if (packetTimer) clearTimeout(packetTimer);
    if (crackInterval) clearInterval(crackInterval);
});
</script>

<style scoped>
.bh2-overlay { position: fixed; inset: 0; background: rgba(4, 6, 10, 0.92); z-index: 200; display: flex; align-items: center; justify-content: center; }
.bh2-terminal { width: min(760px, 94vw); max-height: 88vh; overflow-y: auto; background: #0a0f16; border: 1px solid #2a3a4a; font-family: 'JetBrains Mono', monospace; color: #a8c4d8; padding: 18px 20px; }
.bh2-topbar { display: flex; justify-content: space-between; align-items: center; font-size: 10px; letter-spacing: 0.05em; color: #6a8aa0; }
.bh2-rule { border-top: 1px solid #1e2a36; margin: 10px 0 16px; }
.bh2-label { display: flex; justify-content: space-between; align-items: center; font-size: 10px; letter-spacing: 0.08em; color: #4a90d8; margin-bottom: 12px; }
.bh2-copy { font-size: 11px; line-height: 1.6; opacity: 0.85; margin: 0 0 16px; }

.bh2-bar-row { display: flex; align-items: center; gap: 8px; }
.bh2-bar-label { font-size: 9px; }
.bh2-bar-wrap { width: 120px; height: 8px; background: #101822; border: 1px solid #2a3a4a; }
.bh2-bar-fill { height: 100%; transition: width 0.2s linear; }
.bh2-bar--clean { background: #2ed88a; }
.bh2-bar--warn { background: #d8c43c; }
.bh2-bar--engaged { background: #d8a83c; }
.bh2-bar--heavy { background: #e06b3c; }
.bh2-bar--lockdown { background: #e04848; }

.bh2-stream { text-align: center; padding: 30px 0; }
.bh2-crosshair { font-size: 12px; padding: 14px; border: 1px dashed #2a3a4a; margin-bottom: 16px; }
.bh2-crosshair--gold { border-color: #d8a83c; color: #d8a83c; }
.bh2-catch { font-family: inherit; font-size: 11px; padding: 8px 20px; background: #101822; border: 1px solid #4a90d8; color: #4a90d8; cursor: pointer; }

.bh2-ledger { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.bh2-account { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 8px; font-family: inherit; font-size: 10px; padding: 8px 10px; background: #101822; border: 1px solid #2a3a4a; color: #a8c4d8; cursor: pointer; text-align: left; }
.bh2-account:hover:not(:disabled) { border-color: #4a90d8; }
.bh2-account--investment { border-left: 2px solid #d8a83c; }
.bh2-account--looted { opacity: 0.35; }
.bh2-account--locked { opacity: 0.35; color: #e04848; }
.bh2-totals { font-size: 10px; color: #2ed88a; margin-bottom: 10px; }
.bh2-extract { font-family: inherit; font-size: 10px; padding: 8px 16px; background: #101822; border: 1px solid #2ed88a; color: #2ed88a; cursor: pointer; }

.bh2-timer { font-size: 10px; color: #4a90d8; }
.bh2-timer--warn { color: #d8a83c; }
.bh2-timer--crit { color: #e04848; animation: bh2-pulse 0.6s ease-in-out infinite; }
@keyframes bh2-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

.bh2-payload { font-size: 11px; line-height: 2; margin-bottom: 12px; }
.bh2-mono { color: #d8a83c; }
.bh2-good { color: #2ed88a; }
.bh2-bad { color: #e04848; }
.bh2-spoof { font-family: inherit; font-size: 10px; padding: 8px 16px; background: #101822; border: 1px solid #4a90d8; color: #4a90d8; cursor: pointer; }

.bh2-cli { display: flex; align-items: center; gap: 8px; margin: 12px 0 6px; font-size: 10px; }
.bh2-cli-prompt { color: #6a8aa0; white-space: nowrap; }
.bh2-cli-input { flex: 1; font-family: inherit; font-size: 11px; background: #101822; border: 1px solid #2a3a4a; color: #a8c4d8; padding: 6px 8px; }
.bh2-cli-submit { font-family: inherit; font-size: 10px; padding: 6px 12px; background: #101822; border: 1px solid #4a90d8; color: #4a90d8; cursor: pointer; }
.bh2-flash { font-size: 10px; margin-bottom: 10px; }
.bh2-flash.good { color: #2ed88a; }
.bh2-flash.bad { color: #e04848; }
.bh2-inject { font-family: inherit; font-size: 10px; padding: 8px 16px; background: #101822; border: 1px solid #2ed88a; color: #2ed88a; cursor: pointer; margin-top: 4px; }
.bh2-inject:disabled { opacity: 0.3; cursor: not-allowed; }

.bh2-backout { display: block; margin-top: 16px; font-family: inherit; font-size: 9px; color: #6a8aa0; background: transparent; border: 1px solid #2a3a4a; padding: 6px 12px; cursor: pointer; }
.bh2-backout:hover { border-color: #e04848; color: #e04848; }
</style>
