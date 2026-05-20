package com.codecraft.engine.network.generation

import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.Districts
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository

/**
 * Assigns a starting network position to a new player.
 * Prefers a public node in Downtown; falls back to University Campus, then any public node.
 * Idempotent — does nothing if the player already has a position.
 */
class PlayerSpawnService(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager,
    private val positionRepository: PositionRepository
) {
    companion object {
        /** Radius (metres) for auto-discovering nearby nodes at spawn */
        const val SPAWN_DISCOVERY_RADIUS = 300
        /** Maximum additional nodes to reveal at spawn (beyond the spawn node itself) */
        const val MAX_INITIAL_DISCOVERIES = 3
    }

    /**
     * Assign a spawn position for the player if they don't have one yet.
     * Discovers the spawn node (CONNECTED) and up to MAX_INITIAL_DISCOVERIES nearby public nodes.
     */
    fun spawnPlayer(playerId: String) {
        if (positionRepository.hasPosition(playerId)) {
            return  // Already spawned — idempotent
        }

        val spawnNode = findSpawnNode() ?: run {
            println("[PlayerSpawn] No suitable spawn node found for $playerId — network may be empty")
            return
        }

        // Set network position
        positionRepository.updatePosition(playerId, spawnNode.nodeId)

        // Discover the spawn node as immediately CONNECTED
        discoveryManager.discoverNode(playerId, spawnNode)
        discoveryManager.updateState(playerId, spawnNode.nodeId, DiscoveryState.CONNECTED)

        // Auto-discover nearby public nodes (gives the player an initial cluster to explore)
        val nearby = nodeRepository
            .getNodesInRadius(spawnNode.coordX, spawnNode.coordY, SPAWN_DISCOVERY_RADIUS)
            .filter { it.isPublic && it.nodeId != spawnNode.nodeId }
            .take(MAX_INITIAL_DISCOVERIES)

        for (node in nearby) {
            discoveryManager.discoverNode(playerId, node)
        }

        println("[PlayerSpawn] Spawned $playerId at '${spawnNode.nodeName}' (+${nearby.size} nearby nodes discovered)")
    }

    /**
     * Choose the best spawn node:
     * 1. A random public node in Downtown (starter district, highest density)
     * 2. A random public node in University Campus (also unlocked from start)
     * 3. Any public node in the network
     */
    private fun findSpawnNode(): NetworkNode? {
        val downtown = nodeRepository
            .getNodesByDistrict(Districts.DOWNTOWN.districtId)
            .filter { it.isPublic }
        if (downtown.isNotEmpty()) return downtown.random()

        val university = nodeRepository
            .getNodesByDistrict(Districts.UNIVERSITY_CAMPUS.districtId)
            .filter { it.isPublic }
        if (university.isNotEmpty()) return university.random()

        return nodeRepository.getPublicNodes().firstOrNull()
    }
}
