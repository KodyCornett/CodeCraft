package com.codecraft.engine.session

import com.codecraft.engine.persistence.PlayerRepository
import java.util.UUID
import java.util.concurrent.ConcurrentHashMap

/**
 * Manages all active game sessions.
 * Uses in-memory cache backed by database persistence.
 */
class SessionManager(
    private val repository: PlayerRepository = PlayerRepository()
) {
    private val sessions = ConcurrentHashMap<String, GameSession>()

    /**
     * Create a new session
     */
    fun createSession(): GameSession {
        val sessionId = UUID.randomUUID().toString()
        val session = GameSession(sessionId)
        sessions[sessionId] = session
        repository.savePlayer(session)
        println("[SessionManager] Created session: $sessionId")
        return session
    }

    /**
     * Get existing session or create new one.
     * If sessionId is provided, checks in-memory first, then DB.
     */
    fun getOrCreateSession(sessionId: String?): GameSession {
        if (sessionId != null) {
            // Check in-memory cache first
            sessions[sessionId]?.let { return it }

            // Check database
            val loaded = repository.loadPlayer(sessionId)
            if (loaded != null) {
                sessions[sessionId] = loaded
                println("[SessionManager] Loaded session from DB: $sessionId")
                return loaded
            }

            // Create new session with this ID
            val session = GameSession(sessionId)
            sessions[sessionId] = session
            repository.savePlayer(session)
            println("[SessionManager] Created session for external ID: $sessionId")
            return session
        }
        return createSession()
    }

    /**
     * Get session by ID
     */
    fun getSession(sessionId: String): GameSession? {
        sessions[sessionId]?.let { return it }

        // Try loading from DB
        val loaded = repository.loadPlayer(sessionId)
        if (loaded != null) {
            sessions[sessionId] = loaded
            return loaded
        }

        return null
    }

    /**
     * Save session state to database
     */
    fun saveSession(sessionId: String) {
        sessions[sessionId]?.let { session ->
            repository.saveAfterCommand(session)
        }
    }

    /**
     * Remove a session from memory (keeps DB record)
     */
    fun removeSession(sessionId: String) {
        sessions[sessionId]?.let { session ->
            repository.savePlayer(session)
        }
        sessions.remove(sessionId)
        println("[SessionManager] Removed session from memory: $sessionId")
    }

    /**
     * Get all active sessions (for admin/debug)
     */
    fun getAllSessions(): List<GameSession> {
        return sessions.values.toList()
    }

    /**
     * Get session count
     */
    fun sessionCount(): Int = sessions.size

    /**
     * Get the repository for direct access (e.g. transaction recording)
     */
    fun getRepository(): PlayerRepository = repository

    /**
     * Clean up inactive sessions — save to DB and remove from memory
     */
    fun cleanupInactiveSessions(maxAgeMs: Long = 3600000) {
        val now = System.currentTimeMillis()
        val toRemove = sessions.entries.filter { (_, session) ->
            (now - session.lastActionTime) > maxAgeMs
        }

        toRemove.forEach { (id, session) ->
            repository.savePlayer(session)
            sessions.remove(id)
            println("[SessionManager] Cleaned up inactive session: $id")
        }

        if (toRemove.isNotEmpty()) {
            println("[SessionManager] Cleaned up ${toRemove.size} inactive sessions")
        }
    }
}
