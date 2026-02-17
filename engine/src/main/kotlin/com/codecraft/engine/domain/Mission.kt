package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Puzzle types available for missions
 */
@Serializable
enum class PuzzleType {
    WORD_SEARCH,      // Find hidden words in grid
    CAESAR_CIPHER,    // Decode shifted text
    BINARY_DECODE,    // Convert binary to text
    PATTERN_MATCH,    // Find the sequence
    ANAGRAM,          // Unscramble words
    PORT_SEQUENCE,    // Complete port number sequences
    HEX_DECODE,       // Decode hex bytes to ASCII
    REVERSE,          // Reverse a string
    WORD_JUMBLE,      // Unscramble a word

    // Combat puzzles (used during sentinel/hacker attacks)
    MEMORY_FORENSICS,
    PORT_SCAN_INTERCEPTION,
    LOG_PATTERN_RECOGNITION,
    PROCESS_KILL,
    PACKET_INSPECTION,
    ENCRYPTION_CRACKING,
    TRACE_ROUTE,
    REGEX_FILTERING,

    // Node access puzzles (used when connecting to protected nodes)
    PASSWORD_CRACKING,
    FIREWALL_BYPASS,
    CRYPTOGRAPHIC_CHALLENGE,
    IDS_EVASION,
    MULTI_FACTOR_BYPASS,
    CERTIFICATE_BYPASS,
    CAPTCHA_SOLVING,
    NETWORK_SEGMENTATION
}

/**
 * Mission objective definition
 */
@Serializable
data class ObjectiveDefinition(
    val id: String,
    val description: String,
    val type: ObjectiveType,
    val target: String? = null,  // Target file, node, etc.
    val threshold: Int? = null,  // For REMAIN_UNDETECTED: max exposure %
    val timeLimit: Int? = null,  // Time limit in seconds (optional)
    val requiresObjective: String? = null  // ID of prerequisite objective
)

@Serializable
enum class ObjectiveType {
    CONNECT_NODE,     // Connect to a specific node
    DOWNLOAD_FILE,    // Download a specific file
    EXFILTRATE_FILE,  // Exfiltrate (deliver) file to contact
    SOLVE_PUZZLE,     // Solve the puzzle in a file
    EXTRACT_DATA,     // Extract data from a node
    REMAIN_UNDETECTED // Keep exposure below threshold
}

/**
 * Contact who offers jobs
 */
@Serializable
data class Contact(
    val id: String,
    val name: String,
    val alias: String,
    val faction: String,
    val description: String,
    val trustLevel: Int = 0  // -100 to 100
)

/**
 * Mission definition with puzzle requirements
 */
@Serializable
data class MissionDefinition(
    val id: String,
    val title: String,
    val contactId: String,           // Who offers the job
    val description: String,         // Short description for job listing
    val briefing: String,            // Full story text
    val targetNodeId: String,
    val targetFile: String?,         // File containing the puzzle (null for missions without puzzles)
    val baseReward: Int,
    val difficulty: Int,             // 1-5, affects puzzle complexity
    val requiredReputation: Int,     // Min rep with contact's faction
    val puzzleType: PuzzleType,
    val puzzleSolution: String,      // Expected answer
    val objectives: List<ObjectiveDefinition>,
    val bonusObjectives: List<ObjectiveDefinition> = emptyList(),  // Optional bonus objectives
    val estimatedTimeMinutes: Int = 30,  // Estimated completion time (for speed bonuses)
    val reputationReward: Map<String, Int>,  // Faction rep changes on completion
    val unlocksMissions: List<String> = emptyList()  // Mission IDs unlocked on completion
)

/**
 * Active mission state (player's current mission)
 */
@Serializable
data class ActiveMission(
    val definition: MissionDefinition,
    val startedAt: Long = System.currentTimeMillis(),
    val negotiatedReward: Int,       // Final agreed reward after negotiation
    val completedObjectives: MutableSet<String> = mutableSetOf(),
    val bonusObjectivesCompleted: MutableSet<String> = mutableSetOf(),
    val failedObjectives: MutableSet<String> = mutableSetOf(),
    var stealthViolated: Boolean = false,
    var peakExposure: Double = 0.0,  // Highest exposure reached during mission
    var finalPayout: Int? = null,
    var puzzleSolved: Boolean = false,
    var detectionCount: Int = 0,     // Track how many times detected during mission
    var failed: Boolean = false,     // Mission has failed (too many detections, time expired, etc.)
    var failureReason: String? = null // Why the mission failed
) {
    fun isComplete(): Boolean {
        // All required objectives must be complete and not failed
        return !failed && definition.objectives.all {
            it.id in completedObjectives || it.id in failedObjectives
        } && definition.objectives.any { it.id in completedObjectives }
    }

    fun isFailed(): Boolean {
        return failed
    }

    fun fail(reason: String) {
        failed = true
        failureReason = reason
    }

    /**
     * Check if mission has exceeded time limit
     */
    fun checkTimeLimit(): Boolean {
        val timeLimit = definition.objectives
            .filter { it.type == ObjectiveType.REMAIN_UNDETECTED && it.timeLimit != null }
            .mapNotNull { it.timeLimit }
            .minOrNull() ?: return false

        val elapsedSeconds = (System.currentTimeMillis() - startedAt) / 1000
        return elapsedSeconds > timeLimit
    }

    /**
     * Check if mission should auto-fail (too many detections)
     */
    fun checkDetectionLimit(): Boolean {
        return detectionCount >= 3
    }

    fun completeObjective(objectiveId: String): Boolean {
        val objective = definition.objectives.find { it.id == objectiveId }
            ?: definition.bonusObjectives.find { it.id == objectiveId }

        if (objective != null && objectiveId !in completedObjectives && objectiveId !in bonusObjectivesCompleted) {
            val isBonusObjective = definition.bonusObjectives.any { it.id == objectiveId }
            if (isBonusObjective) {
                bonusObjectivesCompleted.add(objectiveId)
            } else {
                completedObjectives.add(objectiveId)
            }
            return true
        }
        return false
    }

    fun getIncompleteObjectives(): List<ObjectiveDefinition> {
        return definition.objectives.filter {
            it.id !in completedObjectives && it.id !in failedObjectives
        }
    }
}

/**
 * Contact catalog
 */
object ContactCatalog {
    val contacts = listOf(
        Contact(
            id = "ghost",
            name = "Unknown",
            alias = "Ghost",
            faction = "underground",
            description = "Veteran hacker. Runs jobs for the underground network. Pays fair, expects results.",
            trustLevel = 0
        ),
        Contact(
            id = "zero",
            name = "Unknown",
            alias = "Zero",
            faction = "underground",
            description = "New player. Ambitious. Offers riskier jobs with higher payouts.",
            trustLevel = -20
        ),
        Contact(
            id = "cipher",
            name = "Unknown",
            alias = "Cipher",
            faction = "underground",
            description = "Cryptography specialist. Jobs involve data extraction and decryption.",
            trustLevel = 10
        ),
        Contact(
            id = "proxy",
            name = "Unknown",
            alias = "Proxy",
            faction = "corporate",
            description = "Corporate insider. Offers sanctioned corporate espionage jobs.",
            trustLevel = 0
        ),
        Contact(
            id = "lena",
            name = "Unknown",
            alias = "[REDACTED]",
            faction = "unknown",
            description = "Mysterious contact. Identity unknown. Offers high-risk intelligence jobs.",
            trustLevel = 0
        ),
        Contact(
            id = "hale",
            name = "Director Hale",
            alias = "Director Hale",
            faction = "government",
            description = "Head of SIGINT Division. Offers sanctioned government operations.",
            trustLevel = -50
        )
    )

    fun getById(id: String): Contact? = contacts.find { it.id == id }
    fun getByFaction(faction: String): List<Contact> = contacts.filter { it.faction == faction }
}

/**
 * Mission catalog - all available missions
 */
object MissionCatalog {

    val missions = listOf(
        // Mission 0: Tutorial Basics - First boot tutorial
        MissionDefinition(
            id = "tutorial_basics",
            title = "First Boot",
            contactId = "ghost",
            description = "Learn the basics of your terminal",
            briefing = """
                |Hey,
                |
                |Before you do anything else, get familiar with your system.
                |
                |Try these commands in the terminal:
                |
                |1. `ls` — see what's in your current directory
                |2. `cd documents` — navigate to your documents folder
                |3. `cat mission_briefing.txt` — read the briefing I left you
                |4. `mail` — check your inbox
                |
                |Once you've done all four, I'll know you're ready and send you the real work.
                |
                |Don't skip steps. This isn't a game.
                |
                |- Ghost
            """.trimMargin(),
            targetNodeId = "localhost",
            targetFile = null,  // Tutorial doesn't use puzzle files
            baseReward = 0,
            difficulty = 0,
            requiredReputation = -100,
            puzzleType = PuzzleType.WORD_SEARCH,  // Unused for tutorial
            puzzleSolution = "TUTORIAL",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "tut_ls",
                    description = "Use 'ls' to list files",
                    type = ObjectiveType.EXTRACT_DATA,  // Repurposed for command tracking
                    target = "command:ls"
                ),
                ObjectiveDefinition(
                    id = "tut_cd",
                    description = "Navigate with 'cd'",
                    type = ObjectiveType.EXTRACT_DATA,
                    target = "command:cd"
                ),
                ObjectiveDefinition(
                    id = "tut_cat",
                    description = "Read mission_briefing.txt",
                    type = ObjectiveType.EXTRACT_DATA,
                    target = "file:mission_briefing.txt"
                ),
                ObjectiveDefinition(
                    id = "tut_mail",
                    description = "Check messages",
                    type = ObjectiveType.EXTRACT_DATA,
                    target = "command:mail"
                )
            ),
            reputationReward = mapOf("underground" to 0),
            unlocksMissions = listOf("mission_1")
        ),

        // Mission 1: Ghost's First Job - Easy introduction
        MissionDefinition(
            id = "mission_1",
            title = "Ghost's First Job",
            contactId = "ghost",
            description = "Retrieve employee data from a mid-tier corporate server. Low exposure window.",
            briefing = """Ghost,

Target: NovaCorp subsidiary — they run a public-facing web server and a backend
database with loose access controls. Classic underfunded IT.

What we need: employee records stored on the web server. The data is encoded —
you'll need to decode it on-site. Client wants it clean.

The network isn't isolated. Start from your local machine, map the topology,
and work your way in. Don't linger.

Payment: §1500 on delivery.
Keep your exposure low. First impression counts.

- Ghost""",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/credentials.dat",
            baseReward = 1500,
            difficulty = 1,
            requiredReputation = -50,  // Available to almost everyone
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "ACCESS GRANTED SECURE",  // 3 words to find
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Establish access to the target server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                ),
                ObjectiveDefinition(
                    id = "obj_find_file",
                    description = "Locate and retrieve the employee records",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/credentials.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_solve_puzzle",
                    description = "Decode the extracted data",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/data/credentials.dat"
                )
            ),
            reputationReward = mapOf(
                "underground" to 10,
                "corporate" to -5
            ),
            unlocksMissions = listOf("mission_2")
        ),

        // Mission 2: Cipher's Request - Medium difficulty
        MissionDefinition(
            id = "mission_2",
            title = "The Cipher Job",
            contactId = "cipher",
            description = "Decode encrypted communications from NovaCorp database",
            briefing = """
                |Greetings,
                |
                |I've been watching your work. The Ghost job was... adequate.
                |
                |I have a more challenging task. NovaCorp's database server holds encrypted
                |communications between executives. The encryption is a classic Caesar cipher
                |- simple but effective against amateurs.
                |
                |The database is at 192.168.50.20. You'll need to pivot through their web
                |server first - the database isn't directly accessible.
                |
                |Find the encrypted memo, decode it, and extract the key phrase.
                |Keep your exposure low. My client values discretion.
                |
                |- Cipher
            """.trimMargin(),
            targetNodeId = "nova-corp-db",
            targetFile = "/data/executive_memo.enc",
            baseReward = 3000,
            difficulty = 2,
            requiredReputation = 0,
            puzzleType = PuzzleType.CAESAR_CIPHER,
            puzzleSolution = "OPERATION BLACKOUT",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect_web",
                    description = "Access NovaCorp web server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                ),
                ObjectiveDefinition(
                    id = "obj_connect_db",
                    description = "Pivot to database server (192.168.50.20)",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-db"
                ),
                ObjectiveDefinition(
                    id = "obj_decode",
                    description = "Decode the encrypted executive memo",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/data/executive_memo.enc"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected (exposure < 50%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 50
                )
            ),
            reputationReward = mapOf(
                "underground" to 15,
                "corporate" to -10
            ),
            unlocksMissions = listOf("mission_3")
        ),

        // Mission 3: Binary Dreams - Medium-hard
        MissionDefinition(
            id = "mission_3",
            title = "Binary Dreams",
            contactId = "zero",
            description = "Extract binary-encoded research data from a tech startup",
            briefing = """
                |Yo,
                |
                |Zero here. Got wind of your work with Ghost and Cipher. Impressive.
                |
                |Here's the deal: There's a tech startup called DataMind that's been doing
                |some interesting AI research. My client wants their latest prototype specs.
                |The file's encoded in binary - old school security through obscurity.
                |
                |The catch? DataMind has decent security. Not corporate level, but they're
                |not sleeping either. You'll need to move fast and stay quiet.
                |
                |Big risk, big reward. You in?
                |
                |- Zero
            """.trimMargin(),
            targetNodeId = "datamind-server",
            targetFile = "/research/prototype_v3.bin",
            baseReward = 4500,
            difficulty = 3,
            requiredReputation = 10,
            puzzleType = PuzzleType.BINARY_DECODE,
            puzzleSolution = "NEURAL SYNC PROTOCOL",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to DataMind server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "datamind-server"
                ),
                ObjectiveDefinition(
                    id = "obj_find",
                    description = "Locate the prototype specifications",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/research/prototype_v3.bin"
                ),
                ObjectiveDefinition(
                    id = "obj_decode",
                    description = "Decode the binary data",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/research/prototype_v3.bin"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected (exposure < 40%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 40
                )
            ),
            reputationReward = mapOf(
                "underground" to 20,
                "corporate" to -15
            ),
            unlocksMissions = listOf("mission_4")
        ),

        // ============================================
        // ACT II: THE TRAP
        // ============================================

        // Mission 4: Zero's Big Score - The honeypot
        MissionDefinition(
            id = "mission_4",
            title = "Zero's Big Score",
            contactId = "zero",
            description = "High-value target: Government contractor development server",
            briefing = """
                |Hey, listen up.
                |
                |I've got intel on a BIG score. Government contractor - they do work for
                |SIGINT, DoD, the whole alphabet soup. And their dev server? Wide open.
                |
                |The file we want has a pattern-locked encryption. You'll need to crack
                |the sequence to get the goods. But trust me, the payout is worth it.
                |
                |Ghost said this was too risky. That's how I know it's the real deal.
                |You in or you out?
                |
                |- Zero
            """.trimMargin(),
            targetNodeId = "gov-contractor-dev",
            targetFile = "/projects/classified/access_codes.sec",
            baseReward = 6000,
            difficulty = 4,
            requiredReputation = 15,
            puzzleType = PuzzleType.PATTERN_MATCH,
            puzzleSolution = "2 4 8 16 32",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to government contractor server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "gov-contractor-dev"
                ),
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract the classified access codes",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/projects/classified/access_codes.sec"
                ),
                ObjectiveDefinition(
                    id = "obj_crack",
                    description = "Crack the pattern-locked encryption",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/projects/classified/access_codes.sec"
                )
            ),
            estimatedTimeMinutes = 20,
            reputationReward = mapOf(
                "underground" to -30,  // This is a trap - loses rep
                "government" to -50
            ),
            unlocksMissions = listOf("mission_5")
        ),

        // Mission 5: The Fallout - Escape and survive
        MissionDefinition(
            id = "mission_5",
            title = "The Fallout",
            contactId = "ghost",
            description = "EMERGENCY: Scrub your connection logs from ISP server",
            briefing = """
                |This is Ghost. Emergency contact only.
                |
                |That contractor job was a honeypot. Zero's been caught. SIGINT has
                |your connection records and they're tracing back through your ISP.
                |
                |You have maybe 15 minutes before they get a warrant. Get into your
                |ISP's server, find the connection logs, and DELETE THEM. All of them.
                |
                |The auth system uses scrambled word verification. Solve it fast.
                |
                |Move. NOW.
                |
                |- Ghost
            """.trimMargin(),
            targetNodeId = "isp-local",
            targetFile = "/var/log/connections/subscriber_logs.dat",
            baseReward = 0,  // Survival mission - no payment
            difficulty = 3,
            requiredReputation = -100,  // Always available after mission_4
            puzzleType = PuzzleType.ANAGRAM,
            puzzleSolution = "EMERGENCY OVERRIDE",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Access your ISP server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "isp-local"
                ),
                ObjectiveDefinition(
                    id = "obj_find_logs",
                    description = "Locate the connection logs",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/var/log/connections/subscriber_logs.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_auth",
                    description = "Bypass authentication",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/var/log/connections/subscriber_logs.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_time",
                    description = "Complete within 15 minutes",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 100,  // Any exposure is risky
                    timeLimit = 900  // 15 minutes
                )
            ),
            estimatedTimeMinutes = 15,
            reputationReward = mapOf(
                "underground" to 5,
                "government" to -20
            ),
            unlocksMissions = listOf("mission_6")
        ),

        // Mission 6: Lena's Offer - Mystery contact revealed
        MissionDefinition(
            id = "mission_6",
            title = "Lena's Offer",
            contactId = "lena",
            description = "Unknown contact offers escape route through SIGINT proxy",
            briefing = """
                |You don't know me. Not yet.
                |
                |I've been watching your work. The NovaCorp jobs, the DataMind extraction,
                |and now... the contractor incident. You're good, but you're in deep.
                |
                |I can help. There's a SIGINT proxy server that routes surveillance data.
                |If we compromise it, I can feed them false leads. Buy you time.
                |
                |The authentication is port-sequence based. Solve it and we're in business.
                |
                |Oh, and if you find a credential file while you're in there... keep it safe.
                |We'll need it later.
                |
                |- [REDACTED]
            """.trimMargin(),
            targetNodeId = "sigint-proxy",
            targetFile = "/auth/verification.seq",
            baseReward = 3000,
            difficulty = 5,
            requiredReputation = -100,
            puzzleType = PuzzleType.PORT_SEQUENCE,
            puzzleSolution = "22 80 443 3306 8080",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to SIGINT proxy server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "sigint-proxy"
                ),
                ObjectiveDefinition(
                    id = "obj_auth",
                    description = "Crack the port-sequence authentication",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/auth/verification.seq"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected (exposure < 35%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 35
                )
            ),
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_creds",
                    description = "Find and extract Lena's credential file",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/lena_creds.enc"
                )
            ),
            estimatedTimeMinutes = 25,
            reputationReward = mapOf(
                "unknown" to 30,
                "government" to -25
            ),
            unlocksMissions = listOf("mission_7")
        ),

        // Mission 7: Erasing the Trail - Multi-stage cleanup
        MissionDefinition(
            id = "mission_7",
            title = "Erasing the Trail",
            contactId = "lena",
            description = "Delete evidence logs from SIGINT evidence server",
            briefing = """
                |It's Lena. We met through the proxy job.
                |
                |SIGINT has an evidence server with logs that connect you to the contractor
                |breach. Five specific files - connection records, packet captures, the works.
                |
                |All five must be deleted. Miss one and they can still build a case.
                |
                |The deletion auth uses hex-encoded verification. You've got 20 minutes
                |before the next backup cycle. After that, the logs are archived offsite.
                |
                |This is your last chance to disappear.
                |
                |- Lena
            """.trimMargin(),
            targetNodeId = "evidence-server",
            targetFile = "/secure/deletion_auth.hex",
            baseReward = 4500,
            difficulty = 6,
            requiredReputation = -100,
            puzzleType = PuzzleType.HEX_DECODE,
            puzzleSolution = "DELETE ALL EVIDENCE",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Access SIGINT evidence server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "evidence-server"
                ),
                ObjectiveDefinition(
                    id = "obj_delete_1",
                    description = "Delete connection log #1",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/evidence/conn_log_001.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_delete_2",
                    description = "Delete packet capture #2",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/evidence/pcap_002.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_delete_3",
                    description = "Delete session trace #3",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/evidence/trace_003.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_auth",
                    description = "Solve deletion authorization",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/secure/deletion_auth.hex"
                ),
                ObjectiveDefinition(
                    id = "obj_time",
                    description = "Complete before backup cycle (20 min)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 60,
                    timeLimit = 1200  // 20 minutes
                )
            ),
            estimatedTimeMinutes = 20,
            reputationReward = mapOf(
                "unknown" to 40,
                "government" to -30
            ),
            unlocksMissions = listOf("mission_8")
        ),

        // ============================================
        // ACT III: THE DEVIL'S OFFER
        // ============================================

        // Mission 8: Hale's Proposition - The branching point
        MissionDefinition(
            id = "mission_8",
            title = "Hale's Proposition",
            contactId = "hale",
            description = "Government offer: Work for SIGINT or face prosecution",
            briefing = """
                |This is Director Hale, SIGINT Division.
                |
                |We know about the contractor breach. We know about the evidence deletion.
                |We know about your contact 'Lena' - former analyst, now rogue.
                |
                |You have two options:
                |
                |Option A: Work for us. We have targets that need... unofficial handling.
                |Journalists, activists, leakers. You do the jobs, we clear your record.
                |
                |Option B: We build a case. Federal charges. You'll never touch a keyboard again.
                |
                |Your first test: A journalist has encrypted files we need. The encryption
                |is a simple reversal cipher. Prove your compliance.
                |
                |Choose wisely.
                |
                |- Director Hale
            """.trimMargin(),
            targetNodeId = "journalist-laptop",
            targetFile = "/documents/sources.enc",
            baseReward = 5000,
            difficulty = 5,
            requiredReputation = -100,
            puzzleType = PuzzleType.REVERSE,
            puzzleSolution = "SOURCES PROTECTED",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Access journalist's laptop",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "journalist-laptop"
                ),
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract encrypted source list",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/documents/sources.enc"
                ),
                ObjectiveDefinition(
                    id = "obj_decrypt",
                    description = "Decrypt the source list",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/documents/sources.enc"
                )
            ),
            estimatedTimeMinutes = 25,
            reputationReward = mapOf(
                "government" to 50,
                "underground" to -40
            ),
            unlocksMissions = listOf("mission_9a", "mission_10")
        ),

        // Mission 9a: The Activist - Hale's path
        MissionDefinition(
            id = "mission_9a",
            title = "The Activist",
            contactId = "hale",
            description = "SIGINT target: Compromise activist organization server",
            briefing = """
                |Director Hale again.
                |
                |Good work on the journalist. Your next target is an activist group
                |organizing protests against government surveillance programs.
                |
                |They're using a word-scramble authentication on their server. Crack it,
                |download their membership database, and report back.
                |
                |This is sanctioned work. You're doing your country a service.
                |
                |- Director Hale
            """.trimMargin(),
            targetNodeId = "activist-server",
            targetFile = "/db/members.scrambled",
            baseReward = 6000,
            difficulty = 6,
            requiredReputation = -100,
            puzzleType = PuzzleType.WORD_JUMBLE,
            puzzleSolution = "SURVEILLANCE STATE",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to activist server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "activist-server"
                ),
                ObjectiveDefinition(
                    id = "obj_download",
                    description = "Download membership database",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/db/members.scrambled"
                ),
                ObjectiveDefinition(
                    id = "obj_decrypt",
                    description = "Decrypt member list",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/db/members.scrambled"
                )
            ),
            estimatedTimeMinutes = 20,
            reputationReward = mapOf(
                "government" to 60,
                "underground" to -50
            ),
            unlocksMissions = listOf("mission_11")
        ),

        // Mission 9b: The Double Game - Lena's path
        MissionDefinition(
            id = "mission_9b",
            title = "The Double Game",
            contactId = "lena",
            description = "Counter-operation: Feed false data to SIGINT while gathering evidence",
            briefing = """
                |Lena here. I saw Hale's message to you.
                |
                |Don't work for them. They'll use you and then burn you like they did Erik Holst.
                |
                |Here's what we do instead: I've set up a false data feed. We give SIGINT
                |what they want - fake activist data - while we copy the REAL Meridian files.
                |
                |The Meridian node has binary-encoded authorization. Crack it, get the files,
                |and we can expose the whole operation.
                |
                |This is your chance to do the right thing.
                |
                |- Lena
            """.trimMargin(),
            targetNodeId = "meridian-node-01",
            targetFile = "/meridian/authorization.bin",
            baseReward = 5000,
            difficulty = 7,
            requiredReputation = -100,
            puzzleType = PuzzleType.BINARY_DECODE,
            puzzleSolution = "PROJECT MERIDIAN CLASSIFIED",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to Meridian node",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "meridian-node-01"
                ),
                ObjectiveDefinition(
                    id = "obj_auth",
                    description = "Crack binary authorization",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/meridian/authorization.bin"
                ),
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract Meridian evidence files",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/meridian/surveillance_logs.dat"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected (exposure < 30%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 30
                )
            ),
            estimatedTimeMinutes = 30,
            reputationReward = mapOf(
                "unknown" to 80,
                "government" to -70
            ),
            unlocksMissions = listOf("mission_11")
        ),

        // Mission 10: The Data Harvest - Continuing either path
        MissionDefinition(
            id = "mission_10",
            title = "The Data Harvest",
            contactId = "hale",
            description = "Extract mass surveillance data from Meridian collection node",
            briefing = """
                |Director Hale.
                |
                |Your work has been... acceptable. Now for something bigger.
                |
                |Meridian collects surveillance data from dozens of sources. We need a
                |specific dataset extracted - communication metadata from the past 90 days.
                |
                |The file is Caesar-cipher protected. Standard SIGINT encryption.
                |
                |This is a high-value operation. Compensation reflects that.
                |
                |- Director Hale
            """.trimMargin(),
            targetNodeId = "meridian-node-02",
            targetFile = "/collection/metadata_q4.enc",
            baseReward = 7500,
            difficulty = 7,
            requiredReputation = -100,
            puzzleType = PuzzleType.CAESAR_CIPHER,
            puzzleSolution = "MASS SURVEILLANCE ACTIVE",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect to Meridian collection node",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "meridian-node-02"
                ),
                ObjectiveDefinition(
                    id = "obj_download",
                    description = "Download metadata archive",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/collection/metadata_q4.enc"
                ),
                ObjectiveDefinition(
                    id = "obj_decrypt",
                    description = "Decrypt the metadata file",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/collection/metadata_q4.enc"
                )
            ),
            estimatedTimeMinutes = 25,
            reputationReward = mapOf(
                "government" to 70,
                "underground" to -60
            ),
            unlocksMissions = listOf("mission_11")
        ),

        // Mission 11: Point of No Return - Pre-finale
        MissionDefinition(
            id = "mission_11",
            title = "Point of No Return",
            contactId = "lena",
            description = "Final evidence collection: Erik Holst's original Meridian authorization",
            briefing = """
                |This is it. The final piece.
                |
                |Erik Holst - the contractor NovaCorp terminated - he discovered Meridian
                |before any of us. He tried to expose it and they buried him.
                |
                |But he left a dead drop. The ORIGINAL Meridian authorization documents.
                |Signed by Director Hale himself. Proof of the whole operation.
                |
                |The authentication is pattern-locked. Holst loved his sequences.
                |
                |Get those documents and we can bring down Meridian. All of it.
                |
                |After this, there's no going back.
                |
                |- Lena
            """.trimMargin(),
            targetNodeId = "holst-dead-drop",
            targetFile = "/archive/meridian_auth_original.sec",
            baseReward = 8000,
            difficulty = 8,
            requiredReputation = -100,
            puzzleType = PuzzleType.PATTERN_MATCH,
            puzzleSolution = "1 1 2 3 5 8 13",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Access Holst's dead drop server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "holst-dead-drop"
                ),
                ObjectiveDefinition(
                    id = "obj_auth",
                    description = "Crack Holst's pattern lock",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/archive/meridian_auth_original.sec"
                ),
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract original authorization documents",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/archive/meridian_auth_original.sec"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected (exposure < 25%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 25
                )
            ),
            estimatedTimeMinutes = 30,
            reputationReward = mapOf(
                "unknown" to 100,
                "government" to -100
            ),
            unlocksMissions = listOf("mission_12")
        ),

        // Mission 12: Meridian Down - The finale
        MissionDefinition(
            id = "mission_12",
            title = "Meridian Down",
            contactId = "lena",
            description = "FINALE: Infiltrate Meridian core and release all evidence",
            briefing = """
                |Final message.
                |
                |You have everything: the surveillance logs, the evidence, Holst's documents.
                |Now we finish this.
                |
                |Meridian's core server holds the master database - every target, every
                |intercept, every illegal operation. We're going to copy it all and release
                |it to every journalist, every oversight committee, every media outlet.
                |
                |The core uses hex-encoded master authorization. Crack it, download the
                |database, and get out.
                |
                |SIGINT will come down on you hard after this. Hale will deploy everything.
                |But the data will be out. Meridian will be exposed.
                |
                |Ghost has an extraction plan for you. After you release the data, follow
                |his instructions. He'll get you somewhere safe.
                |
                |This is what Erik Holst died trying to do. Finish it.
                |
                |- Lena
            """.trimMargin(),
            targetNodeId = "meridian-core",
            targetFile = "/core/master_database.hex",
            baseReward = 10000,
            difficulty = 10,
            requiredReputation = -100,
            puzzleType = PuzzleType.HEX_DECODE,
            puzzleSolution = "MERIDIAN EXPOSED FREEDOM WINS",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Infiltrate Meridian core server",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "meridian-core"
                ),
                ObjectiveDefinition(
                    id = "obj_master_auth",
                    description = "Crack master authorization",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/core/master_database.hex"
                ),
                ObjectiveDefinition(
                    id = "obj_database",
                    description = "Download complete Meridian database",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/core/master_database.hex"
                ),
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected until extraction (exposure < 50%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 50
                )
            ),
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_perfect",
                    description = "Complete with zero detection (exposure < 10%)",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 10
                )
            ),
            estimatedTimeMinutes = 45,
            reputationReward = mapOf(
                "unknown" to 200,
                "underground" to 150,
                "government" to -200
            ),
            unlocksMissions = emptyList()  // Final mission
        )
    )

    fun getById(id: String): MissionDefinition? = missions.find { it.id == id }

    fun getByContact(contactId: String): List<MissionDefinition> =
        missions.filter { it.contactId == contactId }

    /**
     * Get missions available to a player based on reputation and completed missions
     */
    fun getAvailableMissions(
        reputation: Map<String, Int>,
        completedMissions: Set<String>
    ): List<MissionDefinition> {
        // All missions that have been unlocked by completed missions
        val unlockedByCompleted = missions
            .filter { it.id in completedMissions }
            .flatMap { it.unlocksMissions }
            .toSet()

        // All missions that are gated behind another mission's completion
        val gatedMissions = missions.flatMap { it.unlocksMissions }.toSet()

        return missions.filter { mission ->
            // Skip tutorial missions — they auto-start, not board picks
            if (mission.id.startsWith("tutorial_")) return@filter false
            // Skip already completed
            if (mission.id in completedMissions) return@filter false
            // If gated, only show once unlocked
            if (mission.id in gatedMissions) return@filter mission.id in unlockedByCompleted
            // No prerequisites — always available
            true
        }
    }
}
