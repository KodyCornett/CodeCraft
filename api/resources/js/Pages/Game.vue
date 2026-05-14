<template>
    <GameScreen>
        <!-- Boot sequence — shown before map loads -->
        <Transition name="boot-fade">
            <BootSequence v-if="!booted" @done="booted = true" />
        </Transition>

        <div ref="mapStageRef" class="map-stage" :class="{ 'map-hidden': !booted }">
            <!-- Layer 1+: Hex node map -->
            <HexMapCanvas
                ref="mapCanvasRef"
                :nodes="mapNodes"
                :links="mapLinks"
                :pings="pings"
                :current-node-id="currentNodeId"
                @node-clicked="onNodeClicked"
                @street-doc-selected="onStreetDocSelected"
            />

            <!-- Layer 4: HUD overlay -->
            <HUD :player="mockPlayer" :current-node="currentNode" />

            <!-- Layer 5: Panel overlay -->
            <Transition name="panel-overlay">
                <div v-if="activePanel" class="panel-backdrop" @click.self="closePanel" />
            </Transition>

            <Transition name="panel-slide">
                <div v-if="activePanel" class="panel-drawer">
                    <component
                        :is="panelComponents[activePanel]"
                        :player="mockPlayer"
                        :rig="mockRig"
                        :commands="mockCommands"
                        :inventory="mockInventory"
                        @close="closePanel"
                    />
                </div>
            </Transition>

            <!-- Open Season notification flash -->
            <OpenSeasonNotification
                v-if="showOsNotification"
                :bounty-value="osBountyValue"
                @done="showOsNotification = false"
            />

            <!-- Layer 7: Combat overlay (highest priority) -->
            <Transition name="fade-instant">
                <CombatOverlay
                    v-if="activeCombat"
                    ref="combatRef"
                    :session="activeCombat"
                    :send="ws.send"
                    @close="activeCombat = null"
                />
            </Transition>

            <!-- Layer 6: Street Doc overlay -->
            <Transition name="fade-instant">
                <StreetDocMenu
                    v-if="activeStreetDoc"
                    :node="activeStreetDoc"
                    :player="mockPlayer"
                    @close="activeStreetDoc = null"
                />
            </Transition>

            <!-- Node interaction window -->
            <Transition name="nw-fade">
                <NodeWindow
                    v-if="selectedNode && !activeMinigame && !hackResultData"
                    :node="selectedNode"
                    :is-on-node="selectedNode.id === currentNodeId"
                    :is-adjacent="adjacentNodeIds.has(selectedNode?.id)"
                    :player="mockPlayer"
                    :position="nodeWindowPos"
                    :moving="isMoving"
                    @close="selectedNode = null"
                    @move="onMoveRequest"
                    @hack="onHackRequest"
                />
            </Transition>

            <!-- Map loading indicator -->
            <div v-if="mapLoading" class="map-loading">// LOADING NETWORK DATA...</div>
        </div>

        <!-- Mini games — fixed overlays above everything -->
        <BubblePop
            v-if="activeMinigame === 'bubble'"
            :tier="hackContext?.tier ?? 1"
            @done="onMinigameDone"
        />
        <MemoryGame
            v-if="activeMinigame === 'memory'"
            :tier="hackContext?.tier ?? 1"
            @done="onMinigameDone"
        />
        <HackResult
            v-if="hackResultData"
            :success="hackResultData.success"
            :resource="hackResultData.resource"
            :cred-value="hackResultData.credValue"
            :movement-pts="hackResultData.movementPts"
            @done="onHackResultDone"
        />

        <!-- NavBar sits below map-stage, inside GameScreen -->
        <NavBar :active-panel="activePanel" @panel-open="openPanel" @panel-close="closePanel" />
    </GameScreen>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios         from 'axios';
import GameScreen    from '@/components/layout/GameScreen.vue';
import HUD           from '@/components/layout/HUD.vue';
import NavBar        from '@/components/layout/NavBar.vue';
import HexMapCanvas  from '@/components/map/HexMapCanvas.vue';
import NodeWindow    from '@/components/map/NodeWindow.vue';
import StreetDocMenu from '@/components/streetdoc/StreetDocMenu.vue';
import PlayerStats   from '@/components/panels/PlayerStats.vue';
import RigInfo       from '@/components/panels/RigInfo.vue';
import Commands      from '@/components/panels/Commands.vue';
import Inventory     from '@/components/panels/Inventory.vue';
import ScanArea      from '@/components/panels/ScanArea.vue';
import CombatOverlay          from '@/components/combat/CombatOverlay.vue';
import OpenSeasonNotification from '@/components/shared/OpenSeasonNotification.vue';
import BootSequence           from '@/components/shared/BootSequence.vue';
import BubblePop  from '@/components/minigames/BubblePop.vue';
import MemoryGame from '@/components/minigames/MemoryGame.vue';
import HackResult from '@/components/minigames/HackResult.vue';
import { useMapData }   from '@/composables/useMapData.js';
import { useWebSocket } from '@/composables/useWebSocket.js';

const panelComponents = { PlayerStats, RigInfo, Commands, Inventory, ScanArea };

// ─── Map data ─────────────────────────────────────────────────────────────────
const {
    nodes: apiNodes, links: apiLinks, loading: mapLoading,
    fetchAll, updateNodeState, updateNodeResources,
} = useMapData();

// Fall back to mock data while API is unavailable / loading
const MOCK_NODES = [
    { id: 'a1',  district: 'Downtown',   state: 'active',     label: 'Steam Plant',        tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'a2',  district: 'Downtown',   state: 'active',     label: 'River Park',         tier: 1, credValueBase: 120, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'a3',  district: 'Downtown',   state: 'active',     label: 'Spokane Club',       tier: 2, credValueBase: 150, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'a4',  district: 'Downtown',   state: 'active',     label: 'Fox Theater',        tier: 2, credValueBase: 160, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'a5',  district: 'Downtown',   state: 'hacked',     label: 'Davenport Hotel',    tier: 2, credValueBase: 200, credDepleted: true,  movementDepleted: false, credLastHackedAt: null },
    { id: 'a6',  district: 'Downtown',   state: 'hacked',     label: 'Saranac Bldg',       tier: 1, credValueBase: 110, credDepleted: false, movementDepleted: true,  credLastHackedAt: null },
    { id: 'a7',  district: 'Downtown',   state: 'hacked',     label: 'Old City Hall',      tier: 2, credValueBase: 175, credDepleted: true,  movementDepleted: false, credLastHackedAt: null },
    { id: 'a8',  district: 'Downtown',   state: 'scorched',   label: 'Bank of America',    tier: 3, credValueBase: 300, credDepleted: true,  movementDepleted: true,  credLastHackedAt: null },
    { id: 'a9',  district: 'Downtown',   state: 'scorched',   label: 'Chase Tower',        tier: 3, credValueBase: 280, credDepleted: true,  movementDepleted: true,  credLastHackedAt: null },
    { id: 'a10', district: 'Downtown',   state: 'active',     label: 'Flour Mill',         tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'a11', district: 'Downtown',   state: 'street_doc', label: 'Monroe Underground', tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b1',  district: 'South Hill', state: 'active',     label: 'Manito Park',        tier: 1, credValueBase: 110, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b2',  district: 'South Hill', state: 'active',     label: 'Lincoln Heights',    tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b3',  district: 'South Hill', state: 'active',     label: 'Rockwood Bakery',    tier: 2, credValueBase: 150, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b4',  district: 'South Hill', state: 'hacked',     label: 'South Hill Mall',    tier: 2, credValueBase: 180, credDepleted: true,  movementDepleted: false, credLastHackedAt: null },
    { id: 'b5',  district: 'South Hill', state: 'hacked',     label: 'Latah Creek',        tier: 1, credValueBase: 120, credDepleted: false, movementDepleted: true,  credLastHackedAt: null },
    { id: 'b6',  district: 'South Hill', state: 'active',     label: 'Perry District',     tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b7',  district: 'South Hill', state: 'scorched',   label: 'Comstock Park',      tier: 2, credValueBase: 200, credDepleted: true,  movementDepleted: true,  credLastHackedAt: null },
    { id: 'b8',  district: 'South Hill', state: 'active',     label: 'Eagle Ridge',        tier: 2, credValueBase: 140, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'b9',  district: 'South Hill', state: 'hacked',     label: 'Thorpe Road',        tier: 1, credValueBase: 100, credDepleted: true,  movementDepleted: false, credLastHackedAt: null },
    { id: 'b10', district: 'South Hill', state: 'street_doc', label: 'South Hill Salvage', tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c1',  district: 'North Side', state: 'active',     label: 'Whitworth Campus',   tier: 2, credValueBase: 160, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c2',  district: 'North Side', state: 'active',     label: 'Shadle Park',        tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c3',  district: 'North Side', state: 'active',     label: 'Five Mile Prairie',  tier: 1, credValueBase: 110, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c4',  district: 'North Side', state: 'hacked',     label: 'Wandermere',         tier: 2, credValueBase: 145, credDepleted: true,  movementDepleted: false, credLastHackedAt: null },
    { id: 'c5',  district: 'North Side', state: 'active',     label: "Spokane Int'l",      tier: 2, credValueBase: 175, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c6',  district: 'North Side', state: 'scorched',   label: 'Hillyard Depot',     tier: 2, credValueBase: 200, credDepleted: true,  movementDepleted: true,  credLastHackedAt: null },
    { id: 'c7',  district: 'North Side', state: 'active',     label: 'Francis Ave',        tier: 1, credValueBase: 100, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
    { id: 'c8',  district: 'North Side', state: 'hacked',     label: 'North Spokane Lib',  tier: 1, credValueBase: 120, credDepleted: false, movementDepleted: true,  credLastHackedAt: null },
    { id: 'c9',  district: 'North Side', state: 'active',     label: 'Northtown Mall',     tier: 2, credValueBase: 155, credDepleted: false, movementDepleted: false, credLastHackedAt: null },
];

const MOCK_LINKS = [
    { source: 'a1',  target: 'a2'  }, { source: 'a1',  target: 'a3'  },
    { source: 'a2',  target: 'a4'  }, { source: 'a3',  target: 'a5'  },
    { source: 'a4',  target: 'a6'  }, { source: 'a5',  target: 'a7'  },
    { source: 'a6',  target: 'a8'  }, { source: 'a7',  target: 'a9'  },
    { source: 'a8',  target: 'a10' }, { source: 'a9',  target: 'a11' },
    { source: 'a10', target: 'a11' }, { source: 'a2',  target: 'a6'  },
    { source: 'a3',  target: 'a7'  }, { source: 'a4',  target: 'a8'  },
    { source: 'a1',  target: 'a10' }, { source: 'a5',  target: 'a11' },
    { source: 'b1',  target: 'b2'  }, { source: 'b1',  target: 'b3'  },
    { source: 'b2',  target: 'b4'  }, { source: 'b3',  target: 'b5'  },
    { source: 'b4',  target: 'b6'  }, { source: 'b5',  target: 'b7'  },
    { source: 'b6',  target: 'b8'  }, { source: 'b7',  target: 'b9'  },
    { source: 'b8',  target: 'b10' }, { source: 'b9',  target: 'b10' },
    { source: 'b2',  target: 'b6'  }, { source: 'b3',  target: 'b7'  },
    { source: 'b1',  target: 'b8'  }, { source: 'b4',  target: 'b9'  },
    { source: 'c1',  target: 'c2'  }, { source: 'c1',  target: 'c3'  },
    { source: 'c2',  target: 'c4'  }, { source: 'c3',  target: 'c5'  },
    { source: 'c4',  target: 'c6'  }, { source: 'c5',  target: 'c7'  },
    { source: 'c6',  target: 'c8'  }, { source: 'c7',  target: 'c9'  },
    { source: 'c8',  target: 'c9'  }, { source: 'c2',  target: 'c6'  },
    { source: 'c3',  target: 'c7'  },
    { source: 'a1',  target: 'b1'  }, { source: 'a3',  target: 'b3'  },
    { source: 'a10', target: 'b6'  }, { source: 'a11', target: 'b10' },
    { source: 'a1',  target: 'c1'  }, { source: 'a4',  target: 'c2'  },
    { source: 'a10', target: 'c5'  }, { source: 'b1',  target: 'c8'  },
];

const mapNodes = computed(() => apiNodes.value.length ? apiNodes.value : MOCK_NODES);
const mapLinks = computed(() => apiLinks.value.length ? apiLinks.value : MOCK_LINKS);

// ─── WebSocket ────────────────────────────────────────────────────────────────
const ws = useWebSocket();

// ─── Live game state ──────────────────────────────────────────────────────────
const pings         = ref([]);
const currentNodeId = ref(null);

ws.onMessage('PLAYER_MOVED', (msg) => {
    if (msg.ping) pings.value.push(msg.ping);
    if (msg.playerId === mockPlayer.value.id) currentNodeId.value = msg.nodeId;
});

ws.onMessage('PING_EXPIRED', (msg) => {
    pings.value = pings.value.filter(p => p.pingId !== msg.pingId);
});

ws.onMessage('NODE_STATE_CHANGED', (msg) => {
    updateNodeState(msg.nodeId, msg.newState);
});

ws.onMessage('COMBAT_TRIGGER', (msg) => {
    activeCombat.value = {
        combatId:            msg.combatId,
        opponentHandle:      msg.opponentHandle ?? 'UNKNOWN',
        isResident:          msg.isResident ?? false,
        gridSize:            msg.gridSize ?? 8,
        escapeWindowSeconds: msg.escapeWindowSeconds ?? 0,
        myCommands:          msg.myCommands ?? [],
    };
});

ws.onMessage('SHOT_RESULT',     (msg) => combatRef.value?.applyEvent({ type: 'SHOT_RESULT',     ...msg }));
ws.onMessage('COMMAND_RESULT',  (msg) => combatRef.value?.applyEvent({ type: 'COMMAND_RESULT',  ...msg }));
ws.onMessage('COMBAT_RESOLVED', (msg) => combatRef.value?.applyEvent({ type: 'COMBAT_RESOLVED', ...msg }));

// ─── Open Season notification ─────────────────────────────────────────────────
const showOsNotification = ref(false);
const osBountyValue      = ref(0);

ws.onMessage('OPEN_SEASON_TRIGGERED', (msg) => {
    mockPlayer.value.isOpenSeason = true;
    osBountyValue.value           = msg.bountyValue ?? 0;
    showOsNotification.value      = true;
});

// ─── Boot state ───────────────────────────────────────────────────────────────
const booted = ref(false);

// ─── Combat state ─────────────────────────────────────────────────────────────
const activeCombat = ref(null);
const combatRef    = ref(null);

// ─── Panel state ──────────────────────────────────────────────────────────────
const activePanel     = ref(null);
const activeStreetDoc = ref(null);

function openPanel(name) { activePanel.value = name; }
function closePanel()    { activePanel.value = null; }

// ─── Mock player ──────────────────────────────────────────────────────────────
const mockPlayer = ref({
    id:                    null,
    handle:                'CodeCraft',
    currentSS:             75,
    maxSS:                 100,
    creds:                 12500,
    cpuCycles:             8,
    maxCpu:                10,
    district:              'DOWNTOWN',
    bountyLevel:           0,
    nodesHackedThisRun:    0,
    pvpWinsThisRun:        0,
    bountyMultiplier:      1.00,
    isOpenSeason:          false,
    isLimping:             false,
    postCombatSilentMoves: 0,
    lastStreetDocName:     'Monroe Underground',
});

const mockRig = ref({
    chassis:   'GHOST FRAME Mk.II',
    portSlots: 4,
    pointCap:  20,
    os:        4,
    ram:       3,
    cpu:       4,
    storage:   3,
    firewall:  3,
    peripherals: [
        { id: 'p1', name: 'RAM Overdrive',    stat: 'ram',      boost: 2, installed: true,  damaged: false },
        { id: 'p2', name: 'Firewall Patch',   stat: 'firewall', boost: 1, installed: true,  damaged: false },
        { id: 'p3', name: 'Storage Vault',    stat: 'storage',  boost: 1, installed: false, damaged: false },
        { id: 'p4', name: 'Cracked CPU Chip', stat: 'cpu',      boost: 3, installed: true,  damaged: true  },
    ],
});

const mockCommands = ref([
    { id: 'c1',  name: 'Databomb',        type: 'offensive', description: 'Plants a false hit marker with a subtle visual tell.',                                    active: true  },
    { id: 'c2',  name: 'Overload',        type: 'offensive', description: "Displaces opponent's next shot randomly near their target.",                              active: true  },
    { id: 'c3',  name: 'Crash',           type: 'offensive', description: 'Scans a row — green if ship present, red if clear.',                                     active: true  },
    { id: 'c4',  name: 'Trojan',          type: 'offensive', description: 'Auto-triggers negative effect when the Trojan ship is sunk.',                            active: false },
    { id: 'c5',  name: 'Exploit',         type: 'offensive', description: 'Free retaliatory shot when opponent hits your ship.',                                    active: false },
    { id: 'c6',  name: 'Packet Flood',    type: 'offensive', description: "Opponent's next 3 shots each have a 5-second timer.",                                    active: false },
    { id: 'c7',  name: 'Scramble',        type: 'offensive', description: "Wipes opponent's shot history markers for up to 3 turns.",                               active: false },
    { id: 'c8',  name: 'SQL_Injection',   type: 'offensive', description: 'First hit on a chosen 3+ square ship reads as a miss.',                                  active: false },
    { id: 'c9',  name: 'Fork Bomb',       type: 'offensive', description: 'If initial shot hits, a second hit fires on an adjacent square.',                        active: false },
    { id: 'c10', name: 'DDOS_FOG',        type: 'offensive', description: 'Blocks a 2×2 area for 2 turns. No marker left behind.',                                  active: false },
    { id: 'c11', name: 'RootKit',         type: 'offensive', description: 'Steal 1 use of an unused command if you sink a ship.',                                   active: false },
    { id: 'c12', name: 'Buffer Overflow', type: 'offensive', description: "Silently disables one of opponent's unused commands for its next use.",                  active: false },
    { id: 'c13', name: 'Blackout',        type: 'defensive', description: 'Silences all incoming offensive commands for 3 turns.',                                  active: true  },
]);

const mockInventory = ref([
    { id: 'i1', name: 'RAM Overdrive Mk.II', stat: 'ram',     boost: 3, rarity: 'rare',     installed: false },
    { id: 'i2', name: 'CPU Overclock',       stat: 'cpu',     boost: 2, rarity: 'uncommon', installed: false },
    { id: 'i3', name: 'OS Hardened Kernel',  stat: 'os',      boost: 1, rarity: 'common',   installed: false },
]);

// ─── Node window state ────────────────────────────────────────────────────────
const mapCanvasRef  = ref(null);
const mapStageRef   = ref(null);
const selectedNode  = ref(null);  // mutable copy of the clicked node
const nodeWindowPos = ref({ x: 40, y: 60 });
const isMoving      = ref(false);
const currentNode   = ref(null);  // node the player is currently standing on (HUD feed)

/** Build a currentNode object with pre-computed adjacentCount and movementPts. */
function buildCurrentNode(nodeId) {
    const node = mapNodes.value.find(n => n.id === nodeId);
    if (!node) return null;
    let adjacentCount = 0;
    for (const link of mapLinks.value) {
        const s = typeof link.source === 'object' ? link.source.id : link.source;
        const t = typeof link.target === 'object' ? link.target.id : link.target;
        if (s === nodeId || t === nodeId) adjacentCount++;
    }
    return {
        ...node,
        adjacentCount,
        movementPts: Math.max(3, (node.tier ?? 1) + 2),
        isStreetDoc: node.state === 'street_doc',
    };
}

function refreshCurrentNode() {
    currentNode.value = currentNodeId.value ? buildCurrentNode(currentNodeId.value) : null;
}

// Update HUD node panel whenever player position or map data changes
watch([currentNodeId, mapNodes], refreshCurrentNode);

// Adjacent node IDs relative to current position
const adjacentNodeIds = computed(() => {
    if (!currentNodeId.value) return new Set();
    const ids = new Set();
    for (const link of mapLinks.value) {
        const s = typeof link.source === 'object' ? link.source.id : link.source;
        const t = typeof link.target === 'object' ? link.target.id : link.target;
        if (s === currentNodeId.value) ids.add(t);
        if (t === currentNodeId.value) ids.add(s);
    }
    return ids;
});

function clampWindowPos(x, y) {
    const stage = mapStageRef.value;
    const W = 256, H = 260;
    if (!stage) return { x, y };
    const maxX = stage.clientWidth  - W - 10;
    const maxY = stage.clientHeight - H - 10;
    return { x: Math.min(Math.max(8, x), maxX), y: Math.min(Math.max(8, y), maxY) };
}

function onNodeClicked({ node, screenX, screenY }) {
    selectedNode.value = { ...node };
    nodeWindowPos.value = clampWindowPos(screenX + 18, screenY - 70);
}

function onStreetDocSelected(node) {
    activeStreetDoc.value = node;
}

// ─── Movement ─────────────────────────────────────────────────────────────────
function onMoveRequest() {
    if (!selectedNode.value || isMoving.value) return;
    const toId   = selectedNode.value.id;
    const fromId = currentNodeId.value;

    // Deduct CPU immediately
    mockPlayer.value.cpuCycles -= mockPlayer.value.isLimping ? 2 : 1;
    isMoving.value = true;

    mapCanvasRef.value?.animatePlayerTo(fromId, toId, () => {
        currentNodeId.value = toId;
        isMoving.value = false;

        // Auto-open node window for new node
        const liveNode = mapNodes.value.find(n => n.id === toId);
        if (liveNode) {
            selectedNode.value = { ...liveNode };
            // Centre the window on the settled node position
            const pos = mapCanvasRef.value?.getNodeScreenPos(toId);
            if (pos) {
                nodeWindowPos.value = clampWindowPos(pos.x + 18, pos.y - 70);
            }
        }

        // Notify engine
        ws.send('MOVE', { targetNodeId: toId });
    });
}

// ─── Mini game state ──────────────────────────────────────────────────────────
const activeMinigame = ref(null);  // 'bubble' | 'memory' | null
const hackContext    = ref(null);  // { nodeId, resource, tier, credValue, movementPts }
const hackResultData = ref(null);  // { success, resource, credValue, movementPts }

function onHackRequest(resource) {
    if (!selectedNode.value) return;
    const node = selectedNode.value;
    const base = node.credValueBase ?? 100;
    const hackedAt = node.credLastHackedAt;
    const mins = hackedAt ? (Date.now() - new Date(hackedAt).getTime()) / 60_000 : 0;
    const credValue   = base + Math.floor(mins / 2.5) * 25;
    const movementPts = Math.max(3, (node.tier ?? 1) + 2);

    hackContext.value = {
        nodeId: node.id,
        resource,
        tier:   node.tier ?? 1,
        credValue,
        movementPts,
    };

    activeMinigame.value = Math.random() < 0.5 ? 'bubble' : 'memory';
}

function onMinigameDone({ success }) {
    activeMinigame.value = null;
    hackResultData.value = {
        success,
        resource:    hackContext.value.resource,
        credValue:   hackContext.value.credValue,
        movementPts: hackContext.value.movementPts,
    };
}

function onHackResultDone({ ssDamage }) {
    const ctx = hackContext.value;
    hackResultData.value = null;
    hackContext.value    = null;

    if (!ctx) return;

    if (ssDamage === 0) {
        // ── Success: award resources + deplete node ────────────────────────────
        if (ctx.resource === 'creds') {
            mockPlayer.value.creds += ctx.credValue;
        } else {
            mockPlayer.value.cpuCycles = Math.min(
                mockPlayer.value.maxCpu,
                mockPlayer.value.cpuCycles + ctx.movementPts,
            );
        }

        // Update node window display
        if (selectedNode.value?.id === ctx.nodeId) {
            if (ctx.resource === 'creds') {
                selectedNode.value.credDepleted     = true;
                selectedNode.value.credLastHackedAt = new Date().toISOString();
            } else {
                selectedNode.value.movementDepleted = true;
            }
        }

        // Patch underlying map reactivity (visual state update)
        const now = new Date().toISOString();
        updateNodeResources(ctx.nodeId, {
            credDepleted:     ctx.resource === 'creds'    ? true : undefined,
            movementDepleted: ctx.resource === 'movement' ? true : undefined,
            credLastHackedAt: ctx.resource === 'creds'    ? now  : undefined,
        });

        // Persist to API — fire and forget, local state is source of truth
        axios.post(`/api/nodes/${ctx.nodeId}/deplete`, {
            resource:  ctx.resource,
            player_id: mockPlayer.value.id ?? 'local',
        }).catch(() => {});
    } else {
        // ── Failure: apply SS damage ───────────────────────────────────────────
        mockPlayer.value.currentSS = Math.max(0, mockPlayer.value.currentSS - ssDamage);

        axios.post('/api/rig/damage', {
            player_id: mockPlayer.value.id ?? 'local',
            amount:    ssDamage,
            source:    'pve',
        }).catch(() => {});
    }

    // Refresh HUD node panel to reflect updated resource state
    refreshCurrentNode();
}

// ─── Init ─────────────────────────────────────────────────────────────────────
onMounted(() => {
    fetchAll();
});

onUnmounted(() => {
    ws.disconnect();
});
</script>

<style scoped>
.map-stage {
    position: relative;
    width: 100%;
    flex: 1;
    overflow: hidden;
    min-height: 0;
    transition: opacity 0.5s ease 0.3s;
}

/* Boot sequence fade */
.boot-fade-leave-active {
    transition: opacity 0.6s ease;
}
.boot-fade-leave-to {
    opacity: 0;
}

/* Map hidden until booted */
.map-hidden {
    opacity: 0;
    pointer-events: none;
}

.map-loading {
    position: absolute;
    bottom: 12px;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.06em;
    pointer-events: none;
    z-index: 5;
}

/* Panel backdrop darkens the map */
.panel-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 30;
}

/* Panel drawer slides up from bottom */
.panel-drawer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 55%;
    z-index: 31;
    border-top: 1px solid rgba(0, 255, 255, 0.35);
    overflow: hidden;
}

/* NodeWindow transition */
.nw-fade-enter-active,
.nw-fade-leave-active {
    transition: opacity 0.15s ease;
}
.nw-fade-enter-from,
.nw-fade-leave-to {
    opacity: 0;
}

/* Street Doc / Combat instant fade */
.fade-instant-enter-active,
.fade-instant-leave-active {
    transition: opacity 0.18s ease;
}
.fade-instant-enter-from,
.fade-instant-leave-to {
    opacity: 0;
}

/* Panel transitions */
.panel-overlay-enter-active,
.panel-overlay-leave-active {
    transition: opacity 0.2s ease;
}
.panel-overlay-enter-from,
.panel-overlay-leave-to {
    opacity: 0;
}

.panel-slide-enter-active,
.panel-slide-leave-active {
    transition: transform 0.25s ease;
}
.panel-slide-enter-from,
.panel-slide-leave-to {
    transform: translateY(100%);
}
</style>
