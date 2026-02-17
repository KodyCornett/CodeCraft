# CodeCraft — Development Progress

## Committed Features (on `develop`)

| Commit | Feature |
|--------|---------|
| `7b7f477` | Initial project setup with IntelliJ IDEA configuration |
| `2883a6c` | Foundation scaffold — Laravel + Kotlin project structure |
| `2c55b0b` | Diegetic desktop UI with terminal and file explorer |
| `5ec918b` | Dark theme — proper OS-like styling |
| `498a6b1` | Start menu and Node Manager canvas visualization |
| `bf979f4` | Subtle hacker-themed desktop background |
| `4fbb89e` | In-game browser with modular site architecture |
| `b9c92c2` | Messaging system with GUI and terminal integration |
| `facde80` | Sentinel security monitor with exposure tracking |
| `ebcdd9b` | Kotlin game engine with Ktor server |

---

## Uncommitted Work (on `develop` working tree)

All changes below are implemented but not yet committed/pushed.

### Act I — Mission System
- **MissionService** (`web/app/Services/Mission/MissionService.php`) — 5 missions with objective tracking, credit rewards, message injection on completion
- **MockGameEngine** mission integration — calls `trackAction()` after every command
- Mission completion messages (IDs 101–109) injected via MessageService

### Messaging System Expansion
- **MessageService** rewritten — 3 sources: base messages, dynamic messages (`game_dynamic_messages`), encrypted messages
- **MessagesController** (`web/app/Http/Controllers/Api/MessagesController.php`) — new API
- **messages.js** + **messages.blade.php** — GUI app for reading messages

### Security Systems
- **SentinelService** — exposure tracking with time-based decay, shield status, counter-hack puzzle generation
- **PuzzleGenerator** (`web/app/Services/GameEngine/PuzzleGenerator.php`) — static utility with 6 puzzle types (cipher, wordJumble, ports, binary, hexDecode, reverse)
- **Counter-hack puzzle shield** — player solves puzzle in terminal after clicking Counter-Hack in Sentinel; correct answer gives 240s exposure freeze
- **Proactive DEFRAG** — available from Firewall app when connection traces exist (not just during Sentinel attacks); requires localhost; doesn't damage firewall unless triggered by attack

### Firewall App
- **FirewallController** (`web/app/Http/Controllers/Api/FirewallController.php`) — status, repair, activate endpoints; returns trace count, localhost status, defrag state
- **firewall.js** + **firewall.blade.php** — repair flow, DEFRAG section with trace indicators

### MockGameEngine Expansion
- Full command set: help, ls, cd, cat, connect, solve, scan, disconnect, open, download, mail, sentinel, defrag
- 6 network nodes with filesystems and file contents (localhost, public-gateway, nova-corp-web, nova-corp-mail, nova-corp-sec, nova-corp-db)
- Connection puzzles, node access tracking, download tracking
- Sentinel attack trigger at 100% exposure, auto-disconnect, DEFRAG puzzle flow
- Shield check in `increaseTrace()` — skips exposure increase while shield active

### Kotlin Engine Updates
- New command categories: MissionCommands, PuzzleCommands, StoreCommands
- Domain models: Mission, Negotiation, PuzzleEngine
- Session management updates, WebSocket protocol changes
- Route and filesystem command expansions

### Frontend Updates
- **terminal.js** — state change handling (connections, nodes, shield, defrag), typewriter output
- **sentinel.js** + **sentinel.blade.php** — counter-hack puzzle UI, shield banner, event log
- **node-manager.js** + **node-manager.blade.php** — canvas rendering, session persistence
- **window-manager.js** — new window types
- **secure-channel.js** + **secure-channel.blade.php** — new app (scaffolded)
- **index.blade.php** — new apps in start menu, layout updates

### Infrastructure
- **CLAUDE.md** — project instructions for Claude Code
- **KotlinGameEngine** — HTTP client updates for new endpoints
- Route additions in `web/routes/web.php`
- Gradle wrapper files for engine

---

## Not Yet Started

### Story Content
- **Act II** — "The Trap" (Zer0's honeypot, SIGINT radar, ISP hack)
- **Act III** — "The Devil's Offer" (SIGINT coercion, grey-hat missions)
- **Act IV** — "Inside the Machine" (Meridian scope, Lena reveal)
- **Act V** — "Burned" (identity burned, rebuild from safehouse)
- **Act VI** — "The Holst Files" (dead drops, SIGINT infiltration)
- **Act VII** — "Meridian Down" (evidence release, 3 endings)

### Game Systems (Known Gaps)
- **Store/shop** — Cipher's tool store (StoreCommands.kt scaffolded, no frontend)
- **Secure Channel** — encrypted messaging app (secure-channel.js scaffolded, not functional)
- **Kotlin engine parity** — MockGameEngine has features the Kotlin engine doesn't yet mirror (missions, counter-hack, shield, proactive defrag)
- **Negotiation system** — domain model exists (`Negotiation.kt`), not integrated
- **Probe/exploit commands** — listed as discoverable in MockGameEngine but not implemented
- **Persistent save/load** — `game.php` config references autosave but no persistence layer beyond session
- **Testing** — no PHPUnit tests for MockGameEngine, MissionService, SentinelService, etc.

---

## Session State Quick Reference

| Key | Purpose | Set By |
|-----|---------|--------|
| `game_connected_to` | Current remote connection | MockGameEngine |
| `game_current_path` | Current filesystem path | MockGameEngine |
| `game_exposure` | Exposure percentage (0–100) | MockGameEngine |
| `game_connection_history` | Array of connection traces | MockGameEngine |
| `game_node_access` | Array of solved node IDs | MockGameEngine |
| `game_pending_puzzle` | Active connection puzzle | MockGameEngine |
| `game_counterhack_puzzle` | Active counter-hack puzzle | SentinelService |
| `game_shield_active` | Shield active flag | MockGameEngine |
| `game_shield_expires_at` | Shield expiry ISO timestamp | MockGameEngine |
| `game_defrag_state` | DEFRAG progress + `was_under_attack` | MockGameEngine |
| `game_sentinel_attack_active` | Sentinel breach flag | MockGameEngine |
| `game_firewall_status` | `active` / `damaged` / `repairing` | MockGameEngine / FirewallController |
| `game_mission_state` | Mission objective flags | MissionService |
| `game_dynamic_messages` | Injected messages | MessageService |
| `game_downloads` | Downloaded file records | MockGameEngine |
| `game_discovered_nodes` | Nodes for Node Manager | MockGameEngine |
| `game_node_connections` | Edges for Node Manager | MockGameEngine |
| `game_sentinel_events` | Event log (max 50) | MockGameEngine |
| `game_last_action_time` | For exposure decay calculation | MockGameEngine |
