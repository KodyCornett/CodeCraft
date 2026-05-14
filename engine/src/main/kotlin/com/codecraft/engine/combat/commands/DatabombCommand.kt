package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*
import com.codecraft.engine.models.Coordinate

/**
 * Databomb — plants a false "hit" marker on the opponent's board view.
 * The opponent sees a hit at [coordinate] and may waste shots nearby.
 * There is a subtle visual tell that the client renders (but does not announce).
 *
 * Implementation: adds a fake ShotRecord (isFake=true) to the opponent's shot history.
 * The opponent's client shows it as a hit; the server never counts it as a real hit.
 *
 * Params: { "row": Int, "col": Int }
 */
object DatabombCommand : CommandHandler {
    override val name = "Databomb"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val row = (params["row"] as? Number)?.toInt()
            ?: return CommandResult(false, "Missing row parameter", session)
        val col = (params["col"] as? Number)?.toInt()
            ?: return CommandResult(false, "Missing col parameter", session)

        val coord = Coordinate(row, col)
        val opponentId = session.opponentOf(playerId)

        // Add fake hit record to OPPONENT's shot history (as if they fired and hit there)
        val fakeRecord = ShotRecord(coord, wasHit = true, wasSunk = false, isFake = true)
        val updated = if (opponentId == session.player1Id)
            session.copy(player1ShotHistory = session.player1ShotHistory + fakeRecord)
        else
            session.copy(player2ShotHistory = session.player2ShotHistory + fakeRecord)

        return CommandResult(
            success        = true,
            message        = "Databomb planted",
            updatedSession = updated,
            visibleToOpponent = mapOf("event" to "BOARD_UPDATE", "fakeHit" to mapOf("row" to row, "col" to col)),
        )
    }
}
