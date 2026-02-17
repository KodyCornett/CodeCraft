package com.codecraft.engine.network.generation

import com.codecraft.engine.network.domain.Districts
import com.codecraft.engine.network.naming.NodeNameGenerator
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.DistrictRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.routing.ConnectionGraphBuilder

/**
 * Bootstraps the city network on first engine startup.
 * Idempotent — skips if nodes already exist in the database.
 */
class WorldBootstrap(
    private val districtRepository: DistrictRepository,
    private val nodeRepository: NodeRepository,
    private val connectionRepository: ConnectionRepository
) {
    private val districtGenerator = DistrictGenerator(NodeNameGenerator())
    private val connectionGraphBuilder = ConnectionGraphBuilder()

    /**
     * Populate the world if the network tables are empty.
     * Safe to call on every startup — exits immediately if data exists.
     */
    fun bootstrapIfEmpty() {
        if (nodeRepository.count() > 0) {
            println("[WorldBootstrap] Network already populated (${nodeRepository.count()} nodes). Skipping.")
            return
        }

        println("[WorldBootstrap] Empty network detected. Bootstrapping city...")

        // 1. Persist all predefined districts
        if (districtRepository.count() == 0L) {
            val districts = Districts.getAll()
            for (district in districts) {
                districtRepository.saveDistrict(district)
            }
            println("[WorldBootstrap] Seeded ${districts.size} districts")
        }

        // 2. Generate nodes for every district using DistrictGenerator
        val allNodes = mutableListOf<com.codecraft.engine.network.domain.NetworkNode>()
        for (district in Districts.getAll()) {
            val nodes = districtGenerator.generateDistrict(district)
            for (node in nodes) {
                nodeRepository.saveNode(node)
            }
            allNodes.addAll(nodes)
        }
        println("[WorldBootstrap] Generated ${allNodes.size} nodes across ${Districts.getAll().size} districts")

        // 3. Build full connection graph and persist
        val connections = connectionGraphBuilder.buildConnections(allNodes)
        connectionRepository.saveConnections(connections)
        println("[WorldBootstrap] Built ${connections.size} connections")

        println("[WorldBootstrap] City bootstrap complete! ${allNodes.size} nodes, ${connections.size} connections")
    }
}
