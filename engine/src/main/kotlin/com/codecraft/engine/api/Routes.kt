package com.codecraft.engine.api

import com.codecraft.engine.command.CommandRegistry
import com.codecraft.engine.domain.Node
import com.codecraft.engine.network.discovery.DiscoveryManager
import com.codecraft.engine.network.gateway.GatewayManager
import com.codecraft.engine.network.generation.PlayerSpawnService
import com.codecraft.engine.network.generation.WorldBootstrap
import com.codecraft.engine.network.persistence.ConnectionRepository
import com.codecraft.engine.network.persistence.DiscoveryRepository
import com.codecraft.engine.network.persistence.DistrictRepository
import com.codecraft.engine.network.persistence.NodeRepository
import com.codecraft.engine.network.persistence.PositionRepository
import com.codecraft.engine.persistence.GameDatabase
import com.codecraft.engine.protocol.CommandRequest
import com.codecraft.engine.protocol.NetworkConnectionData
import com.codecraft.engine.protocol.NetworkNodeData
import com.codecraft.engine.protocol.NetworkStateResponse
import com.codecraft.engine.protocol.SessionRequest
import com.codecraft.engine.protocol.SessionResponse
import com.codecraft.engine.puzzle.PuzzleGenerator
import com.codecraft.engine.session.PendingPuzzle
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
    // Phase 3 network repositories
    val db = GameDatabase.database
    val districtRepository = DistrictRepository(db)
    val nodeRepository = NodeRepository(db)
    val connectionRepository = ConnectionRepository(db)
    val discoveryRepository = DiscoveryRepository(db)
    val discoveryManager = DiscoveryManager(discoveryRepository, nodeRepository)
    val positionRepository = PositionRepository(db)
    val gatewayManager = GatewayManager(nodeRepository, discoveryManager)

    // Phase 3.5: bootstrap the city on first startup and spawn service
    WorldBootstrap(districtRepository, nodeRepository, connectionRepository).bootstrapIfEmpty()
    val playerSpawnService = PlayerSpawnService(nodeRepository, discoveryManager, positionRepository)

    val commandRegistry = CommandRegistry(
        repository = sessionManager.getRepository(),
        nodeRepository = nodeRepository,
        connectionRepository = connectionRepository,
        discoveryManager = discoveryManager,
        positionRepository = positionRepository,
        gatewayManager = gatewayManager
    )

    routing {
        // Health check
        get("/health") {
            call.respond(HealthResponse(
                status = "ok",
                engine = "CodeCraft",
                version = "0.1.0",
                sessions = sessionManager.sessionCount()
            ))
        }

        // Dev-only: seed Phase 3 test data for a session
        post("/dev/seed-network/{sessionId}") {
            val sessionId = call.parameters["sessionId"]
                ?: return@post call.respond(HttpStatusCode.BadRequest, "missing sessionId")
            try {
                val generator = com.codecraft.engine.network.generation.NetworkTestDataGenerator(
                    com.codecraft.engine.network.naming.NodeNameGenerator(),
                    nodeRepository,
                    connectionRepository
                )
                val nodes = generator.generateTestDataset(20)
                nodes.take(5).forEach { node ->
                    try {
                        discoveryManager.discoverNode(sessionId, node)
                    } catch (e: Exception) {
                        println("[Seed] Could not discover node ${node.nodeName}: ${e.message}")
                    }
                }
                val firstNodeName = nodes.firstOrNull()?.let { node ->
                    try {
                        discoveryManager.updateState(sessionId, node.nodeId, com.codecraft.engine.network.domain.DiscoveryState.CONNECTED)
                        positionRepository.updatePosition(sessionId, node.nodeId)
                        node.nodeName
                    } catch (e: Exception) {
                        println("[Seed] Could not set position: ${e.message}")
                        null
                    }
                }
                call.respond(mapOf("seeded" to "${nodes.size}", "startNode" to (firstNodeName ?: "none")))
            } catch (e: Exception) {
                println("[Seed] Error: ${e.message}")
                e.printStackTrace()
                call.respond(HttpStatusCode.InternalServerError, mapOf("error" to (e.message ?: "unknown")))
            }
        }

        // API routes
        route("/api") {
            // Execute command (REST alternative to WebSocket)
            post("/command") {
                val request = call.receive<CommandRequest>()

                val session = sessionManager.getOrCreateSession(request.sessionId)
                playerSpawnService.spawnPlayer(session.sessionId)

                // Update session context
                session.currentPath = request.context.currentPath
                if (request.context.connectedTo != null) {
                    session.connectTo(request.context.connectedTo)
                }

                // Execute command
                val result = commandRegistry.execute(session, request.command)

                // Persist session state to database
                sessionManager.saveSession(request.sessionId)

                // Return response
                call.respond(result.toResponse())
            }

            // Session management
            post("/session") {
                val request = call.receive<SessionRequest>()

                when (request.action) {
                    "create" -> {
                        val session = sessionManager.createSession()
                        playerSpawnService.spawnPlayer(session.sessionId)
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
                        request.sessionId?.let { sessionManager.saveSession(it) }
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

            // ============================================
            // Network state endpoint (for node-manager.js)
            // ============================================
            get("/network-state/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getSession(sessionId)
                    ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                // Collect all visible node IDs (discovered + public)
                val allVisibleNodeIds = session.player.discoveredNodes.toMutableSet()
                session.network.getAllNodes()
                    .filter { it.isPublic }
                    .forEach { allVisibleNodeIds.add(it.id) }

                val discoveredNodes = allVisibleNodeIds.mapNotNull { nodeId ->
                    session.network.getNode(nodeId)?.let { node ->
                        NetworkNodeData(
                            id = if (node.id == "localhost") "local" else node.id,
                            name = node.name,
                            ip = node.ip,
                            type = node.type.name.lowercase(),
                            status = when {
                                node.id == "localhost" -> "owned"
                                node.isPublic && node.compromised -> "owned"
                                node.compromised || node.id in session.nodeAccess -> "compromised"
                                node.id in session.player.scannedNodes -> "scanned"
                                else -> "discovered"
                            },
                            isPublic = node.isPublic.toString(),
                            isDiscovered = (node.id in session.player.discoveredNodes).toString(),
                            securityLevel = node.securityLevel.toString()
                        )
                    }
                }

                val connections = allVisibleNodeIds.flatMap { nodeId ->
                    session.network.getNode(nodeId)?.connectedNodes
                        ?.filter { it in allVisibleNodeIds }
                        ?.map { targetId ->
                            val targetNode = session.network.getNode(targetId)
                            val isBreached = targetNode?.compromised == true || targetId in session.nodeAccess
                            NetworkConnectionData(
                                from = if (nodeId == "localhost") "local" else nodeId,
                                to = if (targetId == "localhost") "local" else targetId,
                                style = if (isBreached) "solid" else "dotted"
                            )
                        } ?: emptyList()
                }.distinctBy { "${it.from}-${it.to}" }

                val currentNodeId = session.connectedNode?.id?.let { if (it == "localhost") "local" else it } ?: "local"

                call.respond(NetworkStateResponse(
                    nodes = discoveredNodes,
                    connections = connections,
                    currentNode = currentNodeId,
                    currentNodeId = currentNodeId
                ))
            }

            // ============================================
            // Sentinel API endpoints
            // ============================================
            route("/sentinel") {
                get("/status/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val player = session.player
                    val machine = player.currentMachine

                    call.respond(SentinelStatusResponse(
                        exposure = player.exposure,
                        exposureLevel = when {
                            player.exposure >= 80 -> "CRITICAL"
                            player.exposure >= 60 -> "HIGH"
                            player.exposure >= 40 -> "MODERATE"
                            player.exposure >= 20 -> "LOW"
                            else -> "MINIMAL"
                        },
                        shieldActive = session.isShieldActive(),
                        shieldExpiresAt = if (session.isShieldActive()) session.shieldExpiresAt else null,
                        firewallCurrent = machine.firewallCurrent,
                        firewallMax = machine.firewallMax,
                        firewallStatus = session.firewallStatus,
                        damageState = machine.damageState.name,
                        activeConnections = session.getActiveTraces().map { trace ->
                            mapOf(
                                "nodeId" to trace.nodeId,
                                "nodeName" to trace.nodeName,
                                "connectedAt" to trace.connectedAt.toString()
                            )
                        },
                        incomingThreats = session.incomingThreats.map { threat ->
                            mapOf(
                                "id" to threat.id,
                                "sourceNode" to threat.sourceNode,
                                "sourceIp" to threat.sourceIp,
                                "type" to threat.type.name,
                                "severity" to when (threat.type) {
                                    com.codecraft.engine.session.ThreatType.COUNTER_HACK -> "critical"
                                    com.codecraft.engine.session.ThreatType.INTRUSION -> "high"
                                    com.codecraft.engine.session.ThreatType.SCAN -> "medium"
                                    com.codecraft.engine.session.ThreatType.PROBE -> "low"
                                },
                                "timestamp" to threat.startTime.toString(),
                                "active" to "true",
                                "progress" to threat.progress.toString(),
                                "timeRemaining" to threat.timeRemaining.toString()
                            )
                        },
                        threatCount = session.incomingThreats.size,
                        sentinelAttackActive = session.sentinelAttackActive
                    ))
                }

                get("/events/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    call.respond(mapOf("events" to session.sentinelEvents.map { event ->
                        mapOf(
                            "type" to event.type,
                            "message" to event.message,
                            "timestamp" to event.timestamp.toString(),
                            "data" to event.data
                        )
                    }))
                }

                post("/counter-hack/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@post call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val puzzle = PuzzleGenerator.generateCounterHackPuzzle()
                    session.counterHackPuzzle = PendingPuzzle(
                        nodeId = "sentinel",
                        puzzleType = puzzle.type.name,
                        question = puzzle.content,
                        answer = puzzle.solution,
                        difficulty = 2
                    )

                    session.addSentinelEvent("counter_hack_initiated", "Counter-hack puzzle generated")

                    call.respond(mapOf(
                        "success" to true,
                        "puzzle" to mapOf(
                            "type" to puzzle.type.name,
                            "content" to puzzle.content,
                            "difficulty" to puzzle.difficulty
                        )
                    ))
                }
            }

            // ============================================
            // Mission API endpoints
            // ============================================
            route("/mission") {
                // Get active mission status
                get("/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val activeMission = session.currentMission
                    if (activeMission == null) {
                        call.respond(mapOf("active" to false))
                        return@get
                    }

                    val mission = activeMission.definition
                    val contact = com.codecraft.engine.domain.ContactCatalog.getById(mission.contactId)

                    call.respond(MissionStatusResponse(
                        active = true,
                        missionId = mission.id,
                        title = mission.title,
                        contact = contact?.alias ?: "Unknown",
                        reward = activeMission.negotiatedReward,
                        startedAt = activeMission.startedAt,
                        elapsedSeconds = (System.currentTimeMillis() - activeMission.startedAt) / 1000,
                        objectives = mission.objectives.map { obj ->
                            MissionObjective(
                                id = obj.id,
                                description = obj.description,
                                type = obj.type.name,
                                completed = obj.id in activeMission.completedObjectives,
                                failed = obj.id in activeMission.failedObjectives,
                                timeLimit = obj.timeLimit,
                                threshold = obj.threshold
                            )
                        },
                        bonusObjectives = mission.bonusObjectives.map { obj ->
                            MissionObjective(
                                id = obj.id,
                                description = obj.description,
                                type = obj.type.name,
                                completed = obj.id in activeMission.bonusObjectivesCompleted,
                                failed = obj.id in activeMission.failedObjectives,
                                timeLimit = obj.timeLimit,
                                threshold = obj.threshold
                            )
                        },
                        stealthViolated = activeMission.stealthViolated,
                        peakExposure = activeMission.peakExposure,
                        detectionCount = activeMission.detectionCount,
                        failed = activeMission.failed,
                        failureReason = activeMission.failureReason,
                        isComplete = activeMission.isComplete()
                    ))
                }

                // Complete active mission
                post("/{sessionId}/complete") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@post call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val result = commandRegistry.execute(session, "mission complete")

                    call.respond(mapOf(
                        "success" to result.success,
                        "output" to result.output
                    ))
                }

                // Abandon active mission
                post("/{sessionId}/abandon") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@post call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val result = commandRegistry.execute(session, "mission abandon")

                    call.respond(mapOf(
                        "success" to result.success,
                        "output" to result.output
                    ))
                }

                // Accept a mission from the board
                post("/{sessionId}/{missionId}/accept") {
                    try {
                        val sessionId = call.parameters["sessionId"]
                            ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                        val missionId = call.parameters["missionId"]
                            ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing mission ID"))

                        val session = sessionManager.getOrCreateSession(sessionId)
                        val result = commandRegistry.execute(session, "job accept $missionId")

                        // Persist changes to database
                        sessionManager.saveSession(sessionId)

                        // Use the same toResponse() method as the command endpoint
                        call.respond(result.toResponse())
                    } catch (e: Exception) {
                        println("[Accept Mission Error] ${e.message}")
                        e.printStackTrace()
                        call.respond(HttpStatusCode.InternalServerError, mapOf(
                            "success" to false,
                            "output" to "Server error: ${e.message}"
                        ))
                    }
                }
            }

            // Get available missions
            get("/missions/available/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getOrCreateSession(sessionId)

                val availableMissions = com.codecraft.engine.domain.MissionCatalog.getAvailableMissions(
                    reputation = session.player.reputation,
                    completedMissions = session.player.completedMissions
                )

                val items = availableMissions.map { mission ->
                    val contact = com.codecraft.engine.domain.ContactCatalog.getById(mission.contactId)
                    AvailableMissionItem(
                        id = mission.id,
                        title = mission.title,
                        contact = contact?.alias ?: "Unknown",
                        description = mission.description,
                        briefing = mission.briefing,
                        baseReward = mission.baseReward,
                        difficulty = mission.difficulty,
                        requiredReputation = mission.requiredReputation,
                        estimatedTimeMinutes = mission.estimatedTimeMinutes
                    )
                }
                call.respond(AvailableMissionsResponse(missions = items))
            }

            // Get job offers
            get("/jobs/{sessionId}") {
                val sessionId = call.parameters["sessionId"]
                    ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                val session = sessionManager.getSession(sessionId)
                    ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                val repository = sessionManager.getRepository()
                val offers = repository?.loadAllJobOffers(session.player.id) ?: emptyList()

                call.respond(mapOf("offers" to offers.map { offer ->
                    mapOf(
                        "missionId" to offer.mission.id,
                        "contactId" to offer.contact.id,
                        "contactName" to offer.contact.alias,
                        "baseOfferPercentage" to offer.baseOfferPercentage,
                        "currentOfferPercentage" to offer.currentOfferPercentage,
                        "maxOfferPercentage" to offer.maxOfferPercentage,
                        "negotiationAttempts" to offer.negotiationAttempts,
                        "maxAttempts" to offer.maxAttempts,
                        "rejected" to offer.rejected,
                        "baseReward" to offer.mission.baseReward,
                        "currentReward" to (offer.mission.baseReward * offer.currentOfferPercentage) / 100
                    )
                }))
            }

            // ============================================
            // Firewall API endpoints
            // ============================================
            route("/firewall") {
                get("/status/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    val machine = session.player.currentMachine
                    val traceCount = session.connectionHistory.size
                    val isOnLocalhost = session.connectedNode == null

                    call.respond(FirewallStatusResponse(
                        firewallCurrent = machine.firewallCurrent,
                        firewallMax = machine.firewallMax,
                        damageState = machine.damageState.name,
                        status = session.firewallStatus,
                        traceCount = traceCount,
                        isOnLocalhost = isOnLocalhost,
                        defragActive = session.defragState != null,
                        shieldActive = session.isShieldActive()
                    ))
                }

                post("/repair/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@post call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@post call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    if (session.firewallStatus != "damaged") {
                        call.respond(mapOf("success" to false, "error" to "Firewall not damaged"))
                        return@post
                    }

                    val repairCost = 500
                    if (!session.player.spendCredits(repairCost)) {
                        call.respond(mapOf("success" to false, "error" to "Insufficient credits (need $repairCost)"))
                        return@post
                    }

                    session.firewallStatus = "repairing"
                    session.addSentinelEvent("firewall_repair", "Firewall repair initiated (cost: $repairCost)")

                    // Repair firewall immediately for now
                    session.player.currentMachine.repairFirewall(session.player.currentMachine.firewallMax)
                    session.firewallStatus = "active"
                    session.addSentinelEvent("firewall_repaired", "Firewall fully repaired")

                    sessionManager.saveSession(sessionId)

                    call.respond(mapOf(
                        "success" to true,
                        "firewallCurrent" to session.player.currentMachine.firewallCurrent,
                        "firewallMax" to session.player.currentMachine.firewallMax,
                        "status" to session.firewallStatus,
                        "creditsRemaining" to session.player.credits
                    ))
                }
            }

            // ============================================
            // Downloads API endpoint
            // ============================================
            route("/downloads") {
                get("/{sessionId}") {
                    val sessionId = call.parameters["sessionId"]
                        ?: return@get call.respond(HttpStatusCode.BadRequest, mapOf("error" to "Missing session ID"))

                    val session = sessionManager.getSession(sessionId)
                        ?: return@get call.respond(HttpStatusCode.NotFound, mapOf("error" to "Session not found"))

                    call.respond(mapOf(
                        "downloads" to session.downloads.map { download ->
                            mapOf(
                                "file" to download.filename,
                                "sourceNode" to download.sourceNode,
                                "path" to download.path,
                                "timestamp" to download.downloadedAt
                            )
                        }
                    ))
                }
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

@Serializable
data class HealthResponse(
    val status: String,
    val engine: String,
    val version: String,
    val sessions: Int
)

@Serializable
data class SentinelStatusResponse(
    val exposure: Double,
    val exposureLevel: String,
    val shieldActive: Boolean,
    val shieldExpiresAt: Long?,
    val firewallCurrent: Int,
    val firewallMax: Int,
    val firewallStatus: String,
    val damageState: String,
    val activeConnections: List<Map<String, String>>,
    val incomingThreats: List<Map<String, String>> = emptyList(),
    val threatCount: Int,
    val sentinelAttackActive: Boolean
)

@Serializable
data class FirewallStatusResponse(
    val firewallCurrent: Int,
    val firewallMax: Int,
    val damageState: String,
    val status: String,
    val traceCount: Int,
    val isOnLocalhost: Boolean,
    val defragActive: Boolean,
    val shieldActive: Boolean
)

@Serializable
data class MissionStatusResponse(
    val active: Boolean,
    val missionId: String? = null,
    val title: String? = null,
    val contact: String? = null,
    val reward: Int? = null,
    val startedAt: Long? = null,
    val elapsedSeconds: Long? = null,
    val objectives: List<MissionObjective> = emptyList(),
    val bonusObjectives: List<MissionObjective> = emptyList(),
    val stealthViolated: Boolean = false,
    val peakExposure: Double = 0.0,
    val detectionCount: Int = 0,
    val failed: Boolean = false,
    val failureReason: String? = null,
    val isComplete: Boolean = false
)

@Serializable
data class MissionObjective(
    val id: String,
    val description: String,
    val type: String,
    val completed: Boolean,
    val failed: Boolean,
    val timeLimit: Int? = null,
    val threshold: Int? = null
)

@Serializable
data class AvailableMissionItem(
    val id: String,
    val title: String,
    val contact: String,
    val description: String,
    val briefing: String,
    val baseReward: Int,
    val difficulty: Int,
    val requiredReputation: Int,
    val estimatedTimeMinutes: Int
)

@Serializable
data class AvailableMissionsResponse(
    val missions: List<AvailableMissionItem>
)
