<template>
    <div ref="container" class="map-canvas-wrapper">
        <svg
            ref="svgEl"
            class="map-svg"
            viewBox="0 0 1200 800"
            preserveAspectRatio="xMidYMid meet"
        />

        <!-- Node tooltip — Vue-owned, positioned by mouse -->
        <div
            v-if="tooltipNode"
            class="node-tooltip"
            :style="{ left: tooltipX + 'px', top: tooltipY + 'px' }"
        >
            <div class="tt-name">{{ tooltipNode.label }}</div>
            <div class="tt-row">
                <span class="tt-key">TIER</span>
                <span class="tt-val">{{ tooltipNode.tier ?? '?' }}</span>
            </div>
            <div class="tt-row">
                <span class="tt-key">STATE</span>
                <span class="tt-val tt-state" :class="`tt-state--${tooltipNode.state}`">
                    {{ tooltipNode.state?.replace('_', ' ').toUpperCase() }}
                </span>
            </div>
            <div class="tt-row">
                <span class="tt-key">CREDS</span>
                <span class="tt-val" :class="tooltipNode.credDepleted ? 'tt-depleted' : 'tt-available'">
                    {{ tooltipNode.credDepleted ? 'DEPLETED' : 'AVAILABLE' }}
                </span>
            </div>
            <div class="tt-row">
                <span class="tt-key">FUEL</span>
                <span class="tt-val" :class="tooltipNode.movementDepleted ? 'tt-depleted' : 'tt-available'">
                    {{ tooltipNode.movementDepleted ? 'DEPLETED' : 'AVAILABLE' }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import * as d3 from 'd3';
import { renderPings } from './PingMarker.js';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    nodes:         { type: Array,  default: () => [] },
    links:         { type: Array,  default: () => [] },
    pings:         { type: Array,  default: () => [] },
    currentNodeId: { type: String, default: null     },
});

const emit = defineEmits(['node-clicked', 'street-doc-selected']);

// ─── Refs ─────────────────────────────────────────────────────────────────────
const container = ref(null);
const svgEl     = ref(null);
let   simulation  = null;
let   packetTimer = null;
let   linkEls     = []; // array of SVG path DOM nodes for packet animation

const nodePositions = new Map();
// linkElMap: `${idA}|${idB}` (both orderings) → { el: SVGPathElement, forward: bool }
const linkElMap     = new Map();
let   pingsGroup    = null;
let   playerGroup   = null;
let   nodesData     = [];
let   isAnimating   = false;

// ─── Tooltip state ────────────────────────────────────────────────────────────
const tooltipNode    = ref(null);
const tooltipX       = ref(0);
const tooltipY       = ref(0);
let   tooltipTimeout = null;

// ─── District cluster centres ─────────────────────────────────────────────────
const DISTRICT_CENTRES = {
    'Downtown':             { x: 540, y: 390 },
    'South Hill':           { x: 660, y: 600 },
    'University District':  { x: 680, y: 195 },
    "Browne's Addition":    { x: 290, y: 400 },
    'North Spokane':        { x: 520, y: 170 },
    'Spokane Valley':       { x: 880, y: 430 },
    'North Side':           { x: 560, y: 175 },
    'East Valley':          { x: 830, y: 420 },
    'West Side':            { x: 280, y: 430 },
};

function cx(district) { return DISTRICT_CENTRES[district]?.x ?? 600; }
function cy(district) { return DISTRICT_CENTRES[district]?.y ?? 400; }

// ─── Build graph ──────────────────────────────────────────────────────────────
function initD3() {
    if (!svgEl.value || !props.nodes.length) return;

    if (packetTimer) { clearInterval(packetTimer); packetTimer = null; }
    linkEls = [];

    const svg = d3.select(svgEl.value);
    svg.selectAll('*').remove();
    nodePositions.clear();

    nodesData = props.nodes.map(n => ({ ...n }));
    const linksData = props.links.map(l => ({ ...l }));

    // ── Layer order ───────────────────────────────────────────────────────────
    const linksGroup    = svg.append('g').attr('class', 'links-group');
    const packetsGroup  = svg.append('g').attr('class', 'packets-group');
    pingsGroup          = svg.append('g').attr('class', 'pings-group');
    const labelsGroup   = svg.append('g').attr('class', 'labels-group').style('opacity', 0);
    const nodesGroup    = svg.append('g').attr('class', 'nodes-group');
    playerGroup         = svg.append('g').attr('class', 'player-group');

    // ── Links ─────────────────────────────────────────────────────────────────
    const link = linksGroup
        .selectAll('path.link-line')
        .data(linksData)
        .join('path')
        .attr('class', 'link-line');

    // ── Nodes ─────────────────────────────────────────────────────────────────
    const nodeGroups = nodesGroup
        .selectAll('g.node')
        .data(nodesData)
        .join('g')
        .attr('class', d => `node node-${d.state}`)
        .style('cursor', 'pointer')
        .on('click', (event, d) => {
            if (isAnimating) return;
            if (d.state === 'street_doc') {
                emit('street-doc-selected', d);
                return;
            }
            const rect = container.value?.getBoundingClientRect();
            emit('node-clicked', {
                node:    d,
                screenX: rect ? event.clientX - rect.left : event.clientX,
                screenY: rect ? event.clientY - rect.top  : event.clientY,
            });
        })
        .on('mouseenter', (event, d) => {
            clearTimeout(tooltipTimeout);
            const rect = container.value?.getBoundingClientRect();
            if (rect) {
                tooltipX.value = event.clientX - rect.left + 14;
                tooltipY.value = event.clientY - rect.top  + 14;
            }
            tooltipNode.value = d;
            tooltipTimeout = setTimeout(() => { tooltipNode.value = null; }, 2000);
        })
        .on('mousemove', (event) => {
            const rect = container.value?.getBoundingClientRect();
            if (rect) {
                tooltipX.value = event.clientX - rect.left + 14;
                tooltipY.value = event.clientY - rect.top  + 14;
            }
            clearTimeout(tooltipTimeout);
            tooltipTimeout = setTimeout(() => { tooltipNode.value = null; }, 2000);
        })
        .on('mouseleave', () => {
            clearTimeout(tooltipTimeout);
            tooltipNode.value = null;
        });

    // Circle
    nodeGroups.append('circle')
        .attr('r', d => d.state === 'street_doc' ? 8 : 6)
        .attr('class', 'node-circle')
        // Random animation-delay so nodes don't pulse in sync
        .style('animation-delay', () => `-${(Math.random() * 3).toFixed(2)}s`);

    // Label
    nodeGroups.append('text')
        .attr('class', 'node-label')
        .attr('dy', '-12')
        .attr('text-anchor', 'middle')
        .text(d => d.label ?? '');

    // ── Simulation ────────────────────────────────────────────────────────────
    simulation = d3.forceSimulation(nodesData)
        .force('link',
            d3.forceLink(linksData).id(d => d.id).distance(85).strength(0.45),
        )
        .force('charge',
            d3.forceManyBody().strength(-180).distanceMax(350),
        )
        .force('collide',
            d3.forceCollide().radius(d => d.state === 'street_doc' ? 16 : 12).strength(0.8),
        )
        .force('center', d3.forceCenter(600, 400).strength(0.04))
        .force('districtX', d3.forceX(d => cx(d.district)).strength(0.08))
        .force('districtY', d3.forceY(d => cy(d.district)).strength(0.08))
        .alphaDecay(0.025)
        .velocityDecay(0.4);

    simulation.on('tick', () => {
        nodesData.forEach(n => nodePositions.set(n.id, { x: n.x ?? 0, y: n.y ?? 0 }));

        link.attr('d', d => {
            const dx   = d.target.x - d.source.x;
            const dy   = d.target.y - d.source.y;
            const dist = Math.hypot(dx, dy);
            const dr   = dist * 2.4;
            return `M${d.source.x},${d.source.y}A${dr},${dr} 0 0,1 ${d.target.x},${d.target.y}`;
        });

        nodeGroups.attr('transform', d => `translate(${d.x ?? 0},${d.y ?? 0})`);
        refreshPings();
        refreshPlayerMarker();
    });

    // On settle: fade in district labels + start data packets + index links
    simulation.on('end', () => {
        renderDistrictLabels(labelsGroup, nodesData);
        labelsGroup.transition().duration(1200).style('opacity', 1);

        // Collect rendered link paths for packet animation and movement
        linkEls = [];
        linkElMap.clear();
        linksGroup.selectAll('path.link-line').each(function(d) {
            linkEls.push(this);
            const sId = typeof d.source === 'object' ? d.source.id : d.source;
            const tId = typeof d.target === 'object' ? d.target.id : d.target;
            linkElMap.set(`${sId}|${tId}`, { el: this, forward: true  });
            linkElMap.set(`${tId}|${sId}`, { el: this, forward: false });
        });
        startPackets(packetsGroup);
    });
}

// ─── District labels ──────────────────────────────────────────────────────────
function renderDistrictLabels(group, nodes) {
    // Centroid per district
    const districtMap = new Map();
    nodes.forEach(n => {
        if (!districtMap.has(n.district)) districtMap.set(n.district, []);
        districtMap.get(n.district).push({ x: n.x ?? 0, y: n.y ?? 0 });
    });

    group.selectAll('text.district-label').remove();

    districtMap.forEach((pts, district) => {
        const cx = pts.reduce((s, p) => s + p.x, 0) / pts.length;
        const cy = pts.reduce((s, p) => s + p.y, 0) / pts.length;

        group.append('text')
            .attr('class', 'district-label')
            .attr('x', cx)
            .attr('y', cy - 28)
            .attr('text-anchor', 'middle')
            .text(district.toUpperCase());
    });
}

// ─── Data packets ─────────────────────────────────────────────────────────────
function startPackets(group) {
    if (!linkEls.length) return;

    function spawnPacket() {
        const pathEl = linkEls[Math.floor(Math.random() * linkEls.length)];
        if (!pathEl) return;
        const totalLength = pathEl.getTotalLength?.();
        if (!totalLength) return;

        const dot = group.append('circle')
            .attr('class', 'data-packet')
            .attr('r', 1.8);

        const duration = 800 + Math.random() * 1800;

        dot.transition()
            .duration(duration)
            .ease(d3.easeLinear)
            .tween('path-travel', () => t => {
                const pt = pathEl.getPointAtLength(t * totalLength);
                dot.attr('cx', pt.x).attr('cy', pt.y);
            })
            .on('end', function() { d3.select(this).remove(); });
    }

    // Spawn packets on a staggered random interval
    function scheduleNext() {
        const delay = 400 + Math.random() * 1200;
        packetTimer = setTimeout(() => {
            spawnPacket();
            scheduleNext();
        }, delay);
    }
    scheduleNext();
}

// ─── Ping layer ───────────────────────────────────────────────────────────────
function refreshPings() {
    if (!pingsGroup) return;
    renderPings(d3, pingsGroup, props.pings, nodePositions);
}

// ─── Player marker helpers ────────────────────────────────────────────────────
const CROSSHAIR_ARM = 6;
const CROSSHAIR_GAP = 2.5;
const RING_BASE_R   = 16;

function buildPlayerMarker(parent, x, y) {
    const g = parent.append('g')
        .attr('class', 'player-marker')
        .attr('transform', `translate(${x},${y})`);

    // Sonar rings — CSS animates scale from 1→1.5 while fading
    g.append('circle').attr('class', 'player-ring-pulse').attr('r', RING_BASE_R);
    g.append('circle').attr('class', 'player-ring-ghost').attr('r', RING_BASE_R);

    // Crosshair — 4 arms with gap, center dot
    const A = CROSSHAIR_ARM, G = CROSSHAIR_GAP;
    g.append('line').attr('class', 'player-cross').attr('x1', -A).attr('y1',  0).attr('x2', -G).attr('y2',  0);
    g.append('line').attr('class', 'player-cross').attr('x1',  G).attr('y1',  0).attr('x2',  A).attr('y2',  0);
    g.append('line').attr('class', 'player-cross').attr('x1',  0).attr('y1', -A).attr('x2',  0).attr('y2', -G);
    g.append('line').attr('class', 'player-cross').attr('x1',  0).attr('y1',  G).attr('x2',  0).attr('y2',  A);
    g.append('circle').attr('class', 'player-dot').attr('r', 1.8);

    return g;
}

// ─── Player marker — static ───────────────────────────────────────────────────
function refreshPlayerMarker() {
    if (!playerGroup || isAnimating) return;
    const pos = props.currentNodeId ? nodePositions.get(props.currentNodeId) : null;

    playerGroup.selectAll('g.player-marker').remove();
    if (pos) buildPlayerMarker(playerGroup, pos.x, pos.y);
}

// ─── Movement animation ───────────────────────────────────────────────────────
function animatePlayerTo(fromId, toId, onDone) {
    const entry = linkElMap.get(`${fromId}|${toId}`);
    if (!entry) { onDone?.(); return; }

    const { el: pathEl, forward } = entry;
    const totalLength = pathEl.getTotalLength?.();
    if (!totalLength) { onDone?.(); return; }

    isAnimating = true;

    // Remove static marker
    playerGroup.selectAll('g.player-marker').remove();

    // Build travel marker at from-position
    const fromPos = nodePositions.get(fromId) ?? { x: 0, y: 0 };
    const travel  = buildPlayerMarker(playerGroup, fromPos.x, fromPos.y);
    travel.classed('player-travel', true);

    travel.transition()
        .duration(600)
        .ease(d3.easeCubicInOut)
        .tween('travel', () => t => {
            const frac = forward ? t : 1 - t;
            const pt   = pathEl.getPointAtLength(frac * totalLength);
            travel.attr('transform', `translate(${pt.x},${pt.y})`);
        })
        .on('end', () => {
            travel.remove();
            isAnimating = false;
            onDone?.();
            // Watch on currentNodeId will fire refreshPlayerMarker after onDone
        });
}

// ─── SVG → screen coordinate conversion ──────────────────────────────────────
function getNodeScreenPos(nodeId) {
    const pos = nodePositions.get(nodeId);
    if (!pos || !svgEl.value || !container.value) return null;
    const svgRect  = svgEl.value.getBoundingClientRect();
    const contRect = container.value.getBoundingClientRect();
    const scaleX   = svgRect.width  / 1200;
    const scaleY   = svgRect.height / 800;
    return {
        x: pos.x * scaleX + (svgRect.left - contRect.left),
        y: pos.y * scaleY + (svgRect.top  - contRect.top),
    };
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => initD3());

watch(() => [props.nodes, props.links], () => {
    simulation?.stop();
    initD3();
}, { deep: false });

watch(() => props.pings,         () => refreshPings());
watch(() => props.currentNodeId, () => refreshPlayerMarker());

onUnmounted(() => {
    simulation?.stop();
    if (packetTimer) clearTimeout(packetTimer);
    clearTimeout(tooltipTimeout);
});

defineExpose({ animatePlayerTo, getNodeScreenPos });
</script>

<style scoped>
.map-canvas-wrapper {
    position: absolute;
    inset: 0;
}

.map-svg {
    width: 100%;
    height: 100%;
    pointer-events: all;
    overflow: visible;
}

/* ── Links ──────────────────────────────────────────────────────────────────── */
:deep(.link-line) {
    fill: none;
    stroke: #00FFFF;
    stroke-width: 1;
    stroke-opacity: 0.2;
}

/* ── Data packets ───────────────────────────────────────────────────────────── */
:deep(.data-packet) {
    fill: #00FFFF;
    opacity: 0.55;
    pointer-events: none;
}

/* ── Node base ──────────────────────────────────────────────────────────────── */
:deep(.node-circle) {
    transform-box: fill-box;
    transform-origin: center;
}

/* ── Active ─────────────────────────────────────────────────────────────────── */
:deep(.node-active .node-circle) {
    fill: #FF00CC;
    stroke: #FF00CC;
    stroke-width: 1.5;
    stroke-opacity: 0.6;
    animation: node-pulse 2.2s ease-in-out infinite;
}

/* ── Hacked ─────────────────────────────────────────────────────────────────── */
:deep(.node-hacked .node-circle) {
    fill: rgba(255, 0, 204, 0.4);
    stroke: #FF00CC;
    stroke-width: 1;
    stroke-opacity: 0.3;
    animation: node-flicker 2.8s steps(1) infinite;
}

/* ── Scorched ───────────────────────────────────────────────────────────────── */
:deep(.node-scorched .node-circle) {
    fill: #2A2A2A;
    stroke: #444444;
    stroke-width: 1;
}

/* ── Street Doc ─────────────────────────────────────────────────────────────── */
:deep(.node-street_doc .node-circle) {
    fill: #FFB300;
    stroke: #FFB300;
    stroke-width: 2;
    stroke-opacity: 0.7;
    animation: street-doc-pulse 3.5s ease-in-out infinite;
}

/* ── Labels ─────────────────────────────────────────────────────────────────── */
:deep(.node-label) {
    font-family: 'JetBrains Mono', monospace;
    font-size: 7px;
    fill: #00FFFF;
    fill-opacity: 0;
    pointer-events: none;
    letter-spacing: 0.04em;
}
:deep(.node:hover .node-label)          { fill-opacity: 0.9; }
:deep(.node-street_doc .node-label)     { fill: #FFB300; }

/* ── District labels ────────────────────────────────────────────────────────── */
:deep(.district-label) {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    fill: rgba(0, 255, 255, 0.2);
    letter-spacing: 0.12em;
    pointer-events: none;
    text-transform: uppercase;
}

/* ── Ping markers ───────────────────────────────────────────────────────────── */
:deep(.ping-marker) { pointer-events: none; }
:deep(.ping-loud) {
    animation: ping-loud-pulse 2.5s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: center;
}

/* ── Player marker ──────────────────────────────────────────────────────────── */
:deep(.player-ring-pulse) {
    fill: none;
    stroke: #00FF41;
    stroke-width: 1.5;
    pointer-events: none;
    transform-box: fill-box;
    transform-origin: center;
    animation: sonar-pulse 1.5s ease-out infinite;
}

:deep(.player-ring-ghost) {
    fill: none;
    stroke: #00FF41;
    stroke-width: 1;
    pointer-events: none;
    transform-box: fill-box;
    transform-origin: center;
    animation: sonar-pulse 1.5s ease-out infinite;
    animation-delay: -0.75s;
}

:deep(.player-cross) {
    stroke: #00FF41;
    stroke-width: 1.5;
    stroke-linecap: round;
    pointer-events: none;
}

:deep(.player-dot) {
    fill: #00FF41;
    pointer-events: none;
}

/* ── Keyframes ──────────────────────────────────────────────────────────────── */
@keyframes node-pulse {
    0%, 100% { transform: scale(1);    opacity: 1;    }
    50%       { transform: scale(1.15); opacity: 0.85; }
}
@keyframes node-flicker {
    0%   { opacity: 1;    }
    8%   { opacity: 0.2;  }
    10%  { opacity: 0.9;  }
    55%  { opacity: 0.9;  }
    57%  { opacity: 0.15; }
    59%  { opacity: 0.85; }
    100% { opacity: 0.85; }
}
@keyframes street-doc-pulse {
    0%, 100% { transform: scale(1);    stroke-opacity: 0.7; }
    50%       { transform: scale(1.12); stroke-opacity: 1;   }
}
@keyframes ping-loud-pulse {
    0%, 100% { transform: scale(1);   opacity: 0.9;  }
    50%       { transform: scale(1.3); opacity: 0.55; }
}
@keyframes sonar-pulse {
    0%   { transform: scale(1);   stroke-opacity: 0.75; }
    100% { transform: scale(1.5); stroke-opacity: 0;    }
}

/* ── Node tooltip ───────────────────────────────────────────────────────────── */
.node-tooltip {
    position: absolute;
    pointer-events: none;
    z-index: 25;
    background: rgba(5, 5, 5, 0.95);
    border: 1px solid rgba(0, 255, 255, 0.4);
    padding: 8px 12px;
    min-width: 160px;
    box-shadow: 0 0 12px rgba(0, 255, 255, 0.1);
    animation: tooltip-appear 0.12s ease-out forwards;
}

@keyframes tooltip-appear {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0);    }
}

.tt-name {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: #00FFFF;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.15);
    padding-bottom: 5px;
}

.tt-row {
    display: flex;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    line-height: 1.7;
}

.tt-key { color: rgba(0, 255, 255, 0.4); min-width: 44px; letter-spacing: 0.04em; }
.tt-val { color: rgba(0, 255, 255, 0.8); }

.tt-state--active     { color: #FF00CC; }
.tt-state--hacked     { color: rgba(255, 0, 204, 0.5); }
.tt-state--scorched   { color: #444444; }
.tt-state--street_doc { color: #FFB300; }

.tt-available { color: #00FF88; }
.tt-depleted  { color: rgba(255, 51, 51, 0.7); }
</style>
