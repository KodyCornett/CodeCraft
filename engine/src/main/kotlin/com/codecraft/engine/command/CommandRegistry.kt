package com.codecraft.engine.command

import com.codecraft.engine.command.commands.*
import com.codecraft.engine.domain.Detection
import com.codecraft.engine.domain.ToolCatalog
import com.codecraft.engine.domain.ToolEffectType
import com.codecraft.engine.domain.ToolType
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.discovery.ScanService
import com.codecraft.engine.network.gateway.GatewayManager
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.persistence.PlayerRepository
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.puzzle.CombatType
import com.codecraft.engine.puzzle.PuzzleDifficulty
import com.codecraft.engine.puzzle.PuzzleGenerator
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.PendingCombatPuzzle
import com.codecraft.engine.session.ToolActivation

/**
 * Registry of all available commands
 */
class CommandRegistry(
    private val repository: PlayerRepository? = null,
    private val nodeRepository: NodeRepository? = null,
    private val connectionRepository: ConnectionRepository? = null,
    private val discoveryManager: DiscoveryManager? = null,
    private val positionRepository: PositionRepository? = null,
    private val gatewayManager: GatewayManager? = null
) {
    private val commands = mutableMapOf<String, Command>()

    init {
        // Register all commands
        registerSystemCommands()
        registerFilesystemCommands()
        registerNetworkCommands()
        registerUtilityCommands()
        registerMissionCommands()
        registerPuzzleCommands()
    }

    private fun registerSystemCommands() {
        register(HelpCommand(this))
        register(ClearCommand())
    }

    private fun registerFilesystemCommands() {
        register(LsCommand())
        register(CdCommand())
        register(CatCommand())
        register(PwdCommand())
        register(OpenCommand())
        register(DownloadCommand())
        register(ExfilCommand())
        register(DownloadsCommand())
    }

    private fun registerNetworkCommands() {
        register(ScanCommand())
        register(ConnectCommand())
        register(DisconnectCommand())
        register(ProbeCommand())
        // Phase 3 enhanced commands (registered when repos are available)
        if (nodeRepository != null && discoveryManager != null && positionRepository != null) {
            register(EnhancedScanCommand(nodeRepository, discoveryManager, ScanService(), positionRepository))
            register(EnhancedMapCommand(discoveryManager, positionRepository))
            if (gatewayManager != null) {
                register(EnhancedConnectCommand(nodeRepository, discoveryManager, positionRepository, gatewayManager))
            }
            if (connectionRepository != null) {
                register(RouteCommand(nodeRepository, connectionRepository, discoveryManager, positionRepository))
            }
        }
    }

    private fun registerUtilityCommands() {
        register(MailCommand())
        register(SentinelCommand())
        register(WhoamiCommand())
        register(StoreCommand())
        register(StatusCommand())
        register(LoadoutCommand())
        register(UseCommand())
    }

    private fun registerMissionCommands() {
        register(JobsCommand())
        register(JobCommand(repository))
        register(MissionCommand())
    }

    private fun registerPuzzleCommands() {
        register(AnalyzeCommand())
        register(DecodeCommand())
        register(DecryptCommand())
        register(SubmitCommand())
        register(SolveCommand())
        register(DefragCommand())
    }

    fun register(command: Command) {
        commands[command.name.lowercase()] = command
    }

    fun get(name: String): Command? {
        return commands[name.lowercase()]
    }

    fun getAll(): List<Command> {
        return commands.values.toList()
    }

    fun getByCategory(category: CommandCategory): List<Command> {
        return commands.values.filter { it.category == category }
    }

    /**
     * Execute a command string
     */
    fun execute(session: GameSession, commandLine: String): CommandResult {
        // Auto-start tutorial if no mission active and no missions completed
        if (session.currentMission == null && session.player.completedMissions.isEmpty()) {
            val tutorial = com.codecraft.engine.domain.MissionCatalog.getById("tutorial_basics")
            if (tutorial != null && "tutorial_basics" !in session.player.completedMissions) {
                session.currentMission = com.codecraft.engine.domain.ActiveMission(
                    definition = tutorial,
                    negotiatedReward = 0
                )
            }
        }

        val parts = commandLine.trim().split(Regex("\\s+"))
        if (parts.isEmpty() || parts[0].isBlank()) {
            return CommandResult.success("")
        }

        val commandName = parts[0].lowercase()
        val args = parts.drop(1)

        // Check if player has command unlocked
        if (!session.player.hasCommand(commandName)) {
            return CommandResult.error("$commandName: command not found")
        }

        // Get command
        val command = get(commandName)
            ?: return CommandResult.error("$commandName: command not found")

        // Check if firewall is damaged and command is blocked
        if (session.firewallStatus == "damaged" && commandName in listOf("scan", "connect")) {
            return CommandResult.error("$commandName: firewall damaged — repair required before network operations")
        }

        // Apply exposure decay based on idle time
        applyExposureDecay(session)

        // Execute the command
        var result = command.execute(session, args)

        // Apply tool effects to modify exposure change
        if (result.exposureChange > 0.0) {
            result = applyToolEffects(session, result, commandName)
        }

        // Apply exposure change (skip if shield is active)
        var actualExposureChange = 0.0
        if (result.exposureChange > 0.0) {
            if (session.isShieldActive()) {
                // Shield absorbs exposure increase
            } else {
                session.player.increaseExposure(result.exposureChange)
                actualExposureChange = result.exposureChange
            }
        } else if (result.exposureChange < 0.0) {
            session.player.decreaseExposure(-result.exposureChange)
            actualExposureChange = result.exposureChange
        }

        // Clean up expired tools
        ToolActivation(session).cleanupExpiredTools()

        // Update last action time
        session.lastActionTime = System.currentTimeMillis()

        // Clean up expired shield
        session.cleanupExpiredShield()

        // Check for detection (probability-based)
        var detectionTriggered = false
        if (actualExposureChange > 0.0 && session.player.exposure >= 61.0) {
            // Get node-specific detection multiplier (includes alarm state)
            val nodeMultiplier = session.connectedNode?.getTotalDetectionMultiplier() ?: 1.0

            val detected = Detection.rollForDetection(
                session.player.exposure,
                session.player.currentMachine.firewallCurrent.toDouble(),
                session.player.currentMachine.firewallMax.toDouble(),
                nodeMultiplier
            )
            if (detected) {
                detectionTriggered = true

                // Track detection count for active mission
                session.currentMission?.let { mission ->
                    mission.detectionCount++

                    // Check for mission auto-fail (3 detections)
                    if (mission.checkDetectionLimit()) {
                        mission.fail("Detected 3 times - mission compromised")
                    }
                }

                triggerSentinelAttack(session)
            }
        }

        // Check for mission failure conditions (time limits)
        session.currentMission?.let { mission ->
            if (!mission.failed && mission.checkTimeLimit()) {
                mission.fail("Time limit exceeded")
            }
        }

        // Merge exposure/detection into state changes
        var finalResult = if (actualExposureChange != 0.0 || detectionTriggered) {
            val existingChanges = result.stateChanges
            val mergedChanges = (existingChanges ?: StateChanges()).copy(
                exposure = session.player.exposure,
                traceIncrease = if (actualExposureChange > 0) actualExposureChange else null,
                sentinelAttack = if (detectionTriggered) true else existingChanges?.sentinelAttack,
                threatPressure = if (detectionTriggered) session.threatPressure else existingChanges?.threatPressure,
                systemIntegrity = session.systemIntegrity,
                combatPuzzleActive = if (session.combatPuzzle != null) true else existingChanges?.combatPuzzleActive
            )
            result.copy(stateChanges = mergedChanges)
        } else {
            result
        }

        // Track game events (mission completion, etc.)
        val gameEvents = mutableListOf<com.codecraft.engine.protocol.GameEvent>()

        // Auto-track mission objectives
        if (session.currentMission != null) {
            val newlyCompleted = com.codecraft.engine.mission.ObjectiveTracker.checkObjectives(
                session, commandName, args, finalResult
            )

            // Merge newly completed objectives into state changes if any
            if (newlyCompleted.isNotEmpty()) {
                val existingChanges = finalResult.stateChanges
                val mergedChanges = (existingChanges ?: StateChanges()).copy(
                    objectivesCompleted = newlyCompleted
                )
                finalResult = finalResult.copy(stateChanges = mergedChanges)
            }

            // Check if mission just completed
            val mission = session.currentMission
            if (mission != null && mission.isComplete()) {
                println("[CommandRegistry] Mission completed: ${mission.definition.id} - ${mission.definition.title}")

                gameEvents.add(com.codecraft.engine.protocol.GameEvent(
                    type = "mission_completed",
                    data = mapOf(
                        "missionId" to mission.definition.id,
                        "title" to mission.definition.title,
                        "unlocks" to mission.definition.unlocksMissions.joinToString(",")
                    )
                ))

                println("[CommandRegistry] GameEvent created for mission_completed")

                // Mark mission as complete and add to player's completed list
                session.player.completedMissions.add(mission.definition.id)
                session.currentMission = null
            }
        }

        // Add game events to result if any
        if (gameEvents.isNotEmpty()) {
            finalResult = finalResult.copy(events = gameEvents)
        }

        // Persist state after command
        try {
            repository?.saveAfterCommand(session)
        } catch (e: Exception) {
            println("[CommandRegistry] Failed to persist state: ${e.message}")
        }

        return finalResult
    }

    /**
     * Apply time-based exposure decay
     */
    private fun applyExposureDecay(session: GameSession) {
        val now = System.currentTimeMillis()
        val idleMs = now - session.lastActionTime
        val idleMinutes = idleMs / 60000.0

        if (idleMinutes > 0.5 && session.player.exposure > 0) {
            val decayRate = 0.5 // per minute, matching game.php config
            val decay = idleMinutes * decayRate
            session.player.decreaseExposure(decay)
        }
    }

    /**
     * Trigger sentinel attack — increase threat pressure, generate combat puzzle if needed
     */
    private fun triggerSentinelAttack(session: GameSession) {
        session.increaseThreatPressure(25)

        // Determine combat type based on pressure level
        session.activeCombatType = when {
            session.threatPressure >= 67 -> CombatType.AI_HUNTER
            else -> CombatType.SENTINEL_ATTACK
        }

        // Force disconnect if connected
        if (session.connectedNode != null) {
            session.disconnect()
        }

        // Generate combat puzzle if none active and pressure >= 34
        if (session.combatPuzzle == null && session.threatPressure >= 34) {
            val difficulty = PuzzleDifficulty.fromExposure(session.player.exposure, session.threatPressure)
            val combatType = session.activeCombatType ?: CombatType.SENTINEL_ATTACK
            try {
                val puzzle = PuzzleGenerator.generateCombatPuzzle(
                    combatType,
                    difficulty,
                    session.recentPuzzleTypes.toList()
                )
                session.combatPuzzle = PendingCombatPuzzle(
                    puzzleType = puzzle.type,
                    combatType = combatType,
                    question = puzzle.content,
                    answer = puzzle.solution,
                    difficulty = difficulty.intValue,
                    threatReduction = puzzle.threatReduction,
                    systemIntegrityPenalty = puzzle.systemIntegrityPenalty,
                    allowsPartialCredit = puzzle.allowsPartialCredit
                )
                session.puzzleProgress.markSeen(puzzle.type.name)
                session.addSentinelEvent(
                    "sentinel_attack",
                    "INTRUSION DETECTED — Combat puzzle generated. Use 'solve <answer>' to counter."
                )
            } catch (e: Exception) {
                println("[CommandRegistry] Failed to generate combat puzzle: ${e.message}")
                session.addSentinelEvent(
                    "sentinel_attack",
                    "INTRUSION DETECTED — Connection terminated. Initiate DEFRAG to clear traces."
                )
            }
        } else {
            session.addSentinelEvent(
                "sentinel_attack",
                "INTRUSION DETECTED — Threat pressure: ${session.threatPressure}%. Initiate DEFRAG to clear traces."
            )
        }
    }

    /**
     * Apply active tool effects to modify command results
     */
    private fun applyToolEffects(session: GameSession, result: CommandResult, commandName: String): CommandResult {
        val loadout = session.player.currentLoadout ?: return result
        var modifiedExposure = result.exposureChange

        // Check for OVERDRIVE (exposure reduction)
        val overdrive = loadout.getActiveTools().find { it.type == ToolType.OVERDRIVE }
        if (overdrive != null) {
            val def = ToolCatalog.getDefinition(overdrive.type, overdrive.version, overdrive.category)
            if (def != null && def.effectType == ToolEffectType.EXPOSURE_REDUCTION) {
                val reduction = def.effectMagnitude / 100.0
                modifiedExposure *= (1.0 - reduction)
            }
        }

        // Check for CLOAK (command masking)
        val cloak = loadout.getActiveTools().find { it.type == ToolType.CLOAK }
        if (cloak != null && (cloak.remainingCharges ?: 0) > 0) {
            val def = ToolCatalog.getDefinition(cloak.type, cloak.version, cloak.category)
            if (def != null && def.effectType == ToolEffectType.COMMAND_MASKING) {
                modifiedExposure = 0.0  // Command is masked
                ToolActivation(session).consumeCharge(cloak)
            }
        }

        return result.copy(exposureChange = modifiedExposure)
    }
}
