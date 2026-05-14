package com.codecraft.engine

import com.codecraft.engine.combat.*
import com.codecraft.engine.combat.commands.*
import com.codecraft.engine.models.Coordinate
import com.codecraft.engine.models.Ship
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

/**
 * Unit tests covering:
 *   1. Databomb fake-hit detection
 *   2. Scramble — early termination when scrambled player scores a hit
 *   3. SQL_Injection — false miss on first hit, reveal on second
 *   4. Packet Flood — timer expiry forfeits shot
 *   5. Buffer Overflow — silently disables an opponent command on next hit
 *   6. DDOS FOG — shots into the zone receive "blocked" response
 */
class CombatCommandTest {

    // -------------------------------------------------------------------------
    // Test fixtures
    // -------------------------------------------------------------------------

    private fun makeShip(id: String, squares: List<Coordinate>, size: Int? = null): Ship {
        val coords = if (squares.isEmpty()) (0 until (size ?: 3)).map { Coordinate(0, it) } else squares
        return Ship(shipId = id, squares = coords)
    }

    private fun baseSession(
        gridSize: Int = 10,
        p1Ships: List<Ship>  = listOf(makeShip("s1", listOf(Coordinate(0,0), Coordinate(0,1), Coordinate(0,2)))),
        p2Ships: List<Ship>  = listOf(makeShip("s2", listOf(Coordinate(5,5), Coordinate(5,6), Coordinate(5,7)))),
        effects: List<CombatEffect> = emptyList(),
        p1Used: Set<String>  = emptySet(),
        p2Used: Set<String>  = emptySet(),
        p1ShotHistory: List<ShotRecord> = emptyList(),
        p2ShotHistory: List<ShotRecord> = emptyList(),
    ) = CombatSession(
        combatId        = "test-combat",
        player1Id       = "p1",
        player2Id       = "p2",
        nodeId          = "node-X",
        gridSize        = gridSize,
        player1Ships    = p1Ships,
        player2Ships    = p2Ships,
        player1Ready    = true,
        player2Ready    = true,
        player1ShotHistory = p1ShotHistory,
        player2ShotHistory = p2ShotHistory,
        player1CommandsUsed = p1Used,
        player2CommandsUsed = p2Used,
        activeEffects   = effects,
        phase           = CombatPhase.ACTIVE,
    )

    private fun combatManager(nowMs: Long = System.currentTimeMillis()) =
        CombatManager(clock = { nowMs })

    // -------------------------------------------------------------------------
    // 1. Databomb — fake hit planted on opponent's board
    // -------------------------------------------------------------------------

    @Test
    fun `Databomb adds fake hit to opponent shot history`() {
        val session = baseSession()
        val result = DatabombCommand.execute(session, "p1", mapOf("row" to 3, "col" to 3))

        assertTrue(result.success)
        // A fake record should be in p2's shot history (p2 is the opponent of p1)
        val fakeRecords = result.updatedSession.player2ShotHistory.filter { it.isFake }
        assertEquals(1, fakeRecords.size)
        assertEquals(Coordinate(3, 3), fakeRecords[0].coordinate)
        assertTrue(fakeRecords[0].wasHit, "Fake record should look like a hit")
    }

    @Test
    fun `Databomb does not affect real shot history of the shooter`() {
        val session = baseSession()
        val result = DatabombCommand.execute(session, "p1", mapOf("row" to 3, "col" to 3))

        assertTrue(result.success)
        assertTrue(result.updatedSession.player1ShotHistory.isEmpty(), "Shooter's history is unaffected")
    }

    // -------------------------------------------------------------------------
    // 2. Scramble — early termination when scrambled player scores a hit
    // -------------------------------------------------------------------------

    @Test
    fun `Scramble effect ends early when scrambled player scores a hit`() {
        val now = System.currentTimeMillis()
        val scramble = CombatEffect.Scramble(affectedPlayerId = "p2", shotsRemaining = 3)
        val session = baseSession(effects = listOf(scramble))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p2 fires and HITS p1's ship at (0,0)
        val shotResult = cm.processShot("test-combat", "p2", Coordinate(0, 0))
        assertTrue(shotResult.isSuccess)
        val updated = shotResult.getOrThrow().updatedSession

        // Scramble should be gone — ended early because p2 scored a hit
        val scrambleEffects = updated.activeEffects.filterIsInstance<CombatEffect.Scramble>()
        assertTrue(scrambleEffects.isEmpty(), "Scramble must end early when scrambled player hits")
    }

    @Test
    fun `Scramble decrements counter on miss and persists`() {
        val now = System.currentTimeMillis()
        val scramble = CombatEffect.Scramble(affectedPlayerId = "p2", shotsRemaining = 3)
        val session = baseSession(effects = listOf(scramble))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p2 fires and MISSES (coordinate not in any p1 ship)
        val shotResult = cm.processShot("test-combat", "p2", Coordinate(9, 9))
        assertTrue(shotResult.isSuccess)

        val updated = shotResult.getOrThrow().updatedSession
        val remaining = updated.activeEffects.filterIsInstance<CombatEffect.Scramble>()
        assertEquals(1, remaining.size)
        assertEquals(2, remaining[0].shotsRemaining, "Scramble should decrement on miss")
    }

    // -------------------------------------------------------------------------
    // 3. SQL_Injection — false miss on first hit, reveal on second
    // -------------------------------------------------------------------------

    @Test
    fun `SQL_Injection causes false miss on first hit of affected ship`() {
        val now = System.currentTimeMillis()
        // p2's ship s2 has SQL_Injection; p1 is the one being deceived (affectedPlayerId = p1)
        val sqlEffect = CombatEffect.SqlInjection(affectedPlayerId = "p1", shipId = "s2", used = false)
        val session = baseSession(effects = listOf(sqlEffect))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires at p2's ship s2 — first hit should be a false miss
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 5))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertTrue(result.isFalseMiss, "First hit on SQL_Injection ship should be a false miss")
        assertFalse(result.hit, "False miss means hit=false")

        // Effect should be marked as used
        val usedEffect = result.updatedSession.activeEffects
            .filterIsInstance<CombatEffect.SqlInjection>()
            .firstOrNull { it.shipId == "s2" }
        assertNotNull(usedEffect)
        assertTrue(usedEffect.used, "SQL_Injection effect should be marked used after false miss")
    }

    @Test
    fun `SQL_Injection second hit on same ship processes as real hit`() {
        val now = System.currentTimeMillis()
        // Already used — second hit should be real
        val sqlEffect = CombatEffect.SqlInjection(affectedPlayerId = "p1", shipId = "s2", used = true)
        val session = baseSession(effects = listOf(sqlEffect))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 6))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertFalse(result.isFalseMiss, "Second hit should not be a false miss")
        assertTrue(result.hit, "Second hit should register as a real hit")
    }

    // -------------------------------------------------------------------------
    // 4. Packet Flood — timer expiry forfeits shot
    // -------------------------------------------------------------------------

    @Test
    fun `Packet Flood forfeits shot when deadline is exceeded`() {
        val now = 1_000_000L
        val expiredFlood = CombatEffect.PacketFlood(
            affectedPlayerId = "p1",
            shotsRemaining   = 3,
            deadlineAt       = now - 1L,   // deadline already passed
            timerMs          = 5_000L,
        )
        val session = baseSession(effects = listOf(expiredFlood))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 tries to shoot but the timer expired
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 5))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertFalse(result.hit, "Expired PacketFlood shot should be forfeited (miss)")
        assertFalse(result.isFalseMiss, "Forfeited shot is not a false miss — just forfeited")

        // Flood counter should decrement
        val remaining = result.updatedSession.activeEffects
            .filterIsInstance<CombatEffect.PacketFlood>()
            .firstOrNull()
        assertNotNull(remaining)
        assertEquals(2, remaining.shotsRemaining)
    }

    @Test
    fun `Packet Flood allows shot when within deadline`() {
        val now = 1_000_000L
        val activeFlood = CombatEffect.PacketFlood(
            affectedPlayerId = "p1",
            shotsRemaining   = 3,
            deadlineAt       = now + 5_000L,  // still within window
            timerMs          = 5_000L,
        )
        val session = baseSession(effects = listOf(activeFlood))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires at p2's ship within time — should process normally
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 5))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertTrue(result.hit, "Shot within deadline should register as a hit")
    }

    // -------------------------------------------------------------------------
    // 5. Buffer Overflow — disables an opponent command on next hit
    // -------------------------------------------------------------------------

    @Test
    fun `Buffer Overflow disables random opponent command on hit`() {
        val now = System.currentTimeMillis()
        val bofEffect = CombatEffect.BufferOverflow(ownerPlayerId = "p1")
        val session = baseSession(effects = listOf(bofEffect))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires and hits p2's ship at (5,5)
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 5))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertTrue(result.hit)

        // Buffer Overflow effect should be consumed
        val bofRemaining = result.updatedSession.activeEffects
            .filterIsInstance<CombatEffect.BufferOverflow>()
            .filter { it.ownerPlayerId == "p1" }
        assertTrue(bofRemaining.isEmpty(), "BufferOverflow effect consumed after triggering")

        // p2 should have at least one disabled command
        assertTrue(
            result.updatedSession.player2CommandsDisabled.isNotEmpty(),
            "Buffer Overflow should have disabled at least one p2 command"
        )
        // The disabled command should have max turns (permanent)
        val disabledTurns = result.updatedSession.player2CommandsDisabled.values.first()
        assertEquals(Int.MAX_VALUE, disabledTurns, "Buffer Overflow disables permanently")
    }

    @Test
    fun `Buffer Overflow does not trigger on miss`() {
        val now = System.currentTimeMillis()
        val bofEffect = CombatEffect.BufferOverflow(ownerPlayerId = "p1")
        val session = baseSession(effects = listOf(bofEffect))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires and misses
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(9, 9))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertFalse(result.hit)

        // Buffer Overflow effect should still be active (not consumed on miss)
        val bofRemaining = result.updatedSession.activeEffects
            .filterIsInstance<CombatEffect.BufferOverflow>()
            .filter { it.ownerPlayerId == "p1" }
        assertEquals(1, bofRemaining.size, "BufferOverflow should persist on miss")
        assertTrue(result.updatedSession.player2CommandsDisabled.isEmpty(), "No commands disabled on miss")
    }

    // -------------------------------------------------------------------------
    // 6. DDOS FOG — shots into the zone are blocked
    // -------------------------------------------------------------------------

    @Test
    fun `DDOS FOG blocks shots within the 2x2 zone`() {
        val now = System.currentTimeMillis()
        // Fog zone top-left (2,2) covers (2,2), (2,3), (3,2), (3,3)
        val fog = CombatEffect.DdosFog(affectedPlayerId = "p1", topLeft = Coordinate(2, 2), turnsRemaining = 2)
        val session = baseSession(effects = listOf(fog))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires into the fog zone at (3,3)
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(3, 3))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertTrue(result.isBlocked, "Shot inside DDOS FOG zone should be blocked")
        assertFalse(result.hit, "Blocked shot does not register as a hit")
    }

    @Test
    fun `DDOS FOG does not block shots outside the zone`() {
        val now = System.currentTimeMillis()
        val fog = CombatEffect.DdosFog(affectedPlayerId = "p1", topLeft = Coordinate(2, 2), turnsRemaining = 2)
        val session = baseSession(effects = listOf(fog))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        // p1 fires outside the fog zone, at (5,5) — p2's ship
        val shotResult = cm.processShot("test-combat", "p1", Coordinate(5, 5))
        assertTrue(shotResult.isSuccess)
        val result = shotResult.getOrThrow()
        assertFalse(result.isBlocked, "Shot outside fog zone should not be blocked")
        assertTrue(result.hit, "Shot outside fog zone should register normally")
    }

    @Test
    fun `DDOS FOG turnsRemaining decrements on each blocked shot`() {
        val now = System.currentTimeMillis()
        val fog = CombatEffect.DdosFog(affectedPlayerId = "p1", topLeft = Coordinate(2, 2), turnsRemaining = 2)
        val session = baseSession(effects = listOf(fog))

        val cm = combatManager(now)
        cm.sessions["test-combat"] = session

        cm.processShot("test-combat", "p1", Coordinate(2, 2))  // first blocked shot
        val state1 = cm.sessions["test-combat"]!!
        val fogAfter1 = state1.activeEffects.filterIsInstance<CombatEffect.DdosFog>().firstOrNull()
        assertNotNull(fogAfter1)
        assertEquals(1, fogAfter1.turnsRemaining)

        cm.processShot("test-combat", "p1", Coordinate(2, 3))  // second blocked shot
        val state2 = cm.sessions["test-combat"]!!
        val fogAfter2 = state2.activeEffects.filterIsInstance<CombatEffect.DdosFog>().firstOrNull()
        assertNull(fogAfter2, "Fog should be removed after all turns are consumed")
    }
}
