package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*
import com.codecraft.engine.models.Coordinate

/**
 * DDOS FOG — blocks a 2×2 area for 2 turns. When the opponent fires at a fogged square,
 * they receive "Signal Blocked" and no permanent marker is left. The shot is consumed
 * (not refunded) — the opponent just wasted a shot into the fog.
 *
 * A "turn" here = one of the opponent's shot attempts while fog is active.
 *
 * Params: { "row": Int, "col": Int }  ← top-left of the 2×2 zone
 */
object DdosFogCommand : CommandHandler {
    override val name = "DDOS FOG"
    const val TURNS = 2

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val row = (params["row"] as? Number)?.toInt() ?: return CommandResult(false, "Missing row", session)
        val col = (params["col"] as? Number)?.toInt() ?: return CommandResult(false, "Missing col", session)

        // Validate: the 2×2 block must fit inside the grid
        if (row + 1 >= session.gridSize || col + 1 >= session.gridSize)
            return CommandResult(false, "Fog zone extends outside the grid", session)

        val topLeft = Coordinate(row, col)
        val opponentId = session.opponentOf(playerId)

        // Remove any existing fog for this opponent
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.DdosFog && it.affectedPlayerId == opponentId
        }
        val updated = session.copy(
            activeEffects = effects + CombatEffect.DdosFog(opponentId, topLeft, TURNS)
        )

        return CommandResult(
            success           = true,
            message           = "DDOS FOG deployed at ($row,$col) for $TURNS turns",
            updatedSession    = updated,
            visibleToOpponent = mapOf("event" to "FOG_DEPLOYED"),  // no coordinates revealed
        )
    }
}
