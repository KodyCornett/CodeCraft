# CodeCraft Engine

Kotlin/Ktor game engine for CodeCraft - the text-based hacking game.

## Status

**Phase 2 - Not yet implemented**

This engine will handle:
- Command parsing (`<verb> <target> <flags>`)
- Game state simulation
- Trace level calculations
- Deterministic outcomes

## Requirements

- JDK 21+
- Gradle 8.5+

## Building

```bash
./gradlew build
```

## Running

```bash
./gradlew run
```

## Architecture

```
Laravel (Frontend) ←→ WebSocket ←→ Kotlin Engine (Backend)
```

- Laravel asks: "What happens if the player does X?"
- Engine answers: "Here is the result."

## API

The engine exposes a WebSocket endpoint that accepts JSON commands:

```json
{
  "sessionId": "abc123",
  "command": "scan 192.168.1.1",
  "context": {
    "currentPath": "/home/user",
    "connectedTo": null
  }
}
```

And returns:

```json
{
  "success": true,
  "output": "Scanning 192.168.1.1...",
  "lines": ["Scanning 192.168.1.1...", "PORT  STATE  SERVICE", ...],
  "traceIncrease": 0.02,
  "delayMs": 1500,
  "stateChanges": {}
}
```
