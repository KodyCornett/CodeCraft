# CodeCraft — Developer Reference

## What This Game Is

CodeCraft is a real-time multiplayer cyberpunk hacking game. Players move across a hex node map, hack nodes to earn creds and tech points, manage a bounty system that makes them increasingly visible to ICE and other players, and engage in PvP Grid-Breach combat. The game runs in a browser (Inertia + Vue 3) backed by a Laravel 11 API.

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.5, SQLite (dev) |
| Auth | Laravel Sanctum — session-based for the SPA, Bearer tokens for the Kotlin engine |
| Frontend | Vue 3 (Composition API), Inertia.js, Vite |
| Realtime | WebSocket engine (Kotlin, not yet running — `VITE_ENGINE_ENABLED=false` in .env) |
| Font | JetBrains Mono (monospace throughout) |
| Map | SVG hex grid, 228 canvas nodes |

---

## Project Layout

```
api/
  app/
    Http/Controllers/       One controller per resource. Keep controllers thin.
      Auth/LoginController  Web session login/logout
      AuthController        POST /api/auth/token (Kotlin engine only)
      PlayerController      /api/player/me, /api/player/position
      NodeController        /api/nodes, /api/nodes/{id}/players, /api/nodes/{id}/deplete
      RigController         /api/rig CRUD
      BountyController      /api/leaderboard/*, /api/player/{id}/extract
      StreetDocController   /api/street-doc/*
      CombatController      /api/combat/result
      CombatChallengeController  PvP challenge handshake
    Models/
      User                  Laravel auth user (email + password)
      Player                Game identity — handle, economy, bounty state
      PlayerRig             Rig stats (invested levels, current_ss)
      ChassisTemplate       Chassis catalog — base stats + caps per tier
      ...
    Services/
      RigService            All stat calculation lives here — effectiveStats(), maxSs(), etc.
      StreetDocService      Banking pocket_creds, cache flush, bounty reset
  database/
    migrations/             One file per schema change — never edit existing migrations
    seeders/
      ChassisTemplateSeeder Chassis catalog (run this after migrations)
      PlayerSeeder          Test player + rig
  resources/js/
    Pages/
      Game.vue              Root game component — wires all composables together
      Auth/Login.vue        Cyberpunk login page
    components/
      layout/               GameScreen, HUD, NavBar, SidePanel
      map/                  HexMapCanvas (SVG hex grid + ping layer)
      panel/                NodeInfoBlock, LoadoutBlock, BountyBlock, PanelBlock
      browser/              InGameBrowser, SpliceRouter, pages/
      minigame/             GridBreach
      shared/               BootSequence
      streetdoc/            StreetDocMenu
    composables/
      useAuth.js            Fetches /api/player/me — session cookie handles auth
      useMockGameState.js   Reactive player/rig/commands refs (hydrated from API on login)
      useMapData.js         Fetches 228 nodes, exposes getByCanvasId(), getSpawnNode()
      useMapInteraction.js  Node selection, player movement, currentNodeId
      usePosition.js        Debounced POST /api/player/position on every move
      useNodePresence.js    Polls /api/nodes/{id}/players every 3s (post-auth only)
      useCombat.js          PvP challenge handshake + result submission
      useBountyBoard.js     Polls /api/leaderboard/bounty every 30s
      useDepletion.js       POST /api/nodes/{id}/deplete after each hack
      useStreetDoc.js       POST /api/street-doc/visit
      useWebSocket.js       WS stub (disabled — set VITE_ENGINE_ENABLED=true when ready)
      useBrowserState.js    Controls which SPLICE browser URL is active
```

---

## Separation of Concerns — Rules

**Controllers** validate input and return JSON. No business logic. If a method is longer than ~30 lines something belongs in a Service.

**Services** own all game logic (RigService, StreetDocService, etc.). No HTTP, no request objects.

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
| **RAM** | Cache pool (CPU+RAM) | — | Game length: base 30s + (RAM × 5s) | Loadout slots — active commands = effective RAM |
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

### System Stability (SS)

```
max_ss = 100 (flat for all rigs — chassis and OS do not affect this value)
```

SS is the rig's health pool. PvP and PvE damage reduce it. Hitting 0 triggers Critical System Failure: pocket creds wiped, bounty reset, teleported to last Street Doc, SS stays at 0 until repaired.

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

Uplink is **chassis-locked** — it cannot be boosted by invested points or peripherals. The only way to increase uplink is to upgrade to a higher-tier chassis. It sets the player's movement range per run.

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

Bounty resets to 0 at Street Doc visit (banked as part of the extract).

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
| `wallet_creds` | Safe creds — banked at Street Doc. Spent at CyberDoc store. |
| `pocket_creds` | At-risk creds from hacking. Lost if killed in PvP. Converted to wallet at Street Doc. |

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

`useAuth.js` in the client simply calls `GET /api/player/me`. If the session is valid, it returns the player + rig snapshot used to hydrate `useMockGameState`.

---

## Key Rules for New Code

1. **Never put game math in a controller or component.** Controllers validate + delegate. Components render + emit.
2. **All stat values come from `RigService::effectiveStats()`**. Never manually add `base_*` + `*_level` on the client.
3. **Uplink is chassis-locked.** Never make it upgradeable via invested points.
4. **Cache = CPU + RAM effective values.** Recompute server-side after every stat change.
5. **Pocket vs wallet distinction is load-bearing.** Hacks → pocket. Street Doc → wallet. Store purchases → wallet only.
6. **Node presence polling must not run pre-auth.** `useNodePresence` guards `fetchPresence()` with an auth token check.
7. **WebSocket is disabled** (`VITE_ENGINE_ENABLED=false`). `useWebSocket` returns a stub. Set to `true` when the Kotlin engine is running.
8. **The SPLICE browser routes are all in `SpliceRouter.js`**. Add new pages there — nothing else needs changing.
9. **Pings are client-side only for now.** The WebSocket engine will broadcast real pings when it's live. Client-side pings are approximations.
10. **One migration per change.** Never alter existing migration files — always add a new one.
11. **Surgical edits only — never touch what wasn't asked.** When making a change, edit only the exact lines required to fulfil the request. Do not reformat surrounding code, remove unrelated sections, reorder template blocks, or "clean up" anything that wasn't explicitly broken. If a file must be read first, read the smallest slice needed. Before saving any edit, verify that every line outside the requested change is byte-for-byte identical to what was there before.
12. **Stat changes require explicit confirmation before implementation.** Any modification to stat roles, formulas, degradation tiers, GridBreach effects, or new mechanic assignments must be discussed and confirmed by the user before any code is written. Present the full impact (numbers, affected files, gameplay consequences) and wait for a go-ahead.
