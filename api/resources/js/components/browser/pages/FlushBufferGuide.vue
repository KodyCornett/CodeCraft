<template>
    <div class="fbg-guide">

        <header class="fbg-header">
            <span class="fbg-title">◈ FLUSH_BUFFER // OPERATOR MANUAL</span>
            <span class="fbg-sub">Echo-match protocol — read before your first run</span>
        </header>

        <nav class="fbg-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="fbg-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="fbg-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS FLUSH_BUFFER</h2>
                <p class="sec-body">
                    Flush_Buffer is a recursive signal-termination sequence. A rogue process is
                    running nested recursive loops inside a target system. Your job is to unwind
                    each layer by intercepting the signal amplitude at the exact moment it cycles —
                    then matching the value before the window closes.
                </p>
                <p class="sec-body">
                    The match runs in repeating cycles until all recursion layers are flushed.
                    Fail to match in time, or let your rig stability crash, and the process
                    re-entrenches.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-green">WIN</span>
                    <span class="rule-val hl-green">Flush all recursion layers. The depth counter reaches its maximum.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL (STABILITY)</span>
                    <span class="rule-val hl-red">Your rig stability bar depletes to zero. Too many missed matches or window timeouts.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL (TIMER)</span>
                    <span class="rule-val hl-red">The mission timer expires before you flush all layers.</span>
                </div>
            </section>

            <!-- ── CYCLE ─────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'cycle'">
                <h2 class="sec-title">THE SIGNAL CYCLE</h2>
                <p class="sec-body">
                    The match alternates between two phases. Watch the status badge top-left —
                    it tells you which phase is active.
                </p>

                <div class="phase-block phase-block--transmit">
                    <div class="phase-header">
                        <span class="phase-badge phase-badge--transmit">[ TRANSMITTING ]</span>
                    </div>
                    <p class="phase-desc">
                        The signal is active and building. The waveform scrolls across the monitor
                        and the SIG_AMP display flickers with noise — the actual amplitude is hidden.
                        <strong>Wait.</strong> You cannot act during TRANSMITTING.
                        When the transmit timer expires, the signal locks and the cycle begins.
                    </p>
                </div>

                <div class="phase-block phase-block--cycling" style="margin-top:12px">
                    <div class="phase-header">
                        <span class="phase-badge phase-badge--cycling">[ CYCLING ]</span>
                    </div>
                    <p class="phase-desc">
                        The locked amplitude is revealed as a large hex value — <strong>0x??</strong>.
                        A set of options appear below it. Click the one that matches exactly.
                        A WINDOW bar depletes — if it hits zero before you select, the window closes
                        and you take a stability hit. Then the cycle restarts from TRANSMITTING.
                    </p>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">MATCHING CORRECTLY</h3>
                <div class="sec-rule">
                    <span class="rule-key hl-green">CORRECT</span>
                    <span class="rule-val hl-green">Layer flushed. A brief confirmation flash appears. Cycle restarts for the next layer.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">WRONG</span>
                    <span class="rule-val hl-red">Stability penalty applied. Cycle restarts from TRANSMITTING with no layer progress.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">WINDOW TIMEOUT</span>
                    <span class="rule-val hl-red">Same as a wrong answer — stability hit, cycle restarts.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Read the target value the moment CYCLING appears. The window is short.
                    Don't re-read the options after the bar is halfway depleted — commit.
                    At higher difficulties the decoys are bit-flips of the correct value,
                    nearly identical. Compare digit by digit.
                </div>
            </section>

            <!-- ── RECURSION ─────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'recursion'">
                <h2 class="sec-title">RECURSION DEPTH</h2>
                <p class="sec-body">
                    The RECURSION DEPTH display shows your progress as a pip row.
                    Each successful match flushes one layer and advances the counter.
                    The ACTIVE INSTANCES display shows the process halving with each flush —
                    collapse is progressing.
                </p>
                <p class="sec-body">
                    As layers collapse the signal becomes harder to catch — TRANSMITTING phases
                    get shorter, and CYCLING windows shrink. The recursive process is destabilising.
                    You are racing against tightening margins.
                </p>

                <h3 class="subsec-title">LAYERS BY DIFFICULTY</h3>
                <div class="sec-rule">
                    <span class="rule-key">D1</span>
                    <span class="rule-val">3 layers. Manageable depth, generous window.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">D2</span>
                    <span class="rule-val">5 layers. Moderate depth, tighter margins as you progress.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">D3</span>
                    <span class="rule-val">7 layers. Deep recursion. Window at the final layers is under a second.</span>
                </div>

                <div class="depth-example">
                    <div class="dep-row">
                        <span class="dep-label">LAYER 1</span>
                        <span class="dep-val">TRANSMIT 3.0s / WINDOW 2.0s</span>
                    </div>
                    <div class="dep-row">
                        <span class="dep-label">LAYER 3</span>
                        <span class="dep-val">TRANSMIT 2.7s / WINDOW 1.7s</span>
                    </div>
                    <div class="dep-row">
                        <span class="dep-label">LAYER 5</span>
                        <span class="dep-val">TRANSMIT 2.4s / WINDOW ~1.1s</span>
                    </div>
                    <div class="dep-row dep-row--note">
                        Example at D2. Each layer loses ~0.15s transmit and ~0.12s window.
                    </div>
                </div>
            </section>

            <!-- ── DIFFICULTY ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'difficulty'">
                <h2 class="sec-title">DIFFICULTY TIERS</h2>
                <p class="sec-body">
                    Difficulty controls the number of layers, the decoy quality, and the timing margins.
                    Higher difficulty means more layers to flush, fewer options at first but harder decoys,
                    and a shorter initial window.
                </p>

                <div class="diff-block diff-block--1">
                    <div class="diff-header">
                        <span class="diff-tier">D1</span>
                        <span class="diff-name">SHALLOW RECURSION</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">LAYERS</span><span class="rule-val">3</span></div>
                    <div class="sec-rule"><span class="rule-key">WINDOW</span><span class="rule-val">2.0s base, −0.15s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">TRANSMIT</span><span class="rule-val">3.0s base, −0.15s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">OPTIONS</span><span class="rule-val">2 (1 correct + 1 decoy — clearly different)</span></div>
                    <div class="sec-rule"><span class="rule-key">STABILITY HIT</span><span class="rule-val">25% per miss or timeout</span></div>
                </div>

                <div class="diff-block diff-block--2" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D2</span>
                        <span class="diff-name">DEEP RECURSION</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">LAYERS</span><span class="rule-val">5</span></div>
                    <div class="sec-rule"><span class="rule-key">WINDOW</span><span class="rule-val">1.6s base, −0.12s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">TRANSMIT</span><span class="rule-val">2.5s base, −0.15s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">OPTIONS</span><span class="rule-val">3 (1 correct + 2 decoys — one nibble flipped)</span></div>
                    <div class="sec-rule"><span class="rule-key">STABILITY HIT</span><span class="rule-val">22% per miss or timeout</span></div>
                </div>

                <div class="diff-block diff-block--3" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D3</span>
                        <span class="diff-name">HOSTILE RECURSION</span>
                    </div>
                    <div class="sec-rule"><span class="rule-key">LAYERS</span><span class="rule-val">7</span></div>
                    <div class="sec-rule"><span class="rule-key">WINDOW</span><span class="rule-val">1.3s base, −0.10s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">TRANSMIT</span><span class="rule-val">2.0s base, −0.15s per layer</span></div>
                    <div class="sec-rule"><span class="rule-key">OPTIONS</span><span class="rule-val">4 (1 correct + 3 decoys — 1–2 bit flips, nearly identical)</span></div>
                    <div class="sec-rule"><span class="rule-key">STABILITY HIT</span><span class="rule-val">20% per miss or timeout</span></div>
                </div>

                <div class="sec-callout" style="margin-top:20px">
                    <span class="callout-label">WARNING</span>
                    At D3, decoys are bit-flipped versions of the correct value.
                    <span class="hl-red">0xA3</span> vs <span class="hl-red">0xA1</span> vs <span class="hl-red">0xB3</span> vs <span class="hl-red">0xA3</span> is a real scenario.
                    Focus on each hex digit individually. Never rush — the window is tight but
                    a wrong click costs more than a slow one.
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
    { id: 'overview',   label: 'OVERVIEW'   },
    { id: 'cycle',      label: 'THE CYCLE'  },
    { id: 'recursion',  label: 'RECURSION'  },
    { id: 'difficulty', label: 'DIFFICULTY' },
];
</script>

<style scoped>
.fbg-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.fbg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 100, 0.1);
    flex-shrink: 0;
}
.fbg-title { font-size: 12px; color: #00ff9d; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,100,0.3); }
.fbg-sub   { font-size: 9px;  color: rgba(0,255,100,0.5); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.fbg-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.fbg-nav-btn {
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
.fbg-nav-btn:hover  { color: rgba(0,255,100,0.85); background: rgba(0,255,100,0.03); }
.fbg-nav-btn.active { color: #00ff9d; background: rgba(0,255,100,0.05); border-bottom: 2px solid #00ff9d; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.fbg-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.fbg-content::-webkit-scrollbar       { width: 3px; }
.fbg-content::-webkit-scrollbar-thumb { background: rgba(0,255,100,0.1); }

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

/* ── Phase blocks ───────────────────────────────────────────────────────────── */
.phase-block {
    padding: 14px 16px;
    border: 1px solid rgba(0,255,100,0.08);
    background: rgba(0,255,100,0.015);
}
.phase-block--transmit {
    border-color: rgba(255,102,0,0.2);
    background: rgba(255,102,0,0.02);
}
.phase-block--cycling {
    border-color: rgba(0,255,100,0.2);
    background: rgba(0,255,100,0.02);
}
.phase-header { margin-bottom: 10px; }
.phase-badge {
    font-size: 10px;
    letter-spacing: 0.15em;
    padding: 3px 8px;
    border: 1px solid;
}
.phase-badge--transmit {
    color: rgba(255,102,0,0.75);
    border-color: rgba(255,102,0,0.3);
}
.phase-badge--cycling {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.5);
}
.phase-desc {
    font-size: 9px;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.04em;
    line-height: 1.75;
    margin: 0;
}

/* ── Depth example ──────────────────────────────────────────────────────────── */
.depth-example {
    margin-top: 16px;
    padding: 12px 16px;
    border: 1px solid rgba(0,255,100,0.08);
    background: rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.dep-row {
    display: flex;
    gap: 14px;
    font-size: 9px;
}
.dep-label { width: 70px; flex-shrink: 0; color: rgba(0,255,100,0.45); letter-spacing: 0.08em; }
.dep-val   { color: rgba(255,255,255,0.65); }
.dep-row--note { color: rgba(0,255,100,0.25); font-size: 8px; letter-spacing: 0.04em; border-top: 1px solid rgba(0,255,100,0.05); padding-top: 6px; margin-top: 2px; }

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
