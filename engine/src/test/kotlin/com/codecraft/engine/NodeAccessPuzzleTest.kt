package com.codecraft.engine

import com.codecraft.engine.domain.PuzzleType
import com.codecraft.engine.puzzle.*
import kotlin.test.Test
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class NodeAccessPuzzleTest {

    // ──────────────────────────────────────────────
    // PASSWORD CRACKING
    // ──────────────────────────────────────────────

    @Test
    fun `password cracking generates valid puzzle`() {
        val puzzle = PasswordCrackingPuzzle(1)
        assertNotNull(puzzle.solution)
        assertTrue(puzzle.content.contains("PASSWORD CRACKING"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `password cracking primary solution passes`() {
        val puzzle = PasswordCrackingPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `password cracking alternative solutions pass`() {
        val puzzle = PasswordCrackingPuzzle(1)
        puzzle.alternativeSolutions.forEach { alt ->
            assertTrue(puzzle.validate(alt), "Alternative solution '$alt' failed")
        }
    }

    @Test
    fun `password cracking wrong solution fails`() {
        val puzzle = PasswordCrackingPuzzle(1)
        assertFalse(puzzle.validate("wrongpassword"))
    }

    // ──────────────────────────────────────────────
    // FIREWALL BYPASS
    // ──────────────────────────────────────────────

    @Test
    fun `firewall bypass generates valid puzzle`() {
        val puzzle = FirewallBypassPuzzle(1)
        assertTrue(puzzle.content.contains("FIREWALL BYPASS"))
        assertTrue(puzzle.solution.toIntOrNull() != null)
    }

    @Test
    fun `firewall bypass correct solution passes`() {
        val puzzle = FirewallBypassPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `firewall bypass has exposure cost`() {
        assertTrue(FirewallBypassPuzzle(1).exposureCost > 0.0)
    }

    // ──────────────────────────────────────────────
    // CRYPTOGRAPHIC CHALLENGE
    // ──────────────────────────────────────────────

    @Test
    fun `cryptographic challenge generates valid puzzle`() {
        val puzzle = CryptographicChallengePuzzle(1)
        assertTrue(puzzle.content.contains("CRYPTOGRAPHIC CHALLENGE"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `cryptographic challenge correct solution passes`() {
        val puzzle = CryptographicChallengePuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `cryptographic challenge case insensitive`() {
        val puzzle = CryptographicChallengePuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution.uppercase()))
        assertTrue(puzzle.validate(puzzle.solution.lowercase()))
    }

    // ──────────────────────────────────────────────
    // IDS EVASION
    // ──────────────────────────────────────────────

    @Test
    fun `ids evasion generates valid puzzle`() {
        val puzzle = IdsEvasionPuzzle(1)
        assertTrue(puzzle.content.contains("IDS EVASION"))
        assertTrue(puzzle.solution in listOf("A", "B", "C", "D"))
    }

    @Test
    fun `ids evasion correct solution passes`() {
        val puzzle = IdsEvasionPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `ids evasion has failure penalty`() {
        assertTrue(IdsEvasionPuzzle(1).failurePenalty > 0.0)
    }

    // ──────────────────────────────────────────────
    // MULTI-FACTOR BYPASS
    // ──────────────────────────────────────────────

    @Test
    fun `multi factor bypass generates valid puzzle`() {
        val puzzle = MultiFactorBypassPuzzle(1)
        assertTrue(puzzle.content.contains("MULTI-FACTOR BYPASS"))
        assertTrue(puzzle.solution.length == 6)
        assertTrue(puzzle.solution.all { it.isDigit() })
    }

    @Test
    fun `multi factor bypass correct solution passes`() {
        val puzzle = MultiFactorBypassPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `multi factor bypass wrong solution fails`() {
        val puzzle = MultiFactorBypassPuzzle(1)
        assertFalse(puzzle.validate("000000"))
    }

    // ──────────────────────────────────────────────
    // CERTIFICATE BYPASS
    // ──────────────────────────────────────────────

    @Test
    fun `certificate bypass generates valid puzzle`() {
        val puzzle = CertificateBypassPuzzle(1)
        assertTrue(puzzle.content.contains("CERTIFICATE BYPASS"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `certificate bypass correct solution passes`() {
        val puzzle = CertificateBypassPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `certificate bypass wrong solution fails`() {
        val puzzle = CertificateBypassPuzzle(1)
        assertFalse(puzzle.validate("99 99"))
    }

    // ──────────────────────────────────────────────
    // CAPTCHA SOLVING
    // ──────────────────────────────────────────────

    @Test
    fun `captcha solving generates valid puzzle`() {
        val puzzle = CaptchaSolvingPuzzle(1)
        assertTrue(puzzle.content.contains("CAPTCHA SOLVING"))
        assertTrue(puzzle.solution.isNotBlank())
        assertTrue(puzzle.solution.length in 3..5)
    }

    @Test
    fun `captcha solving correct solution passes`() {
        val puzzle = CaptchaSolvingPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `captcha solving case insensitive`() {
        val puzzle = CaptchaSolvingPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution.lowercase()))
    }

    // ──────────────────────────────────────────────
    // NETWORK SEGMENTATION
    // ──────────────────────────────────────────────

    @Test
    fun `network segmentation generates valid puzzle`() {
        val puzzle = NetworkSegmentationPuzzle(1)
        assertTrue(puzzle.content.contains("NETWORK SEGMENTATION"))
        assertTrue(puzzle.solution.isNotBlank())
    }

    @Test
    fun `network segmentation correct solution passes`() {
        val puzzle = NetworkSegmentationPuzzle(1)
        assertTrue(puzzle.validate(puzzle.solution))
    }

    @Test
    fun `network segmentation has exposure and firewall costs`() {
        val puzzle = NetworkSegmentationPuzzle(3)
        assertTrue(puzzle.exposureCost > 0.0)
    }

    // ──────────────────────────────────────────────
    // All types self-validate
    // ──────────────────────────────────────────────

    @Test
    fun `all node access puzzle types self-validate`() {
        val puzzles = listOf(
            PasswordCrackingPuzzle(1),
            FirewallBypassPuzzle(1),
            CryptographicChallengePuzzle(1),
            IdsEvasionPuzzle(1),
            MultiFactorBypassPuzzle(1),
            CertificateBypassPuzzle(1),
            CaptchaSolvingPuzzle(1),
            NetworkSegmentationPuzzle(1)
        )
        puzzles.forEach { puzzle ->
            assertTrue(puzzle.validate(puzzle.solution), "Puzzle ${puzzle.type} failed self-validation")
        }
    }

    @Test
    fun `node access puzzles have positive exposure costs`() {
        val puzzles = listOf(
            PasswordCrackingPuzzle(1),
            FirewallBypassPuzzle(1),
            CryptographicChallengePuzzle(1),
            IdsEvasionPuzzle(1),
            MultiFactorBypassPuzzle(1),
            CertificateBypassPuzzle(1),
            CaptchaSolvingPuzzle(1),
            NetworkSegmentationPuzzle(1)
        )
        puzzles.forEach { puzzle ->
            assertTrue(puzzle.exposureCost >= 0.0)
            assertTrue(puzzle.failurePenalty >= 0.0)
        }
    }
}
