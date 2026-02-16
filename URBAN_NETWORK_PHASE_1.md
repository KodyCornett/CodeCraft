# 🏗️ PHASE 1: FOUNDATION - Name Generation & Database Schema

**Timeline:** Week 1 (5-7 days)
**Status:** Not Started
**Dependencies:** None
**Blocks:** Phase 2, 3, 4, 5

---

## 🎯 GOALS

1. Create procedural name generation system in Kotlin
2. Design and implement database schema for nodes
3. Generate initial test dataset (100+ nodes)
4. Ensure names are realistic, varied, and memorable

---

## 📋 TASKS BREAKDOWN

### Task 1.1: Design Name Generation System
**Time:** 1 day
**Owner:** Kotlin Engine

#### Subtasks
- [ ] Define business type categories (PUBLIC_ACCESS, COMMERCIAL, INDUSTRIAL, etc.)
- [ ] Create word lists for each category
- [ ] Design name pattern templates
- [ ] Create district/location modifiers
- [ ] Define network suffixes (WiFi, Guest, Hotspot, etc.)

#### Word Lists to Create

**Business Types by Category:**
```kotlin
enum class NodeType {
    // PUBLIC_ACCESS
    CAFE, COFFEE_SHOP, DINER, BAR, LIBRARY, LAUNDROMAT,
    HOTEL, HOSTEL, COMMUNITY_CENTER, INTERNET_CAFE,

    // COMMERCIAL
    PHARMACY, BODEGA, CONVENIENCE_STORE, PAWN_SHOP,
    TECH_SHOP, BOOKSTORE, RECORD_STORE, ARCADE, GYM,
    TATTOO_PARLOR,

    // INDUSTRIAL
    WAREHOUSE, FACTORY, DISTRIBUTION_CENTER, POWER_STATION,
    DATA_CENTER, SERVER_FARM, TELECOM_HUB,

    // MEDICAL
    CLINIC, HOSPITAL, MEDICAL_LAB, URGENT_CARE, DENTAL_OFFICE,

    // CORPORATE
    OFFICE_BUILDING, TECH_STARTUP, LAW_FIRM, ACCOUNTING_FIRM,
    CONSULTING_GROUP, RESEARCH_LAB,

    // INFRASTRUCTURE
    TRAFFIC_CONTROL, SECURITY_STATION, PARKING_GARAGE,
    TRANSIT_HUB, MUNICIPAL_BUILDING
}
```

**Adjectives/Modifiers:**
```
Blue Neon, Red Light, Silver Screen, Golden Gate, Dark Alley,
Midnight, Sunrise, Neon, Digital, Electric, Chrome, Steel, Glass
```

**Street Names:**
```
Main St, River Ave, Park Blvd, Oak Street, Cyber Lane, Data Drive,
Network Way, Fifth Avenue, Market Street, Harbor Road
```

**Districts:**
```
Downtown, Midtown, Uptown, Old Town, Tech District, Financial Quarter,
Industrial Zone, Waterfront, Sector [1-9]
```

**Network Suffixes:**
```
WiFi, Guest, Public, Open, Network, Hotspot, Access Point,
Free WiFi, Wireless
```

#### Name Pattern Examples
```
Pattern 1: [Modifier] [Business] [Suffix]
  "Blue Neon Cafe WiFi"
  "Midnight Diner Hotspot"
  "Chrome Bar Guest"

Pattern 2: [District] [Business] [Suffix]
  "Sector 7 Pharmacy Guest Network"
  "Downtown Laundromat Public Access"
  "Tech District Data Center"

Pattern 3: [Street] [Business] [Suffix]
  "Main St Coffee Shop WiFi"
  "River Ave Hospital Network"
  "Oak Street Bookstore Free WiFi"

Pattern 4: [Company] [Building] [Department] (Corporate only)
  "NovaCorp Tower Executive Network"
  "Zenith Pharma Research Lab Internal"
  "DataCorp HQ Server Room"
```

#### Deliverable
- `NodeNameGenerator.kt` with word lists and generation logic
- Unit tests ensuring variety and uniqueness

---

### Task 1.2: Database Schema Design
**Time:** 1 day
**Owner:** Kotlin Engine

#### New Tables

**1. `network_nodes` - Persistent node definitions**
```sql
CREATE TABLE network_nodes (
    node_id UUID PRIMARY KEY,
    node_name VARCHAR(255) NOT NULL,
    node_type VARCHAR(50) NOT NULL,  -- cafe, pharmacy, corporate, etc.
    district_id UUID REFERENCES network_districts(district_id),
    coord_x INT NOT NULL,
    coord_y INT NOT NULL,
    ip_address VARCHAR(15) NOT NULL,
    signal_strength INT DEFAULT 100,  -- 0-100
    security_level INT DEFAULT 1,     -- 1-5
    is_public BOOLEAN DEFAULT TRUE,
    is_mission_critical BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),

    INDEX idx_coords (coord_x, coord_y),
    INDEX idx_type (node_type),
    INDEX idx_district (district_id)
);
```

**2. `player_discovered_nodes` - Player discovery state**
```sql
CREATE TABLE player_discovered_nodes (
    id UUID PRIMARY KEY,
    player_id UUID NOT NULL REFERENCES players(id),
    node_id UUID NOT NULL REFERENCES network_nodes(node_id),
    discovery_state VARCHAR(50) NOT NULL, -- DISCOVERED, CONNECTED, COMPROMISED, LOCKED
    discovered_at TIMESTAMP DEFAULT NOW(),
    last_accessed TIMESTAMP,
    access_count INT DEFAULT 0,
    player_notes TEXT,

    UNIQUE(player_id, node_id),
    INDEX idx_player (player_id),
    INDEX idx_state (discovery_state)
);
```

**3. `network_districts` - Geographic regions**
```sql
CREATE TABLE network_districts (
    district_id UUID PRIMARY KEY,
    district_name VARCHAR(100) NOT NULL,
    district_type VARCHAR(50) NOT NULL, -- downtown, industrial, residential
    center_x INT NOT NULL,
    center_y INT NOT NULL,
    radius INT NOT NULL,
    ip_prefix VARCHAR(10) NOT NULL,     -- e.g., "10.42" for all nodes in district
    node_density VARCHAR(20) DEFAULT 'MEDIUM', -- LOW, MEDIUM, HIGH
    description TEXT,
    unlock_condition TEXT,              -- NULL = unlocked from start
    created_at TIMESTAMP DEFAULT NOW(),

    INDEX idx_name (district_name)
);
```

**4. `node_connections` - Network topology edges**
```sql
CREATE TABLE node_connections (
    id UUID PRIMARY KEY,
    node_a_id UUID NOT NULL REFERENCES network_nodes(node_id),
    node_b_id UUID NOT NULL REFERENCES network_nodes(node_id),
    distance INT NOT NULL,              -- meters
    connection_quality INT DEFAULT 100, -- 0-100
    connection_type VARCHAR(50) DEFAULT 'DIRECT', -- DIRECT, RELAY, BACKDOOR
    is_public BOOLEAN DEFAULT TRUE,
    is_bidirectional BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),

    INDEX idx_node_a (node_a_id),
    INDEX idx_node_b (node_b_id),
    CHECK (node_a_id != node_b_id)
);
```

**5. `player_network_position` - Current location tracking**
```sql
CREATE TABLE player_network_position (
    player_id UUID PRIMARY KEY REFERENCES players(id),
    current_node_id UUID REFERENCES network_nodes(node_id),
    previous_node_id UUID REFERENCES network_nodes(node_id),
    last_position_update TIMESTAMP DEFAULT NOW()
);
```

#### Deliverable
- SQL migration files for Exposed ORM
- Database initialization in `GameDatabase.kt`
- Repository interfaces for CRUD operations

---

### Task 1.3: Implement Name Generator
**Time:** 2 days
**Owner:** Kotlin Engine

#### File Structure
```
engine/src/main/kotlin/com/codecraft/engine/network/
├── naming/
│   ├── NodeNameGenerator.kt          (main generator)
│   ├── WordLists.kt                   (all word lists)
│   ├── NamingPattern.kt               (pattern definitions)
│   └── NamingRules.kt                 (business logic)
└── domain/
    ├── NetworkNode.kt                 (data class)
    └── DiscoveryState.kt              (enum)
```

#### Implementation: `NodeNameGenerator.kt`
```kotlin
package com.codecraft.engine.network.naming

import kotlin.random.Random

class NodeNameGenerator {

    /**
     * Generate a procedural name for a network node
     *
     * @param nodeType The type of business/location
     * @param district Optional district for location-based names
     * @return A unique, realistic node name
     */
    fun generateName(
        nodeType: NodeType,
        district: District? = null
    ): String {
        return when (nodeType.category) {
            NodeCategory.PUBLIC_ACCESS -> generatePublicAccessName(nodeType, district)
            NodeCategory.COMMERCIAL -> generateCommercialName(nodeType, district)
            NodeCategory.CORPORATE -> generateCorporateName(nodeType)
            NodeCategory.INFRASTRUCTURE -> generateInfrastructureName(nodeType, district)
            NodeCategory.RESIDENTIAL -> generateResidentialName(district)
            else -> generateGenericName(nodeType, district)
        }
    }

    private fun generatePublicAccessName(type: NodeType, district: District?): String {
        val patterns = listOf(
            { "${randomModifier()} ${type.displayName} ${randomSuffix()}" },
            { "${randomStreet()} ${type.displayName} ${randomSuffix()}" },
            { "${district?.name ?: randomDistrict()} ${type.displayName} ${randomSuffix()}" }
        )
        return patterns.random()()
    }

    private fun generateCorporateName(type: NodeType): String {
        val company = randomCorporateName()
        val department = randomDepartment()
        return "$company ${type.displayName} $department"
    }

    private fun generateInfrastructureName(type: NodeType, district: District?): String {
        val location = district?.name ?: randomDistrict()
        return "$location ${type.displayName} System"
    }

    // Helper methods
    private fun randomModifier() = WordLists.MODIFIERS.random()
    private fun randomStreet() = WordLists.STREETS.random()
    private fun randomDistrict() = WordLists.DISTRICTS.random()
    private fun randomSuffix() = WordLists.NETWORK_SUFFIXES.random()
    private fun randomCorporateName() = WordLists.CORPORATE_NAMES.random()
    private fun randomDepartment() = WordLists.DEPARTMENTS.random()

    /**
     * Ensure generated name is unique in the given set
     */
    fun ensureUnique(name: String, existingNames: Set<String>): String {
        var uniqueName = name
        var counter = 2
        while (existingNames.contains(uniqueName)) {
            uniqueName = "$name #$counter"
            counter++
        }
        return uniqueName
    }
}
```

#### Implementation: `WordLists.kt`
```kotlin
package com.codecraft.engine.network.naming

object WordLists {

    val MODIFIERS = listOf(
        "Blue Neon", "Red Light", "Silver Screen", "Golden Gate",
        "Dark Alley", "Midnight", "Sunrise", "Neon", "Digital",
        "Electric", "Chrome", "Steel", "Glass", "Copper", "Iron",
        "Brass", "Bronze", "Platinum", "Diamond", "Ruby"
    )

    val STREETS = listOf(
        "Main St", "River Ave", "Park Blvd", "Oak Street",
        "Cyber Lane", "Data Drive", "Network Way", "Fifth Avenue",
        "Market Street", "Harbor Road", "Pine Street", "Elm Avenue",
        "Maple Drive", "Cedar Lane", "Walnut Way", "Birch Boulevard"
    )

    val DISTRICTS = listOf(
        "Downtown", "Midtown", "Uptown", "Old Town", "Tech District",
        "Financial Quarter", "Industrial Zone", "Waterfront",
        "Sector 1", "Sector 2", "Sector 3", "Sector 4", "Sector 5",
        "Sector 6", "Sector 7", "Sector 8", "Sector 9"
    )

    val NETWORK_SUFFIXES = listOf(
        "WiFi", "Guest", "Public", "Open", "Network", "Hotspot",
        "Access Point", "Free WiFi", "Wireless", "Guest Network",
        "Public Access", "Open Network"
    )

    val CORPORATE_NAMES = listOf(
        "NovaCorp", "Zenith Pharma", "DataCorp", "TechGiant",
        "Megacorp", "CyberDyne Systems", "Apex Industries",
        "Summit Solutions", "Pinnacle Tech", "Vertex Systems",
        "Nexus Corporation", "Quantum Dynamics", "Helix Biotech"
    )

    val DEPARTMENTS = listOf(
        "Executive Network", "Research Lab Internal", "Server Room",
        "Financial Division", "Engineering Wing", "HR Department",
        "Legal Department", "Marketing Division", "Sales Floor",
        "Operations Center", "Security Terminal", "Data Archives"
    )
}
```

#### Unit Tests: `NodeNameGeneratorTest.kt`
```kotlin
package com.codecraft.engine.network.naming

import org.junit.jupiter.api.Test
import kotlin.test.assertTrue
import kotlin.test.assertNotEquals

class NodeNameGeneratorTest {

    private val generator = NodeNameGenerator()

    @Test
    fun `generates unique names for 1000 nodes`() {
        val names = mutableSetOf<String>()
        repeat(1000) {
            val name = generator.generateName(
                nodeType = NodeType.CAFE,
                district = null
            )
            names.add(name)
        }

        // At least 900 unique names out of 1000 (90% uniqueness)
        assertTrue(names.size >= 900, "Generated ${names.size} unique names out of 1000")
    }

    @Test
    fun `public access nodes have network suffixes`() {
        val name = generator.generateName(NodeType.CAFE)
        val suffixes = WordLists.NETWORK_SUFFIXES

        assertTrue(
            suffixes.any { name.contains(it) },
            "Name '$name' should contain a network suffix"
        )
    }

    @Test
    fun `corporate nodes do not have public suffixes`() {
        val name = generator.generateName(NodeType.OFFICE_BUILDING)
        val publicSuffixes = listOf("WiFi", "Guest", "Public", "Open", "Hotspot")

        assertTrue(
            publicSuffixes.none { name.contains(it) },
            "Corporate name '$name' should not contain public suffix"
        )
    }

    @Test
    fun `ensureUnique appends counter to duplicates`() {
        val baseName = "Blue Neon Cafe WiFi"
        val existing = setOf(baseName, "$baseName #2")

        val uniqueName = generator.ensureUnique(baseName, existing)

        assertNotEquals(baseName, uniqueName)
        assertTrue(uniqueName.contains("#"))
    }
}
```

#### Deliverable
- Functional name generator with 90%+ uniqueness
- 20+ unit tests covering all patterns
- Documentation with examples

---

### Task 1.4: Create Node Repository
**Time:** 1 day
**Owner:** Kotlin Engine

#### Implementation: `NodeRepository.kt`
```kotlin
package com.codecraft.engine.network.persistence

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.DiscoveryState
import org.jetbrains.exposed.sql.*
import org.jetbrains.exposed.sql.transactions.transaction
import java.util.UUID

class NodeRepository(private val database: Database) {

    fun saveNode(node: NetworkNode): NetworkNode {
        transaction(database) {
            NetworkNodesTable.insert {
                it[nodeId] = node.nodeId
                it[nodeName] = node.nodeName
                it[nodeType] = node.nodeType.name
                it[districtId] = node.district?.districtId
                it[coordX] = node.coordX
                it[coordY] = node.coordY
                it[ipAddress] = node.ipAddress
                it[signalStrength] = node.signalStrength
                it[securityLevel] = node.securityLevel
                it[isPublic] = node.isPublic
                it[isMissionCritical] = node.isMissionCritical
            }
        }
        return node
    }

    fun getNodeById(nodeId: UUID): NetworkNode? {
        return transaction(database) {
            NetworkNodesTable.select { NetworkNodesTable.nodeId eq nodeId }
                .mapNotNull { it.toNetworkNode() }
                .singleOrNull()
        }
    }

    fun getNodesInRadius(x: Int, y: Int, radius: Int): List<NetworkNode> {
        return transaction(database) {
            NetworkNodesTable.selectAll()
                .mapNotNull { it.toNetworkNode() }
                .filter { node ->
                    val distance = calculateDistance(x, y, node.coordX, node.coordY)
                    distance <= radius
                }
        }
    }

    fun updateDiscoveryState(
        playerId: UUID,
        nodeId: UUID,
        state: DiscoveryState
    ) {
        transaction(database) {
            PlayerDiscoveredNodesTable.insertOrUpdate(
                PlayerDiscoveredNodesTable.playerId,
                PlayerDiscoveredNodesTable.nodeId
            ) {
                it[PlayerDiscoveredNodesTable.playerId] = playerId
                it[PlayerDiscoveredNodesTable.nodeId] = nodeId
                it[discoveryState] = state.name
                it[discoveredAt] = System.currentTimeMillis()
            }
        }
    }

    fun getPlayerDiscoveredNodes(playerId: UUID): List<Pair<NetworkNode, DiscoveryState>> {
        return transaction(database) {
            (NetworkNodesTable innerJoin PlayerDiscoveredNodesTable)
                .select { PlayerDiscoveredNodesTable.playerId eq playerId }
                .map { row ->
                    val node = row.toNetworkNode()!!
                    val state = DiscoveryState.valueOf(row[PlayerDiscoveredNodesTable.discoveryState])
                    node to state
                }
        }
    }

    private fun calculateDistance(x1: Int, y1: Int, x2: Int, y2: Int): Int {
        val dx = (x2 - x1).toDouble()
        val dy = (y2 - y1).toDouble()
        return kotlin.math.sqrt(dx * dx + dy * dy).toInt()
    }

    private fun ResultRow.toNetworkNode(): NetworkNode? {
        return NetworkNode(
            nodeId = this[NetworkNodesTable.nodeId],
            nodeName = this[NetworkNodesTable.nodeName],
            nodeType = NodeType.valueOf(this[NetworkNodesTable.nodeType]),
            district = null, // Load separately if needed
            coordX = this[NetworkNodesTable.coordX],
            coordY = this[NetworkNodesTable.coordY],
            ipAddress = this[NetworkNodesTable.ipAddress],
            signalStrength = this[NetworkNodesTable.signalStrength],
            securityLevel = this[NetworkNodesTable.securityLevel],
            isPublic = this[NetworkNodesTable.isPublic],
            isMissionCritical = this[NetworkNodesTable.isMissionCritical]
        )
    }
}
```

#### Deliverable
- Repository with CRUD operations
- Spatial queries (radius search)
- Discovery state management
- Integration tests

---

### Task 1.5: Generate Test Dataset
**Time:** 0.5 days
**Owner:** Kotlin Engine

#### Script: `GenerateTestNodes.kt`
```kotlin
package com.codecraft.engine.network.generation

import com.codecraft.engine.network.naming.NodeNameGenerator
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.domain.NetworkNode
import java.util.UUID

class NetworkTestDataGenerator(
    private val nameGenerator: NodeNameGenerator,
    private val nodeRepository: NodeRepository
) {

    fun generateTestDataset(count: Int = 100): List<NetworkNode> {
        val nodes = mutableListOf<NetworkNode>()
        val existingNames = mutableSetOf<String>()

        repeat(count) {
            val nodeType = NodeType.values().random()
            var nodeName = nameGenerator.generateName(nodeType)
            nodeName = nameGenerator.ensureUnique(nodeName, existingNames)
            existingNames.add(nodeName)

            val node = NetworkNode(
                nodeId = UUID.randomUUID(),
                nodeName = nodeName,
                nodeType = nodeType,
                district = null,
                coordX = kotlin.random.Random.nextInt(0, 5000),
                coordY = kotlin.random.Random.nextInt(0, 5000),
                ipAddress = generateRandomIP(),
                signalStrength = kotlin.random.Random.nextInt(60, 100),
                securityLevel = when (nodeType.category) {
                    NodeCategory.PUBLIC_ACCESS -> 1
                    NodeCategory.COMMERCIAL -> 2
                    NodeCategory.CORPORATE -> kotlin.random.Random.nextInt(3, 5)
                    NodeCategory.INFRASTRUCTURE -> 5
                    else -> 2
                },
                isPublic = nodeType.category in listOf(
                    NodeCategory.PUBLIC_ACCESS,
                    NodeCategory.COMMERCIAL
                ),
                isMissionCritical = false
            )

            nodeRepository.saveNode(node)
            nodes.add(node)
        }

        println("Generated ${nodes.size} test nodes")
        println("Unique names: ${existingNames.size}")
        println("Sample names:")
        nodes.take(10).forEach { println("  - ${it.nodeName}") }

        return nodes
    }

    private fun generateRandomIP(): String {
        return "10.${kotlin.random.Random.nextInt(1, 255)}.${kotlin.random.Random.nextInt(1, 255)}.${kotlin.random.Random.nextInt(1, 255)}"
    }
}
```

#### Deliverable
- 100+ test nodes in database
- Name variety validation report
- Sample output for verification

---

## ✅ PHASE 1 COMPLETION CHECKLIST

### Code Deliverables
- [ ] `NodeNameGenerator.kt` with full word lists
- [ ] Database tables created (5 tables)
- [ ] `NodeRepository.kt` with CRUD operations
- [ ] `NetworkNode.kt` data class
- [ ] `DiscoveryState.kt` enum
- [ ] Test data generation script

### Testing
- [ ] 20+ unit tests for name generation
- [ ] Name uniqueness >= 90% for 1000 nodes
- [ ] All node types have appropriate patterns
- [ ] Database queries work correctly
- [ ] Spatial radius search performs well
- [ ] Discovery state persistence works

### Documentation
- [ ] Code comments and KDoc
- [ ] Example names for each pattern
- [ ] Database schema diagrams
- [ ] Repository usage examples

### Verification
- [ ] Run: `./gradlew test` - all tests pass
- [ ] Generate 1000 nodes - inspect variety
- [ ] Query database - verify schema
- [ ] No hardcoded mission nodes yet (that's Phase 5)

---

## 🚀 NEXT PHASE

Once Phase 1 is complete, proceed to **Phase 2: Discovery System** (`URBAN_NETWORK_PHASE_2.md`).

Phase 2 will implement:
- Enhanced `scan` command
- "Fog of war" discovery mechanics
- `map` command for visualization
- Cross-session persistence

---

**Last Updated:** 2026-02-16
**Status:** Not Started
**Blockers:** None
