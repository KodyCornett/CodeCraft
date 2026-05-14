package com.codecraft.engine.combat

enum class CombatPhase {
    /** Resident's 10-second escape window. */
    PRE_COMBAT,
    /** Both players are placing ships on their grids. */
    PLACING_SHIPS,
    /** Grids locked, combat is underway. */
    ACTIVE,
    /** A winner has been determined. Final state. */
    RESOLVED,
}
