package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Network node - represents a hackable target in the game
 */
@Serializable
data class Node(
    val id: String,
    val name: String,
    val ip: String,
    val type: NodeType,
    val securityLevel: Int = 1, // 1-10, affects difficulty

    // Open ports and services
    val ports: List<Port> = emptyList(),

    // Vulnerabilities that can be exploited
    val vulnerabilities: List<String> = emptyList(),

    // Files on this node
    val files: Map<String, String> = emptyMap(), // path -> content

    // Is this node currently compromised by player?
    var compromised: Boolean = false,

    // Does player have a backdoor installed?
    var hasBackdoor: Boolean = false,

    // Connected nodes (network topology)
    val connectedNodes: List<String> = emptyList(),

    // Organization that owns this node
    val organization: String? = null,

    // Root path for filesystem navigation
    val rootPath: String? = null,

    // Puzzle types this node uses for connection
    val puzzleTypes: List<String> = listOf("cipher"),

    // Loot available when hacked
    val loot: NodeLoot? = null,

    // Detection multiplier (node-specific security awareness)
    val detectionMultiplier: Double = 1.0,  // 0.7 (low security) to 1.5 (high security)

    // Public nodes are always visible on the map without scanning
    val isPublic: Boolean = false,

    // Alarm state (triggered by aggressive actions)
    var alarmActive: Boolean = false,
    var alarmExpiresAt: Long? = null,  // When alarm expires (5 minutes after trigger)
    var alarmTriggeredCount: Int = 0   // Track how many times alarm has been triggered
) {
    /**
     * Trigger alarm on this node (doubles detection for 5 minutes)
     */
    fun triggerAlarm() {
        alarmActive = true
        alarmExpiresAt = System.currentTimeMillis() + (5 * 60 * 1000)  // 5 minutes
        alarmTriggeredCount++
    }

    /**
     * Check if alarm is still active, clear if expired
     */
    fun updateAlarmState() {
        if (alarmActive && alarmExpiresAt != null && System.currentTimeMillis() > alarmExpiresAt!!) {
            alarmActive = false
            alarmExpiresAt = null
        }
    }

    /**
     * Get total detection multiplier (base + alarm)
     */
    fun getTotalDetectionMultiplier(): Double {
        updateAlarmState()
        return if (alarmActive) detectionMultiplier * 2.0 else detectionMultiplier
    }
}

@Serializable
enum class NodeType {
    PERSONAL,       // Home computers, easy targets
    CORPORATE,      // Company servers, medium difficulty
    GOVERNMENT,     // Government systems, high difficulty
    FINANCIAL,      // Banks, crypto exchanges
    SECURITY,       // Security firms, very difficult
    INFRASTRUCTURE, // Power grids, traffic systems
    UNDERGROUND     // Hacker servers, dark web
}

@Serializable
data class Port(
    val number: Int,
    val service: String,
    val version: String? = null,
    val state: PortState = PortState.OPEN,
    val requiresAuth: Boolean = false
)

@Serializable
enum class PortState {
    OPEN,
    CLOSED,
    FILTERED
}

@Serializable
data class NodeLoot(
    val credits: Int = 0,
    val data: List<String> = emptyList(), // Data files that can be sold
    val exploits: List<String> = emptyList(), // Exploits that can be found
    val intel: List<String> = emptyList() // Information about other nodes
)

/**
 * The game's network state
 */
class NetworkState {
    private val nodes = mutableMapOf<String, Node>()

    init {
        // Initialize with starting nodes
        initializeNetwork()
    }

    private fun initializeNetwork() {
        // Player's local machine
        addNode(Node(
            id = "localhost",
            name = "Home Terminal",
            ip = "127.0.0.1",
            type = NodeType.PERSONAL,
            securityLevel = 0,
            isPublic = true,
            ports = listOf(Port(22, "ssh"), Port(80, "http")),
            files = mapOf(
                "/home/user/readme.txt" to "Welcome to CodeCraft.\n\nType 'help' to see available commands.",
                "/home/user/notes.txt" to "Remember: The password hint is in the server logs.\nDon't trust Marcus.",
                "/home/user/documents/mission_briefing.txt" to "TERMINAL BASICS - READ THIS FIRST\n\nYou're new here, so let's keep it simple:\n\n1. Use 'ls' to see files in the current directory\n2. Use 'cd' to navigate between folders  \n3. Use 'cat' to read file contents\n4. Use 'mail' to open your message box\n\nOnce you've got the basics down, check your inbox.\nReal work starts after that.\n\n- Ghost",
                "/home/user/documents/contacts.txt" to "GHOST - Reliable. Expensive.\nMARCUS - Avoid. Suspected fed.\nZERO - New. Unverified."
            ),
            connectedNodes = listOf("public-gateway"),
            compromised = true, // Player owns this
            detectionMultiplier = 0.0  // No detection on own machine
        ))

        // Public relay - open gateway between player and NovaCorp
        addNode(Node(
            id = "public-gateway",
            name = "Public Relay",
            ip = "45.33.32.1",
            type = NodeType.PERSONAL,
            securityLevel = 0,
            isPublic = true,
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.1"),
                Port(80, "http", "nginx 1.24.0"),
                Port(8080, "http-proxy", "Squid 5.7")
            ),
            files = mapOf(
                "/etc/motd" to "Welcome to Public Relay Node 7.",
                "/var/spool/notices.txt" to "=== PUBLIC RELAY NOTICE ===\n\nKnown routes:\n  192.168.50.0/24 — Corporate segment (NovaCorp)"
            ),
            connectedNodes = listOf("nova-corp-web"),
            compromised = true,
            detectionMultiplier = 0.7  // Public node - low security
        ))

        // First target - easy corporate server
        addNode(Node(
            id = "nova-corp-web",
            name = "NovaCorp Web Server",
            ip = "192.168.50.10",
            type = NodeType.CORPORATE,
            securityLevel = 2,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 7.9"),
                Port(80, "http", "nginx 1.18"),
                Port(443, "https"),
                Port(3306, "mysql", state = PortState.FILTERED)
            ),
            vulnerabilities = listOf("ssh_bruteforce", "sql_injection"),
            files = mapOf(
                "/var/www/config.php" to "<?php\n\$db_user = 'webapp';\n\$db_pass = 'corp2024!';\n?>",
                "/home/admin/notes.txt" to "Server maintenance scheduled for Sunday 2am.\nBackup admin password: n0v4_b4ckup_2024",
                "/data/credentials.dat" to "[ENCRYPTED DATA - Mission puzzle file]"
            ),
            connectedNodes = listOf("nova-corp-db", "nova-corp-mail"),
            rootPath = "/var/www",
            loot = NodeLoot(
                credits = 500,
                data = listOf("employee_emails.db"),
                intel = listOf("nova-corp-db")
            ),
            detectionMultiplier = 0.9  // Corporate web server - below average security
        ))

        // Second target - database server (requires pivoting)
        addNode(Node(
            id = "nova-corp-db",
            name = "NovaCorp Database",
            ip = "192.168.50.20",
            type = NodeType.CORPORATE,
            securityLevel = 4,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.2"),
                Port(3306, "mysql", "MySQL 8.0")
            ),
            vulnerabilities = listOf("mysql_exploit"),
            files = mapOf(
                "/data/hr/employees.db" to "[BINARY DATABASE FILE - Employee records]",
                "/data/finance/transactions.db" to "[BINARY DATABASE FILE - Financial transactions]",
                "/data/executive_memo.enc" to "[ENCRYPTED COMMUNICATION - Mission puzzle file]",
                "/opt/scripts/meridian_sync.sh" to "#!/bin/bash\n# meridian_sync.sh - Automated data sync\n# Target: MERIDIAN-PRIME\n# Schedule: Daily 0300 UTC\n# Contact: E.Holst (REMOVED)\n\ncurl -s https://meridian.internal/api/sync \\\n  --cert /etc/ssl/meridian.pem \\\n  --data-binary @/data/export/daily_feed.enc\n\n# DO NOT MODIFY - authorized by Director Hale"
            ),
            connectedNodes = listOf("nova-corp-mail"),
            rootPath = "/data",
            loot = NodeLoot(
                credits = 2500,
                data = listOf("employees.db", "transactions.db")
            ),
            detectionMultiplier = 1.1  // Database server - elevated security
        ))

        // NovaCorp Mail Server
        addNode(Node(
            id = "nova-corp-mail",
            name = "NovaCorp Mail Server",
            ip = "192.168.50.30",
            type = NodeType.CORPORATE,
            securityLevel = 3,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.2"),
                Port(25, "smtp", "Postfix"),
                Port(143, "imap", "Dovecot"),
                Port(993, "imaps")
            ),
            files = mapOf(
                "/var/mail/webb/inbox/re_project_update.txt" to "From: k.chen@novacorp.com\nTo: m.webb@novacorp.com\nSubject: RE: Project Update\n\nMarcus,\n\nThe Meridian integration is ahead of schedule. Director Hale's team\nhas been very... insistent about timelines.\n\nI'm uncomfortable with the scope of data we're providing. This goes\nbeyond the original contract. Can we discuss offline?\n\n- Karen",
                "/var/mail/webb/inbox/meeting_notes.txt" to "From: m.webb@novacorp.com\nTo: security-team@novacorp.com\nSubject: Security Audit Notes\n\nTeam,\n\nFollowing the audit, we need to:\n1. Rotate all API keys (especially the Meridian ones)\n2. Review access logs for the DB server\n3. Check the sync script permissions\n\nAlso - has anyone heard from Erik Holst? His access was revoked\nbut I'm seeing queries from his old credentials.\n\n- Marcus Webb, Security",
                "/var/mail/admin/sent/holst_termination.txt" to "From: hr@novacorp.com\nTo: admin@novacorp.com\nSubject: Access Revocation - E. Holst\n\nPlease immediately revoke all access for Erik Holst.\nContract terminated effective immediately.\nReason: Unauthorized access to classified materials.\n\nDo NOT contact Holst directly. Legal is handling."
            ),
            connectedNodes = listOf("nova-corp-sec"),
            rootPath = "/var/mail",
            detectionMultiplier = 1.0  // Mail server - standard corporate security
        ))

        // NovaCorp Security Server
        addNode(Node(
            id = "nova-corp-sec",
            name = "NovaCorp Security",
            ip = "192.168.50.40",
            type = NodeType.CORPORATE,
            securityLevel = 5,
            organization = "NovaCorp",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.4"),
                Port(443, "https"),
                Port(514, "syslog")
            ),
            vulnerabilities = listOf("log_injection"),
            files = mapOf(
                "/var/log/auth.log" to "2024-12-01 03:14:22 [INFO] Login: webb@192.168.50.1\n2024-12-01 03:15:01 [WARN] Failed login: holst@10.0.0.99 (REVOKED)\n2024-12-01 03:15:03 [WARN] Failed login: holst@10.0.0.99 (REVOKED)\n2024-12-01 03:15:05 [ALERT] Brute force detected: holst@10.0.0.99\n2024-12-02 14:30:00 [INFO] SIGINT query: SELECT * FROM employees WHERE name LIKE '%holst%'\n2024-12-02 14:30:01 [INFO] SIGINT query: SELECT * FROM access_log WHERE user_id = 'eholst'\n2024-12-03 09:00:00 [INFO] Meridian sync: 847 records exported",
                "/etc/security/access_policy.conf" to "# NovaCorp Security Policy\n# Last modified: 2024-11-28\n\n[EXTERNAL_ACCESS]\ndeny_list = holst, johnson, chen_temp\nallow_sigint = true\nmeridian_clearance = LEVEL_3\n\n[AUDIT]\nlog_all_queries = true\nretention_days = 90\nalert_on_revoked_access = true"
            ),
            rootPath = "/var/log",
            loot = NodeLoot(
                credits = 3000,
                data = listOf("auth.log", "access_policy.conf")
            ),
            detectionMultiplier = 1.3  // Security server - high awareness
        ))

        // DataMind startup server (Mission 3 target)
        addNode(Node(
            id = "datamind-server",
            name = "DataMind Research Server",
            ip = "10.20.30.40",
            type = NodeType.CORPORATE,
            securityLevel = 5,
            organization = "DataMind",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.4"),
                Port(443, "https"),
                Port(5432, "postgresql")
            ),
            vulnerabilities = listOf("path_traversal"),
            files = mapOf(
                "/home/dev/readme.txt" to "DataMind Research Division\nAI Prototype Development Team",
                "/research/prototype_v3.bin" to "[BINARY ENCODED DATA - Mission puzzle file]",
                "/research/notes.txt" to "Neural sync experiments showing promising results.\nProtocol documentation in v3.bin (binary encoded for security)."
            ),
            loot = NodeLoot(
                credits = 3500,
                data = listOf("prototype_specs.dat")
            ),
            detectionMultiplier = 1.2  // Tech startup - above average security
        ))

        // Underground contact's server
        addNode(Node(
            id = "ghost-relay",
            name = "Ghost's Relay",
            ip = "10.13.37.1",
            type = NodeType.UNDERGROUND,
            securityLevel = 6,
            organization = "Underground",
            ports = listOf(
                Port(22, "ssh"),
                Port(6667, "irc"),
                Port(9050, "tor")
            ),
            files = mapOf(
                "/public/welcome.txt" to "Welcome to the underground.\nRules: No feds. No snitches. No traces.",
                "/public/jobs.txt" to "Current contracts available. Check the forum."
            ),
            detectionMultiplier = 1.4  // Underground server - paranoid security
        ))

        // ============================================
        // ACT II: THE TRAP - New Nodes
        // ============================================

        // Government contractor dev server (Mission 4 - honeypot)
        addNode(Node(
            id = "gov-contractor-dev",
            name = "GovTech Development Server",
            ip = "172.16.100.50",
            type = NodeType.GOVERNMENT,
            securityLevel = 7,
            organization = "GovTech Solutions",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.9"),
                Port(443, "https"),
                Port(8443, "https-alt"),
                Port(9000, "dev-api")
            ),
            vulnerabilities = listOf("backdoor_planted"),  // It's a trap
            files = mapOf(
                "/projects/classified/access_codes.sec" to "[PATTERN-LOCKED DATA - Mission puzzle file]",
                "/projects/classified/honeypot_credentials.txt" to "[ENCRYPTED] High-value admin credentials\nAccess level: ROOT\nSystems: ALL",
                "/projects/README.txt" to "GovTech Solutions - SIGINT Integration Project\nClassified: LEVEL 3\nContact: Director Hale",
                "/var/log/honeypot.log" to "[INTERNAL] Honeypot active. All access logged and traced.\nAlert threshold: ANY unauthorized access\nNotify: hale@sigint.gov"
            ),
            connectedNodes = listOf("sigint-proxy"),
            rootPath = "/projects",
            loot = NodeLoot(
                credits = 0,  // Trap - no reward
                data = listOf("fake_access_codes")
            ),
            detectionMultiplier = 1.5  // Government contractor - maximum security
        ))

        // Player's ISP server (Mission 5 - emergency cleanup)
        addNode(Node(
            id = "isp-local",
            name = "Regional ISP Server",
            ip = "198.51.100.1",
            type = NodeType.INFRASTRUCTURE,
            securityLevel = 5,
            organization = "ConnectCorp ISP",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.2"),
                Port(80, "http"),
                Port(443, "https"),
                Port(514, "syslog")
            ),
            vulnerabilities = listOf("weak_admin_password"),
            files = mapOf(
                "/var/log/connections/subscriber_logs.dat" to "[SCRAMBLED AUTH - Mission puzzle file]",
                "/var/log/connections/trace_${System.currentTimeMillis()}.log" to "Connection trace to 172.16.100.50 (gov-contractor-dev)\nSource: YOUR_IP\nTimestamp: [RECENT]\nDuration: 847 seconds\nData transferred: 2.3 MB",
                "/etc/retention_policy.txt" to "Log retention: 90 days\nBackup schedule: Daily 0200 UTC\nLegal holds: Active warrant from SIGINT Division\nDO NOT DELETE without authorization"
            ),
            rootPath = "/var/log",
            loot = NodeLoot(
                credits = 1000,
                data = listOf("connection_metadata")
            ),
            detectionMultiplier = 1.0  // ISP infrastructure - standard security
        ))

        // SIGINT proxy server (Mission 6 - Lena's introduction)
        addNode(Node(
            id = "sigint-proxy",
            name = "SIGINT Routing Proxy",
            ip = "203.0.113.10",
            type = NodeType.GOVERNMENT,
            securityLevel = 8,
            organization = "SIGINT Division",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.0"),
                Port(443, "https"),
                Port(8080, "proxy"),
                Port(9443, "secure-proxy")
            ),
            vulnerabilities = listOf("port_sequence_auth"),
            files = mapOf(
                "/auth/verification.seq" to "[PORT SEQUENCE AUTH - Mission puzzle file]",
                "/data/lena_creds.enc" to "[ENCRYPTED] Lena's SIGINT credentials\nFormer analyst - REVOKED 2023-04-15\nReason: Unauthorized data access\nClearance: LEVEL 4 (stripped)",
                "/data/admin_keys.txt" to "[ENCRYPTED] SIGINT Administrator Keys\nClearance: DIRECTOR LEVEL\nUse with extreme caution",
                "/routing/targets.txt" to "Active surveillance targets:\n- journalist networks\n- activist organizations\n- corporate whistleblowers\n- underground hacker groups\n\nMeridian integration: ACTIVE"
            ),
            connectedNodes = listOf("evidence-server", "meridian-node-01"),
            rootPath = "/",
            loot = NodeLoot(
                credits = 2000,
                data = listOf("routing_table", "lena_creds.enc")
            ),
            detectionMultiplier = 1.5  // SIGINT infrastructure - maximum security
        ))

        // SIGINT evidence server (Mission 7 - evidence deletion)
        addNode(Node(
            id = "evidence-server",
            name = "SIGINT Evidence Archive",
            ip = "203.0.113.20",
            type = NodeType.GOVERNMENT,
            securityLevel = 9,
            organization = "SIGINT Division",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.1"),
                Port(443, "https"),
                Port(5432, "postgresql")
            ),
            vulnerabilities = listOf("hex_auth_bypass"),
            files = mapOf(
                "/secure/deletion_auth.hex" to "[HEX-ENCODED AUTH - Mission puzzle file]",
                "/secure/root_access.key" to "[ENCRYPTED] Root Access Key\nSystems: ALL EVIDENCE SERVERS\nValid until: NEVER EXPIRES",
                "/evidence/conn_log_001.dat" to "[EVIDENCE] Connection: YOUR_IP -> 172.16.100.50\nCase: GOV-2024-1847\nClassification: Federal intrusion",
                "/evidence/pcap_002.dat" to "[EVIDENCE] Packet capture from honeypot\nDuration: 847 seconds\nPayload: Access code extraction attempt",
                "/evidence/trace_003.dat" to "[EVIDENCE] Session trace\nCommands executed: 23\nFiles accessed: 7\nExfiltration detected: YES",
                "/backup/schedule.txt" to "Backup schedule: Every 20 minutes\nOffsite archive: Immediate\nRetention: PERMANENT\nNext backup: [COUNTDOWN]"
            ),
            rootPath = "/evidence",
            loot = NodeLoot(
                credits = 3000,
                data = listOf("case_files")
            ),
            detectionMultiplier = 1.5  // SIGINT evidence - maximum security
        ))

        // ============================================
        // ACT III: THE DEVIL'S OFFER - New Nodes
        // ============================================

        // Journalist laptop (Mission 8 - Hale's first test)
        addNode(Node(
            id = "journalist-laptop",
            name = "Journalist Personal Laptop",
            ip = "74.125.200.15",
            type = NodeType.PERSONAL,
            securityLevel = 4,
            organization = "Independent Press",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.4"),
                Port(443, "https")
            ),
            vulnerabilities = listOf("reverse_cipher"),
            files = mapOf(
                "/documents/sources.enc" to "[REVERSE CIPHER - Mission puzzle file]",
                "/documents/whistleblower_contacts.txt" to "Anonymous sources (CONFIDENTIAL):\n- Government analyst (codename: ORACLE)\n- Corporate insider (codename: RAVEN)\n- Former SIGINT contractor (Erik H.)",
                "/documents/meridian_article_draft.txt" to "DRAFT - DO NOT PUBLISH\n\nTitle: 'Project Meridian: The Surveillance Program Nobody Knows About'\n\nSources confirm that a domestic surveillance program code-named Meridian\nhas been operating since 2019, collecting data from dozens of corporations\nwithout oversight or warrants.\n\n[INCOMPLETE - need more evidence]"
            ),
            rootPath = "/documents",
            loot = NodeLoot(
                credits = 1500,
                data = listOf("source_list")
            ),
            detectionMultiplier = 0.8  // Personal laptop - below average security
        ))

        // Activist server (Mission 9a - Hale path)
        addNode(Node(
            id = "activist-server",
            name = "Digital Rights Coalition Server",
            ip = "185.220.100.50",
            type = NodeType.PERSONAL,
            securityLevel = 6,
            organization = "Digital Rights Coalition",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.6"),
                Port(80, "http"),
                Port(443, "https")
            ),
            vulnerabilities = listOf("word_scramble_auth"),
            files = mapOf(
                "/db/members.scrambled" to "[WORD JUMBLE AUTH - Mission puzzle file]",
                "/organizing/protest_schedule.txt" to "Upcoming protests against government surveillance:\n- Capitol rally: March 15\n- Tech company sit-in: March 22\n- Media campaign launch: April 1",
                "/private/donor_list.txt" to "Coalition donors (CONFIDENTIAL):\nProtect this list. Government has requested it multiple times.\nWe are legally protected but expect pressure."
            ),
            rootPath = "/db",
            loot = NodeLoot(
                credits = 2000,
                data = listOf("member_database")
            ),
            detectionMultiplier = 1.1  // Activist server - decent security, privacy-focused
        ))

        // Meridian node 01 (Mission 9b - Lena path)
        addNode(Node(
            id = "meridian-node-01",
            name = "Meridian Collection Node Alpha",
            ip = "198.18.0.10",
            type = NodeType.GOVERNMENT,
            securityLevel = 10,
            organization = "SIGINT Division - Meridian Program",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.2"),
                Port(443, "https"),
                Port(8443, "secure-api")
            ),
            vulnerabilities = listOf("binary_auth_bypass"),
            files = mapOf(
                "/meridian/authorization.bin" to "[BINARY AUTH - Mission puzzle file]",
                "/meridian/surveillance_logs.dat" to "[CLASSIFIED] Meridian surveillance records\nTargets: 15,847 individuals\nCorporate partners: 43 companies\nData retention: INDEFINITE",
                "/meridian/holst_investigation.txt" to "Investigation: Erik Holst\nStatus: TERMINATED\nReason: Unauthorized access to Meridian files\nAction taken: Employment terminated, credentials revoked\nCurrent location: UNKNOWN\nThreat level: MEDIUM"
            ),
            connectedNodes = listOf("meridian-node-02", "meridian-core"),
            rootPath = "/meridian",
            loot = NodeLoot(
                credits = 5000,
                data = listOf("surveillance_logs", "evidence_files")
            ),
            detectionMultiplier = 1.5  // Meridian infrastructure - maximum security
        ))

        // Meridian node 02 (Mission 10 - data harvest)
        addNode(Node(
            id = "meridian-node-02",
            name = "Meridian Collection Node Beta",
            ip = "198.18.0.20",
            type = NodeType.GOVERNMENT,
            securityLevel = 10,
            organization = "SIGINT Division - Meridian Program",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.2"),
                Port(443, "https"),
                Port(5432, "postgresql")
            ),
            vulnerabilities = listOf("caesar_encryption"),
            files = mapOf(
                "/collection/metadata_q4.enc" to "[CAESAR CIPHER - Mission puzzle file]",
                "/collection/corporate_feeds.txt" to "Active data feeds:\n- NovaCorp: Employee communications, location data\n- DataMind: Research metadata, email headers\n- TechGiant Inc: Search queries, browsing history\n- ConnectCorp ISP: Connection logs, traffic metadata\n\nTotal records: 2.4 billion",
                "/collection/legal_cover.txt" to "Legal justification: National Security Letter #2019-4782\nOversight: CLASSIFIED\nExpiration: NONE\nDirector authorization: Director Hale"
            ),
            connectedNodes = listOf("meridian-core"),
            rootPath = "/collection",
            loot = NodeLoot(
                credits = 6000,
                data = listOf("metadata_archive")
            ),
            detectionMultiplier = 1.5  // Meridian infrastructure - maximum security
        ))

        // Holst's dead drop (Mission 11 - final evidence)
        addNode(Node(
            id = "holst-dead-drop",
            name = "Secure Dead Drop Server",
            ip = "192.0.2.99",
            type = NodeType.UNDERGROUND,
            securityLevel = 9,
            organization = "Erik Holst (former NovaCorp)",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 8.8"),
                Port(443, "https")
            ),
            vulnerabilities = listOf("fibonacci_pattern_lock"),
            files = mapOf(
                "/archive/meridian_auth_original.sec" to "[PATTERN LOCK - Mission puzzle file]",
                "/archive/holst_message.txt" to "If you're reading this, I'm either dead or in hiding.\n\nI discovered Meridian in 2022 while working for NovaCorp. It's worse\nthan you think. Mass surveillance on US citizens, zero oversight,\ncompletely illegal.\n\nI tried to expose it through proper channels. That's when they came after me.\n\nThe original authorization is in this dead drop. Signed by Hale himself.\nIt's proof. Use it.\n\n- Erik Holst",
                "/archive/meridian_authorization_2019.pdf" to "[CLASSIFIED DOCUMENT]\n\nPROJECT MERIDIAN\nAuthorization: Director James Hale, SIGINT Division\nDate: 2019-08-15\nScope: Domestic mass surveillance program\nLegal basis: [REDACTED]\nOversight: NONE\n\nObjective: Collection and analysis of communications metadata\nfrom US persons for national security purposes.\n\nSignature: [J. Hale]"
            ),
            rootPath = "/archive",
            loot = NodeLoot(
                credits = 7000,
                data = listOf("meridian_authorization", "holst_evidence")
            ),
            detectionMultiplier = 1.3  // Dead drop - paranoid security, but not active monitoring
        ))

        // Meridian core (Mission 12 - finale)
        addNode(Node(
            id = "meridian-core",
            name = "Meridian Core Database",
            ip = "198.18.0.1",
            type = NodeType.GOVERNMENT,
            securityLevel = 10,
            organization = "SIGINT Division - Meridian Program",
            ports = listOf(
                Port(22, "ssh", "OpenSSH 9.3"),
                Port(443, "https"),
                Port(5432, "postgresql"),
                Port(8443, "admin-api")
            ),
            vulnerabilities = listOf("hex_master_auth"),
            files = mapOf(
                "/core/master_database.hex" to "[HEX MASTER AUTH - Mission puzzle file]",
                "/core/master_key.txt" to "[ENCRYPTED] Meridian Master Key\nAccess: ALL SYSTEMS\nClearance: UNLIMITED\nAuthorization: Director Hale",
                "/core/complete_target_list.dat" to "[CLASSIFIED] Complete Meridian target database\nRecords: 2,847,392 individuals\nCorporate sources: 43\nGovernment sources: 12\nRetention: PERMANENT",
                "/core/hale_directive.txt" to "DIRECTOR'S EYES ONLY\n\nMeridian has been compromised. Analyst L. Hayes (codename: Lena)\nhas copied surveillance logs and may have contacted external parties.\n\nTerminate all her access. Locate and neutralize.\n\nIf evidence leaks, activate Protocol Omega: Blame rogue contractor,\ndeny program existence, destroy all records.\n\n- Director Hale",
                "/core/shutdown_protocol.txt" to "MERIDIAN SHUTDOWN PROTOCOL\n\nIn event of exposure:\n1. Wipe all node databases\n2. Terminate all corporate feeds\n3. Destroy authorization documents\n4. Activate legal denial framework\n5. Prosecute leakers to full extent\n\nThis protocol must NEVER be executed unless absolutely necessary."
            ),
            rootPath = "/core",
            loot = NodeLoot(
                credits = 10000,
                data = listOf("complete_database", "all_evidence")
            ),
            detectionMultiplier = 1.5  // Meridian core - maximum security (final boss node)
        ))
    }

    private fun validateNode(node: Node) {
        require(node.name.isNotBlank()) { "Node ${node.id} has blank name" }
        require(node.ip.isNotBlank()) { "Node ${node.id} has blank IP" }
        require(node.ip.matches(Regex("\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}"))) {
            "Node ${node.id} has invalid IP: ${node.ip}"
        }
    }

    fun addNode(node: Node) {
        validateNode(node)
        nodes[node.id] = node
    }

    fun getNode(id: String): Node? = nodes[id]

    fun getNodeByIp(ip: String): Node? = nodes.values.find { it.ip == ip }

    fun getAllNodes(): List<Node> = nodes.values.toList()

    fun getDiscoverableFrom(nodeId: String): List<Node> {
        val node = nodes[nodeId] ?: return emptyList()
        return node.connectedNodes.mapNotNull { nodes[it] }
    }
}
