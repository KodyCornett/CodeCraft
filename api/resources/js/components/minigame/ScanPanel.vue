<template>
    <div class="sp-panel">

        <!-- ── Panel header ──────────────────────────────────────────────── -->
        <div class="sp-header">
            <div class="sp-header-path">
                <span class="sp-path-dim">ARCHIVE_DIR /</span>
                <span class="sp-path-frag">FRAGMENT_{{ padded }}</span>
            </div>
            <button class="sp-close" @click="$emit('close')" aria-label="Close scan panel">
                [ CLOSE ]
            </button>
        </div>

        <!-- ── Codename strip ────────────────────────────────────────────── -->
        <div class="sp-codename">
            <span class="sp-codename-label">TARGET //</span>
            {{ props.fragment?.codename ?? '—' }}
        </div>

        <!-- ── File directory ────────────────────────────────────────────── -->
        <div class="sp-dir-header">
            <span class="sp-dir-col sp-dir-col--name">FILENAME</span>
            <span class="sp-dir-col sp-dir-col--size">SIZE</span>
            <span class="sp-dir-col sp-dir-col--status">STATUS</span>
        </div>

        <div class="sp-file-list">
            <div
                v-for="(file, fi) in archive"
                :key="fi"
                class="sp-file-row"
                :class="{
                    'sp-file-row--active':  props.openFileIdx === fi,
                    'sp-file-row--locked':  file.locked && !isUnlocked(fi),
                }"
                @click="$emit('file-click', fi)"
            >
                <!-- Open button -->
                <button class="sp-open-btn" tabindex="-1">
                    <span class="sp-btn-bracket">[</span>
                    OPEN
                    <span class="sp-btn-bracket">]</span>
                </button>

                <!-- Filename -->
                <span class="sp-file-name">{{ file.name }}</span>

                <!-- Size -->
                <span class="sp-file-size">{{ file.size }}</span>

                <!-- Lock / status badge -->
                <span class="sp-file-status">
                    <template v-if="file.locked && !isUnlocked(fi)">
                        <span class="sp-lock-badge">
                            🔒 LOCKED
                        </span>
                        <span class="sp-lock-cost">[-{{ lockCostPct }}% STAB]</span>
                    </template>
                    <template v-else-if="file.locked && isUnlocked(fi)">
                        <span class="sp-unlocked-badge">◆ DECRYPTED</span>
                    </template>
                    <template v-else>
                        <span class="sp-open-badge">○ READABLE</span>
                    </template>
                </span>
            </div>
        </div>

        <!-- ── File content viewer ───────────────────────────────────────── -->
        <Transition name="sp-slide">
            <div v-if="props.openFileIdx !== null" class="sp-viewer">

                <div class="sp-viewer-header">
                    <span class="sp-viewer-fname">
                        {{ archive[props.openFileIdx]?.name ?? '' }}
                    </span>
                    <span
                        v-if="archive[props.openFileIdx]?.locked && isUnlocked(props.openFileIdx)"
                        class="sp-viewer-dec"
                    >DECRYPTED</span>
                </div>

                <div class="sp-viewer-body">
                    <pre class="sp-viewer-text">{{ props.fileContent }}</pre>
                </div>

            </div>
        </Transition>

    </div>
</template>

<script setup>
import { computed } from 'vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * Fragment object: { word, hint, codename, archive }
     * archive: [{ name, size, locked, content }]
     */
    fragment: { type: Object, default: null },

    /** 0-based index — used for the padded label */
    fragmentIndex: { type: Number, required: true },

    /** Index of the file currently open in the viewer, or null */
    openFileIdx: { type: [Number, null], default: null },

    /**
     * Set of file indexes the player has already paid to decrypt.
     * Passed as a plain Set from the parent.
     */
    unlockedIdxs: { type: Set, default: () => new Set() },

    /** Pre-resolved file content string (computed in parent) */
    fileContent: { type: String, default: '' },

    /**
     * Stability cost percentage shown in the lock badge.
     * Parent passes Math.round(cfg.lockCost * 100).
     */
    lockCostPct: { type: Number, default: 10 },
});

// ── Emits ──────────────────────────────────────────────────────────────────────

defineEmits([
    /** User clicked a file row. Payload: fileIndex (number) */
    'file-click',
    /** User clicked the close button */
    'close',
]);

// ── Derived ────────────────────────────────────────────────────────────────────

const padded  = computed(() => String(props.fragmentIndex + 1).padStart(2, '0'));
const archive = computed(() => props.fragment?.archive ?? []);

function isUnlocked(fi) {
    return props.unlockedIdxs.has(fi);
}
</script>

<style scoped>
/* ── Panel shell ──────────────────────────────────────────────────────────── */

.sp-panel {
    display: flex;
    flex-direction: column;
    gap: 0;
    font-family: 'JetBrains Mono', monospace;
    color: #00c8f0;
    border-bottom: 1px solid rgba(0,200,240,0.1);
    /* flex: 1 is set by parent; panel fills available space above SystemNoise */
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

/* ── Header ───────────────────────────────────────────────────────────────── */

.sp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 8px 8px;
    flex-shrink: 0;
    background: rgba(24,24,27,0.55);
    border: 1px solid rgba(251,146,60,0.3);
    box-shadow: 0 0 12px rgba(251,146,60,0.07);
}

.sp-header-path {
    display: flex;
    align-items: baseline;
    gap: 5px;
    font-size: 10px;
    letter-spacing: 0.12em;
}

.sp-path-dim  { color: rgba(251,146,60,0.35); }
.sp-path-frag {
    color: rgba(251,146,60,0.85);
    text-shadow: 0 0 8px rgba(251,146,60,0.4);
}

.sp-close {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    padding: 4px 8px;
    border: 1px solid rgba(0,200,240,0.18);
    background: transparent;
    color: rgba(0,200,240,0.4);
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
    flex-shrink: 0;
}

.sp-close:hover { color: #ff4444; border-color: rgba(255,60,60,0.4); }

/* ── Codename strip ───────────────────────────────────────────────────────── */

.sp-codename {
    padding: 8px 0;
    font-size: 10px;
    letter-spacing: 0.14em;
    color: rgba(0,200,240,0.5);
    border-bottom: 1px solid rgba(0,200,240,0.06);
    flex-shrink: 0;
}

.sp-codename-label {
    color: rgba(0,200,240,0.25);
    font-size: 9px;
    margin-right: 6px;
}

/* ── Directory column headers ─────────────────────────────────────────────── */

.sp-dir-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0 6px;
    border-bottom: 1px solid rgba(0,200,240,0.07);
    flex-shrink: 0;
}

.sp-dir-col {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: rgba(0,200,240,0.25);
}

.sp-dir-col--name   { flex: 1; }
.sp-dir-col--size   { width: 46px; text-align: right; }
.sp-dir-col--status { width: 100px; text-align: right; }

/* ── File list ────────────────────────────────────────────────────────────── */

.sp-file-list {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.sp-file-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(0,200,240,0.05);
    cursor: pointer;
    transition: background 0.12s;
}

.sp-file-row:hover         { background: rgba(0,200,240,0.04); }
.sp-file-row--active       { background: rgba(0,200,240,0.07) !important; }
.sp-file-row--locked       { opacity: 0.75; }

/* Open button */
.sp-open-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 2px 6px;
    border: 1px solid rgba(0,200,240,0.18);
    background: transparent;
    color: rgba(0,200,240,0.45);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s, border-color 0.12s;
}

.sp-file-row:hover .sp-open-btn,
.sp-file-row--active .sp-open-btn {
    color: #00c8f0;
    border-color: rgba(0,200,240,0.5);
}

.sp-btn-bracket { color: rgba(0,200,240,0.2); }

/* Filename */
.sp-file-name {
    flex: 1;
    font-size: 10px;
    color: rgba(0,200,240,0.75);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Size */
.sp-file-size {
    width: 46px;
    text-align: right;
    font-size: 9px;
    color: rgba(0,200,240,0.28);
    flex-shrink: 0;
}

/* Status badge */
.sp-file-status {
    width: 100px;
    text-align: right;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.sp-lock-badge    { font-size: 9px; color: rgba(255,170,0,0.6); letter-spacing: 0.06em; }
.sp-lock-cost     { font-size: 8px; color: rgba(255,100,0,0.45); letter-spacing: 0.04em; }
.sp-unlocked-badge{ font-size: 9px; color: rgba(0,200,240,0.45); letter-spacing: 0.06em; }
.sp-open-badge    { font-size: 9px; color: rgba(0,200,240,0.3);  letter-spacing: 0.06em; }

/* ── File content viewer ──────────────────────────────────────────────────── */

.sp-viewer {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(0,200,240,0.1);
    background: rgba(0,6,12,0.8);
    margin-top: 10px;
    overflow: hidden;
}

.sp-viewer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(0,200,240,0.08);
    flex-shrink: 0;
    background: rgba(0,14,22,0.6);
}

.sp-viewer-fname {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(0,200,240,0.5);
}

.sp-viewer-dec {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: rgba(0,200,240,0.35);
}

.sp-viewer-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px 10px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,200,240,0.15) transparent;
}

.sp-viewer-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: rgba(255,210,90,0.82);
    line-height: 1.75;
    margin: 0;
    white-space: pre-wrap;
    letter-spacing: 0.03em;
}

/* ── Transitions ──────────────────────────────────────────────────────────── */

.sp-slide-enter-active { transition: opacity 0.2s, transform 0.2s; }
.sp-slide-leave-active { transition: opacity 0.15s, transform 0.15s; }
.sp-slide-enter-from,
.sp-slide-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
