<template>
    <div class="dgg-guide">

        <header class="dgg-header">
            <span class="dgg-title">◈ DATA_GRAB // OPERATOR MANUAL</span>
            <span class="dgg-sub">Intercept protocol reference — read before your first run</span>
        </header>

        <nav class="dgg-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="dgg-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="dgg-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS DATA_GRAB</h2>
                <p class="sec-body">
                    Data_Grab is a live packet interception sequence. Target data packets stream
                    across the screen in real time, mixed with decoys. Your job is to click and
                    capture the correct packets before the trace locks your connection or the
                    timer runs out.
                </p>
                <p class="sec-body">
                    The minigame is attached to quest objectives — a skin layer wraps it with
                    mission-specific file names, objectives, and flavour text. The mechanics
                    are identical regardless of context.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-green">WIN</span>
                    <span class="rule-val hl-green">Capture the required number of target packets before the trace fills or the timer expires.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL (TRACE)</span>
                    <span class="rule-val hl-red">The red TRACE bar at the top fills to 100%. ICE has locked your connection.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL (TIMEOUT)</span>
                    <span class="rule-val hl-red">The countdown timer hits zero before you capture enough packets.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DECOYS</span>
                    <span class="rule-val">Clicking a decoy reveals it as junk but wastes nothing except the click. You cannot click the same packet twice.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">BUFFER</span>
                    <span class="rule-val">The right-side panel tracks your capture progress. Slots fill as you grab target packets.</span>
                </div>
            </section>

            <!-- ── STREAM ────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'stream'">
                <h2 class="sec-title">THE PACKET STREAM</h2>
                <p class="sec-body">
                    Packets spawn from the left edge of the stream area and move right
                    at a constant speed. When a packet exits the right side it is gone —
                    you cannot recover missed targets. New packets spawn continuously until
                    the match ends.
                </p>

                <h3 class="subsec-title">PACKET TYPES</h3>
                <div class="pkt-block pkt-block--target">
                    <div class="pkt-icon">▣</div>
                    <div>
                        <div class="pkt-name">TARGET PACKET</div>
                        <div class="pkt-desc">
                            Labelled <span class="hl-green">PKT_DATA, PAYLOAD, CHUNK, FRAGMENT,</span> or <span class="hl-green">BLOCK</span>.
                            Click immediately — it counts toward your capture total.
                            The packet fades out after capture.
                        </div>
                    </div>
                </div>
                <div class="pkt-block pkt-block--decoy">
                    <div class="pkt-icon pkt-icon--decoy">▢</div>
                    <div>
                        <div class="pkt-name pkt-name--decoy">DECOY PACKET</div>
                        <div class="pkt-desc">
                            Labelled <span class="hl-red">NOISE, DECOY, NULL, JUNK, FILLER,</span> or <span class="hl-red">EMPTY</span>.
                            Clicking a decoy reveals its label in red. No penalty beyond the wasted click.
                        </div>
                    </div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">VISUAL TELLS</h3>
                <div class="sec-rule">
                    <span class="rule-key hl-green">▣ ICON</span>
                    <span class="rule-val">Filled square — target. Click it.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key" style="color:rgba(255,255,255,0.4)">▢ ICON</span>
                    <span class="rule-val">Hollow square — decoy. Skip or click to confirm it's junk.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-green">BRIGHT BORDER</span>
                    <span class="rule-val">Target packets have a slightly brighter border on hover. Decoys are duller.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">RED BORDER</span>
                    <span class="rule-val">A decoy you already clicked. Already revealed — ignore it.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Read the label first. Target labels are generic data words. Decoy labels are junk words.
                    At higher difficulties the decoy ratio increases — more false signals. Glance at
                    the label before clicking, not after.
                </div>
            </section>

            <!-- ── PRESSURE ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'pressure'">
                <h2 class="sec-title">TRACE BAR &amp; TIMER</h2>
                <p class="sec-body">
                    Two independent clocks are running simultaneously. Either one ending the match early means failure.
                    Neither resets on packet captures — every second counts from the moment the stream starts.
                </p>

                <h3 class="subsec-title">TRACE BAR</h3>
                <p class="sec-body">
                    The red bar beneath the header fills continuously. When it reaches 100%,
                    ICE has locked the connection — match over, no reward. The fill speed
                    scales with difficulty. You cannot slow or reverse the trace.
                </p>

                <div class="sec-rule">
                    <span class="rule-key">DIFFICULTY 1</span>
                    <span class="rule-val">Trace speed: 1.8% per second. Roughly 55 seconds to full at base rate.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DIFFICULTY 2</span>
                    <span class="rule-val">Trace speed: 2.5% per second. Roughly 40 seconds to full.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DIFFICULTY 3</span>
                    <span class="rule-val">Trace speed: 4.0% per second. Roughly 25 seconds to full. You need to move fast.</span>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">COUNTDOWN TIMER</h3>
                <p class="sec-body">
                    The top-right displays the remaining time. Set per mission by the quest skin.
                    When it hits zero, the match fails regardless of trace level.
                </p>
                <div class="sec-rule">
                    <span class="rule-key hl-amber">≤10s</span>
                    <span class="rule-val hl-amber">Timer turns amber. Increase urgency.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">≤5s</span>
                    <span class="rule-val hl-red">Timer turns red and blinks. Final window.</span>
                </div>

                <div class="sec-callout" style="margin-top:20px">
                    <span class="callout-label">NOTE</span>
                    The trace bar and the timer are independent fail conditions.
                    At D3, the trace fills in ~25 seconds and the timer may be 30 seconds —
                    the trace often kills the run before the timer does.
                    Prioritize speed over accuracy at high difficulty.
                </div>
            </section>

            <!-- ── DIFFICULTY ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'difficulty'">
                <h2 class="sec-title">DIFFICULTY TIERS</h2>
                <p class="sec-body">
                    Quest skins assign difficulty 1–3. Higher difficulty means faster packets,
                    more decoys, and a faster trace. The required capture count and time limit
                    are set by the quest — only the stream parameters change with difficulty.
                </p>

                <div class="diff-block diff-block--1">
                    <div class="diff-header">
                        <span class="diff-tier">D1</span>
                        <span class="diff-name">STANDARD INTERCEPT</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">TRACE SPEED</span><span class="rule-val">1.8% / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">DECOY RATIO</span><span class="rule-val">30% of spawns are decoys</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">PACKET SPEED</span><span class="rule-val">90 px / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">SPAWN RATE</span><span class="rule-val">One packet every 1.8 seconds</span>
                    </div>
                </div>

                <div class="diff-block diff-block--2" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D2</span>
                        <span class="diff-name">ACCELERATED INTERCEPT</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">TRACE SPEED</span><span class="rule-val">2.5% / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">DECOY RATIO</span><span class="rule-val">50% of spawns are decoys</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">PACKET SPEED</span><span class="rule-val">120 px / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">SPAWN RATE</span><span class="rule-val">One packet every 1.4 seconds</span>
                    </div>
                </div>

                <div class="diff-block diff-block--3" style="margin-top:12px">
                    <div class="diff-header">
                        <span class="diff-tier">D3</span>
                        <span class="diff-name">HOSTILE ENVIRONMENT</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">TRACE SPEED</span><span class="rule-val">4.0% / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">DECOY RATIO</span><span class="rule-val">60% of spawns are decoys</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">PACKET SPEED</span><span class="rule-val">150 px / second</span>
                    </div>
                    <div class="sec-rule">
                        <span class="rule-key">SPAWN RATE</span><span class="rule-val">One packet every 1.0 second</span>
                    </div>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">TIP</span>
                    At D3, more than half the packets are decoys and they move fast.
                    Resist the urge to click everything. Read labels first.
                    A wrong click wastes time you don't have at that trace speed.
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
    { id: 'stream',     label: 'THE STREAM'  },
    { id: 'pressure',   label: 'TRACE / TIMER' },
    { id: 'difficulty', label: 'DIFFICULTY'  },
];
</script>

<style scoped>
.dgg-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.dgg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 100, 0.1);
    flex-shrink: 0;
}
.dgg-title { font-size: 12px; color: #00ff9d; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,100,0.3); }
.dgg-sub   { font-size: 9px;  color: rgba(0,255,100,0.5); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.dgg-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.dgg-nav-btn {
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
.dgg-nav-btn:hover  { color: rgba(0,255,100,0.85); background: rgba(0,255,100,0.03); }
.dgg-nav-btn.active { color: #00ff9d; background: rgba(0,255,100,0.05); border-bottom: 2px solid #00ff9d; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.dgg-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.dgg-content::-webkit-scrollbar       { width: 3px; }
.dgg-content::-webkit-scrollbar-thumb { background: rgba(0,255,100,0.1); }

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

/* ── Packet type blocks ─────────────────────────────────────────────────────── */
.pkt-block {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    margin-bottom: 8px;
    border: 1px solid rgba(0,255,100,0.1);
    background: rgba(0,255,100,0.015);
}
.pkt-block--decoy {
    border-color: rgba(255,51,51,0.15);
    background: rgba(255,0,0,0.02);
}
.pkt-icon {
    font-size: 18px;
    color: #00ff9d;
    flex-shrink: 0;
    line-height: 1;
    padding-top: 2px;
}
.pkt-icon--decoy { color: rgba(255,51,51,0.4); }
.pkt-name {
    font-size: 9px;
    color: rgba(0,255,100,0.7);
    letter-spacing: 0.14em;
    margin-bottom: 5px;
}
.pkt-name--decoy { color: rgba(255,51,51,0.5); }
.pkt-desc {
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
