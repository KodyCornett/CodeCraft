# 🔧 KOTLIN ENGINE TECHNICAL SPECIFICATION
## Urban Network Exploration System

**Target:** Kotlin/Ktor Engine (`engine/`)
**Purpose:** Core game logic, procedural generation, persistence

---

## 📐 ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────┐
│                   KOTLIN ENGINE LAYERS                       │
├─────────────────────────────────────────────────────────────┤
│  API Layer (Ktor Routes)                                    │
│  - /api/network/scan                                         │
│  - /api/network/map                                          │
│  - /api/network/route                                        │
│  - /api/network/state                                        │
├─────────────────────────────────────────────────────────────┤
│  Command Layer                                               │
│  - ScanCommand (discovery)                                   │
│  - MapCommand (visualization)                                │
│  - RouteCommand (pathfinding)                                │
│  - ConnectCommand (gateway activation)                       │
├─────────────────────────────────────────────────────────────┤
│  Domain Logic Layer                                          │
│  - NodeNameGenerator (procedural naming)                     │
│  - DistrictGenerator (clustering)                            │
│  - DiscoveryManager (fog of war)                             │
│  - GatewayManager (auto-reveal)                              │
│  - PathfindingService (routing)                              │
│  - ConnectionGraphBuilder (topology)                         │
├─────────────────────────────────────────────────────────────┤
│  Persistence Layer (Exposed ORM)                             │
│  - NodeRepository                                            │
│  - DistrictRepository                                        │
│  - ConnectionRepository                                      │
│  - DiscoveryRepository                                       │
│  - PositionRepository                                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 PACKAGE STRUCTURE

```
engine/src/main/kotlin/com/codecraft/engine/
├── network/
│   ├── domain/
│   │   ├── NetworkNode.kt                (data class)
│   │   ├── District.kt                   (data class)
│   │   ├── NodeConnection.kt             (data class)
│   │   ├── DiscoveryState.kt             (enum)
│   │   ├── NodeType.kt                   (enum)
│   │   ├── NodeCategory.kt               (enum)
│   │   ├── ConnectionType.kt             (enum)
│   │   └── DistrictType.kt               (enum)
│   │
│   ├── naming/
│   │   ├── NodeNameGenerator.kt          (procedural names)
│   │   ├── WordLists.kt                  (word banks)
│   │   ├── NamingPattern.kt              (pattern templates)
│   │   └── NamingRules.kt                (business logic)
│   │
│   ├── generation/
│   │   ├── DistrictGenerator.kt          (cluster generation)
│   │   ├── ConnectionGraphBuilder.kt     (topology)
│   │   ├── NetworkWorldGenerator.kt      (full world)
│   │   └── NetworkTestDataGenerator.kt   (test data)
│   │
│   ├── discovery/
│   │   ├── DiscoveryManager.kt           (fog of war)
│   │   ├── ScanService.kt                (detection logic)
│   │   └── VisibilityCalculator.kt       (range/probability)
│   │
│   ├── gateway/
│   │   ├── GatewayManager.kt             (auto-reveal)
│   │   └── GatewayRules.kt               (reveal conditions)
│   │
│   ├── routing/
│   │   ├── PathfindingService.kt         (Dijkstra's)
│   │   ├── RouteOptimizer.kt             (risk vs distance)
│   │   └── ConnectionGraph.kt            (graph structure)
│   │
│   └── persistence/
│       ├── NodeRepository.kt             (CRUD for nodes)
│       ├── DistrictRepository.kt         (CRUD for districts)
│       ├── ConnectionRepository.kt       (CRUD for connections)
│       ├── DiscoveryRepository.kt        (player discoveries)
│       └── PositionRepository.kt         (player location)
│
└── database/
    └── tables/
        ├── NetworkNodesTable.kt
        ├── PlayerDiscoveredNodesTable.kt
        ├── NetworkDistrictsTable.kt
        ├── NodeConnectionsTable.kt
        └── PlayerNetworkPositionTable.kt
```

---

## 🗄️ DATABASE SCHEMA (Exposed ORM)

### Table Definitions

#### `NetworkNodesTable`
```kotlin
object NetworkNodesTable : Table("network_nodes") {
    val nodeId = uuid("node_id").primaryKey()
    val nodeName = varchar("node_name", 255)
    val nodeType = varchar("node_type", 50)
    val districtId = uuid("district_id").nullable().references(NetworkDistrictsTable.districtId)
    val coordX = integer("coord_x")
    val coordY = integer("coord_y")
    val ipAddress = varchar("ip_address", 15)
    val signalStrength = integer("signal_strength").default(100)
    val securityLevel = integer("security_level").default(1)
    val isPublic = bool("is_public").default(true)
    val isMissionCritical = bool("is_mission_critical").default(false)
    val createdAt = long("created_at").default(System.currentTimeMillis())

    init {
        index(false, coordX, coordY)
        index(false, nodeType)
        index(false, districtId)
    }
}
```

#### `PlayerDiscoveredNodesTable`
```kotlin
object PlayerDiscoveredNodesTable : Table("player_discovered_nodes") {
    val id = uuid("id").primaryKey()
    val playerId = uuid("player_id").references(PlayersTable.id)
    val nodeId = uuid("node_id").references(NetworkNodesTable.nodeId)
    val discoveryState = varchar("discovery_state", 50)
    val discoveredAt = long("discovered_at").default(System.currentTimeMillis())
    val lastAccessed = long("last_accessed").nullable()
    val accessCount = integer("access_count").default(0)
    val playerNotes = text("player_notes").nullable()

    init {
        uniqueIndex(playerId, nodeId)
        index(false, playerId)
        index(false, discoveryState)
    }
}
```

#### `NetworkDistrictsTable`
```kotlin
object NetworkDistrictsTable : Table("network_districts") {
    val districtId = uuid("district_id").primaryKey()
    val districtName = varchar("district_name", 100)
    val districtType = varchar("district_type", 50)
    val centerX = integer("center_x")
    val centerY = integer("center_y")
    val radius = integer("radius")
    val ipPrefix = varchar("ip_prefix", 10)
    val nodeDensity = varchar("node_density", 20).default("MEDIUM")
    val description = text("description").nullable()
    val unlockCondition = varchar("unlock_condition", 100).nullable()
    val createdAt = long("created_at").default(System.currentTimeMillis())

    init {
        index(false, districtName)
    }
}
```

#### `NodeConnectionsTable`
```kotlin
object NodeConnectionsTable : Table("node_connections") {
    val id = uuid("id").primaryKey()
    val nodeAId = uuid("node_a_id").references(NetworkNodesTable.nodeId)
    val nodeBId = uuid("node_b_id").references(NetworkNodesTable.nodeId)
    val distance = integer("distance")
    val connectionQuality = integer("connection_quality").default(100)
    val connectionType = varchar("connection_type", 50).default("DIRECT")
    val isPublic = bool("is_public").default(true)
    val isBidirectional = bool("is_bidirectional").default(true)
    val createdAt = long("created_at").default(System.currentTimeMillis())

    init {
        index(false, nodeAId)
        index(false, nodeBId)
    }
}
```

#### `PlayerNetworkPositionTable`
```kotlin
object PlayerNetworkPositionTable : Table("player_network_position") {
    val playerId = uuid("player_id").primaryKey().references(PlayersTable.id)
    val currentNodeId = uuid("current_node_id").nullable().references(NetworkNodesTable.nodeId)
    val previousNodeId = uuid("previous_node_id").nullable().references(NetworkNodesTable.nodeId)
    val lastPositionUpdate = long("last_position_update").default(System.currentTimeMillis())
}
```

---

## 🎮 COMMAND SYSTEM INTEGRATION

### Command Registration
```kotlin
// In CommandRegistry.kt

class CommandRegistry(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager,
    private val gatewayManager: GatewayManager,
    private val pathfindingService: PathfindingService,
    // ... existing dependencies
) {

    private val commands: Map<String, Command> = mapOf(
        // ... existing commands

        // Network commands
        "scan" to ScanCommand(nodeRepository, discoveryManager),
        "map" to MapCommand(discoveryManager),
        "route" to RouteCommand(pathfindingService, connectionRepository),

        // Update connect to activate gateway
        "connect" to ConnectCommand(
            nodeRepository,
            discoveryManager,
            gatewayManager
        )
    )
}
```

### Enhanced ConnectCommand
```kotlin
class ConnectCommand(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager,
    private val gatewayManager: GatewayManager
) : Command {

    override fun execute(args: List<String>, session: GameSession): CommandResult {
        val targetNodeName = args.joinToString(" ")

        // Find target node (must be discovered)
        val targetNode = nodeRepository.findByName(targetNodeName)
            ?: return CommandResult(
                output = "ERROR: Node '$targetNodeName' not found or not discovered.",
                success = false
            )

        // Check if discovered
        if (!discoveryManager.isDiscovered(session.player.id, targetNode.nodeId)) {
            return CommandResult(
                output = "ERROR: Node must be discovered first. Use 'scan' to find nodes.",
                success = false
            )
        }

        // Check if in range (or connected via gateway)
        val currentNode = session.network.currentNode
        if (currentNode != null) {
            val distance = calculateDistance(currentNode, targetNode)
            if (distance > session.player.connectionRange && !hasRouteViaGateway(currentNode, targetNode)) {
                return CommandResult(
                    output = "ERROR: Node out of range. Use 'route' to find a path.",
                    success = false
                )
            }
        }

        // Disconnect from current node
        if (currentNode != null) {
            session.network.disconnect()
        }

        // Connect to target
        session.network.currentNode = targetNode
        session.network.connectionTime = System.currentTimeMillis()

        // Update discovery state to CONNECTED
        discoveryManager.updateState(
            playerId = session.player.id,
            nodeId = targetNode.nodeId,
            newState = DiscoveryState.CONNECTED
        )

        // Activate gateway if public node
        if (targetNode.isPublic) {
            gatewayManager.activateGateway(session.player.id, targetNode)
        }

        // Build output
        val output = buildString {
            appendLine("Connecting to ${targetNode.nodeName}...")
            appendLine("Connection established.")
            appendLine()
            appendLine("You are now at: ${targetNode.nodeName}")
            appendLine("IP Address: ${targetNode.ipAddress}")
            appendLine("Signal Strength: ${targetNode.signalStrength}%")

            if (targetNode.isPublic) {
                appendLine()
                appendLine("[Gateway activated - nearby nodes revealed]")
            }
        }

        return CommandResult(
            output = output,
            success = true,
            exposureIncrease = 5.0 // Standard connection exposure
        )
    }
}
```

---

## 🔌 API ENDPOINTS

### Network State Endpoint
```kotlin
// In Routes.kt

routing {
    route("/api/network") {

        // Get current network state for UI
        get("/state/{sessionId}") {
            val sessionId = call.parameters["sessionId"] ?: return@get call.respond(
                HttpStatusCode.BadRequest,
                ErrorResponse("Missing sessionId")
            )

            val session = sessionManager.getSession(sessionId) ?: return@get call.respond(
                HttpStatusCode.NotFound,
                ErrorResponse("Session not found")
            )

            val discoveredNodes = discoveryManager.getDiscoveredNodes(session.player.id)
            val currentNode = session.network.currentNode

            call.respond(NetworkStateResponse(
                currentNode = currentNode?.toDto(),
                discoveredNodes = discoveredNodes.map { (node, state) ->
                    NodeDiscoveryDto(
                        node = node.toDto(),
                        state = state.name
                    )
                },
                playerPosition = currentNode?.let {
                    PositionDto(it.coordX, it.coordY)
                }
            ))
        }

        // Scan for nodes
        post("/scan/{sessionId}") {
            val sessionId = call.parameters["sessionId"] ?: return@post call.respond(
                HttpStatusCode.BadRequest,
                ErrorResponse("Missing sessionId")
            )

            val request = call.receive<ScanRequest>()

            val result = commandRegistry.execute(
                command = "scan",
                args = request.range?.let { listOf(it.toString()) } ?: emptyList(),
                sessionId = sessionId
            )

            call.respond(result)
        }

        // Get map data
        get("/map/{sessionId}") {
            val sessionId = call.parameters["sessionId"] ?: return@get call.respond(
                HttpStatusCode.BadRequest,
                ErrorResponse("Missing sessionId")
            )

            val filter = call.parameters["filter"]
            val sort = call.parameters["sort"]

            val result = commandRegistry.execute(
                command = "map",
                args = listOfNotNull(filter, sort),
                sessionId = sessionId
            )

            call.respond(result)
        }

        // Calculate route
        post("/route/{sessionId}") {
            val sessionId = call.parameters["sessionId"] ?: return@post call.respond(
                HttpStatusCode.BadRequest,
                ErrorResponse("Missing sessionId")
            )

            val request = call.receive<RouteRequest>()

            val result = commandRegistry.execute(
                command = "route",
                args = listOf(request.targetNodeName),
                sessionId = sessionId
            )

            call.respond(result)
        }
    }
}
```

### Data Transfer Objects
```kotlin
data class NetworkStateResponse(
    val currentNode: NodeDto?,
    val discoveredNodes: List<NodeDiscoveryDto>,
    val playerPosition: PositionDto?
)

data class NodeDto(
    val nodeId: String,
    val nodeName: String,
    val nodeType: String,
    val coordX: Int,
    val coordY: Int,
    val ipAddress: String,
    val signalStrength: Int,
    val securityLevel: Int,
    val isPublic: Boolean
)

data class NodeDiscoveryDto(
    val node: NodeDto,
    val state: String
)

data class PositionDto(
    val x: Int,
    val y: Int
)

data class ScanRequest(
    val range: Int?
)

data class RouteRequest(
    val targetNodeName: String
)
```

---

## 🎲 PROCEDURAL GENERATION PIPELINE

### World Generation on Server Start
```kotlin
// In Application.kt

fun Application.module() {
    // ... existing setup

    // Initialize network world
    val worldGenerator = NetworkWorldGenerator(
        districtGenerator = DistrictGenerator(nodeNameGenerator),
        connectionGraphBuilder = ConnectionGraphBuilder(),
        nodeRepository = nodeRepository,
        districtRepository = districtRepository,
        connectionRepository = connectionRepository
    )

    // Check if world already generated
    val existingNodes = nodeRepository.count()
    if (existingNodes == 0) {
        log.info("Generating network world...")
        worldGenerator.generateWorld()
        log.info("Network world generated successfully")
    } else {
        log.info("Network world already exists ($existingNodes nodes)")
    }
}
```

### Full World Generator
```kotlin
class NetworkWorldGenerator(
    private val districtGenerator: DistrictGenerator,
    private val connectionGraphBuilder: ConnectionGraphBuilder,
    private val nodeRepository: NodeRepository,
    private val districtRepository: DistrictRepository,
    private val connectionRepository: ConnectionRepository
) {

    fun generateWorld() {
        val districts = Districts.getAll()

        // Save districts to database
        districts.forEach { district ->
            districtRepository.save(district)
        }

        // Generate nodes for each district
        val allNodes = mutableListOf<NetworkNode>()
        districts.forEach { district ->
            val nodes = districtGenerator.generateDistrict(district)
            nodes.forEach { node ->
                nodeRepository.saveNode(node)
            }
            allNodes.addAll(nodes)
        }

        // Build connection graph
        val connections = connectionGraphBuilder.buildConnectionGraph(allNodes)
        connections.forEach { connection ->
            connectionRepository.saveConnection(connection)
        }

        println("World generation complete:")
        println("  Districts: ${districts.size}")
        println("  Nodes: ${allNodes.size}")
        println("  Connections: ${connections.size}")
    }
}
```

---

## 🧪 TESTING STRATEGY

### Unit Tests
```kotlin
// NodeNameGeneratorTest.kt
class NodeNameGeneratorTest {
    @Test fun `generates unique names for 1000 nodes`()
    @Test fun `public access nodes have network suffixes`()
    @Test fun `corporate nodes do not have public suffixes`()
}

// DiscoveryManagerTest.kt
class DiscoveryManagerTest {
    @Test fun `newly discovered node has DISCOVERED state`()
    @Test fun `connecting to node updates to CONNECTED state`()
    @Test fun `discovery state persists across sessions`()
}

// PathfindingServiceTest.kt
class PathfindingServiceTest {
    @Test fun `finds shortest path between connected nodes`()
    @Test fun `returns null when no path exists`()
    @Test fun `calculates correct risk score`()
}

// GatewayManagerTest.kt
class GatewayManagerTest {
    @Test fun `gateway reveals nearby public nodes`()
    @Test fun `non-public nodes are not gateways`()
    @Test fun `respects reveal radius`()
}
```

### Integration Tests
```kotlin
class NetworkIntegrationTest {
    @Test fun `full scan-discover-connect-gateway flow`()
    @Test fun `map persists discoveries across sessions`()
    @Test fun `routing works with generated topology`()
}
```

---

## 📊 PERFORMANCE CONSIDERATIONS

### Spatial Indexing
```kotlin
// Optimize radius queries with spatial index
class SpatialIndex {
    private val grid = mutableMapOf<Pair<Int, Int>, MutableList<NetworkNode>>()
    private val cellSize = 500 // 500m grid cells

    fun insert(node: NetworkNode) {
        val cell = getCell(node.coordX, node.coordY)
        grid.getOrPut(cell) { mutableListOf() }.add(node)
    }

    fun queryRadius(x: Int, y: Int, radius: Int): List<NetworkNode> {
        val cells = getCellsInRadius(x, y, radius)
        return cells.flatMap { grid[it] ?: emptyList() }
            .filter { node ->
                calculateDistance(x, y, node.coordX, node.coordY) <= radius
            }
    }

    private fun getCell(x: Int, y: Int): Pair<Int, Int> {
        return Pair(x / cellSize, y / cellSize)
    }
}
```

### Caching
```kotlin
// Cache frequently accessed data
class NetworkCache {
    private val nodeCache = ConcurrentHashMap<UUID, NetworkNode>()
    private val connectionCache = ConcurrentHashMap<UUID, List<NodeConnection>>()

    fun getNode(nodeId: UUID): NetworkNode? {
        return nodeCache.getOrPut(nodeId) {
            nodeRepository.getNodeById(nodeId) ?: return null
        }
    }

    fun invalidate() {
        nodeCache.clear()
        connectionCache.clear()
    }
}
```

---

## 🔐 SECURITY CONSIDERATIONS

### Input Validation
- Sanitize node names in commands
- Validate coordinate ranges
- Rate limit scan operations

### Data Isolation
- Player discoveries are per-player (not shared)
- Discovery state in separate table
- Connection graph is global (shared world)

---

**Last Updated:** 2026-02-16
**Status:** Specification Complete
**Version:** 1.0
