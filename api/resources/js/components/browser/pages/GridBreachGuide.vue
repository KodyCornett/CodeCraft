<template>
    <div class="gb-guide">

        <header class="gb-guide-header">
            <span class="gb-guide-title">◈ GRID-BREACH // OPERATOR MANUAL</span>
            <span class="gb-guide-sub">Breach protocol reference — read before your first run</span>
        </header>

        <nav class="gb-guide-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="gb-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="gb-guide-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS GRID-BREACH</h2>
                <p class="sec-body">
                    Grid-Breach is the breach engine that runs every time you hack a node or engage in PvP combat.
                    It is a coordinate-based puzzle — you are given a target sequence of hex values and must locate
                    each one on a 10×10 grid before the timer expires.
                </p>
                <p class="sec-body">
                    The grid scrambles every 5 seconds. Your confirmed cells hold their position.
                    Every run is procedurally generated — no two breaches are identical.
                </p>

                <div class="sec-rule">
                    <span class="rule-key">PvE MODE</span>
                    <span class="rule-val">Complete the target sequence to breach the node and extract its resource.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">PvP MODE</span>
                    <span class="rule-val">Maximize sequences breached before time runs out. Both players' scores are compared — highest score wins.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SEQUENCE LENGTH</span>
                    <span class="rule-val">Equal to the node's ICE rating. ICE 3 = 3 hexakeys. ICE 9 = 9 hexakeys.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ABORT</span>
                    <span class="rule-val">You can abort at any time. No reward, but no SS damage either. Use it if a node is out of your range.</span>
                </div>
            </section>

            <!-- ── THE GRID ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'grid'">
                <h2 class="sec-title">THE GRID</h2>
                <p class="sec-body">
                    The breach surface is a 10×10 matrix of two-digit hex values (00–FF).
                    Columns are labeled <span class="hl-cyan">A through J</span> across the top and bottom.
                    Rows are numbered <span class="hl-cyan">1 through 10</span> on both sides.
                </p>
                <p class="sec-body">
                    To enter a coordinate, type the <strong class="hl-cyan">column letter first</strong>,
                    then the <strong class="hl-cyan">row number</strong> — for example
                    <span class="hl-green">F6</span> or <span class="hl-green">A10</span>.
                    Hit Enter or click [SUBMIT].
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-cyan">PULSING CELLS</span>
                    <span class="rule-val">Cells that match your current target hexakey glow cyan. These are the ones you want.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-green">CONFIRMED CELLS</span>
                    <span class="rule-val">Once you lock in a correct coordinate it turns green and stays locked — even through scrambles.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SCRAMBLE</span>
                    <span class="rule-val">Every 5 seconds the board reshuffles. Your confirmed cells are unaffected. The current target is always guaranteed to appear somewhere after a scramble.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ROW DIRECTION</span>
                    <span class="rule-val">[>>>] rows scan forward. [&lt;&lt;&lt;] rows scan backward. Direction is cosmetic — coordinates work the same either way.</span>
                </div>

                <div class="coord-example">
                    <div class="ce-label">COORDINATE FORMAT</div>
                    <div class="ce-row">
                        <span class="ce-col">A</span><span class="ce-sep">–</span><span class="ce-col">J</span>
                        <span class="ce-arrow">+</span>
                        <span class="ce-row-num">1</span><span class="ce-sep">–</span><span class="ce-row-num">10</span>
                        <span class="ce-arrow">=</span>
                        <span class="ce-example">F6, A1, J10</span>
                    </div>
                </div>
            </section>

            <!-- ── SEQUENCE ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'sequence'">
                <h2 class="sec-title">TARGET SEQUENCE</h2>
                <p class="sec-body">
                    At the top of the breach terminal you will see the <strong class="hl-cyan">TARGET SEQUENCE</strong> —
                    a chain of hex values you must find and confirm in order, left to right.
                    The currently required hexakey pulses brighter than the rest.
                </p>
                <p class="sec-body">
                    You must find them <strong class="hl-cyan">in sequence</strong> — you cannot skip ahead.
                    Confirming the wrong coordinate does not advance the sequence, but it does not reset it either.
                    Only wrong coordinates on GLITCH rows carry a time penalty.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-cyan">◄ LOCATE</span>
                    <span class="rule-val">The indicator beneath a sequence slot tells you which hexakey you're currently hunting.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">STRIKETHROUGH</span>
                    <span class="rule-val">Completed hexakeys in the sequence are struck through in green. You cannot revisit them.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SEQUENCE LENGTH</span>
                    <span class="rule-val">Determined by the node's ICE level. Starter nodes (ICE 3) have 3-step sequences. High-sec nodes (ICE 9) have 9-step sequences.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TWO COPIES</span>
                    <span class="rule-val">Each target hexakey is seeded at least twice on the grid. You only need one — pick whichever is in a safer row.</span>
                </div>
            </section>

            <!-- ── ROW MODIFIERS ─────────────────────────────────────────────── -->
            <section v-if="activeSection === 'modifiers'">
                <h2 class="sec-title">ROW MODIFIERS</h2>
                <p class="sec-body">
                    At higher ICE levels, rows gain modifiers that add pressure to the breach.
                    Modifiers appear on the right side of the affected row.
                </p>

                <div class="modifier-block modifier-block--locked">
                    <div class="mod-header">
                        <span class="mod-tag mod-tag--locked">LOCKED</span>
                        <span class="mod-since">Appears at ICE 5+</span>
                    </div>
                    <p class="mod-body">
                        An ICE barrier makes the entire row inaccessible. Any coordinate entered in a LOCKED
                        row is rejected outright — no penalty, but no progress either. Avoid these rows entirely.
                        Target hexakeys are never seeded in LOCKED rows, so you will always find your target elsewhere.
                    </p>
                </div>

                <div class="modifier-block modifier-block--glitch">
                    <div class="mod-header">
                        <span class="mod-tag mod-tag--glitch">GLITCH</span>
                        <span class="mod-since">Appears at ICE 5+</span>
                    </div>
                    <p class="mod-body">
                        A corrupted data row. Using a GLITCH row to confirm a correct coordinate
                        costs <strong class="hl-amber">−2 seconds</strong> from your timer.
                        Submitting a wrong coordinate in a GLITCH row costs <strong class="hl-red">−3 seconds</strong>.
                        Avoid GLITCH rows where possible — targets can be seeded there, so sometimes you have no choice but to pay the penalty.
                    </p>
                </div>

                <div class="sec-rule" style="margin-top: 16px;">
                    <span class="rule-key">ICE 3–4</span>
                    <span class="rule-val">No modifiers. All 10 rows are open.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 5–6</span>
                    <span class="rule-val">1 LOCKED row + 1 GLITCH row — positions randomised each match.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 7</span>
                    <span class="rule-val">2 LOCKED rows + 1 GLITCH row — positions randomised each match.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 8+</span>
                    <span class="rule-val">2 LOCKED rows + 2 GLITCH rows — positions randomised each match.</span>
                </div>
            </section>

            <!-- ── STATS ─────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'stats'">
                <h2 class="sec-title">HOW YOUR STATS AFFECT THE BREACH</h2>
                <p class="sec-body">
                    Your rig stats directly shape the difficulty of every GridBreach run.
                    A well-matched rig makes high-ICE nodes manageable. A mismatched one will bleed your timer dry.
                </p>

                <div class="sec-rule">
                    <span class="rule-key stat--ram">RAM</span>
                    <span class="rule-val">Sets total game length. Base 30s + (RAM × 5s). A RAM 4 rig gets 50 seconds. A RAM 2 rig gets 40.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--os">OS</span>
                    <span class="rule-val">Adds breathing room per move. Base 3s + (OS × 0.3s) per decision window. Higher OS = more time to think between inputs.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU vs ICE</span>
                    <span class="rule-val">Asymmetric modifier on your timer. Being above ICE gives a small bonus. Being below ICE compounds as a penalty — each point below squares the penalty. Stay close to node ICE or above it.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--firewall">FIREWALL</span>
                    <span class="rule-val">In PvP, Firewall shields your confirmed hexakeys from opponent commands. Higher Firewall = harder for opponents to disrupt your sequence.</span>
                </div>

                <div class="sec-callout">
                    <span class="callout-label">WARNING</span>
                    Attempting a node with ICE more than 4 above your CPU locks you out entirely —
                    you will not be able to initiate a breach. Check node ICE before committing.
                    Use <span class="hl-cyan">splice://sys.local/guide/stats</span> for the full CPU vs ICE table.
                </div>

                <div class="sec-rule" style="margin-top: 8px;">
                    <span class="rule-key">TIMER FORMULA</span>
                    <span class="rule-val">Base (30 + RAM×5 + OS×0.3) + CPU modifier. Minimum 8 seconds regardless of rig.</span>
                </div>
            </section>

            <!-- ── PVP ───────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'pvp'">
                <h2 class="sec-title">PvP DUEL MODE</h2>
                <p class="sec-body">
                    When two runners meet on the same node, either player can initiate a Grid-Breach duel.
                    Both players run the same breach engine simultaneously. The player who completes more
                    sequences before time expires wins.
                </p>

                <div class="sec-rule">
                    <span class="rule-key">OBJECTIVE</span>
                    <span class="rule-val">Maximize sequences breached. There is no threshold — keep going until time runs out.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">WINNER</span>
                    <span class="rule-val">Highest score when time expires. Determined server-side — both players submit their scores and the server resolves the outcome.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">LOSER</span>
                    <span class="rule-val hl-red">Pocket creds wiped. Bounty retained. SS damage applied based on winner's CPU vs loser's Firewall.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-green">WINNER</span>
                    <span class="rule-val hl-green">Receives the loser's pocket creds. Bounty and SS unaffected.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SAFE ZONES</span>
                    <span class="rule-val">CyberDoc nodes block PvP challenges entirely. No duels can be initiated there.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DECLINE</span>
                    <span class="rule-val">You can decline a challenge. No penalty for either player.</span>
                </div>

                <div class="sec-callout sec-callout--amber">
                    <span class="callout-label">STRATEGY</span>
                    In PvP, speed matters more than accuracy. Prioritize low-modifier rows.
                    Avoid GLITCH rows entirely — the time penalty at high ICE is severe.
                    Your Firewall determines how much SS damage you take if you lose.
                    High-Firewall rigs can absorb multiple losses without reaching Critical Failure.
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';

// ── Tutorial trigger — marks 'read_manual' step when this page is visited ─────
const tutorial = inject('tutorial', null);
onMounted(() => tutorial?.markStepDone('read_manual'));

defineProps({ url: { type: String, default: '' } });

const activeSection = ref('overview');

const sections = [
    { id: 'overview',  label: 'OVERVIEW'   },
    { id: 'grid',      label: 'THE GRID'   },
    { id: 'sequence',  label: 'SEQUENCE'   },
    { id: 'modifiers', label: 'MODIFIERS'  },
    { id: 'stats',     label: 'STATS'      },
    { id: 'pvp',       label: 'PvP MODE'   },
];
</script>

<style scoped>
.gb-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.gb-guide-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
}
.gb-guide-title { font-size: 12px; color: #00FFFF; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,255,0.3); }
.gb-guide-sub   { font-size: 9px;  color: rgba(0,255,255,0.25); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.gb-guide-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.gb-nav-btn {
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0,255,255,0.06);
    color: rgba(0,255,255,0.35);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.gb-nav-btn:hover { color: rgba(0,255,255,0.7); background: rgba(0,255,255,0.03); }
.gb-nav-btn.active { color: #00FFFF; background: rgba(0,255,255,0.05); border-bottom: 2px solid #00FFFF; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.gb-guide-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.gb-guide-content::-webkit-scrollbar       { width: 3px; }
.gb-guide-content::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Typography ─────────────────────────────────────────────────────────────── */
.sec-title {
    font-size: 13px;
    color: #00FFFF;
    letter-spacing: 0.12em;
    margin: 0 0 16px;
    font-weight: normal;
}
.sec-body {
    font-size: 10px;
    color: rgba(255,255,255,0.5);
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
    border-bottom: 1px solid rgba(0,255,255,0.05);
    align-items: baseline;
}
.rule-key {
    font-size: 9px;
    color: rgba(0,255,255,0.5);
    letter-spacing: 0.1em;
    width: 160px;
    flex-shrink: 0;
}
.rule-val {
    font-size: 9px;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.04em;
    line-height: 1.6;
}

/* ── Callout ────────────────────────────────────────────────────────────────── */
.sec-callout {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 10px 14px;
    border: 1px solid rgba(255,51,51,0.2);
    background: rgba(255,51,51,0.03);
    font-size: 9px;
    color: rgba(255,51,51,0.6);
    line-height: 1.65;
    letter-spacing: 0.04em;
    margin-top: 16px;
}
.sec-callout--amber {
    border-color: rgba(255,179,0,0.2);
    background: rgba(255,179,0,0.03);
    color: rgba(255,179,0,0.6);
}
.callout-label {
    font-size: 7px;
    letter-spacing: 0.14em;
    flex-shrink: 0;
    padding-top: 2px;
    opacity: 0.7;
}

/* ── Modifier blocks ────────────────────────────────────────────────────────── */
.modifier-block {
    padding: 14px;
    margin-bottom: 14px;
    border: 1px solid rgba(0,255,255,0.07);
}
.modifier-block--locked { border-color: rgba(255,51,51,0.2); background: rgba(255,51,51,0.02); }
.modifier-block--glitch { border-color: rgba(255,179,0,0.2); background: rgba(255,179,0,0.02); }

.mod-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.mod-tag {
    font-size: 9px;
    letter-spacing: 0.14em;
    padding: 2px 8px;
    border: 1px solid;
}
.mod-tag--locked { color: rgba(255,51,51,0.7);  border-color: rgba(255,51,51,0.3);  }
.mod-tag--glitch { color: rgba(255,179,0,0.75); border-color: rgba(255,179,0,0.3); }
.mod-since { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.08em; }

.mod-body {
    font-size: 9px;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.04em;
    line-height: 1.75;
    margin: 0;
}

/* ── Coordinate example ─────────────────────────────────────────────────────── */
.coord-example {
    margin-top: 16px;
    padding: 12px 16px;
    background: rgba(0,255,255,0.025);
    border: 1px solid rgba(0,255,255,0.1);
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.ce-label { font-size: 8px; color: rgba(0,255,255,0.3); letter-spacing: 0.14em; }
.ce-row   { display: flex; align-items: center; gap: 8px; font-size: 11px; }
.ce-col   { color: #00FFFF; }
.ce-sep   { color: rgba(0,255,255,0.25); }
.ce-row-num { color: rgba(0,255,136,0.8); }
.ce-arrow   { color: rgba(0,255,255,0.25); }
.ce-example { color: #00FF88; letter-spacing: 0.12em; font-size: 13px; margin-left: 8px; }

/* ── Stat colours ───────────────────────────────────────────────────────────── */
.stat--cpu      { color: #7DF9FF !important; }
.stat--ram      { color: #FF69B4 !important; }
.stat--os       { color: #00FF88 !important; }
.stat--firewall { color: #FF6B00 !important; }

/* ── Colour helpers ─────────────────────────────────────────────────────────── */
.hl-cyan  { color: #00FFFF !important; }
.hl-green { color: #00FF88 !important; }
.hl-amber { color: #FFB300 !important; }
.hl-red   { color: #FF3333 !important; }
</style>
