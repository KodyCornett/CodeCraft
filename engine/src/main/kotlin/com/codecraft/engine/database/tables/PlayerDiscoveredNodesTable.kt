package com.codecraft.engine.database.tables

import com.codecraft.engine.persistence.PlayersTable
import org.jetbrains.exposed.sql.Table

/**
 * Table for tracking which nodes each player has discovered
 */
object PlayerDiscoveredNodesTable : Table("player_discovered_nodes") {
    val id = uuid("id")
    val playerId = varchar("player_id", 128).references(PlayersTable.id)
    val nodeId = uuid("node_id").references(NetworkNodesTable.nodeId)
    val discoveryState = varchar("discovery_state", 50)
    val discoveredAt = long("discovered_at").default(System.currentTimeMillis())
    val lastAccessed = long("last_accessed").nullable()
    val accessCount = integer("access_count").default(0)
    val playerNotes = text("player_notes").nullable()

    override val primaryKey = PrimaryKey(id)

    init {
        uniqueIndex(playerId, nodeId)
        index(false, playerId)
        index(false, discoveryState)
    }
}
