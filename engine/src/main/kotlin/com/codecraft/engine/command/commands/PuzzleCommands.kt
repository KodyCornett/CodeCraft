package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.*
import com.codecraft.engine.protocol.GameEvent
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.puzzle.Puzzle
import com.codecraft.engine.puzzle.PuzzleGenerator
import com.codecraft.engine.puzzle.PuzzleTutorials
import com.codecraft.engine.session.DefragState
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.PendingCombatPuzzle
import com.codecraft.engine.session.PendingPuzzle

/**
 * Puzzle state manager - tracks active puzzles per session
 */
object PuzzleStateManager {
    private val activePuzzles = mutableMapOf<String, MutableMap<String, Puzzle>>()

    fun getPuzzle(sessionId: String, filePath: String): Puzzle? {
        return activePuzzles[sessionId]?.get(filePath)
    }

    fun setPuzzle(sessionId: String, filePath: String, puzzle: Puzzle) {
        activePuzzles.getOrPut(sessionId) { mutableMapOf() }[filePath] = puzzle
    }

    fun clearPuzzle(sessionId: String, filePath: String) {
        activePuzzles[sessionId]?.remove(filePath)
    }
}

/**
 * analyze - Analyze a puzzle file to get hints about type and approach
 */
class AnalyzeCommand : Command {
    override val name = "analyze"
    override val description = "Analyze encrypted data to identify puzzle type"
    override val usage = "analyze <file>"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: analyze <file>")
        }

        val filePath = resolvePath(session.currentPath, args[0])
        val node = session.getCurrentNode()

        // Check if file exists
        if (!node.files.containsKey(filePath)) {
            return CommandResult.error("analyze: $filePath: No such file")
        }

        // Check if this is a mission puzzle file
        val activeMission = session.currentMission
        val isMissionFile = activeMission?.definition?.targetFile == filePath

        if (!isMissionFile) {
            return CommandResult.success("""
                |Analysis of: $filePath
                |─────────────────────────────────────────
                |No encrypted or puzzle content detected.
                |This appears to be a standard data file.
            """.trimMargin(), delayMs = 150)
        }

        // Get or generate the puzzle
        val puzzle = getOrCreatePuzzle(session, filePath, activeMission)

        val analysis = PuzzleGenerator.analyzePuzzle(puzzle)

        return CommandResult(
            output = analysis,
            success = true,
            delayMs = 200,
            exposureChange = 1.0
        )
    }

    private fun resolvePath(currentPath: String, path: String): String {
        return if (path.startsWith("/")) {
            path
        } else {
            "$currentPath/$path".replace("//", "/")
        }
    }

    private fun getOrCreatePuzzle(session: GameSession, filePath: String, mission: ActiveMission): Puzzle {
        var puzzle = PuzzleStateManager.getPuzzle(session.sessionId, filePath)
        if (puzzle == null) {
            puzzle = PuzzleGenerator.generate(
                type = mission.definition.puzzleType,
                difficulty = mission.definition.difficulty,
                seed = mission.definition.puzzleSolution,
                hint = "Target has ${mission.definition.puzzleSolution.split(" ").size} elements"
            )
            PuzzleStateManager.setPuzzle(session.sessionId, filePath, puzzle)
        }
        return puzzle
    }
}

/**
 * decode - Attempt to decode encrypted data
 */
class DecodeCommand : Command {
    override val name = "decode"
    override val description = "Decode encrypted data using various methods"
    override val usage = "decode <file> [method]"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return showHelp()
        }

        val filePath = resolvePath(session.currentPath, args[0])
        val method = args.getOrNull(1)?.lowercase()
        val node = session.getCurrentNode()

        // Check if file exists
        if (!node.files.containsKey(filePath)) {
            return CommandResult.error("decode: $filePath: No such file")
        }

        // Check if this is a mission puzzle file
        val activeMission = session.currentMission
        val isMissionFile = activeMission?.definition?.targetFile == filePath

        if (!isMissionFile) {
            return CommandResult.error("decode: No encrypted content detected in $filePath")
        }

        val puzzle = getOrCreatePuzzle(session, filePath, activeMission)

        // Check if method matches puzzle type
        val expectedMethod = when (puzzle.type) {
            PuzzleType.CAESAR_CIPHER -> "caesar"
            PuzzleType.BINARY_DECODE -> "binary"
            else -> null
        }

        if (method == null) {
            return CommandResult.success("""
                |Attempting automatic decode of: $filePath
                |─────────────────────────────────────────
                |Unable to automatically decode. Use 'analyze' first to determine
                |the encryption method, then use 'decode <file> <method>'.
                |
                |Available methods: caesar, binary, base64, hex
            """.trimMargin(), delayMs = 150)
        }

        if (expectedMethod != null && method == expectedMethod) {
            // Correct method - show decoded content (which is the solution)
            val output = buildString {
                appendLine("Decoding $filePath using $method method...")
                appendLine("─".repeat(50))
                appendLine()
                appendLine("DECODED CONTENT:")
                appendLine(puzzle.solution)
                appendLine()
                appendLine("─".repeat(50))
                appendLine("Use 'submit <answer>' to submit this solution.")
            }
            return CommandResult(
                output = output,
                success = true,
                delayMs = 300,
                exposureChange = 2.0
            )
        } else if (expectedMethod != null) {
            return CommandResult.success("""
                |Attempting to decode with '$method' method...
                |─────────────────────────────────────────
                |ERROR: Decoding failed. Wrong method or corrupted data.
                |
                |Hint: Use 'analyze $filePath' to determine the correct method.
            """.trimMargin(), delayMs = 200)
        } else {
            // For word search, anagram, pattern - decode doesn't apply
            return CommandResult.success("""
                |Analyzing $filePath with '$method' decoder...
                |─────────────────────────────────────────
                |This file doesn't appear to use standard encoding.
                |The data may require manual analysis.
                |
                |Use 'cat $filePath' to view the raw content.
            """.trimMargin(), delayMs = 200)
        }
    }

    private fun showHelp(): CommandResult {
        return CommandResult.success("""
            |DECODE - Decrypt encoded data
            |─────────────────────────────────────────
            |Usage: decode <file> [method]
            |
            |Methods:
            |  caesar    - Caesar cipher (letter shift)
            |  binary    - Binary to ASCII
            |  base64    - Base64 decoding
            |  hex       - Hexadecimal to ASCII
            |
            |Use 'analyze <file>' first to detect encryption type.
        """.trimMargin(), delayMs = 100)
    }

    private fun resolvePath(currentPath: String, path: String): String {
        return if (path.startsWith("/")) {
            path
        } else {
            "$currentPath/$path".replace("//", "/")
        }
    }

    private fun getOrCreatePuzzle(session: GameSession, filePath: String, mission: ActiveMission): Puzzle {
        var puzzle = PuzzleStateManager.getPuzzle(session.sessionId, filePath)
        if (puzzle == null) {
            puzzle = PuzzleGenerator.generate(
                type = mission.definition.puzzleType,
                difficulty = mission.definition.difficulty,
                seed = mission.definition.puzzleSolution
            )
            PuzzleStateManager.setPuzzle(session.sessionId, filePath, puzzle)
        }
        return puzzle
    }
}

/**
 * decrypt - Decrypt with a key/password
 */
class DecryptCommand : Command {
    override val name = "decrypt"
    override val description = "Decrypt data using a password or key"
    override val usage = "decrypt <file> <key>"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.size < 2) {
            return CommandResult.error("Usage: decrypt <file> <key>")
        }

        val filePath = resolvePath(session.currentPath, args[0])
        val key = args.drop(1).joinToString(" ")
        val node = session.getCurrentNode()

        // Check if file exists
        if (!node.files.containsKey(filePath)) {
            return CommandResult.error("decrypt: $filePath: No such file")
        }

        // Check if this is a mission puzzle file
        val activeMission = session.currentMission
        val isMissionFile = activeMission?.definition?.targetFile == filePath

        if (!isMissionFile) {
            return CommandResult.error("decrypt: No encrypted content detected in $filePath")
        }

        // Check if key matches solution (for puzzles that use key-based unlock)
        val mission = activeMission.definition
        if (key.uppercase() == mission.puzzleSolution.uppercase()) {
            // Correct key!
            return handleCorrectSolution(session, activeMission, filePath)
        }

        return CommandResult(
            output = """
                |Attempting decryption of $filePath...
                |─────────────────────────────────────────
                |ERROR: Invalid decryption key.
                |
                |The key '$key' does not match the encryption.
                |Use 'analyze' to study the file and find the correct key.
            """.trimMargin(),
            success = true,
            delayMs = 200,
            exposureChange = 1.5
        )
    }

    private fun resolvePath(currentPath: String, path: String): String {
        return if (path.startsWith("/")) {
            path
        } else {
            "$currentPath/$path".replace("//", "/")
        }
    }

    private fun handleCorrectSolution(
        session: GameSession,
        mission: ActiveMission,
        filePath: String
    ): CommandResult {
        mission.puzzleSolved = true

        // Complete puzzle objective
        val puzzleObj = mission.definition.objectives.find { it.type == ObjectiveType.SOLVE_PUZZLE }
        puzzleObj?.let { mission.completeObjective(it.id) }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              DECRYPTION SUCCESSFUL                   ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  File: $filePath".padEnd(55) + "║")
            appendLine("║  Status: DECRYPTED".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Objective completed!".padEnd(55) + "║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        // Check if mission is complete
        val events = if (mission.isComplete()) {
            listOf(GameEvent("mission_complete", mapOf("missionId" to mission.definition.id)))
        } else {
            emptyList()
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 300,
            events = events,
            exposureChange = 3.0
        )
    }
}

/**
 * submit - Submit puzzle solution for current mission
 */
class SubmitCommand : Command {
    override val name = "submit"
    override val description = "Submit puzzle solution for current mission"
    override val usage = "submit <answer>"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: submit <answer>")
        }

        val answer = args.joinToString(" ")

        val activeMission = session.currentMission
            ?: return CommandResult.error("No active mission. Accept a job first with 'jobs' and 'job accept'.")

        if (activeMission.puzzleSolved) {
            return CommandResult.error("Puzzle already solved for this mission.")
        }

        val mission = activeMission.definition
        val filePath = mission.targetFile
            ?: return CommandResult.error("This mission does not have a puzzle component.")

        // Get the puzzle
        val puzzle = PuzzleStateManager.getPuzzle(session.sessionId, filePath)
            ?: return CommandResult.error("No puzzle file analyzed. Use 'analyze' on the target file first.")

        // Validate the answer
        val isCorrect = puzzle.validate(answer)

        if (isCorrect) {
            return handleCorrectSolution(session, activeMission)
        } else {
            val failOutput = buildString {
                appendLine("╔══════════════════════════════════════════════════════╗")
                appendLine("║              SUBMISSION FAILED                       ║")
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  Your answer: ${answer.take(40)}".padEnd(55) + "║")
                appendLine("║  Result: INCORRECT".padEnd(55) + "║")
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  Review the puzzle and try again.                    ║")
                appendLine("║  Use 'analyze' for hints.                            ║")
                appendLine("╚══════════════════════════════════════════════════════╝")
            }
            return CommandResult(
                output = failOutput,
                success = true,
                delayMs = 200,
                exposureChange = 2.0
            )
        }
    }

    private fun handleCorrectSolution(session: GameSession, mission: ActiveMission): CommandResult {
        mission.puzzleSolved = true

        // Complete puzzle objective
        val puzzleObj = mission.definition.objectives.find { it.type == ObjectiveType.SOLVE_PUZZLE }
        puzzleObj?.let { mission.completeObjective(it.id) }

        // Check stealth objective
        val stealthObj = mission.definition.objectives.find { it.type == ObjectiveType.REMAIN_UNDETECTED }
        stealthObj?.let { obj ->
            val threshold = obj.target?.toIntOrNull() ?: 100
            if (session.player.exposure < threshold) {
                mission.completeObjective(obj.id)
            }
        }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              PUZZLE SOLVED!                          ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Solution accepted.                                  ║")
            appendLine("║  Objective completed!".padEnd(55) + "║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        if (mission.isComplete()) {
            // Mission complete!
            return completeMission(session, mission, output)
        }

        return CommandResult.success(output, delayMs = 300)
    }

    private fun completeMission(
        session: GameSession,
        mission: ActiveMission,
        priorOutput: String
    ): CommandResult {
        val player = session.player
        val definition = mission.definition

        // Award credits
        player.earnCredits(mission.negotiatedReward)

        // Award reputation
        definition.reputationReward.forEach { (faction, change) ->
            val current = player.reputation[faction] ?: 0
            player.reputation[faction] = (current + change).coerceIn(-100, 100)
        }

        // Mark mission complete
        player.completedMissions.add(definition.id)
        player.activeMission = null
        session.currentMission = null

        // Clear puzzle state (if mission has a puzzle)
        definition.targetFile?.let { targetFile ->
            PuzzleStateManager.clearPuzzle(session.sessionId, targetFile)
        }

        val output = buildString {
            append(priorOutput)
            appendLine()
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              MISSION COMPLETE!                       ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${definition.title}".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  REWARDS:".padEnd(55) + "║")
            appendLine("║  Credits: +§${mission.negotiatedReward}".padEnd(55) + "║")

            definition.reputationReward.forEach { (faction, change) ->
                val sign = if (change >= 0) "+" else ""
                appendLine("║  ${faction.replaceFirstChar { it.uppercase() }} Rep: $sign$change".padEnd(55) + "║")
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Check 'jobs' for new opportunities.                 ║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        val stateChanges = StateChanges(
            credits = player.credits,
            activeMission = null
        )

        return CommandResult.withStateChange(output, stateChanges, delayMs = 500)
    }
}

/**
 * solve - Unified solve handler for connection puzzles, counter-hack, and defrag
 */
class SolveCommand : Command {
    override val name = "solve"
    override val description = "Solve a pending puzzle (connection, counter-hack, or defrag)"
    override val usage = "solve <answer>"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: solve <answer>")
        }

        val answer = args.joinToString(" ").uppercase().trim()

        // Priority 1: Active defrag
        val defrag = session.defragState
        if (defrag != null) {
            return handleDefragSolve(session, defrag, answer)
        }

        // Priority 2: Combat puzzle (sentinel/hacker attack)
        val combat = session.combatPuzzle
        if (combat != null) {
            return handleCombatPuzzleSolve(session, combat, answer)
        }

        // Priority 3: Counter-hack puzzle (legacy, backward compat)
        val counterHack = session.counterHackPuzzle
        if (counterHack != null) {
            return handleCounterHackSolve(session, counterHack, answer)
        }

        // Priority 4: Connection/node access puzzle
        val pending = session.pendingPuzzle
        if (pending != null) {
            return handleConnectionSolve(session, pending, answer)
        }

        return CommandResult.error("No active puzzle to solve.")
    }

    private fun handleCombatPuzzleSolve(
        session: GameSession,
        puzzle: PendingCombatPuzzle,
        answer: String
    ): CommandResult {
        session.puzzleProgress.recordAttempt(puzzle.puzzleType.name, answer.uppercase() == puzzle.answer.uppercase())

        if (answer.uppercase().trim() == puzzle.answer.uppercase().trim()) {
            session.combatPuzzle = null
            session.recordPuzzleType(puzzle.puzzleType)

            // Reduce threat pressure
            session.decreaseThreatPressure(puzzle.threatReduction)

            // Activate shield (180s for combat success)
            session.activateShield(180)

            // Disconnect if connected
            if (session.connectedNode != null) {
                session.disconnect()
            }

            // Reduce exposure
            session.player.decreaseExposure(8.0)

            val output = buildString {
                appendLine("COMBAT PUZZLE SOLVED")
                appendLine("─".repeat(50))
                appendLine("Threat neutralized. Shield activated for 180 seconds.")
                appendLine("Exposure reduced by 8%.")
                appendLine("Threat pressure: ${session.threatPressure}%")
                if (session.connectedNode == null) appendLine("Connection terminated for safety.")
            }

            return CommandResult(
                output = output,
                success = true,
                delayMs = 300,
                stateChanges = StateChanges(
                    shieldActivated = true,
                    connectedTo = null,
                    exposure = session.player.exposure,
                    threatPressure = session.threatPressure,
                    systemIntegrity = session.systemIntegrity
                ),
                exposureChange = 0.0
            )
        } else {
            // Wrong answer — damage system integrity
            session.damageSystemIntegrity(puzzle.systemIntegrityPenalty)
            session.puzzleProgress.recordAttempt(puzzle.puzzleType.name, false)

            val output = buildString {
                appendLine("COMBAT RESPONSE FAILED")
                appendLine("─".repeat(50))
                appendLine("System integrity: ${session.systemIntegrity}%")
                appendLine()
                appendLine(puzzle.question)
                appendLine()
                appendLine("Use 'solve <answer>' to try again.")
            }

            return CommandResult(
                output = output,
                success = true,
                delayMs = 200,
                stateChanges = StateChanges(
                    systemIntegrity = session.systemIntegrity
                ),
                exposureChange = 3.0
            )
        }
    }

    private fun handleDefragSolve(session: GameSession, defrag: DefragState, answer: String): CommandResult {
        if (answer == defrag.currentAnswer.uppercase()) {
            defrag.completedSteps++

            // Remove a trace
            if (defrag.traceNodeId != null) {
                session.connectionHistory.removeAll { it.nodeId == defrag.traceNodeId && it.disconnectedAt == null }
            }

            if (defrag.completedSteps >= defrag.totalSteps) {
                // Defrag complete
                val wasSentinelAttack = defrag.wasUnderAttack
                session.defragState = null
                session.decreaseThreatPressure(100)

                // Reduce exposure
                session.player.decreaseExposure(10.0)

                // Damage firewall if sentinel attack
                if (wasSentinelAttack) {
                    session.player.currentMachine.damageFirewall(15)
                    session.firewallStatus = "damaged"
                    session.addSentinelEvent("defrag_complete", "DEFRAG complete. Firewall damaged during attack.")
                } else {
                    session.addSentinelEvent("defrag_complete", "DEFRAG complete. Traces cleared.")
                }

                val output = buildString {
                    appendLine("DEFRAG COMPLETE")
                    appendLine("─".repeat(50))
                    appendLine("All traces cleared. Exposure reduced.")
                    if (wasSentinelAttack) {
                        appendLine("WARNING: Firewall sustained damage during attack.")
                        appendLine("Firewall: ${session.player.currentMachine.firewallCurrent}/${session.player.currentMachine.firewallMax}")
                    }
                }

                return CommandResult(
                    output = output,
                    success = true,
                    delayMs = 300,
                    stateChanges = StateChanges(
                        defragComplete = true,
                        firewallStatus = session.firewallStatus,
                        exposure = session.player.exposure
                    ),
                    exposureChange = 0.0
                )
            } else {
                // Generate next defrag puzzle
                val nextPuzzle = PuzzleGenerator.generateDefragPuzzle(defrag.completedSteps)
                val newDefrag = DefragState(
                    totalSteps = defrag.totalSteps,
                    completedSteps = defrag.completedSteps,
                    currentQuestion = nextPuzzle.content,
                    currentAnswer = nextPuzzle.solution,
                    wasUnderAttack = defrag.wasUnderAttack,
                    traceNodeId = session.connectionHistory
                        .filter { it.disconnectedAt == null }
                        .firstOrNull()?.nodeId
                )
                session.defragState = newDefrag

                val output = buildString {
                    appendLine("Correct! Step ${defrag.completedSteps}/${defrag.totalSteps} complete.")
                    appendLine()
                    appendLine(nextPuzzle.content)
                    appendLine()
                    appendLine("Use 'solve <answer>' to continue defrag.")
                }

                return CommandResult.success(output, delayMs = 200)
            }
        } else {
            return CommandResult(
                output = "Incorrect. Try again.\n\n${defrag.currentQuestion}",
                success = true,
                delayMs = 150,
                exposureChange = 1.0
            )
        }
    }

    private fun handleCounterHackSolve(session: GameSession, puzzle: PendingPuzzle, answer: String): CommandResult {
        if (answer == puzzle.answer.uppercase()) {
            session.counterHackPuzzle = null

            // Activate shield
            session.activateShield(240)

            // Disconnect if connected
            if (session.connectedNode != null) {
                session.disconnect()
            }

            // Reduce exposure
            session.player.decreaseExposure(10.0)

            val output = buildString {
                appendLine("COUNTER-HACK SUCCESSFUL")
                appendLine("─".repeat(50))
                appendLine("Shield activated for 240 seconds.")
                appendLine("Exposure reduced by 10%.")
                appendLine("Connection terminated for safety.")
            }

            return CommandResult(
                output = output,
                success = true,
                delayMs = 300,
                stateChanges = StateChanges(
                    shieldActivated = true,
                    connectedTo = null,
                    exposure = session.player.exposure
                )
            )
        } else {
            return CommandResult(
                output = "Incorrect. Counter-hack failed.\n\n${puzzle.question}\n\nUse 'solve <answer>' to try again.",
                success = true,
                delayMs = 150,
                exposureChange = 2.0
            )
        }
    }

    private fun handleConnectionSolve(session: GameSession, puzzle: PendingPuzzle, answer: String): CommandResult {
        if (answer == puzzle.answer.uppercase()) {
            session.pendingPuzzle = null
            session.nodeAccess.add(puzzle.nodeId)

            // Complete the connection
            session.connectTo(puzzle.nodeId)
            val node = session.network.getNode(puzzle.nodeId)

            // Check for mission connect objective
            val activeMission = session.currentMission
            if (activeMission != null && node != null) {
                val connectObj = activeMission.definition.objectives.find {
                    it.type == ObjectiveType.CONNECT_NODE && it.target == node.id
                }
                connectObj?.let {
                    if (it.id !in activeMission.completedObjectives) {
                        activeMission.completeObjective(it.id)
                    }
                }
            }

            val output = buildString {
                appendLine("ACCESS GRANTED")
                appendLine("─".repeat(50))
                appendLine("Connected to ${node?.name ?: puzzle.nodeId}")
                if (node != null && !node.compromised) {
                    appendLine("WARNING: Unauthorized access. Trace level increasing.")
                }
            }

            return CommandResult(
                output = output,
                success = true,
                delayMs = 1000,
                stateChanges = StateChanges(
                    connectedTo = puzzle.nodeId,
                    connectedToName = node?.name,
                    rootPath = node?.rootPath ?: "/home/user"
                ),
                exposureChange = 3.0
            )
        } else {
            val displayQuestion = PuzzleTutorials.maybePrepend(puzzle.question, puzzle.puzzleType, session.puzzleProgress)
            return CommandResult(
                output = "ACCESS DENIED. Incorrect access code.\n\n$displayQuestion\n\nUse 'solve <answer>' to try again.",
                success = true,
                delayMs = 200,
                exposureChange = 2.0
            )
        }
    }
}

/**
 * defrag - Proactive DEFRAG to clear traces
 */
class DefragCommand : Command {
    override val name = "defrag"
    override val description = "Clear connection traces"
    override val usage = "defrag"
    override val category = CommandCategory.HACKING

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        // Must be on localhost
        if (session.connectedNode != null) {
            return CommandResult.error("defrag: must be on localhost. Disconnect first.")
        }

        // Check if already in defrag
        if (session.defragState != null) {
            val defrag = session.defragState!!
            val output = buildString {
                appendLine("DEFRAG IN PROGRESS (${defrag.completedSteps}/${defrag.totalSteps})")
                appendLine()
                appendLine(defrag.currentQuestion)
                appendLine()
                appendLine("Use 'solve <answer>' to continue.")
            }
            return CommandResult.success(output, delayMs = 100)
        }

        // Check if there are traces to clear
        val hasTraces = session.connectionHistory.isNotEmpty()
        val underAttack = session.sentinelAttackActive

        if (!hasTraces && !underAttack) {
            return CommandResult.error("defrag: no connection traces to clear")
        }

        // Calculate steps based on trace count
        val traceCount = session.connectionHistory.size.coerceAtLeast(1)
        val totalSteps = (traceCount + 2).coerceIn(5, 10)

        // Generate first puzzle
        val firstPuzzle = PuzzleGenerator.generateDefragPuzzle(0)
        val firstTrace = session.connectionHistory.firstOrNull { it.disconnectedAt == null }

        session.defragState = DefragState(
            totalSteps = totalSteps,
            completedSteps = 0,
            currentQuestion = firstPuzzle.content,
            currentAnswer = firstPuzzle.solution,
            wasUnderAttack = underAttack,
            traceNodeId = firstTrace?.nodeId
        )

        val output = buildString {
            appendLine("INITIATING DEFRAG")
            appendLine("─".repeat(50))
            appendLine("Traces found: $traceCount")
            appendLine("Steps required: $totalSteps")
            if (underAttack) {
                appendLine("WARNING: Sentinel attack active. Firewall will sustain damage.")
            }
            appendLine()
            appendLine(firstPuzzle.content)
            appendLine()
            appendLine("Use 'solve <answer>' to proceed.")
        }

        return CommandResult.success(output, delayMs = 300)
    }
}

/**
 * Enhanced cat command that shows puzzle content for mission files
 */
class PuzzleCatCommand : Command {
    override val name = "cat"
    override val description = "Display file contents"
    override val usage = "cat <file>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("cat: missing file operand")
        }

        val filename = args[0]
        val path = resolvePath(session.currentPath, filename)
        val node = session.getCurrentNode()

        // Check if file exists
        val content = node.files[path]
            ?: return CommandResult.error("cat: $filename: No such file or directory")

        // Check if this is a mission puzzle file
        val activeMission = session.currentMission
        val isMissionFile = activeMission?.definition?.targetFile == path

        if (isMissionFile && activeMission != null) {
            // Show puzzle content instead of raw file
            val puzzle = getOrCreatePuzzle(session, path, activeMission)

            // Complete the download/find objective
            val downloadObj = activeMission.definition.objectives.find {
                it.type == ObjectiveType.DOWNLOAD_FILE && it.target == path
            }
            downloadObj?.let {
                if (it.id !in activeMission.completedObjectives) {
                    activeMission.completeObjective(it.id)
                }
            }

            return CommandResult.success(puzzle.content, delayMs = 200)
        }

        // Regular file - show content
        return CommandResult.success(content, delayMs = 100)
    }

    private fun resolvePath(currentPath: String, path: String): String {
        return if (path.startsWith("/")) {
            path
        } else {
            "$currentPath/$path".replace("//", "/")
        }
    }

    private fun getOrCreatePuzzle(session: GameSession, filePath: String, mission: ActiveMission): Puzzle {
        var puzzle = PuzzleStateManager.getPuzzle(session.sessionId, filePath)
        if (puzzle == null) {
            puzzle = PuzzleGenerator.generate(
                type = mission.definition.puzzleType,
                difficulty = mission.definition.difficulty,
                seed = mission.definition.puzzleSolution,
                hint = "Look for ${mission.definition.puzzleSolution.split(" ").size} elements"
            )
            PuzzleStateManager.setPuzzle(session.sessionId, filePath, puzzle)
        }
        return puzzle
    }
}
