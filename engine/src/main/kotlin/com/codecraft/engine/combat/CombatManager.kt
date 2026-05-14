package com.codecraft.engine.combat

import com.codecraft.engine.combat.commands.*
import com.codecraft.engine.models.Coordinate
import com.codecraft.engine.models.Ship
import org.slf4j.LoggerFactory
import java.util.UUID
import java.util.concurrent.ConcurrentHashMap
import kotlin.random.Random

/**
 * Manages all active combat sessions.
 *
 * Shot processing pipeline (in order):
 *   1. Phase / valid-player guard
 *   2. Overload — displace coordinate
 *   3. PacketFlood — check deadline
 *   4. DDOS FOG — block if in zone
 *   5. SQL_Injection — false miss on first hit
 *   6. Real hit/miss against ship grid
 *   7. On hit: BufferOverflow, Scramble end-early check
 *   8. On sink: Trojan trigger, RootKit steal
 *   9. Exploit — grant retaliation shot
 *  10. Win condition check
 *  11. TraceBar update
 *
 * @param clock injectable for testing time-sensitive logic (PacketFlood timers).
 */
class CombatManager(
    private val clock: () -> Long = { System.currentTimeMillis() },
) {

    private val log = LoggerFactory.getLogger(CombatManager::class.java)
    internal val sessions = ConcurrentHashMap<String, CombatSession>()

    // All known command handlers keyed by name
    private val commandHandlers: Map<String, CommandHandler> = listOf(
        DatabombCommand,
        OverloadCommand,
        CrashCommand,
        TrojanCommand,
        ExploitCommand,
        PacketFloodCommand,
        ScrambleCommand,
        SqlInjectionCommand,
        ForkBombCommand,
        DdosFogCommand,
        RootKitInstallCommand,
        BufferOverflowCommand,
        BlackoutCommand,
    ).associateBy { it.name }

    // -------------------------------------------------------------------------
    // Session lifecycle
    // -------------------------------------------------------------------------

    /**
     * Creates a new combat session in PRE_COMBAT phase.
     * Grid size is derived from the average bounty level of both players and the node tier.
     */
    fun initiateCombat(
        residentId: String,
        intruderId: String,
        nodeId: String,
        residentBounty: Int = 0,
        intruderBounty: Int = 0,
        nodeTier: Int = 1,
    ): CombatSession {
        val avgBounty = (residentBounty + intruderBounty) / 2
        val gridSize  = calculateGridSize(avgBounty, nodeTier)

        val session = CombatSession(
            combatId   = UUID.randomUUID().toString(),
            player1Id  = residentId,
            player2Id  = intruderId,
            nodeId     = nodeId,
            gridSize   = gridSize,
            startedAt  = clock(),
        )
        sessions[session.combatId] = session
        log.info("Combat initiated: ${session.combatId} resident=$residentId intruder=$intruderId grid=${gridSize}x${gridSize}")
        return session
    }

    fun getSession(combatId: String): CombatSession? = sessions[combatId]

    // -------------------------------------------------------------------------
    // Ship placement
    // -------------------------------------------------------------------------

    /**
     * Records a player's ship placement. When both players are ready, transitions to ACTIVE.
     *
     * @param ships list of ships with their grid coordinates.
     */
    fun placeShips(combatId: String, playerId: String, ships: List<Ship>): Result<CombatSession> {
        val session = sessions[combatId] ?: return Result.failure(IllegalArgumentException("Combat not found"))

        if (session.phase != CombatPhase.PRE_COMBAT && session.phase != CombatPhase.PLACING_SHIPS)
            return Result.failure(IllegalStateException("Ships can only be placed before ACTIVE phase"))

        // Validate placement
        val validationError = validateShipPlacement(ships, session.gridSize)
        if (validationError != null) return Result.failure(IllegalArgumentException(validationError))

        val updated = when (playerId) {
            session.player1Id -> session.copy(player1Ships = ships, player1Ready = true, phase = CombatPhase.PLACING_SHIPS)
            session.player2Id -> session.copy(player2Ships = ships, player2Ready = true, phase = CombatPhase.PLACING_SHIPS)
            else -> return Result.failure(IllegalArgumentException("Player $playerId is not in this combat"))
        }.let { s ->
            // Both ready → go ACTIVE
            if (s.bothReady) s.copy(phase = CombatPhase.ACTIVE) else s
        }

        sessions[combatId] = updated
        return Result.success(updated)
    }

    // -------------------------------------------------------------------------
    // Shot processing
    // -------------------------------------------------------------------------

    fun processShot(
        combatId: String,
        shooterId: String,
        coordinate: Coordinate,
    ): Result<ShotResult> {
        val session = sessions[combatId] ?: return Result.failure(IllegalArgumentException("Combat not found"))

        if (session.phase != CombatPhase.ACTIVE)
            return Result.failure(IllegalStateException("Combat is not in ACTIVE phase"))

        if (shooterId != session.player1Id && shooterId != session.player2Id)
            return Result.failure(IllegalArgumentException("Player $shooterId is not in this combat"))

        var s = session
        val opponentId = s.opponentOf(shooterId)

        // ---- 1. Overload: displace coordinate ----
        val overload = s.activeEffects.filterIsInstance<CombatEffect.Overload>()
            .firstOrNull { it.affectedPlayerId == shooterId }
        val actualCoord = if (overload != null) {
            s = s.copy(activeEffects = s.activeEffects - overload)
            displaceCoordinate(coordinate, s.gridSize)
        } else coordinate

        // ---- 2. PacketFlood: deadline check ----
        val flood = s.activeEffects.filterIsInstance<CombatEffect.PacketFlood>()
            .firstOrNull { it.affectedPlayerId == shooterId }
        if (flood != null) {
            if (clock() > flood.deadlineAt) {
                // Forfeit this shot — still counts against flood budget
                val updatedFlood = if (flood.shotsRemaining > 1)
                    flood.copy(shotsRemaining = flood.shotsRemaining - 1, deadlineAt = clock() + flood.timerMs)
                else null
                val newEffects = s.activeEffects.filterNot { it === flood } +
                        listOfNotNull(updatedFlood)
                s = s.copy(activeEffects = newEffects)
                sessions[combatId] = s
                return Result.success(ShotResult(
                    hit = false, sunk = false, coordinate = actualCoord,
                    updatedSession = s,
                ))
            }
            // Consume one flood charge and reset deadline for next shot
            val updatedFlood = if (flood.shotsRemaining > 1)
                flood.copy(shotsRemaining = flood.shotsRemaining - 1, deadlineAt = clock() + flood.timerMs)
            else null
            s = s.copy(activeEffects = s.activeEffects.filterNot { it === flood } + listOfNotNull(updatedFlood))
        }

        // ---- 3. DDOS FOG: block if in zone ----
        val fog = s.activeEffects.filterIsInstance<CombatEffect.DdosFog>()
            .firstOrNull { it.affectedPlayerId == shooterId }
        if (fog != null && isInFogZone(actualCoord, fog.topLeft)) {
            val updatedFog = if (fog.turnsRemaining > 1) fog.copy(turnsRemaining = fog.turnsRemaining - 1) else null
            s = s.copy(activeEffects = s.activeEffects.filterNot { it === fog } + listOfNotNull(updatedFog))
            sessions[combatId] = s
            return Result.success(ShotResult(
                hit = false, sunk = false, coordinate = actualCoord,
                isBlocked = true, updatedSession = s,
            ))
        }

        // ---- 4. SQL_Injection: false miss on first hit ----
        val opponentShipsMutable = s.shipsOf(opponentId).toMutableList()
        val hitShipIndex = opponentShipsMutable.indexOfFirst { actualCoord in it.squares }
        val sqlEffect = if (hitShipIndex >= 0) {
            s.activeEffects.filterIsInstance<CombatEffect.SqlInjection>()
                .firstOrNull { it.affectedPlayerId == shooterId && it.shipId == opponentShipsMutable[hitShipIndex].shipId && !it.used }
        } else null

        if (sqlEffect != null) {
            // First hit reads as a miss — mark SQL injection as used
            val updatedSql = sqlEffect.copy(used = true)
            s = s.copy(activeEffects = s.activeEffects.map { if (it === sqlEffect) updatedSql else it })
            sessions[combatId] = s
            return Result.success(ShotResult(
                hit = false, sunk = false, coordinate = actualCoord,
                isFalseMiss = true, updatedSession = s,
            ))
        }

        // ---- 5. Process real hit/miss ----
        val realHit = hitShipIndex >= 0
        var trojanFired   = false
        var stolenCommand: String? = null
        var exploitGranted = false

        if (realHit) {
            val targetShip = opponentShipsMutable[hitShipIndex].hit(actualCoord)
            opponentShipsMutable[hitShipIndex] = targetShip
            val wasSunk = targetShip.isSunk

            // Update opponent's ship list
            s = if (opponentId == s.player1Id)
                s.copy(player1Ships = opponentShipsMutable)
            else
                s.copy(player2Ships = opponentShipsMutable)

            // ---- 6. BufferOverflow on hit ----
            val bufferOverflow = s.activeEffects.filterIsInstance<CombatEffect.BufferOverflow>()
                .firstOrNull { it.ownerPlayerId == shooterId }
            if (bufferOverflow != null) {
                s = applyBufferOverflow(s, opponentId)
                s = s.copy(activeEffects = s.activeEffects - bufferOverflow)
            }

            // ---- 7. Scramble: end early if opponent hits ----
            // (This is checked when the OPPONENT fires, but here shooterId just hit — we don't end scramble)
            // Actually: Scramble affects shooterId (opponent of the Scramble user), so we check
            // if shooterId is scrambled and decrement their counter.
            val scramble = s.activeEffects.filterIsInstance<CombatEffect.Scramble>()
                .firstOrNull { it.affectedPlayerId == shooterId }
            if (scramble != null) {
                // Scramble ends early if the scrambled player scores a hit
                s = s.copy(activeEffects = s.activeEffects - scramble)
            }

            // ---- 8. On sink: Trojan + RootKit ----
            if (wasSunk) {
                val trojan = s.activeEffects.filterIsInstance<CombatEffect.Trojan>()
                    .firstOrNull { it.ownerPlayerId == opponentId && it.shipId == targetShip.shipId }
                if (trojan != null) {
                    trojanFired = true
                    s = applyTrojanEffect(s, shooterId)
                    s = s.copy(activeEffects = s.activeEffects - trojan)
                }

                val rootKit = s.activeEffects.filterIsInstance<CombatEffect.RootKit>()
                    .firstOrNull { it.ownerPlayerId == shooterId }
                if (rootKit != null) {
                    val stolen = stealCommand(s, opponentId)
                    stolenCommand = stolen?.first
                    if (stolen != null) s = stolen.second
                    s = s.copy(activeEffects = s.activeEffects - rootKit)
                }
            }

            // ---- 9. Exploit: grant retaliation to opponent ----
            val exploit = s.activeEffects.filterIsInstance<CombatEffect.Exploit>()
                .firstOrNull { it.ownerPlayerId == opponentId }
            if (exploit != null) {
                exploitGranted = true
                s = s.copy(activeEffects = s.activeEffects - exploit)
            }
        } else {
            // Miss — decrement Scramble counter for the shooter (if scrambled)
            val scramble = s.activeEffects.filterIsInstance<CombatEffect.Scramble>()
                .firstOrNull { it.affectedPlayerId == shooterId }
            if (scramble != null) {
                val updated = if (scramble.shotsRemaining > 1) scramble.copy(shotsRemaining = scramble.shotsRemaining - 1) else null
                s = s.copy(activeEffects = s.activeEffects.filterNot { it === scramble } + listOfNotNull(updated))
            }
        }

        // ---- 10. Record shot in history ----
        val record = ShotRecord(actualCoord, realHit, wasSunk = realHit && opponentShipsMutable.getOrNull(hitShipIndex)?.isSunk == true)
        s = if (shooterId == s.player1Id)
            s.copy(player1ShotHistory = s.player1ShotHistory + record)
        else
            s.copy(player2ShotHistory = s.player2ShotHistory + record)

        // ---- 11. Update TraceBar + check win ----
        if (realHit) s = s.withUpdatedTraceBar(shooterId)
        val winnerId = checkWinCondition(s)
        if (winnerId != null) s = s.copy(phase = CombatPhase.RESOLVED)

        sessions[combatId] = s
        return Result.success(ShotResult(
            hit              = realHit,
            sunk             = realHit && opponentShipsMutable.getOrNull(hitShipIndex)?.isSunk == true,
            coordinate       = actualCoord,
            trojanFired      = trojanFired,
            stolenCommand    = stolenCommand,
            updatedSession   = s,
            winnerId         = winnerId,
        ))
    }

    // -------------------------------------------------------------------------
    // Command execution
    // -------------------------------------------------------------------------

    fun processCommand(
        combatId: String,
        playerId: String,
        commandName: String,
        params: Map<String, Any>,
    ): Result<CommandResult> {
        val session = sessions[combatId] ?: return Result.failure(IllegalArgumentException("Combat not found"))

        if (session.phase != CombatPhase.ACTIVE)
            return Result.failure(IllegalStateException("Commands can only be used during ACTIVE phase"))

        // Check already used
        if (commandName in session.commandsUsedBy(playerId))
            return Result.failure(IllegalStateException("$commandName has already been used"))

        // Check disabled
        val disabledTurns = session.commandsDisabledFor(playerId)[commandName] ?: 0
        if (disabledTurns > 0) {
            // Command fires but has no effect — mark it as used (permanent cooldown)
            val updated = markCommandUsed(session, playerId, commandName)
            sessions[combatId] = updated
            return Result.success(CommandResult(true, "$commandName used (silently disabled)", updated))
        }

        // Check if this player is protected by Blackout (they used Blackout, so opponent commands are nullified)
        val opponentId = session.opponentOf(playerId)
        val blackout = session.activeEffects.filterIsInstance<CombatEffect.Blackout>()
            .firstOrNull { it.affectedPlayerId == opponentId }  // opponentId is "protected"
        val isBlackedOut = blackout != null

        val handler = commandHandlers[commandName]
            ?: return Result.failure(IllegalArgumentException("Unknown command: $commandName"))

        val result = if (isBlackedOut) {
            // Command fires, goes on cooldown, but does nothing
            val decremented = if (blackout!!.turnsRemaining > 1)
                blackout.copy(turnsRemaining = blackout.turnsRemaining - 1)
            else null
            val newEffects = session.activeEffects.filterNot { it === blackout } + listOfNotNull(decremented)
            val updated = markCommandUsed(session.copy(activeEffects = newEffects), playerId, commandName)
            CommandResult(true, "$commandName used (nullified by Blackout)", updated)
        } else {
            val r = handler.execute(session, playerId, params)
            if (r.success) r.copy(updatedSession = markCommandUsed(r.updatedSession, playerId, commandName))
            else r
        }

        if (result.success) sessions[combatId] = result.updatedSession
        return Result.success(result)
    }

    // -------------------------------------------------------------------------
    // Win condition
    // -------------------------------------------------------------------------

    fun checkWinCondition(session: CombatSession): String? {
        if (session.player2Ships.isNotEmpty() && session.player2Ships.all { it.isSunk }) return session.player1Id
        if (session.player1Ships.isNotEmpty() && session.player1Ships.all { it.isSunk }) return session.player2Id
        return null
    }

    /**
     * Finalises the combat: removes the session from active map and returns the resolved snapshot.
     * The caller (WebSocket handler) is responsible for calling Laravel's POST /api/combat/result.
     */
    fun resolveCombat(combatId: String, winnerId: String): CombatSession? {
        val session = sessions.remove(combatId) ?: return null
        return session.copy(phase = CombatPhase.RESOLVED)
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private fun calculateGridSize(avgBounty: Int, nodeTier: Int): Int {
        val bountyBonus = minOf(3, avgBounty / 10)
        val tierBonus   = minOf(2, nodeTier - 1)
        return 7 + bountyBonus + tierBonus
    }

    private fun validateShipPlacement(ships: List<Ship>, gridSize: Int): String? {
        val allSquares = mutableSetOf<Coordinate>()
        for (ship in ships) {
            for (sq in ship.squares) {
                if (sq.row !in 0 until gridSize || sq.col !in 0 until gridSize)
                    return "Ship ${ship.shipId} has a square outside the grid"
                if (sq in allSquares) return "Ships overlap at ($sq.row, $sq.col)"
                allSquares += sq
            }
            if (ship.squares.size < 2) return "Ship ${ship.shipId} must have at least 2 squares"
        }
        return null
    }

    private fun displaceCoordinate(coord: Coordinate, gridSize: Int): Coordinate {
        val adjacents = listOf(
            Coordinate(coord.row - 1, coord.col),
            Coordinate(coord.row + 1, coord.col),
            Coordinate(coord.row, coord.col - 1),
            Coordinate(coord.row, coord.col + 1),
        ).filter { it.row in 0 until gridSize && it.col in 0 until gridSize }
        return adjacents.randomOrNull() ?: coord
    }

    private fun isInFogZone(coord: Coordinate, topLeft: Coordinate): Boolean =
        coord.row in topLeft.row..(topLeft.row + 1) && coord.col in topLeft.col..(topLeft.col + 1)

    private fun markCommandUsed(session: CombatSession, playerId: String, commandName: String): CombatSession =
        if (playerId == session.player1Id)
            session.copy(player1CommandsUsed = session.player1CommandsUsed + commandName)
        else
            session.copy(player2CommandsUsed = session.player2CommandsUsed + commandName)

    /** Disables a random unused command on [targetPlayerId] for the rest of combat. */
    private fun applyBufferOverflow(session: CombatSession, targetPlayerId: String): CombatSession {
        val allCommands = commandHandlers.keys
        val used        = session.commandsUsedBy(targetPlayerId)
        val disabled    = session.commandsDisabledFor(targetPlayerId)
        val candidates  = allCommands.filter { it !in used && it !in disabled }
        val target      = candidates.randomOrNull() ?: return session
        val newDisabled = disabled + (target to Int.MAX_VALUE)   // permanent
        return if (targetPlayerId == session.player1Id)
            session.copy(player1CommandsDisabled = newDisabled)
        else
            session.copy(player2CommandsDisabled = newDisabled)
    }

    /** Trojan effect: reveals one unhit square of the triggering player's remaining ships. */
    private fun applyTrojanEffect(session: CombatSession, victimId: String): CombatSession {
        // The trojan "exposes" one unhit square of the victim's remaining ships
        // by adding a fake "detected" marker (implemented as a visibleToShooter event at call site)
        // No state change needed here — the ShotResult.trojanFired flag is used by the WebSocket handler
        return session
    }

    /**
     * Steals one unused command from [targetPlayerId].
     * Returns (commandName, updatedSession) or null if nothing stealable.
     */
    private fun stealCommand(session: CombatSession, targetPlayerId: String): Pair<String, CombatSession>? {
        val used       = session.commandsUsedBy(targetPlayerId)
        val disabled   = session.commandsDisabledFor(targetPlayerId)
        val candidates = commandHandlers.keys.filter { it !in used && it !in disabled }
        val stolen     = candidates.randomOrNull() ?: return null

        // Mark it as used for the target (they lose access)
        val updated = markCommandUsed(session, targetPlayerId, stolen)
        return Pair(stolen, updated)
    }
}
