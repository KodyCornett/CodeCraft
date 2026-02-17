# Jobs Board Decryption & Mission Visibility - Testing Guide

## Implementation Summary

Successfully implemented the decryption mechanic for the Jobs Board with the following features:

### Backend Changes (Laravel)

1. **`MissionService.php`** - Extended with visibility tracking:
   - `markMissionVisible(string $missionId)` - Makes mission appear on board
   - `markMissionDecrypted(string $missionId)` - Tracks decryption state
   - `getVisibleMissions()` - Returns list of visible mission IDs
   - `isMissionDecrypted(string $missionId)` - Checks decryption status
   - `getAvailableJobsForBoard()` - Filters missions by visibility

2. **`MissionController.php`** - Added decryption endpoint:
   - `POST /api/mission/{missionId}/decrypt` - New endpoint to mark mission as decrypted
   - Updated `getAvailableMissions()` to:
     - Filter by visibility (only show missions marked visible)
     - Add `decrypted: true/false` flag to each mission

3. **`TerminalController.php`** - Auto-mark missions visible:
   - Updated `getMissionCompletionMessages()` to call `markMissionVisible()` for each unlocked mission
   - Mission visibility is now triggered automatically when missions are unlocked

4. **`routes/web.php`** - Added decrypt route:
   - `POST /api/mission/{missionId}/decrypt`

### Frontend Changes (Alpine.js + Blade)

1. **`jobs-board.js`** - Decryption animation logic:
   - Added state: `decryptingMissionId`, `decryptionProgress`
   - `selectMission(missionId)` - Replaces `toggleJob`, triggers decryption on first view
   - `startDecryptionAnimation(missionId)` - 1.5-second animation with progress bar
   - `isDecrypting(missionId)` - Check if mission is currently decrypting
   - `getDecryptionProgress(missionId)` - Returns 0-100 progress
   - `generateHexNoise()` - Visual effect during decryption

2. **`jobs-board.blade.php`** - Updated UI:
   - Empty state: "NO ENCRYPTED SIGNALS DETECTED" when no visible missions
   - Decryption overlay with:
     - "[ENCRYPTED - CLICK TO DECRYPT]" prompt
     - "[DECRYPTING SIGNAL...]" animation
     - Progress bar (0-100%)
     - Hex noise visual effect
   - Mission details only shown after decryption completes
   - "ACCEPT MISSION" button only appears after decryption

---

## Testing Flow

### Prerequisites
1. Start Kotlin engine: `cd engine && ./gradlew run`
2. Start Laravel server: `cd web && composer run dev`
3. Open browser: `http://localhost:8000`

### Test 1: Fresh Start (No Visible Missions)

**Expected Behavior:**
- Open Jobs Board → BOARD tab
- Should display: `[SYSTEM] NO ENCRYPTED SIGNALS DETECTED`
- No mission cards visible

**Verify:**
- Empty state message is centered and styled correctly
- No missions in the available jobs list

---

### Test 2: Complete Tutorial to Unlock First Mission

**Steps:**
1. Open Terminal
2. Run commands:
   ```
   ls
   cd documents
   cat mission_briefing.txt
   mail
   ```
3. Check Messages app → Ghost's message should appear
4. Open Jobs Board → BOARD tab

**Expected Behavior:**
- `mission_1` appears on the board (unlocked by tutorial_basics completion)
- Mission card shows:
  - Title, contact, reward, difficulty
  - Card is clickable but NOT expanded initially

**Verify:**
- Mission appears immediately after tutorial completion
- No decryption overlay until card is clicked

---

### Test 3: Decryption Animation (First View)

**Steps:**
1. Click on mission_1 card

**Expected Behavior (Animation Sequence):**
1. Card expands
2. Shows: `[ENCRYPTED - CLICK TO DECRYPT]`
3. Immediately starts decryption animation:
   - Text changes to `[DECRYPTING SIGNAL...]` (pulsing)
   - Progress bar animates 0% → 100% (1.5 seconds)
   - Hex noise flickers in background
4. After 1.5 seconds:
   - Decryption overlay disappears
   - Mission details appear (description, objectives, briefing)
   - "ACCEPT MISSION" button visible

**Verify:**
- Animation is smooth and takes ~1.5 seconds
- Progress bar fills completely
- Hex noise is visible and random
- Backend receives `POST /api/mission/mission_1/decrypt` call
- Session stores `mission_1` in `game_decrypted_missions`

---

### Test 4: Persistent Decryption (Re-Select Mission)

**Steps:**
1. Click away from mission_1 (deselect)
2. Click mission_1 again

**Expected Behavior:**
- Mission details appear **immediately** (no animation)
- No decryption overlay shown
- "ACCEPT MISSION" button visible immediately

**Verify:**
- No re-decryption animation on subsequent views
- Details are cached in `job.decrypted = true`

---

### Test 5: Accept Mission Flow

**Steps:**
1. With mission_1 decrypted and expanded, click "ACCEPT MISSION"

**Expected Behavior:**
- Button shows "PROCESSING..." (pulsing)
- After ~0.8s, switches to ACTIVE tab
- Mission appears in ACTIVE tab with objectives
- BOARD tab still shows mission_1 but with "IN PROGRESS — see ACTIVE tab"

**Verify:**
- Cannot accept multiple missions (button disabled if one active)
- Mission state persists in Kotlin engine

---

### Test 6: Multiple Missions (Branching Paths)

**Steps:**
1. Complete mission_1 through mission_7 (follow mission objectives)
2. After mission_7, both `mission_8` unlocks (accept Hale's offer)
3. After mission_8, both `mission_9a` AND `mission_10` unlock

**Expected Behavior:**
- Jobs Board shows multiple missions simultaneously
- Each mission requires separate decryption animation on first view
- Player can decrypt both, then choose which to accept

**Verify:**
- Both missions appear in BOARD tab
- Each has independent decryption state
- Only one can be accepted at a time

---

### Test 7: Session Persistence (Decryption State)

**Setup:**
1. Decrypt mission_1 (do NOT accept it)
2. Refresh browser (F5)

**Expected Behavior:**
- **Decryption state is LOST** (stored in Laravel session)
- Mission_1 still visible on board (visibility is session-based)
- Clicking mission_1 again triggers decryption animation again

**Known Limitation:**
- Decryption state does NOT survive browser refresh (by design, session-based)
- Visibility DOES survive refresh (same session)

**Future Enhancement:**
- Migrate decryption state to Kotlin engine DB for persistence

---

## API Endpoint Testing

### Test Decrypt Endpoint Directly

```bash
# Start a session by opening the game in browser first
# Get session cookie from browser dev tools

curl -X POST http://localhost:8000/api/mission/mission_1/decrypt \
  -H "Cookie: laravel_session=YOUR_SESSION_COOKIE" \
  -H "X-CSRF-TOKEN: YOUR_CSRF_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response:**
```json
{
  "success": true,
  "missionId": "mission_1",
  "decrypted": true
}
```

### Test Get Available Missions (with visibility filter)

```bash
curl http://localhost:8000/api/missions/available \
  -H "Cookie: laravel_session=YOUR_SESSION_COOKIE"
```

**Expected Response (before tutorial complete):**
```json
{
  "success": true,
  "missions": []
}
```

**Expected Response (after tutorial complete):**
```json
{
  "success": true,
  "missions": [
    {
      "id": "mission_1",
      "title": "NovaCorp Reconnaissance",
      "contact": "Ghost",
      "difficulty": 2,
      "baseReward": 1500,
      "description": "...",
      "objectives": [...],
      "decrypted": false  // ← New field
    }
  ]
}
```

---

## Visual Verification Checklist

### Decryption Overlay Appearance
- [ ] Blue border (`border-blue-500/30`)
- [ ] Semi-transparent black background (`bg-black/50`)
- [ ] Centered text with pulse animation
- [ ] Progress bar: gray background, blue fill
- [ ] Hex noise: green text (`text-green-500/30`)

### Empty State
- [ ] Centered message: "NO ENCRYPTED SIGNALS DETECTED"
- [ ] Muted text color
- [ ] Appears only when `availableJobs.length === 0`

### Mission Card (Decrypted)
- [ ] Details fade in smoothly after animation
- [ ] "ACCEPT MISSION" button has cyan accent
- [ ] Button disabled when another mission is active

---

## Known Issues / Edge Cases

### 1. Browser Refresh Loses Decryption State
**Status:** By design (session-based storage)
**Workaround:** Re-decrypt on refresh (animation is fast)
**Future Fix:** Store in Kotlin engine DB

### 2. Rapid Clicking During Decryption
**Status:** Animation continues if user clicks away and re-clicks
**Workaround:** Let animation complete naturally
**Fix:** Could add animation cancellation on deselect

### 3. No Audio Feedback
**Status:** Out of scope for v1
**Future Enhancement:** Add decryption "beep" sound effects

---

## Success Criteria

✅ All backend files compile without syntax errors
✅ All routes registered correctly
✅ Frontend builds successfully (Vite)
✅ Empty state shows when no missions visible
✅ Decryption animation plays on first view (1.5s)
✅ Mission details appear after decryption
✅ Decryption state persists within session
✅ Multiple missions can be decrypted independently
✅ Accept button only appears after decryption

---

## Next Steps (Optional Enhancements)

1. **Persistent Decryption State:**
   - Add `decrypted_missions` column to Kotlin engine DB
   - Store decryption timestamps for analytics

2. **Different Animation Speeds per Contact:**
   - Ghost: fast (1s)
   - Cipher: complex (2s with glitch effects)
   - Hale: instant (government clearance)

3. **Audio Integration:**
   - Decryption beeps (typewriter sound)
   - Success chime on completion

4. **Secure Channel Integration:**
   - Some missions require puzzle-solving BEFORE decryption
   - Two-stage unlock: decrypt message → solve puzzle → decrypt job details

---

## Files Modified

### Backend (PHP)
- `web/app/Services/Mission/MissionService.php` (extended)
- `web/app/Http/Controllers/Api/MissionController.php` (decrypt endpoint added)
- `web/app/Http/Controllers/Api/TerminalController.php` (auto-mark visible)
- `web/routes/web.php` (new route)

### Frontend (JS + Blade)
- `web/resources/js/desktop/jobs-board.js` (animation logic)
- `web/resources/views/desktop/windows/jobs-board.blade.php` (UI updates)

### Documentation
- `JOBS_BOARD_DECRYPTION_TEST.md` (this file)

---

## Build Commands

```bash
# Backend (no rebuild needed, interpreted language)
cd web
php artisan route:list | grep mission  # Verify routes

# Frontend (rebuild after JS changes)
cd web
npm run build  # Production build
npm run dev    # Development with hot reload

# Kotlin Engine (if modifying mission unlock logic)
cd engine
./gradlew build
./gradlew run
```

---

## Debugging Tips

### Mission Not Appearing on Board
1. Check session: `session()->get('game_visible_missions')`
2. Verify mission unlocked in Kotlin engine: `GET /api/missions/available`
3. Check if `markMissionVisible()` was called in completion message handler

### Decryption Animation Not Starting
1. Check `job.decrypted` flag in frontend state
2. Verify `selectMission()` is triggered on card click
3. Check browser console for errors in `startDecryptionAnimation()`

### Backend Errors
1. Check Laravel logs: `web/storage/logs/laravel.log`
2. Enable debug mode: `APP_DEBUG=true` in `web/.env`
3. Test endpoint directly with curl (see API testing section)

---

## Session Storage Schema

```php
// After completing tutorial_basics:
session()->get('game_visible_missions')
// → ['mission_1']

// After decrypting mission_1:
session()->get('game_decrypted_missions')
// → ['mission_1']

// After unlocking multiple missions:
session()->get('game_visible_missions')
// → ['mission_1', 'mission_2', 'mission_3']

session()->get('game_decrypted_missions')
// → ['mission_1', 'mission_2']  // only decrypted ones
```

---

## Implementation Complete ✅

The Jobs Board decryption system has been successfully implemented with:
- ✅ Session-based visibility tracking
- ✅ Decryption animation (1.5s with progress bar)
- ✅ Persistent decryption state (within session)
- ✅ Empty state UI when no missions visible
- ✅ Auto-unlock on mission completion
- ✅ Accept button gated behind decryption

Ready for testing and user feedback!
