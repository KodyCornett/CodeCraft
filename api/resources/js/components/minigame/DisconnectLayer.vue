<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="dl-wrap">
            <!-- Rerouting flash -->
            <Transition name="dl-reroute">
                <div v-if="rerouting" class="dl-rerouting">⟳ REROUTING...</div>
            </Transition>

            <svg class="dl-svg" viewBox="0 0 620 280" xmlns="http://www.w3.org/2000/svg">

                <!-- Invisible fat hit areas for edges (makes clicking reliable) -->
                <line
                    v-for="e in edges"
                    :key="`hit-${e.id}`"
                    :x1="NODES[e.from].x" :y1="NODES[e.from].y"
                    :x2="NODES[e.to].x"   :y2="NODES[e.to].y"
                    stroke="transparent" stroke-width="22"
                    :style="{ cursor: e.cut ? 'default' : 'pointer' }"
                    @click="onEdgeClick(e)"
                />

                <!-- Visible edges -->
                <line
                    v-for="e in edges"
                    :key="`edge-${e.id}`"
                    :x1="NODES[e.from].x" :y1="NODES[e.from].y"
                    :x2="NODES[e.to].x"   :y2="NODES[e.to].y"
                    v-bind="edgeAttrs(e)"
                    pointer-events="none"
                />

                <!-- Data pulses travelling along the governor chain -->
                <circle
                    v-for="p in pulses"
                    :key="p.id"
                    :cx="p.sx + (p.ex - p.sx) * p.t"
                    :cy="p.sy + (p.ey - p.sy) * p.t"
                    r="4"
                    class="dl-pulse"
                    pointer-events="none"
                />

                <!-- Nodes (rendered last — on top of edges) -->
                <g v-for="n in NODES" :key="`node-${n.id}`" pointer-events="none">
                    <circle :cx="n.x" :cy="n.y" r="16" v-bind="nodeAttrs(n.id)" />
                    <text  :x="n.x"  :y="n.y + 4" class="dl-node-text">
                        {{ n.label ?? n.id }}
                    </text>
                </g>

            </svg>

            <!-- Legend -->
            <div class="dl-legend">
                <span class="dl-leg dl-leg--chain">── GOVERNOR</span>
                <span class="dl-leg dl-leg--idle">── IDLE</span>
                <span class="dl-leg dl-leg--cut">╌╌ SEVERED</span>
            </div>
        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

const props = defineProps({
    skin: { type: Object, required: true },
});
const emit = defineEmits(['complete', 'fail']);

// ── Network definition ────────────────────────────────────────────────────────

const SOURCE = 0;
const TARGET = 10;

const NODES = [
    { id: 0,  x: 42,  y: 140, label: 'SRC' },
    { id: 1,  x: 155, y: 58  },
    { id: 2,  x: 155, y: 140 },
    { id: 3,  x: 155, y: 222 },
    { id: 4,  x: 278, y: 58  },
    { id: 5,  x: 278, y: 140 },
    { id: 6,  x: 278, y: 222 },
    { id: 7,  x: 400, y: 58  },
    { id: 8,  x: 400, y: 140 },
    { id: 9,  x: 400, y: 222 },
    { id: 10, x: 570, y: 140, label: 'GOV' },
];

// Undirected edge definitions
const EDGES_DEF = [
    [0,1],[0,2],[0,3],
    [1,2],[1,4],
    [2,3],[2,5],
    [3,6],
    [4,5],[4,7],
    [5,6],[5,8],
    [6,9],
    [7,8],[7,10],
    [8,9],[8,10],
    [9,10],
];

function eid(a, b) { return `${Math.min(a,b)}-${Math.max(a,b)}`; }

// ── Game state ────────────────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, endGame,
} = useQuestMinigameState(props.skin);

const edges = ref(
    EDGES_DEF.map(([a, b]) => ({ id: eid(a, b), from: a, to: b, cut: false }))
);

const governorPath = ref([]);   // node ID array — current active chain
const pulses       = ref([]);   // { id, sx, sy, ex, ey, t }
const rerouting    = ref(false);

let rerouteTimer   = 0;
let rerouteCount   = 0;
let pulseIdSeq     = 0;
let pulseSpawnTimer = 0;
let animFrame      = null;
let lastTs         = null;

// Edge IDs that form the current governor chain
const chainEdgeIds = computed(() => {
    const path = governorPath.value;
    return new Set(
        path.slice(0, -1).map((n, i) => eid(n, path[i + 1]))
    );
});

const chainNodeIds = computed(() => new Set(governorPath.value));

// ── Chrome passthrough ────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: primaryProgress.value,
    stability:       stability.value,
    stabilityClass:  stabilityClass.value,
    timerClass:      timerClass.value,
    glitchActive:    glitchActive.value,
    glitchType:      glitchType.value,
    glitchIntensity: glitchIntensity.value,
    result:          result.value,
    failReason:      failReason.value,
}));

// ── Pathfinding (BFS) ─────────────────────────────────────────────────────────

function bfs() {
    const adj = {};
    for (const e of edges.value) {
        if (e.cut) continue;
        (adj[e.from] ??= []).push(e.to);
        (adj[e.to]   ??= []).push(e.from);
    }
    const queue = [[SOURCE]];
    const seen  = new Set([SOURCE]);
    while (queue.length) {
        const path = queue.shift();
        const node = path.at(-1);
        if (node === TARGET) return path;
        for (const nb of (adj[node] ?? [])) {
            if (!seen.has(nb)) { seen.add(nb); queue.push([...path, nb]); }
        }
    }
    return null;
}

function applyPath(path) {
    governorPath.value = path;
    pulses.value       = [];
    pulseSpawnTimer    = 0;
}

function triggerReroute() {
    rerouting.value = true;
    // Reroute window shrinks as the governor adapts (min 0.4s)
    rerouteTimer = Math.max(0.4, 1.1 - rerouteCount * 0.1);
    pulses.value = [];
}

// ── Edge interaction ──────────────────────────────────────────────────────────

function onEdgeClick(edge) {
    if (edge.cut || result.value || rerouting.value) return;
    edge.cut = true;
    pulses.value = pulses.value.filter(p => p.edgeId !== edge.id);
    if (chainEdgeIds.value.has(edge.id)) {
        triggerReroute();
    }
}

// ── Pulse helpers ─────────────────────────────────────────────────────────────

const PULSE_SPEED = 0.65;   // t-units per second

function spawnPulse(edgeId) {
    const e = edges.value.find(e => e.id === edgeId);
    if (!e || e.cut) return;
    const path = governorPath.value;
    const fromIdx = path.indexOf(e.from);
    const forward = fromIdx >= 0 && path[fromIdx + 1] === e.to;
    const [sn, en] = forward ? [NODES[e.from], NODES[e.to]] : [NODES[e.to], NODES[e.from]];
    pulses.value.push({
        id: pulseIdSeq++, edgeId,
        sx: sn.x, sy: sn.y,
        ex: en.x, ey: en.y,
        t: 0,
    });
}

function seedPulses() {
    // Spread 4 pulses across chain edges at staggered starting positions
    const path = governorPath.value;
    if (path.length < 2) return;
    const edgeCount = path.length - 1;
    for (let i = 0; i < Math.min(4, edgeCount * 2); i++) {
        const edgeIdx = i % edgeCount;
        const a = path[edgeIdx], b = path[edgeIdx + 1];
        const edgeId = eid(a, b);
        const e = edges.value.find(e => e.id === edgeId);
        if (!e || e.cut) continue;
        const forward = true;
        const [sn, en] = [NODES[a], NODES[b]];
        pulses.value.push({
            id: pulseIdSeq++, edgeId,
            sx: sn.x, sy: sn.y,
            ex: en.x, ey: en.y,
            t: (i / 4),    // stagger them across the chain
        });
    }
}

// ── SVG attribute helpers ─────────────────────────────────────────────────────

function edgeAttrs(e) {
    if (e.cut)                        return { stroke: 'rgba(60,100,80,0.2)', 'stroke-width': 1, 'stroke-dasharray': '4 4' };
    if (chainEdgeIds.value.has(e.id)) return { stroke: '#ff6600',             'stroke-width': 2 };
    return                                   { stroke: 'rgba(0,255,100,0.15)', 'stroke-width': 1.5 };
}

function nodeAttrs(id) {
    if (id === SOURCE) return { fill: '#001a10', stroke: '#00ff9d', 'stroke-width': 2 };
    if (id === TARGET) return { fill: '#1a0800', stroke: '#ff6600', 'stroke-width': 2 };
    if (chainNodeIds.value.has(id)) return { fill: '#0d0800', stroke: '#ff6600', 'stroke-width': 1.5 };
    return { fill: '#050e08', stroke: 'rgba(0,255,100,0.3)', 'stroke-width': 1 };
}

// ── Game loop ─────────────────────────────────────────────────────────────────

function tick(ts) {
    if (result.value) return;
    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    // Shared bars
    const failCause = tickShared(dt);
    if (failCause) {
        const reason = failCause === 'stability'
            ? '[STABILITY CRITICAL] — System failure.'
            : (props.skin.failText ?? 'Trace complete. Connection lost.');
        endGame('fail', reason);
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Reroute countdown
    if (rerouting.value) {
        rerouteTimer -= dt;
        if (rerouteTimer <= 0) {
            rerouting.value = false;
            const path = bfs();
            if (!path) {
                endGame('success');
                setTimeout(() => emit('complete'), 2200);
                return;
            }
            rerouteCount++;
            applyPath(path);
            seedPulses();
        }
        animFrame = requestAnimationFrame(tick);
        return;
    }

    // Advance pulses; wrap finished ones back to t=0 (continuous flow)
    pulses.value.forEach(p => { p.t += PULSE_SPEED * dt; });
    pulses.value.forEach(p => { if (p.t >= 1) p.t = 0; });

    // Spawn a new pulse periodically on the chain start
    pulseSpawnTimer -= dt;
    if (pulseSpawnTimer <= 0 && governorPath.value.length >= 2) {
        const path = governorPath.value;
        spawnPulse(eid(path[0], path[1]));
        // Cap pulse count to avoid overcrowding
        if (pulses.value.length > 12) pulses.value.shift();
        pulseSpawnTimer = 0.38 + Math.random() * 0.12;
    }

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    const path = bfs();
    if (path) { applyPath(path); seedPulses(); }
    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
.dl-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
}

/* Rerouting banner */
.dl-rerouting {
    position: absolute;
    top: 12px;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #ff6600;
    letter-spacing: 0.15em;
    z-index: 10;
    animation: dl-flash 0.3s steps(1) infinite;
}
.dl-reroute-enter-active, .dl-reroute-leave-active { transition: opacity 0.15s; }
.dl-reroute-enter-from,   .dl-reroute-leave-to     { opacity: 0; }

/* SVG */
.dl-svg {
    width: 100%;
    max-width: 640px;
    height: auto;
    display: block;
}

/* Pulses */
.dl-pulse {
    fill: #ff6600;
    filter: drop-shadow(0 0 4px #ff6600);
    opacity: 0.9;
}

/* Node labels */
.dl-node-text {
    fill: rgba(0,255,100,0.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    text-anchor: middle;
    pointer-events: none;
    letter-spacing: 0.05em;
}

/* Legend */
.dl-legend {
    display: flex;
    gap: 20px;
    margin-top: 8px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    opacity: 0.6;
}
.dl-leg         { letter-spacing: 0.08em; }
.dl-leg--chain  { color: #ff6600; }
.dl-leg--idle   { color: rgba(0,255,100,0.5); }
.dl-leg--cut    { color: rgba(60,100,80,0.6); }

@keyframes dl-flash { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }
</style>
