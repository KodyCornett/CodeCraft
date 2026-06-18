<template>
    <div class="sn-wrap" :class="{ 'sn-wrap--compressed': props.compressed }">

        <div class="sn-header">
            <span class="sn-header-bracket">[</span>
            SYS_NOISE // LIVE_FEED
            <span class="sn-header-bracket">]</span>
            <span class="sn-header-dot" :class="{ 'sn-dot--active': running }"/>
        </div>

        <div ref="logEl" class="sn-log">
            <div
                v-for="line in lines"
                :key="line.id"
                class="sn-line"
                :class="line.cls"
            >
                <span class="sn-ts">{{ line.ts }}</span>
                <span class="sn-text">{{ line.text }}</span>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * When true the component shrinks to a fixed bottom strip,
     * leaving room for the ScanPanel above it.
     */
    compressed: { type: Boolean, default: false },
});

// ── Constants ──────────────────────────────────────────────────────────────────

const MAX_LINES  = 120;   // lines kept in DOM before trimming
const MIN_MS     = 280;   // fastest tick interval
const MAX_MS     = 720;   // slowest tick interval

// ─── Line templates ────────────────────────────────────────────────────────────
// Each entry: [weight, generator fn, css class]
// Higher weight = more frequent.

const TEMPLATES = [
    [6, () => `0x${hex(4)}: READ  → 0x${hex(4)}`,                    'sn-dim'  ],
    [6, () => `0x${hex(4)}: WRITE → 0x${hex(4)}`,                    'sn-dim'  ],
    [5, () => `KERN_${kernMod()}: ${okFail()}`,                       'sn-kern' ],
    [4, () => `MEM_DUMP: ${hex(4)}h–${hex(4)}h  [${randInt(1,64)}kb]`,'sn-dim'  ],
    [4, () => `NET_PKT:  ${randInt(64,1500)} bytes  seq=${hex(3)}`,   'sn-dim'  ],
    [3, () => `PROC_${randInt(100,999)}: ${procStatus()}`,            'sn-dim'  ],
    [3, () => `ICE_SCAN: node_${hex(2)} → ${scanResult()}`,          'sn-ice'  ],
    [3, () => `SYS_CLOCK: ${timestamp()}`,                            'sn-dim'  ],
    [2, () => `AUTH_TOKEN: ${hex(8)} [${okFail()}]`,                  'sn-dim'  ],
    [2, () => `SPLICE_FREQ: ${(Math.random()*999+100).toFixed(1)}MHz`,'sn-splice'],
    [2, () => `UPLINK: ${randInt(1,8)} hop${randInt(1,8)>1?'s':''} [OK]`, 'sn-dim'],
    [1, () => `!! WARN: trace_level elevation detected`,               'sn-warn' ],
    [1, () => `!! ICE_RESPONSE: inbound on 0x${hex(3)}`,              'sn-warn' ],
    [1, () => `-- SEGMENT_FAULT: recovered at ${hex(4)}h`,            'sn-err'  ],
];

// Build a weighted flat pool once
const POOL = TEMPLATES.flatMap(([w, fn, cls]) =>
    Array.from({ length: w }, () => ({ fn, cls }))
);

// ── Helpers ────────────────────────────────────────────────────────────────────

function randInt(min, max)  { return Math.floor(Math.random() * (max - min + 1)) + min; }
function hex(n)             { return Math.floor(Math.random() * (16 ** n)).toString(16).toUpperCase().padStart(n, '0'); }
function kernMod()          { return ['SCHED','MEM','FS','NET','IRQ','CRYPTO','SPLICE','PROC'][randInt(0,7)]; }
function okFail()           { return Math.random() > 0.08 ? 'OK' : 'ERR'; }
function procStatus()       { return ['RUNNING','SLEEPING','IDLE','WAITING','BLOCKED'][randInt(0,4)]; }
function scanResult()       { return Math.random() > 0.3 ? 'CLEAR' : 'ANOMALY'; }
function timestamp() {
    const d = new Date();
    return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}.${String(d.getMilliseconds()).padStart(3,'0')}`;
}

function pickLine() {
    const { fn, cls } = POOL[randInt(0, POOL.length - 1)];
    return { id: lineId++, ts: `[${timestamp()}]`, text: fn(), cls };
}

// ── State ──────────────────────────────────────────────────────────────────────

let lineId  = 0;
const lines   = ref([]);
const logEl   = ref(null);
const running = ref(false);
let   timer   = null;

// ── Scroll helper ──────────────────────────────────────────────────────────────

async function scrollToBottom() {
    await nextTick();
    if (logEl.value) {
        logEl.value.scrollTop = logEl.value.scrollHeight;
    }
}

// ── Tick ───────────────────────────────────────────────────────────────────────

function tick() {
    lines.value.push(pickLine());
    if (lines.value.length > MAX_LINES) {
        lines.value.splice(0, lines.value.length - MAX_LINES);
    }
    scrollToBottom();
    // Schedule next tick with random interval for organic feel
    timer = setTimeout(tick, randInt(MIN_MS, MAX_MS));
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    // Seed with initial lines so the log isn't empty on first render
    for (let i = 0; i < 30; i++) lines.value.push(pickLine());
    scrollToBottom();
    running.value = true;
    timer = setTimeout(tick, randInt(MIN_MS, MAX_MS));
});

onUnmounted(() => {
    clearTimeout(timer);
    running.value = false;
});
</script>

<style scoped>
/* ── Wrap ─────────────────────────────────────────────────────────────────── */

.sn-wrap {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
    /* Full height by default; compressed = fixed strip */
    flex: 1;
    min-height: 0;
    overflow: hidden;
    transition: flex 0.3s;
}

.sn-wrap--compressed {
    flex: 0 0 180px;
    border-top: 1px solid rgba(0,200,240,0.08);
    padding-top: 8px;
}

/* ── Header ───────────────────────────────────────────────────────────────── */

.sn-header {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.3);
    flex-shrink: 0;
}

.sn-header-bracket { color: rgba(0,200,240,0.15); }

.sn-header-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(0,200,240,0.15);
    margin-left: auto;
    flex-shrink: 0;
}

.sn-dot--active {
    background: #00ff9d;
    box-shadow: 0 0 6px rgba(0,255,100,0.6);
    animation: sn-pulse 1.2s ease infinite alternate;
}

/* ── Log container ────────────────────────────────────────────────────────── */

.sn-log {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1px;
    /* Hide scrollbar — the auto-scroll makes it invisible anyway */
    scrollbar-width: none;
}

.sn-log::-webkit-scrollbar { display: none; }

/* ── Log line ─────────────────────────────────────────────────────────────── */

.sn-line {
    display: flex;
    gap: 8px;
    font-size: 9px;
    letter-spacing: 0.04em;
    line-height: 1.6;
    white-space: nowrap;
    overflow: hidden;
    flex-shrink: 0;
}

.sn-ts {
    color: rgba(0,200,240,0.15);
    flex-shrink: 0;
}

.sn-text { overflow: hidden; text-overflow: ellipsis; }

/* Line colour variants */
.sn-dim    .sn-text { color: rgba(0,200,240,0.2);  }
.sn-kern   .sn-text { color: rgba(0,200,240,0.32); }
.sn-ice    .sn-text { color: rgba(0,200,240,0.28); }
.sn-splice .sn-text { color: rgba(0,255,160,0.25); }
.sn-warn   .sn-text { color: rgba(255,170,0,0.45); }
.sn-err    .sn-text { color: rgba(255,60,60,0.4);  }

/* ── Keyframes ────────────────────────────────────────────────────────────── */

@keyframes sn-pulse {
    from { opacity: 1;   }
    to   { opacity: 0.3; }
}
</style>
