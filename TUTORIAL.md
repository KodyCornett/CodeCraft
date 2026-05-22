# CodeCraft — Tutorial Design Document

## Problem Statement

Testers are confused about how the game works. Key pain points observed:
- Don't know what to do first after logging in
- Don't understand pocket creds vs wallet creds (and lose them)
- Don't understand why bounty escalation is dangerous
- Don't understand the Street Doc / CyberDoc relationship
- Stats and SS degradation are invisible until it's too late
- The SPLICE browser pages exist but players don't find or read them

---

## Core Design Principle

**Teach by consequence, not by lecture.**
Don't explain systems upfront — put the player in a situation where they discover the system by using it. The tutorial should create moments, not walls of text.

**Layer the systems in order of immediacy:**
1. Move → hack → see pocket creds (the core loop)
2. Bank at CyberDoc → feel the pocket→wallet distinction
3. Hack more → watch bounty stars climb → see the ping ring appear
4. Take damage → understand SS and the cost of not banking
5. Everything else (stats, PvP, commands) after the loop is understood

---

## Proposed Format: Street Doc Terminal Quest

The tutorial is delivered as the first quest in the Street Doc terminal system
(to be built). A Street Doc NPC contacts the player on first login and walks
them through the core loop via a series of objectives. Each step has a terminal
message, a concrete action to complete, and a short response when they do it.

The quest is named **GHOST_PROTOCOL_0** — framed as a test job, not a tutorial.
The Street Doc is vetting the new runner before trusting them with real work.

**Why a terminal quest:**
- Fits the cyberpunk aesthetic far better than a SPLICE orientation page
- Teaches by doing — each step has an action, not just text to read
- The terminal + quest format is the foundation for all future Street Doc jobs
- Building this first means the tutorial and the quest system ship together

**Design constraints:**
- No fixed destination nodes. Spawn locations are scattered. All movement
  objectives are action-based ("move to any node"), not destination-based.
- No gating on node type. Can't guarantee nearby node contents. Hack objectives
  work on "any hackable node."
- CyberDoc visit is guided but a purchase is not required — new players may not
  have enough creds yet.

---

## Quest: GHOST_PROTOCOL_0

---

### STEP 1 — First Move

**Objective:** Move to any adjacent node.

**Terminal message:**
```
> INCOMING TRANSMISSION // ENCRYPTED
> SOURCE: STREET_DOC_7 // NODE UNDISCLOSED

New runner on the grid. Good.
Before I give you any real work I need to know
you can handle yourself out there.

First — get moving.
Click any highlighted node on the map and move to it.
Every move costs 1 UPLINK. Don't waste them.

> OBJECTIVE: MOVE TO ANY NODE
```

**Completion trigger:** Player position changes (any move).

**Completion response:**
```
> MOVE CONFIRMED. You're mobile.
```

---

### STEP 2 — Open SPLICE

**Objective:** Open the SPLICE browser.

**Terminal message:**
```
You're going to need intel out there.
SPLICE is your network terminal — reference data,
the store, the darknet feed. Everything runs through it.

Pull it up. Button's in your HUD.

> OBJECTIVE: OPEN SPLICE BROWSER
```

**Completion trigger:** SPLICE browser is opened (any URL).

**Completion response:**
```
> SPLICE ONLINE. Now find your stat reference.
```

---

### STEP 3 — Read the Stat Reference (ICE vs CPU)

**Objective:** Navigate to `splice://sys.local/guide/stats`.

**Terminal message:**
```
Every node on the map has an ICE rating.
Your CPU has to be close to that rating or you can't crack it.
Too big a gap and the node locks you out entirely.

Open your stat reference. Find the ICE vs CPU table.
Learn the gap rules before you touch anything.

  splice://sys.local/guide/stats

> OBJECTIVE: VISIT STAT REFERENCE
```

**Completion trigger:** Player navigates to `splice://sys.local/guide/stats`.

**Completion response (delivered after SPLICE is closed):**
```
> CONFIRMED. You know your limits.
> Check the node ICE rating before you commit.
> A node you can't crack will drain your SS for nothing.
```

*Note: The ICE vs CPU table is already in SysStatGuide.vue. No new content
needed — the quest just directs players there.*

---

### STEP 4 — Check the Sys Panel

**Objective:** View the rig/stats panel in the side panel.

**Terminal message:**
```
Before you hack anything, know what you're running.

Open your sys panel — the tab on the right side of the screen.
Find your CPU and your Firewall.

CPU tells you what nodes you can hit.
Firewall tells you how much punishment you can take before
your rig starts falling apart.

> OBJECTIVE: VIEW SYS PANEL
```

**Completion trigger:** Player opens the rig/stats view in the side panel.

**Completion response:**
```
> STATS CONFIRMED. You know what you're working with.
> Starter chassis. It'll do for now.
> Upgrade path opens up once you've got creds and tech points.
```

---

### STEP 5 — First Hack

**Objective:** Complete a hack on any node.

**Terminal message:**
```
Time to earn. Find a node and crack it.

Check the node's ICE rating first — compare it to your CPU.
If the gap is too wide, pick a different node.

Everything you earn goes into your pocket, not your wallet.
Pocket creds are at risk. They're gone if someone drops you.
Don't forget that.

> OBJECTIVE: COMPLETE A HACK
```

**Completion trigger:** Player completes a Grid-Breach (win or lose).
Quest does not advance on a loss — player must win at least once.

**Completion response (win):**
```
> BREACH SUCCESSFUL. Pocket creds added.
> That's how you eat out here.
```

**Completion response (loss):**
```
> TRACE CAUGHT YOU. SS took damage.
> That's what Firewall is for.
> Find a lower ICE node and try again.
```

---

### STEP 6 — Visit the CyberDoc Store

**Objective:** Navigate to `splice://cyberdoc.net/shop`.

**Terminal message:**
```
Last stop. You need to know where your home base is.

CyberDoc is where you bank your pocket creds, repair your SS,
and upgrade your rig. You don't survive long without it.

Open SPLICE and pull up the store. Have a look around.
You don't need to buy anything yet.

  splice://cyberdoc.net/shop

> OBJECTIVE: VISIT CYBERDOC STORE
```

**Completion trigger:** Player navigates to `splice://cyberdoc.net/shop`.

**Completion response (delivered after SPLICE is closed):**
```
> LOCATION CONFIRMED.

> That's where you bank your creds before someone takes them.
> Repair there. Upgrade there. Don't stay out too long without it.

> You're ready.
> GHOST_PROTOCOL_0 — COMPLETE

> Payment deposited: 200₡ — WALLET
> Awaiting next transmission...
```

---

## Quest Reward

| Reward | Detail |
|---|---|
| 200₡ wallet creds | Deposited directly to wallet (safe, spendable immediately) |
| Bounty reset | Clears any heat accumulated during the tutorial run |

Wallet deposit (not pocket) so the reward is safe and the player
can spend it at the CyberDoc store right away.

---

## What This Quest Teaches

| Concept | Step |
|---|---|
| Movement costs uplink | 1 |
| SPLICE browser exists and how to open it | 2 |
| ICE vs CPU — node difficulty and lockout rules | 3 |
| Sys panel shows live rig stats | 4 |
| CPU determines what you can hack | 4 |
| Firewall determines damage taken | 4 |
| Pocket creds are at risk; wallet is safe | 5 |
| Grid-Breach is how hacking works | 5 |
| CyberDoc is where you bank, repair, upgrade | 6 |

---

## What Needs to Be Built

This plan assumes these systems do not yet exist:

1. **Street Doc terminal component** — the panel UI where quest messages appear
   and objectives are tracked. Likely a new side panel tab or overlay.

2. **Quest state system** — tracks current step per player. Persisted
   server-side (new `player_quests` table or a `tutorial_step` column on
   Player). Fires completion triggers when relevant game events occur.

3. **First-login detection** — triggers GHOST_PROTOCOL_0 automatically on
   first login (e.g. `tutorial_complete` flag on the Player model).

4. **Completion event hooks** — each step needs a listener:
   - Step 1: player position change
   - Step 2: SPLICE open event
   - Step 3: SPLICE navigation to stat guide URL
   - Step 4: sys panel opened
   - Step 5: Grid-Breach win event
   - Step 6: SPLICE navigation to CyberDoc URL

5. **Wallet reward on completion** — server endpoint that marks quest complete
   and deposits reward directly to `wallet_creds`, bypassing pocket.

---

## Suggested Build Order

1. Street Doc terminal UI (display only — hardcoded first message to start)
2. First-login flag + quest state migration
3. Step 1–2 triggers (move + SPLICE open — lowest friction)
4. Step 3–4 triggers (URL navigation + panel open)
5. Step 5 trigger (hook into existing Grid-Breach result flow)
6. Step 6 trigger + quest completion + wallet reward deposit

---

## Previous Format Considered (Archived)

An earlier version of this doc proposed a SPLICE browser page
(`splice://sys.local/tutorial`) as the tutorial format. That approach was
dropped in favor of the terminal quest system because:
- A static page requires players to read before they do anything
- The quest format teaches the same content through action
- The terminal system will serve all future Street Doc quests, not just
  the tutorial — so the build cost pays off immediately

---

## Open Questions / Ideas to Discuss

- [ ] Should the tutorial be skippable for returning players?
- [ ] Auto-route new players to SPLICE tutorial on first login, or show a dismissable overlay?
- [ ] Should there be a dedicated "tutorial node" on the map that walks through hacking step by step?
- [ ] Animated ping ring demo on the bounty section — is this feasible in the SPLICE browser?
- [ ] Should the BootSequence (first login) reference the tutorial explicitly?
- [ ] Tooltip system on the HUD? (hover over SS bar = explanation, hover over pocket creds = "these are at risk" warning)
- [ ] First-hack reward? Give new players a small cred bonus after their first successful hack to make the loop satisfying immediately

---

## What the Existing SPLICE Pages Cover (Don't Duplicate)

- SysStats — full stat breakdown
- SysCommands / SysCommandCatalog — command reference
- HowToPlay — exists but players aren't finding it
- GridBreach guide — combat mechanics

The tutorial should reference these pages, not rewrite them.

---

## Notes

- Keep all tutorial text in the cyberpunk voice — no "welcome to the game!" energy
- Every section should end with an action, not just information
- The bounty + banking loop is the #1 thing players need to understand — weight the tutorial toward that
