<template>
    <div class="cmd-panel">
        <div class="cmd-panel-header">[COMMANDS]</div>

        <div class="cmd-list">
            <div
                v-for="cmd in commands"
                :key="cmd.name"
                class="cmd-row"
                :class="{
                    'cmd-row--used':     cmd.used,
                    'cmd-row--pending':  pendingCmd === cmd.name,
                    'cmd-row--offensive': cmd.type === 'offensive',
                    'cmd-row--defensive': cmd.type === 'defensive',
                }"
            >
                <button
                    class="cmd-btn"
                    :disabled="cmd.used || !!pendingCmd"
                    @click="selectCommand(cmd)"
                >
                    <span class="cmd-name">[{{ cmd.name }}]</span>
                    <span v-if="cmd.used" class="cmd-used-badge">[USED]</span>
                    <span v-else-if="cmd.type === 'defensive'" class="cmd-type-badge cmd-type-badge--def">DEF</span>
                    <span v-else class="cmd-type-badge">OFF</span>
                </button>
            </div>
        </div>

        <!-- Targeting interface -->
        <div v-if="pendingCmd" class="targeting">
            <div class="targeting-header">// {{ pendingCmd.toUpperCase() }}</div>

            <!-- Crash: row selection -->
            <template v-if="pendingCmd === 'Crash'">
                <div class="targeting-hint">SELECT ROW TO SCAN</div>
                <div class="row-selector">
                    <button
                        v-for="r in gridSize"
                        :key="'row-' + r"
                        class="row-btn"
                        @click="confirmCommand({ row: r - 1 })"
                    >{{ r - 1 }}</button>
                </div>
            </template>

            <!-- DDOS FOG: 2×2 area — uses board click passthrough -->
            <template v-else-if="pendingCmd === 'DDOS FOG'">
                <div class="targeting-hint">CLICK OPPONENT GRID — 2×2 AREA ORIGIN</div>
                <button class="cancel-btn" @click="cancelCommand">[CANCEL]</button>
            </template>

            <!-- Databomb: cell placement on opponent grid -->
            <template v-else-if="pendingCmd === 'Databomb'">
                <div class="targeting-hint">CLICK OPPONENT GRID — PLANT FALSE HIT</div>
                <button class="cancel-btn" @click="cancelCommand">[CANCEL]</button>
            </template>

            <!-- SQL_Injection / Trojan: place on own ship -->
            <template v-else-if="pendingCmd === 'SQL_Injection' || pendingCmd === 'Trojan'">
                <div class="targeting-hint">CLICK YOUR OWN GRID — SELECT SHIP CELL</div>
                <button class="cancel-btn" @click="cancelCommand">[CANCEL]</button>
            </template>

            <!-- No-target commands: confirm immediately -->
            <template v-else>
                <div class="targeting-hint">ACTIVATE {{ pendingCmd.toUpperCase() }}?</div>
                <div class="targeting-actions">
                    <button class="confirm-btn" @click="confirmCommand({})">[CONFIRM]</button>
                    <button class="cancel-btn"  @click="cancelCommand">[CANCEL]</button>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

// Commands that require a grid click for targeting
const GRID_TARGET_CMDS = new Set(['DDOS FOG', 'Databomb', 'SQL_Injection', 'Trojan']);

const props = defineProps({
    commands: {
        type: Array,
        default: () => [],
        // [ { name: String, type: 'offensive'|'defensive', used: Boolean } ]
    },
    combatId: { type: String, default: '' },
    gridSize: { type: Number, default: 8  },
});

const emit = defineEmits([
    'use-command',   // { command, params } — parent sends via WebSocket
    'target-mode',  // { command } — tells parent to listen for next grid click
    'cancel-target',
]);

const pendingCmd = ref(null);

function selectCommand(cmd) {
    if (cmd.used) return;
    pendingCmd.value = cmd.name;

    if (GRID_TARGET_CMDS.has(cmd.name)) {
        emit('target-mode', { command: cmd.name });
    }
}

function confirmCommand(params) {
    if (!pendingCmd.value) return;
    emit('use-command', { command: pendingCmd.value, params });
    pendingCmd.value = null;
}

function cancelCommand() {
    emit('cancel-target');
    pendingCmd.value = null;
}

// Called by parent when a grid click arrives during target mode
function resolveTarget(coord) {
    if (!pendingCmd.value) return;
    confirmCommand(coord);
}

defineExpose({ resolveTarget, cancelCommand, pendingCmd });
</script>

<style scoped>
.cmd-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 12px;
    background: rgba(5, 5, 5, 0.9);
    border-left: 1px solid rgba(0, 255, 255, 0.2);
    height: 100%;
    overflow-y: auto;
}

.cmd-panel-header {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: rgba(0, 255, 255, 0.5);
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.15);
    padding-bottom: 8px;
}

.cmd-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cmd-row { display: flex; }

.cmd-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 6px 8px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.15);
    color: rgba(0, 255, 255, 0.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.04em;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}
.cmd-btn:hover:not(:disabled) {
    background: rgba(0, 255, 255, 0.07);
    border-color: rgba(0, 255, 255, 0.4);
    color: #00FFFF;
}
.cmd-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.cmd-row--defensive .cmd-btn {
    border-color: rgba(0, 255, 136, 0.15);
    color: rgba(0, 255, 136, 0.7);
}
.cmd-row--defensive .cmd-btn:hover:not(:disabled) {
    background: rgba(0, 255, 136, 0.07);
    border-color: rgba(0, 255, 136, 0.4);
    color: #00FF88;
}

.cmd-row--pending .cmd-btn {
    border-color: #00FFFF;
    color: #00FFFF;
    background: rgba(0, 255, 255, 0.06);
}

.cmd-name { flex: 1; }

.cmd-used-badge {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 0 3px;
}

.cmd-type-badge {
    font-size: 8px;
    color: rgba(255, 0, 204, 0.5);
    border: 1px solid rgba(255, 0, 204, 0.2);
    padding: 0 3px;
}

.cmd-type-badge--def {
    color: rgba(0, 255, 136, 0.5);
    border-color: rgba(0, 255, 136, 0.2);
}

/* Targeting section */
.targeting {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    border: 1px solid rgba(0, 255, 255, 0.3);
    background: rgba(0, 255, 255, 0.03);
}

.targeting-header {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: #00FFFF;
    letter-spacing: 0.06em;
}

.targeting-hint {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.04em;
}

.row-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.row-btn {
    padding: 4px 8px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.3);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    transition: background 0.1s;
}
.row-btn:hover { background: rgba(0, 255, 255, 0.1); }

.targeting-actions { display: flex; gap: 8px; }

.confirm-btn, .cancel-btn {
    padding: 5px 10px;
    background: transparent;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.04em;
    cursor: pointer;
    transition: background 0.1s;
}

.confirm-btn {
    border: 1px solid rgba(0, 255, 255, 0.5);
    color: #00FFFF;
}
.confirm-btn:hover { background: rgba(0, 255, 255, 0.1); }

.cancel-btn {
    border: 1px solid rgba(255, 51, 51, 0.4);
    color: rgba(255, 51, 51, 0.7);
}
.cancel-btn:hover { background: rgba(255, 51, 51, 0.07); color: #FF3333; }
</style>
