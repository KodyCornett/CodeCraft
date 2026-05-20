package com.codecraft.engine.session

import com.codecraft.engine.domain.*

/**
 * Manages tool activation, state transitions, and effect application
 * Handles activation costs, charge consumption, and duration tracking
 */
class ToolActivation(private val session: GameSession) {
    private val player get() = session.player

    /**
     * Activate a tool from the loadout
     * Checks state, applies costs, sets ACTIVE state
     */
    fun activateTool(type: ToolType): Result<ActivationResult> {
        val loadout = player.currentLoadout
            ?: return Result.failure(Exception("No loadout equipped."))

        // Find the tool in loadout
        val tool = loadout.findTool(type)
            ?: return Result.failure(Exception("$type not in loadout."))

        // Check if already active
        if (tool.isActive()) {
            val remaining = tool.getRemainingDuration()?.let { it / 1000 } ?: 0
            return Result.failure(Exception("$type is already active (${remaining}s remaining)."))
        }

        // Check if ready
        if (!tool.isReady()) {
            return Result.failure(Exception("$type is not ready (state: ${tool.state})."))
        }

        // Get definition
        val definition = ToolCatalog.getDefinition(tool.type, tool.version, tool.category)
            ?: return Result.failure(Exception("Tool definition not found."))

        // Check cooldown
        if (definition.cooldownPerMission > 0 && tool.usesThisMission >= definition.cooldownPerMission) {
            return Result.failure(Exception("$type has reached its usage limit for this mission (${definition.cooldownPerMission} uses)."))
        }

        // Check activation costs
        val costCheckResult = checkActivationCosts(definition)
        if (costCheckResult.isFailure) {
            return Result.failure(costCheckResult.exceptionOrNull()!!)
        }

        // Apply activation costs
        applyActivationCosts(definition)

        // Set tool to ACTIVE state
        tool.state = ToolState.ACTIVE
        tool.activatedAt = System.currentTimeMillis()
        tool.usesThisMission++

        // Set expiry for duration-based tools
        if (definition.effectDuration != null) {
            tool.expiresAt = System.currentTimeMillis() + (definition.effectDuration * 1000)
        }

        val result = ActivationResult(
            toolName = definition.name,
            effectType = definition.effectType,
            duration = definition.effectDuration,
            charges = definition.effectCharges,
            magnitude = definition.effectMagnitude
        )

        return Result.success(result)
    }

    /**
     * Consume a charge from a charge-based tool
     * Updates state to SPENT or EXHAUSTED when depleted
     */
    fun consumeCharge(tool: LoadoutTool) {
        val charges = tool.remainingCharges ?: return

        if (charges <= 0) {
            // Already exhausted
            tool.state = if (tool.category == ToolCategory.CONSUMABLE) {
                ToolState.EXHAUSTED
            } else {
                ToolState.SPENT
            }
            return
        }

        tool.remainingCharges = charges - 1

        if (tool.remainingCharges!! <= 0) {
            // Depleted
            tool.state = if (tool.category == ToolCategory.CONSUMABLE) {
                ToolState.EXHAUSTED
            } else {
                ToolState.SPENT
            }
            tool.activatedAt = null
            tool.expiresAt = null
        }
    }

    /**
     * Clean up expired duration-based tools
     * Called after each command execution
     */
    fun cleanupExpiredTools() {
        val loadout = player.currentLoadout ?: return

        loadout.tools.forEach { tool ->
            if (tool.isActive() && tool.hasExpired()) {
                // Duration expired
                tool.state = if (tool.category == ToolCategory.CONSUMABLE) {
                    ToolState.EXHAUSTED
                } else {
                    ToolState.SPENT
                }
                tool.activatedAt = null
                tool.expiresAt = null
            }
        }
    }

    /**
     * Check if activation costs can be paid
     */
    private fun checkActivationCosts(definition: ToolDefinition): Result<Unit> {
        val machine = player.currentMachine

        // Check firewall cost
        val firewallCost = (machine.firewallMax * definition.activationFirewallCost) / 100
        if (machine.firewallCurrent < firewallCost) {
            return Result.failure(Exception("Insufficient firewall (need ${firewallCost}%, have ${machine.firewallCurrent}%)."))
        }

        // Exposure cost is always applicable (can't go negative though)
        // We'll apply it anyway since exposure can be 0

        return Result.success(Unit)
    }

    /**
     * Apply activation costs to player state
     */
    private fun applyActivationCosts(definition: ToolDefinition) {
        val machine = player.currentMachine

        // Deduct firewall
        val firewallCost = (machine.firewallMax * definition.activationFirewallCost) / 100
        machine.firewallCurrent = (machine.firewallCurrent - firewallCost).coerceAtLeast(0)

        // Increase exposure
        player.increaseExposure(definition.activationExposureCost)
    }

    /**
     * Get formatted activation message
     */
    fun formatActivationMessage(result: ActivationResult): String {
        val builder = StringBuilder()
        builder.appendLine("╔═══════════════════════════════════════════════════╗")
        builder.appendLine("║  ${result.toolName} ACTIVATED${" ".repeat(49 - result.toolName.length - 10)}║")
        builder.appendLine("╠═══════════════════════════════════════════════════╣")

        when (result.effectType) {
            ToolEffectType.COMMAND_MASKING -> {
                builder.appendLine("║  Effect: Masks next ${result.charges?.toInt()} commands${" ".repeat(32 - result.charges.toString().length)}║")
            }
            ToolEffectType.EXPOSURE_REDUCTION -> {
                builder.appendLine("║  Effect: ${result.magnitude.toInt()}% exposure reduction${" ".repeat(31 - result.magnitude.toInt().toString().length)}║")
                builder.appendLine("║  Duration: ${result.duration}s${" ".repeat(40 - result.duration.toString().length)}║")
            }
            ToolEffectType.EXPOSURE_UNDO -> {
                builder.appendLine("║  Effect: Undo last ${result.magnitude.toInt()} commands${" ".repeat(30 - result.magnitude.toInt().toString().length)}║")
            }
            else -> {
                builder.appendLine("║  Effect: ${result.effectType}${" ".repeat(41 - result.effectType.name.length)}║")
            }
        }

        builder.appendLine("╚═══════════════════════════════════════════════════╝")
        return builder.toString()
    }
}

/**
 * Result of tool activation
 */
data class ActivationResult(
    val toolName: String,
    val effectType: ToolEffectType,
    val duration: Int?,
    val charges: Int?,
    val magnitude: Double
)
