package com.codecraft.engine.api

import com.codecraft.engine.command.CommandRegistry
import com.codecraft.engine.protocol.CommandRequest
import com.codecraft.engine.protocol.SessionRequest
import com.codecraft.engine.protocol.SessionResponse
import com.codecraft.engine.session.SessionManager
import io.ktor.http.*
import io.ktor.server.application.*
import io.ktor.server.request.*
import io.ktor.server.response.*
import io.ktor.server.routing.*
import kotlinx.serialization.Serializable

/**
 * Configure REST API routes
 */
fun Application.configureRoutes(sessionManager: SessionManager) {
    val commandRegistry = CommandRegistry()

    routing {
        // Health check
        get("/health") {
            call.respond(mapOf(
                "status" to "ok",
                "engine" to "CodeCraft",
                "version" to "0.1.0",
                "sessions" to sessionManager.sessionCount()
            ))
        }

        // API routes
        route("/api") {
            // Execute command (REST alternative to WebSocket)
            post("/command") {
                val request = call.receive<CommandRequest>()

                val session = sessionManager.getOrCreateSession(request.sessionId)

                // Update session context
                session.currentPath = request.context.currentPath
                if (request.context.connectedTo != null) {
                    session.connectTo(request.context.connectedTo)
                }

                // Execute command
                val result = commandRegistry.execute(session, request.command)

                // Return response
                call.respond(result.toResponse())
            }

            // Session management
            post("/session") {
                val request = call.receive<SessionRequest>()

                when (request.action) {
                    "create" -> {
                        val session = sessionManager.createSession()
                        call.respond(SessionResponse(
                            success = true,
                            sessionId = session.sessionId,
                            state = session.toSnapshot()
                        ))
                    }
                    "load" -> {
                        val session = request.sessionId?.let { sessionManager.getSession(it) }
                        if (session != null) {
                            call.respond(SessionResponse(
                                success = true,
                                sessionId = session.sessionId,
                                state = session.toSnapshot()
                            ))
                        } else {
                            call.respond(HttpStatusCode.NotFound, SessionResponse(
                                success = false,
                                error = "Session not found"
                            ))
                        }
                    }
                    "save" -> {
                        // TODO: Implement session persistence
                        call.respond(SessionResponse(
                            success = true,
                            sessionId = request.sessionId
                        ))
                    }
                    else -> {
                        call.respond(HttpStatusCode.BadRequest, SessionResponse(
                            success = false,
                            error = "Unknown action: ${request.action}"
                        ))
                    }
                }
            }

            // Get player state
            get("/session/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getSession(sessionId)
                    ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                call.respond(session.toSnapshot())
            }

            // Get available commands for session
            get("/commands/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getSession(sessionId)
                    ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                val commands = commandRegistry.getAll()
                    .filter { session.player.hasCommand(it.name) }
                    .map { CommandInfo(it.name, it.description, it.usage, it.category.name) }

                call.respond(mapOf("commands" to commands))
            }

            // Get network nodes
            get("/network/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getSession(sessionId)
                    ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                // Only return discovered nodes
                val nodes = session.network.getAllNodes()
                    .filter { it.id in session.player.discoveredNodes }
                    .map { node ->
                        mapOf(
                            "id" to node.id,
                            "name" to node.name,
                            "ip" to node.ip,
                            "type" to node.type.name,
                            "compromised" to node.compromised,
                            "securityLevel" to node.securityLevel
                        )
                    }

                call.respond(mapOf("nodes" to nodes))
            }
        }
    }
}

@Serializable
data class CommandInfo(
    val name: String,
    val description: String,
    val usage: String,
    val category: String
)
