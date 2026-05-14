# API Reference

Base URL: `http://localhost:8000/api`

All endpoints except `POST /auth/token` require a Sanctum bearer token:
```
Authorization: Bearer <token>
Accept: application/json
```

---

## Auth

### POST /auth/token
Issue a Sanctum token. Used by the Kotlin engine on startup.

**Request**
```json
{ "email": "test@example.com", "password": "password" }
```

**Response 200**
```json
{ "token": "1|plainTextToken..." }
```

---

## Rig Management

### GET /rig?player_id={uuid}
Returns the full rig snapshot including effective stats and peripheral accounting.

**Response 200**
```json
{
  "rig_id": "<uuid>",
  "chassis": "Standard Chassis",
  "is_limping": false,
  "current_ss": 20,
  "max_ss": 20,
  "stats": {
    "os":       { "level": 2, "base": 2, "peripheral_boost": 0, "effective": 4 },
    "ram":      { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
    "cpu":      { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
    "storage":  { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
    "firewall": { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 }
  },
  "points": { "spent": 7, "cap": 20 }
}
```

---

### POST /rig/damage
Reduce a player's SS. Called by the Kotlin engine on PvE or PvP damage.

**Request**
```json
{ "player_id": "<uuid>", "amount": 10, "source": "pve" }
```

`source`: `"pve"` or `"pvp"`

**Response 200**
```json
{
  "event": null,
  "rig_id": "<uuid>",
  "is_limping": false,
  "current_ss": 10,
  "max_ss": 20
}
```

`event` values:
- `null` — SS reduced, rig still standing
- `"limp_mode"` — SS hit 0 from PvE; `is_limping` set to `true`
- `"street_doc_reset"` — SS hit 0 from PvP; rig restored, random stat loses 1 level

---

### POST /rig/upgrade
Upgrade one stat level. Triggers ring tax if at chassis point cap.

**Request**
```json
{ "player_id": "<uuid>", "stat": "cpu" }
```

`stat`: `os` | `ram` | `cpu` | `storage` | `firewall`

**Response 200**
```json
{
  "stat_upgraded": "cpu",
  "tax_event": { "taxed_stat": "storage", "old_level": 2, "new_level": 1 },
  "rig_id": "<uuid>",
  "current_ss": 20,
  "max_ss": 20,
  "stats": { ... },
  "points": { "spent": 8, "cap": 20 }
}
```

`tax_event` is `null` when the chassis is below its point cap.

**Response 422** — stat at chassis max, or ring tax impossible.

---

### POST /rig/repair
Restore SS to maximum.

**Request**
```json
{ "player_id": "<uuid>", "repair_peripherals": false }
```

**Response 200**
```json
{ "rig_id": "<uuid>", "current_ss": 20, "max_ss": 20 }
```

---

## Player Status

### GET /player/{player_id}/status
Primary endpoint polled by the Kotlin engine to hydrate a `PlayerSession`.

**Response 200**
```json
{
  "player": {
    "id": "<uuid>",
    "handle": "Ghost",
    "current_node_id": "<uuid>",
    "current_district": "Downtown",
    "bounty_level": 0,
    "is_open_season": false,
    "is_limping": false,
    "post_combat_silent_moves": 0,
    "last_street_doc_id": null
  },
  "rig": {
    "rig_id": "<uuid>",
    "chassis": "Standard Chassis",
    "is_limping": false,
    "current_ss": 20,
    "max_ss": 20,
    "stats": {
      "os":       { "level": 2, "base": 2, "peripheral_boost": 0, "effective": 4 },
      "ram":      { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
      "cpu":      { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
      "storage":  { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 },
      "firewall": { "level": 1, "base": 2, "peripheral_boost": 0, "effective": 3 }
    },
    "points": { "spent": 7, "cap": 20 }
  }
}
```

`rig` is `null` when the player has no rig yet.

---

### POST /player/{player_id}/extract
Bank the current bounty run at a Street Doc. Resets all run counters.

**Response 200**
```json
{
  "message": "Run extracted successfully.",
  "multiplier_at_extract": 1.45,
  "bounty_level_banked": 18,
  "player": {
    "player_id": "<uuid>",
    "bounty_level": 0,
    "nodes_hacked_this_run": 0,
    "pvp_wins_this_run": 0,
    "bounty_multiplier": "1.00",
    "is_open_season": false
  }
}
```

---

## Map Data

### GET /nodes/{district}
Returns all nodes in a district with their adjacency lists. Used by `MapManager` on startup.

`district`: URL-encoded district name, e.g. `Downtown`, `South%20Hill`

**Response 200**
```json
[
  {
    "id": "<uuid>",
    "business_name": "Steam Plant Square",
    "district": "Downtown",
    "tier": 3,
    "latitude": 47.6588,
    "longitude": -117.4260,
    "cred_value_base": 300,
    "cred_resource_depleted": false,
    "movement_resource_depleted": false,
    "adjacent_node_ids": ["<uuid>", "<uuid>"]
  }
]
```

---

## Combat

### POST /combat/result
Called by the Kotlin engine when a combat session resolves. Persists all consequences.

**Request**
```json
{
  "winner_id": "<uuid>",
  "loser_id": "<uuid>",
  "node_id": "<uuid>",
  "stolen_cred_amount": 0,
  "stolen_item_id": null
}
```

**Response 200**
```json
{
  "steal_percentage": 25.0,
  "bounty_event": { "type": "none", "data": {} },
  "winner": {
    "player_id": "<uuid>",
    "handle": "Ghost",
    "post_combat_silent_moves": 2,
    "bounty_level": 15,
    "bounty_multiplier": "1.15",
    "is_open_season": false,
    "effective_stats": { ... }
  },
  "loser": {
    "player_id": "<uuid>",
    "handle": "Wraith",
    "post_combat_silent_moves": 2,
    "is_limping": false,
    "effective_stats": { ... },
    "damage_event": "street_doc_reset"
  }
}
```

`bounty_event.type`: `"none"` | `"bounty_marked"` | `"open_season_triggered"`

---

## Leaderboards

### GET /leaderboard/bounty
Returns all players currently on the bounty board (bounty_level ≥ 15), sorted descending.

**Response 200**
```json
{
  "leaderboard": [
    {
      "player_id": "<uuid>",
      "handle": "Wraith",
      "bounty_level": 22,
      "current_district": "South Hill",
      "pvp_wins_this_run": 3,
      "bounty_multiplier": "1.45",
      "is_open_season": false
    }
  ]
}
```

---

### GET /leaderboard/open-season
Returns all-time Open Season hall of fame records, sorted by best win streak.

**Response 200**
```json
{
  "hall_of_fame": [
    {
      "player_id": "<uuid>",
      "handle": "Spectre",
      "best_open_season_wins": 11
    }
  ]
}
```

---

## Street Doc

### POST /street-doc/visit
Register a player's arrival at a Street Doc. Triggers extract if they have an active bounty run.

**Request**
```json
{ "player_id": "<uuid>", "street_doc_id": "<uuid>" }
```

**Response 200**
```json
{
  "message": "Arrived at Monroe St. Underground.",
  "street_doc": { "id": "<uuid>", "name": "Monroe St. Underground" },
  "last_street_doc_id": "<uuid>",
  "bounty_level": 0
}
```

---

### POST /street-doc/repair
Repair the player's SS to maximum.

**Request**
```json
{ "player_id": "<uuid>", "cred_cost": 40 }
```

`cred_cost` is optional; if omitted the server calculates it as `missing_ss × 2`.

**Response 200**
```json
{
  "message": "SS restored to maximum.",
  "current_ss": 20,
  "max_ss": 20,
  "is_limping": false
}
```

---

### POST /street-doc/install
Install a hardware encrypt from the player's inventory onto their rig.

**Request**
```json
{ "player_id": "<uuid>", "encrypt_id": "<uuid>" }
```

**Response 200**
```json
{
  "message": "Hardware encrypt installed.",
  "peripheral_id": "<uuid>",
  "effective_stats": { ... }
}
```

**Response 422** — encrypt not found, already installed, or insufficient port slots.

---

### POST /street-doc/loadout
Set the player's active combat command loadout. Slot count capped by effective RAM stat.

**Request**
```json
{
  "player_id": "<uuid>",
  "active_command_ids": ["<cmd-uuid>", "<cmd-uuid>"]
}
```

**Response 200**
```json
{
  "message": "Loadout updated.",
  "active_commands": [
    { "id": "<uuid>", "name": "Databomb", "type": "offensive" }
  ]
}
```

**Response 422** — slot count exceeds RAM, or player doesn't own a command.

---

### POST /street-doc/reallocate
Move 1 upgrade level from one stat to another (no ring tax).

**Request**
```json
{ "player_id": "<uuid>", "from_stat": "cpu", "to_stat": "ram" }
```

`from_stat` / `to_stat`: `os` | `ram` | `cpu` | `storage` | `firewall`

**Response 200**
```json
{
  "message": "Moved 1 level from cpu to ram.",
  "from": { "stat": "cpu", "new_level": 2 },
  "to":   { "stat": "ram", "new_level": 3 },
  "effective_stats": { ... }
}
```

**Response 422** — from_stat already at minimum (level 1), or same stat specified for both.

---

## Error Responses

All endpoints return `422` for validation failures and `404` for missing resources:

```json
{ "message": "Player not found." }
```

Unauthenticated requests return `401`:

```json
{ "message": "Unauthenticated." }
```
