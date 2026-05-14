package com.codecraft.engine.combat

import com.codecraft.engine.models.Coordinate
import com.codecraft.engine.models.Ship

/**
 * Full state of one active combat session.
 *
 * Immutable data class — every mutation produces a copy via copy().
 * CombatManager holds the authoritative instance in a ConcurrentHashMap.
 *
 * traceBar advances from 0.0 toward 1.0 as ships are sunk.
 * When one player's ships are all sunk → traceBar hits 1.0 for the opponent.
 */
data class CombatSession(
    val combatId: String,
    val player1Id: String,                   // resident (arrived first)
    val player2Id: String,                   // intruder
    val nodeId: String,
    val gridSize: Int,

    val player1Ships: List<Ship> = emptyList(),
    val player2Ships: List<Ship> = emptyList(),
    val player1Ready: Boolean = false,       // ships placed
    val player2Ready: Boolean = false,

    val player1ShotHistory: List<ShotRecord> = emptyList(),
    val player2ShotHistory: List<ShotRecord> = emptyList(),

    val player1CommandsUsed: Set<String> = emptySet(),
    val player2CommandsUsed: Set<String> = emptySet(),
    val player1CommandsDisabled: Map<String, Int> = emptyMap(),  // command → turns remaining
    val player2CommandsDisabled: Map<String, Int> = emptyMap(),

    val activeEffects: List<CombatEffect> = emptyList(),

    /** 0.0 = no progress; 1.0 = all opponent ships sunk (win). Tracks per-player separately. */
    val player1TraceBar: Float = 0.0f,
    val player2TraceBar: Float = 0.0f,

    val phase: CombatPhase = CombatPhase.PRE_COMBAT,
    val startedAt: Long = System.currentTimeMillis(),
) {
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    fun opponentOf(playerId: String): String =
        if (playerId == player1Id) player2Id else player1Id

    fun shipsOf(playerId: String): List<Ship> =
        if (playerId == player1Id) player1Ships else player2Ships

    fun shotHistoryOf(playerId: String): List<ShotRecord> =
        if (playerId == player1Id) player1ShotHistory else player2ShotHistory

    fun commandsUsedBy(playerId: String): Set<String> =
        if (playerId == player1Id) player1CommandsUsed else player2CommandsUsed

    fun commandsDisabledFor(playerId: String): Map<String, Int> =
        if (playerId == player1Id) player1CommandsDisabled else player2CommandsDisabled

    fun traceBerOf(playerId: String): Float =
        if (playerId == player1Id) player1TraceBar else player2TraceBar

    /** Returns true if the session is waiting for this player's ships. */
    fun isReadyOf(playerId: String): Boolean =
        if (playerId == player1Id) player1Ready else player2Ready

    /** True once both players have placed ships and ACTIVE phase can begin. */
    val bothReady: Boolean get() = player1Ready && player2Ready

    /** Advances traceBar for [winnerId] based on ship-sunk ratio. */
    fun withUpdatedTraceBar(sinkingPlayerId: String): CombatSession {
        val opponentShips = shipsOf(opponentOf(sinkingPlayerId))
        val totalShips = opponentShips.size.toFloat().coerceAtLeast(1f)
        val sunkCount  = opponentShips.count { it.isSunk }.toFloat()
        val newBar = sunkCount / totalShips
        return if (sinkingPlayerId == player1Id)
            copy(player1TraceBar = newBar)
        else
            copy(player2TraceBar = newBar)
    }
}

/**
 * A single shot attempt recorded in history.
 *
 * isFake = true when this is a Databomb-planted false marker (it looks like a hit but is fabricated).
 */
data class ShotRecord(
    val coordinate: Coordinate,
    val wasHit: Boolean,
    val wasSunk: Boolean = false,
    val isFake: Boolean = false,
)
