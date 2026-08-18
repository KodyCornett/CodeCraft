// ─── useNodeIdentity.js ───────────────────────────────────────────────────────
//
//  Deterministic identity generation for map nodes.
//  Same node ID always produces the same network name and SPLICE address.
//
//  SPLICE address format:  [ZONE].[HASH]
//    ZONE — district zone code (2-digit, position-derived)
//    HASH — 4-char hex node signature
//
//  Examples:
//    14.A3F9  →  North Spokane node
//    35.7B2A  →  Downtown node
//    02.9F31  →  Neighborhood node (row 2)
//    00.4E82  →  Transit relay / NetLink node
//
// ─────────────────────────────────────────────────────────────────────────────

import { getBusinessNetworkName } from './businessNodes.js';

// ── Deterministic hash ────────────────────────────────────────────────────────
function djb2(str) {
    let hash = 5381;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) + hash) ^ str.charCodeAt(i);
        hash = hash | 0;
    }
    return Math.abs(hash);
}

// ── District zone codes ───────────────────────────────────────────────────────
// Loosely geographic: north = smaller, south/east = larger. No collisions.
const DISTRICT_ZONE = {
    'North Spokane':       14,
    'Spokane Valley':      21,
    'Downtown':            35,
    "Browne's Addition":   49,
    'University District': 63,
};

// ── Name pools per district ───────────────────────────────────────────────────
const DISTRICT_NAMES = {
    'North Spokane': [
        'WHITWORTH', 'SHADLE', 'FIVE_MILE', 'NORTHGATE',
        'HILLYARD', 'FRANCIS', 'WANDERMERE', 'AUDUBON',
    ],
    'Downtown': [
        'DAVENPORT', 'RIVERVIEW', 'STEAM_PLANT', 'FOX_THEATRE',
        'SPOKANE_CLUB', 'CITY_HALL', 'MONROE_BRIDGE', 'FLOUR_MILL',
    ],
    "Browne's Addition": [
        'CANNON', 'PACIFIC', 'COEUR_DALENE', 'ARTHUR',
        'BROWNES', 'GRAND_AVE', 'PACIFIC_PARK',
    ],
    'University District': [
        'GONZAGA', 'RIVERPOINT', 'HAMILTON', 'BOONE',
        'MISSION_QUAD', 'SHARP_AVE', 'PACIFIC_UNIV',
    ],
    'Spokane Valley': [
        'VALLEY_MALL', 'SPRAGUE', 'SULLIVAN', 'DISHMAN',
        'MIRABEAU', 'EAST_VALLEY', 'PINES',
    ],
};

const RELAY_NAMES = [
    'RELAY', 'BACKBONE', 'TRANSIT', 'TRUNK',
    'SPLICE', 'BRIDGE', 'NEXUS', 'GATEWAY',
    'UPLINK', 'CROSSLINK', 'JUNCTION', 'CONDUIT',
];

const NAME_SUFFIXES = [
    '_MESH', '_SECURE', '_WIFI', '_IOT',
    '_NET', '_NODE', '_GRID', '_HUB',
];

// ── Zone code ─────────────────────────────────────────────────────────────────
function getZoneCode(node) {
    // Named district
    if (node.district && DISTRICT_ZONE[node.district] !== undefined) {
        return DISTRICT_ZONE[node.district].toString().padStart(2, '0');
    }

    // Neighborhood — district prop is the cell label, e.g. 'F2', 'M7'
    // Use the numeric row part as the zone
    if (node.district) {
        const match = /[A-Za-z]+(\d+)/.exec(node.district);
        if (match) return parseInt(match[1]).toString().padStart(2, '0');
    }

    // Transit relay / NetLink node
    return '00';
}

// ── Network name ──────────────────────────────────────────────────────────────
export function getNetworkName(node) {
    // 15 hand-picked nodes (see businessNodes.js) show a Codex-thread
    // company's network name instead of a generated one — cosmetic only,
    // canvas_id/ICE/rewards/SPLICE address are all untouched.
    const businessName = getBusinessNetworkName(node.canvasId);
    if (businessName) return businessName;

    const h    = djb2(node.id);
    const pool = (node.district && DISTRICT_NAMES[node.district])
        ? DISTRICT_NAMES[node.district]
        : RELAY_NAMES;

    const base   = pool[h % pool.length];
    const suffix = NAME_SUFFIXES[(h >>> 3) % NAME_SUFFIXES.length];
    const num    = ((h >>> 6) % 99 + 1).toString().padStart(2, '0');

    return `${base}${suffix}_${num}`;
}

// ── SPLICE address ────────────────────────────────────────────────────────────
export function getSpliceAddress(node) {
    const h    = djb2(node.id + '_s');
    const zone = getZoneCode(node);
    const hash = (h & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');

    return `${zone}.${hash}`;
}

// ── Convenience export ────────────────────────────────────────────────────────
export function getNodeIdentity(node) {
    return {
        networkName:   getNetworkName(node),
        spliceAddress: getSpliceAddress(node),
        zoneCode:      getZoneCode(node),
    };
}

// ── Reverse lookup — Splice Site search ────────────────────────────────────────
// Everything above only ever goes node → identity. This is the other
// direction: given whatever a player typed, find the node(s) it matches.
// Backs the Splice Site map page (see useNodeTracking.js) — nothing else
// in the identity system needs this, so it stays isolated down here.

/**
 * Search a list of nodes by SPLICE address (exact, case-insensitive) or by
 * network name (partial, case-insensitive substring — matches a business
 * name from businessNodes.js same as it matches a generated name, since
 * getNetworkName() already prefers the business name when one exists).
 *
 * Address matches are exact by design — the address is meant to feel like
 * a real credential a player copies out of a document, not a fuzzy guess.
 * Name matches are substring so a partial "AVISTA" still finds
 * "AVISTA_CORP_NET". Address hits are returned first.
 *
 * Returns an array of { node, identity } pairs, not bare nodes, so callers
 * don't have to recompute the identity a second time to display it.
 */
export function searchNodes(nodes, query) {
    const q = (query ?? '').trim().toUpperCase();
    if (!q) return [];

    const addressHits = [];
    const nameHits    = [];

    for (const node of nodes) {
        const identity = getNodeIdentity(node);
        if (identity.spliceAddress.toUpperCase() === q) {
            addressHits.push({ node, identity });
            continue;
        }
        if (identity.networkName.toUpperCase().includes(q)) {
            nameHits.push({ node, identity });
        }
    }

    return [...addressHits, ...nameHits];
}
