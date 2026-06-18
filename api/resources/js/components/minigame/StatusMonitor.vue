<template>
    <div class="sm-panel">

        <!-- ── Panel header ──────────────────────────────────────────────── -->
        <div class="sm-header">
            <span class="sm-header-bracket">[</span>
            STATUS_MONITOR
            <span class="sm-header-bracket">]</span>
            <span class="sm-header-id">// CB_{{ sessionId }}</span>
        </div>

        <!-- ── System Stability ──────────────────────────────────────────── -->
        <div class="sm-metric">
            <div class="sm-metric-row">
                <span class="sm-metric-label">SYS.STABILITY</span>
                <span class="sm-metric-val" :class="stabilityValClass">
                    {{ stabilityPct }}<span class="sm-unit">%</span>
                </span>
            </div>
            <div class="sm-bar-track">
                <div
                    class="sm-bar-fill transition-all duration-300 ease-out"
                    :class="stabilityBarClass"
                    :style="{ width: stabilityPct + '%' }"
                />
            </div>
            <div class="sm-metric-sub" :class="stabilityValClass">
                {{ stabilityStatus }}
            </div>
        </div>

        <!-- ── Active Trace ──────────────────────────────────────────────── -->
        <div class="sm-metric">
            <div class="sm-metric-row">
                <span class="sm-metric-label">ACTIVE_TRACE</span>
                <span class="sm-metric-val" :class="traceValClass">
                    {{ tracePct }}<span class="sm-unit">%</span>
                </span>
            </div>
            <div class="sm-bar-track">
                <div
                    class="sm-bar-fill sm-bar--trace transition-all duration-300 ease-out"
                    :style="{ width: tracePct + '%' }"
                />
            </div>
            <div class="sm-metric-sub" :class="traceValClass">
                {{ traceStatus }}
            </div>
        </div>

        <!-- ── Fixer Reputation ──────────────────────────────────────────── -->
        <div class="sm-metric">
            <div class="sm-metric-row">
                <span class="sm-metric-label">FIXER_REP</span>
                <span class="sm-metric-val sm-val--rep">
                    {{ props.reputation }}<span class="sm-unit">/10</span>
                </span>
            </div>
            <div class="sm-pips">
                <span
                    v-for="n in 10"
                    :key="n"
                    class="sm-pip"
                    :class="n <= props.reputation ? 'sm-pip--filled' : 'sm-pip--empty'"
                />
            </div>
            <div class="sm-metric-sub">{{ repLabel }}</div>
        </div>

        <!-- ── Fragments secured ─────────────────────────────────────────── -->
        <div class="sm-metric" v-if="props.solvedFrags.length">
            <div class="sm-metric-row">
                <span class="sm-metric-label">FRAGS_SECURED</span>
                <span class="sm-metric-val">
                    {{ solvedCount }}<span class="sm-unit">/{{ props.solvedFrags.length }}</span>
                </span>
            </div>
            <div class="sm-frag-pips">
                <span
                    v-for="(solved, i) in props.solvedFrags"
                    :key="i"
                    class="sm-frag-pip"
                    :class="solved ? 'sm-frag-pip--secured' : 'sm-frag-pip--open'"
                >◉</span>
            </div>
        </div>

        <!-- ── Divider ───────────────────────────────────────────────────── -->
        <div class="sm-divider"/>

        <!-- ── System log ────────────────────────────────────────────────── -->
        <div class="sm-log">
            <div class="sm-log-header">SYSTEM_LOG</div>
            <div
                v-for="(line, i) in statusLines"
                :key="i"
                class="sm-log-line"
                :class="line.cls"
            >
                <span class="sm-log-bracket">[</span>{{ line.key }}<span class="sm-log-bracket">]</span>
                <span class="sm-log-val">{{ line.val }}</span>
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps({
    /** 0.0 – 1.0 */
    stability:  { type: Number, default: 1.0 },
    /** 0.0 – 1.0 */
    traceLevel: { type: Number, default: 0.0 },
    /** 1 – 10 integer */
    reputation: { type: Number, default: 5 },
    /** Array of booleans, one per fragment */
    solvedFrags: { type: Array, default: () => [] },
});

// ── Stable session ID (decorative) ────────────────────────────────────────────

const sessionId = Math.floor(Math.random() * 0xFFFF).toString(16).toUpperCase().padStart(4, '0');

// ── Derived percentages ────────────────────────────────────────────────────────

const stabilityPct = computed(() => Math.round(props.stability  * 100));
const tracePct     = computed(() => Math.round(props.traceLevel * 100));
const solvedCount  = computed(() => props.solvedFrags.filter(Boolean).length);

// ── Stability colour — green > 60 · yellow > 30 · red ≤ 30 ───────────────────

const stabilityBarClass = computed(() => {
    if (stabilityPct.value > 60) return 'sm-bar--stab-ok';
    if (stabilityPct.value > 30) return 'sm-bar--stab-warn';
    return 'sm-bar--stab-crit';
});

const stabilityValClass = computed(() => {
    if (stabilityPct.value > 60) return 'sm-val--ok';
    if (stabilityPct.value > 30) return 'sm-val--warn';
    return 'sm-val--crit';
});

const stabilityStatus = computed(() => {
    if (stabilityPct.value > 60) return 'NOMINAL';
    if (stabilityPct.value > 30) return 'DEGRADED — monitor closely';
    return 'CRITICAL — system collapse imminent';
});

// ── Trace colour ───────────────────────────────────────────────────────────────

const traceValClass = computed(() => {
    if (tracePct.value >= 85) return 'sm-val--crit';
    if (tracePct.value >= 40) return 'sm-val--trace';
    return 'sm-val--ok';
});

const traceStatus = computed(() => {
    if (tracePct.value < 30)  return 'UNDETECTED';
    if (tracePct.value < 60)  return 'EXPOSURE LOW';
    if (tracePct.value < 85)  return 'TRACED — EVADE NOW';
    return 'ICE LOCK IMMINENT';
});

// ── Rep label ──────────────────────────────────────────────────────────────────

const repLabel = computed(() => {
    if (props.reputation >= 9) return 'LEGEND';
    if (props.reputation >= 7) return 'TRUSTED';
    if (props.reputation >= 5) return 'KNOWN';
    if (props.reputation >= 3) return 'UNKNOWN';
    return 'GHOST';
});

// ── System log lines — reactive to game state ──────────────────────────────────

const statusLines = computed(() => [
    { key: 'KERN',   val: 'OK',                    cls: 'sm-log--ok'   },
    { key: 'SYS',    val: 'RUNNING',                cls: 'sm-log--ok'   },
    { key: 'NET',    val: 'SPLICE_ACTIVE',          cls: 'sm-log--ok'   },
    { key: 'AUTH',   val: 'SESSION_LOCKED',         cls: 'sm-log--dim'  },
    { key: 'MEM',    val: `${847 - Math.floor((1 - props.stability) * 120)}MB`, cls: 'sm-log--dim' },
    {
        key: 'STAB',
        val: stabilityPct.value > 60 ? 'STABLE' : stabilityPct.value > 30 ? 'WARN' : 'CRITICAL',
        cls: stabilityPct.value > 60 ? 'sm-log--ok' : stabilityPct.value > 30 ? 'sm-log--warn' : 'sm-log--crit',
    },
    {
        key: 'TRACE',
        val: tracePct.value < 30 ? 'NONE' : tracePct.value < 85 ? 'DETECTED' : 'RESOLVED',
        cls: tracePct.value < 30 ? 'sm-log--dim' : tracePct.value < 85 ? 'sm-log--warn' : 'sm-log--crit',
    },
    { key: 'ICE',    val: 'MONITORING',             cls: 'sm-log--warn' },
    { key: 'UPLINK', val: 'OK',                     cls: 'sm-log--ok'   },
]);
</script>

<style scoped>
/* ── Panel shell ──────────────────────────────────────────────────────────── */

.sm-panel {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 18px;
    font-family: 'JetBrains Mono', monospace;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Header ───────────────────────────────────────────────────────────────── */

.sm-header {
    font-size: 10px;
    letter-spacing: 0.2em;
    color: rgba(0,200,240,0.4);
    border-bottom: 1px solid rgba(0,200,240,0.1);
    padding-bottom: 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sm-header-bracket { color: rgba(0,200,240,0.2); }
.sm-header-id      { margin-left: auto; color: rgba(0,200,240,0.18); font-size: 9px; }

/* ── Metric block ─────────────────────────────────────────────────────────── */

.sm-metric {
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex-shrink: 0;
}

.sm-metric-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.sm-metric-label {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.4);
}

.sm-metric-val {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.02em;
    line-height: 1;
}

.sm-unit {
    font-size: 11px;
    font-weight: 400;
    color: rgba(0,200,240,0.4);
    margin-left: 2px;
}

.sm-metric-sub {
    font-size: 8px;
    letter-spacing: 0.12em;
    color: rgba(0,200,240,0.3);
}

/* ── Progress bar ─────────────────────────────────────────────────────────── */

.sm-bar-track {
    height: 6px;
    background: rgba(0,200,240,0.06);
    overflow: hidden;
    border: 1px solid rgba(0,200,240,0.08);
}

.sm-bar-fill {
    height: 100%;
    /* Tailwind transition classes handle duration — scoped style just sets defaults */
}

/* Stability fills */
.sm-bar--stab-ok   { background: linear-gradient(90deg, #003d1a, #00ff9d); box-shadow: 0 0 8px rgba(0,255,100,0.35); }
.sm-bar--stab-warn { background: linear-gradient(90deg, #3d2800, #ffaa00); box-shadow: 0 0 8px rgba(255,170,0,0.3);  }
.sm-bar--stab-crit { background: linear-gradient(90deg, #3d0000, #ff3333); box-shadow: 0 0 8px rgba(255,50,50,0.35); }

/* Trace fill — always red-orange */
.sm-bar--trace { background: linear-gradient(90deg, #1a0500, #ff5500); box-shadow: 0 0 8px rgba(255,85,0,0.3); }

/* ── Value colour states ───────────────────────────────────────────────────── */

.sm-val--ok   { color: #00ff9d; }
.sm-val--warn { color: #ffaa00; }
.sm-val--crit { color: #ff3333; animation: sm-pulse 0.55s ease infinite alternate; }
.sm-val--trace{ color: #ff6600; }
.sm-val--rep  { color: #00c8f0; }

/* ── Reputation pips ──────────────────────────────────────────────────────── */

.sm-pips {
    display: flex;
    gap: 4px;
}

.sm-pip {
    flex: 1;
    height: 6px;
    border: 1px solid rgba(0,200,240,0.15);
    transition: background 0.3s, box-shadow 0.3s;
}

.sm-pip--filled {
    background: #00c8f0;
    border-color: rgba(0,200,240,0.6);
    box-shadow: 0 0 6px rgba(0,200,240,0.4);
}

.sm-pip--empty {
    background: rgba(0,200,240,0.04);
}

/* ── Fragment pips ────────────────────────────────────────────────────────── */

.sm-frag-pips {
    display: flex;
    gap: 10px;
}

.sm-frag-pip      { font-size: 22px; transition: color 0.25s, text-shadow 0.25s; }
.sm-frag-pip--open    { color: rgba(0,200,240,0.2); }
.sm-frag-pip--secured { color: #00ff9d; text-shadow: 0 0 12px rgba(0,255,100,0.6); }

/* ── Divider ──────────────────────────────────────────────────────────────── */

.sm-divider {
    height: 1px;
    background: rgba(0,200,240,0.08);
    flex-shrink: 0;
}

/* ── System log ───────────────────────────────────────────────────────────── */

.sm-log {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.sm-log-header {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.3);
    margin-bottom: 6px;
    flex-shrink: 0;
}

.sm-log-line {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    letter-spacing: 0.08em;
    flex-shrink: 0;
}

.sm-log-bracket { color: rgba(0,200,240,0.2); }
.sm-log-val     { margin-left: auto; }

/* Log line colour states */
.sm-log--ok   .sm-log-val { color: rgba(0,255,100,0.7); }
.sm-log--warn .sm-log-val { color: rgba(255,170,0,0.7); }
.sm-log--crit .sm-log-val { color: rgba(255,51,51,0.9); animation: sm-pulse 0.55s ease infinite alternate; }
.sm-log--dim  .sm-log-val { color: rgba(0,200,240,0.28); }

.sm-log--ok   { color: rgba(0,200,240,0.5); }
.sm-log--warn { color: rgba(255,170,0,0.5); }
.sm-log--crit { color: rgba(255,51,51,0.6); }
.sm-log--dim  { color: rgba(0,200,240,0.25); }

/* ── Keyframes ────────────────────────────────────────────────────────────── */

@keyframes sm-pulse {
    from { opacity: 1; }
    to   { opacity: 0.3; }
}
</style>
