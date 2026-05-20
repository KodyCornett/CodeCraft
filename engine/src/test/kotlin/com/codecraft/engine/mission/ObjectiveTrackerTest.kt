package com.codecraft.engine.mission

import com.codecraft.engine.command.CommandResult
import com.codecraft.engine.command.commands.PuzzleStateManager
import com.codecraft.engine.domain.*
import com.codecraft.engine.persistence.GameDatabase
import com.codecraft.engine.puzzle.Puzzle
import com.codecraft.engine.session.DownloadRecord
import com.codecraft.engine.session.GameSession
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class ObjectiveTrackerTest {

    private lateinit var session: GameSession
    private lateinit var mission: MissionDefinition

    @BeforeEach
    fun setup() {
        GameDatabase.initWithUrl("jdbc:h2:mem:test_objective_tracker;DB_CLOSE_DELAY=-1", "org.h2.Driver")
        session = GameSession("test-session")
    }

    @Test
    fun `checkObjectives detects CONNECT_NODE completion`() {
        // Given: Mission with CONNECT_NODE objective
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
                    description = "Connect to target",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Connect to target node
        val targetNode = session.network.getNode("nova-corp-web")!!
        session.connectedNode = targetNode

        // When: Check objectives after connect command
        val completed = ObjectiveTracker.checkObjectives(
            session,
            "connect",
            listOf("nova-corp-web"),
            CommandResult.success("Connected")
        )

        // Then: Objective should be completed
        assertEquals(1, completed.size)
        assertTrue(completed.contains("obj_connect"))
        assertTrue(activeMission.completedObjectives.contains("obj_connect"))
    }

    @Test
    fun `checkObjectives detects DOWNLOAD_FILE completion`() {
        // Given: Mission with DOWNLOAD_FILE objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/credentials.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_download",
                    description = "Download credentials",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/credentials.dat"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Download the file
        session.downloads.add(
            DownloadRecord(
                filename = "credentials.dat",
                sourceNode = "nova-corp-web",
                path = "/data/credentials.dat"
            )
        )

        // When: Check objectives after download command
        val completed = ObjectiveTracker.checkObjectives(
            session,
            "download",
            listOf("/data/credentials.dat"),
            CommandResult.success("Downloaded")
        )

        // Then: Objective should be completed
        assertEquals(1, completed.size)
        assertTrue(completed.contains("obj_download"))
        assertTrue(activeMission.completedObjectives.contains("obj_download"))
    }

    @Test
    fun `checkObjectives detects SOLVE_PUZZLE completion`() {
        // Given: Mission with SOLVE_PUZZLE objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/puzzle.dat",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_solve",
                    description = "Solve the puzzle",
                    type = ObjectiveType.SOLVE_PUZZLE,
                    target = "/data/puzzle.dat"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Mark puzzle as solved in mission
        activeMission.puzzleSolved = true

        // When: Check objectives after submit command
        val completed = ObjectiveTracker.checkObjectives(
            session,
            "submit",
            listOf("TEST"),
            CommandResult.success("Puzzle solved")
        )

        // Then: Objective should be completed
        assertEquals(1, completed.size)
        assertTrue(completed.contains("obj_solve"))
        assertTrue(activeMission.completedObjectives.contains("obj_solve"))
    }

    @Test
    fun `checkObjectives detects EXTRACT_DATA completion via download`() {
        // Given: Mission with EXTRACT_DATA objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
            targetFile = "/data/financial.csv",
            baseReward = 1000,
            difficulty = 1,
            requiredReputation = 0,
            puzzleType = PuzzleType.WORD_SEARCH,
            puzzleSolution = "TEST",
            objectives = listOf(
                ObjectiveDefinition(
                    id = "obj_extract",
                    description = "Extract financial data",
                    type = ObjectiveType.EXTRACT_DATA,
                    target = "/data/financial.csv"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Download the file
        session.downloads.add(
            DownloadRecord(
                filename = "financial.csv",
                sourceNode = "nova-corp-web",
                path = "/data/financial.csv"
            )
        )

        // When: Check objectives after download command
        val completed = ObjectiveTracker.checkObjectives(
            session,
            "download",
            listOf("/data/financial.csv"),
            CommandResult.success("Downloaded")
        )

        // Then: Objective should be completed
        assertEquals(1, completed.size)
        assertTrue(completed.contains("obj_extract"))
        assertTrue(activeMission.completedObjectives.contains("obj_extract"))
    }

    @Test
    fun `checkObjectives detects EXTRACT_DATA completion via cat`() {
        // Given: Mission with EXTRACT_DATA objective
        mission = MissionDefinition(
            id = "test_mission",
            title = "Test Mission",
            contactId = "ghost",
            description = "Test",
            briefing = "Test briefing",
            targetNodeId = "nova-corp-web",
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
                    target = "/data/secret.txt"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // When: Check objectives after cat command
        val completed = ObjectiveTracker.checkObjectives(
            session,
            "cat",
            listOf("/data/secret.txt"),
            CommandResult.success("file contents")
        )

        // Then: Objective should be completed
        assertEquals(1, completed.size)
        assertTrue(completed.contains("obj_extract"))
        assertTrue(activeMission.completedObjectives.contains("obj_extract"))
    }

    @Test
    fun `validateStealthObjectives marks REMAIN_UNDETECTED as failed when threshold exceeded`() {
        // Given: Mission with REMAIN_UNDETECTED objective (threshold 50%)
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

        // Set exposure above threshold
        session.player.exposure = 60.0

        // When: Check objectives (triggers stealth validation)
        ObjectiveTracker.checkObjectives(
            session,
            "scan",
            listOf(),
            CommandResult.success("Scanned")
        )

        // Then: Stealth objective should be marked as failed
        assertTrue(activeMission.failedObjectives.contains("obj_stealth"))
        assertTrue(activeMission.stealthViolated)
    }

    @Test
    fun `checkObjectives handles bonus objectives separately`() {
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
                    description = "Required objective",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                )
            ),
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "obj_bonus",
                    description = "Bonus objective",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/bonus.dat"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Complete required objective
        val targetNode = session.network.getNode("nova-corp-web")!!
        session.connectedNode = targetNode
        ObjectiveTracker.checkObjectives(session, "connect", listOf("nova-corp-web"), CommandResult.success("Connected"))

        // Complete bonus objective
        session.downloads.add(
            DownloadRecord(
                filename = "bonus.dat",
                sourceNode = "nova-corp-web",
                path = "/data/bonus.dat"
            )
        )
        ObjectiveTracker.checkObjectives(session, "download", listOf("/data/bonus.dat"), CommandResult.success("Downloaded"))

        // Then: Required and bonus objectives tracked separately
        assertTrue(activeMission.completedObjectives.contains("obj_required"))
        assertTrue(activeMission.bonusObjectivesCompleted.contains("obj_bonus"))
    }

    @Test
    fun `checkObjectives respects sequential objectives`() {
        // Given: Mission with sequential objectives (B requires A)
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
                    id = "obj_a",
                    description = "First objective",
                    type = ObjectiveType.CONNECT_NODE,
                    target = "nova-corp-web"
                ),
                ObjectiveDefinition(
                    id = "obj_b",
                    description = "Second objective (requires A)",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/test.dat",
                    requiresObjective = "obj_a"
                )
            ),
            reputationReward = mapOf()
        )

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000
        )
        session.currentMission = activeMission

        // Try to complete objective B before A
        session.downloads.add(
            DownloadRecord(
                filename = "test.dat",
                sourceNode = "nova-corp-web",
                path = "/data/test.dat"
            )
        )
        val completedB = ObjectiveTracker.checkObjectives(
            session, "download", listOf("/data/test.dat"), CommandResult.success("Downloaded")
        )

        // Then: Objective B should NOT be completed yet
        assertEquals(0, completedB.size)
        assertTrue(!activeMission.completedObjectives.contains("obj_b"))

        // Complete objective A
        val targetNode = session.network.getNode("nova-corp-web")!!
        session.connectedNode = targetNode
        ObjectiveTracker.checkObjectives(session, "connect", listOf("nova-corp-web"), CommandResult.success("Connected"))

        // Now objective B should be checkable
        val completedBAfterA = ObjectiveTracker.checkObjectives(
            session, "download", listOf("/data/test.dat"), CommandResult.success("Downloaded")
        )

        // Then: Objective B should now be completed
        assertEquals(1, completedBAfterA.size)
        assertTrue(completedBAfterA.contains("obj_b"))
    }
}
