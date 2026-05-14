# WebSocket Events Reference

WebSocket endpoint: `ws://localhost:8085/ws/game`

All messages are JSON objects. Every **inbound** message must include `"playerId"`.
The `"event"` key identifies every **outbound** broadcast.

---

## Inbound Actions (Client → Engine)

### REGISTER
Hydrates the player session from Laravel. Must be the first message sent after connecting.

```json
{
  "action": "REGISTER",
  "playerId": "<uuid>"
}
```

---

### MOVE
Move to an adjacent node.

```json
{
  "action": "MOVE",
  "playerId": "<uuid>",
  "targetNodeId": "<uuid>"
}
```

**Fails with `move_failed` if:**
- Player has insufficient CPU cycles (limping costs 2 per hop, normal costs 1)
- Target node is not adjacent to the player's current node
- Player session is not registered

---

### PLACE_SHIPS
Place ships on the combat grid. Must be sent during `PRE_COMBAT` / `PLACING_SHIPS` phase.

```json
{
  "action": "PLACE_SHIPS",
  "playerId": "<uuid>",
  "combatId": "<uuid>",
  "ships": [
    {
      "shipId": "ship_1",
      "squares": [
        { "row": 0, "col": 0 },
        { "row": 0, "col": 1 },
        { "row": 0, "col": 2 }
      ]
    }
  ]
}
```

---

### SHOOT
Fire a shot at a coordinate on the opponent's grid. Only valid during `ACTIVE` phase.

```json
{
  "action": "SHOOT",
  "playerId": "<uuid>",
  "combatId": "<uuid>",
  "row": 3,
  "col": 5
}
```

---

### USE_COMMAND
Use a combat command. Only each command may be used once per combat.

```json
{
  "action": "USE_COMMAND",
  "playerId": "<uuid>",
  "combatId": "<uuid>",
  "command": "Databomb"
}
```

Commands that require additional parameters include them in the same object:

```json
{ "action": "USE_COMMAND", "playerId": "...", "combatId": "...", "command": "Crash",     "row": 2 }
{ "action": "USE_COMMAND", "playerId": "...", "combatId": "...", "command": "Trojan",    "shipId": "ship_2" }
{ "action": "USE_COMMAND", "playerId": "...", "combatId": "...", "command": "SQL_Injection", "shipId": "ship_3" }
{ "action": "USE_COMMAND", "playerId": "...", "combatId": "...", "command": "DDOS FOG",  "row": 1, "col": 2 }
{ "action": "USE_COMMAND", "playerId": "...", "combatId": "...", "command": "Fork Bomb", "primaryRow": 3, "primaryCol": 4, "forkRow": 3, "forkCol": 5 }
```

---

### ESCAPE
Available to the **resident** player only during the `PRE_COMBAT` escape window (10 seconds).
Moves the resident to an adjacent node and forfeits combat (intruder wins).

```json
{
  "action": "ESCAPE",
  "playerId": "<uuid>",
  "combatId": "<uuid>",
  "targetNodeId": "<uuid>"
}
```

---

## Outbound Events (Engine → Client)

### CONNECTED
Sent immediately on WebSocket connect before any message is received.

```json
{ "event": "CONNECTED", "message": "Welcome to CodeCraft" }
```

---

### SESSION_REGISTERED
Sent after a successful `REGISTER` action.

```json
{
  "event": "SESSION_REGISTERED",
  "playerId": "<uuid>",
  "handle": "Ghost",
  "currentNodeId": "<uuid>",
  "cpuCyclesRemaining": 3,
  "isLimping": false,
  "bountyLevel": 0
}
```

---

### PLAYER_MOVED
Sent to the moving player after a successful `MOVE`. Includes ping data if one was left.

```json
{
  "event": "PLAYER_MOVED",
  "playerId": "<uuid>",
  "newNodeId": "<uuid>",
  "ping": {
    "event": "ping",
    "pingId": "<uuid>",
    "nodeId": "<uuid>",
    "playerId": "<uuid>",
    "createdAt": 1712620800000,
    "expiresAt": 1712621100000,
    "isLoud": false
  },
  "combatTriggeredWith": "<opponent-uuid-or-omitted>"
}
```

`ping` is omitted when the player is cloaked (moves 1–2) or in post-combat silent mode.
`combatTriggeredWith` is present only when another player was found on the target node.

---

### COMBAT_TRIGGER
Sent alongside `PLAYER_MOVED` when combat is initiated.

```json
{
  "event": "COMBAT_TRIGGER",
  "combatId": "<uuid>",
  "nodeId": "<uuid>",
  "intruderId": "<uuid>",
  "residentId": "<uuid>",
  "gridSize": 8,
  "escapePossible": true
}
```

`escapePossible` is `false` when both players arrived within 200ms of each other.

---

### SHIPS_PLACED
Sent after a player successfully places their ships.

```json
{
  "event": "SHIPS_PLACED",
  "combatId": "<uuid>",
  "playerId": "<uuid>",
  "bothReady": false,
  "phase": "ACTIVE"
}
```

`phase: "ACTIVE"` is included only when both players are ready and combat begins.

---

### SHOT_RESULT
Sent after a successful `SHOOT` action.

```json
{
  "event": "SHOT_RESULT",
  "combatId": "<uuid>",
  "shooterId": "<uuid>",
  "row": 3,
  "col": 5,
  "hit": true,
  "sunk": false,
  "isBlocked": false,
  "isFalseMiss": false,
  "trojanFired": false,
  "stolenCommand": "Databomb",
  "winnerId": "<uuid-or-omitted>",
  "p1TraceBar": 0.35,
  "p2TraceBar": 0.65
}
```

Optional fields: `trojanFired` (only when a Trojan triggers), `stolenCommand` (RootKit steal),
`winnerId` (only when this shot ends the game).

---

### COMBAT_RESOLVED
Sent after a winner is determined.

```json
{
  "event": "COMBAT_RESOLVED",
  "combatId": "<uuid>",
  "winnerId": "<uuid>",
  "loserId": "<uuid>",
  "nodeId": "<uuid>"
}
```

After receiving this, the engine calls `POST /api/combat/result` to persist consequences in Laravel.

---

### COMMAND_RESULT
Sent after a `USE_COMMAND` action.

```json
{
  "event": "COMMAND_RESULT",
  "combatId": "<uuid>",
  "command": "Databomb",
  "success": true,
  "message": "Databomb planted."
}
```

**No notification rule:** The opponent does not receive a `COMMAND_RESULT` event.
They must observe the board to detect command usage.

---

### ESCAPE_SUCCESS
Sent to the resident player after a successful escape.

```json
{
  "event": "ESCAPE_SUCCESS",
  "playerId": "<uuid>",
  "newNodeId": "<uuid>",
  "combatId": "<uuid>"
}
```

---

### ERROR
Sent when any action fails validation or encounters an error.

```json
{
  "event": "ERROR",
  "code": "move_failed",
  "message": "Insufficient CPU cycles."
}
```

| Code | Trigger |
|------|---------|
| `invalid_json` | Message could not be parsed |
| `missing_player_id` | Message missing `playerId` field |
| `unknown_action` | Unrecognised `action` value |
| `register_failed` | Laravel returned an error during session hydration |
| `move_failed` | Invalid move (no CPU, non-adjacent, session not found) |
| `missing_field` | Required field missing from message |
| `invalid_ships` | Ships array failed validation |
| `place_ships_failed` | Ship placement rejected by CombatManager |
| `shot_failed` | Shot rejected (not your turn, invalid coordinate, etc.) |
| `command_failed` | Command rejected (already used, Blackout active, etc.) |
| `combat_not_found` | `combatId` does not match any active session |
| `escape_window_closed` | ESCAPE sent after the 10-second window expired |
| `not_resident` | ESCAPE sent by the intruder instead of the resident |
| `escape_failed` | Move during escape was invalid |

---

## Combat Command Reference

| Command | Type | Effect |
|---------|------|--------|
| `Databomb` | Offensive | Plants a false hit marker on opponent's board |
| `Overload` | Offensive | Displaces opponent's next shot near their intended target |
| `Crash` | Offensive | Scans a full row — green (ship present) or red (clear), visible to you only |
| `Trojan` | Offensive | Hidden on one of your ships; triggers a negative effect when that ship is sunk |
| `Exploit` | Offensive | Grants a free retaliatory shot the next time opponent hits one of your ships |
| `Packet Flood` | Offensive | Opponent's next 3 shots each carry a 5-second response timer; miss = forfeit |
| `Scramble` | Offensive | Wipes opponent's shot-history markers for up to 3 of their shots; ends early on hit |
| `SQL_Injection` | Offensive | First hit on a chosen 3+ square ship reads as a miss; revealed on second hit |
| `Fork Bomb` | Offensive | If initial shot hits, a second shot fires on an adjacent square of your choice |
| `DDOS FOG` | Offensive | Blocks a 2×2 area for 2 turns; shots into the zone show "Signal Blocked", no marker left |
| `RootKit Install` | Offensive | Steal one unused command from opponent when you sink a ship |
| `Buffer Overflow` | Offensive | If your next shot hits, one opponent command is permanently disabled |
| `Blackout` | Defensive | All incoming offensive commands have no effect for 3 opponent turns |
