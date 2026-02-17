package com.codecraft.engine.puzzle

/**
 * Tutorial system — shows once per puzzle type, then never again
 */
object PuzzleTutorials {

    private val tutorials: Map<String, String> = mapOf(
        "MEMORY_FORENSICS" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: MEMORY FORENSICS              │
│  A process table is shown. Rogue        │
│  processes have high CPU%, suspicious   │
│  paths (/tmp, /var/tmp), or odd names.  │
│  TIP: Enter PIDs separated by spaces.  │
└──────────────────────────────────────────┘""".trimIndent(),

        "PORT_SCAN_INTERCEPTION" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: PORT SCAN INTERCEPTION        │
│  Netstat output is shown. Find the      │
│  port in ESTABLISHED state connected    │
│  to a non-private external IP.          │
│  TIP: Enter the suspicious port number. │
└──────────────────────────────────────────┘""".trimIndent(),

        "LOG_PATTERN_RECOGNITION" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: LOG PATTERN RECOGNITION       │
│  System logs contain one anomalous      │
│  entry with an unknown source or        │
│  suspicious process name.               │
│  TIP: Enter the distinctive keyword.   │
└──────────────────────────────────────────┘""".trimIndent(),

        "PROCESS_KILL" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: PROCESS KILL                  │
│  All rogue processes must be killed     │
│  simultaneously. Missing even one lets  │
│  the attacker recover. No partial       │
│  credit — enter ALL rogue PIDs at once. │
│  TIP: Separate multiple PIDs by spaces. │
└──────────────────────────────────────────┘""".trimIndent(),

        "PACKET_INSPECTION" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: PACKET INSPECTION             │
│  A TCP header shows a flags byte in     │
│  hex. Decode which flags are active.   │
│  URG=0x20 ACK=0x10 PSH=0x08            │
│  RST=0x04 SYN=0x02 FIN=0x01            │
│  TIP: Enter active flag names e.g.     │
│  "SYN ACK" (space-separated).          │
└──────────────────────────────────────────┘""".trimIndent(),

        "ENCRYPTION_CRACKING" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: ENCRYPTION CRACKING           │
│  The attacker's command is Caesar-      │
│  encrypted. The shift value is shown.  │
│  Decode each letter: shift it back by  │
│  the given amount in the alphabet.      │
│  TIP: Submit the decoded plaintext.    │
└──────────────────────────────────────────┘""".trimIndent(),

        "TRACE_ROUTE" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: TRACE ROUTE                   │
│  A traceroute output has one hop        │
│  replaced with ???. The missing IP     │
│  follows subnet arithmetic — same /24, │
│  last octet increments by a fixed step. │
│  TIP: Submit the missing IP address.   │
└──────────────────────────────────────────┘""".trimIndent(),

        "REGEX_FILTERING" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: REGEX FILTERING               │
│  Log entries contain both benign and    │
│  malicious lines. Malicious entries     │
│  share a common IP prefix or keyword.  │
│  TIP: Submit the distinguishing         │
│  pattern that matches ONLY threats.    │
└──────────────────────────────────────────┘""".trimIndent(),

        "PASSWORD_CRACKING" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: PASSWORD CRACKING             │
│  A truncated hash and salt are shown.   │
│  Match against the common wordlist to  │
│  find the plaintext. You may also       │
│  submit the hash string directly.       │
│  TIP: Try admin, root, password first. │
└──────────────────────────────────────────┘""".trimIndent(),

        "FIREWALL_BYPASS" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: FIREWALL BYPASS               │
│  A firewall rule list shows monitored   │
│  ports. Open port scan results show    │
│  what's available. One open port is    │
│  absent from the rules — use it.        │
│  TIP: Compare scan to rule list.       │
└──────────────────────────────────────────┘""".trimIndent(),

        "CRYPTOGRAPHIC_CHALLENGE" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: CRYPTOGRAPHIC CHALLENGE       │
│  XOR the nonce and key hex values.      │
│  Process byte-by-byte: each pair of    │
│  hex chars = 1 byte. XOR each byte     │
│  and express result in hex.             │
│  TIP: 0xAB XOR 0xCD = 0x66             │
└──────────────────────────────────────────┘""".trimIndent(),

        "IDS_EVASION" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: IDS EVASION                   │
│  Active IDS rules are shown. Each      │
│  technique has specific signatures it  │
│  triggers. Select the one technique    │
│  that avoids ALL active rules.          │
│  TIP: Submit the option letter (A-D).  │
└──────────────────────────────────────────┘""".trimIndent(),

        "MULTI_FACTOR_BYPASS" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: MULTI-FACTOR BYPASS           │
│  Compute a simplified TOTP code.        │
│  XOR the seed (hex integer) with the   │
│  time token, then take mod 1000000.    │
│  Zero-pad to 6 digits.                 │
│  TIP: seed ^ time_token % 1000000      │
└──────────────────────────────────────────┘""".trimIndent(),

        "CERTIFICATE_BYPASS" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: CERTIFICATE BYPASS            │
│  Two hex fingerprints are shown.        │
│  Find where they differ — each pair    │
│  of hex chars = 1 byte (0-indexed).    │
│  Report byte positions of mismatches.  │
│  TIP: Submit offset(s), space-sep.     │
└──────────────────────────────────────────┘""".trimIndent(),

        "CAPTCHA_SOLVING" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: CAPTCHA SOLVING               │
│  ASCII art shows distorted characters.  │
│  Each 3-column block = 1 character.    │
│  Focus on the dominant shape of each   │
│  block — top/bottom rows are key.       │
│  TIP: Submit all chars as one word.    │
└──────────────────────────────────────────┘""".trimIndent(),

        "NETWORK_SEGMENTATION" to """
┌──────────────────────────────────────────┐
│  TUTORIAL: NETWORK SEGMENTATION          │
│  A multi-segment topology is shown.     │
│  Route from entry to target by passing │
│  through each segment's gateway in     │
│  order.                                 │
│  TIP: Submit gateway IPs, space-sep.   │
└──────────────────────────────────────────┘""".trimIndent()
    )

    /**
     * If the player hasn't seen the tutorial for this type, prepend it to content.
     * Always marks the type as seen after first call.
     */
    fun maybePrepend(content: String, typeKey: String, progress: PlayerPuzzleProgress): String {
        val tutorial = tutorials[typeKey] ?: return content
        if (progress.hasSeen(typeKey)) return content
        progress.markSeen(typeKey)
        return "$tutorial\n\n$content"
    }
}
