package com.codecraft.engine

import com.codecraft.engine.domain.PuzzleType
import com.codecraft.engine.puzzle.*
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class PuzzleSelectorTest {

    // ──────────────────────────────────────────────
    // PuzzleDifficulty
    // ──────────────────────────────────────────────

    @Test
    fun `PuzzleDifficulty fromInt round-trips`() {
        assertEquals(PuzzleDifficulty.EASY,   PuzzleDifficulty.fromInt(1))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromInt(2))
        assertEquals(PuzzleDifficulty.HARD,   PuzzleDifficulty.fromInt(3))
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromInt(4))
    }

    @Test
    fun `PuzzleDifficulty fromInt unknown value defaults to MEDIUM`() {
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromInt(99))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromInt(0))
    }

    @Test
    fun `PuzzleDifficulty fromExposure below 40 returns EASY`() {
        assertEquals(PuzzleDifficulty.EASY, PuzzleDifficulty.fromExposure(0.0, 0))
        assertEquals(PuzzleDifficulty.EASY, PuzzleDifficulty.fromExposure(39.9, 0))
    }

    @Test
    fun `PuzzleDifficulty fromExposure 40-60 returns MEDIUM`() {
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromExposure(40.0, 0))
        assertEquals(PuzzleDifficulty.MEDIUM, PuzzleDifficulty.fromExposure(59.9, 0))
    }

    @Test
    fun `PuzzleDifficulty fromExposure 60-80 returns HARD`() {
        assertEquals(PuzzleDifficulty.HARD, PuzzleDifficulty.fromExposure(60.0, 0))
        assertEquals(PuzzleDifficulty.HARD, PuzzleDifficulty.fromExposure(79.9, 0))
    }

    @Test
    fun `PuzzleDifficulty fromExposure above 80 returns EXPERT`() {
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromExposure(80.1, 0))
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromExposure(100.0, 0))
    }

    @Test
    fun `PuzzleDifficulty fromExposure high threatPressure overrides exposure`() {
        // exposure=30 (EASY) but threatPressure=80 → EXPERT
        assertEquals(PuzzleDifficulty.EXPERT, PuzzleDifficulty.fromExposure(30.0, 80))
    }

    // ──────────────────────────────────────────────
    // PuzzleSelector - combat type selection
    // ──────────────────────────────────────────────

    @Test
    fun `selectCombatType returns valid combat puzzle type`() {
        val combatPuzzleTypes = setOf(
            PuzzleType.MEMORY_FORENSICS, PuzzleType.PORT_SCAN_INTERCEPTION,
            PuzzleType.LOG_PATTERN_RECOGNITION, PuzzleType.PROCESS_KILL,
            PuzzleType.PACKET_INSPECTION, PuzzleType.ENCRYPTION_CRACKING,
            PuzzleType.TRACE_ROUTE, PuzzleType.REGEX_FILTERING
        )
        val result = PuzzleSelector.selectCombatType(CombatType.SENTINEL_ATTACK, emptyList())
        assertTrue(result in combatPuzzleTypes, "Expected combat type, got $result")
    }

    @Test
    fun `selectCombatType excludes recent types`() {
        val recent = listOf(
            PuzzleType.PORT_SCAN_INTERCEPTION,
            PuzzleType.PROCESS_KILL,
            PuzzleType.MEMORY_FORENSICS
        )
        // Run 20 times — should never pick from recent 3 when others available
        repeat(20) {
            val result = PuzzleSelector.selectCombatType(CombatType.SENTINEL_ATTACK, recent)
            assertFalse(result in recent, "Should not pick recent type $result")
        }
    }

    @Test
    fun `selectCombatType falls back to all types when all are recent`() {
        // Only 8 types but we pass 8 recent — selector should fall back gracefully
        val allCombat = listOf(
            PuzzleType.MEMORY_FORENSICS, PuzzleType.PORT_SCAN_INTERCEPTION,
            PuzzleType.LOG_PATTERN_RECOGNITION, PuzzleType.PROCESS_KILL,
            PuzzleType.PACKET_INSPECTION, PuzzleType.ENCRYPTION_CRACKING,
            PuzzleType.TRACE_ROUTE, PuzzleType.REGEX_FILTERING
        )
        val result = PuzzleSelector.selectCombatType(CombatType.SENTINEL_ATTACK, allCombat)
        assertTrue(result in allCombat)
    }

    @Test
    fun `selectNodeAccessType returns valid node access type`() {
        val nodeTypes = setOf(
            PuzzleType.PASSWORD_CRACKING, PuzzleType.FIREWALL_BYPASS,
            PuzzleType.CRYPTOGRAPHIC_CHALLENGE, PuzzleType.IDS_EVASION,
            PuzzleType.MULTI_FACTOR_BYPASS, PuzzleType.CERTIFICATE_BYPASS,
            PuzzleType.CAPTCHA_SOLVING, PuzzleType.NETWORK_SEGMENTATION
        )
        val result = PuzzleSelector.selectNodeAccessType(emptyList())
        assertTrue(result in nodeTypes, "Expected node access type, got $result")
    }

    @Test
    fun `selectNodeAccessType excludes recent types`() {
        val recent = listOf(
            PuzzleType.PASSWORD_CRACKING,
            PuzzleType.FIREWALL_BYPASS,
            PuzzleType.CRYPTOGRAPHIC_CHALLENGE
        )
        repeat(20) {
            val result = PuzzleSelector.selectNodeAccessType(recent)
            assertFalse(result in recent, "Should not pick recent type $result")
        }
    }

    // ──────────────────────────────────────────────
    // Adaptive difficulty
    // ──────────────────────────────────────────────

    @Test
    fun `adaptiveDifficulty increases level when success rate above 80 percent`() {
        val progress = PlayerPuzzleProgress()
        // Simulate 10 successes out of 10 attempts
        repeat(10) { progress.recordAttempt("TEST_TYPE", true) }
        val base = PuzzleDifficulty.EASY
        val result = PuzzleSelector.adaptiveDifficulty(base, 0.0, 0, progress, "TEST_TYPE")
        assertTrue(result.intValue > base.intValue)
    }

    @Test
    fun `adaptiveDifficulty decreases level when success rate below 30 percent`() {
        val progress = PlayerPuzzleProgress()
        // Simulate 1 success out of 10 attempts → 10% rate
        progress.recordAttempt("TEST_TYPE", true)
        repeat(9) { progress.recordAttempt("TEST_TYPE", false) }
        val base = PuzzleDifficulty.HARD
        val result = PuzzleSelector.adaptiveDifficulty(base, 0.0, 0, progress, "TEST_TYPE")
        assertTrue(result.intValue < base.intValue)
    }

    @Test
    fun `adaptiveDifficulty floors at EASY`() {
        val progress = PlayerPuzzleProgress()
        repeat(10) { progress.recordAttempt("TEST_TYPE", false) }
        val result = PuzzleSelector.adaptiveDifficulty(PuzzleDifficulty.EASY, 0.0, 0, progress, "TEST_TYPE")
        assertEquals(PuzzleDifficulty.EASY, result)
    }

    @Test
    fun `adaptiveDifficulty caps at EXPERT`() {
        val progress = PlayerPuzzleProgress()
        repeat(10) { progress.recordAttempt("TEST_TYPE", true) }
        val result = PuzzleSelector.adaptiveDifficulty(PuzzleDifficulty.EXPERT, 0.0, 0, progress, "TEST_TYPE")
        assertEquals(PuzzleDifficulty.EXPERT, result)
    }

    // ──────────────────────────────────────────────
    // PuzzleTutorials
    // ──────────────────────────────────────────────

    @Test
    fun `maybePrepend includes tutorial on first call`() {
        val progress = PlayerPuzzleProgress()
        val content = "puzzle content here"
        val result = PuzzleTutorials.maybePrepend(content, "MEMORY_FORENSICS", progress)
        assertTrue(result.contains("TUTORIAL"), "Should include tutorial on first call")
        assertTrue(result.contains(content))
    }

    @Test
    fun `maybePrepend does not include tutorial on second call`() {
        val progress = PlayerPuzzleProgress()
        val content = "puzzle content here"
        PuzzleTutorials.maybePrepend(content, "MEMORY_FORENSICS", progress)
        val secondResult = PuzzleTutorials.maybePrepend(content, "MEMORY_FORENSICS", progress)
        assertEquals(content, secondResult, "Should return content unchanged on second call")
    }

    @Test
    fun `maybePrepend marks tutorial as seen`() {
        val progress = PlayerPuzzleProgress()
        assertFalse(progress.hasSeen("PACKET_INSPECTION"))
        PuzzleTutorials.maybePrepend("content", "PACKET_INSPECTION", progress)
        assertTrue(progress.hasSeen("PACKET_INSPECTION"))
    }

    @Test
    fun `maybePrepend with unknown type returns content unchanged`() {
        val progress = PlayerPuzzleProgress()
        val content = "no tutorial for this"
        val result = PuzzleTutorials.maybePrepend(content, "UNKNOWN_TYPE", progress)
        assertEquals(content, result)
    }

    // ──────────────────────────────────────────────
    // PlayerPuzzleProgress
    // ──────────────────────────────────────────────

    @Test
    fun `PlayerPuzzleProgress tracks success rate accurately`() {
        val progress = PlayerPuzzleProgress()
        repeat(8) { progress.recordAttempt("TYPE_A", true) }
        repeat(2) { progress.recordAttempt("TYPE_A", false) }
        assertEquals(0.8, progress.successRate("TYPE_A"), 0.01)
    }

    @Test
    fun `PlayerPuzzleProgress unknown type defaults to 50 percent`() {
        val progress = PlayerPuzzleProgress()
        assertEquals(0.5, progress.successRate("UNKNOWN"), 0.01)
    }

    // ──────────────────────────────────────────────
    // generateCombatPuzzle via PuzzleGenerator
    // ──────────────────────────────────────────────

    @Test
    fun `generateCombatPuzzle returns valid self-validating puzzle`() {
        val puzzle = PuzzleGenerator.generateCombatPuzzle(CombatType.SENTINEL_ATTACK, PuzzleDifficulty.EASY, emptyList())
        assertNotNull(puzzle)
        assertTrue(puzzle.validate(puzzle.solution), "Generated combat puzzle should self-validate")
    }

    @Test
    fun `generate succeeds for all 16 new PuzzleType entries`() {
        val newTypes = listOf(
            PuzzleType.MEMORY_FORENSICS, PuzzleType.PORT_SCAN_INTERCEPTION,
            PuzzleType.LOG_PATTERN_RECOGNITION, PuzzleType.PROCESS_KILL,
            PuzzleType.PACKET_INSPECTION, PuzzleType.ENCRYPTION_CRACKING,
            PuzzleType.TRACE_ROUTE, PuzzleType.REGEX_FILTERING,
            PuzzleType.PASSWORD_CRACKING, PuzzleType.FIREWALL_BYPASS,
            PuzzleType.CRYPTOGRAPHIC_CHALLENGE, PuzzleType.IDS_EVASION,
            PuzzleType.MULTI_FACTOR_BYPASS, PuzzleType.CERTIFICATE_BYPASS,
            PuzzleType.CAPTCHA_SOLVING, PuzzleType.NETWORK_SEGMENTATION
        )
        newTypes.forEach { type ->
            val puzzle = PuzzleGenerator.generate(type, 1, "test")
            assertNotNull(puzzle, "generate($type) returned null")
            assertTrue(puzzle.validate(puzzle.solution), "Puzzle $type failed self-validation")
        }
    }
}
