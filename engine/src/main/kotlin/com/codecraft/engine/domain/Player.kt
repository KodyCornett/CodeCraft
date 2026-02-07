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

    // Unlocked commands (start with basics)
    val unlockedCommands: MutableSet<String> = mutableSetOf(
        "help", "ls", "cd", "cat", "clear", "mail", "sentinel", "status"
    ),

    // Purchased/owned items
    val ownedExploits: MutableSet<String> = mutableSetOf(),
    val ownedTools: MutableSet<String> = mutableSetOf(),
    val ownedUpgrades: MutableSet<String> = mutableSetOf(),

    // Discovered network nodes
    val discoveredNodes: MutableSet<String> = mutableSetOf("localhost"),

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
    var totalCreditsEarned: Int = 0
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
