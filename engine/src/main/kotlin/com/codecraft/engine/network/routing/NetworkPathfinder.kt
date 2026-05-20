package com.codecraft.engine.network.routing

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
import java.util.UUID

data class RouteHop(
    val node: NetworkNode,
    val connection: NodeConnection?,  // null for start node
    val hopNumber: Int
)

data class PathResult(
    val hops: List<RouteHop>,
    val totalDistance: Int,
    val totalHops: Int,
    val averageQuality: Int
)

/**
 * Pure BFS pathfinder over the network connection graph.
 * No DB dependency — caller pre-fetches all required data.
 */
class NetworkPathfinder {

    /**
     * Find the shortest path (fewest hops) between two nodes.
     *
     * @param startNodeId   UUID of the player's current node
     * @param targetNodeId  UUID of the destination node
     * @param allConnections Full edge list from ConnectionRepository
     * @param nodeMap       Pre-built map of UUID → NetworkNode
     * @param allowedNodeIds Only traverse nodes in this set (discovered by player)
     * @return PathResult if a path exists, null otherwise
     */
    fun findPath(
        startNodeId: UUID,
        targetNodeId: UUID,
        allConnections: List<NodeConnection>,
        nodeMap: Map<UUID, NetworkNode>,
        allowedNodeIds: Set<UUID>
    ): PathResult? {
        // Edge case: already at target
        if (startNodeId == targetNodeId) {
            val startNode = nodeMap[startNodeId] ?: return null
            return PathResult(
                hops = listOf(RouteHop(startNode, connection = null, hopNumber = 0)),
                totalDistance = 0,
                totalHops = 0,
                averageQuality = 100
            )
        }

        // Both endpoints must be discoverable and present in the node map
        if (startNodeId !in allowedNodeIds || startNodeId !in nodeMap) return null
        if (targetNodeId !in allowedNodeIds || targetNodeId !in nodeMap) return null

        // Build bidirectional adjacency map
        val adjacency = mutableMapOf<UUID, MutableList<Pair<UUID, NodeConnection>>>()
        for (conn in allConnections) {
            adjacency.getOrPut(conn.nodeAId) { mutableListOf() }.add(conn.nodeBId to conn)
            if (conn.isBidirectional) {
                adjacency.getOrPut(conn.nodeBId) { mutableListOf() }.add(conn.nodeAId to conn)
            }
        }

        // BFS: queue holds full path (list of hops) to current node
        val startNode = nodeMap[startNodeId] ?: return null
        val startHop = RouteHop(startNode, connection = null, hopNumber = 0)
        val queue = ArrayDeque<List<RouteHop>>()
        queue.addLast(listOf(startHop))
        val visited = mutableSetOf(startNodeId)

        while (queue.isNotEmpty()) {
            val path = queue.removeFirst()
            val currentNodeId = path.last().node.nodeId
            val neighbours = adjacency[currentNodeId] ?: continue

            for ((neighbourId, connection) in neighbours) {
                if (neighbourId !in allowedNodeIds) continue
                if (neighbourId in visited) continue

                val neighbourNode = nodeMap[neighbourId] ?: continue
                val newHop = RouteHop(neighbourNode, connection, path.size)
                val newPath = path + newHop

                if (neighbourId == targetNodeId) {
                    return buildPathResult(newPath)
                }

                visited.add(neighbourId)
                queue.addLast(newPath)
            }
        }

        return null
    }

    private fun buildPathResult(hops: List<RouteHop>): PathResult {
        val totalDistance = hops.sumOf { it.connection?.distance ?: 0 }
        val totalHops = hops.size - 1
        return PathResult(
            hops = hops,
            totalDistance = totalDistance,
            totalHops = totalHops,
            averageQuality = computeAverageQuality(hops)
        )
    }

    fun computeAverageQuality(hops: List<RouteHop>): Int {
        val connections = hops.mapNotNull { it.connection }
        if (connections.isEmpty()) return 100
        return connections.sumOf { it.connectionQuality } / connections.size
    }
}
