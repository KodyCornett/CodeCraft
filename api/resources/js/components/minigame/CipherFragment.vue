<template>
    <div
        class="cf-card"
        :class="{
            'cf-card--solved': props.solved,
            'cf-card--scan-active': props.scanActive,
        }"
    >

        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div class="cf-header">
            <div class="cf-header-left">
                <span class="cf-frag-num">FRAGMENT_{{ padded }}</span>
                <span class="cf-sep">//</span>
                <span class="cf-codename">{{ props.codename }}</span>
            </div>
            <div class="cf-badge" :class="props.solved ? 'cf-badge--secured' : 'cf-badge--pending'">
                <span class="cf-badge-dot">{{ props.solved ? '◆' : '○' }}</span>
                {{ props.solved ? 'SECURED' : 'PENDING' }}
            </div>
        </div>

        <!-- ── Hint ───────────────────────────────────────────────────────── -->
        <div class="cf-hint-wrap">
            <span class="cf-hint-tag">INTEL //</span>
            <span class="cf-hint">"{{ props.hint }}"</span>
        </div>

        <!-- ── Controls row: slots + buttons ─────────────────────────────── -->
        <div class="cf-controls">

            <!-- Slot row — v-for on wordLength prop -->
            <div class="cf-slots">
                <button
                    v-for="si in props.wordLength"
                    :key="si - 1"
                    class="cf-slot"
                    :class="{
                        'cf-slot--filled':    slotData[si - 1] !== null,
                        'cf-slot--droppable': props.selectedPoolId !== null && !props.solved,
                        'cf-slot--solved':    props.solved,
                    }"
                    :disabled="props.solved"
                    :aria-label="`Fragment ${props.fragmentIndex + 1} slot ${si}`"
                    @click="$emit('slot-click', si - 1)"
                >
                    <span v-if="slotData[si - 1]" class="cf-slot-letter">
                        {{ slotData[si - 1].letter }}
                    </span>
                    <span v-else class="cf-slot-empty">?</span>
                </button>
            </div>

            <!-- Action buttons -->
            <div class="cf-actions">
                <button
                    class="cf-btn cf-btn--scan"
                    :class="{ 'cf-btn--scan-glow': props.scanActive }"
                    :aria-pressed="props.scanActive"
                    @click="$emit('scan-click')"
                >
                    <span class="cf-btn-bracket">[</span>
                    SCAN_{{ padded }}
                    <span class="cf-btn-bracket">]</span>
                </button>

                <button
                    class="cf-btn cf-btn--inject"
                    :class="{ 'cf-btn--inject-ready': canInject && !props.solved }"
                    :disabled="props.solved || !canInject"
                    @click="$emit('inject-click')"
                >
                    <span class="cf-btn-bracket">[</span>
                    INJECT_{{ padded }}
                    <span class="cf-btn-bracket">]</span>
                </button>
            </div>

        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /** 0-based index of this fragment */
    fragmentIndex: { type: Number, required: true },
    /** Codename string, e.g. "GHOST_PROTOCOL" */
    codename:      { type: String, required: true },
    /** Hint text shown to the player */
    hint:          { type: String, required: true },
    /**
     * Number of letter slots to render.
     * Drives the v-for — set independently of slotData so
     * the layout is correct even before puzzle data populates.
     */
    wordLength:    { type: Number, required: true },
    /**
     * Array of slot entries, length === wordLength.
     * Each entry is null (empty) or { poolId, letter }.
     */
    slotData:      { type: Array, default: () => [] },
    /** Whether this fragment has been solved */
    solved:        { type: Boolean, default: false },
    /** Whether this fragment's archive scan is currently open */
    scanActive:    { type: Boolean, default: false },
    /**
     * ID of the currently selected pool tile (or null).
     * Used to activate the droppable state on slots.
     */
    selectedPoolId: { type: [Number, null], default: null },
});

// ── Emits ──────────────────────────────────────────────────────────────────────

defineEmits([
    /** User clicked slot si. Parent decides what to place. */
    'slot-click',
    /** User clicked the SCAN button */
    'scan-click',
    /** User clicked the INJECT button */
    'inject-click',
]);

// ── Derived ────────────────────────────────────────────────────────────────────

/** Zero-padded fragment number string, e.g. "01" */
const padded = computed(() => String(props.fragmentIndex + 1).padStart(2, '0'));

/** All slots are filled — INJECT becomes live */
const canInject = computed(() =>
    props.slotData.length === props.wordLength &&
    props.slotData.every(s => s !== null)
);
</script>

<style scoped>
/* ── Card shell ───────────────────────────────────────────────────────────── */

.cf-card {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 28px 32px;
    border: 1px solid rgba(34,211,238,0.22);
    background: rgba(0,10,18,0.75);
    box-shadow: inset 0 0 60px rgba(34,211,238,0.03);
    transition: border-color 0.25s, background 0.25s, box-shadow 0.25s;
    font-family: 'JetBrains Mono', monospace;
    color: #22d3ee;
}

.cf-card--solved {
    border-color: rgba(0,255,100,0.35);
    background: rgba(0,18,12,0.6);
    box-shadow: inset 0 0 40px rgba(0,255,100,0.04);
}

/* Ambient glow on the card when its scan panel is open */
.cf-card--scan-active {
    border-color: rgba(34,211,238,0.5);
    box-shadow:
        inset 0 0 60px rgba(34,211,238,0.06),
        0 0 20px rgba(34,211,238,0.08);
}

/* ── Header ───────────────────────────────────────────────────────────────── */

.cf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 12px;
    margin: -8px -12px 4px;
    background: rgba(24,24,27,0.55);
    border-bottom: 1px solid rgba(251,146,60,0.4);
    box-shadow: 0 2px 14px rgba(251,146,60,0.08);
}

.cf-header-left {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.cf-frag-num {
    font-size: 11px;
    letter-spacing: 0.2em;
    color: rgba(251,146,60,0.85);
    text-shadow: 0 0 8px rgba(251,146,60,0.5);
}

.cf-sep {
    font-size: 11px;
    color: rgba(251,146,60,0.2);
}

.cf-codename {
    font-size: 11px;
    letter-spacing: 0.14em;
    color: rgba(34,211,238,0.6);
    text-shadow: 0 0 6px rgba(34,211,238,0.3);
}

/* ── Badge ────────────────────────────────────────────────────────────────── */

.cf-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 9px;
    letter-spacing: 0.16em;
    flex-shrink: 0;
    padding: 3px 8px;
    border: 1px solid;
}

.cf-badge--pending {
    color: rgba(0,200,240,0.3);
    border-color: rgba(0,200,240,0.1);
}

.cf-badge--secured {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.3);
    text-shadow: 0 0 8px rgba(0,255,100,0.5);
}

.cf-badge-dot { font-size: 8px; }

/* ── Hint ─────────────────────────────────────────────────────────────────── */

.cf-hint-wrap {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 14px 16px;
    background: rgba(0,6,12,0.7);
    border-left: 2px solid rgba(34,211,238,0.5);
    box-shadow: inset 4px 0 12px rgba(34,211,238,0.04);
}

.cf-hint-tag {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(251,146,60,0.5);
    flex-shrink: 0;
}

.cf-hint {
    font-size: 14px;
    font-style: italic;
    color: #22d3ee;
    letter-spacing: 0.03em;
    line-height: 1.5;
    text-shadow: 0 0 12px rgba(34,211,238,0.4);
}

/* ── Controls row ─────────────────────────────────────────────────────────── */

.cf-controls {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

/* ── Slots ────────────────────────────────────────────────────────────────── */

.cf-slots {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.cf-slot {
    width: 56px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(34,211,238,0.25);
    background: rgba(0,8,16,0.9);
    cursor: pointer;
    font-family: 'JetBrains Mono', monospace;
    transition: border-color 0.12s, background 0.12s, box-shadow 0.12s;
    padding: 0;
}

.cf-slot:focus-visible {
    outline: 1px solid #22d3ee;
    outline-offset: 2px;
}

.cf-slot-empty {
    font-size: 18px;
    color: rgba(34,211,238,0.2);
    letter-spacing: 0;
}

.cf-slot-letter {
    font-size: 24px;
    font-weight: 700;
    color: #22d3ee;
    letter-spacing: 0;
    text-shadow: 0 0 10px rgba(34,211,238,0.7);
}

/* Filled slot */
.cf-slot--filled {
    border-color: rgba(34,211,238,0.65);
    background: rgba(0,22,40,0.9);
    box-shadow:
        0 0 8px rgba(34,211,238,0.15),
        inset 0 0 8px rgba(34,211,238,0.06);
}

/* Droppable — a pool tile is selected, show hover affordance */
.cf-slot--droppable:hover {
    border-color: #22d3ee;
    background: rgba(34,211,238,0.08);
    box-shadow: 0 0 18px rgba(34,211,238,0.3);
    cursor: crosshair;
}

/* Solved — dim and locked */
.cf-slot--solved {
    border-color: rgba(0,255,100,0.3);
    background: rgba(0,18,12,0.7);
    cursor: default;
}

.cf-slot--solved .cf-slot-letter {
    color: #00ff9d;
    text-shadow: 0 0 10px rgba(0,255,100,0.6);
}

/* ── Action buttons ───────────────────────────────────────────────────────── */

.cf-actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.cf-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.12em;
    padding: 10px 18px;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, color 0.15s, border-color 0.15s;
    white-space: nowrap;
}

.cf-btn:disabled {
    cursor: not-allowed;
    opacity: 0.2;
}

.cf-btn-bracket {
    color: rgba(0,200,240,0.25);
    font-size: 10px;
}

/* SCAN — default */
.cf-btn--scan {
    color: rgba(34,211,238,0.55);
    border-color: rgba(34,211,238,0.22);
}

.cf-btn--scan:hover {
    color: #22d3ee;
    border-color: rgba(34,211,238,0.6);
    background: rgba(34,211,238,0.06);
    box-shadow: 0 0 10px rgba(34,211,238,0.15);
}

/* SCAN — active glow when this fragment's archive is open */
.cf-btn--scan-glow {
    color: #22d3ee;
    border-color: rgba(34,211,238,0.8);
    background: rgba(34,211,238,0.08);
    text-shadow: 0 0 8px rgba(34,211,238,0.6);
    box-shadow:
        0 0 16px rgba(34,211,238,0.3),
        inset 0 0 10px rgba(34,211,238,0.07);
}

/* INJECT — locked state */
.cf-btn--inject {
    color: rgba(0,255,100,0.25);
    border-color: rgba(0,255,100,0.1);
}

/* INJECT — ready (all slots filled) */
.cf-btn--inject-ready {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.55);
    text-shadow: 0 0 8px rgba(0,255,100,0.5);
}

.cf-btn--inject-ready:hover {
    background: rgba(0,255,100,0.08);
    box-shadow:
        0 0 18px rgba(0,255,100,0.3),
        inset 0 0 8px rgba(0,255,100,0.06);
}
</style>
