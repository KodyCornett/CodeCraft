<template>
    <div class="ql-page">

        <!-- ── Header ────────────────────────────────────────────────────── -->
        <div class="ql-header">
            <div class="ql-header-left">
                <span class="ql-online-dot" />
                <span class="ql-title">MISSION_LOG</span>
                <span class="ql-sep">//</span>
                <span class="ql-sub">QUEST TERMINAL</span>
            </div>
            <button class="ql-refresh" @click="fetchQuestLog" :disabled="loading">
                {{ loading ? 'SYNCING...' : '↺ SYNC' }}
            </button>
        </div>

        <div class="ql-rule" />

        <!-- ── Loading / error states ─────────────────────────────────────── -->
        <div v-if="loading && !docs.length" class="ql-boot">
            <span class="ql-boot-cursor">▌</span> FETCHING MISSION DATA...
        </div>

        <div v-else-if="error" class="ql-error">
            [ ERROR ] {{ error }}
        </div>

        <!-- ── Doc sections ───────────────────────────────────────────────── -->
        <template v-else>
            <div
                v-for="doc in docs"
                :key="doc.cyber_doc_id"
                class="ql-doc"
                :class="{ 'ql-doc--unmet': !doc.met }"
            >
                <!-- Doc header row — always visible -->
                <div
                    class="ql-doc-header"
                    @click="toggleDoc(doc.cyber_doc_id)"
                    :class="{ 'ql-doc-header--active': isOpen(doc.cyber_doc_id) }"
                >
                    <span class="ql-doc-chevron">{{ isOpen(doc.cyber_doc_id) ? '▼' : '▶' }}</span>
                    <span class="ql-doc-name">{{ docHandle(doc.name) }}</span>
                    <span v-if="doc.met && doc.rep" class="ql-rep-label" :class="repClass(doc.rep.tier_index)">
                        [{{ doc.rep.label }}]
                    </span>
                    <span v-if="doc.met && doc.rep" class="ql-rep-score">· {{ doc.rep.score }} REP</span>
                    <span v-if="doc.met && doc.rep" class="ql-rep-bar-wrap">
                        <span
                            class="ql-rep-bar-fill"
                            :class="repClass(doc.rep.tier_index)"
                            :style="{ width: (doc.rep.bar_pct * 100).toFixed(0) + '%' }"
                        />
                    </span>
                    <span v-if="!doc.met && !doc.referral" class="ql-unmet-tag">UNKNOWN</span>
                    <span v-if="!doc.met && doc.referral"  class="ql-referral-tag">INTRODUCTION PENDING</span>
                </div>

                <!-- Doc body — only when open -->
                <div v-if="isOpen(doc.cyber_doc_id)" class="ql-doc-body">

                    <!-- Rep threshold progress -->
                    <div v-if="doc.met && doc.rep && doc.rep.next_threshold" class="ql-rep-threshold">
                        <span class="ql-thresh-label">NEXT TIER</span>
                        <span class="ql-thresh-val">{{ doc.rep.next_threshold }} REP REQUIRED</span>
                    </div>
                    <div v-else-if="doc.met && doc.rep && !doc.rep.next_threshold" class="ql-rep-threshold">
                        <span class="ql-thresh-label ql-thresh-label--root">▸ MAX TIER REACHED</span>
                    </div>

                    <!-- Referral pending (not yet visited) -->
                    <div v-if="!doc.met && doc.referral" class="ql-referral-msg">
                        <span class="ql-chevron-inline">►</span> {{ doc.referral }}
                    </div>
                    <div v-if="!doc.met && !doc.referral" class="ql-unmet-msg">
                        [ No contact established. Locate this operator to open a line. ]
                    </div>

                    <!-- Arcs -->
                    <template v-if="doc.met">
                        <div
                            v-for="arc in doc.arcs"
                            :key="arc.id"
                            class="ql-arc"
                            :class="`ql-arc--${arc.status}`"
                        >
                            <div class="ql-arc-title">
                                <span class="ql-arc-status-icon">{{ arcIcon(arc.status) }}</span>
                                {{ arc.title }}
                                <span v-if="arc.status === 'locked'" class="ql-lock-tag">
                                    LOCKED — {{ arc.rep_required }} REP REQUIRED
                                </span>
                            </div>

                            <!-- Stages -->
                            <div
                                v-for="stage in arc.stages"
                                :key="stage.id"
                                class="ql-stage"
                                :class="`ql-stage--${stage.status}`"
                            >
                                <div class="ql-stage-header">
                                    <span class="ql-stage-icon">{{ stageIcon(stage.status) }}</span>
                                    <span class="ql-stage-title">{{ stage.title }}</span>
                                    <span v-if="stage.status === 'complete' && stage.turned_into_doc_id" class="ql-branch-tag">
                                        ROUTED
                                    </span>
                                </div>

                                <div v-if="stage.status !== 'locked' && stage.objective_text" class="ql-stage-obj">
                                    {{ stage.objective_text }}
                                </div>

                                <div v-if="stage.status === 'locked'" class="ql-stage-obj ql-stage-obj--locked">
                                    [ CLASSIFIED — COMPLETE PREVIOUS OBJECTIVE ]
                                </div>

                                <!-- Minigame launch — only shown when player is at the target node -->
                                <button
                                    v-if="stage.status === 'active' && stage.minigame_type && stage.node_canvas_id"
                                    class="ql-hack-btn"
                                    :class="{ 'ql-hack-btn--ready': currentNodeCanvasId === stage.node_canvas_id }"
                                    :disabled="currentNodeCanvasId !== stage.node_canvas_id"
                                    @click="onLaunchMinigame(stage)"
                                >
                                    <template v-if="currentNodeCanvasId === stage.node_canvas_id">
                                        ▶ INITIATE HACK
                                    </template>
                                    <template v-else>
                                        ▸ TRAVEL TO {{ stage.node_canvas_id?.toUpperCase() }} TO INITIATE
                                    </template>
                                </button>

                                <!-- Branch options -->
                                <div v-if="stage.status === 'active' && stage.is_branch && stage.branch_options" class="ql-branch">
                                    <div class="ql-branch-label">TURN JOB INTO:</div>
                                    <button
                                        v-for="opt in stage.branch_options"
                                        :key="opt.cyber_doc_id"
                                        class="ql-branch-btn"
                                        @click="onBranchSelect(stage.id, opt.cyber_doc_id)"
                                    >
                                        {{ opt.label }}
                                        <span class="ql-branch-rep">+{{ opt.rep_reward }} REP</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useQuestLog }      from '../../../composables/useQuestLog.js';
import { useQuestMinigame } from '../../../composables/useQuestMinigame.js';

const { docs, loading, error, fetchQuestLog, completeStage } = useQuestLog();
const { currentNodeCanvasId, launch } = useQuestMinigame();

// Per-type skin defaults — labels and brief objective text
const MINIGAME_SKIN = {
    disconnect_layer:   { primary: 'TRACE',       stability: 'SYSTEM HEAT', brief: 'Sever the governor chain before it reroutes.'           },
    flush_buffer:       { primary: 'SIGNAL LOAD', stability: 'STABILITY',   brief: 'Cancel the ghost signal before buffer overflow.'        },
    toxic_soak:         { primary: 'ABSORPTION',  stability: 'OVERLOAD',    brief: 'Hold position. Anchor until saturation threshold.'      },
    archive_extraction: { primary: 'DETECTION',   stability: 'SUPPRESSION', brief: 'Extract the packet. Avoid triggering live ICE.'         },
    calibration_tether: { primary: 'PAYLOAD',     stability: 'INTEGRITY',   brief: 'Deliver the sub-routines. Do not let the chain cascade.' },
};

function onLaunchMinigame(stage) {
    const meta = MINIGAME_SKIN[stage.minigame_type] ?? { primary: 'TRACE', stability: 'STABILITY', brief: '' };
    const skin = {
        gameType:        stage.minigame_type,
        fileName:        (stage.node_canvas_id ?? 'UNKNOWN').toUpperCase() + '.sys',
        objectiveText:   meta.brief,
        successText:     'Objective complete. Disconnecting.',
        failText:        'Connection lost.',
        primaryBarLabel: meta.primary,
        stabilityLabel:  meta.stability,
        timeLimit:       30,
        difficulty:      1,
    };
    launch(stage.id, stage.minigame_type, skin);
}

// ── Collapse state ────────────────────────────────────────────────────────────
const openDocs = ref(new Set());

function toggleDoc(id) {
    if (openDocs.value.has(id)) {
        openDocs.value.delete(id);
    } else {
        openDocs.value.add(id);
    }
}

function isOpen(id) {
    return openDocs.value.has(id);
}

// Auto-open docs the player has met on first load
function autoOpen() {
    docs.value.forEach(doc => {
        if (doc.met || doc.referral) openDocs.value.add(doc.cyber_doc_id);
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Extract the operator handle from the full doc name
// e.g. "Knuckle's Med-Wagon" → "KNUCKLE"
function docHandle(name) {
    const match = name.match(/^([A-Za-z]+)/);
    return match ? match[1].toUpperCase() : name.toUpperCase();
}

const REP_CLASSES = ['ql-rep--null', 'ql-rep--resolved', 'ql-rep--routed', 'ql-rep--encrypted', 'ql-rep--root'];

function repClass(tierIndex) {
    return REP_CLASSES[tierIndex] ?? REP_CLASSES[0];
}

function arcIcon(status) {
    if (status === 'complete') return '✓';
    if (status === 'active')   return '►';
    return '░';
}

function stageIcon(status) {
    if (status === 'complete') return '✓';
    if (status === 'active')   return '►';
    return '░';
}

// ── Branch selection ──────────────────────────────────────────────────────────
async function onBranchSelect(stageId, docId) {
    await completeStage(stageId, docId);
    autoOpen();
}

// ── Init ──────────────────────────────────────────────────────────────────────
onMounted(async () => {
    await fetchQuestLog();
    autoOpen();
});
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */
.ql-page {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #a0c4b8;
    background: transparent;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 100%;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.ql-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}
.ql-header-left { display: flex; align-items: center; gap: 6px; }
.ql-online-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #00ff9d;
    box-shadow: 0 0 6px #00ff9d;
    flex-shrink: 0;
}
.ql-title   { color: #00ff9d; font-size: 12px; font-weight: 700; letter-spacing: 1px; }
.ql-sep     { color: #3a5a52; }
.ql-sub     { color: #4a7a6a; font-size: 10px; }
.ql-refresh {
    background: none; border: 1px solid #1e4a3a; color: #4a9a7a;
    font-family: inherit; font-size: 10px; padding: 2px 8px; cursor: pointer;
}
.ql-refresh:hover:not(:disabled) { border-color: #00ff9d; color: #00ff9d; }
.ql-refresh:disabled { opacity: 0.4; cursor: default; }

.ql-rule { border: none; border-top: 1px solid #1a3a2a; margin: 4px 0; }

/* ── Boot / error ────────────────────────────────────────────────────────── */
.ql-boot   { color: #4a9a7a; padding: 20px 0; text-align: center; }
.ql-boot-cursor { animation: blink 1s step-end infinite; }
.ql-error  { color: #ff4444; padding: 10px 0; }
@keyframes blink { 50% { opacity: 0; } }

/* ── Doc block ───────────────────────────────────────────────────────────── */
.ql-doc { border: 1px solid #1a3a2a; margin-bottom: 6px; }
.ql-doc--unmet { opacity: 0.6; }

.ql-doc-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    cursor: pointer;
    background: #060e0a;
    user-select: none;
}
.ql-doc-header:hover,
.ql-doc-header--active { background: #0a1a12; }
.ql-doc-chevron { color: #4a9a7a; width: 10px; flex-shrink: 0; }
.ql-doc-name    { color: #d0f0e0; font-weight: 700; letter-spacing: 1px; flex-shrink: 0; }
.ql-rep-label   { font-size: 10px; font-weight: 700; }
.ql-rep-score   { color: #4a7a6a; font-size: 10px; }

/* Rep bar */
.ql-rep-bar-wrap {
    flex: 1;
    height: 4px;
    background: #0d2a1e;
    position: relative;
    overflow: hidden;
    max-width: 80px;
}
.ql-rep-bar-fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    transition: width 0.4s ease;
}

/* Rep tier colours */
.ql-rep--null      { color: #3a5a52; }
.ql-rep--resolved  { color: #5a9a7a; }
.ql-rep--routed    { color: #00c87a; }
.ql-rep--encrypted { color: #00ff9d; }
.ql-rep--root      { color: #ff9d00; }
.ql-rep--null.ql-rep-bar-fill      { background: #3a5a52; }
.ql-rep--resolved.ql-rep-bar-fill  { background: #5a9a7a; }
.ql-rep--routed.ql-rep-bar-fill    { background: #00c87a; }
.ql-rep--encrypted.ql-rep-bar-fill { background: #00ff9d; }
.ql-rep--root.ql-rep-bar-fill      { background: #ff9d00; }

.ql-unmet-tag    { color: #3a5a52; font-size: 10px; margin-left: auto; }
.ql-referral-tag { color: #d0a020; font-size: 10px; margin-left: auto; animation: pulse 2s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: 0.5; } }

/* ── Doc body ─────────────────────────────────────────────────────────────── */
.ql-doc-body {
    padding: 6px 10px 8px 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px solid #0e2a1e;
}

.ql-rep-threshold {
    display: flex;
    gap: 8px;
    font-size: 10px;
    color: #3a6a52;
    padding-bottom: 2px;
    border-bottom: 1px solid #0a1e14;
}
.ql-thresh-label { color: #4a7a6a; }
.ql-thresh-val   { color: #5a9a7a; }
.ql-thresh-label--root { color: #ff9d00; }

.ql-referral-msg {
    color: #d0a020;
    padding: 4px 0;
}
.ql-chevron-inline { color: #d0a020; margin-right: 4px; }
.ql-unmet-msg { color: #2a4a3a; font-style: italic; }

/* ── Arc ──────────────────────────────────────────────────────────────────── */
.ql-arc { margin-top: 4px; }
.ql-arc-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 0 4px;
    color: #a0d0b8;
}
.ql-arc--locked .ql-arc-title { color: #2a4a3a; }
.ql-arc--complete .ql-arc-title { color: #4a7a6a; }
.ql-arc-status-icon { width: 12px; flex-shrink: 0; }
.ql-lock-tag {
    font-size: 9px;
    color: #4a5a52;
    font-weight: 400;
    margin-left: 4px;
}

/* ── Stage ────────────────────────────────────────────────────────────────── */
.ql-stage {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 3px 0 3px 14px;
    border-left: 2px solid #0a1e14;
}
.ql-stage--active  { border-left-color: #00ff9d; }
.ql-stage--complete { border-left-color: #1e4a3a; }
.ql-stage--locked  { border-left-color: #0a1a0e; opacity: 0.5; }

.ql-stage-header {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ql-stage-icon  { width: 10px; flex-shrink: 0; color: #4a9a7a; }
.ql-stage--active .ql-stage-icon  { color: #00ff9d; }
.ql-stage--complete .ql-stage-icon { color: #2a6a4a; }
.ql-stage-title {
    font-size: 10px;
    font-weight: 700;
    color: #7ab8a0;
    letter-spacing: 0.5px;
}
.ql-stage--active .ql-stage-title  { color: #c0f0d8; }
.ql-stage--complete .ql-stage-title { color: #3a6a52; text-decoration: line-through; }
.ql-stage--locked .ql-stage-title  { color: #2a4a3a; }
.ql-branch-tag {
    font-size: 9px;
    color: #4a7a6a;
    margin-left: auto;
}

.ql-stage-obj {
    font-size: 10px;
    color: #6a9a82;
    padding-left: 16px;
    line-height: 1.5;
}
.ql-stage-obj--locked { color: #2a4a3a; font-style: italic; }
.ql-stage--complete .ql-stage-obj { color: #2a5a3a; }

/* ── Branch options ──────────────────────────────────────────────────────── */
.ql-branch {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 6px 0 2px 16px;
}
.ql-branch-label {
    font-size: 9px;
    color: #d0a020;
    letter-spacing: 1px;
    margin-bottom: 2px;
}
.ql-branch-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #060e0a;
    border: 1px solid #1e4a2a;
    color: #a0d0b8;
    font-family: inherit;
    font-size: 10px;
    padding: 4px 8px;
    cursor: pointer;
    text-align: left;
}
.ql-branch-btn:hover { border-color: #00ff9d; color: #fff; }
.ql-branch-rep { color: #00c87a; font-size: 9px; }

/* ── Minigame launch button ──────────────────────────────────────────────── */
.ql-hack-btn {
    display: block;
    margin-top: 6px;
    margin-left: 16px;
    background: none;
    border: 1px solid #1e3a2a;
    color: #3a6a52;
    font-family: inherit;
    font-size: 10px;
    padding: 5px 12px;
    cursor: default;
    letter-spacing: 0.1em;
    text-align: left;
    transition: border-color 0.15s, color 0.15s;
}
.ql-hack-btn--ready {
    border-color: #00ff9d;
    color: #00ff9d;
    cursor: pointer;
    animation: ql-hack-pulse 2s ease-in-out infinite;
}
.ql-hack-btn--ready:hover {
    background: rgba(0,255,100,0.06);
}
@keyframes ql-hack-pulse {
    0%,100% { box-shadow: none; }
    50%      { box-shadow: 0 0 10px rgba(0,255,100,0.2); }
}
</style>
