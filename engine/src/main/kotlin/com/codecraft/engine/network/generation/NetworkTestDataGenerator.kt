package com.codecraft.engine.network.generation

import com.codecraft.engine.network.domain.NetworkNode
import com.codecraft.engine.network.domain.NodeType
import com.codecraft.engine.network.naming.NodeNameGenerator
import com.codecraft.engine.network.persistence.NodeRepository
import java.util.UUID
import kotlin.random.Random

/**
 * Generates test data for the network system
 */
class NetworkTestDataGenerator(
    private val nameGenerator: NodeNameGenerator,
    private val nodeRepository: NodeRepository
) {

    /**
     * Generate a test dataset of network nodes
     *
     * @param count Number of nodes to generate
     * @return List of generated nodes
     */
    fun generateTestDataset(count: Int = 100): List<NetworkNode> {
        println("[TestDataGenerator] Generating $count test nodes...")

        val nodes = mutableListOf<NetworkNode>()
        val existingNames = mutableSetOf<String>()

        repeat(count) {
            val nodeType = selectRandomNodeType()
            var nodeName = nameGenerator.generateName(nodeType)

            // Ensure uniqueness
            nodeName = nameGenerator.ensureUnique(nodeName, existingNames)
            existingNames.add(nodeName)

            val node = NetworkNode(
                nodeId = UUID.randomUUID(),
                nodeName = nodeName,
                nodeType = nodeType,
                district = null, // Districts will be added in Phase 3
                coordX = Random.nextInt(0, 5000),
                coordY = Random.nextInt(0, 5000),
                ipAddress = generateRandomIP(),
                signalStrength = Random.nextInt(60, 100),
                securityLevel = selectSecurityLevel(nodeType),
                isPublic = isPublicNode(nodeType),
                isMissionCritical = false
            )

            nodeRepository.saveNode(node)
            nodes.add(node)

            // Progress indicator
            if ((it + 1) % 25 == 0) {
                println("[TestDataGenerator] Generated ${it + 1}/$count nodes...")
            }
        }

        println("[TestDataGenerator] ✓ Generated ${nodes.size} nodes")
        println("[TestDataGenerator] ✓ Unique names: ${existingNames.size}")

        // Print statistics
        printStatistics(nodes)

        // Print sample names
        printSampleNames(nodes)

        return nodes
    }

    /**
     * Select a random node type with weighted distribution
     */
    private fun selectRandomNodeType(): NodeType {
        val weights = mapOf(
            NodeType.CAFE to 15,
            NodeType.COFFEE_SHOP to 10,
            NodeType.BAR to 8,
            NodeType.LIBRARY to 5,
            NodeType.PHARMACY to 8,
            NodeType.CONVENIENCE_STORE to 10,
            NodeType.TECH_SHOP to 5,
            NodeType.WAREHOUSE to 8,
            NodeType.FACTORY to 5,
            NodeType.DATA_CENTER to 3,
            NodeType.CLINIC to 6,
            NodeType.HOSPITAL to 4,
            NodeType.OFFICE_BUILDING to 7,
            NodeType.TECH_STARTUP to 5,
            NodeType.LAW_FIRM to 3
        )

        val totalWeight = weights.values.sum()
        var random = Random.nextInt(totalWeight)

        for ((type, weight) in weights) {
            random -= weight
            if (random < 0) {
                return type
            }
        }

        return NodeType.CAFE // Fallback
    }

    /**
     * Select security level based on node type
     */
    private fun selectSecurityLevel(nodeType: NodeType): Int {
        return when (nodeType.category.name) {
            "PUBLIC_ACCESS" -> 1
            "COMMERCIAL" -> Random.nextInt(1, 3)
            "MEDICAL" -> Random.nextInt(2, 4)
            "INDUSTRIAL" -> Random.nextInt(2, 4)
            "CORPORATE" -> Random.nextInt(3, 5)
            "INFRASTRUCTURE" -> 5
            "RESIDENTIAL" -> 2
            else -> 2
        }
    }

    /**
     * Determine if node should be public based on type
     */
    private fun isPublicNode(nodeType: NodeType): Boolean {
        return nodeType.category.name in listOf("PUBLIC_ACCESS", "COMMERCIAL")
    }

    /**
     * Generate a random IP address
     */
    private fun generateRandomIP(): String {
        val prefix = listOf("10.42", "10.48", "10.55", "10.62", "10.78").random()
        return "$prefix.${Random.nextInt(1, 255)}.${Random.nextInt(1, 255)}"
    }

    /**
     * Print statistics about generated nodes
     */
    private fun printStatistics(nodes: List<NetworkNode>) {
        println("\n[TestDataGenerator] === Statistics ===")

        // Type distribution
        val typeCount = nodes.groupingBy { it.nodeType.category }.eachCount()
        println("[TestDataGenerator] Node Types:")
        typeCount.forEach { (category, count) ->
            val percentage = (count * 100.0 / nodes.size).toInt()
            println("[TestDataGenerator]   ${category.name}: $count ($percentage%)")
        }

        // Security levels
        val securityCount = nodes.groupingBy { it.securityLevel }.eachCount()
        println("[TestDataGenerator] Security Levels:")
        securityCount.toSortedMap().forEach { (level, count) ->
            val percentage = (count * 100.0 / nodes.size).toInt()
            println("[TestDataGenerator]   Level $level: $count ($percentage%)")
        }

        // Public vs Private
        val publicCount = nodes.count { it.isPublic }
        val privateCount = nodes.size - publicCount
        println("[TestDataGenerator] Access:")
        println("[TestDataGenerator]   Public: $publicCount (${(publicCount * 100.0 / nodes.size).toInt()}%)")
        println("[TestDataGenerator]   Private: $privateCount (${(privateCount * 100.0 / nodes.size).toInt()}%)")

        // IP prefix distribution
        val ipPrefixes = nodes.map { it.ipAddress.substringBeforeLast(".").substringBeforeLast(".") }
            .groupingBy { it }
            .eachCount()
        println("[TestDataGenerator] IP Prefixes:")
        ipPrefixes.forEach { (prefix, count) ->
            println("[TestDataGenerator]   $prefix.x.x: $count")
        }
    }

    /**
     * Print sample node names
     */
    private fun printSampleNames(nodes: List<NetworkNode>) {
        println("\n[TestDataGenerator] === Sample Names ===")

        val categories = nodes.groupBy { it.nodeType.category }

        categories.forEach { (category, categoryNodes) ->
            println("[TestDataGenerator] ${category.name}:")
            categoryNodes.take(3).forEach { node ->
                println("[TestDataGenerator]   - ${node.nodeName}")
            }
        }
    }
}
