<template>
    <GameScreen>
        <!-- Persona selection — first-login gate, shown before boot sequence -->
        <PersonaSelect v-if="needsPersonaSelect" @done="onPersonaDone" />

        <!-- Watcher signal cutscene, chapter title card, DOC arc-notifications and
             the World Tone intro are all part of the story/quest system, which
             is disconnected pending a new narrative design (see
             CONTRACTS_AND_OS_REWORK_PLAN.md). Components stay on disk; just
             unmounted here. -->

        <!-- Boot sequence — shown before map loads -->
        <Transition name="boot-fade">
            <BootSequence v-if="!booted && !needsPersonaSelect" @done="booted = true" />
        </Transition>

        <!-- Map row: map canvas + persistent side panel side by side -->
        <div class="map-row" :class="{ 'map-hidden': !booted }">

            <div class="map-stage">
                <!-- OS shell desktop — background layer, sits behind every
                     program window. Icons reuse the same launch mechanism
                     NavBar's taskbar buttons already use. -->
                <Desktop @launch="onLaunch" @open-map="openMapWindow" />

                <!-- Map program window — opens/closes/minimizes like any other
                     SPLICE program (see useWindowManager). Starts closed —
                     nothing renders until openMapWindow() runs (desktop icon,
                     Start Menu, or taskbar). v-if only tracks open/closed
                     (unmount on real close); v-show handles minimize so
                     HexMapCanvas stays mounted (state, ref) across a
                     minimize/restore instead of losing it. -->
                <OsWindow
                    v-if="windowManager.isOpen('map')"
                    v-show="!windowManager.isMinimized('map')"
                    title="NETWORK MAP"
                    icon="⬢"
                    accent="#00FF88"
                    max-width="none"
                    app-class="map-window"
                    maximizable
                    :z-index="windowManager.zIndexOf('map')"
                    :geometry="windowManager.geometryOf('map')"
                    :maximized="windowManager.isMaximized('map')"
                    @close="windowManager.close('map')"
                    @minimize="windowManager.minimize('map')"
                    @focus="windowManager.focus('map')"
                    @maximize="windowManager.toggleMaximize('map')"
                    @update:geometry="g => windowManager.setGeometry('map', g)"
                >
                    <!-- Hex node map -->
                    <HexMapCanvas
                        ref="mapCanvasRef"
                        :nodes="nodes"
                        :pings="pings"
                        :traps="myTraps"
                        :quest-markers="questMarkers"
                        :tracked-markers="trackedMarkers"
                        :current-node-id="currentNodeId"
                        :player-uplink="player.uplink"
                        :player-ss="player.currentSS"
                        :is-dev="player.isDev"
                        :target-mode="!!trapTargetMode"
                        @node-clicked="handleNodeClicked"
                        @player-moved="handlePlayerMoved"
                        @move-blocked="onMoveBlocked"
                    />

                    <!-- HUD overlay -->
                    <HUD :player="player" :rig="rig" :current-node="currentNode" :bounty-ticker="bountyTicker" :flash="hudFlash" />

                    <!-- Active objective tracker — tutorial-quest driven; disconnected
                         along with the rest of the quest system (see plan). -->
                </OsWindow>

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

                <!-- Codex find prompt — rolled by useHackFlow after a successful routine hack -->
                <CodexFindPopup
                    :visible="codexFindPending"
                    @play="onCodexFindPlay"
                    @pass="onCodexFindPass"
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

                <!-- In-game SPLICE browser — v-if tracks actually open/closed
                     (Browser manages its own windowManager registration
                     internally); v-show handles minimize so tabs/scroll
                     state survive a minimize/restore instead of resetting. -->
                <Transition name="browser-fade">
                    <InGameBrowser
                        v-if="activeBrowserUrl"
                        v-show="!windowManager.isMinimized('browser')"
                        :initial-url="activeBrowserUrl"
                        @close="onCloseBrowser"
                        @url-change="onBrowserUrlChange"
                    />
                </Transition>

                <!-- Node-hack mini-game (PvE) — generated per-hack by the minigame
                     pool generator (components/minigame/generator/). activeHack is
                     the generation spec {key, node, resource}; HackMinigame resolves
                     `key` against the pool and mounts whichever template got picked. -->
                <Transition name="breach-fade">
                    <HackMinigame
                        v-if="activeHack"
                        :spec="activeHack"
                        :player-cpu="rig.cpu"
                        :player-ram="rig.ram"
                        :player-os="rig.os"
                        :player-firewall="rig.firewall"
                        :player-max-uplink="player.maxUplink"
                        :bounty-multiplier="player.bountyMultiplier"
                        :paused="gbTour.active.value"
                        @complete="onHackComplete"
                        @failed="onHackFailed"
                        @abort="onHackAbort"
                    />
                </Transition>

                <!-- Grid-Breach first-time orientation tour — only while grid_breach
                     is the template the generator actually picked this hack. -->
                <GridBreachTour v-if="activeHack && activeHack.key === 'grid_breach'" />

                <!-- Bank Heist mini-game (PvE) — the 19 fixed bank/brokerage nodes only -->
                <Transition name="breach-fade">
                    <BankHeist
                        v-if="activeBankHeist"
                        :canvas-id="activeBankHeist.canvasId"
                        :bank-name="activeBankHeist.bankName"
                        :bank-ice="activeBankHeist.bankIce"
                        :bank-tier="activeBankHeist.bankTier"
                        :player-cpu="rig.cpu"
                        :player-ram="rig.ram"
                        :player-os="rig.os"
                        :bounty-multiplier="player.bountyMultiplier"
                        @complete="onBankHeistComplete"
                        @abort="onBankHeistAbort"
                    />
                </Transition>

                <!-- Composer dev-lab overlay — input model x win rule pairing test.
                     DEV ONLY, reached only via splice://dev/generator-lab. Never part
                     of the live hack flow; no reward endpoint is called from here. -->
                <Transition name="breach-fade">
                    <ComposedMinigame
                        v-if="activeComposedMinigame"
                        :key="JSON.stringify(activeComposedMinigame)"
                        :spec="activeComposedMinigame"
                        @complete="onComposedMinigameComplete"
                        @failed="onComposedMinigameFailed"
                        @abort="onComposedMinigameAbort"
                    />
                </Transition>

                <!-- SIT (Splice Interface Terminal) dev-lab overlay — typed ls/cd/cat
                     proof-of-concept. DEV ONLY, reached only via splice://dev/sit-lab.
                     Never part of the live hack flow; no reward endpoint is called
                     from here. -->
                <Transition name="breach-fade">
                    <SIT
                        v-if="activeSIT"
                        :scenario-key="activeSIT"
                        @complete="onSITComplete"
                        @failed="onSITFailed"
                        @abort="onSITAbort"
                    />
                </Transition>

                <!-- Signal Lock dev-lab overlay — candidate node-hack pool template,
                     NOT registered in generator/pool.js yet. DEV ONLY, reached only
                     via splice://dev/signal-lock-lab. Never part of the live hack
                     flow; no reward endpoint is called from here. -->
                <Transition name="breach-fade">
                    <SignalLock
                        v-if="activeSignalLock"
                        :node="{ ice: activeSignalLock.ice }"
                        :player-cpu="activeSignalLock.cpu"
                        :player-ram="activeSignalLock.ram"
                        :player-os="activeSignalLock.os"
                        resource="creds"
                        @complete="onSignalLockComplete"
                        @failed="onSignalLockFailed"
                        @abort="onSignalLockAbort"
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
                        :is-practice="ph.isPractice"
                        @match-complete="onPacketHijackMatchComplete"
                    />
                </Transition>

                <!-- Packet Hijack first-time orientation tour (practice match only) -->
                <PacketHijackTour v-if="activePacketHijack" />

                <!-- Quest minigame — launched from QuestLog via useQuestMinigame -->
                <Transition name="breach-fade">
                    <QuestMinigame
                        v-if="activeMinigame"
                        :skin="activeMinigame.skin"
                        @complete="onQuestMinigameComplete"
                        @fail="onQuestMinigameFail"
                    />
                </Transition>

                <!-- Awaiting challenge overlay -->
                <PvpAwaitOverlay :visible="awaitingChallenge" />

                <!-- Incoming challenge overlay -->
                <PvpChallengeOverlay
                    :challenge="incomingChallenge"
                    @accept="onAcceptChallenge"
                    @decline="onDeclineChallenge"
                />

                <!-- Critical System Failure overlay -->
                <CriticalFailureOverlay :failure="criticalFailure" @reboot="criticalFailure = null" />

                <!-- Post-combat result toast -->
                <PvpResultToast :result="pvpResult" />
            </div>

            <!-- Right panel — belongs to the Map program, not the desktop, so
                 it only shows while the Map window is open, matching the same
                 v-if/v-show split as the Map OsWindow above. -->
            <SidePanel
                v-if="windowManager.isOpen('map')"
                v-show="!windowManager.isMinimized('map')"
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
                :dialogue-splice-url="currentNodeDialogueUrl"
                @hack="onHackSelected"
                @open-store="onOpenStore"
                @open-bank="onLaunch(SPLICE.BANK)"
                @open-dialogue="onLaunch(currentNodeDialogueUrl)"
                @reset-cooldowns="onResetCooldowns"
                @use-command="onUseCommand"
                @hack-player="onHackPlayer"
                @bank-heist="onBankHeistSelected"
            />

        </div>

        <!-- NavBar sits below map-stage, inside GameScreen -->
        <NavBar
            :active-browser-url="activeBrowserUrl"
            :has-tutorial-badge="tutorial.hasBadge.value"
            :frequency-available="frequencyAvailable"
            :frequency-open="frequencyOpen"
            :frequency-color="frequencyAccent"
            @launch="onLaunch"
            @tutorial="onTutorial"
            @logout="onLogout"
            @toggle-frequency="toggleFrequency"
            @open-map="openMapWindow"
        />

        <!-- DOC hub live chat — opened via the FREQUENCY hotkey in NavBar -->
        <DocChatWindow
            :visible="frequencyOpen"
            :messages="frequencyMessages"
            :loading="frequencyLoading"
            :sending="frequencySending"
            :error="frequencyError"
            :current-player-id="player.id"
            :accent-color="frequencyAccent"
            :room-label="frequencyRoomLabel"
            @close="frequencyOpen = false"
            @send="sendFrequencyMessage"
        />

        <!-- DOC field comms (scripted quest check-ins) and the first-login
             welcome modal (tutorial-quest entry point) are both part of the
             disconnected story/quest system — see plan. -->

        <!-- Inactivity auto-logout warning -->
        <IdleWarning
            :visible="idle.warningActive.value"
            :countdown="idle.countdown.value"
            :seconds-left="idle.secondsLeft.value"
            @cancel="idle.cancel()"
        />

        <!-- UI orientation tour — teleports to body, pointer-safe -->
        <UiTour :player="player" />
    </GameScreen>
</template>

<script setup>
import { ref, computed, provide, watch, onMounted, onUnmounted } from 'vue';

// ── Layout & shared UI ────────────────────────────────────────────────────────
import GameScreen from '@/components/layout/GameScreen.vue';
import HUD        from '@/components/layout/HUD.vue';
import NavBar     from '@/components/layout/NavBar.vue';
import SidePanel  from '@/components/layout/SidePanel.vue';

// ── Map ───────────────────────────────────────────────────────────────────────
import HexMapCanvas from '@/components/map/HexMapCanvas.vue';

// ── OS shell ──────────────────────────────────────────────────────────────────
import OsWindow from '@/components/shared/OsWindow.vue';
import Desktop from '@/components/shared/Desktop.vue';
import { useWindowManager } from '@/composables/useWindowManager.js';

// ── Overlays ──────────────────────────────────────────────────────────────────
import BootSequence           from '@/components/shared/BootSequence.vue';
import PersonaSelect          from '@/components/shared/PersonaSelect.vue';
import OpenSeasonNotification from '@/components/shared/OpenSeasonNotification.vue';
import CodexFindPopup         from '@/components/shared/CodexFindPopup.vue';
import CommandHitNotification from '@/components/shared/CommandHitNotification.vue';
import IdleWarning            from '@/components/shared/IdleWarning.vue';
import TrapFiredNotification  from '@/components/shared/TrapFiredNotification.vue';
import UiTour                 from '@/components/shared/UiTour.vue';
import DocChatWindow          from '@/components/shared/DocChatWindow.vue';
// WorldTone, WatcherSignal, DocNotification, ObjectiveTracker, FieldCommsWindow,
// ChapterTitleCard, WelcomeModal — story/quest-system UI, disconnected (see
// CONTRACTS_AND_OS_REWORK_PLAN.md). Components remain on disk, unused here.
// ── Extracted overlay components ──────────────────────────────────────────────
import CriticalFailureOverlay from '@/components/shared/CriticalFailureOverlay.vue';
import PvpChallengeOverlay    from '@/components/shared/PvpChallengeOverlay.vue';
import PvpAwaitOverlay        from '@/components/shared/PvpAwaitOverlay.vue';
import PvpResultToast         from '@/components/shared/PvpResultToast.vue';

import InGameBrowser    from '@/components/browser/InGameBrowser.vue';
import HackMinigame     from '@/components/minigame/generator/HackMinigame.vue';
import GridBreachTour   from '@/components/minigame/GridBreachTour.vue';
import PacketHijack     from '@/components/minigame/PacketHijack.vue';
import PacketHijackTour from '@/components/minigame/PacketHijackTour.vue';
import QuestMinigame    from '@/components/minigame/QuestMinigame.vue';
import BankHeist        from '@/components/minigame/BankHeist.vue';
import ComposedMinigame from '@/components/minigame/composer/ComposedMinigame.vue';
import SIT from '@/components/minigame/sit/SIT.vue';
import SignalLock from '@/components/minigame/generator/templates/SignalLock.vue';

// ── Composables ───────────────────────────────────────────────────────────────
import { useMapData }          from '@/composables/useMapData.js';
import { useWebSocket }        from '@/composables/useWebSocket.js';
import { useMapInteraction }   from '@/composables/useMapInteraction.js';
import { useAuth }             from '@/composables/useAuth.js';
import { useDepletion }        from '@/composables/useDepletion.js';
import { useBountyBoard }      from '@/composables/useBountyBoard.js';
import { usePosition }         from '@/composables/usePosition.js';
import { useNodePresence }     from '@/composables/useNodePresence.js';
import { useNodeTraces }       from '@/composables/useNodeTraces.js';
import { useCombat }           from '@/composables/useCombat.js';
import { usePacketHijack }     from '@/composables/usePacketHijack.js';
import { useGameState }        from '@/composables/useGameState.js';
import { useHeartbeat }        from '@/composables/useHeartbeat.js';
import { useAudio }            from '@/composables/useAudio.js';
import { useTutorial }         from '@/composables/useTutorial.js';
import { useUiTour }           from '@/composables/useUiTour.js';
import { useGridBreachTour }   from '@/composables/useGridBreachTour.js';
import { usePacketHijackTour } from '@/composables/usePacketHijackTour.js';
import { useRigDamage }        from '@/composables/useRigDamage.js';
import { useCyberDoc }         from '@/composables/useCyberDoc.js';
import { useTrapSystem }       from '@/composables/useTrapSystem.js';
import { usePingSystem }       from '@/composables/usePingSystem.js';
import { useQuestLog }         from '@/composables/useQuestLog.js';
import { useQuestMinigame }    from '@/composables/useQuestMinigame.js';
import { useDevBankHeist }     from '@/composables/useDevBankHeist.js';
import { useDevComposer }      from '@/composables/useDevComposer.js';
import { useDevSIT }           from '@/composables/useDevSIT.js';
import { useDevSignalLock }    from '@/composables/useDevSignalLock.js';
import { useInactivityTimer }  from '@/composables/useInactivityTimer.js';
import { useActiveObjective }  from '@/composables/useActiveObjective.js';
import { useDocChat }          from '@/composables/useDocChat.js';
// useDocNotifications, useQuestArchive, useDialogue, useFieldComms — story/quest
// system, disconnected (see CONTRACTS_AND_OS_REWORK_PLAN.md).
// ── New composables ───────────────────────────────────────────────────────────
import { useBountyEscalation }  from '@/composables/useBountyEscalation.js';
import { useCommandEffects }    from '@/composables/useCommandEffects.js';
import { useHackFlow }          from '@/composables/useHackFlow.js';
import { useCodex }             from '@/composables/useCodex.js';
import { useCodexFind }         from '@/composables/useCodexFind.js';
import { usePvpFlow }           from '@/composables/usePvpFlow.js';
import { useResourceReplenish } from '@/composables/useResourceReplenish.js';
import { useBrowserNavigation } from '@/composables/useBrowserNavigation.js';
import { useNodeTracking }      from '@/composables/useNodeTracking.js';
import { getBankTargetNetworkName } from '@/composables/businessNodes.js';
// ── Constants ─────────────────────────────────────────────────────────────────
import { docColorByName, docColor } from '@/constants/docColors.js';
import { SPLICE }              from '@/components/browser/SpliceRouter.js';
// WATCHER_TRANSITIONS — Watcher cutscene system, disconnected (see plan).

// ── Auth ──────────────────────────────────────────────────────────────────────
const { playerId, player: authPlayer, rig: authRig, login, logout } = useAuth();

// ── Game state — all reactive refs, seeded from API after login ───────────────
const {
    player, rig, commands, inventory,
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

// ── Dialogue — NPC conversation state + localStorage persistence. CyberDoc
// dialogue is part of the disconnected story/quest system (see plan); no
// longer initialised here.

// ── Combat — challenge handshake + result submission ─────────────────────────
const {
    incomingChallenge,
    challenge:           sendChallenge,
    startPendingPoll,    stopPendingPoll,
    accept:              acceptChallenge,
    decline:             declineChallenge,
    pollChallengeStatus,
} = useCombat(playerId);

// ── Rig damage — SS sync after failed hacks and PvP ──────────────────────────
const { applyDamage } = useRigDamage();

// ── CyberDoc — banking and NPC interactions ───────────────────────────────────
const cyberDoc = useCyberDoc();

// PvP combat state
const activePacketHijack = ref(false); // true while the PH terminal overlay is shown

// Packet Hijack — terminal PvP mini-game
const ph = usePacketHijack(playerId);

// Quest minigame — launched from QuestLog via useQuestMinigame composable
const { activeMinigame, setCurrentNode, clear: clearMinigame } = useQuestMinigame();

// Quest log — story/quest system, disconnected (see
// CONTRACTS_AND_OS_REWORK_PLAN.md). fetchQuestLog is no longer called, so
// questDocs stays empty and everything derived from it below (questMarkers,
// activeObjective, missionToast, etc.) naturally goes dormant. Kept wired
// rather than deleted since the structure is still planned for reuse.
const { docs: questDocs, fetchQuestLog, completeStage: completeQuestStage } = useQuestLog();
const { objective: activeObjective } = useActiveObjective(questDocs);

// Equipped hack/map commands — passed to the PH terminal as the rig loadout strip
const hackCommands = computed(() =>
    (commands.value ?? []).filter(c => c.is_active && (c.context === 'hack' || c.context === 'map'))
);

// Critical system failure overlay — { repairCost } when active, null otherwise
const criticalFailure = ref(null);

// Move-block HUD flash
const hudFlash    = ref('');
let   _flashTimer = null;

// ── Trap system — mine/decoy placement + targeting mode + notifications ──────
const {
    myTraps, trapTargetMode, trapHitNotification, trapFiredNotification,
    placeDecoy, fetchMyTraps,
    attemptPlaceTrap, cancelTrapTarget, handleTrapHit, handleTrapFired,
} = useTrapSystem({ hudFlash });

// Boot notification — shown after Watcher reboot sequence completes
const bootNotification = ref(false);
let   _bootNotifTimer  = null;

// ── Map interaction ───────────────────────────────────────────────────────────
// Pass getByCanvasId so onNodeClicked can merge canvas geometry with live DB state.
const {
    mapCanvasRef, currentNodeId, currentNode,
    selectedNode, selectedNodeIsAdjacent, pings, booted,
    onPlayerMoved, onNodeClicked,
} = useMapInteraction(player, getByCanvasId);

// ── OS shell — window manager for the map + future program windows ───────────
const windowManager = useWindowManager();

// Single place defining what "opening Map" means, so the desktop icon,
// Start Menu, and taskbar all stay in sync automatically.
function openMapWindow() {
    windowManager.open('map', { title: 'NETWORK MAP', icon: '⬢', accent: '#00FF88' });
}

// Map starts closed — player sees the empty desktop first and opens it
// themselves (desktop icon / Start Menu / taskbar). Since HexMapCanvas no
// longer exists synchronously at boot, its starting position can't be seeded
// from Game.vue's onMounted the way it used to be — instead, seed it
// whenever the canvas actually mounts (first open, or any later reopen,
// since closing the window fully unmounts HexMapCanvas). Whichever resolves
// second — the canvas mounting, or the real position loading from the DB —
// is what triggers the seed.
let mapSeededThisMount = false;
watch(mapCanvasRef, (inst) => {
    mapSeededThisMount = false;
    if (inst && currentNodeId.value) {
        inst.setPlayerNode(currentNodeId.value);
        mapSeededThisMount = true;
    }
});
watch(currentNodeId, (id) => {
    if (!mapSeededThisMount && id && mapCanvasRef.value) {
        mapCanvasRef.value.setPlayerNode(id);
        mapSeededThisMount = true;
    }
});

// ── Ping system ───────────────────────────────────────────────────────────────
const {
    firePing, fireFalsePing, clearFalsePings, onMoveTick, reset: resetPings,
} = usePingSystem({ pings, player, rig, currentNode, selectedNode, getByCanvasId, bounties });

// ── Node presence — polls for other players at the current node ───────────────
const { nodePlayers } = useNodePresence(currentNodeId, playerId);

// ── Node traces — data fragments left by recent hackers ──────────────────────
const selectedCanvasId = computed(() => selectedNode.value?.canvasId ?? null);
const { traces: nodeTraces, refreshNow: refreshTraces, storeTrace } = useNodeTraces(selectedCanvasId, playerId);

// ── Tutorial ──────────────────────────────────────────────────────────────────
const tutorial = useTutorial();

// ── UI orientation tours ──────────────────────────────────────────────────────
const tour   = useUiTour();
const gbTour = useGridBreachTour();
const phTour = usePacketHijackTour();

// ── Bounty escalation ─────────────────────────────────────────────────────────
const {
    hackCount, bountyTicker, bountyAlert,
    showBountyAlert, checkBountyEscalation, starLevelFromCount,
} = useBountyEscalation(player);

// Open Season notification — shown once when the player's isOpenSeason flips true
const showOpenSeason = ref(false);
watch(() => player.value.isOpenSeason, (isOs) => {
    if (isOs) showOpenSeason.value = true;
});

// ── Command effects ───────────────────────────────────────────────────────────
const {
    activeEffects, cmdSlug, applyEffectDecrement, applyTrapEffects, onUseCommand,
} = useCommandEffects({
    player, rig, commands,
    activateCommand,
    fireFalsePing, clearFalsePings,
    placeDecoy,
    trapTargetMode,
    pings,
    currentNode,
    currentNodeId,
    selectedNode,
    getNodesNear,
    hudFlash,
});

// ── Resource replenish — drives NodeInfoBlock countdown ticker ────────────────
const replenish = useResourceReplenish({ player, selectedNode, getByCanvasId });
const { nodeResources } = replenish;

// ── Browser navigation ────────────────────────────────────────────────────────
const { activeBrowserUrl, onLaunch, onCloseBrowser, onBrowserUrlChange, onOpenStore } = useBrowserNavigation({ tutorial, currentNodeId });

// ── applyCriticalFailure — single helper, passed to useHackFlow + usePvpFlow ─
// Defined after all state refs so it can close over them directly.
function applyCriticalFailure(cf) {
    player.value.pocketCreds        = 0;
    player.value.bountyLevel        = 0;
    player.value.bountyMultiplier   = 1.0;
    player.value.isOpenSeason       = false;
    player.value.isLimping          = false;
    hackCount.value                 = 0;
    player.value.nodesHackedThisRun = 0;
    player.value.pvpWinsThisRun     = 0;
    resetPings();
    if (cf?.respawn_canvas_id) currentNodeId.value = cf.respawn_canvas_id;
    criticalFailure.value = { repairCost: cf?.repair_cost ?? 0 };
}

// ── Hack flow ─────────────────────────────────────────────────────────────────
const {
    activeHack, effectiveNodeIce,
    onHackSelected, onHackComplete, onHackFailed, onHackAbort,
} = useHackFlow({
    player, playerId,
    deplete, applyDamage,
    storeTrace, refreshTraces,
    updateNodeResources,
    firePing,
    activeEffects,
    hackCount, checkBountyEscalation,
    currentNodeId, selectedNode,
    tutorial, gbTour,
    applyCriticalFailure,
});

// ── Bank Heist flow ──────────────────────────────────────────────────────────
// Separate from useHackFlow — a distinct minigame (BankHeist.vue, not
// GridBreach) gated on the fixed 19-node Bank Heist roster rather than the
// generic hack-any-action-node path. Kept inline (not its own composable)
// since the state/handlers are small; BankHeist.vue owns all the game logic
// round-trips itself via useBankHeist.js, this only opens/closes the overlay
// and syncs the authoritative player/rig fields each server call already saved.
const activeBankHeist = ref(null); // { canvasId, bankName, bankIce, bankTier }

// DEV ONLY — remove alongside splice://dev/minigames before release. Lets the
// dev launcher hand this ref a real roster-backed payload directly, bypassing
// the current-node/cooldown gating onBankHeistSelected() applies below — see
// useDevBankHeist.js's docblock for why this doesn't shortcut the real flow.
const { activeDevBankHeist, clear: clearDevBankHeist } = useDevBankHeist();
watch(activeDevBankHeist, (val) => {
    if (!val) return;
    activeBankHeist.value = val;
    activeBrowserUrl.value = null;
    clearDevBankHeist();
});

function onBankHeistSelected() {
    const node = selectedNode.value;
    if (!node || node.canvasId !== currentNodeId.value) return;
    if (!node.isBankTarget) return;
    if (node.bankCooldownUntil && new Date(node.bankCooldownUntil).getTime() > Date.now()) return;

    activeBankHeist.value = {
        canvasId: node.canvasId,
        bankName: getBankTargetNetworkName(node.canvasId) ?? 'UNKNOWN TARGET',
        bankIce:  node.bankIce ?? 3,
        bankTier: node.bankTier ?? 1,
    };
}

function onBankHeistComplete(payload) {
    activeBankHeist.value = null;

    const sync = payload?.playerSync;
    if (sync) {
        if (sync.pocketCreds !== undefined)      player.value.pocketCreds      = sync.pocketCreds;
        if (sync.techPoints !== undefined)       player.value.techPoints       = sync.techPoints;
        if (sync.bountyLevel !== undefined)      player.value.bountyLevel      = sync.bountyLevel;
        if (sync.bountyMultiplier !== undefined) player.value.bountyMultiplier = sync.bountyMultiplier;
        if (sync.currentSS !== undefined)        player.value.currentSS        = sync.currentSS;
        if (sync.maxSS !== undefined)            player.value.maxSS            = sync.maxSS;
        if (sync.event === 'critical_failure')   applyCriticalFailure(sync.criticalFailure ?? {});
    }

    // Gate 1 failure puts the node on a bank-wide cooldown — patch it into the
    // local node record immediately so SidePanel's countdown doesn't wait for
    // the next natural map refresh.
    if (payload?.gate1Failed && payload?.canvasId) {
        const node = getByCanvasId(payload.canvasId);
        if (node) updateNodeResources(node.id, { bankCooldownUntil: payload.cooldownUntil ?? null });
    }
}

function onBankHeistAbort() {
    activeBankHeist.value = null;
}

// ── Composer dev-lab flow ────────────────────────────────────────────────────
// DEV ONLY — remove alongside splice://dev/generator-lab before release.
// Mirrors the Bank Heist dev-launch bridge above, but for the composer
// (input model x win rule) experiment — see useDevComposer.js's docblock.
// Fully separate from useHackFlow / the real node-hack generator: no reward
// endpoint is ever called from this path.
const activeComposedMinigame = ref(null); // { inputKey, ruleKey, ice } | null
const { activeComposedSpec, clear: clearDevComposer } = useDevComposer();
watch(activeComposedSpec, (val) => {
    if (!val) return;
    activeComposedMinigame.value = val;
    activeBrowserUrl.value = null;
    clearDevComposer();
});

function onComposedMinigameComplete(payload) {
    // Don't clear here — ComposedMinigame.vue shows its own PAIRING SOLVED /
    // PAIRING FAILED outcome pane and waits for [ CLOSE ], which fires
    // @abort below. Clearing immediately on @complete/@failed was unmounting
    // the overlay via v-if before that outcome pane ever got a chance to
    // render, which is why it looked like the game just silently closed.
}

function onComposedMinigameFailed(payload) {
    // See onComposedMinigameComplete's comment — same reasoning applies.
}

function onComposedMinigameAbort() {
    activeComposedMinigame.value = null;
}

// ── SIT dev-lab flow ──────────────────────────────────────────────────────────
// DEV ONLY — remove alongside splice://dev/sit-lab before release. Mirrors
// the composer dev-launch bridge above, but for SIT (Splice Interface
// Terminal, components/minigame/sit/) — see useDevSIT.js's docblock.
// Fully separate from useHackFlow / the real node-hack generator and from
// the composer: no reward endpoint is ever called from this path.
const activeSIT = ref(null); // null | scenario key string (see sit/scenarios/index.js)
const { active: devSITActive, clear: clearDevSIT } = useDevSIT();
watch(devSITActive, (val) => {
    if (!val) return;
    activeSIT.value = val;
    activeBrowserUrl.value = null;
    clearDevSIT();
});

function onSITComplete(payload) {
    // Don't clear here — SIT.vue shows its own outcome pane and waits for
    // [ CLOSE ], which fires @abort below. Same reasoning as
    // onComposedMinigameComplete above.
}

function onSITFailed(payload) {
}

function onSITAbort() {
    activeSIT.value = null;
}

// ── Signal Lock dev-lab flow ─────────────────────────────────────────────────
// DEV ONLY — remove alongside splice://dev/signal-lock-lab before release.
// Mirrors the SIT/composer dev-launch bridges above, but for Signal Lock
// (components/minigame/generator/templates/SignalLock.vue) — a candidate
// addition to the live node-hack pool (generator/pool.js) that is NOT
// registered there yet. No reward endpoint is ever called from this path.
const activeSignalLock = ref(null); // null | { ice, cpu, ram, os } (see useDevSignalLock.js)
const { active: devSignalLockActive, clear: clearDevSignalLock } = useDevSignalLock();
watch(devSignalLockActive, (val) => {
    if (!val) return;
    activeSignalLock.value = val;
    activeBrowserUrl.value = null;
    clearDevSignalLock();
});

function onSignalLockComplete(payload) {
    activeSignalLock.value = null;
}

function onSignalLockFailed(payload) {
    activeSignalLock.value = null;
}

function onSignalLockAbort() {
    activeSignalLock.value = null;
}

// ── Codex find prompt — rolled by useHackFlow on a successful routine hack ─────
const { pendingFind: codexFindPending, accept: onCodexFindPlay, decline: onCodexFindPass } = useCodexFind();
const { fetchState: fetchCodexState } = useCodex();

// ── PvP flow ──────────────────────────────────────────────────────────────────
const {
    pvpResult, awaitingChallenge,
    onHackPlayer, onAcceptChallenge, onDeclineChallenge,
    onPacketHijackMatchComplete,
} = usePvpFlow({
    player, playerId,
    ph,
    incomingChallenge,
    sendChallenge, acceptChallenge, declineChallenge,
    pollChallengeStatus,
    resyncPlayer,
    currentNodeId,
    activePacketHijack,
    tutorial,
    applyCriticalFailure,
});

// ── Quest minigame handlers ───────────────────────────────────────────────────
async function onQuestMinigameComplete() {
    if (!activeMinigame.value) return;
    const { stageId } = activeMinigame.value;
    clearMinigame();
    // Freeform launches (e.g. a Codex find, dev launcher) carry no stageId —
    // there's no quest stage to complete, so there's nothing further to do.
    // ArchiveExtraction.vue already reports its own win to the Codex system.
    if (!stageId) return;
    try {
        await completeQuestStage(stageId);
        // Quest-arc refresh + doc-notification processing disconnected along
        // with the rest of the story/quest system (see plan) — fetchQuestLog
        // is no longer called anywhere, so this path is currently unreachable
        // (no UI can hand back a real stageId), kept only for structure.
    } catch (e) {
        console.warn('[QUEST MINIGAME] stage completion failed:', e?.message);
    }
}

async function onQuestMinigameFail() {
    if (!activeMinigame.value) { clearMinigame(); return; }
    const { skin } = activeMinigame.value;
    clearMinigame();

    if (skin.dealsDamageOnFail && skin.nodeCanvasId) {
        const res = await applyDamage(skin.nodeCanvasId, 'pve');
        if (res) {
            player.value.currentSS = res.current_ss;
            player.value.maxSS     = res.max_ss;
            if (res.event === 'critical_failure') {
                applyCriticalFailure(res.critical_failure ?? {});
            }
        }
    }
}

// ── Node click — intercepts trap targeting mode before normal selection ────────
function handleNodeClicked(event) {
    if (attemptPlaceTrap(event)) return;
    onNodeClicked(event);
}

// ── Player movement ───────────────────────────────────────────────────────────
function handlePlayerMoved(event) {
    onPlayerMoved(event);  // update currentNode, uplink, district

    // Persist position to backend; response carries remaining_uplink + trap_triggered
    updatePosition(event.nodeId, event.district ?? player.value.district, (data) => {
        if (data.remaining_uplink != null) player.value.uplink = data.remaining_uplink;

        if (data.trap_triggered) {
            applyTrapEffects(data.trap_triggered, data.active_effects, data.current_ss);
            handleTrapHit(data.trap_triggered);
        }
        fetchMyTraps();
    });

    applyEffectDecrement();  // mirror server-side decrement; prunes expired effects
    onMoveTick();            // tick false-ping TTL (Signal Noise / Decoy)
}

// ── CyberDoc banking ──────────────────────────────────────────────────────────
async function bankCreds() {
    const pid      = playerId.value;
    const canvasId = currentNodeId.value;
    if (!pid) return null;

    const result = await cyberDoc.bank(pid, canvasId ?? null);
    if (!result) return null;

    const banked = result.pocket_banked ?? 0;
    if (banked > 0) player.value.creds = (player.value.creds ?? 0) + banked;
    player.value.pocketCreds = 0;

    player.value.bountyLevel      = result.player?.bounty_level      ?? 0;
    player.value.bountyMultiplier = result.player?.bounty_multiplier ?? 1.0;
    player.value.isOpenSeason     = result.player?.is_open_season    ?? false;

    hackCount.value                 = 0;
    player.value.nodesHackedThisRun = 0;
    player.value.pvpWinsThisRun     = 0;
    bountyAlert.value               = null;
    resetPings();

    // Clear command cooldowns and active effects on Street Doc visit
    commands.value.forEach(cmd => { cmd.cooldown = false; cmd.movesLeft = 0; });
    activeEffects.value = {};

    return result;
}

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

// ── Misc helpers ──────────────────────────────────────────────────────────────
function onResetCooldowns() {
    commands.value.forEach(c => { c.cooldown = false; });
}

async function onLogout() {
    try { await tutorial.flush(); } catch (e) {
        console.warn('[LOGOUT] tutorial flush failed:', e?.message);
    }
    await logout();
    window.location.href = '/login';
}

function onTutorial() { onLaunch(SPLICE.TERMINAL); }

// ── Persona select ────────────────────────────────────────────────────────────
// First-login account setup (persona/handle) — kept, this is account setup,
// not story content. World Tone (opening cinematic) and the Welcome Modal
// (tutorial-quest entry point) are part of the disconnected story/quest
// system (see CONTRACTS_AND_OS_REWORK_PLAN.md) and no longer trigger:
// dropping World Tone's await here would leave tutorial.tutorialSeen/
// tutorialSkipped permanently false (tutorial.hydrate() is no longer called),
// which would make a showWelcomeModal-style computed permanently true and
// reopen a welcome modal every boot forever — so that computed and its
// handlers are removed outright rather than left as a dangling trigger.
const needsPersonaSelect = ref(false);

function onPersonaDone(persona) {
    player.value.persona      = persona.name;
    player.value.persona_desc = persona.desc;
    needsPersonaSelect.value  = false;
}

// ── Inactivity auto-logout ────────────────────────────────────────────────────
const idle = useInactivityTimer();
idle.setBeforeLogout(() => tutorial.flush());

// ── Tutorial provides and watchers ────────────────────────────────────────────
provide('tutorial', tutorial);

// Clear the Start Menu / TERMINAL badge when SPLICE navigates there. The
// per-step markStepDone() triggers that used to fire alongside this (quest
// progression) are removed — the tutorial-quest system is disconnected (see
// CONTRACTS_AND_OS_REWORK_PLAN.md); tutorial.hydrate() is no longer called,
// so tutorial.markStepDone() would silently no-op anyway, but the call sites
// are cut for clarity rather than left as dead wiring.
watch(activeBrowserUrl, (url) => {
    if (!url) return;

    if (url.startsWith(SPLICE.TERMINAL) || url.startsWith(SPLICE.TUTORIAL)) {
        tutorial.clearBadge();
    }
});

// CORTEX_PATCH install cutscene — narrative cutscene, disconnected (see
// plan). tutorial.needsCortexInstall requires tutorialComplete, which can
// never become true without tutorial.hydrate(), so this is permanently
// false anyway; commented out rather than left as a silent accident.
// watch([booted, tutorial.needsCortexInstall], ([isBooted, needsInstall]) => {
//     if (isBooted && needsInstall) {
//         fetchQuestLog();
//         onLaunch(SPLICE.CORTEX_PATCH);
//     }
// });

// UI tour — the orientation tour itself stays wired up (explicitly excluded
// from the story/quest disconnect). It used to also fire right after the
// CORTEX_PATCH cutscene finished; since that cutscene never launches now,
// tutorial.needsCortexInstall never transitions true → false, so that second
// trigger is dead and removed. The plain post-boot trigger below covers
// every player now (needsCortexInstall.value is permanently false).
watch(booted, (isBooted) => {
    if (isBooted && !tutorial.needsCortexInstall.value) {
        if (tour.start()) openMapWindow();
    }
});

// Node-tracking side effect only — the quest-step triggers that used to live
// here (inspect/move/visit_cyberdoc) are removed along with the tutorial-quest
// system (see plan above).
watch(currentNodeId, (newVal, oldVal) => {
    setCurrentNode(newVal ?? null);
});

// Close the SPLICE browser when a minigame launches
watch(activeMinigame, (val) => {
    if (val) activeBrowserUrl.value = null;
});

// PH tour watchers
watch(() => ph.isPractice && activePacketHijack.value, (active) => {
    if (active) phTour.startPhase1();
});
watch(() => ph.phase, (phase) => {
    if (phase === 2 && ph.isPractice) phTour.startPhase2();
});
// (ph_practice tutorial-step trigger removed along with the tutorial-quest
// system — see plan above.)

// ── Quest log derived state ───────────────────────────────────────────────────
const missionToast = ref(null);
let   _missionToastTimer = null;
watch(() => activeObjective.value?.stageId, (next, prev) => {
    if (!next || next === prev) return;
    if (_missionToastTimer) clearTimeout(_missionToastTimer);
    missionToast.value = activeObjective.value?.stageTitle ?? 'MISSION UPDATED';
    _missionToastTimer = setTimeout(() => { missionToast.value = null; }, 3500);
});

// Player-pinned map markers from the Splice Site map search — session-only,
// module-level state shared with SpliceMapsPage.vue (see useNodeTracking.js)
const { trackedMarkers } = useNodeTracking();

// Derive active objective markers from quest state
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

// ── Field comms + CyberDoc dialogue — disconnected ───────────────────────────
// Both were scripted purely off quest-arc state (field_comms/dialogue columns
// on quest_stages), which is disconnected along with the rest of the
// story/quest system (see CONTRACTS_AND_OS_REWORK_PLAN.md). FieldCommsWindow
// is unmounted above (see template); currentNodeDialogueUrl always returning
// null keeps SidePanel's dialogue button/binding dark without editing that
// component.
const currentNodeDialogueUrl = computed(() => null);

// ── FREQUENCY — DOC hub live chat hotkey ──────────────────────────────────────
// Available at any CyberDoc hub — one isolated room per doc, same as the
// backend (DocChatService::playerIsAtHub / routes/channels.php) already
// enforces generically. The channel connects lazily: walking near a hub only
// lights up the hotkey, it doesn't join anything until the player actually
// opens the window.
const frequencyOpen = ref(false);

const frequencyNode = computed(() => {
    // currentNode only ever holds { canvasId, x, y } (see useMapInteraction's
    // onPlayerMoved) — type/npcHandle live on the DB-merged record, so look it
    // up by the authoritative currentNodeId instead of trusting currentNode.
    const node = getByCanvasId(currentNodeId.value);
    return node?.type === 'cyberdoc' ? node : null;
});
const frequencyHub       = computed(() => frequencyNode.value?.canvasId ?? null);
const frequencyAvailable = computed(() => !!frequencyHub.value);
const frequencyAccent    = computed(() => docColor(frequencyHub.value ?? ''));
const frequencyDocHandle = computed(() => frequencyNode.value?.npcHandle ?? 'CYBERDOC');
const frequencyRoomLabel = computed(() => `${frequencyDocHandle.value.toUpperCase()}'S CHANNEL`);

const {
    messages: frequencyMessages,
    loading:  frequencyLoading,
    sending:  frequencySending,
    error:    frequencyError,
    send:     sendFrequencyMessage,
} = useDocChat(frequencyHub, playerId, computed(() => frequencyOpen.value && frequencyAvailable.value));

function toggleFrequency() {
    if (!frequencyAvailable.value) return;
    frequencyOpen.value = !frequencyOpen.value;
}

// Leaving the hub closes the window rather than leaving it open on a dead room
watch(frequencyHub, (hub) => {
    if (!hub) frequencyOpen.value = false;
});

// ── Watcher signal system — narrative cutscene, disconnected ─────────────────
// WatcherSignal/ChapterTitleCard are unmounted (see template) and nothing
// below arms a transition anymore, so useWatcher() itself, the chapter title
// card state, and the FieldComms→Watcher handoff (handleFieldCommsComplete)
// are cut too rather than left calling into removed UI. See
// CONTRACTS_AND_OS_REWORK_PLAN.md.

provide('questLog', { docs: questDocs, completeStage: completeQuestStage, fetchQuestLog });

// Watcher-transition arming (doc arc → cutscene interrupt) — disconnected
// along with the Watcher signal system above; questDocs never populates now
// (fetchQuestLog is no longer called), so this was already permanently inert,
// but it's commented out rather than left as a silent accident since it also
// referenced triggerSignal/markWatcherSignalSent from the removed systems.
//
// const _WATCHER_RESPAWN_POOL = ['B6', 'E7', 'C10', 'G11', 'H8', 'E5'];
//
// Holds the pending transition config — armed once a doc's entry arc is
// complete but the player hasn't yet left that doc's hub node, cleared when
// the interrupt fires. Armed/re-armed from server quest state (see the
// questDocs/currentNodeId watcher below) rather than from a one-shot client
// callback, so a reload between arc completion and leaving the hub can't
// drop the interrupt.
// _pendingWatcherTransition / _watcherTransitionsFiredThisSession /
// _fireWatcherTransition, the onInstallComplete provide (CORTEX_PATCH install
// reboot cutscene), and the arm/fire watchers below are all removed together
// — they only ever called into the Watcher signal system disconnected above.
// tutorial.markCortexInstall() (previously called from onInstallComplete) is
// likewise no longer reachable; harmless since needsCortexInstall is
// permanently false anyway.

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

// ── Consumable wrapper — merges active_effects returned by software consumables
async function onUseConsumable(consumableId) {
    const result = await useConsumable(consumableId);
    if (result?.type === 'software' && result.active_effects) {
        Object.assign(activeEffects.value, result.active_effects);
    }
    return result;
}

// ── Provides ──────────────────────────────────────────────────────────────────
provide('gameState', {
    player, rig, commands, inventory, bounties,
    bankCreds, currentNodeId, useConsumable: onUseConsumable,
    // Resync helpers — used by CyberDocStore to pull authoritative state after
    // purchases/upgrades rather than manually patching each field.
    resyncPlayer, hydrateFromAuth, fetchCommands, fetchInventory,
});

provide('launchPracticeHijack', async () => {
    activeBrowserUrl.value = null;
    await ph.launchPractice();
    activePacketHijack.value = true;
});

// ── Lifecycle ─────────────────────────────────────────────────────────────────
function onKeyDown(e) {
    if (e.key === 'Escape') cancelTrapTarget();
}

onMounted(async () => {
    window.addEventListener('keydown', onKeyDown);
    window.__tutorial = tutorial;
    idle.start();
    replenish.start();

    // Initialise canvas position from geometry while auth + DB load — only
    // does anything if the Map happens to already be open at this point
    // (it starts closed, so normally this is a no-op; the watchers above
    // handle seeding the real position whenever Map does get opened).
    const geometryStartId = mapCanvasRef.value?.startNodeId;
    if (geometryStartId) currentNodeId.value = geometryStartId;

    // Step 1 — resolve session, hydrate all game state from API
    const loggedIn = await login();
    if (!loggedIn) {
        console.error('[BOOT] Session lookup failed — check auth middleware');
    } else {
        hydrateFromAuth(authPlayer.value, authRig.value);

        // First-login gate — persona selection (account setup). World Tone's
        // await used to chain here too; it's part of the disconnected story
        // system (see plan) so it's dropped rather than gating boot on it.
        if (!player.value.persona) {
            needsPersonaSelect.value = true;
            await new Promise(resolve => {
                const stop = watch(needsPersonaSelect, val => { if (!val) { stop(); resolve(); } });
            });
        }

        // Restore persisted node position so the player can move from any node type on reload
        const savedCanvasId = authPlayer.value?.current_node_canvas_id ?? null;
        if (savedCanvasId) {
            currentNodeId.value = savedCanvasId;
            currentNode.value   = { canvasId: savedCanvasId, x: 0, y: 0 };
        }

        // Restore active command effects from server state (survives page reload)
        Object.assign(activeEffects.value, authPlayer.value?.active_effects ?? {});

        // Seed session hack counter — without this, bountyTicker goes negative on reload
        hackCount.value = player.value.nodesHackedThisRun;

        // Convert raw hack count to 0–5 star level for HUD display
        player.value.bountyLevel = starLevelFromCount(hackCount.value);

        // Story/quest system disconnected (see CONTRACTS_AND_OS_REWORK_PLAN.md):
        // fetchQuestLog/fetchArchive/tutorial.hydrate() no longer run at boot,
        // so questDocs/archiveEvents stay empty and the tutorial-quest system
        // never activates. fetchCodexState() is unrelated (separate Codex
        // Archive feature) and stays.
        await Promise.all([
            fetchCommands(), fetchInventory(), fetchMyTraps(),
            fetchCodexState(),
        ]);

        startHeartbeat();
        startAudio();
    }

    // Step 2 — start polling
    startBountyPolling(30_000);
    startPendingPoll(2_000);

    // Listen for Packet Hijack match start and trap fire events
    if (playerId.value && window.Echo) {
        window.Echo.private(`player.${playerId.value}`)
            .listen('.packet-hijack.started', (data) => {
                ph.init(data.match_id, data.role);
                activePacketHijack.value  = true;
                awaitingChallenge.value   = false;
                incomingChallenge.value   = null;
            })
            .listen('.trap.triggered', (data) => {
                handleTrapFired(data);
                fetchMyTraps();
            });
    }

    // Step 3 — fetch all 228 nodes then resolve starting position
    await fetchAll();

    const savedCanvasId = player.value.currentNodeCanvasId;
    const spawnCanvasId = getSpawnNode();
    const startCanvasId = savedCanvasId ?? spawnCanvasId;

    if (startCanvasId) {
        mapCanvasRef.value?.setPlayerNode(startCanvasId);
        currentNodeId.value = startCanvasId;
        onNodeClicked({ node: { id: startCanvasId, x: 0, y: 0 }, isAdjacent: false });
        if (savedCanvasId) {
        } else {
            updatePosition(spawnCanvasId, player.value.district);
        }
    } else {
        console.warn('[SPAWN] No spawn nodes found — using canvas default');
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
    delete window.__tutorial;
    idle.destroy();
    ws.disconnect();
    replenish.stop();
    stopBountyPolling();
    stopPendingPoll();
    stopHeartbeat();
    stopAudio();
    if (playerId.value && window.Echo) {
        window.Echo.private(`player.${playerId.value}`)
            .stopListening('.trap.triggered');
    }
});
</script>

<style scoped>
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

.ct-icon { font-size: 11px; color: rgba(255, 69, 180, 0.9); animation: ct-pulse 1s ease-in-out infinite; }
.ct-text { font-size: 9px; color: rgba(255, 69, 180, 0.85); letter-spacing: 0.12em; white-space: nowrap; }

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
.ct-cancel:hover { border-color: rgba(255, 69, 180, 0.7); color: rgba(255, 69, 180, 1); background: rgba(255, 69, 180, 0.06); }

@keyframes ct-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

.targeting-fade-enter-active,
.targeting-fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.targeting-fade-enter-from,
.targeting-fade-leave-to     { opacity: 0; transform: translateX(-50%) translateY(-6px); }

/* ── ICE alert banner ─────────────────────────────────────────────────────── */
.ice-alert {
    position: absolute;
    top: 46px;
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

.ice-alert-icon { font-size: 11px; animation: crit-pulse 0.6s ease-in-out infinite; }

.ice-alert-fade-enter-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.ice-alert-fade-leave-active { transition: opacity 0.6s ease 3s; }
.ice-alert-fade-enter-from   { opacity: 0; transform: translateX(-50%) translateY(-6px); }
.ice-alert-fade-leave-to     { opacity: 0; }

/* ── Boot notification + Mission toast ───────────────────────────────────── */
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
    top: 88px;
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

.mission-toast-icon { font-size: 11px; animation: crit-pulse 0.8s ease-in-out infinite; }

@keyframes ice-alert-flicker { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
@keyframes crit-pulse         { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

/* ── Transition names ─────────────────────────────────────────────────────── */
.boot-fade-leave-active { transition: opacity 0.6s ease; }
.boot-fade-leave-to     { opacity: 0; }

.browser-fade-enter-active,
.browser-fade-leave-active { transition: opacity 0.18s ease; }
.browser-fade-enter-from,
.browser-fade-leave-to     { opacity: 0; }

.fade-instant-enter-active,
.fade-instant-leave-active { transition: opacity 0.18s ease; }
.fade-instant-enter-from,
.fade-instant-leave-to     { opacity: 0; }

.breach-fade-enter-active,
.breach-fade-leave-active { transition: opacity 0.22s ease; }
.breach-fade-enter-from,
.breach-fade-leave-to     { opacity: 0; }
</style>
