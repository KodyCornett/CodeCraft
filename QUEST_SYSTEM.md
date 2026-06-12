# CodeCraft — Quest & Reputation System

This document covers the quest engine end-to-end: schema, services, API, frontend,
and exactly where to add new quest content when you're ready to plug it in.

---

## Overview

The quest system is content-driven. The engine reads quest definitions from the
database and tracks each player's progress independently. Adding new quests means
adding data — no new code is required once the engine is in place.

Five CyberDocs each run their own quest arc series. Players begin with Knuckle
(Browne's Addition) and unlock the others by visiting, through forced referrals, or
simply by travelling to their district. All arcs run in parallel.

Rep is per-player, per-doc, and only ever goes up.

---

## Database Schema

### `quest_arcs`

One row per quest arc. Arcs belong to a CyberDoc and are ordered within that doc.

| Column           | Type    | Notes |
|------------------|---------|-------|
| id               | UUID PK | |
| cyber_doc_id     | UUID FK | → `cyber_docs.id` |
| sequence_order   | TINYINT | Order within this doc's arcs (1-based) |
| title            | STRING  | Short arc name shown in the log |
| rep_required     | INT     | Rep threshold to unlock this arc |
| is_entry_arc     | BOOL    | `true` = auto-unlocks for all players on first Knuckle visit |

### `quest_stages`

One row per stage within an arc. Stages are ordered by `stage_number`.

| Column           | Type    | Notes |
|------------------|---------|-------|
| id               | UUID PK | |
| quest_arc_id     | UUID FK | → `quest_arcs.id` |
| stage_number     | TINYINT | Order within the arc (1-based) |
| title            | STRING  | Short label shown in the log header |
| objective_text   | TEXT    | Full briefing shown in the log body |
| rep_reward       | INT     | Rep granted to the owning doc on completion |
| is_branch        | BOOL    | `true` = player chooses which doc to turn the job into |
| branch_options   | JSON    | Array of `{cyber_doc_name, label, rep_reward}`. Only used when `is_branch = true` |
| referral_doc_id  | UUID FK | If set, completing this stage introduces the player to another doc |
| referral_text    | STRING  | Line shown in that doc's log section before first visit |

### `player_reputation`

One row per player per doc.

| Column       | Type    | Notes |
|--------------|---------|-------|
| id           | UUID PK | |
| player_id    | UUID FK | → `players.id` |
| cyber_doc_id | UUID FK | → `cyber_docs.id` |
| score        | INT     | Monotonically increasing. Default 0. |

Unique constraint: `(player_id, cyber_doc_id)`.

### `player_arc_progress`

Tracks each player's relationship to each arc.

| Column        | Type     | Notes |
|---------------|----------|-------|
| id            | UUID PK  | |
| player_id     | UUID FK  | → `players.id` |
| quest_arc_id  | UUID FK  | → `quest_arcs.id` |
| status        | STRING   | `locked` / `active` / `complete` |
| unlocked_at   | DATETIME | Set when status transitions to `active` |
| completed_at  | DATETIME | Set when all stages are complete |

Unique constraint: `(player_id, quest_arc_id)`.

### `player_stage_progress`

Tracks each player's position within each stage.

| Column              | Type     | Notes |
|---------------------|----------|-------|
| id                  | UUID PK  | |
| player_id           | UUID FK  | → `players.id` |
| quest_stage_id      | UUID FK  | → `quest_stages.id` |
| status              | STRING   | `locked` / `active` / `complete` |
| turned_into_doc_id  | UUID FK  | For branch stages — which doc the player chose |
| completed_at        | DATETIME | |

Unique constraint: `(player_id, quest_stage_id)`.

---

## Reputation Tiers

| Tier      | Min Score | Label     |
|-----------|-----------|-----------|
| Tier 0    | 0         | NULL      |
| Tier 1    | 250       | RESOLVED  |
| Tier 2    | 600       | ROUTED    |
| Tier 3    | 1200      | ENCRYPTED |
| Tier 4    | 2000      | ROOT      |

Rep tier thresholds are defined as constants in `ReputationService::TIERS`.

---

## Rep-Granting Events

Rep is granted by calling `ReputationService::grantRep($player, $cyberDocId, $amount)`.
The following events are hooked:

| Event                        | Where hooked                       | Amount |
|------------------------------|------------------------------------|--------|
| CyberDoc visit               | `CyberDocController::visit()`      | Triggers `initArcForDoc` (no rep) |
| Bounty extract (bank)        | `CyberDocController::bank()`       | `bounty_level × 40` rep to the doc the player is currently at |
| Quest stage completion       | `QuestController::completeStage()` | Per-stage `rep_reward` field |
| Node hack in district        | Hook in `NodeController` (future)  | 5 per hack |
| PvP win in district          | Hook in `CombatController` (future)| 25 per win |
| Packet Hijack win in district| Hook in `PacketHijackController` (future) | 20 per win |
| Store purchase               | Hook in `StoreController` (future) | 3 per purchase |

The four "future" hooks are the next integration points. Each calls:
```php
$this->reputationService->grantRep($player, $cyberDoc->id, ReputationService::REP_EVENTS['event_key']);
```

---

## Services

### `ReputationService` — `app/Services/ReputationService.php`

| Method | Description |
|--------|-------------|
| `grantRep($player, $cyberDocId, $amount)` | Add rep to a player for a doc. Never decrements. |
| `grantBountyExtractRep($player, $cyberDocId, $bountyLevel)` | Shorthand for bounty extract. Pass the pre-bank bounty level. |
| `getScore($player, $cyberDocId)` | Returns current rep score (0 if no record). |
| `getTierIndex($score)` | Returns tier 0–4 for a score. |
| `getRepLabel($score)` | Returns the string tier label (e.g. `'ROUTED'`). |
| `getNextThreshold($score)` | Returns the next tier min score, or `null` if ROOT. |
| `getRepStateForPlayer($player)` | Returns full rep state for all docs, keyed by `cyber_doc_id`. |

### `QuestService` — `app/Services/QuestService.php`

| Method | Description |
|--------|-------------|
| `getPlayerQuestState($player)` | Full quest + rep state payload for the Splice terminal. |
| `initArcForDoc($player, $cyberDoc)` | Creates arc/stage progress rows on first visit. Entry arcs unlock immediately; others are `locked` until rep threshold is crossed. |
| `completeStage($player, $stageId, $turnedIntoDocId)` | Marks a stage complete, routes rep, activates the next stage, fires referral if set. Returns a summary of what changed. |
| `checkAndUnlockArcs($player)` | Sweeps all locked arcs and unlocks any whose rep threshold is now met. Called automatically after every `completeStage`. Returns array of newly unlocked arc IDs. |

---

## API Endpoints

All endpoints are under the authenticated `/api` group (Sanctum session).

| Method | Path | Description |
|--------|------|-------------|
| `GET`  | `/api/quests` | Full quest + rep state for the player. Consumed by `useQuestLog.js`. |
| `POST` | `/api/quests/stage/{stageId}/complete` | Complete a stage. For branch stages pass `{ turned_into_doc_id: uuid }` in the body. |

---

## Frontend

### Composable — `resources/js/composables/useQuestLog.js`

```js
const { docs, loading, error, fetchQuestLog, completeStage } = useQuestLog();
```

- `docs` — reactive array of doc objects (see shape below)
- `fetchQuestLog()` — fetches `GET /api/quests`
- `completeStage(stageId, turnedIntoDocId?)` — posts completion, then re-fetches

### Quest Log Terminal — `resources/js/components/browser/pages/QuestLog.vue`

Splice route: `splice://sys.local/terminal`

The NavBar TERMINAL button opens this page via `SPLICE.TERMINAL`.

Features:
- One collapsible section per doc
- Rep bar with tier label and score
- Next tier threshold displayed
- Arc and stage statuses with icons (`►` active, `✓` complete, `░` locked)
- Branch options rendered as buttons when a stage is active and `is_branch = true`
- Referral pending message for docs the player has been introduced to but not yet visited
- Collapsed and dimmed for docs the player has not encountered

### SPLICE constant — `SpliceRouter.js`

```js
SPLICE.TERMINAL  →  'splice://sys.local/terminal'   // Quest Log
SPLICE.TUTORIAL  →  'splice://sys.local/tutorial'   // Ghost Protocol 0 (unchanged)
```

---

## CyberDoc Integration

Every `POST /api/cyberdoc/visit` triggers `QuestService::initArcForDoc()` for the
doc at the player's current node. This is idempotent — visiting the same doc a second
time does nothing.

Every `POST /api/cyberdoc/bank` grants bounty-extract rep to the doc the player is
currently at (pre-bank bounty level × 40).

The `MISSION LOG →` button in the CyberDoc store page navigates to
`splice://sys.local/terminal` so the player can check their objectives immediately
after visiting.

---

## Adding Quest Content

### 1. Add a new arc to an existing doc

In `QuestArcSeeder.php`, add a new entry to the `ARCS` constant under the canvas_id:

```php
'BA-hub' => [   // Knuckle
    // existing arc...
    [
        'sequence_order' => 2,
        'title'          => 'Arc 2: The Second Job',
        'rep_required'   => 600,   // ROUTED tier
        'is_entry_arc'   => false,
    ],
],
```

Then run: `php artisan db:seed --class=QuestArcSeeder`

### 2. Add stages to an arc

In `QuestStageSeeder.php`, add an entry to `STAGES` using `"CANVAS_ID|arc_sequence_order"` as the key:

```php
'BA-hub|2' => [   // Knuckle Arc 2
    [
        'stage_number'       => 1,
        'title'              => 'Stage 1: The Drop',
        'objective_text'     => 'Knuckle needs a package moved. No questions.',
        'rep_reward'         => 150,
        'is_branch'          => false,
        'branch_options'     => null,
        'referral_canvas_id' => null,      // or 'DT-hub' to introduce Veil
        'referral_text'      => null,      // or "Knuckle says Veil has been asking about you."
        // Rewards (omit or set to 0/null if not used)
        'reward_creds'       => 200,       // wallet creds
        'reward_tech_points' => 1.5,       // tech points
        'reward_node_access' => null,      // canvas_id to unlock
        'reward_lore_key'    => null,      // Splice page key
        // Trigger (omit if stage has no map or minigame requirement)
        'node_canvas_id'     => 'BA-hub',  // player must be at this node
        'minigame_type'      => null,      // null | 'data_grab' | 'system_override'
    ],
],
```

Then run: `php artisan db:seed --class=QuestStageSeeder`

### 3. Add a branch stage

A branch stage lets the player choose which doc to turn the job into.
The chosen doc gets the rep reward. The quest completes either way.

```php
[
    'stage_number'       => 3,
    'title'              => 'Stage 3: Whose Side',
    'objective_text'     => 'Two buyers. One package. Make a call.',
    'rep_reward'         => 0,       // rep comes from branch_options, not here
    'is_branch'          => true,
    'branch_options'     => [
        ['canvas_id' => 'BA-hub', 'label' => 'Turn job into Knuckle', 'rep_reward' => 200],
        ['canvas_id' => 'DT-hub', 'label' => 'Turn job into Veil',    'rep_reward' => 200],
    ],
    'referral_canvas_id' => null,
    'referral_text'      => null,
],
```

### 4. Add a referral stage

Set `referral_canvas_id` and `referral_text` on any stage. When the stage is marked
complete, an entry appears in that doc's quest log section immediately, before
the player has visited them.

```php
'referral_canvas_id' => 'DT-hub',   // Veil
'referral_text'      => "Knuckle says Veil's been asking about you. She's Downtown.",
```

### 5. Completing a stage programmatically

To complete a stage from game logic (e.g. after a hack or PvP win):

```php
app(QuestService::class)->completeStage($player, $stageId, $turnedIntoDocId);
```

`$turnedIntoDocId` is only required for branch stages. Pass `null` otherwise.

---

## The 5 CyberDocs

| Handle  | Doc Name                | District             | Splice URL                    | Canvas ID |
|---------|-------------------------|----------------------|-------------------------------|-----------|
| KNUCKLE | Knuckle's Med-Wagon     | Browne's Addition    | `splice://cyberdoc.ba/knuckle`| BA-hub    |
| PATCH   | Patch's Clinic          | North Spokane        | `splice://cyberdoc.ns/patch`  | NS-hub    |
| VEIL    | Veil's Parlour          | Downtown             | `splice://cyberdoc.dt/veil`   | DT-hub    |
| AXIOM   | Axiom Systems           | University District  | `splice://cyberdoc.ud/axiom`  | UD-hub    |
| FLOAT   | Float's Repair Bay      | Spokane Valley       | `splice://cyberdoc.sv/float`  | SV-hub    |

Knuckle's Arc 1 (`is_entry_arc = true`) auto-unlocks for every new player.
All other docs require a first visit (physical travel to their node).
