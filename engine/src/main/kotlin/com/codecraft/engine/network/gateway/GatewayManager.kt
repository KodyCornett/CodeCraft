package com.codecraft.engine.network.gateway

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.discovery.DiscoveryManager

/**
 * Manages gateway activation for public access nodes
 * Gateways auto-reveal nearby nodes when player connects to them
 */
class GatewayManager(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager
) {

    companion object {
        const val DEFAULT_REVEAL_RADIUS = 500
        const val MIN_SIGNAL_FOR_AUTO_REVEAL = 70
    }

    /**
     * Result of gateway activation
     */
    data class GatewayActivationResult(
        val isGateway: Boolean,
        val autoRevealedCount: Int,
        val autoRevealedNodes: List<NetworkNode>,
        val totalNearbyCount: Int
    )

    /**
     * When player connects to a gateway node, reveal nearby nodes
     *
     * @param playerId Player ID
     * @param gatewayNode Node being connected to
     * @param revealRadius Optional custom radius (default: 500m)
     * @return Activation result with revealed node count
     */
    fun activateGateway(
        playerId: String,
        gatewayNode: NetworkNode,
        revealRadius: Int = DEFAULT_REVEAL_RADIUS
    ): GatewayActivationResult {
        // Only public nodes are gateways
        if (!gatewayNode.isPublic) {
            return GatewayActivationResult(
                isGateway = false,
                autoRevealedCount = 0,
                autoRevealedNodes = emptyList(),
                totalNearbyCount = 0
            )
        }

        // Get all nodes within reveal radius
        val nearbyNodes = nodeRepository.getNodesInRadius(
            x = gatewayNode.coordX,
            y = gatewayNode.coordY,
            radius = revealRadius
        )

        // Filter to undiscovered nodes
        val undiscoveredNodes = nearbyNodes.filter { node ->
            !discoveryManager.isDiscovered(playerId, node.nodeId) &&
            node.nodeId != gatewayNode.nodeId
        }

        // Auto-reveal public nodes with strong signal
        val autoRevealedNodes = undiscoveredNodes.filter { node ->
            node.isPublic && node.signalStrength >= MIN_SIGNAL_FOR_AUTO_REVEAL
        }

        // Mark as discovered
        autoRevealedNodes.forEach { node ->
            discoveryManager.discoverNode(playerId, node)
        }

        return GatewayActivationResult(
            isGateway = true,
            autoRevealedCount = autoRevealedNodes.size,
            autoRevealedNodes = autoRevealedNodes,
            totalNearbyCount = nearbyNodes.size
        )
    }

    /**
     * Get all nodes visible from a gateway without activating discovery
     * Used for preview/analysis
     */
    fun getVisibleNodesFromGateway(
        gatewayNode: NetworkNode,
        revealRadius: Int = DEFAULT_REVEAL_RADIUS
    ): List<NetworkNode> {
        if (!gatewayNode.isPublic) {
            return emptyList()
        }

        return nodeRepository.getNodesInRadius(
            x = gatewayNode.coordX,
            y = gatewayNode.coordY,
            radius = revealRadius
        ).filter {
            it.nodeId != gatewayNode.nodeId &&
            it.isPublic &&
            it.signalStrength >= MIN_SIGNAL_FOR_AUTO_REVEAL
        }
    }

    /**
     * Check if a node qualifies as a gateway
     */
    fun isGateway(node: NetworkNode): Boolean {
        return node.isPublic
    }

    /**
     * Get gateway reveal radius based on gateway's signal strength
     * Stronger gateways reveal more area
     */
    fun calculateRevealRadius(gatewayNode: NetworkNode): Int {
        if (!gatewayNode.isPublic) return 0

        // Base radius scales with signal strength
        // 70% signal = 350m, 100% signal = 500m
        val signalRatio = gatewayNode.signalStrength / 100.0
        return (DEFAULT_REVEAL_RADIUS * signalRatio).toInt().coerceAtLeast(300)
    }

    /**
     * Get statistics about gateway network coverage
     */
    fun getGatewayStats(playerId: String, gateways: List<NetworkNode>): GatewayStats {
        var totalAutoRevealed = 0
        var totalCoverage = 0

        gateways.filter { it.isPublic }.forEach { gateway ->
            val visible = getVisibleNodesFromGateway(gateway)
            val alreadyDiscovered = visible.count {
                discoveryManager.isDiscovered(playerId, it.nodeId)
            }
            totalAutoRevealed += alreadyDiscovered
            totalCoverage += visible.size
        }

        return GatewayStats(
            gatewayCount = gateways.count { it.isPublic },
            totalCoverage = totalCoverage,
            discoveredThroughGateways = totalAutoRevealed
        )
    }

    data class GatewayStats(
        val gatewayCount: Int,
        val totalCoverage: Int,
        val discoveredThroughGateways: Int
    )
}
