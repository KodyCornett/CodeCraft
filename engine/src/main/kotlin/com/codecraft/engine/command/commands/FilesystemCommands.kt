package com.codecraft.engine.command.commands

import com.codecraft.engine.command.*
import com.codecraft.engine.protocol.StateChanges
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
 * cat - Display file contents
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

        return CommandResult.success(content, delayMs = 120)
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
