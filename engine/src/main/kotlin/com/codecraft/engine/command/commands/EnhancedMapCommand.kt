package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandCategory
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeCategory
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.session.GameSession
import kotlin.math.sqrt

/**
 * Enhanced map command to display discovered network nodes
 * Works with the new NetworkNode system (parallel to old Node system)
 */
class EnhancedMapCommand(
    private val discoveryManager: DiscoveryManager,
    private val positionRepository: PositionRepository
) : Command {

    override val name = "nmap"
    override val description = "Display discovered network nodes (new network system)"
    override val usage = "nmap [filter] [sort]"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val filter = args.getOrNull(0) // type, state, etc.
        val sortBy = args.getOrNull(1) // distance, name, signal

        val discoveredNodes = discoveryManager.getDiscoveredNodes(session.player.id)

        if (discoveredNodes.isEmpty()) {
            return CommandResult(
                output = "No nodes discovered yet.\nUse 'nscan' to find nearby nodes in the network.",
                success = true
            )
        }

        // Get current position
        val currentNodeId = positionRepository.getCurrentPosition(session.player.id)
        val currentNode = if (currentNodeId != null) {
            discoveredNodes.find { it.first.nodeId == currentNodeId }?.first
        } else {
            null
        }

        // Apply filter
        val filteredNodes = applyFilter(discoveredNodes, filter)

        // Apply sort
        val sortedNodes = applySort(filteredNodes, sortBy, currentNode)

        val output = buildMapOutput(
            nodes = sortedNodes,
            currentNode = currentNode,
            filter = filter,
            sortBy = sortBy
        )

        return CommandResult(
            output = output,
            success = true
        )
    }

    /**
     * Apply filter to discovered nodes
     */
    private fun applyFilter(
        nodes: List<Pair<NetworkNode, DiscoveryState>>,
        filter: String?
    ): List<Pair<NetworkNode, DiscoveryState>> {
        return when (filter?.lowercase()) {
            "public" -> nodes.filter { it.first.isPublic }
            "secure" -> nodes.filter { !it.first.isPublic }
            "discovered" -> nodes.filter { it.second == DiscoveryState.DISCOVERED }
            "connected" -> nodes.filter { it.second == DiscoveryState.CONNECTED }
            "compromised" -> nodes.filter { it.second == DiscoveryState.COMPROMISED }
            "locked" -> nodes.filter { it.second == DiscoveryState.LOCKED }
            "mission" -> nodes.filter { it.first.isMissionCritical }
            else -> nodes
        }
    }

    /**
     * Apply sort to discovered nodes
     */
    private fun applySort(
        nodes: List<Pair<NetworkNode, DiscoveryState>>,
        sortBy: String?,
        currentNode: NetworkNode?
    ): List<Pair<NetworkNode, DiscoveryState>> {
        return when (sortBy?.lowercase()) {
            "distance" -> {
                if (currentNode != null) {
                    nodes.sortedBy { (node, _) -> calculateDistance(currentNode, node) }
                } else {
                    nodes // Can't sort by distance without current position
                }
            }
            "name" -> nodes.sortedBy { it.first.nodeName }
            "signal" -> nodes.sortedByDescending { it.first.signalStrength }
            "security" -> nodes.sortedBy { it.first.securityLevel }
            "type" -> nodes.sortedBy { it.first.nodeType.displayName }
            else -> nodes.sortedBy { it.second.ordinal } // by state (default)
        }
    }

    /**
     * Build formatted map output
     */
    private fun buildMapOutput(
        nodes: List<Pair<NetworkNode, DiscoveryState>>,
        currentNode: NetworkNode?,
        filter: String?,
        sortBy: String?
    ): String {
        val lines = mutableListOf<String>()

        // Header
        lines.add("═══════════════════════════════════════════════════════")
        lines.add("NETWORK MAP")
        if (currentNode != null) {
            lines.add("Current: ${currentNode.nodeName}")
        } else {
            lines.add("Current: Not connected to network")
        }
        if (filter != null) {
            lines.add("Filter: $filter")
        }
        if (sortBy != null) {
            lines.add("Sort: $sortBy")
        }
        lines.add("═══════════════════════════════════════════════════════")
        lines.add("")

        // Group by state
        val stateGroups = nodes.groupBy { it.second }

        // Show COMPROMISED nodes first (high value)
        stateGroups[DiscoveryState.COMPROMISED]?.let { compromised ->
            if (compromised.isNotEmpty()) {
                lines.add("COMPROMISED (${compromised.size}):")
                compromised.forEach { (node, _) ->
                    lines.add(formatNodeLine(node, currentNode, "✓"))
                }
                lines.add("")
            }
        }

        // Show CONNECTED nodes (visited)
        stateGroups[DiscoveryState.CONNECTED]?.let { connected ->
            if (connected.isNotEmpty()) {
                lines.add("CONNECTED (${connected.size}):")
                connected.forEach { (node, _) ->
                    lines.add(formatNodeLine(node, currentNode, "◆"))
                }
                lines.add("")
            }
        }

        // Show DISCOVERED nodes (not yet visited)
        stateGroups[DiscoveryState.DISCOVERED]?.let { discovered ->
            if (discovered.isNotEmpty()) {
                lines.add("DISCOVERED (${discovered.size}):")
                discovered.forEach { (node, _) ->
                    lines.add(formatNodeLine(node, currentNode, "◇"))
                }
                lines.add("")
            }
        }

        // Show LOCKED nodes (failed access)
        stateGroups[DiscoveryState.LOCKED]?.let { locked ->
            if (locked.isNotEmpty()) {
                lines.add("LOCKED (${locked.size}):")
                locked.forEach { (node, _) ->
                    lines.add(formatNodeLine(node, currentNode, "✗"))
                }
                lines.add("")
            }
        }

        lines.add("═══════════════════════════════════════════════════════")
        lines.add("Total: ${nodes.size} nodes | Use 'nmap [filter] [sort]' for options")
        lines.add("")
        lines.add("Filters: public, secure, discovered, connected, compromised, locked, mission")
        lines.add("Sorting: distance, name, signal, security, type")

        return lines.joinToString("\n")
    }

    /**
     * Format single node line
     */
    private fun formatNodeLine(
        node: NetworkNode,
        currentNode: NetworkNode?,
        statusIcon: String
    ): String {
        val icon = getNodeIcon(node)
        val distance = if (currentNode != null && node != currentNode) {
            val dist = calculateDistance(currentNode, node)
            "${dist}m".padStart(6)
        } else if (node == currentNode) {
            " [YOU]"
        } else {
            "    --"
        }

        val securityIndicator = getSecurityIndicator(node.securityLevel)
        val signal = getSignalBar(node.signalStrength, compact = true)

        return "  $statusIcon [$icon] ${node.nodeName.take(35).padEnd(35)} | $signal | $securityIndicator | $distance"
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
     * Generate signal strength bar (compact version)
     */
    private fun getSignalBar(strength: Int, compact: Boolean = false): String {
        val blocks = if (compact) 5 else 10
        val filledBlocks = (strength * blocks / 100).coerceIn(0, blocks)
        return "█".repeat(filledBlocks) + "░".repeat(blocks - filledBlocks)
    }

    /**
     * Get security level indicator
     */
    private fun getSecurityIndicator(level: Int): String {
        return when (level) {
            1 -> "🟢"  // Low
            2 -> "🟡"  // Medium
            3 -> "🟠"  // High
            4 -> "🔴"  // Very High
            5 -> "⚠️"  // Critical
            else -> "  "
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
}
