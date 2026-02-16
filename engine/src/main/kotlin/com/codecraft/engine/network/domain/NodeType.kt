package com.codecraft.engine.network.domain

/**
 * Categories of network nodes
 */
enum class NodeCategory {
    PUBLIC_ACCESS,
    COMMERCIAL,
    INDUSTRIAL,
    MEDICAL,
    CORPORATE,
    INFRASTRUCTURE,
    RESIDENTIAL
}

/**
 * Specific types of network nodes
 */
enum class NodeType(val category: NodeCategory, val displayName: String) {
    // PUBLIC_ACCESS
    CAFE(NodeCategory.PUBLIC_ACCESS, "Cafe"),
    COFFEE_SHOP(NodeCategory.PUBLIC_ACCESS, "Coffee Shop"),
    DINER(NodeCategory.PUBLIC_ACCESS, "Diner"),
    BAR(NodeCategory.PUBLIC_ACCESS, "Bar"),
    LIBRARY(NodeCategory.PUBLIC_ACCESS, "Library"),
    LAUNDROMAT(NodeCategory.PUBLIC_ACCESS, "Laundromat"),
    HOTEL(NodeCategory.PUBLIC_ACCESS, "Hotel"),
    HOSTEL(NodeCategory.PUBLIC_ACCESS, "Hostel"),
    COMMUNITY_CENTER(NodeCategory.PUBLIC_ACCESS, "Community Center"),
    INTERNET_CAFE(NodeCategory.PUBLIC_ACCESS, "Internet Cafe"),

    // COMMERCIAL
    PHARMACY(NodeCategory.COMMERCIAL, "Pharmacy"),
    BODEGA(NodeCategory.COMMERCIAL, "Bodega"),
    CONVENIENCE_STORE(NodeCategory.COMMERCIAL, "Convenience Store"),
    PAWN_SHOP(NodeCategory.COMMERCIAL, "Pawn Shop"),
    TECH_SHOP(NodeCategory.COMMERCIAL, "Tech Shop"),
    BOOKSTORE(NodeCategory.COMMERCIAL, "Bookstore"),
    RECORD_STORE(NodeCategory.COMMERCIAL, "Record Store"),
    ARCADE(NodeCategory.COMMERCIAL, "Arcade"),
    GYM(NodeCategory.COMMERCIAL, "Gym"),
    TATTOO_PARLOR(NodeCategory.COMMERCIAL, "Tattoo Parlor"),

    // INDUSTRIAL
    WAREHOUSE(NodeCategory.INDUSTRIAL, "Warehouse"),
    FACTORY(NodeCategory.INDUSTRIAL, "Factory"),
    DISTRIBUTION_CENTER(NodeCategory.INDUSTRIAL, "Distribution Center"),
    POWER_STATION(NodeCategory.INDUSTRIAL, "Power Station"),
    DATA_CENTER(NodeCategory.INDUSTRIAL, "Data Center"),
    SERVER_FARM(NodeCategory.INDUSTRIAL, "Server Farm"),
    TELECOM_HUB(NodeCategory.INDUSTRIAL, "Telecom Hub"),

    // MEDICAL
    CLINIC(NodeCategory.MEDICAL, "Clinic"),
    HOSPITAL(NodeCategory.MEDICAL, "Hospital"),
    MEDICAL_LAB(NodeCategory.MEDICAL, "Medical Lab"),
    URGENT_CARE(NodeCategory.MEDICAL, "Urgent Care"),
    DENTAL_OFFICE(NodeCategory.MEDICAL, "Dental Office"),

    // CORPORATE
    OFFICE_BUILDING(NodeCategory.CORPORATE, "Office Building"),
    TECH_STARTUP(NodeCategory.CORPORATE, "Tech Startup"),
    LAW_FIRM(NodeCategory.CORPORATE, "Law Firm"),
    ACCOUNTING_FIRM(NodeCategory.CORPORATE, "Accounting Firm"),
    CONSULTING_GROUP(NodeCategory.CORPORATE, "Consulting Group"),
    RESEARCH_LAB(NodeCategory.CORPORATE, "Research Lab"),

    // INFRASTRUCTURE
    TRAFFIC_CONTROL(NodeCategory.INFRASTRUCTURE, "Traffic Control"),
    SECURITY_STATION(NodeCategory.INFRASTRUCTURE, "Security Station"),
    PARKING_GARAGE(NodeCategory.INFRASTRUCTURE, "Parking Garage"),
    TRANSIT_HUB(NodeCategory.INFRASTRUCTURE, "Transit Hub"),
    MUNICIPAL_BUILDING(NodeCategory.INFRASTRUCTURE, "Municipal Building"),

    // RESIDENTIAL
    APARTMENT_COMPLEX(NodeCategory.RESIDENTIAL, "Apartment Complex"),
    CONDO_BUILDING(NodeCategory.RESIDENTIAL, "Condo Building"),
    RESIDENTIAL_NETWORK(NodeCategory.RESIDENTIAL, "Residential Network")
}
