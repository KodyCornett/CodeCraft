package com.codecraft.engine.persistence

import com.codecraft.engine.database.tables.*
import org.jetbrains.exposed.sql.Database
import org.jetbrains.exposed.sql.SchemaUtils
import org.jetbrains.exposed.sql.transactions.transaction

/**
 * Database initialization and management
 */
object GameDatabase {
    private var initialized = false

    fun init() {
        if (initialized) return

        val dbPath = System.getenv("ENGINE_DB_PATH") ?: "codecraft.db"
        val jdbcUrl = "jdbc:sqlite:$dbPath"

        try {
            Database.connect(
                url = jdbcUrl,
                driver = "org.sqlite.JDBC"
            )
            println("[Database] Connected to SQLite at $dbPath")

            transaction {
                try {
                    SchemaUtils.createMissingTablesAndColumns(
                        // Core tables
                        PlayersTable,
                        MissionHistoryTable,
                        TransactionsTable,
                        JobOffersTable,
                        GameConfigTable,
                        // Network tables
                        NetworkDistrictsTable,
                        NetworkNodesTable,
                        PlayerDiscoveredNodesTable,
                        NodeConnectionsTable,
                        PlayerNetworkPositionTable
                    )
                    println("[Database] Schema creation completed")
                } catch (e: Exception) {
                    println("[Database] ERROR during schema creation: ${e.message}")
                    println("[Database] Stack trace:")
                    e.printStackTrace()
                    throw e
                }
            }

            verifyTables()
            initialized = true
            println("[Database] ✓ Database initialized successfully with all tables")
        } catch (e: Exception) {
            println("[Database] ✗ CRITICAL: Database initialization failed!")
            println("[Database] Error: ${e.message}")
            println("[Database] To fix: delete '$dbPath' and restart the server")
            throw e
        }
    }

    /**
     * Verify that all required tables exist in the database
     */
    private fun verifyTables() {
        transaction {
            val expectedTables = setOf(
                "players", "mission_history", "transactions", "job_offers", "game_config",
                "network_districts", "network_nodes", "player_discovered_nodes",
                "node_connections", "player_network_position"
            )
            val existingTables = mutableSetOf<String>()

            // Query SQLite metadata to get list of tables
            exec("SELECT name FROM sqlite_master WHERE type='table'") { rs ->
                while (rs.next()) {
                    existingTables.add(rs.getString(1))
                }
            }

            val missingTables = expectedTables - existingTables
            if (missingTables.isNotEmpty()) {
                throw IllegalStateException(
                    "Missing database tables: ${missingTables.joinToString(", ")}\n" +
                    "Found tables: ${existingTables.joinToString(", ")}"
                )
            }

            println("[Database] Verified tables: ${existingTables.joinToString(", ")}")
        }
    }

    /**
     * Initialize with a specific JDBC URL (for testing with H2)
     */
    fun initWithUrl(jdbcUrl: String, driver: String) {
        try {
            Database.connect(url = jdbcUrl, driver = driver)

            transaction {
                SchemaUtils.create(
                    // Core tables
                    PlayersTable,
                    MissionHistoryTable,
                    TransactionsTable,
                    JobOffersTable,
                    GameConfigTable,
                    // Network tables
                    NetworkDistrictsTable,
                    NetworkNodesTable,
                    PlayerDiscoveredNodesTable,
                    NodeConnectionsTable,
                    PlayerNetworkPositionTable
                )
            }

            initialized = true
            println("[Database] Test database initialized successfully")
        } catch (e: Exception) {
            println("[Database] ERROR: Test database initialization failed: ${e.message}")
            throw e
        }
    }
}
