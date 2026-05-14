package com.codecraft.engine.models

import kotlinx.serialization.Serializable

/**
 * A footprint marker left by a player on a node when they move through it.
 *
 * isLoud = true for:
 *   - Cloak-expiry pings (visible at greater range, longer duration)
 *   - Bounty pings (marks high-value targets on the map)
 *
 * Players cannot see other players directly — only their ping trail.
 */
@Serializable
data class PingMarker(
    val pingId: String,
    val nodeId: String,
    val playerId: String,
    val createdAt: Long,    // epoch milliseconds
    val expiresAt: Long,    // epoch milliseconds
    val isLoud: Boolean = false,
)
