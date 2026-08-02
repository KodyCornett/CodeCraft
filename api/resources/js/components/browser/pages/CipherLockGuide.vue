<template>
    <div class="clg-guide">

        <header class="clg-header">
            <span class="clg-title">◈ CIPHER_LOCK // OPERATOR MANUAL</span>
            <span class="clg-sub">Cipher-key decryption protocol — read before your first run</span>
        </header>

        <nav class="clg-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="clg-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="clg-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS CIPHER_LOCK</h2>
                <p class="sec-body">
                    Cipher_Lock is a cipher-key decryption sequence. Every letter A&ndash;Z has
                    been scrambled behind a 2-character decrypt code. A blanked phrase sits on
                    the node's core — type the code for a letter that appears in the phrase to
                    reveal every instance of it. Crack every unique letter and the phrase
                    resolves.
                </p>
                <p class="sec-body">
                    Not every code in the key belongs to a letter that's actually in your
                    phrase. Guess wrong and you eat a time penalty — and if the code you
                    guessed decodes to a real (but irrelevant) letter, that letter gets marked
                    dead in the key so you don't waste another attempt on it.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-green">WIN</span>
                    <span class="rule-val hl-green">Crack every unique letter in the phrase (LETTERS CRACKED reaches its target) before the timer runs out.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">FAIL</span>
                    <span class="rule-val hl-red">The timer hits 0 before the phrase is fully decrypted.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TIMER</span>
                    <span class="rule-val">A single countdown runs for the whole node — there's no separate trace meter. Every wrong guess subtracts from it directly.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">KEY INSIGHT</span>
                    <span class="rule-val">The cipher key shows all 26 letters up front. Cross-reference the blanks in the phrase against likely letters before you start guessing codes blind.</span>
                </div>
            </section>

            <!-- ── THE KEY ───────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'key'">
                <h2 class="sec-title">CIPHER KEY</h2>
                <p class="sec-body">
                    Every letter A&ndash;Z is listed with its scrambled 2-character code. Type a
                    code and submit it — if it matches a letter that's actually in the phrase,
                    every blank for that letter lights up and reveals it. The key updates live
                    as you work:
                </p>

                <div class="vec-block">
                    <div class="vec-id">SOLVED — GREEN</div>
                    <div class="vec-type">CORRECT, IN THIS PHRASE</div>
                    <div class="vec-desc">The letter is confirmed and revealed everywhere it appears in the phrase. No reason to guess its code again.</div>
                </div>
                <div class="vec-block vec-block--volatile">
                    <div class="vec-id">RULED OUT — RED</div>
                    <div class="vec-type">VALID CODE, WRONG PHRASE</div>
                    <div class="vec-desc">The code you typed decoded to a real letter, but that letter doesn't appear anywhere in this phrase. It's marked dead — retrying it is a free no-penalty acknowledgment, not another time hit.</div>
                </div>
                <div class="vec-block">
                    <div class="vec-id">FREE LETTERS</div>
                    <div class="vec-type">GRANTED AT LOW ICE</div>
                    <div class="vec-desc">A handful of letters are revealed automatically before the timer starts, scaled to the node's ICE rating — see DIFFICULTY.</div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">ATTEMPT LOG</h3>
                <p class="sec-body">
                    Every submitted code — correct, wrong, or repeated — scrolls into the
                    attempt log below the input. Use it to double-check what you've already
                    tried instead of relying on memory alone.
                </p>
            </section>

            <!-- ── CONTROLS ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'controls'">
                <h2 class="sec-title">CONTROLS</h2>
                <p class="sec-body">
                    Type a 2-character code into the <span class="hl-green">DECRYPT KEY</span>
                    field and press <span class="hl-green">Enter</span> or click
                    <span class="hl-green">[ SUBMIT ]</span>. Codes are not case-sensitive.
                </p>
                <p class="sec-body">
                    A correct guess reveals every occurrence of that letter in the phrase
                    immediately — there's no partial credit or single-instance reveal. A wrong
                    guess costs 15 seconds off the clock, whether the code was invalid outright
                    or just belonged to a letter that isn't in play.
                </p>

                <h3 class="subsec-title">TIMING BY ICE LEVEL</h3>
                <div class="sec-rule">
                    <span class="rule-key">BASE TIMER</span>
                    <span class="rule-val">240 seconds at ICE 3, dropping 30 seconds per ICE tier above that — 90 seconds at ICE 8.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">WRONG GUESS</span>
                    <span class="rule-val">&minus;15 seconds per wrong code. Retrying a code you've already ruled out or already solved costs nothing.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Scan the phrase's blanks for short, common words before guessing — a
                    3-letter blank flanked by spaces is worth testing common short words
                    against. Confirming or ruling out a letter narrows the whole board, not
                    just one blank.
                </div>
            </section>

            <!-- ── DIFFICULTY ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'difficulty'">
                <h2 class="sec-title">DIFFICULTY BY ICE LEVEL</h2>
                <p class="sec-body">
                    Difficulty scales on two axes at once — the node's ICE rating shrinks the
                    starting timer and reduces how many free letters you're handed before the
                    clock starts.
                </p>

                <div class="sec-rule"><span class="rule-key">ICE 3</span><span class="rule-val">240s timer — 3 free letters</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 4</span><span class="rule-val">210s timer — 3 free letters</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 5</span><span class="rule-val">180s timer — 2 free letters</span></div>
                <div class="sec-rule"><span class="rule-key">ICE 6</span><span class="rule-val">150s timer — 2 free letters</span></div>
                <div class="sec-rule"><span class="rule-key hl-amber">ICE 7</span><span class="rule-val hl-amber">120s timer — 0 free letters</span></div>
                <div class="sec-rule"><span class="rule-key hl-red">ICE 8</span><span class="rule-val hl-red">90s timer — 0 free letters (current max)</span></div>

                <div class="sec-callout" style="margin-top:20px">
                    <span class="callout-label">HIGH-ICE WARNING</span>
                    At ICE 7&ndash;8 you're working the full phrase blind with a fraction of the
                    time. Every wrong guess at &minus;15s is proportionally far more costly on a
                    90-second clock than a 240-second one — confirm your best guesses before
                    submitting rather than guessing rapid-fire.
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
    { id: 'key',        label: 'CIPHER KEY' },
    { id: 'controls',   label: 'CONTROLS'   },
    { id: 'difficulty', label: 'DIFFICULTY' },
];
</script>

<style scoped>
.clg-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.clg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 100, 0.1);
    flex-shrink: 0;
}
.clg-title { font-size: 12px; color: #00ff9d; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,100,0.3); }
.clg-sub   { font-size: 9px;  color: rgba(0,255,100,0.5); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.clg-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.clg-nav-btn {
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
.clg-nav-btn:hover  { color: rgba(0,255,100,0.85); background: rgba(0,255,100,0.03); }
.clg-nav-btn.active { color: #00ff9d; background: rgba(0,255,100,0.05); border-bottom: 2px solid #00ff9d; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.clg-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.clg-content::-webkit-scrollbar       { width: 3px; }
.clg-content::-webkit-scrollbar-thumb { background: rgba(0,255,100,0.1); }

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
