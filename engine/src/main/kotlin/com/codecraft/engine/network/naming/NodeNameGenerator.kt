package com.codecraft.engine.network.naming

import com.codecraft.engine.network.domain.District
import com.codecraft.engine.network.domain.NodeCategory
import com.codecraft.engine.network.domain.NodeType
import kotlin.random.Random

/**
 * Generates procedural, realistic names for network nodes
 */
class NodeNameGenerator {

    /**
     * Generate a procedural name for a network node
     *
     * @param nodeType The type of business/location
     * @param district Optional district for location-based names
     * @return A unique, realistic node name
     */
    fun generateName(
        nodeType: NodeType,
        district: District? = null
    ): String {
        return when (nodeType.category) {
            NodeCategory.PUBLIC_ACCESS -> generatePublicAccessName(nodeType, district)
            NodeCategory.COMMERCIAL -> generateCommercialName(nodeType, district)
            NodeCategory.CORPORATE -> generateCorporateName(nodeType)
            NodeCategory.INFRASTRUCTURE -> generateInfrastructureName(nodeType, district)
            NodeCategory.RESIDENTIAL -> generateResidentialName(district)
            NodeCategory.INDUSTRIAL -> generateIndustrialName(nodeType, district)
            NodeCategory.MEDICAL -> generateMedicalName(nodeType, district)
        }
    }

    /**
     * Generate name for public access nodes (cafes, libraries, etc.)
     */
    private fun generatePublicAccessName(type: NodeType, district: District?): String {
        val patterns = listOf(
            { "${WordLists.randomModifier()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${WordLists.randomStreet()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${district?.name ?: WordLists.randomDistrict()} ${type.displayName} ${WordLists.randomSuffix()}" }
        )
        return patterns.random()()
    }

    /**
     * Generate name for commercial nodes
     */
    private fun generateCommercialName(type: NodeType, district: District?): String {
        val patterns = listOf(
            { "${WordLists.randomModifier()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${WordLists.randomStreet()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${district?.name ?: WordLists.randomDistrict()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${type.displayName} ${WordLists.randomSuffix()}" } // Simple pattern
        )
        return patterns.random()()
    }

    /**
     * Generate name for corporate nodes (no public suffixes)
     */
    private fun generateCorporateName(type: NodeType): String {
        val company = WordLists.randomCorporateName()
        val building = WordLists.randomBuilding()
        val department = WordLists.randomDepartment()

        val patterns = listOf(
            { "$company $building" },
            { "$company ${type.displayName}" },
            { "$company $building $department" },
            { "$company $department" }
        )
        return patterns.random()()
    }

    /**
     * Generate name for infrastructure nodes
     */
    private fun generateInfrastructureName(type: NodeType, district: District?): String {
        val location = district?.name ?: WordLists.randomDistrict()
        val infrastructureType = WordLists.randomInfrastructureType()

        val patterns = listOf(
            { "$location ${type.displayName} $infrastructureType" },
            { "$location ${type.displayName}" }
        )
        return patterns.random()()
    }

    /**
     * Generate name for residential nodes
     */
    private fun generateResidentialName(district: District?): String {
        val residentialName = WordLists.randomResidentialName()
        val suffix = WordLists.randomResidentialSuffix()

        val patterns = listOf(
            { "$residentialName $suffix" },
            { "${district?.name ?: WordLists.randomDistrict()} $residentialName $suffix" }
        )
        return patterns.random()()
    }

    /**
     * Generate name for industrial nodes
     */
    private fun generateIndustrialName(type: NodeType, district: District?): String {
        val patterns = listOf(
            { "${WordLists.randomModifier()} ${type.displayName}" },
            { "${district?.name ?: WordLists.randomDistrict()} ${type.displayName}" },
            { "${WordLists.randomStreet()} ${type.displayName}" },
            { "${WordLists.randomCorporateName()} ${type.displayName}" }
        )
        return patterns.random()()
    }

    /**
     * Generate name for medical nodes
     */
    private fun generateMedicalName(type: NodeType, district: District?): String {
        val patterns = listOf(
            { "${WordLists.randomModifier()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${WordLists.randomStreet()} ${type.displayName} ${WordLists.randomSuffix()}" },
            { "${district?.name ?: WordLists.randomDistrict()} ${type.displayName}" },
            { "${type.displayName} ${WordLists.randomSuffix()}" }
        )
        return patterns.random()()
    }

    /**
     * Ensure generated name is unique in the given set
     * Appends a counter if duplicate found
     */
    fun ensureUnique(name: String, existingNames: Set<String>): String {
        var uniqueName = name
        var counter = 2
        while (existingNames.contains(uniqueName)) {
            uniqueName = "$name #$counter"
            counter++
        }
        return uniqueName
    }

    /**
     * Generate a batch of unique names
     */
    fun generateBatch(
        types: List<NodeType>,
        district: District? = null
    ): List<String> {
        val names = mutableSetOf<String>()
        types.forEach { type ->
            var name = generateName(type, district)
            name = ensureUnique(name, names)
            names.add(name)
        }
        return names.toList()
    }
}
