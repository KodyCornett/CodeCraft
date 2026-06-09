<template>
    <div class="hct-page">

        <!-- ── Scanline overlay ──────────────────────────────────────────────── -->
        <div class="hct-scanlines" />

        <!-- ═══════════════════════════════════════════════════════════════════
             LOGIN STATE — auto-type spoofed credentials then transition
             ═══════════════════════════════════════════════════════════════════ -->
        <Transition name="hct-fade">
        <div v-if="phase === 'login'" class="hct-login">

            <div class="login-crest">
                <div class="crest-mark">⬡</div>
                <div class="crest-name">HELVETIC CIPHER TRUST</div>
                <div class="crest-sub">OFFSHORE SECURE TERMINAL // v4.2.1</div>
            </div>

            <div class="login-form">
                <div class="lf-field">
                    <span class="lf-label">ACCESS IDENTIFIER</span>
                    <div class="lf-input">
                        <span class="lf-value">{{ accessId }}</span>
                        <span class="lf-cursor" :class="{ 'lf-cursor--blink': accessIdDone }" />
                    </div>
                </div>
                <div class="lf-field">
                    <span class="lf-label">CIPHER HANDSHAKE</span>
                    <div class="lf-input lf-input--masked">
                        <span class="lf-value lf-value--key">{{ handshakeKey }}</span>
                        <span class="lf-cursor" :class="{ 'lf-cursor--blink': keyDone }" />
                    </div>
                </div>
            </div>

            <div class="login-progress">
                <div class="lp-bar">
                    <div class="lp-fill" :style="{ width: loginProgress + '%' }" />
                </div>
                <div class="lp-status" :class="loginStatusSuccess ? 'lp-status--ok' : ''">
                    {{ loginStatus }}
                </div>
            </div>

            <div class="login-footer">
                <span>ENCRYPTED CHANNEL ACTIVE</span>
                <span>JURISDICTION: OFFSHORE // NODE-2A</span>
                <span>TRACE SHIELD: ENGAGED</span>
            </div>

        </div>
        </Transition>

        <!-- ═══════════════════════════════════════════════════════════════════
             DASHBOARD STATE
             ═══════════════════════════════════════════════════════════════════ -->
        <Transition name="hct-fade">
        <div v-if="phase === 'dashboard'" class="hct-dashboard">

            <!-- ── Bank header ─────────────────────────────────────────────── -->
            <div class="dash-header">
                <div class="dh-left">
                    <span class="dh-mark">⬡</span>
                    <div class="dh-title-block">
                        <span class="dh-name">HELVETIC CIPHER TRUST</span>
                        <span class="dh-sub">// SECURE OFFSHORE LEDGER</span>
                    </div>
                </div>
                <div class="dh-badges">
                    <span class="dh-badge dh-badge--enc">ENCRYPTED</span>
                    <span class="dh-badge dh-badge--off">OFFSHORE</span>
                    <span class="dh-badge dh-badge--anon">ANONYMOUS</span>
                </div>
                <div class="dh-account">
                    <span class="dh-acct-label">ACCOUNT</span>
                    <span class="dh-acct-num">{{ accountNumber }}</span>
                </div>
            </div>

            <!-- ── Balance grid ────────────────────────────────────────────── -->
            <div class="balance-grid">

                <div class="balance-card balance-card--dirty" :class="{ 'balance-card--hot': pocketCreds > 0 }">
                    <div class="bc-header">
                        <span class="bc-label">UNCLEAN CAPITAL</span>
                        <span v-if="pocketCreds > 0" class="bc-risk">⚠ AT RISK</span>
                    </div>
                    <div class="bc-amount">{{ pocketCreds.toLocaleString() }} <span class="bc-unit">₡</span></div>
                    <div class="bc-desc">Dirty funds from active network operations. Not insured. Vulnerable to extraction.</div>
                </div>

                <div class="balance-card balance-card--clean">
                    <div class="bc-header">
                        <span class="bc-label">CLEAN RESERVES</span>
                        <span class="bc-secured">● SECURED</span>
                    </div>
                    <div class="bc-amount bc-amount--clean">{{ walletCreds.toLocaleString() }} <span class="bc-unit">₡</span></div>
                    <div class="bc-desc">Laundered, off-ledger holdings. Immovable by third parties. Full spending access.</div>
                </div>

            </div>

            <!-- ── Transfer action ─────────────────────────────────────────── -->
            <div class="transfer-panel" :class="{ 'transfer-panel--active': pocketCreds > 0 }">
                <div class="tp-info">
                    <div class="tp-info-title">OFFSHORE TRANSFER PROTOCOL</div>
                    <div class="tp-info-sub">
                        Route {{ pocketCreds > 0 ? pocketCreds.toLocaleString() + ' ₡' : '— ' }}
                        unclean capital through the cipher relay into secured reserves.
                        Bounty state cleared on execution.
                    </div>
                </div>
                <button
                    class="tp-btn"
                    :disabled="pocketCreds === 0 || transferring"
                    @click="onExecuteTransfer"
                >
                    <span v-if="transferring">ROUTING FUNDS…</span>
                    <span v-else-if="pocketCreds === 0">— NO UNCLEAN CAPITAL —</span>
                    <span v-else>[ EXECUTE OFFSHORE TRANSFER ]</span>
                </button>
                <Transition name="hct-confirm">
                    <div v-if="confirmMsg" class="tp-confirm">
                        <span class="tp-confirm-icon">✓</span>
                        {{ confirmMsg }}
                    </div>
                </Transition>
            </div>

            <!-- ── Transaction ledger ──────────────────────────────────────── -->
            <div class="ledger">
                <div class="ledger-header">
                    <span class="ledger-title">TRANSACTION LEDGER</span>
                    <span class="ledger-sub">LAST 15 ENTRIES // ENCRYPTED LOG</span>
                </div>
                <div class="ledger-body">
                    <div
                        v-for="tx in transactions"
                        :key="tx.id"
                        class="tx-row"
                        :class="tx.type === 'credit' ? 'tx-row--credit' : 'tx-row--debit'"
                    >
                        <span class="tx-id">{{ tx.ref }}</span>
                        <span class="tx-desc">{{ tx.desc }}</span>
                        <span class="tx-ts">{{ tx.ts }}</span>
                        <span class="tx-amount" :class="tx.type === 'credit' ? 'tx-credit' : 'tx-debit'">
                            {{ tx.type === 'credit' ? '+' : '-' }}{{ tx.amount.toLocaleString() }} ₡
                        </span>
                    </div>
                </div>
            </div>

        </div>
        </Transition>

    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue';

// ── Game state ────────────────────────────────────────────────────────────────
const gameState  = inject('gameState', null);
const player     = gameState?.player ?? ref({ creds: 0, pocketCreds: 0 });
const bankCreds  = gameState?.bankCreds ?? null;

const pocketCreds = computed(() => player.value?.pocketCreds ?? 0);
const walletCreds = computed(() => player.value?.creds       ?? 0);

// ── Unique account number — generated once per session ────────────────────────
const accountNumber = 'HCT-' +
    Math.random().toString(36).slice(2, 6).toUpperCase() + '-' +
    Math.random().toString(36).slice(2, 6).toUpperCase();

// ═════════════════════════════════════════════════════════════════════════════
// LOGIN SEQUENCE
// ═════════════════════════════════════════════════════════════════════════════

const phase              = ref('login');
const accessId           = ref('');
const handshakeKey       = ref('');
const accessIdDone       = ref(false);
const keyDone            = ref(false);
const loginStatus        = ref('ESTABLISHING SECURE CHANNEL…');
const loginStatusSuccess = ref(false);
const loginProgress      = ref(0);

const TARGET_ID  = accountNumber;
const KEY_LENGTH = 18;

const STATUS_STEPS = [
    { msg: 'ESTABLISHING SECURE CHANNEL…',  progress: 10,  delay: 0    },
    { msg: 'BYPASSING PERIMETER FIREWALL…', progress: 32,  delay: 420  },
    { msg: 'INJECTING CIPHER TOKEN…',       progress: 56,  delay: 860  },
    { msg: 'VALIDATING HANDSHAKE…',         progress: 78,  delay: 1280 },
    { msg: '✓ HANDSHAKE VERIFIED',          progress: 100, delay: 1680, success: true },
];

let _timers = [];

function scheduleTimeout(fn, delay) {
    const t = setTimeout(fn, delay);
    _timers.push(t);
    return t;
}

function scheduleInterval(fn, interval) {
    const t = setInterval(fn, interval);
    _timers.push(t);
    return t;
}

onMounted(() => {
    // Type access ID
    let idIdx = 0;
    const idTimer = scheduleInterval(() => {
        if (idIdx < TARGET_ID.length) {
            accessId.value += TARGET_ID[idIdx++];
        } else {
            clearInterval(idTimer);
            accessIdDone.value = true;
        }
    }, 38);

    // Type handshake key (offset start slightly)
    scheduleTimeout(() => {
        let kIdx = 0;
        const kTimer = scheduleInterval(() => {
            if (kIdx < KEY_LENGTH) {
                handshakeKey.value += '●';
                kIdx++;
            } else {
                clearInterval(kTimer);
                keyDone.value = true;
            }
        }, 28);
    }, 180);

    // Status progression
    STATUS_STEPS.forEach(({ msg, progress, delay, success }) => {
        scheduleTimeout(() => {
            loginStatus.value        = msg;
            loginProgress.value      = progress;
            loginStatusSuccess.value = !!success;
        }, delay);
    });

    // Transition to dashboard
    scheduleTimeout(() => {
        phase.value = 'dashboard';
    }, 2100);
});

onUnmounted(() => {
    _timers.forEach(t => { clearTimeout(t); clearInterval(t); });
});

// ═════════════════════════════════════════════════════════════════════════════
// TRANSFER
// ═════════════════════════════════════════════════════════════════════════════

const transferring = ref(false);
const confirmMsg   = ref('');
let   _confirmTimer = null;

async function onExecuteTransfer() {
    if (transferring.value || pocketCreds.value === 0 || !bankCreds) return;

    transferring.value = true;
    confirmMsg.value   = '';

    const amount = pocketCreds.value;
    const result = await bankCreds();

    transferring.value = false;

    if (result !== null) {
        confirmMsg.value = `${amount.toLocaleString()} ₡ ROUTED THROUGH CIPHER RELAY — SECURED`;

        // Inject into ledger as first entry
        transactions.value.unshift({
            id:     Date.now(),
            ref:    'TXN-' + Math.random().toString(36).slice(2, 8).toUpperCase(),
            desc:   'Offshore Cache Deposit',
            ts:     formatTs(new Date()),
            amount,
            type:   'credit',
        });
        if (transactions.value.length > 15) transactions.value.pop();

        clearTimeout(_confirmTimer);
        _confirmTimer = setTimeout(() => { confirmMsg.value = ''; }, 4000);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// MOCK TRANSACTION LEDGER
// ═════════════════════════════════════════════════════════════════════════════

function formatTs(d) {
    return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' })
        + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function pastDate(minutesAgo) {
    return new Date(Date.now() - minutesAgo * 60 * 1000);
}

function ref26(n) {
    return 'TXN-' + n.toString(36).toUpperCase().padStart(6, '0');
}

const transactions = ref([
    { id: 1,  ref: ref26(9821), desc: 'Hardware Acquisition',          ts: formatTs(pastDate(14)),    amount: 1840,  type: 'credit'  },
    { id: 2,  ref: ref26(9820), desc: 'Subnet Transit Fee',            ts: formatTs(pastDate(41)),    amount: 120,   type: 'debit'   },
    { id: 3,  ref: ref26(9819), desc: 'Data Payload Extraction',       ts: formatTs(pastDate(78)),    amount: 3200,  type: 'credit'  },
    { id: 4,  ref: ref26(9818), desc: 'Protocol Obfuscation Fee',      ts: formatTs(pastDate(142)),   amount: 80,    type: 'debit'   },
    { id: 5,  ref: ref26(9817), desc: 'Node Access Licensing',         ts: formatTs(pastDate(215)),   amount: 240,   type: 'debit'   },
    { id: 6,  ref: ref26(9816), desc: 'Encrypted Data Bundle',         ts: formatTs(pastDate(310)),   amount: 920,   type: 'credit'  },
    { id: 7,  ref: ref26(9815), desc: 'Grid Relay Surcharge',          ts: formatTs(pastDate(448)),   amount: 55,    type: 'debit'   },
    { id: 8,  ref: ref26(9814), desc: 'Asset Relocation Transfer',     ts: formatTs(pastDate(612)),   amount: 2750,  type: 'credit'  },
    { id: 9,  ref: ref26(9813), desc: 'Bandwidth Allocation',          ts: formatTs(pastDate(780)),   amount: 200,   type: 'debit'   },
    { id: 10, ref: ref26(9812), desc: 'Dark Pool Inbound Transfer',    ts: formatTs(pastDate(1040)),  amount: 4400,  type: 'credit'  },
    { id: 11, ref: ref26(9811), desc: 'Firewall Maintenance Fee',      ts: formatTs(pastDate(1320)),  amount: 150,   type: 'debit'   },
    { id: 12, ref: ref26(9810), desc: 'Cache Node Extraction',         ts: formatTs(pastDate(1680)),  amount: 1100,  type: 'credit'  },
    { id: 13, ref: ref26(9809), desc: 'Routing Node Commission',       ts: formatTs(pastDate(2100)),  amount: 95,    type: 'debit'   },
    { id: 14, ref: ref26(9808), desc: 'System Integrity Audit',        ts: formatTs(pastDate(2640)),  amount: 175,   type: 'debit'   },
    { id: 15, ref: ref26(9807), desc: 'Packet Intercept Dividend',     ts: formatTs(pastDate(3120)),  amount: 680,   type: 'credit'  },
]);
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────────────────────── */
.hct-page {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #060810;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    color: rgba(168, 207, 255, 0.8);
}

.hct-scanlines {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 3px,
        rgba(0, 0, 0, 0.07) 3px,
        rgba(0, 0, 0, 0.07) 4px
    );
    pointer-events: none;
    z-index: 10;
}

/* Transition */
.hct-fade-enter-active, .hct-fade-leave-active { transition: opacity 0.4s ease; }
.hct-fade-enter-from,   .hct-fade-leave-to     { opacity: 0; }

/* ═══════════════════════════════════════════════════════════════════════════
   LOGIN
   ═══════════════════════════════════════════════════════════════════════════ */
.hct-login {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    padding: 32px;
    background: #060810;
}

.login-crest {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.crest-mark {
    font-size: 36px;
    color: rgba(168, 207, 255, 0.7);
    line-height: 1;
    text-shadow: 0 0 24px rgba(168, 207, 255, 0.3);
}

.crest-name {
    font-size: 18px;
    color: rgba(168, 207, 255, 0.9);
    letter-spacing: 0.25em;
    text-shadow: 0 0 20px rgba(168, 207, 255, 0.2);
}

.crest-sub {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.3);
    letter-spacing: 0.2em;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
    max-width: 360px;
}

.lf-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lf-label {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.35);
    letter-spacing: 0.2em;
}

.lf-input {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 6px 10px;
    border: 1px solid rgba(168, 207, 255, 0.15);
    background: rgba(168, 207, 255, 0.03);
    min-height: 28px;
}

.lf-input--masked { letter-spacing: 0.12em; }

.lf-value {
    font-size: 11px;
    color: rgba(168, 207, 255, 0.85);
    letter-spacing: 0.06em;
}

.lf-value--key {
    color: rgba(125, 249, 255, 0.7);
    letter-spacing: 0.12em;
    font-size: 10px;
}

.lf-cursor {
    display: inline-block;
    width: 7px;
    height: 13px;
    background: rgba(168, 207, 255, 0.7);
}

.lf-cursor--blink {
    animation: cur-blink 0.8s step-end infinite;
}

@keyframes cur-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

.login-progress {
    width: 100%;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.lp-bar {
    height: 2px;
    background: rgba(168, 207, 255, 0.08);
    width: 100%;
}

.lp-fill {
    height: 100%;
    background: rgba(168, 207, 255, 0.6);
    transition: width 0.35s ease;
    box-shadow: 0 0 8px rgba(168, 207, 255, 0.4);
}

.lp-status {
    font-size: 8px;
    color: rgba(168, 207, 255, 0.4);
    letter-spacing: 0.14em;
    text-align: center;
    transition: color 0.2s;
}

.lp-status--ok {
    color: #00FF88;
    text-shadow: 0 0 10px rgba(0, 255, 136, 0.3);
}

.login-footer {
    display: flex;
    gap: 20px;
    font-size: 6px;
    color: rgba(168, 207, 255, 0.18);
    letter-spacing: 0.1em;
    text-align: center;
    flex-wrap: wrap;
    justify-content: center;
}

/* ═══════════════════════════════════════════════════════════════════════════
   DASHBOARD
   ═══════════════════════════════════════════════════════════════════════════ */
.hct-dashboard {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ── Header ────────────────────────────────────────────────────────────────── */
.dash-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(168, 207, 255, 0.12);
    background: rgba(168, 207, 255, 0.02);
    flex-shrink: 0;
}

.dh-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.dh-mark {
    font-size: 20px;
    color: rgba(168, 207, 255, 0.6);
}

.dh-title-block {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.dh-name {
    font-size: 13px;
    color: rgba(168, 207, 255, 0.9);
    letter-spacing: 0.18em;
    line-height: 1;
}

.dh-sub {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.3);
    letter-spacing: 0.12em;
}

.dh-badges {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}

.dh-badge {
    font-size: 6px;
    letter-spacing: 0.12em;
    padding: 2px 6px;
    border: 1px solid;
}

.dh-badge--enc  { color: rgba(0, 255, 136, 0.7);   border-color: rgba(0, 255, 136, 0.2);   }
.dh-badge--off  { color: rgba(168, 207, 255, 0.7);  border-color: rgba(168, 207, 255, 0.2); }
.dh-badge--anon { color: rgba(125, 249, 255, 0.7);  border-color: rgba(125, 249, 255, 0.2); }

.dh-account {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1px;
    flex-shrink: 0;
}

.dh-acct-label {
    font-size: 6px;
    color: rgba(168, 207, 255, 0.25);
    letter-spacing: 0.16em;
}

.dh-acct-num {
    font-size: 9px;
    color: rgba(168, 207, 255, 0.55);
    letter-spacing: 0.1em;
}

/* ── Balance grid ──────────────────────────────────────────────────────────── */
.balance-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-bottom: 1px solid rgba(168, 207, 255, 0.08);
    flex-shrink: 0;
}

.balance-card {
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    transition: background 0.2s;
}

.balance-card--dirty {
    border-right: 1px solid rgba(168, 207, 255, 0.08);
    background: rgba(255, 179, 0, 0.02);
}

.balance-card--dirty.balance-card--hot {
    background: rgba(255, 179, 0, 0.04);
}

.balance-card--clean {
    background: rgba(0, 255, 136, 0.02);
}

.bc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.bc-label {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.35);
    letter-spacing: 0.18em;
}

.bc-risk {
    font-size: 6px;
    color: rgba(255, 179, 0, 0.7);
    border: 1px solid rgba(255, 179, 0, 0.2);
    padding: 1px 5px;
    letter-spacing: 0.1em;
    animation: risk-pulse 1.5s ease-in-out infinite;
}

@keyframes risk-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.bc-secured {
    font-size: 6px;
    color: rgba(0, 255, 136, 0.6);
    letter-spacing: 0.1em;
}

.bc-amount {
    font-size: 22px;
    color: rgba(255, 179, 0, 0.85);
    letter-spacing: 0.06em;
    line-height: 1;
}

.bc-amount--clean { color: rgba(168, 207, 255, 0.9); }

.bc-unit {
    font-size: 13px;
    opacity: 0.6;
}

.bc-desc {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.2);
    letter-spacing: 0.03em;
    line-height: 1.6;
}

/* ── Transfer panel ────────────────────────────────────────────────────────── */
.transfer-panel {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(168, 207, 255, 0.07);
    background: rgba(168, 207, 255, 0.015);
    flex-shrink: 0;
    flex-wrap: wrap;
    transition: background 0.2s;
}

.transfer-panel--active {
    background: rgba(168, 207, 255, 0.03);
}

.tp-info {
    flex: 1;
    min-width: 160px;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.tp-info-title {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.45);
    letter-spacing: 0.18em;
}

.tp-info-sub {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.22);
    letter-spacing: 0.03em;
    line-height: 1.55;
}

.tp-btn {
    background: transparent;
    border: 1px solid rgba(168, 207, 255, 0.2);
    color: rgba(168, 207, 255, 0.55);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 7px 16px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
    white-space: nowrap;
}

.tp-btn:hover:not(:disabled) {
    background: rgba(168, 207, 255, 0.06);
    border-color: rgba(168, 207, 255, 0.5);
    color: rgba(168, 207, 255, 0.9);
    box-shadow: 0 0 12px rgba(168, 207, 255, 0.1);
}

.tp-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.tp-confirm {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 8px;
    color: #00FF88;
    letter-spacing: 0.08em;
}

.tp-confirm-icon {
    font-size: 10px;
}

.hct-confirm-enter-active, .hct-confirm-leave-active { transition: opacity 0.3s; }
.hct-confirm-enter-from,   .hct-confirm-leave-to     { opacity: 0; }

/* ── Ledger ─────────────────────────────────────────────────────────────────── */
.ledger {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.ledger-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 16px;
    border-bottom: 1px solid rgba(168, 207, 255, 0.08);
    flex-shrink: 0;
}

.ledger-title {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.4);
    letter-spacing: 0.2em;
}

.ledger-sub {
    font-size: 6px;
    color: rgba(168, 207, 255, 0.18);
    letter-spacing: 0.1em;
}

.ledger-body {
    flex: 1;
    overflow-y: auto;
}

.ledger-body::-webkit-scrollbar       { width: 2px; }
.ledger-body::-webkit-scrollbar-track { background: transparent; }
.ledger-body::-webkit-scrollbar-thumb { background: rgba(168, 207, 255, 0.1); }

.tx-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px;
    border-bottom: 1px solid rgba(168, 207, 255, 0.04);
    transition: background 0.1s;
}

.tx-row:hover { background: rgba(168, 207, 255, 0.02); }

.tx-row--credit { border-left: 2px solid rgba(0, 255, 136, 0.2); }
.tx-row--debit  { border-left: 2px solid rgba(255, 68, 85, 0.2); }

.tx-id {
    font-size: 6px;
    color: rgba(168, 207, 255, 0.2);
    letter-spacing: 0.08em;
    flex-shrink: 0;
    width: 72px;
}

.tx-desc {
    font-size: 8px;
    color: rgba(168, 207, 255, 0.55);
    letter-spacing: 0.04em;
    flex: 1;
}

.tx-ts {
    font-size: 7px;
    color: rgba(168, 207, 255, 0.2);
    letter-spacing: 0.04em;
    flex-shrink: 0;
    width: 90px;
    text-align: right;
}

.tx-amount {
    font-size: 10px;
    letter-spacing: 0.05em;
    flex-shrink: 0;
    width: 88px;
    text-align: right;
}

.tx-credit { color: rgba(0, 255, 136, 0.75); }
.tx-debit  { color: rgba(255, 68, 85, 0.65); }
</style>
