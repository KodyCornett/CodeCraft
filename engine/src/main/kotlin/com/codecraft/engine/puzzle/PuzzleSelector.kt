package com.codecraft.engine.puzzle

import com.codecraft.engine.domain.PuzzleType
import kotlin.random.Random

/**
 * Weighted puzzle selection and adaptive difficulty
 */
object PuzzleSelector {

    // Weights: CombatType → Map<PuzzleType, weight>
    private val sentinelWeights: Map<PuzzleType, Int> = mapOf(
        PuzzleType.PORT_SCAN_INTERCEPTION to 3,
        PuzzleType.PROCESS_KILL          to 3,
        PuzzleType.MEMORY_FORENSICS      to 2,
        PuzzleType.LOG_PATTERN_RECOGNITION to 2,
        PuzzleType.PACKET_INSPECTION     to 1,
        PuzzleType.ENCRYPTION_CRACKING   to 1,
        PuzzleType.TRACE_ROUTE           to 2,
        PuzzleType.REGEX_FILTERING       to 2
    )

    private val rivalWeights: Map<PuzzleType, Int> = mapOf(
        PuzzleType.ENCRYPTION_CRACKING   to 3,
        PuzzleType.REGEX_FILTERING       to 3,
        PuzzleType.LOG_PATTERN_RECOGNITION to 2,
        PuzzleType.TRACE_ROUTE           to 2,
        PuzzleType.PORT_SCAN_INTERCEPTION to 1,
        PuzzleType.PROCESS_KILL          to 1,
        PuzzleType.MEMORY_FORENSICS      to 1,
        PuzzleType.PACKET_INSPECTION     to 2
    )

    private val aiHunterWeights: Map<PuzzleType, Int> = mapOf(
        PuzzleType.MEMORY_FORENSICS      to 1,
        PuzzleType.PORT_SCAN_INTERCEPTION to 1,
        PuzzleType.LOG_PATTERN_RECOGNITION to 1,
        PuzzleType.PROCESS_KILL          to 1,
        PuzzleType.PACKET_INSPECTION     to 1,
        PuzzleType.ENCRYPTION_CRACKING   to 1,
        PuzzleType.TRACE_ROUTE           to 1,
        PuzzleType.REGEX_FILTERING       to 1
    )

    private val nodeAccessTypes: List<PuzzleType> = listOf(
        PuzzleType.PASSWORD_CRACKING,
        PuzzleType.FIREWALL_BYPASS,
        PuzzleType.CRYPTOGRAPHIC_CHALLENGE,
        PuzzleType.IDS_EVASION,
        PuzzleType.MULTI_FACTOR_BYPASS,
        PuzzleType.CERTIFICATE_BYPASS,
        PuzzleType.CAPTCHA_SOLVING,
        PuzzleType.NETWORK_SEGMENTATION
    )

    /**
     * Select a combat puzzle type using weighted random, excluding recent types (last 3)
     */
    fun selectCombatType(combatType: CombatType, recentTypes: List<PuzzleType>): PuzzleType {
        val weights = when (combatType) {
            CombatType.SENTINEL_ATTACK -> sentinelWeights
            CombatType.RIVAL_HACKER    -> rivalWeights
            CombatType.AI_HUNTER       -> aiHunterWeights
        }
        val recentSet = recentTypes.takeLast(3).toSet()
        val eligible = weights.filter { (type, _) -> type !in recentSet }
        val pool = if (eligible.isEmpty()) weights else eligible
        return weightedRandom(pool)
    }

    /**
     * Select a node access puzzle type, excluding recent types
     */
    fun selectNodeAccessType(recentTypes: List<PuzzleType>): PuzzleType {
        val recentSet = recentTypes.takeLast(3).toSet()
        val eligible = nodeAccessTypes.filter { it !in recentSet }
        return if (eligible.isEmpty()) nodeAccessTypes.random() else eligible.random()
    }

    /**
     * Adjust difficulty based on player performance
     */
    fun adaptiveDifficulty(
        base: PuzzleDifficulty,
        exposure: Double,
        threatPressure: Int,
        progress: PlayerPuzzleProgress,
        typeKey: String
    ): PuzzleDifficulty {
        val exposureBased = PuzzleDifficulty.fromExposure(exposure, threatPressure)
        // Use the higher of the two base difficulties
        val effectiveBase = if (exposureBased.intValue > base.intValue) exposureBased else base

        val successRate = progress.successRate(typeKey)
        val adjustment = when {
            successRate > 0.80 -> 1
            successRate < 0.30 -> -1
            else -> 0
        }
        val newInt = (effectiveBase.intValue + adjustment).coerceIn(1, 4)
        return PuzzleDifficulty.fromInt(newInt)
    }

    private fun weightedRandom(weights: Map<PuzzleType, Int>): PuzzleType {
        val total = weights.values.sum()
        var r = Random.nextInt(total)
        for ((type, weight) in weights) {
            r -= weight
            if (r < 0) return type
        }
        return weights.keys.first()
    }
}
