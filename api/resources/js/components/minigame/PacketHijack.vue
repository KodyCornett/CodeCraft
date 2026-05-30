<template>
    <div class="ph-overlay">

        <!-- ── Defender alert banner ────────────────────────────────────────── -->
        <Transition name="alert-fade">
            <div v-if="defenderAlertActive" class="ph-alert-banner">
                ⚠ CRITICAL ALERT: ACTIVE INTRUSION IMMINENT ⚠
            </div>
        </Transition>

        <!-- ── Match complete screen ────────────────────────────────────────── -->
        <div v-if="isComplete" class="ph-complete">
            <div class="ph-complete-box" :class="matchResult.isWinner ? 'complete--win' : 'complete--loss'">
                <template v-if="matchResult.isWinner">
                    <div class="ph-complete-title">[ BREACH COMPLETE ]</div>
                    <div class="ph-complete-line">NODE FULLY PURGED. CRED BUFFER ACQUIRED.</div>
                    <div class="ph-complete-creds">+{{ (matchResult.credsStolen ?? 0).toLocaleString() }} ₡ TRANSFERRED</div>
                </template>
                <template v-else>
                    <div class="ph-complete-title ph-complete-title--loss">[ CONNECTION TERMINATED ]</div>
                    <div class="ph-complete-line">INTRUSION DETECTED. WALLET SEIZED.</div>
                    <div class="ph-complete-creds ph-complete-creds--loss">-{{ (matchResult.credsStolen ?? 0).toLocaleString() }} ₡ LOST</div>
                </template>
                <button class="ph-complete-btn" @click="$emit('match-complete', matchResult)">
                    DISCONNECT
                </button>
            </div>
        </div>

        <!-- ── Main terminal ─────────────────────────────────────────────────── -->
        <div v-else class="ph-terminal">

            <!-- Top bar -->
            <div class="ph-topbar">
                <span class="ph-topbar-id">MATCH_ID: {{ matchId?.slice(0, 8).toUpperCase() }}</span>
                <span class="ph-topbar-role">ROLE: <span class="ph-role-val">{{ role?.toUpperCase() }}</span></span>
                <span class="ph-topbar-phase" :class="phase === 2 ? 'phase--two' : 'phase--one'">
                    PHASE {{ phase }}: {{ phase === 1 ? 'RECON HUNT' : 'PORT INTRUSION' }}
                </span>
            </div>
            <div class="ph-rule" />

            <!-- Phase 2 port matrix (shown above history when in phase 2) -->
            <div v-if="phase === 2 && ports.length" class="ph-port-matrix">
                <div class="ph-matrix-header">[ PORT STATUS MATRIX // TARGET: {{ targetIp }} ]</div>
                <div class="ph-matrix-row ph-matrix-header-row">
                    <span class="ph-matrix-col ph-matrix-col--port">PORT</span>
                    <span class="ph-matrix-col ph-matrix-col--svc">SERVICE</span>
                    <span class="ph-matrix-col ph-matrix-col--bias">BIAS</span>
                    <span class="ph-matrix-col ph-matrix-col--status">STATUS</span>
                </div>
                <div
                    v-for="entry in ports"
                    :key="entry.port"
                    class="ph-matrix-row"
                    :class="portRowClass(entry)"
                >
                    <span class="ph-matrix-col ph-matrix-col--port">{{ entry.port }}</span>
                    <span class="ph-matrix-col ph-matrix-col--svc">{{ entry.service }}</span>
                    <span class="ph-matrix-col ph-matrix-col--bias">{{ entry.shattered ? '---' : entry.bias + '%' }}</span>
                    <span class="ph-matrix-col ph-matrix-col--status">{{ portStatus(entry) }}</span>
                </div>
                <div class="ph-rule ph-rule--light" />
            </div>

            <!-- Rig command loadout strip (hack commands only) -->
            <div v-if="hackCommands && hackCommands.length" class="ph-rig-strip">
                <span class="ph-rig-label">RIG:</span>
                <button
                    v-for="cmd in hackCommands"
                    :key="cmd.name"
                    class="ph-rig-btn"
                    :class="{
                        'rig-btn--used':     usedRigCommands.includes(commandSlug(cmd.name)),
                        'rig-btn--locked':   isLocked || busy,
                        'rig-btn--level2':   cmd.level === 2,
                    }"
                    :disabled="usedRigCommands.includes(commandSlug(cmd.name)) || isLocked || busy || isComplete"
                    :title="`${cmd.name} (L${cmd.level})`"
                    @click="$emit('use-rig-command', commandSlug(cmd.name))"
                >
                    {{ cmd.name.toUpperCase() }}
                    <span class="ph-rig-lvl">L{{ cmd.level }}</span>
                </button>
            </div>

            <!-- Scrollable history pane -->
            <div class="ph-history" ref="historyEl">
                <div v-for="(entry, i) in commandHistory" :key="i" class="ph-history-entry">
                    <div v-if="entry.input !== 'SYSTEM'" class="ph-history-input">
                        <span class="ph-prompt">SYS_INPUT &gt;</span> {{ entry.input }}
                    </div>
                    <div
                        v-for="(line, j) in entry.lines"
                        :key="j"
                        class="ph-history-line"
                        :class="lineClass(line)"
                    >{{ line }}</div>
                </div>
            </div>

            <div class="ph-rule ph-rule--light" />

            <!-- Input row -->
            <div class="ph-input-row">
                <span class="ph-prompt">SYS_INPUT &gt;</span>
                <div v-if="isLocked" class="ph-locked">
                    <span class="ph-lock-msg">[ INPUT LOCKED — {{ lockCountdown }}s ]</span>
                </div>
                <input
                    v-else
                    ref="inputEl"
                    v-model="inputValue"
                    class="ph-input"
                    type="text"
                    spellcheck="false"
                    autocomplete="off"
                    :disabled="busy || isComplete"
                    @keydown.enter.prevent="onSubmit"
                    @keydown.up.prevent="historyUp"
                    @keydown.down.prevent="historyDown"
                />
                <span class="ph-cursor" :class="{ 'cursor--blink': !isLocked }">█</span>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    matchId:             { type: String,  required: true },
    role:                { type: String,  required: true },
    phase:               { type: Number,  required: true },
    commandHistory:      { type: Array,   required: true },
    ports:               { type: Array,   default: () => [] },
    targetIp:            { type: String,  default: null },
    isLocked:            { type: Boolean, default: false },
    lockCountdown:       { type: Number,  default: 0 },
    defenderAlertActive: { type: Boolean, default: false },
    matchResult:         { type: Object,  default: null },
    isComplete:          { type: Boolean, default: false },
    busy:                { type: Boolean, default: false },
    hackCommands:        { type: Array,   default: () => [] },  // equipped hack-context commands
    usedRigCommands:     { type: Array,   default: () => [] },  // slugs spent this match
});

const emit = defineEmits(['submit-command', 'match-complete', 'use-rig-command']);

const inputEl   = ref(null);
const historyEl = ref(null);
const inputValue = ref('');

// Command history navigation (up/down arrow)
let historyNav  = [];
let historyNavI = -1;

// ── Submit ────────────────────────────────────────────────────────────────────

function onSubmit() {
    const val = inputValue.value.trim();
    if (!val || props.busy || props.isLocked) return;

    historyNav.unshift(val);
    historyNavI = -1;

    emit('submit-command', val);
    inputValue.value = '';
}

function historyUp() {
    if (historyNav.length === 0) return;
    historyNavI = Math.min(historyNavI + 1, historyNav.length - 1);
    inputValue.value = historyNav[historyNavI];
}

function historyDown() {
    historyNavI = Math.max(historyNavI - 1, -1);
    inputValue.value = historyNavI === -1 ? '' : historyNav[historyNavI];
}

// ── Auto-scroll history ───────────────────────────────────────────────────────
// Deep watch so we also scroll when typewriter lines are pushed into entries.

watch(() => props.commandHistory, async () => {
    await nextTick();
    if (historyEl.value) {
        historyEl.value.scrollTop = historyEl.value.scrollHeight;
    }
}, { deep: true });

// ── Re-focus input after lock releases ───────────────────────────────────────

watch(() => props.isLocked, (locked) => {
    if (!locked) {
        nextTick(() => inputEl.value?.focus());
    }
});

onMounted(() => {
    nextTick(() => inputEl.value?.focus());
});

// ── CSS helpers ───────────────────────────────────────────────────────────────

function portRowClass(entry) {
    if (entry.shattered)               return 'port--shattered';
    if (entry.port === 8080 && entry.unlocked) return 'port--exfil';
    if (entry.port === 8080)           return 'port--locked';
    if (entry.bias <= 25)              return 'port--low';
    return 'port--high';
}

function portStatus(entry) {
    if (entry.shattered)                        return 'SHATTERED';
    if (entry.port === 8080 && entry.unlocked)  return 'UNLOCKED';
    if (entry.port === 8080)                    return 'LOCKED';
    if (entry.bias <= 10)                       return 'CRITICAL LOW';
    if (entry.bias <= 25)                       return 'LOW';
    return 'HIGH';
}

function commandSlug(name) {
    return name.toLowerCase().replace(/ /g, '_');
}

function lineClass(line) {
    if (line.startsWith('[SUCCESS]') || line.startsWith('[BREACH'))  return 'line--success';
    if (line.startsWith('[ERROR]'))                                   return 'line--error';
    if (line.startsWith('[ALERT]') || line.startsWith('[CRITICAL'))  return 'line--alert';
    if (line.startsWith('[CAPTURED]') || line.startsWith('[DIAG'))   return 'line--clue';
    if (line.startsWith('->'))                                        return 'line--candidate';
    if (line.startsWith('[='))                                        return 'line--progress';
    return 'line--default';
}
</script>

<style scoped>
/* ── Overlay ──────────────────────────────────────────────────────────────── */
.ph-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: rgba(0, 0, 0, 0.92);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', 'Courier New', monospace;
}

/* ── Defender alert banner ───────────────────────────────────────────────── */
.ph-alert-banner {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9100;
    padding: 12px 0;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: #FF4444;
    background: rgba(255, 30, 30, 0.15);
    border-bottom: 2px solid #FF4444;
    animation: alert-pulse 0.5s ease-in-out infinite alternate;
}

@keyframes alert-pulse {
    from { color: #FF4444; background: rgba(255, 30, 30, 0.10); }
    to   { color: #FF8888; background: rgba(255, 30, 30, 0.25); }
}

.alert-fade-enter-active, .alert-fade-leave-active { transition: opacity 0.4s; }
.alert-fade-enter-from,   .alert-fade-leave-to     { opacity: 0; }

/* ── Match complete ──────────────────────────────────────────────────────── */
.ph-complete {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.ph-complete-box {
    border: 1px solid rgba(0, 255, 255, 0.3);
    padding: 40px 56px;
    text-align: center;
    min-width: 380px;
    background: rgba(8, 8, 15, 0.95);
}

.complete--win  { border-color: rgba(0, 255, 136, 0.5); }
.complete--loss { border-color: rgba(255, 50, 50, 0.4); }

.ph-complete-title {
    font-size: 20px;
    letter-spacing: 0.2em;
    color: #00ff88;
    margin-bottom: 16px;
}

.ph-complete-title--loss { color: #FF4444; }

.ph-complete-line {
    font-size: 11px;
    color: rgba(0, 255, 255, 0.6);
    letter-spacing: 0.1em;
    margin-bottom: 12px;
}

.ph-complete-creds {
    font-size: 16px;
    color: #00ff88;
    letter-spacing: 0.12em;
    margin-bottom: 28px;
}

.ph-complete-creds--loss { color: #FF4444; }

.ph-complete-btn {
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.4);
    color: rgba(0, 255, 255, 0.8);
    font-family: inherit;
    font-size: 11px;
    letter-spacing: 0.15em;
    padding: 8px 24px;
    cursor: pointer;
    transition: border-color 0.2s, color 0.2s;
}
.ph-complete-btn:hover {
    border-color: rgba(0, 255, 255, 0.9);
    color: #00FFFF;
}

/* ── Terminal ────────────────────────────────────────────────────────────── */
.ph-terminal {
    width: min(860px, 96vw);
    height: min(620px, 90vh);
    background: #08080f;
    border: 1px solid rgba(0, 255, 255, 0.18);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ph-topbar {
    display: flex;
    gap: 24px;
    padding: 8px 14px;
    font-size: 10px;
    letter-spacing: 0.12em;
    color: rgba(0, 255, 255, 0.45);
    background: rgba(0, 255, 255, 0.03);
    flex-shrink: 0;
}

.ph-topbar-phase { margin-left: auto; }
.phase--one { color: rgba(0, 255, 255, 0.55); }
.phase--two { color: #FFB300; }

.ph-role-val { color: #FF69B4; }

.ph-rule       { height: 1px; background: rgba(0,255,255,0.18); flex-shrink: 0; }
.ph-rule--light { height: 1px; background: rgba(0,255,255,0.07); flex-shrink: 0; }

/* ── Port matrix ─────────────────────────────────────────────────────────── */
.ph-port-matrix {
    flex-shrink: 0;
    padding: 8px 14px 6px;
    background: rgba(0, 255, 255, 0.02);
    font-size: 11px;
}

.ph-matrix-header {
    color: rgba(255, 179, 0, 0.7);
    letter-spacing: 0.1em;
    font-size: 10px;
    margin-bottom: 6px;
}

.ph-matrix-row {
    display: flex;
    gap: 0;
    padding: 2px 0;
    font-size: 11px;
}

.ph-matrix-header-row {
    color: rgba(0,255,255,0.3);
    font-size: 9px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    margin-bottom: 2px;
}

.ph-matrix-col { display: inline-block; }
.ph-matrix-col--port  { width: 70px;  color: rgba(0,255,255,0.7); }
.ph-matrix-col--svc   { width: 110px; color: rgba(0,255,255,0.5); }
.ph-matrix-col--bias  { width: 80px;  }
.ph-matrix-col--status{ flex: 1; }

.port--high     .ph-matrix-col--bias   { color: #FF4444; }
.port--high     .ph-matrix-col--status { color: rgba(255,68,68,0.7); }
.port--low      .ph-matrix-col--bias   { color: #FFB300; }
.port--low      .ph-matrix-col--status { color: #FFB300; }
.port--shattered .ph-matrix-col        { color: rgba(0,255,255,0.2); text-decoration: line-through; }
.port--exfil     .ph-matrix-col--bias  { color: #00ff88; }
.port--exfil     .ph-matrix-col--status { color: #00ff88; animation: exfil-pulse 0.8s ease-in-out infinite; }
.port--locked    .ph-matrix-col        { color: rgba(0,255,255,0.2); }

@keyframes exfil-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

/* ── History ─────────────────────────────────────────────────────────────── */
.ph-history {
    flex: 1;
    overflow-y: auto;
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,255,255,0.15) transparent;
}

.ph-history-entry { display: flex; flex-direction: column; gap: 2px; }

.ph-history-input {
    font-size: 11px;
    color: rgba(0,255,255,0.45);
    letter-spacing: 0.05em;
    margin-bottom: 2px;
}

.ph-history-line {
    font-size: 11px;
    letter-spacing: 0.04em;
    padding-left: 2px;
    white-space: pre-wrap;
    word-break: break-all;
}

.line--default   { color: rgba(0,255,255,0.7); }
.line--success   { color: #00ff88; }
.line--error     { color: #FF4444; }
.line--alert     { color: #FFB300; }
.line--clue      { color: #FF69B4; }
.line--candidate { color: #00FFFF; padding-left: 12px; }
.line--progress  { color: #00ff88; letter-spacing: 0.02em; }

/* ── Input row ───────────────────────────────────────────────────────────── */
.ph-input-row {
    display: flex;
    align-items: center;
    padding: 8px 14px;
    gap: 8px;
    flex-shrink: 0;
    background: rgba(0,255,255,0.02);
}

.ph-prompt {
    font-size: 11px;
    color: rgba(0,255,255,0.5);
    letter-spacing: 0.08em;
    white-space: nowrap;
    flex-shrink: 0;
}

.ph-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    color: #00FFFF;
    letter-spacing: 0.06em;
    caret-color: transparent; /* custom cursor below */
}

.ph-cursor {
    font-size: 12px;
    color: #00FFFF;
    flex-shrink: 0;
}

.cursor--blink { animation: blink 1s step-start infinite; }

@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
}

.ph-locked {
    flex: 1;
    display: flex;
    align-items: center;
}

.ph-lock-msg {
    font-size: 11px;
    color: #FF4444;
    letter-spacing: 0.1em;
    animation: alert-pulse 0.4s ease-in-out infinite alternate;
}

/* ── CRT scanline overlay ────────────────────────────────────────────────── */
.ph-terminal {
    position: relative; /* required for ::after */
}

.ph-terminal::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 3px,
        rgba(0, 0, 0, 0.09) 3px,
        rgba(0, 0, 0, 0.09) 4px
    );
    z-index: 10;
}

/* ── Rig command strip ───────────────────────────────────────────────────── */
.ph-rig-strip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    background: rgba(255, 105, 180, 0.04);
    border-bottom: 1px solid rgba(255, 105, 180, 0.1);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.ph-rig-label {
    font-size: 9px;
    letter-spacing: 0.15em;
    color: rgba(255, 105, 180, 0.5);
    flex-shrink: 0;
    margin-right: 2px;
}

.ph-rig-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: 1px solid rgba(255, 105, 180, 0.35);
    color: rgba(255, 105, 180, 0.85);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.12em;
    padding: 3px 8px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}

.ph-rig-btn:hover:not(:disabled) {
    border-color: rgba(255, 105, 180, 0.8);
    color: #FF69B4;
    background: rgba(255, 105, 180, 0.08);
}

.ph-rig-btn.rig-btn--level2 {
    border-color: rgba(255, 150, 50, 0.4);
    color: rgba(255, 150, 50, 0.85);
}

.ph-rig-btn.rig-btn--level2:hover:not(:disabled) {
    border-color: rgba(255, 150, 50, 0.9);
    color: #FF9632;
    background: rgba(255, 150, 50, 0.08);
}

.ph-rig-btn.rig-btn--used,
.ph-rig-btn:disabled {
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.2);
    cursor: not-allowed;
    text-decoration: line-through;
}

.ph-rig-lvl {
    font-size: 7px;
    opacity: 0.6;
    letter-spacing: 0;
}
</style>
