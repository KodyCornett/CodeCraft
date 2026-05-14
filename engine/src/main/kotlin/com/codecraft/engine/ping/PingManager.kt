package com.codecraft.engine.ping

import com.codecraft.engine.models.PingMarker
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import org.slf4j.LoggerFactory
import java.util.UUID
import java.util.concurrent.ConcurrentHashMap
import kotlin.time.Duration.Companion.seconds

/**
 * Manages footprint pings left by players as they move through the node web.
 *
 * Ping duration = max(30, 300 − firewall × 15) seconds.
 * A background coroutine purges expired pings every 30 seconds.
 */
class PingManager(scope: CoroutineScope) {

    private val log = LoggerFactory.getLogger(PingManager::class.java)
    private val pings = ConcurrentHashMap<String, PingMarker>()

    companion object {
        const val BASE_DURATION_SECONDS = 300L
        const val FIREWALL_REDUCTION_PER_LEVEL = 15L
        const val MIN_DURATION_SECONDS = 30L
        const val LOUD_DURATION_SECONDS = 600L  // cloak-expiry pings last 10 minutes
    }

    init {
        scope.launch {
            while (true) {
                delay(30.seconds)
                purgeExpired()
            }
        }
    }

    /**
     * Drops a ping on [nodeId] for [playerId].
     *
     * @param firewallLevel  The player's effective firewall stat (used to shorten duration).
     * @param isLoud         true for cloak-expiry and bounty pings (longer, more visible).
     */
    fun addPing(playerId: String, nodeId: String, firewallLevel: Int, isLoud: Boolean = false): PingMarker {
        val now = System.currentTimeMillis()
        val durationSeconds = if (isLoud) {
            LOUD_DURATION_SECONDS
        } else {
            maxOf(MIN_DURATION_SECONDS, BASE_DURATION_SECONDS - firewallLevel * FIREWALL_REDUCTION_PER_LEVEL)
        }

        val marker = PingMarker(
            pingId    = UUID.randomUUID().toString(),
            nodeId    = nodeId,
            playerId  = playerId,
            createdAt = now,
            expiresAt = now + durationSeconds * 1000L,
            isLoud    = isLoud,
        )
        pings[marker.pingId] = marker
        return marker
    }

    /** All non-expired pings on nodes in a given district (identified by a set of node IDs). */
    fun getActivePingsForDistrict(districtNodeIds: Set<String>): List<PingMarker> {
        val now = System.currentTimeMillis()
        return pings.values.filter { it.expiresAt > now && it.nodeId in districtNodeIds }
    }

    /** All non-expired pings — useful for broadcasting after a move. */
    fun getActivePings(): List<PingMarker> {
        val now = System.currentTimeMillis()
        return pings.values.filter { it.expiresAt > now }
    }

    private fun purgeExpired() {
        val now = System.currentTimeMillis()
        val before = pings.size
        pings.values.removeIf { it.expiresAt <= now }
        val removed = before - pings.size
        if (removed > 0) log.debug("PingManager purged $removed expired pings")
    }
}
