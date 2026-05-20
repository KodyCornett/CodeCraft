package com.codecraft.engine.network.generation

import com.codecraft.engine.network.domain.*
import com.codecraft.engine.network.naming.NodeNameGenerator
import com.codecraft.engine.network.naming.WordLists
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.*

class DistrictGeneratorTest {

    private lateinit var generator: DistrictGenerator
    private lateinit var nameGenerator: NodeNameGenerator

    @BeforeEach
    fun setup() {
        nameGenerator = NodeNameGenerator()
        generator = DistrictGenerator(nameGenerator)
    }

    @Test
    fun `generates nodes for district`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)

        assertTrue(nodes.isNotEmpty(), "Should generate nodes for district")
    }

    @Test
    fun `generated nodes are within district bounds`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)

        nodes.forEach { node ->
            val dx = node.coordX - district.centerX
            val dy = node.coordY - district.centerY
            val distance = kotlin.math.sqrt((dx * dx + dy * dy).toDouble()).toInt()

            assertTrue(
                distance <= district.radius,
                "Node ${node.nodeName} at distance $distance should be within radius ${district.radius}"
            )
        }
    }

    @Test
    fun `generated nodes use district IP prefix`() {
        val district = Districts.DOWNTOWN // IP prefix: 10.42

        val nodes = generator.generateDistrict(district)

        nodes.forEach { node ->
            assertTrue(
                node.ipAddress.startsWith(district.ipPrefix),
                "Node ${node.nodeName} IP ${node.ipAddress} should start with ${district.ipPrefix}"
            )
        }
    }

    @Test
    fun `generated nodes have district assigned`() {
        val district = Districts.MEDICAL_DISTRICT

        val nodes = generator.generateDistrict(district)

        nodes.forEach { node ->
            assertEquals(district, node.district, "Node ${node.nodeName} should have district assigned")
        }
    }

    @Test
    fun `high density districts generate more nodes`() {
        val highDensityDistrict = Districts.DOWNTOWN.copy(density = NodeDensity.HIGH)
        val lowDensityDistrict = Districts.INDUSTRIAL_ZONE.copy(density = NodeDensity.LOW)

        val highNodes = generator.generateDistrict(highDensityDistrict)
        val lowNodes = generator.generateDistrict(lowDensityDistrict)

        assertTrue(
            highNodes.size > lowNodes.size,
            "High density (${highNodes.size}) should generate more nodes than low density (${lowNodes.size})"
        )
    }

    @Test
    fun `generates public gateway nodes`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)
        val publicNodes = nodes.filter { it.isPublic }

        assertTrue(publicNodes.isNotEmpty(), "Should generate public gateway nodes")
        publicNodes.forEach { node ->
            assertTrue(node.isPublic, "Public nodes should have isPublic=true")
            assertEquals(1, node.securityLevel, "Public nodes should have low security")
            assertTrue(node.signalStrength >= 70, "Public nodes should have strong signal")
        }
    }

    @Test
    fun `generates private commercial nodes`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)
        val privateNodes = nodes.filter { !it.isPublic && it.securityLevel < 3 }

        assertTrue(privateNodes.isNotEmpty(), "Should generate private commercial nodes")
    }

    @Test
    fun `generates secure corporate nodes`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)
        val corporateNodes = nodes.filter { it.securityLevel >= 3 }

        assertTrue(corporateNodes.isNotEmpty(), "Should generate secure corporate nodes")
        corporateNodes.forEach { node ->
            assertFalse(node.isPublic, "Corporate nodes should not be public")
            assertTrue(node.securityLevel >= 3, "Corporate nodes should have high security")
        }
    }

    @Test
    fun `node types appropriate for district type`() {
        val techDistrict = Districts.DOWNTOWN // TECH_HUB
        val medicalDistrict = Districts.MEDICAL_DISTRICT

        val techNodes = generator.generateDistrict(techDistrict)
        val medicalNodes = generator.generateDistrict(medicalDistrict)

        // Tech hub should have tech-related nodes
        val techPublic = techNodes.filter { it.isPublic }
        assertTrue(
            techPublic.any { it.nodeType == NodeType.CAFE || it.nodeType == NodeType.LIBRARY },
            "Tech district should have cafes or libraries"
        )

        // Medical district should have medical-related nodes
        val medicalCorporate = medicalNodes.filter { it.securityLevel >= 3 }
        assertTrue(
            medicalCorporate.any { it.nodeType == NodeType.HOSPITAL },
            "Medical district should have hospitals"
        )
    }

    @Test
    fun `all node names are unique within district`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)
        val names = nodes.map { it.nodeName }
        val uniqueNames = names.toSet()

        assertEquals(
            uniqueNames.size,
            names.size,
            "All node names should be unique (found ${names.size - uniqueNames.size} duplicates)"
        )
    }

    @Test
    fun `IP addresses are unique within district`() {
        val district = Districts.DOWNTOWN

        val nodes = generator.generateDistrict(district)
        val ips = nodes.map { it.ipAddress }
        val uniqueIps = ips.toSet()

        // Allow some IP collisions since we have limited IP space, but not too many
        val collisionRate = (ips.size - uniqueIps.size).toDouble() / ips.size
        assertTrue(
            collisionRate < 0.1,
            "IP collision rate should be under 10% (was ${collisionRate * 100}%)"
        )
    }

    @Test
    fun `districts object contains all predefined districts`() {
        val allDistricts = Districts.getAll()

        assertTrue(allDistricts.contains(Districts.DOWNTOWN))
        assertTrue(allDistricts.contains(Districts.INDUSTRIAL_ZONE))
        assertTrue(allDistricts.contains(Districts.MEDICAL_DISTRICT))
        assertTrue(allDistricts.contains(Districts.RESIDENTIAL))
        assertTrue(allDistricts.contains(Districts.FINANCIAL_QUARTER))
        assertTrue(allDistricts.contains(Districts.UNIVERSITY_CAMPUS))
    }

    @Test
    fun `getStarter returns unlocked districts only`() {
        val starterDistricts = Districts.getStarter()

        starterDistricts.forEach { district ->
            assertNull(district.unlockCondition, "Starter districts should have no unlock condition")
        }

        assertTrue(starterDistricts.contains(Districts.DOWNTOWN))
        assertTrue(starterDistricts.contains(Districts.UNIVERSITY_CAMPUS))
        assertFalse(starterDistricts.contains(Districts.INDUSTRIAL_ZONE)) // Requires mission
    }

    @Test
    fun `getUnlocked filters by mission completion`() {
        val completedMissions = setOf("mission_2_complete", "mission_3_complete")
        val scannerLevel = 1

        val unlockedDistricts = Districts.getUnlocked(completedMissions, scannerLevel)

        // Should include starter districts
        assertTrue(unlockedDistricts.contains(Districts.DOWNTOWN))

        // Should include mission-unlocked districts
        assertTrue(unlockedDistricts.contains(Districts.INDUSTRIAL_ZONE)) // mission_2_complete
        assertTrue(unlockedDistricts.contains(Districts.MEDICAL_DISTRICT)) // mission_3_complete

        // Should NOT include scanner-locked district
        assertFalse(unlockedDistricts.contains(Districts.RESIDENTIAL)) // Requires scanner level 2
    }

    @Test
    fun `getUnlocked filters by scanner level`() {
        val completedMissions = setOf<String>()
        val scannerLevel = 2

        val unlockedDistricts = Districts.getUnlocked(completedMissions, scannerLevel)

        // Should include scanner-unlocked district
        assertTrue(unlockedDistricts.contains(Districts.RESIDENTIAL)) // scanner_upgrade_2
    }
}
