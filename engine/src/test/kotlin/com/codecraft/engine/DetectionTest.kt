package com.codecraft.engine

import com.codecraft.engine.domain.Detection
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNull
import kotlin.test.assertNotNull
import kotlin.test.assertTrue

class DetectionTest {

    // =============================================
    // Base Risk tests
    // =============================================

    @Test
    fun `base risk is zero below 60 exposure`() {
        assertEquals(0.0, Detection.calculateBaseRisk(0.0))
        assertEquals(0.0, Detection.calculateBaseRisk(30.0))
        assertEquals(0.0, Detection.calculateBaseRisk(59.9))
        assertEquals(0.0, Detection.calculateBaseRisk(60.0))
    }

    @Test
    fun `base risk scales at 1x from 61 to 70`() {
        assertEquals(1.0, Detection.calculateBaseRisk(61.0))
        assertEquals(5.0, Detection.calculateBaseRisk(65.0))
        assertEquals(10.0, Detection.calculateBaseRisk(70.0))
    }

    @Test
    fun `base risk scales at 2x from 71 to 85`() {
        assertEquals(22.0, Detection.calculateBaseRisk(71.0))
        assertEquals(40.0, Detection.calculateBaseRisk(80.0))
        assertEquals(50.0, Detection.calculateBaseRisk(85.0))
    }

    @Test
    fun `base risk scales at 3x above 85`() {
        assertEquals(78.0, Detection.calculateBaseRisk(86.0))
        assertEquals(90.0, Detection.calculateBaseRisk(90.0))
        // At 100: (100-60)*3 = 120, clamped to 100
        assertEquals(100.0, Detection.calculateBaseRisk(100.0))
    }

    @Test
    fun `base risk is clamped to 100`() {
        assertEquals(100.0, Detection.calculateBaseRisk(100.0))
        assertEquals(100.0, Detection.calculateBaseRisk(110.0))
    }

    // =============================================
    // Firewall Multiplier tests
    // =============================================

    @Test
    fun `firewall multiplier is 0_3 at 80-100 percent`() {
        assertEquals(0.3, Detection.calculateFirewallMultiplier(100.0))
        assertEquals(0.3, Detection.calculateFirewallMultiplier(80.0))
    }

    @Test
    fun `firewall multiplier is 0_6 at 60-79 percent`() {
        assertEquals(0.6, Detection.calculateFirewallMultiplier(79.0))
        assertEquals(0.6, Detection.calculateFirewallMultiplier(60.0))
    }

    @Test
    fun `firewall multiplier is 1_0 at 40-59 percent`() {
        assertEquals(1.0, Detection.calculateFirewallMultiplier(59.0))
        assertEquals(1.0, Detection.calculateFirewallMultiplier(40.0))
    }

    @Test
    fun `firewall multiplier is 1_8 at 20-39 percent`() {
        assertEquals(1.8, Detection.calculateFirewallMultiplier(39.0))
        assertEquals(1.8, Detection.calculateFirewallMultiplier(20.0))
    }

    @Test
    fun `firewall multiplier is 3_0 below 20 percent`() {
        assertEquals(3.0, Detection.calculateFirewallMultiplier(19.0))
        assertEquals(3.0, Detection.calculateFirewallMultiplier(0.0))
    }

    // =============================================
    // Combined Detection Chance tests
    // =============================================

    @Test
    fun `detection chance is zero below 60 exposure`() {
        assertEquals(0.0, Detection.calculateDetectionChance(50.0, 100.0))
        assertEquals(0.0, Detection.calculateDetectionChance(60.0, 100.0))
    }

    @Test
    fun `detection chance with full firewall at 70 exposure`() {
        // Base risk at 70: 10.0, Firewall at 100%: 0.3
        val chance = Detection.calculateDetectionChance(70.0, 100.0)
        assertEquals(3.0, chance)
    }

    @Test
    fun `detection chance with damaged firewall at 70 exposure`() {
        // Base risk at 70: 10.0, Firewall at 40%: 1.0
        val chance = Detection.calculateDetectionChance(70.0, 40.0)
        assertEquals(10.0, chance)
    }

    @Test
    fun `detection chance with destroyed firewall at 90 exposure`() {
        // Base risk at 90: 90.0, Firewall at 0%: 3.0
        // 90 * 3.0 = 270, clamped to 100
        val chance = Detection.calculateDetectionChance(90.0, 0.0)
        assertEquals(100.0, chance)
    }

    @Test
    fun `detection is clamped at 100 percent`() {
        val chance = Detection.calculateDetectionChance(100.0, 0.0)
        assertEquals(100.0, chance)
    }

    @Test
    fun `detection with zero max firewall`() {
        // firewallMax = 0 → firewallPercent = 0 → multiplier = 3.0
        val chance = Detection.calculateDetectionChance(70.0, 0.0, 0.0)
        // baseRisk = 10, multiplier = 3.0, result = 30.0
        assertEquals(30.0, chance)
    }

    // =============================================
    // Roll tests
    // =============================================

    @Test
    fun `roll never detects at zero chance`() {
        // At 50% exposure, chance is 0, should never detect
        repeat(100) {
            assertFalse(Detection.rollForDetection(50.0, 100.0))
        }
    }

    @Test
    fun `roll always detects at 100 percent chance`() {
        // At 100% exposure, 0 firewall → 100% chance
        repeat(100) {
            assertTrue(Detection.rollForDetection(100.0, 0.0))
        }
    }

    // =============================================
    // Warning Message tests
    // =============================================

    @Test
    fun `no warning below 61 exposure`() {
        assertNull(Detection.getWarningMessage(0.0))
        assertNull(Detection.getWarningMessage(60.0))
    }

    @Test
    fun `caution warning at 61-69 exposure`() {
        val msg = Detection.getWarningMessage(61.0)
        assertNotNull(msg)
        assertTrue(msg.contains("CAUTION"))
    }

    @Test
    fun `warning at 70-79 exposure`() {
        val msg = Detection.getWarningMessage(70.0)
        assertNotNull(msg)
        assertTrue(msg.contains("WARNING"))
    }

    @Test
    fun `danger at 80-89 exposure`() {
        val msg = Detection.getWarningMessage(80.0)
        assertNotNull(msg)
        assertTrue(msg.contains("DANGER"))
    }

    @Test
    fun `critical at 90 plus exposure`() {
        val msg = Detection.getWarningMessage(90.0)
        assertNotNull(msg)
        assertTrue(msg.contains("CRITICAL"))
    }
}
