package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * SQL_Injection — applied at the start of combat to one of your own ships (3+ squares).
 * When the opponent first hits that ship, they receive a false MISS response.
 * The second hit on a different square of the same ship reveals the deception
 * and the false miss is updated to a real hit on the opponent's board.
 *
 * Params: { "shipId": String }
 */
object SqlInjectionCommand : CommandHandler {
    override val name = "SQL_Injection"
    const val MIN_SHIP_SIZE = 3

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val shipId = params["shipId"] as? String
            ?: return CommandResult(false, "Missing shipId parameter", session)

        val myShips = session.shipsOf(playerId)
        val ship = myShips.firstOrNull { it.shipId == shipId }
            ?: return CommandResult(false, "Ship $shipId not found", session)

        if (ship.squares.size < MIN_SHIP_SIZE)
            return CommandResult(false, "SQL_Injection requires a ship of $MIN_SHIP_SIZE+ squares", session)

        if (ship.sqlInjectionActive)
            return CommandResult(false, "SQL_Injection already active on this ship", session)

        // Remove any existing SqlInjection for this player
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.SqlInjection && it.affectedPlayerId == session.opponentOf(playerId)
        }

        val updatedShips = myShips.map { if (it.shipId == shipId) it.copy(sqlInjectionActive = true) else it }
        val updatedSession = if (playerId == session.player1Id)
            session.copy(player1Ships = updatedShips)
        else
            session.copy(player2Ships = updatedShips)

        // affectedPlayerId = opponent (they are the one being deceived)
        val withEffect = updatedSession.copy(
            activeEffects = effects + CombatEffect.SqlInjection(
                affectedPlayerId = session.opponentOf(playerId),
                shipId           = shipId,
            )
        )

        return CommandResult(
            success        = true,
            message        = "SQL_Injection armed on ship $shipId",
            updatedSession = withEffect,
        )
    }
}
