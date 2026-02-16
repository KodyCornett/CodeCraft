# 🕸️ PHASE 3: MESH TOPOLOGY - Districts, Gateways & Routing

**Timeline:** Week 3 (5-7 days)
**Status:** Not Started
**Dependencies:** Phase 1, 2 (Complete)
**Blocks:** Phase 4, 5

---

## 🎯 GOALS

1. Organize nodes into district clusters (not random scatter)
2. Implement gateway system (public nodes reveal nearby nodes)
3. Build connection graph between nodes
4. Create pathfinding/routing algorithms
5. Add `route` command for path planning

---

## 📋 TASKS BREAKDOWN

### Task 3.1: District System
**Time:** 2 days
**Owner:** Kotlin Engine

#### District Definitions

**Districts are geographic clusters with:**
- Center coordinates (x, y)
- Radius (area size)
- IP prefix (e.g., "10.42" for Downtown)
- Node density (LOW, MEDIUM, HIGH)
- Unlock condition (story progression)

#### Predefined Districts
```kotlin
object Districts {

    val DOWNTOWN = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000001"),
        name = "Downtown",
        type = DistrictType.TECH_HUB,
        centerX = 1000,
        centerY = 1000,
        radius = 500,
        ipPrefix = "10.42",
        density = NodeDensity.HIGH,
        description = "The heart of the city's tech scene. Cafes, startups, and corporate offices.",
        unlockCondition = null // Available from start
    )

    val INDUSTRIAL_ZONE = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000002"),
        name = "Industrial Zone",
        type = DistrictType.INDUSTRIAL,
        centerX = 3000,
        centerY = 500,
        radius = 800,
        ipPrefix = "10.48",
        density = NodeDensity.LOW,
        description = "Warehouses, factories, and data centers. High-value targets.",
        unlockCondition = "mission_2_complete"
    )

    val MEDICAL_DISTRICT = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000003"),
        name = "Medical District",
        type = DistrictType.MEDICAL,
        centerX = 500,
        centerY = 1500,
        radius = 400,
        ipPrefix = "10.62",
        density = NodeDensity.MEDIUM,
        description = "Hospitals, clinics, and pharmacies. Sensitive patient data.",
        unlockCondition = "mission_3_complete"
    )

    val RESIDENTIAL = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000004"),
        name = "Residential",
        type = DistrictType.RESIDENTIAL,
        centerX = 2000,
        centerY = 2500,
        radius = 600,
        ipPrefix = "10.55",
        density = NodeDensity.MEDIUM,
        description = "Apartment complexes and residential networks.",
        unlockCondition = "scanner_upgrade_2"
    )

    val FINANCIAL_QUARTER = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000005"),
        name = "Financial Quarter",
        type = DistrictType.FINANCIAL,
        centerX = 1500,
        centerY = 500,
        radius = 350,
        ipPrefix = "10.78",
        density = NodeDensity.MEDIUM,
        description = "Banks, investment firms, high security.",
        unlockCondition = "mission_5_complete"
    )

    fun getAll() = listOf(
        DOWNTOWN,
        INDUSTRIAL_ZONE,
        MEDICAL_DISTRICT,
        RESIDENTIAL,
        FINANCIAL_QUARTER
    )

    fun getUnlocked(playerProgress: PlayerProgress): List<District> {
        return getAll().filter { district ->
            district.unlockCondition == null ||
            playerProgress.hasUnlocked(district.unlockCondition)
        }
    }
}
```

#### Implementation: `DistrictGenerator.kt`
```kotlin
package com.codecraft.engine.network.generation

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.District
import com.codecraft.engine.network.naming.NodeNameGenerator
import java.util.UUID
import kotlin.random.Random
import kotlin.math.*

class DistrictGenerator(
    private val nameGenerator: NodeNameGenerator
) {

    /**
     * Generate all nodes for a district
     */
    fun generateDistrict(district: District): List<NetworkNode> {
        val nodes = mutableListOf<NetworkNode>()
        val existingNames = mutableSetOf<String>()

        // Generate public access nodes (gateways)
        val gatewayCount = when (district.density) {
            NodeDensity.HIGH -> Random.nextInt(8, 15)
            NodeDensity.MEDIUM -> Random.nextInt(5, 10)
            NodeDensity.LOW -> Random.nextInt(3, 6)
        }

        repeat(gatewayCount) {
            val node = generatePublicNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        // Generate private/commercial nodes
        val commercialCount = when (district.density) {
            NodeDensity.HIGH -> Random.nextInt(5, 10)
            NodeDensity.MEDIUM -> Random.nextInt(3, 7)
            NodeDensity.LOW -> Random.nextInt(2, 5)
        }

        repeat(commercialCount) {
            val node = generateCommercialNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        // Generate secure/corporate nodes (targets)
        val targetCount = when (district.type) {
            DistrictType.TECH_HUB -> Random.nextInt(3, 6)
            DistrictType.INDUSTRIAL -> Random.nextInt(2, 5)
            DistrictType.FINANCIAL -> Random.nextInt(4, 7)
            else -> Random.nextInt(1, 3)
        }

        repeat(targetCount) {
            val node = generateCorporateNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        println("Generated ${nodes.size} nodes for ${district.name}")
        return nodes
    }

    private fun generatePublicNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = selectPublicNodeType(district)
        val position = randomPositionInDistrict(district, centralBias = 0.8)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(70, 100),
            securityLevel = 1,
            isPublic = true,
            isMissionCritical = false
        )
    }

    private fun generateCommercialNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = selectCommercialNodeType(district)
        val position = randomPositionInDistrict(district, centralBias = 0.6)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(50, 80),
            securityLevel = Random.nextInt(1, 3),
            isPublic = false,
            isMissionCritical = false
        )
    }

    private fun generateCorporateNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = NodeType.OFFICE_BUILDING
        val position = randomPositionInDistrict(district, centralBias = 0.4)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(30, 60),
            securityLevel = Random.nextInt(3, 5),
            isPublic = false,
            isMissionCritical = false
        )
    }

    /**
     * Generate random position within district, with optional central bias
     */
    private fun randomPositionInDistrict(
        district: District,
        centralBias: Double
    ): Pair<Int, Int> {
        // Use polar coordinates for more natural clustering
        val angle = Random.nextDouble(0.0, 2 * PI)

        // Apply bias towards center (closer to 1.0 = more central)
        val maxRadius = district.radius * centralBias
        val distance = Random.nextDouble(0.0, maxRadius)

        val offsetX = (distance * cos(angle)).toInt()
        val offsetY = (distance * sin(angle)).toInt()

        return Pair(
            district.centerX + offsetX,
            district.centerY + offsetY
        )
    }

    private fun generateDistrictIP(district: District): String {
        val prefix = district.ipPrefix
        val third = Random.nextInt(1, 255)
        val fourth = Random.nextInt(1, 255)
        return "$prefix.$third.$fourth"
    }

    private fun selectPublicNodeType(district: District): NodeType {
        return when (district.type) {
            DistrictType.TECH_HUB -> listOf(
                NodeType.CAFE, NodeType.COFFEE_SHOP, NodeType.LIBRARY
            ).random()
            DistrictType.INDUSTRIAL -> listOf(
                NodeType.DINER, NodeType.LAUNDROMAT
            ).random()
            DistrictType.MEDICAL -> listOf(
                NodeType.CAFE, NodeType.PHARMACY
            ).random()
            else -> NodeType.CAFE
        }
    }

    private fun selectCommercialNodeType(district: District): NodeType {
        return when (district.type) {
            DistrictType.TECH_HUB -> listOf(
                NodeType.TECH_SHOP, NodeType.BOOKSTORE, NodeType.ARCADE
            ).random()
            DistrictType.INDUSTRIAL -> listOf(
                NodeType.WAREHOUSE, NodeType.FACTORY
            ).random()
            DistrictType.MEDICAL -> listOf(
                NodeType.CLINIC, NodeType.PHARMACY
            ).random()
            else -> NodeType.CONVENIENCE_STORE
        }
    }
}
```

#### Deliverable
- District definitions
- `DistrictGenerator` with clustering logic
- Realistic geographic distribution
- IP addresses by district

---

### Task 3.2: Gateway System
**Time:** 2 days
**Owner:** Kotlin Engine

#### Gateway Concept

**Public nodes (cafes, libraries) act as gateways:**
- When player connects to gateway, nearby nodes are revealed
- Creates "streets" of interconnected public access points
- Private nodes are only visible through gateways

#### Implementation: `GatewayManager.kt`
```kotlin
package com.codecraft.engine.network.gateway

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.discovery.DiscoveryManager
import java.util.UUID

class GatewayManager(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager
) {

    /**
     * When player connects to a gateway node, reveal nearby nodes
     */
    fun activateGateway(playerId: UUID, gatewayNode: NetworkNode) {
        if (!gatewayNode.isPublic) {
            return // Only public nodes are gateways
        }

        val revealRadius = 500 // Gateway reveals nodes within 500m

        val nearbyNodes = nodeRepository.getNodesInRadius(
            x = gatewayNode.coordX,
            y = gatewayNode.coordY,
            radius = revealRadius
        )

        // Filter to nodes not yet discovered
        val undiscoveredNodes = nearbyNodes.filter { node ->
            !discoveryManager.isDiscovered(playerId, node.nodeId) &&
            node.nodeId != gatewayNode.nodeId
        }

        // Reveal public nodes automatically (high signal strength)
        val autoRevealedNodes = undiscoveredNodes.filter { node ->
            node.isPublic && node.signalStrength >= 70
        }

        autoRevealedNodes.forEach { node ->
            discoveryManager.discoverNode(playerId, node)
        }

        println("Gateway activated: ${autoRevealedNodes.size} nodes auto-revealed")
    }

    /**
     * Get nodes visible from gateway
     */
    fun getVisibleNodesFromGateway(gatewayNode: NetworkNode): List<NetworkNode> {
        val revealRadius = 500

        return nodeRepository.getNodesInRadius(
            x = gatewayNode.coordX,
            y = gatewayNode.coordY,
            radius = revealRadius
        ).filter { it.nodeId != gatewayNode.nodeId }
    }
}
```

#### Update Connect Command to Activate Gateway
```kotlin
// In ConnectCommand.kt, after successful connection:

override fun execute(args: List<String>, session: GameSession): CommandResult {
    // ... existing connection logic ...

    // Activate gateway if public node
    if (targetNode.isPublic) {
        gatewayManager.activateGateway(session.player.id, targetNode)
    }

    return CommandResult(/* ... */)
}
```

#### Deliverable
- `GatewayManager` with auto-reveal
- Integration with `ConnectCommand`
- Gateway activation on public node connection

---

### Task 3.3: Connection Graph
**Time:** 2 days
**Owner:** Kotlin Engine

#### Connection Types
```kotlin
enum class ConnectionType {
    DIRECT,        // Strong direct link
    RELAY,         // Routed through intermediate node
    BACKDOOR,      // Player-installed persistent connection
    WEAK_SIGNAL,   // Unreliable, may drop
    ENCRYPTED      // Secure, requires decryption
}

data class NodeConnection(
    val nodeA: NetworkNode,
    val nodeB: NetworkNode,
    val distance: Int,
    val connectionType: ConnectionType,
    val quality: Int, // 0-100
    val isBidirectional: Boolean = true
)
```

#### Implementation: `ConnectionGraphBuilder.kt`
```kotlin
package com.codecraft.engine.network.topology

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
import com.codecraft.engine.network.domain.ConnectionType

class ConnectionGraphBuilder {

    /**
     * Build connection graph for a set of nodes
     * Nodes connect based on proximity and type
     */
    fun buildConnectionGraph(nodes: List<NetworkNode>): List<NodeConnection> {
        val connections = mutableListOf<NodeConnection>()

        // Public nodes connect to nearby public nodes (create streets)
        val publicNodes = nodes.filter { it.isPublic }
        publicNodes.forEach { nodeA ->
            val nearbyPublic = publicNodes.filter { nodeB ->
                nodeB != nodeA &&
                calculateDistance(nodeA, nodeB) <= 300
            }

            nearbyPublic.forEach { nodeB ->
                if (!connectionExists(connections, nodeA, nodeB)) {
                    connections.add(createConnection(nodeA, nodeB, ConnectionType.DIRECT))
                }
            }
        }

        // Private nodes connect to nearest public gateway
        val privateNodes = nodes.filter { !it.isPublic }
        privateNodes.forEach { privateNode ->
            val nearestGateway = publicNodes
                .sortedBy { calculateDistance(it, privateNode) }
                .firstOrNull()

            if (nearestGateway != null && calculateDistance(nearestGateway, privateNode) <= 500) {
                connections.add(createConnection(nearestGateway, privateNode, ConnectionType.RELAY))
            }
        }

        // Corporate nodes in same district may have private connections
        val corporateNodes = nodes.filter { it.nodeType.category == NodeCategory.CORPORATE }
        corporateNodes.forEach { nodeA ->
            corporateNodes.filter { it != nodeA && it.district == nodeA.district }
                .forEach { nodeB ->
                    if (calculateDistance(nodeA, nodeB) <= 400) {
                        connections.add(createConnection(nodeA, nodeB, ConnectionType.ENCRYPTED))
                    }
                }
        }

        return connections
    }

    private fun createConnection(
        nodeA: NetworkNode,
        nodeB: NetworkNode,
        type: ConnectionType
    ): NodeConnection {
        val distance = calculateDistance(nodeA, nodeB)
        val quality = calculateConnectionQuality(distance, nodeA.signalStrength, nodeB.signalStrength)

        return NodeConnection(
            nodeA = nodeA,
            nodeB = nodeB,
            distance = distance,
            connectionType = type,
            quality = quality,
            isBidirectional = true
        )
    }

    private fun calculateConnectionQuality(
        distance: Int,
        signalA: Int,
        signalB: Int
    ): Int {
        val avgSignal = (signalA + signalB) / 2
        val distancePenalty = (distance / 10) // -1% per 10m
        return (avgSignal - distancePenalty).coerceIn(0, 100)
    }

    private fun calculateDistance(nodeA: NetworkNode, nodeB: NetworkNode): Int {
        val dx = (nodeB.coordX - nodeA.coordX).toDouble()
        val dy = (nodeB.coordY - nodeA.coordY).toDouble()
        return kotlin.math.sqrt(dx * dx + dy * dy).toInt()
    }

    private fun connectionExists(
        connections: List<NodeConnection>,
        nodeA: NetworkNode,
        nodeB: NetworkNode
    ): Boolean {
        return connections.any {
            (it.nodeA == nodeA && it.nodeB == nodeB) ||
            (it.nodeA == nodeB && it.nodeB == nodeA)
        }
    }
}
```

#### Deliverable
- Connection graph generation
- Multiple connection types
- Quality calculation
- Persist connections to database

---

### Task 3.4: Pathfinding & Route Command
**Time:** 2 days
**Owner:** Kotlin Engine

#### Pathfinding Algorithm (Dijkstra's)
```kotlin
package com.codecraft.engine.network.routing

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeConnection
import java.util.*

class PathfindingService {

    data class PathResult(
        val path: List<NetworkNode>,
        val totalDistance: Int,
        val hopCount: Int,
        val estimatedRisk: Int // exposure risk
    )

    /**
     * Find shortest path between two nodes
     */
    fun findPath(
        start: NetworkNode,
        end: NetworkNode,
        connections: List<NodeConnection>
    ): PathResult? {
        val graph = buildAdjacencyList(connections)
        val distances = mutableMapOf<UUID, Int>()
        val previous = mutableMapOf<UUID, NetworkNode>()
        val queue = PriorityQueue<Pair<NetworkNode, Int>>(compareBy { it.second })

        distances[start.nodeId] = 0
        queue.add(start to 0)

        while (queue.isNotEmpty()) {
            val (currentNode, currentDist) = queue.poll()

            if (currentNode.nodeId == end.nodeId) {
                return reconstructPath(start, end, previous, distances)
            }

            graph[currentNode.nodeId]?.forEach { (neighbor, weight) ->
                val newDist = currentDist + weight
                if (newDist < (distances[neighbor.nodeId] ?: Int.MAX_VALUE)) {
                    distances[neighbor.nodeId] = newDist
                    previous[neighbor.nodeId] = currentNode
                    queue.add(neighbor to newDist)
                }
            }
        }

        return null // No path found
    }

    private fun buildAdjacencyList(
        connections: List<NodeConnection>
    ): Map<UUID, List<Pair<NetworkNode, Int>>> {
        val graph = mutableMapOf<UUID, MutableList<Pair<NetworkNode, Int>>>()

        connections.forEach { conn ->
            graph.getOrPut(conn.nodeA.nodeId) { mutableListOf() }
                .add(conn.nodeB to conn.distance)

            if (conn.isBidirectional) {
                graph.getOrPut(conn.nodeB.nodeId) { mutableListOf() }
                    .add(conn.nodeA to conn.distance)
            }
        }

        return graph
    }

    private fun reconstructPath(
        start: NetworkNode,
        end: NetworkNode,
        previous: Map<UUID, NetworkNode>,
        distances: Map<UUID, Int>
    ): PathResult {
        val path = mutableListOf<NetworkNode>()
        var current = end

        while (current.nodeId != start.nodeId) {
            path.add(current)
            current = previous[current.nodeId] ?: break
        }
        path.add(start)
        path.reverse()

        val totalDistance = distances[end.nodeId] ?: 0
        val estimatedRisk = calculatePathRisk(path)

        return PathResult(
            path = path,
            totalDistance = totalDistance,
            hopCount = path.size - 1,
            estimatedRisk = estimatedRisk
        )
    }

    private fun calculatePathRisk(path: List<NetworkNode>): Int {
        return path.sumOf { node ->
            when {
                node.isPublic -> 2
                node.securityLevel <= 2 -> 5
                else -> 10
            }
        }
    }
}
```

#### Route Command
```kotlin
class RouteCommand(
    private val pathfindingService: PathfindingService,
    private val connectionRepository: ConnectionRepository
) : Command {

    override val name = "route"
    override val description = "Calculate route to target node"
    override val usage = "route <node_name>"

    override fun execute(args: List<String>, session: GameSession): CommandResult {
        if (args.isEmpty()) {
            return CommandResult(
                output = "Usage: route <node_name>",
                success = false
            )
        }

        val targetName = args.joinToString(" ")
        val currentNode = session.network.currentNode ?: return CommandResult(
            output = "ERROR: Not connected to a node.",
            success = false
        )

        val targetNode = nodeRepository.findByName(targetName) ?: return CommandResult(
            output = "ERROR: Node '$targetName' not found.",
            success = false
        )

        val connections = connectionRepository.getAllConnections()
        val pathResult = pathfindingService.findPath(currentNode, targetNode, connections)

        if (pathResult == null) {
            return CommandResult(
                output = "No route found to ${targetNode.nodeName}",
                success = false
            )
        }

        val output = buildRouteOutput(pathResult, targetNode)

        return CommandResult(
            output = output,
            success = true
        )
    }

    private fun buildRouteOutput(result: PathResult, target: NetworkNode): String {
        val lines = mutableListOf<String>()

        lines.add("ROUTE TO: ${target.nodeName}")
        lines.add("═══════════════════════════════════════════")
        lines.add("Hops: ${result.hopCount}")
        lines.add("Distance: ${result.totalDistance}m")
        lines.add("Estimated Risk: ${result.estimatedRisk}")
        lines.add("")
        lines.add("PATH:")

        result.path.forEachIndexed { index, node ->
            val icon = getNodeIcon(node.nodeType)
            val prefix = if (index == 0) "START" else "  ↓"
            lines.add("$prefix [$icon] ${node.nodeName}")
        }

        return lines.joinToString("\n")
    }
}
```

#### Example Output
```
ROUTE TO: NovaCorp Data Terminal
═══════════════════════════════════════════
Hops: 3
Distance: 820m
Estimated Risk: 19

PATH:
START [C] Blue Neon Cafe WiFi
  ↓ [C] Midnight Diner Hotspot
  ↓ [M] Sector 7 Pharmacy Guest
  ↓ [!] NovaCorp Data Terminal
```

#### Deliverable
- Pathfinding service with Dijkstra's algorithm
- `RouteCommand` with path display
- Risk estimation for routes

---

## ✅ PHASE 3 COMPLETION CHECKLIST

### Code Deliverables
- [ ] District definitions with 5+ districts
- [ ] `DistrictGenerator` with clustering
- [ ] `GatewayManager` with auto-reveal
- [ ] `ConnectionGraphBuilder` for topology
- [ ] `PathfindingService` with Dijkstra's
- [ ] `RouteCommand` for path planning

### Testing
- [ ] Nodes cluster realistically in districts
- [ ] IP addresses match district prefixes
- [ ] Gateway activation reveals nearby nodes
- [ ] Connection graph is generated correctly
- [ ] Pathfinding finds optimal route
- [ ] Route command displays valid paths

### Integration
- [ ] Districts saved to database
- [ ] Gateway triggers on connection
- [ ] Connections persist
- [ ] Route command accessible in terminal

---

## 🚀 NEXT PHASE

Proceed to **Phase 4: Visual Polish** (`URBAN_NETWORK_PHASE_4.md`).

Phase 4 implements:
- Icon system for node types
- Color coding for security levels
- Ambient traffic/ghost nodes
- Enhanced map visualization

---

**Last Updated:** 2026-02-16
**Status:** Not Started
**Blockers:** Phase 1, 2 completion required
