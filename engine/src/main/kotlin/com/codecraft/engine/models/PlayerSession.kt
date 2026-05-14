package com.codecraft.engine.models

/**
 * In-memory session state for a connected player.
 *
 * This is the engine's source of truth for real-time state only.
 * Persistent state (stat levels, SS, creds) lives in Laravel and is
 * loaded via LaravelApiClient on session registration.
 */
data class PlayerSession(
    val playerId: String,
    val handle: String,
    val currentNodeId: String,
    val isCloaked: Boolean = false,
    val cloakedMovesRemaining: Int = 0,
    val postCombatSilentMoves: Int = 0,
    val isLimping: Boolean = false,
    val cpuCyclesRemaining: Int,
    val effectiveStats: EffectiveStats,
    /** From Laravel — used to scale combat grid size. */
    val bountyLevel: Int = 0,
    /** Engine-only — epoch-ms when the player last moved to currentNodeId.
     *  Used to detect simultaneous arrival (within 200ms → no escape window). */
    val nodeArrivalTime: Long = 0L,
)
