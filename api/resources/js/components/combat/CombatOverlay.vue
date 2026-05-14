<template>
    <div class="combat-overlay">

        <!-- Trace bar -->
        <TraceBar :trace-value="traceValue" />

        <!-- Phase: Ship Placement -->
        <template v-if="phase === 'placement'">
            <div class="phase-header">
                <span class="phase-title">[PLACE YOUR SHIPS]</span>
                <span class="phase-sub">COMBAT AGAINST: {{ session.opponentHandle }}</span>
                <button
                    v-if="session.isResident && escapeSecondsLeft > 0"
                    class="escape-btn"
                    @click="doEscape"
                >
                    [ESCAPE — {{ escapeSecondsLeft }}s]
                </button>
            </div>

            <div class="placement-area">
                <div class="placement-controls">
                    <div class="control-label">SHIPS TO PLACE</div>
                    <div class="ship-selector">
                        <button
                            v-for="ship in pendingShips"
                            :key="ship.id"
                            class="ship-btn"
                            :class="{ 'ship-btn--active': selectedShip?.id === ship.id }"
                            @click="selectedShip = ship"
                        >
                            [{{ '▪'.repeat(ship.size) }}] {{ ship.size }}-CELL
                        </button>
                        <div v-if="pendingShips.length === 0" class="all-placed">// ALL SHIPS PLACED</div>
                    </div>

                    <button class="orient-btn" @click="toggleOrientation">
                        [ORIENTATION: {{ orientation }}]
                    </button>

                    <button
                        class="confirm-placement-btn"
                        :disabled="pendingShips.length > 0"
                        @click="confirmPlacement"
                    >
                        [CONFIRM_PLACEMENT]
                    </button>
                </div>

                <BattleshipBoard
                    :grid="myGrid"
                    :size="session.gridSize"
                    :phase="phase"
                    :placing-ship="selectedShip ? { size: selectedShip.size, orientation } : null"
                    :is-opponent="false"
                    @square-clicked="handlePlacementClick"
                />
            </div>
        </template>

        <!-- Phase: Active Combat -->
        <template v-else-if="phase === 'active'">
            <div class="combat-area">
                <!-- Grids -->
                <div class="grids-row">
                    <BattleshipBoard
                        ref="myBoardRef"
                        :grid="myGrid"
                        :size="session.gridSize"
                        :phase="phase"
                        :is-opponent="false"
                        @square-clicked="handleOwnGridClick"
                    />
                    <BattleshipBoard
                        ref="oppBoardRef"
                        :grid="opponentGrid"
                        :size="session.gridSize"
                        :phase="phase"
                        :is-opponent="true"
                        :scrambled="scrambleActive"
                        @square-clicked="handleShot"
                    />
                </div>

                <!-- Command panel -->
                <CommandPanel
                    ref="cmdPanelRef"
                    :commands="myCommands"
                    :combat-id="session.combatId"
                    :grid-size="session.gridSize"
                    @use-command="handleUseCommand"
                    @target-mode="onTargetMode"
                    @cancel-target="targetMode = null"
                />
            </div>

            <!-- Packet Flood countdown -->
            <div v-if="packetFloodSecondsLeft > 0" class="packet-flood-bar">
                // PACKET FLOOD — SHOT EXPIRES IN {{ packetFloodSecondsLeft }}s
            </div>
        </template>

        <!-- Phase: Resolved -->
        <template v-else-if="phase === 'resolved'">
            <div class="result-screen">
                <div class="result-title" :class="result.won ? 'result-win' : 'result-loss'">
                    {{ result.won ? '[BREACH SUCCESSFUL]' : '[SYSTEM COMPROMISED]' }}
                </div>

                <div class="result-details">
                    <template v-if="result.won">
                        <div class="result-line">NODE REWARD: {{ result.nodeReward?.toLocaleString('en-US') ?? 0 }} ₢</div>
                        <div class="result-line">CREDS AVAILABLE: {{ result.credsAvailable?.toLocaleString('en-US') ?? 0 }} ₢</div>
                        <div class="result-actions">
                            <button class="result-btn result-btn--primary" @click="chooseReward('steal')">[STEAL_CREDS]</button>
                            <button class="result-btn"                     @click="chooseReward('gamble')">[GAMBLE_ITEM]</button>
                        </div>
                    </template>
                    <template v-else>
                        <div class="result-line result-line--loss">CREDS LOST: {{ result.credsLost?.toLocaleString('en-US') ?? 0 }} ₢</div>
                        <div v-if="result.statLost" class="result-line result-line--loss">
                            STAT DAMAGED: {{ result.statLost.toUpperCase() }} -1 LEVEL
                        </div>
                    </template>
                </div>

                <div class="result-countdown">
                    // RETURNING TO MAP IN {{ returnCountdown }}s
                </div>
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import TraceBar       from './TraceBar.vue';
import BattleshipBoard from './BattleshipBoard.vue';
import CommandPanel   from './CommandPanel.vue';

const props = defineProps({
    session: {
        type: Object,
        required: true,
        // { combatId, opponentHandle, isResident, gridSize, escapeWindowSeconds, myCommands }
    },
    send: { type: Function, required: true },
});

const emit = defineEmits(['close']);

// ─── Phase ────────────────────────────────────────────────────────────────────
const phase = ref('placement'); // placement | active | resolved

// ─── Grids ────────────────────────────────────────────────────────────────────
function makeGrid(size) {
    return Array.from({ length: size }, () => Array(size).fill('empty'));
}

const myGrid       = reactive(makeGrid(props.session.gridSize));
const opponentGrid = reactive(makeGrid(props.session.gridSize));

// ─── Trace bar ────────────────────────────────────────────────────────────────
const traceValue = ref(0.5);

// ─── Commands ─────────────────────────────────────────────────────────────────
const myCommands = reactive(
    (props.session.myCommands ?? []).map(c => ({
        name: typeof c === 'string' ? c : c.name,
        type: typeof c === 'object' ? c.type : 'offensive',
        used: false,
    })),
);

const scrambleActive = ref(false);

// ─── Ship placement ───────────────────────────────────────────────────────────
const SHIP_SIZES  = [5, 4, 3, 3, 2];
const orientation = ref('H');
const selectedShip = ref(null);

const pendingShips = reactive(
    SHIP_SIZES.map((size, i) => ({ id: i, size, placed: false })).filter(s => !s.placed),
);
const placedShips = ref([]); // [ { cells: [{row, col}] } ]

function toggleOrientation() {
    orientation.value = orientation.value === 'H' ? 'V' : 'H';
}

function handlePlacementClick({ row, col }) {
    if (!selectedShip.value) return;
    const { size } = selectedShip.value;
    const cells = [];

    for (let i = 0; i < size; i++) {
        const r = orientation.value === 'H' ? row       : row + i;
        const c = orientation.value === 'H' ? col + i : col;
        if (r >= props.session.gridSize || c >= props.session.gridSize) return; // out of bounds
        if (myGrid[r][c] !== 'empty') return; // collision
        cells.push({ row: r, col: c });
    }

    cells.forEach(({ row: r, col: c }) => { myGrid[r][c] = 'ship'; });
    placedShips.value.push({ cells });

    const idx = pendingShips.findIndex(s => s.id === selectedShip.value.id);
    if (idx !== -1) pendingShips.splice(idx, 1);
    selectedShip.value = pendingShips[0] ?? null;
}

function confirmPlacement() {
    if (pendingShips.length > 0) return;
    props.send('PLACE_SHIPS', {
        combatId: props.session.combatId,
        ships:    placedShips.value,
    });
    phase.value = 'active';
}

// ─── Escape window ────────────────────────────────────────────────────────────
const escapeSecondsLeft = ref(props.session.escapeWindowSeconds ?? 0);
let escapeTimer = null;

if (props.session.isResident && escapeSecondsLeft.value > 0) {
    escapeTimer = setInterval(() => {
        escapeSecondsLeft.value--;
        if (escapeSecondsLeft.value <= 0) clearInterval(escapeTimer);
    }, 1000);
}

function doEscape() {
    props.send('ESCAPE', { combatId: props.session.combatId });
    clearInterval(escapeTimer);
    emit('close');
}

// ─── Combat: shooting ────────────────────────────────────────────────────────
const myBoardRef  = ref(null);
const oppBoardRef = ref(null);
const cmdPanelRef = ref(null);
const targetMode  = ref(null); // null | { command }

function handleShot({ row, col }) {
    const state = opponentGrid[row]?.[col];
    if (state === 'hit' || state === 'miss' || state === 'false_hit') return;

    // Intercept grid click for targeting commands
    if (targetMode.value) {
        const cmd = targetMode.value.command;
        if (cmd === 'DDOS FOG') {
            props.send('USE_COMMAND', {
                combatId: props.session.combatId,
                command: cmd,
                params: { row, col },
            });
            markCommandUsed(cmd);
            targetMode.value = null;
            cmdPanelRef.value?.cancelCommand();
            return;
        }
        if (cmd === 'Databomb') {
            props.send('USE_COMMAND', {
                combatId: props.session.combatId,
                command: cmd,
                params: { row, col },
            });
            markCommandUsed(cmd);
            targetMode.value = null;
            cmdPanelRef.value?.cancelCommand();
            return;
        }
    }

    // Normal shot
    props.send('SHOOT', {
        combatId:   props.session.combatId,
        coordinate: { row, col },
    });
}

function handleUseCommand({ command, params }) {
    props.send('USE_COMMAND', {
        combatId: props.session.combatId,
        command,
        params,
    });
    markCommandUsed(command);
    targetMode.value = null;
}

function handleOwnGridClick({ row, col }) {
    if (!targetMode.value) return;
    const cmd = targetMode.value.command;
    if (cmd === 'SQL_Injection' || cmd === 'Trojan') {
        props.send('USE_COMMAND', { combatId: props.session.combatId, command: cmd, params: { row, col } });
        markCommandUsed(cmd);
        targetMode.value = null;
        cmdPanelRef.value?.cancelCommand();
    }
}

function onTargetMode({ command }) {
    targetMode.value = { command };
}

function markCommandUsed(name) {
    const cmd = myCommands.find(c => c.name === name);
    if (cmd) cmd.used = true;
}

// ─── Incoming WebSocket events (called from parent via expose) ────────────────
function applyEvent(event) {
    const { type } = event;

    if (type === 'SHOT_RESULT') {
        const { shooterId, coordinate, result: shotResult, traceBar: tb } = event;
        const { row, col } = coordinate;
        const isMyShot = shooterId === 'me'; // parent replaces with real playerId

        if (isMyShot) {
            opponentGrid[row][col] = shotResult; // hit | miss | false_hit | signal_blocked
        } else {
            myGrid[row][col] = shotResult === 'hit' ? 'hit' : 'miss';
        }

        if (tb !== undefined) traceValue.value = tb;
    }

    if (type === 'COMMAND_RESULT') {
        const { command, effects } = event;
        if (command === 'Scramble' && effects?.active) scrambleActive.value = true;
        if (command === 'Scramble' && !effects?.active) scrambleActive.value = false;
        // DDOS FOG: parent calls board ref to trigger flash
        if (command === 'DDOS FOG' && effects?.cells) {
            effects.cells.forEach(({ row, col }) => oppBoardRef.value?.triggerFogFlash(row, col));
        }
    }

    if (type === 'COMBAT_RESOLVED') {
        resolveCombat(event);
    }
}

// ─── Packet Flood timer ───────────────────────────────────────────────────────
const packetFloodSecondsLeft = ref(0);
let packetFloodTimer = null;

function startPacketFlood(seconds) {
    packetFloodSecondsLeft.value = seconds;
    packetFloodTimer = setInterval(() => {
        packetFloodSecondsLeft.value--;
        if (packetFloodSecondsLeft.value <= 0) clearInterval(packetFloodTimer);
    }, 1000);
}

// ─── Result screen ────────────────────────────────────────────────────────────
const result        = reactive({});
const returnCountdown = ref(3);
let returnTimer = null;

function resolveCombat(event) {
    Object.assign(result, event.result ?? {});
    phase.value = 'resolved';

    returnTimer = setInterval(() => {
        returnCountdown.value--;
        if (returnCountdown.value <= 0) {
            clearInterval(returnTimer);
            emit('close');
        }
    }, 1000);
}

function chooseReward(choice) {
    props.send('REWARD_CHOICE', { combatId: props.session.combatId, choice });
}

// ─── Cleanup ──────────────────────────────────────────────────────────────────
onUnmounted(() => {
    clearInterval(escapeTimer);
    clearInterval(packetFloodTimer);
    clearInterval(returnTimer);
});

defineExpose({ applyEvent, startPacketFlood });
</script>

<style scoped>
.combat-overlay {
    position: absolute;
    inset: 0;
    z-index: 50;
    background: rgba(3, 3, 3, 0.97);
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    border: 1px solid rgba(0, 255, 255, 0.25);
    box-shadow: inset 0 0 60px rgba(0, 0, 0, 0.8);
    overflow: hidden;
}

/* ── Phase header ───────────────────────────────────────────────────────────── */
.phase-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 10px 20px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.15);
    flex-shrink: 0;
}

.phase-title {
    font-size: 13px;
    color: #00FFFF;
    letter-spacing: 0.08em;
}

.phase-sub {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.4);
    flex: 1;
}

.escape-btn {
    padding: 5px 12px;
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.5);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    animation: escape-pulse 1s ease-in-out infinite;
    transition: background 0.15s;
}
.escape-btn:hover { background: rgba(255, 179, 0, 0.1); }

@keyframes escape-pulse {
    0%, 100% { box-shadow: 0 0 6px rgba(255, 179, 0, 0.3); }
    50%       { box-shadow: 0 0 14px rgba(255, 179, 0, 0.6); }
}

/* ── Placement area ─────────────────────────────────────────────────────────── */
.placement-area {
    display: flex;
    gap: 24px;
    padding: 16px 24px;
    flex: 1;
    overflow: auto;
    align-items: flex-start;
}

.placement-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 180px;
}

.control-label {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.06em;
}

.ship-selector { display: flex; flex-direction: column; gap: 4px; }

.ship-btn {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.2);
    color: rgba(0, 255, 255, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}
.ship-btn:hover        { border-color: rgba(0,255,255,0.4); color: #00FFFF; }
.ship-btn--active      { border-color: #00FFFF; color: #00FFFF; background: rgba(0,255,255,0.07); }

.all-placed { font-size: 10px; color: #00FF88; letter-spacing: 0.04em; }

.orient-btn {
    padding: 6px 10px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.3);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    cursor: pointer;
    transition: background 0.1s;
}
.orient-btn:hover { background: rgba(0, 255, 255, 0.07); }

.confirm-placement-btn {
    padding: 8px 14px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.5);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    margin-top: 8px;
}
.confirm-placement-btn:hover:not(:disabled) {
    background: rgba(0, 255, 255, 0.08);
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.2);
}
.confirm-placement-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* ── Active combat area ─────────────────────────────────────────────────────── */
.combat-area {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.grids-row {
    display: flex;
    gap: 24px;
    padding: 16px 20px;
    flex: 1;
    align-items: flex-start;
    overflow: auto;
}

/* ── Packet Flood bar ───────────────────────────────────────────────────────── */
.packet-flood-bar {
    text-align: center;
    padding: 6px;
    font-size: 10px;
    color: #FF3333;
    background: rgba(255, 51, 51, 0.08);
    border-top: 1px solid rgba(255, 51, 51, 0.3);
    letter-spacing: 0.05em;
    animation: flood-blink 0.5s steps(1) infinite;
    flex-shrink: 0;
}

@keyframes flood-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}

/* ── Result screen ──────────────────────────────────────────────────────────── */
.result-screen {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex: 1;
    gap: 20px;
    padding: 40px;
}

.result-title {
    font-size: 28px;
    letter-spacing: 0.1em;
}

.result-win  { color: #00FF88; text-shadow: 0 0 20px rgba(0,255,136,0.5); }
.result-loss { color: #FF3333; text-shadow: 0 0 20px rgba(255,51,51,0.5); }

.result-details {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.result-line {
    font-size: 13px;
    color: rgba(0, 255, 255, 0.8);
    letter-spacing: 0.05em;
}

.result-line--loss { color: rgba(255, 51, 51, 0.8); }

.result-actions {
    display: flex;
    gap: 12px;
    margin-top: 8px;
}

.result-btn {
    padding: 10px 20px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.4);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.06em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
}
.result-btn:hover { background: rgba(0,255,255,0.08); box-shadow: 0 0 10px rgba(0,255,255,0.2); }
.result-btn--primary { border-color: #00FFFF; }

.result-countdown {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.05em;
    animation: countdown-fade 1s ease-in-out infinite;
}

@keyframes countdown-fade {
    0%, 100% { opacity: 0.5; }
    50%       { opacity: 1;   }
}
</style>
