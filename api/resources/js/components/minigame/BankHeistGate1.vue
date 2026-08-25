<template>
    <div class="bh1-overlay">
        <div class="bh1-terminal">

            <div class="bh1-topbar">
                <span>TARGET: {{ bankName }}</span>
                <span class="bh1-timer" :class="{ 'bh1-timer--warn': timeLeft <= 10, 'bh1-timer--crit': timeLeft <= 5 }">
                    COUNTERTRACE: {{ timeLeft.toFixed(1) }}s
                </span>
                <span>BANK ICE {{ bankIce }}</span>
            </div>
            <div class="bh1-rule" />

            <!-- ── Spoofed Handshake ─────────────────────────────────────────── -->
            <div class="bh1-label">[ INTERCEPTED HANDSHAKE READOUT ] — probe for the flaw, decrypt it, slot it</div>

            <div class="bh1-slots">
                <div v-for="(v, i) in slots" :key="i" class="bh1-slot" :class="{ 'bh1-slot--filled': v }">
                    {{ v ?? '[ EMPTY ]' }}
                </div>
            </div>

            <div class="bh1-readout">
                <button
                    v-for="entry in readout"
                    :key="entry.id"
                    class="bh1-candidate"
                    :class="{ 'bh1-candidate--used': entry.used }"
                    :disabled="entry.used"
                    @click="probe(entry)"
                >
                    {{ entry.display }}
                </button>
            </div>

            <div v-if="flash" class="bh1-flash" :class="flash.kind">{{ flash.text }}</div>

            <div class="bh1-footer">
                <button class="bh1-abort" @click="abort">[ ABORT — no cost, but no entry either ]</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useBankHeist } from '@/composables/useBankHeist.js';

const props = defineProps({
    canvasId:  { type: String, required: true },
    bankName:  { type: String, default: 'UNKNOWN TARGET' },
    bankIce:   { type: Number, required: true },
    playerCpu: { type: Number, default: 3 },
    playerRam: { type: Number, default: 2 },
    playerOs:  { type: Number, default: 2 },
});

const emit = defineEmits(['success', 'failed', 'abort']);

const bh = useBankHeist();

const timeLeft = ref(bh.baseTimer(props.playerCpu, props.playerRam, props.bankIce));
const flash = ref(null);

// ── Spoofed Handshake state ─────────────────────────────────────────────────
const REAL_COUNT = 3;
const slots = ref([null, null, null]);
const realValues = [];
const readout = ref([]);

function randCode() {
    const h = '0123456789ABCDEF';
    return h[~~(Math.random() * 16)] + h[~~(Math.random() * 16)] + h[~~(Math.random() * 16)];
}

function buildReadout() {
    const used = new Set();
    function fresh() {
        let c;
        do { c = randCode(); } while (used.has(c));
        used.add(c);
        return c;
    }
    for (let i = 0; i < REAL_COUNT; i++) realValues.push(fresh());
    const decoys = bh.decoyCount(props.bankIce);
    const entries = realValues.map((v, i) => ({ id: `real-${i}`, display: v, real: true, used: false }));
    for (let i = 0; i < decoys; i++) {
        entries.push({ id: `decoy-${i}`, display: fresh(), real: false, used: false });
    }
    // Shuffle
    for (let i = entries.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [entries[i], entries[j]] = [entries[j], entries[i]];
    }
    readout.value = entries;
}

function showFlash(text, kind) {
    flash.value = { text, kind };
    setTimeout(() => { flash.value = null; }, 900);
}

function probe(entry) {
    if (entry.used || status.value !== 'playing') return;
    entry.used = true;

    if (entry.real) {
        const slotIndex = slots.value.findIndex((s) => s === null);
        if (slotIndex !== -1) slots.value[slotIndex] = entry.display;
        showFlash('DECRYPTED — SLOTTED', 'good');
        if (slots.value.every((s) => s !== null)) {
            status.value = 'success';
            emit('success');
        }
    } else {
        const penalty = bh.wrongActionPenalty(props.playerOs);
        timeLeft.value = Math.max(0, timeLeft.value - penalty);
        showFlash(`DECOY — TRACE ADVANCES −${penalty}s`, 'bad');
    }
}

// ── Shared state / lifecycle ────────────────────────────────────────────────
const status = ref('playing'); // 'playing' | 'success' | 'failed'
let tickInterval = null;

function abort() {
    if (tickInterval) clearInterval(tickInterval);
    emit('abort');
}

onMounted(() => {
    buildReadout();

    const tickMs = 200;
    tickInterval = setInterval(() => {
        if (status.value !== 'playing') return;

        timeLeft.value = Math.max(0, timeLeft.value - tickMs / 1000);

        if (timeLeft.value <= 0) {
            status.value = 'failed';
            clearInterval(tickInterval);
            emit('failed');
        }
    }, tickMs);
});

onBeforeUnmount(() => {
    if (tickInterval) clearInterval(tickInterval);
});
</script>

<style scoped>
.bh1-overlay { position: fixed; inset: 0; background: rgba(4, 6, 10, 0.92); z-index: 200; display: flex; align-items: center; justify-content: center; }
.bh1-terminal { width: min(680px, 92vw); background: #0a0f16; border: 1px solid #2a3a4a; font-family: 'JetBrains Mono', monospace; color: #a8c4d8; padding: 18px 20px; }
.bh1-topbar { display: flex; justify-content: space-between; font-size: 10px; letter-spacing: 0.05em; color: #6a8aa0; }
.bh1-timer { color: #4a90d8; }
.bh1-timer--warn { color: #d8a83c; }
.bh1-timer--crit { color: #e04848; animation: bh1-pulse 0.6s ease-in-out infinite; }
@keyframes bh1-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
.bh1-rule { border-top: 1px solid #1e2a36; margin: 10px 0 16px; }
.bh1-label { font-size: 10px; letter-spacing: 0.08em; color: #4a90d8; margin-bottom: 12px; }

.bh1-slots { display: flex; gap: 10px; margin-bottom: 16px; }
.bh1-slot { flex: 1; text-align: center; padding: 10px 6px; border: 1px solid #2a3a4a; font-size: 12px; opacity: 0.5; }
.bh1-slot--filled { opacity: 1; border-color: #2ed88a; color: #2ed88a; }

.bh1-readout { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.bh1-candidate { font-family: inherit; font-size: 11px; padding: 6px 10px; background: #101822; border: 1px solid #2a3a4a; color: #a8c4d8; cursor: pointer; }
.bh1-candidate:hover:not(:disabled) { border-color: #4a90d8; }
.bh1-candidate--used { opacity: 0.25; cursor: default; }

.bh1-flash { font-size: 10px; letter-spacing: 0.05em; padding: 6px 0; }
.bh1-flash.good { color: #2ed88a; }
.bh1-flash.bad { color: #e04848; }

.bh1-footer { margin-top: 16px; text-align: right; }
.bh1-abort { font-family: inherit; font-size: 9px; color: #6a8aa0; background: transparent; border: 1px solid #2a3a4a; padding: 6px 12px; cursor: pointer; }
.bh1-abort:hover { border-color: #e04848; color: #e04848; }
</style>
