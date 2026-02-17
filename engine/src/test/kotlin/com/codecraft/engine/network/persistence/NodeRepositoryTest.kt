package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class NodeRepositoryTest {

    private lateinit var database: Database
    private lateinit var repository: NodeRepository

    @BeforeEach
    fun setup() {
        // Initialize in-memory H2 database for testing (unique name per test)
        database = Database.connect("jdbc:h2:mem:test_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

        transaction(database) {
            SchemaUtils.create(
                NetworkDistrictsTable,
                NetworkNodesTable
            )
        }

        repository = NodeRepository(database)
    }

    @Test
    fun `saveNode persists to database`() {
        val node = createTestNode()

        val saved = repository.saveNode(node)

        assertEquals(node.nodeId, saved.nodeId)
        assertEquals(node.nodeName, saved.nodeName)
    }

    @Test
    fun `getNodeById retrieves correct node`() {
        val node = createTestNode()
        repository.saveNode(node)

        val retrieved = repository.getNodeById(node.nodeId)

        assertNotNull(retrieved)
        assertEquals(node.nodeId, retrieved.nodeId)
        assertEquals(node.nodeName, retrieved.nodeName)
    }

    @Test
    fun `getNodeById returns null for non-existent node`() {
        val retrieved = repository.getNodeById(UUID.randomUUID())

        assertNull(retrieved)
    }

    @Test
    fun `getNodeByName finds node by name`() {
        val node = createTestNode(name = "Blue Neon Cafe WiFi")
        repository.saveNode(node)

        val retrieved = repository.getNodeByName("Blue Neon Cafe WiFi")

        assertNotNull(retrieved)
        assertEquals(node.nodeId, retrieved.nodeId)
    }

    @Test
    fun `getNodesInRadius returns nodes within range`() {
        // Create nodes at different distances
        val centerNode = createTestNode(coordX = 1000, coordY = 1000)
        val nearNode = createTestNode(coordX = 1100, coordY = 1100) // ~141m away
        val farNode = createTestNode(coordX = 2000, coordY = 2000) // ~1414m away

        repository.saveNode(centerNode)
        repository.saveNode(nearNode)
        repository.saveNode(farNode)

        // Query 200m radius
        val nodesInRange = repository.getNodesInRadius(1000, 1000, 200)

        assertEquals(2, nodesInRange.size) // center + near
        assertTrue(nodesInRange.any { it.nodeId == centerNode.nodeId })
        assertTrue(nodesInRange.any { it.nodeId == nearNode.nodeId })
    }

    @Test
    fun `getNodesInRadius returns empty list when no nodes in range`() {
        val node = createTestNode(coordX = 5000, coordY = 5000)
        repository.saveNode(node)

        val nodesInRange = repository.getNodesInRadius(0, 0, 100)

        assertTrue(nodesInRange.isEmpty())
    }

    @Test
    fun `getNodesByType filters by node type`() {
        val cafe1 = createTestNode(type = NodeType.CAFE, name = "Cafe 1")
        val cafe2 = createTestNode(type = NodeType.CAFE, name = "Cafe 2")
        val bar = createTestNode(type = NodeType.BAR, name = "Bar 1")

        repository.saveNode(cafe1)
        repository.saveNode(cafe2)
        repository.saveNode(bar)

        val cafes = repository.getNodesByType(NodeType.CAFE)

        assertEquals(2, cafes.size)
        assertTrue(cafes.all { it.nodeType == NodeType.CAFE })
    }

    @Test
    fun `getPublicNodes returns only public nodes`() {
        val publicNode = createTestNode(isPublic = true, name = "Public")
        val privateNode = createTestNode(isPublic = false, name = "Private")

        repository.saveNode(publicNode)
        repository.saveNode(privateNode)

        val publicNodes = repository.getPublicNodes()

        assertEquals(1, publicNodes.size)
        assertTrue(publicNodes.first().isPublic)
    }

    @Test
    fun `updateNode modifies existing node`() {
        val node = createTestNode(signalStrength = 50)
        repository.saveNode(node)

        val updated = node.copy(signalStrength = 100)
        repository.updateNode(updated)

        val retrieved = repository.getNodeById(node.nodeId)
        assertEquals(100, retrieved?.signalStrength)
    }

    @Test
    fun `deleteNode removes node from database`() {
        val node = createTestNode()
        repository.saveNode(node)

        repository.deleteNode(node.nodeId)

        val retrieved = repository.getNodeById(node.nodeId)
        assertNull(retrieved)
    }

    @Test
    fun `count returns correct number of nodes`() {
        repository.saveNode(createTestNode(name = "Node 1"))
        repository.saveNode(createTestNode(name = "Node 2"))
        repository.saveNode(createTestNode(name = "Node 3"))

        assertEquals(3, repository.count())
    }

    @Test
    fun `nodeNameExists returns true for existing name`() {
        val node = createTestNode(name = "Unique Name")
        repository.saveNode(node)

        assertTrue(repository.nodeNameExists("Unique Name"))
    }

    // Helper function to create test nodes
    private fun createTestNode(
        name: String = "Test Node ${UUID.randomUUID()}",
        type: NodeType = NodeType.CAFE,
        coordX: Int = 1000,
        coordY: Int = 1000,
        signalStrength: Int = 80,
        isPublic: Boolean = true
    ): NetworkNode {
        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = type,
            district = null,
            coordX = coordX,
            coordY = coordY,
            ipAddress = "10.42.1.${(1..254).random()}",
            signalStrength = signalStrength,
            securityLevel = 2,
            isPublic = isPublic,
            isMissionCritical = false
        )
    }
}
