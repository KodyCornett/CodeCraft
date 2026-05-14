<template>
    <div class="sub-panel">
        <div class="sub-header">
            <button class="back-btn" @click="$emit('back')">[← BACK]</button>
            <span class="sub-title">[INSTALL_HW]</span>
        </div>

        <div class="port-info">
            PORTS USED: {{ installedCount }} / {{ portSlots }}
        </div>

        <div v-if="mockInventory.length === 0" class="empty-msg">
            // INVENTORY EMPTY — NO HARDWARE ENCRYPTS AVAILABLE
        </div>

        <div v-else class="hw-list">
            <div
                v-for="item in mockInventory"
                :key="item.id"
                class="hw-item"
                :class="{ 'hw-item--installed': item.installed }"
            >
                <div class="hw-main">
                    <span class="hw-name">[{{ item.name }}]</span>
                    <span class="hw-stat">+{{ item.boostAmount }} {{ item.statBoosted.toUpperCase() }}</span>
                    <span class="hw-rarity hw-rarity--{{ item.rarity }}">{{ item.rarity.toUpperCase() }}</span>
                </div>
                <div class="hw-actions">
                    <span v-if="item.installed" class="installed-badge">[INSTALLED]</span>
                    <button
                        v-else
                        class="install-btn"
                        :disabled="installedCount >= portSlots"
                        @click="install(item)"
                    >[INSTALL]</button>
                </div>
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
    player:    { type: Object, required: true },
    portSlots: { type: Number, default: 4 },
});
const emit = defineEmits(['back', 'installed']);

// Mock hardware inventory — replaced by real API data in Prompt 9+
const mockInventory = ref([
    { id: 'hw1', name: 'Overshield Mk.I',   statBoosted: 'os',       boostAmount: 2, rarity: 'common',    installed: false },
    { id: 'hw2', name: 'TurboCache v2',      statBoosted: 'ram',      boostAmount: 3, rarity: 'uncommon',  installed: false },
    { id: 'hw3', name: 'ClockBoost Chip',    statBoosted: 'cpu',      boostAmount: 2, rarity: 'common',    installed: false },
    { id: 'hw4', name: 'DeepVault Array',    statBoosted: 'storage',  boostAmount: 4, rarity: 'rare',      installed: false },
    { id: 'hw5', name: 'WallBreaker Prime',  statBoosted: 'firewall', boostAmount: 5, rarity: 'legendary', installed: false },
]);

const installedCount = computed(() => mockInventory.value.filter(i => i.installed).length);

const confirmMsg = ref('');
const errorMsg   = ref('');

async function install(item) {
    errorMsg.value   = '';
    confirmMsg.value = '';
    try {
        await axios.post('/api/street-doc/install', {
            player_id:  props.player.id,
            encrypt_id: item.id,
        });
        item.installed   = true;
        confirmMsg.value = `// ${item.name.toUpperCase()} INSTALLED — +${item.boostAmount} ${item.statBoosted.toUpperCase()} ACTIVE`;
        emit('installed');
    } catch (e) {
        errorMsg.value = `// ERROR: ${e?.response?.data?.message ?? 'INSTALL FAILED'}`;
    }
}
</script>

<style scoped>
.sub-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
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
    border-bottom: 1px solid rgba(255,179,0,0.25);
    padding-bottom: 12px;
}

.back-btn {
    background: transparent;
    border: 1px solid rgba(255,179,0,0.35);
    color: rgba(255,179,0,0.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    padding: 4px 10px;
    cursor: pointer;
    transition: color 0.15s;
}
.back-btn:hover { color: #FFB300; border-color: #FFB300; }

.sub-title  { font-size: 13px; letter-spacing: 0.08em; }
.port-info  { font-size: 10px; color: rgba(255,179,0,0.5); letter-spacing: 0.04em; }
.empty-msg  { font-size: 11px; color: rgba(255,179,0,0.3); letter-spacing: 0.04em; margin-top: 20px; }

.hw-list { display: flex; flex-direction: column; gap: 6px; }

.hw-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border: 1px solid rgba(255,179,0,0.18);
    transition: border-color 0.15s;
}
.hw-item--installed { border-color: rgba(255,179,0,0.4); background: rgba(255,179,0,0.04); }

.hw-main { display: flex; align-items: center; gap: 12px; }

.hw-name   { font-size: 11px; }
.hw-stat   { font-size: 10px; color: rgba(255,179,0,0.7); }
.hw-rarity { font-size: 9px; letter-spacing: 0.06em; padding: 1px 5px; border: 1px solid; }
.hw-rarity--common    { color: rgba(255,179,0,0.5); border-color: rgba(255,179,0,0.2); }
.hw-rarity--uncommon  { color: #00FFFF; border-color: rgba(0,255,255,0.3); }
.hw-rarity--rare      { color: #FF00CC; border-color: rgba(255,0,204,0.3); }
.hw-rarity--legendary { color: #FF3333; border-color: rgba(255,51,51,0.4); animation: legendary-pulse 2s ease-in-out infinite; }

@keyframes legendary-pulse {
    0%, 100% { text-shadow: 0 0 4px #FF3333; }
    50%       { text-shadow: 0 0 10px #FF3333, 0 0 20px rgba(255,51,51,0.4); }
}

.hw-actions { display: flex; align-items: center; }

.installed-badge {
    font-size: 9px;
    color: #00FF88;
    border: 1px solid rgba(0,255,136,0.4);
    padding: 2px 6px;
    letter-spacing: 0.04em;
}

.install-btn {
    background: transparent;
    border: 1px solid rgba(255,179,0,0.4);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.04em;
    padding: 4px 10px;
    cursor: pointer;
    transition: background 0.15s;
}
.install-btn:hover:not(:disabled) { background: rgba(255,179,0,0.08); }
.install-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.confirm-msg { font-size: 10px; color: #00FF88; letter-spacing: 0.04em; }
.error-msg   { font-size: 10px; color: #FF3333; letter-spacing: 0.04em; }
</style>
