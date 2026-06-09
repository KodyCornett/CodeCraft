# CodeCraft — Cleanup & Efficiency Plan

Each item below was identified by reading the actual code. Work through them in order — earlier sections remove the most risk before quest work begins.

---

## Section 1 — Dead Code Removal (Backend)

These are unreachable methods that survived the Packet Hijack Phase 2 redesign. They inflate `PacketHijackService.php` by ~600 lines and will cause confusion when adding quest hooks near the combat system.

- [x] **1.1 — Delete old fingerprint methods from `PacketHijackService`**

  Grepped all methods for external callers before deleting. Two surprises:
  - `generatePortTopology()` and `generateFingerprint()` are still called from `CombatChallengeController` (match creation seeding) — kept.
  - `biasToExposure()` and `generateBanner()` are called inside `generatePortTopology()` — kept.
  - `commandAuthenticate()` and `corruptFragments()` are called by `PacketHijackController::handleAuth` — kept.

  Deleted in two surgical blocks (lines 1759–2004 and 2185–2410):
  `commandScan`, `commandProbe` (old), `commandValidate`, `updateFingerprintDisplay`, `fingerprintComplete`, `commandExploitFingerprint`, `commandDecodeFingerprint`, `commandBreachFingerprint`, `_legacyProbePort`, `_legacyExploitPort`, `commandDecodePort`, `commandBreach` (old), orphaned section header.

  **Result: 3323 → 2847 lines (−476 lines)**

- [x] **1.2 — `generateBanner()` status confirmed**

  Called inside `generatePortTopology()` which is still active. Kept.

- [x] **1.3 — `generateNodeConnections()` and `generateRigIp()` confirmed active**

  Both called from `CombatChallengeController::accept()` during match creation. Kept.

### ✅ Section 1 Complete

**What was done:** Removed 476 lines of dead code from `PacketHijackService.php` — all orphaned methods from the old Phase 1 fingerprint/bias/decode/validate system that were replaced during the exploit-chain redesign but never deleted. Grepped every suspect method for external callers before touching anything. File went from 3323 → 2847 lines. No logic changes; zero callers affected.

---

## Section 2 — Game.vue Axios Violations

`Game.vue` has 7 direct `axios` calls. CLAUDE.md rule: components never call the API directly. These need to move to composables before adding quest hooks, or the quest system will inherit the same anti-pattern.

- [x] **2.1 — `useRigDamage.js` (new)** — `applyDamage(nodeCanvasId, source)` → returns server response. Called in `onHackFailed`. Critical-failure state update remains in Game.vue.

- [x] **2.2 — `useNodeTraces.js`** — added `storeTrace(nodeId, playerId)`. Called in `onHackFailed` after a failed hack; `refreshTraces()` called after on success.

- [x] **2.3 — `useCyberDoc.js` (new)** — `bank(playerId, canvasId)` → returns server data. Game.vue's `bankCreds()` now delegates the HTTP call and handles all state mutation from the returned result.

- [x] **2.4 — `useCombat.js`** — added `pollChallengeStatus(challengeId)` → Promise that resolves `{ status }` when no longer pending. Game.vue's `onHackPlayer` now `await`s it and handles state in one place instead of inside a setInterval.

- [x] **2.5 — `useGameState.js`** — added `resyncPlayer()` (GET /api/player/me → returns raw data) and `activateCommand(commandId)` (POST /api/player/activate-command). Both called from Game.vue.

**Bonus — three dynamic `import('axios')` calls also removed:**
- `useTrapSystem.js` (new) — `placeTrap`, `placeDecoy`, `fetchMyTraps` extracted. `myTraps` ref moved into the composable; Game.vue uses the destructured ref directly. Full trap reactive state migration is Section 3.3.

**Result: `import axios` removed from Game.vue entirely. Zero axios references remain.**

### ✅ Section 2 Complete + Validated

**What was done:** Removed all axios usage from Game.vue. The original plan listed 7 calls but the full audit found 10 (3 were dynamic `import('axios')` calls that don't match a simple `axios.` grep). Created two new composables — `useRigDamage.js` and `useCyberDoc.js` — and extended `useNodeTraces`, `useCombat`, and `useGameState` with new methods. As a side effect, `useTrapSystem.js` was scaffolded (API layer only) to handle the trap/decoy calls; reactive state migration completes in Section 3.3.

**Validation pass (post-section):** All three new files confirmed intact on disk (`useRigDamage` 36 lines, `useCyberDoc` 30 lines, `useTrapSystem` 55 lines). Game.vue imports and destructures all symbols correctly. `resyncPlayer` and `activateCommand` confirmed in `useGameState` return object. `/api/player/me` response shape `{ player, rig }` confirmed against `PlayerController`. `applyDamage` response shape `{ event, current_ss, max_ss, critical_failure }` confirmed against `RigController`. `storeTrace` node ID confirmed UUID-compatible with `Node::find()`. Zero `import axios` references remain in Game.vue.

---

## Section 3 — Game.vue Inline Logic Extraction

After Section 2 is done, Game.vue will still be ~2100 lines because game logic lives inline. The ping system and bounty escalation ladder are the biggest offenders and are the most likely to collide with quest triggers.

- [x] **3.1 — Extract the entire ping system into `usePingSystem.js`**

  Moved out of Game.vue:
  - `PING_BASE_RANGE`, `PING_MAX_RANGE`, `PING_TTL_MS`, `PING_PX_PER_HOP`, `PING_MIN_RADIUS_PX`, `OS_WEIGHT` constants
  - `calcPingRange()`, `pingRadiusPx()`, `firePing()`, `fireFalsePing()`, `clearFalsePings()`
  - `FALSE_PING_MOVE_TTL`, `_falsePingMovesLeft`, `_falsePingIds` module-level vars
  - `fireOpponentPings()` function + its `watch(bounties, ...)` — watcher lives in the composable now
  - All three `pings.value = []` resets replaced with `resetPings()`
  - False-ping TTL block in `handlePlayerMoved` replaced with `onMoveTick()`

  Note: `pings` ref stays in `useMapInteraction` (HexMapCanvas needs it as a prop). `usePingSystem` receives it as a parameter. Instantiated in Game.vue right after `useMapInteraction` so the ref is available.

  **Result: Game.vue 2241 → 2060 lines (−181 lines). `usePingSystem.js` 228 lines.**

- [ ] **3.2 — Extract bounty escalation into `useBountyEscalation.js`**

  Move out of Game.vue:
  - `BOUNTY_THRESHOLDS`, `STAR_HACK_THRESHOLDS` constants (these duplicate `BountyService::STAR_TIERS` — keep the server authoritative, expose them from an API endpoint or hardcode only in one place)
  - `hackCount` ref
  - `bountyTicker` computed
  - `checkBountyEscalation()`, `showBountyAlert()`
  - `bountyAlert` ref + `_alertTimer`
  - `showOpenSeason` ref + its watcher

  Composable exposes `{ hackCount, bountyTicker, bountyAlert, showOpenSeason, onHackSuccess, onBountyReset }`. Game.vue calls those events rather than mutating `player.value.bountyLevel` directly.

- [ ] **3.3 — Extract trap state into `useTrapSystem.js`**

  Move out of Game.vue:
  - `trapTargetMode`, `myTraps`, `trapHitNotification`, `trapFiredNotification` refs
  - `cancelTrapTarget()`, trap-placement logic from `onUseCommand()`
  - `_osExploitReduction`, `_bufferOverflowCmdId` module-level vars

  Composable exposes `{ trapTargetMode, myTraps, trapHitNotification, trapFiredNotification, activateTrapCommand, cancelTrapTarget, handleTrapHit, handleTrapFired }`.

- [ ] **3.4 — Remove the 29 `console.log` calls from Game.vue**

  Either delete them outright (preferred) or gate them behind `import.meta.env.DEV`. The ping and bounty logs are the noisiest — those live inside the composables extracted in 3.1 and 3.2, so they come out automatically. Clean remaining ones manually.

---

## Section 4 — Oversized Component Splits

These are over the 300-line target but are lower priority than Sections 1–3. Tackle before quest UI components arrive so the new components have clean homes.

- [ ] **4.1 — Extract `GridBreach` game loop into `useGridBreach.js`**

  `GridBreach.vue` is 1693 lines, most of it game-loop logic (sequence generation, input timer, hex key matching, scoring). The Vue component should own rendering and emit results. Move the logic: sequence generation, key-press handler, input window timer, health calculation, command effects into the composable. Target: component under 400 lines.

- [ ] **4.2 — Split `PacketHijack.vue` into phase sub-components**

  1498 lines. The three phases have almost nothing in common visually:
  - Extract `PacketHijackPhase1.vue` — the suspect table and recon terminal
  - Extract `PacketHijackPhase2.vue` — port board, credential strip, trace counter
  - Extract `PacketHijackPhase3.vue` — filesystem browser
  
  `PacketHijack.vue` becomes a shell that shows the correct phase component and passes props through. Keep the terminal input strip in the shell since it's shared.

- [ ] **4.3 — Extract `HexMapCanvas` ping layer into `PingLayer.vue`**

  `HexMapCanvas.vue` is 1559 lines. The SVG ping rings (amber dashed, red solid, teal dashed) are a self-contained rendering concern. Extract them to `PingLayer.vue` which receives the `pings` array as a prop and renders the SVG `<g>` elements. HexMapCanvas includes it inside its SVG. This also makes the ping layer easier to test visually in isolation.

---

## Section 5 — Naming Inconsistencies

These are cosmetic but will cause confusion when adding quest content that references CyberDoc.

- [ ] **5.1 — Rename `StreetDocInventoryService` → `CyberDocInventoryService`**

  File: `api/app/Services/StreetDocInventoryService.php`. Update the class name, filename, and the `use` import in `StoreController`. The underlying model (`StreetDocCatalog`) can be addressed in 5.2.

- [ ] **5.2 — Rename `StreetDocCatalog` model → `CyberDocCatalog`**

  File: `api/app/Models/StreetDocCatalog.php`. Write a new migration to rename the `street_doc_catalogs` table to `cyber_doc_catalogs`. Update all references in the service and any controller that touches the model.

- [ ] **5.3 — Rename `StreetDoc` model references to `CyberDoc` in `Player.php`**

  `Player` has a `hardwareEncrypts()` relationship referencing `StreetDoc`-era naming. Audit the model file and update any relationship or comment that still uses the legacy name.

---

## Section 6 — Quest System Prerequisites

Before writing the first quest, make sure the infrastructure is ready.

- [ ] **6.1 — Decide: keep tutorial in `localStorage` or migrate to DB**

  `useTutorial.js` uses `localStorage` keyed by `codecraft_tutorial_v1`. This is fine for the 4-step orientation but will not survive multiple devices, private browsing, or cross-session quest continuity if the story has branching or persistent consequences. If quests need server-side state (recommended for anything beyond orientation), plan a `player_quests` migration before writing quest content.

- [ ] **6.2 — Add a `quests` route group to `api.php`**

  Create the stub now so it exists alongside tutorial:
  ```
  Route::get('/quests',              [QuestController::class, 'index']);
  Route::get('/quests/{quest}',      [QuestController::class, 'show']);
  Route::post('/quests/{quest}/advance', [QuestController::class, 'advance']);
  ```

- [ ] **6.3 — Review `useTutorial.js` and plan the `useQuests.js` composable shape**

  `useTutorial` is a good template — it has quest defs, step tracking, reward claiming, and a badge. A `useQuests.js` composable should follow the same pattern but read/write from the server (6.1) rather than localStorage. Map out what events Game.vue will need to emit to advance quest steps (e.g., `onHackComplete` advancing a quest step alongside the existing bounty logic).

---

## Notes

- Each section can be worked independently after Section 1 is done first (dead code removal de-risks everything else).
- Section 2 and 3 must be done before adding any quest-triggered side effects to `onHackComplete`, `onHackFailed`, or `bankCreds` — otherwise the new quest calls sit next to raw axios in Game.vue.
- Section 4 is cosmetic efficiency — skip if time-pressed, but do 4.1 before adding GridBreach quest steps.
- Section 5 is low-risk renaming — safe to batch into a single commit.
- Section 6 is the on-ramp to quest work — complete this last or in parallel with Sections 4–5.
