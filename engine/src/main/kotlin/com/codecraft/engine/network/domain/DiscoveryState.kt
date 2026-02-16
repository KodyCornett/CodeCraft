package com.codecraft.engine.network.domain

/**
 * Player's discovery state for a network node
 */
enum class DiscoveryState {
    /**
     * Node exists in the world but player hasn't discovered it yet
     */
    UNDISCOVERED,

    /**
     * Player has scanned and found this node
     */
    DISCOVERED,

    /**
     * Player has connected to this node at least once
     */
    CONNECTED,

    /**
     * Player has full access (backdoor installed)
     */
    COMPROMISED,

    /**
     * Failed access attempt, node is locked out
     */
    LOCKED
}

/**
 * Type of connection between nodes
 */
enum class ConnectionType {
    DIRECT,        // Strong direct link
    RELAY,         // Routed through intermediate node
    BACKDOOR,      // Player-installed persistent connection
    WEAK_SIGNAL,   // Unreliable, may drop
    ENCRYPTED      // Secure, requires decryption
}

/**
 * District type classification
 */
enum class DistrictType {
    TECH_HUB,
    INDUSTRIAL,
    FINANCIAL,
    MEDICAL,
    RESIDENTIAL,
    COMMERCIAL,
    GOVERNMENT
}

/**
 * Node density in a district
 */
enum class NodeDensity {
    LOW,
    MEDIUM,
    HIGH
}
