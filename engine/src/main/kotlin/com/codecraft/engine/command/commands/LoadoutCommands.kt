package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandCategory
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.domain.*
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.LoadoutManager
import com.codecraft.engine.session.ToolActivation

/**
 * LOADOUT command — Manage pre-mission equipment
 * Subcommands: build, add, remove, view, equip
 */
class LoadoutCommand : Command {
    override val name = "loadout"
    override val category = CommandCategory.UTILITY
    override val description = "Manage mission loadout"
    override val usage = "loadout <build|add|remove|view|equip> [args]"

    val examples = listOf(
        "loadout build           — Create new loadout",
        "loadout add cloak v1    — Add CLOAK V1 to loadout",
        "loadout remove 0        — Remove tool at index 0",
        "loadout view            — View current loadout",
        "loadout equip           — Finalize and equip loadout"
    )

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: $usage\n\nSubcommands:\n  build  — Create new loadout\n  add    — Add tool to loadout\n  remove — Remove tool from loadout\n  view   — View current loadout\n  equip  — Finalize loadout")
        }

        val manager = LoadoutManager(session)
        val subcommand = args[0].lowercase()

        return when (subcommand) {
            "build" -> handleBuild(manager)
            "add" -> handleAdd(manager, args.drop(1))
            "remove" -> handleRemove(manager, args.drop(1))
            "view" -> handleView(manager)
            "equip" -> handleEquip(manager)
            else -> CommandResult.error("Unknown subcommand: $subcommand\nUse 'loadout' for help.")
        }
    }

    private fun handleBuild(manager: LoadoutManager): CommandResult {
        val loadout = manager.buildLoadout()
        return CommandResult.success(
            "New loadout created.\n" +
            "Storage capacity: ${loadout.maxSlots} slots\n\n" +
            "Use 'loadout add <tool> <version>' to add tools.\n" +
            "Use 'loadout equip' when ready."
        )
    }

    private fun handleAdd(manager: LoadoutManager, args: List<String>): CommandResult {
        if (args.size < 2) {
            return CommandResult.error("Usage: loadout add <tool> <version> [consumable]\nExample: loadout add cloak v1")
        }

        // Parse tool spec: "cloak v1" or "cloak v1 consumable"
        val typeStr = args[0]
        val versionStr = args[1]
        val isConsumable = args.size > 2 && args[2].equals("consumable", ignoreCase = true)

        val type = try {
            ToolType.valueOf(typeStr.uppercase())
        } catch (e: IllegalArgumentException) {
            return CommandResult.error("Unknown tool type: $typeStr")
        }

        val version = try {
            ToolVersion.valueOf(versionStr.uppercase())
        } catch (e: IllegalArgumentException) {
            return CommandResult.error("Unknown version: $versionStr (use V1, V2, or V3)")
        }

        val category = if (isConsumable) ToolCategory.CONSUMABLE else ToolCategory.PERMANENT

        val result = manager.addToolToLoadout(type, version, category)
        return if (result.isSuccess) {
            CommandResult.success(result.getOrNull()!!)
        } else {
            CommandResult.error(result.exceptionOrNull()?.message ?: "Failed to add tool")
        }
    }

    private fun handleRemove(manager: LoadoutManager, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: loadout remove <index>\nExample: loadout remove 0")
        }

        val index = args[0].toIntOrNull()
            ?: return CommandResult.error("Invalid index: ${args[0]}")

        val result = manager.removeToolFromLoadout(index)
        return if (result.isSuccess) {
            CommandResult.success(result.getOrNull()!!)
        } else {
            CommandResult.error(result.exceptionOrNull()?.message ?: "Failed to remove tool")
        }
    }

    private fun handleView(manager: LoadoutManager): CommandResult {
        return CommandResult.success(manager.getLoadoutView())
    }

    private fun handleEquip(manager: LoadoutManager): CommandResult {
        val result = manager.finalizeLoadout()
        return if (result.isSuccess) {
            CommandResult.success(result.getOrNull()!!)
        } else {
            CommandResult.error(result.exceptionOrNull()?.message ?: "Failed to finalize loadout")
        }
    }
}

/**
 * USE command — Activate a tool from the loadout
 */
class UseCommand : Command {
    override val name = "use"
    override val category = CommandCategory.UTILITY
    override val description = "Activate a tool from your loadout"
    override val usage = "use <tool>"

    val examples = listOf(
        "use cloak      — Activate CLOAK tool",
        "use overdrive  — Activate OVERDRIVE tool",
        "use rollback   — Activate ROLLBACK tool"
    )

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("Usage: $usage\nExample: use cloak")
        }

        val toolTypeStr = args[0]
        val toolType = try {
            ToolType.valueOf(toolTypeStr.uppercase())
        } catch (e: IllegalArgumentException) {
            return CommandResult.error("Unknown tool: $toolTypeStr")
        }

        val activation = ToolActivation(session)
        val result = activation.activateTool(toolType)

        return if (result.isSuccess) {
            val activationResult = result.getOrNull()!!
            CommandResult.success(activation.formatActivationMessage(activationResult))
        } else {
            CommandResult.error(result.exceptionOrNull()?.message ?: "Failed to activate tool")
        }
    }
}
