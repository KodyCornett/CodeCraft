package com.codecraft.engine.command

import com.codecraft.engine.protocol.CommandResponse
import com.codecraft.engine.protocol.GameEvent
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.session.GameSession

/**
 * Base interface for all game commands
 */
interface Command {
    val name: String
    val description: String
    val usage: String
    val category: CommandCategory

    /**
     * Execute the command
     */
    fun execute(session: GameSession, args: List<String>): CommandResult
}

enum class CommandCategory {
    SYSTEM,      // help, clear, etc.
    FILESYSTEM,  // ls, cd, cat
    NETWORK,     // scan, connect, disconnect
    HACKING,     // exploit, probe, crack
    UTILITY      // mail, sentinel, etc.
}

/**
 * Result of command execution
 */
data class CommandResult(
    val output: String,
    val success: Boolean = true,
    val delayMs: Int = 100,
    val stateChanges: StateChanges? = null,
    val events: List<GameEvent> = emptyList(),
    val exposureChange: Double = 0.0
) {
    companion object {
        fun success(output: String, delayMs: Int = 100) = CommandResult(
            output = output,
            success = true,
            delayMs = delayMs
        )

        fun error(message: String) = CommandResult(
            output = message,
            success = false,
            delayMs = 50
        )

        fun withStateChange(
            output: String,
            stateChanges: StateChanges,
            delayMs: Int = 100,
            exposureChange: Double = 0.0
        ) = CommandResult(
            output = output,
            success = true,
            delayMs = delayMs,
            stateChanges = stateChanges,
            exposureChange = exposureChange
        )
    }

    /**
     * Convert to protocol response
     */
    fun toResponse(): CommandResponse {
        return CommandResponse(
            success = success,
            output = output,
            error = if (!success) output else null,
            delayMs = delayMs,
            stateChanges = stateChanges,
            gameEvents = events,
            traceIncrease = exposureChange
        )
    }
}
