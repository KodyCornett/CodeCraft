package com.codecraft.engine.map

import com.codecraft.engine.api.LaravelApiClient
import com.codecraft.engine.models.NodeState
import org.slf4j.LoggerFactory
import java.util.concurrent.ConcurrentHashMap

/**
 * Loads the node adjacency list from Laravel on startup and caches it in memory.
 *
 * The engine never writes to this map. It is a read-only cache of the world graph.
 * Call [loadDistrict] for each district at startup, or [loadAllDistricts] with the
 * known district list.
 */
class MapManager(private val laravelClient: LaravelApiClient) {

    private val log = LoggerFactory.getLogger(MapManager::class.java)

    // internal so tests can inject nodes without going through LaravelApiClient
    internal val nodeIndex = ConcurrentHashMap<String, NodeState>()
    internal val districtIndex = ConcurrentHashMap<String, List<String>>()

    /** Load all nodes for a single district from Laravel. */
    suspend fun loadDistrict(district: String) {
        val nodes = laravelClient.getNodes(district)
        nodes.forEach { node -> nodeIndex[node.id] = node }
        districtIndex[district] = nodes.map { it.id }
        log.info("MapManager loaded district='$district' (${nodes.size} nodes)")
    }

    /** Convenience: load multiple districts in sequence. */
    suspend fun loadAllDistricts(districts: List<String>) {
        districts.forEach { loadDistrict(it) }
        log.info("MapManager ready — ${nodeIndex.size} total nodes across ${districtIndex.size} districts")
    }

    fun isAdjacent(fromNodeId: String, toNodeId: String): Boolean {
        val node = nodeIndex[fromNodeId] ?: return false
        return toNodeId in node.adjacentNodeIds
    }

    fun getAdjacentNodes(nodeId: String): List<String> =
        nodeIndex[nodeId]?.adjacentNodeIds ?: emptyList()

    fun getNode(nodeId: String): NodeState? = nodeIndex[nodeId]

    fun getNodesInDistrict(district: String): List<NodeState> =
        districtIndex[district]?.mapNotNull { nodeIndex[it] } ?: emptyList()

    /** Looks up which district a node belongs to. */
    fun getDistrictForNode(nodeId: String): String? = nodeIndex[nodeId]?.district
}
