package com.codecraft.engine.puzzle

import com.codecraft.engine.domain.PuzzleType
import kotlin.random.Random

// ────────────────────────────────────────────────────────────────
// PASSWORD CRACKING
// ────────────────────────────────────────────────────────────────

class PasswordCrackingPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.PASSWORD_CRACKING
    override val accessType = NodeAccessPuzzleType.PASSWORD_CRACKING
    override val exposureCost = when (difficulty) { 1 -> 2.0; 2 -> 3.0; 3 -> 4.0; else -> 5.0 }
    override val firewallCost = 0
    override val failurePenalty = 2.0

    private val wordlist = listOf("admin", "root", "secure", "password", "default",
        "guest", "access", "server", "master", "oracle")
    private val plaintext = wordlist.random()
    private val salt = Random.nextInt(100, 999).toString()
    // Simplified: display first 16 chars of "sha256-like" hash
    private val hashDisplay = buildString {
        val seed = "$plaintext$salt".hashCode().toLong().and(0x7FFFFFFF)
        append("%016x".format(seed))
    }

    override val solution: String = plaintext
    override val alternativeSolutions: List<String> = listOf(plaintext, hashDisplay)

    override val content: String
        get() = buildString {
            appendLine("=== PASSWORD CRACKING ===")
            appendLine("Authentication required for node: $nodeId")
            appendLine()
            appendLine("  Hash (SHA-256, truncated): $hashDisplay")
            appendLine("  Salt: $salt")
            appendLine()
            appendLine("Common password list: admin root secure password default guest access server master oracle")
            appendLine()
            appendLine("Submit the plaintext password OR the hash string.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        alternativeSolutions.any { it.equals(answer.trim(), ignoreCase = true) }

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Password cracked. Access granted." else "Wrong password. Try the wordlist.",
            hint = if (!correct) "Try common admin passwords from the list above." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// FIREWALL BYPASS
// ────────────────────────────────────────────────────────────────

class FirewallBypassPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.FIREWALL_BYPASS
    override val accessType = NodeAccessPuzzleType.FIREWALL_BYPASS
    override val exposureCost = when (difficulty) { 1 -> 3.0; 2 -> 4.0; 3 -> 5.0; else -> 7.0 }
    override val firewallCost = when (difficulty) { 1 -> 2; 2 -> 3; 3 -> 5; else -> 8 }
    override val failurePenalty = 3.0
    override val alternativeSolutions: List<String> get() = emptyList()

    private val allPorts = listOf(21, 22, 23, 25, 53, 80, 110, 143, 443, 465, 587, 993, 995,
        1433, 3306, 3389, 5432, 6379, 8080, 8443, 27017)
    private val monitoredPorts: List<Int>
    private val openPorts: List<Int>
    private val unmonitoredPort: Int

    init {
        val shuffled = allPorts.shuffled()
        monitoredPorts = shuffled.take(6 + difficulty)
        // Add one open port not in monitored list
        val candidates = allPorts.filter { it !in monitoredPorts }
        unmonitoredPort = candidates.random()
        openPorts = (monitoredPorts.take(3) + unmonitoredPort).shuffled()
    }

    override val solution: String = unmonitoredPort.toString()

    override val content: String
        get() = buildString {
            appendLine("=== FIREWALL BYPASS ===")
            appendLine("Firewall rules for node: $nodeId")
            appendLine()
            appendLine("  MONITORED PORTS (blocked on detection):")
            monitoredPorts.chunked(6).forEach { chunk ->
                appendLine("    ${chunk.joinToString(", ")}")
            }
            appendLine()
            appendLine("  SCAN RESULTS (open ports):")
            appendLine("    ${openPorts.joinToString(", ")}")
            appendLine()
            appendLine("Submit the port NOT listed in firewall rules.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Unmonitored port found. Bypass route established." else "Wrong port — that one is monitored.",
            hint = if (!correct) "Compare scan results against the monitored list. One open port is missing from rules." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// CRYPTOGRAPHIC CHALLENGE
// ────────────────────────────────────────────────────────────────

class CryptographicChallengePuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.CRYPTOGRAPHIC_CHALLENGE
    override val accessType = NodeAccessPuzzleType.CRYPTOGRAPHIC_CHALLENGE
    override val exposureCost = when (difficulty) { 1 -> 2.0; 2 -> 3.0; 3 -> 5.0; else -> 7.0 }
    override val firewallCost = 0
    override val failurePenalty = 3.0
    override val alternativeSolutions: List<String> get() = emptyList()

    private val byteCount = when (difficulty) { 1 -> 2; 2 -> 4; 3 -> 6; else -> 8 }
    private val nonceBytes = (0 until byteCount).map { Random.nextInt(0, 256) }
    private val keyBytes = (0 until byteCount).map { Random.nextInt(0, 256) }
    private val xorResult = nonceBytes.zip(keyBytes).map { (a, b) -> a xor b }

    private val nonceHex = nonceBytes.joinToString("") { "%02x".format(it) }
    private val keyHex = keyBytes.joinToString("") { "%02x".format(it) }

    override val solution: String = xorResult.joinToString("") { "%02x".format(it) }

    override val content: String
        get() = buildString {
            appendLine("=== CRYPTOGRAPHIC CHALLENGE ===")
            appendLine("Node $nodeId requires signed response.")
            appendLine()
            appendLine("  Nonce:   $nonceHex")
            appendLine("  Key:     $keyHex")
            appendLine()
            appendLine("  Operation: XOR(nonce, key)")
            appendLine("  Submit the hex result.")
            if (difficulty <= 2) appendLine("  Hint: XOR each byte pair individually.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().lowercase() == solution.lowercase()

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Signed response accepted." else "Wrong response. XOR nonce and key bytes.",
            hint = if (!correct) "XOR: 0x$nonceHex XOR 0x$keyHex = ?" else null)
    }
}

// ────────────────────────────────────────────────────────────────
// IDS EVASION
// ────────────────────────────────────────────────────────────────

class IdsEvasionPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.IDS_EVASION
    override val accessType = NodeAccessPuzzleType.IDS_EVASION
    override val exposureCost = when (difficulty) { 1 -> 2.0; 2 -> 4.0; 3 -> 5.0; else -> 6.0 }
    override val firewallCost = 0
    override val failurePenalty = 4.0
    override val alternativeSolutions: List<String> get() = emptyList()

    data class EvasionOption(val letter: String, val technique: String, val description: String)

    private val options = listOf(
        EvasionOption("A", "slow",      "Slow-rate scanning below IDS threshold"),
        EvasionOption("B", "obfuscate", "Obfuscate traffic patterns with padding"),
        EvasionOption("C", "mimic",     "Mimic legitimate application traffic"),
        EvasionOption("D", "tunnel",    "Tunnel through allowed protocol (DNS/HTTP)")
    )

    // IDS rules — one technique avoids all
    private val idsRules = when (difficulty) {
        1 -> listOf("ALERT: rapid port scan (>50 ports/sec)", "ALERT: unusual source port ranges")
        2 -> listOf("ALERT: rapid port scan", "ALERT: unusual source port", "ALERT: large payload bursts")
        3 -> listOf("ALERT: rapid scan", "ALERT: unusual port", "ALERT: payload bursts", "ALERT: non-standard timing")
        else -> listOf("ALERT: rapid scan", "ALERT: unusual port", "ALERT: payload burst",
            "ALERT: non-standard timing", "ALERT: encrypted tunnel via port 443")
    }

    // Correct answer depends on which rules are active
    private val correctOption = when {
        difficulty == 4 && idsRules.any { it.contains("tunnel") } -> "C" // mimic (tunnel is blocked)
        idsRules.any { it.contains("payload") } -> "A"  // slow avoids payload detection
        else -> "D"                                       // tunnel
    }

    override val solution: String = correctOption

    override val content: String
        get() = buildString {
            appendLine("=== IDS EVASION ===")
            appendLine("IDS configuration for node: $nodeId")
            appendLine()
            appendLine("  Active IDS Rules:")
            idsRules.forEach { appendLine("    $it") }
            appendLine()
            appendLine("  Available techniques:")
            options.forEach { o -> appendLine("  ${o.letter}) ${o.technique}: ${o.description}") }
            appendLine()
            appendLine("Submit the letter of the technique that evades ALL IDS rules.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().uppercase() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Evasion technique selected. IDS bypassed." else "That technique triggers an IDS rule.",
            hint = if (!correct) "Match each technique against the active rules. One avoids all of them." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// MULTI-FACTOR BYPASS
// ────────────────────────────────────────────────────────────────

class MultiFactorBypassPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.MULTI_FACTOR_BYPASS
    override val accessType = NodeAccessPuzzleType.MULTI_FACTOR_BYPASS
    override val exposureCost = when (difficulty) { 1 -> 3.0; 2 -> 4.0; 3 -> 6.0; else -> 8.0 }
    override val firewallCost = 0
    override val failurePenalty = 3.0
    override val alternativeSolutions: List<String> get() = emptyList()

    private val seed = Random.nextInt(0x10000000, 0x7FFFFFFF)
    private val timeToken = Random.nextInt(100, 999)
    private val code = (seed xor timeToken) % 1_000_000

    override val solution: String = code.toString().padStart(6, '0')

    override val content: String
        get() = buildString {
            appendLine("=== MULTI-FACTOR BYPASS ===")
            appendLine("TOTP verification required for node: $nodeId")
            appendLine()
            appendLine("  TOTP Seed:   ${"%08x".format(seed)}")
            appendLine("  Time Token:  $timeToken")
            appendLine()
            appendLine("  Formula: (seed XOR time_token) mod 1000000 → zero-pad to 6 digits")
            appendLine()
            appendLine("Submit the 6-digit code.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "TOTP code accepted. Second factor bypassed." else "Wrong code.",
            hint = if (!correct) "XOR the seed (hex) with the time token, then mod 1000000." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// CERTIFICATE BYPASS
// ────────────────────────────────────────────────────────────────

class CertificateBypassPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.CERTIFICATE_BYPASS
    override val accessType = NodeAccessPuzzleType.CERTIFICATE_BYPASS
    override val exposureCost = when (difficulty) { 1 -> 2.0; 2 -> 3.0; 3 -> 4.0; else -> 5.0 }
    override val firewallCost = 0
    override val failurePenalty = 2.0
    override val alternativeSolutions: List<String> get() = emptyList()

    private val certLength = 20
    private val diffCount = when (difficulty) { 1 -> 1; 2 -> 1; 3 -> 2; else -> 3 }
    private val expectedCert = (0 until certLength).map { Random.nextInt(0, 256) }
    private val diffPositions = (0 until certLength).shuffled().take(diffCount).sorted()
    private val presentedCert = expectedCert.toMutableList().also { cert ->
        diffPositions.forEach { pos ->
            cert[pos] = (cert[pos] + Random.nextInt(1, 255)) % 256
        }
    }

    private val expectedHex = expectedCert.joinToString("") { "%02x".format(it) }
    private val presentedHex = presentedCert.joinToString("") { "%02x".format(it) }

    override val solution: String = diffPositions.joinToString(" ")

    override val content: String
        get() = buildString {
            appendLine("=== CERTIFICATE BYPASS ===")
            appendLine("Certificate mismatch detected on node: $nodeId")
            appendLine()
            appendLine("  Expected (CA):  $expectedHex")
            appendLine("  Presented:      $presentedHex")
            appendLine()
            appendLine("Submit the byte offset(s) where they differ (0-indexed, space-separated).")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean {
        val submitted = answer.trim().split(Regex("\\s+"))
            .mapNotNull { it.toIntOrNull() }.sorted()
        return submitted == diffPositions
    }

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Mismatched bytes identified. Certificate forgery confirmed." else "Wrong byte positions.",
            hint = if (!correct) "Compare hex strings character-by-character. Each pair of chars = 1 byte." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// CAPTCHA SOLVING
// ────────────────────────────────────────────────────────────────

class CaptchaSolvingPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.CAPTCHA_SOLVING
    override val accessType = NodeAccessPuzzleType.CAPTCHA_SOLVING
    override val exposureCost = when (difficulty) { 1 -> 2.0; 2 -> 3.0; 3 -> 4.0; else -> 5.0 }
    override val firewallCost = 0
    override val failurePenalty = 2.0
    override val alternativeSolutions: List<String> get() = emptyList()

    // Simple ASCII art for letters A-Z and 0-9
    private val asciiChars = mapOf(
        'A' to listOf(" # ", "# #", "###", "# #", "# #"),
        'B' to listOf("## ", "# #", "## ", "# #", "## "),
        'C' to listOf(" ##", "#  ", "#  ", "#  ", " ##"),
        'E' to listOf("###", "#  ", "## ", "#  ", "###"),
        'G' to listOf(" ##", "#  ", "# #", "# #", " ##"),
        'H' to listOf("# #", "# #", "###", "# #", "# #"),
        'I' to listOf("###", " # ", " # ", " # ", "###"),
        'L' to listOf("#  ", "#  ", "#  ", "#  ", "###"),
        'N' to listOf("# #", "##x", "# #", "# #", "# #"),
        'O' to listOf(" # ", "# #", "# #", "# #", " # "),
        'P' to listOf("## ", "# #", "## ", "#  ", "#  "),
        'R' to listOf("## ", "# #", "## ", "# #", "# #"),
        'S' to listOf(" ##", "#  ", " # ", "  #", "## "),
        'T' to listOf("###", " # ", " # ", " # ", " # "),
        'U' to listOf("# #", "# #", "# #", "# #", " # "),
        'X' to listOf("# #", " # ", " # ", " # ", "# #"),
        '0' to listOf(" # ", "# #", "# #", "# #", " # "),
        '1' to listOf(" # ", "## ", " # ", " # ", "###"),
        '3' to listOf("## ", "  #", " # ", "  #", "## "),
        '7' to listOf("###", "  #", " # ", "#  ", "#  ")
    )

    private val charCount = when (difficulty) { 1 -> 3; 2 -> 4; 3 -> 5; else -> 5 }
    private val chars: List<Char>

    init {
        val pool = asciiChars.keys.toList()
        chars = (0 until charCount).map { pool.random() }
    }

    override val solution: String = chars.joinToString("")

    override val content: String
        get() = buildString {
            appendLine("=== CAPTCHA SOLVING ===")
            appendLine("Anti-bot verification for node: $nodeId")
            appendLine()
            // Render chars side by side
            val renders = chars.map { c -> asciiChars[c] ?: listOf("???", "???", "???", "???", "???") }
            for (row in 0..4) {
                appendLine("  " + renders.joinToString("  ") { it.getOrElse(row) { "   " } })
            }
            appendLine()
            appendLine("Submit the characters shown above.")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().uppercase() == solution

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        val submitted = answer.trim().uppercase()
        val matchCount = submitted.zip(solution).count { (a, b) -> a == b }
        val score = if (solution.isEmpty()) 0.0 else matchCount.toDouble() / solution.length
        return ValidationResult(correct, score,
            if (correct) "Captcha solved." else "Wrong characters. $matchCount/${solution.length} correct.",
            hint = if (!correct) "Each character block is 3 columns wide. Focus on the dominant shape." else null)
    }
}

// ────────────────────────────────────────────────────────────────
// NETWORK SEGMENTATION
// ────────────────────────────────────────────────────────────────

class NetworkSegmentationPuzzle(
    override val difficulty: Int,
    private val nodeId: String = "target",
    override val hint: String? = null
) : NodeAccessPuzzle {
    override val type = PuzzleType.NETWORK_SEGMENTATION
    override val accessType = NodeAccessPuzzleType.NETWORK_SEGMENTATION
    override val exposureCost = when (difficulty) { 1 -> 3.0; 2 -> 5.0; 3 -> 7.0; else -> 9.0 }
    override val firewallCost = when (difficulty) { 1 -> 0; 2 -> 2; 3 -> 5; else -> 8 }
    override val failurePenalty = 4.0
    override val alternativeSolutions: List<String> get() = emptyList()

    data class Segment(val subnet: String, val gateway: String, val nextSegment: String?)

    private val hopCount = when (difficulty) { 1 -> 3; 2 -> 4; 3 -> 4; else -> 5 }
    private val segments: List<Segment>
    private val entryIp: String
    private val targetIp: String

    init {
        val bases = listOf("10.0", "172.16", "192.168.1", "10.10", "172.17")
        val chosen = bases.shuffled().take(hopCount)
        segments = chosen.mapIndexed { i, base ->
            val gw = "$base.1"
            Segment(
                subnet = "$base.0/24",
                gateway = gw,
                nextSegment = if (i < hopCount - 1) chosen[i + 1] + ".0/24" else null
            )
        }
        entryIp = "${chosen[0]}.100"
        targetIp = "${chosen.last()}.50"
    }

    override val solution: String = segments.map { it.gateway }.joinToString(" ")

    override val content: String
        get() = buildString {
            appendLine("=== NETWORK SEGMENTATION ===")
            appendLine("Pivoting required to reach node: $nodeId")
            appendLine()
            appendLine("  Entry point: $entryIp")
            appendLine("  Target:      $targetIp")
            appendLine()
            appendLine("  Network topology:")
            segments.forEachIndexed { i, seg ->
                appendLine("  Segment ${i + 1}: ${seg.subnet}  Gateway: ${seg.gateway}" +
                        if (seg.nextSegment != null) "  → ${seg.nextSegment}" else "  [TARGET SEGMENT]")
            }
            appendLine()
            appendLine("Submit the gateway IPs in pivot order (space-separated).")
            hint?.let { appendLine("Hint: $it") }
        }

    override fun validate(answer: String): Boolean =
        answer.trim().split(Regex("\\s+")) == solution.split(" ")

    override fun validateWithScore(answer: String): ValidationResult {
        val correct = validate(answer)
        return ValidationResult(correct, if (correct) 1.0 else 0.0,
            if (correct) "Pivot path established. Routing through all segments." else "Wrong path. Follow gateways in order.",
            hint = if (!correct) "Use each segment's gateway as the next hop." else null)
    }
}
