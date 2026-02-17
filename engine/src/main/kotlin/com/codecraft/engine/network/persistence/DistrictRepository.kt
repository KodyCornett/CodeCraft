package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.NetworkDistrictsTable
import com.codecraft.engine.network.domain.District
import com.codecraft.engine.network.domain.DistrictType
import com.codecraft.engine.network.domain.NodeDensity
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID

/**
 * Repository for district CRUD operations
 */
class DistrictRepository(private val database: Database) {

    /**
     * Save a district to the database
     */
    fun saveDistrict(district: District): District {
        transaction(database) {
            NetworkDistrictsTable.insert {
                it[districtId] = district.districtId
                it[districtName] = district.name
                it[districtType] = district.type.name
                it[centerX] = district.centerX
                it[centerY] = district.centerY
                it[radius] = district.radius
                it[ipPrefix] = district.ipPrefix
                it[nodeDensity] = district.density.name
                it[description] = district.description
                it[unlockCondition] = district.unlockCondition
            }
        }
        return district
    }

    /**
     * Get a district by ID
     */
    fun getDistrictById(districtId: UUID): District? {
        return transaction(database) {
            NetworkDistrictsTable.selectAll().where { NetworkDistrictsTable.districtId eq districtId }
                .mapNotNull { it.toDistrict() }
                .singleOrNull()
        }
    }

    /**
     * Get a district by name
     */
    fun getDistrictByName(name: String): District? {
        return transaction(database) {
            NetworkDistrictsTable.selectAll().where { NetworkDistrictsTable.districtName eq name }
                .mapNotNull { it.toDistrict() }
                .singleOrNull()
        }
    }

    /**
     * Get all districts
     */
    fun getAllDistricts(): List<District> {
        return transaction(database) {
            NetworkDistrictsTable.selectAll()
                .mapNotNull { it.toDistrict() }
        }
    }

    /**
     * Get unlocked districts (no unlock condition or condition is null)
     */
    fun getUnlockedDistricts(): List<District> {
        return transaction(database) {
            NetworkDistrictsTable.selectAll().where { NetworkDistrictsTable.unlockCondition.isNull() }
                .mapNotNull { it.toDistrict() }
        }
    }

    /**
     * Get districts by type
     */
    fun getDistrictsByType(type: DistrictType): List<District> {
        return transaction(database) {
            NetworkDistrictsTable.selectAll().where { NetworkDistrictsTable.districtType eq type.name }
                .mapNotNull { it.toDistrict() }
        }
    }

    /**
     * Update a district
     */
    fun updateDistrict(district: District) {
        transaction(database) {
            NetworkDistrictsTable.update({ NetworkDistrictsTable.districtId eq district.districtId }) {
                it[districtName] = district.name
                it[districtType] = district.type.name
                it[centerX] = district.centerX
                it[centerY] = district.centerY
                it[radius] = district.radius
                it[ipPrefix] = district.ipPrefix
                it[nodeDensity] = district.density.name
                it[description] = district.description
                it[unlockCondition] = district.unlockCondition
            }
        }
    }

    /**
     * Delete a district
     */
    fun deleteDistrict(districtId: UUID) {
        transaction(database) {
            NetworkDistrictsTable.deleteWhere { NetworkDistrictsTable.districtId eq districtId }
        }
    }

    /**
     * Count total districts
     */
    fun count(): Long {
        return transaction(database) {
            NetworkDistrictsTable.selectAll().count()
        }
    }

    /**
     * Convert ResultRow to District
     */
    private fun ResultRow.toDistrict(): District? {
        return try {
            District(
                districtId = this[NetworkDistrictsTable.districtId],
                name = this[NetworkDistrictsTable.districtName],
                type = DistrictType.valueOf(this[NetworkDistrictsTable.districtType]),
                centerX = this[NetworkDistrictsTable.centerX],
                centerY = this[NetworkDistrictsTable.centerY],
                radius = this[NetworkDistrictsTable.radius],
                ipPrefix = this[NetworkDistrictsTable.ipPrefix],
                density = NodeDensity.valueOf(this[NetworkDistrictsTable.nodeDensity]),
                description = this[NetworkDistrictsTable.description],
                unlockCondition = this[NetworkDistrictsTable.unlockCondition]
            )
        } catch (e: Exception) {
            println("[DistrictRepository] Error converting row to District: ${e.message}")
            null
        }
    }
}
