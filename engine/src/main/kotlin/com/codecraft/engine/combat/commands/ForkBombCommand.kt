package com.codecraft.engine.combat.commands

import com.codecraft.engine.combat.*
import com.codecraft.engine.models.Coordinate

/**
 * Fork Bomb — fires a primary shot. If it hits (not a miss), a second shot fires
 * on an adjacent square of the attacker's choosing.
 *
 * Both shots are processed by CombatManager. ForkBomb just validates inputs;
 * the actual dual-shot processing happens in CombatManager.processShot with the
 * "forkCoord" param.
 *
 * Params: { "row": Int, "col": Int, "forkRow": Int, "forkCol": Int }
 */
object ForkBombCommand : CommandHandler {
    override val name = "Fork Bomb"

    override fun execute(session: CombatSession, playerId: String, params: Map<String, Any>): CommandResult {
        val row     = (params["row"]     as? Number)?.toInt() ?: return CommandResult(false, "Missing row",     session)
        val col     = (params["col"]     as? Number)?.toInt() ?: return CommandResult(false, "Missing col",     session)
        val forkRow = (params["forkRow"] as? Number)?.toInt() ?: return CommandResult(false, "Missing forkRow", session)
        val forkCol = (params["forkCol"] as? Number)?.toInt() ?: return CommandResult(false, "Missing forkCol", session)

        val primary = Coordinate(row, col)
        val fork    = Coordinate(forkRow, forkCol)

        // Validate adjacency — fork must be exactly 1 step away (no diagonals)
        val dr = Math.abs(primary.row - fork.row)
        val dc = Math.abs(primary.col - fork.col)
        if (!((dr == 1 && dc == 0) || (dr == 0 && dc == 1)))
            return CommandResult(false, "Fork coordinate must be adjacent (no diagonals)", session)

        // Validate within grid bounds
        if (fork.row !in 0 until session.gridSize || fork.col !in 0 until session.gridSize)
            return CommandResult(false, "Fork coordinate is out of bounds", session)

        // Validation passes — actual shot processing is handled by CombatManager
        // which checks for the FORK_BOMB command flag after processing the primary shot.
        return CommandResult(
            success        = true,
            message        = "Fork Bomb primed: primary=($row,$col) fork=($forkRow,$forkCol)",
            updatedSession = session,
            visibleToShooter = mapOf(
                "primary" to mapOf("row" to row, "col" to col),
                "fork"    to mapOf("row" to forkRow, "col" to forkCol),
            ),
        )
    }
}
