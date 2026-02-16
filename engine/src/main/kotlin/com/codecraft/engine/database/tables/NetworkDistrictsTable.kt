package com.codecraft.engine.database.tables

import org.jetbrains.exposed.sql.Table

/**
 * Table for storing network districts (geographic regions)
 */
object NetworkDistrictsTable : Table("network_districts") {
    val districtId = uuid("district_id")
    val districtName = varchar("district_name", 100)
    val districtType = varchar("district_type", 50)
    val centerX = integer("center_x")
    val centerY = integer("center_y")
    val radius = integer("radius")
    val ipPrefix = varchar("ip_prefix", 10)
    val nodeDensity = varchar("node_density", 20).default("MEDIUM")
    val description = text("description").nullable()
    val unlockCondition = varchar("unlock_condition", 100).nullable()
    val createdAt = long("created_at").default(System.currentTimeMillis())

    override val primaryKey = PrimaryKey(districtId)

    init {
        index(false, districtName)
    }
}
