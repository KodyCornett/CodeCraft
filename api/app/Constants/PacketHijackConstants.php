<?php

namespace App\Constants;

/**
 * PacketHijackConstants — immutable static data for the Packet Hijack engine.
 *
 * This class is a pure data store. No logic, no instantiation, no inheritance.
 * Services reference these via PacketHijackConstants::CONSTANT_NAME.
 *
 * Extracted from PacketHijackService to allow sub-domain services to stay
 * under the 500-line ceiling without duplicating large array definitions.
 */
final class PacketHijackConstants
{
    private function __construct() {}

    // =========================================================================
    // Port Services
    // =========================================================================

    /**
     * Service name + version strings per port — used for probe banner generation
     * and flare-line version substitution.
     */
    public const PORT_SERVICES = [
        21    => ['service' => 'FTP',      'versions' => ['vsftpd 3.0.3', 'ProFTPD 1.3.5', 'Pure-FTPd 1.0.47']],
        22    => ['service' => 'SSH',      'versions' => ['OpenSSH 7.2p1', 'OpenSSH 6.9', 'Dropbear 2019.78']],
        25    => ['service' => 'SMTP',     'versions' => ['Postfix 3.4.13', 'Sendmail 8.15.2', 'Exim 4.92']],
        53    => ['service' => 'DNS',      'versions' => ['BIND 9.16.1', 'Unbound 1.9.4', 'dnsmasq 2.80']],
        80    => ['service' => 'HTTP',     'versions' => ['Apache 2.4.49', 'nginx 1.18.0', 'Apache 2.2.34']],
        443   => ['service' => 'HTTPS',    'versions' => ['TLS 1.3 / nginx', 'TLS 1.2 / Apache', 'TLS 1.3 / Caddy']],
        3306  => ['service' => 'MySQL',    'versions' => ['MySQL 5.6.0', 'MySQL 5.7.32', 'MariaDB 10.3.27']],
        3389  => ['service' => 'RDP',      'versions' => ['RDP 10.0', 'RDP 8.1', 'FreeRDP 2.0']],
        5432  => ['service' => 'Postgres', 'versions' => ['PostgreSQL 13.3', 'PostgreSQL 12.7', 'PostgreSQL 14.1']],
        6379  => ['service' => 'Redis',    'versions' => ['Redis 6.2.6', 'Redis 5.0.14', 'Redis 7.0.0']],
        27017 => ['service' => 'MongoDB',  'versions' => ['MongoDB 4.4.6', 'MongoDB 5.0.3', 'MongoDB 6.0.1']],
    ];

    // =========================================================================
    // Chain Anomalies
    // =========================================================================

    /**
     * Relational anomaly templates for chain ports.
     * Keyed by [from_service][to_service] — describes the dependency relationship
     * shown to the attacker when they probe a chain port.
     */
    public const CHAIN_ANOMALIES = [
        'FTP'      => [
            'SSH'      => 'PLAIN-TEXT CREDENTIAL BLEED — SHARED AUTH TOKEN PATTERN MATCHES ENCRYPTED CHANNEL UPSTREAM',
            'HTTP'     => 'PASSIVE MODE RELAY DETECTED — TRANSFER STREAM CORRELATED WITH HTTP SESSION HANDLER',
            'MySQL'    => 'ANONYMOUS LOGIN PATH EXPOSES DATABASE STAGING AREA — DOWNSTREAM DB RELAY SUSPECTED',
            'Redis'    => 'FILE TRANSFER SESSION TAG CORRELATES WITH IN-MEMORY CACHE ENTRY — UPSTREAM RELAY DETECTED',
            'Postgres' => 'CREDENTIAL STAGING FILE DETECTED — DOWNSTREAM DATABASE AUTH DEPENDENCY SUSPECTED',
            'SMTP'     => 'BOUNCE RELAY PATTERN — MAIL HANDLER SHARES SESSION NAMESPACE WITH FTP DAEMON',
        ],
        'SSH'      => [
            'MySQL'    => 'KEY EXCHANGE DOWNGRADE PATTERN — SESSION CREDENTIAL BLEED TO DATABASE AUTH LAYER SUSPECTED',
            'Redis'    => 'TUNNELLED SESSION DETECTED — FORWARDED PORT CORRELATES WITH IN-MEMORY BROKER',
            'HTTP'     => 'AUTHORIZED KEYS FILE EXPOSES WEB SESSION NAMESPACE — CROSS-SERVICE DEPENDENCY',
            'Postgres' => 'PUBKEY AUTH PLUGIN SHARES CREDENTIAL STORE WITH DOWNSTREAM DATABASE — BLEED VECTOR ACTIVE',
            'FTP'      => 'HOST KEY MISMATCH — DOWNGRADE PATH CORRELATES WITH LEGACY FILE TRANSFER SERVICE',
            'SMTP'     => 'AGENT FORWARDING ACTIVE — MAIL RELAY SESSION SHARES SSH NAMESPACE',
        ],
        'HTTP'     => [
            'MySQL'    => 'SESSION COOKIE NAMESPACE LEAKS INTO DATABASE QUERY HANDLER — UPSTREAM DEPENDENCY DETECTED',
            'Redis'    => 'FASTCGI SESSION BROKER SHARES KEYSPACE WITH IN-MEMORY CACHE — RELAY VECTOR ACTIVE',
            'SSH'      => 'REVERSE PROXY CONFIG EXPOSES BACKEND SHELL — ENCRYPTED CHANNEL DEPENDENCY DETECTED',
            'Postgres' => 'ORM SESSION POOL LEAKS CREDENTIAL HASH — DOWNSTREAM DB AUTH BLEED SUSPECTED',
            'SMTP'     => 'FORM HANDLER SHARES MAIL RELAY NAMESPACE — SESSION TOKEN CORRELATION DETECTED',
            'DNS'      => 'VIRTUAL HOST RESOLUTION MISMATCH — DNS REBINDING PATTERN DETECTED UPSTREAM',
        ],
        'HTTPS'    => [
            'MySQL'    => 'TLS SESSION TICKET REUSE — DATABASE AUTH HANDLER SHARES RESUMPTION KEY',
            'Redis'    => 'TLS CERT PIN MISMATCH — CACHE BROKER SESSION ID BLEEDS THROUGH ENCRYPTED LAYER',
            'HTTP'     => 'CERTIFICATE DOWNGRADE NEGOTIATED — PLAIN HTTP FALLBACK CHANNEL ACTIVE DOWNSTREAM',
            'DNS'      => 'CERT TRANSPARENCY LOG ANOMALY — DNS RESOLVER DEPENDENCY IN VALIDATION CHAIN',
            'SSH'      => 'CLIENT CERT ISSUER MATCHES SSH HOST KEY AUTHORITY — SHARED PKI DEPENDENCY',
        ],
        'MySQL'    => [
            'Redis'    => 'QUERY CACHE ENTRIES LEAK INTO VOLATILE KEYSPACE — IN-MEMORY RELAY DEPENDENCY ACTIVE',
            'HTTP'     => 'STORED PROCEDURE EXPOSES SESSION NAMESPACE SHARED WITH WEB HANDLER',
            'FTP'      => 'DATABASE EXPORT STAGING PATH OVERLAPS WITH FILE TRANSFER DAEMON ROOT',
            'Postgres' => 'CROSS-DATABASE CREDENTIAL SHARE — REPLICATION LINK BRIDGES AUTH STORES',
            'SMTP'     => 'TRIGGER EVENT FIRES MAIL RELAY — SESSION TOKEN CARRIED IN NOTIFICATION PAYLOAD',
        ],
        'Redis'    => [
            'MySQL'    => 'VOLATILE KEYSPACE PATTERN — ACTIVE AUTH HANDSHAKE SESSION RELAYED TO DATABASE LAYER',
            'HTTP'     => 'SESSION BROKER CACHE FEEDS WEB HANDLER — TOKEN REUSE ACROSS SERVICE BOUNDARY',
            'SSH'      => 'PUBSUB CHANNEL LEAKS TUNNEL SESSION KEY — ENCRYPTED SHELL DEPENDENCY UPSTREAM',
            'MongoDB'  => 'KEYSPACE NOTIFICATION PATTERN MATCHES DOCUMENT STORE EVENT STREAM — RELAY ACTIVE',
            'Postgres' => 'CACHE INVALIDATION TIED TO DATABASE WRITE CYCLE — CREDENTIAL WINDOW EXPOSED',
        ],
        'SMTP'     => [
            'DNS'      => 'MX RECORD RESOLUTION ANOMALY — MAIL EXCHANGER CHAINS THROUGH RESOLVER — UPSTREAM DEPENDENCY',
            'HTTP'     => 'RELAY AGENT EXPOSES FORM-HANDLER SESSION — WEB LAYER DEPENDENCY DETECTED',
            'Redis'    => 'DELIVERY QUEUE SERIALISED INTO CACHE LAYER — SESSION TOKEN PRESENT IN PAYLOAD',
            'MySQL'    => 'BOUNCE TABLE SHARES DATABASE AUTH NAMESPACE — DOWNSTREAM DB RELAY SUSPECTED',
        ],
        'DNS'      => [
            'HTTP'     => 'REBINDING ATTACK PATTERN — LOCAL RESOLVER MAPS EXTERNAL HOST TO WEB HANDLER INTERNAL IP',
            'HTTPS'    => 'DNSSEC VALIDATION FAILURE — CERT CHAIN DEPENDENCY ON RESOLVER INTEGRITY BROKEN',
            'SMTP'     => 'PTR RECORD MISMATCH — MAIL RELAY DEPENDS ON RESOLVER STATE FOR AUTH HANDSHAKE',
            'Redis'    => 'DYNAMIC RECORD INJECTION DETECTED — CACHE POISONING VECTOR TO IN-MEMORY BROKER',
        ],
        'Postgres' => [
            'Redis'    => 'LISTEN/NOTIFY CHANNEL PUSHES SESSION EVENTS TO CACHE BROKER — RELAY DEPENDENCY ACTIVE',
            'HTTP'     => 'STORED PROCEDURE EXPOSES WEB SESSION CREDENTIAL — CROSS-LAYER BLEED DETECTED',
            'MySQL'    => 'FOREIGN DATA WRAPPER BRIDGES AUTH STORES — CREDENTIAL REUSE ACROSS DATABASES',
            'SMTP'     => 'DATABASE TRIGGER FIRES MAIL RELAY — SESSION TOKEN EXPOSED IN NOTIFICATION BODY',
        ],
        'MongoDB'  => [
            'Redis'    => 'DOCUMENT CHANGE STREAM FEEDS CACHE BROKER — EVENT PAYLOAD CONTAINS SESSION TOKEN',
            'HTTP'     => 'AGGREGATION PIPELINE LEAKS SESSION NAMESPACE INTO WEB REQUEST HANDLER',
            'MySQL'    => 'CROSS-STORE SYNC JOB SHARES CREDENTIAL FILE — AUTH BLEED BETWEEN DATABASES',
            'Postgres' => 'OPLOG REPLAY EXPOSES WRITE OPERATIONS — DOWNSTREAM DB AUTH DEPENDENCY DETECTED',
        ],
        'RDP'      => [
            'SSH'      => 'DESKTOP SESSION CREDENTIAL FORWARDING — OVERLAPPING AUTH STORE WITH SHELL SERVICE',
            'MySQL'    => 'REMOTE APPLICATION SHARES DATABASE CREDENTIAL FILE — DOWNSTREAM DB RELAY DETECTED',
            'HTTP'     => 'SESSION TOKEN REUSED ACROSS REMOTE DESKTOP AND WEB HANDLER — CROSS-SERVICE BLEED',
        ],
    ];

    // =========================================================================
    // Red Herring Anomalies
    // =========================================================================

    /**
     * Decoy anomaly pools keyed by OS tier.
     * OS 1–3 → 'low'  (obviously vague — easy to dismiss)
     * OS 4–6 → 'mid'  (sounds relational but generic)
     * OS 7+  → 'high' (closely mimics chain language — hardest to distinguish)
     */
    public const REDHERRING_ANOMALIES = [
        'low' => [
            'MISCONFIGURATION DETECTED — NON-STANDARD PORT CONFIGURATION',
            'SERVICE RUNNING OUTSIDE EXPECTED PARAMETERS',
            'UNUSUAL PROCESS BINDING — NOT EXPLOITABLE',
            'LEGACY PROTOCOL IN USE — NO KNOWN ACTIVE VECTOR',
            'CONFIGURATION DRIFT DETECTED — ISOLATED SERVICE',
        ],
        'mid' => [
            'UPSTREAM DEPENDENCY SUSPECTED — NO ACTIVE SIGNATURE MATCH',
            'INTER-SERVICE COMMUNICATION PATTERN — CORRELATION INCONCLUSIVE',
            'SESSION NAMESPACE OVERLAP — INDEPENDENT SERVICES',
            'AUTH HANDSHAKE ANOMALY — SOURCE UNRESOLVABLE',
            'RELAY PATTERN DETECTED — NO DOWNSTREAM DEPENDENCY CONFIRMED',
            'TOKEN REUSE SUSPECTED — CROSS-SERVICE ORIGIN UNCLEAR',
        ],
        'high' => [
            'CREDENTIAL BLEED PATTERN — SESSION TOKEN ORIGIN AMBIGUOUS — FURTHER CORRELATION REQUIRED',
            'VOLATILE KEYSPACE ACTIVITY — UPSTREAM RELAY POSSIBLE — NO CONFIRMED DEPENDENCY',
            'AUTH PLUGIN MISMATCH — DOWNSTREAM SERVICE UNIDENTIFIED — TRACE TO CONFIRM',
            'SESSION FORWARDING ACTIVE — TARGET SERVICE INDETERMINATE — CROSS-REFERENCE REQUIRED',
            'CERTIFICATE CHAIN ANOMALY — DEPENDENCY VECTOR PRESENT — UNRESOLVED SOURCE',
            'TUNNELLED SESSION DETECTED — DESTINATION ENDPOINT UNCONFIRMED — FURTHER RECON NEEDED',
        ],
    ];

    // =========================================================================
    // Port Flare
    // =========================================================================

    /**
     * Realistic noise-line pools per service port for probe banners.
     * Placeholders resolved at generation time:
     *   {n}   → random int 1–24
     *   {n2}  → random int 100–999
     *   {kb}  → random int 128–9999
     *   {kb2} → random int 128–9999
     *   {ts}  → random int 4–7200
     *   {ver} → service version string
     */
    public const PORT_FLARE = [
        21    => [
            'Transfer-Mode: BINARY',
            'Auth-Mode: PLAIN',
            'Passive-Mode: ENABLED',
            'Max-Connections: 50',
            'Idle-Timeout: 300s',
            'Session-Count: {n}',
            'Last-Transfer: {ts}s ago',
            'Bytes-In: {kb}K / Bytes-Out: {kb2}K',
            'Failed-Logins: {n}',
        ],
        22    => [
            'Key-Exchange: diffie-hellman-group14-sha256',
            'Auth-Methods: publickey,password',
            'Cipher: aes128-ctr',
            'Active-Sessions: {n}',
            'Failed-Auth: {n}',
            'Banner-Exchange: ENABLED',
            'Keep-Alive: 60s',
            'Host-Key-Type: RSA-4096',
            'Compression: none',
        ],
        25    => [
            'Queue-Size: {n} messages',
            'Relay-Status: OPEN',
            'TLS-Required: NO',
            'Auth-Methods: PLAIN LOGIN',
            'Bounce-Rate: {n}%',
            'Active-Connections: {n}',
            'Last-Delivery: {ts}s ago',
            'Max-Message-Size: 10MB',
            'Accepted-Domains: {n}',
        ],
        53    => [
            'Recursion: ENABLED',
            'DNSSEC: DISABLED',
            'Query-Rate: {n}/s',
            'Cache-Size: {kb}K entries',
            'Upstream-Resolvers: 2',
            'Response-Time: {n}ms avg',
            'Zone-Transfers: ALLOWED',
            'AXFR-Restricted: NO',
            'Negative-Cache-TTL: 30s',
        ],
        80    => [
            'Server: {ver}',
            'X-Powered-By: FastCGI',
            'Keep-Alive: timeout=5, max=100',
            'Content-Type: text/html',
            'Active-Connections: {n}',
            'Request-Rate: {n}/min',
            'Cache-Control: no-store',
            'Worker-Processes: {n}',
            'Upstream-Timeout: 30s',
        ],
        443   => [
            'Cipher-Suite: TLS_AES_256_GCM_SHA384',
            'OCSP-Status: GOOD',
            'Active-Sessions: {n}',
            'Session-Resumption: ENABLED',
            'HSTS: max-age=31536000',
            'Certificate-Expiry: {n} days',
            'Cert-Transparency: ENABLED',
            'Perfect-Forward-Secrecy: YES',
            'Renegotiation: DISABLED',
        ],
        3306  => [
            'Auth-Plugin: caching_sha2_password',
            'Charset: utf8mb4',
            'Status: AUTOCOMMIT',
            'Active-Connections: {n}',
            'Query-Cache: DISABLED',
            'Max-Allowed-Packet: 64MB',
            'Slow-Query-Log: ENABLED',
            'Uptime: {ts}s',
            'Open-Tables: {n}',
        ],
        3389  => [
            'RDP-Protocol: v10',
            'NLA-Required: NO',
            'Active-Sessions: {n}',
            'Clipboard-Redirect: ENABLED',
            'Drive-Redirect: ENABLED',
            'Encryption: 128-bit RC4',
            'Color-Depth: 32bpp',
            'Idle-Timeout: 900s',
            'Session-Broker: STANDALONE',
        ],
        5432  => [
            'Auth-Method: md5',
            'Max-Connections: 100',
            'Active-Connections: {n}',
            'Shared-Buffers: 128MB',
            'WAL-Level: REPLICA',
            'Logging: ENABLED',
            'SSL: OFF',
            'Extensions: uuid-ossp, pg_stat_statements',
            'Uptime: {ts}s',
        ],
        6379  => [
            'Connected-Clients: {n}',
            'Used-Memory: {kb}K',
            'Keyspace-Hits: {n} / Misses: {n2}',
            'Last-Save: {ts}s ago',
            'Blocked-Clients: 0',
            'Active-Channels: {n}',
            'Persistence: RDB + AOF',
            'Eviction-Policy: allkeys-lru',
            'Uptime: {ts}s',
        ],
        27017 => [
            'Auth: DISABLED',
            'Active-Connections: {n}',
            'Collections: {n}',
            'Storage-Engine: WiredTiger',
            'Oplog-Size: 512MB',
            'Replication: STANDALONE',
            'Journaling: ENABLED',
            'Cache-Size: {kb}MB',
            'Network-Compression: DISABLED',
        ],
    ];
}
