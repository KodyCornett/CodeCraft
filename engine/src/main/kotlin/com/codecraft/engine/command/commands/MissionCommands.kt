package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.*
import com.codecraft.engine.mission.PayoutCalculator
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.LoadoutManager

/**
 * jobs - List available job offers
 */
class JobsCommand : Command {
    override val name = "jobs"
    override val description = "List available job offers from contacts"
    override val usage = "jobs"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val availableMissions = MissionCatalog.getAvailableMissions(
            session.player.reputation,
            session.player.completedMissions
        )

        if (availableMissions.isEmpty()) {
            return CommandResult.success("""
                |╔══════════════════════════════════════════════════════╗
                |║              JOB BOARD                               ║
                |╠══════════════════════════════════════════════════════╣
                |║  No jobs currently available.                        ║
                |║  Check back later or improve your reputation.        ║
                |╚══════════════════════════════════════════════════════╝
            """.trimMargin(), delayMs = 100)
        }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              JOB BOARD                               ║")
            appendLine("╠══════════════════════════════════════════════════════╣")

            availableMissions.forEach { mission ->
                val contact = ContactCatalog.getById(mission.contactId)
                val offer = NegotiationEngine.createOffer(mission, session.player.reputation)

                val contactName = contact?.alias ?: "Unknown"
                val stars = mission.difficulty.coerceIn(0, 5)
                val difficulty = "★".repeat(stars) + "☆".repeat(5 - stars) + if (mission.difficulty > 5) "+" else ""

                appendLine("║                                                      ║")
                appendLine("║  [${mission.id}]".padEnd(55) + "║")
                appendLine("║  ${mission.title}".padEnd(55) + "║")
                appendLine("║  Contact: ${contactName.padEnd(42)}║")
                appendLine("║  Difficulty: ${difficulty.padEnd(39)}║")

                if (offer != null) {
                    appendLine("║  Offer: ${offer.currentOfferPercentage}% (§${offer.currentReward})".padEnd(55) + "║")
                } else {
                    appendLine("║  Offer: §${mission.baseReward} base".padEnd(55) + "║")
                }
            }

            appendLine("║                                                      ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Commands:                                           ║")
            appendLine("║    job info <id>      - View job details             ║")
            appendLine("║    job accept <id>    - Accept a job                 ║")
            appendLine("║    job negotiate <id> <percent> - Counter-offer      ║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 150)
    }
}

/**
 * job - Job management commands
 */
class JobCommand(
    private val repository: com.codecraft.engine.persistence.PlayerRepository? = null
) : Command {
    override val name = "job"
    override val description = "Manage job offers and negotiations"
    override val usage = "job <info|accept|negotiate> <id> [options]"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return showHelp()
        }

        return when (args[0].lowercase()) {
            "info" -> showJobInfo(session, args.getOrNull(1))
            "accept" -> acceptJob(session, args.getOrNull(1))
            "negotiate" -> negotiateJob(session, args.getOrNull(1), args.getOrNull(2))
            else -> showHelp()
        }
    }

    private fun showJobInfo(session: GameSession, missionId: String?): CommandResult {
        if (missionId == null) {
            return CommandResult.error("Usage: job info <mission_id>")
        }

        val mission = MissionCatalog.getById(missionId)
            ?: return CommandResult.error("Job not found: $missionId")

        val contact = ContactCatalog.getById(mission.contactId)
        val offer = getOrCreateOffer(session, mission)

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║  JOB DETAILS: ${mission.title}".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Contact: ${contact?.alias ?: "Unknown"}".padEnd(55) + "║")
            appendLine("║  Faction: ${contact?.faction?.replaceFirstChar { it.uppercase() } ?: "Unknown"}".padEnd(55) + "║")
            val infoStars = mission.difficulty.coerceIn(0, 5)
            val infoDifficulty = "★".repeat(infoStars) + "☆".repeat(5 - infoStars) + if (mission.difficulty > 5) "+" else ""
            appendLine("║  Difficulty: $infoDifficulty".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")

            // Briefing
            appendLine("║  BRIEFING:".padEnd(55) + "║")
            mission.briefing.lines().forEach { line ->
                if (line.length > 52) {
                    // Word wrap long lines
                    line.chunked(52).forEach { chunk ->
                        appendLine("║  ${chunk.padEnd(52)}║")
                    }
                } else {
                    appendLine("║  ${line.padEnd(52)}║")
                }
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  OBJECTIVES:".padEnd(55) + "║")
            mission.objectives.forEach { obj ->
                appendLine("║  • ${obj.description}".take(54).padEnd(55) + "║")
            }

            appendLine("╠══════════════════════════════════════════════════════╣")

            if (offer != null) {
                appendLine("║  OFFER:".padEnd(55) + "║")
                appendLine("║  Current: ${offer.currentOfferPercentage}% = §${offer.currentReward}".padEnd(55) + "║")
                appendLine("║  Base reward: §${mission.baseReward}".padEnd(55) + "║")
                if (offer.canNegotiate()) {
                    appendLine("║  Negotiation attempts: ${offer.negotiationAttempts}/${offer.maxAttempts}".padEnd(55) + "║")
                }
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  REWARDS:".padEnd(55) + "║")
            mission.reputationReward.forEach { (faction, change) ->
                val sign = if (change >= 0) "+" else ""
                appendLine("║  ${faction.replaceFirstChar { it.uppercase() }} reputation: $sign$change".padEnd(55) + "║")
            }

            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 200)
    }

    private fun acceptJob(session: GameSession, missionId: String?): CommandResult {
        if (missionId == null) {
            return CommandResult.error("Usage: job accept <mission_id>")
        }

        // Check if already on a mission
        if (session.player.activeMission != null) {
            return CommandResult.error("You already have an active mission. Complete or abandon it first.")
        }

        val mission = MissionCatalog.getById(missionId)
            ?: return CommandResult.error("Job not found: $missionId")

        // Verify availability
        val available = MissionCatalog.getAvailableMissions(
            session.player.reputation,
            session.player.completedMissions
        )
        if (mission !in available) {
            return CommandResult.error("This job is not available to you.")
        }

        val offer = getOrCreateOffer(session, mission)
            ?: return CommandResult.error("Unable to create job offer.")

        // Reset loadout states for new mission
        if (session.player.currentLoadout != null) {
            val manager = LoadoutManager(session)
            manager.resetLoadoutStates()
        }

        // Create active mission
        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = offer.currentReward
        )

        session.currentMission = activeMission
        session.player.activeMission = mission.id

        // Delete the job offer (mission is now active)
        repository?.deleteJobOffer(session.player.id, mission.id)

        // TUTORIAL TRIGGER: Unlock network commands for mission_1
        if (mission.id == "mission_1") {
            session.player.unlockCommand("scan")
            session.player.unlockCommand("connect")
            session.player.unlockCommand("disconnect")
        }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              JOB ACCEPTED                            ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${mission.title}".padEnd(55) + "║")
            appendLine("║  Agreed payment: §${activeMission.negotiatedReward}".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  OBJECTIVES:".padEnd(55) + "║")
            mission.objectives.forEach { obj ->
                appendLine("║  [ ] ${obj.description}".take(54).padEnd(55) + "║")
            }
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Type 'mission' to view progress                     ║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        // Create state changes
        var stateChanges = StateChanges(activeMission = mission.id)

        // Add tutorial event and unlocked commands for first mission
        if (mission.id == "mission_1") {
            val events = listOf(
                com.codecraft.engine.protocol.GameEvent(
                    type = "tutorial_mission_start",
                    data = mapOf("missionId" to "mission_1")
                )
            )

            // Include newly unlocked network commands in state changes
            stateChanges = stateChanges.copy(
                unlockedCommands = listOf("scan", "connect", "disconnect")
            )

            return CommandResult(
                output = output,
                success = true,
                delayMs = 200,
                stateChanges = stateChanges,
                events = events
            )
        }

        return CommandResult.withStateChange(
            output,
            stateChanges,
            delayMs = 200
        )
    }

    private fun negotiateJob(session: GameSession, missionId: String?, percentStr: String?): CommandResult {
        if (missionId == null || percentStr == null) {
            return CommandResult.error("Usage: job negotiate <mission_id> <percentage>")
        }

        val requestedPercent = percentStr.toIntOrNull()
            ?: return CommandResult.error("Invalid percentage: $percentStr")

        if (requestedPercent < 1 || requestedPercent > 100) {
            return CommandResult.error("Percentage must be between 1 and 100")
        }

        val mission = MissionCatalog.getById(missionId)
            ?: return CommandResult.error("Job not found: $missionId")

        val contact = ContactCatalog.getById(mission.contactId)
        val playerRep = session.player.reputation[contact?.faction] ?: 0

        var offer = getOrCreateOffer(session, mission)
            ?: return CommandResult.error("Unable to negotiate this job.")

        if (!offer.canNegotiate()) {
            return CommandResult.error("No further negotiation possible for this job.")
        }

        val (newOffer, result) = NegotiationEngine.negotiate(offer, requestedPercent, playerRep)
        saveOffer(session, newOffer)

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              NEGOTIATION                             ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  You: \"I want ${requestedPercent}% of the cut.\"".padEnd(55) + "║")
            appendLine("║".padEnd(56) + "║")

            when (result) {
                is NegotiationResult.Accepted -> {
                    appendLine("║  ${contact?.alias ?: "Contact"}: \"Deal.\"".padEnd(55) + "║")
                    appendLine("╠══════════════════════════════════════════════════════╣")
                    appendLine("║  Offer accepted: ${result.newPercentage}% = §${result.newReward}".padEnd(55) + "║")
                    appendLine("║  Use 'job accept $missionId' to start the mission.".padEnd(55) + "║")
                }
                is NegotiationResult.CounterOffer -> {
                    appendLine("║  ${contact?.alias ?: "Contact"}:".padEnd(55) + "║")
                    result.message.chunked(50).forEach { chunk ->
                        appendLine("║    $chunk".padEnd(55) + "║")
                    }
                    appendLine("╠══════════════════════════════════════════════════════╣")
                    appendLine("║  Counter-offer: ${result.counterPercentage}% = §${result.counterReward}".padEnd(55) + "║")
                    if (newOffer.canNegotiate()) {
                        appendLine("║  Attempts remaining: ${newOffer.maxAttempts - newOffer.negotiationAttempts}".padEnd(55) + "║")
                    } else {
                        appendLine("║  This is their final offer.".padEnd(55) + "║")
                    }
                }
                is NegotiationResult.Rejected -> {
                    appendLine("║  ${result.message}".padEnd(55) + "║")
                }
                is NegotiationResult.WalkedAway -> {
                    appendLine("║  ${contact?.alias ?: "Contact"}:".padEnd(55) + "║")
                    result.message.chunked(50).forEach { chunk ->
                        appendLine("║    $chunk".padEnd(55) + "║")
                    }
                    appendLine("╠══════════════════════════════════════════════════════╣")
                    appendLine("║  The contact has walked away. Job no longer          ║")
                    appendLine("║  available through this contact.                     ║")
                }
            }

            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 200)
    }

    private fun showHelp(): CommandResult {
        val output = """
            |JOB COMMANDS
            |─────────────────────────────────────────
            |  jobs                    List available jobs
            |  job info <id>           View job details
            |  job accept <id>         Accept a job at current offer
            |  job negotiate <id> <%>  Counter-offer a percentage
            |
            |NEGOTIATION TIPS
            |─────────────────────────────────────────
            |  • Higher reputation = better starting offers
            |  • You have 3 negotiation attempts per job
            |  • Asking too much may cause contact to walk away
            |  • Build trust by completing jobs successfully
        """.trimMargin()
        return CommandResult.success(output, delayMs = 100)
    }

    private fun getOrCreateOffer(session: GameSession, mission: MissionDefinition): JobOffer? {
        // Try to load from repository first
        var offer = repository?.loadJobOffer(session.player.id, mission.id)

        // If not found or no repository, create new offer
        if (offer == null) {
            offer = NegotiationEngine.createOffer(mission, session.player.reputation)
            if (offer != null && repository != null) {
                repository.saveJobOffer(session.player.id, offer)
            }
        }

        return offer
    }

    private fun saveOffer(session: GameSession, offer: JobOffer) {
        repository?.saveJobOffer(session.player.id, offer)
    }
}

/**
 * mission - View current mission status
 */
class MissionCommand : Command {
    override val name = "mission"
    override val description = "View current mission status and objectives"
    override val usage = "mission [complete|abandon]"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        return when (args.firstOrNull()) {
            "complete" -> completeMission(session)
            "abandon" -> abandonMission(session)
            else -> showMissionStatus(session)
        }
    }

    private fun showMissionStatus(session: GameSession): CommandResult {

        val activeMission = session.currentMission
            ?: return CommandResult.success("""
                |╔══════════════════════════════════════════════════════╗
                |║              MISSION STATUS                         ║
                |╠══════════════════════════════════════════════════════╣
                |║  No active mission.                                  ║
                |║  Use 'jobs' to see available work.                   ║
                |╚══════════════════════════════════════════════════════╝
            """.trimMargin(), delayMs = 100)

        val mission = activeMission.definition
        val contact = ContactCatalog.getById(mission.contactId)

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              ACTIVE MISSION                          ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${mission.title}".padEnd(55) + "║")
            appendLine("║  Contact: ${contact?.alias ?: "Unknown"}".padEnd(55) + "║")
            appendLine("║  Reward: §${activeMission.negotiatedReward}".padEnd(55) + "║")

            // Mission time tracking
            val currentTime = System.currentTimeMillis()
            val elapsedSeconds = (currentTime - activeMission.startedAt) / 1000
            val elapsedMinutes = elapsedSeconds / 60
            appendLine("║  Elapsed: ${elapsedMinutes}m ${elapsedSeconds % 60}s".padEnd(55) + "║")

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  OBJECTIVES:".padEnd(55) + "║")

            mission.objectives.forEach { obj ->
                val status = when {
                    obj.id in activeMission.completedObjectives -> "[✓]"
                    obj.id in activeMission.failedObjectives -> "[✗]"
                    else -> "[ ]"
                }

                var line = "║  $status ${obj.description}".take(54)

                // Show additional info for special objective types
                when (obj.type) {
                    ObjectiveType.REMAIN_UNDETECTED -> {
                        val threshold = obj.threshold ?: 50
                        val current = session.player.exposure.toInt()
                        val statusStr = if (current < threshold) "OK" else "VIOLATED"
                        line = "║  $status ${obj.description} ($current%/$threshold% - $statusStr)".take(54)
                    }
                    else -> {
                        // Check if this objective has a time limit
                        obj.timeLimit?.let { limit ->
                            val remaining = limit - elapsedSeconds
                            if (remaining > 0) {
                                line = "║  $status ${obj.description} [${remaining}s left]".take(54)
                            } else {
                                line = "║  $status ${obj.description} [TIME EXPIRED]".take(54)
                            }
                        }
                    }
                }

                appendLine(line.padEnd(55) + "║")
            }

            // Show bonus objectives if any
            if (mission.bonusObjectives.isNotEmpty()) {
                appendLine("║".padEnd(56) + "║")
                appendLine("║  BONUS OBJECTIVES (Optional):".padEnd(55) + "║")
                mission.bonusObjectives.forEach { obj ->
                    val status = when {
                        obj.id in activeMission.bonusObjectivesCompleted -> "[✓]"
                        obj.id in activeMission.failedObjectives -> "[✗]"
                        else -> "[ ]"
                    }
                    appendLine("║  $status ${obj.description}".take(54).padEnd(55) + "║")
                }
            }

            val completed = activeMission.completedObjectives.size
            val total = mission.objectives.size
            val progress = if (total > 0) (completed * 100) / total else 0

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Progress: $completed/$total objectives ($progress%)".padEnd(55) + "║")

            // Show stealth analytics
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  STEALTH ANALYTICS:".padEnd(55) + "║")

            val currentExposure = session.player.exposure.toInt()
            val peakExposure = activeMission.peakExposure.toInt()
            val stealthRating = com.codecraft.engine.domain.Detection.getStealthRating(session.player.exposure)

            appendLine("║  Current Exposure: $currentExposure%".padEnd(55) + "║")
            appendLine("║  Peak Exposure: $peakExposure%".padEnd(55) + "║")
            appendLine("║  Stealth Rating: $stealthRating".padEnd(55) + "║")

            // Count alarms triggered across all nodes
            val alarmsTriggered = session.network.getAllNodes().sumOf { it.alarmTriggeredCount }
            if (alarmsTriggered > 0) {
                appendLine("║  Alarms Triggered: $alarmsTriggered".padEnd(55) + "║")
            }

            // Show if currently on an alarmed node
            val connectedNode = session.connectedNode
            if (connectedNode != null && connectedNode.alarmActive) {
                val remainingSeconds = ((connectedNode.alarmExpiresAt ?: 0) - System.currentTimeMillis()) / 1000
                appendLine("║  ⚠ CURRENT NODE ON ALERT (${remainingSeconds}s remaining)".padEnd(55) + "║")
            }

            // Show stealth status for missions with stealth requirements
            if (mission.objectives.any { it.type == ObjectiveType.REMAIN_UNDETECTED }) {
                val stealthStatus = if (activeMission.stealthViolated) "VIOLATED" else "MAINTAINED"
                appendLine("║  Stealth Requirement: $stealthStatus".padEnd(55) + "║")
            }

            // Show detection count
            if (activeMission.detectionCount > 0) {
                val limit = 3
                val remaining = limit - activeMission.detectionCount
                appendLine("║  Detections: ${activeMission.detectionCount}/$limit ($remaining before auto-fail)".padEnd(55) + "║")
            }

            // Mission status (complete or failed)
            appendLine("╠══════════════════════════════════════════════════════╣")
            when {
                activeMission.isFailed() -> {
                    appendLine("║  ✗ MISSION FAILED                                    ║")
                    appendLine("║  Reason: ${activeMission.failureReason}".take(54).padEnd(55) + "║")
                }
                activeMission.isComplete() -> {
                    appendLine("║  ✓ MISSION COMPLETE - Rewards pending                ║")
                }
                else -> {
                    appendLine("║  Mission in progress...                              ║")
                }
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            when {
                activeMission.isFailed() -> {
                    appendLine("║  'mission abandon' to end failed mission             ║")
                }
                activeMission.isComplete() -> {
                    appendLine("║  'mission complete' to claim rewards                 ║")
                }
                else -> {
                    appendLine("║  'mission abandon' to abort (reputation penalty)     ║")
                }
            }
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 150)
    }

    private fun completeMission(session: GameSession): CommandResult {
        val activeMission = session.currentMission
            ?: return CommandResult.error("No active mission to complete.")

        // Check if mission has failed
        if (activeMission.isFailed()) {
            return CommandResult.error("Mission has failed: ${activeMission.failureReason}\nUse 'mission abandon' to end the failed mission.")
        }

        // Check if all required objectives are complete
        if (!activeMission.isComplete()) {
            return CommandResult.error("Mission not complete. ${activeMission.getIncompleteObjectives().size} objectives remaining.")
        }

        val mission = activeMission.definition
        val contact = ContactCatalog.getById(mission.contactId)

        // Calculate payout with bonuses/penalties
        val payoutResult = PayoutCalculator.calculatePayout(activeMission, session.player)

        // Award credits
        session.player.earnCredits(payoutResult.finalPayout)
        activeMission.finalPayout = payoutResult.finalPayout

        // Award reputation
        mission.reputationReward.forEach { (faction, change) ->
            val current = session.player.reputation[faction] ?: 0
            session.player.reputation[faction] = (current + change).coerceIn(-100, 100)
        }

        // Add base reputation gain from payout calculator
        if (payoutResult.reputationGain > 0) {
            val faction = contact?.faction ?: "underground"
            val current = session.player.reputation[faction] ?: 0
            session.player.reputation[faction] = (current + payoutResult.reputationGain).coerceIn(-100, 100)
        }

        // Record completed mission
        session.player.completedMissions.add(mission.id)

        // Update statistics
        session.player.totalCreditsEarned += payoutResult.finalPayout

        // Clear active mission
        session.currentMission = null
        session.player.activeMission = null

        // Build output with payout breakdown
        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              MISSION COMPLETE                        ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${mission.title}".padEnd(55) + "║")
            appendLine("║  Contact: ${contact?.alias ?: "Unknown"}".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")

            // Mission statistics
            val elapsedSeconds = (System.currentTimeMillis() - activeMission.startedAt) / 1000
            val elapsedMinutes = elapsedSeconds / 60
            appendLine("║  MISSION STATISTICS:".padEnd(55) + "║")
            appendLine("║  Time: ${elapsedMinutes}m ${elapsedSeconds % 60}s".padEnd(55) + "║")
            appendLine("║  Peak Exposure: ${activeMission.peakExposure.toInt()}%".padEnd(55) + "║")
            appendLine("║  Objectives: ${activeMission.completedObjectives.size}/${mission.objectives.size}".padEnd(55) + "║")
            if (mission.bonusObjectives.isNotEmpty()) {
                appendLine("║  Bonus: ${activeMission.bonusObjectivesCompleted.size}/${mission.bonusObjectives.size}".padEnd(55) + "║")
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  PAYOUT BREAKDOWN:".padEnd(55) + "║")
            appendLine("║  Base reward: §${payoutResult.baseReward}".padEnd(55) + "║")

            if (payoutResult.bonuses.isNotEmpty()) {
                appendLine("║".padEnd(56) + "║")
                appendLine("║  BONUSES:".padEnd(55) + "║")
                payoutResult.bonuses.forEach { (reason, amount) ->
                    appendLine("║    +§$amount - $reason".padEnd(55) + "║")
                }
            }

            if (payoutResult.penalties.isNotEmpty()) {
                appendLine("║".padEnd(56) + "║")
                appendLine("║  PENALTIES:".padEnd(55) + "║")
                payoutResult.penalties.forEach { (reason, amount) ->
                    appendLine("║    -§$amount - $reason".padEnd(55) + "║")
                }
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  FINAL PAYOUT: §${payoutResult.finalPayout}".padEnd(55) + "║")
            appendLine("║  New balance: §${session.player.credits}".padEnd(55) + "║")

            if (mission.reputationReward.isNotEmpty() || payoutResult.reputationGain > 0) {
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  REPUTATION CHANGES:".padEnd(55) + "║")
                mission.reputationReward.forEach { (faction, change) ->
                    val sign = if (change >= 0) "+" else ""
                    appendLine("║    ${faction.replaceFirstChar { it.uppercase() }}: $sign$change".padEnd(55) + "║")
                }
                if (payoutResult.reputationGain > 0) {
                    val faction = contact?.faction ?: "underground"
                    appendLine("║    ${faction.replaceFirstChar { it.uppercase() }}: +${payoutResult.reputationGain} (performance)".padEnd(55) + "║")
                }
            }

            // Show unlocked missions if any
            if (mission.unlocksMissions.isNotEmpty()) {
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  NEW JOBS AVAILABLE:".padEnd(55) + "║")
                mission.unlocksMissions.forEach { unlockedId ->
                    val unlocked = MissionCatalog.getById(unlockedId)
                    if (unlocked != null) {
                        appendLine("║    • ${unlocked.title}".take(54).padEnd(55) + "║")
                    }
                }
            }

            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.withStateChange(
            output,
            StateChanges(
                credits = session.player.credits,
                activeMission = null,
                reputation = session.player.reputation
            ),
            delayMs = 300
        )
    }

    private fun abandonMission(session: GameSession): CommandResult {
        val activeMission = session.currentMission
            ?: return CommandResult.error("No active mission to abandon.")

        val mission = activeMission.definition
        val contact = ContactCatalog.getById(mission.contactId)
        val faction = contact?.faction ?: "underground"

        // Apply reputation penalty
        val penalty = 5 + mission.difficulty * 2
        val currentRep = session.player.reputation[faction] ?: 0
        session.player.reputation[faction] = (currentRep - penalty).coerceAtLeast(-100)

        // Clear mission
        session.currentMission = null
        session.player.activeMission = null

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              MISSION ABANDONED                       ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${mission.title}".padEnd(55) + "║")
            appendLine("║  Reputation penalty: -$penalty with ${faction.replaceFirstChar { it.uppercase() }}".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${contact?.alias ?: "Contact"} won't be happy about this...".padEnd(55) + "║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.withStateChange(
            output,
            StateChanges(activeMission = null),
            delayMs = 200
        )
    }
}

/**
 * status - Show player status including reputation
 */
class StatusCommand : Command {
    override val name = "status"
    override val description = "View your hacker profile and reputation"
    override val usage = "status"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val player = session.player
        val machine = player.currentMachine
        val shieldStr = if (session.isShieldActive()) "ACTIVE" else "inactive"

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              HACKER PROFILE                          ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ID: ${player.id.take(40)}".padEnd(55) + "║")
            appendLine("║  Credits: §${player.credits}".padEnd(55) + "║")
            appendLine("║  Exposure: ${player.exposure.toInt()}%".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  MACHINE: ${machine.machineType.name}".padEnd(55) + "║")
            appendLine("║  Firewall: ${machine.firewallCurrent}/${machine.firewallMax} (${machine.damageState.label})".padEnd(55) + "║")
            appendLine("║  Shield: $shieldStr".padEnd(55) + "║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  REPUTATION:".padEnd(55) + "║")

            player.reputation.forEach { (faction, rep) ->
                val bar = renderRepBar(rep)
                val label = getRepLabel(rep)
                appendLine("║  ${faction.replaceFirstChar { it.uppercase() }.padEnd(12)} $bar $label".padEnd(55) + "║")
            }

            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  STATISTICS:".padEnd(55) + "║")
            appendLine("║  Completed Missions: ${player.completedMissions.size}".padEnd(55) + "║")
            appendLine("║  Total Hacks: ${player.totalHacks} (${player.successfulHacks} successful)".padEnd(55) + "║")
            appendLine("║  Total Earned: §${player.totalCreditsEarned}".padEnd(55) + "║")
            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 100)
    }

    private fun renderRepBar(rep: Int): String {
        // Rep ranges from -100 to 100
        // Bar shows position on scale
        val normalized = (rep + 100) / 10  // 0 to 20
        val leftEmpty = normalized.coerceAtMost(10)
        val rightFilled = (normalized - 10).coerceAtLeast(0)
        val rightEmpty = 10 - rightFilled

        return "[-" + "░".repeat(10 - leftEmpty) + "█".repeat(leftEmpty.coerceAtMost(10)) +
                "|" + "█".repeat(rightFilled) + "░".repeat(rightEmpty) + "+]"
    }

    private fun getRepLabel(rep: Int): String {
        return when {
            rep >= 75 -> "Legendary"
            rep >= 50 -> "Trusted"
            rep >= 25 -> "Respected"
            rep >= 0 -> "Neutral"
            rep >= -25 -> "Suspicious"
            rep >= -50 -> "Untrusted"
            else -> "Hostile"
        }
    }
}
