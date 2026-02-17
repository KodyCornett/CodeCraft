package com.codecraft.engine.network.generation

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.Districts
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.DiscoveryRepository
import com.codecraft.engine.network.persistence.DistrictRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.persistence.PlayersTable
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.insert
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class PlayerSpawnServiceTest {

    private lateinit var database: Database
    private lateinit var nodeRepository: NodeRepository
    private lateinit var discoveryManager: DiscoveryManager
    private lateinit var positionRepository: PositionRepository
    private lateinit var spawnService: PlayerSpawnService

    private val testPlayerId = "test-player-spawn"

    @BeforeEach
    fun setup() {
        database = Database.connect(
            "jdbc:h2:mem:spawn_test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
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
        val discoveryRepository = DiscoveryRepository(database)
        discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
        positionRepository = PositionRepository(database)
        spawnService = PlayerSpawnService(nodeRepository, discoveryManager, positionRepository)

        // Seed the world so there are nodes to spawn into
        val districtRepository = DistrictRepository(database)
        val connectionRepository = ConnectionRepository(database)
        WorldBootstrap(districtRepository, nodeRepository, connectionRepository).bootstrapIfEmpty()
    }

    @Test
    fun `spawnPlayer sets position on first call`() {
        assertNull(positionRepository.getCurrentPosition(testPlayerId))

        spawnService.spawnPlayer(testPlayerId)

        assertNotNull(positionRepository.getCurrentPosition(testPlayerId))
    }

    @Test
    fun `spawnPlayer discovers spawn node as CONNECTED`() {
        spawnService.spawnPlayer(testPlayerId)

        val spawnNodeId = positionRepository.getCurrentPosition(testPlayerId)!!
        val state = discoveryManager.getDiscoveryState(testPlayerId, spawnNodeId)
        assertEquals(DiscoveryState.CONNECTED, state)
    }

    @Test
    fun `spawnPlayer auto-discovers nearby nodes`() {
        spawnService.spawnPlayer(testPlayerId)

        val discoveries = discoveryManager.getDiscoveredNodes(testPlayerId)
        // Player should discover the spawn node + at least some nearby nodes
        assertTrue(discoveries.isNotEmpty(), "Player should have at least 1 discovered node after spawn")
    }

    @Test
    fun `spawnPlayer is idempotent (does not re-spawn if position exists)`() {
        spawnService.spawnPlayer(testPlayerId)
        val firstPosition = positionRepository.getCurrentPosition(testPlayerId)
        val firstDiscoveries = discoveryManager.getDiscoveredNodes(testPlayerId).size

        // Second call should be a no-op
        spawnService.spawnPlayer(testPlayerId)

        assertEquals(firstPosition, positionRepository.getCurrentPosition(testPlayerId))
        assertEquals(firstDiscoveries, discoveryManager.getDiscoveredNodes(testPlayerId).size)
    }

    @Test
    fun `spawnPlayer prefers Downtown public nodes`() {
        spawnService.spawnPlayer(testPlayerId)

        val spawnNodeId = positionRepository.getCurrentPosition(testPlayerId)!!
        val downtownNodes = nodeRepository
            .getNodesByDistrict(Districts.DOWNTOWN.districtId)
            .map { it.nodeId }
            .toSet()

        assertTrue(
            spawnNodeId in downtownNodes,
            "Spawn node should be in Downtown when Downtown has public nodes"
        )
    }

    @Test
    fun `spawnPlayer falls back to any public node when no downtown nodes`() {
        // Use a fresh player with a custom node repository that has no downtown nodes
        val plainNode = NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = "Fallback Node",
            nodeType = NodeType.CAFE,
            district = null,
            coordX = 9000,
            coordY = 9000,
            ipAddress = "192.168.1.1",
            isPublic = true
        )

        // Create a fresh DB with only the fallback node
        val freshDb = Database.connect(
            "jdbc:h2:mem:spawn_fallback_test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            driver = "org.h2.Driver"
        )
        transaction(freshDb) {
            SchemaUtils.create(
                PlayersTable,
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable,
                PlayerDiscoveredNodesTable,
                PlayerNetworkPositionTable
            )
            PlayersTable.insert { it[id] = "fallback-player" }
        }

        val freshNodeRepo = NodeRepository(freshDb)
        freshNodeRepo.saveNode(plainNode)
        val freshDiscoveryRepo = DiscoveryRepository(freshDb)
        val freshDiscoveryManager = DiscoveryManager(freshDiscoveryRepo, freshNodeRepo)
        val freshPositionRepo = PositionRepository(freshDb)

        val freshSpawnService = PlayerSpawnService(freshNodeRepo, freshDiscoveryManager, freshPositionRepo)
        freshSpawnService.spawnPlayer("fallback-player")

        val position = freshPositionRepo.getCurrentPosition("fallback-player")
        assertEquals(plainNode.nodeId, position)
    }

    @Test
    fun `spawnPlayer does nothing when network is empty`() {
        // Use a completely empty DB
        val emptyDb = Database.connect(
            "jdbc:h2:mem:spawn_empty_test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            driver = "org.h2.Driver"
        )
        transaction(emptyDb) {
            SchemaUtils.create(
                PlayersTable,
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable,
                PlayerDiscoveredNodesTable,
                PlayerNetworkPositionTable
            )
            PlayersTable.insert { it[id] = "empty-player" }
        }

        val emptyNodeRepo = NodeRepository(emptyDb)
        val emptyDiscoveryRepo = DiscoveryRepository(emptyDb)
        val emptyDiscoveryManager = DiscoveryManager(emptyDiscoveryRepo, emptyNodeRepo)
        val emptyPositionRepo = PositionRepository(emptyDb)

        val emptySpawnService = PlayerSpawnService(emptyNodeRepo, emptyDiscoveryManager, emptyPositionRepo)
        emptySpawnService.spawnPlayer("empty-player")

        // Position should still be null — no crash, just a no-op
        assertNull(emptyPositionRepo.getCurrentPosition("empty-player"))
    }
}
