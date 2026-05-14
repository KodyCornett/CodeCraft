<template>
    <div class="board-wrapper">
        <div class="board-header">
            <span class="board-title">{{ isOpponent ? '[ENEMY_GRID]' : '[YOUR_GRID]' }}</span>
            <span v-if="scrambled && isOpponent" class="scramble-warn">// SCRAMBLE ACTIVE</span>
        </div>

        <div
            class="board-grid"
            :style="{ gridTemplateColumns: `20px repeat(${size}, 1fr)` }"
        >
            <!-- Column headers -->
            <div class="board-coord board-coord--corner" />
            <div v-for="col in size" :key="'ch-' + col" class="board-coord board-coord--col">
                {{ col - 1 }}
            </div>

            <template v-for="row in size" :key="'row-' + row">
                <!-- Row header -->
                <div class="board-coord board-coord--row">{{ row - 1 }}</div>

                <!-- Cells -->
                <div
                    v-for="col in size"
                    :key="`cell-${row - 1}-${col - 1}`"
                    class="board-cell"
                    :class="cellClass(row - 1, col - 1)"
                    :title="cellTitle(row - 1, col - 1)"
                    @click="handleCellClick(row - 1, col - 1)"
                    @mouseenter="hoveredRow = row - 1; hoveredCol = col - 1"
                    @mouseleave="hoveredRow = null; hoveredCol = null"
                >
                    <span class="cell-inner">
                        <!-- Permanent markers (hidden when scrambled on opponent view) -->
                        <template v-if="!shouldHide(row - 1, col - 1)">
                            <span v-if="cellState(row-1,col-1) === 'hit'"       class="marker marker--hit">✕</span>
                            <span v-if="cellState(row-1,col-1) === 'miss'"      class="marker marker--miss">·</span>
                            <span v-if="cellState(row-1,col-1) === 'false_hit'" class="marker marker--false-hit">✕</span>
                        </template>

                        <!-- DDOS FOG flash — transient, no permanent marker -->
                        <span v-if="fogged.has(`${row-1},${col-1}`)" class="fog-flash">BLOCKED</span>
                    </span>
                </div>
            </template>
        </div>

        <!-- Placement preview labels -->
        <div v-if="phase === 'placement' && !isOpponent" class="placement-hint">
            CLICK TO PLACE — [H/V] TO ROTATE
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

const props = defineProps({
    grid:        { type: Array,   default: () => [] },   // 2D array of cell state strings
    isOpponent:  { type: Boolean, default: false },
    size:        { type: Number,  default: 8 },
    phase:       { type: String,  default: 'active' },   // placement | active | resolved
    scrambled:   { type: Boolean, default: false },
    // Placement props (own grid only)
    placingShip: { type: Object,  default: null },        // { size, orientation }
});

const emit = defineEmits(['square-clicked', 'placement-preview']);

const hoveredRow = ref(null);
const hoveredCol = ref(null);
// Set of "row,col" strings currently showing DDOS FOG flash
const fogged = reactive(new Set());

function cellState(row, col) {
    return props.grid?.[row]?.[col] ?? 'empty';
}

function shouldHide(row, col) {
    // Scramble hides shot history on opponent grid
    if (!props.scrambled || !props.isOpponent) return false;
    const s = cellState(row, col);
    return s === 'hit' || s === 'miss' || s === 'false_hit';
}

function cellClass(row, col) {
    const state  = cellState(row, col);
    const classes = [`cell-${state}`];

    if (props.isOpponent && props.phase === 'active') {
        classes.push('cell-clickable');
    }

    // Placement hover preview
    if (!props.isOpponent && props.phase === 'placement' && props.placingShip) {
        if (isInPreview(row, col)) classes.push('cell-preview');
    }

    if (fogged.has(`${row},${col}`)) classes.push('cell-fogged');

    return classes;
}

function cellTitle(row, col) {
    if (fogged.has(`${row},${col}`)) return 'SIGNAL BLOCKED';
    return '';
}

function isInPreview(row, col) {
    if (!props.placingShip || hoveredRow.value === null) return false;
    const { size, orientation } = props.placingShip;
    for (let i = 0; i < size; i++) {
        const r = orientation === 'H' ? hoveredRow.value       : hoveredRow.value + i;
        const c = orientation === 'H' ? hoveredCol.value + i : hoveredCol.value;
        if (r === row && c === col) return true;
    }
    return false;
}

function handleCellClick(row, col) {
    if (props.phase !== 'active' && props.phase !== 'placement') return;

    const state = cellState(row, col);

    // DDOS FOG blocked cell: show flash, no permanent marker
    if (state === 'signal_blocked') {
        triggerFogFlash(row, col);
        return;
    }

    emit('square-clicked', { row, col });
}

function triggerFogFlash(row, col) {
    const key = `${row},${col}`;
    fogged.add(key);
    setTimeout(() => fogged.delete(key), 1500);
}

// Called externally by parent to trigger a fog flash on a cell
defineExpose({ triggerFogFlash });
</script>

<style scoped>
.board-wrapper {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.board-header {
    display: flex;
    align-items: center;
    gap: 10px;
}

.board-title {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #00FFFF;
    letter-spacing: 0.06em;
}

.board-title + .scramble-warn,
.scramble-warn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: #FF00CC;
    letter-spacing: 0.04em;
    animation: scramble-blink 1s steps(1) infinite;
}

@keyframes scramble-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

.board-grid {
    display: grid;
    gap: 2px;
}

.board-coord {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    min-height: 20px;
}

.board-coord--corner { /* empty top-left cell */ }

.board-cell {
    width: 100%;
    aspect-ratio: 1;
    min-width: 28px;
    min-height: 28px;
    background: rgba(5, 5, 5, 0.8);
    border: 1px solid rgba(0, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    transition: background 0.1s, border-color 0.1s;
}

/* States */
.cell-ship   { background: rgba(0, 255, 255, 0.12); border-color: rgba(0, 255, 255, 0.35); }
.cell-hit    { background: rgba(255, 51,  51,  0.18); border-color: rgba(255, 51,  51,  0.5); }
.cell-miss   { background: rgba(40,  40,  40,  0.5);  border-color: rgba(80,  80,  80,  0.4); }
.cell-false_hit { background: rgba(255, 51, 51, 0.18); border-color: rgba(255, 51, 51, 0.5); } /* looks like hit to shooter */
.cell-fogged { border-color: rgba(255, 179, 0, 0.4); }
.cell-preview { background: rgba(0, 255, 255, 0.08); border-color: rgba(0, 255, 255, 0.5); }

.cell-clickable {
    cursor: crosshair;
}
.cell-clickable:hover {
    background: rgba(0, 255, 255, 0.06);
    border-color: rgba(0, 255, 255, 0.35);
}

.cell-hit.cell-clickable:hover,
.cell-miss.cell-clickable:hover {
    cursor: not-allowed;
}

.cell-inner {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.marker {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    pointer-events: none;
}
.marker--hit       { color: #FF3333; }
.marker--miss      { color: rgba(100, 100, 100, 0.6); font-size: 16px; }
.marker--false-hit { color: #FF3333; opacity: 0.7; } /* subtle difference from real hit — the "tell" */

.fog-flash {
    font-family: 'JetBrains Mono', monospace;
    font-size: 6px;
    color: #FFB300;
    letter-spacing: 0.04em;
    white-space: nowrap;
    animation: fog-appear 1.5s ease forwards;
    pointer-events: none;
}

@keyframes fog-appear {
    0%   { opacity: 1;   transform: scale(1);    }
    70%  { opacity: 0.9; transform: scale(1.05); }
    100% { opacity: 0;   transform: scale(0.9);  }
}

.placement-hint {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.04em;
    text-align: center;
}
</style>
