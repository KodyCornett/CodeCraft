<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="cf-wrap">

            <!-- ── HUD ─────────────────────────────────────────────────────────── -->
            <div class="cf-hud">
                <div class="cf-metric">
                    <span class="cf-ml">SYS STABILITY</span>
                    <div class="cf-mbar"><div class="cf-mfill cf-stab" :style="{ width: stability * 100 + '%' }"/></div>
                    <span class="cf-mv">{{ Math.round(stability * 100) }}%</span>
                </div>
                <div class="cf-metric">
                    <span class="cf-ml">CONDUIT PRESSURE</span>
                    <div class="cf-mbar"><div class="cf-mfill cf-pres" :style="{ width: conduitPressure + '%' }"/></div>
                    <span class="cf-mv cf-mv--warn">{{ conduitPressure }}%</span>
                </div>
                <div class="cf-metric">
                    <span class="cf-ml">FREQ BALANCE</span>
                    <div class="cf-mbar"><div class="cf-mfill cf-freq" :style="{ width: freqBalance + '%' }"/></div>
                    <span class="cf-mv cf-mv--danger">{{ freqBalance }}%</span>
                </div>
            </div>

            <!-- ── Arena ─────────────────────────────────────────────────────── -->
            <div class="cf-arena">

                <!-- Sources (left) -->
                <div class="cf-sources-col">
                    <div class="cf-src-spacer"/>
                    <div class="cf-src-list">
                        <div
                            v-for="src in activeSources"
                            :key="src.id"
                            class="cf-src-card"
                            :class="[`cf-t--${src.type}`, srcStatusClass(src.id)]"
                            :style="{ flex: `0 0 ${TILE_PX * 2}px` }"
                        >
                            <div class="cf-src-icon">{{ TYPE_META[src.type].symbol }}</div>
                            <div class="cf-src-info">
                                <div class="cf-src-name">{{ src.type }}</div>
                                <div class="cf-src-st">{{ flowResults[src.id] ?? 'UNROUTED' }}</div>
                                <div class="cf-src-timer">
                                    <div class="cf-src-tf" :style="{ width: (src.changeTimer / src.changeInterval * 100) + '%' }"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grid column -->
                <div class="cf-grid-col">
                    <!-- Top sinks row -->
                    <div class="cf-top-sinks">
                        <div class="cf-row-label-spacer"/>
                        <div
                            v-for="c in 10"
                            :key="c"
                            class="cf-top-sink-cell"
                            :style="{ width: TILE_PX + 'px' }"
                        >
                            <div
                                v-if="topSinkAt(c - 1)"
                                class="cf-sink-badge cf-sink-top"
                                :class="`cf-t--${topSinkAt(c - 1).accepts}`"
                            >
                                <span class="cf-sink-icon">{{ TYPE_META[topSinkAt(c - 1).accepts].symbol }}</span>
                                <span class="cf-sink-lbl">{{ topSinkAt(c - 1).label }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Col numbers -->
                    <div class="cf-col-nums">
                        <div class="cf-row-label-spacer"/>
                        <span
                            v-for="c in 10"
                            :key="c"
                            class="cf-col-n"
                            :style="{ width: TILE_PX + 'px' }"
                        >{{ c }}</span>
                    </div>

                    <!-- Grid rows -->
                    <div class="cf-grid">
                        <div v-for="(row, r) in grid" :key="r" class="cf-grid-row">
                            <span class="cf-row-label">{{ ROW_LABELS[r] }}</span>
                            <div
                                v-for="(tile, c) in row"
                                :key="c"
                                class="cf-tile"
                                :class="tileClass(r, c)"
                                :style="{ width: TILE_PX + 'px', height: TILE_PX + 'px' }"
                                @click="rotateTile(r, c)"
                            >
                                <svg :viewBox="`0 0 ${TILE_PX} ${TILE_PX}`" class="cf-tile-svg">
                                    <defs>
                                        <filter :id="`glow-${r}-${c}`" x="-50%" y="-50%" width="200%" height="200%">
                                            <feGaussianBlur stdDeviation="2" result="blur"/>
                                            <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
                                        </filter>
                                    </defs>
                                    <path
                                        v-if="tilePath(r, c)"
                                        :d="tilePath(r, c)"
                                        class="cf-pipe"
                                        :class="pipeClass(r, c)"
                                        :filter="tile.flowType ? `url(#glow-${r}-${c})` : ''"
                                        :stroke-width="TILE_PX * 0.22"
                                        fill="none"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right sinks + failure log -->
                <div class="cf-right-col">
                    <div class="cf-right-top"/>
                    <div class="cf-right-sinks">
                        <div
                            v-for="r in 10"
                            :key="r"
                            class="cf-right-sink-cell"
                            :style="{ height: TILE_PX + 'px' }"
                        >
                            <div
                                v-if="rightSinkAt(r - 1)"
                                class="cf-sink-badge cf-sink-right"
                                :class="[`cf-t--${rightSinkAt(r - 1).accepts}`, sinkConnected(r - 1) ? 'cf-sink--live' : '']"
                            >
                                <span class="cf-sink-icon">{{ TYPE_META[rightSinkAt(r - 1).accepts].symbol }}</span>
                                <div class="cf-sink-detail">
                                    <span class="cf-sink-lbl">{{ rightSinkAt(r - 1).label }}</span>
                                    <span class="cf-sink-st">{{ sinkConnected(r - 1) ? 'CONNECTED' : 'IDLE' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Failure log -->
                    <div class="cf-fail-log">
                        <div class="cf-fail-title">FAILURE</div>
                        <div v-for="(entry, i) in failLog" :key="i" class="cf-fail-entry">
                            [{{ entry }}]
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── Status bar ─────────────────────────────────────────────────── -->
            <div class="cf-statusbar">
                <div
                    v-for="src in activeSources"
                    :key="src.id"
                    class="cf-sb-item"
                    :class="`cf-t--${src.type}`"
                >
                    <span class="cf-sb-name">{{ src.type }}</span>
                    <span class="cf-sb-st">{{ flowResults[src.id] ?? 'UNROUTED' }}</span>
                </div>
            </div>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Constants ─────────────────────────────────────────────────────────────────

const TILE_PX   = 34;
const ROW_LABELS = 'ABCDEFGHIJ'.split('');
const N = 1, E = 2, S = 4, W = 8;

const TYPE_META = {
    CACHE_FLOOD:  { symbol: '⬡', color: '#00d4ff' },
    STACK_LEAK:   { symbol: 'Ω', color: '#00ff9d' },
    SIGNAL_NOISE: { symbol: 'Ω', color: '#ffaa00' },
};
const ALL_TYPES = Object.keys(TYPE_META);

// Tile types: 0=empty 1=straight 2=corner 3=tee 4=cross
// Base connections at rotation=0
const BASE_CONN = [0, E|W, N|E, N|E|S, N|E|S|W];

// SVG pipe paths in viewBox(0,0,TILE_PX,TILE_PX) — all coords are proportional
// We'll scale by TILE_PX at render time; define paths in 0–40 space, rescale via transform
const H = 20, CX = 20, CY = 20; // center and half in 40-unit space
const PIPE_PATHS = {
    [N|S]:     `M ${CX},0 L ${CX},40`,
    [E|W]:     `M 0,${CY} L 40,${CY}`,
    [N|E]:     `M ${CX},0 Q ${CX},${CY} 40,${CY}`,
    [E|S]:     `M 40,${CY} Q ${CX},${CY} ${CX},40`,
    [S|W]:     `M ${CX},40 Q ${CX},${CY} 0,${CY}`,
    [W|N]:     `M 0,${CY} Q ${CX},${CY} ${CX},0`,
    [N|E|S]:   `M ${CX},0 L ${CX},40 M ${CX},${CY} L 40,${CY}`,
    [E|S|W]:   `M 0,${CY} L 40,${CY} M ${CX},${CY} L ${CX},40`,
    [S|W|N]:   `M ${CX},0 L ${CX},40 M ${CX},${CY} L 0,${CY}`,
    [W|N|E]:   `M 0,${CY} L 40,${CY} M ${CX},${CY} L ${CX},0`,
    [N|E|S|W]: `M 0,${CY} L 40,${CY} M ${CX},0 L ${CX},40`,
};

// Difficulty configs
const DIFF_CONFIGS = {
    1: {
        sources: [
            { row: 1, type: 'CACHE_FLOOD' },
            { row: 4, type: 'STACK_LEAK' },
            { row: 7, type: 'SIGNAL_NOISE' },
        ],
        sinks: [
            { col: 5, edge: 'top',   accepts: 'CACHE_FLOOD',  label: 'CACHE_FLOOD_DUMP' },
            { row: 5, edge: 'right', accepts: 'STACK_LEAK',   label: 'STACK_LEAK_DUMP' },
            { row: 8, edge: 'right', accepts: 'SIGNAL_NOISE', label: 'SIGNAL_NOISE_DUMP' },
        ],
        scramble: 0.6,
        changeRange: [20, 30],
    },
    2: {
        sources: [
            { row: 0, type: 'CACHE_FLOOD' },
            { row: 3, type: 'CACHE_FLOOD' },
            { row: 5, type: 'STACK_LEAK' },
            { row: 8, type: 'SIGNAL_NOISE' },
        ],
        sinks: [
            { col: 3, edge: 'top',   accepts: 'CACHE_FLOOD',  label: 'CACHE_FLOOD_DUMP' },
            { col: 7, edge: 'top',   accepts: 'CACHE_FLOOD',  label: 'CACHE_FLOOD_DUMP 2' },
            { row: 5, edge: 'right', accepts: 'STACK_LEAK',   label: 'STACK_LEAK_DUMP' },
            { row: 8, edge: 'right', accepts: 'SIGNAL_NOISE', label: 'SIGNAL_NOISE_DUMP' },
        ],
        scramble: 0.7,
        changeRange: [14, 22],
    },
    3: {
        sources: [
            { row: 0, type: 'CACHE_FLOOD' },
            { row: 2, type: 'CACHE_FLOOD' },
            { row: 4, type: 'STACK_LEAK' },
            { row: 6, type: 'SIGNAL_NOISE' },
            { row: 8, type: 'SIGNAL_NOISE' },
        ],
        sinks: [
            { col: 4, edge: 'top',   accepts: 'CACHE_FLOOD',  label: 'CACHE_FLOOD_DUMP' },
            { col: 8, edge: 'top',   accepts: 'CACHE_FLOOD',  label: 'CACHE_FLOOD_DUMP 2' },
            { row: 5, edge: 'right', accepts: 'STACK_LEAK',   label: 'STACK_LEAK_DUMP' },
            { row: 3, edge: 'right', accepts: 'SIGNAL_NOISE', label: 'SIGNAL_NOISE_DUMP' },
            { row: 7, edge: 'right', accepts: 'SIGNAL_NOISE', label: 'SIGNAL_NOISE_DUMP 2' },
        ],
        scramble: 0.8,
        changeRange: [9, 16],
    },
};

// ── Shared state ──────────────────────────────────────────────────────────────

const diff   = props.skin.difficulty ?? 1;
const config = DIFF_CONFIGS[diff] ?? DIFF_CONFIGS[1];

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    tickShared, applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Grid state ────────────────────────────────────────────────────────────────

// tile: { type:0-4, rotation:0|90|180|270, flowType:null|string, flowStatus:'none'|'correct'|'wrong'|'unrouted' }
const grid = ref([]);

// Sources: { id, row, type, changeTimer, changeInterval, status }
const activeSources = ref([]);

// Flow results per source: null | 'ROUTED' | 'WRONG SINK' | 'UNROUTED'
const flowResults = ref({});

// Sink live state
const sinkLive = ref({}); // sinkKey → true if correct flow reaches it

// Failure log (last 4)
const failLog = ref([]);

// ── Computed ──────────────────────────────────────────────────────────────────

const conduitPressure = computed(() => {
    const sources = activeSources.value;
    if (!sources.length) return 0;
    const bad = sources.filter(s => flowResults.value[s.id] !== 'ROUTED').length;
    return Math.round((bad / sources.length) * 100);
});

const freqBalance = computed(() => {
    const sources = activeSources.value;
    if (!sources.length) return 0;
    const good = sources.filter(s => flowResults.value[s.id] === 'ROUTED').length;
    return Math.round((good / sources.length) * 100);
});

// ── Connection helpers ────────────────────────────────────────────────────────

function rotateMaskCW(mask) {
    return ((mask & N) ? E : 0) |
           ((mask & E) ? S : 0) |
           ((mask & S) ? W : 0) |
           ((mask & W) ? N : 0);
}

function getConn(type, rotation) {
    if (type === 0) return 0;
    let mask = BASE_CONN[type];
    const steps = rotation / 90;
    for (let i = 0; i < steps; i++) mask = rotateMaskCW(mask);
    return mask;
}

// ── Tile display ──────────────────────────────────────────────────────────────

function tilePath(r, c) {
    const tile = grid.value[r]?.[c];
    if (!tile) return null;
    const conn = getConn(tile.type, tile.rotation);
    const raw  = PIPE_PATHS[conn];
    if (!raw) return null;
    // Scale from 40-unit space to TILE_PX space
    const s = TILE_PX / 40;
    return raw.replace(/[\d.]+/g, n => (parseFloat(n) * s).toFixed(2));
}

function tileClass(r, c) {
    const tile = grid.value[r]?.[c];
    if (!tile) return '';
    const cls = ['cf-tile-active'];
    if (tile.flowType)   cls.push(`cf-t--${tile.flowType}`);
    if (tile.flowStatus) cls.push(`cf-fs--${tile.flowStatus}`);
    return cls.join(' ');
}

function pipeClass(r, c) {
    const tile = grid.value[r]?.[c];
    if (!tile || !tile.flowType) return 'cf-pipe--dim';
    if (tile.flowStatus === 'correct')  return 'cf-pipe--correct';
    if (tile.flowStatus === 'wrong')    return 'cf-pipe--wrong';
    return 'cf-pipe--flow';
}

function srcStatusClass(id) {
    const r = flowResults.value[id];
    if (r === 'ROUTED')    return 'cf-src--ok';
    if (r === 'WRONG SINK') return 'cf-src--warn';
    return 'cf-src--bad';
}

function topSinkAt(col) {
    return config.sinks.find(s => s.edge === 'top' && s.col - 1 === col) ?? null;
}

function rightSinkAt(row) {
    return config.sinks.find(s => s.edge === 'right' && s.row - 1 === row) ?? null;
}

function sinkConnected(row) {
    const sink = rightSinkAt(row);
    if (!sink) return false;
    return !!sinkLive.value[`right-${row}`];
}

// ── Grid generation ───────────────────────────────────────────────────────────

function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }
function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = randInt(0, i);
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function connToTileRot(mask) {
    // Find a tile type and rotation that produces this connection mask
    for (let type = 1; type <= 4; type++) {
        for (let rotSteps = 0; rotSteps < 4; rotSteps++) {
            const rot = rotSteps * 90;
            if (getConn(type, rot) === mask) return { type, rotation: rot };
        }
    }
    return { type: 4, rotation: 0 }; // fallback: cross
}

function oppDir(dir) {
    return { [N]: S, [E]: W, [S]: N, [W]: E }[dir];
}

const DIRS = [
    { bit: N, dr: -1, dc: 0 },
    { bit: E, dr: 0,  dc: 1 },
    { bit: S, dr: 1,  dc: 0 },
    { bit: W, dr: 0,  dc: -1 },
];

function bfsPath(startRow, sinkDef, occupied) {
    // Find a path from [startRow, 0] to the sink
    // Sink is either top edge (exit N from row 0) or right edge (exit E from col 9)
    const visited = new Map(); // key → parent key
    const queue   = [[startRow, 0]];
    visited.set(`${startRow},0`, null);

    while (queue.length) {
        const [r, c] = queue.shift();

        // Check if we can reach the sink from here
        if (sinkDef.edge === 'top' && r === 0 && c === sinkDef.col - 1) return reconstruct(visited, r, c);
        if (sinkDef.edge === 'right' && c === 9 && r === sinkDef.row - 1) return reconstruct(visited, r, c);

        for (const { bit, dr, dc } of DIRS) {
            const nr = r + dr, nc = c + dc;
            const nk = `${nr},${nc}`;
            // Allow going off top edge only if targeting top sink and in right column
            if (sinkDef.edge === 'top' && nr < 0 && nc === sinkDef.col - 1 && r === 0) {
                return reconstruct(visited, r, c);
            }
            // Allow going off right edge only if targeting right sink and in right row
            if (sinkDef.edge === 'right' && nc > 9 && r === sinkDef.row - 1 && c === 9) {
                return reconstruct(visited, r, c);
            }
            if (nr < 0 || nr >= 10 || nc < 0 || nc >= 10) continue;
            if (visited.has(nk)) continue;
            if (occupied.has(nk)) continue;
            visited.set(nk, `${r},${c}`);
            queue.push([nr, nc]);
        }
    }
    return null; // no path found
}

function reconstruct(visited, endR, endC) {
    const path = [];
    let key = `${endR},${endC}`;
    while (key !== null) {
        const [r, c] = key.split(',').map(Number);
        path.unshift([r, c]);
        key = visited.get(key);
    }
    return path;
}

function generateGrid() {
    // Build empty 10×10 grid
    const g = Array.from({ length: 10 }, () =>
        Array.from({ length: 10 }, () => ({ type: 0, rotation: 0, flowType: null, flowStatus: 'none' }))
    );

    const occupied = new Set();
    const paths = [];

    // Generate a path for each source-sink pair
    for (let i = 0; i < config.sources.length; i++) {
        const src  = config.sources[i];
        const sink = config.sinks[i % config.sinks.length];
        const path = bfsPath(src.row, sink, occupied);
        if (!path) continue;
        paths.push({ path, sinkDef: sink });
        path.forEach(([r, c]) => occupied.add(`${r},${c}`));
    }

    // Place tiles along paths with correct rotations
    for (const { path, sinkDef } of paths) {
        for (let i = 0; i < path.length; i++) {
            const [r, c] = path[i];
            let mask = 0;

            // First cell must accept from W (source is on the left)
            if (i === 0) mask |= W;

            // Back-connection toward previous cell
            if (i > 0) {
                const [pr, pc] = path[i - 1];
                const dr = r - pr, dc = c - pc; // direction FROM prev TO current
                if      (dr === -1) mask |= S; // moved north → back is south
                else if (dr === 1)  mask |= N; // moved south → back is north
                else if (dc === 1)  mask |= W; // moved east  → back is west
                else if (dc === -1) mask |= E; // moved west  → back is east
            }

            // Connection to next cell
            if (i < path.length - 1) {
                const [nr, nc] = path[i + 1];
                const dr = nr - r, dc = nc - c;
                if (dr === -1) mask |= N;
                else if (dr === 1)  mask |= S;
                else if (dc === -1) mask |= W;
                else if (dc === 1)  mask |= E;
            } else {
                // Last cell: exit toward sink
                if (sinkDef.edge === 'top')   mask |= N;
                if (sinkDef.edge === 'right')  mask |= E;
            }

            const { type, rotation } = connToTileRot(mask);
            g[r][c] = { type, rotation, flowType: null, flowStatus: 'none', _solRot: rotation };
        }
    }

    // Fill remaining cells with random tiles (mix of types)
    const fillTypes  = [1, 1, 1, 2, 2, 2, 3, 4];
    const fillRots   = [0, 90, 180, 270];
    for (let r = 0; r < 10; r++) {
        for (let c = 0; c < 10; c++) {
            if (g[r][c].type !== 0) continue;
            g[r][c] = {
                type:      fillTypes[randInt(0, fillTypes.length - 1)],
                rotation:  fillRots[randInt(0, 3)],
                flowType:  null,
                flowStatus: 'none',
            };
        }
    }

    // Scramble path tile rotations
    for (let r = 0; r < 10; r++) {
        for (let c = 0; c < 10; c++) {
            if (g[r][c]._solRot !== undefined && Math.random() < config.scramble) {
                const otherRots = [0, 90, 180, 270].filter(x => x !== g[r][c]._solRot);
                g[r][c].rotation = otherRots[randInt(0, 2)];
            }
        }
    }

    return g;
}

// ── Flow propagation ──────────────────────────────────────────────────────────

function propagateFlows() {
    // Clear flow state
    for (let r = 0; r < 10; r++) {
        for (let c = 0; c < 10; c++) {
            grid.value[r][c].flowType   = null;
            grid.value[r][c].flowStatus = 'none';
        }
    }

    const results  = {};
    const newSinkLive = {};

    for (const src of activeSources.value) {
        // Check entry: col 0, source row, must have W connection
        const entryConn = getConn(grid.value[src.row][0].type, grid.value[src.row][0].rotation);
        if (!(entryConn & W)) {
            results[src.id] = 'UNROUTED';
            continue;
        }

        // BFS from source entry
        const visited  = new Set();
        const queue    = [[src.row, 0]];
        let   reached  = null; // { sinkDef }

        while (queue.length) {
            const [r, c] = queue.shift();
            const key = `${r},${c}`;
            if (visited.has(key)) continue;
            visited.add(key);

            const conn = getConn(grid.value[r][c].type, grid.value[r][c].rotation);

            for (const { bit, dr, dc } of DIRS) {
                if (!(conn & bit)) continue;
                const nr = r + dr, nc = c + dc;

                // Check if exiting the grid
                if (nr < 0 || nr >= 10 || nc < 0 || nc >= 10) {
                    // Is there a sink here?
                    if (bit === N && r === 0) {
                        const sink = config.sinks.find(s => s.edge === 'top' && s.col - 1 === c);
                        if (sink) reached = sink;
                    }
                    if (bit === E && c === 9) {
                        const sink = config.sinks.find(s => s.edge === 'right' && s.row - 1 === r);
                        if (sink) reached = sink;
                    }
                    continue;
                }

                const nConn = getConn(grid.value[nr][nc].type, grid.value[nr][nc].rotation);
                if (!(nConn & oppDir(bit))) continue; // neighbour doesn't connect back
                if (!visited.has(`${nr},${nc}`)) queue.push([nr, nc]);
            }
        }

        const status = reached
            ? (reached.accepts === src.type ? 'correct' : 'wrong')
            : 'unrouted';

        results[src.id] = reached
            ? (reached.accepts === src.type ? 'ROUTED' : 'WRONG SINK')
            : 'UNROUTED';

        if (reached && reached.accepts === src.type) {
            const sk = reached.edge === 'right' ? `right-${reached.row - 1}` : `top-${reached.col - 1}`;
            newSinkLive[sk] = true;
        }

        // Paint tiles
        for (const key of visited) {
            const [r, c] = key.split(',').map(Number);
            grid.value[r][c].flowType   = src.type;
            grid.value[r][c].flowStatus = status;
        }
    }

    flowResults.value = results;
    sinkLive.value    = newSinkLive;
    return results;
}

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

// ── Interaction ───────────────────────────────────────────────────────────────

function rotateTile(r, c) {
    if (result.value) return;
    const tile = grid.value[r][c];
    tile.rotation = (tile.rotation + 90) % 360;
    propagateFlows();
}

// ── Game loop ─────────────────────────────────────────────────────────────────

let animFrame = null;
let lastTs    = null;

const DMG_UNROUTED = 0.003;  // stability/s per unrouted source (gentle — base drain handles pressure)
const DMG_WRONG    = 0.0015; // stability/s per wrong-sink source

function tick(ts) {
    if (result.value) return;

    const dt  = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0;
    lastTs = ts;

    const cause = tickShared(dt);
    if (cause === 'trace') {
        endGame('success');
        setTimeout(() => emit('complete'), 2200);
        return;
    }
    if (cause === 'stability') {
        endGame('fail', '[CONDUIT COLLAPSE] — Frequency line saturated. Ejected from node.');
        setTimeout(() => emit('fail'), 2200);
        return;
    }

    // Apply damage from bad routing
    let dmg = 0;
    for (const src of activeSources.value) {
        const r = flowResults.value[src.id];
        if (r === 'UNROUTED')    dmg += DMG_UNROUTED;
        else if (r === 'WRONG SINK') dmg += DMG_WRONG;

        // Type-change timer
        src.changeTimer -= dt;
        if (src.changeTimer <= 0) {
            const newType = ALL_TYPES.filter(t => t !== src.type)[randInt(0, 1)];
            failLog.value.unshift(`${src.id}: ${src.type}→${newType}`);
            if (failLog.value.length > 4) failLog.value.pop();
            src.type = newType;
            src.changeInterval = randInt(...config.changeRange);
            src.changeTimer    = src.changeInterval;
            propagateFlows();
        }
    }

    if (dmg > 0) applyHit(dmg * dt);

    animFrame = requestAnimationFrame(tick);
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    // Build sources
    activeSources.value = config.sources.map((s, i) => ({
        id:             `SRC_${i}`,
        row:            s.row,
        type:           s.type,
        changeInterval: randInt(...config.changeRange),
        changeTimer:    randInt(...config.changeRange),
    }));

    // Generate grid and do initial flow propagation
    grid.value = generateGrid();
    propagateFlows();

    animFrame = requestAnimationFrame(tick);
});

onUnmounted(() => {
    if (animFrame) cancelAnimationFrame(animFrame);
});
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */

.cf-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    background: #030d08;
    overflow: hidden;
    box-sizing: border-box;
    padding: 6px 8px 4px;
    gap: 4px;
}

/* ── HUD ──────────────────────────────────────────────────────────────────── */

.cf-hud {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
    padding-bottom: 4px;
    border-bottom: 1px solid rgba(0,255,100,0.08);
}

.cf-metric {
    display: flex;
    align-items: center;
    gap: 5px;
    flex: 1;
}

.cf-ml {
    font-size: 7px;
    letter-spacing: 0.1em;
    color: rgba(0,255,100,0.3);
    white-space: nowrap;
    flex-shrink: 0;
}

.cf-mbar {
    flex: 1;
    height: 4px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
}

.cf-mfill { height: 100%; transition: width 0.2s; }
.cf-stab  { background: linear-gradient(90deg, #003322, #00ff9d); box-shadow: 0 0 4px rgba(0,255,100,0.4); }
.cf-pres  { background: linear-gradient(90deg, #332200, #ffaa00); box-shadow: 0 0 4px rgba(255,170,0,0.4); }
.cf-freq  { background: linear-gradient(90deg, #330000, #ff3333); box-shadow: 0 0 4px rgba(255,50,50,0.4); }

.cf-mv        { font-size: 8px; color: rgba(0,255,100,0.6); flex-shrink: 0; }
.cf-mv--warn  { color: #ffaa00; }
.cf-mv--danger{ color: #ff3333; }

/* ── Arena ───────────────────────────────────────────────────────────────── */

.cf-arena {
    display: flex;
    gap: 4px;
    flex: 1;
    min-height: 0;
    align-items: flex-start;
}

/* ── Sources column ──────────────────────────────────────────────────────── */

.cf-sources-col {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 112px;
}

.cf-src-spacer {
    flex-shrink: 0;
    /* matches col-nums + top-sinks height */
    height: 36px;
}

.cf-src-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.cf-src-card {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 6px;
    border: 1px solid rgba(0,255,100,0.1);
    background: rgba(0,0,0,0.3);
    overflow: hidden;
    transition: border-color 0.2s;
    cursor: default;
}

.cf-src-card.cf-src--ok   { border-color: rgba(0,255,100,0.35); }
.cf-src-card.cf-src--warn { border-color: rgba(255,170,0,0.35); }
.cf-src-card.cf-src--bad  { border-color: rgba(255,50,50,0.35); animation: cf-pulse 0.8s ease infinite alternate; }

.cf-src-icon {
    font-size: 16px;
    flex-shrink: 0;
    text-align: center;
    width: 22px;
    filter: drop-shadow(0 0 4px currentColor);
}

.cf-src-info { flex: 1; min-width: 0; }

.cf-src-name {
    font-size: 7px;
    letter-spacing: 0.1em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 700;
}

.cf-src-st {
    font-size: 6px;
    opacity: 0.6;
    letter-spacing: 0.08em;
    margin-top: 1px;
}

.cf-src-timer {
    height: 2px;
    background: rgba(255,255,255,0.1);
    margin-top: 3px;
    overflow: hidden;
}

.cf-src-tf {
    height: 100%;
    background: currentColor;
    opacity: 0.5;
    transition: width 0.5s linear;
}

/* ── Type colors ─────────────────────────────────────────────────────────── */

.cf-t--CACHE_FLOOD  { color: #00d4ff; }
.cf-t--STACK_LEAK   { color: #00ff9d; }
.cf-t--SIGNAL_NOISE { color: #ffaa00; }

/* ── Grid column ──────────────────────────────────────────────────────────── */

.cf-grid-col {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

/* Top sinks */
.cf-top-sinks {
    display: flex;
    align-items: flex-end;
    height: 26px;
    flex-shrink: 0;
}

.cf-row-label-spacer {
    width: 16px;
    flex-shrink: 0;
}

.cf-top-sink-cell {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    flex-shrink: 0;
}

.cf-sink-badge {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 2px 4px;
    border: 1px solid currentColor;
    background: rgba(0,0,0,0.7);
    font-size: 6px;
    letter-spacing: 0.05em;
    white-space: nowrap;
    opacity: 0.85;
}

.cf-sink-top {
    flex-direction: column;
    gap: 1px;
    text-align: center;
}

.cf-sink-icon { font-size: 10px; }
.cf-sink-lbl  { font-size: 5.5px; opacity: 0.8; }

/* Column numbers */
.cf-col-nums {
    display: flex;
    align-items: center;
    height: 14px;
    flex-shrink: 0;
}

.cf-col-n {
    display: block;
    text-align: center;
    font-size: 7px;
    color: rgba(0,255,100,0.2);
    flex-shrink: 0;
}

/* Grid */
.cf-grid {
    display: flex;
    flex-direction: column;
}

.cf-grid-row {
    display: flex;
    align-items: center;
}

.cf-row-label {
    width: 16px;
    font-size: 7px;
    color: rgba(0,255,100,0.2);
    text-align: center;
    flex-shrink: 0;
}

.cf-tile {
    border: 1px solid rgba(0,255,100,0.06);
    background: rgba(0,20,10,0.5);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    flex-shrink: 0;
    transition: background 0.15s, border-color 0.15s;
    position: relative;
}

.cf-tile:hover { background: rgba(0,40,20,0.6); border-color: rgba(0,255,100,0.15); }
.cf-tile-active { border-color: rgba(0,255,100,0.08); }

.cf-tile-svg {
    width: 100%;
    height: 100%;
    display: block;
}

/* Pipe styles */
.cf-pipe { transition: stroke 0.2s; }
.cf-pipe--dim     { stroke: rgba(0,255,100,0.12); }
.cf-pipe--flow    { stroke: currentColor; opacity: 0.9; }
.cf-pipe--correct { stroke: currentColor; filter: drop-shadow(0 0 3px currentColor); }
.cf-pipe--wrong   { stroke: #ff3333; filter: drop-shadow(0 0 2px rgba(255,50,50,0.7)); animation: cf-flicker 1.2s ease infinite; }

/* ── Right column ────────────────────────────────────────────────────────── */

.cf-right-col {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 100px;
    gap: 4px;
}

.cf-right-top {
    height: 36px; /* aligns with col headers */
    flex-shrink: 0;
}

.cf-right-sinks {
    display: flex;
    flex-direction: column;
}

.cf-right-sink-cell {
    display: flex;
    align-items: center;
}

.cf-sink-right {
    flex-direction: column;
    align-items: flex-start;
    gap: 1px;
    width: 100%;
}

.cf-sink-detail { display: flex; flex-direction: column; gap: 1px; }
.cf-sink-st     { font-size: 5px; opacity: 0.5; letter-spacing: 0.08em; }
.cf-sink--live  { opacity: 1; box-shadow: inset 0 0 6px rgba(0,255,100,0.15); }

/* Failure log */
.cf-fail-log {
    margin-top: 4px;
    border: 1px solid rgba(255,50,50,0.15);
    padding: 4px 5px;
    background: rgba(80,0,0,0.15);
}

.cf-fail-title {
    font-size: 7px;
    color: rgba(255,50,50,0.6);
    letter-spacing: 0.18em;
    margin-bottom: 3px;
}

.cf-fail-entry {
    font-size: 6px;
    color: rgba(255,100,100,0.5);
    letter-spacing: 0.05em;
    margin-bottom: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Status bar ──────────────────────────────────────────────────────────── */

.cf-statusbar {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
    padding-top: 4px;
    border-top: 1px solid rgba(0,255,100,0.06);
}

.cf-sb-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1px;
    border: 1px solid currentColor;
    padding: 3px 5px;
    opacity: 0.7;
    background: rgba(0,0,0,0.3);
}

.cf-sb-name { font-size: 7px; font-weight: 700; letter-spacing: 0.08em; }
.cf-sb-st   { font-size: 6px; opacity: 0.6; }

/* ── Animations ──────────────────────────────────────────────────────────── */

@keyframes cf-pulse {
    from { border-color: rgba(255,50,50,0.2); box-shadow: none; }
    to   { border-color: rgba(255,50,50,0.7); box-shadow: 0 0 6px rgba(255,50,50,0.2); }
}

@keyframes cf-flicker {
    0%, 100% { opacity: 0.9; }
    50%       { opacity: 0.4; }
}
</style>
