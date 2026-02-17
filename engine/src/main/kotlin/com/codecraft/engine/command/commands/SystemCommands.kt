package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.session.GameSession

/**
 * help - Display available commands
 */
class HelpCommand(private val registry: CommandRegistry) : Command {
    override val name = "help"
    override val description = "Display available commands"
    override val usage = "help [command]"
    override val category = CommandCategory.SYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isNotEmpty()) {
            // Show help for specific command
            val cmd = registry.get(args[0])
            if (cmd != null && session.player.hasCommand(cmd.name)) {
                val output = buildString {
                    appendLine("${cmd.name.uppercase()} - ${cmd.description}")
                    appendLine("─".repeat(50))
                    appendLine("Usage: ${cmd.usage}")
                    appendLine("Category: ${cmd.category.name.lowercase()}")
                }
                return CommandResult.success(output)
            }
            return CommandResult.error("help: no help available for '${args[0]}'")
        }

        // Show all available commands
        val output = buildString {
            appendLine("AVAILABLE COMMANDS")
            appendLine("─".repeat(50))

            CommandCategory.entries.forEach { category ->
                val cmds = registry.getByCategory(category)
                    .filter { session.player.hasCommand(it.name) }

                if (cmds.isNotEmpty()) {
                    appendLine("\n${category.name}:")
                    cmds.forEach { cmd ->
                        appendLine("  ${cmd.name.padEnd(14)} ${cmd.description}")
                    }
                }
            }

            appendLine()
            appendLine("─".repeat(50))
            appendLine("Type 'help <command>' for detailed information.")
        }

        return CommandResult.success(output, delayMs = 150)
    }
}

/**
 * clear - Clear the terminal
 */
class ClearCommand : Command {
    override val name = "clear"
    override val description = "Clear the terminal screen"
    override val usage = "clear"
    override val category = CommandCategory.SYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        // Return empty string - frontend handles the clear
        return CommandResult.success("", delayMs = 0)
    }
}

/**
 * whoami - Display current user
 */
class WhoamiCommand : Command {
    override val name = "whoami"
    override val description = "Display current user identity"
    override val usage = "whoami"
    override val category = CommandCategory.SYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val node = session.getCurrentNode()
        val user = if (node.compromised) "root" else "guest"
        return CommandResult.success("$user@${node.name}")
    }
}
