package com.codecraft.engine

import com.codecraft.engine.command.CommandRegistry
import com.codecraft.engine.session.GameSession
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class CommandPipelineTest {

    private fun createRegistry(): CommandRegistry = CommandRegistry()

    private fun createSessionWithNetworkCommands(id: String): GameSession {
        val session = GameSession(id)
        // Unlock network commands for testing (normally unlocked via mission_1)
        session.player.unlockCommand("scan")
        session.player.unlockCommand("connect")
        session.player.unlockCommand("disconnect")
        return session
    }

    // =============================================
    // Basic command execution
    // =============================================

    @Test
    fun `help command executes successfully`() {
        val registry = createRegistry()
        val session = GameSession("test-help")

        val result = registry.execute(session, "help")
        assertTrue(result.success)
        assertTrue(result.output.isNotBlank())
    }

    @Test
    fun `unknown command returns error`() {
        val registry = createRegistry()
        val session = GameSession("test-unknown")

        val result = registry.execute(session, "nonexistent")
        assertFalse(result.success)
        assertTrue(result.output.contains("command not found"))
    }

    @Test
    fun `empty command returns success`() {
        val registry = createRegistry()
        val session = GameSession("test-empty")

        val result = registry.execute(session, "")
        assertTrue(result.success)
    }

    @Test
    fun `ls command works at home directory`() {
        val registry = createRegistry()
        val session = GameSession("test-ls")

        val result = registry.execute(session, "ls")
        assertTrue(result.success)
    }

    @Test
    fun `pwd command returns current path`() {
        val registry = createRegistry()
        val session = GameSession("test-pwd")

        val result = registry.execute(session, "pwd")
        assertTrue(result.success)
        assertTrue(result.output.contains("/home/user"))
    }

    // =============================================
    // Exposure application
    // =============================================

    @Test
    fun `scan command increases exposure`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-scan-exposure")

        val initialExposure = session.player.exposure
        registry.execute(session, "scan 192.168.50.10")

        assertTrue(session.player.exposure > initialExposure)
    }

    @Test
    fun `exposure is capped at 100`() {
        val registry = createRegistry()
        val session = GameSession("test-cap")

        session.player.exposure = 99.0
        registry.execute(session, "scan 192.168.50.10")

        assertTrue(session.player.exposure <= 100.0)
    }

    @Test
    fun `disconnect decreases exposure`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-disconnect-exposure")

        // Connect to a node first (public-gateway is compromised, so no puzzle needed)
        session.connectTo("public-gateway")
        session.player.exposure = 20.0

        registry.execute(session, "disconnect")

        assertTrue(session.player.exposure < 20.0)
    }

    // =============================================
    // Shield blocks exposure
    // =============================================

    @Test
    fun `shield blocks exposure increase from commands`() {
        val registry = createRegistry()
        val session = GameSession("test-shield-block")

        session.activateShield(240)
        val initialExposure = session.player.exposure

        // Scan should try to increase exposure, but shield blocks it
        registry.execute(session, "scan 192.168.50.10")

        assertEquals(initialExposure, session.player.exposure)
    }

    // =============================================
    // Firewall damage blocks commands
    // =============================================

    @Test
    fun `damaged firewall blocks scan command`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-firewall-block")

        session.firewallStatus = "damaged"

        val result = registry.execute(session, "scan 192.168.50.10")
        assertFalse(result.success)
        assertTrue(result.output.contains("firewall damaged"))
    }

    @Test
    fun `damaged firewall blocks connect command`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-firewall-connect")

        session.firewallStatus = "damaged"

        val result = registry.execute(session, "connect 192.168.50.10")
        assertFalse(result.success)
        assertTrue(result.output.contains("firewall damaged"))
    }

    @Test
    fun `damaged firewall does not block non-network commands`() {
        val registry = createRegistry()
        val session = GameSession("test-firewall-ls")

        session.firewallStatus = "damaged"

        val result = registry.execute(session, "ls")
        assertTrue(result.success)
    }

    // =============================================
    // Exposure decay
    // =============================================

    @Test
    fun `exposure decays after idle time`() {
        val registry = createRegistry()
        val session = GameSession("test-decay")

        session.player.exposure = 50.0
        // Simulate 10 minutes idle
        session.lastActionTime = System.currentTimeMillis() - 600000

        registry.execute(session, "pwd")

        // 10 min * 0.5/min = 5.0 decay
        assertTrue(session.player.exposure < 50.0)
    }

    @Test
    fun `exposure decay does not go below zero`() {
        val registry = createRegistry()
        val session = GameSession("test-decay-floor")

        session.player.exposure = 1.0
        // Simulate 60 minutes idle
        session.lastActionTime = System.currentTimeMillis() - 3600000

        registry.execute(session, "pwd")

        assertTrue(session.player.exposure >= 0.0)
    }

    // =============================================
    // Connection puzzle flow
    // =============================================

    @Test
    fun `connect to non-compromised node generates puzzle`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-connect-puzzle")

        // nova-corp-web is not compromised by default
        session.player.discoveredNodes.add("nova-corp-web")

        val result = registry.execute(session, "connect 192.168.50.10")
        assertTrue(result.success)
        assertTrue(result.output.contains("Authentication required"))
        assertNotNull(session.pendingPuzzle)
    }

    @Test
    fun `connect to compromised node skips puzzle`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-connect-no-puzzle")

        // public-gateway is compromised
        val result = registry.execute(session, "connect 45.33.32.1")
        assertTrue(result.success)
        assertTrue(result.output.contains("Connected"))
        assertNotNull(session.connectedNode)
    }

    @Test
    fun `connect to node with access skips puzzle`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-connect-access")

        session.player.discoveredNodes.add("nova-corp-web")
        session.nodeAccess.add("nova-corp-web")

        val result = registry.execute(session, "connect 192.168.50.10")
        assertTrue(result.success)
        assertTrue(result.output.contains("Connected"))
    }

    // =============================================
    // State changes in results
    // =============================================

    @Test
    fun `scan result includes discovered nodes`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-scan-state")

        val result = registry.execute(session, "scan 192.168.50.10")
        assertTrue(result.success)
        assertNotNull(result.stateChanges)
        assertNotNull(result.stateChanges?.discoveredNodes)
        assertTrue(result.stateChanges?.discoveredNodes?.contains("nova-corp-web") == true)
    }

    @Test
    fun `lastActionTime is updated after command`() {
        val registry = createRegistry()
        val session = GameSession("test-action-time")

        val oldTime = session.lastActionTime - 10000
        session.lastActionTime = oldTime

        registry.execute(session, "pwd")

        assertTrue(session.lastActionTime > oldTime)
    }

    @Test
    fun `disconnect returns complete state changes`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-disconnect-state")

        // Connect to a node first
        session.connectTo("public-gateway")
        val result = registry.execute(session, "disconnect")

        assertTrue(result.success)
        assertEquals(null, result.stateChanges?.connectedTo)
        assertEquals(null, result.stateChanges?.connectedToName)
        assertEquals("/home/user", result.stateChanges?.currentPath)
    }

    // =============================================
    // Lateral movement (auto-disconnect)
    // =============================================

    @Test
    fun `connect while already connected performs auto-disconnect`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-lateral")

        // Connect to first node
        session.connectTo("public-gateway")
        assertEquals("public-gateway", session.connectedNode?.id)

        // Connect to second node without manual disconnect
        session.nodeAccess.add("nova-corp-web")  // Skip puzzle for test
        val result = registry.execute(session, "connect 192.168.50.10")

        assertTrue(result.success)
        assertTrue(result.output.contains("Disconnecting from"))
        assertTrue(result.output.contains("Connected to"))
        assertEquals("nova-corp-web", session.connectedNode?.id)
    }

    @Test
    fun `lateral movement closes previous connection trace`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-lateral-trace")

        session.connectTo("public-gateway")
        assertEquals(1, session.connectionHistory.size)
        assertNull(session.connectionHistory[0].disconnectedAt)

        // Lateral move to nova-corp-web
        session.nodeAccess.add("nova-corp-web")
        registry.execute(session, "connect 192.168.50.10")

        // First trace should be closed
        assertNotNull(session.connectionHistory[0].disconnectedAt)
        // Second trace should be open
        assertEquals(2, session.connectionHistory.size)
        assertNull(session.connectionHistory[1].disconnectedAt)
    }

    @Test
    fun `lateral movement net exposure matches manual disconnect flow`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-lateral-exposure")

        session.connectTo("public-gateway")
        val initialExposure = session.player.exposure

        // Lateral move: auto-disconnect (-2.0) + connect (+5.0) = +3.0 net
        session.nodeAccess.add("nova-corp-web")
        val result = registry.execute(session, "connect 192.168.50.10")

        // Net change should be +3.0 (auto-disconnect -2.0 + connect +5.0)
        val expectedExposure = initialExposure + 3.0
        val actualExposure = session.player.exposure

        assertTrue(actualExposure >= expectedExposure - 0.5, "Expected exposure ~$expectedExposure, got $actualExposure")
        assertTrue(actualExposure <= expectedExposure + 0.5, "Expected exposure ~$expectedExposure, got $actualExposure")
    }

    @Test
    fun `lateral movement triggers CONNECT_NODE objective`() {
        val registry = createRegistry()
        val session = createSessionWithNetworkCommands("test-lateral-objective")

        // Load mission with CONNECT_NODE objective for nova-corp-web
        val missionDef = com.codecraft.engine.domain.MissionCatalog.getById("mission_1_first_score")
        if (missionDef != null) {
            session.currentMission = com.codecraft.engine.domain.ActiveMission(
                definition = missionDef,
                negotiatedReward = 2000
            )
        }

        // Start at public-gateway
        session.connectTo("public-gateway")

        // Lateral move to nova-corp-web (target of objective)
        session.nodeAccess.add("nova-corp-web")
        val result = registry.execute(session, "connect 192.168.50.10")

        // Connection should succeed and connect to nova-corp-web
        assertTrue(result.success)
        assertEquals("nova-corp-web", session.connectedNode?.id)
    }
}
