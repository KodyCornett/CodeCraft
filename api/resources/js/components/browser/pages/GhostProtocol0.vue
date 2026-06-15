<template>
    <div class="gp0-root" @click="onSkip">

        <!-- ── BOOT PHASE ──────────────────────────────────────────────────────── -->
        <div v-if="phase === 'booting'" class="gp0-terminal">
            <div class="gp0-scanline" />
            <div class="gp0-term-inner">
                <div
                    v-for="(line, i) in visibleLines"
                    :key="i"
                    class="gp0-term-line"
                    :class="line.cls"
                >
                    <span v-if="line.prefix" class="gp0-term-prefix">{{ line.prefix }}</span>
                    <span class="gp0-term-text">{{ line.text }}</span>
                    <span v-if="i === visibleLines.length - 1 && !bootDone" class="gp0-cursor">▌</span>
                </div>

                <div v-if="visibleLines.length > 3" class="gp0-skip-hint">
                    click anywhere to skip
                </div>
            </div>
        </div>

        <!-- ── READY PHASE ─────────────────────────────────────────────────────── -->
        <Transition name="gp0-reveal">
            <div v-if="phase === 'ready'" class="gp0-page" @click.stop>

                <!-- Header -->
                <header class="gp0-header">
                    <div class="gp0-header-left">
                        <span class="gp0-mission-tag">MISSION // GHOST_PROTOCOL_0</span>
                        <span class="gp0-header-sub">New runner orientation — complete objectives to earn wallet creds</span>
                    </div>
                    <div class="gp0-header-right">
                        <span v-if="allComplete" class="gp0-status gp0-status--done">◎ COMPLETE</span>
                        <span v-else class="gp0-status">◉ ACTIVE</span>
                        <button class="gp0-replay-btn" @click.stop="replayBoot" title="Replay intro">↺</button>
                    </div>
                </header>

                <div class="gp0-content">

                    <!-- ── ALL COMPLETE ─────────────────────────────────────────── -->
                    <div v-if="allComplete" class="gp0-complete-block">
                        <div class="gp0-complete-icon">◎</div>
                        <div class="gp0-complete-title">ORIENTATION COMPLETE</div>
                        <div class="gp0-complete-body">
                            You're operational. Wallet funded. Stay dark, stay profitable.
                        </div>
                        <div class="gp0-complete-hint">
                            Access <span class="hl-cyan">splice://sys.local/guide/gridbreach</span> and
                            <span class="hl-cyan">splice://sys.local/manual</span> any time for reference.
                        </div>
                    </div>

                    <!-- ── ACTIVE QUEST BLOCK ───────────────────────────────────── -->
                    <div v-else-if="activeQuest" class="gp0-active-block">
                        <div class="gp0-active-tag">▸ ACTIVE OBJECTIVE</div>
                        <div class="gp0-active-label">{{ activeQuest.label }}</div>
                        <div class="gp0-active-sub">{{ activeQuest.subtitle }}</div>

                        <div class="gp0-step-list">
                            <div
                                v-for="(step, i) in activeQuest.steps"
                                :key="step.id"
                                class="gp0-step-row"
                                :class="{ 'gp0-step--done': step.done, 'gp0-step--waiting': !step.done }"
                            >
                                <span class="gp0-step-num">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="gp0-step-check">{{ step.done ? '✓' : '⋯' }}</span>
                                <span class="gp0-step-label">{{ step.label }}</span>
                                <span v-if="!step.done" class="gp0-step-waiting">WAITING</span>
                            </div>
                        </div>

                        <div v-if="!activeQuest.allDone" class="gp0-action-hint">
                            {{ activeQuest.hint ?? 'Close SPLICE and complete the step above — this page updates automatically.' }}
                        </div>

                        <div class="gp0-reward-row">
                            <span class="gp0-reward-label">REWARD</span>
                            <span class="gp0-reward-val">+{{ activeQuest.reward }}₡ → WALLET</span>
                            <span class="gp0-reward-note">safe — cannot be stolen</span>
                        </div>
                    </div>

                    <!-- ── QUEST LOG ─────────────────────────────────────────────── -->
                    <div class="gp0-log">
                        <div class="gp0-log-header">MISSION LOG</div>
                        <div
                            v-for="quest in quests"
                            :key="quest.id"
                            class="gp0-log-row"
                            :class="{
                                'gp0-log--complete': quest.allDone,
                                'gp0-log--active':   !quest.locked && !quest.allDone,
                                'gp0-log--locked':    quest.locked,
                            }"
                        >
                            <span class="gp0-log-icon">
                                {{ quest.allDone ? '✓' : quest.locked ? '⬡' : '▸' }}
                            </span>
                            <span class="gp0-log-name">{{ quest.label }}</span>
                            <span class="gp0-log-steps">
                                {{ quest.steps.filter(s => s.done).length }}/{{ quest.steps.length }}
                            </span>
                            <span class="gp0-log-reward">
                                <template v-if="quest.rewarded">+{{ quest.reward }}₡ CREDITED</template>
                                <template v-else-if="quest.locked">LOCKED</template>
                                <template v-else>+{{ quest.reward }}₡</template>
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </Transition>

    </div>
</template>

<script setup>
import { ref, inject, onMounted, onUnmounted } from 'vue';

defineProps({ url: { type: String, default: '' } });

// ── Tutorial state — provided by Game.vue ──────────────────────────────────────
const tutorial = inject('tutorial', null);

const quests      = tutorial?.quests      ?? ref([]);
const activeQuest = tutorial?.activeQuest ?? ref(null);
const allComplete = tutorial?.allComplete ?? ref(false);

// Clear the NavBar badge whenever this page is open
onMounted(() => tutorial?.clearBadge());

// ── Boot sequence lines ────────────────────────────────────────────────────────
const BOOT_LINES = [
    { text: '',                                                           cls: '' },
    { prefix: '>', text: 'INITIALISING SPLICE TUNNEL…',                  cls: 'gp0-line--dim',   delay: 0    },
    { prefix: '>', text: 'ROUTING VIA NODE CLUSTER NW-14…',              cls: 'gp0-line--dim',   delay: 320  },
    { prefix: '>', text: 'LAYER 7 ENCRYPTION ACTIVE',                    cls: 'gp0-line--dim',   delay: 640  },
    { text: '',                                                           cls: '',                delay: 900  },
    { prefix: '>', text: 'AUTHENTICATING RUNNER CREDENTIALS…',           cls: 'gp0-line--cyan',  delay: 1050 },
    { prefix: '>', text: 'IDENTITY VERIFIED  //  ACCESS GRANTED',        cls: 'gp0-line--green', delay: 1500 },
    { text: '',                                                           cls: '',                delay: 1700 },
    { prefix: '>', text: 'FETCHING SECURE TRANSMISSION…',                cls: 'gp0-line--dim',   delay: 1850 },
    { prefix: '>', text: 'DECRYPTING  ████████████████  DONE',           cls: 'gp0-line--dim',   delay: 2300 },
    { text: '',                                                           cls: '',                delay: 2600 },
    { text: '══════════════════════════════════════════════', cls: 'gp0-line--sep',    delay: 2750 },
    { text: 'INCOMING TRANSMISSION',                          cls: 'gp0-line--header', delay: 2900 },
    { text: 'SOURCE  : UNKNOWN // ENCRYPTED',                 cls: 'gp0-line--meta',   delay: 3050 },
    { text: 'SUBJECT : GHOST_PROTOCOL_0 — NEW RUNNER ORIENTATION', cls: 'gp0-line--meta', delay: 3200 },
    { text: '══════════════════════════════════════════════', cls: 'gp0-line--sep',    delay: 3400 },
    { text: '',                                                           cls: '',                delay: 3550 },
    { text: 'You are connected to the grid.',                             cls: 'gp0-line--msg',   delay: 3700 },
    { text: 'Your rig is online. Your uplink is live.',                   cls: 'gp0-line--msg',   delay: 4000 },
    { text: '',                                                           cls: '',                delay: 4200 },
    { text: 'The city\'s node network is open — hundreds of data',       cls: 'gp0-line--msg',   delay: 4350 },
    { text: 'caches waiting to be breached. ICE stands between',         cls: 'gp0-line--msg',   delay: 4550 },
    { text: 'you and every one of them. Your job is to stay ahead.',     cls: 'gp0-line--msg',   delay: 4750 },
    { text: '',                                                           cls: '',                delay: 4950 },
    { text: 'Complete the protocol before running anything hot.',         cls: 'gp0-line--amber', delay: 5100 },
    { text: 'Runners who skip orientation don\'t last long.',            cls: 'gp0-line--amber', delay: 5300 },
    { text: '',                                                           cls: '',                delay: 5500 },
    { text: '// END TRANSMISSION',                                        cls: 'gp0-line--dim',   delay: 5650 },
    { text: '',                                                           cls: '',                delay: 5800 },
    { prefix: '>', text: 'LOADING GHOST_PROTOCOL_0…',                    cls: 'gp0-line--cyan',  delay: 5950 },
];

const TOTAL_DURATION = 6600;

// ── State ──────────────────────────────────────────────────────────────────────
const phase        = ref('booting');
const visibleLines = ref([]);
const bootDone     = ref(false);

// ── Boot sequence engine ───────────────────────────────────────────────────────
let _timers = [];

function clearTimers() {
    _timers.forEach(t => clearTimeout(t));
    _timers = [];
}

function runBoot() {
    visibleLines.value = [];
    bootDone.value     = false;
    phase.value        = 'booting';

    BOOT_LINES.forEach((line, i) => {
        const t = setTimeout(() => {
            visibleLines.value.push(line);
            if (i === BOOT_LINES.length - 1) bootDone.value = true;
        }, line.delay ?? i * 120);
        _timers.push(t);
    });

    const done = setTimeout(() => { phase.value = 'ready'; }, TOTAL_DURATION);
    _timers.push(done);
}

function onSkip() {
    if (phase.value !== 'booting') return;
    clearTimers();
    visibleLines.value = [...BOOT_LINES];
    bootDone.value     = true;
    phase.value        = 'ready';
}

function replayBoot() {
    clearTimers();
    runBoot();
}

onMounted(runBoot);
onUnmounted(clearTimers);
</script>

<style scoped>
/* ── Root ────────────────────────────────────────────────────────────────────── */
.gp0-root {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #02020a;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    cursor: default;
}

/* ── Terminal phase ──────────────────────────────────────────────────────────── */
.gp0-terminal {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative;
}

.gp0-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0px,
        transparent 3px,
        rgba(0, 0, 0, 0.08) 3px,
        rgba(0, 0, 0, 0.08) 4px
    );
    pointer-events: none;
    z-index: 1;
}

.gp0-term-inner {
    flex: 1;
    overflow-y: auto;
    padding: 28px 32px 80px;
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}
.gp0-term-inner::-webkit-scrollbar { width: 0; }

.gp0-term-line {
    font-size: 11px;
    letter-spacing: 0.06em;
    line-height: 1.9;
    color: rgba(0, 255, 200, 0.55);
    display: flex;
    align-items: baseline;
    gap: 8px;
    min-height: 1.9em;
}

.gp0-term-prefix { color: rgba(0, 255, 136, 0.4); flex-shrink: 0; }

.gp0-line--dim    { color: rgba(0, 255, 200, 0.2); }
.gp0-line--cyan   { color: rgba(0, 255, 255, 0.7); }
.gp0-line--green  { color: #00FF88; text-shadow: 0 0 10px rgba(0,255,136,0.4); }
.gp0-line--amber  { color: rgba(255, 179, 0, 0.75); }
.gp0-line--msg    { color: rgba(255, 255, 255, 0.55); }
.gp0-line--sep    { color: rgba(0, 255, 255, 0.12); letter-spacing: 0; font-size: 10px; }
.gp0-line--header { color: #00FFFF; font-size: 12px; letter-spacing: 0.12em; text-shadow: 0 0 12px rgba(0,255,255,0.4); }
.gp0-line--meta   { color: rgba(0, 255, 255, 0.3); font-size: 9px; letter-spacing: 0.08em; }

.gp0-cursor {
    color: #00FFFF;
    animation: blink 0.8s step-start infinite;
    margin-left: 2px;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

.gp0-skip-hint {
    position: fixed;
    bottom: 56px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 8px;
    color: rgba(0, 255, 255, 0.18);
    letter-spacing: 0.14em;
    pointer-events: none;
    animation: hint-pulse 3s ease-in-out infinite;
}
@keyframes hint-pulse { 0%,100%{opacity:0.6} 50%{opacity:0.2} }

/* ── Reveal transition ───────────────────────────────────────────────────────── */
.gp0-reveal-enter-active { transition: opacity 0.5s ease, transform 0.5s ease; }
.gp0-reveal-enter-from   { opacity: 0; transform: translateY(8px); }

/* ── Ready phase ─────────────────────────────────────────────────────────────── */
.gp0-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    overflow: hidden;
}

.gp0-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 24px 10px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
    gap: 16px;
}
.gp0-header-left  { display: flex; flex-direction: column; gap: 3px; }
.gp0-header-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.gp0-mission-tag  { font-size: 12px; color: #00FFFF; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,255,0.3); }
.gp0-header-sub   { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.07em; }

.gp0-status {
    font-size: 8px;
    color: #00FF88;
    letter-spacing: 0.14em;
    text-shadow: 0 0 8px rgba(0,255,136,0.6);
    animation: status-pulse 3s ease-in-out infinite;
}
.gp0-status--done {
    color: rgba(0, 255, 136, 0.4);
    animation: none;
}
@keyframes status-pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

.gp0-replay-btn {
    background: transparent;
    border: 1px solid rgba(0,255,255,0.12);
    color: rgba(0,255,255,0.3);
    font-family: inherit;
    font-size: 10px;
    width: 22px;
    height: 22px;
    cursor: pointer;
    transition: all 0.12s;
    padding: 0;
    line-height: 1;
}
.gp0-replay-btn:hover { color: #00FFFF; border-color: rgba(0,255,255,0.5); background: rgba(0,255,255,0.05); }

/* ── Content scroll area ─────────────────────────────────────────────────────── */
.gp0-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
    display: flex;
    flex-direction: column;
    gap: 28px;
}
.gp0-content::-webkit-scrollbar       { width: 3px; }
.gp0-content::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Complete state ──────────────────────────────────────────────────────────── */
.gp0-complete-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 40px 20px;
    text-align: center;
}
.gp0-complete-icon  { font-size: 32px; color: #00FF88; opacity: 0.6; }
.gp0-complete-title { font-size: 14px; color: #00FF88; letter-spacing: 0.14em; }
.gp0-complete-body  { font-size: 10px; color: rgba(255,255,255,0.4); letter-spacing: 0.05em; line-height: 1.8; max-width: 400px; }
.gp0-complete-hint  { font-size: 9px; color: rgba(0,255,255,0.3); letter-spacing: 0.04em; line-height: 1.8; max-width: 440px; margin-top: 8px; }

/* ── Active quest block ──────────────────────────────────────────────────────── */
.gp0-active-block {
    border: 1px solid rgba(0, 255, 255, 0.12);
    background: rgba(0, 255, 255, 0.02);
    padding: 18px 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.gp0-active-tag   { font-size: 8px; color: #00FF88; letter-spacing: 0.18em; }
.gp0-active-label { font-size: 13px; color: #00FFFF; letter-spacing: 0.1em; }
.gp0-active-sub   { font-size: 9px; color: rgba(255,255,255,0.35); letter-spacing: 0.05em; margin-bottom: 4px; }

/* ── Step list ───────────────────────────────────────────────────────────────── */
.gp0-step-list { display: flex; flex-direction: column; gap: 2px; }

.gp0-step-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid rgba(0,255,255,0.05);
    background: rgba(0,0,0,0.2);
}
.gp0-step--done    { border-color: rgba(0,255,136,0.12); background: rgba(0,255,136,0.03); }
.gp0-step--waiting { border-color: rgba(0,255,255,0.08); }

.gp0-step-num     { font-size: 8px; color: rgba(0,255,255,0.2); flex-shrink: 0; width: 20px; }
.gp0-step-check   { font-size: 10px; flex-shrink: 0; width: 14px; }
.gp0-step--done   .gp0-step-check { color: #00FF88; }
.gp0-step--waiting .gp0-step-check { color: rgba(0,255,255,0.3); animation: waiting-pulse 2s ease-in-out infinite; }
@keyframes waiting-pulse { 0%,100%{opacity:0.6} 50%{opacity:0.2} }

.gp0-step-label {
    font-size: 9px;
    letter-spacing: 0.05em;
    line-height: 1.5;
    flex: 1;
}
.gp0-step--done    .gp0-step-label { color: rgba(0,255,136,0.5); }
.gp0-step--waiting .gp0-step-label { color: rgba(255,255,255,0.6); }

.gp0-step-waiting {
    font-size: 7px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.14em;
    flex-shrink: 0;
    animation: waiting-pulse 2s ease-in-out infinite;
}

/* ── Action hint ─────────────────────────────────────────────────────────────── */
.gp0-action-hint {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.5);
    letter-spacing: 0.06em;
    line-height: 1.7;
    border-left: 2px solid rgba(255,179,0,0.2);
    padding-left: 10px;
}

/* ── Reward row ──────────────────────────────────────────────────────────────── */
.gp0-reward-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 4px;
    border-top: 1px solid rgba(0,255,255,0.06);
}
.gp0-reward-label { font-size: 8px; color: rgba(0,255,255,0.3); letter-spacing: 0.14em; }
.gp0-reward-val   { font-size: 10px; color: #00FF88; letter-spacing: 0.08em; }
.gp0-reward-note  { font-size: 8px; color: rgba(0,255,136,0.3); letter-spacing: 0.06em; }

/* ── Quest log ───────────────────────────────────────────────────────────────── */
.gp0-log        { display: flex; flex-direction: column; }
.gp0-log-header { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.2em; margin-bottom: 8px; }

.gp0-log-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    border-bottom: 1px solid rgba(0,255,255,0.04);
}
.gp0-log--complete { opacity: 0.5; }
.gp0-log--active   { background: rgba(0,255,255,0.02); }
.gp0-log--locked   { opacity: 0.25; }

.gp0-log-icon   { font-size: 9px; color: rgba(0,255,255,0.4); flex-shrink: 0; width: 12px; }
.gp0-log--complete .gp0-log-icon { color: #00FF88; }
.gp0-log--active   .gp0-log-icon { color: #00FFFF; }

.gp0-log-name   { font-size: 9px; color: rgba(255,255,255,0.5); letter-spacing: 0.06em; flex: 1; }
.gp0-log-steps  { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.06em; flex-shrink: 0; }
.gp0-log-reward {
    font-size: 8px;
    letter-spacing: 0.06em;
    flex-shrink: 0;
    width: 110px;
    text-align: right;
}
.gp0-log--complete .gp0-log-reward { color: rgba(0,255,136,0.45); }
.gp0-log--active   .gp0-log-reward { color: rgba(0,255,136,0.7); }
.gp0-log--locked   .gp0-log-reward { color: rgba(255,255,255,0.15); }

/* ── Colour helpers ──────────────────────────────────────────────────────────── */
.hl-cyan  { color: #00FFFF; }
.hl-green { color: #00FF88; }
</style>
