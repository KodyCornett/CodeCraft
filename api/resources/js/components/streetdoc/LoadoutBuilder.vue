<template>
    <div class="sub-panel">
        <div class="sub-header">
            <button class="back-btn" @click="$emit('back')">[← BACK]</button>
            <span class="sub-title">[SET_LOADOUT]</span>
        </div>

        <div class="slot-info">
            ACTIVE SLOTS: {{ activeIds.length }} / {{ maxSlots }}
            &nbsp;·&nbsp;
            RAM LEVEL: {{ rig.ram ?? 4 }}
        </div>

        <div class="columns">
            <!-- Owned library -->
            <div class="col">
                <div class="col-header">// OWNED COMMANDS</div>
                <div class="cmd-list">
                    <button
                        v-for="cmd in ownedCommands"
                        :key="cmd.id"
                        class="cmd-item"
                        :class="{
                            'cmd-item--active':   activeIds.includes(cmd.id),
                            'cmd-item--offensive': cmd.type === 'offensive',
                            'cmd-item--defensive': cmd.type === 'defensive',
                        }"
                        @click="toggleCommand(cmd.id)"
                    >
                        <span class="cmd-name">[{{ cmd.name }}]</span>
                        <span class="cmd-badge cmd-badge--active"   v-if="activeIds.includes(cmd.id)">ACTIVE</span>
                        <span class="cmd-badge cmd-badge--type">{{ cmd.type.toUpperCase().slice(0,3) }}</span>
                    </button>
                </div>
            </div>

            <!-- Active loadout slots -->
            <div class="col">
                <div class="col-header">// ACTIVE LOADOUT</div>
                <div class="slot-list">
                    <div
                        v-for="n in maxSlots"
                        :key="'slot-' + n"
                        class="slot"
                        :class="{ 'slot--filled': activeIds[n - 1] }"
                    >
                        <span class="slot-num">{{ String(n).padStart(2, '0') }}</span>
                        <span class="slot-name">
                            {{ activeIds[n - 1] ? getCommandName(activeIds[n - 1]) : '— EMPTY —' }}
                        </span>
                        <button
                            v-if="activeIds[n - 1]"
                            class="slot-remove"
                            @click="removeSlot(n - 1)"
                        >[×]</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="lock-section">
            <button
                class="lock-btn"
                :disabled="locked || activeIds.length === 0"
                @click="lockLoadout"
            >
                [LOCK_LOADOUT]
            </button>
            <div v-if="locked" class="locked-msg">
                // LOADOUT LOCKED. COMMANDS ACTIVE UNTIL NEXT STREET DOC.
            </div>
            <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    player: { type: Object, required: true },
    rig:    { type: Object, required: true },
});
const emit = defineEmits(['back', 'locked']);

// Slot count = RAM level + 2 (minimum 3)
const maxSlots = computed(() => Math.max(3, (props.rig.ram ?? 4) + 2));

// All 13 seeded commands
const ownedCommands = [
    { id: 'databomb',       name: 'Databomb',       type: 'offensive' },
    { id: 'overload',       name: 'Overload',       type: 'offensive' },
    { id: 'crash',          name: 'Crash',          type: 'offensive' },
    { id: 'trojan',         name: 'Trojan',         type: 'offensive' },
    { id: 'exploit',        name: 'Exploit',        type: 'offensive' },
    { id: 'packet_flood',   name: 'Packet Flood',   type: 'offensive' },
    { id: 'scramble',       name: 'Scramble',       type: 'offensive' },
    { id: 'sql_injection',  name: 'SQL_Injection',  type: 'offensive' },
    { id: 'fork_bomb',      name: 'Fork Bomb',      type: 'offensive' },
    { id: 'ddos_fog',       name: 'DDOS FOG',       type: 'offensive' },
    { id: 'rootkit',        name: 'RootKit Install',type: 'offensive' },
    { id: 'buffer_overflow',name: 'Buffer Overflow',type: 'offensive' },
    { id: 'blackout',       name: 'Blackout',       type: 'defensive' },
];

const activeIds  = ref([]);
const locked     = ref(false);
const errorMsg   = ref('');

function toggleCommand(id) {
    if (locked.value) return;
    const idx = activeIds.value.indexOf(id);
    if (idx !== -1) {
        activeIds.value.splice(idx, 1);
    } else if (activeIds.value.length < maxSlots.value) {
        activeIds.value.push(id);
    }
}

function removeSlot(index) {
    if (locked.value) return;
    activeIds.value.splice(index, 1);
}

function getCommandName(id) {
    return ownedCommands.find(c => c.id === id)?.name ?? id;
}

async function lockLoadout() {
    errorMsg.value = '';
    try {
        await axios.post('/api/street-doc/loadout', {
            player_id:          props.player.id,
            active_command_ids: activeIds.value,
        });
        locked.value = true;
        emit('locked');
    } catch (e) {
        errorMsg.value = `// ERROR: ${e?.response?.data?.message ?? 'LOADOUT SAVE FAILED'}`;
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
    letter-spacing: 0.05em;
    padding: 4px 10px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
}
.back-btn:hover { color: #FFB300; border-color: #FFB300; }

.sub-title  { font-size: 13px; letter-spacing: 0.08em; }
.slot-info  { font-size: 10px; color: rgba(255,179,0,0.5); letter-spacing: 0.04em; }

.columns { display: flex; gap: 16px; flex: 1; min-height: 0; }
.col { display: flex; flex-direction: column; flex: 1; gap: 6px; min-height: 0; }

.col-header {
    font-size: 9px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.06em;
    margin-bottom: 4px;
}

.cmd-list, .slot-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    overflow-y: auto;
    max-height: 220px;
}

.cmd-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    background: transparent;
    border: 1px solid rgba(255,179,0,0.15);
    color: rgba(255,179,0,0.65);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}
.cmd-item:hover { border-color: rgba(255,179,0,0.4); color: #FFB300; }
.cmd-item--active {
    border-color: #FFB300;
    color: #FFB300;
    background: rgba(255,179,0,0.07);
}
.cmd-item--defensive { border-color: rgba(0,255,136,0.2); color: rgba(0,255,136,0.7); }
.cmd-item--defensive.cmd-item--active { border-color: #00FF88; color: #00FF88; background: rgba(0,255,136,0.06); }

.cmd-name { flex: 1; }

.cmd-badge {
    font-size: 8px;
    letter-spacing: 0.05em;
    padding: 1px 4px;
    border: 1px solid;
}
.cmd-badge--active { border-color: #FFB300; color: #FFB300; }
.cmd-badge--type   { border-color: rgba(255,179,0,0.2); color: rgba(255,179,0,0.4); }

.slot {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border: 1px solid rgba(255,179,0,0.12);
    color: rgba(255,179,0,0.3);
    font-size: 10px;
    min-height: 28px;
}
.slot--filled { border-color: rgba(255,179,0,0.35); color: #FFB300; }

.slot-num  { color: rgba(255,179,0,0.3); font-size: 9px; min-width: 18px; }
.slot-name { flex: 1; }

.slot-remove {
    background: transparent;
    border: none;
    color: rgba(255,179,0,0.4);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.1s;
}
.slot-remove:hover { color: #FF3333; }

.lock-section { display: flex; flex-direction: column; gap: 8px; border-top: 1px solid rgba(255,179,0,0.15); padding-top: 12px; }

.lock-btn {
    align-self: flex-start;
    padding: 9px 18px;
    background: transparent;
    border: 1px solid rgba(255,179,0,0.5);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
}
.lock-btn:hover:not(:disabled) {
    background: rgba(255,179,0,0.08);
    box-shadow: 0 0 10px rgba(255,179,0,0.2);
}
.lock-btn:disabled { opacity: 0.3; cursor: not-allowed; }

.locked-msg { font-size: 10px; color: #00FF88; letter-spacing: 0.04em; }
.error-msg  { font-size: 10px; color: #FF3333; letter-spacing: 0.04em; }
</style>
