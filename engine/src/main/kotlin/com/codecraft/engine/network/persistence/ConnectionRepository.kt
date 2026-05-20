package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.NodeConnectionsTable
import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.NodeConnection
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID

/**
 * Repository for node connection CRUD operations
 */
class ConnectionRepository(private val database: Database) {

    /**
     * Save a single connection to the database
     */
    fun saveConnection(connection: NodeConnection): NodeConnection {
        transaction(database) {
            NodeConnectionsTable.insert {
                it[id] = connection.id
                it[nodeAId] = connection.nodeAId
                it[nodeBId] = connection.nodeBId
                it[distance] = connection.distance
                it[connectionQuality] = connection.connectionQuality
                it[connectionType] = connection.connectionType.name
                it[isPublic] = connection.isPublic
                it[isBidirectional] = connection.isBidirectional
            }
        }
        return connection
    }

    /**
     * Save multiple connections in a single transaction (efficient batch)
     */
    fun saveConnections(connections: List<NodeConnection>) {
        transaction(database) {
            for (connection in connections) {
                NodeConnectionsTable.insert {
                    it[id] = connection.id
                    it[nodeAId] = connection.nodeAId
                    it[nodeBId] = connection.nodeBId
                    it[distance] = connection.distance
                    it[connectionQuality] = connection.connectionQuality
                    it[connectionType] = connection.connectionType.name
                    it[isPublic] = connection.isPublic
                    it[isBidirectional] = connection.isBidirectional
                }
            }
        }
    }

    /**
     * Get all connections for a node (either side)
     */
    fun getConnectionsForNode(nodeId: UUID): List<NodeConnection> {
        return transaction(database) {
            NodeConnectionsTable.selectAll()
                .where { (NodeConnectionsTable.nodeAId eq nodeId) or (NodeConnectionsTable.nodeBId eq nodeId) }
                .mapNotNull { it.toNodeConnection() }
        }
    }

    /**
     * Get connection between two specific nodes (order-independent)
     */
    fun getConnection(nodeAId: UUID, nodeBId: UUID): NodeConnection? {
        return transaction(database) {
            NodeConnectionsTable.selectAll()
                .where {
                    ((NodeConnectionsTable.nodeAId eq nodeAId) and (NodeConnectionsTable.nodeBId eq nodeBId)) or
                    ((NodeConnectionsTable.nodeAId eq nodeBId) and (NodeConnectionsTable.nodeBId eq nodeAId))
                }
                .mapNotNull { it.toNodeConnection() }
                .singleOrNull()
        }
    }

    /**
     * Get all connections
     */
    fun getAllConnections(): List<NodeConnection> {
        return transaction(database) {
            NodeConnectionsTable.selectAll()
                .mapNotNull { it.toNodeConnection() }
        }
    }

    /**
     * Get connections filtered by type
     */
    fun getConnectionsByType(type: ConnectionType): List<NodeConnection> {
        return transaction(database) {
            NodeConnectionsTable.selectAll()
                .where { NodeConnectionsTable.connectionType eq type.name }
                .mapNotNull { it.toNodeConnection() }
        }
    }

    /**
     * Delete all connections involving a specific node (cleanup on node removal)
     */
    fun deleteConnectionsForNode(nodeId: UUID) {
        transaction(database) {
            NodeConnectionsTable.deleteWhere {
                (nodeAId eq nodeId) or (nodeBId eq nodeId)
            }
        }
    }

    /**
     * Delete all connections (for graph regeneration idempotency)
     */
    fun deleteAllConnections() {
        transaction(database) {
            NodeConnectionsTable.deleteAll()
        }
    }

    /**
     * Count total connections
     */
    fun countConnections(): Long {
        return transaction(database) {
            NodeConnectionsTable.selectAll().count()
        }
    }

    /**
     * Check if a connection exists between two nodes (order-independent)
     */
    fun connectionExists(nodeAId: UUID, nodeBId: UUID): Boolean {
        return transaction(database) {
            NodeConnectionsTable.selectAll()
                .where {
                    ((NodeConnectionsTable.nodeAId eq nodeAId) and (NodeConnectionsTable.nodeBId eq nodeBId)) or
                    ((NodeConnectionsTable.nodeAId eq nodeBId) and (NodeConnectionsTable.nodeBId eq nodeAId))
                }
                .count() > 0
        }
    }

    /**
     * Convert ResultRow to NodeConnection
     */
    private fun ResultRow.toNodeConnection(): NodeConnection? {
        return try {
            NodeConnection(
                id = this[NodeConnectionsTable.id],
                nodeAId = this[NodeConnectionsTable.nodeAId],
                nodeBId = this[NodeConnectionsTable.nodeBId],
                distance = this[NodeConnectionsTable.distance],
                connectionQuality = this[NodeConnectionsTable.connectionQuality],
                connectionType = ConnectionType.valueOf(this[NodeConnectionsTable.connectionType]),
                isPublic = this[NodeConnectionsTable.isPublic],
                isBidirectional = this[NodeConnectionsTable.isBidirectional]
            )
        } catch (e: Exception) {
            println("[ConnectionRepository] Error converting row to NodeConnection: ${e.message}")
            null
        }
    }
}
