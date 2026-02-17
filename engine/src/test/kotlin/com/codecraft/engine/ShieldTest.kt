package com.codecraft.engine

import com.codecraft.engine.session.GameSession
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class ShieldTest {

    private fun createSession(): GameSession = GameSession("test-shield")

    @Test
    fun `shield starts inactive`() {
        val session = createSession()
        assertFalse(session.shieldActive)
        assertNull(session.shieldExpiresAt)
        assertFalse(session.isShieldActive())
    }

    @Test
    fun `activateShield sets shield active with expiry`() {
        val session = createSession()
        session.activateShield(240)

        assertTrue(session.shieldActive)
        assertNotNull(session.shieldExpiresAt)
        assertTrue(session.isShieldActive())
    }

    @Test
    fun `shield expiry is set to correct duration`() {
        val session = createSession()
        val beforeActivation = System.currentTimeMillis()
        session.activateShield(60)
        val afterActivation = System.currentTimeMillis()

        val expiresAt = session.shieldExpiresAt!!
        // Should expire ~60 seconds from now
        assertTrue(expiresAt >= beforeActivation + 60000)
        assertTrue(expiresAt <= afterActivation + 60000)
    }

    @Test
    fun `shield with past expiry is not active`() {
        val session = createSession()
        session.shieldActive = true
        session.shieldExpiresAt = System.currentTimeMillis() - 1000 // expired 1 second ago

        assertFalse(session.isShieldActive())
    }

    @Test
    fun `cleanupExpiredShield resets flags`() {
        val session = createSession()
        session.shieldActive = true
        session.shieldExpiresAt = System.currentTimeMillis() - 1000 // expired

        session.cleanupExpiredShield()

        assertFalse(session.shieldActive)
        assertNull(session.shieldExpiresAt)
    }

    @Test
    fun `cleanupExpiredShield does nothing if shield still active`() {
        val session = createSession()
        session.activateShield(240)

        session.cleanupExpiredShield()

        assertTrue(session.shieldActive)
        assertNotNull(session.shieldExpiresAt)
    }

    @Test
    fun `activation adds sentinel event`() {
        val session = createSession()
        session.activateShield(240)

        assertTrue(session.sentinelEvents.any { it.type == "shield_activated" })
    }

    @Test
    fun `expired shield adds sentinel event on cleanup`() {
        val session = createSession()
        session.shieldActive = true
        session.shieldExpiresAt = System.currentTimeMillis() - 1000

        // Trigger cleanup via isShieldActive check
        session.isShieldActive()

        assertTrue(session.sentinelEvents.any { it.type == "shield_expired" })
    }

    @Test
    fun `default shield duration is 240 seconds`() {
        val session = createSession()
        val before = System.currentTimeMillis()
        session.activateShield()
        val after = System.currentTimeMillis()

        val expiresAt = session.shieldExpiresAt!!
        assertTrue(expiresAt >= before + 240000)
        assertTrue(expiresAt <= after + 240000)
    }

    @Test
    fun `exposure is blocked when shield is active`() {
        val session = createSession()
        session.activateShield(240)

        val initialExposure = session.player.exposure

        // Simulate what CommandRegistry does: skip exposure increase when shield active
        if (!session.isShieldActive()) {
            session.player.increaseExposure(10.0)
        }

        assertEquals(initialExposure, session.player.exposure)
    }

    @Test
    fun `exposure is applied when shield is inactive`() {
        val session = createSession()

        session.player.increaseExposure(10.0)

        assertEquals(10.0, session.player.exposure)
    }
}
