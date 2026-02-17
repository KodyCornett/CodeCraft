package com.codecraft.engine.network.discovery

import com.codecraft.engine.network.domain.NetworkNode
import java.util.UUID
import kotlin.math.sqrt
import kotlin.random.Random

/**
 * Service for scanning and detecting network nodes
 */
class ScanService {

    /**
     * Scan for nodes around a position
     *
     * @param allNodes All nodes in the network
     * @param centerX Center X coordinate
     * @param centerY Center Y coordinate
     * @param scanRange Maximum scan distance
     * @param scannerPower Scanner power level (0-100)
     * @param alreadyDiscovered Node IDs already discovered by player
     * @return List of newly detected nodes
     */
    fun scanForNodes(
        allNodes: List<NetworkNode>,
        centerX: Int,
        centerY: Int,
        scanRange: Int,
        scannerPower: Int = 50,
        alreadyDiscovered: Set<UUID> = emptySet()
    ): ScanResult {
        // Find nodes within range
        val nodesInRange = allNodes.filter { node ->
            val distance = calculateDistance(centerX, centerY, node.coordX, node.coordY)
            distance <= scanRange
        }

        // Filter out already discovered nodes
        val undiscoveredNodes = nodesInRange.filter { node ->
            !alreadyDiscovered.contains(node.nodeId)
        }

        // Attempt detection with probability
        val detectedNodes = undiscoveredNodes.filter { node ->
            val distance = calculateDistance(centerX, centerY, node.coordX, node.coordY)
            val detectionChance = calculateDetectionChance(node, scannerPower, distance, scanRange)
            Random.nextInt(100) < detectionChance
        }

        // Get already discovered nodes in range for context
        val knownNodesInRange = nodesInRange.filter { node ->
            alreadyDiscovered.contains(node.nodeId)
        }

        return ScanResult(
            newlyDetected = detectedNodes,
            knownInRange = knownNodesInRange,
            totalInRange = nodesInRange.size,
            scanRange = scanRange
        )
    }

    /**
     * Calculate detection probability for a node
     *
     * @param node The node to detect
     * @param scannerPower Scanner power (0-100)
     * @param distance Distance to node
     * @param maxRange Maximum scan range
     * @return Detection chance percentage (0-100)
     */
    fun calculateDetectionChance(
        node: NetworkNode,
        scannerPower: Int,
        distance: Int,
        maxRange: Int
    ): Int {
        // Base chance decreases with distance
        val distanceRatio = distance.toDouble() / maxRange
        val baseChance = 100 - (distanceRatio * 50).toInt() // -50% at max range

        // Signal strength bonus
        val signalBonus = node.signalStrength / 5 // +20% for 100 signal

        // Scanner power bonus
        val scannerBonus = scannerPower / 5 // +20% for 100 power

        // Stealth penalty for non-public nodes
        val stealthPenalty = if (node.isPublic) 0 else 30

        val totalChance = baseChance + signalBonus + scannerBonus - stealthPenalty

        return totalChance.coerceIn(10, 100) // Min 10%, max 100%
    }

    /**
     * Calculate Euclidean distance between two points
     */
    private fun calculateDistance(x1: Int, y1: Int, x2: Int, y2: Int): Int {
        val dx = (x2 - x1).toDouble()
        val dy = (y2 - y1).toDouble()
        return sqrt(dx * dx + dy * dy).toInt()
    }
}

/**
 * Result of a network scan
 */
data class ScanResult(
    val newlyDetected: List<NetworkNode>,
    val knownInRange: List<NetworkNode>,
    val totalInRange: Int,
    val scanRange: Int
)
