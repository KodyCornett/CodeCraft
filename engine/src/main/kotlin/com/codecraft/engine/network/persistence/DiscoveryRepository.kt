package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.PlayerDiscoveredNodesTable
import com.codecraft.engine.network.domain.DiscoveryState
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID

/**
 * Repository for player discovery state management
 */
class DiscoveryRepository(private val database: Database) {

    /**
     * Record a node discovery for a player
     */
    fun recordDiscovery(
        playerId: String,
        nodeId: UUID,
        state: DiscoveryState
    ) {
        transaction(database) {
            PlayerDiscoveredNodesTable.insert {
                it[id] = UUID.randomUUID()
                it[PlayerDiscoveredNodesTable.playerId] = playerId
                it[PlayerDiscoveredNodesTable.nodeId] = nodeId
                it[discoveryState] = state.name
                it[discoveredAt] = System.currentTimeMillis()
            }
        }
    }

    /**
     * Update discovery state for a node
     */
    fun updateDiscoveryState(
        playerId: String,
        nodeId: UUID,
        newState: DiscoveryState
    ) {
        transaction(database) {
            val existing = PlayerDiscoveredNodesTable.selectAll()
                .where {
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.nodeId eq nodeId)
                }.singleOrNull()

            if (existing != null) {
                // Update existing record
                val currentCount = existing[PlayerDiscoveredNodesTable.accessCount]
                PlayerDiscoveredNodesTable.update({
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.nodeId eq nodeId)
                }) {
                    it[discoveryState] = newState.name
                    it[lastAccessed] = System.currentTimeMillis()
                    it[accessCount] = currentCount + 1
                }
            } else {
                // Insert new record
                recordDiscovery(playerId, nodeId, newState)
            }
        }
    }

    /**
     * Get discovery state for a specific node
     */
    fun getDiscoveryState(playerId: String, nodeId: UUID): DiscoveryState? {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where {
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.nodeId eq nodeId)
                }
                .map { DiscoveryState.valueOf(it[PlayerDiscoveredNodesTable.discoveryState]) }
                .singleOrNull()
        }
    }

    /**
     * Get all discovered nodes for a player
     */
    fun getPlayerDiscoveries(playerId: String): List<Pair<UUID, DiscoveryState>> {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where { PlayerDiscoveredNodesTable.playerId eq playerId }
                .map {
                    it[PlayerDiscoveredNodesTable.nodeId] to
                            DiscoveryState.valueOf(it[PlayerDiscoveredNodesTable.discoveryState])
                }
        }
    }

    /**
     * Get nodes in a specific discovery state
     */
    fun getNodesByState(playerId: String, state: DiscoveryState): List<UUID> {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where {
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.discoveryState eq state.name)
                }
                .map { it[PlayerDiscoveredNodesTable.nodeId] }
        }
    }

    /**
     * Check if a node is discovered by player
     */
    fun isDiscovered(playerId: String, nodeId: UUID): Boolean {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where {
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.nodeId eq nodeId)
                }.count() > 0
        }
    }

    /**
     * Get discovery count for a player
     */
    fun getDiscoveryCount(playerId: String): Long {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where { PlayerDiscoveredNodesTable.playerId eq playerId }
                .count()
        }
    }

    /**
     * Get discovery count by state
     */
    fun getDiscoveryCountByState(playerId: String, state: DiscoveryState): Long {
        return transaction(database) {
            PlayerDiscoveredNodesTable.selectAll()
                .where {
                    (PlayerDiscoveredNodesTable.playerId eq playerId) and
                            (PlayerDiscoveredNodesTable.discoveryState eq state.name)
                }
                .count()
        }
    }

    /**
     * Add player notes to a discovered node
     */
    fun addNotes(playerId: String, nodeId: UUID, notes: String) {
        transaction(database) {
            PlayerDiscoveredNodesTable.update({
                (PlayerDiscoveredNodesTable.playerId eq playerId) and
                        (PlayerDiscoveredNodesTable.nodeId eq nodeId)
            }) {
                it[playerNotes] = notes
            }
        }
    }

    /**
     * Delete all discoveries for a player (for testing/reset)
     */
    fun clearPlayerDiscoveries(playerId: String) {
        transaction(database) {
            PlayerDiscoveredNodesTable.deleteWhere {
                PlayerDiscoveredNodesTable.playerId eq playerId
            }
        }
    }
}
