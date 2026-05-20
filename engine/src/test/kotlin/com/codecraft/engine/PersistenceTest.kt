package com.codecraft.engine

import com.codecraft.engine.domain.*
import com.codecraft.engine.persistence.GameDatabase
import com.codecraft.engine.persistence.PlayerRepository
import com.codecraft.engine.session.ConnectionTrace
import com.codecraft.engine.session.DownloadRecord
import com.codecraft.engine.session.GameSession
import kotlin.test.BeforeTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue

class PersistenceTest {

    private lateinit var repo: PlayerRepository

    @BeforeTest
    fun setup() {
        // Use H2 in-memory database for each test
        GameDatabase.initWithUrl(
            "jdbc:h2:mem:test_${System.nanoTime()};DB_CLOSE_DELAY=-1",
            "org.h2.Driver"
        )
        repo = PlayerRepository()
    }

    @Test
    fun `playerExists returns false for non-existent player`() {
        assertFalse(repo.playerExists("nonexistent"))
    }

    @Test
    fun `save and load roundtrip preserves basic state`() {
        val session = GameSession("test-player")
        session.player.credits = 7500
        session.player.exposure = 42.5
        session.currentPath = "/data/files"

        repo.savePlayer(session)
        assertTrue(repo.playerExists("test-player"))

        val loaded = repo.loadPlayer("test-player")
        assertNotNull(loaded)
        assertEquals("test-player", loaded.sessionId)
        assertEquals(7500, loaded.player.credits)
        assertEquals(42.5, loaded.player.exposure)
        assertEquals("/data/files", loaded.currentPath)
    }

    @Test
    fun `save and load preserves machine state`() {
        val session = GameSession("test-machine")
        session.player.currentMachine = MachineState(
            machineType = MachineType.GREYBOX,
            firewallCurrent = 75,
            firewallMax = 100,
            residualExposure = 15,
            storageLevel = 1,
            storageSlots = 6
        )

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-machine")!!

        assertEquals(MachineType.GREYBOX, loaded.player.currentMachine.machineType)
        assertEquals(75, loaded.player.currentMachine.firewallCurrent)
        assertEquals(100, loaded.player.currentMachine.firewallMax)
        assertEquals(15, loaded.player.currentMachine.residualExposure)
        assertEquals(1, loaded.player.currentMachine.storageLevel)
    }

    @Test
    fun `save and load preserves game phase`() {
        val session = GameSession("test-phase")
        session.player.currentGamePhase = GamePhase.MISSION_ACTIVE

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-phase")!!

        assertEquals(GamePhase.MISSION_ACTIVE, loaded.player.currentGamePhase)
    }

    @Test
    fun `save and load preserves shield state`() {
        val session = GameSession("test-shield")
        session.activateShield(240)

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-shield")!!

        assertTrue(loaded.shieldActive)
        assertNotNull(loaded.shieldExpiresAt)
    }

    @Test
    fun `save and load preserves firewall status`() {
        val session = GameSession("test-firewall")
        session.firewallStatus = "damaged"

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-firewall")!!

        assertEquals("damaged", loaded.firewallStatus)
    }

    @Test
    fun `save and load preserves collections`() {
        val session = GameSession("test-collections")
        session.player.discoveredNodes.addAll(listOf("nova-corp-web", "nova-corp-db"))
        session.player.completedMissions.add("mission_1")
        session.player.ownedTools.add(OwnedTool(ToolType.CLOAK, ToolVersion.V1, ToolCategory.PERMANENT))
        session.nodeAccess.addAll(listOf("nova-corp-web"))

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-collections")!!

        assertTrue("nova-corp-web" in loaded.player.discoveredNodes)
        assertTrue("nova-corp-db" in loaded.player.discoveredNodes)
        assertTrue("mission_1" in loaded.player.completedMissions)
        assertTrue(loaded.player.ownedTools.any { it.type == ToolType.CLOAK && it.version == ToolVersion.V1 })
        assertTrue("nova-corp-web" in loaded.nodeAccess)
    }

    @Test
    fun `save and load preserves downloads`() {
        val session = GameSession("test-downloads")
        session.downloads.add(DownloadRecord(
            filename = "data.db",
            sourceNode = "nova-corp-db",
            path = "/data/employees.db"
        ))

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-downloads")!!

        assertEquals(1, loaded.downloads.size)
        assertEquals("data.db", loaded.downloads[0].filename)
        assertEquals("nova-corp-db", loaded.downloads[0].sourceNode)
    }

    @Test
    fun `save and load preserves connection history`() {
        val session = GameSession("test-history")
        session.connectionHistory.add(ConnectionTrace(
            nodeId = "nova-corp-web",
            nodeName = "NovaCorp Web Server"
        ))

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-history")!!

        assertEquals(1, loaded.connectionHistory.size)
        assertEquals("nova-corp-web", loaded.connectionHistory[0].nodeId)
    }

    @Test
    fun `save and load preserves stats`() {
        val session = GameSession("test-stats")
        session.player.totalHacks = 10
        session.player.successfulHacks = 7
        session.player.failedHacks = 3
        session.player.totalCreditsEarned = 15000
        session.player.totalCreditsSpent = 8000

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-stats")!!

        assertEquals(10, loaded.player.totalHacks)
        assertEquals(7, loaded.player.successfulHacks)
        assertEquals(3, loaded.player.failedHacks)
        assertEquals(15000, loaded.player.totalCreditsEarned)
        assertEquals(8000, loaded.player.totalCreditsSpent)
    }

    @Test
    fun `save and load preserves reputation`() {
        val session = GameSession("test-rep")
        session.player.modifyReputation("underground", 50)
        session.player.modifyReputation("corporate", -30)

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-rep")!!

        assertEquals(50, loaded.player.getReputation("underground"))
        assertEquals(-30, loaded.player.getReputation("corporate"))
    }

    @Test
    fun `update existing player preserves changed state`() {
        val session = GameSession("test-update")
        session.player.credits = 5000
        repo.savePlayer(session)

        // Update credits
        session.player.credits = 8000
        session.player.exposure = 25.0
        repo.savePlayer(session)

        val loaded = repo.loadPlayer("test-update")!!
        assertEquals(8000, loaded.player.credits)
        assertEquals(25.0, loaded.player.exposure)
    }

    @Test
    fun `loadPlayer returns null for non-existent player`() {
        assertNull(repo.loadPlayer("does-not-exist"))
    }

    @Test
    fun `recordTransaction stores transaction`() {
        // Create player first (FK constraint)
        val session = GameSession("test-tx")
        repo.savePlayer(session)

        // Should not throw
        repo.recordTransaction("test-tx", "earn", 1000, 6000, mapOf("source" to "mission"))
    }

    @Test
    fun `recordMissionCompletion stores mission record`() {
        // Create player first (FK constraint)
        val session = GameSession("test-mission")
        repo.savePlayer(session)

        // Should not throw
        repo.recordMissionCompletion(
            playerId = "test-mission",
            missionId = "first_boot",
            startedAt = System.currentTimeMillis() - 60000,
            success = true,
            finalExposure = 15.0,
            payout = 500,
            objectivesCompleted = listOf("obj1", "obj2")
        )
    }

    @Test
    fun `connected node is restored on load`() {
        val session = GameSession("test-connected")
        // public-gateway is compromised, so connectTo should work
        session.player.discoveredNodes.add("public-gateway")
        session.connectTo("public-gateway")
        assertNotNull(session.connectedNode)

        repo.savePlayer(session)
        val loaded = repo.loadPlayer("test-connected")!!

        assertNotNull(loaded.connectedNode)
        assertEquals("public-gateway", loaded.connectedNode!!.id)
    }
}
