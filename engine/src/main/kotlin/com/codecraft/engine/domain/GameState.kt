package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Game state machine — tracks what phase the player is in
 */
@Serializable
enum class GamePhase {
    HOME_NODE,
    MISSION_PREP,
    MISSION_ACTIVE,
    COMBAT,
    MISSION_SUCCESS,
    MISSION_FAILED,
    DEFRAG_ACTIVE;

    companion object {
        private val validTransitions = mapOf(
            HOME_NODE to setOf(MISSION_PREP, DEFRAG_ACTIVE),
            MISSION_PREP to setOf(MISSION_ACTIVE, HOME_NODE),
            MISSION_ACTIVE to setOf(COMBAT, MISSION_SUCCESS, MISSION_FAILED, DEFRAG_ACTIVE, HOME_NODE),
            COMBAT to setOf(MISSION_FAILED, DEFRAG_ACTIVE, HOME_NODE),
            MISSION_SUCCESS to setOf(HOME_NODE),
            MISSION_FAILED to setOf(HOME_NODE),
            DEFRAG_ACTIVE to setOf(HOME_NODE, MISSION_ACTIVE, COMBAT)
        )

        fun canTransition(from: GamePhase, to: GamePhase): Boolean {
            return validTransitions[from]?.contains(to) == true
        }
    }
}
