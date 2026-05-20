package com.codecraft.engine.network.routing

import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
import com.codecraft.engine.network.domain.NodeType
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class NetworkPathfinderTest {

    private val pathfinder = NetworkPathfinder()

    // ── helpers ────────────────────────────────────────────────────────────────

    private fun makeNode(name: String): NetworkNode = NetworkNode(
        nodeId = UUID.randomUUID(),
        nodeName = name,
        nodeType = NodeType.CAFE,
        coordX = 0,
        coordY = 0,
        ipAddress = "10.0.0.${(1..254).random()}"
    )

    private fun makeConnection(
        a: NetworkNode,
        b: NetworkNode,
        distance: Int = 100,
        quality: Int = 80,
        type: ConnectionType = ConnectionType.DIRECT
    ): NodeConnection = NodeConnection(
        id = UUID.randomUUID(),
        nodeAId = a.nodeId,
        nodeBId = b.nodeId,
        distance = distance,
        connectionQuality = quality,
        connectionType = type,
        isPublic = true,
        isBidirectional = true
    )

    private fun nodeMap(vararg nodes: NetworkNode): Map<UUID, NetworkNode> =
        nodes.associateBy { it.nodeId }

    private fun allowedAll(vararg nodes: NetworkNode): Set<UUID> =
        nodes.map { it.nodeId }.toSet()

    // ── tests ──────────────────────────────────────────────────────────────────

    @Test
    fun `findPath returns single hop for directly connected nodes`() {
        val a = makeNode("Alpha")
        val b = makeNode("Beta")
        val conn = makeConnection(a, b)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNotNull(result)
        assertEquals(2, result.hops.size)   // start + target
        assertEquals(1, result.totalHops)
        assertEquals(a.nodeId, result.hops[0].node.nodeId)
        assertEquals(b.nodeId, result.hops[1].node.nodeId)
    }

    @Test
    fun `findPath returns two hops through intermediate node`() {
        val a = makeNode("Alpha")
        val m = makeNode("Middle")
        val b = makeNode("Beta")
        val conn1 = makeConnection(a, m)
        val conn2 = makeConnection(m, b)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn1, conn2),
            nodeMap = nodeMap(a, m, b),
            allowedNodeIds = allowedAll(a, m, b)
        )

        assertNotNull(result)
        assertEquals(3, result.hops.size)
        assertEquals(2, result.totalHops)
        assertEquals(m.nodeId, result.hops[1].node.nodeId)
        assertEquals(b.nodeId, result.hops[2].node.nodeId)
    }

    @Test
    fun `findPath returns null when no path exists`() {
        val a = makeNode("Alpha")
        val b = makeNode("Beta")
        // No connection between a and b

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = emptyList(),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNull(result)
    }

    @Test
    fun `findPath returns null when target not in allowed nodes`() {
        val a = makeNode("Alpha")
        val b = makeNode("Beta")
        val conn = makeConnection(a, b)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = setOf(a.nodeId)  // b is NOT allowed
        )

        assertNull(result)
    }

    @Test
    fun `findPath returns null when intermediate is not in allowed nodes`() {
        val a = makeNode("Alpha")
        val m = makeNode("Hidden Middle")
        val b = makeNode("Beta")
        val conn1 = makeConnection(a, m)
        val conn2 = makeConnection(m, b)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn1, conn2),
            nodeMap = nodeMap(a, m, b),
            allowedNodeIds = setOf(a.nodeId, b.nodeId)  // m is NOT allowed
        )

        assertNull(result)
    }

    @Test
    fun `findPath uses BFS (finds fewest hops, not arbitrary path)`() {
        // a → m1 → b  (2 hops, short)
        // a → m2 → m3 → b  (3 hops, long)
        val a = makeNode("Alpha")
        val m1 = makeNode("Relay1")
        val m2 = makeNode("Relay2")
        val m3 = makeNode("Relay3")
        val b = makeNode("Beta")

        val connections = listOf(
            makeConnection(a, m1),
            makeConnection(m1, b),
            makeConnection(a, m2),
            makeConnection(m2, m3),
            makeConnection(m3, b)
        )

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = connections,
            nodeMap = nodeMap(a, m1, m2, m3, b),
            allowedNodeIds = allowedAll(a, m1, m2, m3, b)
        )

        assertNotNull(result)
        assertEquals(2, result.totalHops)  // Should take the 2-hop path
    }

    @Test
    fun `findPath returns correct total distance`() {
        val a = makeNode("Alpha")
        val b = makeNode("Beta")
        val conn = makeConnection(a, b, distance = 250)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNotNull(result)
        assertEquals(250, result.totalDistance)
    }

    @Test
    fun `findPath returns correct hop count`() {
        val a = makeNode("Alpha")
        val m = makeNode("Middle")
        val b = makeNode("Beta")

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(makeConnection(a, m, distance = 100), makeConnection(m, b, distance = 150)),
            nodeMap = nodeMap(a, m, b),
            allowedNodeIds = allowedAll(a, m, b)
        )

        assertNotNull(result)
        assertEquals(2, result.totalHops)
        assertEquals(250, result.totalDistance)
    }

    @Test
    fun `findPath for start equals target returns single-node result`() {
        val a = makeNode("Alpha")

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = a.nodeId,
            allConnections = emptyList(),
            nodeMap = nodeMap(a),
            allowedNodeIds = allowedAll(a)
        )

        assertNotNull(result)
        assertEquals(1, result.hops.size)
        assertEquals(0, result.totalHops)
        assertEquals(0, result.totalDistance)
        assertEquals(100, result.averageQuality)
    }

    @Test
    fun `findPath works with RELAY connection type`() {
        val a = makeNode("Private Node")
        val b = makeNode("Gateway Node")
        val conn = makeConnection(a, b, type = ConnectionType.RELAY)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNotNull(result)
        assertEquals(1, result.totalHops)
        assertEquals(ConnectionType.RELAY, result.hops[1].connection?.connectionType)
    }

    @Test
    fun `findPath works with ENCRYPTED connection type`() {
        val a = makeNode("Corp Node A")
        val b = makeNode("Corp Node B")
        val conn = makeConnection(a, b, type = ConnectionType.ENCRYPTED)

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = listOf(conn),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNotNull(result)
        assertEquals(ConnectionType.ENCRYPTED, result.hops[1].connection?.connectionType)
    }

    @Test
    fun `findPath handles empty connections list`() {
        val a = makeNode("Alpha")
        val b = makeNode("Beta")

        val result = pathfinder.findPath(
            startNodeId = a.nodeId,
            targetNodeId = b.nodeId,
            allConnections = emptyList(),
            nodeMap = nodeMap(a, b),
            allowedNodeIds = allowedAll(a, b)
        )

        assertNull(result)
    }
}
