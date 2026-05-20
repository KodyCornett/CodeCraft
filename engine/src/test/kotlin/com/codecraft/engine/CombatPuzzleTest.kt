package com.codecraft.engine

import com.codecraft.engine.domain.PuzzleType
import com.codecraft.engine.puzzle.*
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class CombatPuzzleTest {

    // ──────────────────────────────────────────────
    // MEMORY FORENSICS
    // ──────────────────────────────────────────────

    @Test
    fun `memory forensics generates valid puzzle`() {
        val puzzle = MemoryForensicsPuzzle(1)
        assertEquals(PuzzleType.MEMORY_FORENSICS, puzzle.type)
        assertNotNull(puzzle.solution)
        assertTrue(puzzle.content.contains("MEMORY FORENSICS"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `memory forensics correct solution passes`() {
        val puzzle = MemoryForensicsPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `memory forensics wrong solution fails`() {
        val puzzle = MemoryForensicsPuzzle(1)
        assertFalse(puzzle.validate("9999"))
    }

    @Test
    fun `memory forensics allows partial credit`() {
        assertTrue(MemoryForensicsPuzzle(2).allowsPartialCredit)
    }

    @Test
    fun `memory forensics partial score for partial answer`() {
        val puzzle = MemoryForensicsPuzzle(2) // 2 rogue PIDs
        val pids = puzzle.solution.split(" ")
        if (pids.size >= 2) {
            val partialAnswer = pids[0]
            val result = puzzle.validateWithScore(partialAnswer)
            assertFalse(result.isCorrect)
            assertTrue(result.score > 0.0)
        }
    }

    // ──────────────────────────────────────────────
    // PORT SCAN INTERCEPTION
    // ──────────────────────────────────────────────

    @Test
    fun `port scan interception generates valid puzzle`() {
        val puzzle = PortScanInterceptionPuzzle(1)
        assertEquals(PuzzleType.PORT_SCAN_INTERCEPTION, puzzle.type)
        assertTrue(puzzle.content.contains("PORT SCAN"))
        assertTrue(puzzle.solution.toIntOrNull() != null)
    }

    @Test
    fun `port scan interception correct solution passes`() {
        val puzzle = PortScanInterceptionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `port scan interception wrong solution fails`() {
        val puzzle = PortScanInterceptionPuzzle(1)
        // Use a port that is never in the suspicious port list
        assertFalse(puzzle.validate("1"))
    }

    // ──────────────────────────────────────────────
    // LOG PATTERN RECOGNITION
    // ──────────────────────────────────────────────

    @Test
    fun `log pattern recognition generates valid puzzle`() {
        val puzzle = LogPatternRecognitionPuzzle(1)
        assertEquals(PuzzleType.LOG_PATTERN_RECOGNITION, puzzle.type)
        assertTrue(puzzle.content.contains("LOG PATTERN"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `log pattern recognition correct solution passes`() {
        val puzzle = LogPatternRecognitionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `log pattern recognition case insensitive`() {
        val puzzle = LogPatternRecognitionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution.lowercase()))
        assertTrue(puzzle.validate(puzzle.solution.uppercase()))
    }

    // ──────────────────────────────────────────────
    // PROCESS KILL
    // ──────────────────────────────────────────────

    @Test
    fun `process kill generates valid puzzle`() {
        val puzzle = ProcessKillPuzzle(1)
        assertEquals(PuzzleType.PROCESS_KILL, puzzle.type)
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `process kill correct solution passes`() {
        val puzzle = ProcessKillPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `process kill partial solution fails`() {
        val puzzle = ProcessKillPuzzle(2) // 2 PIDs expected
        val pids = puzzle.solution.split(" ")
        if (pids.size >= 2) {
            assertFalse(puzzle.validate(pids[0]))
        }
    }

    // ──────────────────────────────────────────────
    // PACKET INSPECTION
    // ──────────────────────────────────────────────

    @Test
    fun `packet inspection generates valid puzzle`() {
        val puzzle = PacketInspectionPuzzle(1)
        assertEquals(PuzzleType.PACKET_INSPECTION, puzzle.type)
        assertTrue(puzzle.content.contains("PACKET INSPECTION"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `packet inspection correct solution passes`() {
        val puzzle = PacketInspectionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `packet inspection case insensitive`() {
        val puzzle = PacketInspectionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution.lowercase()))
    }

    // ──────────────────────────────────────────────
    // ENCRYPTION CRACKING
    // ──────────────────────────────────────────────

    @Test
    fun `encryption cracking generates valid puzzle`() {
        val puzzle = EncryptionCrackingPuzzle(1)
        assertEquals(PuzzleType.ENCRYPTION_CRACKING, puzzle.type)
        assertTrue(puzzle.content.contains("ENCRYPTION CRACKING"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `encryption cracking correct solution passes`() {
        val puzzle = EncryptionCrackingPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `encryption cracking wrong solution fails`() {
        val puzzle = EncryptionCrackingPuzzle(1)
        assertFalse(puzzle.validate("WRONG ANSWER"))
    }

    // ──────────────────────────────────────────────
    // TRACE ROUTE
    // ──────────────────────────────────────────────

    @Test
    fun `trace route generates valid puzzle`() {
        val puzzle = TraceRoutePuzzle(1)
        assertEquals(PuzzleType.TRACE_ROUTE, puzzle.type)
        assertTrue(puzzle.content.contains("TRACE ROUTE"))
        assertTrue(puzzle.solution.contains("."))
    }

    @Test
    fun `trace route correct solution passes`() {
        val puzzle = TraceRoutePuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `trace route wrong solution fails`() {
        val puzzle = TraceRoutePuzzle(1)
        assertFalse(puzzle.validate("1.2.3.4"))
    }

    // ──────────────────────────────────────────────
    // REGEX FILTERING
    // ──────────────────────────────────────────────

    @Test
    fun `regex filtering generates valid puzzle`() {
        val puzzle = RegexFilteringPuzzle(1)
        assertEquals(PuzzleType.REGEX_FILTERING, puzzle.type)
        assertTrue(puzzle.content.contains("REGEX FILTERING"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `regex filtering correct solution passes`() {
        val puzzle = RegexFilteringPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `regex filtering allows partial credit`() {
        assertTrue(RegexFilteringPuzzle(1).allowsPartialCredit)
    }

    // ──────────────────────────────────────────────
    // Difficulty scaling
    // ──────────────────────────────────────────────

    @Test
    fun `combat puzzles scale with difficulty`() {
        for (d in 1..4) {
            val puzzle = MemoryForensicsPuzzle(d)
            assertTrue(puzzle.validate(puzzle.solution))
        }
    }

    @Test
    fun `all combat puzzle types self-validate`() {
        val combatTypes = listOf(
            MemoryForensicsPuzzle(1),
            PortScanInterceptionPuzzle(1),
            LogPatternRecognitionPuzzle(1),
            ProcessKillPuzzle(1),
            PacketInspectionPuzzle(1),
            EncryptionCrackingPuzzle(1),
            TraceRoutePuzzle(1),
            RegexFilteringPuzzle(1)
        )
        combatTypes.forEach { puzzle ->
            assertTrue(puzzle.validate(puzzle.solution), "Puzzle ${puzzle.type} failed self-validation")
        }
    }
}
