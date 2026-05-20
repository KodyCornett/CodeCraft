package com.codecraft.engine.domain

import kotlin.random.Random

/**
 * Probability-based detection system.
 *
 * Detection Chance = Base Risk x Firewall Multiplier
 */
object Detection {

    /**
     * Calculate base risk based on exposure level
     */
    fun calculateBaseRisk(exposure: Double): Double {
        return when {
            exposure <= 60.0 -> 0.0
            exposure <= 70.0 -> (exposure - 60.0) * 1.0  // 0-10%
            exposure <= 85.0 -> (exposure - 60.0) * 2.0  // 0-50%
            else -> (exposure - 60.0) * 3.0               // 0-120% (clamped)
        }.coerceIn(0.0, 100.0)
    }

    /**
     * Calculate firewall multiplier based on firewall health
     */
    fun calculateFirewallMultiplier(firewallPercent: Double): Double {
        return when {
            firewallPercent >= 80.0 -> 0.3
            firewallPercent >= 60.0 -> 0.6
            firewallPercent >= 40.0 -> 1.0
            firewallPercent >= 20.0 -> 1.8
            else -> 3.0
        }
    }

    /**
     * Calculate the detection chance (0-100%)
     */
    fun calculateDetectionChance(
        exposure: Double,
        firewallCurrent: Double,
        firewallMax: Double = 100.0,
        nodeMultiplier: Double = 1.0  // Node-specific detection multiplier
    ): Double {
        val baseRisk = calculateBaseRisk(exposure)
        val firewallPercent = if (firewallMax > 0) (firewallCurrent / firewallMax) * 100.0 else 0.0
        val firewallMultiplier = calculateFirewallMultiplier(firewallPercent)
        return (baseRisk * firewallMultiplier * nodeMultiplier).coerceIn(0.0, 100.0)
    }

    /**
     * Roll for detection — returns true if detected
     */
    fun rollForDetection(
        exposure: Double,
        firewallCurrent: Double,
        firewallMax: Double = 100.0,
        nodeMultiplier: Double = 1.0  // Node-specific detection multiplier
    ): Boolean {
        val chance = calculateDetectionChance(exposure, firewallCurrent, firewallMax, nodeMultiplier)
        if (chance <= 0.0) return false
        return Random.nextDouble(100.0) < chance
    }

    /**
     * Get stealth rating based on exposure level
     */
    fun getStealthRating(exposure: Double): String {
        return when {
            exposure < 10.0 -> "GHOST"      // Perfect stealth
            exposure < 25.0 -> "EXCELLENT"
            exposure < 40.0 -> "GOOD"
            exposure < 60.0 -> "FAIR"
            exposure < 75.0 -> "RISKY"
            exposure < 90.0 -> "DANGEROUS"
            else -> "CRITICAL"
        }
    }

    /**
     * Get stealth rating color for display
     */
    fun getStealthRatingColor(exposure: Double): String {
        return when {
            exposure < 25.0 -> "green"
            exposure < 60.0 -> "yellow"
            else -> "red"
        }
    }

    /**
     * Get a warning message for the current danger level
     */
    fun getWarningMessage(exposure: Double): String? {
        return when {
            exposure >= 90.0 -> "CRITICAL: Detection imminent. Disconnect immediately."
            exposure >= 80.0 -> "DANGER: Exposure critical. High detection probability."
            exposure >= 70.0 -> "WARNING: Exposure elevated. Detection risk increasing."
            exposure >= 61.0 -> "CAUTION: Entering danger zone. Monitor exposure."
            else -> null
        }
    }
}
