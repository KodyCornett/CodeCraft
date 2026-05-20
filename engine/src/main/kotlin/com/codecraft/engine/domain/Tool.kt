package com.codecraft.engine.domain

import kotlinx.serialization.Serializable
import java.time.Instant

/**
 * Type of tool available in the game
 */
enum class ToolType {
    CLOAK,              // Masks commands from detection
    ROLLBACK,           // Undoes recent exposure/damage
    OVERDRIVE,          // Reduces exposure from commands
    GHOST_PROTOCOL,     // Temporary immunity from detection
    EMERGENCY_PATCH,    // Instant firewall repair
    SMOKE_SCREEN,       // Confuses sentinel AI
    TRACE_ERASER,       // Removes connection traces
    BANDWIDTH_BOOST,    // Faster download speeds
    STEALTH_SCAN        // Silent network reconnaissance
}

/**
 * Version tier of a tool
 */
enum class ToolVersion {
    V1, V2, V3
}

/**
 * Category determining tool behavior
 */
enum class ToolCategory {
    PERMANENT,  // Reusable with cooldowns
    CONSUMABLE  // Single-use, destroyed after exhaustion
}

/**
 * Current state of a tool in a loadout
 */
enum class ToolState {
    READY,      // Available for activation
    ACTIVE,     // Currently in effect
    SPENT,      // Used but can be reused (permanent tools on cooldown)
    EXHAUSTED   // Fully consumed (consumables only)
}

/**
 * Type of effect a tool provides
 */
enum class ToolEffectType {
    EXPOSURE_REDUCTION,     // Multiplier on exposure gain
    COMMAND_MASKING,        // Makes commands invisible to detection
    EXPOSURE_UNDO,          // Reverses recent exposure increases
    FIREWALL_DAMAGE_UNDO,   // Reverses recent firewall damage
    DETECTION_IMMUNITY,     // Prevents detection rolls entirely
    FIREWALL_REPAIR,        // Restores firewall points
    TRACE_REMOVAL,          // Removes connection traces
    DOWNLOAD_SPEED_BOOST,   // Increases download speed
    SCAN_STEALTH            // Prevents scan commands from increasing exposure
}

/**
 * Tool definition from the catalog
 * Represents the blueprint for a tool type/version combination
 */
@Serializable
data class ToolDefinition(
    val type: ToolType,
    val version: ToolVersion,
    val category: ToolCategory,
    val name: String,
    val description: String,

    // Purchase requirements
    val unlockCost: Int,
    val prerequisiteVersion: ToolVersion?,  // null for V1

    // Loadout constraints
    val slotCost: Int,

    // Activation costs
    val activationFirewallCost: Int,        // Percentage (0-100)
    val activationExposureCost: Double,     // Percentage (0.0-100.0)

    // Effect properties
    val effectType: ToolEffectType,
    val effectMagnitude: Double,            // Interpretation depends on effectType
    val effectDuration: Int?,               // Seconds (null for instant effects)
    val effectCharges: Int?,                // Number of uses before depletion (null for duration-based)

    // Cooldown
    val cooldownPerMission: Int,            // Number of times usable per mission (0 = unlimited)

    // Special abilities (V3 tools only)
    val specialAbility: String? = null
)

/**
 * Tool owned by a player (inventory entry)
 */
@Serializable
data class OwnedTool(
    val type: ToolType,
    val version: ToolVersion,
    val category: ToolCategory,
    val acquiredAt: Long = System.currentTimeMillis()
) {
    fun toKey(): String = "${type.name}-${version.name}"
}

/**
 * Tool instance in a loadout (mission-specific)
 * Tracks state and usage during a mission
 */
@Serializable
data class LoadoutTool(
    val type: ToolType,
    val version: ToolVersion,
    val category: ToolCategory,
    var state: ToolState = ToolState.READY,
    var remainingCharges: Int? = null,      // For charge-based tools
    var activatedAt: Long? = null,          // Timestamp when activated
    var expiresAt: Long? = null,            // Timestamp when effect expires
    var usesThisMission: Int = 0            // Cooldown tracking
) {
    fun isActive(): Boolean = state == ToolState.ACTIVE

    fun isReady(): Boolean = state == ToolState.READY

    fun isExhausted(): Boolean = state == ToolState.EXHAUSTED

    fun hasExpired(): Boolean {
        val expiry = expiresAt ?: return false
        return System.currentTimeMillis() >= expiry
    }

    fun getRemainingDuration(): Long? {
        val expiry = expiresAt ?: return null
        val remaining = expiry - System.currentTimeMillis()
        return if (remaining > 0) remaining else 0
    }

    companion object {
        fun fromOwnedTool(owned: OwnedTool, definition: ToolDefinition): LoadoutTool {
            return LoadoutTool(
                type = owned.type,
                version = owned.version,
                category = owned.category,
                remainingCharges = definition.effectCharges
            )
        }
    }
}

/**
 * Pre-mission equipment setup
 * Contains all tools equipped for a mission with slot constraints
 */
@Serializable
data class Loadout(
    val tools: MutableList<LoadoutTool> = mutableListOf(),
    val maxSlots: Int,
    var isFinalized: Boolean = false
) {
    fun getTotalSlotCost(catalog: Map<String, ToolDefinition>): Int {
        return tools.sumOf { tool ->
            val key = "${tool.type.name}-${tool.version.name}"
            catalog[key]?.slotCost ?: 0
        }
    }

    fun hasAvailableSlots(catalog: Map<String, ToolDefinition>, additionalSlots: Int): Boolean {
        return getTotalSlotCost(catalog) + additionalSlots <= maxSlots
    }

    fun findTool(type: ToolType): LoadoutTool? {
        return tools.find { it.type == type }
    }

    fun findActiveTool(type: ToolType): LoadoutTool? {
        return tools.find { it.type == type && it.isActive() }
    }

    fun getActiveTools(): List<LoadoutTool> {
        return tools.filter { it.isActive() }
    }

    fun resetStates() {
        tools.forEach { tool ->
            if (tool.category == ToolCategory.PERMANENT) {
                tool.state = ToolState.READY
                tool.usesThisMission = 0
                tool.activatedAt = null
                tool.expiresAt = null
                // Reset charges to original value from definition
                // Note: This requires catalog lookup in LoadoutManager
            }
        }
    }
}
