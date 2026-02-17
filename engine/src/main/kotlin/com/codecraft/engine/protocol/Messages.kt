package com.codecraft.engine.protocol

import kotlinx.serialization.Serializable

/**
 * Protocol messages for Laravel <-> Kotlin communication
 */

// ============================================
// Incoming messages (from Laravel/client)
// ============================================

@Serializable
data class CommandRequest(
    val sessionId: String,
    val command: String,
    val context: CommandContext = CommandContext()
)

@Serializable
data class CommandContext(
    val currentPath: String = "/home/user",
    val connectedTo: String? = null,
    val variables: Map<String, String> = emptyMap()
)

@Serializable
data class SessionRequest(
    val action: String, // "create", "load", "save"
    val sessionId: String? = null,
    val saveData: String? = null
)

// ============================================
// Outgoing messages (to Laravel/client)
// ============================================

@Serializable
data class CommandResponse(
    val success: Boolean,
    val output: String,
    val error: String? = null,
    val delayMs: Int = 0,
    val stateChanges: StateChanges? = null,
    val gameEvents: List<GameEvent> = emptyList(),
    val traceIncrease: Double = 0.0
)

@Serializable
data class StateChanges(
    val currentPath: String? = null,
    val connectedTo: String? = null,
    val exposure: Double? = null,
    val credits: Int? = null,
    val unlockedCommands: List<String>? = null,
    val discoveredNodes: List<String>? = null,
    val activeMission: String? = null,
    val missionProgress: List<String>? = null,  // Completed objective IDs
    val objectivesCompleted: List<String>? = null,  // Newly completed objectives (this command)
    val reputation: Map<String, Int>? = null,
    val connectedToName: String? = null,
    val rootPath: String? = null,
    val pendingConnection: String? = null,
    val traceIncrease: Double? = null,
    val sentinelAttack: Boolean? = null,
    val defragComplete: Boolean? = null,
    val shieldActivated: Boolean? = null,
    val firewallStatus: String? = null,
    val downloads: List<String>? = null,
    val openWindow: String? = null,
    val threatPressure: Int? = null,
    val systemIntegrity: Int? = null,
    val combatPuzzleActive: Boolean? = null
)

@Serializable
data class GameEvent(
    val type: String,
    val data: Map<String, String> = emptyMap()
)

@Serializable
data class SessionResponse(
    val success: Boolean,
    val sessionId: String? = null,
    val error: String? = null,
    val state: GameStateSnapshot? = null
)

@Serializable
data class GameStateSnapshot(
    val playerId: String,
    val credits: Int,
    val exposure: Double,
    val currentPath: String,
    val connectedTo: String?,
    val unlockedCommands: List<String>,
    val discoveredNodes: List<String>,
    val completedMissions: List<String>,
    val activeMission: String?,
    val currentObjectives: List<MissionObjectiveSnapshot>,
    val reputation: Map<String, Int>,
    val firewallCurrent: Int = 100,
    val firewallMax: Int = 100,
    val damageState: String = "PRISTINE",
    val shieldActive: Boolean = false,
    val shieldExpiresAt: Long? = null,
    val threatPressure: Int = 0,
    val systemIntegrity: Int = 100,
    val combatPuzzleActive: Boolean = false
)

@Serializable
data class MissionObjectiveSnapshot(
    val id: String,
    val description: String,
    val completed: Boolean
)

// ============================================
// Network State
// ============================================

@Serializable
data class NetworkNodeData(
    val id: String,
    val name: String,
    val ip: String,
    val type: String,
    val status: String,
    val isPublic: String,
    val isDiscovered: String,
    val securityLevel: String
)

@Serializable
data class NetworkConnectionData(
    val from: String,
    val to: String,
    val style: String
)

@Serializable
data class NetworkStateResponse(
    val nodes: List<NetworkNodeData>,
    val connections: List<NetworkConnectionData>,
    val currentNode: String,
    val currentNodeId: String
)

// ============================================
// WebSocket message wrapper
// ============================================

@Serializable
data class WebSocketMessage(
    val type: String, // "command", "ping", "state_request"
    val payload: String // JSON string of actual message
)

@Serializable
data class WebSocketResponse(
    val type: String, // "command_result", "pong", "state_update", "event"
    val payload: String // JSON string of actual response
)
