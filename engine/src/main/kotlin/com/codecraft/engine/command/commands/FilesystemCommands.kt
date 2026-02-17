package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.domain.ActiveMission
import com.codecraft.engine.domain.ObjectiveType
import com.codecraft.engine.protocol.StateChanges
import com.codecraft.engine.puzzle.Puzzle
import com.codecraft.engine.puzzle.PuzzleGenerator
import com.codecraft.engine.session.GameSession

/**
 * ls - List directory contents
 */
class LsCommand : Command {
    override val name = "ls"
    override val description = "List directory contents"
    override val usage = "ls [path] [-l] [-a]"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val showHidden = args.contains("-a") || args.contains("-la") || args.contains("-al")
        val longFormat = args.contains("-l") || args.contains("-la") || args.contains("-al")
        val pathArg = args.find { !it.startsWith("-") }

        val targetPath = if (pathArg != null) {
            resolvePath(session.currentPath, pathArg)
        } else {
            session.currentPath
        }

        val node = session.getCurrentNode()
        val files = node.files.keys
            .filter { it.startsWith(targetPath) }
            .map { it.removePrefix(targetPath).trimStart('/') }
            .filter { it.isNotEmpty() && !it.contains('/') }
            .filter { showHidden || !it.startsWith(".") }
            .sorted()

        // Also check for "directories" (paths that have deeper content)
        val dirs = node.files.keys
            .filter { it.startsWith(targetPath) && it != targetPath }
            .map { it.removePrefix(targetPath).trimStart('/').split('/').first() }
            .filter { it.isNotEmpty() }
            .filter { showHidden || !it.startsWith(".") }
            .distinct()
            .filter { dir -> node.files.keys.any { it.startsWith("$targetPath/$dir/") } }
            .sorted()

        val allItems = (dirs.map { "$it/" } + files).distinct().sorted()

        if (allItems.isEmpty()) {
            return CommandResult.success("") // Empty directory
        }

        val output = if (longFormat) {
            allItems.joinToString("\n") { item ->
                val isDir = item.endsWith("/")
                val perms = if (isDir) "drwxr-xr-x" else "-rw-r--r--"
                val size = if (isDir) "4096" else node.files["$targetPath/${item.trimEnd('/')}"]?.length?.toString() ?: "0"
                "$perms  1 user user ${size.padStart(6)}  $item"
            }
        } else {
            allItems.joinToString("  ")
        }

        return CommandResult.success(output, delayMs = 80)
    }
}

/**
 * cd - Change directory
 */
class CdCommand : Command {
    override val name = "cd"
    override val description = "Change current directory"
    override val usage = "cd <path>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        val target = args.firstOrNull() ?: "~"

        val newPath = when {
            target == "~" -> "/home/user"
            target == ".." -> {
                val parent = session.currentPath.substringBeforeLast('/', "/")
                if (parent.isEmpty()) "/" else parent
            }
            target.startsWith("/") -> target
            else -> "${session.currentPath.trimEnd('/')}/$target"
        }

        // Normalize path
        val normalizedPath = normalizePath(newPath)

        // Verify path exists (check if any files start with this path)
        val node = session.getCurrentNode()
        val pathExists = node.files.keys.any {
            it.startsWith(normalizedPath) || it == normalizedPath
        } || normalizedPath == "/home/user" || normalizedPath == "/" || normalizedPath == "/home"

        if (!pathExists) {
            return CommandResult.error("cd: $target: No such directory")
        }

        session.currentPath = normalizedPath

        return CommandResult.withStateChange(
            output = "",
            stateChanges = StateChanges(currentPath = normalizedPath),
            delayMs = 50
        )
    }
}

/**
 * cat - Display file contents (with puzzle integration for mission files)
 */
class CatCommand : Command {
    override val name = "cat"
    override val description = "Display file contents"
    override val usage = "cat <file>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("cat: missing operand")
        }

        val filename = args[0]
        val fullPath = if (filename.startsWith("/")) {
            filename
        } else {
            "${session.currentPath.trimEnd('/')}/$filename"
        }

        val node = session.getCurrentNode()
        val content = node.files[fullPath]

        if (content == null) {
            return CommandResult.error("cat: $filename: No such file or directory")
        }

        // Check if this is a honeypot/trap file
        val trapResult = checkForTrap(node.id, fullPath, session)
        if (trapResult != null) {
            return trapResult
        }

        // Check if this is a mission puzzle file
        val activeMission = session.currentMission
        val isMissionFile = activeMission?.definition?.targetFile == fullPath

        if (isMissionFile && activeMission != null) {
            // Show puzzle content instead of raw file
            val puzzle = getOrCreatePuzzle(session, fullPath, activeMission)

            // Complete the download/find objective
            val downloadObj = activeMission.definition.objectives.find {
                it.type == ObjectiveType.DOWNLOAD_FILE && it.target == fullPath
            }
            downloadObj?.let {
                if (it.id !in activeMission.completedObjectives) {
                    activeMission.completeObjective(it.id)
                }
            }

            return CommandResult.success(puzzle.content, delayMs = 200)
        }

        return CommandResult.success(content, delayMs = 120)
    }

    private fun getOrCreatePuzzle(session: GameSession, filePath: String, mission: ActiveMission): Puzzle {
        var puzzle = PuzzleStateManager.getPuzzle(session.sessionId, filePath)
        if (puzzle == null) {
            puzzle = PuzzleGenerator.generate(
                type = mission.definition.puzzleType,
                difficulty = mission.definition.difficulty,
                seed = mission.definition.puzzleSolution,
                hint = "Look for ${mission.definition.puzzleSolution.split(" ").size} elements"
            )
            PuzzleStateManager.setPuzzle(session.sessionId, filePath, puzzle)
        }
        return puzzle
    }

    /**
     * Check if file is a honeypot/trap
     */
    private fun checkForTrap(nodeId: String, filePath: String, session: GameSession): CommandResult? {
        // Define honeypot files per node
        val traps = mapOf(
            "gov-contractor-dev" to listOf("/projects/classified/honeypot_credentials.txt"),
            "sigint-proxy" to listOf("/data/admin_keys.txt"),
            "evidence-server" to listOf("/secure/root_access.key"),
            "meridian-core" to listOf("/core/master_key.txt")
        )

        val nodeTrapFiles = traps[nodeId] ?: return null
        if (filePath !in nodeTrapFiles) return null

        // Trap triggered!
        val node = session.getCurrentNode()
        val exposureIncrease = 15.0
        val firewallDamage = 10

        // Trigger alarm
        if (!node.alarmActive) {
            node.triggerAlarm()
        }

        // Damage firewall
        val currentFirewall = session.player.currentMachine.firewallCurrent
        val newFirewall = (currentFirewall - firewallDamage).coerceAtLeast(0)
        session.player.currentMachine.firewallCurrent = newFirewall

        val output = buildString {
            appendLine("Reading file...")
            appendLine()
            appendLine("⚠⚠⚠ HONEYPOT DETECTED ⚠⚠⚠")
            appendLine()
            appendLine("[FAKE CREDENTIALS - DECOY FILE]")
            appendLine()
            appendLine("This file was a trap! Automated security response triggered:")
            appendLine("  • Intrusion alarm activated (detection risk x2 for 5 minutes)")
            appendLine("  • Firewall integrity compromised (-$firewallDamage%)")
            appendLine("  • Exposure increased significantly (+${exposureIncrease.toInt()}%)")
            appendLine()
            appendLine("System admins have been alerted. Recommend immediate disconnection.")
        }

        return CommandResult(
            output = output,
            success = false,
            exposureChange = exposureIncrease,
            delayMs = 1500
        )
    }
}

/**
 * pwd - Print working directory
 */
class PwdCommand : Command {
    override val name = "pwd"
    override val description = "Print current directory"
    override val usage = "pwd"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        return CommandResult.success(session.currentPath)
    }
}

/**
 * open - Read remote file contents (increases exposure)
 */
class OpenCommand : Command {
    override val name = "open"
    override val description = "Open and read a remote file"
    override val usage = "open <file>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("open: missing file operand")
        }

        val filename = args[0]
        val fullPath = if (filename.startsWith("/")) filename
        else "${session.currentPath.trimEnd('/')}/$filename"

        val node = session.getCurrentNode()
        val content = node.files[fullPath]
            ?: return CommandResult.error("open: $filename: No such file")

        val output = buildString {
            appendLine("Opening $filename...")
            appendLine("─".repeat(50))
            appendLine(content)
            appendLine("─".repeat(50))
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 200,
            exposureChange = 8.0
        )
    }
}

/**
 * download - Exfiltrate a file (increases exposure, records download)
 */
class DownloadCommand : Command {
    override val name = "download"
    override val description = "Download a file to local machine"
    override val usage = "download <file>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("download: missing file operand")
        }

        if (session.connectedNode == null) {
            return CommandResult.error("download: must be connected to a remote system")
        }

        val filename = args[0]
        val fullPath = if (filename.startsWith("/")) filename
        else "${session.currentPath.trimEnd('/')}/$filename"

        val node = session.getCurrentNode()
        if (!node.files.containsKey(fullPath)) {
            return CommandResult.error("download: $filename: No such file")
        }

        val fileBaseName = fullPath.substringAfterLast('/')

        // Record the download
        session.downloads.add(
            com.codecraft.engine.session.DownloadRecord(
                filename = fileBaseName,
                sourceNode = node.id,
                path = fullPath
            )
        )

        val output = buildString {
            appendLine("Downloading $fileBaseName from ${node.name}...")
            appendLine("Transfer complete. File saved to /home/user/downloads/$fileBaseName")

            // Add hint if file matches active mission exfiltrate objective
            val mission = session.currentMission
            if (mission != null) {
                val needsExfil = mission.definition.objectives.any {
                    it.type == ObjectiveType.EXFILTRATE_FILE &&
                    it.target != null &&
                    it.target.equals(fileBaseName, ignoreCase = true) &&
                    it.id !in mission.completedObjectives
                }
                if (needsExfil) {
                    appendLine()
                    appendLine("Tip: Use 'exfil $fileBaseName' to deliver this file to your contact.")
                }
            }
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 1500,
            stateChanges = StateChanges(downloads = session.downloads.map { it.filename }),
            exposureChange = 15.0
        )
    }
}

/**
 * exfil - Exfiltrate file to mission contact
 */
class ExfilCommand : Command {
    override val name = "exfil"
    override val description = "Exfiltrate file to mission contact"
    override val usage = "exfil <filename>"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (args.isEmpty()) {
            return CommandResult.error("exfil: missing file operand")
        }

        // Must be at localhost
        if (session.connectedNode != null) {
            return CommandResult.error("exfil: cannot exfiltrate while connected to remote system. Disconnect first.")
        }

        // Must have active mission
        val mission = session.currentMission
        if (mission == null) {
            return CommandResult.error("exfil: no active mission")
        }

        val filename = args[0]

        // Check if file exists in downloads
        val download = session.downloads.find { it.filename.equals(filename, ignoreCase = true) }
        if (download == null) {
            return CommandResult.error("exfil: $filename not found in downloads. Use 'download' first.")
        }

        // Find matching EXFILTRATE_FILE objective
        val objective = mission.definition.objectives.find {
            it.type == ObjectiveType.EXFILTRATE_FILE &&
            it.target != null &&
            it.target.equals(filename, ignoreCase = true)
        }

        if (objective == null) {
            return CommandResult.error("exfil: mission does not require this file")
        }

        // Check if already completed
        if (objective.id in mission.completedObjectives) {
            return CommandResult.error("exfil: $filename already delivered")
        }

        // Mark objective complete
        mission.completeObjective(objective.id)

        // Remove file from downloads (it's been sent)
        session.downloads.remove(download)

        // Get contact name
        val contactName = when (mission.definition.contactId) {
            "ghost" -> "Ghost"
            "cipher" -> "Cipher"
            "zero" -> "Zero"
            "lena" -> "[REDACTED]"
            "proxy" -> "Proxy"
            "hale" -> "Director Hale"
            else -> "Contact"
        }

        val output = buildString {
            appendLine("Establishing encrypted tunnel to $contactName...")
            appendLine("Transmitting $filename...")
            appendLine()
            appendLine("✓ Encrypted transmission complete")
            appendLine("✓ File exfiltrated: $filename")
            appendLine()
            appendLine("$contactName has confirmed receipt.")
        }

        return CommandResult(
            output = output,
            success = true,
            delayMs = 1200,
            stateChanges = StateChanges(
                downloads = session.downloads.map { it.filename },
                objectivesCompleted = listOf(objective.id)
            ),
            exposureChange = 2.0
        )
    }
}

/**
 * downloads - List downloaded files
 */
class DownloadsCommand : Command {
    override val name = "downloads"
    override val description = "List downloaded files"
    override val usage = "downloads"
    override val category = CommandCategory.FILESYSTEM

    override fun execute(session: GameSession, args: List<String>): CommandResult {
        if (session.downloads.isEmpty()) {
            return CommandResult.success("No downloaded files.")
        }

        val output = buildString {
            appendLine("DOWNLOADED FILES:")
            appendLine()
            session.downloads.forEachIndexed { index, download ->
                appendLine("${index + 1}. ${download.filename}")
                appendLine("   Source: ${download.sourceNode}")
                appendLine()
            }
            appendLine("Use 'exfil <filename>' to deliver files to your contact.")
        }

        return CommandResult.success(output)
    }
}

// Helper functions
private fun resolvePath(currentPath: String, path: String): String {
    return if (path.startsWith("/")) {
        path
    } else {
        "${currentPath.trimEnd('/')}/$path"
    }
}

private fun normalizePath(path: String): String {
    val parts = path.split("/").filter { it.isNotEmpty() && it != "." }
    val result = mutableListOf<String>()

    for (part in parts) {
        if (part == "..") {
            if (result.isNotEmpty()) result.removeLast()
        } else {
            result.add(part)
        }
    }

    return "/" + result.joinToString("/")
}
