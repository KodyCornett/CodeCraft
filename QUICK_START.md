# Quick Start — Decryption System Test

## Current Status ✅

**IMPORTANT:** The Kotlin engine is required and must be running for the game to function.

**Kotlin Engine:** Port 8085 (required)
**Laravel Web:** Port 8000
**Decryption System:** Fully implemented and ready

---

## How to Test Right Now

### 1. Open the Game
Open your browser: **http://localhost:8000**

### 2. Complete the Tutorial
Run these commands in the terminal:
```
ls
cd documents
cat mission_briefing.txt
mail
```

### 3. Watch the Decryption
- Tutorial completes → Ghost sends message
- Open **Jobs Board** app (briefcase icon)
- Click **BOARD** tab
- You'll see **"Ghost's First Job"** (encrypted)
- Click the mission card → Shows "[ENCRYPTED - CLICK TO DECRYPT]"
- Click again → **Decryption animation plays** (1.5 seconds)
  - Progress bar animates 0% → 100%
  - Hex noise flickers
  - "[DECRYPTING SIGNAL...]" pulses
- Mission details appear
- Click **"ACCEPT MISSION"** button

---

## If You Want to Restart the Engine

### Option A: Stop and Restart
```powershell
# PowerShell commands:
taskkill /F /PID 37032
cd C:\dev\CodeCraft\engine
./gradlew run
```

### Option B: Just Use What's Running
**The engine is already working perfectly!** No need to restart unless you made code changes.

---

## Verify Everything Is Working

### Test Engine Health
```bash
curl http://localhost:8085/health
```

**Expected Response:**
```json
{
    "status": "ok",
    "engine": "CodeCraft",
    "version": "0.1.0",
    "sessions": 8
}
```

### Test Command Execution
```bash
curl -X POST http://localhost:8085/api/command \
  -H "Content-Type: application/json" \
  -d '{"sessionId":"test","command":"help"}'
```

Should return the help text with available commands.

### Test Laravel Connection
```bash
curl http://localhost:8000
```

Should return the game UI HTML.

---

## Common Issues

### "Address already in use: bind"
- **Cause:** Engine is already running
- **Solution:** Either use the running instance OR kill it with `taskkill /F /PID 37032`

### "Connection refused" from Laravel
- **Cause:** Engine not running
- **Solution:** Start the engine with `./gradlew run`

### Session state lost
- **Cause:** Browser refresh clears session
- **Solution:** This is normal for session-based storage. Re-decrypt missions (takes 1.5s)

---

## What You Should See

### Empty State (Before Tutorial)
```
┌─────────────────────────────────────┐
│           JOBS BOARD                │
├─────────────────────────────────────┤
│  ACTIVE  │  BOARD  │  HISTORY       │
├─────────────────────────────────────┤
│                                     │
│   [SYSTEM] NO ENCRYPTED SIGNALS     │
│            DETECTED                 │
│                                     │
│   Complete objectives to unlock     │
│   new jobs                          │
│                                     │
└─────────────────────────────────────┘
```

### After Tutorial (mission_1 unlocked)
```
┌─────────────────────────────────────┐
│  BOARD                              │
├─────────────────────────────────────┤
│  ┌───────────────────────────────┐ │
│  │ Ghost's First Job             │ │
│  │ [Ghost]           §1500  EASY │ │
│  │                               │ │
│  │ [ENCRYPTED - CLICK TO DECRYPT]│ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

### During Decryption
```
┌─────────────────────────────────────┐
│  ┌───────────────────────────────┐ │
│  │ [DECRYPTING SIGNAL...]        │ │
│  │ ▓▓▓▓▓▓▓▓▓▓░░░░░░░░  65%       │ │
│  │                               │ │
│  │ A3F2B8E1C4D7F9A2B5C8E1F4A7... │ │
│  │ D6F3A8B2E5C1F7A4B9D2E6F8A3... │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

### After Decryption
```
┌─────────────────────────────────────┐
│  ┌───────────────────────────────┐ │
│  │ Ghost's First Job             │ │
│  │ [Ghost]           §1500  EASY │ │
│  │                               │ │
│  │ Extract employee data from    │ │
│  │ NovaCorp web server...        │ │
│  │                               │ │
│  │ OBJECTIVES:                   │ │
│  │ • Connect to nova-corp-web    │ │
│  │ • Locate employee records     │ │
│  │                               │ │
│  │ [ACCEPT MISSION] ←── Click!   │ │
│  └───────────────────────────────┘ │
└─────────────────────────────────────┘
```

---

## Next Steps After Testing

1. **Test mission progression:** Complete mission_1 → mission_2 unlocks
2. **Test branching:** Complete mission_7 → both mission_9a AND mission_10 appear
3. **Test persistence:** Click away and re-select mission → details show instantly
4. **Test empty state:** Clear session storage → board shows "NO ENCRYPTED SIGNALS DETECTED"

---

## Documentation

- **Full implementation details:** `JOBS_BOARD_DECRYPTION_IMPLEMENTATION.md`
- **Summary:** `DECRYPTION_COMPLETE.md`
- **This quick start:** `QUICK_START.md`

**Everything is ready!** Just open http://localhost:8000 and start playing! 🎮
