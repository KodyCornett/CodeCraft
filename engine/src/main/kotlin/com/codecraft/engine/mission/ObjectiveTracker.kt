package com.codecraft.engine.mission

import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.command.commands.PuzzleStateManager
import com.codecraft.engine.domain.ObjectiveDefinition
import com.codecraft.engine.domain.ObjectiveType
import com.codecraft.engine.session.GameSession

/**
 * Tracks and validates mission objective completion.
 * Called after every command execution to check if objectives are met.
 */
object ObjectiveTracker {

    /**
     * Checks if any objectives should be completed based on the command executed.
     *
     * @param session Current game session
     * @param commandName The command that was executed
     * @param args Arguments passed to the command
     * @param result Result of the command execution
     * @return List of newly completed objective IDs
     */
    fun checkObjectives(
        session: GameSession,
        commandName: String,
        args: List<String>,
        result: CommandResult
    ): List<String> {
        val mission = session.currentMission ?: return emptyList()
        val definition = mission.definition
        val newlyCompleted = mutableListOf<String>()

        // Check each incomplete objective (both required and bonus)
        val allObjectives = definition.objectives + definition.bonusObjectives
        val incompleteObjectives = allObjectives.filter { objective ->
            objective.id !in mission.completedObjectives &&
            objective.id !in mission.bonusObjectivesCompleted &&
            !mission.failedObjectives.contains(objective.id) &&
            canCheckObjective(mission, objective)
        }

        for (objective in incompleteObjectives) {
            if (isObjectiveComplete(session, objective, commandName, args, result)) {
                // Determine if this is a bonus objective
                val isBonusObjective = definition.bonusObjectives.any { it.id == objective.id }

                if (isBonusObjective) {
                    mission.bonusObjectivesCompleted.add(objective.id)
                } else {
                    mission.completedObjectives.add(objective.id)
                }

                newlyCompleted.add(objective.id)
            }
        }

        // Continuously validate REMAIN_UNDETECTED objectives
        validateStealthObjectives(session)

        // Check time-based objectives for expiration
        checkTimeBasedObjectives(session)

        return newlyCompleted
    }

    /**
     * Checks if an objective's prerequisites are met.
     */
    private fun canCheckObjective(mission: com.codecraft.engine.domain.ActiveMission, objective: ObjectiveDefinition): Boolean {
        val requiredObjective = objective.requiresObjective ?: return true
        return mission.completedObjectives.contains(requiredObjective)
    }

    /**
     * Determines if a specific objective is complete.
     */
    private fun isObjectiveComplete(
        session: GameSession,
        objective: ObjectiveDefinition,
        commandName: String,
        args: List<String>,
        result: CommandResult
    ): Boolean {
        return when (objective.type) {
            ObjectiveType.CONNECT_NODE -> checkConnectNode(session, objective, commandName, args)
            ObjectiveType.DOWNLOAD_FILE -> checkDownloadFile(session, objective, commandName)
            ObjectiveType.EXFILTRATE_FILE -> false // Completed directly by ExfilCommand
            ObjectiveType.SOLVE_PUZZLE -> checkSolvePuzzle(session, objective, commandName, result)
            ObjectiveType.EXTRACT_DATA -> checkExtractData(session, objective, commandName, args)
            ObjectiveType.REMAIN_UNDETECTED -> false // Checked separately in validateStealthObjectives
        }
    }

    /**
     * Checks if a CONNECT_NODE objective is complete.
     */
    private fun checkConnectNode(
        session: GameSession,
        objective: ObjectiveDefinition,
        commandName: String,
        args: List<String>
    ): Boolean {
        if (commandName != "connect") return false

        val targetNodeId = objective.target ?: return false
        val connectedNode = session.connectedNode ?: return false

        return connectedNode.id == targetNodeId
    }

    /**
     * Checks if a DOWNLOAD_FILE objective is complete.
     */
    private fun checkDownloadFile(
        session: GameSession,
        objective: ObjectiveDefinition,
        commandName: String
    ): Boolean {
        if (commandName != "download") return false

        val targetFile = objective.target ?: return false

        // Check if the file is in downloads
        return session.downloads.any { download ->
            download.path.contains(targetFile) || download.path.endsWith(targetFile)
        }
    }

    /**
     * Checks if a SOLVE_PUZZLE objective is complete.
     * Puzzles are solved via submit or decrypt commands.
     */
    private fun checkSolvePuzzle(
        session: GameSession,
        objective: ObjectiveDefinition,
        commandName: String,
        result: CommandResult
    ): Boolean {
        // Puzzles are solved via submit or decrypt commands
        if (commandName !in listOf("submit", "decrypt")) return false

        val mission = session.currentMission ?: return false

        // Check if the mission puzzle was solved
        return result.success && mission.puzzleSolved
    }

    /**
     * Checks if an EXTRACT_DATA objective is complete.
     * Requires both accessing the node AND reading/downloading the specific file.
     * Also handles tutorial-specific command and file tracking.
     */
    private fun checkExtractData(
        session: GameSession,
        objective: ObjectiveDefinition,
        commandName: String,
        args: List<String>
    ): Boolean {
        val target = objective.target ?: return false

        // Tutorial command tracking
        if (target.startsWith("command:")) {
            val requiredCommand = target.substringAfter("command:")
            if (commandName == requiredCommand) return true
            // Handle mail alias (mail → inbox)
            if (requiredCommand == "mail" && commandName in listOf("mail", "inbox")) return true
            return false
        }

        // Tutorial file tracking
        if (target.startsWith("file:")) {
            val fileName = target.substringAfter("file:")
            // Check if player has used 'cat' on this file
            if (commandName == "cat" && args.isNotEmpty()) {
                val path = args[0]
                return path.contains(fileName) || path.endsWith(fileName) || path.endsWith("/$fileName")
            }
            return false
        }

        // Regular EXTRACT_DATA logic (file download/read)
        val targetFile = target

        // Check if the file was downloaded
        val isDownloaded = session.downloads.any { download ->
            download.path.contains(targetFile) || download.path.endsWith(targetFile)
        }

        if (isDownloaded) return true

        // Or if the file was read via cat (and command succeeded)
        if (commandName == "cat" && args.isNotEmpty()) {
            val filePath = args[0]
            // Match both exact path and filename
            val matches = filePath.contains(targetFile) ||
                         filePath.endsWith(targetFile) ||
                         filePath.endsWith("/" + targetFile)
            return matches
        }

        return false
    }

    /**
     * Validates REMAIN_UNDETECTED objectives continuously.
     * Marks objective as failed if exposure exceeds threshold.
     */
    private fun validateStealthObjectives(session: GameSession) {
        val mission = session.currentMission ?: return
        val definition = mission.definition

        // Track peak exposure
        if (session.player.exposure > mission.peakExposure) {
            mission.peakExposure = session.player.exposure
        }

        // Find all active REMAIN_UNDETECTED objectives (both required and bonus)
        val allObjectives = definition.objectives + definition.bonusObjectives
        val stealthObjectives = allObjectives.filter { objective ->
            objective.type == ObjectiveType.REMAIN_UNDETECTED &&
            objective.id !in mission.completedObjectives &&
            objective.id !in mission.bonusObjectivesCompleted &&
            objective.id !in mission.failedObjectives
        }

        for (objective in stealthObjectives) {
            val threshold = objective.threshold ?: 50 // Default 50% exposure threshold

            if (session.player.exposure > threshold) {
                mission.failedObjectives.add(objective.id)

                // Only mark stealthViolated if this is a required objective
                val isRequiredObjective = definition.objectives.any { it.id == objective.id }
                if (isRequiredObjective) {
                    mission.stealthViolated = true
                }
            }
        }
    }

    /**
     * Checks time-based objectives and marks them as failed if time limit exceeded.
     */
    private fun checkTimeBasedObjectives(session: GameSession) {
        val mission = session.currentMission ?: return
        val definition = mission.definition

        val currentTime = System.currentTimeMillis()
        val startTime = mission.startedAt
        val elapsedSeconds = (currentTime - startTime) / 1000L

        // Check objectives with time limits
        val timedObjectives = definition.objectives.filter { objective ->
            objective.timeLimit != null &&
            objective.id !in mission.completedObjectives &&
            objective.id !in mission.failedObjectives
        }

        for (objective in timedObjectives) {
            val timeLimit = objective.timeLimit?.toLong() ?: continue

            if (elapsedSeconds > timeLimit) {
                mission.failedObjectives.add(objective.id)
            }
        }
    }
}
