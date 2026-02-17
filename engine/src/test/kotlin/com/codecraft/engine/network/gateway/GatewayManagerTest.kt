package com.codecraft.engine.network.gateway

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.discovery.DiscoveryManager
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

class GatewayManagerTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var gatewayManager: GatewayManager

    private val testPlayerId = "test-player-gateway"

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:gateway_test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

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
        val discoveryRepository = DiscoveryRepository(database)
        discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
        gatewayManager = GatewayManager(nodeRepository, discoveryManager)
    }

    @Test
    fun `public nodes are gateways`() {
        val publicNode = createAndSaveNode("Public Node", 1000, 1000, isPublic = true)
        val privateNode = createAndSaveNode("Private Node", 1100, 1100, isPublic = false)

        assertTrue(gatewayManager.isGateway(publicNode))
        assertFalse(gatewayManager.isGateway(privateNode))
    }

    @Test
    fun `private nodes do not activate as gateways`() {
        val privateNode = createAndSaveNode("Private Node", 1000, 1000, isPublic = false)

        val result = gatewayManager.activateGateway(testPlayerId, privateNode)

        assertFalse(result.isGateway)
        assertEquals(0, result.autoRevealedCount)
    }

    @Test
    fun `gateway activation reveals nearby public nodes`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyPublic1 = createAndSaveNode("Nearby Public 1", 1100, 1100, isPublic = true, signalStrength = 80)
        val nearbyPublic2 = createAndSaveNode("Nearby Public 2", 1050, 1050, isPublic = true, signalStrength = 75)

        // Initially, nearby nodes are not discovered
        assertFalse(discoveryManager.isDiscovered(testPlayerId, nearbyPublic1.nodeId))
        assertFalse(discoveryManager.isDiscovered(testPlayerId, nearbyPublic2.nodeId))

        // Activate gateway
        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        // Should auto-reveal both nearby public nodes
        assertTrue(result.isGateway)
        assertTrue(result.autoRevealedCount >= 2)
        assertTrue(discoveryManager.isDiscovered(testPlayerId, nearbyPublic1.nodeId))
        assertTrue(discoveryManager.isDiscovered(testPlayerId, nearbyPublic2.nodeId))
    }

    @Test
    fun `gateway does not reveal private nodes`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyPrivate = createAndSaveNode("Nearby Private", 1100, 1100, isPublic = false, signalStrength = 80)

        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        // Private node should NOT be auto-revealed
        assertFalse(discoveryManager.isDiscovered(testPlayerId, nearbyPrivate.nodeId))
    }

    @Test
    fun `gateway does not reveal weak signal public nodes`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val weakSignal = createAndSaveNode("Weak Signal", 1100, 1100, isPublic = true, signalStrength = 50)

        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        // Weak signal node (< 70%) should NOT be auto-revealed
        assertFalse(discoveryManager.isDiscovered(testPlayerId, weakSignal.nodeId))
    }

    @Test
    fun `gateway respects reveal radius`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearNode = createAndSaveNode("Near Node", 1200, 1200, isPublic = true, signalStrength = 80) // ~283m
        val farNode = createAndSaveNode("Far Node", 2000, 2000, isPublic = true, signalStrength = 80) // ~1414m

        // Activate with 300m radius
        val result = gatewayManager.activateGateway(testPlayerId, gateway, revealRadius = 300)

        // Near node should be revealed, far node should not
        assertTrue(discoveryManager.isDiscovered(testPlayerId, nearNode.nodeId))
        assertFalse(discoveryManager.isDiscovered(testPlayerId, farNode.nodeId))
    }

    @Test
    fun `gateway does not reveal already discovered nodes`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyNode = createAndSaveNode("Nearby Node", 1100, 1100, isPublic = true, signalStrength = 80)

        // Manually discover the node first
        discoveryManager.discoverNode(testPlayerId, nearbyNode)

        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        // Should not count as auto-revealed since it was already discovered
        assertEquals(0, result.autoRevealedCount)
    }

    @Test
    fun `gateway activation returns revealed node list`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyPublic1 = createAndSaveNode("Nearby Public 1", 1100, 1100, isPublic = true, signalStrength = 80)
        val nearbyPublic2 = createAndSaveNode("Nearby Public 2", 1050, 1050, isPublic = true, signalStrength = 75)

        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        assertTrue(result.autoRevealedNodes.isNotEmpty())
        assertTrue(result.autoRevealedNodes.any { it.nodeId == nearbyPublic1.nodeId })
        assertTrue(result.autoRevealedNodes.any { it.nodeId == nearbyPublic2.nodeId })
    }

    @Test
    fun `getVisibleNodesFromGateway returns nodes without discovering`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyPublic = createAndSaveNode("Nearby Public", 1100, 1100, isPublic = true, signalStrength = 80)

        // Get visible nodes (preview mode)
        val visible = gatewayManager.getVisibleNodesFromGateway(gateway)

        // Should include nearby public node
        assertTrue(visible.any { it.nodeId == nearbyPublic.nodeId })

        // But should NOT discover it
        assertFalse(discoveryManager.isDiscovered(testPlayerId, nearbyPublic.nodeId))
    }

    @Test
    fun `calculateRevealRadius scales with signal strength`() {
        val strongGateway = createAndSaveNode("Strong Gateway", 1000, 1000, isPublic = true, signalStrength = 100)
        val weakGateway = createAndSaveNode("Weak Gateway", 2000, 2000, isPublic = true, signalStrength = 70)

        val strongRadius = gatewayManager.calculateRevealRadius(strongGateway)
        val weakRadius = gatewayManager.calculateRevealRadius(weakGateway)

        assertTrue(strongRadius > weakRadius, "Stronger gateway should have larger reveal radius")
        assertEquals(500, strongRadius) // 100% signal = 500m
        assertEquals(350, weakRadius) // 70% signal = 350m
    }

    @Test
    fun `calculateRevealRadius returns zero for private nodes`() {
        val privateNode = createAndSaveNode("Private Node", 1000, 1000, isPublic = false, signalStrength = 100)

        val radius = gatewayManager.calculateRevealRadius(privateNode)

        assertEquals(0, radius)
    }

    @Test
    fun `gateway activation result includes total nearby count`() {
        val gateway = createAndSaveNode("Gateway", 1000, 1000, isPublic = true, signalStrength = 90)
        val nearbyPublic = createAndSaveNode("Nearby Public", 1100, 1100, isPublic = true, signalStrength = 80)
        val nearbyPrivate = createAndSaveNode("Nearby Private", 1050, 1050, isPublic = false, signalStrength = 70)

        val result = gatewayManager.activateGateway(testPlayerId, gateway)

        // Total nearby should include both public and private nodes
        assertTrue(result.totalNearbyCount >= 2)
    }

    @Test
    fun `multiple gateway activations discover different nodes`() {
        val gateway1 = createAndSaveNode("Gateway 1", 1000, 1000, isPublic = true, signalStrength = 90)
        val gateway2 = createAndSaveNode("Gateway 2", 2000, 2000, isPublic = true, signalStrength = 90)

        val nearGateway1 = createAndSaveNode("Near Gateway 1", 1100, 1100, isPublic = true, signalStrength = 80)
        val nearGateway2 = createAndSaveNode("Near Gateway 2", 2100, 2100, isPublic = true, signalStrength = 80)

        // Activate first gateway
        gatewayManager.activateGateway(testPlayerId, gateway1)

        // Should discover node near gateway 1 but not gateway 2
        assertTrue(discoveryManager.isDiscovered(testPlayerId, nearGateway1.nodeId))
        assertFalse(discoveryManager.isDiscovered(testPlayerId, nearGateway2.nodeId))

        // Activate second gateway
        gatewayManager.activateGateway(testPlayerId, gateway2)

        // Now should discover node near gateway 2
        assertTrue(discoveryManager.isDiscovered(testPlayerId, nearGateway2.nodeId))
    }

    @Test
    fun `getGatewayStats returns coverage statistics`() {
        val gateway1 = createAndSaveNode("Gateway 1", 1000, 1000, isPublic = true, signalStrength = 90)
        val gateway2 = createAndSaveNode("Gateway 2", 2000, 2000, isPublic = true, signalStrength = 90)

        createAndSaveNode("Near Gateway 1", 1100, 1100, isPublic = true, signalStrength = 80)
        createAndSaveNode("Near Gateway 2", 2100, 2100, isPublic = true, signalStrength = 80)

        val gateways = listOf(gateway1, gateway2)
        val stats = gatewayManager.getGatewayStats(testPlayerId, gateways)

        assertEquals(2, stats.gatewayCount)
        assertTrue(stats.totalCoverage >= 2)
    }

    // Helper function
    private fun createAndSaveNode(
        name: String,
        x: Int,
        y: Int,
        isPublic: Boolean = true,
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
            securityLevel = if (isPublic) 1 else 3,
            isPublic = isPublic,
            isMissionCritical = false
        )
        nodeRepository.saveNode(node)
        return node
    }
}
