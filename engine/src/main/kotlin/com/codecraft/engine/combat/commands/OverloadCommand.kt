package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Overload — the opponent's very next shot will be displaced to a random adjacent square.
 * The opponent doesn't know this happened; they aim at one square and hit another.
 *
 * Implementation: adds an Overload effect for the opponent.
 * When that player fires, CombatManager checks for Overload and displaces the coordinate.
 *
 * No params required.
 */
object OverloadCommand : CommandHandler {
    override val name = "Overload"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val opponentId = session.opponentOf(playerId)

        // Remove any existing Overload for opponent (shouldn't stack)
        val effects = session.activeEffects.filterNot { it is CombatEffect.Overload && it.affectedPlayerId == opponentId }
        val updated = session.copy(activeEffects = effects + CombatEffect.Overload(opponentId))

        return CommandResult(
            success        = true,
            message        = "Overload armed",
            updatedSession = updated,
        )
    }
}
