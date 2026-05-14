package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Blackout — defensive command. Silences all incoming offensive commands for 3 turns.
 * When active, the opponent's commands fire and go on cooldown but have no effect.
 * The opponent is NOT notified that Blackout is active.
 *
 * A "turn" here = one of the opponent's command uses while Blackout is active.
 *
 * No params required.
 */
object BlackoutCommand : CommandHandler {
    override val name = "Blackout"
    const val TURNS = 3

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.Blackout && it.affectedPlayerId == session.opponentOf(playerId)
        }
        // affectedPlayerId is the OPPONENT — commands aimed AT this player are silenced
        val updated = session.copy(
            activeEffects = effects + CombatEffect.Blackout(
                affectedPlayerId = session.opponentOf(playerId),
                turnsRemaining   = TURNS,
            )
        )

        return CommandResult(
            success        = true,
            message        = "Blackout active — incoming commands nullified for $TURNS turns",
            updatedSession = updated,
        )
    }
}
