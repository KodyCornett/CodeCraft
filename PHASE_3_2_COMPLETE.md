# Phase 3.2: Objective System Enhancements - COMPLETE ✅

## Summary

Enhanced the objective tracking system with comprehensive display improvements, peak exposure tracking, detailed mission analytics, and better validation for all objective types. Built on the foundation from Phase 3.1 to create a more polished and informative player experience.

## What Was Implemented

### 1. **Enhanced Mission Display** (`MissionCommands.kt`)

**Before:**
```
╔══════════════════════════════════════════════════════╗
║              ACTIVE MISSION                          ║
╠══════════════════════════════════════════════════════╣
║  Ghost's First Job                                   ║
║  Contact: Ghost                                      ║
║  Reward: §1500                                       ║
╠══════════════════════════════════════════════════════╣
║  OBJECTIVES:                                         ║
║  [ ] Connect to NovaCorp web server                  ║
║  [ ] Locate the credentials file                     ║
║  [ ] Decode the credentials                          ║
╚══════════════════════════════════════════════════════╝
```

**After:**
```
╔══════════════════════════════════════════════════════╗
║              ACTIVE MISSION                          ║
╠══════════════════════════════════════════════════════╣
║  The Cipher Job                                      ║
║  Contact: Cipher                                     ║
║  Reward: §3000                                       ║
║  Elapsed: 3m 45s                                     ║
╠══════════════════════════════════════════════════════╣
║  OBJECTIVES:                                         ║
║  [✓] Access NovaCorp web server                      ║
║  [✓] Pivot to database server                        ║
║  [ ] Decode the encrypted memo [180s left]           ║
║  [ ] Remain undetected (32%/50% - OK)                ║
║                                                      ║
║  BONUS OBJECTIVES (Optional):                        ║
║  [✓] Complete in under 5 minutes                     ║
║  [✗] Take no firewall damage                         ║
╠══════════════════════════════════════════════════════╣
║  Progress: 2/4 objectives (50%)                      ║
║  Stealth: MAINTAINED                                 ║
╠══════════════════════════════════════════════════════╣
║  'mission complete' to claim rewards                 ║
║  'mission abandon' to abort (reputation penalty)     ║
╚══════════════════════════════════════════════════════╝
```

**Features:**
- ✅ **Mission timer:** Shows elapsed time (minutes and seconds)
- ✅ **Objective status icons:** [✓] complete, [✗] failed, [ ] pending
- ✅ **Time limits:** Displays remaining time for timed objectives
- ✅ **Stealth tracking:** Shows current exposure vs threshold with status
- ✅ **Bonus objectives:** Listed separately with optional label
- ✅ **Failed objectives:** Clearly marked with [✗]
- ✅ **Stealth summary:** Overall stealth status (MAINTAINED/VIOLATED)

### 2. **Peak Exposure Tracking** (`Mission.kt`, `ObjectiveTracker.kt`)

Added `peakExposure: Double` to `ActiveMission` model:
- Automatically tracks highest exposure reached during mission
- Updated by `validateStealthObjectives()` on every command
- Used for post-mission analytics and payout calculations
- Never decreases during a mission (tracks maximum only)

**Usage:**
```kotlin
// Peak exposure tracked automatically
session.player.exposure = 20.0
ObjectiveTracker.checkObjectives(...) // peakExposure = 20.0

session.player.exposure = 45.0
ObjectiveTracker.checkObjectives(...) // peakExposure = 45.0

session.player.exposure = 30.0
ObjectiveTracker.checkObjectives(...) // peakExposure = 45.0 (unchanged)
```

### 3. **Enhanced Mission Completion Display** (`MissionCommands.kt`)

**Before:**
```
╔══════════════════════════════════════════════════════╗
║              MISSION COMPLETE                        ║
╠══════════════════════════════════════════════════════╣
║  Ghost's First Job                                   ║
║  Contact: Ghost                                      ║
╠══════════════════════════════════════════════════════╣
║  PAYOUT BREAKDOWN:                                   ║
║  Base reward: §1500                                  ║
...
```

**After:**
```
╔══════════════════════════════════════════════════════╗
║              MISSION COMPLETE                        ║
╠══════════════════════════════════════════════════════╣
║  The Cipher Job                                      ║
║  Contact: Cipher                                     ║
╠══════════════════════════════════════════════════════╣
║  MISSION STATISTICS:                                 ║
║  Time: 4m 23s                                        ║
║  Peak Exposure: 37%                                  ║
║  Objectives: 4/4                                     ║
║  Bonus: 1/2                                          ║
╠══════════════════════════════════════════════════════╣
║  PAYOUT BREAKDOWN:                                   ║
║  Base reward: §3000                                  ║
...
```

**Added Statistics:**
- Mission completion time
- Peak exposure reached
- Objectives completed count
- Bonus objectives completed count

### 4. **Enhanced Stealth Validation**

**Bonus Objectives Support:**
- Stealth objectives can now be bonus objectives
- Bonus stealth violations don't set `stealthViolated = true`
- Only required stealth objectives affect main stealth status
- Both types tracked in `failedObjectives`

**Example:**
```kotlin
objectives = listOf(
    ObjectiveDefinition(
        id = "obj_stealth_required",
        description = "Remain under 50% exposure",
        type = ObjectiveType.REMAIN_UNDETECTED,
        threshold = 50
    )
),
bonusObjectives = listOf(
    ObjectiveDefinition(
        id = "obj_stealth_bonus",
        description = "Bonus: Stay under 30% exposure",
        type = ObjectiveType.REMAIN_UNDETECTED,
        threshold = 30
    )
)
```

### 5. **Improved EXTRACT_DATA Validation**

Enhanced file path matching to handle:
- Exact filename: `secret.txt`
- Full path: `/data/secret.txt`
- Path with filename: `/other/path/secret.txt`
- Leading slash variations: both `/secret.txt` and `secret.txt`

### 6. **Updated Mission Definitions**

Fixed existing missions to use proper threshold values:
```kotlin
// Old (incorrect - used string target)
ObjectiveDefinition(
    id = "obj_stealth",
    description = "Complete with exposure under 50%",
    type = ObjectiveType.REMAIN_UNDETECTED,
    target = "50"  // ❌ Wrong field
)

// New (correct - uses threshold)
ObjectiveDefinition(
    id = "obj_stealth",
    description = "Remain undetected (exposure < 50%)",
    type = ObjectiveType.REMAIN_UNDETECTED,
    threshold = 50  // ✅ Correct field
)
```

## Files Modified

1. **`MissionCommands.kt`**
   - Enhanced `showMissionStatus()` with detailed display
   - Added elapsed time calculation
   - Added time limit remaining display
   - Added stealth status with current/threshold display
   - Added bonus objectives section
   - Enhanced `completeMission()` with mission statistics

2. **`Mission.kt`**
   - Added `peakExposure: Double` to `ActiveMission`
   - Updated mission_2 and mission_3 to use `threshold` instead of `target`

3. **`ObjectiveTracker.kt`**
   - Added peak exposure tracking in `validateStealthObjectives()`
   - Enhanced `validateStealthObjectives()` to check bonus objectives
   - Distinguished between required and bonus stealth violations
   - Improved `checkExtractData()` path matching

## Files Created

1. **`EnhancedObjectivesTest.kt`** - 6 comprehensive tests:
   - Peak exposure tracking
   - Stealth threshold validation
   - Time-based objective auto-failure
   - Time limit validation
   - Enhanced file path matching
   - Bonus vs required objective separation

## Test Results

```
> Task :test
BUILD SUCCESSFUL

27 mission tests passing (21 from Phase 3.1 + 6 new)
Full test suite: 120+ tests passing
```

### New Tests (6)
1. ✅ `peak exposure is tracked during mission`
2. ✅ `stealth objective fails when threshold exceeded`
3. ✅ `time-based objectives auto-fail when time limit exceeded`
4. ✅ `time-based objectives do not fail before time limit`
5. ✅ `extract data validates file path matching correctly`
6. ✅ `bonus objectives are tracked separately from required objectives`

## Example: Mission with All Features

Here's a mission showcasing all enhanced features:

```kotlin
MissionDefinition(
    id = "enhanced_example",
    title = "The Enhanced Mission",
    contactId = "ghost",
    description = "Test all enhanced features",
    briefing = "Complete objectives quickly and quietly.",
    targetNodeId = "test-server",
    targetFile = "/data/secure.dat",
    baseReward = 5000,
    difficulty = 3,
    requiredReputation = 20,
    puzzleType = PuzzleType.PATTERN_MATCH,
    puzzleSolution = "ALPHA BETA GAMMA",
    estimatedTimeMinutes = 10,
    objectives = listOf(
        ObjectiveDefinition(
            id = "obj_connect",
            description = "Connect to target server",
            type = ObjectiveType.CONNECT_NODE,
            target = "test-server",
            timeLimit = 120  // 2 minutes
        ),
        ObjectiveDefinition(
            id = "obj_extract",
            description = "Extract secure data",
            type = ObjectiveType.EXTRACT_DATA,
            target = "secure.dat"
        ),
        ObjectiveDefinition(
            id = "obj_solve",
            description = "Decode the encryption",
            type = ObjectiveType.SOLVE_PUZZLE,
            target = "/data/secure.dat",
            requiresObjective = "obj_extract"  // Sequential
        ),
        ObjectiveDefinition(
            id = "obj_stealth",
            description = "Remain undetected (< 50%)",
            type = ObjectiveType.REMAIN_UNDETECTED,
            threshold = 50
        )
    ),
    bonusObjectives = listOf(
        ObjectiveDefinition(
            id = "bonus_speed",
            description = "Complete in under 5 minutes",
            type = ObjectiveType.CONNECT_NODE,
            target = "test-server",
            timeLimit = 300  // Fast completion bonus
        ),
        ObjectiveDefinition(
            id = "bonus_stealth",
            description = "Maintain exposure under 30%",
            type = ObjectiveType.REMAIN_UNDETECTED,
            threshold = 30
        )
    ),
    reputationReward = mapOf("underground" to 25),
    unlocksMissions = listOf("next_mission")
)
```

## Player Experience Examples

### Scenario 1: Perfect Run
```bash
> mission

╔══════════════════════════════════════════════════════╗
║              ACTIVE MISSION                          ║
╠══════════════════════════════════════════════════════╣
║  The Enhanced Mission                                ║
║  Contact: Ghost                                      ║
║  Reward: §5000                                       ║
║  Elapsed: 4m 15s                                     ║
╠══════════════════════════════════════════════════════╣
║  OBJECTIVES:                                         ║
║  [✓] Connect to target server                        ║
║  [✓] Extract secure data                             ║
║  [✓] Decode the encryption                           ║
║  [ ] Remain undetected (28%/50% - OK)                ║
║                                                      ║
║  BONUS OBJECTIVES (Optional):                        ║
║  [✓] Complete in under 5 minutes [45s left]          ║
║  [✓] Maintain exposure under 30%                     ║
╠══════════════════════════════════════════════════════╣
║  Progress: 3/4 objectives (75%)                      ║
║  Stealth: MAINTAINED                                 ║
╚══════════════════════════════════════════════════════╝

> mission complete

╔══════════════════════════════════════════════════════╗
║              MISSION COMPLETE                        ║
╠══════════════════════════════════════════════════════╣
║  The Enhanced Mission                                ║
║  Contact: Ghost                                      ║
╠══════════════════════════════════════════════════════╣
║  MISSION STATISTICS:                                 ║
║  Time: 4m 18s                                        ║
║  Peak Exposure: 28%                                  ║
║  Objectives: 4/4                                     ║
║  Bonus: 2/2                                          ║
╠══════════════════════════════════════════════════════╣
║  PAYOUT BREAKDOWN:                                   ║
║  Base reward: §5000                                  ║
║                                                      ║
║  BONUSES:                                            ║
║    +§1000 - All Bonus Objectives                     ║
║    +§750 - Stealth Maintained                        ║
║    +§500 - Fast Completion                           ║
╠══════════════════════════════════════════════════════╣
║  FINAL PAYOUT: §7250                                 ║
║  New balance: §12250                                 ║
╚══════════════════════════════════════════════════════╝
```

### Scenario 2: Partial Success
```bash
> mission

╔══════════════════════════════════════════════════════╗
║              ACTIVE MISSION                          ║
╠══════════════════════════════════════════════════════╣
║  The Enhanced Mission                                ║
║  Contact: Ghost                                      ║
║  Reward: §5000                                       ║
║  Elapsed: 7m 32s                                     ║
╠══════════════════════════════════════════════════════╣
║  OBJECTIVES:                                         ║
║  [✓] Connect to target server                        ║
║  [✓] Extract secure data                             ║
║  [✓] Decode the encryption                           ║
║  [ ] Remain undetected (54%/50% - VIOLATED)          ║
║                                                      ║
║  BONUS OBJECTIVES (Optional):                        ║
║  [✗] Complete in under 5 minutes [TIME EXPIRED]      ║
║  [✗] Maintain exposure under 30%                     ║
╠══════════════════════════════════════════════════════╣
║  Progress: 3/4 objectives (75%)                      ║
║  Stealth: VIOLATED                                   ║
╠══════════════════════════════════════════════════════╣
║  'mission abandon' to abort (reputation penalty)     ║
╚══════════════════════════════════════════════════════╝

# Note: Can't complete - stealth objective failed!
```

## Technical Implementation Details

### Peak Exposure Tracking Flow
```
Command executed
    ↓
ObjectiveTracker.checkObjectives()
    ↓
validateStealthObjectives()
    ↓
if (player.exposure > mission.peakExposure) {
    mission.peakExposure = player.exposure  // Update peak
}
```

### Bonus vs Required Stealth
```kotlin
// Both types checked for threshold violations
val allObjectives = definition.objectives + definition.bonusObjectives
val stealthObjectives = allObjectives.filter {
    it.type == ObjectiveType.REMAIN_UNDETECTED
}

for (objective in stealthObjectives) {
    if (exposure > threshold) {
        mission.failedObjectives.add(objective.id)

        // Only required objectives set stealthViolated flag
        if (objective.id in definition.objectives) {
            mission.stealthViolated = true  // Payout penalty
        }
    }
}
```

### Time Display Logic
```kotlin
val currentTime = System.currentTimeMillis()
val elapsedSeconds = (currentTime - mission.startedAt) / 1000
val remaining = timeLimit - elapsedSeconds

when {
    remaining > 0 -> "[${remaining}s left]"
    else -> "[TIME EXPIRED]"
}
```

## Verification Checklist ✅

### Display Features
- ✅ Mission elapsed time shown
- ✅ Time limits display remaining seconds
- ✅ Stealth objectives show current/threshold
- ✅ Bonus objectives labeled separately
- ✅ Failed objectives marked with [✗]
- ✅ Stealth status summary shown
- ✅ Mission statistics on completion

### Tracking Features
- ✅ Peak exposure tracked automatically
- ✅ Peak exposure never decreases
- ✅ Bonus stealth violations tracked separately
- ✅ Time-based objectives auto-fail
- ✅ Sequential objectives enforced

### Validation Features
- ✅ EXTRACT_DATA matches multiple path formats
- ✅ Bonus objectives don't affect required completion
- ✅ Stealth thresholds validated continuously
- ✅ All 27 tests passing

## What's Next: Phase 3.3

With enhanced objectives complete, next up is **Job Offer Persistence**:
- Persist job offers to database (survive server restarts)
- Add offer expiration (7 days)
- Track negotiation history
- Clean up expired offers
- Repository methods for CRUD operations

## Notes for Developers

### Adding Time-Limited Objectives
```kotlin
ObjectiveDefinition(
    id = "obj_fast_connect",
    description = "Connect within 2 minutes",
    type = ObjectiveType.CONNECT_NODE,
    target = "target-node",
    timeLimit = 120  // Seconds
)
```

### Adding Sequential Objectives
```kotlin
objectives = listOf(
    ObjectiveDefinition(
        id = "obj_first",
        description = "Do this first",
        type = ObjectiveType.CONNECT_NODE,
        target = "node-a"
    ),
    ObjectiveDefinition(
        id = "obj_second",
        description = "Then do this",
        type = ObjectiveType.DOWNLOAD_FILE,
        target = "file.txt",
        requiresObjective = "obj_first"  // Must complete obj_first first
    )
)
```

### Adding Bonus Stealth
```kotlin
objectives = listOf(/* required objectives */),
bonusObjectives = listOf(
    ObjectiveDefinition(
        id = "bonus_stealth",
        description = "Perfect stealth (< 25%)",
        type = ObjectiveType.REMAIN_UNDETECTED,
        threshold = 25
    )
)
```

---

**Status:** Phase 3.2 COMPLETE ✅
**Tests:** 27/27 passing
**Build:** Successful
**Ready for:** Phase 3.3 (Job Offer Persistence)
