package com.codecraft.engine.session

import com.codecraft.engine.domain.ActiveMission
import com.codecraft.engine.domain.NetworkState
import com.codecraft.engine.domain.Node
import com.codecraft.engine.domain.Player
import com.codecraft.engine.domain.PuzzleType
import com.codecraft.engine.protocol.GameStateSnapshot
import com.codecraft.engine.protocol.MissionObjectiveSnapshot
import com.codecraft.engine.puzzle.CombatType
import com.codecraft.engine.puzzle.PlayerPuzzleProgress
import kotlinx.serialization.Serializable

/**
 * A pending puzzle (connection or counter-hack)
 */
@Serializable
data class PendingPuzzle(
    val nodeId: String,
    val puzzleType: String,
    val question: String,
    val answer: String,
    val difficulty: Int = 1
)

/**
 * A pending combat puzzle (sentinel/hacker attack)
 */
@Serializable
data class PendingCombatPuzzle(
    val puzzleType: PuzzleType,
    val combatType: CombatType,
    val question: String,
    val answer: String,
    val difficulty: Int = 2,
    val threatReduction: Int = 15,
    val systemIntegrityPenalty: Int = 10,
    val allowsPartialCredit: Boolean = false
)

/**
 * Active defrag session
 */
@Serializable
data class DefragState(
    val totalSteps: Int,
    var completedSteps: Int = 0,
    val currentQuestion: String,
    val currentAnswer: String,
    val wasUnderAttack: Boolean = false,
    val traceNodeId: String? = null
)

/**
 * Connection trace record (for sentinel)
 */
@Serializable
data class ConnectionTrace(
    val nodeId: String,
    val nodeName: String,
    val connectedAt: Long = System.currentTimeMillis(),
    var disconnectedAt: Long? = null,
    var peakExposure: Double = 0.0
)

/**
 * Sentinel event log entry
 */
@Serializable
data class SentinelEvent(
    val type: String,
    val message: String,
    val timestamp: Long = System.currentTimeMillis(),
    val data: Map<String, String> = emptyMap()
)

/**
 * Download record
 */
@Serializable
data class DownloadRecord(
    val filename: String,
    val sourceNode: String,
    val path: String,
    val downloadedAt: Long = System.currentTimeMillis()
)

/**
 * A game session for a single player.
 * Contains all state for that player's game.
 */
class GameSession(
    val sessionId: String,
    val player: Player = Player(id = sessionId)
) {
    // Network state (shared but could be per-session in multiplayer)
    val network = NetworkState()

    init {
        // Auto-discover all public nodes — they're always visible on the map
        network.getAllNodes().filter { it.isPublic }.forEach { node ->
            player.discoveredNodes.add(node.id)
        }
    }

    // Current terminal state
    var currentPath: String = "/home/user"
    var connectedNode: Node? = null

    // Terminal history
    val commandHistory = mutableListOf<String>()

    // Active incoming threats (for Sentinel)
    val incomingThreats = mutableListOf<IncomingThreat>()

    // Mission progress
    var currentMission: ActiveMission? = null

    // Shield system
    var shieldActive: Boolean = false
    var shieldExpiresAt: Long? = null

    // Firewall status
    var firewallStatus: String = "active" // "active", "damaged", "repairing", "repaired"

    // Connection history (for sentinel)
    val connectionHistory = mutableListOf<ConnectionTrace>()

    // Sentinel events log (max 50)
    val sentinelEvents = mutableListOf<SentinelEvent>()

    // Nodes with solved puzzles (access granted)
    val nodeAccess = mutableSetOf<String>()

    // Connection puzzle
    var pendingPuzzle: PendingPuzzle? = null

    // Counter-hack puzzle
    var counterHackPuzzle: PendingPuzzle? = null

    // Defrag state
    var defragState: DefragState? = null

    // Downloaded files
    val downloads = mutableListOf<DownloadRecord>()

    // Last action time (for exposure decay)
    var lastActionTime: Long = System.currentTimeMillis()

    // Combat stats (replaces Boolean sentinelAttackActive)
    var threatPressure: Int = 0      // 0-100
    var systemIntegrity: Int = 100   // 0-100
    var activeCombatType: CombatType? = null
    var combatPuzzle: PendingCombatPuzzle? = null
    val recentPuzzleTypes: ArrayDeque<PuzzleType> = ArrayDeque(3)
    var puzzleProgress: PlayerPuzzleProgress = PlayerPuzzleProgress()

    // Backward-compat: sentinelAttackActive computed from threatPressure
    val sentinelAttackActive: Boolean get() = threatPressure >= 67

    fun increaseThreatPressure(amount: Int) {
        threatPressure = (threatPressure + amount).coerceIn(0, 100)
    }

    fun decreaseThreatPressure(amount: Int) {
        threatPressure = (threatPressure - amount).coerceIn(0, 100)
    }

    fun damageSystemIntegrity(amount: Int) {
        systemIntegrity = (systemIntegrity - amount).coerceIn(0, 100)
    }

    fun recordPuzzleType(type: PuzzleType) {
        recentPuzzleTypes.addLast(type)
        while (recentPuzzleTypes.size > 3) recentPuzzleTypes.removeFirst()
    }

    /**
     * Activate shield for a duration
     */
    fun activateShield(durationSeconds: Int = 240) {
        shieldActive = true
        shieldExpiresAt = System.currentTimeMillis() + (durationSeconds * 1000L)
        addSentinelEvent("shield_activated", "Shield activated for ${durationSeconds}s")
    }

    /**
     * Check if shield is currently active (not expired)
     */
    fun isShieldActive(): Boolean {
        if (!shieldActive) return false
        val expiresAt = shieldExpiresAt ?: return false
        if (System.currentTimeMillis() > expiresAt) {
            cleanupExpiredShield()
            return false
        }
        return true
    }

    /**
     * Clean up expired shield
     */
    fun cleanupExpiredShield() {
        if (shieldActive && shieldExpiresAt != null && System.currentTimeMillis() > shieldExpiresAt!!) {
            shieldActive = false
            shieldExpiresAt = null
            addSentinelEvent("shield_expired", "Shield has expired")
        }
    }

    /**
     * Add a sentinel event (capped at 50)
     */
    fun addSentinelEvent(type: String, message: String, data: Map<String, String> = emptyMap()) {
        sentinelEvents.add(SentinelEvent(type, message, data = data))
        if (sentinelEvents.size > 50) {
            sentinelEvents.removeAt(0)
        }
    }

    /**
     * Get the current working directory's files
     */
    fun getCurrentFiles(): Map<String, String> {
        val node = connectedNode ?: network.getNode("localhost")!!
        return node.files.filter { (path, _) ->
            path.startsWith(currentPath) &&
                    path.removePrefix(currentPath).trimStart('/').count { it == '/' } == 0
        }
    }

    /**
     * Connect to a node
     */
    fun connectTo(nodeId: String): Boolean {
        val node = network.getNode(nodeId) ?: network.getNodeByIp(nodeId)
        if (node != null) {
            connectedNode = node
            // Set path to the node's root path or default
            currentPath = node.rootPath ?: "/home/user"

            // Record connection trace
            connectionHistory.add(ConnectionTrace(
                nodeId = node.id,
                nodeName = node.name
            ))
            addSentinelEvent("connection", "Connected to ${node.name}", mapOf("nodeId" to node.id))

            return true
        }
        return false
    }

    /**
     * Disconnect from current node
     */
    fun disconnect() {
        val node = connectedNode
        if (node != null) {
            // Update the last connection trace
            connectionHistory.lastOrNull { it.nodeId == node.id && it.disconnectedAt == null }?.let {
                it.disconnectedAt = System.currentTimeMillis()
                it.peakExposure = player.exposure
            }
            addSentinelEvent("disconnection", "Disconnected from ${node.name}", mapOf("nodeId" to node.id))
        }
        connectedNode = null
        currentPath = "/home/user"
    }

    /**
     * Get current node (connected or localhost)
     */
    fun getCurrentNode(): Node {
        return connectedNode ?: network.getNode("localhost")!!
    }

    /**
     * Add an incoming threat
     */
    fun addThreat(threat: IncomingThreat) {
        incomingThreats.add(threat)
    }

    /**
     * Remove a threat (blocked or resolved)
     */
    fun removeThreat(threatId: String) {
        incomingThreats.removeIf { it.id == threatId }
    }

    /**
     * Get active connection traces (not disconnected)
     */
    fun getActiveTraces(): List<ConnectionTrace> {
        return connectionHistory.filter { it.disconnectedAt == null }
    }

    /**
     * Create a snapshot for saving/sending to client
     */
    fun toSnapshot(): GameStateSnapshot {
        val objectives = currentMission?.definition?.objectives?.map { obj ->
            MissionObjectiveSnapshot(
                id = obj.id,
                description = obj.description,
                completed = obj.id in (currentMission?.completedObjectives ?: emptySet())
            )
        } ?: emptyList()

        return GameStateSnapshot(
            playerId = player.id,
            credits = player.credits,
            exposure = player.exposure,
            currentPath = currentPath,
            connectedTo = connectedNode?.id,
            unlockedCommands = player.unlockedCommands.toList(),
            discoveredNodes = player.discoveredNodes.toList(),
            completedMissions = player.completedMissions.toList(),
            activeMission = player.activeMission,
            currentObjectives = objectives,
            reputation = player.reputation.toMap(),
            firewallCurrent = player.currentMachine.firewallCurrent,
            firewallMax = player.currentMachine.firewallMax,
            damageState = player.currentMachine.damageState.name,
            shieldActive = isShieldActive(),
            shieldExpiresAt = if (isShieldActive()) shieldExpiresAt else null,
            threatPressure = threatPressure,
            systemIntegrity = systemIntegrity,
            combatPuzzleActive = combatPuzzle != null
        )
    }
}

/**
 * Incoming threat from counter-hack or detection
 */
data class IncomingThreat(
    val id: String,
    val sourceIp: String,
    val sourceNode: String,
    val type: ThreatType,
    val progress: Int = 0, // 0-100
    val startTime: Long = System.currentTimeMillis()
) {
    val timeRemaining: Int
        get() {
            val elapsed = (System.currentTimeMillis() - startTime) / 1000
            return maxOf(0, 60 - elapsed.toInt()) // 60 second timeout
        }
}

enum class ThreatType {
    PROBE,
    SCAN,
    INTRUSION,
    COUNTER_HACK
}
