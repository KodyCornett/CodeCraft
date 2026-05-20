package com.codecraft.engine.command.commands

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.domain.Player
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.discovery.ScanService
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

class EnhancedScanCommandTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var positionRepository: PositionRepository
    private lateinit var scanCommand: EnhancedScanCommand
    private lateinit var session: GameSession

    private val testPlayerId = "test-player-scan"

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:scan_cmd_test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

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
        val scanService = ScanService()
        scanCommand = EnhancedScanCommand(nodeRepository, discoveryManager, scanService, positionRepository)

        // Create test session
        session = createTestSession()
    }

    @Test
    fun `scan command requires current node`() {
        // Don't set a position - player not connected to network

        val result = scanCommand.execute(session, emptyList())

        assertFalse(result.success)
        assertTrue(result.output.contains("Not connected"))
    }

    @Test
    fun `scan discovers new nodes in range`() {
        val centerNode = createAndSaveNode("Center Node", 1000, 1000)
        val nearNode = createAndSaveNode("Near Node", 1100, 1100) // ~141m away
        val farNode = createAndSaveNode("Far Node", 2000, 2000) // ~1414m away

        // Set player position to center node
        positionRepository.updatePosition(testPlayerId, centerNode.nodeId)

        // Initially, no discoveries
        assertEquals(0, discoveryManager.getDiscoveredNodes(testPlayerId).size)

        val result = scanCommand.execute(session, listOf("500")) // 500m range

        assertTrue(result.success)
        assertTrue(result.output.contains("SCANNING"))

        // Should have discovered nodes (at least nearNode)
        val discoveries = discoveryManager.getDiscoveredNodes(testPlayerId)
        assertTrue(discoveries.isNotEmpty())
    }

    @Test
    fun `scan adds exposure`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        positionRepository.updatePosition(testPlayerId, node.nodeId)

        val result = scanCommand.execute(session, emptyList())

        assertEquals(2.0, result.exposureChange)
    }

    @Test
    fun `scan shows new discoveries separately from known nodes`() {
        val centerNode = createAndSaveNode("Center Node", 1000, 1000)
        val node1 = createAndSaveNode("Node 1", 1100, 1100)
        val node2 = createAndSaveNode("Node 2", 1050, 1050)

        positionRepository.updatePosition(testPlayerId, centerNode.nodeId)

        // First scan - both should be new
        val result1 = scanCommand.execute(session, listOf("500"))
        assertTrue(result1.output.contains("NEW NODES DISCOVERED"))

        // Second scan - should show as known
        val result2 = scanCommand.execute(session, listOf("500"))
        assertTrue(result2.output.contains("KNOWN NODES IN RANGE") || result2.output.contains("No nodes detected"))
    }

    @Test
    fun `scan shows node details with signal bars`() {
        val centerNode = createAndSaveNode("Center Node", 1000, 1000, signalStrength = 80)
        val nearNode = createAndSaveNode("Near Node", 1050, 1050, signalStrength = 90)

        positionRepository.updatePosition(testPlayerId, centerNode.nodeId)

        val result = scanCommand.execute(session, listOf("500"))

        // Should show signal bars (█ characters)
        assertTrue(result.output.contains("█"))
        // Should show IP addresses
        assertTrue(result.output.contains("10."))
        // Should show distance
        assertTrue(result.output.contains("m"))
    }

    @Test
    fun `scan summary shows counts`() {
        val centerNode = createAndSaveNode("Center Node", 1000, 1000)
        createAndSaveNode("Node 1", 1100, 1100)
        createAndSaveNode("Node 2", 1050, 1050)

        positionRepository.updatePosition(testPlayerId, centerNode.nodeId)

        val result = scanCommand.execute(session, listOf("500"))

        assertTrue(result.output.contains("Summary:"))
        assertTrue(result.output.contains("New discoveries:") || result.output.contains("Known in range:"))
    }

    // Helper functions
    private fun createTestSession(): GameSession {
        return GameSession(sessionId = testPlayerId)
    }

    private fun createAndSaveNode(
        name: String,
        x: Int,
        y: Int,
        signalStrength: Int = 80
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
            securityLevel = 2,
            isPublic = true,
            isMissionCritical = false
        )
        nodeRepository.saveNode(node)
        return node
    }
}
