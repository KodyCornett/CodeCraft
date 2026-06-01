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
                <span class="ph-topbar-phase" :class="phase === 3 ? 'phase--three' : phase === 2 ? 'phase--two' : 'phase--one'">
                    PHASE {{ phase }}: {{ phase === 1 ? 'RECON HUNT' : phase === 2 ? 'SYSTEM FINGERPRINT' : 'FILESYSTEM EXTRACTION' }}
                </span>
            </div>
            <div class="ph-rule" />

            <!-- ── Phase data zone (top, full width) ───────────────────────── -->

            <!-- Phase 1: 3×5 suspect grid -->
            <div v-if="phase === 1" class="ph-data-zone ph-data-zone--p1">
                <div v-if="!boardReady" class="ph-data-empty">
                    RUN <span class="ph-boot-cmd">netstat --active</span> TO BEGIN TRACE
                </div>
                <template v-else>
                    <div class="ph-suspect-meta">
                        ACTIVE: {{ activeSuspectCount }} / {{ suspects.length }}
                        <span v-if="octetClue" class="ph-cf-clue"> // OCTET LOCKED: {{ octetClue }}</span>
                    </div>
                    <div class="ph-suspect-grid">
                        <div
                            v-for="s in suspects"
                            :key="s.ip"
                            class="ph-suspect-card"
                            :class="{ 'suspect-card--flushed': s.flushed }"
                        >
                            <span class="psc-ip">{{ s.ip }}</span>
                            <span class="psc-sep">//</span>
                            <span class="psc-label">PNG</span>
                            <span class="psc-val" :class="pingClass(s)">{{ pingDisplay(s) }}</span>
                            <span class="psc-sep">|</span>
                            <span class="psc-label">HPS</span>
                            <span class="psc-val">{{ s.hops !== undefined ? s.hops : '???' }}</span>
                            <span class="psc-sep">|</span>
                            <span class="psc-label">ARP</span>
                            <span class="psc-val" :class="arpClass(s)">{{ arpDisplay(s) }}</span>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Phase 2: fingerprint strip -->
            <div v-else-if="phase === 2" class="ph-data-zone ph-data-zone--p2">
                <div v-if="!fingerprint || !fingerprint.ports" class="ph-data-empty">
                    RUN <span class="ph-boot-cmd">scan {{ targetIp || '&lt;ip&gt;' }}</span> TO INITIALISE FINGERPRINT
                </div>
                <template v-else>
                    <div class="ph-fp-strip">
                        <div class="ph-fp-strip-cred">
                            <span class="ph-fp-strip-label">OS</span>
                            <span class="ph-fp-strip-val" :class="fingerprint.os?.display === fingerprint.os?.full ? 'fp--complete' : ''">
                                {{ fingerprint.os?.display || (fingerprint.os?.tier1 + '-????-???') }}
                            </span>
                        </div>
                        <div class="ph-fp-strip-divider" />
                        <div class="ph-fp-strip-cred">
                            <span class="ph-fp-strip-label">HOST</span>
                            <span class="ph-fp-strip-val" :class="fingerprint.hostname?.display === fingerprint.hostname?.full ? 'fp--complete' : ''">
                                {{ fingerprint.hostname?.display || (fingerprint.hostname?.tier1 + '-????-????') }}
                            </span>
                        </div>
                        <div class="ph-fp-strip-divider" />
                        <div class="ph-fp-ports">
                            <div v-for="p in fingerprint.ports" :key="p.port"
                                class="ph-fp-port-card"
                                :class="{ 'fp-port--shattered': p.shattered }"
                            >
                                <span class="fpp-port">:{{ p.port }}</span>
                                <span class="fpp-svc">{{ p.service }}</span>
                                <span class="fpp-exp" :class="exposureClass(p.exposure)">{{ p.probed ? p.exposure : '???' }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Phase 3: filesystem trail -->
            <div v-else-if="phase === 3" class="ph-data-zone ph-data-zone--p3">
                <span class="ph-fs-label">PATH</span>
                <div class="ph-fs-trail">
                    <span
                        v-for="p in exploredPaths"
                        :key="p"
                        class="ph-fs-crumb"
                        :class="{ 'fs-crumb--current': p === currentPath }"
                    >{{ p }}</span>
                </div>
                <span class="ph-fs-hint">// WALLET HIDDEN IN FILESYSTEM — USE ls / cd / extract</span>
            </div>

            <div class="ph-rule ph-rule--light" />

            <!-- ── Lower body: terminal + right panel ──────────────────────── -->
            <div class="ph-body" @mousedown.self="refocusInput">

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
                    <div v-else class="ph-history" ref="historyEl" @mousedown="refocusInput">
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

                    <!-- Auth prompt — shown after successful breach -->
                    <div v-if="awaitingAuth && phase === 2" class="ph-auth-prompt">
                        <div class="ph-auth-title">[ SYSTEM LOGIN // ENTER CREDENTIALS ]</div>
                        <div class="ph-auth-row">
                            <span class="ph-auth-label">USERNAME &gt;</span>
                            <input v-model="authUser" class="ph-auth-input" type="text" spellcheck="false" autocomplete="off" placeholder="ENTER OS CREDENTIAL" @keydown.enter="onSubmitAuth" />
                        </div>
                        <div class="ph-auth-row">
                            <span class="ph-auth-label">PASSWORD &gt;</span>
                            <input v-model="authPass" class="ph-auth-input" type="text" spellcheck="false" autocomplete="off" placeholder="ENTER HOSTNAME CREDENTIAL" @keydown.enter="onSubmitAuth" />
                        </div>
                        <button class="ph-auth-submit" @click="onSubmitAuth">AUTHENTICATE</button>
                    </div>

                    <!-- Input row -->
                    <div class="ph-input-row" v-show="!awaitingAuth || phase !== 2">
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

                <!-- Right: CMD ref (always visible, phase-aware) + rig cmds -->
                <div class="ph-ref-panel">

                    <!-- CMD REF — Phase 1 -->
                    <div v-if="phase === 1" class="ph-cmd-ref-section">
                        <div class="ph-ref-title">CMD REF</div>
                        <div class="ph-ref-phase">// PHASE 1 — RECON</div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">ping &lt;ip&gt;</div>
                            <div class="ph-ref-desc">Probe a suspect. Fast response = active player.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">traceroute &lt;ip&gt;</div>
                            <div class="ph-ref-desc">Low hop count = same local network.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">arp --scan</div>
                            <div class="ph-ref-desc">Check last-active times. Target just arrived.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">whois &lt;ip&gt;</div>
                            <div class="ph-ref-desc">May reveal chassis if target has low OS.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">sniff --traffic</div>
                            <div class="ph-ref-desc">Intercepts one octet of target IP.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">flush &lt;ip&gt;</div>
                            <div class="ph-ref-desc">Remove a confirmed non-target.</div>
                        </div>
                        <div class="ph-ref-entry ph-ref-entry--commit">
                            <div class="ph-ref-cmd">inject &lt;ip&gt;</div>
                            <div class="ph-ref-desc">Commit your guess. Wrong = input locked.</div>
                        </div>
                    </div>

                    <!-- CMD REF — Phase 2 -->
                    <div v-else-if="phase === 2" class="ph-cmd-ref-section">
                        <div class="ph-ref-title">CMD REF</div>
                        <div class="ph-ref-phase">// PHASE 2 — FINGERPRINT</div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">scan &lt;ip&gt;</div>
                            <div class="ph-ref-desc">Discover open ports on target.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">probe &lt;port&gt;</div>
                            <div class="ph-ref-desc">Read banner — credential fragments hidden inside.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">validate &lt;string&gt;</div>
                            <div class="ph-ref-desc">Confirm a string is a valid credential fragment.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">decode &lt;port&gt;</div>
                            <div class="ph-ref-desc">Weaken MODERATE / LOW ports before exploit.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">exploit &lt;port&gt;</div>
                            <div class="ph-ref-desc">Shatter a port. CRITICAL/HIGH direct.</div>
                        </div>
                        <div class="ph-ref-entry ph-ref-entry--commit">
                            <div class="ph-ref-cmd">breach &lt;ip&gt; &lt;port&gt;</div>
                            <div class="ph-ref-desc">Fingerprint complete + port shattered = breach.</div>
                        </div>
                    </div>

                    <!-- CMD REF — Phase 3 -->
                    <div v-else-if="phase === 3" class="ph-cmd-ref-section">
                        <div class="ph-ref-title">CMD REF</div>
                        <div class="ph-ref-phase">// PHASE 3 — EXTRACTION</div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">ls</div>
                            <div class="ph-ref-desc">List contents of current directory.</div>
                        </div>
                        <div class="ph-ref-entry">
                            <div class="ph-ref-cmd">cd &lt;dir&gt;</div>
                            <div class="ph-ref-desc">Navigate into a directory. cd .. to go back.</div>
                        </div>
                        <div class="ph-ref-entry ph-ref-entry--commit">
                            <div class="ph-ref-cmd">extract</div>
                            <div class="ph-ref-desc">Steal the wallet. Wins the match.</div>
                        </div>
                        <div class="ph-ref-note">WALLET IS HIDDEN — SEARCH THE FILESYSTEM</div>
                    </div>

                    <!-- Rig commands stub -->
                    <div v-if="hackCommands && hackCommands.length" class="ph-rig-section">
                        <div class="ph-panel-rule" />
                        <div class="ph-ref-title ph-ref-title--rig">HACK CMDS</div>
                        <div class="ph-rig-grid">
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
                    </div>

                </div>

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
    // Phase 1
    suspects:            { type: Array,   default: () => [] },
    octetClue:           { type: String,  default: null },
    activeSuspectCount:  { type: Number,  default: 0 },
    boardReady:          { type: Boolean, default: false },
    // Phase 2
    fingerprint:         { type: Object,  default: null },
    portScanResult:      { type: Array,   default: () => [] },
    awaitingAuth:        { type: Boolean, default: false },
    // Phase 3
    currentPath:         { type: String,  default: '/' },
    directoryEntries:    { type: Array,   default: () => [] },
    exploredPaths:       { type: Array,   default: () => [] },
    // Legacy
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

const emit = defineEmits(['submit-command', 'submit-auth', 'match-complete', 'use-rig-command']);

const inputEl    = ref(null);
const historyEl  = ref(null);
const inputValue = ref('');
const authUser   = ref('');
const authPass   = ref('');

let historyNav  = [];
let historyNavI = -1;

function onSubmitAuth() {
    if (!authUser.value.trim() || !authPass.value.trim()) return;
    emit('submit-auth', authUser.value.trim(), authPass.value.trim());
    authUser.value = '';
    authPass.value = '';
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
    nextTick(() => inputEl.value?.focus());
}

function refocusInput() {
    if (!props.awaitingAuth && !props.isLocked && !props.isComplete) {
        inputEl.value?.focus();
    }
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
    refocusInput();
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

function exposureClass(exposure) {
    if (!exposure || exposure === '???') return '';
    if (exposure === 'CRITICAL') return 'cf-val--live';
    if (exposure === 'HIGH')     return 'cf-val--degraded';
    if (exposure === 'MODERATE') return 'cf-val--degraded';
    return 'cf-val--dead';
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
    width: min(1280px, 99vw);
    height: min(860px, 97vh);
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
.phase--three { color: #00ff88; }
.ph-role-val { color: #FF69B4; }

.ph-rule        { height: 1px; background: rgba(0,255,255,0.18); flex-shrink: 0; }
.ph-rule--light { height: 1px; background: rgba(0,255,255,0.07); flex-shrink: 0; }

/* ── Phase data zone (top strip, full width) ─────────────────────────────── */
.ph-data-zone {
    flex-shrink: 0;
    padding: 8px 14px;
    background: rgba(0,255,255,0.02);
    min-height: 68px;
}

.ph-data-empty {
    font-size: 10px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.08em;
    padding: 8px 0;
}

/* Phase 1 — 3×5 suspect grid */
.ph-suspect-meta {
    font-size: 9px;
    color: rgba(0,255,255,0.35);
    letter-spacing: 0.06em;
    margin-bottom: 6px;
}

.ph-suspect-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 4px 8px;
}

.ph-suspect-card {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 9px;
    padding: 3px 6px;
    border: 1px solid rgba(0,255,255,0.07);
    background: rgba(0,255,255,0.02);
    white-space: nowrap;
    overflow: hidden;
    transition: opacity 0.2s;
}

.suspect-card--flushed {
    opacity: 0.2;
    text-decoration: line-through;
}

.psc-ip    { color: rgba(0,255,255,0.65); font-size: 8.5px; flex-shrink: 0; min-width: 94px; }
.psc-sep   { color: rgba(0,255,255,0.15); flex-shrink: 0; }
.psc-label { color: rgba(0,255,255,0.25); font-size: 7.5px; letter-spacing: 0.06em; flex-shrink: 0; }
.psc-val   { color: rgba(0,255,255,0.5);  font-size: 8.5px; flex-shrink: 0; min-width: 42px; }

/* Phase 2 — fingerprint horizontal strip */
.ph-fp-strip {
    display: flex;
    align-items: flex-start;
    gap: 0;
    height: 100%;
}

.ph-fp-strip-cred {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding-right: 16px;
    flex-shrink: 0;
}

.ph-fp-strip-label {
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.12em;
}

.ph-fp-strip-val {
    font-size: 10px;
    color: rgba(0,255,255,0.6);
    letter-spacing: 0.05em;
    word-break: break-all;
}

.ph-fp-strip-divider {
    width: 1px;
    background: rgba(0,255,255,0.1);
    align-self: stretch;
    margin: 0 16px 0 0;
    flex-shrink: 0;
}

.ph-fp-ports {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-content: flex-start;
}

.ph-fp-port-card {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 3px 8px;
    border: 1px solid rgba(0,255,255,0.1);
    background: rgba(0,255,255,0.02);
    min-width: 74px;
}

.fp-port--shattered { opacity: 0.25; text-decoration: line-through; }

.fpp-port { font-size: 10px; color: rgba(0,255,255,0.7); letter-spacing: 0.04em; }
.fpp-svc  { font-size: 8px;  color: rgba(0,255,255,0.4); letter-spacing: 0.04em; }
.fpp-exp  { font-size: 8px;  letter-spacing: 0.06em; margin-top: 1px; }

/* Phase 3 — filesystem breadcrumb trail */
.ph-data-zone--p3 {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    min-height: 44px;
    padding: 6px 14px;
}

.ph-fs-label {
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.12em;
    flex-shrink: 0;
}

.ph-fs-trail {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    flex-wrap: wrap;
}

.ph-fs-crumb {
    font-size: 10px;
    color: rgba(0,255,255,0.35);
    letter-spacing: 0.04em;
    padding: 1px 6px;
    border: 1px solid rgba(0,255,255,0.08);
}

.fs-crumb--current {
    color: #00ff88;
    border-color: rgba(0,255,136,0.3);
    background: rgba(0,255,136,0.04);
}

.ph-fs-hint {
    font-size: 8px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.05em;
    flex-shrink: 0;
}

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

/* ── Right panel (CMD ref + rig cmds) ────────────────────────────────────── */
.ph-ref-panel {
    width: 220px;
    flex-shrink: 0;
    border-left: 1px solid rgba(0,255,255,0.1);
    background: rgba(0,255,255,0.015);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Divider */
.ph-panel-rule {
    height: 1px;
    background: rgba(0,255,255,0.1);
    flex-shrink: 0;
    margin: 0;
}

/* Command ref — scrollable, fills top of right panel */
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

.ph-ref-title         { font-size: 9px; letter-spacing: 0.2em; color: rgba(0,255,255,0.35); margin-bottom: 2px; }
.ph-ref-title--rig    { color: rgba(255,105,180,0.4); padding: 8px 12px 4px; }
.ph-ref-phase         { font-size: 9px; letter-spacing: 0.08em; color: #FFB300; margin-bottom: 8px; opacity: 0.75; }
.ph-ref-empty         { font-size: 9px; color: rgba(0,255,255,0.25); letter-spacing: 0.06em; margin-top: 8px; }
.ph-ref-entry         { display: flex; flex-direction: column; gap: 2px; padding: 4px 0; border-bottom: 1px solid rgba(0,255,255,0.05); }
.ph-ref-entry--commit .ph-ref-cmd { color: #00ff88; }
.ph-ref-cmd           { font-size: 10px; color: rgba(0,255,255,0.75); letter-spacing: 0.04em; word-break: break-all; }
.ph-ref-desc          { font-size: 9px; color: rgba(0,255,255,0.35); letter-spacing: 0.03em; line-height: 1.4; }
.ph-ref-note          { font-size: 8px; color: rgba(255,179,0,0.45); letter-spacing: 0.08em; margin-top: 8px; line-height: 1.5; }

/* Rig commands — stub section below CMD ref */
.ph-rig-section { flex-shrink: 0; }

.ph-rig-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 6px 12px 10px;
}

.ph-rig-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: 1px solid rgba(255,105,180,0.35);
    color: rgba(255,105,180,0.85);
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 3px 7px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s, background 0.15s;
    white-space: nowrap;
}
.ph-rig-btn:hover:not(:disabled) { border-color: rgba(255,105,180,0.8); color: #FF69B4; background: rgba(255,105,180,0.08); }
.ph-rig-btn.rig-btn--level2 { border-color: rgba(255,150,50,0.4); color: rgba(255,150,50,0.85); }
.ph-rig-btn.rig-btn--level2:hover:not(:disabled) { border-color: rgba(255,150,50,0.9); color: #FF9632; background: rgba(255,150,50,0.08); }
.ph-rig-btn.rig-btn--used, .ph-rig-btn:disabled { border-color: rgba(255,255,255,0.08); color: rgba(255,255,255,0.2); cursor: not-allowed; text-decoration: line-through; }
.ph-rig-lvl { font-size: 7px; opacity: 0.6; letter-spacing: 0; }

/* ── Shared value colour helpers ─────────────────────────────────────────── */
.cf-val--live     { color: #00ff88 !important; }
.cf-val--degraded { color: #FFB300 !important; }
.cf-val--dead     { color: rgba(255,68,68,0.5) !important; }
.ph-cf-clue       { color: #FF69B4; }

/* ── Auth prompt ─────────────────────────────────────────────────────────── */
.ph-auth-prompt {
    flex-shrink: 0;
    padding: 12px 14px;
    background: rgba(0,255,136,0.04);
    border-top: 1px solid rgba(0,255,136,0.15);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.ph-auth-title {
    font-size: 10px;
    letter-spacing: 0.12em;
    color: #00ff88;
    margin-bottom: 4px;
}

.ph-auth-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ph-auth-label {
    font-size: 11px;
    color: rgba(0,255,255,0.5);
    letter-spacing: 0.08em;
    white-space: nowrap;
    width: 110px;
    flex-shrink: 0;
}

.ph-auth-input {
    flex: 1;
    background: rgba(0,255,136,0.05);
    border: 1px solid rgba(0,255,136,0.25);
    outline: none;
    font-family: inherit;
    font-size: 11px;
    color: #00ff88;
    letter-spacing: 0.06em;
    padding: 4px 8px;
}

.ph-auth-submit {
    align-self: flex-start;
    background: transparent;
    border: 1px solid rgba(0,255,136,0.4);
    color: rgba(0,255,136,0.8);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.15em;
    padding: 5px 16px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.ph-auth-submit:hover {
    border-color: #00ff88;
    color: #00ff88;
}

/* ── fp--complete (used in Phase 2 top strip) ────────────────────────────── */
.fp--complete { color: #00ff88 !important; }
</style>
