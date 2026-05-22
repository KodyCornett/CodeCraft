<template>
    <div class="guide-page">

        <header class="guide-header">
            <span class="guide-title">RIG &amp; STAT REFERENCE</span>
            <span class="guide-sub">How your rig stats work and what they control</span>
        </header>

        <div class="guide-body">

            <!-- ── Stat Overview ─────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">CORE STATS</div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>STAT</span>
                        <span>WHAT IT CONTROLS</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--cpu">CPU</span>
                        <span>Node ICE gate — cannot attempt nodes with ICE more than 4 above your CPU. PvP damage multiplier. GridBreach sequence length. Command level cap — the highest command level you can equip equals your effective CPU.</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--ram">RAM</span>
                        <span>Active loadout slots — effective RAM = max commands running simultaneously. GridBreach game length — base 30s + (RAM × 5s).</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--os">OS</span>
                        <span>Reduces ICE ping accuracy — high OS means hunters get a district, not your exact node. GridBreach input window — base 3s + (OS × 0.3s) per move.</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--storage">STORAGE</span>
                        <span>Command library — Storage × 2 = total commands you can own. Pocket creds carry cap — limits what you can earn before banking. Item inventory — slots for uninstalled peripherals and gear. <span class="g-highlight">Storage never degrades from SS damage.</span></span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--firewall">FIREWALL</span>
                        <span>PvE damage shield — each Firewall point absorbs 1 SS from failed hack damage. PvP damage absorption — each Firewall point blocks 5 SS from incoming combat damage. The only stat that directly reduces damage taken.</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--uplink">UPLINK</span>
                        <span>Movement points per turn. Chassis-locked — cannot be boosted by peripherals or player points. Only increases by upgrading to a higher-tier chassis.</span>
                    </div>
                </div>
            </section>

            <!-- ── Effective Stat ─────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">EFFECTIVE STAT FORMULA</div>
                <div class="g-formula">
                    Effective Stat = Chassis Base + Player Points + Peripheral Boost
                </div>
                <div class="g-prose">
                    All three layers stack additively, capped by the chassis ceiling for each stat. Player points are invested at the Street Doc — you cannot change your loadout or reallocate stats in the field.
                </div>
            </section>

            <!-- ── ICE vs CPU ─────────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">ICE vs CPU — HACK DIFFICULTY</div>
                <div class="g-prose">
                    Each node has an ICE rating. Your CPU is compared against it to determine difficulty.
                </div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>CPU vs NODE ICE</span>
                        <span>HACKS PER RESOURCE</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">CPU higher by 2+</span>
                        <span class="g-green">1 hack</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">CPU equal to ICE</span>
                        <span class="g-green">1 hack</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">CPU lower by 1–2</span>
                        <span class="g-amber">2 hacks</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">CPU lower by 3–4</span>
                        <span class="g-amber">3 hacks</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">CPU lower by 5+</span>
                        <span class="g-red">Cannot attempt</span>
                    </div>
                </div>
            </section>

            <!-- ── System Stability ───────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">SYSTEM STABILITY (SS)</div>
                <div class="g-formula">
                    Max SS = 100 for all rigs — chassis and OS do not affect this value
                </div>
                <div class="g-prose">
                    SS is your rig's health pool. Failed hacks (PvE) and losing PvP combat both reduce it. Every 20% lost temporarily strips 1 point from CPU, RAM, Firewall, and OS — your rig degrades as it takes damage. At 0 SS: Critical System Failure.
                </div>
                <div class="g-callout g-callout--warn">
                    <span class="callout-label">CRITICAL</span>
                    At 0 SS your pocket creds are wiped, bounty is reset, and you are teleported to your last Street Doc. SS stays at 0 — you must pay for a repair before hacking again.
                </div>
            </section>

            <!-- ── SS Degradation ─────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">SYSTEM DEGRADATION TIERS</div>
                <div class="g-prose">Penalties are cumulative. Storage is never affected — it is physical capacity, not performance.</div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>SS REMAINING</span>
                        <span>CUMULATIVE PENALTY</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">80 SS  (−20%)</span>
                        <span>CPU −1, RAM −1, Firewall −1, OS −1</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">60 SS  (−40%)</span>
                        <span>CPU −2, RAM −2, Firewall −2, OS −2</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-red">40 SS  (−60%)</span>
                        <span>CPU −3, RAM −3, Firewall −3, OS −3</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-red">20 SS  (−80%)</span>
                        <span>CPU −4, RAM −4, Firewall −4, OS −4</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-red">0 SS</span>
                        <span class="g-red">CRITICAL FAILURE — pocket wiped, teleport to Street Doc</span>
                    </div>
                </div>
                <div class="g-callout g-callout--warn">
                    <span class="callout-label">WARNING</span>
                    Degradation is a death spiral. Lower Firewall means more damage per hit, which drops Firewall further. When RAM drops below your active command count, your highest loadout slots go offline. When CPU drops below a command's level, that command deactivates.
                </div>
            </section>

            <!-- ── GridBreach Stats ───────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">GRIDBREACH — STAT EFFECTS</div>
                <div class="g-prose">GridBreach is the hacking minigame for both PvE node breaches and PvP duels. Stats affect the game directly.</div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>STAT</span>
                        <span>GRIDBREACH EFFECT</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--cpu">CPU</span>
                        <span>Sequence length — higher CPU = more complex sequence required to breach</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--ram">RAM</span>
                        <span>Game length — base 30s + (effective RAM × 5s). More RAM = more time to complete the breach</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--os">OS</span>
                        <span>Input window — base 3s + (effective OS × 0.3s) per move. More OS = more time per decision</span>
                    </div>
                    <div class="g-table-row">
                        <span class="stat-name stat--firewall">FIREWALL</span>
                        <span>Shields your hexakeys from opponent commands in PvP breaches</span>
                    </div>
                </div>
            </section>

            <!-- ── PvP Combat ─────────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">PVP — SS DAMAGE FORMULA</div>
                <div class="g-formula">
                    Damage = max(15,  20 + (winner CPU × 5) − (loser Firewall × 5))
                </div>
                <div class="g-prose">
                    The base 20 is unavoidable — Firewall cannot cancel the flat cost. CPU and Firewall scale symmetrically. Equal investments cancel out, leaving the 20 base. Each fight costs at least 15 SS — there is no safe way to absorb unlimited PvP.
                </div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>MATCHUP</span>
                        <span>SS DAMAGE</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Starter vs Starter  (CPU 3 / FW 1)</span>
                        <span class="g-amber">30 SS — ~3 fights to critical</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Max CPU vs no Firewall</span>
                        <span class="g-red">85+ SS — near one-shot</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Equal CPU and Firewall investment</span>
                        <span class="g-green">20 SS floor — 5 fights to critical</span>
                    </div>
                </div>
            </section>

            <!-- ── OS & Ping Accuracy ─────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">OS — PING ACCURACY</div>
                <div class="g-prose">
                    OS does not reduce how often ICE pings occur — it reduces how precise the location data is. Pings are inevitable. OS just makes them harder to act on.
                </div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>OS LEVEL</span>
                        <span>WHAT HUNTERS RECEIVE</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Low (1–3)</span>
                        <span class="g-red">Exact node name — e.g. FOX_THEATRE_WIFI_15</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Mid (4–6)</span>
                        <span class="g-amber">General area — within a few nodes</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">High (7–9)</span>
                        <span class="g-green">District only — vague enough to require searching</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Open Season</span>
                        <span class="g-red">Always exact node, regardless of OS</span>
                    </div>
                </div>
            </section>

            <!-- ── Storage ───────────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">STORAGE — CAPACITY STAT</div>
                <div class="g-prose">
                    Storage governs what you can carry and own — not how well your system performs. It is the only stat unaffected by SS degradation.
                </div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>STORAGE EFFECT</span>
                        <span>FORMULA</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Command library</span>
                        <span>Effective Storage × 2 = total commands you can own</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Pocket creds cap</span>
                        <span>Limits how much you can carry before banking at Street Doc</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">Item inventory</span>
                        <span>Slots for uninstalled peripherals and consumables</span>
                    </div>
                </div>
                <div class="g-example">
                    Vault chassis (max Storage 15) — 30 commands owned, largest carry cap in game
                </div>
            </section>

            <!-- ── Bounty ─────────────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">BOUNTY SYSTEM</div>
                <div class="g-prose">
                    Bounty builds with every successful hack. It works both for and against you — more pings and more hunters, but a significant loot multiplier on everything you earn. Reach a CyberDoc before getting caught and the bounty wipes clean.
                </div>
                <div class="g-table g-table--3col">
                    <div class="g-table-row g-table-head">
                        <span>BOUNTY LEVEL</span>
                        <span>PING TRIGGER</span>
                        <span>LOOT MULTIPLIER</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">LVL 0</span>
                        <span class="g-dim">None</span>
                        <span>1.0×</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">LVL 1</span>
                        <span class="g-dim">Every 8 moves or every hack</span>
                        <span class="g-amber">1.25×</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">LVL 2</span>
                        <span class="g-dim">Every 6 moves or every hack</span>
                        <span class="g-amber">1.5×</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">LVL 3</span>
                        <span class="g-dim">Every 4 moves or every hack</span>
                        <span class="g-amber">1.75×</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-amber">LVL 4</span>
                        <span class="g-dim">Every 3 moves or every hack</span>
                        <span class="g-amber">2.0×</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-red">OPEN SEASON</span>
                        <span class="g-red">Every 2 moves AND every hack</span>
                        <span class="g-red">2.5×</span>
                    </div>
                </div>
                <div class="g-callout g-callout--warn">
                    <span class="callout-label">OPEN SEASON</span>
                    At max bounty all runners on the network are notified. ICE pings always reveal your exact node regardless of OS. Highest risk, highest reward.
                </div>
            </section>

            <!-- ── Chassis Tiers ─────────────────────────────────────────────── -->
            <section class="g-section">
                <div class="g-section-title">CHASSIS TIERS</div>
                <div class="g-prose">
                    Rigs are purchased as full chassis — you cannot incrementally upgrade the same rig. Each tier jump is a significant leap in base stats, stat caps, and port slot availability. Uplink only increases by buying a higher tier chassis.
                </div>
                <div class="g-table">
                    <div class="g-table-row g-table-head">
                        <span>TIER</span>
                        <span>UPLINK RANGE</span>
                        <span>STAT CAP RANGE</span>
                        <span>MAX POINTS/STAT</span>
                        <span>PORT SLOTS</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">v1.x</span>
                        <span>3–5</span>
                        <span>up to ~5</span>
                        <span>3</span>
                        <span>0–2</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">v2.x</span>
                        <span>5–7</span>
                        <span>up to ~7</span>
                        <span>6</span>
                        <span>2–3</span>
                    </div>
                    <div class="g-table-row">
                        <span class="g-dim">v3.x</span>
                        <span>7–10</span>
                        <span>up to ~10</span>
                        <span>9</span>
                        <span>3–4</span>
                    </div>
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
defineProps({ url: { type: String, default: '' } });
</script>

<style scoped>
.guide-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    color: rgba(0,255,255,0.8);
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.guide-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0,255,255,0.1);
    flex-shrink: 0;
}
.guide-title { font-size: 13px; color: #00FFFF; letter-spacing: 0.12em; text-shadow: 0 0 10px rgba(0,255,255,0.25); }
.guide-sub   { font-size: 9px;  color: rgba(0,255,255,0.28); letter-spacing: 0.1em; }

/* ── Body ───────────────────────────────────────────────────────────────────── */
.guide-body {
    flex: 1;
    overflow-y: auto;
    padding: 0 24px 40px;
    display: flex;
    flex-direction: column;
}
.guide-body::-webkit-scrollbar       { width: 3px; }
.guide-body::-webkit-scrollbar-track { background: transparent; }
.guide-body::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Sections ───────────────────────────────────────────────────────────────── */
.g-section {
    padding: 20px 0 16px;
    border-bottom: 1px solid rgba(0,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.g-section:last-child { border-bottom: none; }

.g-section-title {
    font-size: 9px;
    color: rgba(0,255,255,0.4);
    letter-spacing: 0.2em;
}

/* ── Table ──────────────────────────────────────────────────────────────────── */
.g-table { display: flex; flex-direction: column; gap: 1px; }

.g-table-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 16px;
    padding: 7px 10px;
    font-size: 9px;
    line-height: 1.55;
    border: 1px solid rgba(0,255,255,0.05);
    background: rgba(0,0,0,0.15);
}

/* 3-column tables (bounty) */
.g-table--3col .g-table-row {
    grid-template-columns: 1.2fr 1.5fr 1fr;
}

/* 5-column chassis table (always last section) */
.g-section:last-child .g-table-row {
    grid-template-columns: 0.8fr 0.8fr 1fr 1fr 0.8fr;
}

.g-table-head {
    background: rgba(0,255,255,0.03);
    border-color: rgba(0,255,255,0.1);
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.1em;
}

/* ── Stat name badges ───────────────────────────────────────────────────────── */
.stat-name {
    font-size: 9px;
    letter-spacing: 0.1em;
    font-weight: bold;
}
.stat--cpu      { color: #7DF9FF; }
.stat--ram      { color: #FF69B4; }
.stat--os       { color: #00FF88; }
.stat--storage  { color: #FFB300; }
.stat--firewall { color: #FF6B00; }
.stat--uplink   { color: #00FFFF; }

/* ── Colour helpers ─────────────────────────────────────────────────────────── */
.g-green { color: #00FF88; }
.g-amber { color: #FFB300; }
.g-red   { color: #FF3333; }
.g-dim   { color: rgba(0,255,255,0.45); }

/* ── Formula block ──────────────────────────────────────────────────────────── */
.g-formula {
    font-size: 10px;
    color: #00FFFF;
    letter-spacing: 0.06em;
    padding: 10px 14px;
    border-left: 3px solid rgba(0,255,255,0.3);
    background: rgba(0,255,255,0.03);
}

/* ── Prose ──────────────────────────────────────────────────────────────────── */
.g-prose {
    font-size: 9px;
    color: rgba(0,255,255,0.5);
    letter-spacing: 0.04em;
    line-height: 1.75;
}
.g-highlight { color: rgba(0,255,136,0.8); }

/* ── Example ────────────────────────────────────────────────────────────────── */
.g-example {
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.06em;
    font-style: italic;
    padding-left: 14px;
    border-left: 2px solid rgba(0,255,255,0.1);
}

/* ── Callout ────────────────────────────────────────────────────────────────── */
.g-callout {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 10px 14px;
    border: 1px solid rgba(0,255,136,0.2);
    background: rgba(0,255,136,0.03);
    font-size: 9px;
    color: rgba(0,255,136,0.6);
    line-height: 1.65;
    letter-spacing: 0.04em;
}
.g-callout--warn {
    border-color: rgba(255,51,51,0.25);
    background: rgba(255,51,51,0.03);
    color: rgba(255,51,51,0.6);
}
.callout-label {
    font-size: 7px;
    letter-spacing: 0.14em;
    flex-shrink: 0;
    padding-top: 2px;
    opacity: 0.7;
}
</style>
