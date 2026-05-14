package com.codecraft.engine.combat

/**
 * Every combat command implements this interface.
 *
 * execute() returns a [CommandResult] containing the mutated session and optional
 * per-player events. CombatManager dispatches to the right handler by name.
 */
interface CommandHandler {
    val name: String

    fun execute(
        session: CombatSession,
        playerId: String,
        params: Map<String, Any>,
    ): CommandResult
}

/**
 * The outcome of executing a command.
 *
 * [updatedSession]     the new session state after applying the command.
 * [visibleToShooter]   event payload sent only to the player who used the command.
 * [visibleToOpponent]  event payload sent only to the opponent (e.g., "your board changed").
 */
data class CommandResult(
    val success: Boolean,
    val message: String,
    val updatedSession: CombatSession,
    val visibleToShooter:  Map<String, Any> = emptyMap(),
    val visibleToOpponent: Map<String, Any> = emptyMap(),
)

/** The outcome of processing a shot. */
data class ShotResult(
    val hit: Boolean,
    val sunk: Boolean,
    val coordinate: com.codecraft.engine.models.Coordinate,
    val isFalseMiss: Boolean = false,   // SQL_Injection consumed
    val isBlocked: Boolean = false,      // DDOS FOG
    val trojanFired: Boolean = false,    // Trojan triggered on sink
    val stolenCommand: String? = null,   // RootKit triggered on sink
    val updatedSession: CombatSession,
    val winnerId: String? = null,
)
