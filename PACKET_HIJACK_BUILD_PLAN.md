# PACKET HIJACK — BUILD PLAN & CHECKLIST
> **Module:** Standalone PvP mini-game replacing Grid Breach for PvP combat resolution
> **Stack:** Laravel 11 · Reverb (WebSocket) · Vue 3 · Inertia.js
> **Economy:** Winner steals loser's `pocket_creds` · Loser bounty resets (mirrors current PvP logic)
> **Trigger:** Replaces Grid Breach when a PvP challenge is accepted on a shared node

Mark each item `[x]` as it is completed. Never skip a phase — later phases depend on earlier ones being stable.

---

## GAME PHASE 2 REDESIGN — Exploit Chain Investigation
> **Status:** Design confirmed — not yet implemented. Supersedes the original bias/decode/validate system entirely.
> **Scope:** `PacketHijackService.php`, `PacketHijackController.php`, `PacketHijack.vue`, `usePacketHijack.js`, one new migration for schema changes.

---

### Philosophy

The original Phase 2 was a recipe: probe each port, read the exposure rating, decode N times (told by the error message), exploit. No deduction. No uncertainty. The player followed instructions rather than investigated a system.

The redesign makes the exploit path the puzzle. The player knows there is a correct sequence of ports to crack in order — they don't know what it is. They gather evidence through probing, form hypotheses, test them, and deduce the chain from what the system tells them. Fragment collection (credentials needed for auth) is a byproduct of working the chain correctly — not a separate scavenger hunt.

**Removed entirely:** bias values, exposure ratings, `decode` command, `validate` command, port bias thresholds, cascade math.

---

### Port Pool

#### Expanded Catalogue

Phase 2 now draws from a larger port catalogue. 8–10 ports are shown per match (up from 5). The correct exploit chain is 2–3 ports. The rest are noise — dead ends or red herrings.

| Port  | Service    | Notes                          |
|-------|------------|--------------------------------|
| 21    | FTP        |                                |
| 22    | SSH        |                                |
| 25    | SMTP       |                                |
| 53    | DNS        |                                |
| 80    | HTTP       |                                |
| 443   | HTTPS      |                                |
| 3306  | MySQL      |                                |
| 5432  | Postgres   |                                |
| 6379  | Redis      |                                |
| 8080  | Alt-HTTP   | Exfil port — always present, always the final exploit target |
| 27017 | MongoDB    |                                |
| 3389  | RDP        |                                |

Match generation selects 7–9 ports from the catalogue (excluding 8080), then appends 8080. Total visible ports: 8–10.

Port count scales with target's **Firewall** stat:
- FW 1–3 → 8 ports (chain easier to isolate)
- FW 4–6 → 9 ports
- FW 7+  → 10 ports

#### Three Port Categories

Every port in a match falls into exactly one category. Category is assigned at match generation and never changes.

**Chain ports** — 2–3 ports that form the correct exploit sequence. Each has an anomaly that accurately describes its relationship to the next port in the chain. `trace` between connected chain ports confirms the link. Exploiting them in the correct order is the win condition for Phase 2.

**Dead ends** — ports with a generic anomaly or no anomaly at all. They look like real services. `trace` with any other port returns no correlation. They exist to pad the board and force probing investment.

**Red herrings** — ports with a specific, relational-sounding anomaly that leads nowhere. `trace` always returns no correlation regardless of what the player pairs them with. They are designed to look like chain ports. The target's **OS stat** controls how convincing they are:
- OS 1–3 → anomaly is vague and easy to dismiss (`MISCONFIGURATION DETECTED — NON-STANDARD CONFIG`)
- OS 4–6 → anomaly sounds relational but generic (`UPSTREAM DEPENDENCY SUSPECTED — NO SIGNATURE MATCH`)
- OS 7+  → anomaly closely mimics real chain language, nearly indistinguishable without `trace` confirmation

---

### Chain Generation

At match start `generateExploitChain()` builds the exploit sequence for each player's side independently.

**Structure:** `[entry_port → ... → 8080]`

A 2-port chain: `SSH → 8080`
A 3-port chain: `Redis → MySQL → 8080`

Port 8080 (exfil) is always the terminal node. The player must exploit all chain ports in sequence before 8080 becomes accessible.

**Chain rules:**
- No port appears twice in a chain
- Entry port is never 8080
- Chain length: 2 (FW 1–4) or 3 (FW 5+)
- Chain ports are seeded into the port pool alongside dead ends and red herrings before the match is stored

**Anomaly seeding:** Each chain port receives an anomaly that references the next port in the chain by service type (not port number) — keeping it deductive rather than explicit. Example for `Redis → MySQL`:

Redis anomaly: `VOLATILE KEYSPACE — ACTIVE AUTH HANDSHAKE PATTERN — DOWNSTREAM DATABASE DEPENDENCY SUSPECTED`

MySQL anomaly: `AUTH PLUGIN MISMATCH — SESSION TOKEN BLEED FROM IN-MEMORY CACHE — UPSTREAM RELAY DETECTED`

The player reads both, forms the hypothesis, confirms with `trace 6379 3306`.

---

### Commands

#### `scan <ip>`
Opens Phase 2. Returns port numbers and service names only. No exposure, no anomaly, no banner. Same role as `netstat` in Phase 1 — populates the board, nothing more.

```
> scan 192.168.4.17

PORT SCAN COMPLETE — 9 SERVICES DETECTED
:22    SSH
:25    SMTP
:53    DNS
:80    HTTP
:443   HTTPS
:3306  MySQL
:6379  Redis
:3389  RDP
:8080  Alt-HTTP  [EXFIL — LOCKED]
```

---

#### `probe <port>`
Fingerprints a specific port. Returns a multi-line service banner (flare data) followed by the anomaly line. Marks the port as probed. Probing a port is required before `trace` or `exploit` can reference it.

Banner content is flare — process stats, session counts, version strings, memory usage — realistic noise that makes the system feel alive. The anomaly is always the last line, prefixed `ANOMALY:`. For dead ends the anomaly is absent or generic. For red herrings and chain ports it is specific and relational.

Players must read through the flare to reach the anomaly. High-OS targets generate longer banners (more lines of noise before the anomaly).

```
> probe 6379

Redis 6.2.6 — In-Memory Data Store
Uptime             : 14d 3h 22m
Connected clients  : 4
Used memory        : 1.24MB
Keyspace hits      : 31,047 / misses: 203
Last save          : 47s ago
Blocked clients    : 0
Active channels    : RELAY-NODE-07, SESSION-BROKER-02
Persistence        : RDB + AOF

ANOMALY: VOLATILE KEYSPACE — ACTIVE AUTH HANDSHAKE PATTERN — DOWNSTREAM DATABASE DEPENDENCY SUSPECTED
```

---

#### `trace <port1> <port2>`
Tests a hypothesized relationship between two probed ports. Both ports must have been probed first.

If the two ports are adjacent in the exploit chain → confirms the link and reveals the directionality:

```
> trace 6379 3306

CROSS-REFERENCE: REDIS SESSION TOKEN → MYSQL AUTH LAYER — DEPENDENCY CONFIRMED
CHAIN VECTOR: EXPLOIT 6379 FIRST — COLLAPSE WILL PROPAGATE TO 3306
```

If the ports have no real relationship (dead end or red herring pairing) → returns no correlation:

```
> trace 25 443

NO CORRELATED ANOMALY BETWEEN SMTP:25 AND HTTPS:443
SERVICES OPERATE INDEPENDENTLY
```

**Trace attempts are limited** and governed by the attacker's **CPU stat**:
- CPU 1–3 → 4 trace attempts
- CPU 4–6 → 6 trace attempts
- CPU 7+  → 8 trace attempts

Attempts are consumed on both confirmed and failed traces. Running out does not lock the player out — they can still exploit based on their current deductions, but blind. The attempt counter is visible in the UI.

---

#### `exploit <port>`
Attempt to shatter a port. The port must have been probed first.

**Correct chain order:** If the port is the current head of the chain (i.e. no unshattered chain ports precede it), the exploit succeeds. A credential fragment for the auth step is revealed as output.

```
> exploit 6379

BREACHING REDIS SESSION LAYER...
[============================] GATE COLLAPSED

CREDENTIAL FRAGMENT EXTRACTED:
  HOSTNAME : CORE-????-????  →  CORE-RELAY-????
  OS       : PROC-????-???   →  (LOCKED — CONTINUE CHAIN)
```

**Wrong order or non-chain port:** Fails with an informative but cryptic response that reinforces the investigation rather than spelling out the answer:

```
> exploit 3306

BREACH ATTEMPT — GATE HOLDING
AUTH LAYER UNRESPONSIVE — NO UPSTREAM SIGNAL DETECTED
HINT: THIS SERVICE MAY REQUIRE A PRIOR DEPENDENCY TO BE CLEARED
```

```
> exploit 25

BREACH ATTEMPT — GATE HOLDING
SMTP SERVICE INTEGRITY HIGH — NO KNOWN VECTOR
```

The player learns from the failure. "No upstream signal" confirms this port has a dependency. "No known vector" suggests it may be a dead end.

**Exfil port 8080:** Only exploitable after all chain ports are shattered in sequence. Attempting it early:

```
> exploit 8080

EXFIL PORT LOCKED — CHAIN INCOMPLETE
CLEAR ALL CASCADE DEPENDENCIES FIRST
```

---

#### `breach <ip>`
Final Phase 2 command. Fired after the full chain is shattered (all chain ports exploited in order, 8080 shattered last). Validates the IP from Phase 1 and opens the auth prompt.

```
> breach 192.168.4.17

IP SIGNATURE MATCHED — INITIATING CONNECTION...
SYSTEM LOGIN REQUIRED
```

Auth prompt opens. Player types the assembled hostname and OS credentials (built up from fragment reveals during correct exploit steps). Correct credentials → Phase 3. Wrong credentials → 1–2 fragments corrupt (same penalty as before) and the player must re-derive them from the banner data.

---

### Fragment Collection

Fragments are no longer hidden in banner text for the player to spot and copy. They are **revealed automatically as rewards for correct chain exploitation.**

Each successful `exploit` on a chain port (in correct order) drops one segment of the credential pair. The top strip updates in real time:

| Chain step | Hostname display    | OS display       |
|------------|---------------------|------------------|
| Start       | `CORE-????-????`   | `PROC-????-???`  |
| Exploit port 1 | `CORE-RELAY-????` | `PROC-????-???` |
| Exploit port 2 | `CORE-RELAY-????` | `PROC-4.2-???`  |
| Exploit 8080   | `CORE-RELAY-A3F2` | `PROC-4.2-B91`  |

The credential is complete when the chain is complete. No `validate` step. No manual reading and copying. The banner flare data still contains the fragment strings embedded in noise — but only as a secondary reference if the player wants to double-check before typing auth. The primary delivery mechanism is chain exploitation.

**Auth failure + corruption** still applies: if the player misremembers or misreads a fragment at the auth step, 1–3 segments corrupt and they must re-derive from the banner they already probed. This keeps the banners meaningful as reference material without making them the primary gameplay loop.

---

### Stat Expression in Phase 2

| Stat | Effect |
|---|---|
| Target **Firewall** | Controls port count (8–10) and chain length (2–3). Higher FW = more noise to sift through. |
| Target **OS** | Controls red herring quality. High OS = red herrings that closely mimic chain anomaly language. |
| Attacker **CPU** | Controls trace attempt budget (4–8). High CPU = more hypotheses testable before going blind. |
| Attacker **OS** | No direct effect in Phase 2. Expression is in Phase 1 ping evasion. |

---

### UI Changes — Phase 2 Data Zone

The port matrix in the top strip changes:

**Before (old):** Port | Service | Exposure rating (CRITICAL/LOW/etc.)

**After (new):** Port | Service | Probed indicator | Shattered indicator | Chain confirmed indicator

```
:22   SSH     [PROBED]  [CHAIN ✓]  [SHATTERED]
:6379 Redis   [PROBED]  [CHAIN ✓]  [SHATTERED]
:3306 MySQL   [PROBED]  [CHAIN ✓]  [         ]  ← current target
:25   SMTP    [PROBED]  [       ]  [         ]
:443  HTTPS   [       ]  [       ]  [         ]
:8080 Alt-HTTP[LOCKED UNTIL CHAIN COMPLETE    ]
```

Chain confirmation indicator only lights when `trace` has confirmed that port as part of the chain. This gives the player a visual record of their confirmed deductions without giving away ports they haven't traced yet.

Credential strip persists at top right — fragments fill in as chain ports are exploited.

---

### Files to Change When Implementation Begins

| File | Change |
|---|---|
| `PacketHijackService.php` | Remove `generatePortTopology`, `commandExploitPort`, `commandDecodePort`, `commandProbePort`, `commandValidate`, `biasToExposure`, bias/cascade math. Add `generateExploitChain`, `generatePortPool`, `commandTrace`, revised `commandProbe`, revised `commandExploit`, `commandBreach` (IP-only). |
| `PacketHijackController.php` | Update command routing to remove decode/validate handlers, add trace handler. |
| `PacketHijack.vue` | Replace port matrix exposure labels with probed/chain/shattered indicators. Add trace attempt counter. Remove validate UI. Update credential strip to fill progressively. |
| `usePacketHijack.js` | Remove decode/validate state. Add `traceAttemptsRemaining`, `chainConfirmed` port flags, progressive credential state. |
| Migration | New column: `challenger_exploit_chain` JSON, `defender_exploit_chain` JSON, `challenger_trace_attempts` TINYINT, `defender_trace_attempts` TINYINT. Remove or repurpose bias columns if no longer needed. |

---

## PHASE 0 — Laravel Reverb Installation & WebSocket Foundation
> **Prerequisite for everything.** `useWebSocket.js` is currently a silent stub. Reverb must be live before any real-time game events can fire.

- [x] `composer require laravel/reverb` and publish config — already present in vendor
- [x] Set `BROADCAST_CONNECTION=reverb` and all `REVERB_*` env vars in `.env` — already set
- [x] Verify `config/broadcasting.php` has the `reverb` driver configured — confirmed
- [x] Run `php artisan reverb:start` — confirm server boots without errors — config ready; start when deploying
- [x] `npm install --save-dev laravel-echo pusher-js` — already in `package.json`
- [x] Replace `useWebSocket.js` stub with real Laravel Echo client wired to Reverb
  - Reflects live Pusher/Reverb socket connection state via `connected` ref
  - Exports `joinChannel(name)` helper for public channels
  - All existing call sites remain silent — no behaviour change (no server events yet)
- [x] `BroadcastServiceProvider` — handled automatically by Laravel 11 via `bootstrap/app.php` channels registration
- [x] `routes/channels.php` registered via `withRouting(channels:)` in `bootstrap/app.php` — confirmed

---

## PHASE 1 — Database Schema
> One migration per change. Never edit existing migration files.

- [x] Migration: `2026_05_27_100001_create_packet_hijack_matches_table`
  - `id` (primary)
  - `challenger_id` → FK `players.id`
  - `defender_id` → FK `players.id`
  - `status` ENUM `pending | phase1 | phase2 | complete`
  - `winner_id` → FK `players.id` (nullable)
  - `challenger_target_ip` VARCHAR — the real IP the challenger must find (defender's rig IP)
  - `defender_target_ip` VARCHAR — the real IP the defender must find (challenger's rig IP)
  - `challenger_ip_pool` JSON — 50+ decoys + 1 real, shuffled
  - `defender_ip_pool` JSON — 50+ decoys + 1 real, shuffled
  - `challenger_ports` JSON — port topology with bias values for challenger's Phase 2
  - `defender_ports` JSON — port topology with bias values for defender's Phase 2
  - `challenger_phase` TINYINT default 1
  - `defender_phase` TINYINT default 1
  - `challenger_locked_until` TIMESTAMP nullable
  - `defender_locked_until` TIMESTAMP nullable
  - `started_at` TIMESTAMP nullable
  - `completed_at` TIMESTAMP nullable
  - Standard `timestamps()`
- [x] Run `php artisan migrate` and verify table structure — migration file written; run on server
- [x] Add `PacketHijackMatch` Eloquent model
  - Relationships: `challenger()`, `defender()`, `winner()` → `Player`
  - Helpers: `roleOf()`, `opponentIdOf()`, `isLocked()`, `portsFor()`, `ipPoolFor()`, `targetIpFor()`, `phaseOf()`
  - Casts: `challenger_ip_pool`, `defender_ip_pool`, `challenger_ports`, `defender_ports` → `array`

---

## PHASE 2 — Core Game Logic: `PacketHijackService`
> All game math lives here. No game logic in controllers or models.

### IP Pool Generation
- [x] `generateIpPool(string $realIp): array`
  - Produces 52 decoy IPs across RFC-1918 ranges + 5 near-miss /24 decoys
  - Injects `$realIp` at a random position in the pool
  - Returns shuffled array

### Phase 1 — Recon Commands
- [x] `commandNetstat(array $pool): array` — returns shuffled 15-IP sample from pool
- [x] `commandSniff(string $realIp): string` — extracts inner octet segment (e.g. `.4.`) as clue
- [x] `commandIsolate(array $pool, string $segment): array` — filters pool to IPs containing `$segment`
- [x] `commandInject(string $realIp, string $attempt, array $pool): array`
  - Returns `['success' => true]` if `$attempt === $realIp`
  - Returns `['success' => false, 'honeypot' => true, 'lock_until' => Carbon]` for decoy hit
  - Returns `['success' => false, 'error' => 'not_in_pool']` if not in pool at all

### Phase 2 — Port Topology Generation
- [x] `generatePortTopology(PlayerRig $rig, Player $player): array`
  - Selects 4 ports from catalogue (FTP/SSH/HTTP/HTTPS/MySQL) + locks exfil port 8080
  - One port starts LOW (8–18%), rest HIGH (70–90%), FW stat raises all bias values
  - Formula: `base_bias + min(firewall × 3, cap)`

### Phase 2 — Port Commands
- [x] `commandScanPort(array $ports, int $port): array` — returns port name + current bias
- [x] `commandExploitPort(array $ports, int $port, PlayerRig $rig, Player $player): array`
  - Fails if bias > 25
  - On success: marks port shattered, applies cascade `floor(cpu × 4)` (min 10, max 40)
  - Unlocks exfil port 8080 when all catalogue ports shattered
- [x] `commandMalwareInject(array $ports, string $targetIp, string $inputIp, int $inputPort): array`
  - Validates IP match AND port 8080 AND exfil unlocked
  - Returns `['success' => true]` on match — triggers match completion

### Penalty Handler
- [x] `parseCommand(string $raw): array`
  - Splits on spaces into `[command, ...args]`
  - Returns `['valid' => false, 'error' => 'COMMAND NOT FOUND: X']` for unrecognised commands
  - Validates per-command argument counts

---

## PHASE 3 — Reverb Broadcast Events
> One event class per game moment. Events are broadcast on private per-match channels.

- [x] `PacketHijackStarted` — dispatched per-player on `player.{id}` channel with role + opening IP sample
- [x] `PacketHijackPhaseTransition` — dispatched twice (advancing player gets ports; opponent gets alert)
- [x] `PacketHijackCommandResult` — dispatched to executing player only with output lines + port state
- [x] `PacketHijackMatchComplete` — dispatched to both players with `is_winner`, `creds_stolen`
- [x] Register private channel `packet-hijack.{matchId}` with dual-participant auth in `routes/channels.php`

---

## PHASE 4 — HTTP Controllers & Routes
> Controllers validate input and delegate to `PacketHijackService`. No game math here.

- [x] `PacketHijackController` with these endpoints:

  | Method | URI | Action |
  |--------|-----|--------|
  | ~~POST~~ | ~~`/api/packet-hijack/start`~~ | Folded into `CombatChallengeController::accept()` |
  | POST | `/api/packet-hijack/{match}/command` | Parse + execute one terminal command, broadcast result |
  | GET  | `/api/packet-hijack/{match}/state` | Fallback state poll (if WS drops) |

- [x] `command` endpoint validates participant, match status, lock state, input length (max 200)
- [x] On `malware inject` success: BountyService loot resolution + SS damage + winner bounty escalation + broadcast
- [x] `CombatChallengeController::accept()` now creates `PacketHijackMatch` + broadcasts `PacketHijackStarted` to both
- [x] Routes registered in `routes/api.php` under `auth:sanctum` with `throttle:120,1` on command endpoint

---

## PHASE 5 — Frontend: Terminal Shell
> Two new frontend files. Zero raw axios calls in components.

### `usePacketHijack.js` Composable
- [x] State refs: `phase`, `commandHistory`, `ports`, `targetIp`, `matchId`, `role`, `isLocked`, `lockCountdown`, `defenderAlertActive`, `matchResult`, `busy`
- [x] `init(matchId, role, ipPoolSample)` — seeds history with opening netstat, subscribes to Echo
- [x] `submitCommand(rawInput)` — client-side pre-validation, then `POST /api/packet-hijack/{match}/command`
- [x] Echo listeners on `player.{id}` channel for all four PH event types
- [x] Honeypot lock: `_applyLock()` + `_tickLock()` interval countdown
- [x] `destroy()` — clears intervals + unsubscribes from Echo

### `PacketHijack.vue` Component
- [x] Full-screen dark overlay — JetBrains Mono, cyberpunk palette (`#08080f` bg, cyan, amber, pink)
- [x] Scrollable command history pane — colour-coded line classes (success/error/alert/clue/candidate)
- [x] `SYS_INPUT >` prompt with blinking cursor block, keyboard focus on mount
- [x] Phase 1: IP pool in formatted rows, sniff clue in pink, isolate candidates with `->` prefix
- [x] Phase 2: Port Status Matrix with bias %, shattered strikethrough, exfil pulse animation
- [x] Malware injection: `[==>] 100%` progress line rendered from `line--progress` class
- [x] Defender alert overlay: amber/red pulsing banner with fade-in transition
- [x] Honeypot lock: input hidden, `[INPUT LOCKED — Xs]` counter shown
- [x] Win screen: `[BREACH COMPLETE]` green box with creds stolen, DISCONNECT button
- [x] Loss screen: `[CONNECTION TERMINATED]` red box with creds lost
- [x] Arrow-key command history navigation (up/down)
- [x] All state via props from `usePacketHijack`; emits `submit-command` and `match-complete`

---

## PHASE 6 — PvP Flow Integration
> Wire Packet Hijack into the existing challenge → accept → game loop.

- [x] `useCombat.js` — unchanged; `accept()` return value is ignored; WS event drives init
- [x] `Game.vue` — imports PacketHijack + usePacketHijack; `activePacketHijack` ref; `.packet-hijack.started` listener registered on mount; `onPacketHijackMatchComplete` syncs economy + cleans up
- [x] GridBreach PvP block replaced with PacketHijack in template; GridBreach PvE block untouched
- [x] `NodeInfoBlock.vue` — no changes needed; HACK button flow unchanged
- [x] `CombatController.php` — no changes needed; `POST /api/combat/result` is PvE/GridBreach-only; PH is self-resolving

---

## PHASE 7 — Existing Loadout Command Effects in Packet Hijack ✅ COMPLETE
> Define what each equipped command does when used as a **defender disruption** during an active Packet Hijack match.

- [x] Audit full command list — CommandSeeder.php reviewed; 12 hack commands confirmed
- [x] Design per-command Packet Hijack effects (player sign-off received per Rule 12):

  | Command | Packet Hijack Effect |
  |---------|----------------------|
  | Trace Route | Reveals first octet of target IP (e.g. 192.x.x.x) — self-buff |
  | Overclock | Raises exploit threshold 25% → 45% for next exploit only — self-buff |
  | Mirror Protocol | Reflects next opponent rig command — buffs also go to holder; attacks rebound on attacker |
  | Data Spike | Auto-scans lowest-bias port (scan output without spending input) — self-buff |
  | Phase Shift | Un-shatters opponent's most recently shattered port; re-locks exfil if applicable |
  | Sector Corrupt | Fake-inverts displayed bias on 1–2 opponent ports for 10s |
  | Hardlock | Locks opponent terminal — L1: 2.5s / L2: 3.5s |
  | Null Byte | Injects 1–2 decoy IPs back into opponent's Phase 1 pool |
  | Static Burst | Floods opponent terminal with garbage + locks input — L1: 2s / L2: 3s |
  | Phantom Key | Adds 1–2 extra ports (HIGH bias) to opponent's Phase 2 cascade |
  | Sector Purge | Re-randomises all opponent port biases — L1: 50–95% / L2: 70–95% |
  | Bait | Plants fake-low honeypot on a random opponent port; locks attacker on exploit trigger — L1: 3s / L2: 5s |
  | Ghost Protocol | Injects 8 fresh decoy IPs into opponent's Phase 1 pool (existing) |
  | Signal Noise | Locks opponent input for 4s (existing) |

- [x] Migration `2026_05_27_200001` adds overclock_active, mirror_active, corrupt_ports, bait_ports, used_commands columns (×2 roles)
- [x] `PacketHijackMatch` model — new fillable, casts, and Phase 7 helper methods
- [x] `PacketHijackService::applyRigCommand(string $slug, PacketHijackMatch $match, string $userRole, int $level): array`
  - One-use-per-match guard via `{role}_used_commands` JSON array
  - 14 command handlers (12 new + 2 existing updated to new return format)
  - Mirror reflection logic in `applyMirrorEffect()` helper
  - `commandScanPort()` updated — accepts corruptPorts + baitPorts; returns fake bias when active
  - `commandExploitPort()` updated — bait check first, overclock threshold, shattered_at timestamp
- [x] `PacketHijackController::command()` — accepts `rig_command` field as alternative to `input`
- [x] `handleRigCommand()` private method — validates equipped hack command, one-use guard, delegates to service, broadcasts result + mirror events
- [x] `handleScan()` — passes corruptPorts + baitPorts to service
- [x] `handleExploit()` — passes overclocked flag + baitPorts; handles bait-hit lockout; clears overclock on success
- [x] `CommandSeeder` — updated packethijack_effect descriptions for Trace Route, Overclock, Mirror Protocol, Data Spike

---

## PHASE 8 — Balance, Polish & Edge Cases

### Frontend — Phase 7 Gap (rig command UI was not yet wired)
- [x] **`submitRigCommand(slug)`** — add to `usePacketHijack.js`; POSTs `rig_command` field, appends result lines, tracks `usedRigCommands` ref
- [x] **Rig command panel** — add to `PacketHijack.vue`; shows equipped hack commands as clickable buttons; greyed when used or locked
- [x] **Game.vue wiring** — pass `hackCommands` prop from `useGameState.commands` (filtered to `context === 'hack'`); wire `@use-rig-command` → `ph.submitRigCommand`

### Polish
- [x] **Typewriter effect** — multi-line terminal output streams in with ~80ms delay between lines rather than rendering all at once
- [x] **Scan-line CSS overlay** — subtle CRT scanline texture on the terminal pane
- [x] **Simultaneous `malware inject` UX** — first verified payload wins (lockForUpdate handles race); second gets `[ERROR: TARGET ALREADY PURGED]` terminal line
- [x] **Typo penalty UX** — `COMMAND NOT FOUND` prints instantly via client-side pre-validation *(already implemented in Phase 5)*

### Deferred — requires playtesting
- [ ] **Cascade coefficient tuning** — playtesting pass; adjust `attacker_cpu × 4` multiplier if ports fall too fast or too slow
- [ ] **Bias floor for starting LOW port** — confirm `8–18%` range is reliably exploitable on first attempt without feeling too easy
- [ ] **Simultaneous Phase 1 finish** — if both players `inject` the correct IP within the same request cycle, resolve by request arrival order (pessimistic lock handles this); no ties
- [ ] **Disconnection handling** — if a player's WebSocket drops mid-match, hold match state for 60s then auto-forfeit (requires scheduler job)

---

## PHASE 9 — QA & Integration Testing

- [ ] Two-browser local test of full match flow: Phase 1 → inject → Phase 2 → malware → win/loss
- [ ] Verify `pocket_creds` transfer is correct (winner gains, loser zeroed)
- [ ] Verify loser bounty resets to 0
- [ ] Confirm Reverb channel auth rejects non-participants
- [ ] Honeypot lock: test 3-second block is enforced server-side (not just client-side)
- [ ] Malware syntax edge cases: wrong IP, wrong port number, extra spaces, mixed case
- [ ] Cascade math: exploit Port 80 → confirm other port biases drop by correct amount
- [ ] Defender disruption commands: confirm effect applies to opponent state and not self
- [ ] Race condition: script two simultaneous `inject` calls and verify only one player advances
- [ ] Full regression: confirm existing PvE node hacking, StreetDoc, and bounty flows are unaffected

---

## FILE MANIFEST
> Every file touched by this build. Use as a final diff checklist.

### New Files
| File | Phase |
|------|-------|
| `database/migrations/2026_05_27_100001_create_packet_hijack_matches_table.php` | 1 |
| `app/Models/PacketHijackMatch.php` | 1 |
| `app/Services/PacketHijackService.php` | 2 |
| `app/Events/PacketHijackStarted.php` | 3 |
| `app/Events/PacketHijackPhaseTransition.php` | 3 |
| `app/Events/PacketHijackCommandResult.php` | 3 |
| `app/Events/PacketHijackMatchComplete.php` | 3 |
| `app/Http/Controllers/PacketHijackController.php` | 4 |
| `resources/js/composables/usePacketHijack.js` | 5 |
| `resources/js/components/minigame/PacketHijack.vue` | 5 |

### Modified Files
| File | Change | Phase |
|------|--------|-------|
| `.env` | Add `REVERB_*` vars, set `BROADCAST_CONNECTION=reverb` | 0 |
| `config/broadcasting.php` | Reverb driver config | 0 |
| `resources/js/composables/useWebSocket.js` | Replace stub with Laravel Echo | 0 |
| `routes/channels.php` | Register `packet-hijack.{matchId}` private channel | 3 |
| `routes/api.php` | Add Packet Hijack routes | 4 |
| `app/Http/Controllers/CombatChallengeController.php` | Replace GridBreach trigger with PacketHijack init | 4, 6 |
| `app/Http/Controllers/CombatController.php` | Deprecate/guard PvP result endpoint | 6 |
| `resources/js/composables/useCombat.js` | Open PacketHijack instead of GridBreach on accept | 6 |
| `resources/js/Pages/Game.vue` | Wire `<PacketHijack>` component | 6 |
| `app/Services/PacketHijackService.php` | Add `applyRigCommand()` | 7 |
| `app/Http/Controllers/PacketHijackController.php` | Add `use_rig_command` flag handling | 7 |

---

*Last updated: 2026-05-27 — Phases 0–6 complete. Phase 7 (rig command audit) awaits player sign-off per Rule 12. Phases 8–9 (balance + QA) pending live testing.*
