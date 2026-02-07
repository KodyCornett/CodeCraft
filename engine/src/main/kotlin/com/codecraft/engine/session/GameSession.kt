package com.codecraft.engine.session

import com.codecraft.engine.domain.NetworkState
import com.codecraft.engine.domain.Node
import com.codecraft.engine.domain.Player
import com.codecraft.engine.protocol.GameStateSnapshot

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

    // Current terminal state
    var currentPath: String = "/home/user"
    var connectedNode: Node? = null

    // Terminal history
    val commandHistory = mutableListOf<String>()

    // Active incoming threats (for Sentinel)
    val incomingThreats = mutableListOf<IncomingThreat>()

    // Mission progress
    var currentMission: Mission? = null

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
            currentPath = "/home/user" // Reset path on new connection
            return true
        }
        return false
    }

    /**
     * Disconnect from current node
     */
    fun disconnect() {
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
     * Create a snapshot for saving/sending to client
     */
    fun toSnapshot(): GameStateSnapshot {
        return GameStateSnapshot(
            playerId = player.id,
            credits = player.credits,
            exposure = player.exposure,
            currentPath = currentPath,
            connectedTo = connectedNode?.id,
            unlockedCommands = player.unlockedCommands.toList(),
            discoveredNodes = player.discoveredNodes.toList(),
            completedMissions = player.completedMissions.toList()
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

/**
 * Mission/job data
 */
data class Mission(
    val id: String,
    val title: String,
    val description: String,
    val targetNode: String,
    val objectives: List<MissionObjective>,
    val reward: Int,
    val timeLimit: Int? = null // seconds, null = no limit
)

data class MissionObjective(
    val id: String,
    val description: String,
    var completed: Boolean = false
)
