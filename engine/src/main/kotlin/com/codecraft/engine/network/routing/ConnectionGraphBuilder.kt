package com.codecraft.engine.network.routing

import com.codecraft.engine.network.domain.ConnectionType
import com.codecraft.engine.network.domain.NodeCategory
import com.codecraft.engine.network.domain.NodeConnection
import com.codecraft.engine.network.domain.NetworkNode
import java.util.UUID
import kotlin.math.sqrt
import kotlin.math.max
import kotlin.math.min

/**
 * Builds the connection graph topology from a list of nodes.
 * Pure builder — no DB dependency. Caller handles persistence.
 */
class ConnectionGraphBuilder {

    companion object {
        const val PUBLIC_PUBLIC_MAX_DISTANCE = 300
        const val PRIVATE_GATEWAY_MAX_DISTANCE = 500
        const val CORPORATE_INTRADISTRICT_MAX_DISTANCE = 400
    }

    /**
     * Build all connections for the given node list.
     * Returns deduplicated list — each node pair appears at most once.
     */
    fun buildConnections(nodes: List<NetworkNode>): List<NodeConnection> {
        val connections = mutableListOf<NodeConnection>()
        val connectedPairs = mutableSetOf<Pair<UUID, UUID>>()

        connections.addAll(buildPublicToPublicConnections(nodes, connectedPairs))
        connections.addAll(buildPrivateToGatewayConnections(nodes, connectedPairs))
        connections.addAll(buildCorporateIntraDistrictConnections(nodes, connectedPairs))

        return connections
    }

    /**
     * Rule 1: Public-to-public nodes within 300m get DIRECT connections
     */
    private fun buildPublicToPublicConnections(
        nodes: List<NetworkNode>,
        connectedPairs: MutableSet<Pair<UUID, UUID>>
    ): List<NodeConnection> {
        val publicNodes = nodes.filter { it.isPublic }
        val connections = mutableListOf<NodeConnection>()

        for (i in 0 until publicNodes.size) {
            for (j in i + 1 until publicNodes.size) {
                val nodeA = publicNodes[i]
                val nodeB = publicNodes[j]
                val distance = calculateDistance(nodeA.coordX, nodeA.coordY, nodeB.coordX, nodeB.coordY)

                if (distance <= PUBLIC_PUBLIC_MAX_DISTANCE) {
                    val quality = calculateConnectionQuality(nodeA.signalStrength, nodeB.signalStrength, distance)
                    val connection = NodeConnection(
                        id = UUID.randomUUID(),
                        nodeAId = nodeA.nodeId,
                        nodeBId = nodeB.nodeId,
                        distance = distance,
                        connectionQuality = quality,
                        connectionType = ConnectionType.DIRECT,
                        isPublic = true,
                        isBidirectional = true
                    )
                    connections.add(connection)
                    connectedPairs.add(orderedPair(nodeA.nodeId, nodeB.nodeId))
                }
            }
        }

        return connections
    }

    /**
     * Rule 2: Private node → nearest public gateway → RELAY if distance ≤ 500m
     * Each private node gets at most ONE relay (to closest qualifying gateway)
     */
    private fun buildPrivateToGatewayConnections(
        nodes: List<NetworkNode>,
        connectedPairs: MutableSet<Pair<UUID, UUID>>
    ): List<NodeConnection> {
        val privateNodes = nodes.filter { !it.isPublic }
        val publicNodes = nodes.filter { it.isPublic }
        val connections = mutableListOf<NodeConnection>()

        for (privateNode in privateNodes) {
            val nearest = publicNodes
                .filter { calculateDistance(privateNode.coordX, privateNode.coordY, it.coordX, it.coordY) <= PRIVATE_GATEWAY_MAX_DISTANCE }
                .minByOrNull { calculateDistance(privateNode.coordX, privateNode.coordY, it.coordX, it.coordY) }
                ?: continue

            val distance = calculateDistance(privateNode.coordX, privateNode.coordY, nearest.coordX, nearest.coordY)
            val quality = calculateConnectionQuality(privateNode.signalStrength, nearest.signalStrength, distance)
            val connection = NodeConnection(
                id = UUID.randomUUID(),
                nodeAId = privateNode.nodeId,
                nodeBId = nearest.nodeId,
                distance = distance,
                connectionQuality = quality,
                connectionType = ConnectionType.RELAY,
                isPublic = false,
                isBidirectional = true
            )
            connections.add(connection)
            connectedPairs.add(orderedPair(privateNode.nodeId, nearest.nodeId))
        }

        return connections
    }

    /**
     * Rule 3: Corporate nodes in the same district within 400m get ENCRYPTED connections
     * Skip if connection already exists (from Rule 1)
     */
    private fun buildCorporateIntraDistrictConnections(
        nodes: List<NetworkNode>,
        connectedPairs: MutableSet<Pair<UUID, UUID>>
    ): List<NodeConnection> {
        val corporateNodes = nodes.filter { it.nodeType.category == NodeCategory.CORPORATE }
        val connections = mutableListOf<NodeConnection>()

        for (i in 0 until corporateNodes.size) {
            for (j in i + 1 until corporateNodes.size) {
                val nodeA = corporateNodes[i]
                val nodeB = corporateNodes[j]

                // Skip if either node has no district, or districts differ
                if (nodeA.district == null || nodeB.district == null) continue
                if (nodeA.district.districtId != nodeB.district.districtId) continue

                val distance = calculateDistance(nodeA.coordX, nodeA.coordY, nodeB.coordX, nodeB.coordY)
                if (distance > CORPORATE_INTRADISTRICT_MAX_DISTANCE) continue

                val pair = orderedPair(nodeA.nodeId, nodeB.nodeId)
                if (pair in connectedPairs) continue

                val quality = calculateConnectionQuality(nodeA.signalStrength, nodeB.signalStrength, distance)
                val connection = NodeConnection(
                    id = UUID.randomUUID(),
                    nodeAId = nodeA.nodeId,
                    nodeBId = nodeB.nodeId,
                    distance = distance,
                    connectionQuality = quality,
                    connectionType = ConnectionType.ENCRYPTED,
                    isPublic = nodeA.isPublic && nodeB.isPublic,
                    isBidirectional = true
                )
                connections.add(connection)
                connectedPairs.add(pair)
            }
        }

        return connections
    }

    /**
     * Calculate connection quality based on signal strengths and distance.
     * quality = ((signalA + signalB) / 2) - (distance / 10), clamped 0..100
     */
    fun calculateConnectionQuality(signalA: Int, signalB: Int, distance: Int): Int {
        val raw = ((signalA + signalB) / 2) - (distance / 10)
        return max(0, min(100, raw))
    }

    /**
     * Calculate Euclidean distance between two points
     */
    private fun calculateDistance(x1: Int, y1: Int, x2: Int, y2: Int): Int {
        val dx = (x2 - x1).toDouble()
        val dy = (y2 - y1).toDouble()
        return sqrt(dx * dx + dy * dy).toInt()
    }

    /**
     * Returns a canonical ordered pair (smaller UUID first) for dedup tracking
     */
    private fun orderedPair(a: UUID, b: UUID): Pair<UUID, UUID> {
        return if (a < b) Pair(a, b) else Pair(b, a)
    }
}
