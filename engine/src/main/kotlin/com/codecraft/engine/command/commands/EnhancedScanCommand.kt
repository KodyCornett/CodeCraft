package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandCategory
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.discovery.ScanResult
import com.codecraft.engine.network.discovery.ScanService
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeCategory
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.session.GameSession
import kotlin.math.sqrt

/**
 * Enhanced scan command with discovery system integration
 * Works with the new NetworkNode system (parallel to old Node system)
 */
class EnhancedScanCommand(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager,
    private val scanService: ScanService,
    private val positionRepository: PositionRepository
) : Command {

    override val name = "nscan"
    override val description = "Scan for nearby network nodes (new network system)"
    override val usage = "nscan [range]"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        // Parse custom range if provided
        val customRange = args.firstOrNull()?.toIntOrNull()
        val scanRange = customRange ?: getScannerRange(session)

        // Get player's current position in the network
        val currentNodeId = positionRepository.getCurrentPosition(session.player.id)
        if (currentNodeId == null) {
            return CommandResult(
                output = "ERROR: Cannot scan. Not connected to the network.\n" +
                        "You need to connect to a network node first using the new network system.",
                success = false
            )
        }

        val currentNode = nodeRepository.getNodeById(currentNodeId)
        if (currentNode == null) {
            return CommandResult(
                output = "ERROR: Current position node not found in network.",
                success = false
            )
        }

        // Get all nodes in the network
        val allNodes = nodeRepository.getAllNodes()

        // Get already discovered node IDs
        val alreadyDiscovered = discoveryManager.getDiscoveredNodes(session.player.id)
            .map { it.first.nodeId }
            .toSet()

        // Perform scan
        val scanResult = scanService.scanForNodes(
            allNodes = allNodes,
            centerX = currentNode.coordX,
            centerY = currentNode.coordY,
            scanRange = scanRange,
            scannerPower = getScannerPower(session),
            alreadyDiscovered = alreadyDiscovered
        )

        // Mark newly detected nodes as discovered
        scanResult.newlyDetected.forEach { node ->
            discoveryManager.discoverNode(session.player.id, node)
        }

        // Build output
        val output = buildScanOutput(
            currentNode = currentNode,
            scanResult = scanResult,
            session = session
        )

        return CommandResult(
            output = output,
            success = true,
            exposureChange = 2.0 // Scanning generates small exposure
        )
    }

    /**
     * Build formatted scan output
     */
    private fun buildScanOutput(
        currentNode: NetworkNode,
        scanResult: ScanResult,
        session: GameSession
    ): String {
        val lines = mutableListOf<String>()

        // Header
        lines.add("SCANNING LOCAL NETWORK...")
        lines.add("Current Node: ${currentNode.nodeName}")
        lines.add("Scan Range: ${scanResult.scanRange}m")
        lines.add("Signal Strength: ${getSignalBar(currentNode.signalStrength)} ${currentNode.signalStrength}%")
        lines.add("")

        // Newly discovered nodes
        if (scanResult.newlyDetected.isNotEmpty()) {
            lines.add("═══ NEW NODES DISCOVERED ═══")
            lines.add("")

            scanResult.newlyDetected.forEach { node ->
                val distance = calculateDistance(currentNode, node)
                lines.addAll(formatNodeInfo(node, distance, "Never visited", isNew = true))
                lines.add("")
            }
        }

        // Known nodes in range
        if (scanResult.knownInRange.isNotEmpty()) {
            lines.add("═══ KNOWN NODES IN RANGE ═══")
            lines.add("")

            scanResult.knownInRange
                .sortedBy { calculateDistance(currentNode, it) }
                .forEach { node ->
                    val distance = calculateDistance(currentNode, node)
                    val state = discoveryManager.getDiscoveryState(session.player.id, node.nodeId)
                    val statusText = formatDiscoveryState(state)
                    lines.addAll(formatNodeInfo(node, distance, statusText, isNew = false))
                    lines.add("")
                }
        }

        // Summary
        if (scanResult.newlyDetected.isEmpty() && scanResult.knownInRange.isEmpty()) {
            lines.add("No nodes detected in range.")
            lines.add("Try moving to a different location or upgrading your scanner.")
        } else {
            lines.add("═══════════════════════════════════════")
            lines.add("Summary:")
            lines.add("  New discoveries: ${scanResult.newlyDetected.size}")
            lines.add("  Known in range: ${scanResult.knownInRange.size}")
            lines.add("  Total in range: ${scanResult.totalInRange}")
            lines.add("")
            lines.add("Use 'map' to view all discovered nodes.")
        }

        return lines.joinToString("\n")
    }

    /**
     * Format individual node information
     */
    private fun formatNodeInfo(
        node: NetworkNode,
        distance: Int,
        status: String,
        isNew: Boolean
    ): List<String> {
        val icon = getNodeIcon(node)
        val prefix = if (isNew) "[NEW]" else "     "
        val securityIndicator = getSecurityIndicator(node.securityLevel)

        return listOf(
            "$prefix [$icon] ${node.nodeName}",
            "       ├─ Type: ${node.nodeType.displayName}",
            "       ├─ Signal: ${getSignalBar(node.signalStrength)} ${node.signalStrength}%",
            "       ├─ Distance: ${distance}m",
            "       ├─ Security: $securityIndicator Level ${node.securityLevel}",
            "       ├─ Status: $status",
            "       └─ IP: ${node.ipAddress}"
        )
    }

    /**
     * Get node type icon
     */
    private fun getNodeIcon(node: NetworkNode): String {
        return when (node.nodeType.category) {
            NodeCategory.PUBLIC_ACCESS -> "C"
            NodeCategory.COMMERCIAL -> "S"
            NodeCategory.MEDICAL -> "M"
            NodeCategory.CORPORATE -> "!"
            NodeCategory.INDUSTRIAL -> "I"
            NodeCategory.INFRASTRUCTURE -> "G"
            NodeCategory.RESIDENTIAL -> "R"
        }
    }

    /**
     * Generate signal strength bar
     */
    private fun getSignalBar(strength: Int): String {
        val filledBlocks = (strength / 10).coerceIn(0, 10)
        return "█".repeat(filledBlocks) + "░".repeat(10 - filledBlocks)
    }

    /**
     * Get security level indicator
     */
    private fun getSecurityIndicator(level: Int): String {
        return when (level) {
            1 -> "🟢" // Low
            2 -> "🟡" // Medium
            3 -> "🟠" // High
            4 -> "🔴" // Very High
            5 -> "⚠️ " // Critical
            else -> "  "
        }
    }

    /**
     * Format discovery state for display
     */
    private fun formatDiscoveryState(state: DiscoveryState?): String {
        return when (state) {
            DiscoveryState.DISCOVERED -> "Never visited"
            DiscoveryState.CONNECTED -> "Previously visited"
            DiscoveryState.COMPROMISED -> "COMPROMISED (backdoor)"
            DiscoveryState.LOCKED -> "LOCKED (access denied)"
            null -> "Unknown"
            else -> state.name
        }
    }

    /**
     * Calculate distance between two nodes
     */
    private fun calculateDistance(from: NetworkNode, to: NetworkNode): Int {
        val dx = (to.coordX - from.coordX).toDouble()
        val dy = (to.coordY - from.coordY).toDouble()
        return sqrt(dx * dx + dy * dy).toInt()
    }

    /**
     * Get scanner range based on player upgrades
     */
    private fun getScannerRange(session: GameSession): Int {
        // TODO: Read from player's scanner level when upgrades are implemented
        return 500 // Default 500m range
    }

    /**
     * Get scanner power based on player upgrades
     */
    private fun getScannerPower(session: GameSession): Int {
        // TODO: Read from player's scanner power when upgrades are implemented
        return 50 // Default 50 power (medium)
    }
}
