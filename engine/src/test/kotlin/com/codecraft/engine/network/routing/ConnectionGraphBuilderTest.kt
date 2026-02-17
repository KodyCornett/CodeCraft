package com.codecraft.engine.network.routing

import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.District
import com.codecraft.engine.network.domain.DistrictType
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeCategory
import com.codecraft.engine.network.domain.NodeDensity
import com.codecraft.engine.network.domain.NodeType
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class ConnectionGraphBuilderTest {

    private val builder = ConnectionGraphBuilder()

    private val testDistrict = District(
        districtId = UUID.randomUUID(),
        name = "Test District",
        type = DistrictType.FINANCIAL,
        centerX = 1000,
        centerY = 1000,
        radius = 1000,
        ipPrefix = "10.42",
        density = NodeDensity.MEDIUM
    )

    @Test
    fun `public nodes within 300m get DIRECT connection`() {
        val nodeA = publicNode(coordX = 0, coordY = 0)
        val nodeB = publicNode(coordX = 200, coordY = 0) // 200m apart

        val connections = builder.buildConnections(listOf(nodeA, nodeB))

        assertEquals(1, connections.size)
        assertEquals(ConnectionType.DIRECT, connections.first().connectionType)
    }

    @Test
    fun `public nodes exactly at 300m get DIRECT connection`() {
        val nodeA = publicNode(coordX = 0, coordY = 0)
        val nodeB = publicNode(coordX = 300, coordY = 0) // exactly 300m

        val connections = builder.buildConnections(listOf(nodeA, nodeB))

        assertEquals(1, connections.size)
        assertEquals(ConnectionType.DIRECT, connections.first().connectionType)
    }

    @Test
    fun `public nodes beyond 300m get no connection`() {
        val nodeA = publicNode(coordX = 0, coordY = 0)
        val nodeB = publicNode(coordX = 301, coordY = 0) // 301m apart

        val connections = builder.buildConnections(listOf(nodeA, nodeB))

        assertTrue(connections.isEmpty())
    }

    @Test
    fun `single public node produces no connections`() {
        val nodeA = publicNode(coordX = 0, coordY = 0)

        val connections = builder.buildConnections(listOf(nodeA))

        assertTrue(connections.isEmpty())
    }

    @Test
    fun `three public nodes within 300m produce 3 connections, not 6`() {
        // Triangle of 3 nodes all within 300m of each other
        val nodeA = publicNode(coordX = 0, coordY = 0)
        val nodeB = publicNode(coordX = 200, coordY = 0)
        val nodeC = publicNode(coordX = 100, coordY = 100)

        val connections = builder.buildConnections(listOf(nodeA, nodeB, nodeC))

        // 3 pairs: A-B, A-C, B-C — no duplicates
        assertEquals(3, connections.size)
        val pairs = connections.map { setOf(it.nodeAId, it.nodeBId) }.toSet()
        assertEquals(3, pairs.size)
    }

    @Test
    fun `private node within 500m of public gets RELAY connection`() {
        val publicN = publicNode(coordX = 0, coordY = 0)
        val privateN = privateNode(coordX = 400, coordY = 0) // 400m away

        val connections = builder.buildConnections(listOf(publicN, privateN))

        assertEquals(1, connections.size)
        assertEquals(ConnectionType.RELAY, connections.first().connectionType)
    }

    @Test
    fun `private node beyond 500m gets no connection`() {
        val publicN = publicNode(coordX = 0, coordY = 0)
        val privateN = privateNode(coordX = 600, coordY = 0) // 600m away

        val connections = builder.buildConnections(listOf(publicN, privateN))

        assertTrue(connections.isEmpty())
    }

    @Test
    fun `private node connects to nearest public when multiple options exist`() {
        val nearPublic = publicNode(coordX = 100, coordY = 0)  // 100m away
        val farPublic = publicNode(coordX = 400, coordY = 0)   // 400m away
        val privateN = privateNode(coordX = 0, coordY = 0)

        val connections = builder.buildConnections(listOf(nearPublic, farPublic, privateN))

        val relayConnections = connections.filter { it.connectionType == ConnectionType.RELAY }
        assertEquals(1, relayConnections.size)
        assertEquals(nearPublic.nodeId, relayConnections.first().nodeBId)
    }

    @Test
    fun `each private node gets at most one RELAY connection`() {
        // Two public nodes within range, one private node
        val pub1 = publicNode(coordX = 100, coordY = 0)
        val pub2 = publicNode(coordX = 200, coordY = 0)
        val priv = privateNode(coordX = 0, coordY = 0)

        val connections = builder.buildConnections(listOf(pub1, pub2, priv))

        val relayConnections = connections.filter { it.connectionType == ConnectionType.RELAY }
        assertEquals(1, relayConnections.size)
    }

    @Test
    fun `corporate nodes in same district within 400m get ENCRYPTED connection`() {
        val corp1 = corporateNode(coordX = 0, coordY = 0, district = testDistrict)
        val corp2 = corporateNode(coordX = 300, coordY = 0, district = testDistrict)

        val connections = builder.buildConnections(listOf(corp1, corp2))

        assertEquals(1, connections.size)
        assertEquals(ConnectionType.ENCRYPTED, connections.first().connectionType)
    }

    @Test
    fun `corporate nodes in different districts do not connect`() {
        val districtB = testDistrict.copy(districtId = UUID.randomUUID(), name = "District B")
        val corp1 = corporateNode(coordX = 0, coordY = 0, district = testDistrict)
        val corp2 = corporateNode(coordX = 300, coordY = 0, district = districtB)

        val connections = builder.buildConnections(listOf(corp1, corp2))

        assertTrue(connections.none { it.connectionType == ConnectionType.ENCRYPTED })
    }

    @Test
    fun `corporate nodes in same district beyond 400m do not connect`() {
        val corp1 = corporateNode(coordX = 0, coordY = 0, district = testDistrict)
        val corp2 = corporateNode(coordX = 500, coordY = 0, district = testDistrict) // 500m

        val connections = builder.buildConnections(listOf(corp1, corp2))

        assertTrue(connections.none { it.connectionType == ConnectionType.ENCRYPTED })
    }

    @Test
    fun `corporate nodes with null district do not connect`() {
        val corp1 = corporateNode(coordX = 0, coordY = 0, district = null)
        val corp2 = corporateNode(coordX = 300, coordY = 0, district = null)

        val connections = builder.buildConnections(listOf(corp1, corp2))

        assertTrue(connections.none { it.connectionType == ConnectionType.ENCRYPTED })
    }

    @Test
    fun `calculateConnectionQuality returns average signal minus distance penalty`() {
        // quality = ((80 + 60) / 2) - (200 / 10) = 70 - 20 = 50
        val quality = builder.calculateConnectionQuality(80, 60, 200)

        assertEquals(50, quality)
    }

    @Test
    fun `calculateConnectionQuality clamps to 0 when penalty exceeds signal`() {
        // quality = ((20 + 10) / 2) - (500 / 10) = 15 - 50 = -35 → clamped to 0
        val quality = builder.calculateConnectionQuality(20, 10, 500)

        assertEquals(0, quality)
    }

    @Test
    fun `buildConnections handles empty node list`() {
        val connections = builder.buildConnections(emptyList())

        assertTrue(connections.isEmpty())
    }

    // --- Helpers ---

    private fun publicNode(
        coordX: Int = 0,
        coordY: Int = 0,
        district: District? = null,
        nodeType: NodeType = NodeType.CAFE
    ): NetworkNode = NetworkNode(
        nodeId = UUID.randomUUID(),
        nodeName = "Public Node ${UUID.randomUUID()}",
        nodeType = nodeType,
        district = district,
        coordX = coordX,
        coordY = coordY,
        ipAddress = "10.42.1.1",
        signalStrength = 80,
        securityLevel = 1,
        isPublic = true,
        isMissionCritical = false
    )

    private fun privateNode(
        coordX: Int = 0,
        coordY: Int = 0,
        district: District? = null
    ): NetworkNode = NetworkNode(
        nodeId = UUID.randomUUID(),
        nodeName = "Private Node ${UUID.randomUUID()}",
        nodeType = NodeType.WAREHOUSE,
        district = district,
        coordX = coordX,
        coordY = coordY,
        ipAddress = "10.42.2.1",
        signalStrength = 70,
        securityLevel = 3,
        isPublic = false,
        isMissionCritical = false
    )

    private fun corporateNode(
        coordX: Int = 0,
        coordY: Int = 0,
        district: District? = null
    ): NetworkNode = NetworkNode(
        nodeId = UUID.randomUUID(),
        nodeName = "Corporate Node ${UUID.randomUUID()}",
        nodeType = NodeType.OFFICE_BUILDING,
        district = district,
        coordX = coordX,
        coordY = coordY,
        ipAddress = "10.42.3.1",
        signalStrength = 75,
        securityLevel = 4,
        isPublic = false,
        isMissionCritical = false
    )
}
