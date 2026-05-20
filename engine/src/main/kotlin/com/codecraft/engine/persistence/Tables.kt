package com.codecraft.engine.persistence

import org.jetbrains.exposed.sql.Table

/**
 * Players table — core player state
 */
object PlayersTable : Table("players") {
    val id = varchar("id", 128)
    val credits = integer("credits").default(5000)
    val exposure = double("exposure").default(0.0)
    val currentNode = varchar("current_node", 64).default("localhost")
    val currentPath = varchar("current_path", 256).default("/home/user")
    val connectedTo = varchar("connected_to", 64).nullable()
    val activeMission = varchar("active_mission", 64).nullable()
    val gamePhase = varchar("game_phase", 32).default("HOME_NODE")
    val tutorialCompleted = bool("tutorial_completed").default(false)

    // Machine state (embedded)
    val machineType = varchar("machine_type", 32).default("GREYBOX")
    val firewallCurrent = integer("firewall_current").default(100)
    val firewallMax = integer("firewall_max").default(100)
    val residualExposure = integer("residual_exposure").default(0)
    val storageLevel = integer("storage_level").default(1)
    val damageLevel = varchar("damage_level", 32).default("PRISTINE")

    // Shield
    val shieldActive = bool("shield_active").default(false)
    val shieldExpiresAt = long("shield_expires_at").nullable()

    // Firewall status
    val firewallStatus = varchar("firewall_status", 32).default("active")

    // JSON blobs for complex state
    val unlockedCommands = text("unlocked_commands").default("[]")
    val discoveredNodes = text("discovered_nodes").default("[\"localhost\"]")
    val scannedNodes = text("scanned_nodes").default("[\"localhost\"]")
    val completedMissions = text("completed_missions").default("[]")
    val ownedTools = text("owned_tools").default("[]")
    val ownedExploits = text("owned_exploits").default("[]")
    val ownedUpgrades = text("owned_upgrades").default("[]")
    val nodeAccess = text("node_access").default("[]")
    val reputation = text("reputation").default("{}")
    val downloads = text("downloads").default("[]")
    val connectionHistory = text("connection_history").default("[]")
    val loadoutData = text("loadout_data").nullable()

    // Combat stats (Puzzle Revamp 1.0)
    val threatPressure = integer("threat_pressure").default(0)
    val systemIntegrity = integer("system_integrity").default(100)
    val activeCombatType = varchar("active_combat_type", 32).nullable()
    val puzzleProgress = text("puzzle_progress").default("{\"tutorialsSeen\":[],\"attemptsPerType\":{},\"successesPerType\":{}}")
    val recentPuzzleTypes = text("recent_puzzle_types").default("[]")

    // Stats
    val totalHacks = integer("total_hacks").default(0)
    val successfulHacks = integer("successful_hacks").default(0)
    val failedHacks = integer("failed_hacks").default(0)
    val totalCreditsEarned = integer("total_credits_earned").default(0)
    val totalCreditsSpent = integer("total_credits_spent").default(0)

    val lastActionTime = long("last_action_time").default(System.currentTimeMillis())
    val createdAt = long("created_at").default(System.currentTimeMillis())
    val updatedAt = long("updated_at").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(id)
}

/**
 * Mission history — completed mission records
 */
object MissionHistoryTable : Table("mission_history") {
    val id = integer("id").autoIncrement()
    val playerId = varchar("player_id", 128).references(PlayersTable.id)
    val missionId = varchar("mission_id", 64)
    val startedAt = long("started_at")
    val completedAt = long("completed_at").nullable()
    val success = bool("success").default(false)
    val finalExposure = double("final_exposure").default(0.0)
    val payout = integer("payout").default(0)
    val objectivesCompleted = text("objectives_completed").default("[]")

    override val primaryKey = PrimaryKey(id)
}

/**
 * Transactions — credit history
 */
object TransactionsTable : Table("transactions") {
    val id = integer("id").autoIncrement()
    val playerId = varchar("player_id", 128).references(PlayersTable.id)
    val type = varchar("type", 32) // "earn", "spend", "reward", "penalty"
    val amount = integer("amount")
    val balanceAfter = integer("balance_after")
    val createdAt = long("created_at").default(System.currentTimeMillis())
    val metadata = text("metadata").default("{}")

    override val primaryKey = PrimaryKey(id)
}

/**
 * Job offers — persistent negotiation state
 */
object JobOffersTable : Table("job_offers") {
    val id = varchar("id", 128) // "${playerId}_${missionId}"
    val playerId = varchar("player_id", 128).references(PlayersTable.id)
    val missionId = varchar("mission_id", 64)
    val contactId = varchar("contact_id", 64)
    val baseOfferPercentage = integer("base_offer_percentage")
    val currentOfferPercentage = integer("current_offer_percentage")
    val maxOfferPercentage = integer("max_offer_percentage")
    val negotiationAttempts = integer("negotiation_attempts").default(0)
    val maxAttempts = integer("max_attempts").default(3)
    val rejected = bool("rejected").default(false)
    val createdAt = long("created_at").default(System.currentTimeMillis())
    val updatedAt = long("updated_at").default(System.currentTimeMillis())
    val expiresAt = long("expires_at") // 7 days from creation

    override val primaryKey = PrimaryKey(id)
}

/**
 * Game config — tunable parameters
 */
object GameConfigTable : Table("game_config") {
    val key = varchar("key", 128)
    val value = text("value")
    val updatedAt = long("updated_at").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(key)
}
