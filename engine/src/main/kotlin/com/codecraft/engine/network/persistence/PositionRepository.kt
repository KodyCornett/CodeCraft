package com.codecraft.engine.network.persistence

import com.codecraft.engine.database.tables.PlayerNetworkPositionTable
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID

/**
 * Repository for tracking player's position in the network
 */
class PositionRepository(private val database: Database) {

    /**
     * Update player's current position
     */
    fun updatePosition(
        playerId: String,
        currentNodeId: UUID?,
        previousNodeId: UUID? = null
    ) {
        transaction(database) {
            val existing = PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .singleOrNull()

            if (existing != null) {
                // Update existing position
                PlayerNetworkPositionTable.update({
                    PlayerNetworkPositionTable.playerId eq playerId
                }) {
                    it[PlayerNetworkPositionTable.currentNodeId] = currentNodeId
                    it[PlayerNetworkPositionTable.previousNodeId] = previousNodeId
                    it[lastPositionUpdate] = System.currentTimeMillis()
                }
            } else {
                // Insert new position record
                PlayerNetworkPositionTable.insert {
                    it[PlayerNetworkPositionTable.playerId] = playerId
                    it[PlayerNetworkPositionTable.currentNodeId] = currentNodeId
                    it[PlayerNetworkPositionTable.previousNodeId] = previousNodeId
                    it[lastPositionUpdate] = System.currentTimeMillis()
                }
            }
        }
    }

    /**
     * Get player's current position
     */
    fun getCurrentPosition(playerId: String): UUID? {
        return transaction(database) {
            PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .map { it[PlayerNetworkPositionTable.currentNodeId] }
                .singleOrNull()
        }
    }

    /**
     * Get player's previous position
     */
    fun getPreviousPosition(playerId: String): UUID? {
        return transaction(database) {
            PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .map { it[PlayerNetworkPositionTable.previousNodeId] }
                .singleOrNull()
        }
    }

    /**
     * Get both current and previous positions
     */
    fun getPositions(playerId: String): Pair<UUID?, UUID?> {
        return transaction(database) {
            PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .map {
                    it[PlayerNetworkPositionTable.currentNodeId] to
                            it[PlayerNetworkPositionTable.previousNodeId]
                }
                .singleOrNull() ?: (null to null)
        }
    }

    /**
     * Clear player's position (disconnect)
     */
    fun clearPosition(playerId: String) {
        transaction(database) {
            PlayerNetworkPositionTable.update({
                PlayerNetworkPositionTable.playerId eq playerId
            }) {
                it[currentNodeId] = null
                it[lastPositionUpdate] = System.currentTimeMillis()
            }
        }
    }

    /**
     * Check if player has a position recorded
     */
    fun hasPosition(playerId: String): Boolean {
        return transaction(database) {
            PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .count() > 0
        }
    }

    /**
     * Get last position update timestamp
     */
    fun getLastUpdateTime(playerId: String): Long? {
        return transaction(database) {
            PlayerNetworkPositionTable.selectAll()
                .where { PlayerNetworkPositionTable.playerId eq playerId }
                .map { it[PlayerNetworkPositionTable.lastPositionUpdate] }
                .singleOrNull()
        }
    }

    /**
     * Delete position record for a player (for testing/reset)
     */
    fun deletePosition(playerId: String) {
        transaction(database) {
            PlayerNetworkPositionTable.deleteWhere {
                PlayerNetworkPositionTable.playerId eq playerId
            }
        }
    }
}
