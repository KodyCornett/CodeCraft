# CodeCraft — Developer Reference

## Server Paths

| Location | Path |
|---|---|
| **EC2 project root** | `/var/www/codecraft` |
| **Laravel API** | `/var/www/codecraft/api` |
| **Run migrations** | `cd /var/www/codecraft/api && php artisan migrate` |

---

## Terminology — Important

> **The network in CodeCraft is called The Splice Frequency, shortened to SPLICE.**
> Any reference to "the Matrix" (from films or Shadowrun) = The Splice Frequency / SPLICE in this codebase.
> This applies to all narrative text, code comments, variable names, and documentation.
> "Jacking in" = connecting to SPLICE. "The network" = SPLICE. "The grid" = SPLICE.

---

## What This Game Is

CodeCraft is a real-time multiplayer cyberpunk hacking game. Players move across a hex node map, hack nodes to earn creds and tech points, manage a bounty system that makes them increasingly visible to ICE and other players, and engage in PvP Grid-Breach combat. The game runs in a browser (Inertia + Vue 3) backed by a Laravel 11 API. The underground network runners operate on is called **The Splice Frequency (SPLICE)** — a hidden channel carved into the city's infrastructure that exists beneath the corporate grid.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.5, SQLite (dev) |
| Auth | Laravel Sanctum — session-based for the SPA |
| Frontend | Vue 3 (Composition API), Inertia.js, Vite |
| Realtime | Laravel Reverb (WebSocket) — live via Laravel Echo (`window.Echo`, gated by `VITE_ENGINE_ENABLED`) |
| Font | JetBrains Mono (monospace throughout) |
| Map | SVG hex grid, 228 canvas nodes |

---

## Project Layout

```
api/
  app/
    Http/Controllers/            One controller per resource. Keep controllers thin.
      Auth/LoginController       Web session login/logout (GET/POST /login, POST /logout)
      Auth/RegisterController    New player registration (GET/POST /register)
      AuthController             POST /api/auth/token — Bearer token for external engine only
      PlayerController           /api/player/me, /api/player/position, /api/player/heartbeat
      NodeController             /api/nodes, /api/nodes/{id}/players, /api/nodes/{id}/deplete
      RigController              /api/rig CRUD + /api/rig/upgrade, /api/rig/chassis-upgrade
      BountyController           /api/leaderboard/bounty, /api/leaderboard/open-season, /api/player/{id}/extract
      CyberDocController         /api/cyberdoc/* — banking, repair, install, loadout, reallocate, upgrade-command
      CombatController           /api/combat/result
      CombatChallengeController  /api/combat/challenge/* — PvP challenge handshake
      PacketHijackController     /api/packet-hijack/{match}/command, /api/packet-hijack/{match}/state
      StoreController            /api/store/catalog, /api/store/purchase-*
      InventoryController        /api/inventory, /api/inventory/use
      TutorialController         /api/tutorial/reward
      DocChatController          GET/POST /api/doc-chat/{hubCanvasId}/messages — history + send,
                                 gated by DocChatService::playerIsAtHub()
    Models/
      User                  Laravel auth user (email + password)
      Player                Game identity — handle, economy, bounty state, active_effects
      PlayerRig             Rig stats (invested levels, current_ss)
      ChassisTemplate       Chassis catalog — base stats + caps per tier
      Command               Command definition — name, context, level, duration
      PlayerCommand         Player's owned commands + loadout_slot assignment
      Peripheral            Peripheral definition — type, stat_boosted, price
      PlayerPeripheral      Player's installed peripherals
      Consumable            Consumable item definition
      PlayerConsumable      Player's consumable inventory
      Node                  Map node — ice, type, is_spawn, is_safe_zone, npc_handle
      NodeConnection        Adjacency edges between nodes
      NodeTrace             Trace markers left on nodes by players
      CombatChallenge       Active PvP challenge record (30s TTL)
      PacketHijackMatch     Active Packet Hijack session — phase, port pool, chain state
      StreetDoc             Street Doc NPC locations (legacy name — maps to CyberDoc NPCs)
      HardwareEncrypt       Encryption hardware item (status: verify active usage)
      DocChatMessage        FREQUENCY hub chat message — hub_canvas_id, player_id, handle, body,
                            expires_at (45min TTL)
    Services/
      RigService            All stat calculation — effectiveStats(), maxSs(), loadoutSlots(),
                            applyDamage(), enforceRamCap(), enforceCpuCommandCap()
      CyberDocService       Banking pocket_creds, SS repair, peripheral install, loadout management
      BountyService         Bounty threshold evaluation, leaderboard queries
      NodeService           Node depletion logic, reward calculation
      InventoryService      Consumable use effects
      PacketHijackService   Full Packet Hijack game logic — Phase 1 recon, Phase 2 exploit chain,
                            rig commands, port pool generation, chain generation, command parsing
      DocChatService        FREQUENCY hub chat — playerIsAtHub() presence gate, recentMessages(),
                            postMessage() (profanity check + 45min TTL + broadcast), pruneExpired()
      ProfanityFilterService Blocklist filter for DocChatService — leetspeak normalization +
                            run-length-aware stretch matching (e.g. "fuuuuuck"), backed by
                            config/profanity.php
    Events/
      DocChatMessageSent    ShouldBroadcastNow → PrivateChannel('doc-chat.{hubCanvasId}')
    DTOs/
      BountyEvent           Value object carrying bounty state for broadcast events
  database/
    migrations/             One file per schema change — never edit existing migrations
    seeders/
      ChassisTemplateSeeder Chassis catalog (run after migrations)
      PlayerSeeder          Test player + rig
  config/
    profanity.php           Blocklist config for ProfanityFilterService — categorized word/phrase
                            list, see file comments for expansion sources
  resources/js/
    Pages/
      Game.vue              Root game component — wires all composables together (integration layer only)
      Auth/Login.vue        Cyberpunk login page
      Auth/Register.vue     New player registration page
    components/
      layout/               GameScreen, HUD, NavBar, SidePanel, GameMenu
      map/                  HexMapCanvas (SVG hex grid + ping layer), NodeWindow
      panel/                NodeInfoBlock, LoadoutBlock, BountyBlock, PanelBlock
      browser/              InGameBrowser, SpliceRouter.js, pages/
      minigame/             GridBreach, PacketHijack
      shared/               BootSequence, NeonBorder, OpenSeasonNotification, SSBar, TerminalText,
                            DocChatPanel (presentational FREQUENCY log + input), DocChatWindow
                            (floating shell, teleport-to-body), FieldCommsWindow (one-way DOC
                            voice-call ticker, ringing→live phase machine)
    composables/
      useAuth.js            Fetches /api/player/me — session cookie handles auth
      useGameState.js       Reactive player/rig/commands/inventory refs (hydrated from API on login)
                            Note: was useMockGameState — renamed
      useMapData.js         Fetches 228 nodes, exposes getByCanvasId(), getSpawnNode()
      useMapInteraction.js  Node selection, player movement, currentNodeId
      usePosition.js        Debounced POST /api/player/position on every move
      useHeartbeat.js       POST /api/player/heartbeat on a keepalive interval
      useNodePresence.js    Polls /api/nodes/{id}/players every 3s (post-auth only)
      useNodeTraces.js      Fetches + stores node trace markers for the current node
      useNodeIdentity.js    Pure utility — getNodeIdentity(), getNetworkName(), getSpliceAddress()
      useCombat.js          PvP challenge handshake + result submission
      useBountyBoard.js     Polls /api/leaderboard/bounty every 30s
      useDepletion.js       POST /api/nodes/{id}/deplete after each hack
      usePacketHijack.js    Packet Hijack WebSocket state + command dispatch
      useUpgradeCosts.js    Client-side upgrade cost projection for store UI display only
                            (server recomputes all actual costs via RigService)
      useWebSocket.js       Thin wrapper around window.Echo (Laravel Echo → Reverb) — connected reflects
                            live socket state; joinChannel() for public channels. Private/presence
                            channels (combat, packet-hijack, node presence, bounty board) are managed
                            directly by their own composables via window.Echo, not through this wrapper.
      useBrowserState.js    Controls which SPLICE panel URL is active at the Game.vue level
      useBrowser.js         Internal navigation history inside InGameBrowser.vue
      useAudio.js           Sound effect playback helpers; duckForCall()/unduckAfterCall() partially
                            fade music under a field comms call instead of full silence
      useTutorial.js        Tutorial quest state + POST /api/tutorial/reward
      useDocChat.js          FREQUENCY hub chat — joins/leaves the private doc-chat.{hub} Echo
                            channel per active hub, fetches history, optimistic send
      useFieldComms.js       DOC field comms call queue (one-way voice-call check-ins during a
                            mission stage); FieldCommsWindow.vue owns reveal timing + audio,
                            same split as useWatcher.js / WatcherSignal.vue
```

---

## Separation of Concerns — Rules

**Controllers** validate input and return JSON. No business logic. If a method is longer than ~30 lines something belongs in a Service.

**Services** own all game logic (RigService, CyberDocService, etc.). No HTTP, no request objects.

**Models** are data + relationships only. No game logic in models.

**Composables** each own one concern. If a composable needs to call another composable's internals, extract shared state into a third composable. Game.vue wires them together — it does not duplicate their logic.

**Game.vue** is the integration layer. It imports composables, threads props down to components, and handles inter-composable events. Keep it as a wiring harness — no raw axios calls, no game math inline.

**Components** receive props and emit events. They never call the API directly. All data flows down, all actions flow up via emits.

**File size target** — aim for under 300 lines per Vue component. If a component is growing, extract a child component or move logic to a composable.

---

## Stat System

### The Five Stats

| Stat | PvE | PvP | GridBreach | Other |
|---|---|---|---|---|
| **CPU** | Node ICE gate (can't attempt nodes with ICE >4 above CPU). Cache pool (CPU+RAM). | Damage multiplier: `20 + CPU×5 − FW×5` | Sequence length | Command level cap — max equippable command level = effective CPU |
| **RAM** | Cache pool (CPU+RAM) | — | Game length: base 30s + (RAM × 5s) | Chassis-only — cannot be upgraded via invested points. |
| **Firewall** | SS damage reduction: `max(1, nodeICE − Firewall)` | Damage absorption: `loserFW × 5` subtracted from formula | Shields hexakeys from opponent commands | Death spiral stat — degrades under SS loss, causing more damage per hit |
| **Storage** | — | — | — | Pocket creds carry cap. Command library: `Storage × 2` = total commands owned. Item inventory slots. **Never degrades.** |
| **OS** | Ping evasion — wider rings = harder to locate | Harder to locate on map | Input window: base 3s + (OS × 0.3s) per move | Degrades heavily under SS loss |

### Stat Formula

```
effective_stat = base_stat + invested_level + peripheral_boost
```

- `base_stat` — from `chassis_templates` (e.g. `base_cpu`)
- `invested_level` — from `player_rigs` (e.g. `cpu_level`), starts at 0
- `peripheral_boost` — sum of installed peripherals that boost this stat
- Cannot exceed `cap_*` from `chassis_templates`

All stat math lives in `RigService::effectiveStats()`. Never recompute stats on the client — always trust the API snapshot.

### Loadout Slots

Loadout slots are **chassis-based + hardware-expandable**, not stat-driven. RAM no longer controls slot count.

Each chassis has three typed base slot counts (`base_map_slots`, `base_hack_slots`, `base_open_slots`):

| Chassis | Map | Hack | Open | Hardware slots | Max loadout |
|---|---|---|---|---|---|
| BlackHat v1.0 | 1 | 1 | 1 | 0 | 3 |
| GX-7 Ghost | 2 | 1 | 0 | 2 | 5 |
| BR-9 Breaker | 1 | 2 | 0 | 2 | 5 |
| VT-3 Vault | 1 | 1 | 0 | 3 | 5 |

Players expand hardware slots by installing **command module peripherals** (same shared port pool as stat-boost peripherals):
- **Nav Wraith Mk.I/II/III** — adds 1 map slot. Mk tier = max command level for that slot (T1=L1, T2=L2, T3=L3).
- **ICE Pick Mk.I/II/III** — adds 1 hack slot. Same tier gating.

Slot rules:
- Map slots accept map-context commands only.
- Hack slots accept hack-context commands only.
- Open slots accept either context (overflow).
- Validation: map commands ≤ map + open slots; hack commands ≤ hack + open slots; total ≤ total slots.

`RigService::loadoutSlots()` is the server-authoritative slot calculator. Always call it — never derive slot counts client-side.

### System Stability (SS)

```
max_ss = 100 (flat for all rigs — chassis and OS do not affect this value)
```

SS is the rig's health pool. PvP and PvE damage reduce it. Hitting 0 triggers Critical System Failure: pocket creds wiped, bounty reset, teleported to last CyberDoc, SS stays at 0 until repaired.

### SS Degradation

Every 20% SS lost strips 1 point from CPU, RAM, Firewall, and OS (cumulative). Storage is never affected.

| SS Remaining | Cumulative Penalty |
|---|---|
| 80 | CPU −1, RAM −1, Firewall −1, OS −1 |
| 60 | CPU −2, RAM −2, Firewall −2, OS −2 |
| 40 | CPU −3, RAM −3, Firewall −3, OS −3 |
| 20 | CPU −4, RAM −4, Firewall −4, OS −4 |
| 0  | **Critical System Failure** |

When RAM degrades below active command count, `RigService::enforceRamCap()` deactivates slots from highest to lowest. When CPU degrades below an equipped command's level, `enforceCpuCommandCap()` deactivates it. Both fire automatically in `applyDamage()`.

### SS Combat Formulas

```
PvE damage  = max(1, nodeICE − playerFirewall)
PvP damage  = max(15, 20 + (winnerCPU × 5) − (loserFirewall × 5))
Repair cost = floor((missingSS / maxSS) × 600)
             → 150 ₡ per 25% SS lost (e.g. 25% = 150₡, 50% = 300₡, 100% = 600₡)
```

### Uplink

Uplink sets the player's movement range per run. It **cannot be boosted by invested points** — the only two ways to increase it are:
1. Upgrade to a higher-tier chassis.
2. Install **Deep Link** hardware peripherals (Mk.I +1 / Mk.II +2 / Mk.III +3). These are stat-boost peripherals that draw from the same port pool as other hardware and add directly to effective uplink.

---

## Chassis Catalog

### Tier 1

| Chassis | CPU | RAM | Firewall | Storage | OS | Uplink | Cache | Point Cap |
|---|---|---|---|---|---|---|---|---|
| BlackHat v1.0 | 3 | 2 | 1 | 2 | 2 | 3 | 5 | 9 |

Caps: CPU 5 / RAM 4 / FW 3 / STG 4 / OS 4

### Tier 2 — NullTek Series (unlocked at BlackHat v1.9)

| Chassis | CPU | RAM | Firewall | Storage | OS | Uplink | Cache | Point Cap | Peripheral Slots |
|---|---|---|---|---|---|---|---|---|---|
| GX-7 Ghost | 3 | 3 | 2 | 3 | 5 | 7 | 6 | 18 | 2 |
| BR-9 Breaker | 5 | 5 | 1 | 3 | 2 | 5 | 10 | 18 | 2 |
| VT-3 Vault | 3 | 3 | 5 | 5 | 2 | 5 | 6 | 18 | 3 |

Caps:
- GX-7 Ghost: CPU 6 / RAM 6 / FW 5 / STG 6 / OS 9
- BR-9 Breaker: CPU 9 / RAM 8 / FW 4 / STG 6 / OS 5
- VT-3 Vault: CPU 6 / RAM 6 / FW 9 / STG 10 / OS 5

**Ghost** — evasion/mobile. High OS (hard to locate via pings), highest uplink in the game at v2. Built for long runs and stealth.

**Breaker** — aggressive hacking. Massive cache (10 base), high CPU for cracking tough nodes. Low firewall is the trade-off.

**Vault** — PvP tank. High firewall shrugs off most opponent commands. Large storage = biggest loadout. Built for combat.

---

## Bounty System

### Thresholds

| Hacks | Star Level | Multiplier | Effect |
|---|---|---|---|
| 10 | ★1 | ×1.25 | ICE starts watching |
| 15 | ★2 | ×1.50 | Pings fire every 2 moves |
| 20 | ★3 | ×1.75 | Priority ICE target |
| 25 | ★4 | ×2.00 | Open Season — all players notified |
| 30 | ★5 | ×2.25 | Max heat, pings every move |

Bounty resets to 0 at CyberDoc visit (banked as part of the extract).

### ICE Ping Formula

```
effective_ice  = node.ice × (1 + bounty_level)
raw_range      = PING_BASE_RANGE(8) + player.os − effective_ice
ping_range     = clamp(raw_range, 0, MAX_RANGE_PER_STAR[bounty_level])
```

`MAX_RANGE_PER_STAR = [8, 5, 4, 3, 2, 1]` — index is bounty level 0–5.

At ★5, the cap is 1 node regardless of OS. High-ICE nodes compound heavily — a level 6 ICE node against a ★3 bounty = 6×4 = 24 effective ICE, which pins location almost exactly. Commands (Ghost Protocol, Signal Noise) are the only real escape at high star levels.

### Ping SVG radius

```
radiusPx = range === 0 ? 18 : 18 + range × 38
```

Rings render in the SVG ping layer of HexMapCanvas. Bounty pings are amber dashed, Open Season pings are red solid, false pings (Signal Noise) are teal dashed.

---

## Economy

| Pool | Description |
|---|---|
| `wallet_creds` | Safe creds — banked at CyberDoc. Spent at CyberDoc store. |
| `pocket_creds` | At-risk creds from hacking. Lost if killed in PvP. Converted to wallet at CyberDoc. |

Hack rewards always go to `pocket_creds`, never directly to `wallet_creds`.

---

## PvP Flow

1. Two players are on the same node simultaneously
2. NodeInfoBlock shows the other player with a pink `[HACK]` button
3. Challenger clicks `[HACK]` → `POST /api/combat/challenge` (30s TTL)
4. Target's pending poll (`GET /api/combat/pending`, 2s interval) picks it up
5. Target sees incoming challenge overlay → Accept or Decline
6. Both clients launch GridBreach in PvP mode
7. Winner calls `POST /api/combat/result`
8. Loser: pocket_creds zeroed, bounty reset. Winner: receives stolen pocket_creds.

Node presence is detected by `GET /api/nodes/{canvasId}/players` (polled every 3s, post-auth only).

---

## Auth

Laravel session auth via Sanctum.

- `GET /login` → `Auth/Login.vue` (Inertia, guest middleware)
- `POST /login` → `LoginController::store()` → session set → redirect to `/`
- `GET /` → `Game.vue` (auth middleware — redirects to `/login` if unauthenticated)
- `POST /api/auth/token` → Bearer token for the Kotlin engine only (not used by the SPA)

Sanctum's `EnsureFrontendRequestsAreStateful` middleware is prepended to the API group so session cookies authenticate all `/api/*` requests from the SPA — no Bearer token is needed on the frontend.

`useAuth.js` in the client simply calls `GET /api/player/me`. If the session is valid, it returns the player + rig snapshot used to hydrate `useGameState`.

---

## Key Rules for New Code

1. **Never put game math in a controller or component.** Controllers validate + delegate. Components render + emit.
2. **All stat values come from `RigService::effectiveStats()`**. Never manually add `base_*` + `*_level` on the client.
3. **Uplink cannot be raised by invested points.** It can only grow via chassis upgrade or Deep Link hardware peripherals.
4. **Cache = CPU + RAM effective values.** Recompute server-side after every stat change.
5. **Pocket vs wallet distinction is load-bearing.** Hacks → pocket. CyberDoc bank → wallet. Store purchases → wallet only.
6. **Node presence polling must not run pre-auth.** `useNodePresence` guards `fetchPresence()` with an auth token check.
7. **Reverb is live.** `useWebSocket` wraps `window.Echo` (Laravel Echo → Reverb, gated by `VITE_ENGINE_ENABLED`) and its `connected` ref reflects the real socket state. Private/presence channels (combat, packet-hijack, node presence, bounty board) are managed directly by their own composables via `window.Echo` — don't route new realtime features through `useWebSocket` unless they're public channels.
8. **The SPLICE browser routes are all in `SpliceRouter.js`**. Add new pages there — nothing else needs changing.
9. **Pings are client-side only for now.** The WebSocket engine will broadcast real pings when it's live. Client-side pings are approximations.
10. **One migration per change.** Never alter existing migration files — always add a new one.
11. **Surgical edits only — never touch what wasn't asked.** When making a change, edit only the exact lines required to fulfil the request. Do not reformat surrounding code, remove unrelated sections, reorder template blocks, or "clean up" anything that wasn't explicitly broken. If a file must be read first, read the smallest slice needed. Before saving any edit, verify that every line outside the requested change is byte-for-byte identical to what was there before.
12. **Stat changes require explicit confirmation before implementation.** Any modification to stat roles, formulas, degradation tiers, GridBreach effects, or new mechanic assignments must be discussed and confirmed by the user before any code is written. Present the full impact (numbers, affected files, gameplay consequences) and wait for a go-ahead.

---

## Phase 2 Redesign — Exploit Chain Implementation Checklist

> Replaces the bias/decode/validate system. Full design spec in `PACKET_HIJACK_BUILD_PLAN.md`.

- [x] **1. Migration** — add `challenger_exploit_chain`, `defender_exploit_chain` (JSON), `challenger_trace_attempts`, `defender_trace_attempts` (TINYINT) to `packet_hijack_matches`
- [x] **2. `PacketHijackService` — remove old Phase 2 methods** — deleted `EXPLOIT_THRESHOLD`, `OVERCLOCK_THRESHOLD`, bias/cascade constants; old probe/decode/validate/biasToExposure methods remain in file but are no longer routed
- [x] **3. `PacketHijackService` — expand port catalogue** — SMTP(25), DNS(53), RDP(3389), Postgres(5432), Redis(6379), MongoDB(27017) added; `PORT_FLARE`, `CHAIN_ANOMALIES`, `REDHERRING_ANOMALIES` pools added for all ports
- [x] **4. `PacketHijackService` — `generateExploitChain()`** — builds 2–3 port chain ending at 8080; chain length driven by target FW stat
- [x] **5. `PacketHijackService` — `generatePortPool()`** — selects 7–9 ports, seeds chain/red-herring/dead-end categories, assigns anomalies and flare lines per port
- [x] **6. `PacketHijackService` — anomaly generation** — chain ports get relational anomalies from `CHAIN_ANOMALIES`; red herrings get OS-tier-scaled anomalies; dead ends get null anomaly
- [x] **7. `PacketHijackService` — `commandProbePort()`** — returns flare banner + anomaly line; banner length scales with target OS stat
- [x] **8. `PacketHijackService` — `commandTrace()`** — validates two-port hypothesis; consumes one attempt; confirms adjacency or returns no-correlation
- [x] **9. `PacketHijackService` — `commandExploitPort()`** — chain-order enforcement; credential fragment reveal on success; informative failure for wrong order / non-chain
- [x] **10. `PacketHijackService` — `commandBreachChain()`** — IP-only validation; all chain ports must be shattered; opens auth prompt
- [x] **11. Migration / service — trace attempt + chain initialisation** — seeded at inject→phase2 transition in controller from attacker CPU stat; `challenger_chain_progress`, `challenger_credential_state` columns added
- [x] **12. `PacketHijackController`** — `trace` handler added; `probe`/`exploit`/`breach` updated; `decode`/`validate` handlers removed; `handleInject` seeds chain+pool+attempts at phase transition
- [x] **13. `usePacketHijack.js`** — removed decode/validate/fingerprint state; added `portPool`, `chainConfirmed`, `traceAttemptsLeft`, `credentialState`, `boardScanned`; WS handlers updated for new event keys
- [x] **14. `PacketHijack.vue` — port board** — new port cards with probed/chain-confirmed/shattered indicators; old exposure labels removed
- [x] **15. `PacketHijack.vue` — credential strip** — fills progressively as chain ports exploited; trace attempt counter with colour states (normal/low/depleted)
- [x] **16. `PacketHijack.vue` — remove decode/validate UI** — CMD REF updated to new command set; old port matrix helpers removed; `Game.vue` props updated to new state shape
