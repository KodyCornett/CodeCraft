# Jobs Board Decryption Feature - Implementation Complete

## Overview

The Jobs Board now features a **mission decryption mechanic** where missions are:
1. **Hidden by default** until explicitly triggered (e.g., via mission completion)
2. **Encrypted on first view** with a 1.5-second decryption animation
3. **Persistently decrypted** once viewed (no re-animation on subsequent views)
4. **Only acceptable** after decryption completes

This creates an immersive "hacker receiving encrypted job offers" experience.

---

## Implementation Summary

### 1. Backend: Mission Visibility Tracking (Laravel Session)

**File:** `web/app/Services/Mission/MissionService.php`

Added methods (lines 438-490):
- `markMissionVisible(string $missionId)` - Mark mission as visible on Jobs Board
- `markMissionDecrypted(string $missionId)` - Mark mission as decrypted (details viewed)
- `getVisibleMissions()` - Get list of visible mission IDs
- `isMissionDecrypted(string $missionId)` - Check if mission has been decrypted
- `getAvailableJobsForBoard(string $sessionId, array $allAvailable)` - Filter missions by visibility

**Session Keys:**
- `game_visible_missions` - Array of mission IDs visible on board
- `game_decrypted_missions` - Array of mission IDs that have been decrypted

---

### 2. Backend: API Endpoints

**File:** `web/app/Http/Controllers/Api/MissionController.php`

Added/Modified endpoints:
- `GET /api/missions/available` (lines 68-89) - Returns missions filtered by visibility with `decrypted` flag
- `POST /api/mission/{missionId}/decrypt` (lines 94-106) - Marks mission as decrypted

**File:** `web/routes/web.php`

Routes configured (lines 58-59):
```php
Route::post('/mission/{missionId}/decrypt', [MissionController::class, 'decrypt']);
Route::get('/missions/available', [MissionController::class, 'getAvailableMissions']);
```

---

### 3. Frontend: Decryption Animation

**File:** `web/resources/js/desktop/jobs-board.js`

Added state and methods (lines 14-206):
- State: `decryptingMissionId`, `decryptionProgress`
- `selectMission(missionId)` - Triggers decryption animation when mission clicked
- `startDecryptionAnimation(missionId)` - Animates progress bar 0% → 100% over 1.5s, calls backend
- `isDecrypting(missionId)` - Check if mission is currently decrypting
- `getDecryptionProgress(missionId)` - Get current progress (0-100)
- `generateHexNoise()` - Generate random hex noise for visual effect

**Animation Flow:**
1. User clicks encrypted mission card
2. Progress bar animates over 1.5 seconds
3. Hex noise flickers in background
4. Backend called to mark as decrypted
5. Local state updated: `mission.decrypted = true`
6. Mission details appear

---

### 4. Frontend: UI Updates

**File:** `web/resources/views/desktop/windows/jobs-board.blade.php`

Added UI components (lines 224-253):

**Decryption Overlay (when `!job.decrypted`):**
- Shows "ENCRYPTED - CLICK TO DECRYPT" before animation starts
- Shows animated progress bar during decryption
- Shows flickering hex noise for visual effect

**Mission Details (only after `job.decrypted`):**
- Description, briefing, objectives
- Difficulty stars and estimated time
- "ACCEPT MISSION" button

**Empty State:**
- Shows "[SYSTEM] NO ENCRYPTED SIGNALS DETECTED" when no visible missions

---

### 5. Integration: Mission Unlocking

**File:** `web/app/Http/Controllers/Api/TerminalController.php`

**On Mission Completion (lines 318-328):**
```php
foreach ($unlockedMissions as $unlockedId) {
    $missionService->markMissionVisible($unlockedId);
    $unlockMessage = $this->getJobUnlockMessage($unlockedId);
    if ($unlockMessage) {
        $messages[] = $unlockMessage;
    }
}
```

When a mission is completed in the Kotlin engine, it returns `unlockedMissions` array. For each unlocked mission:
1. Marks it as visible on Jobs Board
2. Injects a "New job available" message from the contact

**Bootstrap: Initial Mission (lines 57-67):**
```php
private function ensureInitialMissionVisible(): void
{
    if (!session()->has('game_initial_mission_visible')) {
        $missionService = app(\App\Services\Mission\MissionService::class);
        $missionService->markMissionVisible('mission_1');
        session()->put('game_initial_mission_visible', true);
    }
}
```

On first command execution, `mission_1` is automatically marked visible (no unlock needed).

---

## User Experience Flow

### First Boot

1. Player opens Jobs Board → BOARD tab
2. Sees: `mission_1` (marked visible on first boot)
3. Mission card shows title, contact, reward, difficulty
4. Click mission card → Shows `[ENCRYPTED - CLICK TO DECRYPT]`

### Decryption

1. Click mission card again → Decryption animation starts
2. `[DECRYPTING SIGNAL...]` pulses
3. Progress bar animates 0% → 100% over 1.5s
4. Hex noise flickers in background
5. Backend marks mission as decrypted
6. Mission details appear (description, objectives, accept button)

### Subsequent Views

1. Click away and re-select mission
2. Details show immediately (no re-decryption)
3. Backend session remembers: `mission_1` is in `game_decrypted_missions`

### Mission Unlocking

1. Complete `tutorial_basics` mission
2. Kotlin engine returns `unlockedMissions: ['mission_1']`
3. Laravel marks `mission_1` visible
4. Ghost sends "New job available" message
5. Open Jobs Board → `mission_1` appears (encrypted)
6. Player must decrypt to view details

---

## Testing Checklist

- [x] Fresh start: mission_1 visible on first boot
- [x] Click mission card: shows encrypted overlay
- [x] Click again: decryption animation plays
- [x] Progress bar: animates 0% → 100% over 1.5s
- [x] Hex noise: displays during animation
- [x] Post-decryption: details appear (description, objectives, accept button)
- [x] Re-select mission: details show immediately (no re-animation)
- [x] Backend: `game_decrypted_missions` session contains mission_1
- [x] Complete mission: unlocks new missions, marks them visible
- [x] New mission: appears encrypted on board
- [x] Empty board: shows "NO ENCRYPTED SIGNALS DETECTED"

---

## Architecture Notes

### Why Laravel Session Instead of Kotlin Engine DB?

**Decision:** Use Laravel session-based tracking for visibility/decryption state.

**Rationale:**
- ✅ Quick to implement (no engine schema change + migration)
- ✅ Fits existing session-based patterns (messages, secure channel)
- ✅ Faster iteration for testing
- ✅ Decryption animation is a UI flourish, not critical game state
- ❌ Lost on browser refresh (acceptable tradeoff for v1)

**Future Migration:**
If persistent state is needed, migrate to Kotlin engine:
1. Add `visibleMissions: Set<String>` and `decryptedMissions: Set<String>` to `Player`
2. Persist via `PlayerRepository` JSON serialization
3. Update `MissionController` to call engine instead of session

---

## Related Files Modified

### Backend (PHP/Laravel)
- `web/app/Services/Mission/MissionService.php` - Visibility/decryption tracking methods
- `web/app/Http/Controllers/Api/MissionController.php` - API endpoints
- `web/app/Http/Controllers/Api/TerminalController.php` - Bootstrap + unlock triggering
- `web/routes/web.php` - Route definitions

### Frontend (Blade/JavaScript)
- `web/resources/js/desktop/jobs-board.js` - Animation logic
- `web/resources/views/desktop/windows/jobs-board.blade.php` - UI components

### Engine (Kotlin)
- No changes required (mission unlocking already returns `unlockedMissions`)

---

## Completion Status

✅ **FULLY IMPLEMENTED**

All planned features from `IMPLEMENTATION_PLAN.md` have been completed:
1. ✅ Mission visibility tracking (session-based)
2. ✅ Decryption state tracking (session-based)
3. ✅ API endpoints (`/decrypt`, `/missions/available`)
4. ✅ Frontend animation (1.5s progress bar + hex noise)
5. ✅ UI components (encrypted overlay, mission details)
6. ✅ Mission unlocking integration (completion → visibility)
7. ✅ Bootstrap logic (mission_1 visible on first boot)

---

## Known Limitations

1. **Session-based persistence**: Decryption state lost on browser refresh (acceptable for v1)
2. **No audio effects**: Decryption beeps/success chimes not implemented (no audio system yet)
3. **Single animation style**: All contacts use same decryption animation (future: Ghost = fast, Cipher = complex)
4. **HISTORY tab placeholder**: Completed missions log not implemented yet

---

## Next Steps (Out of Scope)

- [ ] Migrate visibility/decryption state to Kotlin engine (persistent DB)
- [ ] Add audio effects for decryption animation
- [ ] Implement contact-specific animation styles
- [ ] Build HISTORY tab with completed missions log
- [ ] Add Secure Channel integration (puzzle-gated access)
