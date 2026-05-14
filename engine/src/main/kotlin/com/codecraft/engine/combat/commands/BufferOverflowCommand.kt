package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * Buffer Overflow — if your next shot hits, one of the opponent's unused commands is
 * silently disabled for the rest of combat. The opponent doesn't know which command
 * was blocked. If they use it, it fires but has no effect (and goes on permanent cooldown).
 *
 * No params required.
 */
object BufferOverflowCommand : CommandHandler {
    override val name = "Buffer Overflow"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.BufferOverflow && it.ownerPlayerId == playerId
        }
        val updated = session.copy(activeEffects = effects + CombatEffect.BufferOverflow(playerId))

        return CommandResult(
            success        = true,
            message        = "Buffer Overflow primed — next hit will disable an opponent command",
            updatedSession = updated,
        )
    }
}
