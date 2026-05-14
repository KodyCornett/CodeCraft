package com.codecraft.engine.models

import kotlinx.serialization.Serializable

/**
 * The five computed stat values for a player's rig.
 * Each value is base + upgrade level + peripheral boost, as returned by
 * GET /api/player/{id}/status from Laravel.
 */
@Serializable
data class EffectiveStats(
    val os: Int,
    val ram: Int,
    val cpu: Int,
    val storage: Int,
    val firewall: Int,
)
