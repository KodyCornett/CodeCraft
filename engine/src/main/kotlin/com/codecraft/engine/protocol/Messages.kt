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
    val gameEvents: List<GameEvent> = emptyList()
)

@Serializable
data class StateChanges(
    val currentPath: String? = null,
    val connectedTo: String? = null,
    val exposure: Double? = null,
    val credits: Int? = null,
    val unlockedCommands: List<String>? = null,
    val discoveredNodes: List<String>? = null
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
    val completedMissions: List<String>
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
