package com.codecraft.engine.combat

import com.codecraft.engine.models.Coordinate

/**
 * Transient effects active during a combat session.
 * CombatSession.activeEffects holds all currently live effects.
 *
 * affectedPlayerId  = the player who is SUFFERING the effect
 * ownerPlayerId     = the player who activated the effect
 */
sealed class CombatEffect {

    /** Scramble: wipes affectedPlayer's shot-history view for up to [shotsRemaining] shots.
     *  Ends early if affectedPlayer scores a hit. */
    data class Scramble(
        val affectedPlayerId: String,
        val shotsRemaining: Int,
    ) : CombatEffect()

    /** PacketFlood: affectedPlayer's next [shotsRemaining] shots each carry a deadline.
     *  [deadlineAt] = epoch-ms by which the SHOOT must arrive, refreshed per shot. */
    data class PacketFlood(
        val affectedPlayerId: String,
        val shotsRemaining: Int,
        val deadlineAt: Long,           // refreshed when each shot window opens
        val timerMs: Long = 5_000L,
    ) : CombatEffect()

    /** DdosFog: top-left of 2×2 blocked zone. Shots into zone receive "Signal Blocked". */
    data class DdosFog(
        val affectedPlayerId: String,
        val topLeft: Coordinate,
        val turnsRemaining: Int,
    ) : CombatEffect()

    /** SqlInjection: first hit on [shipId] on affectedPlayer's grid reads as a miss.
     *  [used] flips to true after the false miss is consumed. */
    data class SqlInjection(
        val affectedPlayerId: String,
        val shipId: String,
        val used: Boolean = false,
    ) : CombatEffect()

    /** Trojan: planted inside ownerPlayer's ship. Triggers a negative effect when that ship is sunk. */
    data class Trojan(
        val ownerPlayerId: String,
        val shipId: String,
    ) : CombatEffect()

    /** Blackout: incoming offensive commands against affectedPlayer have no effect for [turnsRemaining].
     *  Opponent's commands fire and go on cooldown but are silently nullified. */
    data class Blackout(
        val affectedPlayerId: String,   // this player is PROTECTED
        val turnsRemaining: Int,
    ) : CombatEffect()

    /** RootKit: ownerPlayer steals 1 unused command from opponent if they sink a ship. */
    data class RootKit(val ownerPlayerId: String) : CombatEffect()

    /** Exploit: ownerPlayer gets one free retaliatory shot the next time their ship is hit. */
    data class Exploit(val ownerPlayerId: String) : CombatEffect()

    /** Overload: affectedPlayer's very next shot is displaced to a random adjacent square. */
    data class Overload(val affectedPlayerId: String) : CombatEffect()

    /** BufferOverflow: the next hit scored by ownerPlayer will silently disable one of
     *  the opponent's unused commands for the rest of combat. */
    data class BufferOverflow(val ownerPlayerId: String) : CombatEffect()
}
