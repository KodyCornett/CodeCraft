package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.ExposureLevel
import com.codecraft.engine.session.GameSession
import com.codecraft.engine.session.ThreatType

/**
 * mail - Access messages
 */
class MailCommand : Command {
    override val name = "mail"
    override val description = "Access your messages"
    override val usage = "mail [list|read <id>|help]"
    override val category = CommandCategory.UTILITY

    // Mock messages for now - in production would come from database
    private val messages = listOf(
        Message(1, "Ghost", "Welcome to the Network", "Hey, welcome aboard...", false),
        Message(2, "Ghost", "Job: Data Extraction", "Got a job for you. NovaCorp has some files...", false),
        Message(3, "System", "Tutorial: Getting Started", "Welcome to your new hacking terminal...", true)
    )

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val subcommand = args.firstOrNull() ?: "list"

        return when (subcommand) {
            "list", "" -> listMessages()
            "read" -> readMessage(args.getOrNull(1))
            "help" -> showHelp()
            else -> {
                // Check if it's a number (shortcut for read)
                val id = subcommand.toIntOrNull()
                if (id != null) readMessage(subcommand) else showHelp()
            }
        }
    }

    private fun listMessages(): CommandResult {
        val output = buildString {
            appendLine("INBOX (${messages.count { !it.read }} unread)")
            appendLine("─".repeat(60))
            messages.forEach { msg ->
                val status = if (msg.read) " " else "*"
                appendLine("[$status] #${msg.id} ${msg.from.padEnd(12)} - ${msg.subject}")
            }
            appendLine("─".repeat(60))
            appendLine("Type 'mail read <id>' to read a message")
        }
        return CommandResult.success(output, delayMs = 100)
    }

    private fun readMessage(idStr: String?): CommandResult {
        if (idStr == null) {
            return CommandResult.error("mail read: missing message ID")
        }
        val id = idStr.toIntOrNull()
            ?: return CommandResult.error("mail read: invalid message ID")

        val message = messages.find { it.id == id }
            ?: return CommandResult.error("mail read: message #$id not found")

        val output = buildString {
            appendLine("━".repeat(55))
            appendLine("From:    ${message.from}")
            appendLine("Subject: ${message.subject}")
            appendLine("━".repeat(55))
            appendLine()
            appendLine(message.body)
            appendLine()
            appendLine("━".repeat(55))
        }
        return CommandResult.success(output, delayMs = 150)
    }

    private fun showHelp(): CommandResult {
        val output = """
            |MAIL COMMANDS
            |─────────────────────────────────────────
            |  mail              List all messages
            |  mail list         List all messages
            |  mail read <id>    Read message by ID
            |  mail <id>         Shortcut to read message
            |  mail help         Show this help
        """.trimMargin()
        return CommandResult.success(output, delayMs = 50)
    }

    private data class Message(
        val id: Int,
        val from: String,
        val subject: String,
        val body: String,
        val read: Boolean
    )
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
