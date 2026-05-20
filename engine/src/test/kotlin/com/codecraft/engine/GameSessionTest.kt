package com.codecraft.engine

import com.codecraft.engine.domain.DamageState
import com.codecraft.engine.domain.GamePhase
import com.codecraft.engine.domain.MachineState
import com.codecraft.engine.session.GameSession
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class GameSessionTest {

    // =============================================
    // Connection / Disconnection
    // =============================================

    @Test
    fun `connectTo sets connected node and path`() {
        val session = GameSession("test-connect")
        assertTrue(session.connectTo("public-gateway"))
        assertNotNull(session.connectedNode)
        assertEquals("public-gateway", session.connectedNode!!.id)
    }

    @Test
    fun `connectTo returns false for unknown node`() {
        val session = GameSession("test-bad-connect")
        assertFalse(session.connectTo("nonexistent-node"))
        assertNull(session.connectedNode)
    }

    @Test
    fun `connectTo records connection trace`() {
        val session = GameSession("test-trace")
        session.connectTo("public-gateway")

        assertEquals(1, session.connectionHistory.size)
        assertEquals("public-gateway", session.connectionHistory[0].nodeId)
        assertNull(session.connectionHistory[0].disconnectedAt)
    }

    @Test
    fun `disconnect clears connected node`() {
        val session = GameSession("test-disconnect")
        session.connectTo("public-gateway")
        session.disconnect()

        assertNull(session.connectedNode)
        assertEquals("/home/user", session.currentPath)
    }

    @Test
    fun `disconnect updates trace with disconnection time`() {
        val session = GameSession("test-disconnect-trace")
        session.connectTo("public-gateway")
        session.disconnect()

        assertNotNull(session.connectionHistory[0].disconnectedAt)
    }

    @Test
    fun `getActiveTraces returns only active connections`() {
        val session = GameSession("test-active-traces")
        session.connectTo("public-gateway")
        session.disconnect()
        session.connectTo("public-gateway")

        val active = session.getActiveTraces()
        assertEquals(1, active.size)
    }

    @Test
    fun `connectTo adds sentinel event`() {
        val session = GameSession("test-connect-event")
        session.connectTo("public-gateway")

        assertTrue(session.sentinelEvents.any { it.type == "connection" })
    }

    @Test
    fun `disconnect adds sentinel event`() {
        val session = GameSession("test-disconnect-event")
        session.connectTo("public-gateway")
        session.disconnect()

        assertTrue(session.sentinelEvents.any { it.type == "disconnection" })
    }

    // =============================================
    // Sentinel events capped at 50
    // =============================================

    @Test
    fun `sentinel events capped at 50`() {
        val session = GameSession("test-events-cap")
        repeat(60) { i ->
            session.addSentinelEvent("test", "Event $i")
        }
        assertEquals(50, session.sentinelEvents.size)
    }

    // =============================================
    // Network topology
    // =============================================

    @Test
    fun `network has localhost node`() {
        val session = GameSession("test-network")
        val localhost = session.network.getNode("localhost")
        assertNotNull(localhost)
        assertEquals("127.0.0.1", localhost.ip)
    }

    @Test
    fun `network has nova-corp-web node`() {
        val session = GameSession("test-nova")
        val node = session.network.getNode("nova-corp-web")
        assertNotNull(node)
        assertEquals("192.168.50.10", node.ip)
        assertEquals("NovaCorp", node.organization)
    }

    @Test
    fun `network has nova-corp-mail node`() {
        val session = GameSession("test-mail")
        val node = session.network.getNode("nova-corp-mail")
        assertNotNull(node)
        assertEquals("192.168.50.30", node.ip)
    }

    @Test
    fun `network has nova-corp-sec node`() {
        val session = GameSession("test-sec")
        val node = session.network.getNode("nova-corp-sec")
        assertNotNull(node)
        assertEquals("192.168.50.40", node.ip)
    }

    @Test
    fun `network getNodeByIp finds nodes`() {
        val session = GameSession("test-ip")
        val node = session.network.getNodeByIp("192.168.50.10")
        assertNotNull(node)
        assertEquals("nova-corp-web", node.id)
    }

    @Test
    fun `getCurrentNode returns localhost when disconnected`() {
        val session = GameSession("test-current")
        val node = session.getCurrentNode()
        assertEquals("localhost", node.id)
    }

    @Test
    fun `getCurrentNode returns connected node`() {
        val session = GameSession("test-current-connected")
        session.connectTo("public-gateway")
        val node = session.getCurrentNode()
        assertEquals("public-gateway", node.id)
    }

    // =============================================
    // Machine & Damage State
    // =============================================

    @Test
    fun `machine starts pristine`() {
        val machine = MachineState()
        assertEquals(DamageState.PRISTINE, machine.damageState)
    }

    @Test
    fun `damage firewall reduces current`() {
        val machine = MachineState(firewallCurrent = 100, firewallMax = 100)
        machine.damageFirewall(30)
        assertEquals(70, machine.firewallCurrent)
        assertEquals(DamageState.DAMAGED, machine.damageState)
    }

    @Test
    fun `damage firewall floors at zero`() {
        val machine = MachineState(firewallCurrent = 10, firewallMax = 100)
        machine.damageFirewall(50)
        assertEquals(0, machine.firewallCurrent)
        assertEquals(DamageState.DESTROYED, machine.damageState)
    }

    @Test
    fun `repair firewall increases current`() {
        val machine = MachineState(firewallCurrent = 50, firewallMax = 100)
        machine.repairFirewall(30)
        assertEquals(80, machine.firewallCurrent)
    }

    @Test
    fun `repair firewall caps at max`() {
        val machine = MachineState(firewallCurrent = 90, firewallMax = 100)
        machine.repairFirewall(50)
        assertEquals(100, machine.firewallCurrent)
    }

    @Test
    fun `damage state transitions match thresholds`() {
        assertEquals(DamageState.PRISTINE, DamageState.fromFirewall(100, 100))
        assertEquals(DamageState.GOOD, DamageState.fromFirewall(75, 100))
        assertEquals(DamageState.DAMAGED, DamageState.fromFirewall(50, 100))
        assertEquals(DamageState.HEAVILY_DAMAGED, DamageState.fromFirewall(25, 100))
        assertEquals(DamageState.CRITICAL, DamageState.fromFirewall(10, 100))
        assertEquals(DamageState.DESTROYED, DamageState.fromFirewall(0, 100))
    }

    // =============================================
    // GamePhase transitions
    // =============================================

    @Test
    fun `valid game phase transitions`() {
        assertTrue(GamePhase.canTransition(GamePhase.HOME_NODE, GamePhase.MISSION_PREP))
        assertTrue(GamePhase.canTransition(GamePhase.HOME_NODE, GamePhase.DEFRAG_ACTIVE))
        assertTrue(GamePhase.canTransition(GamePhase.MISSION_ACTIVE, GamePhase.COMBAT))
        assertTrue(GamePhase.canTransition(GamePhase.COMBAT, GamePhase.DEFRAG_ACTIVE))
        assertTrue(GamePhase.canTransition(GamePhase.DEFRAG_ACTIVE, GamePhase.HOME_NODE))
    }

    @Test
    fun `invalid game phase transitions`() {
        assertFalse(GamePhase.canTransition(GamePhase.HOME_NODE, GamePhase.COMBAT))
        assertFalse(GamePhase.canTransition(GamePhase.MISSION_SUCCESS, GamePhase.COMBAT))
        assertFalse(GamePhase.canTransition(GamePhase.MISSION_FAILED, GamePhase.MISSION_ACTIVE))
    }

    // =============================================
    // Snapshot
    // =============================================

    @Test
    fun `snapshot contains all required fields`() {
        val session = GameSession("test-snapshot")
        session.player.credits = 7000
        session.player.exposure = 30.0
        session.player.currentMachine.firewallCurrent = 80

        val snapshot = session.toSnapshot()

        assertEquals("test-snapshot", snapshot.playerId)
        assertEquals(7000, snapshot.credits)
        assertEquals(30.0, snapshot.exposure)
        assertEquals(80, snapshot.firewallCurrent)
        assertEquals(100, snapshot.firewallMax)
        assertEquals("GOOD", snapshot.damageState) // 80/100 = 0.8, >= 0.75 → GOOD
        assertFalse(snapshot.shieldActive)
    }

    // =============================================
    // Player state
    // =============================================

    @Test
    fun `player starts with default commands`() {
        val session = GameSession("test-commands")
        assertTrue(session.player.hasCommand("help"))
        assertTrue(session.player.hasCommand("ls"))
        // scan, connect, disconnect are locked until mission_1
        assertFalse(session.player.hasCommand("scan"))
        assertFalse(session.player.hasCommand("connect"))
        assertFalse(session.player.hasCommand("disconnect"))
        // but other commands are available
        assertTrue(session.player.hasCommand("solve"))
        assertTrue(session.player.hasCommand("open"))
        assertTrue(session.player.hasCommand("download"))
    }

    @Test
    fun `player spendCredits tracks totalCreditsSpent`() {
        val session = GameSession("test-spend")
        session.player.spendCredits(1000)
        assertEquals(4000, session.player.credits)
        assertEquals(1000, session.player.totalCreditsSpent)
    }

    @Test
    fun `player spendCredits fails with insufficient funds`() {
        val session = GameSession("test-spend-fail")
        assertFalse(session.player.spendCredits(10000))
        assertEquals(5000, session.player.credits) // unchanged
    }

    @Test
    fun `exposure clamped between 0 and 100`() {
        val session = GameSession("test-exposure-clamp")
        session.player.increaseExposure(150.0)
        assertEquals(100.0, session.player.exposure)

        session.player.decreaseExposure(200.0)
        assertEquals(0.0, session.player.exposure)
    }
}
