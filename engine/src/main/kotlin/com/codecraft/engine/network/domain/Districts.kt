package com.codecraft.engine.network.domain

import java.util.UUID

/**
 * Predefined district definitions for the urban network
 */
object Districts {

    val DOWNTOWN = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000001"),
        name = "Downtown",
        type = DistrictType.TECH_HUB,
        centerX = 1000,
        centerY = 1000,
        radius = 500,
        ipPrefix = "10.42",
        density = NodeDensity.HIGH,
        description = "The heart of the city's tech scene. Cafes, startups, and corporate offices.",
        unlockCondition = null // Available from start
    )

    val INDUSTRIAL_ZONE = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000002"),
        name = "Industrial Zone",
        type = DistrictType.INDUSTRIAL,
        centerX = 3000,
        centerY = 500,
        radius = 800,
        ipPrefix = "10.48",
        density = NodeDensity.LOW,
        description = "Warehouses, factories, and data centers. High-value targets.",
        unlockCondition = "mission_2_complete"
    )

    val MEDICAL_DISTRICT = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000003"),
        name = "Medical District",
        type = DistrictType.MEDICAL,
        centerX = 500,
        centerY = 1500,
        radius = 400,
        ipPrefix = "10.62",
        density = NodeDensity.MEDIUM,
        description = "Hospitals, clinics, and pharmacies. Sensitive patient data.",
        unlockCondition = "mission_3_complete"
    )

    val RESIDENTIAL = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000004"),
        name = "Residential",
        type = DistrictType.RESIDENTIAL,
        centerX = 2000,
        centerY = 2500,
        radius = 600,
        ipPrefix = "10.55",
        density = NodeDensity.MEDIUM,
        description = "Apartment complexes and residential networks.",
        unlockCondition = "scanner_upgrade_2"
    )

    val FINANCIAL_QUARTER = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000005"),
        name = "Financial Quarter",
        type = DistrictType.FINANCIAL,
        centerX = 1500,
        centerY = 500,
        radius = 350,
        ipPrefix = "10.78",
        density = NodeDensity.MEDIUM,
        description = "Banks, investment firms, high security.",
        unlockCondition = "mission_5_complete"
    )

    val UNIVERSITY_CAMPUS = District(
        districtId = UUID.fromString("00000000-0000-0000-0000-000000000006"),
        name = "University Campus",
        type = DistrictType.TECH_HUB,
        centerX = 500,
        centerY = 500,
        radius = 450,
        ipPrefix = "10.33",
        density = NodeDensity.HIGH,
        description = "Research labs, student networks, and academic systems.",
        unlockCondition = null // Available from start
    )

    /**
     * Get all defined districts
     */
    fun getAll(): List<District> = listOf(
        DOWNTOWN,
        UNIVERSITY_CAMPUS,
        INDUSTRIAL_ZONE,
        MEDICAL_DISTRICT,
        RESIDENTIAL,
        FINANCIAL_QUARTER
    )

    /**
     * Get unlocked districts based on player progress
     */
    fun getUnlocked(completedMissions: Set<String>, scannerLevel: Int): List<District> {
        return getAll().filter { district ->
            when (district.unlockCondition) {
                null -> true // No condition, always unlocked
                "scanner_upgrade_2" -> scannerLevel >= 2
                else -> completedMissions.contains(district.unlockCondition)
            }
        }
    }

    /**
     * Get starter districts (no unlock conditions)
     */
    fun getStarter(): List<District> {
        return getAll().filter { it.unlockCondition == null }
    }
}
