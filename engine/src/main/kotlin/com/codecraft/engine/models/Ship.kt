package com.codecraft.engine.models

import kotlinx.serialization.Serializable

/**
 * A ship placed on a player's grid.
 *
 * Squares are the coordinates occupied. hitSquares tracks which have been hit.
 * isSunk = hitSquares.size == squares.size.
 *
 * Special flags:
 *   hasTrojan        — TrojanCommand was planted here; triggers on sink
 *   sqlInjectionActive — SqlInjectionCommand applied; first hit reads as miss
 *   sqlInjectionUsed   — false miss already consumed
 */
@Serializable
data class Ship(
    val shipId: String,
    val squares: List<Coordinate>,
    val hitSquares: Set<Coordinate> = emptySet(),
    val hasTrojan: Boolean = false,
    val sqlInjectionActive: Boolean = false,
    val sqlInjectionUsed: Boolean = false,
) {
    val isSunk: Boolean get() = hitSquares.size >= squares.size

    fun hit(coord: Coordinate): Ship {
        if (coord !in squares) return this
        return copy(hitSquares = hitSquares + coord)
    }
}
