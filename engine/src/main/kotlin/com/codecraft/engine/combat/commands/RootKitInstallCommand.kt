package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*

/**
 * RootKit Install — if you sink a ship while this is active, you steal one use
 * of a randomly chosen unused command from the opponent's arsenal.
 *
 * DDOS FOG can block the kill shot and prevent the trigger.
 *
 * No params required.
 */
object RootKitInstallCommand : CommandHandler {
    override val name = "RootKit Install"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val effects = session.activeEffects.filterNot {
            it is CombatEffect.RootKit && it.ownerPlayerId == playerId
        }
        val updated = session.copy(activeEffects = effects + CombatEffect.RootKit(playerId))

        return CommandResult(
            success        = true,
            message        = "RootKit Install armed — will steal a command on next kill",
            updatedSession = updated,
        )
    }
}
