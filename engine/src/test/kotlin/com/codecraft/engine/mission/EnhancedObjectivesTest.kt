package com.codecraft.engine.mission

import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.domain.*
import com.codecraft.engine.persistence.GameDatabase
import com.codecraft.engine.session.GameSession
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class EnhancedObjectivesTest {

    private lateinit var session: GameSession
    private lateinit var mission: MissionDefinition

    @BeforeEach
    fun setup() {
        GameDatabase.initWithUrl("jdbc:h2:mem:test_enhanced_objectives;DB_CLOSE_DELAY=-1", "org.h2.Driver")
        session = GameSession("test-session")
    }

    @Test
    fun `peak exposure is tracked during mission`() {
        // Given: Mission with REMAIN_UNDETECTED objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "test-node",
            targetFile = "/data/test.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 50
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // When: Player's exposure increases over time
        session.player.exposure = 20.0
        ObjectiveTracker.checkObjectives(session, "scan", emptyList(), CommandResult.success("Scanned"))
        assertEquals(20.0, activeMission.peakExposure)

        session.player.exposure = 35.0
        ObjectiveTracker.checkObjectives(session, "probe", emptyList(), CommandResult.success("Probed"))
        assertEquals(35.0, activeMission.peakExposure)

        session.player.exposure = 25.0 // Exposure decreases
        ObjectiveTracker.checkObjectives(session, "disconnect", emptyList(), CommandResult.success("Disconnected"))

        // Then: Peak exposure should remain at highest value
        assertEquals(35.0, activeMission.peakExposure)
    }

    @Test
    fun `stealth objective fails when threshold exceeded`() {
        // Given: Mission with REMAIN_UNDETECTED objective (threshold 50%)
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "test-node",
            targetFile = "/data/test.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_stealth",
                    description = "Remain undetected",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 50
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // When: Exposure remains under threshold
        session.player.exposure = 40.0
        ObjectiveTracker.checkObjectives(session, "scan", emptyList(), CommandResult.success("Scanned"))

        // Then: Objective should not fail
        assertTrue(!activeMission.failedObjectives.contains("obj_stealth"))
        assertTrue(!activeMission.stealthViolated)

        // When: Exposure exceeds threshold
        session.player.exposure = 55.0
        ObjectiveTracker.checkObjectives(session, "probe", emptyList(), CommandResult.success("Probed"))

        // Then: Objective should fail
        assertTrue(activeMission.failedObjectives.contains("obj_stealth"))
        assertTrue(activeMission.stealthViolated)
    }

    @Test
    fun `time-based objectives auto-fail when time limit exceeded`() {
        // Given: Mission with time-limited objective (60 seconds)
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/test.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect quickly",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web",
                    timeLimit = 60 // 60 seconds
                )
            ),
            reputationReward = mapOf()
        )

        // Mission started 90 seconds ago
        val now = System.currentTimeMillis()
        val startTime = now - (90 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime
        )
        session.currentMission = activeMission

        // When: Check objectives (after time limit)
        ObjectiveTracker.checkObjectives(session, "scan", emptyList(), CommandResult.success("Scanned"))

        // Then: Objective should be marked as failed
        assertTrue(activeMission.failedObjectives.contains("obj_connect"))
    }

    @Test
    fun `time-based objectives do not fail before time limit`() {
        // Given: Mission with time-limited objective (60 seconds)
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/test.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_connect",
                    description = "Connect quickly",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web",
                    timeLimit = 60 // 60 seconds
                )
            ),
            reputationReward = mapOf()
        )

        // Mission started 30 seconds ago
        val now = System.currentTimeMillis()
        val startTime = now - (30 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime
        )
        session.currentMission = activeMission

        // When: Check objectives (before time limit)
        ObjectiveTracker.checkObjectives(session, "scan", emptyList(), CommandResult.success("Scanned"))

        // Then: Objective should not be failed
        assertTrue(!activeMission.failedObjectives.contains("obj_connect"))
    }

    @Test
    fun `extract data validates file path matching correctly`() {
        // Given: Mission with EXTRACT_DATA objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "test-node",
            targetFile = "/data/secret.txt",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract secret file",
                    type = ObjectiveType.EXTRACT_DATA,
                    target = "secret.txt"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // When: Cat command with exact filename
        val completed1 = ObjectiveTracker.checkObjectives(
            session, "cat", listOf("secret.txt"), CommandResult.success("file contents")
        )
        assertTrue(completed1.contains("obj_extract"))

        // Reset mission
        activeMission.completedObjectives.clear()

        // When: Cat command with full path
        val completed2 = ObjectiveTracker.checkObjectives(
            session, "cat", listOf("/data/secret.txt"), CommandResult.success("file contents")
        )
        assertTrue(completed2.contains("obj_extract"))

        // Reset mission
        activeMission.completedObjectives.clear()

        // When: Cat command with different path but same filename
        val completed3 = ObjectiveTracker.checkObjectives(
            session, "cat", listOf("/other/path/secret.txt"), CommandResult.success("file contents")
        )
        assertTrue(completed3.contains("obj_extract"))
    }

    @Test
    fun `bonus objectives are tracked separately from required objectives`() {
        // Given: Mission with both required and bonus objectives
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/test.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_required",
                    description = "Required: Connect to target",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                )
            ),
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "obj_bonus_1",
                    description = "Bonus: Stay under 30% exposure",
                    type = ObjectiveType.REMAIN_UNDETECTED,
                    threshold = 30
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // When: Complete required objective
        val targetNode = session.network.getNode("nova-corp-web")!!
        session.connectedNode = targetNode
        ObjectiveTracker.checkObjectives(session, "connect", listOf("nova-corp-web"), CommandResult.success("Connected"))

        // Then: Required objective completed, bonus not affected
        assertTrue(activeMission.completedObjectives.contains("obj_required"))
        assertTrue(!activeMission.bonusObjectivesCompleted.contains("obj_bonus_1"))

        // When: Exposure stays under bonus threshold
        session.player.exposure = 25.0
        ObjectiveTracker.checkObjectives(session, "scan", emptyList(), CommandResult.success("Scanned"))

        // Then: Bonus stealth objective should still be active (not failed)
        assertTrue(!activeMission.failedObjectives.contains("obj_bonus_1"))

        // When: Exposure exceeds bonus threshold
        session.player.exposure = 35.0
        ObjectiveTracker.checkObjectives(session, "probe", emptyList(), CommandResult.success("Probed"))

        // Then: Bonus stealth objective should fail
        assertTrue(activeMission.failedObjectives.contains("obj_bonus_1"))
    }
}
