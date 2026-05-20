package com.codecraft.engine.puzzle

import com.codecraft.engine.domain.PuzzleType
import kotlin.random.Random

// ────────────────────────────────────────────────────────────────
// MEMORY FORENSICS
// ────────────────────────────────────────────────────────────────

class MemoryForensicsPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.MEMORY_FORENSICS
    override val combatType = CombatPuzzleType.MEMORY_FORENSICS
    override val threatReduction = 15
    override val systemIntegrityPenalty = 10
    override val allowsPartialCredit = true

    private val totalRows = when (difficulty) {
        1 -> 10; 2 -> 12; 3 -> 14; else -> 15
    }
    private val rogueCount = when (difficulty) {
        1 -> 1; 2 -> 2; else -> minOf(3, difficulty)
    }

    private val processes: List<ProcessEntry>
    private val roguePids: List<Int>

    init {
        val rogueNames = listOf("xz8001", "kq2234", "sy7743", "lp0091", "rr1188", "dw3392")
        val benignNames = listOf("systemd", "sshd", "nginx", "cron", "rsyslog", "dbus", "atd",
            "getty", "udev", "kworker", "ksoftirqd", "kthreadd", "migration")
        val roguePaths = listOf("/tmp/.x11", "/var/tmp/.sys", "/tmp/srv", "/tmp/.lock", "/var/tmp/run")
        val benignPaths = listOf("/usr/sbin", "/usr/lib/systemd", "/usr/bin", "/sbin", "/lib")

        val allPids = (1000..9999).shuffled().take(totalRows)
        val rogueIndices = (0 until totalRows).shuffled().take(rogueCount).toSet()

        processes = allPids.mapIndexed { idx, pid ->
            if (idx in rogueIndices) {
                ProcessEntry(
                    pid = pid,
                    name = rogueNames.random(),
                    cpu = Random.nextInt(87, 99),
                    mem = Random.nextInt(2, 8),
                    path = roguePaths.random(),
                    rogue = true
                )
            } else {
                ProcessEntry(
                    pid = pid,
                    name = benignNames.random(),
                    cpu = Random.nextInt(0, 12),
                    mem = Random.nextInt(0, 4),
                    path = benignPaths.random(),
                    rogue = false
                )
            }
        }
        roguePids = processes.filter { it.rogue }.map { it.pid }
    }

    override val solution: String = roguePids.sorted().joinToString(" ")

    override val content: String
        get() = buildString {
            appendLine("=== MEMORY FORENSICS ALERT ===")
            appendLine("Sentinel has injected rogue processes. Identify and terminate.")
            appendLine()
            appendLine("  PID   NAME          CPU%  MEM%  PATH")
            appendLine("  " + "─".repeat(60))
            processes.forEach { p ->
                appendLine("  ${p.pid.toString().padEnd(6)}${p.name.padEnd(14)}${p.cpu.toString().padEnd(6)}${p.mem.toString().padEnd(6)}${p.path}")
            }
            appendLine()
            appendLine("Submit PIDs of ALL rogue processes (space-separated).")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean = validateWithScore(answer).isCorrect

    override fun validateWithScore(answer: String): ValidationResult {
        val submitted = answer.trim().split(Regex("\\s+"))
            .mapNotNull { it.toIntOrNull() }.toSet()
        val expected = roguePids.toSet()
        if (submitted == expected) {
            return ValidationResult(true, 1.0, "All rogue processes identified.")
        }
        val correct = submitted.intersect(expected).size
        val total = expected.size
        val score = correct.toDouble() / total
        return if (score >= 0.5) {
            ValidationResult(false, score, "Partial: $correct/$total correct.", partialCredit = true,
                hint = "Check for processes with high CPU in /tmp or /var/tmp.")
        } else {
            ValidationResult(false, score, "Incorrect. Look for high-CPU processes in suspicious paths.",
                hint = "Rogue processes often hide in /tmp with short random names.")
        }
    }
}

private data class ProcessEntry(val pid: Int, val name: String, val cpu: Int, val mem: Int, val path: String, val rogue: Boolean)

// ────────────────────────────────────────────────────────────────
// PORT SCAN INTERCEPTION
// ────────────────────────────────────────────────────────────────

class PortScanInterceptionPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.PORT_SCAN_INTERCEPTION
    override val combatType = CombatPuzzleType.PORT_SCAN_INTERCEPTION
    override val threatReduction = 20
    override val systemIntegrityPenalty = 12
    override val allowsPartialCredit = false

    private val totalRows = when (difficulty) { 1 -> 8; 2 -> 10; 3 -> 12; else -> 14 }
    private val suspiciousPort: Int
    private val suspiciousIp: String
    private val rows: List<PortEntry>

    init {
        suspiciousPort = listOf(4444, 31337, 1337, 8888, 9999, 6666, 12345, 54321).random()
        suspiciousIp = "${Random.nextInt(140, 220)}.${Random.nextInt(10, 250)}.${Random.nextInt(1, 254)}.${Random.nextInt(1, 254)}"

        val localPorts = listOf(22, 80, 443, 8080, 3306, 5432, 6379, 27017, 25, 53)
        val noiseStates = listOf("LISTEN", "TIME_WAIT", "CLOSE_WAIT", "SYN_SENT", "FIN_WAIT")
        val rfc1918 = listOf("10.0.0.1", "192.168.1.1", "172.16.0.1", "10.10.10.10")

        val normalRows = (0 until totalRows - 1).map {
            PortEntry(localPorts.random(), noiseStates.random(), rfc1918.random())
        }
        val suspRow = PortEntry(suspiciousPort, "ESTABLISHED", suspiciousIp)
        rows = (normalRows + suspRow).shuffled()
    }

    override val solution: String = suspiciousPort.toString()

    override val content: String
        get() = buildString {
            appendLine("=== PORT SCAN INTERCEPTION ===")
            appendLine("Rogue connection detected. Identify the exfiltration port.")
            appendLine()
            appendLine("  LOCAL_PORT  STATE         REMOTE_IP")
            appendLine("  " + "─".repeat(55))
            rows.forEach { r ->
                appendLine("  ${r.port.toString().padEnd(12)}${r.state.padEnd(14)}${r.remote}")
            }
            appendLine()
            appendLine("Submit the suspicious port number.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.trim() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Rogue port identified." else "Wrong port. Look for ESTABLISHED connections to external IPs.",
            hint = if (!correct) "Only one port is in ESTABLISHED state to a non-private IP." else null)
    }
}

private data class PortEntry(val port: Int, val state: String, val remote: String)

// ────────────────────────────────────────────────────────────────
// LOG PATTERN RECOGNITION
// ────────────────────────────────────────────────────────────────

class LogPatternRecognitionPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.LOG_PATTERN_RECOGNITION
    override val combatType = CombatPuzzleType.LOG_PATTERN_RECOGNITION
    override val threatReduction = 15
    override val systemIntegrityPenalty = 8
    override val allowsPartialCredit = false

    private val totalLines = when (difficulty) { 1 -> 6; 2 -> 8; 3 -> 10; else -> 12 }

    private val maliciousKeywords = listOf("sentinel_probe", "exfil_agent", "backdoor_conn",
        "rootkit_init", "keylogger", "covert_channel", "shadow_exec", "phantom_pid")
    private val benignSources = listOf("sshd", "nginx", "cron", "systemd", "kernel", "rsyslog", "su", "sudo")
    private val maliciousKeyword: String
    private val logLines: List<String>

    init {
        maliciousKeyword = maliciousKeywords.random()
        val benignLines = (0 until totalLines - 1).map { i ->
            val ts = "2025-02-16T${(8 + i / 2).toString().padStart(2, '0')}:${(i * 3 % 60).toString().padStart(2, '0')}:01"
            "[$ts] ${benignSources.random()}[${Random.nextInt(1000, 9999)}]: Connection accepted"
        }
        val malTs = "2025-02-16T${Random.nextInt(1, 6).toString().padStart(2, '0')}:${Random.nextInt(0, 59).toString().padStart(2, '0')}:57"
        val malLine = "[$malTs] unknown[${Random.nextInt(10000, 99999)}]: $maliciousKeyword initiated"
        logLines = (benignLines + malLine).shuffled()
    }

    override val solution: String = maliciousKeyword

    override val content: String
        get() = buildString {
            appendLine("=== LOG PATTERN RECOGNITION ===")
            appendLine("Malicious activity detected in system logs. Identify the anomaly.")
            appendLine()
            logLines.forEach { appendLine(it) }
            appendLine()
            appendLine("Submit the distinctive keyword from the malicious log entry.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().lowercase() == solution.lowercase()

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Anomaly identified." else "Wrong keyword. Look for unusual process names or actions.",
            hint = if (!correct) "One log entry has an unknown source and unusual action." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// PROCESS KILL
// ────────────────────────────────────────────────────────────────

class ProcessKillPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.PROCESS_KILL
    override val combatType = CombatPuzzleType.PROCESS_KILL
    override val threatReduction = 25
    override val systemIntegrityPenalty = 15
    override val allowsPartialCredit = false

    private val inner = MemoryForensicsPuzzle(difficulty, hint)

    override val solution: String = inner.solution
    override val content: String
        get() = buildString {
            appendLine("=== PROCESS KILL — SIMULTANEOUS TERMINATION REQUIRED ===")
            appendLine("ALL rogue processes must be killed at once. No partial credit.")
            appendLine()
            appendLine(inner.content.lines().drop(3).joinToString("\n"))
        }

    override fun validate(answer: String): Boolean {
        val submitted = answer.trim().split(Regex("\\s+"))
            .mapNotNull { it.toIntOrNull() }.toSet()
        val expected = solution.split(" ").map { it.toInt() }.toSet()
        return submitted == expected
    }

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "All rogue processes terminated." else "Must kill ALL rogue processes simultaneously.",
            hint = if (!correct) "Submit all PIDs at once, space-separated." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// PACKET INSPECTION
// ────────────────────────────────────────────────────────────────

class PacketInspectionPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.PACKET_INSPECTION
    override val combatType = CombatPuzzleType.PACKET_INSPECTION
    override val threatReduction = 18
    override val systemIntegrityPenalty = 10
    override val allowsPartialCredit = false

    // TCP flags: URG(32) ACK(16) PSH(8) RST(4) SYN(2) FIN(1)
    private val flagDefs = listOf(
        32 to "URG", 16 to "ACK", 8 to "PSH", 4 to "RST", 2 to "SYN", 1 to "FIN"
    )
    private val flagCount = when (difficulty) { 1 -> Random.nextInt(1, 3); 2 -> Random.nextInt(2, 4); 3 -> Random.nextInt(3, 5); else -> Random.nextInt(4, 7) }
    private val selectedFlags: List<Pair<Int, String>>
    private val flagByte: Int
    private val srcIp = "${Random.nextInt(1,254)}.${Random.nextInt(0,254)}.${Random.nextInt(0,254)}.${Random.nextInt(1,254)}"
    private val dstIp = "${Random.nextInt(1,254)}.${Random.nextInt(0,254)}.${Random.nextInt(0,254)}.${Random.nextInt(1,254)}"

    init {
        selectedFlags = flagDefs.shuffled().take(flagCount.coerceIn(1, flagDefs.size))
            .sortedByDescending { it.first }
        flagByte = selectedFlags.sumOf { it.first }
    }

    override val solution: String = selectedFlags.joinToString(" ") { it.second }

    override val content: String
        get() = buildString {
            appendLine("=== PACKET INSPECTION ===")
            appendLine("Suspicious packet intercepted. Decode the TCP flags.")
            appendLine()
            appendLine("  IP Header:")
            appendLine("    Src: $srcIp  Dst: $dstIp")
            appendLine("    Protocol: TCP  TTL: ${Random.nextInt(60, 128)}")
            appendLine()
            appendLine("  TCP Header:")
            appendLine("    Src Port: ${Random.nextInt(1024, 65535)}  Dst Port: ${Random.nextInt(1, 1024)}")
            appendLine("    Flags: 0x${flagByte.toString(16).padStart(2, '0').uppercase()}")
            appendLine()
            appendLine("  Flag Reference: URG=0x20 ACK=0x10 PSH=0x08 RST=0x04 SYN=0x02 FIN=0x01")
            appendLine()
            appendLine("Submit the active flag names (space-separated, e.g. SYN ACK).")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().uppercase().split(Regex("\\s+")).sorted() == solution.split(" ").sorted()

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Flags correctly decoded." else "Wrong flags. Sum the bits of the flags byte.",
            hint = if (!correct) "Each flag bit: URG=32, ACK=16, PSH=8, RST=4, SYN=2, FIN=1." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// ENCRYPTION CRACKING
// ────────────────────────────────────────────────────────────────

class EncryptionCrackingPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.ENCRYPTION_CRACKING
    override val combatType = CombatPuzzleType.ENCRYPTION_CRACKING
    override val threatReduction = 20
    override val systemIntegrityPenalty = 10
    override val allowsPartialCredit = false

    private val plaintexts = listOf("OVERRIDE ACCESS", "KILL SWITCH ACTIVE", "TRACE COMPLETE",
        "SYSTEM BREACH", "LOCKDOWN INIT", "SENTINEL ARM", "PURGE LOGS", "DISARM SHIELD")
    private val plaintext = plaintexts.random()
    private val shift = when (difficulty) { 1 -> 3; 2 -> 7; 3 -> 13; else -> Random.nextInt(5, 20) }
    private val ciphertext = encrypt(plaintext, shift)

    override val solution: String = plaintext

    override val content: String
        get() = buildString {
            appendLine("=== ENCRYPTION CRACKING ===")
            appendLine("Attacker command is Caesar-encrypted. Decode to counter.")
            appendLine()
            appendLine("  Encrypted: $ciphertext")
            if (difficulty <= 2) appendLine("  Shift: $shift")
            appendLine()
            appendLine("Submit the plaintext command.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().uppercase() == solution.uppercase()

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Command decoded." else "Wrong plaintext. Apply the shift to each letter.")
    }

    private fun encrypt(text: String, s: Int): String = text.map { c ->
        when {
            c.isUpperCase() -> (('A'.code + ((c.code - 'A'.code + s) % 26 + 26) % 26)).toChar()
            c.isLowerCase() -> (('A'.code + ((c.code - 'a'.code + s) % 26 + 26) % 26)).toChar()
            else -> c
        }
    }.joinToString("")
}

// ────────────────────────────────────────────────────────────────
// TRACE ROUTE
// ────────────────────────────────────────────────────────────────

class TraceRoutePuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.TRACE_ROUTE
    override val combatType = CombatPuzzleType.TRACE_ROUTE
    override val threatReduction = 15
    override val systemIntegrityPenalty = 8
    override val allowsPartialCredit = false

    private val hopCount = when (difficulty) { 1 -> 4; 2 -> 5; 3 -> 5; else -> 6 }
    private val subnet = "${Random.nextInt(10, 200)}.${Random.nextInt(0, 254)}.${Random.nextInt(0, 254)}"
    private val missingHopIndex: Int
    private val hops: List<String?>
    private val missingIp: String

    init {
        val baseLastOctet = Random.nextInt(1, 240)
        val fullHops = (0 until hopCount).map { i ->
            "$subnet.${(baseLastOctet + i * 2).coerceAtMost(254)}"
        }
        missingHopIndex = Random.nextInt(1, hopCount - 1)
        missingIp = fullHops[missingHopIndex]
        hops = fullHops.mapIndexed { idx, ip -> if (idx == missingHopIndex) null else ip }
    }

    override val solution: String = missingIp

    override val content: String
        get() = buildString {
            appendLine("=== TRACE ROUTE ANALYSIS ===")
            appendLine("Attack origin trace — one hop is hidden. Reconstruct it.")
            appendLine()
            hops.forEachIndexed { idx, hop ->
                val display = hop ?: "???"
                appendLine("  Hop ${idx + 1}: $display")
            }
            appendLine()
            appendLine("Submit the missing IP address.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.trim() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Missing hop identified." else "Wrong IP. Hops follow subnet arithmetic.",
            hint = if (!correct) "Look for the pattern in the last octet of each hop." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// REGEX FILTERING
// ────────────────────────────────────────────────────────────────

class RegexFilteringPuzzle(
    override val difficulty: Int,
    override val hint: String? = null
) : CombatPuzzle {
    override val type = PuzzleType.REGEX_FILTERING
    override val combatType = CombatPuzzleType.REGEX_FILTERING
    override val threatReduction = 18
    override val systemIntegrityPenalty = 10
    override val allowsPartialCredit = true

    private val maliciousSubnet = "${Random.nextInt(140, 220)}.${Random.nextInt(10, 200)}"
    private val benignSubnets = listOf("192.168.1", "10.0.0", "172.16.0", "192.168.100")
    private val totalLines = when (difficulty) { 1 -> 8; 2 -> 10; 3 -> 12; else -> 14 }
    private val maliciousCount = when (difficulty) { 1 -> 2; 2 -> 3; else -> 4 }
    private val logLines: List<Pair<String, Boolean>>

    init {
        val benignLines = (0 until totalLines - maliciousCount).map {
            val ip = "${benignSubnets.random()}.${Random.nextInt(1, 254)}"
            "[INFO] Connection from $ip - status OK" to false
        }
        val malLines = (0 until maliciousCount).map { i ->
            val ip = "$maliciousSubnet.${Random.nextInt(1, 254)}"
            "[WARN] Probe from $ip - path /etc/shadow attempt" to true
        }
        logLines = (benignLines + malLines).shuffled()
    }

    override val solution: String = maliciousSubnet

    override val content: String
        get() = buildString {
            appendLine("=== REGEX FILTERING ===")
            appendLine("Log stream contains threat traffic. Enter the pattern matching ONLY malicious entries.")
            appendLine()
            logLines.forEach { (line, _) -> appendLine(line) }
            appendLine()
            appendLine("Submit the distinguishing keyword or IP prefix.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.trim() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val pattern = answer.trim()
        val matched = logLines.filter { (line, _) -> line.contains(pattern, ignoreCase = true) }
        val malMatched = matched.count { (_, isMal) -> isMal }
        val malTotal = logLines.count { (_, isMal) -> isMal }
        val benignMatched = matched.count { (_, isMal) -> !isMal }

        if (malMatched == malTotal && benignMatched == 0) {
            return ValidationResult(true, 1.0, "Filter matches all threats, no false positives.")
        }
        val score = malMatched.toDouble() / malTotal - (benignMatched.toDouble() / (logLines.size - malTotal)) * 0.5
        return if (score >= 0.5) {
            ValidationResult(false, score.coerceIn(0.0, 1.0), "Partial match: $malMatched/$malTotal threats caught.", partialCredit = true)
        } else {
            ValidationResult(false, 0.0, "Filter too broad or misses threats. Try a more specific pattern.",
                hint = "Look for shared IP prefix among suspicious entries.")
        }
    }
}
