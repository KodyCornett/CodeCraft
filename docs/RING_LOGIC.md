# CodeCraft — Stat Ring Logic Reference

> Source of truth for the Kotlin engine and any future service that touches stat calculations.
> Do not implement stat math outside of `App\Services\RigService` (Laravel) or a direct port of this document.

---

## The Five Stats

| Stat | Column | Role |
|------|--------|------|
| OS (Operating System) | `os_level` | Life Support — determines Max System Stability (SS) |
| RAM | `ram_level` | Memory — active command slots / missed bubble buffer |
| CPU | `cpu_level` | Processing — movement points per turn / reduces cooldowns |
| Storage | `storage_level` | Capacity — loot slots / protects items during breach |
| Firewall | `firewall_level` | Security — shortens ping duration / slows enemy trace bar |

Each stat is stored as an integer level on the `player_rigs` table.

---

## Effective Stat Value

```
effective_value = chassis_base + upgrade_level + peripheral_boost
```

- `chassis_base` — the flat base value from the player's `ChassisTemplate` (e.g., `base_cpu`).
- `upgrade_level` — the stat's current level on the `PlayerRig` (e.g., `cpu_level`).
- `peripheral_boost` — the sum of boost amounts from installed, undamaged peripherals that target this stat.

**Peripheral boosts do NOT trigger the dependency ring tax.** They are additive only.

For OS, `chassis_base` is a string (the OS name — e.g., "Ubuntu") rather than a numeric value. Effective OS is `os_level + peripheral_boost`.

---

## The Dependency Ring

```
OS → RAM → CPU → Storage → Firewall → OS
```

This ring defines which stat is taxed (downgraded by 1) when a player upgrades a stat at the point cap.

### Tax Rule

Tax only triggers when:
```
(cpu_level + ram_level + firewall_level + storage_level + os_level) >= chassis.total_point_cap
```

When the cap is reached, upgrading a stat costs 1 level from the **next stat clockwise in the ring**:

| Stat upgraded | Stat taxed |
|---------------|------------|
| OS            | RAM        |
| RAM           | CPU        |
| CPU           | Storage    |
| Storage       | Firewall   |
| Firewall      | OS         |

### Search Behaviour

The engine walks the ring starting at the position immediately after the upgraded stat. It skips any stat that is already at the minimum level (1) and taxes the first eligible stat found. If all other stats are at minimum, the upgrade is rejected with a `RuntimeException`.

### Peripheral Exception

Peripheral boosts are additive and never trigger or interact with the ring tax. Installing a peripheral that boosts CPU does not affect RAM, Storage, or any other stat.

---

## Point Cap

Each `ChassisTemplate` defines a `total_point_cap`. This is the maximum sum of all five stat levels a player can hold simultaneously. The cap limits total investment across the ring — it does not limit any individual stat.

---

## System Stability (SS)

Max SS is derived entirely from OS level:

```
max_ss = os_level × 10
```

`current_ss` is stored on `player_rigs` and must be recalculated (clamped to `max_ss`) whenever `os_level` changes — whether from an upgrade or a ring tax.

---

## Failure States

### PvE Failure — Limp Mode

**Trigger:** `current_ss` reaches 0 from a PvE damage source.

**Consequences:**
- `is_limping` set to `true` on `player_rigs`.
- `current_ss` floored at **1** (rig is alive but barely).
- Movement cost doubles (2 CPU cycles per hop instead of 1).
- Player emits a "Glitch" ping visible to all nearby players.
- **No stat levels are lost.**

**Recovery:** Visiting a Street Doc repairs SS and clears `is_limping`.

**API event string:** `"limp_mode"`

---

### PvP Failure — Critical Crash

**Trigger:** `current_ss` reaches 0 from a PvP damage source.

**Consequences:**
- `is_limping` cleared (`false`).
- `current_ss` restored to `max_ss` (the Street Doc patches them up).
- Player position reset to `last_street_doc_id`.
- **Loses 1 upgrade level on a randomly chosen chassis stat** (handled by `POST /api/combat/result`, not by `applyDamage()`).
- Loses a percentage of carried creds scaled to bounty level (handled by `BountyService::calculateStealPercentage()`).
- If player is Open Season status: no post-combat cooldown protection.

**API event string:** `"street_doc_reset"`

---

## Implementation Reference

All stat logic lives in `App\Services\RigService`:

| Method | Responsibility |
|--------|---------------|
| `upgradeStat(PlayerRig, string)` | Levels a stat, triggers ring tax if at cap, syncs SS if OS changes |
| `applyDamage(PlayerRig, int, string)` | Reduces SS, dispatches to `enterLimpMode` or `resetToStreetDoc` |
| `effectiveStats(PlayerRig)` | Returns full per-stat breakdown including peripheral boosts |
| `totalPointsSpent(PlayerRig)` | Sum of all five stat levels |
| `maxSs(PlayerRig)` | `os_level × 10` |
| `peripheralBoosts(PlayerRig)` | Per-stat peripheral boost totals (stubbed at 0 until peripherals table exists) |

**Controllers must never perform stat math.** Controllers call `RigService`. `RigService` does the work.
