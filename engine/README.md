# CodeCraft Game Engine

Kotlin/Ktor server that handles game logic, command execution, and state management.

## Requirements

- JDK 21 or later
- Gradle 8.x (included via wrapper)

## Quick Start

```bash
# Navigate to engine directory
cd engine

# Run the engine (will download dependencies on first run)
./gradlew run

# Or on Windows
gradlew.bat run
```

The engine will start on port 8085 by default.

## Configuration

Environment variables:
- `ENGINE_PORT` - Server port (default: 8085)

## API Endpoints

### REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | Health check |
| `/api/command` | POST | Execute a command |
| `/api/session` | POST | Create/load/save session |
| `/api/session/{id}` | GET | Get session state |
| `/api/commands/{id}` | GET | Get available commands |
| `/api/network/{id}` | GET | Get discovered nodes |

### WebSocket

Connect to `/ws/terminal/{sessionId}` for real-time terminal communication.

Message format:
```json
{
  "type": "command",
  "payload": "{\"sessionId\":\"...\",\"command\":\"ls\",\"context\":{...}}"
}
```

Response format:
```json
{
  "type": "command_result",
  "payload": "{\"success\":true,\"output\":\"...\",\"delayMs\":100}"
}
```

## Project Structure

```
src/main/kotlin/com/codecraft/engine/
├── Application.kt          # Main entry point
├── api/
│   ├── Routes.kt           # REST endpoints
│   └── WebSocket.kt        # WebSocket handler
├── command/
│   ├── Command.kt          # Command interface
│   ├── CommandRegistry.kt  # Command lookup
│   └── commands/           # Command implementations
├── domain/
│   ├── Player.kt           # Player state
│   └── Network.kt          # Network nodes
├── protocol/
│   └── Messages.kt         # Request/response types
└── session/
    ├── GameSession.kt      # Per-player session
    └── SessionManager.kt   # Session management
```

## Connecting Laravel

Set in Laravel's `.env`:
```
GAME_ENGINE=kotlin
GAME_ENGINE_URL=http://localhost:8085
```

Or keep using mock engine for UI development:
```
GAME_ENGINE=mock
```

## Development

```bash
# Build
./gradlew build

# Run tests
./gradlew test

# Build distribution
./gradlew distZip
```

## Implemented Commands

### System
- `help [command]` - Display help
- `clear` - Clear terminal
- `whoami` - Show current user

### Filesystem
- `ls [path] [-l] [-a]` - List directory
- `cd <path>` - Change directory
- `cat <file>` - Display file
- `pwd` - Print working directory

### Network
- `scan <target> [-A]` - Scan for ports
- `connect <target> [port]` - Connect to system
- `disconnect` - Close connection
- `probe` - Deep system analysis

### Utility
- `mail [list|read <id>]` - Access messages
- `sentinel` / `status` - Security monitor
