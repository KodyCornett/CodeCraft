# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CodeCraft is a text-based hacking game where the desktop is the world and the terminal is the only way to act. It has a diegetic UI design — no game menus or HUD, everything is an in-world "app."

## Architecture

```
[Browser UI]  Alpine.js + Tailwind, rendered by Laravel Blade
     ↓
[Laravel Backend]  web/ — UI rendering, API routes, game service layer
     ↓  HTTP / WebSocket
[Kotlin Engine]  engine/ — command parsing, game simulation, state management
```

- **Laravel** (`web/`) handles UI rendering, API endpoints, and delegates game logic via `GameEngineInterface`
- **Kotlin/Ktor** (`engine/`) handles command execution, player state, network topology, missions, and puzzles
- Laravel communicates with the Kotlin engine via HTTP/WebSocket. The Kotlin engine must be running for the game to function.

**IMPORTANT:** This project uses **Kotlin engine only**. The `MockGameEngine` has been removed. Do NOT create mock implementations or session-based game state. All game logic and state management happens in the Kotlin engine, accessed via HTTP API calls through `KotlinGameEngine.php`.

### Key Integration Points

- `web/app/Contracts/GameEngine/GameEngineInterface.php` — the contract between Laravel and the game engine
- `web/app/Services/GameEngine/KotlinGameEngine.php` — HTTP client connecting to Kotlin engine
- `web/app/Providers/GameEngineServiceProvider.php` — binds KotlinGameEngine to GameEngineInterface
- `web/config/game.php` — engine URL and game settings (exposure decay, starting credits, autosave)

### Frontend Architecture

The desktop UI is a single Blade view (`web/resources/views/desktop/index.blade.php`) with Alpine.js components in `web/resources/js/desktop/`. Each "app" is a draggable window managed by `window-manager.js`. The terminal (`terminal.js`) posts commands to `/api/terminal`. Network topology is canvas-rendered in `node-manager.js`.

### Engine Command System

Commands are registered in `engine/.../command/CommandRegistry.kt` and implemented in `engine/.../command/commands/`. Each command implements the `Command` interface. Categories: System, Filesystem, Network, Utility, Mission, Puzzle, Store.

#### Lateral Movement

- Players can connect to a new node while already connected without manual `disconnect`
- The engine automatically disconnects from the current node first
- Net exposure per lateral move: -2.0 (auto-disconnect) + 5.0 (connect) = **+3.0**
- Terminal output shows both disconnection and connection messages for clarity
- Connection traces are properly closed/opened to maintain forensic integrity

### State Management

**ALL game state is managed by the Kotlin engine**, including:
- Player state (exposure, credits, inventory, discovered nodes)
- Network topology and node connections
- Mission progress and objectives
- Puzzle state (connection puzzles, DEFRAG, counter-hack)
- Shield, firewall, and detection state
- Downloads and file metadata

Laravel controllers query the Kotlin engine via HTTP API (`KotlinGameEngine.php`) and **MUST NOT** store game state in Laravel sessions. The only session data allowed is the PHP session ID used to identify the player's Kotlin engine session.

**Do NOT:**
- Store game state in `session()` (e.g., `session('game_exposure')`)
- Create mock implementations or fallback logic
- Read/write game state directly in controllers

**Do:**
- Call `$this->engine->getSentinelStatus($sessionId)`
- Call `$this->engine->getFirewallStatus($sessionId)`
- Use the Kotlin API endpoints for all game data

## Prerequisites

- **PHP 8.2+** with Composer
- **Node.js 18+** with npm
- **JDK 21** for Kotlin engine
- **SQLite** (for game state persistence)

## Quick Start

**To run the game (requires both services running):**

```bash
# Terminal 1: Start Kotlin Engine (REQUIRED - must start first)
cd engine
./gradlew run

# Terminal 2: Start Laravel + Vite (wait for engine to start)
cd web
composer dev

# Browser: Open http://localhost:8000
```

**To verify the engine is running:**
```bash
curl http://localhost:8085/health
# Expected: {"status":"ok","engine":"CodeCraft","version":"0.1.0","sessions":0}
```

**To stop:**
- Press `Ctrl+C` in both terminals

## Running the Application

**IMPORTANT:** The Kotlin engine must be running before starting the Laravel application. The game will NOT work if only Laravel is running.

### Step 1: Start Kotlin Engine (Port 8085)

Open a terminal and run:
```bash
cd engine
./gradlew run
```

**Wait for this output:**
```
??????????????????????????????????????????
?     CodeCraft Game Engine v0.1.0       ?
??????????????????????????????????????????
?  Starting on port 8085                  ?
??????????????????????????????????????????
[Database] ? Database initialized successfully with all tables
Responding at http://127.0.0.1:8085
```

### Step 2: Start Laravel + Vite (Port 8000)

Open a **second terminal** and run:
```bash
cd web
composer dev
```

This command runs Laravel server, queue worker, logs, and Vite concurrently.

**Alternative (run services separately):**
```bash
# Terminal 2: Laravel server
cd web
php artisan serve

# Terminal 3: Vite dev server (hot reload)
cd web
npm run dev

# Terminal 4: Queue worker (optional, for background jobs)
cd web
php artisan queue:work
```

### Step 3: Open the Game

Open your browser to: **http://localhost:8000**

You should see the CodeCraft desktop with draggable app windows.

## Build & Run Commands

### Laravel Web App (`web/`)

```bash
cd web
composer setup          # One-time: install deps, generate key, migrate, build assets
composer run dev        # Development: runs Laravel server (8000), queue, logs, Vite concurrently
composer run test       # Run PHPUnit tests
php artisan serve       # Laravel server only (port 8000)
npm run dev             # Vite dev server only (hot reload)
npm run build           # Production asset build
./vendor/bin/pint       # Code style linting (Laravel Pint)
```

### Kotlin Engine (`engine/`)

```bash
cd engine
./gradlew build         # Build the engine
./gradlew run           # Run engine server (port 8085)
./gradlew test          # Run all tests
```

## Testing

### Run All Tests

**Kotlin Engine Tests:**
```bash
cd engine
./gradlew test
```

Expected output: `BUILD SUCCESSFUL` with all tests passing.

**Laravel Tests (currently minimal):**
```bash
cd web
composer test
# Or: ./vendor/bin/phpunit
```

### Manual Testing Checklist

After starting both services, verify:

1. **Engine health check:**
   ```bash
   curl http://localhost:8085/health
   ```
   Should return: `{"status":"ok","engine":"CodeCraft","version":"0.1.0"}`

2. **Terminal commands work:**
   - Open http://localhost:8000
   - Click Terminal window
   - Type: `help`, `ls`, `cd documents`, `cat mission_briefing.txt`
   - All commands should return output

3. **Jobs Board integration:**
   - Complete tutorial commands
   - Open Jobs Board app
   - Verify missions appear and can be accepted

4. **Sentinel app:**
   - Open Sentinel app
   - Verify exposure level shows (from Kotlin engine)
   - Run terminal commands, exposure should change

5. **Node Manager:**
   - Type `scan` in terminal
   - Open Node Manager app
   - Verify nodes appear on graph

## Tech Stack

- **Frontend:** Alpine.js 3, Tailwind CSS 4, Vite 7, Axios
- **Backend:** Laravel 12 (PHP 8.2+), SQLite (dev)
- **Engine:** Kotlin 2.0.21, Ktor 3.0.3, Kotlinx Serialization, JDK 21
- **Testing:** PHPUnit 11, Mockery, ktor-server-test-host

## Git Workflow

- `main` — production-ready
- `develop` — integration branch (current working branch)
- `feature/*` — feature branches, merged into `develop`

## Design Principles

1. **Diegetic UI** — No game menus or HUD. Everything is an in-world "app"
2. **Commands are discovered** — Players find commands through files, logs, and contacts
3. **Files as knowledge** — Answers are hidden in the UI (files, emails, websites), not the terminal
4. **The computer feels real** — The UI should feel like an actual operating system before anything "game-like" happens

## Mission System

Mission state is managed by the Kotlin engine. Missions are tracked in the engine's database, and mission events are returned via command responses. The engine exposes mission endpoints via REST API:
- `GET /api/missions/available/{sessionId}` — available missions for the player
- `POST /api/mission/{sessionId}/{missionId}/accept` — accept a mission
- `GET /api/mission/{sessionId}` — active mission status
- `POST /api/mission/{sessionId}/complete` — complete the active mission

Mission messages are injected into Laravel's `MessageService` via game events returned from command execution.

## Story — 7-Act Outline

**Premise:** The player is a freelance hacker taking small jobs from underground contacts. Their first target — NovaCorp — seems like routine corporate espionage, but breadcrumbs in the data hint at something much larger: **Project Meridian**, a domestic surveillance program run by a government-adjacent organization.

### Characters

| Character | Role | Acts |
|-----------|------|------|
| **Ghost** | Veteran fixer, player's mentor/handler | I–VII |
| **Cipher** | Crypto specialist, tool seller | I–VII |
| **Zer0** | Ambitious, reckless newcomer | I–III |
| **Marcus Webb** | NovaCorp security analyst, federal informant | I–II |
| **Erik Holst** | Terminated NovaCorp contractor, discovered Meridian | I–VII (through files) |
| **Unknown / [REDACTED]** | Later revealed as **Lena**, former SIGINT analyst | I–VII |
| **Director Hale** | Head of SIGINT Division | III–VI |

### Connecting Thread

Project Meridian references and Erik Holst's digital footprints appear in every act, discovered through files — never announced. The `meridian_sync.sh` script in NovaCorp's DB server is the first direct reference. The SIGINT query log searching for `%holst%` shows government interest in Erik.

### Act I — "Small Crimes, Real Consequences" (5 missions — IMPLEMENTED)

Tutorial arc. Player learns terminal basics, network recon, system intrusion, data exfiltration, and lateral movement through NovaCorp. Ends with discovery of Meridian breadcrumbs. Ghost guides progression; Cipher offers tools; Zero teases Act II; Unknown names Project Meridian.

### Act II — "The Trap"

Zero recruits player for a "big score" — a government contractor. It's a honeypot. Zero is captured; player narrowly escapes but is now on SIGINT's radar. Ghost goes dark. Lena (still Unknown) provides a lifeline. Player must hack their own ISP to scrub connection logs.

### Act III — "The Devil's Offer"

Director Hale contacts the player through official channels. SIGINT offers a deal: work for them as a grey-hat operative, or face prosecution. Player is coerced into hacking targets on SIGINT's behalf. Missions feel wrong — targeting journalists, activists. Erik Holst files surface showing he faced the same choice.

### Act IV — "Inside the Machine"

Player operates within SIGINT's infrastructure. Discovers the full scope of Meridian: mass data collection from dozens of corporations. Lena reveals herself and her goal — building a case to expose Meridian. Player must decide: continue working for Hale, or start secretly copying evidence.

### Act V — "Burned"

Player's double game is discovered. SIGINT burns their identity — real name leaked, accounts frozen, contacts endangered. Ghost resurfaces with a safehouse server. Player must rebuild from scratch on a hardened system while Hale's team hunts them. Erik Holst's fate is revealed: he's alive, in hiding, and has the final piece of evidence.

### Act VI — "The Holst Files"

Player tracks down Erik Holst through a chain of dead drops and encrypted messages across multiple networks. Must infiltrate SIGINT's own systems to retrieve the original Meridian authorization documents. Highest-difficulty puzzles. Ghost and Lena coordinate the operation. Cipher provides custom tools.

### Act VII — "Meridian Down"

Final act. Player has the evidence. Must choose how to release it: leak to journalists (maximum impact, player stays underground), deliver to oversight committee (institutional change, player gets immunity), or burn it all (destroy Meridian's infrastructure, scorched earth). Each ending has consequences shown through epilogue messages. The final message is from Ghost — or from Hale, depending on the ending.

### Story Design Rules

1. **No monologues** — Characters speak in short, punchy messages. No exposition dumps.
2. **Truth lives in files** — Plot revelations come from data the player finds, not from NPCs telling them.
3. **Diegetic only** — No cutscenes, no narration. Everything happens through the terminal, messages, and files.
4. **Player agency through action** — The player's choices are expressed through what they hack, what they download, and what they ignore.
5. **Breadcrumbs, not breadtrails** — Each act plants seeds for future acts in file contents, log entries, and database records.
