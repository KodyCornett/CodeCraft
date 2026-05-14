package com.codecraft.engine.models

import kotlinx.serialization.Serializable

/**
 * Lightweight node snapshot fetched from GET /api/nodes/{district}.
 * The engine only needs adjacency data for movement validation.
 */
@Serializable
data class NodeState(
    val id: String,
    val businessName: String,
    val district: String,
    val tier: Int,
    val adjacentNodeIds: List<String>,
)
