<template>
    <div class="hex-map-wrapper">
        <svg
            ref="svgEl"
            class="hex-map-svg"
            viewBox="0 0 1200 800"
            preserveAspectRatio="xMidYMid meet"
        >
            <!-- Grid lines — one line per deduplicated hex edge -->
            <g class="hex-grid-layer">
                <line
                    v-for="(edge, i) in GRID_EDGES"
                    :key="i"
                    :x1="edge.x1" :y1="edge.y1"
                    :x2="edge.x2" :y2="edge.y2"
                    class="hex-grid-edge"
                />
            </g>

            <!-- Districts — 7-hex flower clusters -->
            <g v-for="d in DISTRICTS" :key="d.name" class="district-cluster">
                <polygon
                    v-for="(hex, i) in d.hexPolygons"
                    :key="`${d.name}-hex-${i}`"
                    :points="hex.points"
                    class="cluster-hex-outline"
                />
                <circle
                    v-for="(node, i) in d.allNodes"
                    :key="`${d.name}-node-${i}`"
                    :cx="node.x" :cy="node.y"
                    r="4"
                    class="cluster-shared-node"
                />
                <circle
                    :cx="d.hub.x" :cy="d.hub.y"
                    r="6"
                    class="district-yellow-hub"
                />
                <text :x="d.labelX" :y="d.labelY" class="district-name">{{ d.name.toUpperCase() }}</text>
            </g>

            <!-- Neighborhoods — single hex cells -->
            <g v-for="(nb, i) in NEIGHBORHOODS" :key="`nb-${i}`" class="neighborhood">
                <polygon :points="nb.points" class="cluster-hex-outline" />
                <circle
                    v-for="(node, j) in nb.nodes"
                    :key="`nb-${i}-n${j}`"
                    :cx="node.x" :cy="node.y"
                    r="4"
                    class="cluster-shared-node"
                />
            </g>

            <!-- NetLinks — connections between districts and neighborhoods -->
            <g v-for="(link, li) in NET_LINKS" :key="`link-${li}`" class="net-link">
                <polyline
                    v-for="(seg, si) in link.segments"
                    :key="`link-${li}-seg-${si}`"
                    :points="seg.map(p => `${p.x},${p.y}`).join(' ')"
                    class="route-edge"
                />
                <circle
                    v-for="(pt, i) in link.points"
                    :key="`link-${li}-pt-${i}`"
                    :cx="pt.x"
                    :cy="pt.y"
                    r="4"
                    :class="pt.isJunction ? 'junction-node' : 'route-node'"
                />
            </g>

            <!-- Cell coordinate labels (column letter + row number) -->
            <g class="hex-labels-layer">
                <text
                    v-for="cell in CELL_LABELS"
                    :key="cell.label"
                    :x="cell.x"
                    :y="cell.y"
                    class="hex-cell-label"
                >{{ cell.label }}</text>
            </g>
        </svg>
    </div>
</template>

<script setup>
// ─── Props (kept for Game.vue compatibility — not rendered yet) ───────────────
defineProps({
    nodes:         { type: Array,  default: () => [] },
    links:         { type: Array,  default: () => [] },
    pings:         { type: Array,  default: () => [] },
    currentNodeId: { type: String, default: null     },
});
defineEmits(['node-clicked', 'street-doc-selected']);

// ═══════════════════════════════════════════════════════════════════════════════
// HEX LATTICE
// ═══════════════════════════════════════════════════════════════════════════════
//
//  Pointy-top hexagons, axial coordinates (q, r).
//
//  Vertex (a, b) pixel position:
//    x = CX + SIZE * (√3/2) * a
//    y = CY + SIZE * 0.5   * b
//
//  For hex cell (q, r), vertex i has:
//    a = 2q + r + da[i]
//    b = 3r     + db[i]
//
// ═══════════════════════════════════════════════════════════════════════════════

const HEX_SIZE = 48;
const GRID_CX  = 600;
const GRID_CY  = 400;

// Corner offsets — pointy-top, angles: -30°, 30°, 90°, 150°, 210°, 270°
const V_OFFSETS = [[1,-1],[1,1],[0,2],[-1,1],[-1,-1],[0,-2]];

const vKey = (a, b) => `${a},${b}`;

// ─── Vertex registry and adjacency ───────────────────────────────────────────
const VERTEX_REGISTRY = new Map(); // key → { x, y }
const ADJACENCY       = new Map(); // key → Set<key>

for (let q = -10; q <= 10; q++) {
    for (let r = -9; r <= 9; r++) {
        const cellCx = GRID_CX + HEX_SIZE * Math.sqrt(3) * (q + r / 2);
        const cellCy = GRID_CY + HEX_SIZE * 1.5 * r;
        if (cellCx < -120 || cellCx > 1320 || cellCy < -120 || cellCy > 920) continue;

        const cellKeys = V_OFFSETS.map(([da, db]) => {
            const a   = 2 * q + r + da;
            const b   = 3 * r + db;
            const key = vKey(a, b);
            if (!VERTEX_REGISTRY.has(key)) {
                VERTEX_REGISTRY.set(key, {
                    x: GRID_CX + HEX_SIZE * (Math.sqrt(3) / 2) * a,
                    y: GRID_CY + HEX_SIZE * 0.5 * b,
                });
                ADJACENCY.set(key, new Set());
            }
            return key;
        });

        for (let i = 0; i < 6; i++) {
            const k1 = cellKeys[i];
            const k2 = cellKeys[(i + 1) % 6];
            ADJACENCY.get(k1).add(k2);
            ADJACENCY.get(k2).add(k1);
        }
    }
}

// ─── Deduplicated edge list ───────────────────────────────────────────────────
const GRID_EDGES = (() => {
    const seen  = new Set();
    const edges = [];
    for (const [from, neighbors] of ADJACENCY) {
        const vf = VERTEX_REGISTRY.get(from);
        for (const to of neighbors) {
            const edgeId = from < to ? `${from}|${to}` : `${to}|${from}`;
            if (!seen.has(edgeId)) {
                seen.add(edgeId);
                const vt = VERTEX_REGISTRY.get(to);
                edges.push({ x1: vf.x, y1: vf.y, x2: vt.x, y2: vt.y });
            }
        }
    }
    return edges;
})();

// ─── Cell coordinate labels ───────────────────────────────────────────────────
//  col = q + ⌊r/2⌋, offset so A = leftmost visible column, row 1 = topmost.
const CELL_LABELS = (() => {
    const cells = [];
    for (let q = -10; q <= 10; q++) {
        for (let r = -9; r <= 9; r++) {
            const x = GRID_CX + HEX_SIZE * Math.sqrt(3) * (q + r / 2);
            const y = GRID_CY + HEX_SIZE * 1.5 * r;
            if (x < 0 || x > 1200 || y < 0 || y > 800) continue;
            cells.push({ q, r, x, y });
        }
    }
    const colVals = cells.map(c => c.q + Math.floor(c.r / 2));
    const minCol  = Math.min(...colVals);
    const minRow  = Math.min(...cells.map(c => c.r));
    return cells.map(({ q, r, x, y }) => {
        const colIdx = q + Math.floor(r / 2) - minCol;
        const rowNum = r - minRow + 1;
        return { label: `${String.fromCharCode(65 + colIdx)}${rowNum}`, x, y };
    });
})();

// ─── Hex pixel helpers ────────────────────────────────────────────────────────
const SQ3 = Math.sqrt(3);

function hexCenterPx(q, r) {
    return {
        x: GRID_CX + HEX_SIZE * SQ3 * (q + r / 2),
        y: GRID_CY + HEX_SIZE * 1.5 * r,
    };
}

function hexVertexPx(q, r, vi) {
    const [da, db] = V_OFFSETS[vi];
    return {
        x: GRID_CX + HEX_SIZE * (SQ3 / 2) * (2 * q + r + da),
        y: GRID_CY + HEX_SIZE * 0.5 * (3 * r + db),
    };
}

// ─── Map element factories ────────────────────────────────────────────────────

function createDistrict(centerCell, neighborCells, name) {
    const cells = [centerCell, ...neighborCells];
    const hub   = hexCenterPx(centerCell.q, centerCell.r);

    const hexPolygons = cells.map(({ q, r }) => ({
        points: V_OFFSETS.map((_, vi) => {
            const p = hexVertexPx(q, r, vi);
            return `${p.x},${p.y}`;
        }).join(' '),
    }));

    const vCount = new Map();
    for (const { q, r } of cells) {
        for (let vi = 0; vi < 6; vi++) {
            const [da, db] = V_OFFSETS[vi];
            const key = `${2 * q + r + da},${3 * r + db}`;
            if (!vCount.has(key)) vCount.set(key, { ...hexVertexPx(q, r, vi), n: 0 });
            vCount.get(key).n++;
        }
    }
    const allNodes = [...vCount.values()].map(({ x, y }) => ({ x, y }));

    // Place label below the cluster when the center is near the top of the viewport
    const labelY = hub.y < 150
        ? hub.y + HEX_SIZE * 2.5 + 20
        : hub.y - HEX_SIZE * 2.5 - 12;

    return { name, hexPolygons, allNodes, hub, labelX: hub.x, labelY };
}

function createNeighborhood({ q, r }) {
    return {
        points: V_OFFSETS.map((_, vi) => {
            const p = hexVertexPx(q, r, vi);
            return `${p.x},${p.y}`;
        }).join(' '),
        nodes: V_OFFSETS.map((_, vi) => hexVertexPx(q, r, vi)),
    };
}

// ─── Districts ────────────────────────────────────────────────────────────────
const DISTRICTS = [
    createDistrict(
        { q: -3, r: -4 },
        [{ q: -2, r: -4 }, { q: -3, r: -3 }, { q: -4, r: -3 },
         { q: -4, r: -4 }, { q: -3, r: -5 }, { q: -2, r: -5 }],
        'North Spokane',
    ),
    createDistrict(
        { q: -6, r:  3 },
        [{ q: -5, r:  3 }, { q: -6, r:  4 }, { q: -7, r:  4 },
         { q: -7, r:  3 }, { q: -6, r:  2 }, { q: -5, r:  2 }],
        "Browne's Addition",
    ),
    createDistrict(
        { q: -1, r: -1 },
        [{ q: -1, r: -2 }, { q:  0, r: -2 }, { q:  0, r: -1 },
         { q: -1, r:  0 }, { q: -2, r:  0 }, { q: -2, r: -1 }],
        'Downtown',
    ),
    createDistrict(
        { q:  1, r:  3 },
        [{ q:  2, r:  3 }, { q:  0, r:  3 }, { q:  1, r:  4 },
         { q:  0, r:  4 }, { q:  1, r:  2 }, { q:  2, r:  2 }],
        'University District',
    ),
    createDistrict(
        { q:  6, r: -3 },
        [{ q:  7, r: -3 }, { q:  5, r: -3 }, { q:  6, r: -2 },
         { q:  5, r: -2 }, { q:  6, r: -4 }, { q:  7, r: -4 }],
        'Spokane Valley',
    ),
];

// ─── Neighborhoods ────────────────────────────────────────────────────────────
const NEIGHBORHOODS = [
    { q:  0, r: -4 }, // F2
    { q:  2, r: -5 }, // G1
    { q:  3, r: -3 }, // I3
    { q:  5, r: -5 }, // J1
    { q:  5, r:  1 }, // M7
    { q:  2, r: -1 }, // I5
    { q:  4, r:  4 }, // N10
    { q:  4, r:  5 }, // N11
    { q: -3, r:  5 }, // G11
    { q: -6, r:  0 }, // B6
    { q: -3, r:  3 }, // F9
    { q:  0, r:  1 }, // H7
].map(createNeighborhood);

// ─── Junction factory ─────────────────────────────────────────────────────────
// cell — vertex position in hex lattice space { a, b }
// Returns a named split point that any NetLink path can reference. When two
// NetLinks share the same junction object, the node renders once at that vertex.
function createJunction({ a, b }) {
    return {
        a, b,
        isJunction: true,
        x: GRID_CX + HEX_SIZE * (SQ3 / 2) * a,
        y: GRID_CY + HEX_SIZE * 0.5 * b,
    };
}

// ─── NetLink factory ──────────────────────────────────────────────────────────
// startNode / endNode — district name or neighborhood label (for reference only)
// path — ordered array of entries. Each entry is one of:
//   { a, b }                 plain lattice vertex
//   Junction                 named split point (from createJunction)
//   [[branch…], [branch…]]   array-of-arrays triggers a split; each inner array
//                            continues from the vertex immediately preceding it
//
// Returns { startNode, endNode, segments, points } where segments is the list
// of polylines to draw and points is the deduped vertex set for circles.
function createNetLink(startNode, endNode, path) {
    function resolve(v) {
        if (v.isJunction) return v;
        return { x: GRID_CX + HEX_SIZE * (SQ3 / 2) * v.a, y: GRID_CY + HEX_SIZE * 0.5 * v.b, isJunction: false };
    }

    function walk(entries, seed) {
        const segments = [];
        let run = seed ? [seed] : [];
        for (const entry of entries) {
            if (Array.isArray(entry)) {
                const pivot = run[run.length - 1];
                if (run.length > 1) segments.push(run);
                for (const branch of entry) segments.push(...walk(branch, pivot));
                run = [];
            } else {
                run.push(resolve(entry));
            }
        }
        if (run.length > 1) segments.push(run);
        return segments;
    }

    const segments = walk(path, null);

    const seen  = new Set();
    const points = [];
    for (const seg of segments) {
        for (const pt of seg) {
            const key = `${pt.x},${pt.y}`;
            if (!seen.has(key)) { seen.add(key); points.push(pt); }
        }
    }

    return { startNode, endNode, segments, points };
}

// ─── Junctions ────────────────────────────────────────────────────────────────
const JUNCTIONS = {
    // Apex of gap hex (q=−1, r=−4) — midpoint on the north corridor between
    // the North Spokane cluster and F2. Any future link routing through this
    // area can branch from here.
    northCorridor: createJunction({ a: -6, b: -14 }),

    // Top vertex of I5 (q=2, r=−1) vi=5 — sits directly between I3 and the
    // I5/G5 row. Used as the split point for the I3 → G5/I5 branch.
    i5Top: createJunction({ a: 3, b: -5 }),

    // Bottom vertex of J5 (q=3, r=−1) vi=2 — shared by J5/J6/K6.
    // Lies on the I5→L4 path; the J5→K8 link branches downward from here.
    j5Bottom: createJunction({ a: 5, b: -1 }),

    // Lower-right vertex of L8 (q=3, r=2) vi=2 = K9 vi=0 upper-right.
    // Lies on the K9→M7 path; the L8→N10 link branches downward from here.
    l8Corner: createJunction({ a: 8, b: 8 }),

    // Right vertex of B4 (q=−5, r=−2) vi=1 = C4 vi=3.
    // Lies on the B3→B6 path; the B4→E5 link branches eastward from here.
    b4Right: createJunction({ a: -11, b: -5 }),

    // Upper-left vertex of F9 (q=−3, r=3) vi=4 = E9 vi=0.
    // Split destination from the F6→D9 link; also the entry point for any future
    // link that passes through F9.
    f9: createJunction({ a: -4, b: 8 }),

    // Lower-left vertex of I2 (q=3, r=−4) vi=3 = H3 vi=5.
    // Entry point for connections routing southwest out of I2.
    i2: createJunction({ a: 1, b: -11 }),
};

// ─── NetLinks ─────────────────────────────────────────────────────────────────
const NET_LINKS = [
    // North Spokane → F2
    // Traces the top two edges of gap hex (q=−1, r=−4):
    //   (a=−7, b=−13): top-right of NS cluster hex (q=−2, r=−4)
    //   JUNCTIONS.northCorridor: apex of gap hex, reusable branch point
    //   (a=−5, b=−13): top-left of F2 (q=0, r=−4)
    createNetLink('North Spokane', 'F2', [
        { a: -7, b: -13 },
        JUNCTIONS.northCorridor,
        { a: -5, b: -13 },
    ]),

    // F2 → G1
    // Traces the lower two edges of gap hex (q=1, r=−5):
    //   (a=−4, b=−14): top of F2 (q=0, r=−4), shared with (q=1, r=−5) lower-left
    //   (a=−3, b=−13): bottom of gap hex, shared tri-corner of F2/(1,−4)/(1,−5)
    //   (a=−2, b=−14): lower-left of G1 (q=2, r=−5)
    createNetLink('F2', 'G1', [
        { a: -4, b: -14 },
        { a: -3, b: -13 },
        { a: -2, b: -14 },
    ]),

    // G1 → I3
    // Descends through gap hexes (q=2, r=−4) and (q=3, r=−4):
    //   (a= 0, b=−14): G1 vi=1 lower-right, enters (2,−4) via its top edge
    //   (a= 1, b=−13): (2,−4) vi=0, shared corner of (2,−4)/(3,−4)
    //   (a= 1, b=−11): (3,−4) vi=3, base of its left edge
    //   (a= 2, b=−10): I3 vi=4 upper-left, shared with (3,−4) vi=2
    createNetLink('G1', 'I3', [
        { a:  0, b: -14 },
        { a:  1, b: -13 },
        { a:  1, b: -11 },
        { a:  2, b: -10 },
    ]),

    // I3 → G5 (with branch to I5 via JUNCTIONS.i5Top)
    // Trunk drops straight down through gap hex (q=3, r=−2):
    //   (a= 3, b=−7): I3 vi=2 bottom vertex, shared with (3,−2) vi=4
    //   JUNCTIONS.i5Top: (3,−2) vi=3 = I5 vi=5 top vertex — split point
    // G5 branch — zigzags left through (q=2,r=−2) then (q=1,r=−2):
    //   (a= 2, b=−4): I5 vi=4, (2,−2) vi=2
    //   (a= 1, b=−5): (2,−2) vi=3 = (1,−2) vi=1
    //   (a= 0, b=−4): (1,−2) vi=2
    //   (a=−1, b=−5): G5 vi=5 top vertex
    // I5 branch — traces I5's right edge:
    //   (a= 4, b=−4): I5 vi=0 upper-right, shared with (3,−2) vi=2
    //   (a= 4, b=−2): I5 vi=1 lower-right
    createNetLink('I3', ['G5', 'I5'], [
        { a:  3, b: -7 },
        JUNCTIONS.i5Top,
        [
            [{ a:  2, b: -4 }, { a:  1, b: -5 }, { a:  0, b: -4 }, { a: -1, b: -5 }],
            [{ a:  4, b: -4 }, { a:  4, b: -2 }],
        ],
    ]),

    // I5 → L4
    // Traces the bottom of J5 (q=3,r=−1), up the left side of K5 (q=4,r=−1),
    // then connects to L4 (q=5,r=−2):
    //   (a= 4, b=−2): I5 vi=1 = J5 vi=3 lower-left — start
    //   (a= 5, b=−1): J5 vi=2 bottom vertex
    //   (a= 6, b=−2): J5 vi=1 = K5 vi=3 lower-left
    //   (a= 6, b=−4): K5 vi=4 upper-left (vertical left edge of K5)
    //   (a= 7, b=−5): K5 vi=5 = L4 vi=3 lower-left — K5/L4 boundary
    //   (a= 8, b=−4): L4 vi=2 lower body
    createNetLink('I5', 'L4', [
        { a:  4, b: -2 },
        JUNCTIONS.j5Bottom,
        { a:  6, b: -2 },
        { a:  6, b: -4 },
        { a:  7, b: -5 },
        { a:  8, b: -4 },
    ]),

    // H2 → J1
    // Zigzags along the upper corridor through gap hexes (q=3,r=−4) and (q=4,r=−5):
    //   (a= 1, b=−13): H2 (q=2,r=−4) vi=0 upper-right; shared corner of (3,−4)/(3,−5)
    //   (a= 2, b=−14): tri-corner of (3,−4) / (3,−5) / (4,−5)
    //   (a= 3, b=−13): tri-corner of (3,−4) / (4,−4) / (4,−5)
    //   (a= 4, b=−14): J1 (q=5,r=−5) vi=3 lower-left; shared with (4,−5) vi=1
    createNetLink('H2', 'J1', [
        { a:  1, b: -13 },
        { a:  2, b: -14 },
        { a:  3, b: -13 },
        { a:  4, b: -14 },
    ]),

    // M7 → M4
    // Ascends through N6 (q=6,r=0) and M5 (q=6,r=−1) to M4 (q=6,r=−2):
    //   (a=11, b= 1): M7 vi=5 top vertex = N6 vi=3
    //   (a=11, b=−1): N6 vi=4 = M5 vi=2
    //   (a=10, b=−2): M5 vi=3 lower-left
    //   (a=10, b=−4): M5 vi=4 = M4 vi=2 lower-left of M4
    createNetLink('M7', 'M4', [
        { a: 11, b:  1 },
        { a: 11, b: -1 },
        { a: 10, b: -2 },
        { a: 10, b: -4 },
    ]),

    // L8 → N10
    // Descends lower-right through L9 (q=3,r=3) and M9 (q=4,r=3) to N10 (q=4,r=4):
    //   JUNCTIONS.l8Corner: L8 vi=2 = K9 vi=0 — branch point
    //   (a= 8, b=10): L9 vi=3 lower-left
    //   (a= 9, b=11): L9 vi=2 bottom vertex
    //   (a=10, b=10): L9 vi=1 = M9 vi=3 lower-left of M9
    //   (a=11, b=11): M9 vi=2 = N10 vi=4 upper-left of N10
    //   (a=12, b=10): M9 vi=1 = N10 vi=5 top vertex of N10
    createNetLink('L8', 'N10', [
        JUNCTIONS.l8Corner,
        { a:  8, b: 10 },
        { a:  9, b: 11 },
        { a: 10, b: 10 },
        { a: 11, b: 11 },
        { a: 12, b: 10 },
    ]),

    // J5 → K8
    // Descends from J5's bottom through J6 (q=2,r=0) and J7 (q=2,r=1) into K8 (q=2,r=2):
    //   JUNCTIONS.j5Bottom: J5 vi=2 = J6 vi=0 — shared branch point
    //   (a= 5, b= 1): J6 vi=1 = J7 vi=5 — lower-right of J6
    //   (a= 6, b= 2): J7 vi=0 = K6 vi=2 — upper-right of J7
    //   (a= 6, b= 4): J7 vi=1 = K7 vi=3 — right edge of J7
    //   (a= 5, b= 5): J7 vi=2 = K8 vi=4 — upper-left of K8
    createNetLink('J5', 'K8', [
        JUNCTIONS.j5Bottom,
        { a:  5, b:  1 },
        { a:  6, b:  2 },
        { a:  6, b:  4 },
        { a:  5, b:  5 },
    ]),

    // B3 → B6
    // Descends through B4 (q=−5,r=−2) and B5 (q=−5,r=−1) to B6 (q=−6,r=0):
    //   (a=−11, b=−7): B3 vi=2 bottom vertex = B4 vi=0
    //   (a=−11, b=−5): B4 vi=1 = B5 vi=5 right edge of B4
    //   (a=−12, b=−4): B5 vi=4 lower-left
    //   (a=−12, b=−2): B5 vi=3 = B6 vi=5 top vertex of B6
    createNetLink('B3', 'B6', [
        { a: -11, b: -7 },
        JUNCTIONS.b4Right,
        { a: -12, b: -4 },
        { a: -12, b: -2 },
    ]),

    // B4 → E5
    // Branches east from B4 through C4 (q=−4,r=−2), C5 (q=−4,r=−1), D5 (q=−3,r=−1)
    // to E5 (q=−2,r=−1), zigzagging along the b=−5/−4 corridor:
    //   JUNCTIONS.b4Right: B4 vi=1 = C4 vi=3 — branch point
    //   (a=−10, b=−4): C4 vi=2 lower-right
    //   (a= −9, b=−5): C4 vi=1 = C5 vi=5
    //   (a= −8, b=−4): C5 vi=0 = D5 vi=4
    //   (a= −7, b=−5): D5 vi=5
    //   (a= −6, b=−4): D5 vi=0 = E5 vi=4
    createNetLink('B4', 'E5', [
        JUNCTIONS.b4Right,
        { a: -10, b: -4 },
        { a:  -9, b: -5 },
        { a:  -8, b: -4 },
        { a:  -7, b: -5 },
        { a:  -6, b: -4 },
    ]),

    // F6 → D9 (with junction to F9)
    // Descends through F7 (q=−2,r=1) and the F8/E8 boundary, then splits at the
    // E8/E9/F8 triple point (a=−5,b=7) into two arms:
    //   Trunk:
    //   (a=−4, b= 2): F6 vi=2 bottom = F7 vi=4
    //   (a=−4, b= 4): F7 vi=3 = F8 vi=5
    //   (a=−5, b= 5): F8 vi=4 = E8 vi=0 — F8/E8 boundary
    //   (a=−5, b= 7): E8 vi=1 = E9 vi=5 = F8 vi=3 — triple split point
    //   D9 arm — left into E9 then D9:
    //   (a=−6, b= 8): E9 vi=4 = D9 vi=0
    //   (a=−6, b=10): E9 vi=3 = D9 vi=1
    //   F9 arm — right to junction:
    //   JUNCTIONS.f9: E9 vi=0 = F9 vi=4
    createNetLink('F6', ['D9', 'F9'], [
        { a: -4, b:  2 },
        { a: -4, b:  4 },
        { a: -5, b:  5 },
        { a: -5, b:  7 },
        [
            [{ a: -6, b:  8 }, { a: -6, b: 10 }],
            [JUNCTIONS.f9],
        ],
    ]),

    // F9 → G11
    // Descends from JUNCTIONS.f9 through G10 (q=−3,r=4) to G11 (q=−3,r=5):
    //   JUNCTIONS.f9: F9 vi=4 — start
    //   (a=−4, b=10): F9 vi=3 = E9 vi=1
    //   (a=−3, b=11): F9 vi=2 = G10 vi=4
    //   (a=−3, b=13): G10 vi=3
    //   (a=−2, b=14): G10 vi=2 = G11 vi=4
    createNetLink('F9', 'G11', [
        JUNCTIONS.f9,
        { a: -4, b: 10 },
        { a: -3, b: 11 },
        { a: -3, b: 13 },
        { a: -2, b: 14 },
    ]),

    // G11 → J10
    // Ascends upper-right through H10 (q=−2,r=4) and I10 (q=−1,r=4) to J10 (q=0,r=4):
    //   (a= 0, b=14): G11 vi=0 upper-right = H10 vi=2
    //   (a= 1, b=13): H10 vi=1 = I10 vi=3
    //   (a= 1, b=11): H10 vi=0 = I10 vi=4
    //   (a= 2, b=10): I10 vi=5
    //   (a= 3, b=11): I10 vi=0 = J10 vi=4
    createNetLink('G11', 'J10', [
        { a:  0, b: 14 },
        { a:  1, b: 13 },
        { a:  1, b: 11 },
        { a:  2, b: 10 },
        { a:  3, b: 11 },
    ]),

    // N10 → M7
    // Climbs the right-side corridor through N9 (q=5,r=3), N8 (q=5,r=2), and
    // N7 (q=6,r=1) to land on M7 (q=5,r=1):
    //   (a=13, b=11): N10 vi=0 = N9 vi=2 — entry from N10
    //   (a=14, b=10): N9 vi=1 — right side of N9
    //   (a=14, b= 8): N9 vi=0 — top-right of N9
    //   (a=13, b= 7): N9 vi=5 = N8 vi=1 — N9/N8 boundary
    //   (a=13, b= 5): N8 vi=0 = N7 vi=2 — N8/N7 boundary
    //   (a=12, b= 4): N7 vi=3 = M7 vi=1 — connects to M7
    createNetLink('N10', 'M7', [
        { a: 13, b: 11 },
        { a: 14, b: 10 },
        { a: 14, b:  8 },
        { a: 13, b:  7 },
        { a: 13, b:  5 },
        { a: 12, b:  4 },
    ]),

    // M7 → K8
    // Descends left through L7 (q=4,r=1) and K7 (q=3,r=1) to K8 (q=2,r=2):
    //   (a=10, b= 4): M7 vi=3 = L7 vi=1 — lower-left of M7
    //   (a= 9, b= 5): L7 vi=2
    //   (a= 8, b= 4): L7 vi=3 = K7 vi=1
    //   (a= 7, b= 5): K7 vi=2 = K8 vi=0 — upper-right of K8
    createNetLink('M7', 'K8', [
        { a: 10, b:  4 },
        { a:  9, b:  5 },
        { a:  8, b:  4 },
        { a:  7, b:  5 },
    ]),

    // N11 → K10
    // Traverses left through M11 (q=3,r=5), M10 (q=3,r=4), and L10 (q=2,r=4)
    // to reach K10 (q=1,r=4), zigzagging along the b=10–14 corridor:
    //   (a=12, b=14): N11 vi=4 = M11 vi=0 — upper-left of N11
    //   (a=11, b=13): M11 vi=5 = M10 vi=1
    //   (a=11, b=11): M10 vi=0
    //   (a=10, b=10): M10 vi=5
    //   (a= 9, b=11): M10 vi=4 = L10 vi=0
    //   (a= 8, b=10): L10 vi=5
    //   (a= 7, b=11): L10 vi=4 = K10 vi=0
    createNetLink('N11', 'K10', [
        { a: 12, b: 14 },
        { a: 11, b: 13 },
        { a: 11, b: 11 },
        { a: 10, b: 10 },
        { a:  9, b: 11 },
        { a:  8, b: 10 },
        { a:  7, b: 11 },
    ]),

    // G6 → H7
    createNetLink('G6', 'H7', [
        { a: -1, b: -1 },
        { a: -1, b:  1 },
        { a:  0, b:  2 },
        { a:  1, b:  1 },
        { a:  2, b:  2 },
    ]),

    // H7 → J8
    createNetLink('H7', 'J8', [
        { a:  2, b:  4 },
        { a:  3, b:  5 },
        { a:  4, b:  4 },
        { a:  5, b:  5 },
    ]),

    // F9 → H7
    createNetLink('F9', 'H7', [
        { a: -2, b:  8 },
        { a: -1, b:  7 },
        { a: -1, b:  5 },
        { a:  0, b:  4 },
    ]),

    // C8 → B6
    createNetLink('C8', 'B6', [
        { a: -10, b:  4 },
        { a: -10, b:  2 },
        { a: -11, b:  1 },
        { a: -11, b: -1 },
    ]),

    // F2 → F4
    createNetLink('F2', 'F4', [
        { a: -3, b: -11 },
        { a: -4, b: -10 },
        { a: -4, b:  -8 },
        { a: -3, b:  -7 },
    ]),

    // I2 → G4
    createNetLink('I2', 'G4', [
        JUNCTIONS.i2,
        { a:  0, b: -10 },
        { a:  0, b:  -8 },
        { a: -1, b:  -7 },
    ]),

];

// ─── Expose stubs (Game.vue calls these after movement) ──────────────────────
defineExpose({
    animatePlayerTo:  () => {},
    getNodeScreenPos: () => null,
});
</script>

<style scoped>
.hex-map-wrapper {
    position: absolute;
    inset: 0;
    background: #05050A;
}

.hex-map-svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}

.hex-grid-edge {
    stroke: #00FFFF;
    stroke-width: 0.8;
    stroke-opacity: 0.15;
}

.hex-cell-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    fill: rgba(0, 255, 255, 0.3);
    text-anchor: middle;
    dominant-baseline: middle;
    pointer-events: none;
    letter-spacing: 0.04em;
}

.cluster-hex-outline {
    fill: none;
    stroke: #00FFFF;
    stroke-width: 1.5;
    stroke-opacity: 0.7;
    stroke-linejoin: round;
}

.cluster-shared-node {
    fill: #FF69B4;
    fill-opacity: 0.9;
}

.district-yellow-hub {
    fill: #FFB300;
    stroke: #FFB300;
    stroke-width: 1.5;
    stroke-opacity: 0.7;
    transform-box: fill-box;
    transform-origin: center;
    animation: hub-pulse 3s ease-in-out infinite;
}

.district-name {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    fill: rgba(0, 255, 255, 0.6);
    text-anchor: middle;
    dominant-baseline: middle;
    pointer-events: none;
    letter-spacing: 0.12em;
}

.route-edge {
    fill: none;
    stroke: #00FFFF;
    stroke-width: 2;
    stroke-opacity: 0.9;
    stroke-linejoin: round;
    stroke-linecap: round;
}

.route-node {
    fill: #FF69B4;
    fill-opacity: 0.9;
}

.junction-node {
    fill: #FF69B4;
    fill-opacity: 0.9;
}

@keyframes hub-pulse {
    0%, 100% { transform: scale(1); }
    50%       { transform: scale(1.25); }
}
</style>
