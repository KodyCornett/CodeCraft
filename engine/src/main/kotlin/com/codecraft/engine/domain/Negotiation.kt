package com.codecraft.engine.domain

import kotlin.random.Random

/**
 * Job offer with negotiation state
 */
data class JobOffer(
    val mission: MissionDefinition,
    val contact: Contact,
    val baseOfferPercentage: Int,    // Initial offer (e.g., 50%)
    val currentOfferPercentage: Int, // Current negotiated offer
    val maxOfferPercentage: Int,     // Max the contact will go
    val negotiationAttempts: Int = 0,
    val maxAttempts: Int = 3,
    val rejected: Boolean = false    // Contact walked away
) {
    val currentReward: Int
        get() = (mission.baseReward * currentOfferPercentage) / 100

    val maxReward: Int
        get() = (mission.baseReward * maxOfferPercentage) / 100

    fun canNegotiate(): Boolean = !rejected && negotiationAttempts < maxAttempts
}

/**
 * Result of a negotiation attempt
 */
sealed class NegotiationResult {
    data class Accepted(val newPercentage: Int, val newReward: Int) : NegotiationResult()
    data class CounterOffer(val counterPercentage: Int, val counterReward: Int, val message: String) : NegotiationResult()
    data class Rejected(val message: String) : NegotiationResult()
    data class WalkedAway(val message: String) : NegotiationResult()
}

/**
 * Negotiation Engine - Handles reputation-based job offers and negotiations
 */
object NegotiationEngine {

    /**
     * Calculate the base offer percentage based on player's reputation with faction
     * Low rep: 40-50%, High rep: 70-80%
     */
    fun calculateBaseOffer(playerRep: Int, missionDifficulty: Int): Int {
        // Reputation ranges from -100 to 100
        // Convert to a 0-100 scale for calculation
        val normalizedRep = (playerRep + 100) / 2  // -100 -> 0, 0 -> 50, 100 -> 100

        // Base offer starts at 40% for unknown, up to 80% for max rep
        val basePercentage = 40 + (normalizedRep * 40) / 100

        // Harder missions start with slightly lower offers (risk premium for contact)
        val difficultyPenalty = (missionDifficulty - 1) * 3

        return (basePercentage - difficultyPenalty).coerceIn(35, 80)
    }

    /**
     * Calculate maximum negotiable percentage based on reputation
     */
    fun calculateMaxOffer(playerRep: Int, missionDifficulty: Int): Int {
        val normalizedRep = (playerRep + 100) / 2

        // Max starts at 60% for unknown, up to 95% for max rep
        val maxPercentage = 60 + (normalizedRep * 35) / 100

        // Harder missions have slightly lower ceiling
        val difficultyPenalty = (missionDifficulty - 1) * 2

        return (maxPercentage - difficultyPenalty).coerceIn(55, 95)
    }

    /**
     * Create a job offer for a player
     */
    fun createOffer(
        mission: MissionDefinition,
        playerReputation: Map<String, Int>
    ): JobOffer? {
        val contact = ContactCatalog.getById(mission.contactId) ?: return null
        val playerRep = playerReputation[contact.faction] ?: 0

        // Check reputation requirement
        if (playerRep < mission.requiredReputation) {
            return null
        }

        val baseOffer = calculateBaseOffer(playerRep, mission.difficulty)
        val maxOffer = calculateMaxOffer(playerRep, mission.difficulty)

        return JobOffer(
            mission = mission,
            contact = contact,
            baseOfferPercentage = baseOffer,
            currentOfferPercentage = baseOffer,
            maxOfferPercentage = maxOffer
        )
    }

    /**
     * Attempt to negotiate a better offer
     * @param offer Current job offer
     * @param requestedPercentage Percentage the player is asking for
     * @param playerRep Player's reputation with the contact's faction
     * @return NegotiationResult indicating success, counter-offer, or rejection
     */
    fun negotiate(
        offer: JobOffer,
        requestedPercentage: Int,
        playerRep: Int
    ): Pair<JobOffer, NegotiationResult> {
        // Can't negotiate if already rejected or out of attempts
        if (!offer.canNegotiate()) {
            return offer to NegotiationResult.Rejected("No further negotiation possible.")
        }

        // If asking for less than or equal to current offer, auto-accept
        if (requestedPercentage <= offer.currentOfferPercentage) {
            return offer.copy(
                currentOfferPercentage = offer.currentOfferPercentage,
                negotiationAttempts = offer.negotiationAttempts + 1
            ) to NegotiationResult.Accepted(
                offer.currentOfferPercentage,
                offer.currentReward
            )
        }

        // If asking for more than max, it depends on how much over
        val overMax = requestedPercentage - offer.maxOfferPercentage

        if (overMax > 15) {
            // Way too greedy - contact walks away
            return offer.copy(rejected = true) to NegotiationResult.WalkedAway(
                "\"You're out of your mind. We're done here.\""
            )
        }

        if (overMax > 0) {
            // Over max but not absurd - counter with max
            val newAttempts = offer.negotiationAttempts + 1
            val newOffer = offer.copy(
                currentOfferPercentage = offer.maxOfferPercentage,
                negotiationAttempts = newAttempts
            )

            return if (newAttempts >= offer.maxAttempts) {
                // Last attempt - final offer
                newOffer to NegotiationResult.CounterOffer(
                    offer.maxOfferPercentage,
                    newOffer.currentReward,
                    "\"${offer.maxOfferPercentage}%. That's my final offer. Take it or leave it.\""
                )
            } else {
                newOffer to NegotiationResult.CounterOffer(
                    offer.maxOfferPercentage,
                    newOffer.currentReward,
                    "\"Can't do ${requestedPercentage}%. Best I can offer is ${offer.maxOfferPercentage}%.\""
                )
            }
        }

        // Within range - calculate success chance
        val difficulty = requestedPercentage - offer.currentOfferPercentage
        val successChance = calculateSuccessChance(playerRep, difficulty, offer.negotiationAttempts)

        val roll = Random.nextInt(100)
        val success = roll < successChance

        val newAttempts = offer.negotiationAttempts + 1

        return if (success) {
            // Negotiation successful
            val newOffer = offer.copy(
                currentOfferPercentage = requestedPercentage,
                negotiationAttempts = newAttempts
            )
            newOffer to NegotiationResult.Accepted(
                requestedPercentage,
                newOffer.currentReward
            )
        } else {
            // Counter-offer: split the difference
            val counterPercentage = offer.currentOfferPercentage +
                    (requestedPercentage - offer.currentOfferPercentage) / 2
            val newOffer = offer.copy(
                currentOfferPercentage = counterPercentage.coerceAtMost(offer.maxOfferPercentage),
                negotiationAttempts = newAttempts
            )

            val message = if (newAttempts >= offer.maxAttempts) {
                "\"${newOffer.currentOfferPercentage}%. Final offer.\""
            } else {
                "\"I can do ${newOffer.currentOfferPercentage}%. That's fair.\""
            }

            newOffer to NegotiationResult.CounterOffer(
                newOffer.currentOfferPercentage,
                newOffer.currentReward,
                message
            )
        }
    }

    /**
     * Calculate success chance for negotiation
     */
    private fun calculateSuccessChance(playerRep: Int, difficulty: Int, attempts: Int): Int {
        // Base chance from reputation (0-60%)
        val repBonus = ((playerRep + 100) * 30) / 200  // -100 -> 0%, 100 -> 30%

        // Base success rate
        val baseChance = 50

        // Penalty for each percentage point requested over current
        val difficultyPenalty = difficulty * 3

        // Penalty for repeated attempts
        val attemptPenalty = attempts * 10

        return (baseChance + repBonus - difficultyPenalty - attemptPenalty).coerceIn(5, 90)
    }

    /**
     * Get negotiation tips based on reputation
     */
    fun getNegotiationTips(playerRep: Int, offer: JobOffer): String {
        return buildString {
            appendLine("Negotiation Analysis:")
            appendLine("- Current offer: ${offer.currentOfferPercentage}% (§${offer.currentReward})")
            appendLine("- Your reputation: ${getRepLevel(playerRep)}")

            when {
                playerRep >= 50 -> {
                    appendLine("- High reputation gives you strong leverage")
                    appendLine("- Recommended ask: ${(offer.maxOfferPercentage - 5).coerceAtLeast(offer.currentOfferPercentage + 5)}%")
                }
                playerRep >= 0 -> {
                    appendLine("- Neutral standing, moderate leverage")
                    appendLine("- Recommended ask: ${(offer.currentOfferPercentage + 10).coerceAtMost(offer.maxOfferPercentage)}%")
                }
                else -> {
                    appendLine("- Low reputation limits your options")
                    appendLine("- Consider accepting to build trust")
                }
            }

            if (offer.negotiationAttempts > 0) {
                appendLine("- Attempts remaining: ${offer.maxAttempts - offer.negotiationAttempts}")
            }
        }
    }

    private fun getRepLevel(rep: Int): String {
        return when {
            rep >= 75 -> "Legendary"
            rep >= 50 -> "Trusted"
            rep >= 25 -> "Respected"
            rep >= 0 -> "Neutral"
            rep >= -25 -> "Suspicious"
            rep >= -50 -> "Untrusted"
            else -> "Hostile"
        }
    }
}
