package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.NetworkNodesTable
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID
import kotlin.math.sqrt

/**
 * Repository for network node CRUD operations
 */
class NodeRepository(private val database: Database) {

    /**
     * Save a node to the database
     */
    fun saveNode(node: NetworkNode): NetworkNode {
        transaction(database) {
            NetworkNodesTable.insert {
                it[nodeId] = node.nodeId
                it[nodeName] = node.nodeName
                it[nodeType] = node.nodeType.name
                it[districtId] = node.district?.districtId
                it[coordX] = node.coordX
                it[coordY] = node.coordY
                it[ipAddress] = node.ipAddress
                it[signalStrength] = node.signalStrength
                it[securityLevel] = node.securityLevel
                it[isPublic] = node.isPublic
                it[isMissionCritical] = node.isMissionCritical
            }
        }
        return node
    }

    /**
     * Get a node by ID
     */
    fun getNodeById(nodeId: UUID): NetworkNode? {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.nodeId eq nodeId }
                .mapNotNull { it.toNetworkNode() }
                .singleOrNull()
        }
    }

    /**
     * Get a node by name
     */
    fun getNodeByName(name: String): NetworkNode? {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.nodeName eq name }
                .mapNotNull { it.toNetworkNode() }
                .singleOrNull()
        }
    }

    /**
     * Get all nodes in a radius around a point (spatial query)
     */
    fun getNodesInRadius(x: Int, y: Int, radius: Int): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .mapNotNull { it.toNetworkNode() }
                .filter { node ->
                    calculateDistance(x, y, node.coordX, node.coordY) <= radius
                }
        }
    }

    /**
     * Get all nodes in a district
     */
    fun getNodesByDistrict(districtId: UUID): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.districtId eq districtId }
                .mapNotNull { it.toNetworkNode() }
        }
    }

    /**
     * Get all nodes of a specific type
     */
    fun getNodesByType(type: NodeType): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.nodeType eq type.name }
                .mapNotNull { it.toNetworkNode() }
        }
    }

    /**
     * Get all public nodes
     */
    fun getPublicNodes(): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.isPublic eq true }
                .mapNotNull { it.toNetworkNode() }
        }
    }

    /**
     * Get all mission-critical nodes
     */
    fun getMissionCriticalNodes(): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.isMissionCritical eq true }
                .mapNotNull { it.toNetworkNode() }
        }
    }

    /**
     * Update a node
     */
    fun updateNode(node: NetworkNode) {
        transaction(database) {
            NetworkNodesTable.update({ NetworkNodesTable.nodeId eq node.nodeId }) {
                it[nodeName] = node.nodeName
                it[nodeType] = node.nodeType.name
                it[districtId] = node.district?.districtId
                it[coordX] = node.coordX
                it[coordY] = node.coordY
                it[ipAddress] = node.ipAddress
                it[signalStrength] = node.signalStrength
                it[securityLevel] = node.securityLevel
                it[isPublic] = node.isPublic
                it[isMissionCritical] = node.isMissionCritical
            }
        }
    }

    /**
     * Delete a node
     */
    fun deleteNode(nodeId: UUID) {
        transaction(database) {
            NetworkNodesTable.deleteWhere { NetworkNodesTable.nodeId eq nodeId }
        }
    }

    /**
     * Count total nodes
     */
    fun count(): Long {
        return transaction(database) {
            NetworkNodesTable.selectAll().count()
        }
    }

    /**
     * Get all nodes
     */
    fun getAllNodes(): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .mapNotNull { it.toNetworkNode() }
        }
    }

    /**
     * Check if a node name exists
     */
    fun nodeNameExists(name: String): Boolean {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .where { NetworkNodesTable.nodeName eq name }
                .count() > 0
        }
    }

    /**
     * Calculate distance between two points (Euclidean distance)
     */
    private fun calculateDistance(x1: Int, y1: Int, x2: Int, y2: Int): Int {
        val dx = (x2 - x1).toDouble()
        val dy = (y2 - y1).toDouble()
        return sqrt(dx * dx + dy * dy).toInt()
    }

    /**
     * Convert ResultRow to NetworkNode
     */
    private fun ResultRow.toNetworkNode(): NetworkNode? {
        return try {
            NetworkNode(
                nodeId = this[NetworkNodesTable.nodeId],
                nodeName = this[NetworkNodesTable.nodeName],
                nodeType = NodeType.valueOf(this[NetworkNodesTable.nodeType]),
                district = null, // Load separately if needed
                coordX = this[NetworkNodesTable.coordX],
                coordY = this[NetworkNodesTable.coordY],
                ipAddress = this[NetworkNodesTable.ipAddress],
                signalStrength = this[NetworkNodesTable.signalStrength],
                securityLevel = this[NetworkNodesTable.securityLevel],
                isPublic = this[NetworkNodesTable.isPublic],
                isMissionCritical = this[NetworkNodesTable.isMissionCritical]
            )
        } catch (e: Exception) {
            println("[NodeRepository] Error converting row to NetworkNode: ${e.message}")
            null
        }
    }
}
