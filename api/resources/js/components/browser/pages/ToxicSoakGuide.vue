<template>
    <div class="tsg-guide">

        <header class="tsg-header">
            <span class="tsg-title">◈ TOXIC_SOAK // OPERATOR MANUAL</span>
            <span class="tsg-sub">Pressure management protocol — read before your first run</span>
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
                    Toxic_Soak is a pressure-management sequence. You are feeding corrupted
                    data into a sink node — the architecture is absorbing toxicity across
                    multiple process vectors. Your job is to vent each vector before it
                    overflows and destabilises your rig.
                </p>
                <p class="sec-body">
                    Unlike most minigames, the absorption is working in your favour.
                    The primary bar (ABSORPTION) fills automatically over time — when it
                    completes, the soak is done and you win. The only way to fail is to
                    let your stability crash before absorption finishes.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-green">WIN</span>
                    <span class="rule-val hl-green">The ABSORPTION bar reaches 100%. Hold position long enough and the soak completes.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL</span>
                    <span class="rule-val hl-red">Rig stability depletes to zero. Too many vector overflows before absorption completes.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TIMER</span>
                    <span class="rule-val">A secondary countdown runs. If it expires, the match fails regardless of absorption level.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">KEY INSIGHT</span>
                    <span class="rule-val">You are not racing to do anything — you are defending. Manage the vectors. Let the absorption fill itself.</span>
                </div>
            </section>

            <!-- ── VECTORS ───────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'vectors'">
                <h2 class="sec-title">PRESSURE VECTORS</h2>
                <p class="sec-body">
                    Each vector is a named process bleeding data into your architecture.
                    Pressure builds automatically at a fixed rate per vector. When pressure
                    hits 100%, the vector overflows — your stability takes a hit and pressure
                    partially resets.
                </p>

                <h3 class="subsec-title">VECTOR TYPES</h3>
                <div class="vec-block">
                    <div class="vec-id">PROC_DELTA</div>
                    <div class="vec-type">DATA_BLEED</div>
                    <div class="vec-desc">Standard bleed rate. Present at all difficulties. Manageable if vented regularly.</div>
                </div>
                <div class="vec-block">
                    <div class="vec-id">PROC_SIGMA</div>
                    <div class="vec-type">CACHE_FLOOD</div>
                    <div class="vec-desc">Slightly slower than DELTA. Present at D2+. Fills at a predictable pace.</div>
                </div>
                <div class="vec-block">
                    <div class="vec-id">PROC_OMEGA</div>
                    <div class="vec-type">STACK_LEAK</div>
                    <div class="vec-desc">Fastest steady build rate. Present at D2+. Will overflow quickly if ignored.</div>
                </div>
                <div class="vec-block vec-block--volatile">
                    <div class="vec-id">PROC_NULL</div>
                    <div class="vec-type">VOLATILE ⚠</div>
                    <div class="vec-desc">
                        D3 only. Unpredictable spikes fire every 3–7 seconds, jumping pressure by
                        28–43% instantly. Normal build rate is slower than OMEGA — but the spikes
                        will catch you off guard.
                    </div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">PRESSURE STATES</h3>
                <div class="sec-rule">
                    <span class="rule-key hl-green">0–60%</span>
                    <span class="rule-val">Safe. Bar fills green. You have time to vent other vectors first.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-amber">60–85%</span>
                    <span class="rule-val hl-amber">WARN state. Bar turns amber, border highlights. Vent soon.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">85–100%</span>
                    <span class="rule-val hl-red">CRITICAL. Bar turns red, border pulses. Vent immediately or overflow.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">OVERFLOW</span>
                    <span class="rule-val hl-red">Stability hit. Pressure resets to a partial level — not zero. The overflow tag flashes briefly.</span>
                </div>
            </section>

            <!-- ── VENTING ───────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'venting'">
                <h2 class="sec-title">VENTING</h2>
                <p class="sec-body">
                    Each vector has a <span class="hl-green">[ VENT ]</span> button.
                    Clicking it starts a continuous drain — pressure falls at the vent rate
                    until the vector reaches zero. The button shows <span class="hl-amber">[ VENTING... ]</span>
                    while draining and becomes available again when the vector empties.
                </p>
                <p class="sec-body">
                    You can vent multiple vectors simultaneously. There is no cooldown — the
                    only limit is that venting one vector does not pause another from building.
                    All vectors build pressure independently at all times.
                </p>

                <h3 class="subsec-title">VENT RATES BY DIFFICULTY</h3>
                <div class="sec-rule">
                    <span class="rule-key">D1</span>
                    <span class="rule-val">80% pressure drained per second. Fast relief.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">D2</span>
                    <span class="rule-val">70% per second. Slightly slower — more risk at high pressure.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">D3</span>
                    <span class="rule-val">65% per second. Vent takes longer, and OMEGA + PROC_NULL build fast. Timing is critical.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Vent the fastest-building vector first — at D2+ that's PROC_OMEGA.
                    Don't wait for vectors to hit CRITICAL before reacting. A brief vent
                    when a vector hits 60% keeps everything manageable.
                    At D3, watch PROC_NULL constantly — a spike at 70% can push it to overflow
                    before you react if you're already venting something else.
                </div>
            </section>

            <!-- ── DIFFICULTY ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'difficulty'">
                <h2 class="sec-title">DIFFICULTY TIERS</h2>
                <p class="sec-body">
                    Difficulty controls the number of vectors, their build rates, the overflow
                    stability penalty, and the post-overflow pressure reset point.
                    Higher difficulties add more vectors and punish each overflow more severely.
                </p>

                <div class="diff-block diff-block--1">
                    <div class="diff-header">
                        <span class="diff-tier">D1</span>
                        <span class="diff-name">CONTAINED LEAK</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">VECTORS</span><span class="rule-val">2 — PROC_DELTA (7.5%/s), PROC_SIGMA (6.0%/s)</span></div>
                    <div class="sec-rule"><span class="rule-key">VENT RATE</span><span class="rule-val">80% / second</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW HIT</span><span class="rule-val">18% stability per overflow</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW RESET</span><span class="rule-val">Pressure resets to 20%</span></div>
                </div>

                <div class="diff-block diff-block--2" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D2</span>
                        <span class="diff-name">SATURATED ENVIRONMENT</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">VECTORS</span><span class="rule-val">3 — DELTA (9.5%/s), SIGMA (8.0%/s), OMEGA (11.0%/s)</span></div>
                    <div class="sec-rule"><span class="rule-key">VENT RATE</span><span class="rule-val">70% / second</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW HIT</span><span class="rule-val">24% stability per overflow</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW RESET</span><span class="rule-val">Pressure resets to 28%</span></div>
                </div>

                <div class="diff-block diff-block--3" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D3</span>
                        <span class="diff-name">HOSTILE SATURATION</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">VECTORS</span><span class="rule-val">4 — DELTA (11.5%/s), SIGMA (10.0%/s), OMEGA (13.0%/s), NULL (8.5%/s + spikes)</span></div>
                    <div class="sec-rule"><span class="rule-key">VENT RATE</span><span class="rule-val">65% / second</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW HIT</span><span class="rule-val">30% stability per overflow</span></div>
                    <div class="sec-rule"><span class="rule-key">OVERFLOW RESET</span><span class="rule-val">Pressure resets to 32%</span></div>
                    <div class="sec-rule"><span class="rule-key">PROC_NULL SPIKES</span><span class="rule-val">+28–43% pressure spike every 3–7 seconds</span></div>
                </div>

                <div class="sec-callout" style="margin-top:20px">
                    <span class="callout-label">D3 WARNING</span>
                    At D3, PROC_OMEGA builds to overflow in under 8 seconds from reset.
                    After each overflow, it resets to 32% and is back to critical in roughly 5 seconds.
                    PROC_NULL spikes can push a 50% vector to overflow before you can vent it.
                    You will need to vent two vectors simultaneously at times.
                    Three overflows at 30% each eliminates 90% of your stability — one more ends the run.
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
    { id: 'vectors',    label: 'VECTORS'   },
    { id: 'venting',    label: 'VENTING'   },
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
