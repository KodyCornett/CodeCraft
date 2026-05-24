<template>
    <GameScreen>
        <!-- Boot sequence — shown before map loads -->
        <Transition name="boot-fade">
            <BootSequence v-if="!booted" @done="booted = true" />
        </Transition>

        <!-- Map row: map canvas + persistent side panel side by side -->
        <div class="map-row" :class="{ 'map-hidden': !booted }">

            <div class="map-stage">
                <!-- Hex node map -->
                <HexMapCanvas
                    ref="mapCanvasRef"
                    :pings="pings"
                    :crash-mines="crashMines"
                    :current-node-id="currentNodeId"
                    :player-uplink="player.uplink"
                    :player-ss="player.currentSS"
                    @node-clicked="handleNodeClicked"
                    @player-moved="handlePlayerMoved"
                    @move-blocked="onMoveBlocked"
                />

                <!-- HUD overlay -->
                <HUD :player="player" :rig="rig" :current-node="currentNode" :bounty-ticker="bountyTicker" :flash="hudFlash" />

                <!-- ICE alert banner — fires on bounty escalation events -->
                <Transition name="ice-alert-fade">
                    <div v-if="bountyAlert" class="ice-alert">
                        <span class="ice-alert-icon">⚠</span>
                        {{ bountyAlert }}
                    </div>
                </Transition>

                <!-- Open Season notification — mounts once when Open Season triggers -->
                <OpenSeasonNotification
                    v-if="showOpenSeason"
                    :bounty-value="player.pocketCreds"
                    @done="showOpenSeason = false"
                />

                <!-- Map loading indicator -->
                <div v-if="mapLoading" class="map-loading">// LOADING NETWORK DATA...</div>

                <!-- Crash mine targeting mode — persists until player picks a node or cancels -->
                <Transition name="targeting-fade">
                    <div v-if="crashTargetMode" class="crash-targeting-banner">
                        <span class="ct-icon">⚠</span>
                        <span class="ct-text">CRASH MINE — SELECT AN ADJACENT NODE TO PLANT</span>
                        <button class="ct-cancel" @click="cancelCrashTarget">[ CANCEL ]</button>
                    </div>
                </Transition>

                <!-- In-game SPLICE browser -->
                <Transition name="browser-fade">
                    <InGameBrowser
                        v-if="activeBrowserUrl"
                        :initial-url="activeBrowserUrl"
                        @close="onCloseBrowser"
                    />
                </Transition>

                <!-- Grid-Breach mini-game (PvE) -->
                <Transition name="breach-fade">
                    <GridBreach
                        v-if="activeHack"
                        :node="activeHack.node"
                        :resource="activeHack.resource"
                        :player-cpu="rig.cpu"
                        :player-ram="rig.ram"
                        :player-os="rig.os"
                        :player-firewall="rig.firewall"
                        :player-max-uplink="player.maxUplink"
                        :bounty-multiplier="player.bountyMultiplier"
                        @complete="onHackComplete"
                        @failed="onHackFailed"
                        @abort="onHackAbort"
                    />
                </Transition>

                <!-- Grid-Breach mini-game (PvP) -->
                <Transition name="breach-fade">
                    <GridBreach
                        v-if="activePvpCombat"
                        :node="{ ice: activePvpCombat.iceLevel, id: activePvpCombat.nodeCanvasId }"
                        resource="creds"
                        :player-cpu="rig.cpu"
                        :player-ram="rig.ram"
                        :player-os="rig.os"
                        :player-firewall="rig.firewall"
                        :player-max-uplink="player.maxUplink"
                        :bounty-multiplier="player.bountyMultiplier"
                        :pvp-mode="true"
                        :pvp-opponent="activePvpCombat.opponent"
                        @complete="onPvpComplete"
                        @failed="onPvpComplete({ won: false, amount: 0 })"
                        @abort="activePvpCombat = null"
                    />
                </Transition>

                <!-- Awaiting challenge acceptance overlay -->
                <Transition name="pvp-fade">
                    <div v-if="awaitingChallenge && !activePvpCombat" class="pvp-await">
                        <div class="pvp-await-inner">
                            <span class="pvp-await-icon">⚡</span>
                            <div class="pvp-await-title">CHALLENGE SENT</div>
                            <div class="pvp-await-sub">Waiting for target to respond…</div>
                            <div class="pvp-await-dots">
                                <span /><span /><span />
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Incoming challenge notification -->
                <Transition name="pvp-fade">
                    <div v-if="incomingChallenge && !activePvpCombat" class="pvp-challenge">
                        <div class="pvp-challenge-inner">
                            <span class="pvp-challenge-icon">⚡</span>
                            <div class="pvp-challenge-title">COMBAT CHALLENGE</div>
                            <div class="pvp-challenge-handle">
                                <span class="pvp-ch-label">FROM</span>
                                <span class="pvp-ch-name">{{ incomingChallenge.challenger?.handle ?? 'UNKNOWN' }}</span>
                            </div>
                            <div class="pvp-challenge-sub">You are being challenged to Grid-Breach combat</div>
                            <div class="pvp-challenge-actions">
                                <button class="pvp-btn pvp-btn--accept" @click="onAcceptChallenge">
                                    [ACCEPT]
                                </button>
                                <button class="pvp-btn pvp-btn--decline" @click="onDeclineChallenge">
                                    [DECLINE]
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Critical System Failure overlay -->
                <Transition name="pvp-fade">
                    <div v-if="criticalFailure" class="critical-failure-overlay">
                        <div class="cf-inner">
                            <div class="cf-icon">☠</div>
                            <div class="cf-title">CRITICAL SYSTEM FAILURE</div>
                            <div class="cf-sub">SYSTEM INTEGRITY LOST — REBOOTING AT STREET DOC</div>
                            <div class="cf-details">
                                <div class="cf-row">
                                    <span class="cf-key">POCKET CREDS</span>
                                    <span class="cf-val cf-val--wiped">WIPED</span>
                                </div>
                                <div class="cf-row">
                                    <span class="cf-key">BOUNTY</span>
                                    <span class="cf-val cf-val--wiped">CLEARED</span>
                                </div>
                                <div class="cf-row">
                                    <span class="cf-key">REPAIR COST</span>
                                    <span class="cf-val cf-val--cost">◈ {{ (criticalFailure.repairCost ?? 0).toLocaleString() }}</span>
                                </div>
                            </div>
                            <div class="cf-warn">Visit the Street Doc to pay for repairs before hacking again.</div>
                            <button class="cf-btn" @click="criticalFailure = null">[ REBOOT SYSTEM ]</button>
                        </div>
                    </div>
                </Transition>

                <!-- Post-combat result overlay -->
                <Transition name="pvp-fade">
                    <div v-if="pvpResult" class="pvp-result" :class="pvpResult.won ? 'pvp-result--won' : 'pvp-result--lost'">
                        <div class="pvp-result-inner">
                            <div class="pvp-result-badge">{{ pvpResult.won ? '◉ BREACH SUCCESS' : '◈ BREACH FAILED' }}</div>
                            <div class="pvp-result-vs">vs <span class="pvp-result-handle">{{ pvpResult.opponentHandle }}</span></div>
                            <div v-if="pvpResult.won && pvpResult.loot?.stolen > 0" class="pvp-result-loot">
                                <span class="pvp-loot-label">EXTRACTED</span>
                                <span class="pvp-loot-val">◈ {{ pvpResult.loot.stolen.toLocaleString() }}</span>
                            </div>
                            <div v-else-if="!pvpResult.won" class="pvp-result-lost-msg">
                                POCKET WIPED — BOUNTY RETAINED
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Persistent right panel -->
            <SidePanel
                :node="selectedNode"
                :is-on-node="selectedNode?.canvasId === currentNodeId"
                :is-adjacent="selectedNodeIsAdjacent"
                :resources="nodeResources"
                :commands="commands"
                :current-s-s="player.currentSS"
                :max-s-s="player.maxSS"
                :is-limping="player.isLimping"
                :bounties="bounties"
                :player-bounty="player.bountyLevel"
                :player-multiplier="player.bountyMultiplier"
                :player-open-season="player.isOpenSeason"
                :node-players="nodePlayers"
                :traces="nodeTraces"
                @hack="onHackSelected"
                @open-store="onOpenStore"
                @use-command="onUseCommand"
                @hack-player="onHackPlayer"
                @move="onMoveToSelected"
            />

        </div>

        <!-- NavBar sits below map-stage, inside GameScreen -->
        <NavBar
            :active-browser-url="activeBrowserUrl"
            :has-tutorial-badge="tutorial.hasBadge.value"
            @launch="onLaunch"
            @tutorial="onTutorial"
        />

        <!-- First-login welcome modal — shown once after boot for new players -->
        <Transition name="welcome-fade">
            <div v-if="showWelcomeModal" class="welcome-overlay">
                <div class="welcome-modal">
                    <div class="welcome-tag">// INCOMING TRANSMISSION</div>
                    <div class="welcome-title">WELCOME, RUNNER</div>
                    <div class="welcome-body">
                        Your rig is online. Your uplink is live.<br>
                        Complete orientation before running anything hot.<br>
                        <span class="welcome-note">Rewards go directly to your wallet — safe from PvP.</span>
                    </div>
                    <div class="welcome-actions">
                        <button class="welcome-btn welcome-btn--primary" @click="onWelcomeStart">
                            [ BEGIN ORIENTATION ]
                        </button>
                        <button class="welcome-btn welcome-btn--ghost" @click="onWelcomeSkip">
                            skip
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </GameScreen>
</template>

<script setup>
import { ref, computed, provide, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// ── Layout & shared UI ────────────────────────────────────────────────────────
import GameScreen    from '@/components/layout/GameScreen.vue';
import HUD           from '@/components/layout/HUD.vue';
import NavBar        from '@/components/layout/NavBar.vue';
import SidePanel     from '@/components/layout/SidePanel.vue';

// ── Map ───────────────────────────────────────────────────────────────────────
import HexMapCanvas  from '@/components/map/HexMapCanvas.vue';

// ── Overlays ──────────────────────────────────────────────────────────────────
import BootSequence             from '@/components/shared/BootSequence.vue';
import OpenSeasonNotification  from '@/components/shared/OpenSeasonNotification.vue';
import InGameBrowser from '@/components/browser/InGameBrowser.vue';
import GridBreach    from '@/components/minigame/GridBreach.vue';

// ── Composables ───────────────────────────────────────────────────────────────
import { useMapData }        from '@/composables/useMapData.js';
import { useWebSocket }      from '@/composables/useWebSocket.js';
import { useMapInteraction } from '@/composables/useMapInteraction.js';
import { useBrowserState }   from '@/composables/useBrowserState.js';
import { useAuth }           from '@/composables/useAuth.js';
import { useDepletion }      from '@/composables/useDepletion.js';
import { useBountyBoard }    from '@/composables/useBountyBoard.js';
import { usePosition }       from '@/composables/usePosition.js';
import { useNodePresence }   from '@/composables/useNodePresence.js';
import { useNodeTraces }     from '@/composables/useNodeTraces.js';
import { useCombat }         from '@/composables/useCombat.js';
import { useGameState }      from '@/composables/useGameState.js';
import { useHeartbeat }     from '@/composables/useHeartbeat.js';
import { useAudio }        from '@/composables/useAudio.js';
import { useTutorial }    from '@/composables/useTutorial.js';
import { SPLICE }         from '@/components/browser/SpliceRouter.js';

// ── Auth ──────────────────────────────────────────────────────────────────────
const { playerId, player: authPlayer, rig: authRig, login } = useAuth();

// ── Game state — all reactive refs, seeded from API after login ───────────────
const {
    player, rig, commands, inventory, bounties: gameStateBounties,
    hydrateFromAuth, fetchCommands, fetchInventory, upgradeCommand, useConsumable,
} = useGameState();

// ── Map data (API) ────────────────────────────────────────────────────────────
const { loading: mapLoading, fetchAll, getSpawnNode, updateNodeState, updateNodeResources, getByCanvasId, getNodesNear } = useMapData();

// ── Deplete — fires after every successful hack ───────────────────────────────
const { deplete } = useDepletion(playerId);

// ── Bounty board — live leaderboard (players with ★1+ appear here) ───────────
const { entries: bounties, startPolling: startBountyPolling, stopPolling: stopBountyPolling } = useBountyBoard(playerId);

// ── Position persistence — updates current_node_id on every move ─────────────
const { updatePosition } = usePosition(playerId);

// ── Heartbeat — keeps last_seen_at fresh; sendBeacon cleans up on tab close ───
const { startHeartbeat, stopHeartbeat } = useHeartbeat();

// ── Audio — shuffled background music, starts on first user interaction ───────
const { startAudio, stopAudio } = useAudio();

// ── Combat — challenge handshake + result submission ─────────────────────────
const {
    incomingChallenge,
    challenge:     sendChallenge,
    startPendingPoll,
    stopPendingPoll,
    accept:        acceptChallenge,
    decline:       declineChallenge,
    submitResult:  submitCombatResult,
    pollResult:    pollCombatResult,
} = useCombat(playerId);

// PvP combat state
const activePvpCombat   = ref(null);   // { opponent, challengeId, nodeCanvasId } when combat is live
const pvpResult         = ref(null);   // { won, loot } shown after combat
const awaitingChallenge = ref(false);  // true while waiting for target to accept

// Critical system failure overlay — shown when SS hits 0
const criticalFailure   = ref(null);   // { repairCost } or null

// ── Bounty escalation ladder ──────────────────────────────────────────────────
//
// Ticker display:
//   Before Star 1  — shows X/10  (first threshold is 10 hacks)
//   Each tier after — shows X/5  (stars every 5 hacks)
//
// Star 4 at 25 hacks flips Open Season — every player on the map sees a
// target marker and can collect the bounty reward.
// Star 5 at 30 hacks is maximum heat — multiplier capped, heaviest ICE pings.
//
const BOUNTY_THRESHOLDS = [
    { hacks: 30, level: 5, multiplier: 2.25, openSeason: true,  message: '⚡ MAXIMUM HEAT — HEAVY ICE CLOSING IN' },
    { hacks: 25, level: 4, multiplier: 2.00, openSeason: true,  message: 'OPEN SEASON DECLARED — ALL PLAYERS NOTIFIED' },
    { hacks: 20, level: 3, multiplier: 1.75, openSeason: false, message: 'ICE PRIORITY TARGET — ELIMINATION ORDER ISSUED' },
    { hacks: 15, level: 2, multiplier: 1.50, openSeason: false, message: 'WARNING: ICE HAS TRACKED YOUR ACTIONS — ASSETS ON THE WAY', triggerPing: true },
    { hacks: 10, level: 1, multiplier: 1.25, openSeason: false, message: 'ICE IS WATCHING — SENDING ASSETS AFTER TARGET' },
];

// Hack count → ticker display (current progress / next threshold)
// Level 0: counts to 10. Each level after counts to 5.
const STAR_HACK_THRESHOLDS = [10, 15, 20, 25, 30]; // absolute hack counts per star

// Total successful hacks this session — drives the ticker + bounty escalation
const hackCount = ref(0);

// ── Active command effects ─────────────────────────────────────────────────────
//
// Tracks moves remaining for self-targeted timed commands (Ghost Protocol,
// Dark Mode, Signal Noise, etc.). Seeded from active_effects on the /me
// response so state survives a mid-session page reload.
//
// Shape: { ghost_protocol: 3, dark_mode: 2, ... } — key is snake_case command name.
// Decremented in handlePlayerMoved. Used for:
//   • Ping suppression (ghost_protocol, dark_mode) — client-side
//   • Trace suppression (ghost_protocol)            — server-side via active_effects col
//   • Firewall boost display (firewall_patch)       — client-side HUD tweak
//
const activeEffects = ref({});

/** "Ghost Protocol" → "ghost_protocol" */
function cmdSlug(name) {
    return name.toLowerCase().replace(/\s+/g, '_');
}

// ── Open Season notification ───────────────────────────────────────────────────
// Shown once when the player's isOpenSeason flips from false to true.
// The component auto-dismisses after 3.5 s (or on click); @done unmounts it.
const showOpenSeason = ref(false);
watch(() => player.value.isOpenSeason, (isOs) => {
    if (isOs) showOpenSeason.value = true;
});

// Active ICE alert message — auto-dismissed after 5 s
const bountyAlert = ref(null);
let   _alertTimer = null;

// Move-block HUD flash — auto-dismissed after 3 s
const hudFlash        = ref('');
let   _flashTimer     = null;

// ── Crash mine state ──────────────────────────────────────────────────────────
// crashTargetMode: set to { cmd, match } while the player is picking a target node.
// crashMines:      client-only list of placed mines visible only to this player.
const crashTargetMode = ref(null);
const crashMines      = ref([]);

function showBountyAlert(message) {
    if (_alertTimer) clearTimeout(_alertTimer);
    bountyAlert.value = message;
    _alertTimer = setTimeout(() => { bountyAlert.value = null; }, 5000);
}

// Ticker: how far into the current star are we?
const bountyTicker = computed(() => {
    const n   = hackCount.value;
    const lvl = player.value.bountyLevel; // 0–5

    if (lvl === 0) {
        return { current: Math.min(n, 10), max: 10 };
    }
    if (lvl >= 5) {
        return { current: 5, max: 5 }; // maxed
    }

    const prevThreshold = STAR_HACK_THRESHOLDS[lvl - 1]; // absolute hacks at current star
    const nextThreshold = STAR_HACK_THRESHOLDS[lvl];     // absolute hacks for next star

    return {
        current: Math.min(n - prevThreshold, nextThreshold - prevThreshold),
        max:     nextThreshold - prevThreshold, // always 5
    };
});

function checkBountyEscalation(nodeIce = null) {
    const n    = hackCount.value;
    const tier = BOUNTY_THRESHOLDS.find(t => n >= t.hacks);
    if (!tier || tier.level <= player.value.bountyLevel) return;

    player.value.bountyLevel      = tier.level;
    player.value.bountyMultiplier = tier.multiplier;
    player.value.isOpenSeason     = tier.openSeason;

    showBountyAlert(tier.message);

    // Threshold ping uses the node that pushed the player over — most accurate reading
    if (tier.level >= 2) {
        firePing('threshold', nodeIce);
    }

    console.log(`[BOUNTY] ★${tier.level} — ${tier.message}`);
}

// ── ICE Ping range formula ─────────────────────────────────────────────────────
//
// The bounty star level amplifies the hacked node's ICE signal — the higher
// the star AND the higher the node's ICE, the tighter the ping circle gets.
//
//   effective_ice  = node.ice × (1 + bountyLevel)
//   raw_range      = BASE_RANGE + player.os − effective_ice
//   ping_range     = clamp(raw_range, 0, MAX_RANGE_PER_STAR[bountyLevel])
//
// At ★5, even a low-ICE node (1 × 6 = 6) leaves very little OS headroom.
// A high-ICE node (6 × 6 = 36) is instant pinpoint regardless of OS.
// Commands like Signal Noise / Ghost Protocol are the only real escape at ★4–5.
//
const PING_BASE_RANGE      = 8;
const PING_TTL_MS          = 30_000;
// Max radius (in abstract "node hops") per bounty star level
const PING_MAX_RANGE       = [8, 5, 4, 3, 2, 1];   // index = bountyLevel (0–5)
// SVG pixel radius per node-hop — roughly matches hex node spacing on the canvas
const PING_PX_PER_HOP      = 38;
const PING_MIN_RADIUS_PX   = 18;   // tight ring for a range-0 exact ping

function calcPingRange(nodeIce, playerOs, bountyLevel) {
    const lvl          = Math.min(bountyLevel, 5);
    const effectiveIce = nodeIce * (1 + lvl);
    const raw          = PING_BASE_RANGE + playerOs - effectiveIce;
    const cap          = PING_MAX_RANGE[lvl] ?? 1;
    return Math.max(0, Math.min(cap, raw));
}

function pingRadiusPx(range) {
    return range === 0
        ? PING_MIN_RADIUS_PX
        : PING_MIN_RADIUS_PX + range * PING_PX_PER_HOP;
}

// ── ICE Ping generation ────────────────────────────────────────────────────────
//
// nodeIce  — ICE rating of the node the player just hacked (0 = use current node)
// reason   — 'hack' | 'threshold' | 'movement' | 'false'
// type     — 'real' | 'false'  (false pings come from Signal Noise command)
//
function firePing(reason = 'movement', nodeIce = null, type = 'real') {
    const node = currentNode.value;
    if (!node || (!node.x && !node.y)) return;

    // Resolve ICE: provided > selected node > current standing node > default 3
    const ice = nodeIce
        ?? selectedNode.value?.ice
        ?? getByCanvasId(node.canvasId)?.ice
        ?? 3;

    const os    = rig.value?.os ?? 2;
    const range = calcPingRange(ice, os, player.value.bountyLevel);

    const ping = {
        pingId:       Math.random().toString(36).slice(2),
        canvasId:     node.canvasId,
        x:            node.x,
        y:            node.y,
        range,                              // abstract node-hop radius
        radiusPx:     pingRadiusPx(range),  // pre-computed SVG radius
        bountyLevel:  player.value.bountyLevel,
        isOpenSeason: player.value.isOpenSeason,
        type,
        reason,
        createdAt: Date.now(),
    };
    pings.value.push(ping);

    setTimeout(() => {
        pings.value = pings.value.filter(p => p.pingId !== ping.pingId);
    }, PING_TTL_MS);

    console.log(
        `[ICE PING] ★${ping.bountyLevel} — ICE ${ice} × (1+${ping.bountyLevel}) ` +
        `vs OS ${os} → range ${range} (${ping.radiusPx}px) — ${reason}`
    );
}

// ── False ping (Signal Noise command) ─────────────────────────────────────────
//
// Drops a fake ping ring at a chosen node for N player moves, then expires.
// Called when the player activates the Signal Noise command and picks a target.
// The target must be within 4 node-hops of the player's current position.
//
const FALSE_PING_MOVE_TTL = 3;
let   _falsePingMovesLeft  = 0;
let   _falsePingIds        = [];   // tracks all active false pings (Signal Noise plants 2)

// Remove all currently tracked false pings from the pings array.
function clearFalsePings() {
    pings.value = pings.value.filter(p => !_falsePingIds.includes(p.pingId));
    _falsePingIds = [];
}

// Add a single false ping ring at the given node.
// Callers are responsible for calling clearFalsePings() first when they want to
// replace existing pings (Decoy) vs. accumulate them (Signal Noise).
function fireFalsePing(targetNode) {
    if (!targetNode?.x) return;

    const range = calcPingRange(
        targetNode.ice ?? 3,
        rig.value?.os ?? 2,
        player.value.bountyLevel,
    );

    const ping = {
        pingId:       Math.random().toString(36).slice(2),
        canvasId:     targetNode.canvasId ?? targetNode.id,
        x:            targetNode.x,
        y:            targetNode.y,
        range,
        radiusPx:     pingRadiusPx(range),
        bountyLevel:  player.value.bountyLevel,
        isOpenSeason: player.value.isOpenSeason,
        type:         'false',
        reason:       'signal_noise',
        createdAt:    Date.now(),
    };
    pings.value.push(ping);
    _falsePingIds.push(ping.pingId);
    _falsePingMovesLeft = FALSE_PING_MOVE_TTL;

    console.log(`[SIGNAL NOISE] False ping planted at ${ping.canvasId} — ${FALSE_PING_MOVE_TTL} moves`);
}

// ── Player movement handler ────────────────────────────────────────────────────
//
// Ping frequency by bounty level:
//   ★0–1  — no movement pings (ICE hasn't locked on yet)
//   ★2–3  — ping every other move
//   ★4–5  — ping every move
//
let _movePingCounter = 0;
function handlePlayerMoved(event) {
    onPlayerMoved(event);   // update currentNode, uplink, district

    // Restore uplink to full whenever the player lands on a CyberDoc node
    if (getByCanvasId(event.nodeId)?.type === 'cyberdoc') {
        player.value.uplink = player.value.maxUplink;
    }

    // Persist position to backend so other players can detect same-node presence.
    // The response carries remaining_uplink — sync it so a mid-session reload
    // always restores the correct value rather than giving the player a free refill.
    updatePosition(event.nodeId, event.district ?? player.value.district, (data) => {
        if (data.remaining_uplink != null) {
            player.value.uplink = data.remaining_uplink;
        }
    });

    // ── Decrement active command effects ──────────────────────────────────────
    // Mirror the server-side decrement in position() so the client stays in sync.
    // Also update the movesLeft display property on the matching command object.
    for (const slug of Object.keys(activeEffects.value)) {
        activeEffects.value[slug] = Math.max(0, activeEffects.value[slug] - 1);
        // Reflect in the commands list so LoadoutBlock can show the countdown
        const cmd = commands.value.find(c => cmdSlug(c.name) === slug);
        if (cmd) cmd.movesLeft = activeEffects.value[slug];
        // Prune expired effects and revert any stat boosts they applied
        if (activeEffects.value[slug] === 0) {
            delete activeEffects.value[slug];
            if (cmd) cmd.movesLeft = 0;
            // Firewall Patch applied +2 on activation — undo it on expiry
            if (slug === 'firewall_patch') {
                rig.value.firewall = Math.max(0, (rig.value.firewall ?? 0) - 2);
            }
        }
    }

    // Tick down crash mine TTLs — remove any that have expired
    if (crashMines.value.length > 0) {
        crashMines.value = crashMines.value
            .map(m => ({ ...m, movesLeft: m.movesLeft - 1 }))
            .filter(m => m.movesLeft > 0);
    }

    // Tick down false ping counter (Signal Noise / Decoy planted via fireFalsePing)
    if (_falsePingIds.length > 0 && _falsePingMovesLeft > 0) {
        _falsePingMovesLeft--;
        if (_falsePingMovesLeft <= 0) {
            clearFalsePings();
            // Re-ping real location so ICE snaps back on
            setTimeout(() => firePing('false_expired'), 0);
        }
    }

    // ── Movement pings ────────────────────────────────────────────────────────
    // Ghost Protocol and Dark Mode both suppress movement pings.
    const ghostActive = (activeEffects.value.ghost_protocol ?? 0) > 0;
    const darkActive  = (activeEffects.value.dark_mode      ?? 0) > 0;

    const lvl = player.value.bountyLevel;
    if (lvl < 2 || ghostActive || darkActive) return;

    _movePingCounter++;
    const pingEvery = (lvl >= 4) ? 1 : 2;
    if (_movePingCounter % pingEvery === 0) {
        // Tick fires after onPlayerMoved sets currentNode
        setTimeout(() => firePing('movement'), 0);
    }
}

// ── JACK IN — confirmed move from NodeInfoBlock JACK IN button ────────────────
function onMoveToSelected() {
    if (!selectedNode.value?.canvasId) return;
    mapCanvasRef.value?.commitMove(selectedNode.value.canvasId);
}

// ── Node click — intercepts crash targeting mode before normal selection ───────
function handleNodeClicked(event) {
    if (crashTargetMode.value) {
        if (!event.isAdjacent) {
            clearTimeout(_flashTimer);
            hudFlash.value = 'OUT OF RANGE — select an adjacent node to plant the mine';
            _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
            return;
        }
        const { cmd, match } = crashTargetMode.value;
        const node           = event.node;
        const ttl            = cmd.duration?.moves ?? 5;
        crashMines.value.push({ canvasId: node.id, x: node.x, y: node.y, movesLeft: ttl });
        match.cooldown  = true;
        match.movesLeft = ttl;
        crashTargetMode.value = null;
        console.log(`[CRASH] Mine planted at ${node.id} — ${ttl} moves TTL`);
        return;
    }
    onNodeClicked(event);
}

function cancelCrashTarget() {
    if (!crashTargetMode.value) return;
    // Revert the premature cooldown set before the switch — command stays ready.
    crashTargetMode.value.match.cooldown = false;
    crashTargetMode.value = null;
}

// ── Resource availability ──────────────────────────────────────────────────────
//
// Ticks every second so the replenish countdowns in NodeInfoBlock run smoothly.
const _now = ref(Date.now());
let   _nowTick = null;

// Replenish window — must match NodeService::CRED_REPLENISH_MINUTES (10 min).
const REPLENISH_MS = 10 * 60 * 1000;

/** Seconds until a depleted resource replenishes, or 0 if already ready. */
function secsUntilReplenish(lastHackedAt) {
    if (!lastHackedAt) return 0;
    const readyAt = new Date(lastHackedAt).getTime() + REPLENISH_MS;
    return Math.max(0, Math.ceil((readyAt - _now.value) / 1000));
}

// Drives NodeInfoBlock [HACK] button enabled states. Merges:
//   • node resource depletion flags from the DB (server-authoritative)
//   • client-side replenish countdown so the button re-enables without polling
//   • display values for the panel (what the player could earn)
//
const nodeResources = computed(() => {
    // Always read from the live store so post-hack depletion patches are
    // reflected immediately. selectedNode.value is a spread copy made at
    // click time — getByCanvasId returns the actual reactive store entry.
    const node = selectedNode.value?.canvasId
        ? (getByCanvasId(selectedNode.value.canvasId) ?? selectedNode.value)
        : null;
    // SS = 0 (critical failure) locks all hacking.
    const ssEmpty    = (player.value.currentSS ?? 1) <= 0;

    const credSecsLeft     = secsUntilReplenish(node?.credLastHackedAt);
    const movementSecsLeft = secsUntilReplenish(node?.movementLastHackedAt);

    // A resource is available if the DB says it isn't depleted, OR the
    // client-side countdown has already reached zero (server will confirm
    // on next deplete call via replenishCheck()).
    const credReady     = !node?.credDepleted     || credSecsLeft     === 0;
    const movementReady = !node?.movementDepleted || movementSecsLeft === 0;

    return {
        creds: {
            available:    !!node && !ssEmpty && credReady,
            value:        node?.credValueBase ?? 750,
            replenishesIn: credReady ? 0 : credSecsLeft,
        },
        tech: {
            // Tech hacks draw from the same cred pool — share the depletion flag
            available:    !!node && !ssEmpty && credReady,
            value:        Math.max(1, Math.floor((node?.credValueBase ?? 100) / 100)),
            replenishesIn: credReady ? 0 : credSecsLeft,
        },
        uplink: {
                available:    !!node && !ssEmpty && movementReady,
            value:        player.value.maxUplink ?? 3,
            replenishesIn: movementReady ? 0 : movementSecsLeft,
        },
    };
});

// ── Map interaction ───────────────────────────────────────────────────────────
// Pass getByCanvasId so onNodeClicked can merge canvas geometry with live DB state
// (ice rating, resource depletion, UUID) into a single enriched node object.
const {
    mapCanvasRef, currentNodeId, currentNode,
    selectedNode, selectedNodeIsAdjacent, pings, booted,
    onPlayerMoved, onNodeClicked,
} = useMapInteraction(player, getByCanvasId);

// ── Node presence — polls for other players at the current node ──────────────
// Must come after useMapInteraction so currentNodeId is already declared.
// playerId is passed so the guard knows auth is complete (session auth uses
// cookies — there is no Authorization header to check).
const { nodePlayers } = useNodePresence(currentNodeId, playerId);

// ── Node traces — data fragments left by recent hackers on the selected node.
// Driven by selectedNode (not currentNode) so the player can inspect any node
// they click on, not just the one they're standing on. refreshTraces() is
// called after the player's own hack completes so they see their own fragment
// pop in without waiting for the 10s poll.
const selectedCanvasId = computed(() => selectedNode.value?.canvasId ?? null);
const { traces: nodeTraces, refreshNow: refreshTraces } = useNodeTraces(selectedCanvasId, playerId);

// ── Browser state ─────────────────────────────────────────────────────────────
const { activeBrowserUrl, onLaunch, onCloseBrowser } = useBrowserState();

// Maps each CyberDoc hub node ID to its named SPLICE page.
// When the player opens the store from a hub, they land on that doc's branded page.
const CYBERDOC_URLS = {
    'NS-hub': SPLICE.CYBER_DOC_PATCH,
    'BA-hub': SPLICE.CYBER_DOC_KNUCKLE,
    'DT-hub': SPLICE.CYBER_DOC_VEIL,
    'UD-hub': SPLICE.CYBER_DOC_AXIOM,
    'SV-hub': SPLICE.CYBER_DOC_FLOAT,
};

function onOpenStore() {
    const url = CYBERDOC_URLS[currentNodeId.value] ?? SPLICE.CYBER_DOC;
    onLaunch(url);
}

// ── Tutorial ──────────────────────────────────────────────────────────────────
const tutorial = useTutorial();

// Provide tutorial state to all SPLICE page components via inject('tutorial').
// GhostProtocol0 reads it to render quest status.
// GridBreachGuide calls markStepDone('read_manual') on mount.
provide('tutorial', tutorial);

// Clear TERMINAL badge when the player opens the tutorial page
watch(activeBrowserUrl, (url) => {
    if (url?.startsWith(SPLICE.TERMINAL)) tutorial.clearBadge();
});

// Quest trigger: node inspected (any node click)
watch(() => selectedNode.value, (node) => {
    if (node) tutorial.markStepDone('inspect');
});

// Quest trigger: player actually moved (both old and new nodeId must be non-null
// so the initial position load doesn't count as a move).
// Also fires visit_cyberdoc if the destination is a CyberDoc hub node.
watch(currentNodeId, (newVal, oldVal) => {
    if (newVal && oldVal) {
        tutorial.markStepDone('move');
        const node = getByCanvasId(newVal);
        if (node?.type === 'cyberdoc') tutorial.markStepDone('visit_cyberdoc');
    }
});

// onTutorial — kept for GameMenu backward compat; opens TERMINAL page
function onTutorial() {
    onLaunch(SPLICE.TERMINAL);
}

// First-login modal
const showWelcomeModal = computed(() =>
    booted.value && !tutorial.tutorialSeen.value && !tutorial.tutorialSkipped.value
);

function onWelcomeStart() {
    tutorial.markSeen();
    onLaunch(SPLICE.TERMINAL);
}

function onWelcomeSkip() {
    tutorial.skip();
}

// ── WebSocket — live server events ────────────────────────────────────────────
const ws = useWebSocket();

ws.onMessage('PLAYER_MOVED', (msg) => {
    if (msg.ping) pings.value.push(msg.ping);
    if (msg.playerId === player.value.id) currentNodeId.value = msg.nodeId;
});

ws.onMessage('PING_EXPIRED', (msg) => {
    pings.value = pings.value.filter(p => p.pingId !== msg.pingId);
});

ws.onMessage('NODE_STATE_CHANGED', (msg) => {
    updateNodeState(msg.nodeId, msg.newState);
});

// ── Move blocked ──────────────────────────────────────────────────────────────
function onMoveBlocked({ reason }) {
    const messages = {
        SS_CRITICAL: 'SYSTEM FAILURE — REPAIR RIG BEFORE MOVING',
        NO_UPLINK:   'UPLINK DEPLETED — HACK AN UPLINK NODE',
    };
    const msg = messages[reason];
    if (!msg) return;

    clearTimeout(_flashTimer);
    hudFlash.value = msg;
    _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
}

// ── Grid-Breach mini-game ─────────────────────────────────────────────────────
//
// ICE escalation: every successful hack on a node raises its effective ICE by 1
// (every 2 hacks = +1 ICE, capped at 10). This means hot zones get harder over
// time — players are pushed to explore rather than farm the same spot forever.
//
// Minimum ICE = 3 (BlackHat v1.0 CPU — starting rig CPU equals starting node ICE,
// so fresh players face balanced content on day one).

const MIN_ICE         = 3;
const ICE_ESCALATION  = 2;   // hacks per +1 ICE on a node

// Track how many times each node has been successfully hacked this session
const nodeHackCounts  = ref(new Map());

function effectiveNodeIce(node) {
    if (!node) return MIN_ICE;
    const baseIce  = Math.max(MIN_ICE, node.ice ?? MIN_ICE);
    const hacks    = nodeHackCounts.value.get(node.id) ?? 0;
    return Math.min(10, baseIce + Math.floor(hacks / ICE_ESCALATION));
}

// activeHack: { node (with effectiveIce injected), resource }
const activeHack = ref(null);

function onHackSelected(resource) {
    if (!selectedNode.value) return;
    // Player must be standing on the node to hack it. With inspect-before-move,
    // the panel shows hack buttons for any inspected node, so we guard here.
    if (selectedNode.value.canvasId !== currentNodeId.value) return;
    const ice  = effectiveNodeIce(selectedNode.value);
    // Pass a shallow copy with the live effective ICE baked in
    activeHack.value = {
        node: { ...selectedNode.value, ice },
        resource,
    };
}

function applyHackReward(resource, amount) {
    // Cred hacks fill the pocket (at-risk) — not the safe wallet.
    // The wallet only grows when pocket is banked at Street Doc.
    if (resource === 'creds')  player.value.pocketCreds = (player.value.pocketCreds ?? 0) + amount;
    if (resource === 'tech')   player.value.techPoints  = (player.value.techPoints  ?? 0) + amount;
    if (resource === 'uplink') player.value.uplink      = player.value.maxUplink    ?? 3;
}

async function onHackComplete({ resource, amount }) {
    const node   = selectedNode.value;
    const nodeId = node?.id;           // UUID — required for the deplete API
    const nodeIce = effectiveNodeIce(node);  // ICE at time of hack — used for ping range
    activeHack.value = null;

    // Quest trigger: first breach (success counts)
    tutorial.markStepDone('hack');

    // Escalate node ICE — each successful breach tightens the node's defences
    if (nodeId) {
        nodeHackCounts.value.set(nodeId, (nodeHackCounts.value.get(nodeId) ?? 0) + 1);
    }

    // Increment session hack counter then check for bounty level-up.
    // Pass nodeIce so the threshold ping is calibrated to the node that triggered it.
    hackCount.value += 1;
    checkBountyEscalation(nodeIce);

    // Fire a hack ping — suppressed by Ghost Protocol and Dark Mode
    const ghostActive = (activeEffects.value.ghost_protocol ?? 0) > 0;
    const darkActive  = (activeEffects.value.dark_mode      ?? 0) > 0;
    if (player.value.bountyLevel >= 1 && !ghostActive && !darkActive) {
        firePing('hack', nodeIce);
    }

    applyHackReward(resource, amount);
    console.log(
        `[HACK] ${resource.toUpperCase()} +${amount}` +
        ` | bounty LVL ${player.value.bountyLevel}` +
        ` | hacks #${hackCount.value}`
    );

    // Tell the backend — credits pocket_creds, records hack for bounty tracking
    if (nodeId) {
        const patch = await deplete(nodeId, resource, resource !== 'uplink' ? amount : 0);

        if (patch) {
            updateNodeResources(nodeId, patch);

            // Sync server-authoritative balances
            if (patch.player?.tech_points !== undefined) {
                player.value.techPoints = patch.player.tech_points;
            }
            if (patch.currentUplink != null) {
                player.value.uplink = patch.currentUplink;
            }
            // Surface bounty escalation from the server (if any)
            if (patch.bountyEvent?.type === 'bounty_marked') {
                console.log(`[BOUNTY] Server confirmed: on the board at hack ${patch.player?.nodes_hacked_this_run}`);
            }
            if (patch.bountyEvent?.type === 'open_season_triggered') {
                console.warn('[BOUNTY] ⚡ OPEN SEASON — server confirmed');
            }

            // Pull the fresh trace we just wrote so the player sees it
            // immediately in the node panel without waiting for the next poll.
            refreshTraces();
        }
    }
}

async function onHackFailed({ resource, amount }) {
    // Capture node before clearing activeHack — needed for ICE + SS damage
    const failedNode = activeHack.value?.node ?? selectedNode.value;
    activeHack.value = null;

    // Quest trigger: first breach (failure counts — you learn either way)
    tutorial.markStepDone('hack');

    // Failed breach: no rewards.

    // If the player is stranded (uplink = 0), grant exactly 1 escape move.
    // They must use it to reach a node they can actually breach for full recovery.
    if ((player.value.uplink ?? 0) === 0) {
        player.value.uplink = 1;
    }

    // ── SS damage — server computes max(1, nodeICE − effectiveFirewall) ──────
    // The client no longer computes or sends the damage amount; the server
    // resolves node ICE and effective Firewall (including peripherals) itself.
    const nodeCanvasId = failedNode?.canvasId ?? failedNode?.id ?? null;

    console.log(`[HACK] Breach failed on ${nodeCanvasId}`);

    // Sync SS with the server and handle critical failure if SS hits 0
    if (playerId.value && nodeCanvasId) {
        try {
            const res = await axios.post('/api/rig/damage', {
                node_canvas_id: nodeCanvasId,
                source:         'pve',
            });
            player.value.currentSS = res.data.current_ss;
            player.value.maxSS     = res.data.max_ss;

            if (res.data.event === 'critical_failure') {
                const cf = res.data.critical_failure ?? {};

                // Wipe pocket + bounty state
                player.value.pocketCreds      = 0;
                player.value.bountyLevel      = 0;
                player.value.bountyMultiplier = 1.0;
                player.value.isOpenSeason     = false;
                player.value.isLimping        = false;
                hackCount.value               = 0;

                // Teleport to spawn node
                if (cf.respawn_canvas_id) {
                    currentNodeId.value = cf.respawn_canvas_id;
                }

                // Show critical failure overlay — blocks action until dismissed
                criticalFailure.value = { repairCost: cf.repair_cost ?? 0 };
            }
        } catch {
            // Server sync failed — client-side SS is already decremented
        }
    }

    // Leave a data fragment even on failure — the attempt is detectable.
    const nodeId = failedNode?.id;
    const pid    = playerId.value;
    if (nodeId && pid) {
        try {
            await axios.post(`/api/nodes/${nodeId}/trace`, { player_id: pid });
            refreshTraces();
        } catch {
            // Best-effort — don't surface trace errors to the player
        }
    }
}

function onHackAbort() {
    activeHack.value = null;
}

// ── CyberDoc banking — called from CyberDocStore via gameState injection ──────
//
// Banks all pocket_creds into the safe wallet, resets bounty.
// Returns the API response or null on failure.
//
async function bankCreds() {
    const pid       = playerId.value;
    const canvasId  = currentNodeId.value;
    if (!pid) return null;

    try {
        const res = await axios.post('/api/cyberdoc/bank', {
            player_id:           pid,
            cyberdoc_canvas_id:  canvasId ?? undefined,
        });
        const result = res.data;

        // Move pocket into wallet
        const banked = result.pocket_banked ?? 0;
        if (banked > 0) {
            player.value.creds = (player.value.creds ?? 0) + banked;
            console.log(`[CYBERDOC] ◈${banked} pocket creds banked to wallet`);
        }
        player.value.pocketCreds = 0;

        // Restore uplink to full
        player.value.uplink = player.value.maxUplink;

        // Reset bounty run state
        player.value.bountyLevel      = result.player?.bounty_level      ?? 0;
        player.value.bountyMultiplier = result.player?.bounty_multiplier  ?? 1.0;
        player.value.isOpenSeason     = result.player?.is_open_season     ?? false;

        // Reset session counters
        hackCount.value   = 0;
        bountyAlert.value = null;
        pings.value        = [];
        _movePingCounter    = 0;
        _falsePingIds       = [];
        _falsePingMovesLeft = 0;

        // Clear command cooldowns and active effects
        commands.value.forEach(cmd => { cmd.cooldown = false; cmd.movesLeft = 0; });
        activeEffects.value = {};

        console.log('[CYBERDOC] Bounty reset. Commands refreshed.');
        return result;
    } catch (e) {
        console.error('[CYBERDOC] Bank failed:', e?.response?.data?.message ?? e.message);
        return null;
    }
}

// ── Command activation ────────────────────────────────────────────────────────
//
// Dispatches a player's equipped command when they click [USE ON MAP].
// Self-targeted commands with a move duration are registered server-side
// via POST /api/player/activate-command so effects like Ghost Protocol
// trace suppression are enforced on the backend even across reconnects.
//
// Move-counted effects are mirrored in activeEffects so the client can
// suppress pings locally without polling. LoadoutBlock reads movesLeft
// to show a countdown badge instead of the generic "CD" indicator.
//
async function onUseCommand(cmd) {
    const match = commands.value.find(c => c.id === cmd.id);
    if (!match || match.cooldown) return;

    // Mark cooldown immediately — cleared on Street Doc visit
    match.cooldown  = true;
    match.movesLeft = 0;

    const slug         = cmdSlug(cmd.name);
    const moveDuration = cmd.duration?.moves ?? 0;

    console.log(`[CMD] ${cmd.name.toUpperCase()} activated`);

    // ── Register server-side for commands that need backend enforcement ───────
    // Self-targeted + move duration = server needs to know (Ghost Protocol trace
    // suppression, Blackout incoming-command blocking, Firewall Patch stat boost).
    if (cmd.targetType === 'self' && moveDuration > 0) {
        try {
            await import('axios').then(m =>
                m.default.post('/api/player/activate-command', { command_id: cmd.id })
            );
        } catch (e) {
            console.warn(`[CMD] Server activation failed for ${cmd.name}:`, e?.response?.data);
        }
        // Mirror locally for ping suppression and UI countdown
        activeEffects.value[slug] = moveDuration;
        match.movesLeft           = moveDuration;
    }

    // ── Dispatch per-command map effects ──────────────────────────────────────
    switch (cmd.name) {

        case 'Crash':
            // Node-targeted trap — revert the premature cooldown and enter
            // targeting mode. Cooldown is applied once the player picks a node.
            match.cooldown  = false;
            match.movesLeft = 0;
            crashTargetMode.value = { cmd, match };
            console.log('[CRASH] Targeting mode active — awaiting node selection.');
            break;

        case 'Ghost Protocol':
            // Trace suppression: handled server-side by NodeController::deplete()
            // checking active_effects['ghost_protocol'] > 0.
            // Ping suppression: handled in handlePlayerMoved + onHackComplete
            // via activeEffects.ghost_protocol check.
            console.log(`[GHOST PROTOCOL] Active — ${moveDuration} moves. Traces suppressed. Pings masked.`);
            break;

        case 'Dark Mode':
            // Suppresses ALL ICE pings (hack + movement) for the duration.
            // activeEffects.dark_mode check is applied in handlePlayerMoved + onHackComplete.
            // Any currently visible pings from this player fade immediately.
            pings.value = pings.value.filter(p => p.type !== 'real');
            console.log(`[DARK MODE] Active — ${moveDuration} moves. All ICE pings suppressed.`);
            break;

        case 'Signal Noise': {
            // Plants 2 false pings at random nearby nodes to confuse ICE tracking.
            // Clear any previous false pings first, then add both new ones.
            // Each call to fireFalsePing accumulates into _falsePingIds rather than
            // replacing, so both pings persist for the full FALSE_PING_MOVE_TTL.
            const node    = currentNode.value;
            const nearby  = node ? getNodesNear(node.x, node.y, { minPx: 80, maxPx: 260, count: 2 }) : [];
            if (nearby.length === 0) {
                console.warn('[SIGNAL NOISE] No nearby nodes found for false pings.');
            }
            clearFalsePings();   // replace any existing false-ping set
            for (const target of nearby) {
                fireFalsePing(target);
            }
            console.log(`[SIGNAL NOISE] ${nearby.length} false ping(s) planted.`);
            break;
        }

        case 'Decoy': {
            // Plants a single false ping at the currently selected node (if different
            // from the player's current node) or a random node within 6 hops.
            const decoyTarget = (selectedNode.value && selectedNode.value.canvasId !== currentNodeId.value)
                ? selectedNode.value
                : getNodesNear(currentNode.value?.x ?? 0, currentNode.value?.y ?? 0, { minPx: 100, maxPx: 400, count: 1 })[0];

            if (decoyTarget) {
                clearFalsePings();   // replace any existing false ping with the new decoy
                fireFalsePing(decoyTarget);
                console.log(`[DECOY] False ping planted at ${decoyTarget.canvasId}.`);
            } else {
                console.warn('[DECOY] No valid target node found.');
            }
            break;
        }

        case 'Firewall Patch':
            // +2 effective Firewall for moveDuration moves.
            // Apply locally to the rig snapshot — the boost is cosmetic/PvP only
            // (server-side stat checks will use the un-boosted value for hacks).
            rig.value.firewall = (rig.value.firewall ?? 0) + 2;
            console.log(`[FIREWALL PATCH] Firewall boosted to ${rig.value.firewall} for ${moveDuration} moves.`);
            break;

        case 'Blackout':
            // Blocks all incoming player commands for moveDuration moves.
            // Enforced server-side: active_effects['blackout'] > 0 checked by
            // CombatChallengeController (TODO: wire that check when PvP commands land).
            console.log(`[BLACKOUT] Incoming commands blocked for ${moveDuration} moves.`);
            break;

        default:
            // Offensive / player-targeted / node-targeted commands need a target-picker
            // UI that isn't built yet. They are on cooldown but their effect is pending.
            console.log(`[CMD] ${cmd.name} — target selection UI not yet implemented.`);
    }
}

// ── PvP Combat ────────────────────────────────────────────────────────────────
//
// Flow:
//   1. Challenger clicks [HACK] on a player in NodeInfoBlock
//   2. sendChallenge() POSTs to /api/combat/challenge
//   3. Target's pending poll picks it up → incomingChallenge ref is set
//   4. Target sees notification overlay → accepts or declines
//   5. On accept: both enter GridBreach PvP mode (challenger's CPU vs target's firewall)
//   6. GridBreach result → submitCombatResult() → POST /api/combat/result
//

// Called when the [HACK] button is clicked next to a player in NodeInfoBlock
async function onHackPlayer(targetPlayer) {
    if (!currentNodeId.value) return;

    awaitingChallenge.value = true;
    const result = await sendChallenge(targetPlayer.id, currentNodeId.value);
    if (!result) {
        awaitingChallenge.value = false;
        return;
    }

    console.log(`[PVP] Challenge sent to ${targetPlayer.handle} — waiting for response`);

    // Poll /api/combat/challenge/{id}/status until the target accepts, declines,
    // or the 30s TTL expires (15 attempts × 2s).
    const challengeId = result.challenge_id;
    let   attempts    = 0;
    const maxAttempts = 15;

    const pollAccept = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(pollAccept);
            awaitingChallenge.value = false;
            console.log('[PVP] Challenge expired — no response from target');
            return;
        }

        try {
            const res = await axios.get(`/api/combat/challenge/${challengeId}/status`);
            const status = res.data.status;

            if (status === 'accepted') {
                clearInterval(pollAccept);
                awaitingChallenge.value = false;
                launchPvpGridBreach(targetPlayer, challengeId, 'challenger');
            } else if (status === 'declined') {
                clearInterval(pollAccept);
                awaitingChallenge.value = false;
                console.log(`[PVP] ${targetPlayer.handle} declined — they took the penalty`);
                // Challenger gets their stolen creds from the decline response which
                // fires on the target's client; poll /api/player/me to sync pocket.
                const meRes = await axios.get('/api/player/me');
                if (meRes.data?.player) {
                    player.value.pocketCreds = meRes.data.player.pocket_creds ?? player.value.pocketCreds;
                }
            } else if (status === 'expired' || status === 'not_found') {
                clearInterval(pollAccept);
                awaitingChallenge.value = false;
                console.log('[PVP] Challenge expired');
            }
            // status === 'pending' → keep polling
        } catch { /* silent — keep polling */ }
    }, 2_000);
}

// Called when the target accepts an incoming challenge
async function onAcceptChallenge() {
    const c = incomingChallenge.value;
    if (!c) return;

    const result = await acceptChallenge(c.id);
    if (!result) return;

    launchPvpGridBreach(c.challenger, c.id, 'target');
}

async function onDeclineChallenge() {
    const c = incomingChallenge.value;
    if (!c) return;

    const result = await declineChallenge(c.id);

    // Apply decline penalty to local state
    if (result?.penalty) {
        player.value.pocketCreds = result.penalty.pocket_after ?? player.value.pocketCreds;

        // Apply SS damage — sync from server via player/me rather than guessing
        const meRes = await axios.get('/api/player/me').catch(() => null);
        if (meRes?.data?.rig) {
            player.value.currentSS = meRes.data.rig.current_ss ?? player.value.currentSS;
        }

        if (result.critical_failure) {
            player.value.bountyLevel      = 0;
            player.value.bountyMultiplier = 1.0;
            player.value.isOpenSeason     = false;
            hackCount.value               = 0;
            pings.value                   = [];
            if (result.critical_failure.respawn_canvas_id) {
                currentNodeId.value = result.critical_failure.respawn_canvas_id;
            }
            criticalFailure.value = { repairCost: result.critical_failure.repair_cost ?? 0 };
        }
    }
}

// Launch GridBreach in PvP mode.
// ICE level = opponent's effective firewall (from accept response) or a
// conservative fallback based on their bounty level if stats aren't available.
function launchPvpGridBreach(opponent, challengeId, role) {
    const iceLevel = opponent.effective_firewall
        ?? Math.max(3, (opponent.bounty_level ?? 0) + 3);
    activePvpCombat.value = {
        opponent,
        challengeId,
        role,               // 'challenger' | 'target'
        nodeCanvasId: currentNodeId.value,
        iceLevel,
    };
    console.log(`[PVP] GridBreach launching vs ${opponent.handle} — ICE ${iceLevel}`);
}

// Called when the PvP GridBreach timer expires and the player dismisses the overlay.
// score = number of full sequences completed this duel.
async function onPvpComplete({ score = 0 }) {
    const combat = activePvpCombat.value;
    if (!combat) return;
    activePvpCombat.value = null;

    const result = await submitCombatResult(combat.challengeId, score, combat.nodeCanvasId);
    if (!result) return;

    if (result.resolved) {
        // Both scores were in — server resolved immediately
        applyPvpResult(result, combat.opponent.handle);
        return;
    }

    // First to submit: poll until the opponent's score arrives (max 60 s)
    console.log('[PVP] Score submitted — waiting for opponent result...');
    let attempts = 0;
    const maxAttempts = 30;   // 30 × 2s = 60s
    const poll = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(poll);
            console.warn('[PVP] Opponent result timeout — duel unresolved');
            return;
        }
        const r = await pollCombatResult(combat.challengeId);
        if (r?.resolved) {
            clearInterval(poll);
            applyPvpResult(r, combat.opponent.handle);
        }
    }, 2_000);
}

// Apply a resolved PvP result to local state and show the result overlay.
function applyPvpResult(result, opponentHandle) {
    const won = result.winner_id === player.value.id;

    if (won) {
        const stolen = result.loot?.stolen ?? 0;
        if (stolen > 0) {
            player.value.pocketCreds = (player.value.pocketCreds ?? 0) + stolen;
        }
        player.value.bountyLevel      = result.winner?.bounty_level      ?? player.value.bountyLevel;
        player.value.bountyMultiplier = result.winner?.bounty_multiplier ?? player.value.bountyMultiplier;
        player.value.isOpenSeason     = result.winner?.is_open_season    ?? player.value.isOpenSeason;
    } else {
        // Lost — pocket zeroed, limping flag set.
        // Bounty/run state is preserved on a survivable loss — it only resets
        // at the CyberDoc (extract) or on Critical System Failure (SS = 0).
        player.value.pocketCreds  = 0;
        player.value.isLimping    = result.loser?.is_limping ?? true;

        const cf = result.loser?.critical_failure ?? null;
        if (cf) {
            // SS hit 0 during combat — bounty and run are wiped server-side.
            player.value.bountyLevel      = 0;
            player.value.bountyMultiplier = 1.0;
            player.value.isOpenSeason     = false;
            hackCount.value               = 0;
            pings.value                   = [];

            // Teleport to spawn node
            if (cf.respawn_canvas_id) {
                currentNodeId.value = cf.respawn_canvas_id;
            }

            // Show the critical failure overlay — blocks movement until dismissed
            criticalFailure.value = { repairCost: cf.repair_cost ?? 0 };
        }
    }

    pvpResult.value = {
        won,
        opponentHandle,
        loot:        result.loot ?? null,
        winnerScore: result.winner_score ?? null,
        loserScore:  result.loser_score  ?? null,
    };

    setTimeout(() => { pvpResult.value = null; }, 6_000);
    console.log(`[PVP] ${won ? 'WON' : 'LOST'} vs ${opponentHandle} | scores: ${result.winner_score}–${result.loser_score}`);
}

// ── Consumable use — wraps gameState useConsumable to sync activeEffects ──────
// Software consumables return active_effects from the server. Game.vue owns the
// activeEffects ref so the movement handler can decrement them correctly. This
// wrapper ensures they're merged in immediately when a consumable is used from
// the SPLICE browser rather than waiting for the next auth hydration.
async function onUseConsumable(consumableId) {
    const result = await useConsumable(consumableId);
    if (result?.type === 'software' && result.active_effects) {
        Object.assign(activeEffects.value, result.active_effects);
    }
    return result;
}

// ── Provide game state to SPLICE browser pages ────────────────────────────────
provide('gameState', { player, rig, commands, inventory, bounties, bankCreds, currentNodeId, useConsumable: onUseConsumable });

// ── Lifecycle ─────────────────────────────────────────────────────────────────
function onKeyDown(e) {
    if (e.key === 'Escape') cancelCrashTarget();
}

onMounted(async () => {
    window.addEventListener('keydown', onKeyDown);

    // Drive the replenish countdown in nodeResources — 1 s resolution is enough.
    _nowTick = setInterval(() => { _now.value = Date.now(); }, 1000);

    // Initialise canvas position from geometry while auth + DB load
    const geometryStartId = mapCanvasRef.value?.startNodeId;
    if (geometryStartId) currentNodeId.value = geometryStartId;

    // Step 1 — resolve session, hydrate all game state from API
    const loggedIn = await login();
    if (!loggedIn) {
        console.error('[BOOT] Session lookup failed — check auth middleware');
    } else {
        // Single call seeds player + rig from the /api/player/me response
        hydrateFromAuth(authPlayer.value, authRig.value);

        // Restore active command effects from server state (survives page reload).
        // The server decrements these on every position() call so they're fresh.
        const serverEffects = authPlayer.value?.active_effects ?? {};
        Object.assign(activeEffects.value, serverEffects);

        // Seed the session hack counter from the server's run counter.
        // Without this, bountyTicker math goes negative on any mid-session reload
        // because hackCount starts at 0 while bountyLevel is already > 0.
        hackCount.value = player.value.nodesHackedThisRun;

        // bounty_level in the DB mirrors nodes_hacked_this_run (raw count), but
        // the HUD uses bountyLevel as a 0–5 star level. Convert once on boot.
        player.value.bountyLevel = BOUNTY_THRESHOLDS.find(
            t => hackCount.value >= t.hacks
        )?.level ?? 0;

        // Fetch commands and inventory in parallel
        await Promise.all([fetchCommands(), fetchInventory()]);

        // Start presence heartbeat — stamps last_seen_at every 45s and fires
        // sendBeacon on beforeunload so ghost players are removed immediately
        // when the tab closes rather than waiting for the stale window to expire.
        startHeartbeat();

        // Queue background music — plays automatically on first user click
        // (browser autoplay policy requires a user gesture before audio starts).
        startAudio();

        console.log(`[BOOT] Auth OK — playing as ${player.value.handle} (${playerId.value})`);
    }

    // Step 2 — start bounty board polling (30 s interval)
    // Players appear on the board the moment they hit Star 1 (10 hacks).
    startBountyPolling(30_000);

    // Start polling for incoming PvP challenges (2s interval)
    startPendingPoll(2_000);


    // Step 3 — fetch all 228 nodes (bearer token is now set by login above)
    // Position rules (in priority order):
    //   1. player.currentNodeCanvasId — the node the player was on when they last
    //      moved (persisted server-side via POST /api/player/position). Restores
    //      exact position on reload so uplink cannot be gamed by refreshing.
    //   2. getSpawnNode() — falls back to a random spawn node only on true first
    //      login (no saved position in the DB yet).
    // If the DB call fails entirely, the player stays on the canvas geometry default.
    await fetchAll();

    const savedCanvasId = player.value.currentNodeCanvasId;
    const spawnCanvasId = getSpawnNode();
    const startCanvasId = savedCanvasId ?? spawnCanvasId;

    if (startCanvasId) {
        mapCanvasRef.value?.setPlayerNode(startCanvasId);
        currentNodeId.value = startCanvasId;
        if (savedCanvasId) {
            console.log('[BOOT] Position restored at', savedCanvasId);
        } else {
            console.log('[SPAWN] Player placed at', spawnCanvasId);
        }
    } else {
        console.warn('[SPAWN] No spawn nodes found — using canvas default');
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    ws.disconnect();
    stopBountyPolling();
    stopPendingPoll();
    stopHeartbeat();
    stopAudio();
    clearInterval(_nowTick);
});
</script>

<style scoped>
/* ── Welcome modal ───────────────────────────────────────────────────────────── */
.welcome-overlay {
    position: absolute;
    inset: 0;
    z-index: 60;
    background: rgba(0, 0, 0, 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-modal {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 32px 36px;
    background: #06060e;
    border: 1px solid rgba(0, 255, 255, 0.2);
    box-shadow: 0 0 0 1px rgba(0,255,255,0.05), 0 24px 60px rgba(0,0,0,0.8);
    max-width: 440px;
    width: 100%;
    font-family: 'JetBrains Mono', monospace;
}

.welcome-tag {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.2em;
}

.welcome-title {
    font-size: 20px;
    color: #00FFFF;
    letter-spacing: 0.12em;
    text-shadow: 0 0 20px rgba(0,255,255,0.3);
}

.welcome-body {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.45);
    letter-spacing: 0.04em;
    line-height: 2;
}

.welcome-note {
    display: block;
    margin-top: 8px;
    color: rgba(0, 255, 136, 0.5);
    font-size: 9px;
}

.welcome-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 8px;
}

.welcome-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}

.welcome-btn--primary {
    padding: 10px 20px;
    background: rgba(0, 255, 136, 0.08);
    border: 1px solid rgba(0, 255, 136, 0.4);
    color: #00FF88;
}
.welcome-btn--primary:hover {
    background: rgba(0, 255, 136, 0.15);
    border-color: #00FF88;
}

.welcome-btn--ghost {
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.2);
    padding: 10px 4px;
}
.welcome-btn--ghost:hover { color: rgba(255,255,255,0.45); }

.welcome-fade-enter-active,
.welcome-fade-leave-active { transition: opacity 0.3s ease; }
.welcome-fade-enter-from,
.welcome-fade-leave-to     { opacity: 0; }

/* ── Map row ─────────────────────────────────────────────────────────────────── */
.map-row {
    display: flex;
    flex: 1;
    min-height: 0;
    transition: opacity 0.5s ease 0.3s;
}

.map-stage {
    position: relative;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

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

/* ── Crash mine targeting banner ─────────────────────────────────────────────── */
.crash-targeting-banner {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 16px;
    background: rgba(8, 4, 12, 0.9);
    border: 1px solid rgba(255, 69, 180, 0.5);
    font-family: 'JetBrains Mono', monospace;
    z-index: 20;
    box-shadow: 0 0 20px rgba(255, 69, 180, 0.15);
}

.ct-icon {
    font-size: 11px;
    color: rgba(255, 69, 180, 0.9);
    animation: ct-pulse 1s ease-in-out infinite;
}

.ct-text {
    font-size: 9px;
    color: rgba(255, 69, 180, 0.85);
    letter-spacing: 0.12em;
    white-space: nowrap;
}

.ct-cancel {
    background: transparent;
    border: 1px solid rgba(255, 69, 180, 0.3);
    color: rgba(255, 69, 180, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 3px 8px;
    cursor: pointer;
    transition: all 0.12s;
}
.ct-cancel:hover {
    border-color: rgba(255, 69, 180, 0.7);
    color: rgba(255, 69, 180, 1);
    background: rgba(255, 69, 180, 0.06);
}

@keyframes ct-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

.targeting-fade-enter-active,
.targeting-fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.targeting-fade-enter-from,
.targeting-fade-leave-to     { opacity: 0; transform: translateX(-50%) translateY(-6px); }

/* ── ICE alert banner ─────────────────────────────────────────────────────── */
.ice-alert {
    position: absolute;
    top: 40px;           /* sits just below the 32px HUD bar */
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 20px;
    background: rgba(4, 4, 10, 0.92);
    border: 1px solid rgba(255, 51, 51, 0.55);
    border-radius: 2px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.14em;
    color: #FF3333;
    white-space: nowrap;
    pointer-events: none;
    box-shadow: 0 0 20px rgba(255, 51, 51, 0.2);
    animation: ice-alert-flicker 0.12s steps(1) 3;
}

.ice-alert-icon {
    font-size: 11px;
    animation: crit-pulse 0.6s ease-in-out infinite;
}

.ice-alert-fade-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.ice-alert-fade-leave-active { transition: opacity 0.6s ease 3s; } /* hold for 3s then fade */
.ice-alert-fade-enter-from   { opacity: 0; transform: translateX(-50%) translateY(-6px); }
.ice-alert-fade-leave-to     { opacity: 0; }

@keyframes ice-alert-flicker {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

@keyframes crit-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

/* Boot fade */
.boot-fade-leave-active { transition: opacity 0.6s ease; }
.boot-fade-leave-to     { opacity: 0; }

/* Browser fade */
.browser-fade-enter-active,
.browser-fade-leave-active { transition: opacity 0.18s ease; }
.browser-fade-enter-from,
.browser-fade-leave-to     { opacity: 0; }

/* Street Doc / overlays */
.fade-instant-enter-active,
.fade-instant-leave-active { transition: opacity 0.18s ease; }
.fade-instant-enter-from,
.fade-instant-leave-to     { opacity: 0; }

/* Grid-Breach */
.breach-fade-enter-active,
.breach-fade-leave-active { transition: opacity 0.22s ease; }
.breach-fade-enter-from,
.breach-fade-leave-to     { opacity: 0; }

/* PvP overlays */
.pvp-fade-enter-active,
.pvp-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.pvp-fade-enter-from,
.pvp-fade-leave-to     { opacity: 0; transform: translateY(8px); }

/* ── Awaiting challenge ───────────────────────────────────────────────────── */
.pvp-await {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 6, 10, 0.72);
    z-index: 40;
    pointer-events: all;
}
.pvp-await-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 28px 40px;
    background: rgba(4, 8, 14, 0.96);
    border: 1px solid rgba(255, 179, 0, 0.3);
    font-family: 'JetBrains Mono', monospace;
}
.pvp-await-icon  { font-size: 20px; color: #FFB300; animation: crit-pulse 0.8s ease-in-out infinite; }
.pvp-await-title { font-size: 11px; color: #FFB300; letter-spacing: 0.18em; }
.pvp-await-sub   { font-size: 8px; color: rgba(255,179,0,0.45); letter-spacing: 0.1em; }
.pvp-await-dots  { display: flex; gap: 6px; margin-top: 4px; }
.pvp-await-dots span {
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(255, 179, 0, 0.5);
    animation: dot-bounce 1.2s ease-in-out infinite;
}
.pvp-await-dots span:nth-child(2) { animation-delay: 0.2s; }
.pvp-await-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes dot-bounce {
    0%, 100% { transform: translateY(0); opacity: 0.4; }
    50%       { transform: translateY(-5px); opacity: 1; }
}

/* ── Incoming challenge ───────────────────────────────────────────────────── */
.pvp-challenge {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 6, 10, 0.78);
    z-index: 40;
    pointer-events: all;
}
.pvp-challenge-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 28px 44px;
    background: rgba(4, 8, 14, 0.97);
    border: 1px solid rgba(255, 51, 51, 0.45);
    font-family: 'JetBrains Mono', monospace;
    box-shadow: 0 0 30px rgba(255, 51, 51, 0.15);
    animation: pvp-challenge-pulse 1.4s ease-in-out infinite;
}
@keyframes pvp-challenge-pulse {
    0%, 100% { border-color: rgba(255, 51, 51, 0.45); }
    50%       { border-color: rgba(255, 51, 51, 0.9); }
}
.pvp-challenge-icon   { font-size: 22px; color: #FF3333; animation: crit-pulse 0.6s ease-in-out infinite; }
.pvp-challenge-title  { font-size: 12px; color: #FF3333; letter-spacing: 0.2em; }
.pvp-challenge-handle { display: flex; align-items: center; gap: 8px; margin: 4px 0; }
.pvp-ch-label  { font-size: 7px; color: rgba(255,51,51,.4); letter-spacing: .12em; }
.pvp-ch-name   { font-size: 13px; color: #FF69B4; letter-spacing: 0.1em; }
.pvp-challenge-sub    { font-size: 8px; color: rgba(255,51,51,0.45); letter-spacing: 0.08em; text-align: center; }
.pvp-challenge-actions { display: flex; gap: 12px; margin-top: 8px; }

.pvp-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.14em;
    padding: 8px 18px;
    cursor: pointer;
    background: transparent;
    transition: all 0.14s;
}
.pvp-btn--accept {
    border: 1px solid rgba(0, 255, 136, 0.45);
    color: rgba(0, 255, 136, 0.8);
}
.pvp-btn--accept:hover {
    background: rgba(0, 255, 136, 0.08);
    border-color: rgba(0, 255, 136, 0.85);
    color: #00FF88;
}
.pvp-btn--decline {
    border: 1px solid rgba(255, 51, 51, 0.35);
    color: rgba(255, 51, 51, 0.6);
}
.pvp-btn--decline:hover {
    background: rgba(255, 51, 51, 0.07);
    border-color: rgba(255, 51, 51, 0.75);
    color: #FF3333;
}

/* ── Post-combat result ───────────────────────────────────────────────────── */
.pvp-result {
    position: absolute;
    bottom: 60px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 40;
    pointer-events: none;
}
.pvp-result-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 16px 36px;
    font-family: 'JetBrains Mono', monospace;
    white-space: nowrap;
}
.pvp-result--won .pvp-result-inner {
    background: rgba(4, 12, 8, 0.96);
    border: 1px solid rgba(0, 255, 136, 0.5);
    box-shadow: 0 0 24px rgba(0, 255, 136, 0.15);
}
.pvp-result--lost .pvp-result-inner {
    background: rgba(12, 4, 4, 0.96);
    border: 1px solid rgba(255, 51, 51, 0.45);
    box-shadow: 0 0 24px rgba(255, 51, 51, 0.12);
}
.pvp-result-badge {
    font-size: 10px;
    letter-spacing: 0.18em;
}
.pvp-result--won  .pvp-result-badge { color: #00FF88; }
.pvp-result--lost .pvp-result-badge { color: #FF3333; }
.pvp-result-vs     { font-size: 8px; color: rgba(0,255,255,0.35); letter-spacing: 0.1em; }
.pvp-result-handle { color: #FF69B4; }
.pvp-result-loot   { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.pvp-loot-label    { font-size: 7px; color: rgba(0,255,136,0.4); letter-spacing: 0.12em; }
.pvp-loot-val      { font-size: 11px; color: #00FF88; letter-spacing: 0.08em; }
.pvp-result-lost-msg { font-size: 8px; color: rgba(255,51,51,0.55); letter-spacing: 0.1em; margin-top: 2px; }

/* ── Critical System Failure overlay ─────────────────────────────────────── */
.critical-failure-overlay {
    position: absolute;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.88);
    backdrop-filter: blur(4px);
}
.cf-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 32px 40px;
    border: 1px solid rgba(255, 51, 51, 0.5);
    background: rgba(8, 0, 0, 0.95);
    box-shadow: 0 0 40px rgba(255, 51, 51, 0.25), inset 0 0 30px rgba(255, 51, 51, 0.04);
    font-family: 'JetBrains Mono', monospace;
    text-align: center;
    max-width: 380px;
    animation: cf-flicker 6s steps(1) infinite;
}
@keyframes cf-flicker {
    0%, 95%, 100% { opacity: 1; }
    96%            { opacity: 0.85; }
    97%            { opacity: 1; }
    98%            { opacity: 0.9; }
}
.cf-icon  { font-size: 28px; color: #FF3333; text-shadow: 0 0 20px rgba(255,51,51,0.8); animation: cf-pulse 1s ease-in-out infinite; }
.cf-title { font-size: 13px; color: #FF3333; letter-spacing: 0.2em; text-shadow: 0 0 12px rgba(255,51,51,0.6); }
.cf-sub   { font-size: 8px;  color: rgba(255,51,51,0.55); letter-spacing: 0.12em; }
@keyframes cf-pulse { 0%,100%{text-shadow:0 0 12px rgba(255,51,51,0.6)} 50%{text-shadow:0 0 24px rgba(255,51,51,1)} }
.cf-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
    border-top: 1px solid rgba(255,51,51,0.15);
    border-bottom: 1px solid rgba(255,51,51,0.15);
    padding: 10px 0;
    margin: 4px 0;
}
.cf-row        { display: flex; justify-content: space-between; align-items: center; }
.cf-key        { font-size: 7px; color: rgba(0,255,255,0.3); letter-spacing: 0.12em; }
.cf-val        { font-size: 9px; letter-spacing: 0.08em; }
.cf-val--wiped { color: #FF3333; }
.cf-val--cost  { color: #FFB300; }
.cf-warn       { font-size: 7px; color: rgba(255,179,0,0.6); letter-spacing: 0.08em; line-height: 1.7; max-width: 280px; }
.cf-btn {
    margin-top: 4px;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.45);
    color: rgba(255,51,51,0.8);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    padding: 8px 24px;
    cursor: pointer;
    transition: all 0.15s;
}
.cf-btn:hover {
    background: rgba(255,51,51,0.1);
    border-color: #FF3333;
    color: #FF3333;
    box-shadow: 0 0 12px rgba(255,51,51,0.2);
}
</style>
