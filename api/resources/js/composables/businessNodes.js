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
