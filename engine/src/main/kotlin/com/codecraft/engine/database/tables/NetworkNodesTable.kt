package com.codecraft.engine.database.tables

import org.jetbrains.exposed.sql.Table

/**
 * Table for storing network nodes in the game world
 */
object NetworkNodesTable : Table("network_nodes") {
    val nodeId = uuid("node_id")
    val nodeName = varchar("node_name", 255)
    val nodeType = varchar("node_type", 50)
    val districtId = uuid("district_id").nullable()
    val coordX = integer("coord_x")
    val coordY = integer("coord_y")
    val ipAddress = varchar("ip_address", 15)
    val signalStrength = integer("signal_strength").default(100)
    val securityLevel = integer("security_level").default(1)
    val isPublic = bool("is_public").default(true)
    val isMissionCritical = bool("is_mission_critical").default(false)
    val createdAt = long("created_at").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(nodeId)

    init {
        index(false, coordX, coordY)
        index(false, nodeType)
        index(false, districtId)
    }
}
