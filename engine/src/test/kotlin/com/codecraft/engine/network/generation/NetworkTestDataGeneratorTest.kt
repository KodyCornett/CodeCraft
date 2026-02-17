package com.codecraft.engine.network.generation

import com.codecraft.engine.database.tables.NetworkDistrictsTable
import com.codecraft.engine.database.tables.NetworkNodesTable
import com.codecraft.engine.database.tables.NodeConnectionsTable
import com.codecraft.engine.network.naming.NodeNameGenerator
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.NodeRepository
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.transactions.transaction
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class NetworkTestDataGeneratorTest {

    private lateinit var database: Database
    private lateinit var repository: NodeRepository
    private lateinit var generator: NetworkTestDataGenerator

    @BeforeEach
    fun setup() {
        database = Database.connect("jdbc:h2:mem:testgen_${System.nanoTime()};DB_CLOSE_DELAY=-1", driver = "org.h2.Driver")

        transaction(database) {
            SchemaUtils.create(
                NetworkDistrictsTable,
                NetworkNodesTable,
                NodeConnectionsTable
            )
        }

        repository = NodeRepository(database)
        val connectionRepository = ConnectionRepository(database)
        val nameGenerator = NodeNameGenerator()
        generator = NetworkTestDataGenerator(nameGenerator, repository, connectionRepository)
    }

    @Test
    fun `generates exactly 100 nodes`() {
        val nodes = generator.generateTestDataset(100)

        assertEquals(100, nodes.size)
        assertEquals(100, repository.count())
    }

    @Test
    fun `all generated nodes have unique names`() {
        val nodes = generator.generateTestDataset(100)

        val names = nodes.map { it.nodeName }
        val uniqueNames = names.toSet()

        assertEquals(names.size, uniqueNames.size)
    }

    @Test
    fun `all generated nodes have valid IP addresses`() {
        val nodes = generator.generateTestDataset(50)

        nodes.forEach { node ->
            assertTrue(node.ipAddress.matches(Regex("""10\.\d+\.\d+\.\d+""")))
        }
    }

    @Test
    fun `generated nodes have varied types`() {
        val nodes = generator.generateTestDataset(100)

        val typeCount = nodes.map { it.nodeType }.distinct().size

        assertTrue(typeCount >= 5, "Expected at least 5 different node types, got $typeCount")
    }

    @Test
    fun `signal strength is in valid range`() {
        val nodes = generator.generateTestDataset(50)

        nodes.forEach { node ->
            assertTrue(node.signalStrength in 60..100)
        }
    }

    @Test
    fun `security level matches node type`() {
        val nodes = generator.generateTestDataset(50)

        nodes.forEach { node ->
            assertTrue(node.securityLevel in 1..5)

            // Public access should have low security
            if (node.nodeType.category.name == "PUBLIC_ACCESS") {
                assertEquals(1, node.securityLevel)
            }

            // Infrastructure should have high security
            if (node.nodeType.category.name == "INFRASTRUCTURE") {
                assertEquals(5, node.securityLevel)
            }
        }
    }

    @Test
    fun `coordinates are within bounds`() {
        val nodes = generator.generateTestDataset(50)

        nodes.forEach { node ->
            assertTrue(node.coordX in 0..5000)
            assertTrue(node.coordY in 0..5000)
        }
    }

    @Test
    fun `public and private nodes are balanced`() {
        val nodes = generator.generateTestDataset(100)

        val publicCount = nodes.count { it.isPublic }
        val privateCount = nodes.size - publicCount

        // Should have both public and private nodes
        assertTrue(publicCount > 0, "Should have public nodes")
        assertTrue(privateCount > 0, "Should have private nodes")

        // Public should be more common (rough estimate)
        assertTrue(publicCount > 30, "Should have at least 30% public nodes")
    }
}
