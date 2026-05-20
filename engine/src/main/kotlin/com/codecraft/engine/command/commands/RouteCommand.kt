package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandCategory
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.network.routing.NetworkPathfinder
import com.codecraft.engine.network.routing.PathResult
import com.codecraft.engine.session.GameSession

class RouteCommand(
    private val nodeRepository: NodeRepository,
    private val connectionRepository: ConnectionRepository,
    private val discoveryManager: DiscoveryManager,
    private val positionRepository: PositionRepository
) : Command {

    override val name = "route"
    override val description = "Find the network path to a target node"
    override val usage = "route <node-name|ip>"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult(
                output = "Usage: $usage\n\nFind the network route to a discovered node.\nExample: route cipher-labs",
                success = false
            )
        }

        val playerId = session.player.id
        val target = args.joinToString(" ")

        // Require an active position
        val currentNodeId = positionRepository.getCurrentPosition(playerId)
            ?: return CommandResult(
                output = "ERROR: Not connected to any node. Use `nconnect` first.",
                success = false
            )

        // Search discovered nodes for the target
        val discoveredNodes = discoveryManager.getDiscoveredNodes(playerId)

        val targetPair = discoveredNodes.find { (node, _) ->
            node.nodeName.lowercase().contains(target.lowercase())
        } ?: discoveredNodes.find { (node, _) ->
            node.ipAddress == target
        }

        if (targetPair == null) {
            return CommandResult(
                output = "ERROR: Node '$target' not found or not yet discovered.\nUse 'nmap' to see discovered nodes.",
                success = false
            )
        }

        val targetNode = targetPair.first

        // Allowed traversal: all discovered node IDs
        val allowedNodeIds = discoveredNodes.map { it.first.nodeId }.toSet()

        // Fetch graph data
        val allConnections = connectionRepository.getAllConnections()
        val nodeMap = nodeRepository.getAllNodes().associateBy { it.nodeId }

        val pathResult = NetworkPathfinder().findPath(
            startNodeId = currentNodeId,
            targetNodeId = targetNode.nodeId,
            allConnections = allConnections,
            nodeMap = nodeMap,
            allowedNodeIds = allowedNodeIds
        )

        if (pathResult == null) {
            return CommandResult(
                output = "No route found to ${targetNode.nodeName}. Node may be unreachable from your current position.",
                success = false
            )
        }

        val startNode = nodeMap[currentNodeId]
            ?: return CommandResult(output = "ERROR: Current node not found.", success = false)

        return CommandResult(
            output = formatPath(startNode, targetNode, pathResult),
            success = true,
            exposureChange = 0.5
        )
    }

    private fun formatPath(startNode: NetworkNode, targetNode: NetworkNode, result: PathResult): String {
        val separator = "━".repeat(40)
        val lines = mutableListOf<String>()

        lines.add("ROUTE: ${startNode.nodeName} → ${targetNode.nodeName}")
        lines.add(separator)

        for (hop in result.hops) {
            val label = "[${hop.hopNumber}] ${hop.node.nodeName}"
            if (hop.hopNumber == 0) {
                lines.add("$label (current)")
            } else {
                lines.add(label)
            }

            // Print edge info leading TO next hop (shown below current hop)
            val nextHop = result.hops.getOrNull(hop.hopNumber + 1)
            if (nextHop?.connection != null) {
                val conn = nextHop.connection
                val typeLabel = conn.connectionType.name.padEnd(9)
                lines.add(" │  $typeLabel ▸ ${conn.distance}m  ▸ quality: ${conn.connectionQuality}")
            }
        }

        lines.add(separator)
        lines.add("${result.totalHops} hops  |  ${result.totalDistance}m total  |  avg quality: ${result.averageQuality}")

        return lines.joinToString("\n")
    }
}
