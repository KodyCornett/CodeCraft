# NetRunner — Game Design Document

> Living document. Updated as design decisions are made.

---

## Overview

NetRunner is a cyberpunk-themed PvP hacking game set across a hex-based network map. Players navigate nodes across a city network, hack resources, upgrade their rigs, and hunt or evade other players. The UI is designed as a hacker's operating system — every element from the taskbar to the side panel is part of an in-world terminal experience called **SPLICE**.

---

## Core Game Loop

1. Player spawns into the network on their starting node (Downtown)
2. Move across the hex map spending **Uplink** per step
3. Arrive at a node — choose which resources to hack (CREDS / TECH / UPLINK)
4. Each hack burns **Cache** — when cache is full, hacking is locked until system is cleaned
5. Accumulate bounty from successful hacks — ICE begins pinging your location to other players
6. Spend resources at **CyberDoc** nodes to upgrade rig, buy consumables, clean cache
7. Evade hunters using commands, movement, and OS-based ping obfuscation
8. Compete for dominance on the network

---

## The Map

### Node Types

| Type | Description |
|------|-------------|
| **Action Node** | Hackable — contains CREDS, TECH, and UPLINK resources |
| **CyberDoc Node** | Store hub — spend resources, clean cache, upgrade rig |

### Node Stats

Each node has the following properties:

- **Name** — unique identifier used when ICE pings the player's location to other players (e.g. `FOX_THEATRE_WIFI_15`)
- **SPLICE Address** — deterministic network address derived from node identity
- **District** — which area of the city the node belongs to
- **ICE** — the node's defensive rating. Compared against player CPU to determine hack difficulty and cache cost

### ICE vs CPU (Hack Difficulty)

| CPU vs Node ICE | Hacks required per resource |
|-----------------|-----------------------------|
| CPU > ICE by 2+ | 1 hack |
| CPU = ICE | 1 hack |
| CPU < ICE by 1–2 | 2 hacks |
| CPU < ICE by 3–4 | 3 hacks |
| CPU < ICE by 5+ | Cannot attempt |

Going for all 3 resources on a node means 3× the hack count (modified by ICE). Every hack costs cache and adds exposure time.

### Node Resources

Each action node contains three hackable resources. Players choose which to go for:

| Resource | Use |
|----------|-----|
| **CREDS** | Currency — used for consumables and rig upgrades (paired with Tech Points) |
| **TECH** | Tech Points — rig upgrade currency (paired with Creds) |
| **UPLINK** | Refills the player's current Uplink pool to keep moving |

Players can hack one, two, or all three resources per visit. Each hack costs 1 Cache. Staying longer increases exposure risk.

---

## Rig System

The player's rig is their core character build. It determines base stats, upgrade ceiling, port slots, and cache capacity.

### Stat Overview

| Stat | What It Does |
|------|-------------|
| **CPU** | ICE cracking power (fewer hacks per node vs high ICE). Contributes to Cache pool |
| **RAM** | Gates command tier (max command level you can equip). Contributes to Cache pool |
| **OS** | Governs max stat investment level. Reduces ping accuracy (not frequency) |
| **Firewall** | Reduces PvP incoming damage and command effects |
| **Storage** | Sets command loadout capacity + inventory item slots |
| **Uplink** | Movement points per turn. **Chassis-locked — cannot be boosted by peripherals** |

### Cache System

```
Cache Pool = Effective CPU + Effective RAM
```

- Every hack consumes 1 Cache
- When Cache is full, the player **cannot hack** until system is cleaned
- **Defrag Injection** (consumable) — clears partial cache in the field
- **CyberDoc visit** — full cache wipe
- Cache is displayed live on the HUD: `CACHE: 3/8`

### Effective Stat Calculation

```
Effective Stat = Chassis Base + Peripheral Boost + Player Points
```

All three layers are capped by the chassis ceiling for that stat. Player point investment is additionally gated by OS level.

---

## Chassis & Progression

### Tier System

Rigs are versioned by tier. Each tier upgrade is a new chassis purchase — not an incremental upgrade of the same rig.

| Tier | Uplink Range | Stat Cap Range | Max Player Points/Stat | Port Slots |
|------|-------------|----------------|------------------------|------------|
| **v1.x** | 3–5 | up to ~5 | 3 | 0–2 |
| **v2.x** | 5–7 | up to ~7 | 6 | 2–3 |
| **v3.x** | 7–10 | up to ~10 | 9 | 3–4 |

- **Uplink is chassis-locked** — the only way to increase Uplink is to buy a higher-tier rig
- Tier jumps are designed to feel significant — v2.0 is a noticeable leap over v1.9
- Within a tier, minor version upgrades incrementally raise base stats

### Starting Rig — BlackHat v1.0

| Stat | Base | Chassis Cap |
|------|------|-------------|
| CPU | 3 | 5 |
| RAM | 2 | 4 |
| OS | 2 | 4 |
| Storage | 2 | 4 |
| Firewall | 1 | 3 |
| **Uplink** | **3** | **chassis only** |
| Port Slots | 0 | — |

- Cache pool at start: CPU 3 + RAM 2 = **5 cache**
- No peripherals, no player points invested
- Designed to be functional but tight — every stat is a meaningful bottleneck

### v2.0 Rig Options (3 choices — build identity decision)

At v2.0 the player picks a rig that defines their playstyle. Three archetypes:

| Build | Focus | Strengths |
|-------|-------|-----------|
| **Ghost** | High Uplink + High OS | Mobile, hard to locate accurately, long operational runs |
| **Breaker** | High CPU + High RAM | Cracks nodes fast, massive cache pool, efficient hacking |
| **Vault** | High Firewall + High Storage | Durable in PvP, large command loadout, tanky |

Each v2.0 rig has the same tier-level stats overall but distributed differently. No single best choice — counters exist between all three.

---

## Player Point Investment System

Players earn **Tech Points** through hacking and bounties and can spend them at CyberDoc to permanently invest in individual stats.

### Rules

- **Max 9 player points** into any single stat across a full playthrough (lifetime cap)
- How many you can currently invest is gated by **chassis tier**:

| Chassis Tier | Max Player Points per Stat |
|-------------|--------------------------|
| v1.x | 3 |
| v2.x | 6 |
| v3.x | 9 |

- **OS gates the investment ceiling** — you cannot invest player points into any stat higher than your current OS level
- Example: OS 4 → no stat can be pushed past level 4 via player points, regardless of available points

### Investment Priority
Players must raise OS to unlock the ability to push other stats higher. OS investment is never wasted.

---

## Economy

### Currencies

| Currency | How Earned | How Spent |
|----------|-----------|-----------|
| **Creds** | Hacking CREDS resource on nodes | Consumables (solo cost) + rig upgrades (paired with TP) |
| **Tech Points (TP)** | Hacking TECH resource on nodes, completing bounties | Rig upgrades only (always paired with Creds) |

### Upgrade Costs
All permanent rig upgrades (chassis, peripherals, stat investment) cost **Creds + Tech Points**. Neither currency alone is enough.

Single-use consumables cost **Creds only**.

### Consumables (CyberDoc)

| Item | Effect | Cost |
|------|--------|------|
| **Defrag Injection** | Clears partial cache in the field — keeps you operational | Creds only |
| **Rollback** | Resets 1 command from cooldown back to ready. Once per CyberDoc visit — cannot stack multiple uses between stops | Creds only |

Consumables are single-use, cost Creds only (no Tech Points), and are limited by the player's Storage stat (inventory slots). Items of the same type stack in a single slot.

---

## Bounty & Detection System

### Bounty

Bounty accumulates with every successful hack. It is both a threat and an incentive — higher bounty means more frequent pings and more hunters, but also a significant reward multiplier on all node loot.

| Bounty Level | Ping Trigger | Reward Multiplier |
|-------------|-------------|-------------------|
| LVL 0 | None | 1.0x (base) |
| LVL 1 | Every 8 moves OR every hack | 1.25x |
| LVL 2 | Every 6 moves OR every hack | 1.5x |
| LVL 3 | Every 4 moves OR every hack | 1.75x |
| LVL 4 | Every 3 moves OR every hack | 2.0x |
| **OPEN SEASON** | Every 2 moves AND every hack | **2.5x** |

**Open Season** — maximum bounty state. All players on the network are notified. Pings always reveal the exact node regardless of OS level. Highest risk, highest reward.

**Bounty wipe** — if the player reaches a CyberDoc node before being caught, their bounty is completely reset to 0. Reward multiplier is lost. This makes CyberDoc nodes strategically valuable as safe harbors and their map positions matter.

**Ping accuracy** is still OS-dependent at all levels except Open Season, where exact node is always revealed.

### ICE Detection & Pings

- Pings are **inevitable** — no player can avoid being detected indefinitely
- **OS does not reduce ping frequency** — it reduces **ping accuracy**

| OS Level | Ping Accuracy |
|----------|--------------|
| Low (1–3) | Exact node name revealed to hunters |
| Mid (4–6) | General area — within a few nodes |
| High (7–9) | District only — vague enough to require searching |

- Node **names** are what get transmitted to other players — this is why nodes have unique identifiers
- A hunter receives: *"Signal detected near `FOX_THEATRE_WIFI_15` — Browne's Addition"* (low OS) or *"Signal detected somewhere in Downtown"* (high OS)
- The hunter must then **physically navigate** to that location on the hex map, spending their own Uplink to get there
- By the time they arrive the target may have already moved — creating a live pursuit dynamic across the map
- Map knowledge becomes a survival skill — knowing fast routes between districts is as important as having a good rig

### Visibility Rules

**Players are never directly visible to each other on the map.** There are no player tokens on other people's screens. You cannot see where other players are — only traces of where they've been or signals they've left behind.

What players CAN see:
- **ICE pings** — node name broadcasts triggered by high bounty + node ICE
- **Bounty board** — active bounties with last known district
- **Ping traces** — a fading signal marker on a node that was recently triggered
- **Command effects** — if someone hits you with an offensive command you know they're close

The hunt is always based on **deduction and information**, not direct observation. You piece together where someone is from the signals they're leaving, not by watching them move.

### Detection Formula (draft)
```
Ping accuracy = node ICE + player bounty level - player OS
```

---

## Commands

Commands are active abilities equipped in the player's loadout. Governed by rig stats.

### Command Stats Interaction

| Stat | Effect on Commands |
|------|-------------------|
| **RAM** | Gates command tier — RAM 3 = can only equip up to tier 3 commands |
| **Storage** | Determines how many commands fit in the loadout (1:1 ratio with inventory slots) |

### Dual-Effect System

Every command has **two effects** — one for the map layer, one for the Grid-Breach hack battle. The player uses the same command pool for both contexts.

**Using a command in either context puts it on cooldown for both until the player visits a CyberDoc.**

This forces a core strategic decision on every command:
- Use it on the **map** to trap nodes, slow pursuers, or escape
- Save it for **Grid-Breach** where it disrupts the opponent's hack
- Once spent in either context → unavailable until CyberDoc pit stop

A player who burns all their commands escaping a pursuer on the map arrives at a hack battle with nothing. A player who saves everything for Grid-Breach is defenceless on the map.

### CyberDoc Pit Stop
Visiting a CyberDoc node resets the full operational loadout:
1. Full cache wipe
2. All command cooldowns reset
3. Consumables restocked (if purchased)
4. Rig upgrades available

### Command List (v1 — 15 commands)

| Command | Tier | Type | Map Effect | Hack Effect |
|---------|------|------|-----------|-------------|
| **Crash** | 1 | Trap | Place a mine — visitor loses 1 Uplink | Hides opponent's hexakeys for 2 seconds |
| **Signal Noise** | 1 | Stealth | Plants false pings in a nearby district | Adds ghost hexakeys to opponent's board |
| **Firewall Patch** | 1 | Defensive | Boost your Firewall +2 for 3 turns | Shields your hexakeys from disruption for 1 round |
| **Ghost Protocol** | 2 | Stealth | Masks your movement trail for 3 turns | Your hexakey inputs leave no readable trace |
| **Packet Flood** | 2 | Offensive | Drains target Uplink by 2 | Slows opponent's input timer |
| **Decoy** | 2 | Stealth | Plants a fake location ping at any node | Creates a duplicate false hexakey on opponent's board |
| **Dark Mode** | 3 | Stealth | Suppresses all outgoing pings for 2 turns | Blacks out a section of opponent's grid for 3 seconds |
| **Blackout** | 3 | Defensive | Blocks all incoming commands for 2 turns | Immune to opponent command effects for 1 full round |
| **Scramble** | 3 | Offensive | Target can't see node resource values for 2 turns | Shuffles opponent's hexakeys into random order |
| **Trojan** | 4 | Offensive | Embeds — target gains +1 cache per hack for 3 turns | Copies opponent's next hexakey input for your own use |
| **OS Exploit** | 4 | Offensive | Lowers target OS by 2 — pings become more accurate | Reveals opponent's full hexakey sequence for 2 seconds |
| **Buffer Overflow** | 4 | Offensive | Disables one of target's commands for 2 turns | Forces opponent to discard their current hexakey |
| **RootKit** | 5 | Offensive | Steals tech points from target's last node hack | Steals one of opponent's hexakeys for your own set |
| **DDOS** | 5 | Offensive | Locks target out of hacking for 2 turns | Freezes opponent's entire board for 3 seconds |
| **Fork Bomb** | 5 | Offensive | Fills target cache completely — forces retreat | Splits opponent's board into two conflicting states |

### Firewall vs Command Tier

| Attacker Command Tier vs Defender Firewall | Result |
|--------------------------------------------|--------|
| Tier > Firewall by 2+ | Full effect |
| Tier > Firewall by 1 | Reduced effect |
| Tier = Firewall | Partial effect |
| Tier < Firewall | Blocked |

---

## Districts

Districts define ICE difficulty ranges and loot value. Higher-tier districts have higher ICE nodes, richer resources, but are more dangerous to farm on a v1.x rig.

| District | ICE Range | Risk Level |
|----------|-----------|------------|
| Downtown | 2–3 | Low |
| North Spokane | 3–4 | Low–Mid |
| Browne's Addition | 4–5 | Mid |
| University District | 4–5 | Mid |
| Spokane Valley | 5–7 | Mid–High |
| *(future districts)* | 6–9 | High |

---

## UI / SPLICE OS

The game UI is framed as a hacker's operating system called **SPLICE**.

| Element | Description |
|---------|-------------|
| **HUD Bar** | Top strip — Handle, District, Uplink, Cache, Bounty |
| **Hex Map** | The network topology — drag to pan |
| **Side Panel** | Node inspector — always visible, updates on node click |
| **Taskbar** | Bottom OS dock — launches SPLICE browser apps |
| **SPLICE Browser** | In-game browser shell — hosts all app pages and mini-games |

### Browser Pages (SPLICE URLs)

| Page | URL | Contents |
|------|-----|----------|
| Home | `splice://home` | Quick launch tiles |
| Stats | `splice://sys.local/stats` | Live player stats |
| Rig | `splice://sys.local/rig` | Rig info, effective stats, peripherals |
| Commands | `splice://sys.local/commands` | Active and inactive command loadout |
| Inventory | `splice://sys.local/inventory` | Carried items |
| CyberDoc | `splice://cyberdoc.net/shop` | Store — upgrades and consumables |
| DarkNet Feed | `splice://darknet.spk/feed` | City news, breach reports, bounty board |

---

## Open Items / To Design

- [ ] Grid-Breach mini-game mechanics (the core hack mini-game)
- [ ] Full command list with tiers and costs
- [ ] Bounty board system (how bounties are posted and claimed)
- [ ] Full v2.0 rig lineup (Ghost / Breaker / Vault stat blocks)
- [ ] Full v3.0 rig lineup
- [ ] District ICE ratings finalized
- [ ] Node resource values per district
- [ ] PvP combat resolution system
- [ ] Uplink regeneration rules (does it regen over time or only from nodes?)
- [ ] Death / wipe mechanic (what happens when you lose a PvP encounter)
- [ ] Matchmaking / server lobby structure

---

## End-to-End Testing Checklist

Mark tested items with `[X]`. Retest after any related code change.

### Bounty Escalation

- [ ] Hack 10 nodes in one run — ★1 alert fires, HUD ticker shows correctly (e.g. 0/5)
- [ ] Continue to ★2 (15 hacks), ★3 (20), ★4 (25) — each alert fires once, multiplier updates
- [ ] Reach 25 node hacks — Open Season flag flips, all-player notification fires
- [ ] Reach Open Season via PvP route (5 wins while on board) — confirm alternate trigger works
- [ ] ICE ping rings shrink as bounty level rises (★0 wide, ★5 nearly pinpoint)
- [ ] Refresh page mid-run — ticker resumes at correct count, not -10 or 0

### Street Doc Extract / Banking

- [ ] Walk to a Street Doc mid-run — pocket_creds transfer to wallet_creds
- [ ] Bounty resets to ★0 after extract, ticker returns to 0/10
- [ ] `nodes_hacked_this_run` and `pvp_wins_this_run` reset to 0 server-side after extract
- [ ] Visit Street Doc with 0 pocket creds and 0 hacks — no extract fires, no state corruption

### PvP Loot Resolution

- [ ] Win PvP against a player carrying pocket creds — loot transferred to your pocket
- [ ] Lose PvP — pocket creds zero out, bounty resets
- [ ] Steal percentage scales with loser's bounty level (★0 ≈ 10%, ★5 ≈ 75%)
- [ ] Loser enters limp mode after loss

### Bounty Leaderboard

- [ ] Reach ★1 (10 hacks) — handle appears on the bounty board
- [ ] Leaderboard sorts by bounty level, then pvp wins
- [ ] Reset at Street Doc — drops off the leaderboard

### Economy

- [ ] Hack a node — reward lands in pocket_creds, not wallet_creds
- [ ] Bounty multiplier inflates reward at higher star levels (★2 payout > ★0 payout)

### Cache & Uplink

- [ ] Fill cache completely (CPU + RAM hacks) — hack button locks
- [ ] Visit Street Doc — cache clears, hacking unlocks
- [ ] Deplete uplink fully — movement blocks
- [ ] Hack a UPLINK node — uplink refills
- [ ] Hack same node twice before replenish timer — depleted state shows, hack blocked

---

*Last updated: Session 3 — Stat system, cache mechanic, bounty/detection, economy finalized*
