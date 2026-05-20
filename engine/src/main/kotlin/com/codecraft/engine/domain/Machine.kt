package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * Machine types available to the player
 */
@Serializable
enum class MachineType {
    GREYBOX
}

/**
 * Damage states for a machine's firewall
 */
@Serializable
enum class DamageState(val label: String) {
    PRISTINE("Pristine"),
    GOOD("Good"),
    DAMAGED("Damaged"),
    HEAVILY_DAMAGED("Heavily Damaged"),
    CRITICAL("Critical"),
    DESTROYED("Destroyed");

    companion object {
        fun fromFirewall(current: Int, max: Int): DamageState {
            if (max <= 0) return DESTROYED
            val ratio = current.toDouble() / max
            return when {
                ratio >= 1.0 -> PRISTINE
                ratio >= 0.75 -> GOOD
                ratio >= 0.50 -> DAMAGED
                ratio >= 0.25 -> HEAVILY_DAMAGED
                ratio > 0.0 -> CRITICAL
                else -> DESTROYED
            }
        }
    }
}

/**
 * State of a player's machine
 */
@Serializable
data class MachineState(
    val machineType: MachineType = MachineType.GREYBOX,
    var firewallCurrent: Int = 100,
    val firewallMax: Int = 100,
    var residualExposure: Int = 0,
    val storageLevel: Int = 1,
    val storageSlots: Int = 6
) {
    val damageState: DamageState
        get() = DamageState.fromFirewall(firewallCurrent, firewallMax)

    fun damageFirewall(amount: Int) {
        firewallCurrent = (firewallCurrent - amount).coerceAtLeast(0)
    }

    fun repairFirewall(amount: Int) {
        firewallCurrent = (firewallCurrent + amount).coerceAtMost(firewallMax)
    }
}

/**
 * Machine definitions with base stats
 */
object MachineDefinition {
    val GREYBOX = MachineState(
        machineType = MachineType.GREYBOX,
        firewallCurrent = 100,
        firewallMax = 100,
        residualExposure = 0,
        storageLevel = 1,
        storageSlots = 6
    )
}
