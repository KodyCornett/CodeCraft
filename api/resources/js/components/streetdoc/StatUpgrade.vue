<template>
    <div class="sub-panel">
        <div class="sub-header">
            <button class="back-btn" @click="$emit('back')">[← BACK]</button>
            <span class="sub-title">[UPGRADE_RIG]</span>
        </div>

        <!-- Stat rows -->
        <div class="stat-table">
            <div
                v-for="stat in STATS"
                :key="stat.key"
                class="stat-row"
                :class="{ 'stat-row--selected': selectedStat === stat.key }"
            >
                <span class="stat-name">[{{ stat.label.padEnd(8) }}]</span>
                <span class="stat-level">LVL {{ String(rig[stat.key] ?? 0).padStart(2, '0') }} / {{ maxLevel }}</span>
                <span class="stat-effect">{{ stat.effect(rig) }}</span>
                <button
                    class="upgrade-btn"
                    :disabled="(rig[stat.key] ?? 0) >= maxLevel"
                    @click="selectUpgrade(stat.key)"
                >
                    [↑]
                </button>
            </div>
        </div>

        <!-- Reallocation section -->
        <div class="realloc-section">
            <div class="section-label">// STAT REALLOCATION — {{ REALLOC_COST.toLocaleString('en-US') }} ₢</div>
            <div class="realloc-row">
                <div class="realloc-col">
                    <div class="col-label">MOVE FROM</div>
                    <button
                        v-for="stat in STATS"
                        :key="'from-' + stat.key"
                        class="realloc-btn"
                        :class="{ 'realloc-btn--active': reallocFrom === stat.key }"
                        :disabled="(rig[stat.key] ?? 0) <= 1"
                        @click="reallocFrom = stat.key"
                    >{{ stat.label }}</button>
                </div>
                <div class="realloc-arrow">→</div>
                <div class="realloc-col">
                    <div class="col-label">MOVE TO</div>
                    <button
                        v-for="stat in STATS"
                        :key="'to-' + stat.key"
                        class="realloc-btn"
                        :class="{ 'realloc-btn--active': reallocTo === stat.key }"
                        :disabled="reallocFrom === stat.key || (rig[stat.key] ?? 0) >= maxLevel"
                        @click="reallocTo = stat.key"
                    >{{ stat.label }}</button>
                </div>
            </div>
            <button
                class="action-btn"
                :disabled="!reallocFrom || !reallocTo || player.creds < REALLOC_COST"
                @click="confirmRealloc"
            >
                [REALLOCATE_STAT]
            </button>
        </div>

        <!-- Upgrade confirmation modal -->
        <div v-if="pendingUpgrade" class="confirm-modal">
            <div class="confirm-header">// UPGRADE CONFIRMATION</div>
            <div class="confirm-line">UPGRADE {{ pendingUpgrade.toUpperCase() }} LVL {{ rig[pendingUpgrade] }} → {{ (rig[pendingUpgrade] ?? 0) + 1 }}</div>
            <div class="confirm-line">COST: {{ UPGRADE_COST.toLocaleString('en-US') }} ₢</div>
            <div v-if="taxStat" class="confirm-tax">TAX: {{ taxStat.toUpperCase() }} -1 LEVEL</div>
            <div class="confirm-actions">
                <button class="action-btn action-btn--confirm" @click="executeUpgrade">[CONFIRM]</button>
                <button class="action-btn"                     @click="pendingUpgrade = null">[CANCEL]</button>
            </div>
        </div>

        <div v-if="confirmMsg" class="confirm-msg">{{ confirmMsg }}</div>
        <div v-if="errorMsg"   class="error-msg">{{ errorMsg }}</div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    player: { type: Object, required: true },
    rig:    { type: Object, required: true },
});
const emit = defineEmits(['back', 'upgraded']);

const UPGRADE_COST = 500;
const REALLOC_COST = 250;
const maxLevel     = 10;

// Tax ring: upgrading X taxes X's neighbor (OS→RAM→CPU→STR→FW→OS)
const TAX_MAP = { os: 'ram', ram: 'cpu', cpu: 'storage', storage: 'firewall', firewall: 'os' };

const STATS = [
    { key: 'os',       label: 'OS',      effect: r => `MAX SS: ${(r.os ?? 1) * 25}` },
    { key: 'ram',      label: 'RAM',     effect: r => `SLOTS: ${(r.ram ?? 1) + 2}` },
    { key: 'cpu',      label: 'CPU',     effect: r => `CYCLES: ${(r.cpu ?? 1) + 2}` },
    { key: 'storage',  label: 'STORAGE', effect: r => `LOOT: ${(r.storage ?? 1) + 3}` },
    { key: 'firewall', label: 'FIREWALL',effect: r => `PING: -${(r.firewall ?? 1) * 15}s` },
];

const selectedStat  = ref(null);
const pendingUpgrade = ref(null);
const reallocFrom   = ref(null);
const reallocTo     = ref(null);
const confirmMsg    = ref('');
const errorMsg      = ref('');

const taxStat = computed(() =>
    pendingUpgrade.value ? TAX_MAP[pendingUpgrade.value] : null,
);

function selectUpgrade(key) {
    selectedStat.value  = key;
    pendingUpgrade.value = key;
    confirmMsg.value    = '';
    errorMsg.value      = '';
}

async function executeUpgrade() {
    try {
        await axios.post('/api/rig/upgrade', {
            player_id: props.player.id,
            stat:      pendingUpgrade.value,
        });
        confirmMsg.value = `// ${pendingUpgrade.value.toUpperCase()} UPGRADED — ${UPGRADE_COST.toLocaleString('en-US')} ₢ DEDUCTED`;
        emit('upgraded');
    } catch (e) {
        errorMsg.value = `// ERROR: ${e?.response?.data?.message ?? 'UPGRADE FAILED'}`;
    } finally {
        pendingUpgrade.value = null;
    }
}

async function confirmRealloc() {
    try {
        await axios.post('/api/street-doc/reallocate', {
            player_id: props.player.id,
            from_stat: reallocFrom.value,
            to_stat:   reallocTo.value,
        });
        confirmMsg.value = `// REALLOCATED ${reallocFrom.value.toUpperCase()} → ${reallocTo.value.toUpperCase()} — ${REALLOC_COST.toLocaleString('en-US')} ₢ DEDUCTED`;
        reallocFrom.value = null;
        reallocTo.value   = null;
        emit('upgraded');
    } catch (e) {
        errorMsg.value = `// ERROR: ${e?.response?.data?.message ?? 'REALLOCATION FAILED'}`;
    }
}
</script>

<style scoped>
.sub-panel {
    display: flex;
    flex-direction: column;
    gap: 16px;
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

.sub-title { font-size: 13px; letter-spacing: 0.08em; }

.stat-table { display: flex; flex-direction: column; gap: 4px; }

.stat-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 5px 8px;
    border: 1px solid transparent;
    transition: border-color 0.15s, background 0.15s;
}
.stat-row--selected {
    border-color: rgba(255, 179, 0, 0.3);
    background: rgba(255, 179, 0, 0.04);
}

.stat-name  { font-size: 10px; letter-spacing: 0.05em; min-width: 110px; color: rgba(255,179,0,0.8); }
.stat-level { font-size: 11px; min-width: 90px; }
.stat-effect { font-size: 9px; color: rgba(255,179,0,0.5); flex: 1; }

.upgrade-btn {
    background: transparent;
    border: 1px solid rgba(255,179,0,0.4);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    padding: 3px 10px;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
}
.upgrade-btn:hover:not(:disabled) {
    background: rgba(255,179,0,0.1);
    box-shadow: 0 0 6px rgba(255,179,0,0.2);
}
.upgrade-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.section-label {
    font-size: 9px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.realloc-section { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid rgba(255,179,0,0.15); padding-top: 12px; }

.realloc-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.realloc-col { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.col-label { font-size: 9px; color: rgba(255,179,0,0.4); letter-spacing: 0.05em; margin-bottom: 2px; }

.realloc-arrow { font-size: 16px; color: rgba(255,179,0,0.4); align-self: center; }

.realloc-btn {
    padding: 4px 8px;
    background: transparent;
    border: 1px solid rgba(255,179,0,0.2);
    color: rgba(255,179,0,0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, color 0.1s, border-color 0.1s;
}
.realloc-btn:hover:not(:disabled) { color: #FFB300; border-color: rgba(255,179,0,0.5); }
.realloc-btn--active { color: #FFB300; border-color: #FFB300; background: rgba(255,179,0,0.08); }
.realloc-btn:disabled { opacity: 0.2; cursor: not-allowed; }

.action-btn {
    padding: 8px 14px;
    background: transparent;
    border: 1px solid rgba(255,179,0,0.4);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: background 0.15s;
}
.action-btn:hover:not(:disabled) { background: rgba(255,179,0,0.08); }
.action-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.action-btn--confirm { border-color: #FFB300; }

.confirm-modal {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px;
    border: 1px solid rgba(255,179,0,0.5);
    background: rgba(255,179,0,0.04);
}

.confirm-header { font-size: 10px; color: rgba(255,179,0,0.5); letter-spacing: 0.05em; }
.confirm-line   { font-size: 12px; }
.confirm-tax    { font-size: 11px; color: #FF3333; }
.confirm-actions { display: flex; gap: 10px; margin-top: 4px; }

.confirm-msg { font-size: 10px; color: #00FF88; letter-spacing: 0.04em; }
.error-msg   { font-size: 10px; color: #FF3333; letter-spacing: 0.04em; }
</style>
