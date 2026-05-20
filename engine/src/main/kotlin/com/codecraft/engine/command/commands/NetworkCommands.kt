package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.ObjectiveType
import com.codecraft.engine.domain.PortState
import com.codecraft.engine.protocol.GameEvent
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.puzzle.PuzzleGenerator
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.PendingPuzzle

/**
 * scan - Scan network for available nodes, or scan specific target for open ports
 */
class ScanCommand : Command {
    override val name = "scan"
    override val description = "Scan network for nodes or scan specific target"
    override val usage = "scan [target] [-A]"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val target = args.find { !it.startsWith("-") }
        val aggressive = args.contains("-A")

        // No target specified: scan network for available nodes
        if (target == null) {
            return scanNetwork(session)
        }

        // Target specified: scan specific node for ports
        return scanTarget(session, target, aggressive)
    }

    /**
     * Scan network to discover available nodes
     */
    private fun scanNetwork(session: GameSession): CommandResult {
        val currentNode = session.getCurrentNode()

        // Get only directly connected nodes (progressive discovery)
        val connectedNodeIds = currentNode.connectedNodes
        val connectedNodes = connectedNodeIds.mapNotNull { session.network.getNode(it) }

        if (connectedNodes.isEmpty()) {
            return CommandResult(
                output = "Scanning network...\n\nNo directly connected nodes detected.",
                success = true,
                delayMs = 1000,
                exposureChange = 1.0
            )
        }

        // Mark nodes as discovered; public nodes also get scanned (their names are in public directories)
        connectedNodes.forEach { node ->
            session.player.discoveredNodes.add(node.id)
            if (node.isPublic) {
                session.player.scannedNodes.add(node.id)
            }
        }

        val output = buildString {
            if (currentNode.id == "localhost") {
                appendLine("Scanning network...")
                appendLine()
                appendLine("Discovered nodes:")
                connectedNodes.forEach { node ->
                    if (node.isPublic) {
                        appendLine("  ${node.ip}  [${node.name}]")
                    } else {
                        appendLine("  ${node.ip}")
                    }
                }
            } else {
                appendLine("Current location: ${currentNode.name} (${currentNode.ip})")
                appendLine()
                appendLine("Connected nodes:")
                connectedNodes.forEach { node ->
                    if (node.isPublic) {
                        appendLine("  -> ${node.ip}  [${node.name}]")
                    } else {
                        appendLine("  -> ${node.ip}")
                    }
                }
            }
            appendLine()
            appendLine("Use 'connect <ip>' to access.")
            appendLine("Use 'scan <ip>' for detailed port scan.")
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 1500,
            stateChanges = StateChanges(discoveredNodes = session.player.discoveredNodes.toList()),
            events = connectedNodes.map { node ->
                GameEvent("node_discovered", mapOf("nodeId" to node.id))
            },
            exposureChange = 2.0
        )
    }

    /**
     * Scan specific target for open ports and services
     */
    private fun scanTarget(session: GameSession, target: String, aggressive: Boolean): CommandResult {
        val node = session.network.getNodeByIp(target) ?: session.network.getNode(target)

        if (node == null) {
            return CommandResult(
                output = "Scanning $target...\n\nHost appears to be down or unreachable.",
                success = false,
                delayMs = 3000,
                exposureChange = 1.0
            )
        }

        session.player.discoveredNodes.add(node.id)
        session.player.scannedNodes.add(node.id)  // targeted scan reveals full details

        val output = buildString {
            appendLine("Scanning ${node.ip}...")
            appendLine()
            appendLine("Host: ${node.name}")
            appendLine()
            appendLine("PORT     STATE     SERVICE" + if (aggressive) "        VERSION" else "")
            appendLine("─".repeat(if (aggressive) 55 else 35))

            node.ports.forEach { port ->
                val stateStr = when (port.state) {
                    PortState.OPEN -> "open"
                    PortState.CLOSED -> "closed"
                    PortState.FILTERED -> "filtered"
                }
                val portStr = "${port.number}/tcp".padEnd(8)
                val stateFormatted = stateStr.padEnd(10)

                if (aggressive && port.version != null) {
                    appendLine("$portStr $stateFormatted ${port.service.padEnd(14)} ${port.version}")
                } else {
                    appendLine("$portStr $stateFormatted ${port.service}")
                }
            }

            appendLine()
            appendLine("Scan complete. ${node.ports.count { it.state == PortState.OPEN }} open ports found.")

            if (aggressive && node.vulnerabilities.isNotEmpty()) {
                appendLine()
                appendLine("Potential vulnerabilities detected:")
                node.vulnerabilities.forEach { vuln ->
                    appendLine("  - $vuln")
                }
            }

            // Aggressive scans trigger alarms on secure nodes
            if (aggressive && node.securityLevel >= 4 && !node.alarmActive) {
                node.triggerAlarm()
                appendLine()
                appendLine("⚠ ALERT: Aggressive scan detected by ${node.name}")
                appendLine("   Security systems now on high alert (detection risk x2 for 5 minutes)")
            }
        }

        val exposureIncrease = if (aggressive) 5.0 else 2.0

        return CommandResult(
            output = output,
            success = true,
            delayMs = if (aggressive) 3000 else 1500,
            exposureChange = exposureIncrease,
            stateChanges = StateChanges(discoveredNodes = session.player.discoveredNodes.toList()),
            events = listOf(GameEvent("node_discovered", mapOf("nodeId" to node.id)))
        )
    }
}

/**
 * connect - Connect to remote system (with connection puzzle)
 */
class ConnectCommand : Command {
    override val name = "connect"
    override val description = "Connect to remote system"
    override val usage = "connect <target> [port]"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("connect: missing target\nUsage: connect <ip/hostname> [port]")
        }

        val target = args[0]
        val port = args.getOrNull(1)?.toIntOrNull() ?: 22

        // Auto-disconnect if already connected (lateral movement)
        val isLateralMove = session.connectedNode != null
        val previousNodeName = session.connectedNode?.name
        if (isLateralMove) {
            session.disconnect()  // Handles trace closure and sentinel event
        }

        // Find the node
        val node = session.network.getNodeByIp(target) ?: session.network.getNode(target)

        if (node == null) {
            return CommandResult(
                output = "Connecting to $target:$port...\n\nConnection refused - host unreachable.",
                success = false,
                delayMs = 2000,
                exposureChange = 2.0
            )
        }

        // Check if port is open
        val targetPort = node.ports.find { it.number == port && it.state == PortState.OPEN }
        if (targetPort == null) {
            return CommandResult(
                output = "Connecting to $target:$port...\n\nConnection refused - port closed or filtered.",
                success = false,
                delayMs = 1500,
                exposureChange = 1.0
            )
        }

        // Check if already has access (puzzle previously solved)
        if (node.id in session.nodeAccess || node.id == "localhost" || node.compromised) {
            return completeConnection(session, node, targetPort, isLateralMove, previousNodeName)
        }

        // Generate connection puzzle
        val puzzle = PuzzleGenerator.generateConnectionPuzzle(node.id, node.securityLevel)
        session.pendingPuzzle = PendingPuzzle(
            nodeId = node.id,
            puzzleType = puzzle.type.name,
            question = puzzle.content,
            answer = puzzle.solution,
            difficulty = node.securityLevel
        )

        val output = buildString {
            if (isLateralMove) {
                appendLine("Disconnecting from $previousNodeName...")
                appendLine("Closing connection... trace stabilizing...")
                appendLine()
            }
            appendLine("Connecting to $target:$port...")
            appendLine("Connection established. Authentication required.")
            appendLine()
            appendLine(puzzle.content)
            appendLine()
            appendLine("Use 'solve <answer>' to authenticate.")
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 1500,
            stateChanges = StateChanges(pendingConnection = node.id),
            exposureChange = 3.0
        )
    }

    private fun completeConnection(
        session: GameSession,
        node: com.codecraft.engine.domain.Node,
        port: com.codecraft.engine.domain.Port,
        isLateralMove: Boolean = false,
        previousNodeName: String? = null
    ): CommandResult {
        session.connectTo(node.id)

        // Check for mission connect objective
        val activeMission = session.currentMission
        if (activeMission != null) {
            val connectObj = activeMission.definition.objectives.find {
                it.type == ObjectiveType.CONNECT_NODE && it.target == node.id
            }
            connectObj?.let {
                if (it.id !in activeMission.completedObjectives) {
                    activeMission.completeObjective(it.id)
                }
            }
        }

        val output = buildString {
            if (isLateralMove) {
                appendLine("Disconnecting from $previousNodeName...")
                appendLine("Closing connection... trace stabilizing...")
                appendLine()
            }
            appendLine("Establishing connection to ${node.ip}:${port.number}...")
            appendLine("Routing through proxy chain...")
            appendLine()
            appendLine("Connected to ${node.name}")
            appendLine("Service: ${port.service}")
            if (!node.compromised) {
                appendLine()
                appendLine("WARNING: Unauthorized access. Trace level increasing.")
            }
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 2000,
            stateChanges = StateChanges(
                connectedTo = node.id,
                connectedToName = node.name,
                rootPath = node.rootPath ?: "/home/user"
            ),
            // Lateral move: net +3.0 (auto-disconnect -2.0 + connect +5.0)
            // Normal connect: +5.0
            exposureChange = if (isLateralMove) 3.0 else 5.0
        )
    }
}

/**
 * disconnect - Close active connection
 */
class DisconnectCommand : Command {
    override val name = "disconnect"
    override val description = "Close active connection"
    override val usage = "disconnect"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (session.connectedNode == null) {
            return CommandResult.error("Not connected to any remote system.")
        }

        val nodeName = session.connectedNode!!.name
        session.disconnect()

        val output = buildString {
            appendLine("Closing connection to $nodeName...")
            appendLine("Connection terminated.")
            appendLine("Trace level stabilizing...")
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 500,
            stateChanges = StateChanges(
                connectedTo = null,
                connectedToName = null,
                currentPath = "/home/user"
            ),
            exposureChange = -2.0
        )
    }
}

/**
 * probe - Deep system analysis
 */
class ProbeCommand : Command {
    override val name = "probe"
    override val description = "Deep analysis of connected system"
    override val usage = "probe"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val node = session.connectedNode
            ?: return CommandResult.error("probe: must be connected to a remote system")

        session.player.scannedNodes.add(node.id)

        val output = buildString {
            appendLine("Running deep analysis on ${node.name}...")
            appendLine()
            appendLine("SYSTEM PROFILE")
            appendLine("─".repeat(50))
            appendLine("  Name:     ${node.name}")
            appendLine("  IP:       ${node.ip}")
            appendLine("  Type:     ${node.type.name.lowercase()}")
            appendLine("  Security: Level ${node.securityLevel}/10")
            if (node.organization != null) {
                appendLine("  Owner:    ${node.organization}")
            }
            appendLine()

            appendLine("OPEN SERVICES")
            appendLine("─".repeat(50))
            node.ports.filter { it.state == PortState.OPEN }.forEach { port ->
                val authReq = if (port.requiresAuth) " [AUTH REQUIRED]" else ""
                appendLine("  ${port.number}/tcp - ${port.service}$authReq")
            }
            appendLine()

            if (node.vulnerabilities.isNotEmpty()) {
                appendLine("VULNERABILITIES")
                appendLine("─".repeat(50))
                node.vulnerabilities.forEach { vuln ->
                    appendLine("  $vuln")
                }
                appendLine()
            }

            if (node.connectedNodes.isNotEmpty()) {
                appendLine("CONNECTED NODES")
                appendLine("─".repeat(50))
                node.connectedNodes.forEach { connectedId ->
                    val connectedNode = session.network.getNode(connectedId)
                    if (connectedNode != null) {
                        appendLine("  -> ${connectedNode.ip} (${connectedNode.name})")
                        session.player.discoveredNodes.add(connectedId)
                        session.player.scannedNodes.add(connectedId)  // probe reveals connected node details
                    }
                }
                appendLine()
            }

            appendLine("Analysis complete. Exposure increased.")

            // Trigger alarm on nodes with moderate to high security
            if (node.securityLevel >= 3 && !node.alarmActive) {
                node.triggerAlarm()
                appendLine()
                appendLine("⚠ ALERT: Intrusion detection activated on ${node.name}")
                appendLine("   Security systems now on high alert (detection risk x2 for 5 minutes)")
            } else if (node.alarmActive) {
                appendLine()
                appendLine("⚠ WARNING: ${node.name} is already on high alert")
            }
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 2500,
            stateChanges = StateChanges(discoveredNodes = session.player.discoveredNodes.toList()),
            exposureChange = 8.0
        )
    }
}
