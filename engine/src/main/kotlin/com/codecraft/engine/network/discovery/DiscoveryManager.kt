package com.codecraft.engine.network.discovery

import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.DiscoveryRepository
import com.codecraft.engine.network.persistence.NodeRepository
import java.util.UUID

/**
 * Manages network node discovery for players (fog of war system)
 */
class DiscoveryManager(
    private val discoveryRepository: DiscoveryRepository,
    private val nodeRepository: NodeRepository
) {

    /**
     * Mark a node as discovered by a player
     */
    fun discoverNode(playerId: String, node: NetworkNode) {
        val currentState = getDiscoveryState(playerId, node.nodeId)

        if (currentState == null) {
            // First discovery
            discoveryRepository.recordDiscovery(playerId, node.nodeId, DiscoveryState.DISCOVERED)
        }
        // If already discovered, don't change state (might be CONNECTED, COMPROMISED, etc.)
    }

    /**
     * Mark a node as discovered by node ID
     */
    fun discoverNodeById(playerId: String, nodeId: UUID) {
        val node = nodeRepository.getNodeById(nodeId) ?: return
        discoverNode(playerId, node)
    }

    /**
     * Update discovery state (e.g., DISCOVERED → CONNECTED)
     */
    fun updateState(playerId: String, nodeId: UUID, newState: DiscoveryState) {
        discoveryRepository.updateDiscoveryState(playerId, nodeId, newState)
    }

    /**
     * Get current discovery state for a node
     */
    fun getDiscoveryState(playerId: String, nodeId: UUID): DiscoveryState? {
        return discoveryRepository.getDiscoveryState(playerId, nodeId)
    }

    /**
     * Check if a node is discovered
     */
    fun isDiscovered(playerId: String, nodeId: UUID): Boolean {
        return discoveryRepository.isDiscovered(playerId, nodeId)
    }

    /**
     * Get all discovered nodes with their states
     */
    fun getDiscoveredNodes(playerId: String): List<Pair<NetworkNode, DiscoveryState>> {
        val discoveries = discoveryRepository.getPlayerDiscoveries(playerId)

        return discoveries.mapNotNull { (nodeId, state) ->
            nodeRepository.getNodeById(nodeId)?.let { node ->
                node to state
            }
        }
    }

    /**
     * Get nodes in a specific discovery state
     */
    fun getNodesByState(playerId: String, state: DiscoveryState): List<NetworkNode> {
        val nodeIds = discoveryRepository.getNodesByState(playerId, state)
        return nodeIds.mapNotNull { nodeRepository.getNodeById(it) }
    }

    /**
     * Get discovery statistics
     */
    fun getDiscoveryStats(playerId: String): DiscoveryStats {
        val total = discoveryRepository.getDiscoveryCount(playerId)
        val discovered = discoveryRepository.getDiscoveryCountByState(playerId, DiscoveryState.DISCOVERED)
        val connected = discoveryRepository.getDiscoveryCountByState(playerId, DiscoveryState.CONNECTED)
        val compromised = discoveryRepository.getDiscoveryCountByState(playerId, DiscoveryState.COMPROMISED)
        val locked = discoveryRepository.getDiscoveryCountByState(playerId, DiscoveryState.LOCKED)

        return DiscoveryStats(
            total = total,
            discovered = discovered,
            connected = connected,
            compromised = compromised,
            locked = locked
        )
    }

    /**
     * Clear all discoveries for a player (for testing/reset)
     */
    fun clearDiscoveries(playerId: String) {
        discoveryRepository.clearPlayerDiscoveries(playerId)
    }
}

/**
 * Discovery statistics for a player
 */
data class DiscoveryStats(
    val total: Long,
    val discovered: Long,
    val connected: Long,
    val compromised: Long,
    val locked: Long
)
