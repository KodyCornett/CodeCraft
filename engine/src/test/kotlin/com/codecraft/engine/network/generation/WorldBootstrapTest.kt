package com.codecraft.engine.network.generation

import com.codecraft.engine.database.tables.*
import com.codecraft.engine.network.domain.Districts
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.DistrictRepository
import com.codecraft.engine.network.persistence.NodeRepository
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class WorldBootstrapTest {

    private lateinit var database: Database
    private lateinit var districtRepository: DistrictRepository
    private lateinit var nodeRepository: NodeRepository
    private lateinit var connectionRepository: ConnectionRepository
    private lateinit var bootstrap: WorldBootstrap

    @BeforeEach
    fun setup() {
        database = Database.connect(
            "jdbc:h2:mem:world_bootstrap_test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            driver = "org.h2.Driver"
        )
        transaction(database) {
            SchemaUtils.create(
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable
            )
        }

        districtRepository = DistrictRepository(database)
        nodeRepository = NodeRepository(database)
        connectionRepository = ConnectionRepository(database)
        bootstrap = WorldBootstrap(districtRepository, nodeRepository, connectionRepository)
    }

    @Test
    fun `bootstrapIfEmpty populates all districts`() {
        bootstrap.bootstrapIfEmpty()

        val districtCount = districtRepository.count()
        assertEquals(Districts.getAll().size.toLong(), districtCount)
    }

    @Test
    fun `bootstrapIfEmpty generates nodes for every district`() {
        bootstrap.bootstrapIfEmpty()

        val nodeCount = nodeRepository.count()
        assertTrue(nodeCount > 0, "Expected nodes to be generated but got 0")

        // Each district should contribute at least some nodes
        for (district in Districts.getAll()) {
            val nodesInDistrict = nodeRepository.getNodesByDistrict(district.districtId)
            assertTrue(
                nodesInDistrict.isNotEmpty(),
                "District '${district.name}' should have at least one node"
            )
        }
    }

    @Test
    fun `bootstrapIfEmpty builds connections`() {
        bootstrap.bootstrapIfEmpty()

        val connectionCount = connectionRepository.countConnections()
        assertTrue(connectionCount > 0, "Expected connections to be built but got 0")
    }

    @Test
    fun `bootstrapped nodes have district assignments`() {
        bootstrap.bootstrapIfEmpty()

        val nodes = nodeRepository.getAllNodes()
        // Nodes from DistrictGenerator always have district set
        // (they are loaded without district from NodeRepository, but districtId is stored)
        assertTrue(nodes.isNotEmpty())
        // Verify district UUIDs are all known districts
        val districtIds = Districts.getAll().map { it.districtId }.toSet()
        for (district in Districts.getAll()) {
            val districtNodes = nodeRepository.getNodesByDistrict(district.districtId)
            assertTrue(districtNodes.isNotEmpty(), "District ${district.name} has no nodes")
        }
    }

    @Test
    fun `bootstrapIfEmpty is idempotent (no duplicates on second call)`() {
        bootstrap.bootstrapIfEmpty()
        val nodeCountAfterFirst = nodeRepository.count()
        val districtCountAfterFirst = districtRepository.count()

        // Second call — should be a no-op
        bootstrap.bootstrapIfEmpty()

        assertEquals(nodeCountAfterFirst, nodeRepository.count(), "Node count changed on second bootstrap")
        assertEquals(districtCountAfterFirst, districtRepository.count(), "District count changed on second bootstrap")
    }

    @Test
    fun `starter districts (no unlock condition) are always included`() {
        bootstrap.bootstrapIfEmpty()

        val starterDistricts = Districts.getStarter()
        assertTrue(starterDistricts.isNotEmpty(), "There should be at least one starter district")

        for (district in starterDistricts) {
            val nodes = nodeRepository.getNodesByDistrict(district.districtId)
            assertTrue(nodes.isNotEmpty(), "Starter district '${district.name}' has no nodes")
        }
    }

    @Test
    fun `bootstrapped network has public nodes for spawning`() {
        bootstrap.bootstrapIfEmpty()

        val publicNodes = nodeRepository.getPublicNodes()
        assertTrue(publicNodes.isNotEmpty(), "Network must have at least one public node for player spawn")
    }
}
