package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Network node - represents a hackable target in the game
 */
@Serializable
data class Node(
    val id: String,
    val name: String,
    val ip: String,
    val type: NodeType,
    val securityLevel: Int = 1, // 1-10, affects difficulty

    // Open ports and services
    val ports: List<Port> = emptyList(),

    // Vulnerabilities that can be exploited
    val vulnerabilities: List<String> = emptyList(),

    // Files on this node
    val files: Map<String, String> = emptyMap(), // path -> content

    // Is this node currently compromised by player?
    var compromised: Boolean = false,

    // Does player have a backdoor installed?
    var hasBackdoor: Boolean = false,

    // Connected nodes (network topology)
    val connectedNodes: List<String> = emptyList(),

    // Organization that owns this node
    val organization: String? = null,

    // Loot available when hacked
    val loot: NodeLoot? = null
)

@Serializable
enum class NodeType {
    PERSONAL,       // Home computers, easy targets
    CORPORATE,      // Company servers, medium difficulty
    GOVERNMENT,     // Government systems, high difficulty
    FINANCIAL,      // Banks, crypto exchanges
    SECURITY,       // Security firms, very difficult
    INFRASTRUCTURE, // Power grids, traffic systems
    UNDERGROUND     // Hacker servers, dark web
}

@Serializable
data class Port(
    val number: Int,
    val service: String,
    val version: String? = null,
    val state: PortState = PortState.OPEN,
    val requiresAuth: Boolean = false
)

@Serializable
enum class PortState {
    OPEN,
    CLOSED,
    FILTERED
}

@Serializable
data class NodeLoot(
    val credits: Int = 0,
    val data: List<String> = emptyList(), // Data files that can be sold
    val exploits: List<String> = emptyList(), // Exploits that can be found
    val intel: List<String> = emptyList() // Information about other nodes
)

/**
 * The game's network state
 */
class NetworkState {
    private val nodes = mutableMapOf<String, Node>()

    init {
        // Initialize with starting nodes
        initializeNetwork()
    }

    private fun initializeNetwork() {
        // Player's local machine
        addNode(Node(
            id = "localhost",
            name = "Home Terminal",
            ip = "127.0.0.1",
            type = NodeType.PERSONAL,
            securityLevel = 0,
            ports = listOf(Port(22, "ssh"), Port(80, "http")),
            files = mapOf(
                "/home/user/readme.txt" to "Welcome to CodeCraft.\n\nType 'help' to see available commands.",
                "/home/user/notes.txt" to "Remember: The password hint is in the server logs.\nDon't trust Marcus.",
                "/home/user/documents/mission_briefing.txt" to "TARGET: Meridian Corp\nOBJECTIVE: Extract personnel database\n\nINTEL: Their legacy-auth system has known vulnerabilities.",
                "/home/user/documents/contacts.txt" to "GHOST - Reliable. Expensive.\nMARCUS - Avoid. Suspected fed.\nZERO - New. Unverified."
            ),
            compromised = true // Player owns this
        ))

        // First target - easy corporate server
        addNode(Node(
            id = "nova-corp-web",
            name = "NovaCorp Web Server",
            ip = "192.168.50.10",
            type = NodeType.CORPORATE,
            securityLevel = 2,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 7.9"),
                Port(80, "http", "nginx 1.18"),
                Port(443, "https"),
                Port(3306, "mysql", state = PortState.FILTERED)
            ),
            vulnerabilities = listOf("ssh_bruteforce", "sql_injection"),
            files = mapOf(
                "/var/www/config.php" to "<?php\n\$db_user = 'webapp';\n\$db_pass = 'corp2024!';\n?>",
                "/home/admin/notes.txt" to "Server maintenance scheduled for Sunday 2am.\nBackup admin password: n0v4_b4ckup_2024"
            ),
            connectedNodes = listOf("nova-corp-db"),
            loot = NodeLoot(
                credits = 500,
                data = listOf("employee_emails.db"),
                intel = listOf("nova-corp-db")
            )
        ))

        // Second target - database server (requires pivoting)
        addNode(Node(
            id = "nova-corp-db",
            name = "NovaCorp Database",
            ip = "192.168.50.20",
            type = NodeType.CORPORATE,
            securityLevel = 4,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.2"),
                Port(3306, "mysql", "MySQL 8.0")
            ),
            vulnerabilities = listOf("mysql_exploit"),
            files = mapOf(
                "/data/hr/employees.db" to "[BINARY DATABASE FILE - Employee records]",
                "/data/finance/transactions.db" to "[BINARY DATABASE FILE - Financial transactions]"
            ),
            loot = NodeLoot(
                credits = 2500,
                data = listOf("employees.db", "transactions.db")
            )
        ))

        // Underground contact's server
        addNode(Node(
            id = "ghost-relay",
            name = "Ghost's Relay",
            ip = "10.13.37.1",
            type = NodeType.UNDERGROUND,
            securityLevel = 6,
            organization = "Underground",
            ports = listOf(
                Port(22, "ssh"),
                Port(6667, "irc"),
                Port(9050, "tor")
            ),
            files = mapOf(
                "/public/welcome.txt" to "Welcome to the underground.\nRules: No feds. No snitches. No traces.",
                "/public/jobs.txt" to "Current contracts available. Check the forum."
            )
        ))
    }

    fun addNode(node: Node) {
        nodes[node.id] = node
    }

    fun getNode(id: String): Node? = nodes[id]

    fun getNodeByIp(ip: String): Node? = nodes.values.find { it.ip == ip }

    fun getAllNodes(): List<Node> = nodes.values.toList()

    fun getDiscoverableFrom(nodeId: String): List<Node> {
        val node = nodes[nodeId] ?: return emptyList()
        return node.connectedNodes.mapNotNull { nodes[it] }
    }
}
