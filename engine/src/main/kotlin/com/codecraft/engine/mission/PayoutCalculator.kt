package com.codecraft.engine.mission

import com.codecraft.engine.domain.ActiveMission
import com.codecraft.engine.domain.DamageState
import com.codecraft.engine.domain.Player
import kotlin.math.max
import kotlin.math.min
import kotlin.math.roundToInt

/**
 * Calculates mission payout with bonuses and penalties.
 */
object PayoutCalculator {

    /**
     * Result of payout calculation with breakdown.
     */
    data class PayoutResult(
        val baseReward: Int,
        val bonuses: Map<String, Int>,
        val penalties: Map<String, Int>,
        val finalPayout: Int,
        val reputationGain: Int
    ) {
        val totalBonus: Int get() = bonuses.values.sum()
        val totalPenalty: Int get() = penalties.values.sum()
    }

    /**
     * Calculates the final payout for a mission.
     *
     * @param mission The active mission to calculate payout for
     * @param player The player completing the mission
     * @return PayoutResult with detailed breakdown
     */
    fun calculatePayout(mission: ActiveMission, player: Player): PayoutResult {
        val baseReward = mission.negotiatedReward
        val bonuses = mutableMapOf<String, Int>()
        val penalties = mutableMapOf<String, Int>()

        // === BONUSES ===

        // Bonus: All bonus objectives completed (+20%)
        if (areAllBonusObjectivesComplete(mission)) {
            bonuses["All Bonus Objectives"] = (baseReward * 0.20).roundToInt()
        }

        // Bonus: Stealth maintained (+15%)
        if (!mission.stealthViolated) {
            bonuses["Stealth Maintained"] = (baseReward * 0.15).roundToInt()
        }

        // Bonus: Fast completion (+10%)
        if (isCompletedQuickly(mission)) {
            bonuses["Fast Completion"] = (baseReward * 0.10).roundToInt()
        }

        // === PENALTIES ===

        // Penalty: Stealth violated (-10%)
        if (mission.stealthViolated) {
            penalties["Stealth Violated"] = (baseReward * 0.10).roundToInt()
        }

        // Penalty: Firewall damaged (-20%)
        if (isFirewallDamaged(player)) {
            penalties["Firewall Damaged"] = (baseReward * 0.20).roundToInt()
        }

        // Calculate final payout
        val totalBonus = bonuses.values.sum()
        val totalPenalty = penalties.values.sum()
        val rawPayout = baseReward + totalBonus - totalPenalty

        // Clamp to [50% of base, 150% of base]
        val minPayout = (baseReward * 0.50).roundToInt()
        val maxPayout = (baseReward * 1.50).roundToInt()
        val finalPayout = max(minPayout, min(maxPayout, rawPayout))

        // Calculate reputation gain
        // Base: 10 reputation
        // +5 if stealth maintained
        // +5 if all bonus objectives complete
        val reputationGain = 10 +
            (if (!mission.stealthViolated) 5 else 0) +
            (if (areAllBonusObjectivesComplete(mission)) 5 else 0)

        return PayoutResult(
            baseReward = baseReward,
            bonuses = bonuses,
            penalties = penalties,
            finalPayout = finalPayout,
            reputationGain = reputationGain
        )
    }

    /**
     * Checks if all bonus objectives are complete.
     */
    private fun areAllBonusObjectivesComplete(mission: ActiveMission): Boolean {
        val bonusObjectiveIds = mission.definition.bonusObjectives.map { it.id }.toSet()
        return bonusObjectiveIds.isNotEmpty() && bonusObjectiveIds == mission.bonusObjectivesCompleted
    }

    /**
     * Checks if mission was completed quickly.
     * Quick completion: within 50% of estimated time.
     */
    private fun isCompletedQuickly(mission: ActiveMission): Boolean {
        val estimatedTimeSeconds = mission.definition.estimatedTimeMinutes * 60L
        val quickThreshold = (estimatedTimeSeconds * 0.5).toLong()

        val currentTime = System.currentTimeMillis()
        val elapsedSeconds = (currentTime - mission.startedAt) / 1000L

        return elapsedSeconds <= quickThreshold
    }

    /**
     * Checks if player's firewall is damaged.
     */
    private fun isFirewallDamaged(player: Player): Boolean {
        return DamageState.fromFirewall(player.currentMachine.firewallCurrent, player.currentMachine.firewallMax) == DamageState.DAMAGED
    }
}
