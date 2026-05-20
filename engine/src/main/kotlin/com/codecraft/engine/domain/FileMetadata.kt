package com.codecraft.engine.domain

import kotlinx.serialization.Serializable

/**
 * File metadata with trap and loot support
 */
@Serializable
data class FileMetadata(
    val content: String,
    val size: Int = content.length,
    val isEncrypted: Boolean = false,
    val isTrap: Boolean = false,
    val lootValue: Int = 0,  // Credits awarded when downloaded
    val trapSeverity: TrapSeverity = TrapSeverity.LOW
)

@Serializable
enum class TrapSeverity {
    LOW,      // Triggers alarm, +5% exposure
    MEDIUM,   // Triggers alarm, +10% exposure, damages firewall 5%
    HIGH      // Triggers alarm, +15% exposure, damages firewall 10%, instant detection roll
}

/**
 * Result of accessing a trapped file
 */
data class TrapResult(
    val triggered: Boolean,
    val severity: TrapSeverity,
    val exposureIncrease: Double,
    val firewallDamage: Int,
    val alarmTriggered: Boolean,
    val immediateDetection: Boolean,
    val message: String
)

/**
 * Helper to create common file types
 */
object FileFactory {
    fun textFile(content: String): FileMetadata {
        return FileMetadata(content = content)
    }

    fun encryptedFile(content: String, size: Int? = null): FileMetadata {
        return FileMetadata(
            content = content,
            size = size ?: content.length,
            isEncrypted = true
        )
    }

    fun trapFile(content: String, severity: TrapSeverity = TrapSeverity.LOW): FileMetadata {
        return FileMetadata(
            content = content,
            isTrap = true,
            trapSeverity = severity
        )
    }

    fun lootFile(content: String, credits: Int): FileMetadata {
        return FileMetadata(
            content = content,
            lootValue = credits
        )
    }

    fun missionFile(content: String): FileMetadata {
        return FileMetadata(
            content = content,
            isEncrypted = true
        )
    }

    /**
     * Honeypot file - looks valuable but triggers harsh trap
     */
    fun honeypot(fakeLootValue: Int = 1000): FileMetadata {
        return FileMetadata(
            content = "[ENCRYPTED DATA - High-value credentials detected]",
            size = 4096,
            isEncrypted = true,
            isTrap = true,
            lootValue = fakeLootValue,
            trapSeverity = TrapSeverity.HIGH
        )
    }
}
