# 🔍 PHASE 2: DISCOVERY SYSTEM - Fog of War & Persistence

**Timeline:** Week 2 (5-7 days)
**Status:** Not Started
**Dependencies:** Phase 1 (Complete)
**Blocks:** Phase 3, 4, 5

---

## 🎯 GOALS

1. Implement "fog of war" discovery mechanics
2. Create enhanced `scan` command with range-based detection
3. Build `map` command for player's discovered nodes
4. Ensure discovery state persists across sessions
5. Track player network position

---

## 📋 TASKS BREAKDOWN

### Task 2.1: Discovery State Management
**Time:** 1 day
**Owner:** Kotlin Engine

#### Discovery States
```kotlin
enum class DiscoveryState {
    UNDISCOVERED,   // Exists in world, not revealed to player
    DISCOVERED,     // Player scanned and found it
    CONNECTED,      // Player connected at least once
    COMPROMISED,    // Player has full access (backdoor)
    LOCKED          // Failed access attempt, locked out
}
```

#### State Transitions
```
UNDISCOVERED → DISCOVERED (via scan)
DISCOVERED → CONNECTED (via connect)
CONNECTED → COMPROMISED (via backdoor install)
CONNECTED → LOCKED (via failed hack attempt)
```

#### Implementation: `DiscoveryManager.kt`
```kotlin
package com.codecraft.engine.network.discovery

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.DiscoveryState
import com.codecraft.engine.network.persistence.NodeRepository
import java.util.UUID

class DiscoveryManager(
    private val nodeRepository: NodeRepository
) {

    /**
     * Mark a node as discovered by player
     */
    fun discoverNode(playerId: UUID, node: NetworkNode) {
        val currentState = getDiscoveryState(playerId, node.nodeId)

        if (currentState == null) {
            // First discovery
            nodeRepository.updateDiscoveryState(
                playerId = playerId,
                nodeId = node.nodeId,
                state = DiscoveryState.DISCOVERED
            )
        }
    }

    /**
     * Update discovery state (e.g., DISCOVERED → CONNECTED)
     */
    fun updateState(playerId: UUID, nodeId: UUID, newState: DiscoveryState) {
        nodeRepository.updateDiscoveryState(playerId, nodeId, newState)
    }

    /**
     * Get current discovery state for a node
     */
    fun getDiscoveryState(playerId: UUID, nodeId: UUID): DiscoveryState? {
        return nodeRepository.getPlayerDiscoveredNodes(playerId)
            .find { it.first.nodeId == nodeId }
            ?.second
    }

    /**
     * Get all discovered nodes for player
     */
    fun getDiscoveredNodes(playerId: UUID): List<Pair<NetworkNode, DiscoveryState>> {
        return nodeRepository.getPlayerDiscoveredNodes(playerId)
    }

    /**
     * Check if node is discovered
     */
    fun isDiscovered(playerId: UUID, nodeId: UUID): Boolean {
        return getDiscoveryState(playerId, nodeId) != null
    }

    /**
     * Get nodes in a given state
     */
    fun getNodesByState(playerId: UUID, state: DiscoveryState): List<NetworkNode> {
        return nodeRepository.getPlayerDiscoveredNodes(playerId)
            .filter { it.second == state }
            .map { it.first }
    }
}
```

#### Unit Tests
```kotlin
class DiscoveryManagerTest {

    @Test
    fun `newly discovered node has DISCOVERED state`()

    @Test
    fun `connecting to node updates to CONNECTED state`()

    @Test
    fun `failed access updates to LOCKED state`()

    @Test
    fun `discovery state persists across sessions`()
}
```

#### Deliverable
- `DiscoveryManager.kt` with state management
- Unit tests for all state transitions
- Integration with repository layer

---

### Task 2.2: Enhanced Scan Command
**Time:** 2 days
**Owner:** Kotlin Engine

#### Scan Mechanics

**Range Calculation:**
- Base range: 300m (early game)
- Upgradable to 500m (mid game) and 1000m (late game)
- Signal strength affects detection probability
- Some nodes are "stealth" (harder to detect)

**Detection Probability:**
```kotlin
fun calculateDetectionChance(
    node: NetworkNode,
    scannerPower: Int,
    distance: Int
): Int {
    val baseChance = 100 - (distance / 5) // -20% per 100m
    val signalBonus = node.signalStrength / 5
    val scannerBonus = scannerPower
    val stealthPenalty = if (node.isPublic) 0 else 30

    return (baseChance + signalBonus + scannerBonus - stealthPenalty)
        .coerceIn(10, 100)
}
```

#### Implementation: `ScanCommand.kt` (Enhanced)
```kotlin
package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.session.GameSession
import kotlin.random.Random

class ScanCommand(
    private val nodeRepository: NodeRepository,
    private val discoveryManager: DiscoveryManager
) : Command {

    override val name = "scan"
    override val description = "Scan for nearby network nodes"
    override val usage = "scan [range]"

    override fun execute(args: List<String>, session: GameSession): CommandResult {
        val customRange = args.firstOrNull()?.toIntOrNull()
        val scanRange = customRange ?: getScannerRange(session)

        // Get player's current position
        val currentNode = session.network.currentNode
        if (currentNode == null) {
            return CommandResult(
                output = "ERROR: Cannot scan. Not connected to a node.",
                success = false
            )
        }

        // Find nodes in range
        val nodesInRange = nodeRepository.getNodesInRadius(
            x = currentNode.coordX,
            y = currentNode.coordY,
            radius = scanRange
        )

        // Filter out already discovered nodes
        val undiscoveredNodes = nodesInRange.filter { node ->
            !discoveryManager.isDiscovered(session.player.id, node.nodeId)
        }

        // Attempt detection with probability
        val detectedNodes = undiscoveredNodes.filter { node ->
            val distance = calculateDistance(currentNode, node)
            val detectionChance = calculateDetectionChance(
                node = node,
                scannerPower = session.player.scannerLevel,
                distance = distance
            )
            Random.nextInt(100) < detectionChance
        }

        // Mark as discovered
        detectedNodes.forEach { node ->
            discoveryManager.discoverNode(session.player.id, node)
        }

        // Get previously discovered nodes in range for display
        val knownNodes = nodesInRange.filter { node ->
            discoveryManager.isDiscovered(session.player.id, node.nodeId) &&
            node.nodeId != currentNode.nodeId
        }

        // Build output
        val output = buildScanOutput(
            currentNode = currentNode,
            newlyDiscovered = detectedNodes,
            knownNodes = knownNodes,
            scanRange = scanRange
        )

        return CommandResult(
            output = output,
            success = true,
            exposureIncrease = 2.0 // Scanning generates small exposure
        )
    }

    private fun buildScanOutput(
        currentNode: NetworkNode,
        newlyDiscovered: List<NetworkNode>,
        knownNodes: List<NetworkNode>,
        scanRange: Int
    ): String {
        val lines = mutableListOf<String>()

        lines.add("SCANNING LOCAL NETWORK...")
        lines.add("Current Node: ${currentNode.nodeName}")
        lines.add("Scan Range: ${scanRange}m")
        lines.add("")

        if (newlyDiscovered.isNotEmpty()) {
            lines.add("NEW NODES DISCOVERED:")
            lines.add("")
            newlyDiscovered.forEach { node ->
                val distance = calculateDistance(currentNode, node)
                val signal = generateSignalBar(node.signalStrength)
                val icon = getNodeIcon(node.nodeType)
                val status = "Never visited"

                lines.add("[$icon] ${node.nodeName}")
                lines.add("    ├─ Type: ${node.nodeType.displayName}")
                lines.add("    ├─ Signal: $signal ${node.signalStrength}%")
                lines.add("    ├─ Distance: ${distance}m")
                lines.add("    ├─ Status: $status")
                lines.add("    └─ IP: ${node.ipAddress}")
                lines.add("")
            }
        }

        if (knownNodes.isNotEmpty()) {
            lines.add("KNOWN NODES IN RANGE:")
            lines.add("")
            knownNodes.forEach { node ->
                val distance = calculateDistance(currentNode, node)
                val signal = generateSignalBar(node.signalStrength)
                val icon = getNodeIcon(node.nodeType)
                val state = discoveryManager.getDiscoveryState(
                    session.player.id,
                    node.nodeId
                )

                lines.add("[$icon] ${node.nodeName}")
                lines.add("    ├─ Signal: $signal ${node.signalStrength}%")
                lines.add("    ├─ Distance: ${distance}m")
                lines.add("    ├─ Status: ${formatState(state)}")
                lines.add("    └─ IP: ${node.ipAddress}")
                lines.add("")
            }
        }

        if (newlyDiscovered.isEmpty() && knownNodes.isEmpty()) {
            lines.add("No nodes detected in range.")
            lines.add("Try moving to a different location or upgrading your scanner.")
        }

        return lines.joinToString("\n")
    }

    private fun calculateDistance(from: NetworkNode, to: NetworkNode): Int {
        val dx = (to.coordX - from.coordX).toDouble()
        val dy = (to.coordY - from.coordY).toDouble()
        return kotlin.math.sqrt(dx * dx + dy * dy).toInt()
    }

    private fun getScannerRange(session: GameSession): Int {
        return when (session.player.scannerLevel) {
            1 -> 300
            2 -> 500
            3 -> 1000
            else -> 300
        }
    }

    private fun generateSignalBar(strength: Int): String {
        val filledBlocks = (strength / 10).coerceIn(0, 10)
        return "█".repeat(filledBlocks) + "░".repeat(10 - filledBlocks)
    }

    private fun getNodeIcon(type: NodeType): String {
        return when (type.category) {
            NodeCategory.PUBLIC_ACCESS -> "C"
            NodeCategory.COMMERCIAL -> "S"
            NodeCategory.MEDICAL -> "M"
            NodeCategory.CORPORATE -> "!"
            NodeCategory.INDUSTRIAL -> "I"
            NodeCategory.INFRASTRUCTURE -> "G"
            NodeCategory.RESIDENTIAL -> "R"
            else -> "?"
        }
    }

    private fun formatState(state: DiscoveryState?): String {
        return when (state) {
            DiscoveryState.DISCOVERED -> "Never visited"
            DiscoveryState.CONNECTED -> "CONNECTED (previously visited)"
            DiscoveryState.COMPROMISED -> "COMPROMISED (backdoor installed)"
            DiscoveryState.LOCKED -> "LOCKED (failed access attempt)"
            null -> "Unknown"
            else -> state.name
        }
    }
}
```

#### Example Output
```
SCANNING LOCAL NETWORK...
Current Node: Blue Neon Cafe WiFi
Scan Range: 500m

NEW NODES DISCOVERED:

[S] Sector 7 Pharmacy Guest
    ├─ Type: Pharmacy
    ├─ Signal: ██████░░░░ 60%
    ├─ Distance: 340m
    ├─ Status: Never visited
    └─ IP: 10.42.3.201

[!] NovaCorp Data Terminal
    ├─ Type: Corporate Office
    ├─ Signal: ████░░░░░░ 40%
    ├─ Distance: 480m
    ├─ Status: Never visited
    └─ IP: 10.42.5.12

KNOWN NODES IN RANGE:

[C] Midnight Diner Hotspot
    ├─ Signal: ████████░░ 80%
    ├─ Distance: 150m
    ├─ Status: CONNECTED (previously visited)
    └─ IP: 10.42.3.159
```

#### Deliverable
- Enhanced `ScanCommand` with range detection
- Discovery probability system
- Rich formatted output
- Integration with `DiscoveryManager`

---

### Task 2.3: Map Command
**Time:** 2 days
**Owner:** Kotlin Engine

#### Map Command Features
- Display all discovered nodes
- Show current position
- Filter by type, distance, state
- Sort options
- ASCII visualization (optional)

#### Implementation: `MapCommand.kt`
```kotlin
package com.codecraft.engine.command.commands

import com.codecraft.engine.command.Command
import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.session.GameSession

class MapCommand(
    private val discoveryManager: DiscoveryManager
) : Command {

    override val name = "map"
    override val description = "Display discovered network nodes"
    override val usage = "map [filter] [sort]"

    override fun execute(args: List<String>, session: GameSession): CommandResult {
        val filter = args.getOrNull(0) // type, state, etc.
        val sortBy = args.getOrNull(1) // distance, name, signal

        val discoveredNodes = discoveryManager.getDiscoveredNodes(session.player.id)

        if (discoveredNodes.isEmpty()) {
            return CommandResult(
                output = "No nodes discovered yet. Use 'scan' to find nearby nodes.",
                success = true
            )
        }

        val currentNode = session.network.currentNode

        // Apply filter
        val filteredNodes = when (filter) {
            "public" -> discoveredNodes.filter { it.first.isPublic }
            "secure" -> discoveredNodes.filter { !it.first.isPublic }
            "connected" -> discoveredNodes.filter { it.second == DiscoveryState.CONNECTED }
            "locked" -> discoveredNodes.filter { it.second == DiscoveryState.LOCKED }
            else -> discoveredNodes
        }

        // Apply sort
        val sortedNodes = when (sortBy) {
            "distance" -> filteredNodes.sortedBy { (node, _) ->
                if (currentNode != null) {
                    calculateDistance(currentNode, node)
                } else {
                    0
                }
            }
            "name" -> filteredNodes.sortedBy { it.first.nodeName }
            "signal" -> filteredNodes.sortedByDescending { it.first.signalStrength }
            else -> filteredNodes.sortedBy { it.second.ordinal } // by state
        }

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

    private fun buildMapOutput(
        nodes: List<Pair<NetworkNode, DiscoveryState>>,
        currentNode: NetworkNode?,
        filter: String?,
        sortBy: String?
    ): String {
        val lines = mutableListOf<String>()

        lines.add("═══════════════════════════════════════════════════════")
        lines.add("NETWORK MAP")
        if (currentNode != null) {
            lines.add("Current: ${currentNode.nodeName}")
        }
        if (filter != null) {
            lines.add("Filter: $filter")
        }
        if (sortBy != null) {
            lines.add("Sort: $sortBy")
        }
        lines.add("═══════════════════════════════════════════════════════")
        lines.add("")

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
        lines.add("Total: ${nodes.size} nodes | Use 'map [filter] [sort]' for options")

        return lines.joinToString("\n")
    }

    private fun formatNodeLine(
        node: NetworkNode,
        currentNode: NetworkNode?,
        statusIcon: String
    ): String {
        val icon = getNodeIcon(node.nodeType)
        val distance = if (currentNode != null) {
            val dist = calculateDistance(currentNode, node)
            "${dist}m"
        } else {
            "---"
        }

        val currentMarker = if (node == currentNode) " [YOU]" else ""

        return "  $statusIcon [$icon] ${node.nodeName.padEnd(40)} | $distance$currentMarker"
    }
}
```

#### Example Output
```
═══════════════════════════════════════════════════════
NETWORK MAP
Current: Blue Neon Cafe WiFi
═══════════════════════════════════════════════════════

COMPROMISED (1):
  ✓ [!] NovaCorp Data Terminal                | 480m

CONNECTED (3):
  ◆ [C] Blue Neon Cafe WiFi                   | 0m [YOU]
  ◆ [C] Midnight Diner Hotspot                | 150m
  ◆ [M] Sector 7 Pharmacy Guest               | 340m

DISCOVERED (2):
  ◇ [S] Main St Pawn Shop WiFi                | 620m
  ◇ [I] Industrial Zone Warehouse Network     | 890m

═══════════════════════════════════════════════════════
Total: 6 nodes | Use 'map [filter] [sort]' for options
```

#### Deliverable
- `MapCommand` with filtering and sorting
- Grouped display by state
- Clear current position indicator
- Usage documentation

---

### Task 2.4: Position Tracking
**Time:** 1 day
**Owner:** Kotlin Engine

#### Update Connect Command
```kotlin
// In ConnectCommand.kt, update to:

override fun execute(args: List<String>, session: GameSession): CommandResult {
    // ... existing connection logic ...

    // Update discovery state
    discoveryManager.updateState(
        playerId = session.player.id,
        nodeId = targetNode.nodeId,
        newState = DiscoveryState.CONNECTED
    )

    // Update player position
    session.network.currentNode = targetNode
    session.network.previousNode = previousNode

    // Save position to database
    positionRepository.updatePosition(
        playerId = session.player.id,
        currentNodeId = targetNode.nodeId,
        previousNodeId = previousNode?.nodeId
    )

    return CommandResult(/* ... */)
}
```

#### Position Repository
```kotlin
class PositionRepository(private val database: Database) {

    fun updatePosition(
        playerId: UUID,
        currentNodeId: UUID,
        previousNodeId: UUID?
    ) {
        transaction(database) {
            PlayerNetworkPositionTable.insertOrUpdate {
                it[PlayerNetworkPositionTable.playerId] = playerId
                it[currentNodeId] = currentNodeId
                it[previousNodeId] = previousNodeId
                it[lastPositionUpdate] = System.currentTimeMillis()
            }
        }
    }

    fun getCurrentPosition(playerId: UUID): UUID? {
        return transaction(database) {
            PlayerNetworkPositionTable
                .select { PlayerNetworkPositionTable.playerId eq playerId }
                .singleOrNull()
                ?.get(PlayerNetworkPositionTable.currentNodeId)
        }
    }
}
```

#### Deliverable
- Position tracking on connect
- Database persistence
- Load position on session restore

---

## ✅ PHASE 2 COMPLETION CHECKLIST

### Code Deliverables
- [ ] `DiscoveryManager.kt` with state management
- [ ] Enhanced `ScanCommand.kt` with detection
- [ ] `MapCommand.kt` with filtering
- [ ] `PositionRepository.kt` for tracking
- [ ] Updated `ConnectCommand.kt` to update state

### Testing
- [ ] Discovery state transitions work
- [ ] Scan reveals nodes based on range
- [ ] Detection probability respects distance
- [ ] Map displays all discovered nodes
- [ ] Filters and sorting work correctly
- [ ] Position persists across sessions

### Integration
- [ ] Scan discovers new nodes
- [ ] Connect updates discovery state
- [ ] Map shows accurate current position
- [ ] State persists after logout/login
- [ ] No duplicate discoveries

---

## 🚀 NEXT PHASE

Proceed to **Phase 3: Mesh Topology** (`URBAN_NETWORK_PHASE_3.md`).

Phase 3 implements:
- District-based node clustering
- Gateway system for network entry
- Connection graph between nodes
- Pathfinding and routing

---

**Last Updated:** 2026-02-16
**Status:** Not Started
**Blockers:** Phase 1 completion required
