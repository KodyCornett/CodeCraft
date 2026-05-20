package com.codecraft.engine.api

import com.codecraft.engine.command.CommandRegistry
import com.codecraft.engine.protocol.CommandRequest
import com.codecraft.engine.protocol.WebSocketMessage
import com.codecraft.engine.protocol.WebSocketResponse
import com.codecraft.engine.session.SessionManager
import io.ktor.server.application.*
import io.ktor.server.routing.*
import io.ktor.server.websocket.*
import io.ktor.websocket.*
import kotlinx.coroutines.channels.ClosedReceiveChannelException
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import kotlin.time.Duration.Companion.seconds

/**
 * Configure WebSocket support for terminal communication
 */
fun Application.configureWebSockets(sessionManager: SessionManager) {
    install(WebSockets) {
        pingPeriod = 15.seconds
        timeout = 30.seconds
        maxFrameSize = Long.MAX_VALUE
        masking = false
    }

    val commandRegistry = CommandRegistry(sessionManager.getRepository())
    val json = Json { ignoreUnknownKeys = true }

    routing {
        webSocket("/ws/terminal/{sessionId?}") {
            val sessionId = call.parameters["sessionId"]
            val session = sessionManager.getOrCreateSession(sessionId)

            println("[WebSocket] Client connected: ${session.sessionId}")

            // Send initial state
            val initialState = WebSocketResponse(
                type = "connected",
                payload = json.encodeToString(mapOf(
                    "sessionId" to session.sessionId,
                    "state" to session.toSnapshot()
                ))
            )
            send(Frame.Text(json.encodeToString(initialState)))

            try {
                for (frame in incoming) {
                    when (frame) {
                        is Frame.Text -> {
                            val text = frame.readText()
                            handleMessage(text, session, commandRegistry, json, this)
                        }
                        is Frame.Ping -> send(Frame.Pong(frame.data))
                        else -> {}
                    }
                }
            } catch (e: ClosedReceiveChannelException) {
                println("[WebSocket] Client disconnected: ${session.sessionId}")
            } catch (e: Exception) {
                println("[WebSocket] Error: ${e.message}")
            }
        }
    }
}

private suspend fun handleMessage(
    text: String,
    session: com.codecraft.engine.session.GameSession,
    commandRegistry: CommandRegistry,
    json: Json,
    socket: DefaultWebSocketSession
) {
    try {
        val message = json.decodeFromString<WebSocketMessage>(text)

        when (message.type) {
            "command" -> {
                val request = json.decodeFromString<CommandRequest>(message.payload)

                // Update context
                session.currentPath = request.context.currentPath
                if (request.context.connectedTo != null) {
                    session.connectTo(request.context.connectedTo)
                } else if (session.connectedNode != null && request.context.connectedTo == null) {
                    session.disconnect()
                }

                // Execute command
                val result = commandRegistry.execute(session, request.command)

                // Send response
                val response = WebSocketResponse(
                    type = "command_result",
                    payload = json.encodeToString(result.toResponse())
                )
                socket.send(Frame.Text(json.encodeToString(response)))
            }

            "ping" -> {
                val response = WebSocketResponse(
                    type = "pong",
                    payload = json.encodeToString(mapOf("time" to System.currentTimeMillis()))
                )
                socket.send(Frame.Text(json.encodeToString(response)))
            }

            "state_request" -> {
                val response = WebSocketResponse(
                    type = "state_update",
                    payload = json.encodeToString(session.toSnapshot())
                )
                socket.send(Frame.Text(json.encodeToString(response)))
            }

            else -> {
                val response = WebSocketResponse(
                    type = "error",
                    payload = json.encodeToString(mapOf("error" to "Unknown message type: ${message.type}"))
                )
                socket.send(Frame.Text(json.encodeToString(response)))
            }
        }
    } catch (e: Exception) {
        println("[WebSocket] Message handling error: ${e.message}")
        val response = WebSocketResponse(
            type = "error",
            payload = Json.encodeToString(mapOf("error" to (e.message ?: "Unknown error")))
        )
        socket.send(Frame.Text(Json.encodeToString(response)))
    }
}
