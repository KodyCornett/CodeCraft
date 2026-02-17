package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandCategory
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.session.GameSession
import java.util.UUID

/**
 * Enhanced connect command for the new network system
 * Handles connection, position tracking, and discovery state updates
 */
class EnhancedConnectCommand(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager,
    private val positionRepository: PositionRepository
) : Command {

    override val name = "nconnect"
    override val description = "Connect to a network node (new network system)"
    override val usage = "nconnect <node-name|ip|id>"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult(
                output = "Usage: nconnect <node-name|ip|id>\n\n" +
                        "Connect to a discovered network node.\n" +
                        "Use 'nmap' to see all discovered nodes.",
                success = false
            )
        }

        val target = args.joinToString(" ")

        // Get discovered nodes for this player
        val discoveredNodes = discoveryManager.getDiscoveredNodes(session.player.id)
        if (discoveredNodes.isEmpty()) {
            return CommandResult(
                output = "ERROR: No nodes discovered yet.\n" +
                        "Use 'nscan' to discover nearby nodes first.",
                success = false
            )
        }

        // Find target node by name, IP, or ID
        val targetNode = findTargetNode(target, discoveredNodes)
        if (targetNode == null) {
            return CommandResult(
                output = "ERROR: Node '$target' not found.\n\n" +
                        "The node must be discovered first. Use 'nscan' to find nearby nodes.\n" +
                        "Use 'nmap' to see all discovered nodes.",
                success = false
            )
        }

        // Check if node is locked
        val currentState = discoveryManager.getDiscoveryState(session.player.id, targetNode.nodeId)
        if (currentState == DiscoveryState.LOCKED) {
            return CommandResult(
                output = "ERROR: Node '${targetNode.nodeName}' is LOCKED.\n" +
                        "Access has been denied due to failed connection attempts.\n" +
                        "You cannot connect to this node.",
                success = false
            )
        }

        // Get current position (if any)
        val currentNodeId = positionRepository.getCurrentPosition(session.player.id)
        val currentNode = if (currentNodeId != null) {
            nodeRepository.getNodeById(currentNodeId)
        } else {
            null
        }

        // Check if already connected to this node
        if (currentNode?.nodeId == targetNode.nodeId) {
            return CommandResult(
                output = "Already connected to ${targetNode.nodeName}.",
                success = true
            )
        }

        // Perform connection
        val previousNodeId = currentNode?.nodeId

        // Update position in repository
        positionRepository.updatePosition(
            playerId = session.player.id,
            currentNodeId = targetNode.nodeId,
            previousNodeId = previousNodeId
        )

        // Update discovery state to CONNECTED
        discoveryManager.updateState(
            playerId = session.player.id,
            nodeId = targetNode.nodeId,
            newState = DiscoveryState.CONNECTED
        )

        // Build connection message
        val output = buildConnectionOutput(
            targetNode = targetNode,
            previousNode = currentNode
        )

        // Small exposure increase for moving through the network
        return CommandResult(
            output = output,
            success = true,
            exposureChange = 3.0 // Lateral movement exposure
        )
    }

    /**
     * Find target node by name, IP, or UUID
     */
    private fun findTargetNode(
        target: String,
        discoveredNodes: List<Pair<NetworkNode, DiscoveryState>>
    ): NetworkNode? {
        val targetLower = target.lowercase()

        return discoveredNodes
            .map { it.first }
            .find { node ->
                // Match by name (case-insensitive, partial match)
                node.nodeName.lowercase().contains(targetLower) ||
                // Match by IP
                node.ipAddress == target ||
                // Match by UUID
                node.nodeId.toString() == target
            }
    }

    /**
     * Build formatted connection output
     */
    private fun buildConnectionOutput(
        targetNode: NetworkNode,
        previousNode: NetworkNode?
    ): String {
        val lines = mutableListOf<String>()

        if (previousNode != null) {
            lines.add("Disconnecting from ${previousNode.nodeName}...")
            lines.add("")
        }

        lines.add("Connecting to ${targetNode.nodeName}...")
        lines.add("")
        lines.add("═══════════════════════════════════════════════════════")
        lines.add("CONNECTION ESTABLISHED")
        lines.add("═══════════════════════════════════════════════════════")
        lines.add("")
        lines.add("Node: ${targetNode.nodeName}")
        lines.add("Type: ${targetNode.nodeType.displayName}")
        lines.add("IP Address: ${targetNode.ipAddress}")
        lines.add("Signal Strength: ${getSignalBar(targetNode.signalStrength)} ${targetNode.signalStrength}%")
        lines.add("Security Level: ${getSecurityIndicator(targetNode.securityLevel)} Level ${targetNode.securityLevel}")
        lines.add("Access: ${if (targetNode.isPublic) "Public" else "Secure"}")

        if (targetNode.isMissionCritical) {
            lines.add("Status: ⚠️  MISSION CRITICAL")
        }

        lines.add("")
        lines.add("Position: (${targetNode.coordX}, ${targetNode.coordY})")

        if (previousNode != null) {
            lines.add("Previous: ${previousNode.nodeName}")
        }

        lines.add("")
        lines.add("You are now connected to this node's network.")
        lines.add("Use 'nscan' to discover nearby nodes from this position.")

        return lines.joinToString("\n")
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
            1 -> "🟢"  // Low
            2 -> "🟡"  // Medium
            3 -> "🟠"  // High
            4 -> "🔴"  // Very High
            5 -> "⚠️"   // Critical
            else -> "  "
        }
    }
}
