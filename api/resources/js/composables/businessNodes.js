/**
 * businessNodes
 *
 * 15 hand-picked map nodes (one per Codex-thread company) that display a
 * company network name instead of the usual procedurally-generated one from
 * useNodeIdentity.js. Purely cosmetic — canvas_id, ICE, rewards, and every
 * other node field are completely unaffected. A node's SPLICE address
 * (getSpliceAddress()) still uses the normal hash-based technical
 * designation; only the human-readable network name changes.
 *
 * Placement was chosen to fit each company's identity rather than picked at
 * random:
 *   - Downtown: A.V.I.S.T.A. (Monroe St. Bridge / Substation 09 is the
 *     anchor of the whole Codex thread), Providence, W.W.P., S.I.N.
 *   - University District: G.O.N.Z.A.G.A.
 *   - Spokane Valley: S.T.A. (its own flavor text calls out the "Valley
 *     Corridor" line)
 *   - Browne's Addition: C.O.P.P.E.R.H.E.A.D. and Inland (both tied to
 *     Knuckle's territory in their flavor text)
 *   - North Spokane: I.B.J. and The Valley Voice (whose own copy explicitly
 *     covers Hillyard, which lives in this district)
 *   - Neighborhood cells: S.T.I.T.C.H.E.R.S., S.P.E.C.T.R.E., WIRE-DEAD —
 *     fringe/underground entities away from the named civic districts
 *   - Netlink waypoints: I.T.R.O.N. and N.U.L.L. — both are conceptually
 *     decentralized (a citywide sensor mesh; a leaderless forum), so an
 *     unnamed relay/junction node fits better than any single district
 *
 * Every canvas_id below is a real 'action'-type node from NodeSeeder.php —
 * none are CyberDoc hubs (which already carry their own NPC identity).
 */
export const BUSINESS_NODES = {
    'DT-v1':      'AVISTA_CORP_NET',
    'DT-v5':      'PROVIDENCE_HEALTH_NET',
    'DT-v9':      'WWP_ARCHIVE_NET',
    'DT-v13':     'SIN_MEDIA_NET',
    'UD-v3':      'GONZAGA_RESEARCH_NET',
    'SV-v5':      'STA_TRANSIT_NET',
    'BA-v3':      'COPPERHEAD_YARD_NET',
    'BA-v9':      'INLAND_LEASING_NET',
    'NS-v5':      'IBJ_FINANCIAL_NET',
    'NS-v11':     'VALLEY_VOICE_NET',
    'I3-v2':      'STITCHERS_MARKET_NET',
    'J1-v3':      'SPECTRE_CELL_NET',
    'M7-v0':      'WIRE_DEAD_NET',
    'wp_1_-13':   'ITRON_MESH_NET',
    'wp_6_-2':    'NULL_RELAY_NET',
};

/**
 * The business network name for this node's canvas_id, or null if it's not
 * one of the 15 business nodes.
 */
export function getBusinessNetworkName(canvasId) {
    return BUSINESS_NODES[canvasId] ?? null;
}

/**
 * BANK_TARGET_NODES
 *
 * Same cosmetic-only mechanism as BUSINESS_NODES above, for the 19-target
 * Bank Heist roster (see BANK_TARGET_ROSTER.md). canvas_id/ICE/rewards are
 * untouched — only the human-readable network name changes. Kept as a
 * separate map from BUSINESS_NODES since these are a different content
 * system (Bank Heist, not the Codex thread), not because the mechanism
 * differs.
 *
 * Placement: Tier 1 (retail/community) in low-ICE cells and Browne's
 * Addition; Tier 2 (neo-tech/fast-yield) spread across North Spokane,
 * Spokane Valley, University District, and a mid-ICE cell; Tier 3
 * (institutional/high-net-worth) in Downtown, the map's highest-ICE named
 * district; Tier 4 (apex/specialized) in Downtown's remaining slots plus
 * scattered cells and one waypoint for Black-Tide Liquidity's underground
 * flavor. None of the 19 collide with the 15 BUSINESS_NODES canvas_ids.
 */
export const BANK_TARGET_NODES = {
    // Tier 1
    'BA-v14': 'FIRSTMETRO_FCU_NET',
    'G11-v2': 'SOLIS_LENDING_NET',
    'F9-v2':  'VANTAGE_POINT_NET',
    // Tier 2
    'NS-v9':  'AETHER_NEOBANK_NET',
    'SV-v9':  'BLUESKY_FUNDS_NET',
    'UD-v7':  'HYPERION_VC_NET',
    'I5-v2':  'PENSION_DIRECT_NET',
    // Tier 3
    'DT-v3':  'IRONCLAD_TRUST_NET',
    'DT-v7':  'AEGIS_WEALTH_NET',
    'DT-v11': 'KUROGANE_FLEET_NET',
    'DT-v15': 'ZENJIN_ASSETS_NET',
    'DT-v17': 'HORIZON_MUTUAL_NET',
    // Tier 4
    'DT-v19':   'APEX_CAPITAL_NET',
    'DT-v21':   'CHRONOS_QUANT_NET',
    'H7-v3':    'HORIZON_SOVEREIGN_NET',
    'I3-v4':    'VERITAS_CUSTODY_NET',
    'I5-v4':    'NOVA_EXCHANGE_NET',
    'N10-v3':   'STARLIGHT_SOVEREIGN_NET',
    'wp_-5_5':  'BLACKTIDE_RELAY_NET',
};

/**
 * The Bank Heist target network name for this node's canvas_id, or null if
 * it's not one of the 19 bank/brokerage nodes.
 */
export function getBankTargetNetworkName(canvasId) {
    return BANK_TARGET_NODES[canvasId] ?? null;
}
