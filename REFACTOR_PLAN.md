# CodeCraft — Refactor & Cleanup Plan

A phased review of dead code, separation-of-concerns violations, and structural improvements. Each phase is ordered from lowest risk to highest, so early phases can be merged independently without touching game logic.

---

## Phase 1 — Dead-Weight Removal

The safest phase. No logic changes — only delete files and methods that are provably unused.

### 1.1 — Orphaned `web/` Directory

The `web/` directory is an entirely separate Laravel application built on Blade + vanilla JS with a `KotlinGameEngine` interface. It has no connection to the active `api/` project and appears to be a prototype that predates the current Inertia/Vue stack.

**Remove:**
- The entire `web/` directory
- `web/cookies.txt`, `web/test_controller.php`, `web/test_mission_flow.php`, `web/test_simple.php`

**Before removing:** confirm no deployment config or CI pipeline still references `web/` as a target.

---

### 1.2 — Root-Level Artifacts

These files are developer debug artifacts that should not live in the project root.

| File | Reason |
|---|---|
| `nul` | Empty Windows artifact (zero bytes) |
| `cookies.txt` | HTTP debug session dump |
| `bugs.md` | Ad-hoc bug notes — migrate open items to issues or a tracked `docs/` file, then delete |
| `progress.md` | Ad-hoc progress notes — same treatment |
| `api/ui_buildV2.md` | Build notes inside the api/ directory — move to `docs/` or delete |
| `PACKET_HIJACK_BUILD_PLAN.md` | All checklist items are ticked. Archive to `docs/archive/` or delete. |

---

### 1.3 — Legacy Methods in `PacketHijackService.php`

The Exploit Chain redesign (Phase 2) replaced the old fingerprint/decode/validate system. The old methods were not deleted — they were renamed with `_legacy` prefixes or left in place. The following are confirmed dead (not called from `PacketHijackController` or any other caller):

**Methods marked `@deprecated` — delete:**
- `_legacyProbePort()`
- `_legacyExploitPort()`

**Old fingerprint-system methods — verify no callers, then delete:**
- `commandScan()` — replaced by `commandScanPorts()`
- `commandProbe()` — replaced by `commandProbePort()`
- `commandValidate()`
- `commandDecodeFingerprint()`
- `commandBreachFingerprint()`
- `commandExploitFingerprint()`
- `commandDecodePort()`
- `commandBreach()` — the chain-based breach is now `commandBreachChain()`

**Old Phase 1 setup methods — verify no callers, then delete:**
- `generateRigIp()`
- `generateNodeConnections()`
- `generatePortTopology()`
- `generateFingerprint()`
- `biasToExposure()`
- `generateBanner()`

**Old filesystem phase methods — verify active game usage, then decide:**
- `generateFilesystem()` / `injectWallet()` / `navigateToPath()`
- `commandLs()` / `commandCd()` / `commandExtract()`

These may still be wired to an active Phase 3 (filesystem exfil). Check `PacketHijackController` command routing before removing.

Removing these dead methods alone will take `PacketHijackService.php` from ~3,183 lines to an estimated ~1,800–2,000 lines — still large, but much cleaner (addressed further in Phase 4).

---

### 1.4 — `AuthController.php`

`POST /api/auth/token` is documented as "for the Kotlin engine only — not used by the SPA." If no Kotlin engine is actively deployed or planned, this route and its controller are dead weight. Remove both, or at minimum add a comment confirming the intended consumer and its status.

---

## Phase 2 — Documentation & Reference Accuracy

Fix stale references so CLAUDE.md and inline docs accurately describe the current codebase.

### 2.1 — Update `CLAUDE.md`

| Stale reference | Correct current state |
|---|---|
| `useMockGameState.js` | Renamed to `useGameState.js` — update all references |
| `StreetDocController` | Renamed/replaced by `CyberDocController` |
| `useWebSocket` described as "will be replaced by Laravel Echo" | Still true, but add note that it is actively a no-op stub — nothing should be wired to it |
| Composables list is incomplete | Add `useAudio.js`, `useHeartbeat.js`, `useTutorial.js`, `useNodeIdentity.js`, `useUpgradeCosts.js` with one-line descriptions |

### 2.2 — `useUpgradeCosts.js` Clarification

This composable does client-side cost projection math (display only — it previews upgrade prices in the store UI). It exports an `effectiveStat()` helper that could be confused with server-authoritative stat computation. Add a clear comment: *"client-side display calculations only — the server recomputes all stats via RigService::effectiveStats() on every transaction."*

### 2.3 — `BountyEvent.php` Location

`app/Services/BountyEvent.php` is a value object / DTO used by `BountyService`. DTOs do not belong in `Services/`. Move to `app/DTOs/BountyEvent.php` and update the import in `BountyService`.

---

## Phase 3 — Backend Separation of Concerns

### 3.1 — `PacketHijackController.php` (1,137 lines, ~106 conditional/logic lines)

The controller has grown beyond input validation + delegation. Several command handlers contain inline result-assembly and conditional branching that belongs in the service layer.

**Approach:** Extract each command type into a dedicated private handler method that does nothing except call the service and shape the HTTP response. The controller should be a thin dispatch table. Target: under 400 lines once dead Phase 1 handlers are removed (Phase 1.3).

Alternatively — if the controller remains large after Phase 1 — split into:
- `PacketHijackController` — match lifecycle (create, state, phase transitions)
- `PacketHijackCommandController` — all `command` endpoint handlers

### 3.2 — `PacketHijackService.php` — Split After Cleanup

After Phase 1.3 removes dead methods, the remaining service will still cover four distinct concerns. Split into focused services:

| New service | Responsibility |
|---|---|
| `PacketHijackSetupService` | Match creation, pool generation, chain generation, initial state seeding |
| `PacketHijackPhase1Service` | Phase 1 recon commands (netstat, ping, traceroute, arp-scan, whois, sniff, flush, inject) |
| `PacketHijackPhase2Service` | Phase 2 chain commands (scan-ports, probe-port, trace, exploit-port, breach-chain) |
| `PacketHijackRigCommandService` | All rig-command effects (data-spike, ghost-protocol, hardlock, null-byte, etc.) |

The existing `PacketHijackService` becomes a facade that delegates to these four, keeping the controller import unchanged.

### 3.3 — `RigService.php` — Verify Single Responsibility

At 681 lines, `RigService` is within acceptable range but covers stat computation, cap enforcement, damage application, loadout slots, and chassis upgrade logic. Confirm each method fits under "all stat calculation" — if chassis upgrade logic grows (new tiers, unlock conditions), consider extracting a `ChassisService`.

---

## Phase 4 — Frontend Separation of Concerns

### 4.1 — `Game.vue` — Remove Direct Axios Calls (2,146 lines)

`Game.vue` is the wiring harness. It must not contain raw API calls. Seven direct `axios` calls were found in the current file, all violating the stated rule. Each should be delegated to the appropriate composable:

| Axios call in Game.vue | Move to |
|---|---|
| `POST /api/rig/damage` | `useCombat.js` or a new `useRig.js` |
| `POST /api/nodes/{nodeId}/trace` | `useNodeTraces.js` |
| `POST /api/cyberdoc/bank` | A new `useCyberDoc.js` (see 4.2) |
| `GET /api/player/me` (multiple) | `useGameState.js` — expose a `refresh()` method |
| `GET /api/combat/challenge/{id}/status` | `useCombat.js` |

### 4.2 — Extract `useCyberDoc.js`

CyberDoc interactions (visit, bank, repair, install, loadout, reallocate, upgrade-command) are currently scattered across `Game.vue` and components that call up through emits. Centralising these in a `useCyberDoc.js` composable would:
- Give one place for all CyberDoc API calls
- Make the StreetDoc → CyberDoc rename final and complete
- Remove the remaining `useStreetDoc` references in the codebase

### 4.3 — `HexMapCanvas.vue` (1,559 lines)

The component handles SVG hex rendering, node interaction hit detection, ping overlay rendering, and camera/viewport logic in a single file. Extract:

- **`usePingLayer.js`** — ping ring computation, animation timing, ring state — currently mixed into the canvas component
- **`PingOverlay.vue`** — SVG ping ring rendering, pulled out of `HexMapCanvas` as a child component it v-slots into the SVG
- The viewport/zoom/pan logic is a candidate for a `useMapViewport.js` composable

### 4.4 — `GridBreach.vue` (1,693 lines)

GridBreach contains the game board state machine, timer, sequence tracking, PvP handshake state, and all UI rendering. Extract:

- **`useGridBreach.js`** — board state, sequence logic, timer, win/loss detection
- **`GridBreachBoard.vue`** — the hex key grid rendering only
- **`GridBreachHUD.vue`** — timer, attempt counter, score display
- `GridBreach.vue` becomes the wiring harness that connects the composable to the two child components

### 4.5 — `PacketHijack.vue` (1,327 lines)

Extract:
- **`PHPortBoard.vue`** — port cards with probed/confirmed/shattered indicators
- **`PHCredentialStrip.vue`** — the progressive credential reveal strip
- **`PHTerminal.vue`** — the command input line, history display, output log
- `PacketHijack.vue` becomes the orchestration layer

### 4.6 — `useBrowserState` vs `useBrowser` Naming Confusion

Both composables exist in `composables/` and both relate to the in-game browser:
- `useBrowserState.js` — controls which SPLICE URL the panel is showing (Game.vue level)
- `useBrowser.js` — manages navigation history and current route inside `InGameBrowser.vue`

Rename for clarity:
- `useBrowserState.js` → `useSplicePanel.js` (controls panel open/close and active URL from outside)
- `useBrowser.js` stays as `useBrowser.js` (internal browser navigation)

Update `CLAUDE.md` entry 8 accordingly.

---

## Phase 5 — Structural & Naming Consistency

### 5.1 — `NodeWindow.vue` vs `NodeInfoBlock.vue`

`NodeWindow.vue` lives in `map/` and `NodeInfoBlock.vue` lives in `panel/`. Confirm these are not overlapping concerns. If `NodeWindow` is a map-level tooltip and `NodeInfoBlock` is the side-panel view of the same node, document this explicitly. If one is unused or redundant, remove it.

### 5.2 — `Register.vue` — Validate Accessibility

`Register.vue` exists and `RegisterController` is wired in `routes/web.php`, but neither is mentioned in `CLAUDE.md`. Confirm registration is intentionally a supported flow (or intentionally hidden), and document it.

### 5.3 — `HardwareEncrypt` Model

`app/Models/HardwareEncrypt.php` exists but is not referenced in `CLAUDE.md` and has no obvious controller or service consumer. Audit its relationships and determine if it is used, planned, or orphaned.

### 5.4 — Factory Files Without Active Test Suite

`database/factories/` has six factory files but there is no meaningful test suite (only Laravel boilerplate tests). Either:
- Begin wiring factories into feature tests (recommended for long-term stability)
- Or document that factories exist solely for `php artisan db:seed` dev seeding, not for automated testing

---

## Summary — Priority Order

| Phase | Risk | Effort | Value |
|---|---|---|---|
| 1 — Dead-weight removal | None | Low | High — dramatically reduces noise |
| 2 — Docs & reference accuracy | None | Low | Medium — reduces confusion |
| 3 — Backend SoC | Low | Medium | High — PacketHijackService is a maintenance liability |
| 4 — Frontend SoC | Medium | High | High — unblocks parallel component work |
| 5 — Structural naming | Low | Low | Medium — long-term clarity |

Phases 1 and 2 should be done together in a single clean-up PR. Phases 3 and 4 are independent tracks that can proceed in parallel once the codebase is clean.
