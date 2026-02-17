package com.codecraft.engine.persistence

import com.codecraft.engine.domain.*
import com.codecraft.engine.puzzle.CombatType
import com.codecraft.engine.puzzle.PlayerPuzzleProgress
import com.codecraft.engine.session.*
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.SqlExpressionBuilder.less
import org.jetbrains.exposed.sql.transactions.transaction

/**
 * Repository for persisting player/session state
 */
class PlayerRepository {
    private val json = Json { ignoreUnknownKeys = true }

    fun playerExists(playerId: String): Boolean {
        return transaction {
            PlayersTable.selectAll().where { PlayersTable.id eq playerId }.count() > 0
        }
    }

    fun savePlayer(session: GameSession) {
        transaction {
            val player = session.player
            val exists = PlayersTable.selectAll().where { PlayersTable.id eq session.sessionId }.count() > 0

            if (exists) {
                PlayersTable.update({ PlayersTable.id eq session.sessionId }) {
                    it[credits] = player.credits
                    it[exposure] = player.exposure
                    it[currentPath] = session.currentPath
                    it[connectedTo] = session.connectedNode?.id
                    it[activeMission] = player.activeMission
                    it[gamePhase] = player.currentGamePhase.name
                    it[machineType] = player.currentMachine.machineType.name
                    it[firewallCurrent] = player.currentMachine.firewallCurrent
                    it[firewallMax] = player.currentMachine.firewallMax
                    it[residualExposure] = player.currentMachine.residualExposure
                    it[storageLevel] = player.currentMachine.storageLevel
                    it[damageLevel] = player.currentMachine.damageState.name
                    it[shieldActive] = session.shieldActive
                    it[shieldExpiresAt] = session.shieldExpiresAt
                    it[firewallStatus] = session.firewallStatus
                    it[unlockedCommands] = json.encodeToString(player.unlockedCommands.toList())
                    it[discoveredNodes] = json.encodeToString(player.discoveredNodes.toList())
                    it[scannedNodes] = json.encodeToString(player.scannedNodes.toList())
                    it[completedMissions] = json.encodeToString(player.completedMissions.toList())
                    it[ownedTools] = json.encodeToString(player.ownedTools.toList())
                    it[ownedExploits] = json.encodeToString(player.ownedExploits.toList())
                    it[ownedUpgrades] = json.encodeToString(player.ownedUpgrades.toList())
                    it[loadoutData] = player.currentLoadout?.let { loadout -> json.encodeToString(loadout) }
                    it[nodeAccess] = json.encodeToString(session.nodeAccess.toList())
                    it[reputation] = json.encodeToString(player.reputation)
                    it[downloads] = json.encodeToString(session.downloads.toList())
                    it[connectionHistory] = json.encodeToString(session.connectionHistory.toList())
                    it[totalHacks] = player.totalHacks
                    it[successfulHacks] = player.successfulHacks
                    it[failedHacks] = player.failedHacks
                    it[totalCreditsEarned] = player.totalCreditsEarned
                    it[totalCreditsSpent] = player.totalCreditsSpent
                    it[lastActionTime] = session.lastActionTime
                    it[updatedAt] = System.currentTimeMillis()
                    // Puzzle Revamp 1.0
                    it[threatPressure] = session.threatPressure
                    it[systemIntegrity] = session.systemIntegrity
                    it[activeCombatType] = session.activeCombatType?.name
                    it[puzzleProgress] = json.encodeToString(session.puzzleProgress)
                    it[recentPuzzleTypes] = json.encodeToString(session.recentPuzzleTypes.toList().map { t -> t.name })
                }
            } else {
                PlayersTable.insert {
                    it[id] = session.sessionId
                    it[credits] = player.credits
                    it[exposure] = player.exposure
                    it[currentPath] = session.currentPath
                    it[connectedTo] = session.connectedNode?.id
                    it[activeMission] = player.activeMission
                    it[gamePhase] = player.currentGamePhase.name
                    it[machineType] = player.currentMachine.machineType.name
                    it[firewallCurrent] = player.currentMachine.firewallCurrent
                    it[firewallMax] = player.currentMachine.firewallMax
                    it[residualExposure] = player.currentMachine.residualExposure
                    it[storageLevel] = player.currentMachine.storageLevel
                    it[damageLevel] = player.currentMachine.damageState.name
                    it[shieldActive] = session.shieldActive
                    it[shieldExpiresAt] = session.shieldExpiresAt
                    it[firewallStatus] = session.firewallStatus
                    it[unlockedCommands] = json.encodeToString(player.unlockedCommands.toList())
                    it[discoveredNodes] = json.encodeToString(player.discoveredNodes.toList())
                    it[scannedNodes] = json.encodeToString(player.scannedNodes.toList())
                    it[completedMissions] = json.encodeToString(player.completedMissions.toList())
                    it[ownedTools] = json.encodeToString(player.ownedTools.toList())
                    it[ownedExploits] = json.encodeToString(player.ownedExploits.toList())
                    it[ownedUpgrades] = json.encodeToString(player.ownedUpgrades.toList())
                    it[loadoutData] = player.currentLoadout?.let { loadout -> json.encodeToString(loadout) }
                    it[nodeAccess] = json.encodeToString(session.nodeAccess.toList())
                    it[reputation] = json.encodeToString(player.reputation)
                    it[downloads] = json.encodeToString(session.downloads.toList())
                    it[connectionHistory] = json.encodeToString(session.connectionHistory.toList())
                    it[totalHacks] = player.totalHacks
                    it[successfulHacks] = player.successfulHacks
                    it[failedHacks] = player.failedHacks
                    it[totalCreditsEarned] = player.totalCreditsEarned
                    it[totalCreditsSpent] = player.totalCreditsSpent
                    it[lastActionTime] = session.lastActionTime
                    it[createdAt] = System.currentTimeMillis()
                    it[updatedAt] = System.currentTimeMillis()
                    // Puzzle Revamp 1.0
                    it[threatPressure] = session.threatPressure
                    it[systemIntegrity] = session.systemIntegrity
                    it[activeCombatType] = session.activeCombatType?.name
                    it[puzzleProgress] = json.encodeToString(session.puzzleProgress)
                    it[recentPuzzleTypes] = json.encodeToString(session.recentPuzzleTypes.toList().map { t -> t.name })
                }
            }
        }
    }

    fun loadPlayer(playerId: String): GameSession? {
        return transaction {
            val row = PlayersTable.selectAll().where { PlayersTable.id eq playerId }.firstOrNull()
                ?: return@transaction null

            val player = Player(
                id = playerId,
                credits = row[PlayersTable.credits],
                exposure = row[PlayersTable.exposure],
                currentMachine = MachineState(
                    machineType = MachineType.valueOf(row[PlayersTable.machineType]),
                    firewallCurrent = row[PlayersTable.firewallCurrent],
                    firewallMax = row[PlayersTable.firewallMax],
                    residualExposure = row[PlayersTable.residualExposure],
                    storageLevel = row[PlayersTable.storageLevel]
                ),
                currentGamePhase = try {
                    GamePhase.valueOf(row[PlayersTable.gamePhase])
                } catch (_: Exception) {
                    GamePhase.HOME_NODE
                },
                activeMission = row[PlayersTable.activeMission],
                totalHacks = row[PlayersTable.totalHacks],
                successfulHacks = row[PlayersTable.successfulHacks],
                failedHacks = row[PlayersTable.failedHacks],
                totalCreditsEarned = row[PlayersTable.totalCreditsEarned],
                totalCreditsSpent = row[PlayersTable.totalCreditsSpent]
            )

            // Restore collections
            json.decodeFromString<List<String>>(row[PlayersTable.unlockedCommands]).forEach {
                player.unlockedCommands.add(it)
            }
            json.decodeFromString<List<String>>(row[PlayersTable.discoveredNodes]).forEach {
                player.discoveredNodes.add(it)
            }
            json.decodeFromString<List<String>>(row[PlayersTable.scannedNodes]).forEach {
                player.scannedNodes.add(it)
            }
            json.decodeFromString<List<String>>(row[PlayersTable.completedMissions]).forEach {
                player.completedMissions.add(it)
            }

            // Migrate old ownedTools format (List<String>) to new format (List<OwnedTool>)
            val ownedToolsJson = row[PlayersTable.ownedTools]
            if (ownedToolsJson.startsWith("[\"") || ownedToolsJson == "[]") {
                // Old format: ["traceblock", "ghostmode"] or empty
                // For now, just skip migration - new tools must be purchased
                // Old string-based tools were command unlockers, not loadout tools
            } else {
                // New format: [{"type":"CLOAK","version":"V1","category":"PERMANENT"}]
                try {
                    json.decodeFromString<List<OwnedTool>>(ownedToolsJson).forEach {
                        player.ownedTools.add(it)
                    }
                } catch (e: Exception) {
                    // Fallback to empty on parse error
                    println("[PlayerRepository] Failed to parse ownedTools: ${e.message}")
                }
            }

            json.decodeFromString<List<String>>(row[PlayersTable.ownedExploits]).forEach {
                player.ownedExploits.add(it)
            }
            json.decodeFromString<List<String>>(row[PlayersTable.ownedUpgrades]).forEach {
                player.ownedUpgrades.add(it)
            }
            json.decodeFromString<Map<String, Int>>(row[PlayersTable.reputation]).forEach { (k, v) ->
                player.reputation[k] = v
            }

            // Create session and restore session-level state
            val session = GameSession(playerId, player)
            session.currentPath = row[PlayersTable.currentPath]
            session.shieldActive = row[PlayersTable.shieldActive]
            session.shieldExpiresAt = row[PlayersTable.shieldExpiresAt]
            session.firewallStatus = row[PlayersTable.firewallStatus]
            session.lastActionTime = row[PlayersTable.lastActionTime]

            // Restore node access
            json.decodeFromString<List<String>>(row[PlayersTable.nodeAccess]).forEach {
                session.nodeAccess.add(it)
            }

            // Restore downloads
            json.decodeFromString<List<DownloadRecord>>(row[PlayersTable.downloads]).forEach {
                session.downloads.add(it)
            }

            // Restore connection history
            json.decodeFromString<List<ConnectionTrace>>(row[PlayersTable.connectionHistory]).forEach {
                session.connectionHistory.add(it)
            }

            // Restore loadout
            val loadoutJson = row[PlayersTable.loadoutData]
            if (loadoutJson != null) {
                try {
                    player.currentLoadout = json.decodeFromString<Loadout>(loadoutJson)
                } catch (e: Exception) {
                    println("[PlayerRepository] Failed to parse loadout: ${e.message}")
                }
            }

            // Restore Puzzle Revamp 1.0 fields
            try {
                val savedThreatPressure = row.getOrNull(PlayersTable.threatPressure)
                if (savedThreatPressure != null) session.threatPressure = savedThreatPressure
            } catch (_: Exception) {}

            try {
                val savedSystemIntegrity = row.getOrNull(PlayersTable.systemIntegrity)
                if (savedSystemIntegrity != null) session.systemIntegrity = savedSystemIntegrity
            } catch (_: Exception) {}

            try {
                val savedCombatType = row.getOrNull(PlayersTable.activeCombatType)
                if (savedCombatType != null) {
                    session.activeCombatType = CombatType.valueOf(savedCombatType)
                }
            } catch (_: Exception) {}

            try {
                val savedProgress = row.getOrNull(PlayersTable.puzzleProgress)
                if (savedProgress != null) {
                    session.puzzleProgress = json.decodeFromString<PlayerPuzzleProgress>(savedProgress)
                }
            } catch (e: Exception) {
                println("[PlayerRepository] Failed to parse puzzleProgress: ${e.message}")
            }

            try {
                val savedRecentTypes = row.getOrNull(PlayersTable.recentPuzzleTypes)
                if (savedRecentTypes != null) {
                    json.decodeFromString<List<String>>(savedRecentTypes).forEach { typeName ->
                        try {
                            val puzzleType = com.codecraft.engine.domain.PuzzleType.valueOf(typeName)
                            session.recordPuzzleType(puzzleType)
                        } catch (_: Exception) {}
                    }
                }
            } catch (e: Exception) {
                println("[PlayerRepository] Failed to parse recentPuzzleTypes: ${e.message}")
            }

            // Reconnect to node if applicable
            val connectedTo = row[PlayersTable.connectedTo]
            if (connectedTo != null) {
                session.connectTo(connectedTo)
            }

            session
        }
    }

    fun saveAfterCommand(session: GameSession) {
        // For now, do a full save. Could be optimized to only save changed fields.
        savePlayer(session)
    }

    fun recordTransaction(playerId: String, type: String, amount: Int, balanceAfter: Int, metadata: Map<String, String> = emptyMap()) {
        transaction {
            TransactionsTable.insert {
                it[TransactionsTable.playerId] = playerId
                it[TransactionsTable.type] = type
                it[TransactionsTable.amount] = amount
                it[TransactionsTable.balanceAfter] = balanceAfter
                it[TransactionsTable.createdAt] = System.currentTimeMillis()
                it[TransactionsTable.metadata] = json.encodeToString(metadata)
            }
        }
    }

    fun recordMissionCompletion(
        playerId: String,
        missionId: String,
        startedAt: Long,
        success: Boolean,
        finalExposure: Double,
        payout: Int,
        objectivesCompleted: List<String>
    ) {
        transaction {
            MissionHistoryTable.insert {
                it[MissionHistoryTable.playerId] = playerId
                it[MissionHistoryTable.missionId] = missionId
                it[MissionHistoryTable.startedAt] = startedAt
                it[completedAt] = System.currentTimeMillis()
                it[MissionHistoryTable.success] = success
                it[MissionHistoryTable.finalExposure] = finalExposure
                it[MissionHistoryTable.payout] = payout
                it[MissionHistoryTable.objectivesCompleted] = json.encodeToString(objectivesCompleted)
            }
        }
    }

    // ========================================
    // Job Offer Persistence
    // ========================================

    /**
     * Save a job offer to the database
     */
    fun saveJobOffer(playerId: String, offer: JobOffer) {
        transaction {
            val id = "${playerId}_${offer.mission.id}"
            val now = System.currentTimeMillis()
            val expiresAt = now + (7 * 24 * 60 * 60 * 1000L) // 7 days from now

            val exists = JobOffersTable.selectAll()
                .where { JobOffersTable.id eq id }
                .count() > 0

            if (exists) {
                JobOffersTable.update({ JobOffersTable.id eq id }) {
                    it[currentOfferPercentage] = offer.currentOfferPercentage
                    it[negotiationAttempts] = offer.negotiationAttempts
                    it[rejected] = offer.rejected
                    it[updatedAt] = now
                }
            } else {
                JobOffersTable.insert {
                    it[JobOffersTable.id] = id
                    it[JobOffersTable.playerId] = playerId
                    it[missionId] = offer.mission.id
                    it[contactId] = offer.contact.id
                    it[baseOfferPercentage] = offer.baseOfferPercentage
                    it[currentOfferPercentage] = offer.currentOfferPercentage
                    it[maxOfferPercentage] = offer.maxOfferPercentage
                    it[negotiationAttempts] = offer.negotiationAttempts
                    it[maxAttempts] = offer.maxAttempts
                    it[rejected] = offer.rejected
                    it[createdAt] = now
                    it[updatedAt] = now
                    it[JobOffersTable.expiresAt] = expiresAt
                }
            }
        }
    }

    /**
     * Load a specific job offer
     */
    fun loadJobOffer(playerId: String, missionId: String): JobOffer? {
        return transaction {
            val id = "${playerId}_${missionId}"
            val row = JobOffersTable.selectAll()
                .where { JobOffersTable.id eq id }
                .firstOrNull() ?: return@transaction null

            // Check if expired
            val now = System.currentTimeMillis()
            if (row[JobOffersTable.expiresAt] < now) {
                // Offer expired, delete it
                JobOffersTable.deleteWhere { JobOffersTable.id eq id }
                return@transaction null
            }

            // Reconstruct JobOffer
            val mission = MissionCatalog.getById(row[JobOffersTable.missionId]) ?: return@transaction null
            val contact = ContactCatalog.getById(row[JobOffersTable.contactId]) ?: return@transaction null

            JobOffer(
                mission = mission,
                contact = contact,
                baseOfferPercentage = row[JobOffersTable.baseOfferPercentage],
                currentOfferPercentage = row[JobOffersTable.currentOfferPercentage],
                maxOfferPercentage = row[JobOffersTable.maxOfferPercentage],
                negotiationAttempts = row[JobOffersTable.negotiationAttempts],
                maxAttempts = row[JobOffersTable.maxAttempts],
                rejected = row[JobOffersTable.rejected]
            )
        }
    }

    /**
     * Load all active job offers for a player
     */
    fun loadAllJobOffers(playerId: String): List<JobOffer> {
        return transaction {
            val now = System.currentTimeMillis()

            JobOffersTable.selectAll()
                .where { JobOffersTable.playerId eq playerId }
                .mapNotNull { row ->
                    // Skip expired offers
                    if (row[JobOffersTable.expiresAt] < now) {
                        return@mapNotNull null
                    }

                    val mission = MissionCatalog.getById(row[JobOffersTable.missionId]) ?: return@mapNotNull null
                    val contact = ContactCatalog.getById(row[JobOffersTable.contactId]) ?: return@mapNotNull null

                    JobOffer(
                        mission = mission,
                        contact = contact,
                        baseOfferPercentage = row[JobOffersTable.baseOfferPercentage],
                        currentOfferPercentage = row[JobOffersTable.currentOfferPercentage],
                        maxOfferPercentage = row[JobOffersTable.maxOfferPercentage],
                        negotiationAttempts = row[JobOffersTable.negotiationAttempts],
                        maxAttempts = row[JobOffersTable.maxAttempts],
                        rejected = row[JobOffersTable.rejected]
                    )
                }
        }
    }

    /**
     * Delete a job offer
     */
    fun deleteJobOffer(playerId: String, missionId: String) {
        transaction {
            val id = "${playerId}_${missionId}"
            JobOffersTable.deleteWhere { JobOffersTable.id eq id }
        }
    }

    /**
     * Clean up expired job offers
     */
    fun cleanupExpiredOffers() {
        transaction {
            val now = System.currentTimeMillis()
            val deleted = JobOffersTable.deleteWhere { JobOffersTable.expiresAt less now }
            if (deleted > 0) {
                println("[Repository] Cleaned up $deleted expired job offers")
            }
        }
    }
}
