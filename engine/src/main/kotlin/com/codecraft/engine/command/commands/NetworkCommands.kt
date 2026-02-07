package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.PortState
import com.codecraft.engine.protocol.GameEvent
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.session.GameSession

/**
 * scan - Scan target for open ports
 */
class ScanCommand : Command {
    override val name = "scan"
    override val description = "Scan target for open ports and services"
    override val usage = "scan <target> [-p ports] [-A]"
    override val category = CommandCategory.NETWORK

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("scan: missing target\nUsage: scan <ip/hostname>")
        }

        val target = args.find { !it.startsWith("-") }
            ?: return CommandResult.error("scan: missing target")

        val aggressive = args.contains("-A")
        val portArg = args.indexOf("-p").let { if (it >= 0 && it < args.lastIndex) args[it + 1] else null }

        // Find the node
        val node = session.network.getNodeByIp(target) ?: session.network.getNode(target)

        if (node == null) {
            // Unknown target - simulate timeout
            return CommandResult(
                output = "Scanning $target...\n\nHost appears to be down or unreachable.",
                success = false,
                delayMs = 3000,
                exposureChange = 1.0
            )
        }

        // Add to discovered nodes
        session.player.discoveredNodes.add(node.id)

        // Build scan output
        val output = buildString {
            appendLine("Scanning ${node.ip} (${node.name})...")
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
                val serviceStr = port.service.padEnd(14)

                if (aggressive && port.version != null) {
                    appendLine("$portStr $stateFormatted $serviceStr ${port.version}")
                } else {
                    appendLine("$portStr $stateFormatted ${port.service}")
                }
            }

            appendLine()
            appendLine("Scan complete. ${node.ports.count { it.state == PortState.OPEN }} open ports found.")

            if (aggressive && node.vulnerabilities.isNotEmpty()) {
                appendLine()
                appendLine("⚠ Potential vulnerabilities detected:")
                node.vulnerabilities.forEach { vuln ->
                    appendLine("  - $vuln")
                }
            }
        }

        val exposureIncrease = if (aggressive) 5.0 else 2.0

        return CommandResult(
            output = output,
            success = true,
            delayMs = if (aggressive) 3000 else 1500,
            exposureChange = exposureIncrease,
            events = listOf(GameEvent("node_discovered", mapOf("nodeId" to node.id)))
        )
    }
}

/**
 * connect - Connect to remote system
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

        // Already connected?
        if (session.connectedNode != null) {
            return CommandResult.error("Already connected to ${session.connectedNode!!.name}. Use 'disconnect' first.")
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

        // Successful connection
        session.connectTo(node.id)

        val output = buildString {
            appendLine("Establishing connection to $target:$port...")
            appendLine("Routing through proxy chain...")
            appendLine()
            appendLine("Connected to ${node.name}")
            appendLine("Service: ${targetPort.service}")
            if (!node.compromised) {
                appendLine()
                appendLine("⚠ WARNING: Unauthorized access. Trace level increasing.")
            }
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 2000,
            stateChanges = StateChanges(connectedTo = node.id),
            exposureChange = 5.0
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
            stateChanges = StateChanges(connectedTo = null),
            exposureChange = -2.0 // Slight decrease for disconnecting cleanly
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
                    appendLine("  ⚠ $vuln")
                }
                appendLine()
            }

            if (node.connectedNodes.isNotEmpty()) {
                appendLine("CONNECTED NODES")
                appendLine("─".repeat(50))
                node.connectedNodes.forEach { connectedId ->
                    val connectedNode = session.network.getNode(connectedId)
                    if (connectedNode != null) {
                        appendLine("  → ${connectedNode.ip} (${connectedNode.name})")
                        // Add to discovered
                        session.player.discoveredNodes.add(connectedId)
                    }
                }
                appendLine()
            }

            appendLine("Analysis complete. Exposure increased.")
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 2500,
            exposureChange = 8.0
        )
    }
}
