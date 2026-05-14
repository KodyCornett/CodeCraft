package com.codecraft.engine.models

import kotlinx.serialization.Serializable

/**
 * Response shape from Laravel's POST /api/rig/damage.
 * The engine uses this to update the in-memory session state.
 */
@Serializable
data class DamageResult(
    val event: String? = null,       // null | "limp_mode" | "street_doc_reset"
    val rig_id: String = "",
    val is_limping: Boolean = false,
    val current_ss: Int = 0,
    val max_ss: Int = 0,
)
