# ARCHITECTURE.md — CodeCraft Living Technical Reference

> **Document Status:** Active — updated as of June 2026.
> This document reflects **only** what is written and deployed in the current codebase.
> No speculative features are described here.

---

## Table of Contents

1. [Executive Summary & Project Status](#1-executive-summary--project-status)
2. [Codebase & System Design](#2-codebase--system-design)
3. [Game Design & Core Philosophy](#3-game-design--core-philosophy)
4. [Applied Mechanics & Systems](#4-applied-mechanics--systems)
5. [Rulesets & Constraint Systems](#5-rulesets--constraint-systems)

---

## 1. Executive Summary & Project Status

### 1.1 Project Overview

CodeCraft is a browser-based multiplayer cyberpunk hacking simulator. Players navigate a 228-node SVG hex grid representing a dark-net city, hack nodes to accumulate credits, build their rig hardware profile, and engage other players in a multi-phase terminal PvP system called **Packet Hijack**. The game runs entirely within a single-page application shell with a terminal aesthetic — all in-game menus, store UIs, and documentation are served through an in-game diegetic browser called **SPLICE**.

### 1.2 Development Velocity & Architectural Health

The project is in active, high-velocity development. The migration log shows daily schema changes through June 2026, with large systems (Packet Hijack, CyberDoc, node traps, bounty leaderboard) having been fully implemented. The architecture is structurally sound with clear separation of concerns enforced by hard-coded size limits (controllers < 300 lines, services < 500 lines). Technical debt is low — the codebase has no God classes and no mixed-concern controllers.

**Current state of major systems:**

| System | Status |
|---|---|
| Hex grid navigation (228 nodes) | ✅ Complete |
| Rig stat engine + chassis catalog | ✅ Complete |
| System Stability (SS) + degradation | ✅ Complete |
| GridBreach PvE minigame | ✅ Complete |
| Packet Hijack PvP (Phases 1 + 2 + 3) | ✅ Complete |
| Bounty board + Open Season | ✅ Complete |
| CyberDoc NPC economy hub | ✅ Complete |
| Command catalog (map + hack) | ✅ Complete |
| Node traps + decoys | ✅ Complete |
| Peripheral install + loadout system | ✅ Complete |
| SPLICE in-game browser | ✅ Complete |
| Laravel Reverb WebSocket layer | ⚙️ Configured, active in production |
| Tutorial quest system | ✅ Complete |

### 1.3 Tech Stack Integration

```
┌─────────────────────────────────────────────────────┐
│                   CLIENT (Browser)                  │
│  Vue 3 + Inertia.js SPA                             │
│  Composables ←→ Components (strict segregation)     │
│  Laravel Echo ←→ Reverb (WebSocket)                 │
└───────────────────────┬─────────────────────────────┘
                        │ HTTPS + Cookie Session
                        │ WebSocket (WSS)
┌───────────────────────▼─────────────────────────────┐
│                  SERVER (EC2)                        │
│  Laravel 11 / PHP 8.5                               │
│  Routes → Controllers → Services → Models           │
│  Laravel Sanctum (session auth for SPA)             │
│  Laravel Reverb (WebSocket broadcast driver)        │
│  SQLite (dev) / production DB                       │
└─────────────────────────────────────────────────────┘
```

**Auth model:** Sanctum session cookies for the SPA. Bearer tokens are only issued via `POST /api/auth/token` for any future external engine integration. All game API routes are guarded by `auth:sanctum` middleware.

**Real-time:** Events implement `ShouldBroadcastNow` (synchronous, no queue delay) and broadcast over private player channels (`player.{id}`) or the public `bounty-board` channel via Laravel Reverb. The client subscribes via Laravel Echo in `usePacketHijack.js` and `useWebSocket.js`.

---

## 2. Codebase & System Design

### 2.1 Directory & File Infrastructure

```
api/
├── app/
│   ├── Constants/
│   │   └── PacketHijackConstants.php    ← Pure static data — port catalogs,
│   │                                       chain anomaly text, flare pools
│   ├── Events/                          ← ShouldBroadcastNow events (Reverb)
│   │   ├── BountyBoardUpdated.php
│   │   ├── CombatChallengeReceived.php
│   │   ├── PacketHijackCommandResult.php
│   │   ├── PacketHijackMatchComplete.php
│   │   ├── PacketHijackPhaseTransition.php
│   │   ├── PacketHijackStarted.php
│   │   ├── PlayerCombatStateChanged.php
│   │   └── TrapTriggered.php
│   ├── Http/Controllers/               ← HTTP handoff only. No business logic.
│   │   ├── Auth/LoginController.php
│   │   ├── Auth/RegisterController.php
│   │   ├── AuthController.php          ← Bearer token issuance
│   │   ├── BountyController.php
│   │   ├── CombatChallengeController.php
│   │   ├── CombatController.php
│   │   ├── CyberDocController.php
│   │   ├── InventoryController.php
│   │   ├── NodeController.php
│   │   ├── PacketHijackController.php
│   │   ├── PlayerController.php
│   │   ├── RigController.php
│   │   ├── StoreController.php
│   │   └── TutorialController.php
│   ├── Models/                         ← Schema blueprints. Relationships +
│   │   │                                  casts + fillable only.
│   │   ├── ChassisTemplate.php
│   │   ├── CombatChallenge.php
│   │   ├── Command.php
│   │   ├── Consumable.php
│   │   ├── CyberDoc.php / CyberDocCatalog / CyberDocInventory
│   │   ├── HardwareEncrypt.php
│   │   ├── Node.php
│   │   ├── NodeConnection.php
│   │   ├── NodeTrace.php
│   │   ├── NodeTrap.php
│   │   ├── PacketHijackMatch.php       ← Rich helper methods for role/phase access
│   │   ├── Peripheral.php / PlayerPeripheral
│   │   ├── Player.php
│   │   ├── PlayerCommand.php
│   │   ├── PlayerConsumable.php
│   │   ├── PlayerRig.php
│   │   └── User.php
│   └── Services/                       ← All authoritative game math + mutations
│       ├── BountyEvent.php             ← Value object (DTO)
│       ├── BountyService.php
│       ├── CyberDocInventoryService.php
│       ├── CyberDocService.php
│       ├── InventoryService.php
│       ├── NodeService.php
│       ├── PacketHijackLifecycleService.php
│       ├── PacketHijackMatchSetupService.php
│       ├── PacketHijackPhase1Service.php
│       ├── PacketHijackPhase2Service.php
│       ├── PacketHijackService.php     ← Orchestrator — delegates to sub-services
│       ├── RigService.php
│       └── StreetDocInventoryService.php
├── database/
│   ├── migrations/                     ← Immutable append-only history
│   └── seeders/                        ← Catalog seeders for chassis, commands,
│                                          consumables, nodes, CyberDoc NPCs
└── resources/js/
    ├── Pages/
    │   ├── Game.vue                    ← Root integration layer only
    │   └── Auth/Login.vue, Register.vue
    ├── components/                     ← Dumb presentational shells
    │   ├── browser/                    ← InGameBrowser + SpliceRouter + SPLICE pages
    │   ├── layout/                     ← GameScreen, HUD, NavBar, SidePanel, GameMenu
    │   ├── map/                        ← HexMapCanvas (SVG), NodeWindow, PingMarker
    │   ├── minigame/                   ← GridBreach (PvE), PacketHijack (PvP)
    │   ├── panel/                      ← BountyBlock, LoadoutBlock, NodeInfoBlock
    │   └── shared/                     ← BootSequence, NeonBorder, SSBar, TerminalText,
    │                                      CommandHitNotification, TrapFiredNotification
    └── composables/                    ← All reactive state + API communication
        ├── useAuth.js
        ├── useBountyBoard.js
        ├── useBrowser.js / useBrowserState.js
        ├── useCombat.js
        ├── useCyberDoc.js
        ├── useDepletion.js
        ├── useGameState.js
        ├── useHeartbeat.js
        ├── useMapData.js
        ├── useMapInteraction.js
        ├── useNodeIdentity.js
        ├── useNodePresence.js
        ├── useNodeTraces.js
        ├── usePacketHijack.js
        ├── usePingSystem.js
        ├── usePosition.js
        ├── useRigDamage.js
        ├── useTrapSystem.js
        ├── useTutorial.js
        ├── useUpgradeCosts.js
        └── useWebSocket.js
```

**Why this structure:** The project enforces a strict 3-layer architecture: Controllers are pure HTTP dispatchers (<300 lines), Services are the authoritative game engines (<500 lines), and Models are schema-only Eloquent blueprints. The PacketHijack subsystem alone is split into 5 services (`PacketHijackService`, `PacketHijackLifecycleService`, `PacketHijackMatchSetupService`, `PacketHijackPhase1Service`, `PacketHijackPhase2Service`) specifically to keep each file under the 500-line ceiling while separating orchestration, lifecycle management, procedural generation, and per-phase logic.

### 2.2 Data Flow & State Management

#### Boot sequence

```
Browser loads /  →  Inertia renders Game.vue shell
                →  useAuth fetches GET /api/player/me
                →  useGameState.hydrateFromAuth() populates player + rig refs
                →  useMapData fetches GET /api/nodes (228 nodes, once)
                →  useHeartbeat starts keepalive loop
                →  useNodePresence begins polling current node for nearby players
```

#### Typical player action flow

```
User interaction (e.g. move to node)
  →  useMapInteraction.handleNodeClicked()
  →  usePosition.POST /api/player/position
  →  PlayerController validates + persists current_node_id
  →  [optional] TrapTriggered event broadcast if enemy trap consumed
  →  Client-side refs updated reactively
```

#### Packet Hijack PvP data flow

```
Challenger: POST /api/combat/challenge
  →  CombatChallengeController creates pending CombatChallenge (30s TTL)
  →  CombatChallengeReceived event broadcast to target's private channel
  →  Target: POST /api/combat/challenge/{id}/accept
  →  PacketHijackMatchSetupService generates match data for both sides
  →  PacketHijackMatch persisted with all generated state (JSON columns)
  →  PacketHijackStarted events broadcast to both players
  →  Both clients initialize PacketHijack terminal UI

During match:
  →  Player: POST /api/packet-hijack/{match}/command (input string)
  →  PacketHijackController: DB::lockForUpdate on match row
  →  PacketHijackService delegates to Phase1Service or Phase2Service
  →  Result persisted; PacketHijackCommandResult event broadcast
  →  Client receives result via WebSocket, updates terminal output

On match complete:
  →  PacketHijackMatchComplete event broadcast to both players
  →  BountyService.resolvePvpLoot() + recordPvpWin() applied
  →  RigService.applyDamage() applied to loser
```

#### State persistence model

| Concern | Where Stored |
|---|---|
| Player economy (wallet, pocket, tech points) | `players` table |
| Rig invested points | `player_rigs` table |
| Active run state (bounty, SS, uplink) | `players` + `player_rigs` |
| PacketHijack match state (ports, suspects, chain, filesystem) | `packet_hijack_matches` (JSON columns) |
| Node depletion timestamps | `nodes` table |
| Node traces + traps | `node_traces`, `node_traps` tables |
| Peripheral install state | `player_peripherals` table |
| CyberDoc cooldowns | `players.cyberdoc_cooldowns` (JSON map) |
| Active player effects | `players.active_effects` (JSON array) |

### 2.3 Design Patterns

**MVC (Laravel variant):** Controllers handle HTTP request/response. Services own business logic and mutations. Models are pure Eloquent schema definitions. No business logic in models — no custom accessors or mutation math (all moved to domain Services).

**Service Decomposition Pattern:** The Packet Hijack engine is decomposed into a hierarchy of single-responsibility services:
- `PacketHijackService` — command routing orchestrator
- `PacketHijackPhase1Service` — pure functional recon commands (no I/O)
- `PacketHijackPhase2Service` — pure functional exploit chain commands (no I/O)
- `PacketHijackMatchSetupService` — procedural match generation (requires `RigService`)
- `PacketHijackLifecycleService` — match start/resolve lifecycle

**Value Object / DTO:** `BountyEvent` is a plain value object returned from `BountyService` methods to carry the event type and payload without coupling service methods to HTTP response shapes.

**Observer / Event Broadcasting:** Laravel Events implement `ShouldBroadcastNow`. The system uses Reverb as the broadcast driver. Events are dispatched from Controllers after service mutations are committed, ensuring the database is consistent before the WebSocket notification fires.

**Command Pattern (terminal):** Packet Hijack terminal commands (`netstat`, `ping`, `traceroute`, `arp-scan`, `whois`, `sniff`, `scan`, `probe`, `trace`, `exploit`, `breach`, `ls`, `cd`, `extract`) are parsed as strings by `PacketHijackService` and dispatched to isolated method handlers. Each command is a pure function — it receives state, transforms it, and returns a result payload.

**Constants Extraction Pattern:** `PacketHijackConstants` is a `final` class with a private constructor — a pure static data store containing port service catalogs, chain anomaly text pools, red herring anomaly pools, and port flare line pools. It is imported by services that need this data to stay under the 500-line limit without duplicating definitions.

**Composable Pattern (Vue 3):** Every domain of reactive state and API interaction lives in a dedicated composable. `Game.vue` is an integration layer only — it wires composables together and routes events between them but contains zero business logic.

---

## 3. Game Design & Core Philosophy

### 3.1 Diegetic Framework

CodeCraft is built on strict diegetic immersion — every UI element is presented as a terminal artifact within the fiction of the game world. There is no traditional game menu. The HUD reads like a system process monitor. The store, documentation, and NPC interactions all happen inside an in-game web browser (SPLICE) that renders actual Vue components behind a fictional URL routing system (`SpliceRouter.js`).

**Atmospheric consistency rules enforced in code:**
- **JetBrains Mono** is the only font used across all UI, enforcing monospace terminal aesthetics
- Shared atomic components (`NeonBorder.vue`, `SSBar.vue`, `TerminalText.vue`) are used everywhere — no bespoke inline styles permitted
- `BootSequence.vue` plays a terminal boot animation before the game canvas becomes visible on first load
- All Packet Hijack output is rendered as terminal line arrays using bracket prefixes (`[EXPLOIT]`, `[CONFIRMED]`, `[FAILED]`) to simulate real shell output

### 3.2 Narrative Pacing via Architecture

The bounty system creates a narrative arc per run: a player begins invisible, becomes "on the board" at 10 node hacks, escalates through 5 star tiers, and ultimately enters **Open Season** — a state where all other players can attack them for maximum reward. This arc is entirely state-machine driven by `BountyService` and visible to all clients via the `bounty-board` public WebSocket channel.

The two-economy model (`pocket_creds` vs `wallet_creds`) enforces risk-reward tension: pocket creds are accumulated during a run and can be stolen in PvP or lost on elimination; wallet creds are safe but require a CyberDoc visit to bank. This single design decision drives all player decision-making on the map.

The **CyberDoc NPC** (formerly Street Doc) is the game's narrative hub: it is simultaneously the bank, repair station, gear shop, and loadout manager. Visiting the CyberDoc breaks a run, creates a 10-minute cooldown, and resets the bounty counter — it is mechanically costly but essential.

---

## 4. Applied Mechanics & Systems

### 4.1 Hex Grid Navigation

**File:** `HexMapCanvas.vue`, `useMapInteraction.js`, `useMapData.js`, `NodeController.php`, `NodeService.php`

- The map is a 228-node SVG hex grid. All nodes are fetched once at boot via `GET /api/nodes` and cached in `useMapData.js`.
- Adjacency is stored bidirectionally in the `node_connections` table. Movement is only permitted to directly connected nodes (`Node::adjacentNodes()` via `BelongsToMany`).
- Each node has a `canvas_id` (stable coordinate reference), `type`, `tier`, `ice` rating, `is_spawn`, `is_safe_zone`, `zone_type`, `zone_group`, and `npc_handle` fields.
- `POST /api/player/position` persists movement and triggers trap detection — any `NodeTrap` on the destination node left by another player is consumed and `TrapTriggered` is broadcast to the trap placer.
- `GET /api/nodes/{canvasId}/players` polls every 3 seconds (via `useNodePresence.js`) to show co-located players.

**Inputs:** Node click event → `useMapInteraction.handleNodeClicked()` → adjacency check → movement permitted or blocked  
**Outputs:** `POST /api/player/position` → updated `current_node_id`, optional trap trigger event

### 4.2 Rig Stat System

**File:** `RigService.php`, `ChassisTemplate.php`, `PlayerRig.php`

The rig has 5 investable stats: **CPU**, **RAM**, **Firewall**, **Storage**, and **OS**. Stats are stored as invested point levels above a chassis base value. The effective stat seen by the game engine is:

```
effective = base_value + invested_level + peripheral_boost + degradation_penalty
```

**Chassis tiers:**

| Chassis | Tier | Total Point Cap | Uplink | Peripheral Slots |
|---|---|---|---|---|
| BlackHat v1.0 | 1 | 9 | 3 | 0 |
| NullTek GX-7 Ghost | 2 | 18 | 7 | 2 |
| NullTek BR-9 Breaker | 2 | 18 | 5 | 2 |
| NullTek VT-3 Vault | 2 | 18 | 5 | 3 |

**Parasite Stat mechanic:** When `totalPointsSpent >= total_point_cap` and the player upgrades a stat, the engine walks a cyclic downgrade ring (`OS → RAM → CPU → Storage → Firewall → OS`) and deducts 1 point from the first eligible stat above the minimum floor (level 1). This forces players to make trade-offs at the cap rather than blocking upgrades entirely.

**OS gate:** CPU, RAM, and Firewall cannot be invested beyond `effective_OS + effective_Storage`. OS and Storage must be raised first to unlock the headroom.

**Loadout slots** are chassis-defined (`base_map_slots`, `base_hack_slots`, `base_open_slots`) and can be expanded by installing `command_module` peripherals (Nav Wraith → +1 map slot, ICE Pick → +1 hack slot).

### 4.3 System Stability (SS)

**File:** `RigService.php`

SS is a universal flat 100 for all chassis — no stat determines the ceiling. It represents the rig's operating health.

**Damage sources:** PvE (node ICE) and PvP (Packet Hijack outcome).

**Degradation tiers** (cumulative, applied silently inside `effectiveStats()`):

| SS Lost | CPU Penalty | RAM Penalty | Firewall Penalty | OS Penalty |
|---|---|---|---|---|
| ≥ 20% | −1 | −1 | −1 | −1 |
| ≥ 40% | −1 | −1 | −1 | −1 |
| ≥ 60% | −1 | −1 | −1 | −1 |
| ≥ 80% | −1 | −1 | −1 | −1 |

Storage is never penalised (physical capacity, not performance).

**Failure states:**

| Condition | Source | Outcome |
|---|---|---|
| `SS ≤ 0` | PvE | Limp mode: SS floored at 1, `is_limping = true`, player stays on map |
| `SS ≤ 0` | PvP | Cyber doc reset: SS restored to 100, one random stat level lost, player respawns at last CyberDoc |
| `0 < SS < 25` | Any | Warning state: `is_limping` set on rig and player, HUD indicates danger |

**Partial repair** is possible via consumable repair kits (`RigService::repairPartial()`). Full repair is done at the CyberDoc.

### 4.4 GridBreach (PvE Minigame — Strictly PvE)

**File:** `GridBreach.vue`, `NodeController.php`, `NodeService.php`

GridBreach is the **PvE-only** node-hacking minigame. It is mounted exclusively from `Game.vue` via `v-if="activeHack"` — the node-hack flow. PvP combat uses Packet Hijack exclusively. `GridBreach.vue` contains a `pvpMode` prop and associated command logic that is written but never wired in the current integration layer — it is not connected to the PvP challenge flow and does not fire in live play.

A player enters GridBreach by selecting a node and choosing a hackable resource (creds, tech points, or uplink). The minigame receives `player_cpu`, `player_ram`, `player_os`, `player_firewall`, `player_max_uplink`, and `bounty_multiplier` as props from `Game.vue`.

On completion, `POST /api/nodes/{nodeId}/deplete` is called via `useDepletion.js`. The server records the depletion timestamp and current cred value. On success, `BountyService::recordNodeHack()` is called, incrementing the run's hack counter and evaluating bounty threshold transitions.

**Inputs:** Node selection → resource type → minigame UI interaction  
**Outputs:** `POST /api/nodes/{nodeId}/deplete` → `BountyBoardUpdated` event broadcast → client bounty ticker updates

### 4.5 Bounty System

**File:** `BountyService.php`

Tracks a player's escalating risk profile per run. The raw hack count drives all calculations.

**Bounty tier ladder (hack count → star level):**

| Hack Count | Star Level | Base Multiplier | State |
|---|---|---|---|
| < 10 | 0 (off board) | 1.00× | No bounty |
| 10–14 | ★ (Level 1) | 1.25× | On bounty board |
| 15–19 | ★★ (Level 2) | 1.50× | |
| 20–24 | ★★★ (Level 3) | 1.75× | |
| 25–29 | ★★★★ (Level 4) | 2.00× | Open Season |
| 30+ | ★★★★★ (Level 5) | 2.25× | |

**Two routes to Open Season:** ≥ 25 node hacks in a run, or ≥ 5 PvP wins while on the board.

Each PvP win adds +0.15 to `bounty_multiplier`, capped at 5.00. The multiplier persists within a run and resets on CyberDoc banking.

The `BountyBoardUpdated` event is broadcast to the public `bounty-board` channel after any qualifying node hack; the client re-polls `GET /api/leaderboard/bounty` on receipt.

### 4.6 PvP Combat Handshake

**File:** `CombatChallengeController.php`, `CombatChallenge.php`

1. Challenger posts `POST /api/combat/challenge` with `target_id` and `node_canvas_id`.
2. Server creates a `CombatChallenge` record with a 30-second TTL and broadcasts `CombatChallengeReceived` to the target's private channel.
3. Target responds within 30 seconds: `accept` → Packet Hijack match created; `decline` → 20 SS damage + bounty-scaled pocket steal applied to the decliner.
4. A player already in an active challenge cannot be targeted by a third party (422 response).

**Decline penalty:** Running is never free. The decliner takes the same financial hit as losing without fighting — `BountyService::resolvePvpLoot()` is called using the decliner's current steal percentage.

### 4.7 Packet Hijack (PvP Minigame — Three Phases)

**Files:** `PacketHijackController.php`, `PacketHijackService.php`, `PacketHijackPhase1Service.php`, `PacketHijackPhase2Service.php`, `PacketHijackMatchSetupService.php`, `PacketHijackLifecycleService.php`, `PacketHijackMatch.php`, `PacketHijack.vue`, `usePacketHijack.js`

Packet Hijack is the asymmetric PvP terminal. Both players operate simultaneously — each attacking the other's rig while defending their own. All match state is stored in a single `packet_hijack_matches` row as JSON columns, updated transactionally with `lockForUpdate` on every command to prevent race conditions.

#### Phase 1 — Network Recon

The attacker receives a list of suspect IPs (one is the real target). Commands reveal forensic attributes to identify the target:

| Command | Effect |
|---|---|
| `netstat --active` | Returns the full suspect list (IP + flushed status) |
| `ping <ip>` | Returns `latency_ms` and `latency_status` for one suspect |
| `traceroute <ip>` | Returns `hops` and `network_range` |
| `arp --scan` | Returns `last_seen_seconds` for all suspects simultaneously |
| `whois <ip>` | Returns `whois_class` (chassis class hint) — redacted if target OS is high |
| `sniff --traffic` | Intercepts one middle octet from the real target's live stream |
| `flush <ip>` | Removes a suspect from the active list |
| `inject <ip>` | Adds a fake decoy IP to the suspect pool |

Phase 1 ends when the attacker issues a `lock <ip>` command with their target guess. Correct identification advances to Phase 2. Incorrect identification costs a penalty.

#### Phase 2 — Exploit Chain

The attacker sees 5 ports (4 random services + the `8080` exfil port). One port has a LOW bias (exploitable entry point). The remaining ports form an ordered dependency chain. The attacker must:

1. `scan <ip>` — reveals port list and service names
2. `probe <port>` — fingerprints a port, reveals banner lines and an anomaly (chain or red herring)
3. `trace <port1> <port2>` — tests a hypothesized chain link (confirmed = no attempt cost, reversed = partial signal, no link = consumes 1 trace attempt)
4. `exploit <port>` — shatters a port in the correct chain order
5. `breach <ip>` — final command after all chain ports are shattered, opens auth prompt

The anomaly text in probe banners is drawn from `PacketHijackConstants::CHAIN_ANOMALIES` (keyed by service pair) and `REDHERRING_ANOMALIES` (scaled to target OS tier — higher OS = harder-to-distinguish red herrings).

**Credential fragments** are progressively revealed as chain ports are shattered. The full credential (hostname + OS) is split into tiers and hidden inside port probe banners during match setup by `PacketHijackMatchSetupService::generateFingerprint()`.

**Rig commands** can be deployed once per match (Phase 2): `overclock` (skip chain order for one exploit), `mirror` (reflect next opponent command), `sector-corrupt` (fake port bias), `bait-trap` (honeypot a port). These are drawn from the player's equipped hack command loadout.

**Bait (honeypot) traps** lock the attacker's input for a configurable number of seconds if they attempt to exploit a baited port.

#### Phase 3 — Bank Extraction

After successful `breach` + credential auth, the attacker gains access to the target player's `bank_balance` (a snapshot of the target's pocket_creds taken at auth time). The attacker then navigates a virtual filesystem (`ls`, `cd`, `extract`) to locate the wallet file and execute `POST /api/packet-hijack/{match}/transfer` to claim the funds.

**Match resolution:** The first player to complete Phase 3 extraction wins. `PacketHijackLifecycleService` applies `BountyService::resolvePvpLoot()` and `RigService::applyDamage()`. A `PacketHijackMatchComplete` event is broadcast to both players.

### 4.8 Node Traps & Decoys

**File:** `NodeController.php`, `NodeTrap.php`, `NodeTrace.php`, `useTrapSystem.js`

Map commands with `type = 'trap'` can be planted on adjacent nodes via `POST /api/nodes/{canvasId}/place-trap`. When another player moves to a trapped node, the trap is consumed server-side in `PlayerController::position()` and `TrapTriggered` is broadcast to the placer. Trap effects (SS damage, uplink drain, cache disruption) are resolved server-side.

Node decoys (`type = 'decoy'`) are ghost trace markers placed to mislead enemy players reading trace data via `GET /api/nodes/{canvasId}/traces`.

### 4.9 CyberDoc NPC Hub

**File:** `CyberDocController.php`, `CyberDocService.php`

The CyberDoc is the game's economic and gear hub. All operations are gated by a per-NPC 600-second (10-minute) cooldown stored in `player.cyberdoc_cooldowns` as a JSON timestamp map.

| Endpoint | Operation |
|---|---|
| `POST /cyberdoc/visit` | Restores current_uplink to full, records cooldown |
| `POST /cyberdoc/bank` | Banks all pocket_creds to wallet, resets run counters |
| `POST /cyberdoc/repair` | Fully restores SS to 100, clears limp state |
| `POST /cyberdoc/install` | Installs a peripheral into the rig |
| `POST /cyberdoc/loadout` | Assigns commands to loadout slots |
| `POST /cyberdoc/reallocate` | Moves 1 invested stat point between two stats (no ring tax) |
| `POST /cyberdoc/upgrade-command` | Upgrades a command by 1 level for TP cost |
| `POST /cyberdoc/purchase` | Purchases gear from the CyberDoc's inventory catalog |

### 4.10 Command System

**File:** `CommandSeeder.php`, `PlayerCommand.php`, `Command.php`, `useGameState.js`

Commands are purchased from the store or CyberDoc and equipped into typed loadout slots. There are two contexts:

- **Map commands (11):** Used during map traversal. Include stealth buffs (Ghost Protocol, Dark Mode), trap placement (Crash Wire, Uplink Drain, EMP Pulse, Cache Flush), and movement tools (Overclock Jump, Signal Noise, Blackout).
- **Hack commands (12):** Used inside Packet Hijack (PvP) sessions. Include PHJ rig abilities (Overclock, Mirror Protocol, Sector Corrupt, Bait Trap). `GridBreach.vue` contains a command panel that references these commands, but it is not connected to the live PvP flow — Packet Hijack is the active PvP combat system.

All commands have `max_level = 3` with explicit `level_scaling` JSON definitions. Single-use per run unless level 3, which grants an extra use. Trap-type commands are single-use per placement.

### 4.11 SPLICE In-Game Browser

**File:** `InGameBrowser.vue`, `SpliceRouter.js`, `components/browser/pages/`

SPLICE is the diegetic UI layer. It renders as a terminal-styled browser window inside the game canvas. `SpliceRouter.js` maps fictional URLs (e.g. `splice://sys/rig`, `splice://cyberdoc/axiom`) to Vue components. Navigating SPLICE never leaves the SPA.

SPLICE pages include: the player's rig stats (`SysRig.vue`), command catalog (`SysCommands.vue`), inventory (`SysInventory.vue`), stat guide (`SysStatGuide.vue`), how-to-play documentation, minigame guides, and all six CyberDoc NPC storefronts.

---

## 5. Rulesets & Constraint Systems

### 5.1 Upgrade Cost Formula

**File:** `RigService::statUpgradeCost()`

All upgrade costs are computed server-side — the client's `useUpgradeCosts.js` is a display-only projection. The server-authoritative formula:

```
cost = baseCost
     × (1.60 ^ sameStatInvested)    -- per-stat scaling
     × (1.25 ^ totalPointsSpent)    -- global progression scaling
     × (1.80 ^ (chassisTier − 1))   -- chassis tier multiplier
```

**Base costs per stat:**

| Stat | Cred Base | TP Base |
|---|---|---|
| CPU | 150 | 2 |
| RAM | 100 | 1 |
| OS | 120 | 1 |
| Storage | 80 | 0 |
| Firewall | 200 | 3 |

### 5.2 Rig Constraint Hierarchy

1. **Per-stat chassis cap:** Effective value (base + invested) cannot exceed `cap_{stat}` from `ChassisTemplate`.
2. **OS + Storage ceiling:** CPU, RAM, and Firewall cannot be invested beyond `effective_OS + effective_Storage`. OS or Storage must be raised first.
3. **Total point cap:** Sum of all invested levels cannot exceed `chassis.total_point_cap` (9 Tier 1, 18 Tier 2). When at cap, Parasite Stat ring tax applies on the next upgrade.
4. **CPU = command level cap:** Any equipped command whose level exceeds `effective_CPU` is automatically deactivated (`enforceCpuCommandCap()` runs after every stat change).
5. **Minimum stat floor:** No stat may be reduced below level 1 via the Parasite or reallocate systems.

### 5.3 Steal Percentage Formula

**File:** `BountyService::calculateStealPercentage()`

```
Off-board  (bounty_level < 10)   →  10.0% fixed
On-board   (10 ≤ level < 15)     →  25.0% fixed
On-board   (15 ≤ level < 25)     →  25.0 + ((level − 15) / 10) × 35.0  [linear interpolation, capped at 75%]
Open Season                       →  87.5% fixed (no cap applied)
```

**Survivable loss:** Winner takes `steal_pct%` of loser's pocket. Loser keeps the rest.  
**Elimination (SS → 0 in PvP):** Winner takes `steal_pct%`. ICE seizes the remainder. Loser's pocket is fully zeroed.

### 5.4 Bounty Multiplier

Each PvP win while `bounty_level ≥ 10` adds +0.15 to `bounty_multiplier`, capped at 5.00. The multiplier can never be lowered below the current tier's base value:

| Star Tier | Tier Base Multiplier |
|---|---|
| 5 | 2.25× |
| 4 | 2.00× |
| 3 | 1.75× |
| 2 | 1.50× |
| 1 | 1.25× |
| 0 | 1.00× |

### 5.5 Node Economy Constraints

**File:** `NodeService.php`

```
Cred value after hack:
  multiplier = min(3.0, 1.0 + (minutes_untouched / 240) × (3.0 − 1.0))
  payout = base_cred_value × multiplier

Depletion cooldown:
  creds:    10 minutes before resource is re-exploitable
  movement: 10 minutes before resource is re-exploitable
  Max multiplier (3×) reached after 240 minutes untouched
```

### 5.6 CyberDoc Cooldown

Per-NPC cooldown of **600 seconds (10 minutes)**. Each CyberDoc NPC on the map tracks a separate cooldown entry in `player.cyberdoc_cooldowns` (JSON map keyed by `canvas_id`). A player can visit a different CyberDoc immediately to avoid the cooldown — it is NPC-specific, not global.

### 5.7 Packet Hijack Generation Constraints

**File:** `PacketHijackMatchSetupService.php`

- Port topology: 4 ports selected randomly from the catalogue (excluding 8080), plus the 8080 exfil port. Exactly 1 port receives a LOW bias (8–18% + `firewall × 3`, capped below exploit threshold of 25%).
- High-bias ports: 70–90% + `firewall × 3` (capped at 98%).
- **Bias-to-exposure mapping:** `≤20% → CRITICAL`, `≤40% → HIGH`, `≤65% → MODERATE`, `≤85% → LOW`, else `MINIMAL`.
- Red herring anomalies scale by target OS tier: OS 1–3 = obvious vague language, OS 4–6 = plausible-sounding generic text, OS 7+ = language closely mimicking chain anomaly phrasing.
- Credential hostname and OS are each split into 3 tiers and hidden inside port probe banner lines. The `fragment` field is stripped from any client-facing payload (`fingerprintPublicView()`).
- Trace attempts are limited — incorrect trace guesses consume 1 attempt each (correct direction costs nothing; reversed direction returns a partial signal at no cost).

### 5.8 API Rate Limits

| Endpoint | Limit |
|---|---|
| `POST /api/auth/token` | 10/min |
| `POST /login` | 10/min |
| `POST /register` | 5/hour |
| `POST /player/position` | 120/min |
| `POST /player/heartbeat` | 10/min |
| `POST /nodes/{nodeId}/deplete` | 60/min |
| `POST /packet-hijack/{match}/command` | 120/min |
| `POST /tutorial/reward` | 10/min |

### 5.9 PvP Decline Penalty

- **SS damage:** Flat 20 SS applied via `RigService::applyDamage(source: 'pvp')` — can trigger limp mode or cyber_doc_reset if SS ≤ 20.
- **Pocket steal:** `BountyService::resolvePvpLoot()` using the decliner's current steal percentage. Decliner's bounty level is not changed — only CyberDoc extraction or PvP elimination resets the bounty.

### 5.10 Chassis Upgrade Constraints

**File:** `RigService::chassisUpgradeCost()`

Chassis upgrades are one-directional (Tier 1 → Tier 2 only). Cost is server-authoritative:

| Target Chassis | Cred Cost | TP Cost |
|---|---|---|
| NullTek GX-7 Ghost | 5,000 | 25 |
| NullTek BR-9 Breaker | 5,000 | 25 |
| NullTek VT-3 Vault | 5,000 | 25 |

Invested stat points from the BlackHat chassis carry forward. The new chassis's higher base values and caps take effect immediately. Uplink is chassis-locked — the only way to increase it is a new chassis.

---

*Last updated: June 2026 — reflects all migrations and features through `2026_06_05`.*
