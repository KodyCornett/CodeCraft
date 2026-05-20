package com.codecraft.engine.session

import com.codecraft.engine.domain.*

/**
 * Manages loadout building, validation, and state management
 * Handles pre-mission equipment setup with slot constraints
 */
class LoadoutManager(private val session: GameSession) {
    private val player get() = session.player

    /**
     * Create a new loadout based on machine storage capacity
     */
    fun buildLoadout(): Loadout {
        val maxSlots = player.currentMachine.storageSlots
        val loadout = Loadout(maxSlots = maxSlots)
        player.currentLoadout = loadout
        return loadout
    }

    /**
     * Add a tool to the current loadout
     * Validates ownership, slot availability, and duplicate constraints
     */
    fun addToolToLoadout(type: ToolType, version: ToolVersion, category: ToolCategory = ToolCategory.PERMANENT): Result<String> {
        val loadout = player.currentLoadout
            ?: return Result.failure(Exception("No loadout active. Use 'loadout build' first."))

        if (loadout.isFinalized) {
            return Result.failure(Exception("Loadout is finalized. Use 'loadout build' to create a new one."))
        }

        // Check if player owns the tool
        if (!player.ownsTool(type, version, category)) {
            return Result.failure(Exception("You don't own $type $version (${category.name})."))
        }

        // Get tool definition
        val definition = ToolCatalog.getDefinition(type, version, category)
            ?: return Result.failure(Exception("Tool definition not found."))

        // Check slot availability
        val catalogMap = buildCatalogMap()
        if (!loadout.hasAvailableSlots(catalogMap, definition.slotCost)) {
            val current = loadout.getTotalSlotCost(catalogMap)
            return Result.failure(Exception("Insufficient slots. Using $current/${loadout.maxSlots}, need ${definition.slotCost} more."))
        }

        // Check for duplicate permanent tools
        if (category == ToolCategory.PERMANENT) {
            val existing = loadout.tools.find { it.type == type && it.category == ToolCategory.PERMANENT }
            if (existing != null) {
                return Result.failure(Exception("$type (permanent) already in loadout. Remove it first to add a different version."))
            }
        }

        // Create LoadoutTool from owned tool
        val ownedTool = player.ownedTools.find {
            it.type == type && it.version == version && it.category == category
        } ?: return Result.failure(Exception("Owned tool not found in inventory."))

        val loadoutTool = LoadoutTool.fromOwnedTool(ownedTool, definition)
        loadout.tools.add(loadoutTool)

        val slotsUsed = loadout.getTotalSlotCost(catalogMap)
        return Result.success("Added ${definition.name} to loadout. Slots: $slotsUsed/${loadout.maxSlots}")
    }

    /**
     * Remove a tool from the loadout by index
     */
    fun removeToolFromLoadout(index: Int): Result<String> {
        val loadout = player.currentLoadout
            ?: return Result.failure(Exception("No loadout active."))

        if (loadout.isFinalized) {
            return Result.failure(Exception("Loadout is finalized. Cannot modify."))
        }

        if (index < 0 || index >= loadout.tools.size) {
            return Result.failure(Exception("Invalid index. Valid range: 0-${loadout.tools.size - 1}"))
        }

        val removed = loadout.tools.removeAt(index)
        val definition = ToolCatalog.getDefinition(removed.type, removed.version, removed.category)
        return Result.success("Removed ${definition?.name ?: removed.type.name} from loadout.")
    }

    /**
     * Finalize the loadout for missions
     * Locks the loadout and prepares it for use
     */
    fun finalizeLoadout(): Result<String> {
        val loadout = player.currentLoadout
            ?: return Result.failure(Exception("No loadout to finalize."))

        if (loadout.tools.isEmpty()) {
            return Result.failure(Exception("Cannot finalize empty loadout. Add at least one tool."))
        }

        loadout.isFinalized = true
        return Result.success("Loadout equipped and ready for missions.")
    }

    /**
     * Reset loadout states for a new mission
     * Resets permanent tools to READY, removes exhausted consumables
     */
    fun resetLoadoutStates() {
        val loadout = player.currentLoadout ?: return

        // Remove exhausted consumables
        loadout.tools.removeIf { it.category == ToolCategory.CONSUMABLE && it.isExhausted() }

        // Reset permanent tools
        loadout.tools.forEach { tool ->
            if (tool.category == ToolCategory.PERMANENT) {
                tool.state = ToolState.READY
                tool.usesThisMission = 0
                tool.activatedAt = null
                tool.expiresAt = null

                // Reset charges from definition
                val definition = ToolCatalog.getDefinition(tool.type, tool.version, tool.category)
                tool.remainingCharges = definition?.effectCharges
            }
        }
    }

    /**
     * Get a formatted view of the current loadout
     */
    fun getLoadoutView(): String {
        val loadout = player.currentLoadout
            ?: return "No loadout built. Use 'loadout build' to create one."

        if (loadout.tools.isEmpty()) {
            return "Loadout is empty. Use 'loadout add <tool> <version>' to add tools."
        }

        val catalogMap = buildCatalogMap()
        val totalSlots = loadout.getTotalSlotCost(catalogMap)
        val builder = StringBuilder()

        builder.appendLine("╔═══════════════════════════════════════════════════╗")
        builder.appendLine("║  LOADOUT ${if (loadout.isFinalized) "[EQUIPPED]" else "[BUILDING]".padEnd(8)}                      ║")
        builder.appendLine("╠═══════════════════════════════════════════════════╣")
        builder.appendLine("║  Storage: $totalSlots/${loadout.maxSlots} slots used${" ".repeat(29 - totalSlots.toString().length - loadout.maxSlots.toString().length)}║")
        builder.appendLine("╠═══════════════════════════════════════════════════╣")

        loadout.tools.forEachIndexed { index, tool ->
            val definition = ToolCatalog.getDefinition(tool.type, tool.version, tool.category)
            val name = definition?.name ?: "${tool.type} ${tool.version}"
            val stateIcon = when (tool.state) {
                ToolState.READY -> "●"
                ToolState.ACTIVE -> "▶"
                ToolState.SPENT -> "○"
                ToolState.EXHAUSTED -> "✗"
            }
            val charges = if (tool.remainingCharges != null) " [${tool.remainingCharges} uses]" else ""

            builder.appendLine("║  [$index] $stateIcon $name$charges${" ".repeat(45 - index.toString().length - name.length - charges.length)}║")
        }

        builder.appendLine("╚═══════════════════════════════════════════════════╝")

        if (!loadout.isFinalized) {
            builder.appendLine("\nUse 'loadout equip' to finalize this loadout.")
        }

        return builder.toString()
    }

    /**
     * Build catalog map for slot calculations
     */
    private fun buildCatalogMap(): Map<String, ToolDefinition> {
        return ToolCatalog.getAllDefinitions().associateBy {
            "${it.type.name}-${it.version.name}${if (it.category == ToolCategory.CONSUMABLE) "-CONSUMABLE" else ""}"
        }
    }
}
