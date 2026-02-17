package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.*
import com.codecraft.engine.session.GameSession

/**
 * store - Browse and purchase tools
 * Now integrated with the versioned tool system
 */
class StoreCommand : Command {
    override val name = "store"
    override val description = "Browse and purchase hacking tools"
    override val usage = "store [list|buy <tool>|info <tool>]"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty() || args[0] == "list") {
            return listItems(session)
        }

        return when (args[0].lowercase()) {
            "buy" -> {
                if (args.size < 2) {
                    CommandResult.error("Usage: store buy <tool>\nExample: store buy cloak-v1")
                } else {
                    // Join remaining args for "cloak v1" or "cloak-v1" format
                    val toolSpec = args.drop(1).joinToString(" ")
                    buyItem(session, toolSpec)
                }
            }
            "info" -> {
                if (args.size < 2) {
                    CommandResult.error("Usage: store info <tool>\nExample: store info cloak-v1")
                } else {
                    val toolSpec = args.drop(1).joinToString(" ")
                    showItemInfo(session, toolSpec)
                }
            }
            else -> CommandResult.error("Unknown store command. Use: list, buy, info")
        }
    }

    private fun listItems(session: GameSession): CommandResult {
        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║            UNDERGROUND MARKET                        ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Your Credits: §${session.player.credits.toString().padEnd(37)}║")
            appendLine("╚══════════════════════════════════════════════════════╝")
            appendLine()

            // Group tools by type
            val toolTypes = listOf(ToolType.CLOAK, ToolType.OVERDRIVE, ToolType.ROLLBACK)

            toolTypes.forEach { type ->
                appendLine("[${type.name}]")
                val versions = ToolCatalog.getVersionChain(type, ToolCategory.PERMANENT)

                versions.forEach { def ->
                    val owned = session.player.ownsTool(def.type, def.version, def.category)
                    val canBuy = ToolCatalog.canPurchase(session.player, def)

                    val status = when {
                        owned -> "[OWNED]"
                        !canBuy && session.player.credits >= def.unlockCost -> "[LOCKED]"
                        else -> "§${def.unlockCost}"
                    }

                    val id = "${def.type.name}-${def.version.name}".lowercase()
                    appendLine("  ${id.padEnd(18)} ${status.padStart(12)}")
                }

                // Show consumable variant if exists
                val consumable = ToolCatalog.getDefinition(type, ToolVersion.V1, ToolCategory.CONSUMABLE)
                if (consumable != null) {
                    val owned = session.player.ownsTool(consumable.type, consumable.version, consumable.category)
                    val status = if (owned) "[OWNED]" else "§${consumable.unlockCost}"
                    val id = "${consumable.type.name}-consumable".lowercase()
                    appendLine("  ${id.padEnd(18)} ${status.padStart(12)}")
                }

                appendLine()
            }

            appendLine("─".repeat(55))
            appendLine("Use 'store info <tool>' for details")
            appendLine("Use 'store buy <tool>' to purchase")
            appendLine("\nExample: store buy cloak-v1")
        }

        return CommandResult.success(output, delayMs = 100)
    }

    private fun buyItem(session: GameSession, toolSpec: String): CommandResult {
        // Parse tool spec: "cloak-v1", "cloak v1", or "cloak-consumable"
        val parsed = ToolCatalog.parseToolSpec(toolSpec)
            ?: return CommandResult.error("Invalid tool format: $toolSpec\nUse: <tool>-<version> (e.g., cloak-v1)")

        val (type, version, category) = parsed

        val definition = ToolCatalog.getDefinition(type, version, category)
            ?: return CommandResult.error("Tool not found: ${type.name} ${version.name}")

        // Check if can purchase (checks credits, ownership, prerequisites)
        if (!session.player.canPurchaseTool(definition)) {
            val owned = session.player.ownsTool(type, version, category)
            if (owned) {
                return CommandResult.error("You already own ${definition.name}")
            }

            val prereq = definition.prerequisiteVersion
            if (prereq != null && !session.player.ownsTool(type, prereq, category)) {
                return CommandResult.error("${definition.name} requires ${type.name} ${prereq.name} first")
            }

            return CommandResult.error("Insufficient credits. Need §${definition.unlockCost}, have §${session.player.credits}")
        }

        // Purchase the tool
        if (!session.player.purchaseTool(definition)) {
            return CommandResult.error("Failed to purchase tool")
        }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║              PURCHASE COMPLETE                       ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  Tool: ${definition.name.padEnd(45)}║")
            appendLine("║  Cost: §${definition.unlockCost.toString().padEnd(44)}║")
            appendLine("║  Type: ${definition.category.name.padEnd(44)}║")
            appendLine("║  Remaining Credits: §${session.player.credits.toString().padEnd(31)}║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${definition.description.padEnd(52)}║")
            appendLine("╚══════════════════════════════════════════════════════╝")
            appendLine()
            appendLine("Use 'loadout add ${type.name.lowercase()} ${version.name.lowercase()}' to equip this tool")
        }

        return CommandResult.success(output, delayMs = 200)
    }

    private fun showItemInfo(session: GameSession, toolSpec: String): CommandResult {
        val parsed = ToolCatalog.parseToolSpec(toolSpec)
            ?: return CommandResult.error("Invalid tool format: $toolSpec\nUse: <tool>-<version> (e.g., cloak-v1)")

        val (type, version, category) = parsed

        val definition = ToolCatalog.getDefinition(type, version, category)
            ?: return CommandResult.error("Tool not found: ${type.name} ${version.name}")

        // Check if player owns this version
        val owned = session.player.ownsTool(type, version, category)

        // Get upgrade info if applicable
        val nextVersion = when (version) {
            ToolVersion.V1 -> ToolVersion.V2
            ToolVersion.V2 -> ToolVersion.V3
            ToolVersion.V3 -> null
        }

        val upgrade = if (owned && nextVersion != null) {
            ToolCatalog.getDefinition(type, nextVersion, category)
        } else null

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║  ${definition.name.uppercase().padEnd(52)}║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  ${definition.description.padEnd(52)}║")
            appendLine("║                                                      ║")
            appendLine("║  Price: §${definition.unlockCost.toString().padEnd(44)}║")
            appendLine("║  Category: ${definition.category.name.padEnd(41)}║")
            appendLine("║  Slot Cost: ${definition.slotCost.toString().padEnd(41)}║")
            appendLine("║                                                      ║")
            appendLine("║  Activation Cost:                                    ║")
            appendLine("║    Firewall: ${definition.activationFirewallCost}%".padEnd(54) + "║")
            appendLine("║    Exposure: ${definition.activationExposureCost}%".padEnd(54) + "║")
            appendLine("║                                                      ║")
            appendLine("║  Effect:                                             ║")

            when (definition.effectType) {
                ToolEffectType.COMMAND_MASKING -> {
                    appendLine("║    Masks ${definition.effectCharges} commands".padEnd(54) + "║")
                }
                ToolEffectType.EXPOSURE_REDUCTION -> {
                    appendLine("║    ${definition.effectMagnitude.toInt()}% exposure reduction".padEnd(54) + "║")
                    appendLine("║    Duration: ${definition.effectDuration}s".padEnd(54) + "║")
                }
                ToolEffectType.EXPOSURE_UNDO -> {
                    appendLine("║    Undo last ${definition.effectMagnitude.toInt()} commands".padEnd(54) + "║")
                }
                else -> {
                    appendLine("║    ${definition.effectType.name}".padEnd(54) + "║")
                }
            }

            if (definition.cooldownPerMission > 0) {
                appendLine("║  Uses per mission: ${definition.cooldownPerMission}".padEnd(54) + "║")
            }

            if (definition.specialAbility != null) {
                appendLine("║                                                      ║")
                appendLine("║  Special: ${definition.specialAbility!!.padEnd(43)}║")
            }

            // Show upgrade comparison if owned
            if (upgrade != null) {
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  UPGRADE TO ${upgrade.name.uppercase()}".padEnd(54) + "║")
                appendLine("╠══════════════════════════════════════════════════════╣")
                appendLine("║  Cost: §${upgrade.unlockCost}".padEnd(54) + "║")
                appendLine("║  Improvements:                                       ║")

                // Compare charges
                if (definition.effectCharges != upgrade.effectCharges) {
                    appendLine("║    Charges: ${definition.effectCharges} → ${upgrade.effectCharges} (+${(upgrade.effectCharges ?: 0) - (definition.effectCharges ?: 0)})".padEnd(54) + "║")
                }

                // Compare magnitude
                if (definition.effectMagnitude != upgrade.effectMagnitude) {
                    val diff = upgrade.effectMagnitude - definition.effectMagnitude
                    appendLine("║    Effect: ${definition.effectMagnitude.toInt()}% → ${upgrade.effectMagnitude.toInt()}% (+${diff.toInt()}%)".padEnd(54) + "║")
                }

                // Compare duration
                if (definition.effectDuration != upgrade.effectDuration) {
                    appendLine("║    Duration: ${definition.effectDuration}s → ${upgrade.effectDuration}s".padEnd(54) + "║")
                }

                // Compare costs
                if (definition.activationFirewallCost != upgrade.activationFirewallCost) {
                    appendLine("║    Firewall cost: ${definition.activationFirewallCost}% → ${upgrade.activationFirewallCost}%".padEnd(54) + "║")
                }
            }

            appendLine("╚══════════════════════════════════════════════════════╝")
        }

        return CommandResult.success(output, delayMs = 100)
    }
}
