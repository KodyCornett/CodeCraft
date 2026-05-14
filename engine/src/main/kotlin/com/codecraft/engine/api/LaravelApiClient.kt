package com.codecraft.engine.api

import com.codecraft.engine.models.DamageResult
import com.codecraft.engine.models.EffectiveStats
import com.codecraft.engine.models.NodeState
import com.codecraft.engine.models.PlayerSession
import io.ktor.client.*
import io.ktor.client.call.*
import io.ktor.client.engine.cio.*
import io.ktor.client.plugins.contentnegotiation.*
import io.ktor.client.request.*
import io.ktor.client.statement.*
import io.ktor.http.*
import io.ktor.serialization.kotlinx.json.*
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json

/**
 * HTTP client wrapper for all calls to the Laravel API.
 *
 * Base URL and bearer token are read from environment variables:
 *   LARAVEL_API_URL   — e.g., "http://localhost:8000"
 *   LARAVEL_API_TOKEN — Sanctum plain-text token
 *
 * The engine never writes to a database. Every persistent state change
 * (damage, combat results, resource depletion) is a POST to Laravel.
 */
class LaravelApiClient {

    private val baseUrl: String = System.getenv("LARAVEL_API_URL")
        ?: "http://localhost:8000"

    private val token: String = System.getenv("LARAVEL_API_TOKEN")
        ?: error("LARAVEL_API_TOKEN environment variable is not set")

    private val client = HttpClient(CIO) {
        install(ContentNegotiation) {
            json(Json {
                ignoreUnknownKeys = true
                coerceInputValues = true
            })
        }
    }

    /** GET with auth + Accept headers pre-applied. */
    private suspend fun apiGet(path: String): HttpResponse =
        client.get("$baseUrl/api/$path") {
            bearerAuth(token)
            header(HttpHeaders.Accept, ContentType.Application.Json)
        }

    /** POST with auth + Accept + Content-Type headers pre-applied. */
    private suspend fun apiPost(path: String, block: HttpRequestBuilder.() -> Unit): HttpResponse =
        client.post("$baseUrl/api/$path") {
            bearerAuth(token)
            header(HttpHeaders.Accept, ContentType.Application.Json)
            contentType(ContentType.Application.Json)
            block()
        }

    // -------------------------------------------------------------------------
    // Player state
    // -------------------------------------------------------------------------

    /**
     * Loads the full player state from Laravel.
     * Called once per session on WebSocket connect to hydrate [PlayerSession].
     */
    suspend fun getPlayerStatus(playerId: String): PlayerSession {
        @Serializable
        data class RigStats(
            val effective: Int = 0,
        )
        @Serializable
        data class StatsPayload(
            val os: RigStats = RigStats(),
            val ram: RigStats = RigStats(),
            val cpu: RigStats = RigStats(),
            val storage: RigStats = RigStats(),
            val firewall: RigStats = RigStats(),
        )
        @Serializable
        data class RigPayload(
            val current_ss: Int = 0,
            val stats: StatsPayload? = null,
        )
        @Serializable
        data class PlayerPayload(
            val id: String,
            val handle: String,
            val current_node_id: String? = null,
            val is_limping: Boolean = false,
            val post_combat_silent_moves: Int = 0,
            val bounty_level: Int = 0,
        )
        @Serializable
        data class StatusResponse(
            val player: PlayerPayload,
            val rig: RigPayload? = null,
        )

        val response: StatusResponse = apiGet("player/$playerId/status").body()

        val stats = response.rig?.stats
        return PlayerSession(
            playerId              = response.player.id,
            handle                = response.player.handle,
            currentNodeId         = response.player.current_node_id ?: "",
            isLimping             = response.player.is_limping,
            postCombatSilentMoves = response.player.post_combat_silent_moves,
            bountyLevel           = response.player.bounty_level,
            cpuCyclesRemaining    = stats?.cpu?.effective ?: 0,
            effectiveStats        = EffectiveStats(
                os       = stats?.os?.effective ?: 0,
                ram      = stats?.ram?.effective ?: 0,
                cpu      = stats?.cpu?.effective ?: 0,
                storage  = stats?.storage?.effective ?: 0,
                firewall = stats?.firewall?.effective ?: 0,
            ),
        )
    }

    // -------------------------------------------------------------------------
    // Damage
    // -------------------------------------------------------------------------

    /**
     * Reduces a player's SS in Laravel.
     * @param source "pve" or "pvp"
     */
    suspend fun applyDamage(playerId: String, amount: Int, source: String): DamageResult {
        return apiPost("rig/damage") {
            setBody(mapOf(
                "player_id" to playerId,
                "amount"    to amount,
                "source"    to source,
            ))
        }.body()
    }

    // -------------------------------------------------------------------------
    // Combat result
    // -------------------------------------------------------------------------

    /**
     * Reports a resolved combat outcome to Laravel.
     * Laravel handles stat loss, cred steal, bounty updates, and silent mode.
     */
    suspend fun reportCombatResult(winnerId: String, loserId: String, nodeId: String) {
        apiPost("combat/result") {
            setBody(mapOf(
                "winner_id" to winnerId,
                "loser_id"  to loserId,
                "node_id"   to nodeId,
            ))
        }
    }

    // -------------------------------------------------------------------------
    // Node resources
    // -------------------------------------------------------------------------

    /**
     * Marks a node resource as depleted in Laravel.
     * @param resource "creds" or "movement"
     */
    suspend fun depleteNodeResource(nodeId: String, resource: String) {
        apiPost("nodes/$nodeId/deplete") {
            setBody(mapOf("resource" to resource))
        }
    }

    // -------------------------------------------------------------------------
    // Node graph
    // -------------------------------------------------------------------------

    /**
     * Returns all nodes for a given district with their adjacency lists.
     * Used by MapManager to build the in-memory node graph on startup.
     */
    suspend fun getNodes(district: String): List<NodeState> {
        @Serializable
        data class NodePayload(
            val id: String,
            val business_name: String,
            val district: String,
            val tier: Int,
            val adjacent_node_ids: List<String>,
        )

        val response: List<NodePayload> = apiGet("nodes/$district").body()

        return response.map { n ->
            NodeState(
                id              = n.id,
                businessName    = n.business_name,
                district        = n.district,
                tier            = n.tier,
                adjacentNodeIds = n.adjacent_node_ids,
            )
        }
    }

    fun close() = client.close()
}
