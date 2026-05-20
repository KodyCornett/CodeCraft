package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.NetworkDistrictsTable
import com.codecraft.engine.database.tables.NetworkNodesTable
import com.codecraft.engine.database.tables.NodeConnectionsTable
import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
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

class ConnectionRepositoryTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var repository: ConnectionRepository

    @BeforeEach
    fun setup() {
        database = Database.connect(
            "jdbc:h2:mem:conntest_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            driver = "org.h2.Driver"
        )

        transaction(database) {
            SchemaUtils.create(
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable
            )
        }

        nodeRepository = NodeRepository(database)
        repository = ConnectionRepository(database)
    }

    @Test
    fun `saveConnection persists to database`() {
        val (nodeA, nodeB) = saveTwoNodes()
        val connection = createTestConnection(nodeA.nodeId, nodeB.nodeId)

        val saved = repository.saveConnection(connection)

        assertEquals(connection.id, saved.id)
        assertEquals(connection.connectionType, saved.connectionType)
    }

    @Test
    fun `getConnectionsForNode retrieves where node is nodeA`() {
        val (nodeA, nodeB) = saveTwoNodes()
        val connection = createTestConnection(nodeA.nodeId, nodeB.nodeId)
        repository.saveConnection(connection)

        val results = repository.getConnectionsForNode(nodeA.nodeId)

        assertEquals(1, results.size)
        assertEquals(connection.id, results.first().id)
    }

    @Test
    fun `getConnectionsForNode retrieves where node is nodeB`() {
        val (nodeA, nodeB) = saveTwoNodes()
        val connection = createTestConnection(nodeA.nodeId, nodeB.nodeId)
        repository.saveConnection(connection)

        val results = repository.getConnectionsForNode(nodeB.nodeId)

        assertEquals(1, results.size)
        assertEquals(connection.id, results.first().id)
    }

    @Test
    fun `getConnectionsForNode returns both sides`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId))
        repository.saveConnection(createTestConnection(nodeC.nodeId, nodeA.nodeId))

        val results = repository.getConnectionsForNode(nodeA.nodeId)

        assertEquals(2, results.size)
    }

    @Test
    fun `getConnection finds connection for A to B order`() {
        val (nodeA, nodeB) = saveTwoNodes()
        val connection = createTestConnection(nodeA.nodeId, nodeB.nodeId)
        repository.saveConnection(connection)

        val result = repository.getConnection(nodeA.nodeId, nodeB.nodeId)

        assertNotNull(result)
        assertEquals(connection.id, result.id)
    }

    @Test
    fun `getConnection finds connection for B to A order`() {
        val (nodeA, nodeB) = saveTwoNodes()
        val connection = createTestConnection(nodeA.nodeId, nodeB.nodeId)
        repository.saveConnection(connection)

        val result = repository.getConnection(nodeB.nodeId, nodeA.nodeId)

        assertNotNull(result)
        assertEquals(connection.id, result.id)
    }

    @Test
    fun `getConnection returns null for non-existent pair`() {
        val result = repository.getConnection(UUID.randomUUID(), UUID.randomUUID())

        assertNull(result)
    }

    @Test
    fun `getAllConnections returns all saved connections`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId))
        repository.saveConnection(createTestConnection(nodeB.nodeId, nodeC.nodeId))
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeC.nodeId))

        val results = repository.getAllConnections()

        assertEquals(3, results.size)
    }

    @Test
    fun `saveConnections batch saves multiple in single transaction`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        val connections = listOf(
            createTestConnection(nodeA.nodeId, nodeB.nodeId),
            createTestConnection(nodeB.nodeId, nodeC.nodeId),
            createTestConnection(nodeA.nodeId, nodeC.nodeId)
        )

        repository.saveConnections(connections)

        assertEquals(3, repository.countConnections())
    }

    @Test
    fun `deleteConnectionsForNode removes from both columns`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        // nodeA appears in both nodeAId and nodeBId positions
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId))
        repository.saveConnection(createTestConnection(nodeC.nodeId, nodeA.nodeId))
        repository.saveConnection(createTestConnection(nodeB.nodeId, nodeC.nodeId)) // no nodeA

        repository.deleteConnectionsForNode(nodeA.nodeId)

        assertEquals(1, repository.countConnections())
        assertTrue(repository.getConnectionsForNode(nodeA.nodeId).isEmpty())
    }

    @Test
    fun `countConnections returns correct count`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId))
        repository.saveConnection(createTestConnection(nodeB.nodeId, nodeC.nodeId))

        assertEquals(2, repository.countConnections())
    }

    @Test
    fun `connectionExists returns true for existing, false otherwise`() {
        val (nodeA, nodeB) = saveTwoNodes()
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId))

        assertTrue(repository.connectionExists(nodeA.nodeId, nodeB.nodeId))
        assertTrue(repository.connectionExists(nodeB.nodeId, nodeA.nodeId)) // symmetric
        assertTrue(!repository.connectionExists(nodeA.nodeId, UUID.randomUUID()))
    }

    @Test
    fun `getConnectionsByType filters correctly`() {
        val nodeA = saveNode("Node A")
        val nodeB = saveNode("Node B")
        val nodeC = saveNode("Node C")
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeB.nodeId, ConnectionType.DIRECT))
        repository.saveConnection(createTestConnection(nodeB.nodeId, nodeC.nodeId, ConnectionType.RELAY))
        repository.saveConnection(createTestConnection(nodeA.nodeId, nodeC.nodeId, ConnectionType.ENCRYPTED))

        val directConnections = repository.getConnectionsByType(ConnectionType.DIRECT)
        val relayConnections = repository.getConnectionsByType(ConnectionType.RELAY)

        assertEquals(1, directConnections.size)
        assertEquals(ConnectionType.DIRECT, directConnections.first().connectionType)
        assertEquals(1, relayConnections.size)
        assertEquals(ConnectionType.RELAY, relayConnections.first().connectionType)
    }

    // --- Helpers ---

    private fun saveNode(name: String): NetworkNode {
        val node = NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = NodeType.CAFE,
            district = null,
            coordX = 1000,
            coordY = 1000,
            ipAddress = "10.42.1.${(1..254).random()}",
            signalStrength = 80,
            securityLevel = 1,
            isPublic = true,
            isMissionCritical = false
        )
        return nodeRepository.saveNode(node)
    }

    private fun saveTwoNodes(): Pair<NetworkNode, NetworkNode> {
        return Pair(saveNode("Node ${UUID.randomUUID()}"), saveNode("Node ${UUID.randomUUID()}"))
    }

    private fun createTestConnection(
        nodeAId: UUID,
        nodeBId: UUID,
        type: ConnectionType = ConnectionType.DIRECT
    ): NodeConnection {
        return NodeConnection(
            id = UUID.randomUUID(),
            nodeAId = nodeAId,
            nodeBId = nodeBId,
            distance = 150,
            connectionQuality = 75,
            connectionType = type,
            isPublic = true,
            isBidirectional = true
        )
    }
}
