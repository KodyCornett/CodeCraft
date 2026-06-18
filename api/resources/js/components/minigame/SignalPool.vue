<template>
    <div class="sp-pool">

        <!-- ── Pool header bar ───────────────────────────────────────────── -->
        <div class="sp-header">

            <div class="sp-header-left">
                <span class="sp-header-bracket">[</span>
                SHARED_SIGNAL_POOL
                <span class="sp-header-bracket">]</span>
            </div>

            <!-- Legend -->
            <div class="sp-legend">
                <span class="sp-legend-item">
                    <span class="sp-legend-swatch sp-legend-swatch--signal"/>
                    SIGNAL
                </span>
                <span class="sp-legend-item">
                    <span class="sp-legend-swatch sp-legend-swatch--noise"/>
                    NOISE
                </span>
                <span class="sp-legend-item">
                    <span class="sp-legend-swatch sp-legend-swatch--used"/>
                    PLACED
                </span>
            </div>

            <!-- Selected state indicator -->
            <Transition name="sp-fade">
                <div v-if="props.selectedPoolId !== null" class="sp-selection-bar">
                    <span class="sp-sel-icon">▸</span>
                    LETTER SELECTED — CLICK A SLOT TO PLACE
                    <button class="sp-cancel" @click="$emit('pool-cancel')">[ CANCEL ]</button>
                </div>
            </Transition>

            <!-- Tile count -->
            <div class="sp-counts">
                <span class="sp-count-val">{{ activeCount }}</span>
                <span class="sp-count-label">/ {{ props.letters.length }} REMAINING</span>
            </div>

        </div>

        <!-- ── Tile grid ──────────────────────────────────────────────────── -->
        <div class="sp-grid">
            <button
                v-for="item in props.letters"
                :key="item.id"
                class="sp-tile"
                :class="tileClass(item)"
                :disabled="item.status === 'used'"
                :aria-label="`Letter ${item.char}${item.noise ? ' (noise)' : ''}`"
                :aria-pressed="item.id === props.selectedPoolId"
                @click="onTileClick(item)"
            >
                {{ item.char }}
            </button>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * Array of letter tile objects.
     * Shape: { id: number, char: string, status: 'active'|'used', noise: boolean }
     *
     * - id      — unique identifier, emitted on selection
     * - char    — the character displayed on the tile
     * - status  — 'active' (available) | 'used' (placed in a slot, dimmed)
     * - noise   — true renders the tile in amber (noise palette) vs cyan (signal)
     */
    letters: { type: Array, default: () => [] },

    /**
     * ID of the currently selected tile, or null.
     * Drives the selected glow state.
     */
    selectedPoolId: { type: [Number, null], default: null },

    /**
     * When true, noise tiles enter the reveal state (amber pulse + jitter).
     * Signal tiles stay visually neutral so the player deduces by exclusion.
     */
    pingActive: { type: Boolean, default: false },
});

// ── Emits ──────────────────────────────────────────────────────────────────────

const emit = defineEmits([
    /**
     * User clicked an active tile.
     * Payload: tile id (number)
     */
    'pool-select',

    /**
     * User clicked the CANCEL button while a tile is selected.
     */
    'pool-cancel',
]);

// ── Derived ────────────────────────────────────────────────────────────────────

const activeCount = computed(() =>
    props.letters.filter(t => t.status === 'active').length
);

// ── Tile class logic ───────────────────────────────────────────────────────────

function tileClass(item) {
    const revealing = props.pingActive && item.noise && item.status !== 'used';
    return {
        // All tiles are visually neutral by default — no signal/noise colour hint.
        // sp-tile--signal / sp-tile--noise only differ in the reveal state below.
        'sp-tile--noise':      item.noise,
        'sp-tile--revealing':  revealing,
        'sp-tile--used':       item.status === 'used',
        'sp-tile--selected':   item.id === props.selectedPoolId,
    };
}

// ── Interaction ────────────────────────────────────────────────────────────────

function onTileClick(item) {
    // used tiles are disabled via :disabled — this guard is a safety net
    if (item.status === 'used') return;
    emit('pool-select', item.id);
}
</script>

<style scoped>
/* ── Pool shell — fills cell D completely ─────────────────────────────────── */

.sp-pool {
    width: 100%;
    /* flex: 1 + min-height: 0 so it shares its parent cell with the control strip */
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Header bar ───────────────────────────────────────────────────────────── */

.sp-header {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,200,240,0.08);
}

.sp-header-left {
    font-size: 10px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.4);
    white-space: nowrap;
    flex-shrink: 0;
}

.sp-header-bracket { color: rgba(0,200,240,0.2); }

/* ── Legend ───────────────────────────────────────────────────────────────── */

.sp-legend {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
}

.sp-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(0,200,240,0.3);
}

.sp-legend-swatch {
    width: 8px;
    height: 8px;
    border: 1px solid;
    flex-shrink: 0;
}

.sp-legend-swatch--signal { border-color: rgba(0,200,240,0.5); background: rgba(0,200,240,0.15); }
.sp-legend-swatch--noise  { border-color: rgba(255,136,0,0.5); background: rgba(255,136,0,0.15); }
.sp-legend-swatch--used   { border-color: rgba(0,200,240,0.15); background: transparent; }

/* ── Selection state bar ──────────────────────────────────────────────────── */

.sp-selection-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    letter-spacing: 0.1em;
    color: #00ff9d;
    animation: sp-blink 0.9s ease infinite alternate;
}

.sp-sel-icon { font-size: 12px; }

.sp-cancel {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 3px 8px;
    border: 1px solid rgba(255,60,60,0.3);
    background: transparent;
    color: rgba(255,80,80,0.6);
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    animation: none; /* cancel button shouldn't blink */
}

.sp-cancel:hover {
    color: #ff4444;
    border-color: rgba(255,60,60,0.6);
}

/* ── Counts ───────────────────────────────────────────────────────────────── */

.sp-counts {
    margin-left: auto;
    display: flex;
    align-items: baseline;
    gap: 5px;
    flex-shrink: 0;
}

.sp-count-val   { font-size: 18px; font-weight: 700; color: rgba(0,200,240,0.6); }
.sp-count-label { font-size: 9px;  letter-spacing: 0.12em; color: rgba(0,200,240,0.25); }

/* ── Tile grid ────────────────────────────────────────────────────────────── */

.sp-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-content: flex-start;
    overflow: hidden;
    flex: 1;
    min-height: 0;
}

/* ── Tile base ────────────────────────────────────────────────────────────── */

.sp-tile {
    width: 40px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    font-weight: 700;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition:
        border-color  0.1s,
        background    0.1s,
        color         0.1s,
        box-shadow    0.1s,
        opacity       0.15s;
    flex-shrink: 0;
    padding: 0;
    letter-spacing: 0;
    user-select: none;
}

.sp-tile:focus-visible {
    outline: 1px solid rgba(0,200,240,0.5);
    outline-offset: 2px;
}

/* ── All tiles — neutral / blind state (no signal vs noise distinction) ───── */
/*    Players cannot tell signal from noise until ping reveals them.           */

.sp-tile {
    /* Neutral cyan — same for both signal and noise until ping fires */
    color: rgba(0,200,240,0.55);
    border-color: rgba(0,200,240,0.14);
    background: rgba(0,12,20,0.8);
}

.sp-tile:hover:not(:disabled):not(.sp-tile--revealing) {
    color: rgba(0,200,240,0.85);
    border-color: rgba(0,200,240,0.4);
    background: rgba(0,200,240,0.06);
}

/* ── Noise tile — NO visual change by default; only styled during reveal ──── */
/* (.sp-tile--noise alone does nothing; paired with --revealing it activates) */

/* ── Reveal state — ping active, noise tiles exposed ─────────────────────── */

.sp-tile--revealing {
    color: #ff6600;
    border-color: rgba(255,100,0,0.65);
    background: rgba(40,8,0,0.92);
    animation:
        sp-jitter   0.12s linear infinite,
        sp-pulse-noise 0.55s ease-in-out infinite alternate;
}

.sp-tile--revealing:hover:not(:disabled) {
    border-color: rgba(255,120,0,0.85);
    color: #ff8800;
}

/* ── Used tile — dimmed, non-interactive ──────────────────────────────────── */

.sp-tile--used {
    opacity: 0.2;
    cursor: default;
    pointer-events: none;
}

/* ── Selected tile — bright glow ──────────────────────────────────────────── */

.sp-tile--selected {
    color: #ffffff;
    border-color: #00c8f0;
    background: rgba(0,200,240,0.14);
    box-shadow:
        0 0 0 1px rgba(0,200,240,0.4),
        0 0 18px rgba(0,200,240,0.35);
}

/* Selected noise tile: during ping reveal, override to white-on-amber so
   selection is still readable over the jitter animation */
.sp-tile--selected.sp-tile--revealing {
    color: #ffffff;
    border-color: rgba(255,140,0,0.9);
    background: rgba(50,14,0,0.95);
    box-shadow:
        0 0 0 1px rgba(255,140,0,0.5),
        0 0 22px rgba(255,100,0,0.5);
    animation: none; /* suspend jitter while selected */
}

/* ── Transitions ──────────────────────────────────────────────────────────── */

.sp-fade-enter-active { transition: opacity 0.2s, transform 0.2s; }
.sp-fade-leave-active { transition: opacity 0.15s, transform 0.15s; }
.sp-fade-enter-from,
.sp-fade-leave-to     { opacity: 0; transform: translateY(-4px); }

/* ── Keyframes ────────────────────────────────────────────────────────────── */

@keyframes sp-blink {
    from { opacity: 1;   }
    to   { opacity: 0.4; }
}

/* Noise reveal — micro-jitter so tiles feel "hot" */
@keyframes sp-jitter {
    0%   { transform: translate(0,    0);    }
    20%  { transform: translate(-1px, 1px);  }
    40%  { transform: translate(1px,  -1px); }
    60%  { transform: translate(-1px, -1px); }
    80%  { transform: translate(1px,  1px);  }
    100% { transform: translate(0,    0);    }
}

/* Noise reveal — amber glow pulse */
@keyframes sp-pulse-noise {
    from {
        box-shadow: 0 0 4px rgba(255,80,0,0.2),  inset 0 0 4px rgba(255,80,0,0.05);
    }
    to {
        box-shadow: 0 0 18px rgba(255,80,0,0.65), inset 0 0 8px rgba(255,80,0,0.12);
    }
}
</style>
