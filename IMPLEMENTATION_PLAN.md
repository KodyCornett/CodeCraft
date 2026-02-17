# IMPLEMENTATION PLAN - TERMINAL HACKING GAME

## PROJECT OVERVIEW

A text-based terminal hacking game built with:

- **Backend/Engine:** Kotlin
- **Frontend/UI:** Laravel
- **Architecture:** Separation of concerns, modular design

---

## TABLE OF CONTENTS

1. [Core System Architecture](#1-core-system-architecture)
2. [Data Models](#2-data-models)
3. [Game State Management](#3-game-state-management)
4. [Command System](#4-command-system)
5. [Tool & Loadout System](#5-tool--loadout-system)
6. [Mission System](#6-mission-system)
7. [Combat System](#7-combat-system)
8. [Defrag System](#8-defrag-system)
9. [Economy & Progression](#9-economy--progression)
10. [Machine & Upgrade System](#10-machine--upgrade-system)
11. [UI/UX Layer](#11-uiux-layer)
12. [Implementation Phases](#12-implementation-phases)
13. [Technical Considerations](#13-technical-considerations)
14. [Data Schemas](#14-data-schemas)
15. [Separation of Concerns Checklist](#15-separation-of-concerns-checklist)
16. [API Contract](#16-api-contract)
17. [Implementation Priorities](#17-implementation-priorities)
18. [Success Metrics](#18-success-metrics)
19. [Final Notes](#19-final-notes)

---

## 1. CORE SYSTEM ARCHITECTURE

### 1.1 High-Level Components

```
┌─────────────────────────────────────────────────────────────┐
│                      LARAVEL (UI LAYER)                     │
│  - Terminal interface rendering                              │
│  - Input handling                                            │
│  - Session management                                        │
│  - Visual feedback (progress bars, colors, animations)      │
└─────────────────────────────────────────────────────────────┘
                            ↕ HTTP/WebSocket
┌─────────────────────────────────────────────────────────────┐
│                    KOTLIN (GAME ENGINE)                      │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Game State   │  │   Command    │  │   Mission    │      │
│  │   Manager     │  │   Processor  │  │   Engine     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Combat      │  │    Defrag    │  │   Economy    │      │
│  │   System      │  │    System    │  │   Manager    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Loadout      │  │   Machine    │  │  Progression │      │
│  │   Manager     │  │   Manager    │  │   Tracker    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                            ↕
┌─────────────────────────────────────────────────────────────┐
│                    DATABASE (PERSISTENCE)                    │
│  - Player state                                              │
│  - Mission data                                              │
│  - Tool ownership                                            │
│  - Game configuration                                        │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Communication Pattern

```
Request Flow:
  User Input (Laravel)
    → Command Parser (Kotlin)
    → Game State Validator (Kotlin)
    → Command Executor (Kotlin)
    → State Update (Kotlin)
    → Response Formatter (Kotlin)
    → UI Update (Laravel)
```

### 1.3 Separation of Concerns

**Laravel Responsibilities:**
- Terminal UI rendering
- Input capture and sanitization
- Session management
- WebSocket connections for real-time updates
- Visual feedback (colors, formatting, animations)
- Asset delivery (images for documents, etc.)

**Kotlin Responsibilities:**
- All game logic
- State management
- Rule enforcement
- Calculations (exposure, firewall, detection)
- Mission flow control
- Combat resolution
- Defrag puzzle generation
- Economy calculations
- Progression tracking

**Database Responsibilities:**
- Persistent storage
- Player state
- Mission definitions
- Tool configurations
- Machine specifications

---

## 2. DATA MODELS

### 2.1 Core Entities

#### Player State

```
PlayerState:
  - playerId: UUID
  - currentMachine: Machine
  - credits: Int
  - currentNode: String (home / mission)
  - gameProgress: ProgressTracker

MachineState:
  - machineType: MachineType enum
  - currentFirewall: Int (0-100+)
  - maxFirewall: Int
  - residualExposure: Int (0-100)
  - storageLevel: Int (1-4)
  - storageSlots: Int
  - flags: List<SentinelFlag>
  - damageState: DamageState

ProgressTracker:
  - missionsCompleted: Int
  - totalCreditsEarned: Long
  - achievements: List<Achievement>
  - sentinelHeatLevel: Int
  - contactRelationships: Map<ContactId, TrustLevel>
```

#### Tool Ownership

```
ToolInventory:
  - playerId: UUID
  - ownedTools: Map<ToolType, ToolVersion>
    // ToolType: CLOAK, ROLLBACK, GHOST_PROTOCOL, etc.
    // ToolVersion: V1, V2, V3, or null (not owned)
  - consumablesInStock: Map<ToolType, Int>
    // How many consumables player owns but hasn't used

Loadout:
  - loadoutSlots: List<LoadoutSlot>
  - totalSlotsUsed: Int
  - totalSlotsAvailable: Int

LoadoutSlot:
  - tool: Tool (permanent or consumable)
  - slotsConsumed: Int
  - state: SlotState (READY, SPENT, COOLDOWN)
```

#### Mission State

```
MissionState:
  - missionId: String
  - type: MissionType enum
  - difficulty: Difficulty enum
  - payout: Int
  - currentPhase: MissionPhase enum
  - currentExposure: Int (0-100+)
  - currentFirewall: Int
  - filesDownloaded: List<File>
  - commandHistory: List<CommandRecord>
  - sessionStartTime: Timestamp
  - targetNode: NetworkNode

CommandRecord:
  - timestamp: Long
  - command: String
  - exposureCost: Int
  - firewallCost: Int
  - result: CommandResult
```

#### Combat State

```
CombatState:
  - combatId: UUID
  - combatType: CombatType enum (SENTINEL, RIVAL_HACKER, AMBUSH)
  - systemIntegrity: Int (0-100)
  - threatPressure: Int (0-100)
  - attackerType: AttackerType
  - activeEffects: List<CombatEffect>
  - puzzleQueue: Queue<CombatPuzzle>
  - commandsAvailable: List<Command>
  - turnNumber: Int
```

#### Defrag State

```
DefragSession:
  - sessionId: UUID
  - playerEventLog: List<EventLogEntry>
  - sentinelTrackerLog: List<SentinelTrace>
  - totalTraceWeight: Int
  - deletedTraceWeight: Int
  - remainingTraces: List<SentinelTrace>
  - toolSignaturesAdded: Int

EventLogEntry:
  - timestamp: String (HH:mm:ss.SSS)
  - commandType: CommandType
  - targetFile: String (optional)
  - metadata: Map<String, Any>

SentinelTrace:
  - traceId: UUID
  - timestamp: String
  - traceType: TraceType enum
  - weight: Int (exposure penalty)
  - flagged: Boolean
  - encrypted: Boolean
  - cascadeParent: UUID (optional)
  - cascadeChildren: List<UUID>
```

### 2.2 Configuration Entities

#### Machine Definitions

```
MachineDefinition:
  - machineType: MachineType enum
  - baseCost: Int
  - baseFirewall: Int
  - maxFirewall: Int
  - storageSlots: Map<StorageLevel, Int>
    // Level 1 -> 6, Level 2 -> 12, etc.
  - sentinelRiskModifier: Float
  - specialTraits: List<MachineTrait>
  - upgradeRequirements: Map<UpgradeType, Requirement>
```

#### Tool Definitions

```
ToolDefinition:
  - toolType: ToolType enum
  - versions: Map<ToolVersion, VersionStats>

VersionStats:
  - unlockCost: Int
  - prerequisiteVersion: ToolVersion (nullable)
  - effect: ToolEffect
  - activationCost: ResourceCost
  - cooldown: Cooldown
  - slotCost: Int
  - defragPenalty: Int
  - specialAbility: SpecialAbility (nullable)

ToolEffect:
  - chargesPerUse: Int
  - duration: Int (seconds, or -1 for instant)
  - effectType: EffectType enum
  - magnitude: Float

ResourceCost:
  - firewallCost: Int
  - exposureCost: Int
  - creditCost: Int (for consumables)
```

#### Command Definitions

```
CommandDefinition:
  - commandName: String
  - category: CommandCategory enum (BASIC, ATTACK, COUNTER, DATA, UTILITY)
  - baseCost: ResourceCost
  - availableIn: List<Context> (HOME, MISSION, COMBAT, DEFRAG)
  - requirements: List<Requirement>
  - cooldown: Cooldown (optional)
  - effect: CommandEffect
```

---

## 3. GAME STATE MANAGEMENT

### 3.1 State Machine

```
GameState enum:
  - HOME_NODE
    → Can: view loadout, repair, defrag, view missions, upgrade
    → Transitions to: MISSION_PREP

  - MISSION_PREP
    → Can: build loadout, purchase consumables, review mission
    → Transitions to: MISSION_ACTIVE, HOME_NODE

  - MISSION_ACTIVE
    → Can: execute mission commands, navigate nodes
    → Transitions to: COMBAT, MISSION_SUCCESS, MISSION_FAILED

  - COMBAT
    → Can: execute combat commands, solve puzzles
    → Transitions to: MISSION_ACTIVE, MISSION_FAILED

  - MISSION_SUCCESS
    → Can: review results, collect payout
    → Transitions to: HOME_NODE

  - MISSION_FAILED
    → Can: review failure, handle sentinel seizure
    → Transitions to: HOME_NODE

  - DEFRAG_ACTIVE
    → Can: match traces, use defrag tools, exit
    → Transitions to: HOME_NODE
```

### 3.2 State Transition Rules

**Validation Required:**
- Can player afford to start mission? (credits for consumables)
- Does loadout fit within storage slots?
- Are all prerequisites met for actions?
- Is current game state valid for requested action?

**State Persistence:**
- Save state after every significant action
- Allow recovery from disconnection
- Checkpoint system for long missions

### 3.3 State Synchronization

```
Between Kotlin and Laravel:

State Update Event:
  - eventType: StateUpdateType enum
  - changedFields: Map<String, Any>
  - timestamp: Long
  - requiresUIRefresh: Boolean
  - animationHints: List<AnimationHint>
```

---

## 4. COMMAND SYSTEM

### 4.1 Command Processing Pipeline

```
1. Input Validation (Kotlin)
   - Parse command string
   - Validate syntax
   - Check if command exists

2. Context Validation (Kotlin)
   - Is command available in current state?
   - Does player have required tools?
   - Are cooldowns satisfied?

3. Cost Calculation (Kotlin)
   - Base command cost
   - Machine modifiers
   - Active tool effects (overdrive, etc.)
   - Sentinel modifier (if high residual exposure)

4. Resource Check (Kotlin)
   - Does player have enough firewall?
   - Would this trigger detection?
   - Calculate post-command state

5. Execution (Kotlin)
   - Apply command effects
   - Update exposure
   - Update firewall
   - Record in command history
   - Generate traces (for later defrag)

6. Detection Roll (Kotlin)
   - Calculate detection chance
   - Roll for detection
   - Trigger combat if detected

7. Response Generation (Kotlin)
   - Success/failure message
   - State changes
   - Visual feedback hints

8. UI Update (Laravel)
   - Render command output
   - Update meters (exposure, firewall)
   - Show new available commands
```

### 4.2 Command Categories

| Category | Cost | Traces | Examples |
|----------|------|--------|----------|
| **Basic** | Low-moderate | Light | `ls`, `cd`, `download`, `cat` |
| **Attack** | High exposure | Heavy | `databomb`, `crashout`, `scramble` |
| **Counter** | Moderate | Moderate | `cloak`, `firewall_patch` |
| **Data** | Varied | Varied | `decrypt`, `forge`, `compress` |
| **Utility** | High credits | Varies | `skipnode`, `autodefrag` |

### 4.3 Command Cost Modifiers

```
Final Cost = Base Cost × Machine Modifier × Residual Exposure Modifier × Tool Effects

Machine Modifier:
  - Blacksteel: 1.0× (baseline)
  - WhisperNode: 0.85× exposure (stealth bonus)
  - Cinder: 1.15× exposure (cheap but noisy)

Residual Exposure Modifier:
  - 0-30% residual: 1.0×
  - 31-50% residual: 1.1×
  - 51-75% residual: 1.25×
  - 76-100% residual: 1.5×

Tool Effects:
  - Overdrive active: -40% to -60% exposure (V1 to V3)
  - Cloak active: Command masked (0% exposure)
  - Degraded machine: +10% all costs
```

---

## 5. TOOL & LOADOUT SYSTEM

### 5.1 Tool Acquisition Flow

```
1. Tool Discovery
   - Player sees tool in shop
   - Displays: V1 unlock cost, V2/V3 upgrade costs
   - Shows current ownership status

2. Purchase Validation
   - Check credits
   - Check prerequisites (V2 requires V1, V3 requires V2)
   - Confirm purchase

3. Tool Unlocked
   - Add to player's ToolInventory
   - Update available tools for loadout
   - Notify player of new capability

4. Consumable Purchase (per-mission)
   - Check credits
   - Add to consumables stock
   - Can be equipped in loadout if slots available
```

### 5.2 Loadout Building System

**Pre-Mission Loadout Screen:**

```
╔══════════════════════════════════════════════════════════════════╗
║ LOADOUT BUILDER                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║ Storage: 8 / 12 slots used                                       ║
║                                                                  ║
║ [1] Cloak V3          [3 slots] ███  [REMOVE]                   ║
║ [2] Rollback V2       [2 slots] ██   [REMOVE]                   ║
║ [3] Emergency_Patch V2[2 slots] ██   [REMOVE]                   ║
║ [4] Overdrive V1      [1 slot]  █    [REMOVE]                   ║
║ [5] (empty)                                                      ║
║                                                                  ║
║ Available Tools:                                                 ║
║ [ ] Ghost_Protocol V1 [1 slot]  [ADD]                           ║
║ [ ] Smoke_Screen V1   [1 slot]  [ADD]                           ║
║                                                                  ║
║ Consumables (purchase & equip):                                  ║
║ [ ] Cloak             [3 slots] 300cr  [BUY]                    ║
║ [ ] Rollback          [3 slots] 400cr  [BUY]                    ║
║                                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║ Commands: add <tool> | remove <slot> | buy <consumable> | done   ║
╚══════════════════════════════════════════════════════════════════╝
```

**Loadout Validation Rules:**
1. Total slot cost <= available slots
2. Can't equip same permanent tool twice
3. CAN equip consumable + permanent of same type
4. Must have at least 1 tool (or allow "naked" runs?)
5. Warn if overloaded (too many tools for slots)

### 5.3 Tool State Management During Mission

```
ToolState enum:
  - READY: Can be used
  - ACTIVE: Currently in effect (cloak active, etc.)
  - SPENT: Used for mission, cooldown active
  - EXHAUSTED: Consumable used up, gone

Tool Usage Flow:
  1. Player types: use cloak
  2. System checks: Is cloak in loadout? State = READY?
  3. Apply activation costs (firewall, exposure)
  4. Set tool state to ACTIVE
  5. Apply tool effects (charges, duration, etc.)
  6. When effect expires or charges used:
     - Permanent: Set state to SPENT (cooldown until next mission)
     - Consumable: Set state to EXHAUSTED (remove from loadout)
```

### 5.4 Tool Upgrade System

**Version Comparison Display:**

```
Show side-by-side:
  Current Version (V2):
    - Slot cost: 2
    - Activation: 12% FW, 6% EXP
    - Effect: 3 charges, 75s
    - Cooldown: 1/mission

  Next Version (V3):
    - Slot cost: 3 (+1)
    - Activation: 8% FW, 3% EXP (improved)
    - Effect: 4 charges, 90s (improved)
    - Cooldown: 2/mission (improved)
    - Special: Can cloak counter commands (NEW)

  Upgrade Cost: 7,000 credits
```

---

## 6. MISSION SYSTEM

### 6.1 Mission Structure

```
Mission:
  - missionId: String
  - title: String
  - contactName: String
  - difficulty: Difficulty enum (EASY, MEDIUM, HARD, EXTREME)
  - basePayout: Int
  - bonusObjectives: List<BonusObjective>
  - targetStructure: NetworkStructure
  - securityLevel: SecurityLevel enum
  - estimatedExposure: Range<Int> (e.g., 60-80%)
  - recommendedTools: List<ToolType>
  - storyContext: String
  - unlockRequirements: List<Requirement>

NetworkStructure:
  - rootNode: NetworkNode
  - depth: Int (how many cd commands to target)
  - branches: Int (optional paths)

NetworkNode:
  - nodeId: String
  - files: List<File>
  - subNodes: List<NetworkNode>
  - securityLevel: SecurityLevel
  - hasTrap: Boolean
  - trapType: TrapType (optional)
```

### 6.2 Mission Flow

```
Phase 1: MISSION_PREP
  → Player at home node
  → Selects mission from available list
  → Views mission brief
  → Builds loadout
  → Purchases consumables
  → Confirms start
  → Transition to MISSION_ACTIVE

Phase 2: MISSION_ACTIVE - Infiltration
  → Player executes commands (connect, cd, ls, etc.)
  → Each command costs exposure + firewall
  → Exposure builds toward detection threshold
  → Player navigates network structure
  → Player downloads files (objectives)

Phase 3: MISSION_ACTIVE - Extraction Decision
  → Player has downloaded some/all files
  → Player decides: push for more or extract now?
  → If extract: end mission, calculate results
  → If continue: risk detection for more rewards

Phase 4: DETECTION (optional, if triggered)
  → Exposure exceeds threshold + detection roll fails
  → Transition to COMBAT
  → Player fights Sentinel or Rival Hacker
  → Combat resolution determines mission outcome

Phase 5: MISSION_COMPLETE
  → Calculate final payout
  → Apply bonuses (full objectives, no detection, etc.)
  → Add story consequences (contact trust, Sentinel heat, etc.)
  → Return to HOME_NODE
  → Trigger REPAIR/DEFRAG prompts if needed
```

### 6.3 Mission Objectives

**Primary Objectives:**
- Download X specific files
- Forge specific logs
- Plant backdoor
- Extract data within time limit

**Bonus Objectives:**
- Complete without detection
- Complete under X% exposure
- Download additional optional files
- Don't use attack commands

```
ObjectiveTracker:
  - primaryObjectives: List<Objective>
  - bonusObjectives: List<Objective>
  - completionStatus: Map<ObjectiveId, Boolean>

Objective:
  - objectiveId: String
  - description: String
  - type: ObjectiveType enum
  - target: String (file name, node name, etc.)
  - completed: Boolean
  - reward: Int (credits)
```

### 6.4 Mission Payout Calculation

```
Final Payout Formula:
  Base Payout
  + Bonus Objective Rewards
  + Completion Multiplier
  - Penalties

Completion Multiplier:
  - No detection: 1.2×
  - Under 70% exposure: 1.1×
  - All objectives: 1.15×
  - Stack multiplicatively

Penalties:
  - Used attack commands: -10% per use
  - Failed bonus objectives: -0 (no bonus, not penalty)
  - High exposure (90%+): -15%
```

---

## 7. COMBAT SYSTEM

### 7.1 Combat Trigger Conditions

```
Detection Chance Calculation:
  Base Risk = f(Exposure tier)
    0-60%: 0%
    61-70%: (Exposure - 60) × 1%
    71-85%: (Exposure - 60) × 2%
    86-100%: (Exposure - 60) × 3%

  Firewall Multiplier:
    80-100% Firewall: Base × 0.3
    60-79%: Base × 0.6
    40-59%: Base × 1.0
    20-39%: Base × 1.8
    0-19%: Base × 3.0

  Final Chance = Base Risk × Firewall Multiplier
```

**Other Triggers:**
- Story-mandated ambush
- Rival hacker intrusion (mission-specific)
- Failed puzzle during mission
- Using forbidden command in restricted area

### 7.2 Combat State Initialization

```
When combat starts:
  1. Save current mission state (pausable)
  2. Initialize CombatState
     - System Integrity: 100%
     - Threat Pressure: Starting value (depends on trigger)
     - Firewall: Current firewall
     - Exposure: Current exposure (frozen during combat)
  3. Identify attacker type (Sentinel, Rival, AI)
  4. Generate puzzle queue based on attacker type
  5. Disable most mission commands
  6. Enable combat commands only
  7. Display combat UI
```

### 7.3 Combat Loop

```
Combat Turn:
  1. Display current state:
     - System Integrity: X%
     - Threat Pressure: Y%
     - Firewall: Z%
     - Active effects

  2. Attacker phase (automatic):
     - If Threat Pressure > threshold: Attacker strikes
     - Damage to System Integrity or Firewall
     - Spawn new puzzle or escalate existing

  3. Player phase (input):
     - Player can:
       a) Execute combat command (counter, attack, data)
       b) Solve active puzzle
       c) Use equipped tool (if available)
       d) Attempt extraction (forfeit mission)

  4. Resolve player action:
     - Apply effects
     - Update metrics
     - Check puzzle solutions

  5. Check end conditions:
     - System Integrity = 0% → COMBAT LOST
     - Threat neutralized (Pressure = 0%) → COMBAT WON
     - Player extracted → MISSION PARTIAL

  6. If combat continues: Next turn
```

### 7.4 Combat Puzzles

**Memory Forensics:**
- Display hex dump with anomalies
- Player must identify corrupted addresses
- Input: list of addresses (e.g., `0x4A2F:0140 0x4A2F:0160`)
- Success: Reduce Threat Pressure by X%
- Failure: System Integrity damage

**Port Scan Interception:**
- Display netstat output
- Show ports under attack (flagged)
- Player must close attacked ports only
- Input: `firewall --close <port>` (multiple commands)
- Success: Block attack
- Failure: Firewall damage

**Log Pattern Recognition:**
- Display log entries
- Player must identify attack chain
- Input: `select <timestamp1> <timestamp2> <timestamp3>`
- Success: Terminate attack
- Failure: Threat Pressure increases

**Process Kill:**
- Display process list (PID, user, CPU%, command)
- Player must identify malicious processes
- Input: `kill <PID> <PID> <PID>`
- Penalty: Killing system processes damages System Integrity
- Success: Remove threats

**Difficulty Scaling Factors:**
- Number of entries (logs, processes, ports)
- Time pressure (optional countdown)
- False positives (decoy data)
- Cascading (solving one spawns another)

### 7.5 Combat Resolution

**Combat Won:**
1. Neutralized threat (Threat Pressure = 0%)
2. Display combat report (duration, SI remaining, puzzles solved/failed)
3. Apply post-combat penalties (attack commands used = Sentinel learns patterns)
4. Resume mission or return to home if mission failed

**Combat Lost:**
1. System Integrity = 0%
2. SENTINEL SEIZURE triggered
3. Mission automatically fails
4. Player chooses asset downgrade (display random eligible upgrades, player selects one)
5. Credits seized (formula: remaining mission value)
6. Machine flagged (heat level increases)
7. Return to HOME_NODE
8. Force repair prompt

**Extraction During Combat:**
1. Player uses `panic_extract` or similar
2. Combat ends immediately
3. Keep partial mission data (40-60% depending on tool)
4. Apply extraction penalty (exposure spike)
5. Mission ends with partial payout

---

## 8. DEFRAG SYSTEM

### 8.1 Defrag Trigger

Available after any mission (successful or failed) when player is at HOME_NODE and residual exposure > 0%.

```
Display:
  "Residual exposure: 87%
   Sentinel has 23 active traces in database.
   Run defrag to reduce exposure and clean traces.

   > defrag (start defrag session)
   > skip (keep residual exposure)"
```

### 8.2 Defrag Session Initialization

```
Start Defrag:
  1. Generate Player Event Log
     - Parse mission command history
     - Create chronological log of player actions
     - Format: [HH:mm:ss] command details

  2. Generate Sentinel Tracker Log
     - For each player action, create corresponding traces
     - Add false positives (unrelated Sentinel activity)
     - Add cascades (1 action → multiple traces)
     - Encrypt some traces (requires decrypt)
     - Assign weight to each trace (exposure cost)

  3. Initialize Defrag State
     - Total trace weight = residual exposure
     - Deleted trace weight = 0%
     - Remaining traces = all traces

  4. Display dual-panel interface
```

### 8.3 Trace Generation Rules

**Mapping Player Actions to Sentinel Traces:**

| Player Action | Sentinel Traces | Weight |
|---------------|----------------|--------|
| `connect` | `conn_detected` | 1-2% |
| `download file` | `data_exfil` | 4-7% |
| `decrypt` | `cpu_spike` + `encrypted_traffic` + `file_access` | 6-10% total |
| `databomb` | `process_spike` + `system_crash` + `recovery_failed` | 12-15% total CASCADE |
| `forge logs` | `log_inconsistency` | 2-3% |
| `cloak (consumable)` | `encryption_signature` + `tool_traces` | 3% |
| `cloak (permanent V3)` | `minimal_signature` | 1% |

**False Positives (20-30% of entries):**
- `routine_scan` (not player)
- `backup_process` (not player)
- `user_activity` (other users)

**Cascade Rules:**
```
Attack commands create cascades:
  databomb:
    Parent trace: attack_signature
    Children:
      - process_spike (4%)
      - system_crash (5%)
      - recovery_failed (3%)

Parent-child relationship stored in trace metadata
```

### 8.4 Defrag Commands

```
match <player_timestamp> <sentinel_timestamp>
  - Match player event with sentinel trace
  - If correct: Delete trace, reduce exposure
  - If wrong: Error message, no penalty

analyze <sentinel_timestamp>
  - Show detailed info about a trace
  - Hint about what caused it

decrypt <sentinel_timestamp>
  - Decrypt an encrypted trace
  - Costs: 50 credits or 5 firewall (optional cost)
  - Reveals trace details

filter <keyword>
  - Show only traces matching keyword
  - E.g., "filter data_exfil" shows all download traces

sort <criteria>
  - Sort Sentinel log by: weight, time, type

hint
  - Highlight 3 likely matches
  - Limited uses or credit cost

done
  - Exit defrag session
  - Keep remaining traces (residual exposure)
```

### 8.5 Defrag Validation Logic

```
Match Validation:
  1. Check if player timestamp exists in event log
  2. Check if sentinel timestamp exists in tracker
  3. Determine if correlation is correct:
     a) Direct match: timestamps within 0-3s, matching types
     b) Cascade match: player event is parent of sentinel trace
     c) Indirect match: player action caused this type of trace

  4. If correct:
     - Remove trace from sentinel log
     - Reduce remaining exposure by trace weight
     - If cascade: mark children for deletion too
     - Update UI

  5. If incorrect:
     - Show error: "No correlation detected"
     - No penalty (player can try again)
```

### 8.6 Defrag Tool Usage

```
turbo_defrag (consumable or permanent):
  1. Player activates: > use turbo_defrag
  2. System auto-matches X obvious traces
  3. Calculate net exposure reduction
  4. Apply tool signature penalty (+3-4% exposure)
  5. Update remaining exposure
  6. Tool consumed (if consumable) or spent (if permanent)

pattern_purge:
  1. Player selects command type: > pattern_purge download
  2. System identifies all traces of that type
  3. 20% chance Sentinel detects purge
  4. If detected: Traces restored + penalty
  5. If success: All traces of type removed
  6. Tool consumed

bulk_decrypt:
  1. Player activates: > use bulk_decrypt
  2. All encrypted traces decrypted instantly
  3. Player can now match them manually
  4. Tool consumed
```

### 8.7 Defrag Completion

```
When player types "done":
  1. Calculate final exposure:
     Starting exposure: 87%
     Deleted traces: -52%
     Tool signatures: +4%
     Final exposure: 39%

  2. Display performance report:
     - Traces deleted: 20/23
     - Traces remaining: 3
     - Exposure reduction: 48%
     - Grade: B

  3. Update player state:
     - Residual exposure = 39%
     - Store remaining traces (for Sentinel learning)

  4. Return to HOME_NODE
```

### 8.8 Defrag Consequence Tracking

```
Sentinel Pattern Learning:
  - Missed traces stored in database
  - Types of traces left behind analyzed
  - Example: Player always leaves databomb traces
    → Sentinel: "Player uses databombs frequently"
    → Next mission: databomb effectiveness -10%

Pattern Recognition Effects:
  - If player leaves same command traces repeatedly:
    → That command costs +5-10% more exposure
    → Sentinel deploys specific countermeasures

  - If player defrags perfectly:
    → Sentinel has no data
    → No pattern recognition
    → Standard difficulty
```

---

## 9. ECONOMY & PROGRESSION

### 9.1 Credit Sources

**Mission Payouts:**

```
Base Payout Formula:
  Difficulty multipliers:
    EASY: 1,000-1,500 credits
    MEDIUM: 2,500-4,000 credits
    HARD: 5,000-8,000 credits
    EXTREME: 10,000-15,000 credits

  Modifiers:
    + Bonus objectives (10-30% per objective)
    + No detection bonus (20%)
    + Under 70% exposure (10%)
    + Perfect run (all bonuses) (1.5× total)

  Penalties:
    - Used attack commands (-10% per use)
    - High exposure 90%+ (-15%)
```

**Other Sources:**
- Selling excess consumables (50% refund)
- Story rewards
- Achievements
- Optional side missions

### 9.2 Credit Sinks

**Required Expenses:**

```
Machine Repair:
  - Quick Patch: 1,000cr (restore 30% firewall)
  - Standard Repair: 2,500cr (restore 70% firewall)
  - Full Rebuild: 5,000cr (restore 100% firewall + bonus)

Defrag Tools (consumables):
  - turbo_defrag: 500cr
  - pattern_purge: 800cr
  - bulk_decrypt: 350cr

Mission Tools (consumables):
  - cloak: 300cr
  - rollback: 400cr
  - ghost_protocol: 800cr
```

**Investment Expenses:**

```
Permanent Tool Unlocks:
  - V1 unlocks: 7,000-15,000cr
  - V2 upgrades: 5,000-8,000cr
  - V3 upgrades: 6,000-12,000cr
  - Total for one tool to V3: 18,000-35,000cr

Machine Upgrades:
  - Storage level ups: 3,000-12,000cr
  - Firewall capacity: 2,000-5,000cr
  - New machines: 15,000-50,000cr
```

### 9.3 Economic Balance Targets

```
Mission 1-10: Struggling, using consumables only
  - Net profit: 500-1,000cr per mission
  - By mission 10: Saved 8,000-10,000cr

Mission 10-15: First permanent unlock
  - Unlock rollback V1 or cloak V1
  - Immediate efficiency improvement
  - Net profit: 1,500-2,500cr per mission

Mission 15-30: Building toolkit
  - Unlock 2-3 more V1 tools
  - Start upgrading to V2
  - Net profit: 2,000-4,000cr per mission

Mission 30-50: Upgrading to V2/V3
  - Most tools at V2
  - Key tools upgraded to V3
  - Net profit: 3,000-6,000cr per mission

Mission 50+: Endgame optimization
  - All tools at V3
  - Multiple machines
  - Net profit: 5,000-10,000cr per mission
  - Buying consumables for stacking
```

### 9.4 Progression Tracking

```
ProgressionTracker:
  - totalCreditsEarned: Long
  - totalCreditsSpent: Long
  - currentNetWorth: Long (credits + tool values)
  - missionsCompleted: Int
  - missionSuccessRate: Float
  - averageExposurePerMission: Float
  - combatEncounters: Int
  - combatWinRate: Float
  - perfectDefragsCompleted: Int
  - toolsUnlocked: Int
  - toolsMaxed: Int (V3)
  - achievements: List<Achievement>
```

### 9.5 Achievements System

| Achievement | Condition | Reward |
|------------|-----------|--------|
| First Blood | Complete first mission | Credits |
| Ghost Protocol | Complete mission with 0% exposure | Unique unlock |
| Survivor | Win 10 combats | Credits |
| Forensic Expert | Perfect defrag (delete all traces) | Credits |
| Millionaire | Earn 1,000,000 total credits | Achievement |
| Arsenal | Own all tools at V3 | Achievement |
| Clean Slate | 5 missions in a row with <10% residual | Credits |

---

## 10. MACHINE & UPGRADE SYSTEM

### 10.1 Machine Definitions

#### GREYBOX (Starter)
- **Cost:** 0 (starting machine)
- **Base firewall:** 100% | **Max:** 100%
- **Storage:** Level 1 (6 slots)
- **Sentinel risk:** High
- **Special:** None
- **Upgrades:** Storage to Lv2 (3,000cr)

#### CINDER (Burner)
- **Cost:** 1,500cr
- **Base firewall:** 120% | **Max:** 120%
- **Storage:** Level 1 (7 slots)
- **Sentinel risk:** Very High
- **Special:** Auto-wipes data on seizure (they get nothing)
- **Quirk:** Logs auto-delete after each session
- **Trade-off:** Cheaper but MORE targeted by Sentinel

#### BLACKSTEEL (Mid-Tier)
- **Cost:** 15,000cr
- **Base firewall:** 100% | **Max:** 130%
- **Storage:** Level 2 (12 slots)
- **Sentinel risk:** Medium
- **Special:** Balanced, reliable
- **Upgrades:** Storage to Lv3 (8,000cr), Lv4 (12,000cr)

#### WHISPERNODE (Stealth)
- **Cost:** 20,000cr
- **Base firewall:** 100% | **Max:** 110%
- **Storage:** Level 1 (8 slots)
- **Sentinel risk:** Low
- **Special:** "Adaptive Camouflage" — Exposure decay 2x faster when idle, all commands -15% exposure cost
- **Trade-off:** Can't run attack commands above Lvl 3

#### DIRECTIVE-7 (Government)
- **Cost:** FREE (story-forced)
- **Base firewall:** 120% | **Max:** 150%
- **Storage:** Level 3 (15 slots)
- **Sentinel risk:** Very High
- **Special:** Powerful stats, but...
  - Hidden backdoors (random mid-mission triggers)
  - Sentinel adapts faster when using this machine
  - Cannot remove until story progression
- **Quirk:** Randomly leaks mission data to government

#### NULLROOT (Endgame)
- **Cost:** 50,000cr (or story unlock)
- **Base firewall:** 130% | **Max:** 170%
- **Storage:** Level 4 (20 slots)
- **Sentinel risk:** Variable
- **Special:** "Adaptive Intelligence" — Sentinel hacks less effective (-50%), can hack Sentinel directly (unique commands), machine has... awareness (story implications)
- **Risk:** Severe penalties if compromised

### 10.2 Machine Switching

```
Switch Machine Flow:
  1. Player at HOME_NODE
  2. View machine inventory: > machines
  3. Select machine: > switch <machine_name>
  4. Validation:
     - Is machine owned?
     - Is machine operational (not damaged)?
  5. Switch confirmation:
     - "This will unequip your current loadout."
     - "Continue? [y/n]"
  6. If yes:
     - Save current machine state
     - Load new machine state
     - Clear loadout (must rebuild)
     - Update available storage slots
  7. Display new machine status
```

### 10.3 Machine Upgrades

**Storage Upgrades:**
```
Example:
  Blacksteel Storage Lv2 (12 slots)
  ↓ Upgrade (8,000cr)
  Blacksteel Storage Lv3 (15 slots)
```

**Firewall Capacity Upgrades:**
```
Example:
  Blacksteel Max Firewall: 100%
  ↓ Upgrade (3,000cr)
  Blacksteel Max Firewall: 110%
```

**Special Ability Unlocks (optional):**
- Blacksteel: Unlock "Fortress Mode" (emergency +50% firewall, 1/mission)
- WhisperNode: Unlock "Deep Cloak" (invisible for 2 actions, 1/mission)
- Cost: 10,000-15,000cr

### 10.4 Machine Damage & Repair

**Damage States:**

| Condition | Operational | Effects | Repair Cost |
|-----------|-------------|---------|-------------|
| PRISTINE | 100% | None | — |
| GOOD | 90-99% | None | — |
| DAMAGED | 60-89% | +10% FW cost, -10% max FW | 2,500cr |
| HEAVILY_DAMAGED | 30-59% | +25% FW cost, -25% max FW, -2 slots | 5,000cr |
| CRITICAL | 1-29% | +50% FW cost, -50% max FW, -4 slots, can't start missions | 10,000cr |
| DESTROYED | 0% | Unplayable | — |

**Repair Options:**
```
> repair

Machine: Blacksteel [DAMAGED - 65%]
Issues:
  - Firewall subsystem degraded
  - Command processor inefficient

Repair Options:
  [a] Quick Patch: 1,000cr (restore to 85%)
  [b] Standard Repair: 2,500cr (restore to 100%)
  [c] Full Rebuild: 5,000cr (restore to 100% + bonus firewall +10 for 1 mission)
```

---

## 11. UI/UX LAYER

### 11.1 Terminal Interface Components

**Status Bar:**
```
[HOME_NODE] | Credits: 8,450 | Machine: Blacksteel | Exposure: 12%

In Mission:
[MISSION] | Exposure: 67% | Firewall: 45% | Detection: 18%

In Combat:
[COMBAT] | SI: 78% | Threat: 45% | Firewall: 30%
```

**Meters (Visual):**
```
Exposure Meter:
  [████████████████░░░░] 80%
  Color-coded: 0-60% Green | 61-85% Yellow | 86-100% Red

Firewall Meter:
  [██████░░░░░░░░░░░░░░] 30%
  Color-coded: 60-100% Blue | 30-59% Yellow | 0-29% Red

System Integrity (Combat):
  [███████████████░░░░░] 75%
  Color: Always red gradient
```

### 11.2 Command Output Formatting

**Success:**
```
> download payroll.db

[████████████████████████████] 100%
✓ Download complete: payroll.db (2.3MB)

Exposure: 55% → 67% (+12%)
Firewall: 60% → 54% (-6%)
```

**Error:**
```
> download nonexistent.file

✗ Error: File not found
Available files: payroll.db, emails.zip, contracts.pdf
```

**Warning:**
```
> download final_file.db

⚠️ WARNING
This action will:
  - Increase exposure: 85% → 103% (capped at 100%)
  - Detection risk: 78%

Proceed? [y/n]
```

### 11.3 Interactive Menus

**Shop Interface:**
```
╔══════════════════════════════════════════════════════════════════╗
║ TOOL SHOP                                                        ║
║ Your Credits: 8,450                                              ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║ CLOAK                                                            ║
║ ├─ Owned: V2                                                    ║
║ ├─ Next: V3 (7,000cr)                                          ║
║ │   └─ Improves: 3→4 charges, 12%→8% FW cost, 6%→3% EXP cost  ║
║ └─ [UPGRADE TO V3]                                              ║
║                                                                  ║
║ ROLLBACK                                                         ║
║ ├─ Owned: V1                                                    ║
║ ├─ Next: V2 (6,000cr)                                          ║
║ │   └─ Improves: Better costs, +firewall reversal, 3 action    ║
║ └─ [UPGRADE TO V2]                                              ║
║                                                                  ║
║ GHOST_PROTOCOL                                                   ║
║ ├─ Owned: None                                                  ║
║ ├─ Unlock V1: 15,000cr                                         ║
║ └─ [UNLOCK] (insufficient credits)                              ║
║                                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║ Commands: upgrade <tool> | unlock <tool> | back                  ║
╚══════════════════════════════════════════════════════════════════╝
```

### 11.4 Real-Time Updates

**WebSocket Events:**
- `StateUpdate` — When game state changes
- `MeterUpdate` — When exposure/firewall changes
- `CombatUpdate` — During combat turns
- `NotificationUpdate` — Alerts, warnings
- `AnimationTrigger` — Visual effects

**Animation Hints:**
- Meter fill/drain (smooth transitions)
- Detection warning pulse (when in danger zone)
- Combat damage shake
- Success/failure flashes
- Text typing effect (for story/dialogue)

### 11.5 Accessibility Features

- Color-blind mode (don't rely solely on color)
- Text size options
- Screen reader support (semantic HTML)
- Keyboard navigation (no mouse required)
- Command history (up arrow to recall)
- Clear error messages
- Undo option where appropriate

---

## 12. IMPLEMENTATION PHASES

### Phase 1: Core Foundation (2-3 weeks)

**Goals:** Basic game loop functional, player can complete simple missions, core state management working.

**Kotlin Backend:**
- Player state model & persistence
- Machine system (Greybox only)
- Basic command processor (`ls`, `cd`, `download`, `connect`, `disconnect`)
- Simple mission structure (1 node, 3 files)
- Exposure/firewall calculation
- Detection system (basic probability)
- Home node functionality (view state)

**Laravel Frontend:**
- Terminal UI rendering
- Command input & output display
- Status bar (exposure, firewall, credits)
- Basic meters (visual)
- Session management
- WebSocket connection to Kotlin

**Database:**
- Player table
- Machine state table
- Mission definitions table (basic schema)
- Game config table

### Phase 2: Tool & Loadout System (2-3 weeks)

**Goals:** Permanent tool system functional, consumable purchase & usage, loadout building operational.

**Kotlin Backend:**
- Tool inventory model
- Tool definitions (all tools, V1-V3)
- Loadout manager
- Tool activation system
- Tool effect application
- Cooldown tracking
- Storage slot validation
- Shop system (purchase tools)

**Laravel Frontend:**
- Loadout builder interface
- Tool shop UI
- Tool status display in mission
- `use <tool>` command handling
- Visual tool effect indicators

**Database:**
- Tool inventory table
- Loadout state table
- Tool definitions table
- Purchase history table

### Phase 3: Enhanced Mission System (2 weeks)

**Goals:** Multi-node missions, mission variety, objective system, detection refined.

**Kotlin Backend:**
- Network structure generation
- Multi-node navigation
- Objective tracking system
- Bonus objectives
- Payout calculation
- Mission difficulty scaling
- File types & properties
- Trap system (optional)

**Laravel Frontend:**
- Network map display (optional visual)
- Objective tracker UI
- Mission brief screen
- Mission results screen
- Payout breakdown display

**Database:**
- Mission catalog (10-15 missions)
- Network structure definitions
- Objective definitions

### Phase 4: Combat System (3 weeks)

**Goals:** Combat fully functional, puzzles working, Sentinel seizure implemented.

**Kotlin Backend:**
- Combat state machine
- Combat initialization
- Attacker AI (Sentinel, Rival)
- Puzzle generation system
- Puzzle validation
- Combat command system
- Combat resolution
- Sentinel seizure flow
- Asset downgrade selection

**Laravel Frontend:**
- Combat UI layout
- Puzzle display (each type)
- Combat metrics display
- Combat command input
- Puzzle input handling
- Combat report screen
- Seizure screen (downgrade selection)

**Database:**
- Combat history table
- Puzzle definitions
- Combat config (difficulty tuning)

### Phase 5: Defrag System (2-3 weeks)

**Goals:** Dual-panel defrag interface, trace matching works, defrag tools functional.

**Kotlin Backend:**
- Event log generation from mission history
- Sentinel trace generation
- Trace correlation logic
- Match validation
- Cascade handling
- False positive generation
- Trace encryption
- Defrag tool effects
- Pattern learning system

**Laravel Frontend:**
- Dual-panel interface (event log + sentinel tracker)
- Match command handling
- Trace highlighting
- Defrag tool usage UI
- Real-time exposure update
- Defrag completion report

**Database:**
- Defrag session history
- Sentinel pattern database
- Trace definitions

### Phase 6: Economy & Progression (2 weeks)

**Goals:** Economic balance finalized, progression tracked, achievements system.

**Kotlin Backend:**
- Credit flow tracking
- Payout calculation refinement
- Repair system
- Machine upgrade system
- Storage upgrades
- Progression tracker
- Achievement system
- Unlock requirements

**Laravel Frontend:**
- Credit display & history
- Repair screen
- Upgrade shop
- Progression stats display
- Achievement notifications
- Achievement gallery

**Database:**
- Transaction history
- Achievement definitions
- Unlock requirements table

### Phase 7: Additional Machines (1-2 weeks)

**Goals:** All machines implemented, machine switching works, machine-specific mechanics functional.

**Kotlin Backend:**
- All machine definitions (Cinder, Blacksteel, WhisperNode, Directive-7, NullRoot)
- Machine purchase system
- Machine switching
- Machine-specific traits
- Damage system per machine
- Machine upgrade paths

**Laravel Frontend:**
- Machine shop UI
- Machine comparison display
- Machine switching interface
- Machine status detailed view

**Database:**
- Machine inventory table
- Machine state history

### Phase 8: Polish & Balance (2-3 weeks)

**Goals:** Game feels complete, balance tuned, edge cases handled.

**All Systems:**
- Balance pass on all costs
- Difficulty tuning
- Edge case handling
- Error recovery
- Performance optimization
- Bug fixing
- Tutorial refinement
- Story integration
- UI polish
- Sound effects (optional)

### Phase 9: Content Expansion (Ongoing)

- Additional missions (15+)
- New tool types
- Story branches
- Rival hackers (characters)
- Endgame content
- New achievements

---

## 13. TECHNICAL CONSIDERATIONS

### 13.1 State Persistence Strategy

**When to Save:**
- Every command in mission
- Tool purchase/upgrade
- Loadout change
- Mission start/end
- Combat action
- Defrag action
- Machine switch
- Repair

**Critical State (must persist):**
- Player ID, current machine & state, credits
- Tool inventory, loadout
- Residual exposure
- Mission progress (if in mission)
- Current node/location
- Command history (recent)

**Can Be Regenerated:**
- Mission structure (from definition)
- Puzzle solutions (from seed)
- Visual state (UI rebuilds)

### 13.2 Communication Protocol

**Request (Laravel → Kotlin):**
```json
{
  "playerId": "uuid",
  "sessionId": "uuid",
  "action": "executeCommand",
  "data": {
    "command": "download payroll.db",
    "context": "mission",
    "timestamp": 1234567890
  }
}
```

**Response (Kotlin → Laravel):**
```json
{
  "success": true,
  "message": "Download complete: payroll.db",
  "stateChanges": {
    "exposure": 67,
    "firewall": 54,
    "filesDownloaded": ["payroll.db"]
  },
  "uiHints": {
    "animations": ["meter_update", "success_flash"],
    "sounds": ["download_complete"]
  },
  "nextState": "mission_active"
}
```

### 13.3 Error Handling

```
1. Validation Errors (user input)
   - Invalid command syntax
   - Command not available in context
   - Insufficient resources
   → Return error message to UI
   → Don't change state

2. System Errors (bugs)
   - Null pointer exceptions
   - Database errors
   - State inconsistency
   → Log error
   → Rollback transaction
   → Return generic error to user
   → Attempt recovery

3. Network Errors
   - Connection lost
   - Timeout
   → Retry logic
   → Session recovery
   → Don't lose player progress
```

### 13.4 Performance Optimization

| Critical Path | Target | Strategy |
|---------------|--------|----------|
| Command execution | <100ms | Cache frequently used data, optimize calculations |
| UI updates | <50ms | Incremental updates, only send changed state |
| State saves | <200ms | Async database writes, batch updates |
| Mission loading | <500ms | Preload definitions, cache network structures |

### 13.5 Testing Strategy

**Unit Tests (Kotlin):**
- Command cost calculations
- Detection probability math
- Loadout validation
- Tool effect application
- Combat resolution
- Defrag matching logic
- Economic calculations
- State transitions

**Integration Tests:**
- Complete mission end-to-end
- Combat trigger and resolution
- Tool purchase and usage
- Loadout building
- Defrag session
- Machine switching
- Sentinel seizure

**UI Tests (Laravel):**
- Command input/output
- Menu navigation
- Visual state updates
- Error display
- Responsive design

### 13.6 Configuration Management

All tunable parameters should be database/file-driven:
- Command costs (all commands)
- Tool costs (all tools)
- Mission payouts
- Detection formulas
- Combat difficulty
- Defrag complexity
- Repair costs
- Machine stats

Easy to adjust without code changes.

---

## 14. DATA SCHEMAS

### 14.1 Core Tables

```sql
players:
  - id (UUID, PK)
  - username
  - created_at
  - current_machine_id (FK)
  - credits
  - current_node (enum: home, mission, combat, defrag)
  - tutorial_completed (boolean)

machines:
  - id (UUID, PK)
  - player_id (FK)
  - machine_type (enum)
  - firewall_current
  - firewall_max
  - residual_exposure
  - storage_level
  - damage_level
  - flags (JSON)
  - is_active (boolean)

tool_inventory:
  - id (UUID, PK)
  - player_id (FK)
  - tool_type (enum)
  - version (enum: V1, V2, V3)
  - unlocked_at

loadout:
  - id (UUID, PK)
  - player_id (FK)
  - machine_id (FK)
  - loadout_data (JSON array of tools)

mission_history:
  - id (UUID, PK)
  - player_id (FK)
  - mission_id (string)
  - started_at
  - completed_at
  - success (boolean)
  - final_exposure
  - payout
  - objectives_completed (JSON)

combat_history:
  - id (UUID, PK)
  - player_id (FK)
  - mission_history_id (FK, nullable)
  - combat_type (enum)
  - outcome (enum: won, lost, extracted)
  - duration_seconds
  - puzzles_solved

defrag_sessions:
  - id (UUID, PK)
  - player_id (FK)
  - mission_history_id (FK, nullable)
  - starting_exposure
  - ending_exposure
  - traces_deleted
  - traces_remaining (JSON)
  - completed_at

transactions:
  - id (UUID, PK)
  - player_id (FK)
  - type (enum: mission_payout, tool_purchase, repair, etc.)
  - amount (can be negative)
  - balance_after
  - created_at
  - metadata (JSON)
```

### 14.2 Configuration Tables

```sql
mission_definitions:
  - id (string, PK)
  - title
  - difficulty (enum)
  - base_payout
  - network_structure (JSON)
  - objectives (JSON)
  - unlock_requirements (JSON)

tool_definitions:
  - id (UUID, PK)
  - tool_type (enum)
  - version (enum)
  - unlock_cost
  - prerequisite_version (nullable)
  - stats (JSON: activation costs, effects, etc.)
  - slot_cost

machine_definitions:
  - id (UUID, PK)
  - machine_type (enum)
  - base_stats (JSON)
  - upgrade_paths (JSON)
  - special_traits (JSON)

command_definitions:
  - id (UUID, PK)
  - command_name
  - category (enum)
  - base_costs (JSON)
  - effects (JSON)
  - available_contexts (JSON)
```

---

## 15. SEPARATION OF CONCERNS CHECKLIST

### Kotlin (Game Engine) — MUST Handle

- All game logic and rules
- State validation and transitions
- Calculations (exposure, firewall, detection, payouts)
- Combat resolution
- Puzzle generation and validation
- Defrag matching logic
- Tool effects and interactions
- Economic calculations
- Progression tracking
- Achievement validation
- Save/load game state
- API endpoints for Laravel to call

### Kotlin — MUST NOT Do

- Render HTML/CSS
- Handle user sessions (Laravel handles this)
- Manage authentication
- Serve static assets
- Direct database queries for UI

### Laravel (UI Layer) — MUST Handle

- Terminal UI rendering
- Input capture and sanitization
- Session management
- User authentication
- WebSocket connections
- Visual feedback (animations, colors, sounds)
- Command history display
- Real-time meter updates
- Menu displays
- Asset serving
- Calling Kotlin API endpoints

### Laravel — MUST NOT Do

- Game logic (no exposure calculations in PHP)
- State validation (Kotlin validates)
- Command execution logic
- Combat resolution
- Economic balance decisions
- Progression rules

### Database

- **Read Access:** Laravel can read directly for display (e.g., transaction history, achievements)
- **Write Access:** Kotlin is source of truth for game state writes

---

## 16. API CONTRACT (Laravel ↔ Kotlin)

### Key Endpoints

```
POST /api/game/command
  Execute a command
  Input: playerId, command, context
  Output: result, state changes, UI hints

POST /api/game/start-mission
  Initialize mission
  Input: playerId, missionId, loadout
  Output: mission state, network structure

POST /api/game/use-tool
  Activate equipped tool
  Input: playerId, toolType, context
  Output: tool effect, state changes

POST /api/game/defrag/match
  Match player event with sentinel trace
  Input: playerId, playerTimestamp, sentinelTimestamp
  Output: success, exposure reduction

GET /api/game/state
  Get current game state
  Input: playerId
  Output: complete state snapshot

POST /api/shop/purchase-tool
  Buy tool or upgrade
  Input: playerId, toolType, version
  Output: success, updated inventory, new balance

POST /api/shop/purchase-consumable
  Buy consumable tool
  Input: playerId, toolType, quantity
  Output: success, updated inventory, new balance

POST /api/loadout/update
  Update equipped loadout
  Input: playerId, loadoutData
  Output: validation result, updated loadout

POST /api/machine/switch
  Switch active machine
  Input: playerId, machineId
  Output: success, new machine state

POST /api/machine/repair
  Repair damaged machine
  Input: playerId, repairType
  Output: success, repaired state, cost
```

---

## 17. IMPLEMENTATION PRIORITIES

### P0 — Critical (Must Have)
- Player state management
- Basic command system
- Exposure/firewall mechanics
- Simple missions (1-3 files)
- Detection system
- Home node (view state, repair)
- Basic UI (terminal, meters)

### P1 — High Priority (Core Features)
- Tool system (permanent + consumable)
- Loadout builder
- Multi-node missions
- Combat system
- Defrag system
- Shop system
- Machine upgrades

### P2 — Medium Priority (Enhancement)
- Multiple machines
- Progression tracking
- Achievements
- Story integration
- Advanced missions
- Rival hackers
- Tutorial refinement

### P3 — Low Priority (Polish)
- Sound effects
- Visual polish
- Additional content
- Leaderboards
- Cosmetic options

---

## 18. SUCCESS METRICS

**Player Engagement:**
- Average session length > 30 minutes
- Return rate > 60% (players come back next day)
- Missions completed per player > 10

**Economic Balance:**
- Players can afford first permanent tool by mission 10-15
- 80% of players complete tutorial without quitting
- Net credits positive for 75% of missions (not constant loss)

**Difficulty Curve:**
- Early missions (1-10): 90% success rate
- Mid missions (11-30): 70% success rate
- Hard missions (31-50): 50% success rate

**Technical:**
- Command execution < 100ms
- UI update < 50ms
- Zero data loss from disconnections
- 99.9% uptime

---

## 19. FINAL NOTES

### Key Principles
1. **Kotlin owns all logic** — Laravel just displays results
2. **State is source of truth** — All decisions based on validated state
3. **Every action has cost** — No free lunches
4. **Player always knows the odds** — Transparency is key
5. **Failure is educational** — Not frustrating

### When in Doubt
- If it's game logic → **Kotlin**
- If it's display → **Laravel**
- If it's data → **Database**

### Testing Approach
- Unit test calculations in Kotlin
- Integration test full flows
- Manually test UI/UX feel
- Playtest for balance

### Balance Philosophy
- **Early game:** Forgiving, teaching
- **Mid game:** Challenging, rewarding
- **Late game:** Mastery, optimization
