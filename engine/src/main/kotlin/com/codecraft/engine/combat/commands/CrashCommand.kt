package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Crash — scans a row on the opponent's grid. Returns green (ship present) or red (clear).
 * Result is visible ONLY to the player who used the command.
 *
 * Params: { "row": Int }
 */
object CrashCommand : CommandHandler {
    override val name = "Crash"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val row = (params["row"] as? Number)?.toInt()
            ?: return CommandResult(false, "Missing row parameter", session)

        val opponentShips = session.shipsOf(session.opponentOf(playerId))
        val shipPresent = opponentShips.any { ship ->
            ship.squares.any { it.row == row }
        }

        return CommandResult(
            success        = true,
            message        = if (shipPresent) "Row $row: SIGNAL DETECTED" else "Row $row: CLEAR",
            updatedSession = session,
            visibleToShooter = mapOf(
                "command"    to "Crash",
                "row"        to row,
                "result"     to if (shipPresent) "green" else "red",
                "shipPresent" to shipPresent,
            ),
        )
    }
}
