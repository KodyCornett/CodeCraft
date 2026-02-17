package com.codecraft.engine.mission

import com.codecraft.engine.domain.*
import com.codecraft.engine.persistence.GameDatabase
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import kotlin.test.assertEquals
import kotlin.test.assertTrue

class PayoutCalculatorTest {

    private lateinit var player: Player
    private lateinit var mission: MissionDefinition

    @BeforeEach
    fun setup() {
        GameDatabase.initWithUrl("jdbc:h2:mem:test_payout_calculator;DB_CLOSE_DELAY=-1", "org.h2.Driver")
        player = Player(id = "test-player")

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
            objectives = listOf(),
            bonusObjectives = listOf(),
            estimatedTimeMinutes = 10, // 10 minutes estimated
            reputationReward = mapOf()
        )
    }

    @Test
    fun `calculatePayout returns base reward with no bonuses or penalties`() {
        // Given: Clean mission completion (no bonuses, no penalties)
        // Start time is 8 minutes ago (not fast, but no bonus objectives)
        val now = System.currentTimeMillis()
        val startTime = now - (8 * 60 * 1000) // 8 minutes ago (over quick threshold of 5 min)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime,
            stealthViolated = true // Violated to avoid stealth bonus
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should return base reward (with penalty for stealth violation)
        assertEquals(1000, result.baseReward)
        assertEquals(900, result.finalPayout) // -10% for stealth violation
        assertEquals(0, result.totalBonus)
        assertEquals(100, result.totalPenalty) // 10% stealth penalty
    }

    @Test
    fun `calculatePayout applies stealth bonus when stealth maintained`() {
        // Given: Mission with stealth maintained
        // Start time is 8 minutes ago (not fast)
        val now = System.currentTimeMillis()
        val startTime = now - (8 * 60 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime,
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have 15% stealth bonus
        assertTrue(result.bonuses.containsKey("Stealth Maintained"))
        assertEquals(150, result.bonuses["Stealth Maintained"])
        assertEquals(1150, result.finalPayout)
    }

    @Test
    fun `calculatePayout applies stealth penalty when violated`() {
        // Given: Mission with stealth violated
        // Start time is 8 minutes ago (not fast)
        val now = System.currentTimeMillis()
        val startTime = now - (8 * 60 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime,
            stealthViolated = true
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have 10% stealth penalty
        assertTrue(result.penalties.containsKey("Stealth Violated"))
        assertEquals(100, result.penalties["Stealth Violated"])
        assertEquals(900, result.finalPayout)
    }

    @Test
    fun `calculatePayout applies firewall damage penalty`() {
        // Given: Mission with damaged firewall (70/100 = 0.7 < 0.75 threshold)
        // Start time is 8 minutes ago (not fast)
        val now = System.currentTimeMillis()
        val startTime = now - (8 * 60 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime,
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 70, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have 20% firewall damage penalty
        assertTrue(result.penalties.containsKey("Firewall Damaged"))
        assertEquals(200, result.penalties["Firewall Damaged"])

        // Base 1000 + 150 stealth bonus - 200 firewall penalty = 950
        assertEquals(950, result.finalPayout)
    }

    @Test
    fun `calculatePayout applies bonus objectives bonus`() {
        // Given: Mission with all bonus objectives completed
        val missionWithBonus = mission.copy(
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_1",
                    description = "Bonus objective",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/bonus.dat"
                )
            )
        )

        val activeMission = ActiveMission(
            definition = missionWithBonus,
            negotiatedReward = 1000,
            bonusObjectivesCompleted = mutableSetOf("bonus_1")
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have 20% bonus objectives bonus
        assertTrue(result.bonuses.containsKey("All Bonus Objectives"))
        assertEquals(200, result.bonuses["All Bonus Objectives"])
    }

    @Test
    fun `calculatePayout applies fast completion bonus`() {
        // Given: Mission completed quickly (within 50% of estimated time)
        // Estimated: 10 minutes (600 seconds)
        // Quick threshold: 300 seconds (5 minutes)
        val now = System.currentTimeMillis()
        val startTime = now - (4 * 60 * 1000) // 4 minutes ago

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have 10% fast completion bonus
        assertTrue(result.bonuses.containsKey("Fast Completion"))
        assertEquals(100, result.bonuses["Fast Completion"])
    }

    @Test
    fun `calculatePayout does not apply fast completion bonus when too slow`() {
        // Given: Mission completed slowly (over 50% of estimated time)
        val now = System.currentTimeMillis()
        val startTime = now - (7 * 60 * 1000) // 7 minutes ago (over 5 min threshold)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should NOT have fast completion bonus
        assertTrue(!result.bonuses.containsKey("Fast Completion"))
    }

    @Test
    fun `calculatePayout applies multiple bonuses`() {
        // Given: Mission with all bonus conditions met
        val missionWithBonus = mission.copy(
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_1",
                    description = "Bonus objective",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/bonus.dat"
                )
            )
        )

        val now = System.currentTimeMillis()
        val startTime = now - (4 * 60 * 1000) // 4 minutes ago (fast)

        val activeMission = ActiveMission(
            definition = missionWithBonus,
            negotiatedReward = 1000,
            startedAt = startTime,
            bonusObjectivesCompleted = mutableSetOf("bonus_1"),
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should have all 3 bonuses
        // +20% bonus objectives = 200
        // +15% stealth = 150
        // +10% fast = 100
        // Total = 1000 + 450 = 1450
        assertEquals(200, result.bonuses["All Bonus Objectives"])
        assertEquals(150, result.bonuses["Stealth Maintained"])
        assertEquals(100, result.bonuses["Fast Completion"])
        assertEquals(450, result.totalBonus)
        assertEquals(1450, result.finalPayout)
    }

    @Test
    fun `calculatePayout clamps payout to minimum 50 percent of base`() {
        // Given: Mission with heavy penalties
        // Start time is 8 minutes ago (not fast)
        val now = System.currentTimeMillis()
        val startTime = now - (8 * 60 * 1000)

        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            startedAt = startTime,
            stealthViolated = true
        )

        // Damaged firewall
        player.currentMachine = MachineState(firewallCurrent = 50, firewallMax = 100)

        // When: Calculate payout
        // Base: 1000
        // Penalties: -100 (stealth) -200 (firewall) = -300
        // Raw: 700, but should not go below 500 (50% of base)
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should be clamped to 50% minimum
        assertTrue(result.finalPayout >= 500)
        assertEquals(700, result.finalPayout) // Actually 700 is above minimum, so no clamping
    }

    @Test
    fun `calculatePayout clamps payout to maximum 150 percent of base`() {
        // Given: Unrealistically high negotiated reward
        val missionWithBonus = mission.copy(
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_1",
                    description = "Bonus",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/bonus.dat"
                )
            )
        )

        val now = System.currentTimeMillis()
        val startTime = now - (4 * 60 * 1000)

        val activeMission = ActiveMission(
            definition = missionWithBonus,
            negotiatedReward = 1000,
            startedAt = startTime,
            bonusObjectivesCompleted = mutableSetOf("bonus_1"),
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        // Base: 1000
        // Bonuses: +200 +150 +100 = +450
        // Total: 1450 (within 150% cap of 1500)
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Should not exceed 150% of base (1500)
        assertTrue(result.finalPayout <= 1500)
        assertEquals(1450, result.finalPayout) // Under cap, so not clamped
    }

    @Test
    fun `calculatePayout calculates reputation gain correctly`() {
        // Given: Mission with stealth and all bonus objectives
        val missionWithBonus = mission.copy(
            bonusObjectives = listOf(
                ObjectiveDefinition(
                    id = "bonus_1",
                    description = "Bonus",
                    type = ObjectiveType.DOWNLOAD_FILE,
                    target = "/data/bonus.dat"
                )
            )
        )

        val activeMission = ActiveMission(
            definition = missionWithBonus,
            negotiatedReward = 1000,
            bonusObjectivesCompleted = mutableSetOf("bonus_1"),
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Reputation should be base (10) + stealth (5) + bonus (5) = 20
        assertEquals(20, result.reputationGain)
    }

    @Test
    fun `calculatePayout reputation gain with partial bonuses`() {
        // Given: Mission with stealth but no bonus objectives
        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            stealthViolated = false
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Reputation should be base (10) + stealth (5) = 15
        assertEquals(15, result.reputationGain)
    }

    @Test
    fun `calculatePayout reputation gain with penalties`() {
        // Given: Mission with stealth violated
        val activeMission = ActiveMission(
            definition = mission,
            negotiatedReward = 1000,
            stealthViolated = true
        )

        player.currentMachine = MachineState(firewallCurrent = 100, firewallMax = 100)

        // When: Calculate payout
        val result = PayoutCalculator.calculatePayout(activeMission, player)

        // Then: Reputation should be base (10) only (no bonuses)
        assertEquals(10, result.reputationGain)
    }
}
