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

class EnhancedMapCommandTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var positionRepository: PositionRepository
    private lateinit var mapCommand: EnhancedMapCommand
    private lateinit var session: GameSession

    private val testPlayerId = "test-player-map"

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:map_cmd_test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

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
        mapCommand = EnhancedMapCommand(discoveryManager, positionRepository)

        // Create test session
        session = GameSession(sessionId = testPlayerId)
    }

    @Test
    fun `map shows message when no nodes discovered`() {
        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        assertTrue(result.output.contains("No nodes discovered yet"))
        assertTrue(result.output.contains("nscan"))
    }

    @Test
    fun `map displays all discovered nodes`() {
        val node1 = createAndSaveNode("Node 1", 1000, 1000)
        val node2 = createAndSaveNode("Node 2", 1100, 1100)
        val node3 = createAndSaveNode("Node 3", 1200, 1200)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        discoveryManager.discoverNode(testPlayerId, node3)

        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        assertTrue(result.output.contains("NETWORK MAP"))
        assertTrue(result.output.contains("Total: 3 nodes"))
    }

    @Test
    fun `map groups nodes by discovery state`() {
        val node1 = createAndSaveNode("Discovered Node", 1000, 1000)
        val node2 = createAndSaveNode("Connected Node", 1100, 1100)
        val node3 = createAndSaveNode("Compromised Node", 1200, 1200)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        discoveryManager.discoverNode(testPlayerId, node3)

        discoveryManager.updateState(testPlayerId, node2.nodeId, DiscoveryState.CONNECTED)
        discoveryManager.updateState(testPlayerId, node3.nodeId, DiscoveryState.COMPROMISED)

        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        assertTrue(result.output.contains("COMPROMISED (1):"))
        assertTrue(result.output.contains("CONNECTED (1):"))
        assertTrue(result.output.contains("DISCOVERED (1):"))
    }

    @Test
    fun `map shows current position when connected`() {
        val node1 = createAndSaveNode("Current Node", 1000, 1000)
        val node2 = createAndSaveNode("Other Node", 1100, 1100)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        positionRepository.updatePosition(testPlayerId, node1.nodeId)

        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        assertTrue(result.output.contains("Current: Current Node"))
        assertTrue(result.output.contains("[YOU]"))
    }

    @Test
    fun `map filters by public nodes`() {
        val publicNode = createAndSaveNode("Public Node", 1000, 1000, isPublic = true)
        val privateNode = createAndSaveNode("Private Node", 1100, 1100, isPublic = false)

        discoveryManager.discoverNode(testPlayerId, publicNode)
        discoveryManager.discoverNode(testPlayerId, privateNode)

        val result = mapCommand.execute(session, listOf("public"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Public Node"))
        assertFalse(result.output.contains("Private Node"))
        assertTrue(result.output.contains("Filter: public"))
    }

    @Test
    fun `map filters by discovery state`() {
        val node1 = createAndSaveNode("Discovered Node", 1000, 1000)
        val node2 = createAndSaveNode("Connected Node", 1100, 1100)

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        discoveryManager.updateState(testPlayerId, node2.nodeId, DiscoveryState.CONNECTED)

        val result = mapCommand.execute(session, listOf("connected"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Connected Node"))
        assertFalse(result.output.contains("Discovered Node"))
    }

    @Test
    fun `map sorts by name`() {
        val nodeZ = createAndSaveNode("Zebra Node", 1000, 1000)
        val nodeA = createAndSaveNode("Alpha Node", 1100, 1100)
        val nodeM = createAndSaveNode("Middle Node", 1200, 1200)

        discoveryManager.discoverNode(testPlayerId, nodeZ)
        discoveryManager.discoverNode(testPlayerId, nodeA)
        discoveryManager.discoverNode(testPlayerId, nodeM)

        val result = mapCommand.execute(session, listOf("", "name"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Sort: name"))

        // Verify Alpha appears before Middle appears before Zebra
        val alphaIndex = result.output.indexOf("Alpha Node")
        val middleIndex = result.output.indexOf("Middle Node")
        val zebraIndex = result.output.indexOf("Zebra Node")

        assertTrue(alphaIndex < middleIndex)
        assertTrue(middleIndex < zebraIndex)
    }

    @Test
    fun `map sorts by distance when position set`() {
        val centerNode = createAndSaveNode("Center Node", 1000, 1000)
        val nearNode = createAndSaveNode("Near Node", 1050, 1050) // ~71m
        val farNode = createAndSaveNode("Far Node", 1200, 1200)   // ~283m

        discoveryManager.discoverNode(testPlayerId, centerNode)
        discoveryManager.discoverNode(testPlayerId, nearNode)
        discoveryManager.discoverNode(testPlayerId, farNode)
        positionRepository.updatePosition(testPlayerId, centerNode.nodeId)

        val result = mapCommand.execute(session, listOf("", "distance"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Sort: distance"))

        // Verify Near appears before Far (excluding Center which shows [YOU])
        val nearIndex = result.output.indexOf("Near Node")
        val farIndex = result.output.indexOf("Far Node")
        assertTrue(nearIndex < farIndex)
    }

    @Test
    fun `map shows signal bars and security indicators`() {
        val node = createAndSaveNode("Test Node", 1000, 1000, signalStrength = 80, securityLevel = 3)

        discoveryManager.discoverNode(testPlayerId, node)

        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        // Should show signal bars (█ characters)
        assertTrue(result.output.contains("█"))
        // Should show security indicator (emoji)
        assertTrue(result.output.contains("🟠"))
    }

    @Test
    fun `map shows usage help at bottom`() {
        val node = createAndSaveNode("Test Node", 1000, 1000)
        discoveryManager.discoverNode(testPlayerId, node)

        val result = mapCommand.execute(session, emptyList())

        assertTrue(result.success)
        assertTrue(result.output.contains("nmap [filter] [sort]"))
        assertTrue(result.output.contains("Filters:"))
        assertTrue(result.output.contains("Sorting:"))
    }

    // Helper function
    private fun createAndSaveNode(
        name: String,
        x: Int,
        y: Int,
        signalStrength: Int = 80,
        securityLevel: Int = 2,
        isPublic: Boolean = true
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
            isMissionCritical = false
        )
        nodeRepository.saveNode(node)
        return node
    }
}
