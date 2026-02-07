package com.codecraft.engine.session

import java.util.UUID
import java.util.concurrent.ConcurrentHashMap

/**
 * Manages all active game sessions.
 */
class SessionManager {
    private val sessions = ConcurrentHashMap<String, GameSession>()

    /**
     * Create a new session
     */
    fun createSession(): GameSession {
        val sessionId = UUID.randomUUID().toString()
        val session = GameSession(sessionId)
        sessions[sessionId] = session
        println("[SessionManager] Created session: $sessionId")
        return session
    }

    /**
     * Get existing session or create new one
     */
    fun getOrCreateSession(sessionId: String?): GameSession {
        if (sessionId != null && sessions.containsKey(sessionId)) {
            return sessions[sessionId]!!
        }
        return createSession()
    }

    /**
     * Get session by ID
     */
    fun getSession(sessionId: String): GameSession? {
        return sessions[sessionId]
    }

    /**
     * Remove a session
     */
    fun removeSession(sessionId: String) {
        sessions.remove(sessionId)
        println("[SessionManager] Removed session: $sessionId")
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
     * Clean up inactive sessions (could be called periodically)
     */
    fun cleanupInactiveSessions(maxAgeMs: Long = 3600000) { // 1 hour default
        // TODO: Track last activity time and clean up old sessions
    }
}
