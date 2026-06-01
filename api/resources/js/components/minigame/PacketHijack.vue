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
                <button class="ph-ref-toggle" :class="{ 'ref-toggle--active': showPanel }" @click="showPanel = !showPanel">[?]</button>
            </div>
            <div class="ph-rule" />

            <!-- Phase 2 port matrix -->
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

            <!-- Rig command strip -->
            <div v-if="hackCommands && hackCommands.length" class="ph-rig-strip">
                <span class="ph-rig-label">RIG:</span>
                <button
                    v-for="cmd in hackCommands"
                    :key="cmd.name"
                    class="ph-rig-btn"
                    :class="{
                        'rig-btn--used':   usedRigCommands.includes(commandSlug(cmd.name)),
                        'rig-btn--locked': isLocked || busy,
                        'rig-btn--level2': cmd.level === 2,
                    }"
                    :disabled="usedRigCommands.includes(commandSlug(cmd.name)) || isLocked || busy || isComplete"
                    @click="$emit('use-rig-command', commandSlug(cmd.name))"
                >
                    {{ cmd.name.toUpperCase() }}
                    <span class="ph-rig-lvl">L{{ cmd.level }}</span>
                </button>
            </div>

            <!-- Body row: terminal col + side panel -->
            <div class="ph-body">

                <!-- Left: history + input -->
                <div class="ph-main-col">

                    <!-- Tension boot screen — shown until netstat is run -->
                    <div v-if="!boardReady && phase === 1" class="ph-boot">
                        <div class="ph-boot-line">[ PACKET HIJACK INITIALISED ]</div>
                        <div class="ph-boot-line">[ NODE {{ nodeLabel }} — SHARED ACCESS DETECTED ]</div>
                        <div class="ph-boot-gap" />
                        <div class="ph-boot-warn">WARNING: HOSTILE OPERATOR DETECTED ON NODE</div>
                        <div class="ph-boot-warn ph-boot-warn--indent">YOUR POSITION IS NOT YET SECURED</div>
                        <div class="ph-boot-gap" />
                        <div class="ph-boot-hint">TYPE <span class="ph-boot-cmd">netstat --active</span> TO BEGIN TRACE</div>
                        <div class="ph-boot-threat">{{ threatMessage }}</div>
                        <div class="ph-boot-cursor">▌</div>
                    </div>

                    <!-- Scrollable history -->
                    <div v-else class="ph-history" ref="historyEl">
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

                <!-- Drag handle -->
                <div v-if="showPanel" class="ph-drag-handle" @mousedown="startDrag" title="Drag to resize"></div>

                <!-- Right: case file + command ref -->
                <Transition name="ref-slide">
                    <div v-if="showPanel" class="ph-ref-panel" :style="{ width: panelWidth + 'px' }">

                        <!-- ── Phase 1: case file ── -->
                        <template v-if="phase === 1">
                            <!-- Case file — full height, no scroll, all rows visible -->
                            <div class="ph-cf-section">
                                <div class="ph-ref-title">CASE FILE</div>
                                <div class="ph-ref-phase">// NODE SUSPECTS</div>

                                <div v-if="!boardReady" class="ph-ref-empty">
                                    RUN netstat --active TO POPULATE
                                </div>

                                <template v-else>
                                    <div class="ph-cf-stats">
                                        ACTIVE: {{ activeSuspectCount }} / {{ suspects.length }}
                                        <span v-if="octetClue" class="ph-cf-clue"> // OCTET: {{ octetClue }}</span>
                                    </div>
                                    <div class="ph-cf-header">
                                        <span class="ph-cf-col ph-cf-col--ip">IP</span>
                                        <span class="ph-cf-col ph-cf-col--ping">PING</span>
                                        <span class="ph-cf-col ph-cf-col--hops">HPS</span>
                                        <span class="ph-cf-col ph-cf-col--arp">ARP</span>
                                    </div>
                                    <div
                                        v-for="s in suspects"
                                        :key="s.ip"
                                        class="ph-cf-row"
                                        :class="{ 'cf-row--flushed': s.flushed }"
                                    >
                                        <span class="ph-cf-col ph-cf-col--ip">{{ s.ip }}</span>
                                        <span class="ph-cf-col ph-cf-col--ping" :class="pingClass(s)">
                                            {{ pingDisplay(s) }}
                                        </span>
                                        <span class="ph-cf-col ph-cf-col--hops">
                                            {{ s.hops !== undefined ? s.hops : '???' }}
                                        </span>
                                        <span class="ph-cf-col ph-cf-col--arp" :class="arpClass(s)">
                                            {{ arpDisplay(s) }}
                                        </span>
                                    </div>
                                </template>
                            </div>

                            <!-- Divider -->
                            <div class="ph-panel-rule" />

                            <!-- Command ref — scrollable, sits below case file -->
                            <div class="ph-cmd-ref-section">
                                <div class="ph-ref-title">CMD REF</div>
                                <div class="ph-ref-phase">// PHASE 1 — RECON</div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">ping &lt;ip&gt;</div>
                                    <div class="ph-ref-desc">Probe a suspect for response time. Fast = likely active player.</div>
                                </div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">traceroute &lt;ip&gt;</div>
                                    <div class="ph-ref-desc">Map route to suspect. Low hop count = same local network.</div>
                                </div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">arp --scan</div>
                                    <div class="ph-ref-desc">Check when all suspects were last active. Your target just arrived.</div>
                                </div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">whois &lt;ip&gt;</div>
                                    <div class="ph-ref-desc">Query registry data. May reveal chassis type if target has low OS.</div>
                                </div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">sniff --traffic</div>
                                    <div class="ph-ref-desc">Intercept a live packet fragment. Reveals one octet of target IP.</div>
                                </div>
                                <div class="ph-ref-entry">
                                    <div class="ph-ref-cmd">flush &lt;ip&gt;</div>
                                    <div class="ph-ref-desc">Remove a confirmed non-target from your case file.</div>
                                </div>
                                <div class="ph-ref-entry ph-ref-entry--commit">
                                    <div class="ph-ref-cmd">inject &lt;ip&gt;</div>
                                    <div class="ph-ref-desc">Commit your guess and deploy payload. Wrong = input locked.</div>
                                </div>
                            </div>
                        </template>

                        <!-- ── Phase 2: port commands only ── -->
                        <template v-else>
                            <div class="ph-ref-title">CMD REF</div>
                            <div class="ph-ref-phase">// PHASE 2 — INTRUSION</div>
                            <div class="ph-ref-entry">
                                <div class="ph-ref-cmd">probe port &lt;n&gt;</div>
                                <div class="ph-ref-desc">Analyse a port's service and encryption weakness.</div>
                            </div>
                            <div class="ph-ref-entry">
                                <div class="ph-ref-cmd">decode port &lt;n&gt;</div>
                                <div class="ph-ref-desc">Manually chip away at bias — works on any port regardless of level.</div>
                            </div>
                            <div class="ph-ref-entry">
                                <div class="ph-ref-cmd">exploit port &lt;n&gt;</div>
                                <div class="ph-ref-desc">Shatter a port when bias is 25% or lower. Cascades to remaining ports.</div>
                            </div>
                            <div class="ph-ref-entry ph-ref-entry--commit">
                                <div class="ph-ref-cmd">breach &lt;ip&gt;</div>
                                <div class="ph-ref-desc">Deploy final payload. Use the IP you found in Phase 1.</div>
                            </div>
                            <div class="ph-ref-note">EXFIL PORT 8080 UNLOCKS AFTER ALL GATES SHATTERED</div>
                        </template>

                    </div>
                </Transition>

            </div><!-- end ph-body -->
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
    matchId:             { type: String,  required: true },
    role:                { type: String,  required: true },
    phase:               { type: Number,  required: true },
    commandHistory:      { type: Array,   required: true },
    suspects:            { type: Array,   default: () => [] },
    octetClue:           { type: String,  default: null },
    activeSuspectCount:  { type: Number,  default: 0 },
    boardReady:          { type: Boolean, default: false },
    ports:               { type: Array,   default: () => [] },
    targetIp:            { type: String,  default: null },
    isLocked:            { type: Boolean, default: false },
    lockCountdown:       { type: Number,  default: 0 },
    defenderAlertActive: { type: Boolean, default: false },
    matchResult:         { type: Object,  default: null },
    isComplete:          { type: Boolean, default: false },
    busy:                { type: Boolean, default: false },
    hackCommands:        { type: Array,   default: () => [] },
    usedRigCommands:     { type: Array,   default: () => [] },
});

const emit = defineEmits(['submit-command', 'match-complete', 'use-rig-command']);

const inputEl    = ref(null);
const historyEl  = ref(null);
const inputValue = ref('');
const showPanel  = ref(true);
const panelWidth = ref(270);

let historyNav  = [];
let historyNavI = -1;

// ── Panel drag resize ─────────────────────────────────────────────────────────

function startDrag(e) {
    e.preventDefault();
    const startX     = e.clientX;
    const startWidth = panelWidth.value;

    function onMove(e) {
        // Dragging left increases panel width, dragging right decreases
        const delta = startX - e.clientX;
        panelWidth.value = Math.min(520, Math.max(200, startWidth + delta));
    }

    function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
    }

    document.body.style.cursor    = 'col-resize';
    document.body.style.userSelect = 'none';
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

// Random threat message — rotates per match open
const threatMessages = [
    'YOUR OPPONENT MAY ALREADY BE HUNTING',
    'HOSTILE TRACE ACTIVITY DETECTED — UNCONFIRMED',
    'UNKNOWN OPERATOR ACTIVE ON THIS NODE',
    'YOU ARE NOT ALONE ON THIS NETWORK',
];
const threatMessage = threatMessages[Math.floor(Math.random() * threatMessages.length)];
const nodeLabel     = props.matchId?.slice(0, 6).toUpperCase() ?? '??????';

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

watch(() => props.commandHistory, async () => {
    await nextTick();
    if (historyEl.value) historyEl.value.scrollTop = historyEl.value.scrollHeight;
}, { deep: true });

watch(() => props.isLocked, (locked) => {
    if (!locked) nextTick(() => inputEl.value?.focus());
});

onMounted(() => nextTick(() => inputEl.value?.focus()));

// ── Case file display helpers ─────────────────────────────────────────────────

function pingDisplay(s) {
    if (s.latency_ms === undefined) return '???';
    if (s.latency_status === 'TIMEOUT') return 'TIMEOUT';
    return `${s.latency_ms}ms`;
}

function pingClass(s) {
    if (s.latency_status === 'TIMEOUT') return 'cf-val--dead';
    if (s.latency_ms !== undefined && s.latency_ms <= 20) return 'cf-val--live';
    if (s.latency_status === 'DEGRADED') return 'cf-val--degraded';
    return '';
}

function arpDisplay(s) {
    if (s._arp_revealed === undefined && s.last_seen_seconds === undefined) return '???';
    const sec = s.last_seen_seconds;
    if (sec <= 5) return 'JUST NOW';
    if (sec < 60) return `${sec}s AGO`;
    return `${Math.floor(sec / 60)}m AGO`;
}

function arpClass(s) {
    if (s.last_seen_seconds === undefined) return '';
    if (s.last_seen_seconds <= 5) return 'cf-val--live';
    if (s.last_seen_seconds > 60) return 'cf-val--dead';
    return 'cf-val--degraded';
}

// ── Port matrix helpers ───────────────────────────────────────────────────────

function portRowClass(entry) {
    if (entry.shattered)                          return 'port--shattered';
    if (entry.port === 8080 && entry.unlocked)    return 'port--exfil';
    if (entry.port === 8080)                      return 'port--locked';
    if (entry.bias <= 25)                         return 'port--low';
    return 'port--high';
}

function portStatus(entry) {
    if (entry.shattered)                          return 'SHATTERED';
    if (entry.port === 8080 && entry.unlocked)    return 'UNLOCKED';
    if (entry.port === 8080)                      return 'LOCKED';
    if (entry.bias <= 10)                         return 'CRITICAL LOW';
    if (entry.bias <= 25)                         return 'LOW';
    return 'HIGH';
}

function commandSlug(name) {
    return name.toLowerCase().replace(/ /g, '_');
}

function lineClass(line) {
    if (line.startsWith('[SUCCESS]') || line.startsWith('[BREACH'))  return 'line--success';
    if (line.startsWith('[ERROR]'))                                   return 'line--error';
    if (line.startsWith('[ALERT]') || line.startsWith('[WARNING'))   return 'line--alert';
    if (line.startsWith('[OCTET') || line.startsWith('[CAPTURED]'))  return 'line--clue';
    if (line.startsWith('[RESULT]'))                                  return 'line--result';
    if (line.startsWith('[='))                                        return 'line--progress';
    if (line.startsWith('  ') && line.includes('—'))                 return 'line--arp';
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

/* ── Defender alert ──────────────────────────────────────────────────────── */
.ph-alert-banner {
    position: absolute;
    top: 0; left: 0; right: 0;
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
    from { color: #FF4444; background: rgba(255,30,30,0.10); }
    to   { color: #FF8888; background: rgba(255,30,30,0.25); }
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
    border: 1px solid rgba(0,255,255,0.3);
    padding: 40px 56px;
    text-align: center;
    min-width: 380px;
    background: rgba(8,8,15,0.95);
}

.complete--win  { border-color: rgba(0,255,136,0.5); }
.complete--loss { border-color: rgba(255,50,50,0.4); }

.ph-complete-title       { font-size: 20px; letter-spacing: 0.2em; color: #00ff88; margin-bottom: 16px; }
.ph-complete-title--loss { color: #FF4444; }
.ph-complete-line        { font-size: 11px; color: rgba(0,255,255,0.6); letter-spacing: 0.1em; margin-bottom: 12px; }
.ph-complete-creds       { font-size: 16px; color: #00ff88; letter-spacing: 0.12em; margin-bottom: 28px; }
.ph-complete-creds--loss { color: #FF4444; }

.ph-complete-btn {
    background: transparent;
    border: 1px solid rgba(0,255,255,0.4);
    color: rgba(0,255,255,0.8);
    font-family: inherit;
    font-size: 11px;
    letter-spacing: 0.15em;
    padding: 8px 24px;
    cursor: pointer;
    transition: border-color 0.2s, color 0.2s;
}
.ph-complete-btn:hover { border-color: rgba(0,255,255,0.9); color: #00FFFF; }

/* ── Terminal shell ──────────────────────────────────────────────────────── */
.ph-terminal {
    position: relative;
    width: min(1060px, 98vw);
    height: min(820px, 96vh);
    background: #08080f;
    border: 1px solid rgba(0,255,255,0.18);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.ph-terminal::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(0,0,0,0.09) 3px, rgba(0,0,0,0.09) 4px);
    z-index: 10;
}

/* ── Top bar ─────────────────────────────────────────────────────────────── */
.ph-topbar {
    display: flex;
    gap: 24px;
    padding: 8px 14px;
    font-size: 10px;
    letter-spacing: 0.12em;
    color: rgba(0,255,255,0.45);
    background: rgba(0,255,255,0.03);
    flex-shrink: 0;
    align-items: center;
}

.ph-topbar-phase { margin-left: auto; }
.phase--one { color: rgba(0,255,255,0.55); }
.phase--two { color: #FFB300; }
.ph-role-val { color: #FF69B4; }

.ph-rule        { height: 1px; background: rgba(0,255,255,0.18); flex-shrink: 0; }
.ph-rule--light { height: 1px; background: rgba(0,255,255,0.07); flex-shrink: 0; }

/* ── Ref toggle button ───────────────────────────────────────────────────── */
.ph-ref-toggle {
    background: transparent;
    border: 1px solid rgba(0,255,255,0.2);
    color: rgba(0,255,255,0.4);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.12em;
    padding: 2px 7px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
    flex-shrink: 0;
}
.ph-ref-toggle:hover,
.ph-ref-toggle.ref-toggle--active {
    border-color: rgba(0,255,255,0.6);
    color: rgba(0,255,255,0.9);
}

/* ── Port matrix ─────────────────────────────────────────────────────────── */
.ph-port-matrix {
    flex-shrink: 0;
    padding: 8px 14px 6px;
    background: rgba(0,255,255,0.02);
    font-size: 11px;
}

.ph-matrix-header { color: rgba(255,179,0,0.7); letter-spacing: 0.1em; font-size: 10px; margin-bottom: 6px; }

.ph-matrix-row { display: flex; padding: 2px 0; font-size: 11px; }
.ph-matrix-header-row {
    color: rgba(0,255,255,0.3);
    font-size: 9px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    margin-bottom: 2px;
}

.ph-matrix-col { display: inline-block; }
.ph-matrix-col--port   { width: 70px;  color: rgba(0,255,255,0.7); }
.ph-matrix-col--svc    { width: 110px; color: rgba(0,255,255,0.5); }
.ph-matrix-col--bias   { width: 80px; }
.ph-matrix-col--status { flex: 1; }

.port--high     .ph-matrix-col--bias   { color: #FF4444; }
.port--high     .ph-matrix-col--status { color: rgba(255,68,68,0.7); }
.port--low      .ph-matrix-col--bias   { color: #FFB300; }
.port--low      .ph-matrix-col--status { color: #FFB300; }
.port--shattered .ph-matrix-col        { color: rgba(0,255,255,0.2); text-decoration: line-through; }
.port--exfil     .ph-matrix-col--bias  { color: #00ff88; }
.port--exfil     .ph-matrix-col--status { color: #00ff88; animation: exfil-pulse 0.8s ease-in-out infinite; }
.port--locked    .ph-matrix-col        { color: rgba(0,255,255,0.2); }

@keyframes exfil-pulse { 0%,100% { opacity:1; } 50% { opacity:0.4; } }

/* ── Rig strip ───────────────────────────────────────────────────────────── */
.ph-rig-strip {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    background: rgba(255,105,180,0.04);
    border-bottom: 1px solid rgba(255,105,180,0.1);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.ph-rig-label { font-size: 9px; letter-spacing: 0.15em; color: rgba(255,105,180,0.5); flex-shrink: 0; margin-right: 2px; }

.ph-rig-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: 1px solid rgba(255,105,180,0.35);
    color: rgba(255,105,180,0.85);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.12em;
    padding: 3px 8px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.ph-rig-btn:hover:not(:disabled) { border-color: rgba(255,105,180,0.8); color: #FF69B4; background: rgba(255,105,180,0.08); }
.ph-rig-btn.rig-btn--level2 { border-color: rgba(255,150,50,0.4); color: rgba(255,150,50,0.85); }
.ph-rig-btn.rig-btn--level2:hover:not(:disabled) { border-color: rgba(255,150,50,0.9); color: #FF9632; background: rgba(255,150,50,0.08); }
.ph-rig-btn.rig-btn--used, .ph-rig-btn:disabled { border-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.2); cursor: not-allowed; text-decoration: line-through; }
.ph-rig-lvl { font-size: 7px; opacity: 0.6; letter-spacing: 0; }

/* ── Body row ────────────────────────────────────────────────────────────── */
.ph-body {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.ph-main-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ── Tension boot screen ─────────────────────────────────────────────────── */
.ph-boot {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 28px 20px;
    gap: 4px;
}

.ph-boot-line {
    font-size: 11px;
    letter-spacing: 0.1em;
    color: rgba(0,255,255,0.5);
}

.ph-boot-gap { height: 14px; }

.ph-boot-warn {
    font-size: 11px;
    letter-spacing: 0.08em;
    color: #FFB300;
}

.ph-boot-warn--indent { padding-left: 24px; }

.ph-boot-hint {
    font-size: 11px;
    letter-spacing: 0.08em;
    color: rgba(0,255,255,0.5);
    margin-top: 14px;
}

.ph-boot-cmd {
    color: #00FFFF;
    font-weight: bold;
}

.ph-boot-threat {
    font-size: 11px;
    letter-spacing: 0.08em;
    color: #FF4444;
    margin-top: 4px;
    animation: alert-pulse 0.8s ease-in-out infinite alternate;
}

.ph-boot-cursor {
    font-size: 14px;
    color: rgba(0,255,255,0.6);
    margin-top: 12px;
    animation: blink 1s step-start infinite;
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

.line--default  { color: rgba(0,255,255,0.7); }
.line--success  { color: #00ff88; }
.line--error    { color: #FF4444; }
.line--alert    { color: #FFB300; }
.line--clue     { color: #FF69B4; }
.line--result   { color: rgba(0,255,255,0.9); }
.line--progress { color: #00ff88; letter-spacing: 0.02em; }
.line--arp      { color: rgba(0,255,255,0.6); font-size: 10px; }

/* ── Input row ───────────────────────────────────────────────────────────── */
.ph-input-row {
    display: flex;
    align-items: center;
    padding: 8px 14px;
    gap: 8px;
    flex-shrink: 0;
    background: rgba(0,255,255,0.02);
}

.ph-prompt { font-size: 11px; color: rgba(0,255,255,0.5); letter-spacing: 0.08em; white-space: nowrap; flex-shrink: 0; }

.ph-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-family: inherit;
    font-size: 12px;
    color: #00FFFF;
    letter-spacing: 0.06em;
    caret-color: transparent;
}

.ph-cursor { font-size: 12px; color: #00FFFF; flex-shrink: 0; }
.cursor--blink { animation: blink 1s step-start infinite; }

@keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0; } }

.ph-locked { flex: 1; display: flex; align-items: center; }
.ph-lock-msg { font-size: 11px; color: #FF4444; letter-spacing: 0.1em; animation: alert-pulse 0.4s ease-in-out infinite alternate; }

/* ── Side panel ──────────────────────────────────────────────────────────── */
.ph-ref-panel {
    width: 270px;
    flex-shrink: 0;
    border-left: 1px solid rgba(0,255,255,0.1);
    background: rgba(0,255,255,0.015);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Case file section — natural height, no scroll, all rows always visible */
.ph-cf-section {
    flex-shrink: 0;
    padding: 10px 12px 8px;
}

/* Divider between case file and command ref */
.ph-panel-rule {
    height: 1px;
    background: rgba(0,255,255,0.1);
    flex-shrink: 0;
    margin: 0;
}

/* Command ref section — scrollable, takes remaining space */
.ph-cmd-ref-section {
    flex: 1;
    overflow-y: auto;
    padding: 10px 12px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,255,255,0.1) transparent;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.ph-ref-title  { font-size: 9px; letter-spacing: 0.2em; color: rgba(0,255,255,0.35); margin-bottom: 2px; }
.ph-ref-phase  { font-size: 9px; letter-spacing: 0.08em; color: #FFB300; margin-bottom: 8px; opacity: 0.75; }
.ph-ref-empty  { font-size: 9px; color: rgba(0,255,255,0.25); letter-spacing: 0.06em; margin-top: 8px; }
.ph-ref-entry  { display: flex; flex-direction: column; gap: 2px; padding: 4px 0; border-bottom: 1px solid rgba(0,255,255,0.05); }
.ph-ref-entry--commit .ph-ref-cmd { color: #00ff88; }
.ph-ref-cmd    { font-size: 10px; color: rgba(0,255,255,0.75); letter-spacing: 0.04em; word-break: break-all; }
.ph-ref-desc   { font-size: 9px; color: rgba(0,255,255,0.35); letter-spacing: 0.03em; line-height: 1.4; }
.ph-ref-note   { font-size: 8px; color: rgba(255,179,0,0.45); letter-spacing: 0.08em; margin-top: 8px; line-height: 1.5; }

/* ── Case file grid ──────────────────────────────────────────────────────── */
.ph-cf-stats {
    font-size: 9px;
    color: rgba(0,255,255,0.4);
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}

.ph-cf-clue { color: #FF69B4; }

.ph-cf-header {
    display: flex;
    font-size: 8px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.06em;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    padding-bottom: 3px;
    margin-bottom: 2px;
}

.ph-cf-row {
    display: flex;
    font-size: 9px;
    padding: 2px 0;
    border-bottom: 1px solid rgba(0,255,255,0.03);
    transition: opacity 0.2s;
}

.cf-row--flushed {
    opacity: 0.2;
    text-decoration: line-through;
}

.ph-cf-col { display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ph-cf-col--ip   { width: 108px; color: rgba(0,255,255,0.6); font-size: 8.5px; }
.ph-cf-col--ping { width: 52px; color: rgba(0,255,255,0.4); }
.ph-cf-col--hops { width: 30px; color: rgba(0,255,255,0.4); }
.ph-cf-col--arp  { flex: 1; color: rgba(0,255,255,0.4); }

.cf-val--live     { color: #00ff88 !important; }
.cf-val--degraded { color: #FFB300 !important; }
.cf-val--dead     { color: rgba(255,68,68,0.5) !important; }

/* ── Drag handle ─────────────────────────────────────────────────────────── */
.ph-drag-handle {
    width: 4px;
    flex-shrink: 0;
    cursor: col-resize;
    background: rgba(0,255,255,0.08);
    transition: background 0.15s;
    position: relative;
    z-index: 2;
}
.ph-drag-handle:hover {
    background: rgba(0,255,255,0.3);
}
.ph-drag-handle::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 1px;
    height: 32px;
    background: rgba(0,255,255,0.4);
    border-radius: 1px;
}

/* ── Slide transition ────────────────────────────────────────────────────── */
.ref-slide-enter-active, .ref-slide-leave-active {
    transition: opacity 0.2s ease;
    overflow: hidden;
}
.ref-slide-enter-from, .ref-slide-leave-to  { opacity: 0; }
.ref-slide-enter-to,   .ref-slide-leave-from { opacity: 1; }

/* Phase 2 ref renders directly in .ph-ref-panel — needs its own padding */
.ph-ref-panel > .ph-ref-title,
.ph-ref-panel > .ph-ref-phase,
.ph-ref-panel > .ph-ref-entry,
.ph-ref-panel > .ph-ref-note {
    padding-left: 12px;
    padding-right: 12px;
}
.ph-ref-panel > .ph-ref-title { padding-top: 10px; }
</style>
