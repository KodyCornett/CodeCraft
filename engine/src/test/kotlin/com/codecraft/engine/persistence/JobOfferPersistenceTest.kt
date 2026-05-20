package com.codecraft.engine.persistence

import com.codecraft.engine.domain.*
import org.jetbrains.exposed.sql.SqlExpressionBuilder.eq
import org.jetbrains.exposed.sql.transactions.transaction
import org.jetbrains.exposed.sql.update
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class JobOfferPersistenceTest {

    private lateinit var repository: PlayerRepository
    private lateinit var testOffer: JobOffer

    @BeforeEach
    fun setup() {
        // Use unique database for each test
        val dbName = "test_job_offers_${System.nanoTime()}"
        GameDatabase.initWithUrl("jdbc:h2:mem:$dbName;DB_CLOSE_DELAY=-1", "org.h2.Driver")
        repository = PlayerRepository()

        // Create test player records (required for foreign key)
        val testSession1 = com.codecraft.engine.session.GameSession("test-player")
        testSession1.player.credits = 1000
        repository.savePlayer(testSession1)

        val testSession2 = com.codecraft.engine.session.GameSession("player-1")
        testSession2.player.credits = 1000
        repository.savePlayer(testSession2)

        val testSession3 = com.codecraft.engine.session.GameSession("player-2")
        testSession3.player.credits = 1000
        repository.savePlayer(testSession3)

        // Create a test offer
        val mission = MissionCatalog.getById("mission_1")!!
        val contact = ContactCatalog.getById("ghost")!!

        testOffer = JobOffer(
            mission = mission,
            contact = contact,
            baseOfferPercentage = 50,
            currentOfferPercentage = 55,
            maxOfferPercentage = 75,
            negotiationAttempts = 1,
            maxAttempts = 3,
            rejected = false
        )
    }

    @Test
    fun `saveJobOffer persists offer to database`() {
        // When: Save a job offer
        repository.saveJobOffer("test-player", testOffer)

        // Then: Offer can be loaded
        val loaded = repository.loadJobOffer("test-player", "mission_1")
        assertNotNull(loaded)
        assertEquals("mission_1", loaded.mission.id)
        assertEquals("ghost", loaded.contact.id)
        assertEquals(50, loaded.baseOfferPercentage)
        assertEquals(55, loaded.currentOfferPercentage)
        assertEquals(75, loaded.maxOfferPercentage)
        assertEquals(1, loaded.negotiationAttempts)
        assertEquals(3, loaded.maxAttempts)
        assertEquals(false, loaded.rejected)
    }

    @Test
    fun `saveJobOffer updates existing offer`() {
        // Given: An existing offer
        repository.saveJobOffer("test-player", testOffer)

        // When: Update the offer
        val updatedOffer = testOffer.copy(
            currentOfferPercentage = 60,
            negotiationAttempts = 2
        )
        repository.saveJobOffer("test-player", updatedOffer)

        // Then: Offer is updated
        val loaded = repository.loadJobOffer("test-player", "mission_1")
        assertNotNull(loaded)
        assertEquals(60, loaded.currentOfferPercentage)
        assertEquals(2, loaded.negotiationAttempts)
        // Other fields unchanged
        assertEquals(50, loaded.baseOfferPercentage)
        assertEquals(75, loaded.maxOfferPercentage)
    }

    @Test
    fun `loadJobOffer returns null for non-existent offer`() {
        // When: Try to load non-existent offer
        val loaded = repository.loadJobOffer("test-player", "non-existent-mission")

        // Then: Returns null
        assertNull(loaded)
    }

    @Test
    fun `loadAllJobOffers returns all offers for player`() {
        // Given: Multiple offers for the same player
        repository.saveJobOffer("test-player", testOffer)

        val mission2 = MissionCatalog.getById("mission_2")!!
        val contact2 = ContactCatalog.getById("cipher")!!
        val offer2 = JobOffer(
            mission = mission2,
            contact = contact2,
            baseOfferPercentage = 45,
            currentOfferPercentage = 50,
            maxOfferPercentage = 70,
            negotiationAttempts = 0,
            maxAttempts = 3,
            rejected = false
        )
        repository.saveJobOffer("test-player", offer2)

        // When: Load all offers
        val allOffers = repository.loadAllJobOffers("test-player")

        // Then: Both offers returned
        assertEquals(2, allOffers.size)
        assertTrue(allOffers.any { it.mission.id == "mission_1" })
        assertTrue(allOffers.any { it.mission.id == "mission_2" })
    }

    @Test
    fun `loadAllJobOffers returns empty list for player with no offers`() {
        // When: Load offers for player with none
        val allOffers = repository.loadAllJobOffers("player-with-no-offers")

        // Then: Returns empty list
        assertEquals(0, allOffers.size)
    }

    @Test
    fun `deleteJobOffer removes offer from database`() {
        // Given: A saved offer
        repository.saveJobOffer("test-player", testOffer)

        // When: Delete the offer
        repository.deleteJobOffer("test-player", "mission_1")

        // Then: Offer can no longer be loaded
        val loaded = repository.loadJobOffer("test-player", "mission_1")
        assertNull(loaded)
    }

    @Test
    fun `job offers expire after 7 days`() {
        // Given: Save a job offer
        repository.saveJobOffer("test-player", testOffer)

        // Verify it exists
        var loaded = repository.loadJobOffer("test-player", "mission_1")
        assertNotNull(loaded)

        // When: Manually set expiration to the past (simulate time passage)
        // Note: This is a simplified test - in production, we'd need to manipulate time
        // For now, we'll just test the cleanup method

        // Manually expire the offer by setting expiresAt to past
        org.jetbrains.exposed.sql.transactions.transaction {
            JobOffersTable.update(
                { JobOffersTable.id eq "test-player_mission_1" }
            ) {
                it[expiresAt] = System.currentTimeMillis() - 1000 // 1 second ago
            }
        }

        // Then: Loading the offer returns null (expired)
        loaded = repository.loadJobOffer("test-player", "mission_1")
        assertNull(loaded)
    }

    @Test
    fun `cleanupExpiredOffers removes expired offers`() {
        // Given: Save multiple offers
        repository.saveJobOffer("test-player", testOffer)

        val mission2 = MissionCatalog.getById("mission_2")!!
        val contact2 = ContactCatalog.getById("cipher")!!
        val offer2 = JobOffer(
            mission = mission2,
            contact = contact2,
            baseOfferPercentage = 45,
            currentOfferPercentage = 50,
            maxOfferPercentage = 70,
            negotiationAttempts = 0,
            maxAttempts = 3,
            rejected = false
        )
        repository.saveJobOffer("test-player", offer2)

        // Expire only the first offer
        org.jetbrains.exposed.sql.transactions.transaction {
            JobOffersTable.update(
                { JobOffersTable.id eq "test-player_mission_1" }
            ) {
                it[expiresAt] = System.currentTimeMillis() - 1000
            }
        }

        // When: Run cleanup
        repository.cleanupExpiredOffers()

        // Then: Only non-expired offer remains
        val allOffers = repository.loadAllJobOffers("test-player")
        assertEquals(1, allOffers.size)
        assertEquals("mission_2", allOffers[0].mission.id)
    }

    @Test
    fun `loadAllJobOffers filters out expired offers automatically`() {
        // Given: Save multiple offers
        repository.saveJobOffer("test-player", testOffer)

        val mission2 = MissionCatalog.getById("mission_2")!!
        val contact2 = ContactCatalog.getById("cipher")!!
        val offer2 = JobOffer(
            mission = mission2,
            contact = contact2,
            baseOfferPercentage = 45,
            currentOfferPercentage = 50,
            maxOfferPercentage = 70,
            negotiationAttempts = 0,
            maxAttempts = 3,
            rejected = false
        )
        repository.saveJobOffer("test-player", offer2)

        // Expire the first offer
        org.jetbrains.exposed.sql.transactions.transaction {
            JobOffersTable.update(
                { JobOffersTable.id eq "test-player_mission_1" }
            ) {
                it[expiresAt] = System.currentTimeMillis() - 1000
            }
        }

        // When: Load all offers (should filter expired automatically)
        val allOffers = repository.loadAllJobOffers("test-player")

        // Then: Only non-expired offer returned
        assertEquals(1, allOffers.size)
        assertEquals("mission_2", allOffers[0].mission.id)
    }

    @Test
    fun `offers from different players are isolated`() {
        // Given: Same mission offered to different players
        repository.saveJobOffer("player-1", testOffer)
        repository.saveJobOffer("player-2", testOffer)

        // When: Each player negotiates independently
        val offer1 = repository.loadJobOffer("player-1", "mission_1")!!
        val updatedOffer1 = offer1.copy(currentOfferPercentage = 60)
        repository.saveJobOffer("player-1", updatedOffer1)

        // Then: Only player-1's offer is updated
        val player1Offer = repository.loadJobOffer("player-1", "mission_1")
        val player2Offer = repository.loadJobOffer("player-2", "mission_1")

        assertEquals(60, player1Offer?.currentOfferPercentage)
        assertEquals(55, player2Offer?.currentOfferPercentage) // Unchanged
    }
}
