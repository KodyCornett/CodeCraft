<template>
    <div
        class="hex-map-wrapper"
        :class="{ 'is-panning': isPanning }"
        @mousedown="onPanStart"
    >
        <!-- Particle drift layer — rendered behind the node map -->
        <canvas ref="particleCanvas" class="particle-layer" />
        <svg
            ref="svgEl"
            class="hex-map-svg"
            viewBox="0 0 1200 800"
            preserveAspectRatio="xMidYMid meet"
        >
            <defs>
                <!-- Bloom filter for the player ring -->
                <filter id="player-glow" x="-150%" y="-150%" width="400%" height="400%">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="5" result="blur"/>
                    <feMerge>
                        <feMergeNode in="blur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>
                <!-- Bloom filter for reachable-node rings -->
                <filter id="reachable-glow" x="-100%" y="-100%" width="300%" height="300%">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="4" result="blur"/>
                    <feMerge>
                        <feMergeNode in="blur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>
                <!-- ICE ping glow -->
                <filter id="ping-glow" x="-150%" y="-150%" width="400%" height="400%">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="6" result="blur"/>
                    <feMerge>
                        <feMergeNode in="blur"/>
                        <feMergeNode in="SourceGraphic"/>
                    </feMerge>
                </filter>
            </defs>
            <!-- Pan group — all content shifts with drag offset -->
            <g :transform="panTransform">

            <!-- Grid lines — one line per deduplicated hex edge -->
            <g v-if="props.isDev" class="hex-grid-layer">
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
                    :style="{ stroke: d.color }"
                />
                <!-- Action nodes — individually clickable; cyberdoc hub is skipped here -->
                <template v-for="(node, i) in d.allNodes" :key="`${d.name}-node-${i}`">
                    <g
                        v-if="node.type === 'action'"
                        class="node-clickable"
                        @click="onNodeClick(node.id)"
                        @mouseenter="onNodeHover(node.id)"
                        @mouseleave="onNodeLeave"
                    >
                        <circle v-if="reachableNames.has(node.id)" :cx="node.x" :cy="node.y" r="20" class="reachable-ring" :class="{ 'reachable-ring--locked': uplinkDepleted }" />
                        <circle :cx="node.x" :cy="node.y" r="20" class="node-hit-area" />
                        <circle :cx="node.x" :cy="node.y" r="4" class="cluster-shared-node" />
                    </g>
                </template>
                <!-- CyberDoc hub — vertex-positioned yellow node, traversable like action nodes -->
                <g class="node-clickable" @click="onNodeClick(d.hub.id)" @mouseenter="onNodeHover(d.hub.id)" @mouseleave="onNodeLeave">
                    <circle v-if="reachableNames.has(d.hub.id)" :cx="d.hub.x" :cy="d.hub.y" r="20" class="reachable-ring" :class="{ 'reachable-ring--locked': uplinkDepleted || ssCritical }" />
                    <circle :cx="d.hub.x" :cy="d.hub.y" r="20" class="node-hit-area" />
                    <circle :cx="d.hub.x" :cy="d.hub.y" r="6" class="district-yellow-hub" />
                </g>
                <text :x="d.labelX" :y="d.labelY" class="district-name">{{ d.name.toUpperCase() }}</text>
            </g>

            <!-- Neighborhoods — single hex cells -->
            <g v-for="(nb, i) in NEIGHBORHOODS" :key="`nb-${i}`" class="neighborhood">
                <polygon :points="nb.points" class="cluster-hex-outline" :style="{ stroke: nb.color }" />
                <!-- Action nodes — individually clickable -->
                <g
                    v-for="(node, j) in nb.nodes"
                    :key="`nb-${i}-n${j}`"
                    class="node-clickable"
                    @click="onNodeClick(node.id)"
                    @mouseenter="onNodeHover(node.id)"
                    @mouseleave="onNodeLeave"
                >
                    <circle v-if="reachableNames.has(node.id)" :cx="node.x" :cy="node.y" r="20" class="reachable-ring" :class="{ 'reachable-ring--locked': uplinkDepleted }" />
                    <circle :cx="node.x" :cy="node.y" r="20" class="node-hit-area" />
                    <circle :cx="node.x" :cy="node.y" r="4" class="cluster-shared-node" />
                </g>
            </g>

            <!-- NetLinks — connections between districts and neighborhoods -->
            <g v-for="(link, li) in NET_LINKS" :key="`link-${li}`" class="net-link">
                <line
                    v-for="(edge, ei) in link.edges"
                    :key="`link-${li}-edge-${ei}`"
                    :x1="edge.x1" :y1="edge.y1"
                    :x2="edge.x2" :y2="edge.y2"
                    class="route-edge"
                    :style="{ stroke: edge.color }"
                />
                <!-- Action nodes — individually clickable -->
                <g
                    v-for="(pt, i) in link.points"
                    :key="`link-${li}-pt-${i}`"
                    class="node-clickable"
                    @click="onNodeClick(pt.id)"
                    @mouseenter="onNodeHover(pt.id)"
                    @mouseleave="onNodeLeave"
                >
                    <circle v-if="reachableNames.has(pt.id)" :cx="pt.x" :cy="pt.y" r="20" class="reachable-ring" :class="{ 'reachable-ring--locked': uplinkDepleted }" />
                    <circle :cx="pt.x" :cy="pt.y" r="20" class="node-hit-area" />
                    <circle :cx="pt.x" :cy="pt.y" r="4" class="route-node" />
                </g>
            </g>

            <!-- Cell coordinate labels (column letter + row number) -->
            <g v-if="props.isDev" class="hex-labels-layer">
                <text
                    v-for="cell in CELL_LABELS"
                    :key="cell.label"
                    :x="cell.x"
                    :y="cell.y"
                    class="hex-cell-label"
                >{{ cell.label }}</text>
            </g>

            <!-- ICE Ping rings — visible when bounty player is being tracked -->
            <!-- ring radius = ping.radiusPx, driven by node ICE × bounty level vs player OS -->
            <g class="ping-layer">
                <g
                    v-for="ping in props.pings"
                    :key="ping.pingId"
                    class="ping-group"
                >
                    <!-- Accuracy ring — size reflects how tight the lock is -->
                    <circle
                        :cx="ping.x"
                        :cy="ping.y"
                        :r="ping.radiusPx ?? 20"
                        class="ping-ring"
                        :class="[
                            ping.type === 'false'  ? 'ping-ring--false'  :
                            ping.isOpenSeason      ? 'ping-ring--os'     :
                                                     'ping-ring--bounty',
                        ]"
                    />
                    <!-- Inner dot — exact centre of the lock -->
                    <circle
                        :cx="ping.x"
                        :cy="ping.y"
                        r="4"
                        class="ping-dot"
                        :class="[
                            ping.type === 'false'  ? 'ping-dot--false'  :
                            ping.isOpenSeason      ? 'ping-dot--os'     :
                                                     'ping-dot--bounty',
                        ]"
                        filter="url(#ping-glow)"
                    />
                    <!-- Range label — shows node-hop radius so player can gauge accuracy -->
                    <text
                        v-if="ping.range > 0"
                        :x="ping.x"
                        :y="ping.y - (ping.radiusPx ?? 20) - 6"
                        class="ping-label"
                        :class="ping.isOpenSeason ? 'ping-label--os' : ''"
                    >±{{ ping.range }}</text>
                </g>
            </g>

            <!-- Trap markers — server-fetched, only visible to the placing player -->
            <g class="crash-mine-layer">
                <g
                    v-for="trap in props.traps"
                    :key="trap.id"
                    class="crash-mine"
                    pointer-events="none"
                >
                    <circle :cx="trap.x" :cy="trap.y" r="11" class="mine-ring" />
                    <line :x1="trap.x - 4" :y1="trap.y - 4" :x2="trap.x + 4" :y2="trap.y + 4" class="mine-cross" />
                    <line :x1="trap.x + 4" :y1="trap.y - 4" :x2="trap.x - 4" :y2="trap.y + 4" class="mine-cross" />
                    <text :x="trap.x" :y="trap.y - 15" class="mine-ttl">{{ trap.movesLeft }}M</text>
                </g>
            </g>

            <!-- Quest objective markers — active stage node targets -->
            <g class="quest-marker-layer" pointer-events="none">
                <g
                    v-for="marker in resolvedQuestMarkers"
                    :key="marker.canvasId"
                    class="quest-marker"
                >
                    <!-- Outer pulse ring -->
                    <circle
                        :cx="marker.x"
                        :cy="marker.y"
                        r="18"
                        class="qm-pulse"
                        :style="{ stroke: marker.color }"
                    />
                    <!-- Diamond objective icon -->
                    <polygon
                        :points="`${marker.x},${marker.y - 8} ${marker.x + 6},${marker.y} ${marker.x},${marker.y + 8} ${marker.x - 6},${marker.y}`"
                        class="qm-diamond"
                        :style="{ fill: marker.color, stroke: marker.color }"
                    />
                </g>
            </g>

            <!-- Player marker — pulsing ring above everything else -->
            <g v-if="playerToken" class="player-marker">
                <circle
                    :cx="playerToken.x"
                    :cy="playerToken.y"
                    r="16"
                    class="player-ring"
                />
            </g>

            <!-- Hover tooltip — appears near hovered node, shows key recon data -->
            <g v-if="hoveredDbNode" class="node-tooltip" pointer-events="none">
                <rect
                    :x="tooltipSvgPos.x - 4"
                    :y="tooltipSvgPos.y - 22"
                    width="200"
                    height="106"
                    class="tooltip-bg"
                />
                <!-- CyberDoc nodes: show store name, type, and safe harbor status -->
                <template v-if="hoveredDbNode.type === 'cyberdoc'">
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y" class="tooltip-line tooltip-line--cyberdoc">
                        {{ cyberDocStoreName(hoveredDbNode) }}
                    </text>
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y + 28" class="tooltip-line tooltip-line--dim">
                        CYBER DOC
                    </text>
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y + 56" class="tooltip-line tooltip-line--safe">
                        SAFE HARBOR
                    </text>
                </template>
                <!-- Action nodes: show ICE, zone type, and cred value -->
                <template v-else>
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y" class="tooltip-line">
                        ICE {{ hoveredDbNode.ice ?? '?' }}  T{{ hoveredDbNode.tier ?? '?' }}
                    </text>
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y + 28" class="tooltip-line tooltip-line--dim">
                        {{ hoveredDbNode.zoneType ?? 'netlink' }}
                    </text>
                    <text :x="tooltipSvgPos.x + 4" :y="tooltipSvgPos.y + 56" class="tooltip-line tooltip-line--creds">
                        ₡ {{ hoveredDbNode.credValueBase ?? '?' }}
                    </text>
                </template>
            </g>

            </g><!-- end pan group -->
        </svg>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

// Template ref — bound to the <svg> element so getNodeScreenPos can convert
// SVG coordinates to stage-relative screen pixels.
const svgEl = ref(null);

// ─── Particle canvas ref ──────────────────────────────────────────────────────
const particleCanvas = ref(null);
let   particleRaf    = null;

// ─── Pan state ────────────────────────────────────────────────────────────────
const panOffset  = ref({ x: 0, y: 0 });
const isPanning  = ref(false);
let   hasDragged = false;
let   dragOrigin = { mx: 0, my: 0, px: 0, py: 0 };

const panTransform = computed(() =>
    `translate(${panOffset.value.x}, ${panOffset.value.y})`
);

function onPanStart(e) {
    // Middle-mouse or left-button only
    if (e.button !== 0 && e.button !== 1) return;
    isPanning.value = true;
    hasDragged      = false;
    dragOrigin      = {
        mx: e.clientX, my: e.clientY,
        px: panOffset.value.x, py: panOffset.value.y,
    };
    e.preventDefault();
}

function onPanMove(e) {
    if (!isPanning.value || !svgEl.value) return;
    const dx = e.clientX - dragOrigin.mx;
    const dy = e.clientY - dragOrigin.my;
    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) hasDragged = true;
    // Convert screen-pixel delta → SVG-unit delta using the SVG's screen CTM.
    const ctm = svgEl.value.getScreenCTM();
    if (!ctm) return;
    panOffset.value = {
        x: dragOrigin.px + dx / ctm.a,
        y: dragOrigin.py + dy / ctm.d,
    };
}

function onPanEnd() {
    isPanning.value = false;
}

// ─── Props (kept for Game.vue compatibility — not rendered yet) ───────────────
const props = defineProps({
    nodes:         { type: Array,   default: () => [] },
    links:         { type: Array,   default: () => [] },
    pings:         { type: Array,   default: () => [] },
    traps:         { type: Array,   default: () => [] },
    // questMarkers: [{ canvasId: 'BA-hub', color: '#FF6B35', docName: 'Knuckle' }, ...]
    questMarkers:  { type: Array,   default: () => [] },
    currentNodeId: { type: String,  default: null     },
    playerUplink:  { type: Number,  default: 3        },
    playerSs:      { type: Number,  default: 100      },
    targetMode:    { type: Boolean, default: false    },
    isDev:         { type: Boolean, default: false    },
});
const emit = defineEmits(['node-clicked', 'player-moved', 'move-blocked']);

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

// ─── Node registry ────────────────────────────────────────────────────────────
const ALL_NODES      = new Map(); // id → node
const COORD_NODE_MAP = new Map(); // "a,b" → node (first-write-wins)
const NODE_ADJACENCY = new Map(); // id → Set<id>  (built progressively)
const AREA_COLOR_MAP = new Map(); // area name → color string (authoritative)

function createNode(id, x, y, type, district, areaColor = null) {
    const node = { id, x, y, type, district, areaColor };
    ALL_NODES.set(id, node);
    return node;
}

function addNodeEdge(a, b) {
    if (a === b) return;
    if (!NODE_ADJACENCY.has(a)) NODE_ADJACENCY.set(a, new Set());
    if (!NODE_ADJACENCY.has(b)) NODE_ADJACENCY.set(b, new Set());
    NODE_ADJACENCY.get(a).add(b);
    NODE_ADJACENCY.get(b).add(a);
}

// ─── Map element factories ────────────────────────────────────────────────────

function createDistrict(centerCell, neighborCells, name, abbr) {
    const color  = '#00FF41'; // neon green — all district lines and edges
    AREA_COLOR_MAP.set(name, color);
    const cells  = [centerCell, ...neighborCells];
    const midPx  = hexCenterPx(centerCell.q, centerCell.r); // geometric center for reference only

    const hexPolygons = cells.map(({ q, r }) => ({
        points: V_OFFSETS.map((_, vi) => {
            const p = hexVertexPx(q, r, vi);
            return `${p.x},${p.y}`;
        }).join(' '),
    }));

    // Collect all cluster vertex positions
    const vMap = new Map();
    for (const { q, r } of cells) {
        for (let vi = 0; vi < 6; vi++) {
            const [da, db] = V_OFFSETS[vi];
            const a = 2 * q + r + da;
            const b = 3 * r + db;
            const key = `${a},${b}`;
            if (!vMap.has(key)) vMap.set(key, { a, b, ...hexVertexPx(q, r, vi) });
        }
    }

    // Place the cyberdoc hub at the cluster vertex nearest to the hex midpoint.
    // Register it in COORD_NODE_MAP *before* allNodes is built so the allNodes
    // pass returns it for that vertex key instead of creating a duplicate action node.
    let hubEntry = null, hubDist = Infinity;
    for (const entry of vMap.values()) {
        const d = Math.hypot(entry.x - midPx.x, entry.y - midPx.y);
        if (d < hubDist) { hubDist = d; hubEntry = entry; }
    }
    const hub = createNode(`${abbr}-hub`, hubEntry.x, hubEntry.y, 'cyberdoc', name, color);
    COORD_NODE_MAP.set(`${hubEntry.a},${hubEntry.b}`, hub);

    // Build allNodes — hub's key is already occupied, so it comes back as the
    // cyberdoc node; all other vertices become action nodes.
    let vIdx = 1;
    const allNodes = [...vMap.values()].map(({ a, b, x, y }) => {
        const key = `${a},${b}`;
        if (!COORD_NODE_MAP.has(key)) {
            const node = createNode(`${abbr}-v${vIdx}`, x, y, 'action', name, color);
            COORD_NODE_MAP.set(key, node);
            vIdx++;
            return node;
        }
        return COORD_NODE_MAP.get(key);
    });

    // Internal vertex adjacency — the hub participates as a regular vertex node.
    for (const { q, r } of cells) {
        for (let vi = 0; vi < 6; vi++) {
            const [da1, db1] = V_OFFSETS[vi];
            const [da2, db2] = V_OFFSETS[(vi + 1) % 6];
            const n1 = COORD_NODE_MAP.get(`${2*q + r + da1},${3*r + db1}`);
            const n2 = COORD_NODE_MAP.get(`${2*q + r + da2},${3*r + db2}`);
            if (n1 && n2) addNodeEdge(n1.id, n2.id);
        }
    }

    // Place the label at the true geometric center of the cluster.
    return { name, color, hexPolygons, allNodes, hub, labelX: midPx.x, labelY: midPx.y };
}

function createNeighborhood({ q, r, name }) {
    const color = '#0099FF'; // neon blue — all neighborhood lines and edges
    AREA_COLOR_MAP.set(name, color);
    const nodes = V_OFFSETS.map((_, vi) => {
        const [da, db] = V_OFFSETS[vi];
        const a = 2 * q + r + da;
        const b = 3 * r + db;
        const key = `${a},${b}`;
        const px = hexVertexPx(q, r, vi);
        if (!COORD_NODE_MAP.has(key)) {
            const node = createNode(`${name}-v${vi}`, px.x, px.y, 'action', name, color);
            COORD_NODE_MAP.set(key, node);
            return node;
        }
        return COORD_NODE_MAP.get(key);
    });
    // Ring adjacency: each vertex connects to its two hex-ring neighbors
    for (let vi = 0; vi < 6; vi++) {
        addNodeEdge(nodes[vi].id, nodes[(vi + 1) % 6].id);
    }

    // If every vertex was already claimed by the same district (e.g. F4 ⊂ Downtown),
    // use that district's color for the polygon outline so it matches the district.
    const d0 = nodes[0]?.district;
    const outlineColor = (d0 && d0 !== name && nodes.every(n => n.district === d0) && AREA_COLOR_MAP.has(d0))
        ? AREA_COLOR_MAP.get(d0)
        : color;

    return {
        color: outlineColor,
        points: V_OFFSETS.map((_, vi) => {
            const p = hexVertexPx(q, r, vi);
            return `${p.x},${p.y}`;
        }).join(' '),
        nodes,
    };
}

// ─── Districts ────────────────────────────────────────────────────────────────
const DISTRICTS = [
    createDistrict(
        { q: -3, r: -4 },
        [{ q: -2, r: -4 }, { q: -3, r: -3 }, { q: -4, r: -3 },
         { q: -4, r: -4 }, { q: -3, r: -5 }, { q: -2, r: -5 }],
        'North Spokane', 'NS',
    ),
    createDistrict(
        { q: -6, r:  3 },
        [{ q: -5, r:  3 }, { q: -6, r:  4 }, { q: -7, r:  4 },
         { q: -7, r:  3 }, { q: -6, r:  2 }, { q: -5, r:  2 }],
        "Browne's Addition", 'BA',
    ),
    createDistrict(
        { q: -1, r: -1 },
        [{ q: -1, r: -2 }, { q:  0, r: -2 }, { q:  0, r: -1 },
         { q: -1, r:  0 }, { q: -2, r:  0 }, { q: -2, r: -1 }],
        'Downtown', 'DT',
    ),
    createDistrict(
        { q:  1, r:  3 },
        [{ q:  2, r:  3 }, { q:  0, r:  3 }, { q:  1, r:  4 },
         { q:  0, r:  4 }, { q:  1, r:  2 }, { q:  2, r:  2 }],
        'University District', 'UD',
    ),
    createDistrict(
        { q:  6, r: -3 },
        [{ q:  7, r: -3 }, { q:  5, r: -3 }, { q:  6, r: -2 },
         { q:  5, r: -2 }, { q:  6, r: -4 }, { q:  7, r: -4 }],
        'Spokane Valley', 'SV',
    ),
];

// ─── Neighborhoods ────────────────────────────────────────────────────────────
const NEIGHBORHOODS = [
    { q:  0, r: -4, name: 'F2'  },
    { q:  2, r: -5, name: 'G1'  },
    { q:  3, r: -3, name: 'I3'  },
    { q:  5, r: -5, name: 'J1'  },
    { q:  5, r:  1, name: 'M7'  },
    { q:  2, r: -1, name: 'I5'  },
    { q:  4, r:  4, name: 'N10' },
    { q:  4, r:  5, name: 'N11' },
    { q: -3, r:  5, name: 'G11' },
    { q: -6, r:  0, name: 'B6'  },
    { q: -3, r:  3, name: 'F9'  },
    { q:  0, r:  1, name: 'H7'  },
    { q: -1, r: -2, name: 'F4'  },
].map((spec) => ({ ...createNeighborhood(spec), name: spec.name, center: hexCenterPx(spec.q, spec.r) }));

// ─── Junction factory ─────────────────────────────────────────────────────────
// cell — vertex position in hex lattice space { a, b }
// Returns a named split point that any NetLink path can reference. When two
// NetLinks share the same junction object, the node renders once at that vertex.
function createJunction(id, { a, b }) {
    const x   = GRID_CX + HEX_SIZE * (SQ3 / 2) * a;
    const y   = GRID_CY + HEX_SIZE * 0.5 * b;
    const key = `${a},${b}`;
    if (!COORD_NODE_MAP.has(key)) {
        const node = createNode(id, x, y, 'action', null);
        COORD_NODE_MAP.set(key, node);
        return { ...node, a, b };
    }
    return { ...COORD_NODE_MAP.get(key), a, b };
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
        if (v.id !== undefined) return v;
        const key = `${v.a},${v.b}`;
        if (COORD_NODE_MAP.has(key)) {
            const node = COORD_NODE_MAP.get(key);
            // Waypoints in a NetLink are never district-owned — strip district so
            // the per-segment color check never mistakes a shared vertex for an
            // internal district edge.
            return node.district !== null ? { ...node, district: null } : node;
        }
        const x = GRID_CX + HEX_SIZE * (SQ3 / 2) * v.a;
        const y = GRID_CY + HEX_SIZE * 0.5 * v.b;
        const node = createNode(`wp_${v.a}_${v.b}`, x, y, 'action', null);
        COORD_NODE_MAP.set(key, node);
        return node;
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

    const color = '#00FFFF'; // cyan — default NetLink line color

    // Pre-compute per-edge colors. If both endpoints belong to the same named
    // area (district or neighborhood), use that area's color; otherwise cyan.
    // Waypoint nodes have district=null and can never match any area.
    const edges = [];
    for (const seg of segments) {
        for (let i = 0; i < seg.length - 1; i++) {
            const a = seg[i], b = seg[i + 1];
            const edgeColor = (a.district !== null
                && a.district === b.district
                && AREA_COLOR_MAP.has(a.district))
                ? AREA_COLOR_MAP.get(a.district)
                : color;
            edges.push({ x1: a.x, y1: a.y, x2: b.x, y2: b.y, color: edgeColor });
        }
    }

    return { startNode, endNode, segments, points, edges, color };
}

// ─── Junctions ────────────────────────────────────────────────────────────────
const JUNCTIONS = {
    // Apex of gap hex (q=−1, r=−4) — midpoint on the north corridor between
    // the North Spokane cluster and F2. Any future link routing through this
    // area can branch from here.
    northCorridor: createJunction('northCorridor', { a: -6, b: -14 }),

    // Top vertex of I5 (q=2, r=−1) vi=5 — sits directly between I3 and the
    // I5/G5 row. Used as the split point for the I3 → G5/I5 branch.
    i5Top: createJunction('i5Top', { a: 3, b: -5 }),

    // Bottom vertex of J5 (q=3, r=−1) vi=2 — shared by J5/J6/K6.
    // Lies on the I5→L4 path; the J5→K8 link branches downward from here.
    j5Bottom: createJunction('j5Bottom', { a: 5, b: -1 }),

    // Lower-right vertex of L8 (q=3, r=2) vi=2 = K9 vi=0 upper-right.
    // Lies on the K9→M7 path; the L8→N10 link branches downward from here.
    l8Corner: createJunction('l8Corner', { a: 8, b: 8 }),

    // Right vertex of B4 (q=−5, r=−2) vi=1 = C4 vi=3.
    // Lies on the B3→B6 path; the B4→E5 link branches eastward from here.
    b4Right: createJunction('b4Right', { a: -11, b: -5 }),

    // Upper-left vertex of F9 (q=−3, r=3) vi=4 = E9 vi=0.
    // Split destination from the F6→D9 link; also the entry point for any future
    // link that passes through F9.
    f9: createJunction('f9', { a: -4, b: 8 }),

    // Lower-left vertex of I2 (q=3, r=−4) vi=3 = H3 vi=5.
    // Entry point for connections routing southwest out of I2.
    i2: createJunction('i2', { a: 1, b: -11 }),
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

    // J1 → L2
    createNetLink('J1', 'L2', [
        { a:  5, b: -13 },
        { a:  6, b: -14 },
        { a:  7, b: -13 },
        { a:  8, b: -14 },
        { a:  9, b: -13 },
    ]),

];

// ─── Player token ─────────────────────────────────────────────────────────────

// NetLink segment adjacency — consecutive pairs only so each move is exactly
// one step. Nodes that are 2+ hops apart along a segment are not adjacent.
for (const link of NET_LINKS) {
    for (const seg of link.segments) {
        for (let i = 0; i < seg.length - 1; i++) {
            if (seg[i].id === undefined || seg[i + 1].id === undefined) {
                console.warn('[HexMap] segment node missing id', seg[i], seg[i + 1]);
                continue;
            }
            addNodeEdge(seg[i].id, seg[i + 1].id);
        }
    }
}

// Debug: verify all nodes and their connections are wired correctly
console.log('[HexMap] ALL_NODES (%d entries):', ALL_NODES.size,
    Object.fromEntries([...ALL_NODES.entries()].map(([id, n]) => [id, { x: Math.round(n.x), y: Math.round(n.y), type: n.type }]))
);
console.log('[HexMap] NODE_ADJACENCY (%d nodes):', NODE_ADJACENCY.size,
    Object.fromEntries([...NODE_ADJACENCY.entries()].map(([id, s]) => [id, [...s]]))
);

// Return the action node inside the Downtown district that is closest to the
// district hub and has at least 2 connections. CyberDoc hub nodes are excluded.
function findStartNode() {
    const dtDistrict = DISTRICTS.find(d => d.name === 'Downtown');
    const dtHub      = dtDistrict.hub;
    let best = null, bestDist = Infinity;
    for (const node of dtDistrict.allNodes) {
        if (node.type !== 'action') continue;
        const nbrs = NODE_ADJACENCY.get(node.id);
        if (!nbrs || nbrs.size < 2) continue;
        const d = Math.hypot(node.x - dtHub.x, node.y - dtHub.y);
        if (d < bestDist) { bestDist = d; best = node; }
    }
    return best;
}

// Player token factory — accepts an ALL_NODES entry { id, x, y, type, district }.
// Moving the player replaces the token: playerToken.value = createPlayerToken(node).
function createPlayerToken(node) {
    return { id: node.id, x: node.x, y: node.y };
}

const startNode      = findStartNode();
const playerToken    = ref(createPlayerToken(startNode));
const uplinkDepleted  = computed(() => props.playerUplink <= 0);
const ssCritical      = computed(() => props.playerSs <= 0);

// ── Quest marker positions ────────────────────────────────────────────────────
// Resolves each questMarker canvas ID to the node's SVG x/y coordinates.
// Markers with no matching node in ALL_NODES are silently skipped.
const resolvedQuestMarkers = computed(() =>
    props.questMarkers
        .map(m => {
            const node = ALL_NODES.get(m.canvasId);
            if (!node) return null;
            return { ...m, x: node.x, y: node.y };
        })
        .filter(Boolean)
);

// ── DB node lookup ────────────────────────────────────────────────────────────
// Build a canvasId → DB node map from the nodes prop for zone_type lookups.
const dbNodeMap = computed(() => {
    const m = new Map();
    for (const n of props.nodes) {
        if (n.canvasId) m.set(n.canvasId, n);
    }
    return m;
});

// ── BFS reachable set ─────────────────────────────────────────────────────────
// Range is determined by the zone_type of the player's current node:
//   district     → 1 step
//   neighborhood → 2 steps
//   netlink      → full uplink value
const reachableNames = computed(() => {
    const currentId = playerToken.value.id;
    const db        = dbNodeMap.value.get(currentId);
    const zoneType  = db?.zoneType ?? 'netlink';
    const maxSteps  = zoneType === 'district'     ? 1
                    : zoneType === 'neighborhood'  ? 2
                    : props.playerUplink;  // netlink: 0 uplink → empty set

    if (maxSteps <= 0) return new Set();

    const reachable = new Set();
    let frontier    = [currentId];
    const visited   = new Set([currentId]);

    for (let step = 0; step < maxSteps; step++) {
        const next = [];
        for (const id of frontier) {
            for (const nb of (NODE_ADJACENCY.get(id) ?? [])) {
                if (!visited.has(nb)) {
                    visited.add(nb);
                    reachable.add(nb);
                    next.push(nb);
                }
            }
        }
        frontier = next;
        if (frontier.length === 0) break;
    }
    return reachable;
});

// ── Hover tooltip ─────────────────────────────────────────────────────────────
const hoveredNodeId  = ref(null);
const tooltipSvgPos  = ref({ x: 0, y: 0 });

const CYBERDOC_STORE_NAMES = {
    KNUCKLE: "Knuckle's Med-Wagon",
    VEIL:    "Veil's Parlour",
    AXIOM:   'Axiom Systems',
    FLOAT:   "Float's Repair Bay",
    PATCH:   "Patch's Clinic",
};

function cyberDocStoreName(node) {
    if (node.npcHandle && CYBERDOC_STORE_NAMES[node.npcHandle.toUpperCase()]) {
        return CYBERDOC_STORE_NAMES[node.npcHandle.toUpperCase()];
    }
    return 'CyberDoc';
}

function onNodeHover(nodeId) {
    hoveredNodeId.value = nodeId;
    const node = ALL_NODES.get(nodeId);
    if (node) tooltipSvgPos.value = { x: node.x + 16, y: node.y - 28 };
}

function onNodeLeave() {
    hoveredNodeId.value = null;
}

const hoveredDbNode = computed(() => {
    if (!hoveredNodeId.value) return null;
    return dbNodeMap.value.get(hoveredNodeId.value) ?? null;
});

// ─── Node click — move if reachable, ignore if not ───────────────────────────
function onNodeClick(nodeId) {
    if (hasDragged) return;   // suppress click if this was a pan gesture

    // In targeting mode (e.g. crash mine placement) emit inspect event with
    // real 1-step adjacency so Game.vue can validate the target — never move.
    if (props.targetMode) {
        const node = ALL_NODES.get(nodeId);
        if (!node) return;
        const adj        = NODE_ADJACENCY.get(playerToken.value.id) ?? new Set();
        const isAdjacent = adj.has(nodeId);
        emit('node-clicked', { node: { ...node }, isAdjacent });
        return;
    }

    if (!reachableNames.value.has(nodeId)) return;  // out of range — do nothing

    // Reachable: commit the move immediately (no Jack In confirmation needed)
    commitMove(nodeId);
}

// ─── BFS shortest-path distance between two node IDs ─────────────────────────
function bfsDistance(fromId, toId) {
    if (fromId === toId) return 0;
    const visited  = new Set([fromId]);
    let   frontier = [fromId];
    let   dist     = 0;
    while (frontier.length > 0) {
        dist++;
        const next = [];
        for (const id of frontier) {
            for (const nb of (NODE_ADJACENCY.get(id) ?? [])) {
                if (nb === toId) return dist;
                if (!visited.has(nb)) { visited.add(nb); next.push(nb); }
            }
        }
        frontier = next;
    }
    return dist; // unreachable — shouldn't happen for a valid move
}

// ─── Commit move — called by Game.vue when player confirms via JACK IN ────────
function commitMove(nodeId) {
    const node = ALL_NODES.get(nodeId);
    if (!node) return;

    if (ssCritical.value) {
        emit('move-blocked', { reason: 'SS_CRITICAL' });
        return;
    }
    if (props.playerUplink <= 0) {
        emit('move-blocked', { reason: 'NO_UPLINK' });
        return;
    }

    // Only allow moving to a reachable node
    if (!reachableNames.value.has(nodeId)) return;

    // Calculate actual hop cost so uplink is decremented correctly for multi-hop moves
    const uplinkCost = bfsDistance(playerToken.value.id, nodeId);

    playerToken.value = createPlayerToken(node);
    emit('player-moved', { nodeId: node.id, district: node.district ?? null, x: node.x, y: node.y, uplinkCost });
    // Keep the right panel anchored to the new current node.
    // isAdjacent is false — the player IS on this node, not adjacent to it.
    emit('node-clicked', { node: { ...node }, isAdjacent: false });
}

// ─── Helpers exposed to Game.vue ─────────────────────────────────────────────

/** Move the player token to toId, then fire callback. */
function animatePlayerTo(fromId, toId, callback) {
    const node = ALL_NODES.get(toId);
    if (node) playerToken.value = createPlayerToken(node);
    callback?.();
}

/**
 * Teleport the player token directly to a node by canvas ID.
 * Called by Game.vue after DB spawn-node selection resolves.
 * Does NOT emit player-moved — that event is for voluntary moves only.
 */
function setPlayerNode(canvasId) {
    const node = ALL_NODES.get(canvasId);
    if (node) playerToken.value = createPlayerToken(node);
}

/** Convert a canvas node's SVG coords to stage-relative screen pixels. */
function getNodeScreenPos(nodeId) {
    const node = ALL_NODES.get(nodeId);
    if (!node || !svgEl.value) return null;
    try {
        const pt  = svgEl.value.createSVGPoint();
        pt.x      = node.x;
        pt.y      = node.y;
        const ctm = svgEl.value.getScreenCTM();
        if (!ctm) return null;
        const screen     = pt.matrixTransform(ctm);
        const parentRect = svgEl.value.parentElement?.getBoundingClientRect();
        return {
            x: screen.x - (parentRect?.left ?? 0),
            y: screen.y - (parentRect?.top  ?? 0),
        };
    } catch {
        return null;
    }
}

onMounted(() => {
    window.addEventListener('mousemove', onPanMove);
    window.addEventListener('mouseup',   onPanEnd);
    initParticles();
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onPanMove);
    window.removeEventListener('mouseup',   onPanEnd);
    if (particleRaf) cancelAnimationFrame(particleRaf);
});

// ─── Particle system — horizontal network traffic ─────────────────────────────
function initParticles() {
    const canvas = particleCanvas.value;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    function resize() {
        canvas.width  = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    // Colours: mostly cyan, occasional green accent
    const COLORS  = ['#00FFFF', '#00FFFF', '#00FFFF', '#00FF9C'];
    // Lane count scales with canvas height — one lane per ~40px
    const LANE_COUNT = 28;

    function makeLane(c, index) {
        const y       = (index / LANE_COUNT) * c.height + (c.height / LANE_COUNT) * 0.5;
        const dir     = Math.random() < 0.5 ? 1 : -1;   // left or right
        const burst   = Math.random() < 0.12;            // occasional fast packet
        const speed   = burst
            ? 2.5 + Math.random() * 2.0
            : 0.4 + Math.random() * 0.8;
        const color   = COLORS[Math.floor(Math.random() * COLORS.length)];
        // Packet length — bursts are longer, normal traffic is short dashes
        const len     = burst
            ? 18 + Math.random() * 30
            : 6  + Math.random() * 14;
        const opacity = burst
            ? 0.35 + Math.random() * 0.25
            : 0.08 + Math.random() * 0.12;

        return {
            x:       dir === 1 ? -len : c.width + len,
            y,
            dir,
            speed,
            len,
            color,
            opacity,
            burst,
            // Delay before this packet enters — staggers lanes so they don't all fire at once
            delay: Math.random() * 300,
        };
    }

    // Build one packet per lane; when a packet exits, recycle the lane
    const packets = Array.from({ length: LANE_COUNT }, (_, i) => makeLane(canvas, i));

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        for (let i = 0; i < packets.length; i++) {
            const p = packets[i];

            // Respect initial delay
            if (p.delay > 0) {
                p.delay--;
                continue;
            }

            // Draw the packet as a horizontal dash with a fading trail
            const trailLen = p.burst ? p.len * 2.5 : p.len * 1.2;
            const grad = ctx.createLinearGradient(
                p.x - p.dir * trailLen, p.y,
                p.x,                    p.y
            );
            grad.addColorStop(0,   'transparent');
            grad.addColorStop(0.6, `${p.color}22`);
            grad.addColorStop(1,   p.color);

            ctx.globalAlpha = p.opacity;
            ctx.strokeStyle = grad;
            ctx.lineWidth   = p.burst ? 1.5 : 1;
            ctx.beginPath();
            ctx.moveTo(p.x - p.dir * p.len, p.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();

            // Leading dot for non-burst packets
            if (!p.burst) {
                ctx.globalAlpha = p.opacity * 1.8;
                ctx.fillStyle   = p.color;
                ctx.beginPath();
                ctx.arc(p.x, p.y, 1.2, 0, Math.PI * 2);
                ctx.fill();
            }

            p.x += p.dir * p.speed;

            // Recycle when fully off-screen
            const offscreen = p.dir === 1
                ? p.x - p.len > canvas.width
                : p.x + p.len < 0;

            if (offscreen) {
                packets[i] = makeLane(canvas, i);
                packets[i].delay = Math.random() * 120; // short re-entry delay
            }
        }

        ctx.globalAlpha = 1;
        particleRaf = requestAnimationFrame(draw);
    }

    draw();
}

defineExpose({
    animatePlayerTo,
    setPlayerNode,
    commitMove,
    getNodeScreenPos,
    nodeAdjacency: NODE_ADJACENCY,   // plain Map — stable after init
    startNodeId:   startNode?.id ?? null,
});
</script>

<style scoped>
.hex-map-wrapper {
    position: absolute;
    inset: 0;
    background: #05050A;
    cursor: grab;
    user-select: none;
}

/* Scanline overlay */
.hex-map-wrapper::after {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent,
        transparent 2px,
        rgba(0, 0, 0, 0.08) 2px,
        rgba(0, 0, 0, 0.08) 4px
    );
    pointer-events: none;
    z-index: 10;
}

/* Particle canvas — sits behind the SVG */
.particle-layer {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.hex-map-wrapper.is-panning {
    cursor: grabbing;
}

.hex-map-svg {
    width: 100%;
    height: 100%;
    overflow: visible;
    position: relative;
    z-index: 1;
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
    font-size: 13px;
    font-weight: 600;
    fill: rgba(0, 255, 255, 0.88);
    text-anchor: middle;
    dominant-baseline: middle;
    pointer-events: none;
    letter-spacing: 0.16em;
    filter: drop-shadow(0 0 6px rgba(0, 255, 255, 0.55)) drop-shadow(0 0 2px rgba(0, 0, 0, 0.9));
}

.route-edge {
    fill: none;
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

/* ── Reachable node highlight ───────────────────────────────────────────────── */

.reachable-ring {
    fill: none;
    stroke: #00FFFF;
    stroke-width: 1.5;
    stroke-opacity: 0.75;
    filter: url(#reachable-glow);
    transform-box: fill-box;
    transform-origin: center;
    animation: reachable-breathe 2s ease-in-out infinite;
    pointer-events: none;
}
.reachable-ring--locked {
    stroke: #FF3333;
    stroke-opacity: 0.5;
    stroke-dasharray: 4 3;
    animation: reachable-locked 1.5s ease-in-out infinite;
}

@keyframes reachable-breathe {
    0%, 100% { stroke-opacity: 0.75; }
    50%       { stroke-opacity: 0.2;  }
}
@keyframes reachable-locked {
    0%, 100% { stroke-opacity: 0.5; }
    50%       { stroke-opacity: 0.15; }
}

/* ── Player marker ──────────────────────────────────────────────────────────── */

.node-clickable {
    cursor: pointer;
}

/* Invisible hit area — same radius as the cyan reachable ring for easy clicking */
.node-hit-area {
    fill: transparent;
    stroke: none;
}

/* Hover tooltip */
.tooltip-bg {
    fill: rgba(5, 5, 10, 0.88);
    stroke: rgba(0, 255, 255, 0.35);
    stroke-width: 0.75;
    rx: 3;
    ry: 3;
}
.tooltip-line {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    fill: rgba(0, 255, 255, 0.9);
    letter-spacing: 0.06em;
}
.tooltip-line--dim {
    fill: rgba(0, 255, 255, 0.5);
    font-size: 16px;
}
.tooltip-line--creds {
    fill: rgba(255, 179, 0, 0.85);
}
.tooltip-line--cyberdoc {
    fill: #FFB300;
    font-size: 16px;
}
.tooltip-line--safe {
    fill: rgba(0, 255, 136, 0.85);
    font-size: 16px;
}

.district-yellow-hub {
    cursor: pointer;
}

.player-ring {
    fill: none;
    stroke: #FF69B4;
    stroke-width: 2.5;
    stroke-opacity: 0.9;
    filter: url(#player-glow);
    transform-box: fill-box;
    transform-origin: center;
    animation: player-pulse 3.5s ease-in-out infinite;
    pointer-events: none;
}

@keyframes player-pulse {
    0%, 100% {
        transform: scale(1);
        stroke-opacity: 0.9;
        stroke-width: 2.5;
    }
    50% {
        transform: scale(1.5);
        stroke-opacity: 0.15;
        stroke-width: 1.5;
    }
}

/* ── ICE Ping rings ──────────────────────────────────────────────────────────── */

.ping-ring {
    fill: none;
    stroke-width: 1.5;
    transform-box: fill-box;
    transform-origin: center;
    pointer-events: none;
}
/* Real ping — bounty not yet open season */
.ping-ring--bounty {
    stroke: #FFB300;
    stroke-opacity: 0.55;
    stroke-dasharray: 6 4;
    animation: ping-pulse 2.4s ease-in-out infinite;
}
/* Open season — bright red, faster pulse */
.ping-ring--os {
    stroke: #FF3333;
    stroke-opacity: 0.7;
    stroke-dasharray: none;
    animation: ping-pulse 1.4s ease-in-out infinite;
}
/* False ping (Signal Noise) — teal, slightly faded so attentive players might notice */
.ping-ring--false {
    stroke: #00FFFF;
    stroke-opacity: 0.45;
    stroke-dasharray: 4 6;
    animation: ping-pulse 2.8s ease-in-out infinite;
}

.ping-dot {
    pointer-events: none;
    transform-box: fill-box;
    transform-origin: center;
    animation: ping-dot-pulse 1.4s ease-in-out infinite;
}
.ping-dot--bounty { fill: #FFB300; }
.ping-dot--os     { fill: #FF3333; }
.ping-dot--false  { fill: #00FFFF; opacity: 0.5; }

/* Range label — ±N shown above the ring */
.ping-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    fill: rgba(255, 179, 0, 0.6);
    text-anchor: middle;
    pointer-events: none;
    letter-spacing: 0.05em;
}
.ping-label--os { fill: rgba(255, 51, 51, 0.7); }

@keyframes ping-pulse {
    0%, 100% { stroke-opacity: 0.6; }
    50%       { stroke-opacity: 0.15; }
}

@keyframes ping-dot-pulse {
    0%, 100% { opacity: 1;   transform: scale(1);   }
    50%       { opacity: 0.3; transform: scale(0.6); }
}

/* ── Crash mine markers ──────────────────────────────────────────────────────── */

.mine-ring {
    fill: rgba(255, 69, 180, 0.08);
    stroke: rgba(255, 69, 180, 0.75);
    stroke-width: 1.5;
    stroke-dasharray: 3 2;
    animation: mine-pulse 1.4s ease-in-out infinite;
}

.mine-cross {
    stroke: rgba(255, 69, 180, 0.85);
    stroke-width: 1.5;
    stroke-linecap: round;
    animation: mine-pulse 1.4s ease-in-out infinite;
}

.mine-ttl {
    font-family: 'JetBrains Mono', monospace;
    font-size: 7px;
    fill: rgba(255, 69, 180, 0.65);
    text-anchor: middle;
    letter-spacing: 0.08em;
}

@keyframes mine-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

/* ── Quest objective markers ─────────────────────────────────────────────── */
.qm-pulse {
    fill: none;
    stroke-width: 1.5;
    opacity: 0.6;
    animation: qm-pulse 1.8s ease-in-out infinite;
}
@keyframes qm-pulse {
    0%, 100% { r: 18; opacity: 0.6; }
    50%       { r: 22; opacity: 0.25; }
}
.qm-diamond {
    opacity: 0.85;
    fill-opacity: 0.15;
    stroke-width: 1.5;
    animation: qm-diamond-pulse 1.8s ease-in-out infinite;
}
@keyframes qm-diamond-pulse {
    0%, 100% { opacity: 0.85; }
    50%       { opacity: 0.5; }
}
</style>
