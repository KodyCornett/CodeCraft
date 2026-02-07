package com.codecraft.engine

import com.codecraft.engine.api.configureRoutes
import com.codecraft.engine.api.configureWebSockets
import com.codecraft.engine.session.SessionManager
import io.ktor.serialization.kotlinx.json.*
import io.ktor.server.application.*
import io.ktor.server.engine.*
import io.ktor.server.netty.*
import io.ktor.server.plugins.contentnegotiation.*
import kotlinx.serialization.json.Json

/**
 * CodeCraft Game Engine
 *
 * Kotlin/Ktor server that handles:
 * - Command parsing and execution
 * - Game state simulation
 * - Trace/exposure level calculations
 * - WebSocket communication with Laravel frontend
 */
fun main() {
    val port = System.getenv("ENGINE_PORT")?.toIntOrNull() ?: 8085

    println("╔════════════════════════════════════════╗")
    println("║     CodeCraft Game Engine v0.1.0       ║")
    println("╠════════════════════════════════════════╣")
    println("║  Starting on port $port                  ║")
    println("╚════════════════════════════════════════╝")

    embeddedServer(Netty, port = port, host = "0.0.0.0") {
        configureEngine()
    }.start(wait = true)
}

fun Application.configureEngine() {
    // JSON serialization
    install(ContentNegotiation) {
        json(Json {
            prettyPrint = true
            isLenient = true
            ignoreUnknownKeys = true
        })
    }

    // Session manager (singleton for now, would be injected in production)
    val sessionManager = SessionManager()

    // Configure WebSocket support
    configureWebSockets(sessionManager)

    // Configure REST routes
    configureRoutes(sessionManager)

    log.info("CodeCraft Engine configured and ready")
}
