package com.codecraft.engine.network.naming

/**
 * Word lists for procedural node name generation
 */
object WordLists {

    val MODIFIERS = listOf(
        "Blue Neon", "Red Light", "Silver Screen", "Golden Gate",
        "Dark Alley", "Midnight", "Sunrise", "Sunset", "Neon", "Digital",
        "Electric", "Chrome", "Steel", "Glass", "Copper", "Iron",
        "Brass", "Bronze", "Platinum", "Diamond", "Ruby", "Emerald",
        "Crystal", "Velvet", "Silk", "Shadow", "Bright", "Lucky",
        "Happy", "Rusty", "Shiny", "Old", "New", "Fast", "Quick"
    )

    val STREETS = listOf(
        "Main St", "River Ave", "Park Blvd", "Oak Street",
        "Cyber Lane", "Data Drive", "Network Way", "Fifth Avenue",
        "Market Street", "Harbor Road", "Pine Street", "Elm Avenue",
        "Maple Drive", "Cedar Lane", "Walnut Way", "Birch Boulevard",
        "Cherry Street", "Willow Road", "Ash Lane", "Spruce Avenue",
        "First Street", "Second Avenue", "Third Boulevard", "Fourth Way",
        "Sixth Lane", "Seventh Road", "Eighth Street", "Ninth Avenue",
        "High Street", "Low Road", "North Avenue", "South Boulevard",
        "East Street", "West Lane", "Central Avenue", "Broadway"
    )

    val DISTRICTS = listOf(
        "Downtown", "Midtown", "Uptown", "Old Town", "Tech District",
        "Financial Quarter", "Industrial Zone", "Waterfront",
        "Sector 1", "Sector 2", "Sector 3", "Sector 4", "Sector 5",
        "Sector 6", "Sector 7", "Sector 8", "Sector 9",
        "North Side", "South Side", "East End", "West End",
        "Harbor District", "Medical District", "Business District"
    )

    val NETWORK_SUFFIXES = listOf(
        "WiFi", "Guest", "Public", "Open", "Network", "Hotspot",
        "Access Point", "Free WiFi", "Wireless", "Guest Network",
        "Public Access", "Open Network", "Free Access", "Guest WiFi",
        "Public WiFi", "Open WiFi", "Wireless Network", "Free Hotspot"
    )

    val CORPORATE_NAMES = listOf(
        "NovaCorp", "Zenith Pharma", "DataCorp", "TechGiant",
        "Megacorp", "CyberDyne Systems", "Apex Industries",
        "Summit Solutions", "Pinnacle Tech", "Vertex Systems",
        "Nexus Corporation", "Quantum Dynamics", "Helix Biotech",
        "Omni Industries", "Stellar Systems", "Fusion Technologies",
        "Catalyst Corp", "Synergy Solutions", "Paradigm Systems",
        "Titan Industries", "Phoenix Technologies", "Aegis Corporation"
    )

    val DEPARTMENTS = listOf(
        "Executive Network", "Research Lab Internal", "Server Room",
        "Financial Division", "Engineering Wing", "HR Department",
        "Legal Department", "Marketing Division", "Sales Floor",
        "Operations Center", "Security Terminal", "Data Archives",
        "Development Lab", "Testing Facility", "Main Server",
        "Backup Server", "Employee Network", "Visitor Terminal",
        "Admin Console", "Database Server", "File Server"
    )

    val BUILDING_NUMBERS = listOf(
        "Tower", "Building A", "Building B", "Building C",
        "North Wing", "South Wing", "East Wing", "West Wing",
        "HQ", "Headquarters", "Main Office", "Branch Office",
        "Campus", "Complex", "Center", "Plaza", "Suite 100",
        "Suite 200", "Floor 5", "Floor 10", "Floor 15"
    )

    val RESIDENTIAL_NAMES = listOf(
        "Sunset Apartments", "Riverside Condos", "Harbor View",
        "Parkside Towers", "Lakeside Residences", "Hillside Manor",
        "Meadow View", "Valley Gardens", "Skyline Apartments",
        "Downtown Lofts", "Central Plaza", "Grand Towers",
        "Royal Apartments", "Imperial Condos", "Majestic Heights",
        "Ocean Breeze", "Mountain View", "Forest Glen",
        "Spring Gardens", "Autumn Estates", "Summer Heights",
        "Winter Park", "Crystal Towers", "Diamond Plaza"
    )

    val RESIDENTIAL_SUFFIXES = listOf(
        "Resident WiFi", "Private Network", "Home Network",
        "Apartment WiFi", "Condo Network", "Building Network",
        "Resident Access", "Private WiFi", "Secured Network"
    )

    val INFRASTRUCTURE_TYPES = listOf(
        "Control System", "Monitoring Station", "Operations Center",
        "Communications Hub", "Network Node", "Access Point",
        "Terminal", "Gateway", "Router", "Switch", "Server"
    )

    /**
     * Get a random modifier
     */
    fun randomModifier() = MODIFIERS.random()

    /**
     * Get a random street name
     */
    fun randomStreet() = STREETS.random()

    /**
     * Get a random district name
     */
    fun randomDistrict() = DISTRICTS.random()

    /**
     * Get a random network suffix
     */
    fun randomSuffix() = NETWORK_SUFFIXES.random()

    /**
     * Get a random corporate name
     */
    fun randomCorporateName() = CORPORATE_NAMES.random()

    /**
     * Get a random department
     */
    fun randomDepartment() = DEPARTMENTS.random()

    /**
     * Get a random building number/name
     */
    fun randomBuilding() = BUILDING_NUMBERS.random()

    /**
     * Get a random residential name
     */
    fun randomResidentialName() = RESIDENTIAL_NAMES.random()

    /**
     * Get a random residential suffix
     */
    fun randomResidentialSuffix() = RESIDENTIAL_SUFFIXES.random()

    /**
     * Get a random infrastructure type
     */
    fun randomInfrastructureType() = INFRASTRUCTURE_TYPES.random()
}
