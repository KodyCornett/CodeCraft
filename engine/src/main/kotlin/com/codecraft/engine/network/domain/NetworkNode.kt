package com.codecraft.engine.network.domain

import java.util.UUID

/**
 * Represents a network node in the game world
 */
data class NetworkNode(
    val nodeId: UUID,
    val nodeName: String,
    val nodeType: NodeType,
    val district: District? = null,
    val coordX: Int,
    val coordY: Int,
    val ipAddress: String,
    val signalStrength: Int = 100,
    val securityLevel: Int = 1,
    val isPublic: Boolean = true,
    val isMissionCritical: Boolean = false
) {
    init {
        require(signalStrength in 0..100) { "Signal strength must be between 0 and 100" }
        require(securityLevel in 1..5) { "Security level must be between 1 and 5" }
        require(nodeName.isNotBlank()) { "Node name cannot be blank" }
    }
}

/**
 * Represents a geographic district in the network
 */
data class District(
    val districtId: UUID,
    val name: String,
    val type: DistrictType,
    val centerX: Int,
    val centerY: Int,
    val radius: Int,
    val ipPrefix: String,
    val density: NodeDensity = NodeDensity.MEDIUM,
    val description: String? = null,
    val unlockCondition: String? = null
) {
    init {
        require(name.isNotBlank()) { "District name cannot be blank" }
        require(radius > 0) { "Radius must be positive" }
    }
}

/**
 * Represents a connection between two nodes
 */
data class NodeConnection(
    val id: UUID,
    val nodeAId: UUID,
    val nodeBId: UUID,
    val distance: Int,
    val connectionQuality: Int = 100,
    val connectionType: ConnectionType = ConnectionType.DIRECT,
    val isPublic: Boolean = true,
    val isBidirectional: Boolean = true
) {
    init {
        require(nodeAId != nodeBId) { "Cannot connect node to itself" }
        require(distance >= 0) { "Distance must be non-negative" }
        require(connectionQuality in 0..100) { "Connection quality must be between 0 and 100" }
    }
}
