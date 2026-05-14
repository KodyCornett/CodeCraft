package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Scramble — wipes the opponent's shot-history view for up to 3 of their shots.
 * The opponent's markers disappear from their board; they lose track of what they've tried.
 * Ends early if the opponent scores a hit during the scramble window.
 *
 * No params required.
 */
object ScrambleCommand : CommandHandler {
    override val name = "Scramble"
    const val SCRAMBLE_SHOTS = 3

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val opponentId = session.opponentOf(playerId)

        val effects = session.activeEffects.filterNot {
            it is CombatEffect.Scramble && it.affectedPlayerId == opponentId
        }
        val updated = session.copy(
            activeEffects = effects + CombatEffect.Scramble(opponentId, SCRAMBLE_SHOTS)
        )

        return CommandResult(
            success           = true,
            message           = "Scramble active — opponent board history wiped",
            updatedSession    = updated,
            visibleToOpponent = mapOf("event" to "SCRAMBLE_START"),
        )
    }
}
