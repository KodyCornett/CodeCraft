package com.codecraft.engine

import com.codecraft.engine.domain.PuzzleType
import com.codecraft.engine.puzzle.*
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class PuzzleTest {

    // =============================================
    // Caesar Cipher
    // =============================================

    @Test
    fun `caesar cipher puzzle generates and validates`() {
        val puzzle = CaesarCipherPuzzle(1, "HELLO")
        assertEquals(PuzzleType.CAESAR_CIPHER, puzzle.type)
        assertTrue(puzzle.validate("HELLO"))
        assertTrue(puzzle.validate("hello"))
        assertFalse(puzzle.validate("WORLD"))
        assertTrue(puzzle.content.contains("ENCRYPTED"))
    }

    // =============================================
    // Binary Decode
    // =============================================

    @Test
    fun `binary decode puzzle generates and validates`() {
        val puzzle = BinaryDecodePuzzle(1, "HI")
        assertEquals(PuzzleType.BINARY_DECODE, puzzle.type)
        assertTrue(puzzle.validate("HI"))
        assertTrue(puzzle.validate("hi"))
        assertFalse(puzzle.validate("BYE"))
        assertTrue(puzzle.content.contains("BINARY"))
    }

    // =============================================
    // Hex Decode
    // =============================================

    @Test
    fun `hex decode puzzle generates and validates`() {
        val puzzle = HexDecodePuzzle(1, "AB")
        assertEquals(PuzzleType.HEX_DECODE, puzzle.type)
        assertTrue(puzzle.validate("AB"))
        assertTrue(puzzle.validate("ab"))
        assertFalse(puzzle.validate("CD"))
        assertTrue(puzzle.content.contains("HEXADECIMAL"))
    }

    @Test
    fun `hex decode encodes correctly`() {
        val puzzle = HexDecodePuzzle(1, "A")
        // 'A' = 0x41
        assertTrue(puzzle.content.contains("41"))
    }

    // =============================================
    // Reverse
    // =============================================

    @Test
    fun `reverse puzzle generates and validates`() {
        val puzzle = ReversePuzzle(1, "CODE")
        assertEquals(PuzzleType.REVERSE, puzzle.type)
        assertTrue(puzzle.validate("CODE"))
        assertTrue(puzzle.validate("code"))
        assertFalse(puzzle.validate("EDOC"))
        // The displayed content should contain the reversed text
        assertTrue(puzzle.content.contains("EDOC"))
    }

    // =============================================
    // Word Jumble
    // =============================================

    @Test
    fun `word jumble puzzle generates and validates`() {
        val puzzle = WordJumblePuzzle(1, "ACCESS")
        assertEquals(PuzzleType.WORD_JUMBLE, puzzle.type)
        assertTrue(puzzle.validate("ACCESS"))
        assertTrue(puzzle.validate("access"))
        assertFalse(puzzle.validate("DENIED"))
        assertTrue(puzzle.content.contains("SCRAMBLED"))
    }

    // =============================================
    // Port Sequence
    // =============================================

    @Test
    fun `port sequence puzzle generates and validates`() {
        val puzzle = PortSequencePuzzle(1, "25")
        assertEquals(PuzzleType.PORT_SEQUENCE, puzzle.type)
        assertTrue(puzzle.validate("25"))
        assertTrue(puzzle.content.contains("PORT SEQUENCE"))
    }

    // =============================================
    // Pattern Match
    // =============================================

    @Test
    fun `pattern match puzzle generates and validates`() {
        val puzzle = PatternMatchPuzzle(1, "10")
        assertEquals(PuzzleType.PATTERN_MATCH, puzzle.type)
        assertTrue(puzzle.validate("10"))
        assertTrue(puzzle.content.contains("PATTERN"))
    }

    // =============================================
    // Word Search
    // =============================================

    @Test
    fun `word search puzzle generates and validates`() {
        val puzzle = WordSearchPuzzle(1, listOf("CODE", "HACK"))
        assertEquals(PuzzleType.WORD_SEARCH, puzzle.type)
        assertTrue(puzzle.validate("CODE HACK"))
        assertTrue(puzzle.validate("HACK CODE"))
        assertFalse(puzzle.validate("CODE"))
        assertTrue(puzzle.content.contains("ENCRYPTED DATA"))
    }

    // =============================================
    // Anagram
    // =============================================

    @Test
    fun `anagram puzzle generates and validates`() {
        val puzzle = AnagramPuzzle(1, listOf("CODE", "HACK"))
        assertEquals(PuzzleType.ANAGRAM, puzzle.type)
        assertTrue(puzzle.validate("CODE HACK"))
        assertFalse(puzzle.validate("HACK CODE")) // order matters for anagram
        assertTrue(puzzle.content.contains("SCRAMBLED"))
    }

    // =============================================
    // PuzzleGenerator factory
    // =============================================

    @Test
    fun `generator creates all puzzle types`() {
        for (type in PuzzleType.entries) {
            val seed = when (type) {
                PuzzleType.WORD_SEARCH -> "CODE HACK"
                PuzzleType.ANAGRAM -> "CODE HACK"
                else -> "TEST"
            }
            val puzzle = PuzzleGenerator.generate(type, 1, seed)
            assertNotNull(puzzle)
            assertEquals(type, puzzle.type)
            assertTrue(puzzle.content.isNotBlank())
            assertTrue(puzzle.solution.isNotBlank())
        }
    }

    @Test
    fun `generateConnectionPuzzle creates valid puzzle`() {
        val puzzle = PuzzleGenerator.generateConnectionPuzzle("nova-corp-web", 2)
        assertNotNull(puzzle)
        assertTrue(puzzle.content.isNotBlank())
        assertTrue(puzzle.solution.isNotBlank())
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `generateCounterHackPuzzle creates valid puzzle`() {
        val puzzle = PuzzleGenerator.generateCounterHackPuzzle()
        assertNotNull(puzzle)
        assertTrue(puzzle.content.isNotBlank())
        assertTrue(puzzle.solution.isNotBlank())
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `generateDefragPuzzle creates valid puzzle`() {
        for (step in 0..5) {
            val puzzle = PuzzleGenerator.generateDefragPuzzle(step)
            assertNotNull(puzzle)
            assertTrue(puzzle.content.isNotBlank())
            assertTrue(puzzle.solution.isNotBlank())
            assertTrue(puzzle.validate(puzzle.solution))
        }
    }

    @Test
    fun `difficulty is clamped to 1-5`() {
        val puzzleLow = PuzzleGenerator.generate(PuzzleType.REVERSE, 0, "TEST")
        assertEquals(1, puzzleLow.difficulty)

        val puzzleHigh = PuzzleGenerator.generate(PuzzleType.REVERSE, 10, "TEST")
        assertEquals(5, puzzleHigh.difficulty)
    }

    @Test
    fun `puzzle analysis generates non-empty output`() {
        for (type in PuzzleType.entries) {
            val seed = when (type) {
                PuzzleType.WORD_SEARCH -> "CODE HACK"
                PuzzleType.ANAGRAM -> "CODE HACK"
                else -> "TEST"
            }
            val puzzle = PuzzleGenerator.generate(type, 1, seed)
            val analysis = PuzzleGenerator.analyzePuzzle(puzzle)
            assertTrue(analysis.isNotBlank())
            assertTrue(analysis.contains("PUZZLE ANALYSIS"))
        }
    }

    // =============================================
    // PuzzleDifficulty (Revamp 1.0)
    // =============================================

    @Test
    fun `PuzzleDifficulty fromInt round-trips for all values`() {
        assertEquals(PuzzleDifficulty.EASY,   PuzzleDifficulty.fromInt(1))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromInt(2))
        assertEquals(PuzzleDifficulty.HARD,   PuzzleDifficulty.fromInt(3))
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromInt(4))
    }

    @Test
    fun `PuzzleDifficulty fromExposure boundary conditions`() {
        assertEquals(PuzzleDifficulty.EASY,   PuzzleDifficulty.fromExposure(39.9, 0))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromExposure(40.0, 0))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromExposure(59.9, 0))
        assertEquals(PuzzleDifficulty.HARD,   PuzzleDifficulty.fromExposure(60.0, 0))
        assertEquals(PuzzleDifficulty.HARD,   PuzzleDifficulty.fromExposure(79.9, 0))
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromExposure(80.1, 0))
    }

    @Test
    fun `generateCombatPuzzle self-validates`() {
        val puzzle = PuzzleGenerator.generateCombatPuzzle(
            com.codecraft.engine.puzzle.CombatType.SENTINEL_ATTACK,
            PuzzleDifficulty.EASY,
            emptyList()
        )
        assertNotNull(puzzle)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `generate with PuzzleDifficulty enum overload works`() {
        val puzzle = PuzzleGenerator.generate(PuzzleType.CAESAR_CIPHER, PuzzleDifficulty.MEDIUM, "HELLO")
        assertNotNull(puzzle)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `PuzzleTutorials maybePrepend first call includes tutorial`() {
        val progress = PuzzleDifficulty.EASY.let { PlayerPuzzleProgress() }
        val content = "test puzzle"
        val result = PuzzleTutorials.maybePrepend(content, "ENCRYPTION_CRACKING", progress)
        assertTrue(result.contains("TUTORIAL"))
        assertTrue(result.contains(content))
    }

    @Test
    fun `PuzzleTutorials maybePrepend second call unchanged`() {
        val progress = PlayerPuzzleProgress()
        val content = "test puzzle"
        PuzzleTutorials.maybePrepend(content, "TRACE_ROUTE", progress)
        val second = PuzzleTutorials.maybePrepend(content, "TRACE_ROUTE", progress)
        assertEquals(content, second)
    }
}
