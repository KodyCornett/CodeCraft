package com.codecraft.engine.database.tables

import org.jetbrains.exposed.sql.Table

/**
 * Table for storing connections between network nodes
 */
object NodeConnectionsTable : Table("node_connections") {
    val id = uuid("id")
    val nodeAId = uuid("node_a_id").references(NetworkNodesTable.nodeId)
    val nodeBId = uuid("node_b_id").references(NetworkNodesTable.nodeId)
    val distance = integer("distance")
    val connectionQuality = integer("connection_quality").default(100)
    val connectionType = varchar("connection_type", 50).default("DIRECT")
    val isPublic = bool("is_public").default(true)
    val isBidirectional = bool("is_bidirectional").default(true)
    val createdAt = long("created_at").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(id)

    init {
        index(false, nodeAId)
        index(false, nodeBId)
    }
}
