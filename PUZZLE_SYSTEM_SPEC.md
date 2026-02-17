# Puzzle System Spec v1.0

## Overview

The CodeCraft puzzle system handles three contexts:
- **Mission puzzles** — decrypt files, solve access codes (existing 9 types)
- **Node Access puzzles** — block node entry, require solving to connect (8 new types)
- **Combat puzzles** — triggered during sentinel/hacker attacks (8 new types)

---

## 1. Difficulty Model

```kotlin
enum class PuzzleDifficulty(val intValue: Int) {
    EASY(1), MEDIUM(2), HARD(3), EXPERT(4)
}
```

| Level  | intValue | Trigger Conditions                                  |
|--------|----------|-----------------------------------------------------|
| EASY   | 1        | exposure < 40, threatPressure < 25                  |
| MEDIUM | 2        | exposure 40–60, threatPressure 25–50                |
| HARD   | 3        | exposure 60–80, threatPressure 50–75                |
| EXPERT | 4        | exposure > 80 OR threatPressure > 75                |

`fromExposure(exposure, threatPressure)` combines both stats; higher stat wins.

---

## 2. Combat Puzzle Types (8)

Used during sentinel attacks and rival hacker intrusions.

### 2.1 MEMORY_FORENSICS
**Context:** Sentinel has injected a rogue process into memory. Find it.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: MEMORY FORENSICS              │
│  A process table is displayed. One or   │
│  more processes are rogue — high CPU,   │
│  suspicious paths, or unknown names.    │
│  TIP: Enter PIDs separated by spaces.  │
└──────────────────────────────────────────┘
```

**Generation:** Display process table (10–15 rows). 1–3 rogue processes have:
- CPU > 85%
- Path in /tmp, /var/tmp, or random hex names
- Name matching `[a-z]{2}[0-9]{4}` pattern

**Validation:** Player submits space-separated PIDs. Partial credit if ≥50% correct.

**Difficulty Variants:**
| Difficulty | Rogue PIDs | Total Processes |
|------------|-----------|-----------------|
| EASY       | 1         | 10              |
| MEDIUM     | 2         | 12              |
| HARD       | 2-3       | 14              |
| EXPERT     | 3         | 15              |

---

### 2.2 PORT_SCAN_INTERCEPTION
**Context:** A rogue connection is tunneling out on a non-standard port.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: PORT SCAN INTERCEPTION        │
│  A netstat-style table is shown. Ports  │
│  in ESTABLISHED state indicate active   │
│  connections. Most are noise.           │
│  TIP: Enter the suspicious port number. │
└──────────────────────────────────────────┘
```

**Generation:** 8–12 rows of port/state/remote-ip data. One port is ESTABLISHED to
a suspicious IP (non-RFC1918, non-standard port). Others are LISTEN, TIME_WAIT, CLOSE_WAIT.

**Validation:** Player submits the port number.

**Difficulty Variants:**
| Difficulty | Total Rows | Suspicious IPs |
|------------|-----------|----------------|
| EASY       | 8         | 1              |
| MEDIUM     | 10        | 1              |
| HARD       | 12        | 2 (one is decoy) |
| EXPERT     | 14        | 3 (one is answer) |

---

### 2.3 LOG_PATTERN_RECOGNITION
**Context:** Malicious activity is buried in system logs.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: LOG PATTERN RECOGNITION       │
│  A log block is displayed. One entry    │
│  contains an anomaly — wrong timestamp, │
│  suspicious path, unknown user.         │
│  TIP: Enter the distinctive keyword.   │
└──────────────────────────────────────────┘
```

**Generation:** 6–10 log lines, one injected malicious line. The answer is the
distinctive keyword/hostname/path that appears only in the malicious entry.

**Validation:** Player submits the keyword. Case-insensitive match.

---

### 2.4 PROCESS_KILL
**Context:** Multiple rogue processes must be terminated simultaneously.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: PROCESS KILL                  │
│  Enter the PIDs of ALL rogue processes  │
│  to terminate them simultaneously.      │
│  Missing one will allow recovery.       │
│  TIP: Separate multiple PIDs by spaces. │
└──────────────────────────────────────────┘
```

**Generation:** Same as MEMORY_FORENSICS but all rogue PIDs must be killed at once.
Partial credit not allowed — requires exact match.

---

### 2.5 PACKET_INSPECTION
**Context:** Intercept and identify a suspicious TCP packet by its flags.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: PACKET INSPECTION             │
│  A packet header is shown with a TCP   │
│  flags byte (hex). Decode the active   │
│  flags from the bitmask.               │
│  TIP: SYN=0x02, ACK=0x10, RST=0x04.  │
└──────────────────────────────────────────┘
```

**Generation:** Display IP/TCP header. Flags byte is hex. Answer = space-separated
flag names in order: URG ACK PSH RST SYN FIN (only active flags).

**Difficulty Variants:**
| Difficulty | Flag Count | Decoy Headers |
|------------|-----------|---------------|
| EASY       | 1-2       | 0             |
| MEDIUM     | 2-3       | 1             |
| HARD       | 3-4       | 2             |
| EXPERT     | 4-5       | 3             |

---

### 2.6 ENCRYPTION_CRACKING
**Context:** Counter an incoming encrypted command by decoding it.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: ENCRYPTION CRACKING           │
│  The attacker's command is Caesar-      │
│  encrypted. Decode the plaintext.       │
│  The shift value is shown as a hint.   │
│  TIP: Apply the shift to each letter.  │
└──────────────────────────────────────────┘
```

**Generation:** Reuses `CaesarCipherPuzzle` logic. Shift varies by difficulty.
Answer = decoded plaintext (uppercase).

---

### 2.7 TRACE_ROUTE
**Context:** Identify a missing hop in an attack trace route.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: TRACE ROUTE                   │
│  A traceroute output has one hop        │
│  replaced with ???. Reconstruct the    │
│  missing IP from the sequence.          │
│  TIP: Hop IPs often follow subnet      │
│  patterns (last octet increments).     │
└──────────────────────────────────────────┘
```

**Generation:** 4–6 hop IPs where one is replaced with `???`. The missing IP
follows a subnet arithmetic pattern (e.g., last octet +1 per hop within subnet).

**Validation:** Player submits the missing IP in `x.x.x.x` format.

---

### 2.8 REGEX_FILTERING
**Context:** Write a filter to block threat IPs from a log stream.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: REGEX FILTERING               │
│  A log block contains benign and        │
│  malicious entries. Enter a keyword    │
│  or simple pattern that matches ONLY   │
│  the malicious lines.                  │
│  TIP: Focus on IP ranges or path names. │
└──────────────────────────────────────────┘
```

**Generation:** 8–12 log lines. 2–4 malicious entries share a common prefix/
pattern (same /24 subnet, same path prefix, same username). Answer = the
distinguishing pattern.

**Partial Credit:** Matching some but not all malicious lines awards 50% credit.

---

## 3. Node Access Puzzle Types (8)

Used when connecting to protected nodes. These replace or supplement connection
puzzles. `exposureCost` is the exposure gained on success; `failurePenalty` is
extra exposure on wrong answer.

### 3.1 PASSWORD_CRACKING
**Context:** A password hash is displayed. Crack it from a wordlist.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: PASSWORD CRACKING             │
│  A password hash + salt is shown.       │
│  Match it against common passwords.    │
│  Two paths: submit plaintext OR hash.  │
│  TIP: Common passwords: admin, root,   │
│  password, secure, default.            │
└──────────────────────────────────────────┘
```

**Generation:** Pick from wordlist [admin, root, secure, password, default, guest,
12345, access, server, master]. Display salted hash (SHA-256 hex prefix, truncated
to 16 chars for display). `alternativeSolutions = [plaintext, hash_prefix]`.

---

### 3.2 FIREWALL_BYPASS
**Context:** Find the unmonitored port to slip through.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: FIREWALL BYPASS               │
│  A firewall rule table shows monitored  │
│  ports. One port in the scan is NOT     │
│  listed in the rules — use it.         │
│  TIP: Compare scan results to rules.   │
└──────────────────────────────────────────┘
```

**Generation:** Display 6–8 firewall rules (ports + actions). Display scan results
with 8–10 open ports. One open port is absent from firewall rules. Answer = that port.

---

### 3.3 CRYPTOGRAPHIC_CHALLENGE
**Context:** Sign a nonce using key fragments.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: CRYPTOGRAPHIC CHALLENGE       │
│  A nonce and two key fragments are      │
│  given. XOR them together and submit   │
│  the hex result (simplified RSA sim).  │
│  TIP: XOR hex values digit by digit.   │
└──────────────────────────────────────────┘
```

**Generation:** 4-byte nonce (hex), 4-byte key (hex). Answer = XOR result (hex).
EASY: 2-byte values. EXPERT: 8-byte values.

---

### 3.4 IDS_EVASION
**Context:** Choose the right technique to evade the Intrusion Detection System.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: IDS EVASION                   │
│  IDS config is shown. Choose the        │
│  technique the IDS does NOT detect.    │
│  Options: A) slow B) obfuscate          │
│  C) mimic D) tunnel                    │
│  TIP: Match technique to IDS blind spots. │
└──────────────────────────────────────────┘
```

**Generation:** Show IDS config snippet (3–4 rules). One technique evades all rules.
Answer = option letter (A/B/C/D). Wrong answers add exposure.

---

### 3.5 MULTI_FACTOR_BYPASS
**Context:** Generate a valid TOTP code from the seed.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: MULTI-FACTOR BYPASS           │
│  A TOTP seed is displayed. Calculate   │
│  the 6-digit code for the current      │
│  time window (simplified TOTP).        │
│  TIP: Seed mod 10^6 gives the code.   │
└──────────────────────────────────────────┘
```

**Generation:** Display seed (8 hex chars). Code = (seed XOR time_token) % 1000000,
zero-padded to 6 digits. time_token is provided in the puzzle. Answer = 6-digit code.

---

### 3.6 CERTIFICATE_BYPASS
**Context:** Identify the mismatched certificate fingerprint.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: CERTIFICATE BYPASS            │
│  Two cert fingerprints are shown:       │
│  expected (from CA) and presented       │
│  (by server). Find the differing byte. │
│  TIP: Compare hex strings character    │
│  by character. Report the byte offset. │
└──────────────────────────────────────────┘
```

**Generation:** Two 20-byte (40-char hex) fingerprints differ at 1–3 positions.
Answer = the byte offset(s) where they differ (space-separated integers, 0-indexed).

---

### 3.7 CAPTCHA_SOLVING
**Context:** Decode distorted ASCII-art characters.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: CAPTCHA SOLVING               │
│  ASCII art shows distorted letters.     │
│  Anti-bot verification. Read and enter  │
│  the characters shown.                 │
│  TIP: Focus on the dominant shape of   │
│  each character block.                 │
└──────────────────────────────────────────┘
```

**Generation:** 3–5 characters rendered in simple ASCII art (3×5 grid per char).
EASY: letters only. EXPERT: mixed alphanumeric + noise chars.

---

### 3.8 NETWORK_SEGMENTATION
**Context:** Find the pivot path through network segments.

**Tutorial:**
```
┌──────────────────────────────────────────┐
│  TUTORIAL: NETWORK SEGMENTATION          │
│  A subnet topology is shown with        │
│  segments and gateways. Find the       │
│  path from your entry point to target. │
│  TIP: Enter IPs in order, space-sep.   │
└──────────────────────────────────────────┘
```

**Generation:** Display 3–4 subnet segments. Show entry IP and target IP.
Answer = ordered list of gateway IPs forming the path.

---

## 4. Data Models (Kotlin)

```kotlin
// PuzzleTypes.kt
enum class PuzzleDifficulty(val intValue: Int) {
    EASY(1), MEDIUM(2), HARD(3), EXPERT(4);
    companion object {
        fun fromInt(v: Int): PuzzleDifficulty = entries.firstOrNull { it.intValue == v } ?: MEDIUM
        fun fromExposure(exposure: Double, threatPressure: Int): PuzzleDifficulty = when {
            exposure > 80.0 || threatPressure > 75 -> EXPERT
            exposure > 60.0 || threatPressure > 50 -> HARD
            exposure > 40.0 || threatPressure > 25 -> MEDIUM
            else -> EASY
        }
    }
}

data class ValidationResult(
    val isCorrect: Boolean,
    val score: Double,          // 0.0–1.0
    val feedback: String,
    val partialCredit: Boolean = false,
    val hint: String? = null
)

interface PuzzleValidator {
    fun validateWithScore(answer: String): ValidationResult
}

interface CombatPuzzle : Puzzle, PuzzleValidator {
    val combatType: CombatPuzzleType
    val threatReduction: Int          // % threat pressure removed on success
    val systemIntegrityPenalty: Int   // % integrity lost on failure
    val allowsPartialCredit: Boolean
}

interface NodeAccessPuzzle : Puzzle, PuzzleValidator {
    val accessType: NodeAccessPuzzleType
    val exposureCost: Double           // exposure gained on success
    val firewallCost: Int              // firewall damage on success
    val failurePenalty: Double         // extra exposure on failure
    val alternativeSolutions: List<String>
}

@Serializable
data class PlayerPuzzleProgress(
    val tutorialsSeen: MutableSet<String> = mutableSetOf(),
    val attemptsPerType: MutableMap<String, Int> = mutableMapOf(),
    val successesPerType: MutableMap<String, Int> = mutableMapOf()
) {
    fun hasSeen(typeKey: String): Boolean = typeKey in tutorialsSeen
    fun markSeen(typeKey: String) { tutorialsSeen.add(typeKey) }
    fun recordAttempt(typeKey: String, success: Boolean) {
        attemptsPerType[typeKey] = (attemptsPerType[typeKey] ?: 0) + 1
        if (success) successesPerType[typeKey] = (successesPerType[typeKey] ?: 0) + 1
    }
    fun successRate(typeKey: String): Double {
        val attempts = attemptsPerType[typeKey] ?: return 0.5
        val successes = successesPerType[typeKey] ?: 0
        return if (attempts == 0) 0.5 else successes.toDouble() / attempts
    }
}
```

---

## 5. Combat Stats

| Stat              | Range  | Description                                      |
|-------------------|--------|--------------------------------------------------|
| `threatPressure`  | 0–100  | Replaces `sentinelAttackActive: Boolean`         |
| `systemIntegrity` | 0–100  | Player machine health; at 0 = forced disconnect  |

**`sentinelAttackActive`** becomes a computed property:
```kotlin
val sentinelAttackActive: Boolean get() = threatPressure >= 67
```

---

## 6. Weighted Puzzle Selection

### Combat type weights (by attacker):
| Attacker       | PORT_SCAN | PROCESS_KILL | ENCRYPTION | REGEX | Others |
|----------------|-----------|--------------|------------|-------|--------|
| SENTINEL_ATTACK| 3         | 3            | 1          | 2     | 1 each |
| RIVAL_HACKER   | 1         | 1            | 3          | 3     | 1 each |
| AI_HUNTER      | 1         | 1            | 1          | 1     | 1 each |

### Deduplication: Last 3 puzzle types are excluded from selection.

### Adaptive difficulty adjustment:
- Success rate > 80% → +1 difficulty level
- Success rate < 30% → -1 difficulty level (floor: EASY)
- Base difficulty = `fromExposure(exposure, threatPressure)`

---

## 7. Tutorial System

Tutorials are shown exactly once per puzzle type, prepended to the puzzle content.

Format:
```
┌──────────────────────────────────────────┐
│  TUTORIAL: <TYPE NAME>                   │
│  [2-4 sentences explaining the puzzle]  │
│  TIP: [one actionable tip]              │
└──────────────────────────────────────────┘

[puzzle content follows]
```

Persistence: `PlayerPuzzleProgress.tutorialsSeen` is stored in SQLite and restored
on engine restart. Tutorial shows on first encounter, never again.

---

## 8. Integration Points

### solve command priority (PuzzleCommands.kt):
1. `defragState != null` → DEFRAG handler
2. `combatPuzzle != null` → **Combat puzzle handler** (NEW)
3. `counterHackPuzzle != null` → Legacy counter-hack handler
4. `pendingPuzzle != null` → Connection/node access handler
5. else → error

### Combat puzzle success:
- `decreaseThreatPressure(puzzle.threatReduction)`
- Activate shield (180s)
- Disconnect
- Exposure -8.0

### Combat puzzle failure:
- `damageSystemIntegrity(puzzle.systemIntegrityPenalty)`
- Exposure +3.0

### triggerSentinelAttack() changes:
```kotlin
session.increaseThreatPressure(25)
session.activeCombatType = when {
    session.threatPressure >= 67 -> CombatType.AI_HUNTER
    else -> CombatType.SENTINEL_ATTACK
}
// Generate combat puzzle if none active and pressure >= 34
if (session.combatPuzzle == null && session.threatPressure >= 34) {
    // generate and assign
}
```

---

## 9. Configuration Values

| Setting                    | Value | Description                            |
|----------------------------|-------|----------------------------------------|
| `threatPressure.increment` | 25    | Per detection event                    |
| `threatPressure.threshold.combat` | 34 | Min pressure to generate puzzle   |
| `threatPressure.threshold.ai`     | 67 | Switch to AI_HUNTER type          |
| `combatPuzzle.shieldDuration`     | 180s | Shield on combat success          |
| `combatPuzzle.exposureReward`     | -8.0 | Exposure change on success        |
| `combatPuzzle.exposurePenalty`    | 3.0  | Exposure change on failure        |
| `systemIntegrity.default`         | 100  | Starting value                    |
| `threatPressure.default`          | 0    | Starting value                    |

---

## 10. Testing Checklist

### Build verification
- [ ] `./gradlew build` — zero errors
- [ ] `./gradlew test` — all tests pass (114 existing + new)

### Unit tests
- [ ] `PuzzleDifficulty.fromInt()` round-trips (1→EASY, 4→EXPERT)
- [ ] `PuzzleDifficulty.fromExposure()` boundary values (40/60/80)
- [ ] All 16 new PuzzleType entries generate valid puzzles
- [ ] Each combat puzzle self-validates (puzzle.validate(puzzle.solution))
- [ ] Each node access puzzle primary solution is valid
- [ ] Node access `alternativeSolutions` all validate
- [ ] Partial credit: MEMORY_FORENSICS with 50% correct PIDs → score >= 0.5
- [ ] `PuzzleTutorials.maybePrepend()` first call includes tutorial
- [ ] `PuzzleTutorials.maybePrepend()` second call is unchanged
- [ ] `PuzzleSelector.selectCombatType()` excludes last-3 types
- [ ] Adaptive difficulty: >80% success rate → level up
- [ ] Adaptive difficulty: <30% success rate → level down

### Integration tests
- [ ] Exposure > 85 → detection → combat puzzle generated
- [ ] `solve <correct>` → threatPressure decreases, shield activates
- [ ] `solve <wrong>` → systemIntegrity decreases
- [ ] Restart engine → tutorial NOT shown again (persisted)
- [ ] `threatPressure` in `/api/sentinel/status`
- [ ] `systemIntegrity` in `/api/sentinel/status`
- [ ] Act I missions unchanged after revamp
