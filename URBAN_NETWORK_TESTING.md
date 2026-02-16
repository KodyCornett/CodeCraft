# ✅ URBAN NETWORK SYSTEM - TESTING CHECKLIST

**Purpose:** Comprehensive testing checklist for all phases of the Urban Network Exploration System.

---

## 📋 TESTING STRATEGY

### Test Pyramid
```
                    ┌────────────────┐
                    │  E2E Tests     │  (10%)
                    │  Player Flows  │
                    └────────────────┘
                 ┌─────────────────────┐
                 │  Integration Tests  │  (30%)
                 │  API & Services     │
                 └─────────────────────┘
              ┌──────────────────────────┐
              │    Unit Tests            │  (60%)
              │    Core Logic            │
              └──────────────────────────┘
```

---

## 🏗️ PHASE 1: FOUNDATION TESTS

### Name Generation (Unit Tests)

#### NodeNameGeneratorTest.kt
- [ ] **Uniqueness**: Generate 1000 names, verify >= 90% unique
- [ ] **Pattern Variety**: Each pattern (modifier, street, district) used at least once
- [ ] **Public Suffixes**: Public nodes contain WiFi/Guest/Hotspot/etc.
- [ ] **Corporate Format**: Corporate nodes do NOT contain public suffixes
- [ ] **Infrastructure Format**: Infrastructure nodes follow "Location + Type + System"
- [ ] **Residential Format**: Residential nodes contain address/building name
- [ ] **Name Length**: All names <= 255 characters
- [ ] **Special Characters**: Names do not contain quotes, backslashes, or SQL injection
- [ ] **Uniqueness Enforcement**: `ensureUnique()` appends counter for duplicates

### Database Schema (Integration Tests)

#### DatabaseSchemaTest.kt
- [ ] **Table Creation**: All 5 tables created successfully
- [ ] **Indexes**: All indexes exist (coords, type, district, player_id)
- [ ] **Foreign Keys**: All references enforced (district_id, node_id, player_id)
- [ ] **Unique Constraints**: (player_id, node_id) unique in player_discovered_nodes
- [ ] **Default Values**: Defaults apply (signal_strength=100, security_level=1)
- [ ] **Nullable Fields**: district_id, unlock_condition, player_notes allow NULL

#### NodeRepositoryTest.kt
- [ ] **Save Node**: saveNode() persists to database
- [ ] **Get Node by ID**: getNodeById() retrieves correct node
- [ ] **Get Nodes in Radius**: getNodesInRadius() returns only nodes within distance
- [ ] **Radius Edge Cases**: Nodes exactly on boundary included
- [ ] **Empty Radius**: Returns empty list when no nodes in range
- [ ] **Update Discovery State**: updateDiscoveryState() inserts or updates
- [ ] **Get Player Discoveries**: getPlayerDiscoveredNodes() returns only player's nodes
- [ ] **Isolation**: Player A discoveries do NOT appear for Player B

### Test Dataset

#### NetworkTestDataGeneratorTest.kt
- [ ] **Generate 100 Nodes**: Produces exactly 100 nodes
- [ ] **Name Uniqueness**: No duplicate names in dataset
- [ ] **IP Address Format**: All IPs match `10.x.x.x` format
- [ ] **Coordinate Range**: All coords within 0-5000 bounds
- [ ] **Security Levels**: Public nodes have security=1, corporate 3-5
- [ ] **Signal Strength**: All signals 60-100
- [ ] **Type Distribution**: All node types represented

---

## 🔍 PHASE 2: DISCOVERY SYSTEM TESTS

### Discovery State Management (Unit Tests)

#### DiscoveryManagerTest.kt
- [ ] **Initial Discovery**: discoverNode() sets state to DISCOVERED
- [ ] **State Transition**: DISCOVERED → CONNECTED on connection
- [ ] **Compromised State**: Can transition to COMPROMISED
- [ ] **Locked State**: Failed access sets LOCKED
- [ ] **Persistence**: State persists after session end/restart
- [ ] **Duplicate Discovery**: Re-discovering same node does not duplicate entry
- [ ] **Get by State**: getNodesByState() filters correctly
- [ ] **Is Discovered Check**: isDiscovered() returns true/false correctly

### Scan Command (Integration Tests)

#### ScanCommandTest.kt
- [ ] **Scan Reveals Nodes**: Nodes within range discovered
- [ ] **Out of Range Ignored**: Nodes beyond range not revealed
- [ ] **Already Discovered Skipped**: Scan does not re-discover known nodes
- [ ] **Detection Probability**: Weak signal nodes have lower detection chance
- [ ] **Stealth Nodes**: Non-public nodes harder to detect (30% penalty)
- [ ] **Exposure Increase**: Scan adds +2.0 exposure
- [ ] **Signal Bars**: Signal strength displayed as progress bar (████░░)
- [ ] **Distance Calculation**: Accurate distance shown for each node
- [ ] **No Current Node**: Scan fails with error if not connected

### Map Command (Integration Tests)

#### MapCommandTest.kt
- [ ] **Display Discovered Nodes**: Shows all player's discoveries
- [ ] **Current Node Marker**: [YOU] indicator on current node
- [ ] **Grouped by State**: Nodes grouped (COMPROMISED, CONNECTED, DISCOVERED, LOCKED)
- [ ] **Filter by Type**: `map public` shows only public nodes
- [ ] **Filter by State**: `map connected` shows only visited nodes
- [ ] **Sort by Distance**: `map distance` sorts by proximity to current node
- [ ] **Sort by Name**: `map name` alphabetically sorts
- [ ] **Sort by Signal**: `map signal` sorts by signal strength
- [ ] **Empty Map**: Shows helpful message when no discoveries

### Position Tracking (Integration Tests)

#### PositionRepositoryTest.kt
- [ ] **Update Position**: updatePosition() saves current node
- [ ] **Get Current Position**: getCurrentPosition() retrieves correct node ID
- [ ] **Previous Position**: Tracks previous node for disconnect
- [ ] **Timestamp Update**: lastPositionUpdate refreshed on change
- [ ] **Persistence**: Position survives session restart

---

## 🕸️ PHASE 3: MESH TOPOLOGY TESTS

### District Generation (Unit Tests)

#### DistrictGeneratorTest.kt
- [ ] **High Density**: Generates 8-15 gateway nodes for HIGH density
- [ ] **Medium Density**: Generates 5-10 gateway nodes for MEDIUM density
- [ ] **Low Density**: Generates 3-6 gateway nodes for LOW density
- [ ] **Clustering**: Nodes clustered near district center (not scattered)
- [ ] **Central Bias**: Gateway nodes closer to center than private nodes
- [ ] **IP Prefix**: All nodes use district's IP prefix (e.g., "10.42.x.x")
- [ ] **Type Match**: Tech Hub has cafes, Industrial has warehouses
- [ ] **Security Levels**: Corporate nodes have security 3-5
- [ ] **Public vs Private**: Correct isPublic flag for each category

### Gateway System (Integration Tests)

#### GatewayManagerTest.kt
- [ ] **Activate Gateway**: Connecting to public node reveals nearby nodes
- [ ] **Reveal Radius**: Only nodes within 500m revealed
- [ ] **Public Only Revealed**: Private nodes not auto-revealed
- [ ] **High Signal Threshold**: Only nodes with signal >= 70 auto-revealed
- [ ] **Non-Gateway Ignored**: Connecting to private node does NOT trigger reveal
- [ ] **Multiple Gateways**: Connecting to different gateways reveals different sets
- [ ] **Already Discovered Skipped**: Gateway does not re-discover known nodes

### Connection Graph (Unit Tests)

#### ConnectionGraphBuilderTest.kt
- [ ] **Public to Public**: Public nodes within 300m connect
- [ ] **Private to Gateway**: Private nodes connect to nearest gateway (within 500m)
- [ ] **Corporate to Corporate**: Corporate nodes in same district connect (within 400m)
- [ ] **Bidirectional**: Connections marked as bidirectional
- [ ] **No Duplicates**: Connection A→B not duplicated as B→A
- [ ] **Connection Quality**: Quality decreases with distance
- [ ] **Connection Types**: DIRECT, RELAY, ENCRYPTED assigned correctly

### Pathfinding (Unit Tests)

#### PathfindingServiceTest.kt
- [ ] **Finds Shortest Path**: Returns optimal route between connected nodes
- [ ] **Multiple Hops**: Correctly handles 3+ hop paths
- [ ] **No Path Exists**: Returns null when nodes disconnected
- [ ] **Distance Calculation**: Total distance accurate
- [ ] **Risk Calculation**: Risk score reflects node security levels
- [ ] **Path Reconstruction**: Correct order from start to end
- [ ] **Optimal Route**: Chooses low-risk over short-distance when beneficial

### Route Command (Integration Tests)

#### RouteCommandTest.kt
- [ ] **Display Path**: Shows step-by-step route
- [ ] **Hop Count**: Correct number of hops displayed
- [ ] **Distance Total**: Accurate total distance
- [ ] **Risk Estimate**: Shows estimated exposure risk
- [ ] **Node Not Found**: Returns error for non-existent target
- [ ] **No Current Node**: Returns error if not connected
- [ ] **Format Output**: Path formatted with arrows (↓) and icons ([C])

---

## 🎨 PHASE 4: VISUAL POLISH TESTS

### Icon System (Unit Tests)

#### NodeVisualizerTest.php (Laravel)
- [ ] **Icon Mapping**: Each node type maps to correct icon (C, M, !, etc.)
- [ ] **Color by Security**: Colors assigned by security level (green=1, red=5)
- [ ] **State Colors**: COMPROMISED=purple, LOCKED=gray, others by security
- [ ] **Icon Consistency**: Same type always gets same icon

### Canvas Rendering (E2E Tests)

#### NodeManagerUITest.php (Laravel Dusk)
- [ ] **Canvas Loads**: Canvas element present and sized correctly
- [ ] **Nodes Render**: All discovered nodes visible on canvas
- [ ] **Current Node Highlight**: Player's current node has white border
- [ ] **Node Click**: Clicking node shows details panel
- [ ] **Zoom Controls**: Mousewheel zooms in/out (0.5x - 2.0x)
- [ ] **Pan Controls**: Drag to pan map
- [ ] **Connection Lines**: Lines drawn between connected nodes
- [ ] **Signal Strength Lines**: Line thickness/style reflects signal quality
- [ ] **Grid Background**: Grid visible for spatial reference

### Map Refresh (Integration Tests)

#### NetworkMapServiceTest.php
- [ ] **Auto Refresh**: Map updates every 10 seconds
- [ ] **Manual Refresh**: Scan button triggers immediate refresh
- [ ] **New Nodes Appear**: Newly discovered nodes added to map
- [ ] **State Updates**: Node state changes reflected (DISCOVERED → CONNECTED)
- [ ] **Performance**: 100+ nodes render without lag

---

## 🎮 PHASE 5: MISSION INTEGRATION TESTS

### Story Node Placement (Integration Tests)

#### MissionNodeTest.kt
- [ ] **Pre-placed Nodes**: Mission-critical nodes exist at fixed locations
- [ ] **Name Match**: Mission text references match actual node names
- [ ] **Unlock Conditions**: District unlock conditions enforce progression
- [ ] **Scanner Upgrades**: Scanner level affects scan range (300m → 500m → 1000m)

### Exposure Integration (Integration Tests)

#### DetectionWithNavigationTest.kt
- [ ] **Scan Exposure**: Scan command adds +2.0 exposure
- [ ] **Connect Exposure**: Connect command adds +5.0 exposure
- [ ] **Public Node Bonus**: Lower exposure increase in public nodes
- [ ] **Corporate Node Penalty**: Higher exposure increase in corporate nodes
- [ ] **Sentinel Tracking**: Detection rolls respect node security level
- [ ] **Shield Blocks Exposure**: Shield prevents exposure from navigation

---

## 🔄 END-TO-END PLAYER FLOWS

### Flow 1: First Discovery
```
Steps:
1. New player starts at localhost
2. Runs `scan` command
3. Discovers 3-5 public nodes
4. Discoveries persist to database
5. `map` command shows discovered nodes
6. Player connects to one
7. Gateway activates, reveals more nodes

Verification:
- [ ] All discoveries persist
- [ ] Map shows correct state
- [ ] Gateway reveals additional nodes
- [ ] Exposure increases correctly
```

### Flow 2: Multi-Hop Navigation
```
Steps:
1. Player at Blue Neon Cafe
2. Runs `route "NovaCorp Data Terminal"`
3. Route shows 3-hop path
4. Player connects through each hop
5. Reaches target node

Verification:
- [ ] Route is optimal
- [ ] Each connection updates position
- [ ] Final node reached
- [ ] Exposure accumulated correctly
```

### Flow 3: District Unlock
```
Steps:
1. Player completes Mission 2
2. Industrial Zone unlocks
3. Player scans from Downtown
4. No Industrial nodes visible (out of range)
5. Player upgrades scanner
6. Scan reveals Industrial nodes

Verification:
- [ ] Unlock condition checked
- [ ] Nodes only visible after unlock
- [ ] Scanner upgrade increases range
```

### Flow 4: Session Persistence
```
Steps:
1. Player discovers 10 nodes
2. Connects to 3 nodes
3. Logs out
4. Logs back in
5. `map` command runs

Verification:
- [ ] All discoveries persisted
- [ ] Connection states preserved
- [ ] Current position restored
```

---

## 🚀 PERFORMANCE TESTS

### Scalability

#### Large Network Tests
- [ ] **1000 Nodes**: World generation completes in < 30 seconds
- [ ] **Scan Performance**: Scan on 1000-node world returns in < 500ms
- [ ] **Map Rendering**: 100+ nodes render in < 1 second
- [ ] **Pathfinding Performance**: Route calculation in < 100ms for 5-hop path

#### Database Queries
- [ ] **Radius Query**: getNodesInRadius() with 1000 nodes < 100ms
- [ ] **Discovery Query**: getPlayerDiscoveredNodes() < 50ms
- [ ] **Spatial Index**: Coordinate index speeds up radius queries

### Memory

#### Memory Tests
- [ ] **Session Size**: GameSession object < 10 MB
- [ ] **Node Cache**: Caching reduces query load by 50%+
- [ ] **Connection Graph**: Full graph fits in memory (< 100 MB for 1000 nodes)

---

## 🛡️ SECURITY TESTS

### Input Validation
- [ ] **SQL Injection**: Node names sanitized (no '; DROP TABLE)
- [ ] **XSS Prevention**: Node names escaped in UI
- [ ] **Path Traversal**: Coordinate bounds enforced (0-5000)
- [ ] **Rate Limiting**: Scan command limited to 1/second

### Data Isolation
- [ ] **Player Isolation**: Player A cannot see Player B's discoveries
- [ ] **Session Isolation**: Different sessions have separate state
- [ ] **Discovery Privacy**: getPlayerDiscoveredNodes() filters by player_id

---

## 📊 TESTING METRICS

### Code Coverage Targets
- [ ] **Kotlin Engine**: >= 80% line coverage
- [ ] **Laravel Controllers**: >= 70% line coverage
- [ ] **JavaScript**: >= 60% line coverage

### Test Counts
- [ ] **Unit Tests**: >= 100
- [ ] **Integration Tests**: >= 40
- [ ] **E2E Tests**: >= 10

---

## ✅ FINAL VERIFICATION

### Pre-Merge Checklist
- [ ] All unit tests pass (`./gradlew test`)
- [ ] All integration tests pass
- [ ] E2E flows complete successfully
- [ ] Code coverage meets targets
- [ ] No SQL injection vulnerabilities
- [ ] Performance benchmarks met
- [ ] Documentation updated
- [ ] CHANGELOG.md updated

### Deployment Checklist
- [ ] Database migrations tested
- [ ] Rollback strategy documented
- [ ] Monitor logs for errors
- [ ] Performance monitoring enabled

---

**Last Updated:** 2026-02-16
**Status:** Ready for Use
**Version:** 1.0
