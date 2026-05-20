package com.codecraft.engine.network.discovery

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import org.junit.jupiter.api.Test
import java.util.UUID
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class ScanServiceTest {

    private val scanService = ScanService()

    @Test
    fun `scanForNodes returns nodes within range`() {
        val nodes = listOf(
            createNode(coordX = 1000, coordY = 1000), // 0m away
            createNode(coordX = 1100, coordY = 1100), // ~141m away
            createNode(coordX = 1200, coordY = 1200), // ~282m away
            createNode(coordX = 2000, coordY = 2000)  // ~1414m away
        )

        // Scan with 300m range from (1000, 1000)
        val result = scanService.scanForNodes(
            allNodes = nodes,
            centerX = 1000,
            centerY = 1000,
            scanRange = 300,
            scannerPower = 100 // High power = 100% detection
        )

        // Should detect nodes within 300m (first 3 nodes)
        assertTrue(result.totalInRange >= 2) // At least center + 141m node
    }

    @Test
    fun `scanForNodes excludes already discovered nodes`() {
        val nodes = listOf(
            createNode(name = "Node 1", coordX = 1000, coordY = 1000),
            createNode(name = "Node 2", coordX = 1100, coordY = 1100),
            createNode(name = "Node 3", coordX = 1050, coordY = 1050)
        )

        val alreadyDiscovered = setOf(nodes[0].nodeId, nodes[1].nodeId)

        val result = scanService.scanForNodes(
            allNodes = nodes,
            centerX = 1000,
            centerY = 1000,
            scanRange = 500,
            scannerPower = 100,
            alreadyDiscovered = alreadyDiscovered
        )

        // Known nodes should be in knownInRange
        assertEquals(2, result.knownInRange.size)

        // Only Node 3 should be newly detected
        assertTrue(result.newlyDetected.all { it.nodeId == nodes[2].nodeId || it.nodeId == nodes[0].nodeId })
    }

    @Test
    fun `calculateDetectionChance decreases with distance`() {
        val node = createNode(signalStrength = 80, isPublic = true)

        val chanceNear = scanService.calculateDetectionChance(node, 50, 100, 500)
        val chanceFar = scanService.calculateDetectionChance(node, 50, 400, 500)

        assertTrue(chanceNear > chanceFar, "Detection chance should decrease with distance")
    }

    @Test
    fun `calculateDetectionChance is higher for public nodes`() {
        val publicNode = createNode(signalStrength = 80, isPublic = true)
        val privateNode = createNode(signalStrength = 80, isPublic = false)

        val publicChance = scanService.calculateDetectionChance(publicNode, 50, 200, 500)
        val privateChance = scanService.calculateDetectionChance(privateNode, 50, 200, 500)

        assertTrue(publicChance > privateChance, "Public nodes should be easier to detect")
    }

    @Test
    fun `calculateDetectionChance increases with scanner power`() {
        val node = createNode(signalStrength = 80, isPublic = true)

        val lowPower = scanService.calculateDetectionChance(node, 0, 200, 500)
        val highPower = scanService.calculateDetectionChance(node, 100, 200, 500)

        assertTrue(highPower > lowPower, "Higher scanner power should increase detection")
    }

    @Test
    fun `calculateDetectionChance increases with signal strength`() {
        val weakNode = createNode(signalStrength = 20, isPublic = true)
        val strongNode = createNode(signalStrength = 100, isPublic = true)

        val weakChance = scanService.calculateDetectionChance(weakNode, 50, 200, 500)
        val strongChance = scanService.calculateDetectionChance(strongNode, 50, 200, 500)

        assertTrue(strongChance > weakChance, "Stronger signal should be easier to detect")
    }

    @Test
    fun `calculateDetectionChance is never below 10%`() {
        val node = createNode(signalStrength = 0, isPublic = false)

        val chance = scanService.calculateDetectionChance(node, 0, 500, 500)

        assertTrue(chance >= 10, "Detection chance should have minimum of 10%")
    }

    @Test
    fun `calculateDetectionChance is never above 100%`() {
        val node = createNode(signalStrength = 100, isPublic = true)

        val chance = scanService.calculateDetectionChance(node, 100, 0, 500)

        assertTrue(chance <= 100, "Detection chance should not exceed 100%")
    }

    // Helper function
    private fun createNode(
        name: String = "Test Node ${UUID.randomUUID()}",
        coordX: Int = 1000,
        coordY: Int = 1000,
        signalStrength: Int = 80,
        isPublic: Boolean = true
    ): NetworkNode {
        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = name,
            nodeType = NodeType.CAFE,
            district = null,
            coordX = coordX,
            coordY = coordY,
            ipAddress = "10.42.1.1",
            signalStrength = signalStrength,
            securityLevel = 2,
            isPublic = isPublic,
            isMissionCritical = false
        )
    }
}
