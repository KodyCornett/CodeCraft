package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Trojan — hides inside one of your own ships. When that ship is sunk by the opponent,
 * a negative effect triggers on them (currently: exposes one of their remaining ship squares).
 *
 * The opponent does not know a Trojan is active.
 *
 * Params: { "shipId": String }
 */
object TrojanCommand : CommandHandler {
    override val name = "Trojan"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val shipId = params["shipId"] as? String
            ?: return CommandResult(false, "Missing shipId parameter", session)

        val myShips = session.shipsOf(playerId)
        val ship = myShips.firstOrNull { it.shipId == shipId }
            ?: return CommandResult(false, "Ship $shipId not found", session)

        if (ship.isSunk) return CommandResult(false, "Cannot plant Trojan on a sunk ship", session)

        // Mark ship as having a Trojan
        val updatedShip = ship.copy(hasTrojan = true)
        val updatedShips = myShips.map { if (it.shipId == shipId) updatedShip else it }
        val updatedSession = if (playerId == session.player1Id)
            session.copy(player1Ships = updatedShips)
        else
            session.copy(player2Ships = updatedShips)

        // Also register a Trojan effect so CombatManager knows to trigger it on sink
        val effects = updatedSession.activeEffects + CombatEffect.Trojan(playerId, shipId)

        return CommandResult(
            success        = true,
            message        = "Trojan planted in ship $shipId",
            updatedSession = updatedSession.copy(activeEffects = effects),
        )
    }
}
