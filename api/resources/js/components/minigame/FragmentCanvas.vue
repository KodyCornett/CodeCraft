<template>
    <div class="fc-canvas">

        <CipherFragment
            v-for="(frag, fi) in props.fragments"
            :key="fi"
            :fragment-index="fi"
            :codename="frag.codename"
            :hint="frag.hint"
            :word-length="frag.word.length"
            :slot-data="props.slots[fi] ?? []"
            :solved="props.solvedFrags[fi] ?? false"
            :scan-active="props.scanOpen === fi"
            :selected-pool-id="props.selectedPoolId"
            @slot-click="(si) => $emit('slot-click', fi, si)"
            @scan-click="$emit('scan-click', fi)"
            @inject-click="$emit('inject-click', fi)"
        />

        <!-- Empty-state placeholder — rendered before puzzle data arrives -->
        <template v-if="!props.fragments.length">
            <div v-for="n in 3" :key="n" class="fc-placeholder">
                <div class="fc-ph-header">
                    <span class="fc-ph-id">FRAGMENT_{{ String(n).padStart(2, '0') }}</span>
                    <span class="fc-ph-badge">○ AWAITING DATA</span>
                </div>
                <div class="fc-ph-body">
                    <span class="fc-ph-dot">·</span>
                    <span class="fc-ph-dot">·</span>
                    <span class="fc-ph-dot">·</span>
                </div>
            </div>
        </template>

    </div>
</template>

<script setup>
import CipherFragment from './CipherFragment.vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * Array of fragment objects from ToxicSoak game state.
     * Shape: [{ word: string, hint: string, codename: string, archive: [] }]
     */
    fragments: { type: Array, default: () => [] },

    /**
     * Slot data array from ToxicSoak.
     * slots[fi][si] = null | { poolId: number, letter: string }
     */
    slots: { type: Array, default: () => [] },

    /**
     * Solved state for each fragment.
     * solvedFrags[fi] = boolean
     */
    solvedFrags: { type: Array, default: () => [] },

    /**
     * Index of the fragment whose scan panel is currently open.
     * null when no scan is active.
     */
    scanOpen: { type: [Number, null], default: null },

    /**
     * ID of the pool tile currently selected for placement.
     * null when no tile is selected.
     */
    selectedPoolId: { type: [Number, null], default: null },
});

// ── Emits ──────────────────────────────────────────────────────────────────────

defineEmits([
    /**
     * User clicked a letter slot.
     * Payload: (fragmentIndex: number, slotIndex: number)
     */
    'slot-click',

    /**
     * User clicked the SCAN button.
     * Payload: (fragmentIndex: number)
     */
    'scan-click',

    /**
     * User clicked the INJECT button.
     * Payload: (fragmentIndex: number)
     */
    'inject-click',
]);
</script>

<style scoped>
/* ── Canvas — fills the 1fr center cell completely ────────────────────────── */

.fc-canvas {
    width: 100%;
    height: 100%;
    min-height: 0; /* prevent flex child from overflowing its parent cell */
    display: flex;
    flex-direction: column;
    /* Equal vertical distribution across the three fragment cards */
    gap: 0;
    overflow: hidden;
    font-family: 'JetBrains Mono', monospace;
}

/*
 * Each CipherFragment card receives equal height via flex.
 * The card's own internal padding controls breathing room.
 * Override :deep() only if card needs explicit flex behaviour.
 */
.fc-canvas > :deep(.cf-card) {
    flex: 1;
    min-height: 0;
    /* Separator between cards — avoids double borders */
    border-top: none;
}

.fc-canvas > :deep(.cf-card:first-child) {
    border-top: 1px solid rgba(0,200,240,0.12);
}

/* ── Empty-state placeholder ──────────────────────────────────────────────── */

.fc-placeholder {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 16px;
    padding: 28px 32px;
    border-top: 1px solid rgba(0,200,240,0.07);
    opacity: 0.35;
}

.fc-ph-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.fc-ph-id    { font-size: 11px; letter-spacing: 0.2em; color: rgba(0,200,240,0.4); }
.fc-ph-badge { font-size: 9px;  letter-spacing: 0.14em; color: rgba(0,200,240,0.25); }

.fc-ph-body {
    display: flex;
    gap: 6px;
}

.fc-ph-dot {
    font-size: 24px;
    color: rgba(0,200,240,0.15);
    animation: fc-blink 1.4s ease infinite alternate;
}

.fc-ph-dot:nth-child(2) { animation-delay: 0.2s; }
.fc-ph-dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes fc-blink {
    from { opacity: 0.15; }
    to   { opacity: 0.5;  }
}
</style>
