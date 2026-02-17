package com.codecraft.engine.command.commands

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.persistence.DiscoveryRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.persistence.PlayersTable
import com.codecraft.engine.session.GameSession
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.insert
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertTrue
import kotlin.test.assertFalse
import kotlin.test.assertEquals
import kotlin.test.assertNull

class EnhancedConnectCommandTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var positionRepository: PositionRepository
    private lateinit var connectCommand: EnhancedConnectCommand
    private lateinit var session: GameSession

    private val testPlayerId = "test-player-connect"

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:connect_cmd_test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

        transaction(database) {
            SchemaUtils.create(
                PlayersTable,
                NetworkDistrictsTable,
                NetworkNodesTable,
                PlayerDiscoveredNodesTable,
                PlayerNetworkPositionTable
            )

            // Create test player
            PlayersTable.insert {
                it[id] = testPlayerId
                it[credits] = 5000
            }
        }

        nodeRepository = NodeRepository(database)
        val discoveryRepository = DiscoveryRepository(database)
        discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
        positionRepository = PositionRepository(database)
        connectCommand = EnhancedConnectCommand(nodeRepository, discoveryManager, positionRepository)

        // Create test session
        session = GameSession(sessionId = testPlayerId)
    }

    @Test
    fun `connect requires argument`() {
        val result = connectCommand.execute(session, emptyList())

        assertFalse(result.success)
        assertTrue(result.output.contains("Usage:"))
    }

    @Test
    fun `connect requires discovered nodes`() {
        val result = connectCommand.execute(session, listOf("some-node"))

        assertFalse(result.success)
        assertTrue(result.output.contains("No nodes discovered yet"))
        assertTrue(result.output.contains("nscan"))
    }

    @Test
    fun `connect fails for undiscovered node`() {
        val node1 = createAndSaveNode("Discovered Node", 1000, 1000)
        val node2 = createAndSaveNode("Undiscovered Node", 1100, 1100)

        discoveryManager.discoverNode(testPlayerId, node1)
        // node2 is NOT discovered

        val result = connectCommand.execute(session, listOf("Undiscovered Node"))

        assertFalse(result.success)
        assertTrue(result.output.contains("not found"))
    }

    @Test
    fun `connect succeeds to discovered node`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf("Test Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("CONNECTION ESTABLISHED"))
        assertTrue(result.output.contains("Test Node"))
    }

    @Test
    fun `connect updates position in repository`() {
        val node = createAndSaveNode("Target Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        // Initially no position
        assertNull(positionRepository.getCurrentPosition(testPlayerId))

        connectCommand.execute(session, listOf("Target Node"))

        // Position should now be set
        val currentPos = positionRepository.getCurrentPosition(testPlayerId)
        assertEquals(node.nodeId, currentPos)
    }

    @Test
    fun `connect updates discovery state to CONNECTED`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        // Initially DISCOVERED
        assertEquals(DiscoveryState.DISCOVERED, discoveryManager.getDiscoveryState(testPlayerId, node.nodeId))

        connectCommand.execute(session, listOf("Test Node"))

        // Should now be CONNECTED
        assertEquals(DiscoveryState.CONNECTED, discoveryManager.getDiscoveryState(testPlayerId, node.nodeId))
    }

    @Test
    fun `connect tracks previous position`() {
        val node1 = createAndSaveNode("First Node", 1000, 1000)
        val node2 = createAndSaveNode("Second Node", 1100, 1100)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)

        // Connect to first node
        connectCommand.execute(session, listOf("First Node"))

        // Connect to second node
        connectCommand.execute(session, listOf("Second Node"))

        // Previous should be first node
        val previous = positionRepository.getPreviousPosition(testPlayerId)
        assertEquals(node1.nodeId, previous)
    }

    @Test
    fun `connect shows disconnect message when moving`() {
        val node1 = createAndSaveNode("First Node", 1000, 1000)
        val node2 = createAndSaveNode("Second Node", 1100, 1100)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)

        // Connect to first
        connectCommand.execute(session, listOf("First Node"))

        // Connect to second
        val result = connectCommand.execute(session, listOf("Second Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Disconnecting from First Node"))
        assertTrue(result.output.contains("Connecting to Second Node"))
        assertTrue(result.output.contains("Previous: First Node"))
    }

    @Test
    fun `connect adds exposure for lateral movement`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf("Test Node"))

        assertEquals(3.0, result.exposureChange)
    }

    @Test
    fun `connect can find node by partial name match`() {
        val node = createAndSaveNode("Blue Neon Cafe WiFi", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf("Blue Neon"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Blue Neon Cafe WiFi"))
    }

    @Test
    fun `connect can find node by IP address`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf(node.ipAddress))

        assertTrue(result.success)
        assertTrue(result.output.contains("Test Node"))
    }

    @Test
    fun `connect fails for locked nodes`() {
        val node = createAndSaveNode("Locked Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)
        discoveryManager.updateState(testPlayerId, node.nodeId, DiscoveryState.LOCKED)

        val result = connectCommand.execute(session, listOf("Locked Node"))

        assertFalse(result.success)
        assertTrue(result.output.contains("LOCKED"))
        assertTrue(result.output.contains("Access has been denied"))
    }

    @Test
    fun `connect shows already connected message`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        // Connect first time
        connectCommand.execute(session, listOf("Test Node"))

        // Try to connect again
        val result = connectCommand.execute(session, listOf("Test Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Already connected"))
    }

    @Test
    fun `connect shows node details`() {
        val node = createAndSaveNode("Test Node", 1000, 1000, signalStrength = 80, securityLevel = 3)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf("Test Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("IP Address: ${node.ipAddress}"))
        assertTrue(result.output.contains("Signal Strength:"))
        assertTrue(result.output.contains("Security Level:"))
        assertTrue(result.output.contains("Position: (1000, 1000)"))
        assertTrue(result.output.contains("█")) // Signal bar
    }

    @Test
    fun `connect shows mission critical indicator`() {
        val node = createAndSaveNode("Mission Node", 1000, 1000, isMissionCritical = true)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = connectCommand.execute(session, listOf("Mission Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("MISSION CRITICAL"))
    }

    // Helper function
    private fun createAndSaveNode(
        name: String,
        x: Int,
        y: Int,
        signalStrength: Int = 80,
        securityLevel: Int = 2,
        isPublic: Boolean = true,
        isMissionCritical: Boolean = false
    ): NetworkNode {
        val node = NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = NodeType.CAFE,
            district = null,
            coordX = x,
            coordY = y,
            ipAddress = "10.42.1.${(1..254).random()}",
            signalStrength = signalStrength,
            securityLevel = securityLevel,
            isPublic = isPublic,
            isMissionCritical = isMissionCritical
        )
        nodeRepository.saveNode(node)
        return node
    }
}
