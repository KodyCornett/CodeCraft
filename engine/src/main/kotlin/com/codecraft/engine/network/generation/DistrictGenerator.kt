package com.codecraft.engine.network.generation

import com.codecraft.engine.network.domain.*
import com.codecraft.engine.network.naming.NodeNameGenerator
import java.util.UUID
import kotlin.math.*
import kotlin.random.Random

/**
 * Generates network nodes within districts with realistic clustering
 */
class DistrictGenerator(
    private val nameGenerator: NodeNameGenerator
) {

    /**
     * Generate all nodes for a district
     */
    fun generateDistrict(district: District): List<NetworkNode> {
        val nodes = mutableListOf<NetworkNode>()
        val existingNames = mutableSetOf<String>()

        // Generate public access nodes (gateways)
        val gatewayCount = when (district.density) {
            NodeDensity.HIGH -> Random.nextInt(8, 15)
            NodeDensity.MEDIUM -> Random.nextInt(5, 10)
            NodeDensity.LOW -> Random.nextInt(3, 6)
        }

        repeat(gatewayCount) {
            val node = generatePublicNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        // Generate private/commercial nodes
        val commercialCount = when (district.density) {
            NodeDensity.HIGH -> Random.nextInt(5, 10)
            NodeDensity.MEDIUM -> Random.nextInt(3, 7)
            NodeDensity.LOW -> Random.nextInt(2, 5)
        }

        repeat(commercialCount) {
            val node = generateCommercialNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        // Generate secure/corporate nodes (targets)
        val targetCount = when (district.type) {
            DistrictType.TECH_HUB -> Random.nextInt(3, 6)
            DistrictType.INDUSTRIAL -> Random.nextInt(2, 5)
            DistrictType.FINANCIAL -> Random.nextInt(4, 7)
            DistrictType.MEDICAL -> Random.nextInt(2, 4)
            DistrictType.RESIDENTIAL -> Random.nextInt(1, 3)
            DistrictType.COMMERCIAL -> Random.nextInt(2, 5)
            DistrictType.GOVERNMENT -> Random.nextInt(3, 6)
        }

        repeat(targetCount) {
            val node = generateCorporateNode(district, existingNames)
            nodes.add(node)
            existingNames.add(node.nodeName)
        }

        println("[DistrictGenerator] Generated ${nodes.size} nodes for ${district.name}")
        return nodes
    }

    /**
     * Generate a public access node (gateway)
     */
    private fun generatePublicNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = selectPublicNodeType(district)
        val position = randomPositionInDistrict(district, centralBias = 0.8)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(70, 100),
            securityLevel = 1,
            isPublic = true,
            isMissionCritical = false
        )
    }

    /**
     * Generate a commercial/private node
     */
    private fun generateCommercialNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = selectCommercialNodeType(district)
        val position = randomPositionInDistrict(district, centralBias = 0.6)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(50, 80),
            securityLevel = Random.nextInt(1, 3),
            isPublic = false,
            isMissionCritical = false
        )
    }

    /**
     * Generate a corporate/secure node (target)
     */
    private fun generateCorporateNode(
        district: District,
        existingNames: Set<String>
    ): NetworkNode {
        val nodeType = selectCorporateNodeType(district)
        val position = randomPositionInDistrict(district, centralBias = 0.4)
        val nodeName = nameGenerator.generateName(nodeType, district)
            .let { nameGenerator.ensureUnique(it, existingNames) }

        return NetworkNode(
            nodeId = UUID.randomUUID(),
            nodeName = nodeName,
            nodeType = nodeType,
            district = district,
            coordX = position.first,
            coordY = position.second,
            ipAddress = generateDistrictIP(district),
            signalStrength = Random.nextInt(30, 60),
            securityLevel = Random.nextInt(3, 5),
            isPublic = false,
            isMissionCritical = false
        )
    }

    /**
     * Generate random position within district using polar coordinates for natural clustering
     */
    private fun randomPositionInDistrict(
        district: District,
        centralBias: Double
    ): Pair<Int, Int> {
        // Use polar coordinates for more natural clustering
        val angle = Random.nextDouble(0.0, 2 * PI)

        // Apply bias towards center (closer to 1.0 = more central)
        val maxRadius = district.radius * centralBias
        val distance = Random.nextDouble(0.0, maxRadius)

        val offsetX = (distance * cos(angle)).toInt()
        val offsetY = (distance * sin(angle)).toInt()

        return Pair(
            district.centerX + offsetX,
            district.centerY + offsetY
        )
    }

    /**
     * Generate IP address within district's subnet
     */
    private fun generateDistrictIP(district: District): String {
        val prefix = district.ipPrefix
        val third = Random.nextInt(1, 255)
        val fourth = Random.nextInt(1, 255)
        return "$prefix.$third.$fourth"
    }

    /**
     * Select appropriate public node type for district
     */
    private fun selectPublicNodeType(district: District): NodeType {
        return when (district.type) {
            DistrictType.TECH_HUB -> listOf(
                NodeType.CAFE, NodeType.COFFEE_SHOP, NodeType.LIBRARY
            ).random()
            DistrictType.INDUSTRIAL -> listOf(
                NodeType.DINER, NodeType.LAUNDROMAT
            ).random()
            DistrictType.MEDICAL -> listOf(
                NodeType.CAFE, NodeType.PHARMACY
            ).random()
            DistrictType.RESIDENTIAL -> listOf(
                NodeType.CAFE, NodeType.CONVENIENCE_STORE
            ).random()
            DistrictType.FINANCIAL -> listOf(
                NodeType.CAFE, NodeType.HOTEL
            ).random()
            DistrictType.COMMERCIAL -> listOf(
                NodeType.CAFE, NodeType.BAR
            ).random()
            DistrictType.GOVERNMENT -> listOf(
                NodeType.LIBRARY, NodeType.CAFE
            ).random()
        }
    }

    /**
     * Select appropriate commercial node type for district
     */
    private fun selectCommercialNodeType(district: District): NodeType {
        return when (district.type) {
            DistrictType.TECH_HUB -> listOf(
                NodeType.TECH_SHOP, NodeType.BOOKSTORE, NodeType.ARCADE
            ).random()
            DistrictType.INDUSTRIAL -> listOf(
                NodeType.WAREHOUSE, NodeType.FACTORY
            ).random()
            DistrictType.MEDICAL -> listOf(
                NodeType.CLINIC, NodeType.PHARMACY
            ).random()
            DistrictType.RESIDENTIAL -> listOf(
                NodeType.CONVENIENCE_STORE, NodeType.LAUNDROMAT, NodeType.GYM
            ).random()
            DistrictType.FINANCIAL -> listOf(
                NodeType.OFFICE_BUILDING, NodeType.HOTEL
            ).random()
            DistrictType.COMMERCIAL -> listOf(
                NodeType.BODEGA, NodeType.CONVENIENCE_STORE, NodeType.HOTEL
            ).random()
            DistrictType.GOVERNMENT -> listOf(
                NodeType.OFFICE_BUILDING, NodeType.LIBRARY
            ).random()
        }
    }

    /**
     * Select appropriate corporate node type for district
     */
    private fun selectCorporateNodeType(district: District): NodeType {
        return when (district.type) {
            DistrictType.TECH_HUB -> NodeType.OFFICE_BUILDING
            DistrictType.INDUSTRIAL -> NodeType.DATA_CENTER
            DistrictType.MEDICAL -> NodeType.HOSPITAL
            DistrictType.RESIDENTIAL -> NodeType.APARTMENT_COMPLEX
            DistrictType.FINANCIAL -> NodeType.OFFICE_BUILDING
            DistrictType.COMMERCIAL -> NodeType.OFFICE_BUILDING
            DistrictType.GOVERNMENT -> NodeType.MUNICIPAL_BUILDING
        }
    }
}
