package com.codecraft.engine.network.discovery

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.persistence.DiscoveryRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.persistence.PlayersTable
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.insert
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.*

class DiscoveryManagerTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryRepository: DiscoveryRepository
    private lateinit var discoveryManager: DiscoveryManager

    private val testPlayerId = "test-player-123"

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:discovery_test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

        transaction(database) {
            SchemaUtils.create(
                PlayersTable,
                NetworkDistrictsTable,
                NetworkNodesTable,
                PlayerDiscoveredNodesTable
            )

            // Create test player
            PlayersTable.insert {
                it[id] = testPlayerId
                it[credits] = 5000
            }
        }

        nodeRepository = NodeRepository(database)
        discoveryRepository = DiscoveryRepository(database)
        discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
    }

    @Test
    fun `discoverNode marks new node as DISCOVERED`() {
        val node = createAndSaveTestNode()

        discoveryManager.discoverNode(testPlayerId, node)

        val state = discoveryManager.getDiscoveryState(testPlayerId, node.nodeId)
        assertEquals(DiscoveryState.DISCOVERED, state)
    }

    @Test
    fun `isDiscovered returns true for discovered nodes`() {
        val node = createAndSaveTestNode()

        assertFalse(discoveryManager.isDiscovered(testPlayerId, node.nodeId))

        discoveryManager.discoverNode(testPlayerId, node)

        assertTrue(discoveryManager.isDiscovered(testPlayerId, node.nodeId))
    }

    @Test
    fun `updateState changes discovery state`() {
        val node = createAndSaveTestNode()

        discoveryManager.discoverNode(testPlayerId, node)
        assertEquals(DiscoveryState.DISCOVERED, discoveryManager.getDiscoveryState(testPlayerId, node.nodeId))

        discoveryManager.updateState(testPlayerId, node.nodeId, DiscoveryState.CONNECTED)
        assertEquals(DiscoveryState.CONNECTED, discoveryManager.getDiscoveryState(testPlayerId, node.nodeId))
    }

    @Test
    fun `getDiscoveredNodes returns all discovered nodes with states`() {
        val node1 = createAndSaveTestNode(name = "Node 1")
        val node2 = createAndSaveTestNode(name = "Node 2")
        val node3 = createAndSaveTestNode(name = "Node 3")

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        discoveryManager.updateState(testPlayerId, node2.nodeId, DiscoveryState.CONNECTED)

        val discovered = discoveryManager.getDiscoveredNodes(testPlayerId)

        assertEquals(2, discovered.size)
        assertTrue(discovered.any { it.first.nodeId == node1.nodeId && it.second == DiscoveryState.DISCOVERED })
        assertTrue(discovered.any { it.first.nodeId == node2.nodeId && it.second == DiscoveryState.CONNECTED })
    }

    @Test
    fun `getNodesByState filters by discovery state`() {
        val node1 = createAndSaveTestNode(name = "Node 1")
        val node2 = createAndSaveTestNode(name = "Node 2")
        val node3 = createAndSaveTestNode(name = "Node 3")

        discoveryManager.discoverNode(testPlayerId, node1)
        discoveryManager.discoverNode(testPlayerId, node2)
        discoveryManager.discoverNode(testPlayerId, node3)

        discoveryManager.updateState(testPlayerId, node2.nodeId, DiscoveryState.CONNECTED)
        discoveryManager.updateState(testPlayerId, node3.nodeId, DiscoveryState.CONNECTED)

        val discoveredNodes = discoveryManager.getNodesByState(testPlayerId, DiscoveryState.DISCOVERED)
        val connectedNodes = discoveryManager.getNodesByState(testPlayerId, DiscoveryState.CONNECTED)

        assertEquals(1, discoveredNodes.size)
        assertEquals(2, connectedNodes.size)
        assertEquals(node1.nodeId, discoveredNodes.first().nodeId)
    }

    @Test
    fun `getDiscoveryStats returns correct counts`() {
        val nodes = List(5) { createAndSaveTestNode(name = "Node $it") }

        // Discover all 5
        nodes.forEach { discoveryManager.discoverNode(testPlayerId, it) }

        // Change states: 2 discovered, 2 connected, 1 compromised
        discoveryManager.updateState(testPlayerId, nodes[1].nodeId, DiscoveryState.CONNECTED)
        discoveryManager.updateState(testPlayerId, nodes[2].nodeId, DiscoveryState.CONNECTED)
        discoveryManager.updateState(testPlayerId, nodes[3].nodeId, DiscoveryState.COMPROMISED)

        val stats = discoveryManager.getDiscoveryStats(testPlayerId)

        assertEquals(5L, stats.total)
        assertEquals(2L, stats.discovered)
        assertEquals(2L, stats.connected)
        assertEquals(1L, stats.compromised)
        assertEquals(0L, stats.locked)
    }

    @Test
    fun `discoverNode does not change existing state`() {
        val node = createAndSaveTestNode()

        discoveryManager.discoverNode(testPlayerId, node)
        discoveryManager.updateState(testPlayerId, node.nodeId, DiscoveryState.CONNECTED)

        // Try to discover again
        discoveryManager.discoverNode(testPlayerId, node)

        // Should still be CONNECTED, not reset to DISCOVERED
        assertEquals(DiscoveryState.CONNECTED, discoveryManager.getDiscoveryState(testPlayerId, node.nodeId))
    }

    @Test
    fun `clearDiscoveries removes all discoveries`() {
        val nodes = List(3) { createAndSaveTestNode(name = "Node $it") }
        nodes.forEach { discoveryManager.discoverNode(testPlayerId, it) }

        assertEquals(3, discoveryManager.getDiscoveredNodes(testPlayerId).size)

        discoveryManager.clearDiscoveries(testPlayerId)

        assertEquals(0, discoveryManager.getDiscoveredNodes(testPlayerId).size)
    }

    // Helper function
    private fun createAndSaveTestNode(
        name: String = "Test Node ${UUID.randomUUID()}"
    ): NetworkNode {
        val node = NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = NodeType.CAFE,
            district = null,
            coordX = 1000,
            coordY = 1000,
            ipAddress = "10.42.1.${(1..254).random()}",
            signalStrength = 80,
            securityLevel = 2,
            isPublic = true,
            isMissionCritical = false
        )
        nodeRepository.saveNode(node)
        return node
    }
}
