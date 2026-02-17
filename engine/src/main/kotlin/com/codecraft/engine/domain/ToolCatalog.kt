package com.codecraft.engine.domain

/**
 * Central registry of all tool definitions
 * Provides lookup, validation, and upgrade path logic
 */
object ToolCatalog {
    private val definitions = mutableMapOf<String, ToolDefinition>()

    init {
        registerCloakTools()
        registerRollbackTools()
        registerOverdriveTools()
        registerConsumables()
    }

    private fun registerCloakTools() {
        // CLOAK V1 - Masks 2 commands
        register(ToolDefinition(
            type = ToolType.CLOAK,
            version = ToolVersion.V1,
            category = ToolCategory.PERMANENT,
            name = "Cloak V1",
            description = "Masks the next 2 commands from detection. Basic stealth protocol.",
            unlockCost = 7000,
            prerequisiteVersion = null,
            slotCost = 2,
            activationFirewallCost = 15,
            activationExposureCost = 5.0,
            effectType = ToolEffectType.COMMAND_MASKING,
            effectMagnitude = 2.0,  // 2 commands
            effectDuration = null,
            effectCharges = 2,
            cooldownPerMission = 2
        ))

        // CLOAK V2 - Masks 3 commands, reduced costs
        register(ToolDefinition(
            type = ToolType.CLOAK,
            version = ToolVersion.V2,
            category = ToolCategory.PERMANENT,
            name = "Cloak V2",
            description = "Masks the next 3 commands from detection. Improved stealth with lower activation cost.",
            unlockCost = 5000,
            prerequisiteVersion = ToolVersion.V1,
            slotCost = 2,
            activationFirewallCost = 10,
            activationExposureCost = 4.0,
            effectType = ToolEffectType.COMMAND_MASKING,
            effectMagnitude = 3.0,  // 3 commands
            effectDuration = null,
            effectCharges = 3,
            cooldownPerMission = 2
        ))

        // CLOAK V3 - Masks 4 commands, can mask counter-hacks
        register(ToolDefinition(
            type = ToolType.CLOAK,
            version = ToolVersion.V3,
            category = ToolCategory.PERMANENT,
            name = "Cloak V3",
            description = "Masks the next 4 commands from detection. Military-grade stealth can hide even counter-hack attempts.",
            unlockCost = 7000,
            prerequisiteVersion = ToolVersion.V2,
            slotCost = 3,
            activationFirewallCost = 8,
            activationExposureCost = 3.0,
            effectType = ToolEffectType.COMMAND_MASKING,
            effectMagnitude = 4.0,  // 4 commands
            effectDuration = null,
            effectCharges = 4,
            cooldownPerMission = 2,
            specialAbility = "Can mask counter-hack puzzle attempts"
        ))
    }

    private fun registerRollbackTools() {
        // ROLLBACK V1 - Undo last command's exposure
        register(ToolDefinition(
            type = ToolType.ROLLBACK,
            version = ToolVersion.V1,
            category = ToolCategory.PERMANENT,
            name = "Rollback V1",
            description = "Reverses exposure from the last command. Emergency undo protocol.",
            unlockCost = 8000,
            prerequisiteVersion = null,
            slotCost = 2,
            activationFirewallCost = 20,
            activationExposureCost = 0.0,
            effectType = ToolEffectType.EXPOSURE_UNDO,
            effectMagnitude = 1.0,  // 1 command
            effectDuration = null,
            effectCharges = 1,
            cooldownPerMission = 2
        ))

        // ROLLBACK V2 - Undo last 2 commands
        register(ToolDefinition(
            type = ToolType.ROLLBACK,
            version = ToolVersion.V2,
            category = ToolCategory.PERMANENT,
            name = "Rollback V2",
            description = "Reverses exposure from the last 2 commands. Enhanced temporal restoration.",
            unlockCost = 6000,
            prerequisiteVersion = ToolVersion.V1,
            slotCost = 3,
            activationFirewallCost = 18,
            activationExposureCost = 0.0,
            effectType = ToolEffectType.EXPOSURE_UNDO,
            effectMagnitude = 2.0,  // 2 commands
            effectDuration = null,
            effectCharges = 1,
            cooldownPerMission = 2
        ))

        // ROLLBACK V3 - Undo last 3 commands + firewall damage
        register(ToolDefinition(
            type = ToolType.ROLLBACK,
            version = ToolVersion.V3,
            category = ToolCategory.PERMANENT,
            name = "Rollback V3",
            description = "Reverses exposure AND firewall damage from the last 3 commands. Full system restoration.",
            unlockCost = 9000,
            prerequisiteVersion = ToolVersion.V2,
            slotCost = 4,
            activationFirewallCost = 15,
            activationExposureCost = 0.0,
            effectType = ToolEffectType.EXPOSURE_UNDO,
            effectMagnitude = 3.0,  // 3 commands
            effectDuration = null,
            effectCharges = 1,
            cooldownPerMission = 2,
            specialAbility = "Also reverses firewall damage from undone commands"
        ))
    }

    private fun registerOverdriveTools() {
        // OVERDRIVE V1 - 40% exposure reduction for 60s
        register(ToolDefinition(
            type = ToolType.OVERDRIVE,
            version = ToolVersion.V1,
            category = ToolCategory.PERMANENT,
            name = "Overdrive V1",
            description = "Reduces exposure from all commands by 40% for 60 seconds. CPU overclock mode.",
            unlockCost = 7500,
            prerequisiteVersion = null,
            slotCost = 2,
            activationFirewallCost = 10,
            activationExposureCost = 3.0,
            effectType = ToolEffectType.EXPOSURE_REDUCTION,
            effectMagnitude = 40.0,  // 40% reduction
            effectDuration = 60,      // 60 seconds
            effectCharges = null,
            cooldownPerMission = 1
        ))

        // OVERDRIVE V2 - 50% exposure reduction for 90s
        register(ToolDefinition(
            type = ToolType.OVERDRIVE,
            version = ToolVersion.V2,
            category = ToolCategory.PERMANENT,
            name = "Overdrive V2",
            description = "Reduces exposure from all commands by 50% for 90 seconds. Optimized overclock with longer duration.",
            unlockCost = 6000,
            prerequisiteVersion = ToolVersion.V1,
            slotCost = 3,
            activationFirewallCost = 8,
            activationExposureCost = 2.5,
            effectType = ToolEffectType.EXPOSURE_REDUCTION,
            effectMagnitude = 50.0,  // 50% reduction
            effectDuration = 90,      // 90 seconds
            effectCharges = null,
            cooldownPerMission = 1
        ))

        // OVERDRIVE V3 - 60% exposure reduction for 120s + no firewall damage
        register(ToolDefinition(
            type = ToolType.OVERDRIVE,
            version = ToolVersion.V3,
            category = ToolCategory.PERMANENT,
            name = "Overdrive V3",
            description = "Reduces exposure by 60% for 120 seconds AND prevents firewall damage. Military-grade performance mode.",
            unlockCost = 8500,
            prerequisiteVersion = ToolVersion.V2,
            slotCost = 4,
            activationFirewallCost = 5,
            activationExposureCost = 2.0,
            effectType = ToolEffectType.EXPOSURE_REDUCTION,
            effectMagnitude = 60.0,  // 60% reduction
            effectDuration = 120,     // 120 seconds
            effectCharges = null,
            cooldownPerMission = 1,
            specialAbility = "Prevents firewall damage during active period"
        ))
    }

    private fun registerConsumables() {
        // Consumable CLOAK - Single use, 5 charges
        register(ToolDefinition(
            type = ToolType.CLOAK,
            version = ToolVersion.V1,
            category = ToolCategory.CONSUMABLE,
            name = "Cloak Burst (Consumable)",
            description = "Single-use stealth burst. Masks 5 commands, then destroys itself.",
            unlockCost = 3000,
            prerequisiteVersion = null,
            slotCost = 1,
            activationFirewallCost = 5,
            activationExposureCost = 2.0,
            effectType = ToolEffectType.COMMAND_MASKING,
            effectMagnitude = 5.0,  // 5 commands
            effectDuration = null,
            effectCharges = 5,
            cooldownPerMission = 0  // No cooldown, single use
        ))

        // Consumable OVERDRIVE - Single use, 180s
        register(ToolDefinition(
            type = ToolType.OVERDRIVE,
            version = ToolVersion.V1,
            category = ToolCategory.CONSUMABLE,
            name = "Overdrive Cell (Consumable)",
            description = "Single-use performance boost. 70% exposure reduction for 180 seconds, then burns out.",
            unlockCost = 4000,
            prerequisiteVersion = null,
            slotCost = 1,
            activationFirewallCost = 3,
            activationExposureCost = 1.0,
            effectType = ToolEffectType.EXPOSURE_REDUCTION,
            effectMagnitude = 70.0,  // 70% reduction
            effectDuration = 180,     // 180 seconds
            effectCharges = null,
            cooldownPerMission = 0
        ))

        // Consumable ROLLBACK - Single use, undo 5 commands
        register(ToolDefinition(
            type = ToolType.ROLLBACK,
            version = ToolVersion.V1,
            category = ToolCategory.CONSUMABLE,
            name = "Emergency Rollback (Consumable)",
            description = "Single-use emergency restoration. Reverses exposure from last 5 commands, then depletes.",
            unlockCost = 4500,
            prerequisiteVersion = null,
            slotCost = 1,
            activationFirewallCost = 10,
            activationExposureCost = 0.0,
            effectType = ToolEffectType.EXPOSURE_UNDO,
            effectMagnitude = 5.0,  // 5 commands
            effectDuration = null,
            effectCharges = 1,
            cooldownPerMission = 0
        ))
    }

    private fun register(definition: ToolDefinition) {
        val key = makeKey(definition.type, definition.version, definition.category)
        definitions[key] = definition
    }

    private fun makeKey(type: ToolType, version: ToolVersion, category: ToolCategory): String {
        return if (category == ToolCategory.CONSUMABLE) {
            "${type.name}-${version.name}-CONSUMABLE"
        } else {
            "${type.name}-${version.name}"
        }
    }

    fun getDefinition(type: ToolType, version: ToolVersion, category: ToolCategory = ToolCategory.PERMANENT): ToolDefinition? {
        return definitions[makeKey(type, version, category)]
    }

    fun getAllDefinitions(): List<ToolDefinition> {
        return definitions.values.toList()
    }

    fun getDefinitionsByType(type: ToolType): List<ToolDefinition> {
        return definitions.values.filter { it.type == type }.sortedWith(
            compareBy({ it.category }, { it.version })
        )
    }

    fun getVersionChain(type: ToolType, category: ToolCategory = ToolCategory.PERMANENT): List<ToolDefinition> {
        return definitions.values
            .filter { it.type == type && it.category == category }
            .sortedBy { it.version }
    }

    fun canPurchase(player: Player, definition: ToolDefinition): Boolean {
        // Check credits
        if (player.credits < definition.unlockCost) {
            return false
        }

        // Check if already owned
        if (player.ownsTool(definition.type, definition.version, definition.category)) {
            return false
        }

        // Check prerequisite version
        val prereq = definition.prerequisiteVersion ?: return true
        return player.ownsTool(definition.type, prereq, definition.category)
    }

    fun getUpgradePath(player: Player, type: ToolType, category: ToolCategory = ToolCategory.PERMANENT): ToolDefinition? {
        val currentVersion = player.getHighestVersion(type, category) ?: return getDefinition(type, ToolVersion.V1, category)

        return when (currentVersion) {
            ToolVersion.V1 -> getDefinition(type, ToolVersion.V2, category)
            ToolVersion.V2 -> getDefinition(type, ToolVersion.V3, category)
            ToolVersion.V3 -> null // Already at max
        }
    }

    fun parseToolSpec(spec: String): Triple<ToolType, ToolVersion, ToolCategory>? {
        // Parse formats: "cloak-v2", "cloak v2", "cloak-v1-consumable"
        val parts = spec.lowercase().split(Regex("[-\\s]"))
        if (parts.size < 2) return null

        return try {
            val type = ToolType.valueOf(parts[0].uppercase())
            val version = ToolVersion.valueOf(parts[1].uppercase())
            val category = if (parts.size > 2 && parts[2] == "consumable") {
                ToolCategory.CONSUMABLE
            } else {
                ToolCategory.PERMANENT
            }
            Triple(type, version, category)
        } catch (e: IllegalArgumentException) {
            null
        }
    }
}
