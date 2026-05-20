package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.ExposureLevel
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.ThreatType

/**
 * mail - Open Messages window
 */
class MailCommand : Command {
    override val name = "mail"
    override val description = "Open messages"
    override val usage = "mail"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        return CommandResult(
            output = "Opening Messages...",
            success = true,
            delayMs = 100,
            stateChanges = com.codecraft.engine.protocol.StateChanges(openWindow = "messages")
        )
    }
}

/**
 * sentinel/status - Security monitoring
 */
class SentinelCommand : Command {
    override val name = "sentinel"
    override val description = "View security status"
    override val usage = "sentinel"
    override val category = CommandCategory.UTILITY

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val player = session.player
        val exposureLevel = ExposureLevel.fromValue(player.exposure)
        val threats = session.incomingThreats

        val exposureBar = renderBar(player.exposure.toInt(), 100, 20)
        val statusText = when {
            threats.any { it.type == ThreatType.INTRUSION } -> "UNDER ATTACK"
            threats.isNotEmpty() -> "ELEVATED THREAT"
            player.exposure > 60 -> "HIGH ALERT"
            else -> "MONITORING"
        }

        val output = buildString {
            appendLine("╔══════════════════════════════════════════════════════╗")
            appendLine("║  SENTINEL v2.1 - Security Monitor                    ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  STATUS: ${statusText.padEnd(44)} ║")
            appendLine("║  EXPOSURE:  $exposureBar ${player.exposure.toInt().toString().padStart(3)}%  ║")
            appendLine("║  LEVEL:     ${exposureLevel.label.padEnd(41)} ║")
            appendLine("╠══════════════════════════════════════════════════════╣")
            appendLine("║  INCOMING CONNECTIONS                                ║")
            appendLine("╠══════════════════════════════════════════════════════╣")

            if (threats.isEmpty()) {
                appendLine("║  No incoming connections detected                    ║")
            } else {
                threats.take(5).forEach { threat ->
                    val icon = when (threat.type) {
                        ThreatType.INTRUSION -> "🔴"
                        ThreatType.COUNTER_HACK -> "🟠"
                        ThreatType.SCAN -> "🟡"
                        ThreatType.PROBE -> "⚪"
                    }
                    val type = threat.type.name.padEnd(12)
                    val ip = threat.sourceIp.padEnd(15)
                    appendLine("║  $icon $ip $type ${threat.timeRemaining}s     ║")
                }
            }

            appendLine("╚══════════════════════════════════════════════════════╝")

            if (threats.any { it.type == ThreatType.INTRUSION }) {
                appendLine()
                appendLine("⚠️  ACTIVE THREATS! Open Sentinel app for response options.")
            }
        }

        return CommandResult.success(output, delayMs = 100)
    }

    private fun renderBar(value: Int, max: Int, width: Int): String {
        val filled = (value.toDouble() / max * width).toInt().coerceIn(0, width)
        val empty = width - filled
        return "[" + "█".repeat(filled) + "░".repeat(empty) + "]"
    }
}
