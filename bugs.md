# CodeCraft — Bug Tracker

Bugs found during review on 2026-05-19. Fixed in order of severity.

---

## 🔴 Critical

- [x] **#1 — Open Season steal returns 60% instead of 75%**
  `api/app/Services/BountyService.php` line 230
  `calculateStealPercentage()` returns `STEAL_TIERS[25]` (60%) when `is_open_season` is true.
  Should return `STEAL_TIERS[30]` (75%) per design.

- [x] **#2 — Decline never saves the loser's pocket_creds deduction**
  `api/app/Http/Controllers/CombatChallengeController.php` — `decline()`
  `resolvePvpLoot()` updates `$me->pocket_creds` in memory but the comment says
  "loser is saved by resetAfterPvpLoss()". `resetAfterPvpLoss()` is never called
  from `decline()`, so on a survivable decline the stolen creds are never persisted —
  the decliner keeps all their money.

- [x] **#3 — Stat upgrade deducts creds before the upgrade with no rollback on failure**
  `api/app/Http/Controllers/RigController.php` — `upgrade()` lines 186–197
  `wallet_creds` and `tech_points` are saved before `upgradeStat()` is called.
  If `upgradeStat()` throws (stat capped, all others at minimum), the player loses
  their creds/TP without getting the upgrade. Needs a `DB::transaction()` wrapping both.

---

## 🟠 High

- [x] **#4 — Critical failure sends a different spawn node to the client than where the player lands**
  `api/app/Http/Controllers/CombatController.php` line 253
  `api/app/Http/Controllers/RigController.php` line 126
  `api/app/Http/Controllers/CombatChallengeController.php` line 290
  `RigService::criticalFailure()` teleports the player to one random spawn node and
  persists it. Each calling controller then fires a *second* `inRandomOrder()->first()`
  for `respawn_canvas_id` in the response — potentially a different node. The client
  displays the wrong destination.

- [x] **#5 — Cache full is not enforced server-side**
  `api/app/Http/Controllers/NodeController.php` — `deplete()`
  The server increments `cache` per hack but never checks whether
  `cache >= maxCache (cpu + ram)` before allowing the hack. A client bypassing
  the UI can keep hacking indefinitely with a full cache.

---

## 🟡 Medium

- [x] **#6 — `Math.round()` truncates fractional tech rewards before sending to API**
  `api/resources/js/composables/useDepletion.js` line 70
  All resource types go through `Math.round(rewardAmount)`, stripping the decimal
  precision the server expects for tech_points (stored as `decimal:2`).

- [x] **#7 — Stale BountyService class docblock contradicts actual thresholds**
  `api/app/Services/BountyService.php` lines 14–18
  The docblock says "< 5 hacks → off board, 5–9 → Level 1" but the real thresholds
  (per CLAUDE.md and the code) are 10/15/20/25/30. Misleads future developers.

- [x] **#8 — Duplicate "Step 1" comment in `CombatController::resolve()`**
  `api/app/Http/Controllers/CombatController.php` lines 155–156
  Two consecutive `// ── 1.` labels describe different operations — the loot comment
  is a leftover from a prior ordering of the resolve steps.

---

## 🟡 Third Pass — 2026-05-19

### 🟡 Medium

- [x] **#9 — `canUpgrade()` and `effectiveStat()` double-count invested stat points**
  `api/resources/js/composables/useUpgradeCosts.js` lines 92–103
  Both functions compute `rig[stat] + rig.investedPoints[stat]`, but `rig[stat]` is
  already the *effective* stat (base + invested) as returned by `RigService::effectiveStats()`.
  Adding `investedPoints[stat]` a second time overstates "current" vs the cap.
  `canUpgrade()` disables upgrade buttons one point before the cap is actually reached.
  `effectiveStat()` returns values that are too high — used in `CyberDocStore.vue` for pip
  rendering, the stat display, and OS-gate checks.

- [x] **#10 — Signal Noise plants only 1 false ping instead of 2**
  `api/resources/js/Pages/Game.vue` — `fireFalsePing()` / Signal Noise handler (lines ~472–503, 1007–1020)
  `fireFalsePing()` always removes the current `_falsePingId` before adding a new one.
  When the Signal Noise loop calls it twice, the second call removes the first ping
  immediately — leaving only 1 false ping on the map instead of the intended 2.

---

## 🔴 Fourth Pass — 2026-05-19

### 🔴 Critical

- [x] **#11 — `upgradeCommand()` casts `tech_points` to int before subtracting cost**
  `api/app/Services/CyberDocService.php` line 292
  `$player->tech_points = (int) ($player->tech_points ?? 0) - $cost;`
  `tech_points` is stored as `decimal:2`. The `(int)` cast truncates the fractional part
  before the subtraction — a player with 2.75 TP paying a 2 TP cost is charged 2.75 TP
  instead of 2 TP (because `(int)(2.75) = 2`, and then `2 - 2 = 0`, not `0.75`).
  `StoreController::purchaseCommand()` correctly uses `round((float)...)` for the same operation.

- [x] **#12 — `repairSS()` deducts wallet creds before repair with no transaction**
  `api/app/Services/CyberDocService.php` — `repairSS()` lines 98–103
  `$player->wallet_creds -= $credCost; $player->save()` executes and persists before
  `$this->rigService->repair()` is called. If `repair()` throws, the player is permanently
  charged without their SS being restored. Needs a `DB::transaction()` wrapping both.

### 🟠 High

- [x] **#13 — Bounty leaderboard omits `pocket_creds` from API response**
  `api/app/Http/Controllers/BountyController.php` — `bountyLeaderboard()` lines 28–38
  `BountyService::getBountyLeaderboard()` selects `pocket_creds` from the DB, but the
  map in `bountyLeaderboard()` does not include it in the JSON response.
  `useBountyBoard.js` reads `api.pocket_creds ?? 0` to compute the reward shown to hunters —
  without the field in the response this is always 0, so the bounty board reward column
  shows ◈0 for every target regardless of their actual pocket balance.

### 🟡 Medium

- [x] **#14 — Consumable quantity decremented outside the effect-application transaction**
  `api/app/Services/InventoryService.php` — `useConsumable()` lines 153–163
  The `DB::transaction()` only wraps the quantity decrement. `applyRepair()` and
  `applySoftware()` run after the transaction closes. If either throws (e.g. player has
  no rig for a repair kit), the consumable is permanently consumed but the effect is never
  applied — the player loses the item with no benefit.

---

## 🔒 Security Review — 2026-05-19

### 🔴 Critical

- [x] **S1 — Free SS repair via client-controlled `cred_cost`**
  `api/app/Http/Controllers/CyberDocController.php` — `repair()` line 76
  `POST /api/cyberdoc/repair` accepts an optional `cred_cost` from the client body:
  `$cost = $data['cred_cost'] ?? $this->cyberDocService->repairCost($player);`
  Sending `{ "cred_cost": 0 }` causes `repairSS()` to skip deduction entirely
  (`if ($credCost > 0)`) and still restore SS to maximum. Any player can repair for
  free at any time. The `cred_cost` parameter must be removed — cost must always be
  computed server-side.

- [x] **S2 — `POST /api/rig/repair` restores SS with no cost at all**
  `api/app/Http/Controllers/RigController.php` — `repair()`
  This endpoint calls `rigService->repair()` directly with no affordability check and
  no cred deduction. Any authenticated player can hit it to fully restore their SS
  for free, completely bypassing the CyberDoc repair cost system.

- [x] **S3 — IDOR: any player can read any other player's full stats and position**
  `api/app/Http/Controllers/PlayerController.php` — `status()` line 330
  `GET /api/player/{player_id}/status` calls `Player::find($playerId)` with no
  ownership check. Any authenticated player who knows (or enumerates) another player's
  UUID gets their exact map position, SS, limping state, stats, and bounty info — full
  pre-attack scouting with no in-game cost.

- [x] **S4 — Player can teleport to any node; movement is not validated server-side**
  `api/app/Http/Controllers/PlayerController.php` — `position()` lines 205–255
  `POST /api/player/position` accepts any `canvas_node_id` string and updates the
  player's position without checking: (a) whether the destination is adjacent to the
  current node, or (b) whether the player has uplink remaining. Any player can set
  their position to any of the 228 nodes instantly at zero uplink cost.

---

### 🟠 High

- [x] **S5 — Hack `reward_amount` is fully trusted from the client**
  `api/app/Http/Controllers/NodeController.php` — `deplete()`
  The cred and tech_point reward is taken entirely at face value from the client body.
  A player making a direct API call can claim an arbitrarily large reward —
  `{ "resource": "creds", "reward_amount": 999999 }` — to mint unlimited currency.
  *(The code already has a comment acknowledging this as a future hardening step.)*

- [x] **S6 — PvP GridBreach score is fully trusted from the client**
  `api/app/Http/Controllers/CombatController.php` — `result()` line 50
  Both players self-report their GridBreach score via `POST /api/combat/result`.
  There is no server-side game replay or validation — submitting `{ "score": 999999 }`
  guarantees winning every PvP duel.

- [x] **S7 — Token endpoint open to brute-force with no rate limiting**
  `api/app/Http/Controllers/AuthController.php` — `token()`
  `POST /api/auth/token` sits outside the `auth:sanctum` middleware group and accepts
  email + password to issue a Bearer token. There is no rate limiting, throttle
  middleware, or lockout — making it trivially open to credential stuffing or
  brute-force attacks against all player accounts.

---

### 🟡 Medium

- [x] **S8 — Any authenticated user can mint permanent API tokens**
  `api/app/Http/Controllers/AuthController.php` — `token()`
  The `/api/auth/token` endpoint is documented as "for the Kotlin engine" but is
  accessible to every authenticated user. Any player can call it with their own
  credentials to create a long-lived Sanctum Bearer token, giving them persistent
  API access that survives session expiry and logout.

- [x] **S9 — "Remember me" is hardcoded to `true` for all logins**
  `api/app/Http/Controllers/Auth/LoginController.php` line 32
  `api/app/Http/Controllers/Auth/RegisterController.php` line 84
  `Auth::attempt($credentials, remember: true)` creates a permanent remember_me cookie
  on every login, regardless of user preference. On shared or compromised devices this
  extends the session attack window indefinitely.

---

## 🔵 Fifth Pass — 2026-05-19

### 🟡 Medium

- [x] **#15 — Pip visualization never shows invested pips after `effectiveStat` fix**
  `api/resources/js/components/browser/pages/CyberDocStore.vue` lines 79–83
  Fixing Bug #9 made `effectiveStat(stat, rig)` return `rig[stat]` (the effective value).
  The invested-pip condition `n > rig[s.key] && n <= effectiveStat(s.key, rig)` is then
  always false — both sides equal the same value. No invested pips were ever rendered.
  Fixed by splitting the boundary correctly:
    `cc-pip--base`:     `n <= rig[s.key] - (rig.investedPoints?.[s.key] ?? 0)`
    `cc-pip--invested`: `n > base_boundary && n <= rig[s.key]`
    `cc-pip--empty`:    `n > rig[s.key]`

### 🔴 Critical

- [x] **S10 — Stat upgrade cost is fully trusted from the client**
  `api/app/Http/Controllers/RigController.php` — `upgrade()` lines 162–197
  `POST /api/rig/upgrade` accepted optional `cred_cost` and `tp_cost` from the request
  body and used them directly as the actual deduction amounts. Sending
  `{ "stat": "cpu", "cred_cost": 0, "tp_cost": 0 }` gave a free stat upgrade every time.
  Same class of vulnerability as S1 (client-controlled repair cost).
  Fixed by adding `RigService::statUpgradeCost()` (mirrors the JS formula exactly) and
  rewriting `upgrade()` to remove those parameters and always compute cost server-side.

- [x] **#16 — `SysRig.vue` pip rendering never shows invested pips**
  `api/resources/js/components/browser/pages/SysRig.vue` — `coreStats` computed
  `base` was set to `r[key]` (the full effective stat from the server, already including
  invested points). The `seg--invested` condition `i > s.base` was then always false for
  any reachable pip position — all lit pips rendered as `seg--base`.
  Fixed by setting `base = effective - invested` so the invested-pip zone is correctly
  bounded between the chassis base and the fully invested effective stat.

- [x] **#17 — `SysCommands.vue` active loadout uses Storage cap instead of RAM**
  `api/resources/js/components/browser/pages/SysCommands.vue` line 179
  `maxSlots` was computed from `rig.value?.storage`, but active loadout slot count
  is governed by RAM (per design doc and server-side enforcement in
  `CyberDocService::setLoadout()`). Storage controls total command library size.
  A player with RAM 2, Storage 4 would incorrectly see 4 slots instead of 2.
  Fixed by changing the computed to use `rig.value?.ram`.

---

## 🔴 Sixth Pass — 2026-05-19

### 🔴 Critical

- [x] **S11 — Chassis upgrade cost is fully trusted from the client**
  `api/app/Http/Controllers/RigController.php` — `chassisUpgrade()`
  `POST /api/rig/chassis-upgrade` accepted `cred_cost` and `tp_cost` from the
  request body and used them directly as the deduction amounts. Sending
  `{ "chassis_name": "NullTek GX-7 Ghost", "cred_cost": 0, "tp_cost": 0 }` gave
  a free Tier 2 chassis upgrade every time.
  Same class of vulnerability as S1 and S10 (client-controlled repair/stat costs).
  Fixed by adding `RigService::chassisUpgradeCost()` with a hardcoded cost table
  and rewriting `chassisUpgrade()` to remove those parameters and always compute
  cost server-side.

### 🟠 High

- [x] **#18 — `chassisUpgrade()` deducts cost outside a DB transaction**
  `api/app/Http/Controllers/RigController.php` — `chassisUpgrade()`
  `$player->save()` (cost deduction) and `$rig->save()` (chassis swap) executed
  as separate statements with no wrapping transaction. If the rig save throws,
  the player permanently loses their creds/TP without receiving the new chassis.
  Same pattern as bugs #3 and #12.
  Fixed by wrapping both saves in a `DB::transaction()`.

### 🟡 Medium

- [x] **#19 — `decline()` doesn't check challenge expiry**
  `api/app/Http/Controllers/CombatChallengeController.php` — `decline()`
  `accept()` guards with `$challenge->isExpired()` and returns 404 if the
  challenge TTL has elapsed. `decline()` has no such check — a target can decline
  an already-expired challenge and receive the full 20 SS damage + bounty-scaled
  pocket steal for free (the penalty fires regardless of TTL).
  Fixed by adding `|| $challenge->isExpired()` to the null guard in `decline()`.

- [x] **#20 — `reallocateStats()` doesn't enforce RAM/CPU caps after reducing a stat**
  `api/app/Services/CyberDocService.php` — `reallocateStats()`
  When a player reallocates FROM ram or cpu, active commands that now exceed the
  new effective RAM (slot count) or CPU (command level cap) are not deactivated.
  `enforceRamCap` and `enforceCpuCommandCap` are only called from
  `RigService::applyDamage()` — they are never triggered by a stat reallocation.
  A player could hold more active commands than their RAM allows, or have commands
  equipped above their CPU cap, until the next damage event fires.
  Fixed by adding a public `RigService::enforceStatCaps()` wrapper and calling it
  from `reallocateStats()` after saving the rig.

- [x] **#21 — `bankCreds()` zeros pocket before crediting wallet — no transaction**
  `api/app/Services/CyberDocService.php` — `bankCreds()`
  `extractToCyberDoc()` zeros `pocket_creds` and calls `$player->save()`.
  If the subsequent `wallet_creds` update save fails, the player's pocket is
  permanently destroyed without ever reaching the wallet. The uplink restore and
  cache clear that follow also execute outside any transaction boundary.
  Fixed by wrapping the full banking sequence in a single `DB::transaction()`.

- [x] **#22 — `SysRig.vue` point cap reads wrong property — always shows X/20**
  `api/resources/js/components/browser/pages/SysRig.vue` line 225
  `pointsCap` computed reads `rig.value.caps?.pointCap ?? 20`. The `caps` object
  holds per-stat caps (`{ cpu, ram, firewall, storage, os }`); there is no
  `pointCap` key on it. The expression always evaluates to `undefined ?? 20 = 20`,
  hardcoding the denominator at 20 instead of reading the actual chassis total
  point cap (9 for BlackHat, 18 for NullTek). The correct property from
  `useGameState` is `rig.value.pointsCap`.
  Fixed by changing the computed to `rig.value.pointsCap ?? 0`.

---

## 🔬 `enforceStatCaps()` Edge-Case Audit — 2026-05-19

### 🟠 High

- [x] **EC-1 — `enforceStatCaps()` dereferences `chassis` without guaranteeing it is loaded**
  `api/app/Services/RigService.php` — `enforceStatCaps()` → `effectiveStats()`
  `effectiveStats()` reads `$rig->chassis` (line 191) and then accesses
  `$chassis->base_cpu` etc. immediately. If the rig is ever passed to
  `enforceStatCaps()` without the `chassis` relationship eager-loaded (e.g. from
  a plain `PlayerRig::find($id)` call), Laravel lazy-loads the relationship — which
  throws a fatal `TypeError` if `chassis_template_id` is null or the template row
  has been deleted.
  Every current call site uses `getRigForPlayer()` which eager-loads `chassis`, so
  this does not bite today. But there is nothing in the signature or code to enforce
  it for future callers.
  Fixed by adding `$rig->loadMissing('chassis')` at the top of `enforceStatCaps()`
  so the relationship is always guaranteed regardless of how the rig was fetched.

### 🟡 Medium

- [x] **EC-2 — Command over both RAM and CPU cap is double-reported to the client**
  `api/app/Services/RigService.php` — `enforceStatCaps()`
  `enforceRamCap()` deactivates the command (by slot) and adds it to
  `deactivated_ram`. Then `enforceCpuCommandCap()` finds the same command still
  matching `WHERE level > $maxLevel` (it queries by level, not `is_active`) and
  adds its `command_id` to `deactivated_cpu`. The deactivation itself is idempotent
  — the second UPDATE is a no-op — but `CyberDocController::reallocate()` surfaces
  both arrays to the client, which would flash two separate deactivation warnings
  for the same command.
  Fixed by de-dupping in `enforceStatCaps()`: strip from `deactivated_cpu` any
  command_id that was already covered by a RAM-cap drop.

- [x] **EC-3 — `enforceRamCap` and `enforceCpuCommandCap` have no row lock**
  `api/app/Services/RigService.php` — `enforceRamCap()`, `enforceCpuCommandCap()`
  Both methods do READ → WRITE with no pessimistic lock or wrapping transaction.
  Two simultaneous reallocation requests could both read the same overflowing
  commands, then both try to deactivate them. The second UPDATE is a no-op so the
  final DB state is always correct — but both callers return non-empty arrays,
  meaning the second response falsely claims commands were just deactivated when
  they were already gone. `CyberDocController::reallocate()` forwards those arrays
  to the client, triggering spurious UI warnings.
  Fixed by wrapping each method's read + write in `DB::transaction()` with
  `lockForUpdate()` on the read query.

- [x] **EC-4 — `player_commands.level = NULL` silently escapes CPU cap enforcement**
  `api/app/Services/RigService.php` — `enforceCpuCommandCap()`
  The query `WHERE level > $maxLevel` evaluates to `NULL` (falsy) for any row
  where `level IS NULL`, so a command with a null level is never caught regardless
  of the CPU cap. This means a player could hold an effectively uncapped command
  in their active loadout if a bad insert ever produced a null level.
  Fixed by adding a migration that alters `player_commands.level` to
  `NOT NULL DEFAULT 1`, and updating any existing null rows to 1 before the
  constraint is applied.

---

## Data-type / Validation Audit Pass

- [x] **DV-1 — `Player` model: `cache` column missing from `$casts` and `$fillable`**
  `api/app/Models/Player.php`
  Migration `2026_05_19_000001_add_cache_to_players_table.php` added `cache` as
  `unsignedSmallInteger` with a default of 0, but the `Player` model has no
  corresponding entry in either `$casts` or `$fillable`. Reading `$player->cache`
  returns a raw string from Eloquent (no automatic integer coercion). Every caller
  currently wraps the value in `(int)` manually — that guard prevents crashes today,
  but the model is inconsistent with every other integer column and is a silent trap
  for future code that forgets the cast.
  Fix: add `'cache' => 'integer'` to `$casts` and `'cache'` to `$fillable`.

- [x] **DV-2 — `CombatController`: unguarded `json_decode()` can throw `TypeError`**
  `api/app/Http/Controllers/CombatController.php` — `result()` and `getResult()`
  Both methods call `json_decode($challenge->result_payload, true)` and pass the
  result directly to `array_merge(['resolved' => true], ...)`. If `result_payload`
  is ever stored as invalid JSON (partial write, truncation, manual DB edit), the
  decode returns `null` and `array_merge()` throws:
  `TypeError: array_merge(): Argument #2 must be of type array, null given`.
  Additionally, `CombatChallenge` stores the payload as `text` with no JSON cast,
  meaning the model offers no type safety. Fix: add `'result_payload' => 'array'`
  to `CombatChallenge::$casts`, and guard all `json_decode()` call sites with `?? []`.

- [x] **DV-3 — `PlayerController::activateCommand` validates `command_id` as `string`, not `uuid`**
  `api/app/Http/Controllers/PlayerController.php` — `activateCommand()`
  The `'command_id'` field is validated with `['required', 'string']`, while every
  other command/peripheral/challenge ID field in the codebase uses `'required|uuid'`.
  A non-UUID string passes validation and reaches `Player::commands()->where('commands.id', ...)`,
  which returns null and correctly yields a 422 — so no crash or security hole.
  But malformed input should fail at the validation layer, not silently downstream.
  Fix: change the rule to `['required', 'uuid']`.

- [x] **DV-4 — `NodeController::deplete` accesses `active_effects` array before null check**
  `api/app/Http/Controllers/NodeController.php` — `deplete()`
  `$player->active_effects` is cast as `array` in the `Player` model, but the DB
  column is nullable. When the column is NULL, Eloquent's array cast returns `null`
  (not `[]`). The line:
  ```php
  $ghostMovesLeft = (int) (($player->active_effects['ghost_protocol'] ?? 0));
  ```
  performs `null['ghost_protocol']` which emits a PHP warning (`Trying to access
  array offset on value of type null`) before the `??` catches it.
  Fix: change to `(($player->active_effects ?? [])['ghost_protocol'] ?? 0)`.
