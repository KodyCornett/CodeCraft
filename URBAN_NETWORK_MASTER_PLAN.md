# 🗺️ URBAN NETWORK EXPLORATION SYSTEM - MASTER PLAN

## 📋 EXECUTIVE SUMMARY

Transform the node navigation system from abstract network nodes into a persistent, explorable urban digital landscape. Players discover and map a living city of networked devices, businesses, and infrastructure.

## 🎯 CORE OBJECTIVES

1. **Procedural Generation** - Create realistic, varied node names and network topology
2. **Persistent Discovery** - "Fog of war" system where discoveries persist across sessions
3. **Urban Geography** - Nodes organized in districts with logical clustering
4. **Visual Clarity** - Enhanced map display with icons, colors, and connection visualization
5. **Story Integration** - Seamless integration with mission system and narrative

## 🏗️ ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────┐
│                     KOTLIN ENGINE                           │
│  - Procedural name generation                               │
│  - Network topology generation                              │
│  - Discovery state management                               │
│  - Persistence layer (SQLite)                               │
│  - Routing & pathfinding                                    │
└─────────────────────┬───────────────────────────────────────┘
                      │ HTTP/WebSocket API
┌─────────────────────▼───────────────────────────────────────┐
│                  LARAVEL BACKEND                            │
│  - API endpoints for network operations                     │
│  - UI state management                                      │
│  - Map data formatting                                      │
└─────────────────────┬───────────────────────────────────────┘
                      │ JSON API
┌─────────────────────▼───────────────────────────────────────┐
│                   FRONTEND (Alpine.js)                      │
│  - Canvas-based map rendering                               │
│  - Node visualization                                       │
│  - Interactive exploration                                  │
│  - Real-time updates                                        │
└─────────────────────────────────────────────────────────────┘
```

## 📅 IMPLEMENTATION PHASES

### Phase 1: Foundation (Week 1)
**Status:** Not Started
**Focus:** Name generation, database schema, basic node creation

**Deliverables:**
- ✅ Procedural name generator (Kotlin)
- ✅ Database schema with migrations
- ✅ 100+ test nodes generated
- ✅ Unit tests for name variety

**Docs:** `URBAN_NETWORK_PHASE_1.md`

---

### Phase 2: Discovery System (Week 2)
**Status:** Not Started
**Focus:** Fog of war, persistence, scan command

**Deliverables:**
- ✅ Enhanced `scan` command
- ✅ Discovery state tracking
- ✅ `map` command with filtered display
- ✅ Cross-session persistence

**Docs:** `URBAN_NETWORK_PHASE_2.md`

---

### Phase 3: Mesh Topology (Week 3)
**Status:** Not Started
**Focus:** District clustering, gateway system, routing

**Deliverables:**
- ✅ District-based node generation
- ✅ Gateway mechanics
- ✅ Connection graph system
- ✅ `route` command (pathfinding)

**Docs:** `URBAN_NETWORK_PHASE_3.md`

---

### Phase 4: Visual Polish (Week 4)
**Status:** Not Started
**Focus:** Enhanced UI, icons, atmosphere

**Deliverables:**
- ✅ Icon system for node types
- ✅ Color coding by security level
- ✅ Ambient traffic/ghost nodes
- ✅ Signal strength visualization

**Docs:** `URBAN_NETWORK_PHASE_4.md`

---

### Phase 5: Mission Integration (Week 5)
**Status:** Not Started
**Focus:** Story nodes, progression, detection

**Deliverables:**
- ✅ Pre-placed mission-critical nodes
- ✅ Progressive district unlocking
- ✅ Exposure tied to node type
- ✅ Mission 1 uses named nodes

**Docs:** `URBAN_NETWORK_PHASE_5.md`

---

## 🔧 TECHNICAL SPECIFICATIONS

### Kotlin Engine Components
- `NodeNameGenerator` - Procedural naming
- `NetworkManager` - Node generation & discovery
- `DistrictGenerator` - Cluster-based topology
- `NodeRepository` - Persistence layer
- `PathfindingService` - Routing algorithms

**Full Spec:** `URBAN_NETWORK_KOTLIN_SPEC.md`

### Laravel UI Components
- `NetworkController` - API endpoints
- `NetworkMapService` - Data formatting
- `node-manager.js` - Canvas rendering
- Blade components for map display

**Full Spec:** `URBAN_NETWORK_LARAVEL_SPEC.md`

---

## 📊 SUCCESS METRICS

### Technical Metrics
- [ ] 1000+ unique procedural names generated
- [ ] Discovery state persists across sessions
- [ ] Scan reveals nodes within 500m radius
- [ ] Map renders 100+ nodes without lag
- [ ] Routing finds optimal path in <100ms

### Player Experience Metrics
- [ ] Players naturally discover network topology
- [ ] Node names feel realistic and memorable
- [ ] Map becomes familiar "mental model" of city
- [ ] Discovery creates exploration incentive
- [ ] Story missions reference recognizable nodes

---

## 🚧 DEPENDENCIES & RISKS

### Dependencies
1. **Database Schema Changes** - Requires migration, affects persistence
2. **Command System** - `scan`, `map`, `route`, `connect` all need updates
3. **Mission System** - Story nodes must be pre-placed
4. **UI Rendering** - Canvas performance critical for many nodes

### Risks & Mitigation
| Risk | Impact | Mitigation |
|------|--------|------------|
| **Performance with 1000+ nodes** | High | Spatial indexing, render only visible nodes |
| **Name repetition** | Medium | Large word lists, uniqueness validation |
| **Save/load compatibility** | High | Version migrations, backward compatibility |
| **Story node conflicts** | Medium | Reserve node IDs for missions |

---

## 📝 DOCUMENT STRUCTURE

```
URBAN_NETWORK_MASTER_PLAN.md (this file)
├── URBAN_NETWORK_PHASE_1.md (Foundation)
├── URBAN_NETWORK_PHASE_2.md (Discovery)
├── URBAN_NETWORK_PHASE_3.md (Topology)
├── URBAN_NETWORK_PHASE_4.md (Visual)
├── URBAN_NETWORK_PHASE_5.md (Integration)
├── URBAN_NETWORK_KOTLIN_SPEC.md (Engine implementation)
├── URBAN_NETWORK_LARAVEL_SPEC.md (UI implementation)
└── URBAN_NETWORK_TESTING.md (Testing checklist)
```

---

## 🎮 PLAYER-FACING CHANGES

### Before (Current)
```
> scan
Scanning network...
Found 3 nodes:
- 10.42.3.158
- 10.42.5.12
- 10.48.1.204

> connect 10.42.3.158
Connected to node.
```

### After (Goal)
```
> scan
SCANNING LOCAL NETWORK...
Signal strength: Strong | Range: 500m

NODES DISCOVERED:

[C] Blue Neon Cafe WiFi
    ├─ Signal: ████████░░ 80%
    ├─ Distance: 120m
    └─ Status: Never visited

[M] Sector 7 Pharmacy Guest
    ├─ Signal: ██████░░░░ 60%
    ├─ Distance: 340m
    └─ Status: CONNECTED (previously visited)

[!] NovaCorp Data Terminal
    ├─ Signal: ████░░░░░░ 40%
    ├─ Distance: 480m
    └─ Status: LOCKED (failed access)

> connect "Blue Neon Cafe WiFi"
Establishing connection to Blue Neon Cafe WiFi...
Connected. You are now at: Blue Neon Cafe WiFi
```

---

## 🔄 INTEGRATION WITH EXISTING SYSTEMS

### ✅ Compatible With
- **Mission System** - Story nodes pre-placed, procedural fills gaps
- **Exposure/Detection** - Node type affects baseline exposure
- **Sentinel** - Tracks player across network hops
- **Save/Load** - Discovery state persists in player save
- **Firewall** - Connection quality affects firewall drain

### ⚠️ Requires Updates
- **`scan` command** - Now returns named nodes with metadata
- **`connect` command** - Accepts node names, updates position
- **Node Manager UI** - Canvas rendering, icon system
- **Database schema** - New tables for nodes, districts, discoveries

---

## 📖 GETTING STARTED

### For Developers
1. Read `URBAN_NETWORK_PHASE_1.md` for immediate next steps
2. Review `URBAN_NETWORK_KOTLIN_SPEC.md` for engine architecture
3. Review `URBAN_NETWORK_LARAVEL_SPEC.md` for UI architecture
4. Run tests in `URBAN_NETWORK_TESTING.md` after each phase

### For Project Managers
- Each phase is ~1 week, 5 weeks total
- Phases 1-3 are foundational (can't be skipped)
- Phases 4-5 are polish (can be deferred if needed)
- Testing checklist must pass before merging to `develop`

---

## 🚀 NEXT STEPS

1. **Review master plan** - Ensure alignment with project vision
2. **Start Phase 1** - Open `URBAN_NETWORK_PHASE_1.md`
3. **Set up branch** - `feature/urban-network-exploration`
4. **Begin implementation** - Start with name generator

---

**Last Updated:** 2026-02-16
**Status:** Planning Complete, Ready for Implementation
**Owner:** Development Team
