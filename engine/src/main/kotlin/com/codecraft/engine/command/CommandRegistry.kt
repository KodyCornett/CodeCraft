package com.codecraft.engine.command

import com.codecraft.engine.command.commands.*
import com.codecraft.engine.session.GameSession

/**
 * Registry of all available commands
 */
class CommandRegistry {
    private val commands = mutableMapOf<String, Command>()

    init {
        // Register all commands
        registerSystemCommands()
        registerFilesystemCommands()
        registerNetworkCommands()
        registerUtilityCommands()
    }

    private fun registerSystemCommands() {
        register(HelpCommand(this))
        register(ClearCommand())
    }

    private fun registerFilesystemCommands() {
        register(LsCommand())
        register(CdCommand())
        register(CatCommand())
        register(PwdCommand())
    }

    private fun registerNetworkCommands() {
        register(ScanCommand())
        register(ConnectCommand())
        register(DisconnectCommand())
        register(ProbeCommand())
    }

    private fun registerUtilityCommands() {
        register(MailCommand())
        register(SentinelCommand())
        register(WhoamiCommand())
    }

    fun register(command: Command) {
        commands[command.name.lowercase()] = command
    }

    fun get(name: String): Command? {
        return commands[name.lowercase()]
    }

    fun getAll(): List<Command> {
        return commands.values.toList()
    }

    fun getByCategory(category: CommandCategory): List<Command> {
        return commands.values.filter { it.category == category }
    }

    /**
     * Execute a command string
     */
    fun execute(session: GameSession, commandLine: String): CommandResult {
        val parts = commandLine.trim().split(Regex("\\s+"))
        if (parts.isEmpty() || parts[0].isBlank()) {
            return CommandResult.success("")
        }

        val commandName = parts[0].lowercase()
        val args = parts.drop(1)

        // Check if player has command unlocked
        if (!session.player.hasCommand(commandName)) {
            return CommandResult.error("$commandName: command not found")
        }

        // Get command
        val command = get(commandName)
            ?: return CommandResult.error("$commandName: command not found")

        // Execute and handle exposure changes
        val result = command.execute(session, args)

        // Apply exposure change if any
        if (result.exposureChange != 0.0) {
            session.player.increaseExposure(result.exposureChange)
        }

        return result
    }
}
