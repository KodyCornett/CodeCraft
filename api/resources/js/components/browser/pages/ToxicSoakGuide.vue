<template>
    <div class="tsg-guide">

        <header class="tsg-header">
            <span class="tsg-title">◈ TOXIC_SOAK // OPERATOR MANUAL</span>
            <span class="tsg-sub">Cipher-ring decryption protocol — read before your first run</span>
        </header>

        <nav class="tsg-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="tsg-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="tsg-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS TOXIC_SOAK</h2>
                <p class="sec-body">
                    Toxic_Soak is a cipher-cracking sequence. A stack of concentric rings sits
                    over the node's encryption core — each ring holds a single digit (0–9) at
                    the read marker. Rotate the rings until every marker digit matches the
                    current target cipher, and the code cracks.
                </p>
                <p class="sec-body">
                    Crack enough codes before the ICE trace locks your signal and you're
                    through. Each successful crack rolls a fresh target and re-scrambles every
                    ring — there is no partial credit carried between codes.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-green">WIN</span>
                    <span class="rule-val hl-green">Crack the required number of codes (CODES CRACKED reaches its target) before time or trace run out.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL</span>
                    <span class="rule-val hl-red">The TRACE meter reaches 100% — ICE has pinpointed your signal.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TIMER</span>
                    <span class="rule-val">A countdown runs independently of trace. If it expires, the run fails regardless of trace level.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">KEY INSIGHT</span>
                    <span class="rule-val">Trace climbs on its own the whole run — it isn't triggered by mistakes. Work fast; there's no penalty for a wrong rotation itself.</span>
                </div>
            </section>

            <!-- ── RINGS ─────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'rings'">
                <h2 class="sec-title">CIPHER RINGS</h2>
                <p class="sec-body">
                    Every ring cycles through digits 0–9. Only one digit per ring sits under
                    the fixed marker at the top of the wheel at any time — that's the ring's
                    current value. Rings are independent; rotating one never moves another.
                </p>

                <h3 class="subsec-title">RING COUNT</h3>
                <div class="vec-block">
                    <div class="vec-id">RING COUNT = ICE LEVEL</div>
                    <div class="vec-type">1:1 SCALING</div>
                    <div class="vec-desc">The node's ICE rating sets the ring count directly. ICE 3 nodes run a 3-ring cipher; ICE 8 nodes run the full 8-ring wheel — the hardest currently in the game.</div>
                </div>
                <div class="vec-block">
                    <div class="vec-id">TARGET CIPHER</div>
                    <div class="vec-type">ONE DIGIT PER RING</div>
                    <div class="vec-desc">The target readout above the wheel shows one digit per ring. A digit lights green the instant that ring's current value matches it.</div>
                </div>
                <div class="vec-block">
                    <div class="vec-id">CODES CRACKED</div>
                    <div class="vec-type">EQUALS RING COUNT</div>
                    <div class="vec-desc">The number of codes required to clear the node equals the ring count for that node — an 8-ring node needs 8 codes cracked, not just one.</div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">TRACE STATES</h3>
                <div class="sec-rule">
                    <span class="rule-key hl-green">0–60%</span>
                    <span class="rule-val">Safe. Plenty of runway to work through remaining codes.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-amber">60–90%</span>
                    <span class="rule-val hl-amber">WARN state. Trace bar and readout turn amber. Pick up the pace.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">90–100%</span>
                    <span class="rule-val hl-red">CRITICAL. Bar turns red and blinks. Screen glitch intensifies. One more tick can end the run.</span>
                </div>
            </section>

            <!-- ── CONTROLS ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'controls'">
                <h2 class="sec-title">CONTROLS</h2>
                <p class="sec-body">
                    Each ring has its own control row: a <span class="hl-green">‹</span> and
                    <span class="hl-green">›</span> button that step the ring backward or
                    forward one digit at a time, plus a live readout of its current value.
                    A row lights up and gets a <span class="hl-green">✓</span> the moment
                    that ring matches the target.
                </p>
                <p class="sec-body">
                    When every ring shows a match simultaneously, the code cracks
                    automatically — there's no separate submit button. The target re-rolls
                    and every ring re-scrambles to a new random position immediately after,
                    so the next code always starts from scratch.
                </p>

                <h3 class="subsec-title">TIMING BY ICE LEVEL</h3>
                <div class="sec-rule">
                    <span class="rule-key">BASE TIMER</span>
                    <span class="rule-val">210 seconds, plus any RAM bonus from your rig, for the full run regardless of ring count.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TRACE PACE</span>
                    <span class="rule-val">Trace accelerates as it climbs — roughly 1%/tick early on, up to 4%/tick once trace passes 90%. The curve is the same at every ICE level.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Because ring count and codes-required both scale with ICE, higher-ICE nodes
                    don't get a harsher trace curve — they just need more simultaneous alignment
                    per code and more codes overall. Work outer rings and inner rings in
                    parallel rather than solving one ring fully before touching the next.
                </div>
            </section>

            <!-- ── DIFFICULTY ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'difficulty'">
                <h2 class="sec-title">DIFFICULTY BY ICE LEVEL</h2>
                <p class="sec-body">
                    Difficulty scales on a single axis — the node's ICE rating sets both the
                    ring count and the number of codes required to clear it. The trace timer
                    and countdown pace stay identical across all ICE levels.
                </p>

                <div class="sec-rule"><span class="rule-key">ICE 3</span><span class="rule-val">3 rings — 3 codes to crack</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 4</span><span class="rule-val">4 rings — 4 codes to crack</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 5</span><span class="rule-val">5 rings — 5 codes to crack</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 6</span><span class="rule-val">6 rings — 6 codes to crack</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 7</span><span class="rule-val">7 rings — 7 codes to crack</span></div>
                <div class="sec-rule"><span class="rule-key hl-red">ICE 8</span><span class="rule-val hl-red">8 rings — 8 codes to crack (current max)</span></div>

                <div class="sec-callout" style="margin-top:20px">
                    <span class="callout-label">HIGH-ICE WARNING</span>
                    At ICE 7–8, aligning all rings at once takes real time even when you know
                    the target — that's where the fixed 210s timer and the accelerating trace
                    curve start to bite. There's no way to slow trace down; the only lever you
                    have is working faster.
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ url: { type: String, default: '' } });

const activeSection = ref('overview');

const sections = [
    { id: 'overview',   label: 'OVERVIEW'  },
    { id: 'rings',      label: 'RINGS'     },
    { id: 'controls',   label: 'CONTROLS'  },
    { id: 'difficulty', label: 'DIFFICULTY'},
];
</script>

<style scoped>
.tsg-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.tsg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 100, 0.1);
    flex-shrink: 0;
}
.tsg-title { font-size: 12px; color: #00ff9d; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,100,0.3); }
.tsg-sub   { font-size: 9px;  color: rgba(0,255,100,0.5); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.tsg-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.tsg-nav-btn {
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0,255,100,0.06);
    color: rgba(0,255,100,0.55);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.tsg-nav-btn:hover  { color: rgba(0,255,100,0.85); background: rgba(0,255,100,0.03); }
.tsg-nav-btn.active { color: #00ff9d; background: rgba(0,255,100,0.05); border-bottom: 2px solid #00ff9d; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.tsg-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.tsg-content::-webkit-scrollbar       { width: 3px; }
.tsg-content::-webkit-scrollbar-thumb { background: rgba(0,255,100,0.1); }

/* ── Typography ─────────────────────────────────────────────────────────────── */
.sec-title {
    font-size: 13px;
    color: #00ff9d;
    letter-spacing: 0.12em;
    margin: 0 0 16px;
    font-weight: normal;
}
.subsec-title {
    font-size: 10px;
    color: rgba(0,255,100,0.8);
    letter-spacing: 0.1em;
    margin: 0 0 10px;
    font-weight: normal;
}
.sec-body {
    font-size: 10px;
    color: rgba(255,255,255,0.75);
    letter-spacing: 0.04em;
    line-height: 1.85;
    margin: 0 0 14px;
    max-width: 640px;
}

/* ── Rules ──────────────────────────────────────────────────────────────────── */
.sec-rule {
    display: flex;
    gap: 20px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(0,255,100,0.05);
    align-items: baseline;
}
.rule-key {
    font-size: 9px;
    color: rgba(0,255,100,0.7);
    letter-spacing: 0.1em;
    width: 160px;
    flex-shrink: 0;
}
.rule-val {
    font-size: 9px;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.04em;
    line-height: 1.6;
}

/* ── Vector blocks ──────────────────────────────────────────────────────────── */
.vec-block {
    padding: 10px 14px;
    margin-bottom: 8px;
    border: 1px solid rgba(0,255,100,0.1);
    background: rgba(0,255,100,0.015);
}
.vec-block--volatile {
    border-color: rgba(255,51,51,0.2);
    background: rgba(255,0,0,0.02);
}
.vec-id {
    font-size: 10px;
    font-weight: 700;
    color: rgba(0,255,100,0.65);
    letter-spacing: 0.1em;
    margin-bottom: 2px;
}
.vec-block--volatile .vec-id { color: rgba(255,51,51,0.6); }
.vec-type {
    font-size: 8px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.14em;
    margin-bottom: 5px;
}
.vec-block--volatile .vec-type { color: rgba(255,51,51,0.4); }
.vec-desc {
    font-size: 9px;
    color: rgba(255,255,255,0.65);
    letter-spacing: 0.03em;
    line-height: 1.65;
    margin: 0;
}

/* ── Difficulty blocks ──────────────────────────────────────────────────────── */
.diff-block {
    padding: 14px 16px;
    border: 1px solid rgba(0,255,100,0.08);
}
.diff-block--2 { border-color: rgba(255,179,0,0.15); }
.diff-block--3 { border-color: rgba(255,51,51,0.2); }

.diff-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.diff-tier {
    font-size: 10px;
    font-weight: 700;
    color: #00ff9d;
    letter-spacing: 0.12em;
}
.diff-block--2 .diff-tier { color: #FFB300; }
.diff-block--3 .diff-tier { color: #ff3333; }

.diff-name {
    font-size: 9px;
    color: rgba(0,255,100,0.45);
    letter-spacing: 0.1em;
}
.diff-block--2 .diff-name { color: rgba(255,179,0,0.5); }
.diff-block--3 .diff-name { color: rgba(255,51,51,0.5); }

/* ── Callout ────────────────────────────────────────────────────────────────── */
.sec-callout {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 10px 14px;
    border: 1px solid rgba(255,51,51,0.2);
    background: rgba(255,51,51,0.03);
    font-size: 9px;
    color: rgba(255,51,51,0.7);
    line-height: 1.65;
    letter-spacing: 0.04em;
    margin-top: 16px;
}
.sec-callout--amber {
    border-color: rgba(255,179,0,0.2);
    background: rgba(255,179,0,0.03);
    color: rgba(255,179,0,0.7);
}
.callout-label {
    font-size: 7px;
    letter-spacing: 0.14em;
    flex-shrink: 0;
    padding-top: 2px;
    opacity: 0.7;
}

/* ── Colour helpers ─────────────────────────────────────────────────────────── */
.hl-green  { color: #00ff9d !important; }
.hl-amber  { color: #FFB300 !important; }
.hl-red    { color: #FF3333 !important; }
</style>
