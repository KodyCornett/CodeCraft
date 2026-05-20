package com.codecraft.engine.database.tables

import com.codecraft.engine.persistence.PlayersTable
import org.jetbrains.exposed.sql.Table

/**
 * Table for tracking player's current position in the network
 */
object PlayerNetworkPositionTable : Table("player_network_position") {
    val playerId = varchar("player_id", 128).references(PlayersTable.id)
    val currentNodeId = uuid("current_node_id").nullable()
    val previousNodeId = uuid("previous_node_id").nullable()
    val lastPositionUpdate = long("last_position_update").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(playerId)
}
