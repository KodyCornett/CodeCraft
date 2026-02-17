package com.codecraft.engine.command.commands

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.persistence.ConnectionRepository
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
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertTrue

class RouteCommandTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var connectionRepository: ConnectionRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var positionRepository: PositionRepository
    private lateinit var routeCommand: RouteCommand
    private lateinit var session: GameSession

    private val testPlayerId = "test-player-route"

    @BeforeEach
    fun setup() {
        database = Database.connect(
            "jdbc:h2:mem:route_cmd_test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            driver = "org.h2.Driver"
        )

        transaction(database) {
            SchemaUtils.create(
                PlayersTable,
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable,
                PlayerDiscoveredNodesTable,
                PlayerNetworkPositionTable
            )

            PlayersTable.insert {
                it[id] = testPlayerId
                it[credits] = 5000
            }
        }

        nodeRepository = NodeRepository(database)
        connectionRepository = ConnectionRepository(database)
        val discoveryRepository = DiscoveryRepository(database)
        discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
        positionRepository = PositionRepository(database)

        routeCommand = RouteCommand(
            nodeRepository = nodeRepository,
            connectionRepository = connectionRepository,
            discoveryManager = discoveryManager,
            positionRepository = positionRepository
        )

        session = GameSession(sessionId = testPlayerId)
    }

    // ── helpers ────────────────────────────────────────────────────────────────

    private fun createNode(name: String, ip: String? = null): NetworkNode {
        val node = NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = NodeType.CAFE,
            coordX = (100..900).random(),
            coordY = (100..900).random(),
            ipAddress = ip ?: "10.42.${(1..254).random()}.${(1..254).random()}",
            signalStrength = 80,
            securityLevel = 2,
            isPublic = true
        )
        nodeRepository.saveNode(node)
        return node
    }

    private fun connect(nodeA: NetworkNode, nodeB: NetworkNode, type: ConnectionType = ConnectionType.DIRECT) {
        val conn = NodeConnection(
            id = UUID.randomUUID(),
            nodeAId = nodeA.nodeId,
            nodeBId = nodeB.nodeId,
            distance = 150,
            connectionQuality = 75,
            connectionType = type,
            isPublic = true,
            isBidirectional = true
        )
        connectionRepository.saveConnection(conn)
    }

    private fun discoverAndPosition(node: NetworkNode) {
        discoveryManager.discoverNode(testPlayerId, node)
        positionRepository.updatePosition(testPlayerId, node.nodeId)
    }

    // ── tests ──────────────────────────────────────────────────────────────────

    @Test
    fun `route with valid path returns success with formatted output`() {
        val start = createNode("Neon Byte Cafe")
        val end = createNode("Cipher Labs")
        connect(start, end)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, end)

        val result = routeCommand.execute(session, listOf("Cipher Labs"))

        assertTrue(result.success)
        assertTrue(result.output.contains("ROUTE:"))
        assertTrue(result.output.contains("Neon Byte Cafe"))
        assertTrue(result.output.contains("Cipher Labs"))
    }

    @Test
    fun `route with no path returns not-found message`() {
        val start = createNode("Island A")
        val isolated = createNode("Island B")
        // No connection between them

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, isolated)

        val result = routeCommand.execute(session, listOf("Island B"))

        assertFalse(result.success)
        assertTrue(result.output.contains("No route found"))
    }

    @Test
    fun `route with unknown target returns error`() {
        val start = createNode("Known Node")
        discoverAndPosition(start)

        val result = routeCommand.execute(session, listOf("Nonexistent Node"))

        assertFalse(result.success)
        assertTrue(result.output.contains("not found") || result.output.contains("not yet discovered"))
    }

    @Test
    fun `route without current position returns not-connected error`() {
        val node = createNode("Some Node")
        discoveryManager.discoverNode(testPlayerId, node)
        // No position set

        val result = routeCommand.execute(session, listOf("Some Node"))

        assertFalse(result.success)
        assertTrue(result.output.contains("Not connected") || result.output.contains("nconnect"))
    }

    @Test
    fun `route without args returns usage error`() {
        val result = routeCommand.execute(session, emptyList())

        assertFalse(result.success)
        assertTrue(result.output.contains("Usage:"))
    }

    @Test
    fun `route output contains hop numbers`() {
        val start = createNode("Start Node")
        val middle = createNode("Middle Node")
        val end = createNode("End Node")
        connect(start, middle)
        connect(middle, end)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, middle)
        discoveryManager.discoverNode(testPlayerId, end)

        val result = routeCommand.execute(session, listOf("End Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("[0]"))
        assertTrue(result.output.contains("[1]"))
        assertTrue(result.output.contains("[2]"))
    }

    @Test
    fun `route output contains connection type labels`() {
        val start = createNode("Src Node")
        val end = createNode("Dst Node")
        connect(start, end, ConnectionType.RELAY)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, end)

        val result = routeCommand.execute(session, listOf("Dst Node"))

        assertTrue(result.success)
        assertTrue(result.output.contains("RELAY"))
    }

    @Test
    fun `route output contains total hop count`() {
        val start = createNode("Alpha")
        val end = createNode("Gamma")
        val mid = createNode("Beta")
        connect(start, mid)
        connect(mid, end)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, mid)
        discoveryManager.discoverNode(testPlayerId, end)

        val result = routeCommand.execute(session, listOf("Gamma"))

        assertTrue(result.success)
        assertTrue(result.output.contains("2 hops"))
    }

    @Test
    fun `route has exposureChange of 0_5`() {
        val start = createNode("Start")
        val end = createNode("End")
        connect(start, end)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, end)

        val result = routeCommand.execute(session, listOf("End"))

        assertTrue(result.success)
        assertEquals(0.5, result.exposureChange)
    }

    @Test
    fun `route finds target by IP address`() {
        val start = createNode("Origin")
        val target = createNode("Target Node", ip = "192.168.42.99")
        connect(start, target)

        discoverAndPosition(start)
        discoveryManager.discoverNode(testPlayerId, target)

        val result = routeCommand.execute(session, listOf("192.168.42.99"))

        assertTrue(result.success)
        assertTrue(result.output.contains("Target Node"))
    }
}
