<template>
    <div class="sub-panel">
        <div class="sub-header">
            <button class="back-btn" @click="$emit('back')">[← BACK]</button>
            <span class="sub-title">[REPAIR_SS]</span>
        </div>

        <div class="ss-status">
            <div class="ss-row">
                <span class="label">CURRENT SS</span>
                <SSBar :current="player.currentSS" :max="player.maxSS" />
            </div>
            <div class="ss-numbers">
                <span class="val-big">{{ player.currentSS }}</span>
                <span class="val-sep">/</span>
                <span class="val-big">{{ player.maxSS }}</span>
                <span class="val-unit">SS</span>
            </div>
        </div>

        <div class="repair-section">
            <div class="repair-row">
                <span class="label">REPAIR AMOUNT</span>
                <span class="repair-amount">+{{ repairAmount }} SS</span>
            </div>
            <input
                v-model.number="repairAmount"
                type="range"
                min="0"
                :max="missingSS"
                class="repair-slider"
                :disabled="missingSS === 0"
            />
            <div class="repair-labels">
                <span>0</span>
                <span>{{ missingSS }}</span>
            </div>

            <div class="cost-row">
                <span class="label">CREDIT COST</span>
                <span class="cost-val" :class="{ 'cost-unaffordable': repairCost > player.creds }">
                    {{ repairCost.toLocaleString('en-US') }} ₢
                </span>
            </div>

            <div class="action-row">
                <button
                    class="action-btn action-btn--full"
                    :disabled="missingSS === 0 || player.creds < fullRepairCost"
                    @click="repairFull"
                >
                    [REPAIR_FULL] — {{ fullRepairCost.toLocaleString('en-US') }} ₢
                </button>
                <button
                    class="action-btn"
                    :disabled="repairAmount === 0 || repairCost > player.creds"
                    @click="repairPartial"
                >
                    [REPAIR_PARTIAL]
                </button>
            </div>

            <div v-if="confirmMsg" class="confirm-msg">{{ confirmMsg }}</div>
            <div v-if="errorMsg"   class="error-msg">{{ errorMsg }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import SSBar from '@/components/shared/SSBar.vue';

const props = defineProps({
    player: { type: Object, required: true },
});
const emit = defineEmits(['back', 'repaired']);

const COST_PER_POINT = 10;

const missingSS     = computed(() => props.player.maxSS - props.player.currentSS);
const fullRepairCost = computed(() => missingSS.value * COST_PER_POINT);

const repairAmount = ref(0);
const repairCost   = computed(() => repairAmount.value * COST_PER_POINT);

const confirmMsg = ref('');
const errorMsg   = ref('');

async function postRepair(amount) {
    confirmMsg.value = '';
    errorMsg.value   = '';
    try {
        await axios.post('/api/street-doc/repair', {
            player_id:         props.player.id,
            repair_amount:     amount,
        });
        confirmMsg.value = `// REPAIRED +${amount} SS — ${(amount * COST_PER_POINT).toLocaleString('en-US')} ₢ DEDUCTED`;
        emit('repaired', amount);
    } catch (e) {
        errorMsg.value = `// ERROR: ${e?.response?.data?.message ?? 'REPAIR FAILED'}`;
    }
}

function repairFull() {
    postRepair(missingSS.value);
}

function repairPartial() {
    postRepair(repairAmount.value);
}
</script>

<style scoped>
.sub-panel {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 18px 22px;
    height: 100%;
    font-family: 'JetBrains Mono', monospace;
    color: #FFB300;
    overflow-y: auto;
}

.sub-header {
    display: flex;
    align-items: center;
    gap: 16px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.25);
    padding-bottom: 12px;
}

.back-btn {
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.35);
    color: rgba(255, 179, 0, 0.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    padding: 4px 10px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
}
.back-btn:hover { color: #FFB300; border-color: #FFB300; }

.sub-title {
    font-size: 13px;
    letter-spacing: 0.08em;
}

.ss-status {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ss-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ss-numbers {
    display: flex;
    align-items: baseline;
    gap: 4px;
    font-size: 11px;
    color: rgba(255, 179, 0, 0.7);
}

.val-big  { font-size: 22px; color: #FFB300; }
.val-sep  { font-size: 14px; }
.val-unit { font-size: 10px; letter-spacing: 0.1em; margin-left: 4px; }

.label {
    font-size: 10px;
    letter-spacing: 0.06em;
    color: rgba(255, 179, 0, 0.5);
    min-width: 120px;
}

.repair-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.repair-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.repair-amount {
    font-size: 14px;
    color: #FFB300;
}

.repair-slider {
    width: 100%;
    accent-color: #FFB300;
    cursor: pointer;
}
.repair-slider:disabled { opacity: 0.3; cursor: not-allowed; }

.repair-labels {
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: rgba(255, 179, 0, 0.4);
}

.cost-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
}

.cost-val { font-size: 13px; }
.cost-unaffordable { color: #FF3333; }

.action-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}

.action-btn {
    padding: 10px 16px;
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.4);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    text-align: left;
}
.action-btn:hover:not(:disabled) {
    background: rgba(255, 179, 0, 0.08);
    box-shadow: 0 0 8px rgba(255, 179, 0, 0.2);
}
.action-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.action-btn--full {
    border-color: rgba(255, 179, 0, 0.6);
}

.confirm-msg {
    font-size: 10px;
    color: #00FF88;
    letter-spacing: 0.04em;
    margin-top: 4px;
}

.error-msg {
    font-size: 10px;
    color: #FF3333;
    letter-spacing: 0.04em;
    margin-top: 4px;
}
</style>
