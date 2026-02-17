package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Player state and progression
 */
@Serializable
data class Player(
    val id: String,
    var credits: Int = 5000,
    var exposure: Double = 0.0,

    // Machine state
    var currentMachine: MachineState = MachineDefinition.GREYBOX.copy(),

    // Game phase state machine
    var currentGamePhase: GamePhase = GamePhase.HOME_NODE,

    // Unlocked commands (start with basics)
    val unlockedCommands: MutableSet<String> = mutableSetOf(
        // System
        "help", "clear", "status", "whoami",
        // Filesystem
        "ls", "cd", "cat", "pwd",
        // Network - LOCKED until mission_1 accepted
        // "scan", "connect", "disconnect" will be unlocked by tutorial trigger
        "probe",  // Keep probe for advanced users
        // Communication
        "mail", "sentinel", "store",
        // Missions
        "jobs", "job", "mission",
        // Puzzles
        "analyze", "decode", "decrypt", "submit", "solve",
        // File operations
        "open", "download",
        // Phase 3 network commands
        "nscan", "nconnect", "nmap", "route"
    ),

    // Purchased/owned items
    val ownedExploits: MutableSet<String> = mutableSetOf(),
    val ownedTools: MutableList<OwnedTool> = mutableListOf(),
    val ownedUpgrades: MutableSet<String> = mutableSetOf(),

    // Current loadout for missions
    var currentLoadout: Loadout? = null,

    // Discovered network nodes
    val discoveredNodes: MutableSet<String> = mutableSetOf("localhost"),

    // Nodes that have been fully scanned (name/details revealed)
    val scannedNodes: MutableSet<String> = mutableSetOf("localhost"),

    // Completed missions
    val completedMissions: MutableSet<String> = mutableSetOf(),

    // Current active mission (if any)
    var activeMission: String? = null,

    // Reputation with factions (-100 to 100)
    val reputation: MutableMap<String, Int> = mutableMapOf(
        "underground" to 0,
        "corporate" to 0,
        "government" to 0
    ),

    // Statistics
    var totalHacks: Int = 0,
    var successfulHacks: Int = 0,
    var failedHacks: Int = 0,
    var totalCreditsEarned: Int = 0,
    var totalCreditsSpent: Int = 0
) {
    /**
     * Check if player has a command unlocked
     */
    fun hasCommand(command: String): Boolean = command in unlockedCommands

    /**
     * Unlock a new command
     */
    fun unlockCommand(command: String) {
        unlockedCommands.add(command)
    }

    /**
     * Add credits (from jobs, hacks, etc.)
     */
    fun earnCredits(amount: Int) {
        credits += amount
        totalCreditsEarned += amount
    }

    /**
     * Spend credits (buying tools, etc.)
     * Returns true if successful, false if insufficient funds
     */
    fun spendCredits(amount: Int): Boolean {
        return if (credits >= amount) {
            credits -= amount
            totalCreditsSpent += amount
            true
        } else {
            false
        }
    }

    /**
     * Increase exposure (from failed hacks, detection, etc.)
     */
    fun increaseExposure(amount: Double) {
        exposure = (exposure + amount).coerceIn(0.0, 100.0)
    }

    /**
     * Decrease exposure (over time, laying low, etc.)
     */
    fun decreaseExposure(amount: Double) {
        exposure = (exposure - amount).coerceIn(0.0, 100.0)
    }

    /**
     * Check if exposure is at critical level
     */
    fun isExposureCritical(): Boolean = exposure >= 80.0

    /**
     * Check if player has been caught (exposure maxed)
     */
    fun isCaught(): Boolean = exposure >= 100.0

    /**
     * Record a hack attempt
     */
    fun recordHack(success: Boolean) {
        totalHacks++
        if (success) successfulHacks++ else failedHacks++
    }

    /**
     * Get reputation with a faction
     */
    fun getReputation(faction: String): Int = reputation[faction] ?: 0

    /**
     * Modify reputation with a faction
     */
    fun modifyReputation(faction: String, change: Int) {
        val current = reputation[faction] ?: 0
        reputation[faction] = (current + change).coerceIn(-100, 100)
    }

    /**
     * Get reputation level label
     */
    fun getReputationLevel(faction: String): String {
        val rep = getReputation(faction)
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

    /**
     * Check if player owns a specific tool
     */
    fun ownsTool(type: ToolType, version: ToolVersion, category: ToolCategory = ToolCategory.PERMANENT): Boolean {
        return ownedTools.any {
            it.type == type && it.version == version && it.category == category
        }
    }

    /**
     * Get the highest version of a tool type the player owns
     */
    fun getHighestVersion(type: ToolType, category: ToolCategory = ToolCategory.PERMANENT): ToolVersion? {
        return ownedTools
            .filter { it.type == type && it.category == category }
            .maxByOrNull { it.version }
            ?.version
    }

    /**
     * Check if player can purchase a tool (credits + prerequisites)
     */
    fun canPurchaseTool(definition: ToolDefinition): Boolean {
        return ToolCatalog.canPurchase(this, definition)
    }

    /**
     * Purchase a tool (deduct credits and add to owned tools)
     * Returns true if successful, false if cannot afford or prerequisites not met
     */
    fun purchaseTool(definition: ToolDefinition): Boolean {
        if (!canPurchaseTool(definition)) {
            return false
        }

        if (!spendCredits(definition.unlockCost)) {
            return false
        }

        val ownedTool = OwnedTool(
            type = definition.type,
            version = definition.version,
            category = definition.category
        )
        ownedTools.add(ownedTool)

        // Auto-unlock any associated commands
        when (definition.type) {
            ToolType.CLOAK -> unlockCommand("use")
            ToolType.ROLLBACK -> unlockCommand("use")
            ToolType.OVERDRIVE -> unlockCommand("use")
            else -> {} // Other tools may unlock different commands
        }

        return true
    }

    /**
     * Get all owned tools of a specific type
     */
    fun getOwnedToolsOfType(type: ToolType): List<OwnedTool> {
        return ownedTools.filter { it.type == type }
    }
}

/**
 * Exposure levels for UI display
 */
enum class ExposureLevel(val label: String, val minValue: Double, val maxValue: Double) {
    MINIMAL("Minimal", 0.0, 20.0),
    LOW("Low", 20.0, 40.0),
    MODERATE("Moderate", 40.0, 60.0),
    HIGH("High", 60.0, 80.0),
    CRITICAL("Critical", 80.0, 100.0);

    companion object {
        fun fromValue(exposure: Double): ExposureLevel {
            return entries.find { exposure >= it.minValue && exposure < it.maxValue }
                ?: CRITICAL
        }
    }
}
