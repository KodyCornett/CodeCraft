# CodeCraft UI Build V2
> Hand this file to Claude at the start of each session.
> Each prompt is self-contained. Work top to bottom.
> Check off each prompt in CLAUDE.md as it completes.

---

## Context — What V1 Built

V1 delivered the core game loop visuals:
- MapCanvas with D3 force graph, sonar player marker, movement animation
- HUD with live stat flashes and current-node interface section
- NodeWindow click-to-open interaction panel
- BubblePop + MemoryGame + HackResult mini game pipeline
- BootSequence, OpenSeasonNotification
- ScanArea (bounty board + Hall of Infamy — fully wired)
- CombatOverlay / BattleshipBoard / CommandPanel / TraceBar (fully wired)
- StreetDocMenu + all four sub-panels (fully wired)

**What is still stubbed:**
- `panels/PlayerStats.vue` — placeholder only
- `panels/RigInfo.vue` — placeholder only
- `panels/Commands.vue` — placeholder only
- `panels/Inventory.vue` — placeholder only

Ping markers are referenced (`PingMarker.js`) but never rendered on the map.

---

## Strict Rules (Never Violate)

- All panels receive `player` prop (the `mockPlayer` ref from Game.vue).
- All panels receive `rig` prop (mock rig object defined in Game.vue — add it there).
- Panels emit `close`. Nothing else.
- No API calls inside panels unless explicitly listed. Use props for display data.
- Do NOT modify MapCanvas.vue, HUD.vue, or Game.vue template structure except where a prompt explicitly says to.
- Always use `JetBrains Mono` monospace, cyan (`#00FFFF`) primary, amber (`#FFB300`) accent for costs/warnings, magenta (`#FF00CC`) for records/PvP, red (`#FF3333`) for damage/danger.
- Panel drawer is `width: 320px` — design for that width.

---

## Mock Data Shape (add to Game.vue before Prompt 1)

Before building any panel, add this mock rig to `Game.vue` alongside `mockPlayer`:

```js
const mockRig = ref({
    chassis: 'GHOST FRAME Mk.II',
    portSlots: 4,
    pointCap: 20,
    os:       4,
    ram:      3,
    cpu:      4,
    storage:  3,
    firewall: 3,
    currentSS:  75,
    maxSS:      100,
    isLimping:  false,
    peripherals: [
        { id: 'p1', name: 'RAM Overdrive',    stat: 'ram',      boost: 2, installed: true,  damaged: false },
        { id: 'p2', name: 'Firewall Patch',   stat: 'firewall', boost: 1, installed: true,  damaged: false },
        { id: 'p3', name: 'Storage Vault',    stat: 'storage',  boost: 1, installed: false, damaged: false },
        { id: 'p4', name: 'Cracked CPU Chip', stat: 'cpu',      boost: 3, installed: true,  damaged: true  },
    ],
});

const mockCommands = ref([
    { id: 'c1',  name: 'Databomb',       type: 'offensive', description: 'Plants a false hit marker with a subtle visual tell.', active: true  },
    { id: 'c2',  name: 'Overload',       type: 'offensive', description: 'Displaces opponent\'s next shot randomly near their target.', active: true  },
    { id: 'c3',  name: 'Crash',          type: 'offensive', description: 'Scans a row — green if ship present, red if clear.', active: true  },
    { id: 'c4',  name: 'Trojan',         type: 'offensive', description: 'Auto-triggers negative effect when the Trojan ship is sunk.', active: false },
    { id: 'c5',  name: 'Exploit',        type: 'offensive', description: 'Free retaliatory shot when opponent hits your ship.', active: false },
    { id: 'c6',  name: 'Packet Flood',   type: 'offensive', description: 'Opponent\'s next 3 shots each have a 5-second timer.', active: false },
    { id: 'c7',  name: 'Scramble',       type: 'offensive', description: 'Wipes opponent\'s shot history markers for up to 3 turns.', active: false },
    { id: 'c8',  name: 'SQL_Injection',  type: 'offensive', description: 'First hit on a chosen 3+ square ship reads as a miss.', active: false },
    { id: 'c9',  name: 'Fork Bomb',      type: 'offensive', description: 'If initial shot hits, a second hit fires on an adjacent square.', active: false },
    { id: 'c10', name: 'DDOS_FOG',       type: 'offensive', description: 'Blocks a 2×2 area for 2 turns. No marker left behind.', active: false },
    { id: 'c11', name: 'RootKit',        type: 'offensive', description: 'Steal 1 use of an unused command if you sink a ship.', active: false },
    { id: 'c12', name: 'Buffer Overflow',type: 'offensive', description: 'Silently disables one of opponent\'s unused commands for its next use.', active: false },
    { id: 'c13', name: 'Blackout',       type: 'defensive', description: 'Silences all incoming offensive commands for 3 turns.', active: true  },
]);

const mockInventory = ref([
    { id: 'i1', name: 'RAM Overdrive Mk.II', stat: 'ram',      boost: 3, rarity: 'rare',     installed: false },
    { id: 'i2', name: 'CPU Overclock',       stat: 'cpu',      boost: 2, rarity: 'uncommon', installed: false },
    { id: 'i3', name: 'OS Hardened Kernel',  stat: 'os',       boost: 1, rarity: 'common',   installed: false },
]);
```

Pass these as props on `PlayerStats`, `RigInfo`, `Commands`, and `Inventory` components inside `panelComponents` — but first update those panels to accept the props (each prompt handles its own component).

In `Game.vue` template, update the panel component rendering to pass all four props:
```html
<component :is="panelComponents[activePanel]"
    :player="mockPlayer"
    :rig="mockRig"
    :commands="mockCommands"
    :inventory="mockInventory"
    @close="closePanel"
/>
```

---

## PROMPT 1 — PlayerStats Panel

**File:** `resources/js/components/panels/PlayerStats.vue`

**What it shows:** Full snapshot of the player's current run state. This is the "who am I right now" panel.

**Layout (top to bottom):**

```
[PLAYER_STATS]                      ← header

HANDLE        KODY_REF              ← amber label, cyan value
DISTRICT      DOWNTOWN
STATUS        ACTIVE  ← green; or  LIMP MODE  ← red pulsing if isLimping

────────────────────────────────────

[SYSTEM STABILITY]
████████████░░░░░░  75 / 100         ← SSBar component, exact same as HUD

────────────────────────────────────

[RESOURCES]
CPU CYCLES    ██████░░░░  8 / 10     ← inline bar, cyan fill
CREDS         ₢ 12,500

────────────────────────────────────

[RUN STATS]
NODES HACKED  0
PVP WINS      0
BOUNTY LVL    0
MULTIPLIER    1.00×

────────────────────────────────────

[THREAT STATUS]
BOUNTY BOARD  INACTIVE  ← gray when inactive
OPEN SEASON   INACTIVE  ← gray; red pulsing when active, shows ⚡ prefix
LAST ST.DOC   MONROE UNDERGROUND  ← or 'NONE' if null

────────────────────────────────────

[CLOSE]
```

**Props:**
```js
defineProps({
    player: { type: Object, required: true },
    rig:    { type: Object, required: true },
});
defineEmits(['close']);
```

**Computed values needed:**
- `statusLabel` / `statusClass`: 'ACTIVE' (green) vs 'LIMP MODE' (red, CSS pulse animation)
- `cpuPct`: `(player.cpuCycles / player.maxCpu) * 100`
- `bountyActive`: `player.bountyLevel >= 15`
- `osActive`: `player.isOpenSeason`
- Formatted creds: `₢ ` + `toLocaleString('en-US')`

**Styling notes:**
- Section dividers: `border-top: 1px solid rgba(0,255,255,0.12)` with `padding-top: 12px; margin-top: 12px`
- Section headers: `font-size: 9px; color: rgba(0,255,255,0.35); letter-spacing: 0.08em; margin-bottom: 8px`
- Label/value rows: `display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 5px`
- Labels: `color: rgba(0,255,255,0.45)`; values: `color: #00FFFF`
- CPU inline bar: `height: 4px`, container `background: rgba(0,255,255,0.12)`, fill `background: #00FFFF`, same pattern as HUD SS bar but inline and smaller
- LIMP MODE: `color: #FF3333` + `@keyframes limp-pulse { 0%,100% { opacity:1 } 50% { opacity:0.4 } }` 1.5s infinite
- OPEN SEASON active: `color: #FF3333`, text `⚡ ACTIVE`, same pulse animation
- BOUNTY BOARD active: `color: #FFB300`, text `TARGET MARKED`

---

## PROMPT 2 — RigInfo Panel

**File:** `resources/js/components/panels/RigInfo.vue`

**What it shows:** Chassis hardware, five stats with level bars, peripheral breakdown, ring tax warning.

**Layout (top to bottom):**

```
[RIG_INFO]                          ← header

CHASSIS       GHOST FRAME Mk.II     ← amber label, cyan value
PORT SLOTS    2 / 4 USED
POINT CAP     17 / 20 USED          ← sum of all stat levels vs pointCap

────────────────────────────────────

[STAT LEVELS]

OS        ████████░░  LVL 04   → Max SS +40
RAM       ██████░░░░  LVL 03   → Loadout slots: 4
CPU       ████████░░  LVL 04   → Move pts: 5
STORAGE   ██████░░░░  LVL 03   → Loot slots: 4
FIREWALL  ██████░░░░  LVL 03   → Ping −45s

  ← if at point cap, show amber ring tax warning below table:
  ⚠ CHASSIS AT CAP — UPGRADES WILL TAX NEIGHBOR STAT

────────────────────────────────────

[PERIPHERALS]

▸ RAM Overdrive        +2 RAM    [INSTALLED]  ← cyan
▸ Firewall Patch       +1 FW     [INSTALLED]
▸ Cracked CPU Chip     +3 CPU    [DAMAGED]    ← red, no boost
▸ Storage Vault        +1 STG    [INVENTORY]  ← gray, not installed

  Effective boosts: RAM +2 · FW +1
  (damaged/uninstalled excluded)

────────────────────────────────────

[CLOSE]
```

**Props:**
```js
defineProps({
    player: { type: Object, required: true },
    rig:    { type: Object, required: true },
});
defineEmits(['close']);
```

**Stat effect descriptions (hardcoded):**
```js
const STAT_EFFECTS = {
    os:       rig => `Max SS +${rig.os * 10}`,
    ram:      rig => `Loadout slots: ${rig.ram + 1}`,
    cpu:      rig => `Move pts: ${rig.cpu + 1}`,
    storage:  rig => `Loot slots: ${rig.storage + 1}`,
    firewall: rig => `Ping −${rig.firewall * 15}s`,
};
```

**Computed values needed:**
- `totalPoints`: sum of os+ram+cpu+storage+firewall
- `atCap`: `totalPoints >= rig.pointCap`
- `portSlotsUsed`: count of `rig.peripherals.filter(p => p.installed)`
- `effectiveBoosts`: object `{ os:0, ram:0, cpu:0, storage:0, firewall:0 }` — only sum peripherals where `installed && !damaged`
- `effectiveBoostSummary`: string showing non-zero boosts e.g. "RAM +2 · FW +1", or "NONE" if all zero

**Styling notes:**
- Stat level bars: 10-segment pip bars. Render 10 `span.pip` elements, filled if `index < rig[stat]`, color `#00FFFF`, empty `rgba(0,255,255,0.12)`. Each pip `width:14px height:6px display:inline-block margin-right:2px`.
- Peripheral rows: `▸` prefix, name left, `+N STAT` center (color cyan), status right
  - `[INSTALLED]` → `color: #00FF88`
  - `[DAMAGED]` → `color: #FF3333`
  - `[INVENTORY]` → `color: rgba(0,255,255,0.3)`
- At-cap warning: amber `#FFB300`, `font-size: 9px`, `⚠` prefix
- Effective boost summary: `font-size: 9px; color: rgba(0,255,255,0.5)` line below peripheral list

---

## PROMPT 3 — Commands Panel

**File:** `resources/js/components/panels/Commands.vue`

**What it shows:** The player's command library. Active loadout (up to RAM+1 slots) is highlighted. Inactive commands are owned but not in the current loadout. All locked until next Street Doc.

**Layout (top to bottom):**

```
[COMMANDS]                          ← header

LOADOUT SLOTS   3 / 4 ACTIVE        ← RAM+1 slots, cyan
LOCKED          UNTIL STREET DOC    ← amber, always shown

────────────────────────────────────

[ACTIVE LOADOUT]

▶ [DATABOMB]      OFFENSIVE   ← cyan border, active state
▶ [OVERLOAD]      OFFENSIVE
▶ [BLACKOUT]      DEFENSIVE   ← magenta accent for defensive
  [SLOT 4]        — EMPTY —   ← gray empty slot

────────────────────────────────────

[FULL LIBRARY]

  [CRASH]           OFF   ← not in loadout, dimmed
  [TROJAN]          OFF
  [EXPLOIT]         OFF
  [PACKET FLOOD]    OFF
  [SCRAMBLE]        OFF
  [SQL_INJECTION]   OFF
  [FORK BOMB]       OFF
  [DDOS_FOG]        OFF
  [ROOTKIT]         OFF
  [BUFFER OVERFLOW] OFF

────────────────────────────────────

Clicking any command (active or library) expands
an inline detail block showing the description.
Only one command can be expanded at a time.

[CLOSE]
```

**Props:**
```js
defineProps({
    player:   { type: Object, required: true },
    rig:      { type: Object, required: true },
    commands: { type: Array,  required: true },
});
defineEmits(['close']);
```

**Computed values needed:**
- `maxSlots`: `(rig.ram ?? 3) + 1`
- `activeCommands`: `commands.filter(c => c.active)`
- `inactiveCommands`: `commands.filter(c => !c.active)`
- `emptySlots`: `Math.max(0, maxSlots - activeCommands.length)` — rendered as `[SLOT N] — EMPTY —`
- `expandedId`: local `ref(null)` — toggles on click

**Styling notes:**
- Active loadout commands: `border: 1px solid rgba(0,255,255,0.35)`, `background: rgba(0,255,255,0.04)`, `color: #00FFFF`, `▶` prefix
- Active defensive commands: `border-color: rgba(255,0,204,0.35)`, `background: rgba(255,0,204,0.04)`, `color: #FF00CC`
- Empty slots: `color: rgba(0,255,255,0.2)`, dashed border `border: 1px dashed rgba(0,255,255,0.12)`
- Library commands (inactive): `color: rgba(0,255,255,0.4)`, no border, hover brightens
- `[OFF]` / `[DEF]` type badges: `font-size: 8px`, right-aligned, OFF cyan-dim, DEF magenta-dim
- Expanded description block: slides down inline under the command row, `font-size: 10px`, `color: rgba(0,255,255,0.6)`, `padding: 6px 10px`, `border-left: 2px solid rgba(0,255,255,0.2)`
- "LOCKED UNTIL STREET DOC" bar: amber background `rgba(255,179,0,0.08)`, amber border-left `3px solid #FFB300`, `padding: 6px 10px`, `font-size: 9px`

---

## PROMPT 4 — Inventory Panel

**File:** `resources/js/components/panels/Inventory.vue`

**What it shows:** Loot the player is carrying — hardware encrypts (uninstalled peripherals) and a summary of what's already installed. Install happens at Street Doc, not here — this is read-only.

**Layout (top to bottom):**

```
[INVENTORY]                         ← header

LOOT SLOTS    3 / 4 USED            ← Storage+1 = capacity; count of inventory items
PORT SLOTS    2 / 4 USED            ← from rig

────────────────────────────────────

[HARDWARE ENCRYPTS — CARRY]

  ▸ RAM Overdrive Mk.II   +3 RAM   [RARE]       ← rarity color
  ▸ CPU Overclock         +2 CPU   [UNCOMMON]
  ▸ OS Hardened Kernel    +1 OS    [COMMON]

  Install at next Street Doc to activate.

────────────────────────────────────

[INSTALLED PERIPHERALS]

  ▸ RAM Overdrive         +2 RAM   [BONDED]     ← cannot be stolen
  ▸ Firewall Patch        +1 FW    [BONDED]
  ▸ Cracked CPU Chip      +3 CPU   [DAMAGED]    ← red

────────────────────────────────────

  Empty inventory message if carry list is empty:
  // INVENTORY CLEAR — NO HARDWARE ENCRYPTS

[CLOSE]
```

**Props:**
```js
defineProps({
    player:    { type: Object, required: true },
    rig:       { type: Object, required: true },
    inventory: { type: Array,  required: true },
});
defineEmits(['close']);
```

**Computed values needed:**
- `lootCapacity`: `(rig.storage ?? 3) + 1`
- `portSlotsUsed`: `rig.peripherals.filter(p => p.installed).length`
- `installedPeripherals`: `rig.peripherals.filter(p => p.installed)`
- `slotsUsedPct`: for the capacity bar / warning if `inventory.length >= lootCapacity`

**Rarity colors:**
```js
const RARITY_COLOR = {
    common:    'rgba(0,255,255,0.45)',
    uncommon:  '#00FF88',
    rare:      '#8B5CF6',
    legendary: '#FFB300',
};
```

**Styling notes:**
- Carry items: `▸` prefix, name left, `+N STAT` center cyan, rarity badge right in rarity color
- `[BONDED]` status: `color: rgba(0,255,255,0.5); font-size: 9px`
- `[DAMAGED]` status: `color: #FF3333`
- "Install at next Street Doc" footer note: `font-size: 9px; color: rgba(0,255,255,0.3); font-style: italic; margin-top: 6px`
- If inventory is empty, show centered `// INVENTORY CLEAR — NO HARDWARE ENCRYPTS` in dim cyan
- Slot usage warning if at capacity: amber line `⚠ LOOT SLOTS FULL — ITEMS WILL BE LOST ON NEXT HACK`

---

## PROMPT 5 — Ping Marker System (Map Layer)

**File:** `resources/js/components/map/PingMarker.js` (already exists — wire it up)

**Context:** `PingMarker.js` exists but is never called. `Game.vue` has a `pings` ref that gets populated by `PLAYER_MOVED` WebSocket events. `MapCanvas.vue` receives `:pings="pings"` as a prop but never uses it.

**Task:**

1. Read `PingMarker.js` in full. If it only has a stub, implement it. If it has a real implementation, wire it.

2. `PingMarker.js` should export a single function:
```js
export function renderPings(d3, svgGroup, pings, nodePositions) { ... }
```
- `pings`: array of `{ pingId, nodeId, playerId, createdAt, expiresAt, isLoud }` objects
- `nodePositions`: `Map<nodeId, { x, y }>` — D3 simulation node positions
- Renders a `circle.ping-marker` per ping at the node's SVG position
- Uses `pingId` as the D3 key so enter/exit animate correctly
- Normal ping: `r=10`, `stroke: rgba(0,255,255,0.5)`, `fill: none`, `stroke-width: 1.5`
- Loud ping (cloak expire / bounty): `r=14`, `stroke: rgba(255,51,51,0.7)`, `fill: rgba(255,51,51,0.05)`
- All pings pulse via CSS: `@keyframes ping-expand { 0% { r:8; opacity:0.8 } 100% { r:22; opacity:0 } }` — use a `<circle class="ping-ring">` sibling that animates outward over 2s, `animation-iteration-count: infinite`
- Pings are positioned offset from the node center (e.g. +18px right +6px up) so they don't overlap the node itself

3. In `MapCanvas.vue`:
- Import `renderPings` from `PingMarker.js`
- Add a `pingGroup` D3 selection created in `onMounted` under the links layer (so it renders below nodes)
- Build a `nodePositionMap` computed from the current simulation nodes
- Call `renderPings(d3, pingGroup, props.pings, nodePositionMap)` inside a `watch` on `props.pings`
- Also call it when simulation ticks so pings follow node positions if the graph is still settling

4. In `Game.vue`, add 2 mock pings to the initial `pings` ref so they're visible immediately without needing a WebSocket:
```js
const pings = ref([
    { pingId: 'mock-1', nodeId: 'a3', playerId: 'other-1', createdAt: Date.now(), expiresAt: Date.now() + 300000, isLoud: false },
    { pingId: 'mock-2', nodeId: 'b2', playerId: 'other-2', createdAt: Date.now(), expiresAt: Date.now() + 300000, isLoud: true  },
]);
```

**Verify:** Two ping rings visible on the map at nodes a3 (cyan) and b2 (red loud), both pulsing outward continuously.

---

## Build Checklist

- [ ] Mock data wired in Game.vue (mockRig, mockCommands, mockInventory; component prop binding updated)
- [ ] PROMPT 1 — PlayerStats panel
- [ ] PROMPT 2 — RigInfo panel
- [ ] PROMPT 3 — Commands panel
- [ ] PROMPT 4 — Inventory panel
- [ ] PROMPT 5 — Ping Marker System
