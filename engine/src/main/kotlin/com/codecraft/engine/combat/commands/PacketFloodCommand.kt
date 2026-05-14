package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Packet Flood — the opponent's next 3 shots each carry a 5-second response timer.
 * If the SHOOT message arrives after the deadline, that shot is forfeited.
 *
 * Deadline logic: when this effect is active and the opponent sends SHOOT,
 * CombatManager checks System.currentTimeMillis() against deadlineAt.
 * deadlineAt is reset per shot (refreshed when each shot window opens).
 *
 * No params required.
 */
object PacketFloodCommand : CommandHandler {
    override val name = "Packet Flood"
    const val TIMER_MS = 5_000L
    const val FLOOD_SHOTS = 3

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val opponentId = session.opponentOf(playerId)
        val now = System.currentTimeMillis()

        // Remove any existing PacketFlood on this opponent
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.PacketFlood && it.affectedPlayerId == opponentId
        }
        val updated = session.copy(
            activeEffects = effects + CombatEffect.PacketFlood(
                affectedPlayerId = opponentId,
                shotsRemaining   = FLOOD_SHOTS,
                deadlineAt       = now + TIMER_MS,
                timerMs          = TIMER_MS,
            )
        )

        return CommandResult(
            success           = true,
            message           = "Packet Flood launched — opponent has ${TIMER_MS / 1000}s per shot",
            updatedSession    = updated,
            visibleToOpponent = mapOf("event" to "PACKET_FLOOD_START", "shotsAffected" to FLOOD_SHOTS, "timerSeconds" to (TIMER_MS / 1000)),
        )
    }
}
