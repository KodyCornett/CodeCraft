<template>
    <GameScreen>
        <!-- Persona selection — first-login gate, shown before boot sequence -->
        <PersonaSelect v-if="needsPersonaSelect" @done="onPersonaDone" />

        <!-- Watcher signal interrupt — renders above everything when active -->
        <WatcherSignal :signal="activeSignal" :player="player" @complete="onSignalComplete" />

        <!-- Doc notifications — identified HUD alerts for arc unlocks and referrals -->
        <DocNotification :queue="docNotifQueue" @dismiss="dismissDocNotif" />

        <!-- World tone — opening cinematic, first login only (after persona selection) -->
        <WorldTone v-if="showWorldTone" @done="onWorldToneDone" />

        <!-- Boot sequence — shown before map loads -->
        <Transition name="boot-fade">
            <BootSequence v-if="!booted && !needsPersonaSelect && !showWorldTone" @done="booted = true" />
        </Transition>

        <!-- Map row: map canvas + persistent side panel side by side -->
        <div class="map-row" :class="{ 'map-hidden': !booted }">

            <div class="map-stage">
                <!-- Hex node map — dev accounts only -->
                <HexMapCanvas
                    v-if="player.isDev"
                    ref="mapCanvasRef"
                    :nodes="nodes"
                    :pings="pings"
                    :traps="myTraps"
                    :quest-markers="questMarkers"
                    :current-node-id="currentNodeId"
                    :player-uplink="player.uplink"
                    :player-ss="player.currentSS"
                    :target-mode="!!trapTargetMode"
                    @node-clicked="handleNodeClicked"
                    @player-moved="handlePlayerMoved"
                    @move-blocked="onMoveBlocked"
                />

                <!-- Restricted map placeholder — shown to non-dev players -->
                <div v-else class="map-restricted">
                    <div class="map-restricted-inner">
                        <div class="mr-glyph">◈</div>
                        <div class="mr-title">SPLICE FREQUENCY</div>
                        <div class="mr-sub">SECTOR MAP — ACCESS RESTRICTED</div>
                        <div class="mr-rule" />
                        <div class="mr-body">
                            The city is still coming online.<br />
                            Runner access will be granted soon.
                        </div>
                        <div class="mr-blink">▌ AWAITING CLEARANCE</div>
                    </div>
                </div>

                <!-- HUD overlay -->
                <HUD :player="player" :rig="rig" :current-node="currentNode" :bounty-ticker="bountyTicker" :flash="hudFlash" />

                <!-- Active objective tracker — top-left, collapses to header bar -->
                <ObjectiveTracker v-if="tutorial.allComplete.value" :objective="activeObjective" />

                <!-- Boot notification — shown after Watcher reboot sequence -->
                <Transition name="ice-alert-fade">
                    <div v-if="bootNotification" class="boot-notification">
                        [ SPLICE ] — session resumed — new data logged
                    </div>
                </Transition>

                <!-- Mission update toast — fires when active stage changes -->
                <Transition name="ice-alert-fade">
                    <div v-if="missionToast" class="mission-toast">
                        <span class="mission-toast-icon">◈</span>
                        OBJECTIVE — {{ missionToast }}
                    </div>
                </Transition>

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

                <!-- Trap targeting mode — persists until player picks a node or cancels -->
                <Transition name="targeting-fade">
                    <div v-if="trapTargetMode" class="crash-targeting-banner">
                        <span class="ct-icon">⚠</span>
                        <span class="ct-text">{{ trapTargetMode.cmd.name.toUpperCase() }} — SELECT AN ADJACENT NODE TO PLANT</span>
                        <button class="ct-cancel" @click="cancelTrapTarget">[ CANCEL ]</button>
                    </div>
                </Transition>

                <!-- Command hit notification — shown when a trap fires on this player (victim) -->
                <CommandHitNotification
                    v-if="trapHitNotification"
                    :command-name="trapHitNotification.commandName"
                    :effect="trapHitNotification.effect"
                    @done="trapHitNotification = null"
                />

                <!-- Trap fired notification — shown when this player's trap hits a victim (placer) -->
                <TrapFiredNotification
                    v-if="trapFiredNotification"
                    :command-name="trapFiredNotification.commandName"
                    :victim-handle="trapFiredNotification.victimHandle"
                    @done="trapFiredNotification = null"
                />

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

                <!-- Packet Hijack terminal (PvP) — replaces GridBreach for PvP combat -->
                <Transition name="breach-fade">
                    <PacketHijack
                        v-if="activePacketHijack"
                        :match-id="ph.matchId"
                        :role="ph.role"
                        :phase="ph.phase"
                        :command-history="ph.commandHistory"
                        :suspects="ph.suspects"
                        :octet-clue="ph.octetClue"
                        :active-suspect-count="ph.activeSuspectCount"
                        :board-ready="ph.boardReady"
                        :port-pool="ph.portPool"
                        :chain-confirmed="ph.chainConfirmed"
                        :trace-attempts-left="ph.traceAttemptsLeft"
                        :credential-state="ph.credentialState"
                        :awaiting-auth="ph.awaitingAuth"
                        :board-scanned="ph.boardScanned"
                        :bank-access="ph.bankAccess"
                        :bank-balance="ph.bankBalance"
                        :transferring="ph.transferring"
                        :current-path="ph.currentPath"
                        :directory-entries="ph.directoryEntries"
                        :explored-paths="ph.exploredPaths"
                        :target-ip="ph.targetIp"
                        :is-locked="ph.isLocked"
                        :lock-countdown="ph.lockCountdown"
                        :defender-alert-active="ph.defenderAlertActive"
                        :match-result="ph.matchResult"
                        :is-complete="ph.isComplete"
                        :busy="ph.busy"
                        :hack-commands="hackCommands"
                        :used-rig-commands="ph.usedRigCommands"
                        @submit-command="ph.submitCommand"
                        @submit-auth="ph.submitAuth"
                        @submit-transfer="ph.submitTransfer"
                        @use-rig-command="ph.submitRigCommand"
                        @match-complete="onPacketHijackMatchComplete"
                    />
                </Transition>

                <!-- Quest minigame — launched from QuestLog via useQuestMinigame -->
                <Transition name="breach-fade">
                    <QuestMinigame
                        v-if="activeMinigame"
                        :skin="activeMinigame.skin"
                        @complete="onQuestMinigameComplete"
                        @fail="onQuestMinigameFail"
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
                                <span></span><span></span><span></span>
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
                            <div class="pvp-challenge-sub">You are being challenged to Packet Hijack combat</div>
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
                :dialogue-splice-url="currentNodeDialogueUrl"
                @open-store="onOpenStore"
                @open-bank="onLaunch(SPLICE.BANK)"
                @open-dialogue="onLaunch(currentNodeDialogueUrl)"
                @reset-cooldowns="onResetCooldowns"
                @use-command="onUseCommand"
                @hack-player="onHackPlayer"
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
                        Your rig is online. Your uplink is live.<br />
                        Complete orientation before running anything hot.<br />
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

        <!-- Inactivity auto-logout warning -->
        <IdleWarning
            :visible="idle.warningActive.value"
            :countdown="idle.countdown.value"
            :seconds-left="idle.secondsLeft.value"
            @cancel="idle.cancel()"
        />

        <!-- UI orientation tour — Teleports to body, pointer-safe -->
        <UiTour :player="player" />
    </GameScreen>
</template>

<script setup>
import { ref, computed, provide, watch, onMounted, onUnmounted } from 'vue';

// ── Layout & shared UI ────────────────────────────────────────────────────────
import GameScreen    from '@/components/layout/GameScreen.vue';
import HUD           from '@/components/layout/HUD.vue';
import NavBar        from '@/components/layout/NavBar.vue';
import SidePanel     from '@/components/layout/SidePanel.vue';

// ── Map ───────────────────────────────────────────────────────────────────────
import HexMapCanvas  from '@/components/map/HexMapCanvas.vue';

// ── Overlays ──────────────────────────────────────────────────────────────────
import BootSequence             from '@/components/shared/BootSequence.vue';
import PersonaSelect            from '@/components/shared/PersonaSelect.vue';
import WorldTone                from '@/components/shared/WorldTone.vue';
import WatcherSignal            from '@/components/shared/WatcherSignal.vue';
import DocNotification          from '@/components/shared/DocNotification.vue';
import OpenSeasonNotification   from '@/components/shared/OpenSeasonNotification.vue';
import CommandHitNotification  from '@/components/shared/CommandHitNotification.vue';
import IdleWarning              from '@/components/shared/IdleWarning.vue';
import ObjectiveTracker         from '@/components/shared/ObjectiveTracker.vue';
import TrapFiredNotification   from '@/components/shared/TrapFiredNotification.vue';
import UiTour                  from '@/components/shared/UiTour.vue';
import InGameBrowser from '@/components/browser/InGameBrowser.vue';
import GridBreach    from '@/components/minigame/GridBreach.vue';
import PacketHijack  from '@/components/minigame/PacketHijack.vue';
import QuestMinigame from '@/components/minigame/QuestMinigame.vue';

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
import { usePacketHijack }   from '@/composables/usePacketHijack.js';
import { useGameState }      from '@/composables/useGameState.js';
import { useHeartbeat }      from '@/composables/useHeartbeat.js';
import { useAudio }          from '@/composables/useAudio.js';
import { useTutorial }       from '@/composables/useTutorial.js';
import { useUiTour }         from '@/composables/useUiTour.js';
import { useRigDamage }      from '@/composables/useRigDamage.js';
import { useCyberDoc }       from '@/composables/useCyberDoc.js';
import { useTrapSystem }     from '@/composables/useTrapSystem.js';
import { usePingSystem }     from '@/composables/usePingSystem.js';
import { useWatcher }            from '@/composables/useWatcher.js';
import { useQuestLog }           from '@/composables/useQuestLog.js';
import { useQuestMinigame }      from '@/composables/useQuestMinigame.js';
import { useDocNotifications }   from '@/composables/useDocNotifications.js';
import { useQuestArchive }       from '@/composables/useQuestArchive.js';
import { useInactivityTimer }    from '@/composables/useInactivityTimer.js';
import { useActiveObjective }    from '@/composables/useActiveObjective.js';
import { docColorByName }        from '@/constants/docColors.js';
import { SPLICE }            from '@/components/browser/SpliceRouter.js';

// ── Auth ──────────────────────────────────────────────────────────────────────
const { playerId, player: authPlayer, rig: authRig, login } = useAuth();

// ── Game state — all reactive refs, seeded from API after login ───────────────
const {
    player, rig, commands, inventory, bounties: gameStateBounties,
    hydrateFromAuth, fetchCommands, fetchInventory, upgradeCommand, useConsumable,
    resyncPlayer, activateCommand,
} = useGameState();

// ── Map data (API) ────────────────────────────────────────────────────────────
const { loading: mapLoading, fetchAll, getSpawnNode, updateNodeState, updateNodeResources, getByCanvasId, getNodesNear, nodes } = useMapData();

// ── Deplete — fires after every successful hack ───────────────────────────────
const { deplete } = useDepletion(playerId);

// ── Bounty board — live leaderboard (players with ★1+ appear here) ───────────
const { entries: bounties, startPolling: startBountyPolling, stopPolling: stopBountyPolling } = useBountyBoard(playerId);

// ── Position persistence — updates current_node_id on every move ─────────────
const { updatePosition } = usePosition(playerId);

// ── Heartbeat — keeps last_seen_at fresh; sendBeacon cleans up on tab close ───
const { startHeartbeat, stopHeartbeat } = useHeartbeat();

// ── Audio — shuffled background music, starts on first user interaction ───────
const { startAudio, stopAudio, cutAudio, resumeAudio } = useAudio();

// ── Combat — challenge handshake + result submission ─────────────────────────
const {
    incomingChallenge,
    challenge:            sendChallenge,
    startPendingPoll,
    stopPendingPoll,
    accept:               acceptChallenge,
    decline:              declineChallenge,
    submitResult:         submitCombatResult,
    pollResult:           pollCombatResult,
    pollChallengeStatus,
} = useCombat(playerId);

// ── Rig damage — SS sync after failed hacks ───────────────────────────────────
const { applyDamage } = useRigDamage();

// ── CyberDoc — banking and NPC interactions ───────────────────────────────────
const cyberDoc = useCyberDoc();

// ── Trap system — mine/decoy placement + server-persisted trap list ───────────
const { myTraps, placeTrap, placeDecoy, fetchMyTraps } = useTrapSystem();

// PvP combat state
const activePvpCombat   = ref(null);   // reserved — not used for PvP (Packet Hijack replaced GridBreach)
const pvpResult         = ref(null);   // { won, loot } shown after combat
const awaitingChallenge = ref(false);  // true while waiting for target to accept

// Packet Hijack — terminal PvP mini-game
const ph                  = usePacketHijack(playerId);
const activePacketHijack  = ref(false);  // true while the PH terminal overlay is shown

// Quest minigame — launched from QuestLog via useQuestMinigame composable
const { activeMinigame, setCurrentNode, clear: clearMinigame } = useQuestMinigame();

async function onQuestMinigameComplete() {
    if (!activeMinigame.value) return;
    const { stageId } = activeMinigame.value;
    clearMinigame();
    try {
        await completeQuestStage(stageId);
        await Promise.all([fetchQuestLog(), fetchArchive()]);
        processDocEvents(archiveEvents.value);
    } catch (e) {
        console.warn('[QUEST MINIGAME] stage completion failed:', e?.message);
    }
}

function onQuestMinigameFail() {
    clearMinigame();
}

// Equipped hack- and map-context commands passed into the PH terminal as the rig loadout strip.
// Ghost Protocol and Signal Noise are 'map' context but carry Packet Hijack effects, so both
// contexts are included here.
const hackCommands = computed(() =>
    (commands.value ?? []).filter(c => c.is_active && (c.context === 'hack' || c.context === 'map'))
);

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

// ── Trap state ────────────────────────────────────────────────────────────────
// trapTargetMode:        set to { cmd, match } while the player is picking a target node.
// myTraps:              owned by useTrapSystem (see composable instantiation above).
// trapHitNotification:  set when position() returns trap_triggered — drives the victim hit popup.
// trapFiredNotification: set when a WS trap.triggered event arrives — drives the placer popup.
const trapTargetMode        = ref(null);
const trapHitNotification   = ref(null);
const trapFiredNotification = ref(null);

// Boot notification — shown after Watcher reboot sequence completes
const bootNotification    = ref(false);
let   _bootNotifTimer     = null;

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

// ── ICE Ping system — see usePingSystem.js ─────────────────────────────────────────────
// Instantiated below after useMapInteraction (needs pings ref from it).
// firePing / fireFalsePing / clearFalsePings / onMoveTick / reset exposed there.

// OS Exploit trap — magnitude of the OS reduction applied to this player so it
// can be reversed exactly when the effect expires.
let _osExploitReduction = 0;

// Buffer Overflow trap — id of the command randomly locked by the effect so it
// can be re-enabled when the effect expires.
let _bufferOverflowCmdId = null;

// ── Player movement handler ────────────────────────────────────────────────────
//
// Ping frequency by bounty level:
//   ★0–1  — no movement pings (ICE hasn't locked on yet)
//   ★2–3  — ping every other move
//   ★4–5  — ping every move
//
function handlePlayerMoved(event) {
    onPlayerMoved(event);   // update currentNode, uplink, district

    // Persist position to backend so other players can detect same-node presence.
    // The response carries remaining_uplink and trap_triggered — sync both.
    updatePosition(event.nodeId, event.district ?? player.value.district, (data) => {
        if (data.remaining_uplink != null) {
            player.value.uplink = data.remaining_uplink;
        }
        // ── Trap hit ──────────────────────────────────────────────────────────
        // Server consumed a trap on this node and returned its effect. Merge any
        // timed effects into activeEffects and show the Splice popup notification.
        if (data.trap_triggered) {
            const { command_name, effect } = data.trap_triggered;
            // Merge server-applied timed effects (os_exploit, buffer_overflow, rootkit)
            if (data.active_effects) {
                Object.assign(activeEffects.value, data.active_effects);
            }
            // Sync SS if the server applied damage (Packet Flood)
            if (data.current_ss != null) {
                player.value.currentSS = data.current_ss;
            }
            // OS Exploit — reduce OS immediately; restore on effect expiry
            if (effect.os_reduction) {
                _osExploitReduction = effect.os_reduction;
                rig.value.os = Math.max(0, (rig.value.os ?? 0) - _osExploitReduction);
            }
            // Buffer Overflow — randomly lock one equipped, ready command
            if (command_name === 'Buffer Overflow') {
                const ready = commands.value.filter(c => c.equipped && !c.cooldown);
                if (ready.length > 0) {
                    const target = ready[Math.floor(Math.random() * ready.length)];
                    _bufferOverflowCmdId = target.id;
                    target.cooldown  = true;
                    target.movesLeft = effect.moves ?? 2;
                }
            }
            // Show the hit notification
            trapHitNotification.value = { commandName: command_name, effect };
        }
        // Refresh our own trap list (a trap may have ticked down server-side)
        fetchMyTraps();
    }, event.uplinkCost ?? 1);

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
            // OS Exploit — restore the OS stat that was reduced on trap hit
            if (slug === 'os_exploit') {
                rig.value.os = (rig.value.os ?? 0) + _osExploitReduction;
                _osExploitReduction = 0;
            }
            // Buffer Overflow — re-enable the command that was randomly locked
            if (slug === 'buffer_overflow' && _bufferOverflowCmdId) {
                const locked = commands.value.find(c => c.id === _bufferOverflowCmdId);
                if (locked) { locked.cooldown = false; locked.movesLeft = 0; }
                _bufferOverflowCmdId = null;
            }
        }
    }

    // Tick down false-ping TTL (Signal Noise / Decoy) — delegated to usePingSystem.
    onMoveTick();

    // Pings are hack-triggered only — no movement pings.
}

// ── Node click — intercepts trap targeting mode before normal selection ───────
function handleNodeClicked(event) {
    if (trapTargetMode.value) {
        if (!event.isAdjacent) {
            clearTimeout(_flashTimer);
            hudFlash.value = `OUT OF RANGE — select an adjacent node to plant ${trapTargetMode.value.cmd.name}`;
            _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
            return;
        }
        const { cmd, match } = trapTargetMode.value;
        const node           = event.node;
        const ttl            = cmd.duration?.moves ?? 5;

        // POST to server — trap is now persisted and will fire for any other player
        placeTrap(node.id, cmd.id).then(res => {
            if (res) {
                fetchMyTraps();  // Refresh map mine markers
            } else {
                // Revert cooldown so the player can try again
                match.cooldown  = false;
                match.movesLeft = 0;
            }
        });

        match.cooldown  = true;
        match.movesLeft = ttl;
        trapTargetMode.value = null;
        return;
    }
    onNodeClicked(event);
}

function cancelTrapTarget() {
    if (!trapTargetMode.value) return;
    // Revert the premature cooldown set before the switch — command stays ready.
    trapTargetMode.value.match.cooldown = false;
    trapTargetMode.value = null;
}

// fetchMyTraps is provided by useTrapSystem (see composable instantiation above)

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
    // Laravel may serialize datetime without a timezone offset ("Y-m-d H:i:s").
    // JS treats a space-separated datetime without a tz as local time, which shifts
    // the countdown by the user's UTC offset. Normalize to an unambiguous UTC string.
    const ts = String(lastHackedAt).replace(' ', 'T').replace(/([+-]\d{2}:\d{2}|Z)$/, '') + 'Z';
    const readyAt = new Date(ts).getTime() + REPLENISH_MS;
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

// ── Ping system — instantiated here so pings ref (from useMapInteraction) is ready ──
const {
    firePing, fireFalsePing, clearFalsePings, onMoveTick, reset: resetPings,
} = usePingSystem({ pings, player, rig, currentNode, selectedNode, getByCanvasId, bounties });

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
const { traces: nodeTraces, refreshNow: refreshTraces, storeTrace } = useNodeTraces(selectedCanvasId, playerId);

// ── Opponent bounty pings — handled by usePingSystem (watches bounties internally) ──


// ── Browser state ─────────────────────────────────────────────────────────────
const { activeBrowserUrl, onLaunch: _onLaunch, onCloseBrowser } = useBrowserState();

// Intercept TERMINAL launches — redirect to the tutorial page until it's complete.
function onLaunch(url) {
    if (url === SPLICE.TERMINAL && !tutorial.allComplete.value && !tutorial.tutorialComplete.value) {
        return _onLaunch(SPLICE.TUTORIAL);
    }
    _onLaunch(url);
}

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

function onResetCooldowns() {
    commands.value.forEach(c => { c.cooldown = false; });
}

// ── Tutorial ──────────────────────────────────────────────────────────────────
const tutorial = useTutorial();

// ── UI orientation tour ───────────────────────────────────────────────────────
const tour = useUiTour();

// ── Inactivity auto-logout ────────────────────────────────────────────────────
const idle = useInactivityTimer();

// Provide tutorial state to all SPLICE page components via inject('tutorial').
// GhostProtocol0 reads it to render quest status.
// GridBreachGuide calls markStepDone('read_manual') on mount.
provide('tutorial', tutorial);

// Clear badge + fire URL-based tutorial step triggers when SPLICE navigates.
watch(activeBrowserUrl, (url) => {
    if (!url) return;

    if (url.startsWith(SPLICE.TERMINAL) || url.startsWith(SPLICE.TUTORIAL)) {
        tutorial.clearBadge();
    }

    // q2_rig — player opened the Rig read-out
    if (url.startsWith(SPLICE.RIG)) {
        tutorial.markStepDone('open_rig');
    }

    // q3_stat_guide — player visited the Stat Reference
    if (url.startsWith(SPLICE.STAT_GUIDE)) {
        tutorial.markStepDone('read_stat_guide');
    }

    // q4_cyberdoc (step 2) — player opened any CyberDoc store page
    if (url.startsWith('splice://cyberdoc')) {
        tutorial.markStepDone('open_cyberdoc_store');
    }
});

// Launch the CORTEX_PATCH update sequence whenever the tutorial is complete
// (or skipped) but the player hasn't yet seen the full sequence + Watcher intrusion.
// Using [booted, needsCortexInstall] covers three cases:
//   1. Normal completion this session — tutorialComplete flips to true after boot.
//   2. Skip this session — same: tutorialComplete set during skip(), booted already true.
//   3. Reload before sequence finished — booted transitions to true; needsCortexInstall
//      was true from hydration. Fires naturally without any special hydrate logic.
// markCortexInstall() (called in _postSignalNav below) sets cortexInstallSeen = true,
// which drops needsCortexInstall to false and prevents the watcher re-firing.
watch([booted, tutorial.needsCortexInstall], ([isBooted, needsInstall]) => {
    console.log('%c[TUTORIAL] cortex-install watcher — booted:', 'color:#00FFC8;font-weight:bold', isBooted, 'needsInstall:', needsInstall);
    if (isBooted && needsInstall) {
        console.log('%c[TUTORIAL] Launching CORTEX_PATCH SPLICE page', 'color:#00FFC8;font-weight:bold');
        // Refresh quest log — tutorial/complete just initialised Knuckle's entry arc.
        // Without this, questDocs is stale and the dialogue button never appears.
        fetchQuestLog();
        onLaunch(SPLICE.CORTEX_PATCH);
    }
});

// UI tour trigger:
//   Case 1 — cortex sequence just completed this session: needsCortexInstall flips false.
//   Case 2 — returning player (cortex already done, tour not yet seen): fires on boot.
// tour.start() is a no-op when localStorage already has cc_ui_tour_seen.
watch(tutorial.needsCortexInstall, (needs, wasNeeded) => {
    if (wasNeeded && !needs && booted.value) {
        // Small delay so the boot notification settles before the tour appears.
        setTimeout(() => tour.start(), 1800);
    }
});

watch(booted, (isBooted) => {
    if (isBooted && !tutorial.needsCortexInstall.value) {
        tour.start();
    }
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
    // Keep QuestLog in sync so the INITIATE HACK button gates correctly
    setCurrentNode(newVal ?? null);
});

// Close the SPLICE browser when a minigame is launched from QuestLog
watch(activeMinigame, (val) => {
    if (val) activeBrowserUrl.value = null;
});

// onTutorial — kept for GameMenu backward compat; opens TERMINAL page
function onTutorial() {
    onLaunch(SPLICE.TERMINAL);
}

// ── Quest log + map markers + doc notifications ───────────────────────────────
const { docs: questDocs, fetchQuestLog, completeStage: completeQuestStage } = useQuestLog();

// ── Active objective tracker ──────────────────────────────────────────────────
const { objective: activeObjective } = useActiveObjective(questDocs);

// Mission toast — fires for 3.5 s whenever the active stage changes.
// Follows the same pattern as bountyAlert (auto-dismiss via timer).
const missionToast = ref(null);
let   _missionToastTimer = null;

watch(() => activeObjective.value?.stageId, (next, prev) => {
    if (!next || next === prev) return;
    if (_missionToastTimer) clearTimeout(_missionToastTimer);
    missionToast.value      = activeObjective.value?.stageTitle ?? 'MISSION UPDATED';
    _missionToastTimer = setTimeout(() => { missionToast.value = null; }, 3500);
});
const { events: archiveEvents, fetchArchive } = useQuestArchive();
const { queue: docNotifQueue, processEvents: processDocEvents, dismiss: dismissDocNotif } = useDocNotifications();

// Derive active objective markers from quest state.
// One marker per active stage that has a node_canvas_id, using the doc's accent colour.
const questMarkers = computed(() => {
    const markers = [];
    for (const doc of questDocs.value) {
        if (!doc.met) continue;
        for (const arc of doc.arcs) {
            if (arc.status !== 'active') continue;
            for (const stage of arc.stages) {
                if (stage.status === 'active' && stage.node_canvas_id) {
                    markers.push({
                        canvasId: stage.node_canvas_id,
                        color:    docColorByName(doc.name),
                        docName:  doc.name,
                    });
                }
            }
        }
    }
    return markers;
});

// Derive the dialogue SPLICE URL for the currently selected cyberdoc node.
// Returns a URL string when:
//   - The selected node is a cyberdoc with a known npcHandle
//   - The matching quest doc has at least one active stage with dialogue data
// Returns null otherwise — NodeInfoBlock hides the button when null.
const NPC_DIALOGUE_URL = {
    KNUCKLE: SPLICE.DIALOGUE_KNUCKLE,
    PATCH:   SPLICE.DIALOGUE_PATCH,
    VEIL:    SPLICE.DIALOGUE_VEIL,
    AXIOM:   SPLICE.DIALOGUE_AXIOM,
    FLOAT:   SPLICE.DIALOGUE_FLOAT,
};

const currentNodeDialogueUrl = computed(() => {
    const node = selectedNode.value;
    if (!node || node.type !== 'cyberdoc' || !node.npcHandle) return null;
    const url = NPC_DIALOGUE_URL[node.npcHandle.toUpperCase()];
    if (!url) return null;

    // Only show the button when there's actually dialogue to read
    const doc = questDocs.value.find(d => d.district === node.district);
    if (!doc) {
        console.log(`%c[DIALOGUE] ${node.npcHandle} — no quest doc found for district "${node.district}"`, 'color:#FF6B35');
        return null;
    }
    const hasDialogue = doc.arcs?.some(arc =>
        arc.stages?.some(s => s.status === 'active' && s.dialogue?.length > 0)
    );
    console.log(`%c[DIALOGUE] ${node.npcHandle} — met=${doc.met} hasActiveDialogue=${hasDialogue}`, 'color:#00FFC8');
    return hasDialogue ? url : null;
});

// ── Watcher signal system ─────────────────────────────────────────────────────
const {
    activeSignal, hasUnread: watcherHasUnread,
    fetchUnread: fetchWatcherUnread,
    markAllRead: watcherMarkAllRead,
    triggerSignal,
    onSignalComplete: _onSignalComplete,
} = useWatcher();

// Post-signal navigation — set before triggering a story signal so the
// handler knows where to go when the sequence finishes.
const _postSignalNav = ref(null);

function onSignalComplete() {
    _onSignalComplete();
    if (_postSignalNav.value) {
        const nav = _postSignalNav.value;
        _postSignalNav.value = null;
        nav();
    }
}

// Provide markAllRead to WatcherChannel.vue via inject
provide('watcherMarkAllRead', watcherMarkAllRead);

// Provide quest log state to SPLICE dialogue pages
provide('questLog', { docs: questDocs, completeStage: completeQuestStage, fetchQuestLog });

// Pool of nodes near BA-hub — random pick so players can't camp a fixed respawn point
const _WATCHER_RESPAWN_POOL = ['B6', 'E7', 'C10', 'G11', 'H8', 'E5'];

// ── Prologue Watcher transition map ───────────────────────────────────────────
// Each entry fires the intrusion cinematic when the player leaves that doc's
// hub node after completing the arc. The Watcher — not the doc — directs the
// player to the next contact.
const _WATCHER_TRANSITIONS = {
    knuckle: {
        leaveNode:  'BA-hub',
        signalId:   'watcher-veil-redirect',
        signalText: '[PROCESS: RESUMING]\n▓░▓░▓▓░░▓░▓░\n...Downtown...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...she sees what he cannot...\n*HIGH_FREQ_INTERFERENCE*\n...Veil...\n[KERNEL_PULSE: ACTIVE]\n...find...her...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    veil: {
        leaveNode:  'DT-hub',
        signalId:   'watcher-float-redirect',
        signalText: '[PROCESS: RESUMING]\n░▓░▓▓░▓░░▓▓░\n...Spokane Valley...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...old architecture...she knows it...\n*HIGH_FREQ_INTERFERENCE*\n...Float...\n[KERNEL_PULSE: ACTIVE]\n...the salvager...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    float: {
        leaveNode:  'SV-hub',
        signalId:   'watcher-axiom-redirect',
        signalText: '[PROCESS: RESUMING]\n▓▓░░▓░▓▓░░▓░\n...University District...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...they have been waiting...\n*HIGH_FREQ_INTERFERENCE*\n...Axiom...\n[KERNEL_PULSE: ACTIVE]\n...they already know...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    axiom: {
        leaveNode:  'UD-hub',
        signalId:   'watcher-patch-redirect',
        signalText: '[PROCESS: RESUMING]\n░░▓▓░▓░░▓▓░▓\n...North Spokane...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...under the grid...\n*HIGH_FREQ_INTERFERENCE*\n...Patch...\n[KERNEL_PULSE: ACTIVE]\n...they hear everything...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
};

// Holds the pending transition config — set when a doc's dialogue completes,
// cleared when the player leaves that doc's hub node.
const _pendingWatcherTransition = ref(null);

// Called by SystemUpdate.vue when the install sequence finishes.
// Cuts music, fires the Watcher intrusion cinematic, then on reboot:
//   → respawns player near BA-hub, resumes audio, shows boot notification, navigates to TERMINAL.
provide('onInstallComplete', () => {
    console.log('%c[TUTORIAL] onInstallComplete fired — cutting audio, queueing Watcher intrusion', 'color:#FF6B35;font-weight:bold');

    // Silence music instantly — the intrusion breaks everything
    cutAudio();

    _postSignalNav.value = () => {
        // Respawn to a random node near BA-hub (anti-camping pool)
        const pool     = _WATCHER_RESPAWN_POOL;
        const respawnId = pool[Math.floor(Math.random() * pool.length)];
        console.log('%c[TUTORIAL] Watcher reboot complete — respawning to', 'color:#FF6B35;font-weight:bold', respawnId);
        currentNodeId.value = respawnId;

        // Resume music as if nothing happened
        resumeAudio();

        // Mark the full sequence (CORTEX_PATCH + Watcher intrusion) as seen.
        // This drops needsCortexInstall to false, preventing the sequence from
        // replaying on subsequent reloads.
        tutorial.markCortexInstall();

        // Understated system notification — just enough to direct the player
        if (_bootNotifTimer) clearTimeout(_bootNotifTimer);
        bootNotification.value = true;
        _bootNotifTimer = setTimeout(() => { bootNotification.value = false; }, 6000);

        // Open the mission terminal
        onLaunch(SPLICE.TERMINAL);
    };

    triggerSignal({
        id:          'watcher-post-cortex-install',
        signal_text: '[UNKNOWN_PROCESS: INJECTING]\n▓░▓▓░░▓░░▓▓░▓░░▓\n...Knuckles...\n*HIGH_FREQ_INTERFERENCE*\n[SYS_INTEGRITY: FAILING]\n[CONTAINMENT: ░░░░░░░░░░] BREACHED\n...not...stable...\n*SIGNAL DECAY — SOURCE UNKNOWN*\n...speak...with...him...\n[KERNEL_PANIC]\n[MEMORY: CORRUPTING]\n...KNUCKLES...\n*EAR-SPLITTING RING*',
    });
});

// Called by DocDialoguePage when a doc's dialogue stage closes.
// Arms the pending Watcher transition for that doc — fires when player leaves the hub.
provide('onDocDialogueComplete', (docHandle) => {
    const transition = _WATCHER_TRANSITIONS[docHandle];
    if (transition) {
        _pendingWatcherTransition.value = transition;
    }
});

// Fire the Watcher intrusion when player leaves a hub node with a pending transition.
// Player is NOT moved on reboot — they stay wherever they walked to.
watch(currentNodeId, (newNode, oldNode) => {
    const t = _pendingWatcherTransition.value;
    if (!t || oldNode !== t.leaveNode || newNode === t.leaveNode) return;

    _pendingWatcherTransition.value = null;
    cutAudio();

    _postSignalNav.value = () => {
        resumeAudio();
        if (_bootNotifTimer) clearTimeout(_bootNotifTimer);
        bootNotification.value = true;
        _bootNotifTimer = setTimeout(() => { bootNotification.value = false; }, 6000);
        onLaunch(SPLICE.TERMINAL);
    };

    triggerSignal({ id: t.signalId, signal_text: t.signalText });
});

// ── Persona selection — shown on first login before boot sequence ─────────────
// true once auth has loaded and player has no persona set yet
const needsPersonaSelect = ref(false);

function onPersonaDone(persona) {
    player.value.persona      = persona.name;
    player.value.persona_desc = persona.desc;
    needsPersonaSelect.value  = false;
}

// ── World tone — opening cinematic, fires once after persona selection ─────────
const showWorldTone = ref(false);

function onWorldToneDone() {
    showWorldTone.value = false;
}

// First-login modal
const showWelcomeModal = computed(() =>
    booted.value && !tutorial.tutorialSeen.value && !tutorial.tutorialSkipped.value
);

function onWelcomeStart() {
    tutorial.markSeen();
    // Send new players to the tutorial page first; quest log is available after
    onLaunch(SPLICE.TUTORIAL);
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
    // Mirror into nodesHackedThisRun so the STATUS page stays live.
    hackCount.value += 1;
    player.value.nodesHackedThisRun = hackCount.value;
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
            if (patch.player?.pocket_creds !== undefined) {
                player.value.pocketCreds = patch.player.pocket_creds;
            }
            if (patch.currentUplink != null) {
                player.value.uplink = patch.currentUplink;
            }
            // Sync server-authoritative bounty state so STATUS page stays accurate
            // (client-side checkBountyEscalation handles UI alerts; server is truth)
            if (patch.player?.bounty_level !== undefined) {
                player.value.bountyLevel = patch.player.bounty_level;
            }
            if (patch.player?.bounty_multiplier !== undefined) {
                player.value.bountyMultiplier = patch.player.bounty_multiplier;
            }
            if (patch.player?.is_open_season !== undefined) {
                player.value.isOpenSeason = patch.player.is_open_season;
            }
            if (patch.player?.nodes_hacked_this_run !== undefined) {
                hackCount.value = patch.player.nodes_hacked_this_run;
                player.value.nodesHackedThisRun = patch.player.nodes_hacked_this_run;
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
        const res = await applyDamage(nodeCanvasId, 'pve');
        if (res) {
            player.value.currentSS = res.current_ss;
            player.value.maxSS     = res.max_ss;

            if (res.event === 'critical_failure') {
                const cf = res.critical_failure ?? {};

                // Wipe pocket + bounty state
                player.value.pocketCreds         = 0;
                player.value.bountyLevel         = 0;
                player.value.bountyMultiplier    = 1.0;
                player.value.isOpenSeason        = false;
                player.value.isLimping           = false;
                hackCount.value                  = 0;
                player.value.nodesHackedThisRun  = 0;
                player.value.pvpWinsThisRun      = 0;

                // Teleport to spawn node
                if (cf.respawn_canvas_id) {
                    currentNodeId.value = cf.respawn_canvas_id;
                }

                // Show critical failure overlay — blocks action until dismissed
                criticalFailure.value = { repairCost: cf.repair_cost ?? 0 };
            }
        }
    }

    // Leave a data fragment even on failure — the attempt is detectable.
    const nodeId = failedNode?.id;
    if (nodeId && playerId.value) {
        await storeTrace(nodeId, playerId.value);
        refreshTraces();
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

    const result = await cyberDoc.bank(pid, canvasId ?? null);
    if (!result) return null;

    // Move pocket into wallet
    const banked = result.pocket_banked ?? 0;
    if (banked > 0) {
        player.value.creds = (player.value.creds ?? 0) + banked;
    }
    player.value.pocketCreds = 0;

    // Uplink is restored by visit() when the storefront opens — not on bank.

    // Reset bounty run state
    player.value.bountyLevel      = result.player?.bounty_level      ?? 0;
    player.value.bountyMultiplier = result.player?.bounty_multiplier  ?? 1.0;
    player.value.isOpenSeason     = result.player?.is_open_season     ?? false;

    // Reset session counters
    hackCount.value                  = 0;
    player.value.nodesHackedThisRun  = 0;
    player.value.pvpWinsThisRun      = 0;
    bountyAlert.value = null;
    resetPings();

    // Clear command cooldowns and active effects
    commands.value.forEach(cmd => { cmd.cooldown = false; cmd.movesLeft = 0; });
    activeEffects.value = {};

    return result;
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

    // RootKit trap effect — all commands locked while the effect is active
    if ((activeEffects.value.rootkit ?? 0) > 0) {
        hudFlash.value = 'ROOTKIT ACTIVE — command systems locked';
        clearTimeout(_flashTimer);
        _flashTimer = setTimeout(() => { hudFlash.value = ''; }, 3_000);
        return;
    }

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
        await activateCommand(cmd.id);
        // Mirror locally for ping suppression and UI countdown
        activeEffects.value[slug] = moveDuration;
        match.movesLeft           = moveDuration;
    }

    // ── Dispatch per-command map effects ──────────────────────────────────────
    switch (cmd.name) {

        case 'Crash':
        case 'Packet Flood':
        case 'OS Exploit':
        case 'Buffer Overflow':
        case 'RootKit':
            // Node-targeted traps — enter targeting mode. Cooldown is applied
            // once the player picks a node and the server confirms placement.
            match.cooldown  = false;
            match.movesLeft = 0;
            trapTargetMode.value = { cmd, match };
            console.log(`[TRAP] ${cmd.name} targeting mode active — awaiting node selection.`);
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
            // Plants a false hack trace on the target node (server-persisted so other
            // players see it) and fires a local false ping for the caster's own view.
            const decoyTarget = (selectedNode.value && selectedNode.value.canvasId !== currentNodeId.value)
                ? selectedNode.value
                : getNodesNear(currentNode.value?.x ?? 0, currentNode.value?.y ?? 0, { minPx: 100, maxPx: 400, count: 1 })[0];

            if (decoyTarget) {
                // Persist to server — other players inspecting that node will see
                // a fake trace from a spoofed handle.
                placeDecoy(decoyTarget.canvasId, cmd.id);

                clearFalsePings();   // replace any existing false ping with the new decoy
                fireFalsePing(decoyTarget);
                console.log(`[DECOY] False trace + ping planted at ${decoyTarget.canvasId}.`);
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
            // Blocks all incoming PvP challenges for moveDuration moves.
            // Enforced server-side: active_effects['blackout'] > 0 checked by
            // CombatChallengeController::challenge() — returns 422 to the challenger.
            console.log(`[BLACKOUT] Incoming challenges blocked for ${moveDuration} moves.`);
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
    if (!playerId.value || targetPlayer.id === playerId.value) return;

    awaitingChallenge.value = true;
    const result = await sendChallenge(targetPlayer.id, currentNodeId.value);
    if (!result) {
        awaitingChallenge.value = false;
        return;
    }

    console.log(`[PVP] Challenge sent to ${targetPlayer.handle} — waiting for response`);

    // Poll until the target accepts, declines, or the 30s TTL expires.
    // PacketHijackStarted WS event fires on accept — ph.init() handles the launch.
    const challengeId   = result.challenge_id;
    const { status }    = await pollChallengeStatus(challengeId);
    awaitingChallenge.value = false;

    if (status === 'declined') {
        // Sync pocket creds — server deducted the decline penalty from the target,
        // and may have credited the challenger; re-fetch to get the accurate value.
        const data = await resyncPlayer();
        if (data?.player) {
            player.value.pocketCreds = data.player.pocket_creds ?? player.value.pocketCreds;
        }
    }
}

// Called when the target accepts an incoming challenge.
// acceptChallenge() triggers server-side PacketHijackMatch creation and broadcasts
// PacketHijackStarted to both players. The .packet-hijack.started Echo listener
// registered on mount calls ph.init() and sets activePacketHijack = true.
async function onAcceptChallenge() {
    const c = incomingChallenge.value;
    if (!c) return;

    // Guard: we must be the target, not the challenger.
    // If target_id doesn't match our player ID the event was misrouted or stale.
    if (c.target_id && c.target_id !== playerId.value) {
        console.warn('[COMBAT] Discarding stale/misrouted challenge — not the target');
        incomingChallenge.value = null;
        return;
    }

    const result = await acceptChallenge(c.id);
    if (!result) return;
    // WS event handles the terminal launch — nothing else needed here.
}

async function onDeclineChallenge() {
    const c = incomingChallenge.value;
    if (!c) return;

    if (c.target_id && c.target_id !== playerId.value) {
        incomingChallenge.value = null;
        return;
    }

    const result = await declineChallenge(c.id);

    // Apply decline penalty to local state
    if (result?.penalty) {
        player.value.pocketCreds = result.penalty.pocket_after ?? player.value.pocketCreds;

        // Apply SS damage — sync from server rather than guessing the formula
        const meData = await resyncPlayer();
        if (meData?.rig) {
            player.value.currentSS = meData.rig.current_ss ?? player.value.currentSS;
        }

        if (result.critical_failure) {
            player.value.bountyLevel         = 0;
            player.value.bountyMultiplier    = 1.0;
            player.value.isOpenSeason        = false;
            hackCount.value                  = 0;
            player.value.nodesHackedThisRun  = 0;
            player.value.pvpWinsThisRun      = 0;
            resetPings();
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

// Called when the player activates a command inside PvP GridBreach.
// Applies cooldown to the command in the shared commands ref so it's correctly
// marked on-cooldown after the duel (same as map-mode command use).
function onPvpCommandUsed({ commandId }) {
    const cmd = commands.value.find(c => c.id === commandId);
    if (cmd) {
        cmd.cooldown  = true;
        cmd.movesLeft = 0;
    }
    console.log(`[PVP CMD] Cooldown applied server-side for command ${commandId}`);
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
        // Use the server-authoritative pocket balance rather than adding stolen
        // locally — avoids compounding any drift in the local state.
        player.value.pocketCreds      = result.winner?.pocket_creds      ?? player.value.pocketCreds;
        player.value.bountyLevel      = result.winner?.bounty_level      ?? player.value.bountyLevel;
        player.value.bountyMultiplier = result.winner?.bounty_multiplier ?? player.value.bountyMultiplier;
        player.value.isOpenSeason     = result.winner?.is_open_season    ?? player.value.isOpenSeason;
        // PvP win increments the run counter server-side — mirror it locally.
        player.value.pvpWinsThisRun   = (player.value.pvpWinsThisRun ?? 0) + 1;
    } else {
        // Use the server-authoritative loser pocket:
        //   survivable loss → loser keeps (pocket_before − stolen), ICE seizes nothing
        //   elimination     → loser zeroed (handled inside critical_failure block below)
        player.value.pocketCreds  = result.loser?.pocket_creds ?? 0;
        player.value.isLimping    = result.loser?.is_limping ?? true;

        const cf = result.loser?.critical_failure ?? null;
        if (cf) {
            // SS hit 0 during combat — bounty and run are wiped server-side.
            player.value.bountyLevel         = 0;
            player.value.bountyMultiplier    = 1.0;
            player.value.isOpenSeason        = false;
            hackCount.value                  = 0;
            player.value.nodesHackedThisRun  = 0;
            player.value.pvpWinsThisRun      = 0;
            resetPings();

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

// Called when PacketHijack.vue emits 'match-complete' (player clicked DISCONNECT).
// Mirrors applyPvpResult() — syncs local economy state from the WS payload.
function onPacketHijackMatchComplete(result) {
    activePacketHijack.value = false;
    ph.destroy();

    if (result.isWinner) {
        player.value.pocketCreds = (player.value.pocketCreds ?? 0) + (result.credsStolen ?? 0);
    } else {
        player.value.pocketCreds = 0;
        player.value.isLimping   = true;
    }

    // Sync full state from the server to pick up bounty escalation, SS changes, and limp flag
    resyncPlayer().then(data => {
        if (data?.player) {
            player.value.bountyLevel      = data.player.bounty_level      ?? player.value.bountyLevel;
            player.value.bountyMultiplier = data.player.bounty_multiplier ?? player.value.bountyMultiplier;
            player.value.isOpenSeason     = data.player.is_open_season    ?? player.value.isOpenSeason;
            player.value.isLimping        = data.player.is_limping        ?? player.value.isLimping;
        }
        if (data?.rig) {
            player.value.currentSS = data.rig.current_ss ?? player.value.currentSS;
        }
    });
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
    if (e.key === 'Escape') cancelTrapTarget();
}

onMounted(async () => {
    window.addEventListener('keydown', onKeyDown);
    idle.start();

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

        // First-login gate — show persona selection then world tone before boot
        if (!player.value.persona) {
            needsPersonaSelect.value = true;
            // Wait until the player confirms their persona
            await new Promise(resolve => {
                const stop = watch(needsPersonaSelect, val => {
                    if (!val) { stop(); resolve(); }
                });
            });

            // World tone cinematic — plays once on first login only
            showWorldTone.value = true;
            await new Promise(resolve => {
                const stop = watch(showWorldTone, val => {
                    if (!val) { stop(); resolve(); }
                });
            });
        }

        // Seed the map's currentNodeId from the server's persisted position so
        // the player can move freely on reload regardless of which node type they
        // are on. Without this, currentNodeId starts null and the server's
        // "first move must be a spawn node" guard blocks anyone at a CyberDoc
        // (e.g. after a critical-failure teleport).
        const savedCanvasId = authPlayer.value?.current_node_canvas_id ?? null;
        if (savedCanvasId) {
            currentNodeId.value = savedCanvasId;
            currentNode.value   = { canvasId: savedCanvasId, x: 0, y: 0 };
        }

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

        // Fetch commands, inventory, active traps, Watcher signals, quest log, archive,
        // and tutorial state in parallel
        await Promise.all([
            fetchCommands(),
            fetchInventory(),
            fetchMyTraps(),
            fetchWatcherUnread(),
            fetchQuestLog(),
            fetchArchive(),
            tutorial.hydrate(),
        ]);
        // Re-fetch quest log now that tutorial.hydrate() has completed.
        // Race condition: if tutorial was already complete, hydrate() posted
        // tutorial/complete above (initialising Knuckle's entry arc), but
        // fetchQuestLog() in the parallel batch resolved before that POST —
        // so questDocs would be missing the arc. This second fetch is cheap
        // and guarantees questDocs reflects current server state on every boot.
        await fetchQuestLog();
        // Process archive events for doc notifications (arc unlocks, referrals)
        processDocEvents(archiveEvents.value);

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

    // Listen for Packet Hijack match start on the player's private channel.
    // PacketHijackStarted is broadcast by the server when accept() is called.
    // Both players (challenger + defender) receive this event simultaneously.
    if (playerId.value && window.Echo) {
        window.Echo.private(`player.${playerId.value}`)
            .listen('.packet-hijack.started', (data) => {
                ph.init(data.match_id, data.role);
                activePacketHijack.value  = true;
                awaitingChallenge.value   = false;
                incomingChallenge.value   = null;
            })
            .listen('.trap.triggered', (data) => {
                // Our trap just consumed a victim — show the placer notification
                // and refresh the trap markers so the map no longer shows the mine.
                trapFiredNotification.value = {
                    commandName:  data.command_name,
                    victimHandle: data.victim_handle,
                };
                fetchMyTraps();
            });
    }


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
        onNodeClicked({ node: { id: startCanvasId, x: 0, y: 0 }, isAdjacent: false });
        if (savedCanvasId) {
            console.log('[BOOT] Position restored at', savedCanvasId);
        } else {
            console.log('[SPAWN] Player placed at', spawnCanvasId);
            // Persist spawn placement so the server knows current_node_id before
            // the first move — without this every move fails the spawn-only gate.
            updatePosition(spawnCanvasId, player.value.district);
        }
    } else {
        console.warn('[SPAWN] No spawn nodes found — using canvas default');
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    idle.destroy();
    ws.disconnect();
    stopBountyPolling();
    stopPendingPoll();
    stopHeartbeat();
    stopAudio();
    clearInterval(_nowTick);
    if (playerId.value && window.Echo) {
        window.Echo.private(`player.${playerId.value}`)
            .stopListening('.trap.triggered');
    }
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

/* ── Restricted map placeholder ──────────────────────────────────────────── */
.map-restricted {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #03040a;
    background-image:
        radial-gradient(ellipse at 50% 40%, rgba(0,255,255,0.04) 0%, transparent 70%);
}

.map-restricted-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
    text-align: center;
    user-select: none;
}

.mr-glyph {
    font-size: 32px;
    color: rgba(0, 255, 255, 0.25);
    text-shadow: 0 0 30px rgba(0, 255, 255, 0.15);
    margin-bottom: 4px;
}

.mr-title {
    font-size: 14px;
    letter-spacing: 0.3em;
    color: rgba(0, 255, 255, 0.5);
    text-shadow: 0 0 12px rgba(0, 255, 255, 0.2);
}

.mr-sub {
    font-size: 9px;
    letter-spacing: 0.2em;
    color: rgba(0, 255, 255, 0.2);
}

.mr-rule {
    width: 120px;
    height: 1px;
    background: rgba(0, 255, 255, 0.1);
    margin: 6px 0;
}

.mr-body {
    font-size: 11px;
    line-height: 1.8;
    color: rgba(255, 255, 255, 0.3);
    letter-spacing: 0.04em;
}

.mr-blink {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.2);
    letter-spacing: 0.15em;
    margin-top: 8px;
    animation: mr-cursor 1.2s step-end infinite;
}

@keyframes mr-cursor {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
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
    top: 46px;           /* sits just below the 38px HUD bar */
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

/* ── Mission update toast ─────────────────────────────────────────────────── */
/* Boot notification — understated system tone after Watcher reboot */
.boot-notification {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
    padding: 5px 18px;
    background: rgba(4, 6, 14, 0.88);
    border: 1px solid rgba(120, 180, 140, 0.25);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.16em;
    color: rgba(140, 210, 170, 0.72);
    white-space: nowrap;
    pointer-events: none;
}

.mission-toast {
    position: absolute;
    top: 88px;           /* sits below the ICE alert slot */
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
    padding: 6px 16px;
    background: rgba(4, 6, 14, 0.92);
    border: 1px solid rgba(0, 255, 200, 0.35);
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.14em;
    color: #00FFC8;
    white-space: nowrap;
    pointer-events: none;
    box-shadow: 0 0 16px rgba(0, 255, 200, 0.1);
    text-shadow: 0 0 8px rgba(0, 255, 200, 0.5);
}

.mission-toast-icon {
    font-size: 11px;
    animation: crit-pulse 0.8s ease-in-out infinite;
}

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
    border-color: rgba(255,51,51,0.5);
}
</style>
