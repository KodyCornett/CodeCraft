package com.codecraft.engine.puzzle

import com.codecraft.engine.domain.PuzzleType
import kotlin.random.Random

/**
 * Base interface for all puzzles
 */
interface Puzzle {
    val type: PuzzleType
    val difficulty: Int
    val content: String      // The puzzle displayed to user
    val solution: String     // Expected answer
    val hint: String?

    fun validate(answer: String): Boolean
}

/**
 * Word Search Puzzle - Find hidden words in a grid
 */
class WordSearchPuzzle(
    override val difficulty: Int,
    private val wordsToFind: List<String>,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.WORD_SEARCH
    override val solution = wordsToFind.joinToString(" ")

    private val gridSize = 8 + (difficulty * 2)
    private val grid: Array<CharArray>
    private val wordPositions: Map<String, List<Pair<Int, Int>>>

    init {
        val (generatedGrid, positions) = generateGrid()
        grid = generatedGrid
        wordPositions = positions
    }

    override val content: String
        get() = buildString {
            appendLine("=== ENCRYPTED DATA BLOCK ===")
            appendLine()
            grid.forEach { row ->
                appendLine(row.joinToString(" "))
            }
            appendLine()
            appendLine("─".repeat(gridSize * 2))
            appendLine("Find ${wordsToFind.size} hidden access codes in the grid above.")
            appendLine("Words can be horizontal, vertical, or diagonal.")
            hint?.let {
                appendLine()
                appendLine("Hint: $it")
            }
        }

    override fun validate(answer: String): Boolean {
        val providedWords = answer.uppercase().split(Regex("\\s+")).toSet()
        val requiredWords = wordsToFind.map { it.uppercase() }.toSet()
        return providedWords == requiredWords
    }

    private fun generateGrid(): Pair<Array<CharArray>, Map<String, List<Pair<Int, Int>>>> {
        val grid = Array(gridSize) { CharArray(gridSize) { '.' } }
        val positions = mutableMapOf<String, List<Pair<Int, Int>>>()

        val directions = listOf(0 to 1, 1 to 0, 1 to 1, 1 to -1)

        for (word in wordsToFind) {
            var placed = false
            var attempts = 0
            while (!placed && attempts < 100) {
                val dir = directions.random()
                val startRow = Random.nextInt(gridSize)
                val startCol = Random.nextInt(gridSize)
                if (canPlaceWord(grid, word, startRow, startCol, dir)) {
                    val wordPositionList = mutableListOf<Pair<Int, Int>>()
                    for (i in word.indices) {
                        val row = startRow + i * dir.first
                        val col = startCol + i * dir.second
                        grid[row][col] = word[i]
                        wordPositionList.add(row to col)
                    }
                    positions[word] = wordPositionList
                    placed = true
                }
                attempts++
            }
        }

        val fillChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
        for (row in 0 until gridSize) {
            for (col in 0 until gridSize) {
                if (grid[row][col] == '.') {
                    grid[row][col] = fillChars.random()
                }
            }
        }

        return grid to positions
    }

    private fun canPlaceWord(
        grid: Array<CharArray>, word: String, startRow: Int, startCol: Int, dir: Pair<Int, Int>
    ): Boolean {
        for (i in word.indices) {
            val row = startRow + i * dir.first
            val col = startCol + i * dir.second
            if (row < 0 || row >= gridSize || col < 0 || col >= gridSize) return false
            val current = grid[row][col]
            if (current != '.' && current != word[i]) return false
        }
        return true
    }
}

/**
 * Caesar Cipher Puzzle - Decode shifted text
 */
class CaesarCipherPuzzle(
    override val difficulty: Int,
    private val plaintext: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.CAESAR_CIPHER
    override val solution = plaintext.uppercase()

    private val shift = when (difficulty) {
        1 -> 3
        2 -> 7
        3 -> 13
        4 -> Random.nextInt(5, 20)
        else -> Random.nextInt(1, 25)
    }

    private val encryptedText = encrypt(plaintext, shift)

    override val content: String
        get() = buildString {
            appendLine("=== ENCRYPTED COMMUNICATION ===")
            appendLine()
            appendLine(encryptedText)
            appendLine()
            appendLine("─".repeat(50))
            appendLine("This message appears to be encrypted with a substitution cipher.")
            if (difficulty <= 2) {
                appendLine("Hint: Classic shift cipher detected (shift value: $shift)")
            }
            hint?.let { appendLine("\nAdditional hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution

    private fun encrypt(text: String, shift: Int): String {
        return text.map { char ->
            when {
                char.isUpperCase() -> (('A'.code + ((char.code - 'A'.code + shift) % 26 + 26) % 26).toChar())
                char.isLowerCase() -> (('A'.code + ((char.code - 'a'.code + shift) % 26 + 26) % 26).toChar())
                else -> char
            }
        }.joinToString("")
    }
}

/**
 * Binary Decode Puzzle - Convert binary to text
 */
class BinaryDecodePuzzle(
    override val difficulty: Int,
    private val plaintext: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.BINARY_DECODE
    override val solution = plaintext.uppercase()

    private val binaryText = plaintext.map { it.uppercaseChar().code.toString(2).padStart(8, '0') }.joinToString("")

    override val content: String
        get() = buildString {
            appendLine("=== BINARY DATA STREAM ===")
            appendLine()
            val formatted = binaryText.chunked(8).chunked(4).joinToString("\n") { it.joinToString(" ") }
            appendLine(formatted)
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Raw binary data detected. Convert to ASCII text.")
            if (difficulty <= 2) appendLine("Hint: Each 8-bit group represents one character.")
            hint?.let { appendLine("\nAdditional hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution
}

/**
 * Pattern Match Puzzle - Find the sequence
 */
class PatternMatchPuzzle(
    override val difficulty: Int,
    private val answer: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.PATTERN_MATCH
    override val solution = answer.uppercase()

    private val sequence: List<String>
    private val patternType: String

    init {
        val (seq, type) = generatePattern()
        sequence = seq
        patternType = type
    }

    override val content: String
        get() = buildString {
            appendLine("=== PATTERN RECOGNITION TEST ===")
            appendLine()
            appendLine("Sequence: ${sequence.joinToString(" -> ")}")
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Identify the pattern and provide the next element(s).")
            if (difficulty <= 2) appendLine("Pattern type: $patternType")
            hint?.let { appendLine("\nHint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution

    private fun generatePattern(): Pair<List<String>, String> {
        return when (difficulty) {
            1 -> listOf("2", "4", "6", "8", "?") to "Arithmetic"
            2 -> listOf("1", "1", "2", "3", "5", "?") to "Mathematical"
            3 -> listOf("A", "C", "E", "G", "?") to "Alphabetic"
            else -> listOf("1", "4", "9", "16", "?") to "Square numbers"
        }
    }
}

/**
 * Anagram Puzzle - Unscramble words
 */
class AnagramPuzzle(
    override val difficulty: Int,
    private val words: List<String>,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.ANAGRAM
    override val solution = words.joinToString(" ") { it.uppercase() }

    private val scrambledWords = words.map { scramble(it) }

    override val content: String
        get() = buildString {
            appendLine("=== SCRAMBLED DATA ===")
            appendLine()
            scrambledWords.forEachIndexed { index, word -> appendLine("${index + 1}. $word") }
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Unscramble these ${words.size} words to reveal the access codes.")
            hint?.let { appendLine("\nHint: $it") }
        }

    override fun validate(answer: String): Boolean {
        val providedWords = answer.uppercase().split(Regex("\\s+"))
        return providedWords == words.map { it.uppercase() }
    }

    private fun scramble(word: String): String {
        val chars = word.uppercase().toMutableList()
        for (i in chars.indices) {
            val j = Random.nextInt(chars.size)
            val temp = chars[i]; chars[i] = chars[j]; chars[j] = temp
        }
        return if (chars.joinToString("") == word.uppercase()) chars.reversed().joinToString("") else chars.joinToString("")
    }
}

/**
 * Port Sequence Puzzle - Complete port number sequences
 */
class PortSequencePuzzle(
    override val difficulty: Int,
    private val answer: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.PORT_SEQUENCE
    override val solution = answer.uppercase()

    private val sequences = listOf(
        Triple(listOf(20, 21, 22, 23), 25, "FTP/SSH/Telnet range"),
        Triple(listOf(80, 443, 8080), 8443, "HTTP/HTTPS variants"),
        Triple(listOf(3306, 5432, 27017), 6379, "Database ports"),
        Triple(listOf(21, 22, 23, 25), 53, "Well-known services")
    )

    private val selectedSequence = sequences[difficulty.coerceIn(1, sequences.size) - 1]

    override val content: String
        get() = buildString {
            appendLine("=== PORT SEQUENCE ANALYSIS ===")
            appendLine()
            appendLine("Detected port sequence: ${selectedSequence.first.joinToString(", ")}, ?")
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Complete the port sequence.")
            if (difficulty <= 2) appendLine("Hint: ${selectedSequence.third}")
            hint?.let { appendLine("\nAdditional hint: $it") }
        }

    override fun validate(answer: String): Boolean {
        return answer.trim() == selectedSequence.second.toString() || answer.uppercase().trim() == solution
    }
}

/**
 * Hex Decode Puzzle - Decode hex bytes to ASCII
 */
class HexDecodePuzzle(
    override val difficulty: Int,
    private val plaintext: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.HEX_DECODE
    override val solution = plaintext.uppercase()

    private val hexText = plaintext.uppercase().map { String.format("%02X", it.code) }.joinToString(" ")

    override val content: String
        get() = buildString {
            appendLine("=== HEXADECIMAL DATA ===")
            appendLine()
            appendLine(hexText)
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Hexadecimal encoded data. Convert to ASCII.")
            if (difficulty <= 2) appendLine("Hint: Each pair of hex digits represents one character.")
            hint?.let { appendLine("\nAdditional hint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution
}

/**
 * Reverse Puzzle - Reverse a string
 */
class ReversePuzzle(
    override val difficulty: Int,
    private val plaintext: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.REVERSE
    override val solution = plaintext.uppercase()

    private val reversedText = plaintext.uppercase().reversed()

    override val content: String
        get() = buildString {
            appendLine("=== REVERSED DATA ===")
            appendLine()
            appendLine(reversedText)
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Data appears to be reversed. Decode to find the original.")
            hint?.let { appendLine("\nHint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution
}

/**
 * Word Jumble Puzzle - Unscramble a single word
 */
class WordJumblePuzzle(
    override val difficulty: Int,
    private val word: String,
    override val hint: String? = null
) : Puzzle {
    override val type = PuzzleType.WORD_JUMBLE
    override val solution = word.uppercase()

    private val jumbled: String

    init {
        val chars = word.uppercase().toMutableList()
        var result: String
        do {
            chars.shuffle()
            result = chars.joinToString("")
        } while (result == word.uppercase() && word.length > 1)
        jumbled = result
    }

    override val content: String
        get() = buildString {
            appendLine("=== SCRAMBLED ACCESS CODE ===")
            appendLine()
            appendLine("  $jumbled")
            appendLine()
            appendLine("─".repeat(50))
            appendLine("Unscramble this word to gain access.")
            hint?.let { appendLine("\nHint: $it") }
        }

    override fun validate(answer: String): Boolean = answer.uppercase().trim() == solution
}

/**
 * Puzzle Generator - Factory for creating puzzles
 */
object PuzzleGenerator {

    fun generate(type: PuzzleType, difficulty: Int, seed: String, hint: String? = null): Puzzle {
        val d = difficulty.coerceIn(1, 5)
        return when (type) {
            PuzzleType.WORD_SEARCH -> WordSearchPuzzle(d, seed.split(Regex("\\s+")).filter { it.isNotBlank() }, hint)
            PuzzleType.CAESAR_CIPHER -> CaesarCipherPuzzle(d, seed, hint)
            PuzzleType.BINARY_DECODE -> BinaryDecodePuzzle(d, seed, hint)
            PuzzleType.PATTERN_MATCH -> PatternMatchPuzzle(d, seed, hint)
            PuzzleType.ANAGRAM -> AnagramPuzzle(d, seed.split(Regex("\\s+")).filter { it.isNotBlank() }, hint)
            PuzzleType.PORT_SEQUENCE -> PortSequencePuzzle(d, seed, hint)
            PuzzleType.HEX_DECODE -> HexDecodePuzzle(d, seed, hint)
            PuzzleType.REVERSE -> ReversePuzzle(d, seed, hint)
            PuzzleType.WORD_JUMBLE -> WordJumblePuzzle(d, seed, hint)
            // Combat puzzles
            PuzzleType.MEMORY_FORENSICS      -> MemoryForensicsPuzzle(d, hint)
            PuzzleType.PORT_SCAN_INTERCEPTION -> PortScanInterceptionPuzzle(d, hint)
            PuzzleType.LOG_PATTERN_RECOGNITION -> LogPatternRecognitionPuzzle(d, hint)
            PuzzleType.PROCESS_KILL          -> ProcessKillPuzzle(d, hint)
            PuzzleType.PACKET_INSPECTION     -> PacketInspectionPuzzle(d, hint)
            PuzzleType.ENCRYPTION_CRACKING   -> EncryptionCrackingPuzzle(d, hint)
            PuzzleType.TRACE_ROUTE           -> TraceRoutePuzzle(d, hint)
            PuzzleType.REGEX_FILTERING       -> RegexFilteringPuzzle(d, hint)
            // Node access puzzles
            PuzzleType.PASSWORD_CRACKING     -> PasswordCrackingPuzzle(d, seed, hint)
            PuzzleType.FIREWALL_BYPASS       -> FirewallBypassPuzzle(d, seed, hint)
            PuzzleType.CRYPTOGRAPHIC_CHALLENGE -> CryptographicChallengePuzzle(d, seed, hint)
            PuzzleType.IDS_EVASION           -> IdsEvasionPuzzle(d, seed, hint)
            PuzzleType.MULTI_FACTOR_BYPASS   -> MultiFactorBypassPuzzle(d, seed, hint)
            PuzzleType.CERTIFICATE_BYPASS    -> CertificateBypassPuzzle(d, seed, hint)
            PuzzleType.CAPTCHA_SOLVING       -> CaptchaSolvingPuzzle(d, seed, hint)
            PuzzleType.NETWORK_SEGMENTATION  -> NetworkSegmentationPuzzle(d, seed, hint)
        }
    }

    /**
     * Generate a puzzle using PuzzleDifficulty enum (delegates to Int overload)
     */
    fun generate(type: PuzzleType, difficulty: PuzzleDifficulty, seed: String, hint: String? = null): Puzzle {
        return generate(type, difficulty.intValue, seed, hint)
    }

    /**
     * Generate a combat puzzle for an attacker type, excluding recent types
     */
    fun generateCombatPuzzle(
        combatType: com.codecraft.engine.puzzle.CombatType,
        difficulty: PuzzleDifficulty,
        recentTypes: List<PuzzleType>
    ): CombatPuzzle {
        val selectedType = PuzzleSelector.selectCombatType(combatType, recentTypes)
        return generate(selectedType, difficulty.intValue, "", null) as CombatPuzzle
    }

    /**
     * Generate a random connection puzzle for a node
     */
    fun generateConnectionPuzzle(nodeId: String, difficulty: Int): Puzzle {
        val words = listOf("ACCESS", "CIPHER", "BREACH", "PROXY", "GHOST", "NODE", "RELAY", "TRACE", "SHELL", "LINK")
        val word = words.random()

        val types = listOf(PuzzleType.WORD_JUMBLE, PuzzleType.REVERSE, PuzzleType.HEX_DECODE)
        val type = types.random()

        return generate(type, difficulty, word, "Access code for $nodeId")
    }

    /**
     * Generate a counter-hack puzzle
     */
    fun generateCounterHackPuzzle(difficulty: Int = 2): Puzzle {
        val words = listOf("FIREWALL", "SHIELD", "DEFEND", "BLOCK", "SECURE", "PROTECT")
        val word = words.random()
        val types = listOf(PuzzleType.REVERSE, PuzzleType.HEX_DECODE, PuzzleType.WORD_JUMBLE)
        return generate(types.random(), difficulty, word, "Solve to activate shield")
    }

    /**
     * Generate a defrag puzzle step
     */
    fun generateDefragPuzzle(step: Int): Puzzle {
        val words = listOf("CLEAN", "PURGE", "RESET", "FLUSH", "CLEAR", "WIPE")
        val word = words[step % words.size]
        return generate(PuzzleType.REVERSE, 1, word, "Defrag step ${step + 1}")
    }

    fun analyzePuzzle(puzzle: Puzzle): String {
        return buildString {
            appendLine("=== PUZZLE ANALYSIS ===")
            appendLine()
            appendLine("Type: ${getPuzzleTypeName(puzzle.type)}")
            appendLine("Difficulty: ${"★".repeat(puzzle.difficulty)}${"☆".repeat(5 - puzzle.difficulty)}")
            appendLine()
            appendLine("Analysis:")

            when (puzzle.type) {
                PuzzleType.WORD_SEARCH -> {
                    appendLine("- Grid contains hidden words")
                    appendLine("- Search horizontally, vertically, and diagonally")
                    appendLine("- Use 'submit WORD1 WORD2 WORD3' with found words")
                }
                PuzzleType.CAESAR_CIPHER -> {
                    appendLine("- Text encrypted with letter substitution")
                    appendLine("- Try 'decode <file> caesar' to attempt decryption")
                    appendLine("- Common shifts: 3 (classic), 13 (ROT13)")
                }
                PuzzleType.BINARY_DECODE -> {
                    appendLine("- Binary data represents ASCII text")
                    appendLine("- Each 8-bit sequence = one character")
                    appendLine("- Use 'decode <file> binary' to convert")
                }
                PuzzleType.PATTERN_MATCH -> {
                    appendLine("- Sequence follows a mathematical or logical pattern")
                    appendLine("- Find the rule and predict the next element")
                }
                PuzzleType.ANAGRAM -> {
                    appendLine("- Words have been scrambled")
                    appendLine("- Rearrange letters to form valid words")
                }
                PuzzleType.PORT_SEQUENCE -> {
                    appendLine("- Port numbers follow a networking pattern")
                    appendLine("- Identify the next port in the sequence")
                }
                PuzzleType.HEX_DECODE -> {
                    appendLine("- Hexadecimal encoded ASCII data")
                    appendLine("- Convert each hex pair to a character")
                }
                PuzzleType.REVERSE -> {
                    appendLine("- Data has been reversed")
                    appendLine("- Read backwards to decode")
                }
                PuzzleType.WORD_JUMBLE -> {
                    appendLine("- Letters have been shuffled")
                    appendLine("- Unscramble to find the word")
                }
                // Combat and node access puzzles have inline hints in their content
                else -> {
                    appendLine("- Read the puzzle prompt carefully")
                    appendLine("- Use 'solve <answer>' to submit")
                }
            }

            puzzle.hint?.let { appendLine("\nHint: $it") }
        }
    }

    private fun getPuzzleTypeName(type: PuzzleType): String = when (type) {
        PuzzleType.WORD_SEARCH -> "Word Search Grid"
        PuzzleType.CAESAR_CIPHER -> "Caesar Cipher"
        PuzzleType.BINARY_DECODE -> "Binary Encoding"
        PuzzleType.PATTERN_MATCH -> "Pattern Sequence"
        PuzzleType.ANAGRAM -> "Anagram"
        PuzzleType.PORT_SEQUENCE -> "Port Sequence"
        PuzzleType.HEX_DECODE -> "Hexadecimal Encoding"
        PuzzleType.REVERSE -> "Reversed Data"
        PuzzleType.WORD_JUMBLE -> "Word Jumble"
        PuzzleType.MEMORY_FORENSICS -> "Memory Forensics"
        PuzzleType.PORT_SCAN_INTERCEPTION -> "Port Scan Interception"
        PuzzleType.LOG_PATTERN_RECOGNITION -> "Log Pattern Recognition"
        PuzzleType.PROCESS_KILL -> "Process Kill"
        PuzzleType.PACKET_INSPECTION -> "Packet Inspection"
        PuzzleType.ENCRYPTION_CRACKING -> "Encryption Cracking"
        PuzzleType.TRACE_ROUTE -> "Trace Route"
        PuzzleType.REGEX_FILTERING -> "Regex Filtering"
        PuzzleType.PASSWORD_CRACKING -> "Password Cracking"
        PuzzleType.FIREWALL_BYPASS -> "Firewall Bypass"
        PuzzleType.CRYPTOGRAPHIC_CHALLENGE -> "Cryptographic Challenge"
        PuzzleType.IDS_EVASION -> "IDS Evasion"
        PuzzleType.MULTI_FACTOR_BYPASS -> "Multi-Factor Bypass"
        PuzzleType.CERTIFICATE_BYPASS -> "Certificate Bypass"
        PuzzleType.CAPTCHA_SOLVING -> "Captcha Solving"
        PuzzleType.NETWORK_SEGMENTATION -> "Network Segmentation"
    }
}
