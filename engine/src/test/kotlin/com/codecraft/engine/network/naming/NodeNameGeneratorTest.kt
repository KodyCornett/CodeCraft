package com.codecraft.engine.network.naming

import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.domain.NodeCategory
import org.junit.jupiter.api.Test
import kotlin.test.assertTrue
import kotlin.test.assertNotEquals
import kotlin.test.assertFalse
import kotlin.test.assertEquals

class NodeNameGeneratorTest {

    private val generator = NodeNameGenerator()

    @Test
    fun `generates unique names for 1000 nodes`() {
        val names = mutableSetOf<String>()
        repeat(1000) {
            val nodeType = NodeType.values().random()
            val name = generator.generateName(nodeType)
            names.add(name)
        }

        // At least 900 unique names out of 1000 (90% uniqueness)
        assertTrue(names.size >= 900, "Generated ${names.size} unique names out of 1000 (${names.size / 10.0}%)")
    }

    @Test
    fun `public access nodes have network suffixes`() {
        val publicTypes = NodeType.values().filter { it.category == NodeCategory.PUBLIC_ACCESS }
        val suffixes = WordLists.NETWORK_SUFFIXES

        repeat(20) {
            val name = generator.generateName(publicTypes.random())
            assertTrue(
                suffixes.any { suffix -> name.contains(suffix) },
                "Public access name '$name' should contain a network suffix"
            )
        }
    }

    @Test
    fun `corporate nodes do not have public suffixes`() {
        val corporateTypes = NodeType.values().filter { it.category == NodeCategory.CORPORATE }
        val publicSuffixes = listOf("WiFi", "Guest", "Public", "Open", "Hotspot")

        repeat(20) {
            val name = generator.generateName(corporateTypes.random())
            assertTrue(
                publicSuffixes.none { suffix -> name.contains(suffix) },
                "Corporate name '$name' should not contain public suffix"
            )
        }
    }

    @Test
    fun `infrastructure nodes follow location pattern`() {
        val infraTypes = NodeType.values().filter { it.category == NodeCategory.INFRASTRUCTURE }

        repeat(10) {
            val name = generator.generateName(infraTypes.random())
            // Should contain either a district name or a street name
            val hasLocation = WordLists.DISTRICTS.any { name.contains(it) } ||
                    WordLists.STREETS.any { name.contains(it) }

            assertTrue(hasLocation, "Infrastructure name '$name' should contain a location")
        }
    }

    @Test
    fun `residential nodes have residential suffix`() {
        val residentialTypes = NodeType.values().filter { it.category == NodeCategory.RESIDENTIAL }

        repeat(10) {
            val name = generator.generateName(residentialTypes.random())
            val hasResidentialSuffix = WordLists.RESIDENTIAL_SUFFIXES.any { name.contains(it) }

            assertTrue(hasResidentialSuffix, "Residential name '$name' should contain residential suffix")
        }
    }

    @Test
    fun `ensureUnique appends counter to duplicates`() {
        val baseName = "Blue Neon Cafe WiFi"
        val existing = setOf(baseName, "$baseName #2", "$baseName #3")

        val uniqueName = generator.ensureUnique(baseName, existing)

        assertNotEquals(baseName, uniqueName)
        assertTrue(uniqueName.contains("#4"), "Should append #4 to avoid duplicates")
    }

    @Test
    fun `ensureUnique returns original if no duplicate`() {
        val baseName = "Blue Neon Cafe WiFi"
        val existing = setOf("Other Name", "Different Name")

        val uniqueName = generator.ensureUnique(baseName, existing)

        assertEquals(baseName, uniqueName)
    }

    @Test
    fun `all node types generate valid names`() {
        NodeType.values().forEach { nodeType ->
            val name = generator.generateName(nodeType)

            assertFalse(name.isBlank(), "Name for $nodeType should not be blank")
            assertTrue(name.length <= 255, "Name for $nodeType should be <= 255 characters")
        }
    }

    @Test
    fun `names do not contain special characters`() {
        repeat(100) {
            val nodeType = NodeType.values().random()
            val name = generator.generateName(nodeType)

            assertFalse(name.contains("\""), "Name should not contain quotes")
            assertFalse(name.contains("\\"), "Name should not contain backslashes")
            assertFalse(name.contains("'"), "Name should not contain single quotes")
            assertFalse(name.contains(";"), "Name should not contain semicolons")
        }
    }

    @Test
    fun `generateBatch produces unique names`() {
        val types = List(50) { NodeType.CAFE }
        val names = generator.generateBatch(types)

        assertEquals(50, names.size)
        assertEquals(names.size, names.toSet().size, "All batch names should be unique")
    }

    @Test
    fun `cafe names are realistic`() {
        repeat(10) {
            val name = generator.generateName(NodeType.CAFE)
            println("Cafe: $name")
            assertTrue(name.contains("Cafe") || name.contains("Coffee"))
        }
    }

    @Test
    fun `corporate names sound secure`() {
        repeat(10) {
            val name = generator.generateName(NodeType.OFFICE_BUILDING)
            println("Corporate: $name")
            // Should contain a corporate name or be clearly corporate
            assertTrue(
                WordLists.CORPORATE_NAMES.any { name.contains(it) } ||
                name.contains("Office") ||
                name.contains("Tower") ||
                name.contains("HQ")
            )
        }
    }

    @Test
    fun `pattern variety`() {
        val names = List(100) { generator.generateName(NodeType.CAFE) }

        // Check that at least 3 different patterns are used
        val modifierPattern = names.count { name ->
            WordLists.MODIFIERS.any { name.startsWith(it) }
        }
        val streetPattern = names.count { name ->
            WordLists.STREETS.any { name.contains(it) }
        }
        val districtPattern = names.count { name ->
            WordLists.DISTRICTS.any { name.contains(it) }
        }

        assertTrue(modifierPattern > 0, "Modifier pattern should be used")
        assertTrue(streetPattern > 0, "Street pattern should be used")
        assertTrue(districtPattern > 0, "District pattern should be used")
    }
}
