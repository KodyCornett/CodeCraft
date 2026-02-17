package com.codecraft.engine.puzzle

import com.codecraft.engine.domain.PuzzleType
import kotlinx.serialization.Serializable

/**
 * Difficulty levels for puzzles
 */
enum class PuzzleDifficulty(val intValue: Int) {
    EASY(1), MEDIUM(2), HARD(3), EXPERT(4);

    companion object {
        fun fromInt(v: Int): PuzzleDifficulty = entries.firstOrNull { it.intValue == v } ?: MEDIUM

        fun fromExposure(exposure: Double, threatPressure: Int = 0): PuzzleDifficulty = when {
            exposure > 80.0 || threatPressure > 75 -> EXPERT
            exposure >= 60.0 || threatPressure > 50 -> HARD
            exposure >= 40.0 || threatPressure > 25 -> MEDIUM
            else -> EASY
        }
    }
}

/**
 * Combat puzzle categories
 */
enum class CombatPuzzleType {
    MEMORY_FORENSICS,
    PORT_SCAN_INTERCEPTION,
    LOG_PATTERN_RECOGNITION,
    PROCESS_KILL,
    PACKET_INSPECTION,
    ENCRYPTION_CRACKING,
    TRACE_ROUTE,
    REGEX_FILTERING
}

/**
 * Node access puzzle categories
 */
enum class NodeAccessPuzzleType {
    PASSWORD_CRACKING,
    FIREWALL_BYPASS,
    CRYPTOGRAPHIC_CHALLENGE,
    IDS_EVASION,
    MULTI_FACTOR_BYPASS,
    CERTIFICATE_BYPASS,
    CAPTCHA_SOLVING,
    NETWORK_SEGMENTATION
}

/**
 * Who is attacking (determines puzzle type weighting)
 */
enum class CombatType {
    SENTINEL_ATTACK,
    RIVAL_HACKER,
    AI_HUNTER
}

/**
 * Result of puzzle validation with partial credit support
 */
data class ValidationResult(
    val isCorrect: Boolean,
    val score: Double,              // 0.0–1.0
    val feedback: String,
    val partialCredit: Boolean = false,
    val hint: String? = null
)

/**
 * Extended puzzle validator that supports partial credit
 */
interface PuzzleValidator {
    fun validateWithScore(answer: String): ValidationResult
}

/**
 * Combat puzzle — shown during sentinel/hacker attacks
 */
interface CombatPuzzle : com.codecraft.engine.puzzle.Puzzle, PuzzleValidator {
    val combatType: CombatPuzzleType
    val threatReduction: Int
    val systemIntegrityPenalty: Int
    val allowsPartialCredit: Boolean
}

/**
 * Node access puzzle — shown when connecting to protected nodes
 */
interface NodeAccessPuzzle : com.codecraft.engine.puzzle.Puzzle, PuzzleValidator {
    val accessType: NodeAccessPuzzleType
    val exposureCost: Double
    val firewallCost: Int
    val failurePenalty: Double
    val alternativeSolutions: List<String>
}

/**
 * Persistent player puzzle progress — tracks tutorials and success rates
 */
@Serializable
data class PlayerPuzzleProgress(
    val tutorialsSeen: MutableSet<String> = mutableSetOf(),
    val attemptsPerType: MutableMap<String, Int> = mutableMapOf(),
    val successesPerType: MutableMap<String, Int> = mutableMapOf()
) {
    fun hasSeen(typeKey: String): Boolean = typeKey in tutorialsSeen

    fun markSeen(typeKey: String) {
        tutorialsSeen.add(typeKey)
    }

    fun recordAttempt(typeKey: String, success: Boolean) {
        attemptsPerType[typeKey] = (attemptsPerType[typeKey] ?: 0) + 1
        if (success) successesPerType[typeKey] = (successesPerType[typeKey] ?: 0) + 1
    }

    fun successRate(typeKey: String): Double {
        val attempts = attemptsPerType[typeKey] ?: return 0.5
        if (attempts == 0) return 0.5
        val successes = successesPerType[typeKey] ?: 0
        return successes.toDouble() / attempts
    }
}
