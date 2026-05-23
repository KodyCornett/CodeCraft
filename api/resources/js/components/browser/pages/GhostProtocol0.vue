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

                <!-- Skip hint — shown after the first few lines -->
                <div v-if="visibleLines.length > 3" class="gp0-skip-hint">
                    click anywhere to skip
                </div>
            </div>
        </div>

        <!-- ── READY PHASE ─────────────────────────────────────────────────────── -->
        <Transition name="gp0-reveal">
            <div v-if="phase === 'ready'" class="gp0-page">

                <header class="gp0-header">
                    <div class="gp0-header-left">
                        <span class="gp0-mission-tag">MISSION // GHOST_PROTOCOL_0</span>
                        <span class="gp0-header-sub">New runner orientation — complete all objectives before going dark</span>
                    </div>
                    <div class="gp0-header-right">
                        <span class="gp0-status">◉ ACTIVE</span>
                        <button class="gp0-replay-btn" @click.stop="replayBoot" title="Replay intro">↺</button>
                    </div>
                </header>

                <nav class="gp0-nav">
                    <button
                        v-for="sec in sections"
                        :key="sec.id"
                        class="gp0-nav-btn"
                        :class="{ active: activeSection === sec.id }"
                        @click.stop="activeSection = sec.id"
                    >{{ sec.label }}</button>
                </nav>

                <div class="gp0-content">

                    <!-- ── BRIEFING ──────────────────────────────────────────── -->
                    <section v-if="activeSection === 'briefing'">
                        <div class="gp0-obj-list">
                            <div class="gp0-obj-header">MISSION OBJECTIVES</div>
                            <div v-for="obj in objectives" :key="obj.id" class="gp0-obj-row">
                                <span class="gp0-obj-bullet">▸</span>
                                <span class="gp0-obj-text">{{ obj.label }}</span>
                                <span class="gp0-obj-ref" v-if="obj.ref">→ {{ obj.ref }}</span>
                            </div>
                        </div>
                    </section>

                    <!-- ── THE HUD ───────────────────────────────────────────── -->
                    <section v-if="activeSection === 'hud'">
                        <h2 class="sec-title">THE HUD — KNOW YOUR READOUTS</h2>
                        <p class="sec-body">
                            The HUD bar runs across the top of your viewport at all times.
                            Learn to read it at a glance — in a live run you will not have time to stop and check.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key stat--ss">SS BAR</span>
                            <span class="rule-val">System Stability — your rig's health. Failed hacks and PvP losses chip it down. At 0: <strong class="hl-red">Critical System Failure</strong> — pocket creds wiped, bounty cleared, teleported to your last Street Doc. Watch it constantly.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--creds">◈ CREDS</span>
                            <span class="rule-val">Two pools. <strong class="hl-green">Wallet</strong> is safe — banked at a Street Doc. <strong class="hl-amber">Pocket</strong> is at risk — anything unbanked is lost on a PvP kill. Bank early, bank often.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--uplink">UPLINK</span>
                            <span class="rule-val">Movement resource. Each Jack In costs 1. At zero you cannot move. Hack Uplink caches on nodes to recover it.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--bounty">★ BOUNTY</span>
                            <span class="rule-val">Your heat level. Climbs with every successful hack. At ★1 ICE starts sending pings that reveal your position. At ★4 it's Open Season — every runner on the grid is notified.</span>
                        </div>
                        <div class="sec-callout sec-callout--amber">
                            <span class="callout-label">TIP</span>
                            SS degradation also weakens stats. Every 20% SS lost strips 1 point from CPU, RAM, Firewall, and OS. A limping rig fails breaches a healthy rig handles cleanly. Visit a Street Doc before you bottom out.
                        </div>
                    </section>

                    <!-- ── MOVEMENT ──────────────────────────────────────────── -->
                    <section v-if="activeSection === 'movement'">
                        <h2 class="sec-title">MOVEMENT — INSPECT FIRST, COMMIT SECOND</h2>
                        <p class="sec-body">
                            Movement is a two-step process. A single click on any node <strong class="hl-cyan">inspects</strong> it — you
                            see its ICE rating, type and resources in the side panel. Nothing is spent. You are scouting, not moving.
                        </p>
                        <p class="sec-body">
                            Only when you click <strong class="hl-green">[ JACK IN ]</strong> at the bottom of the panel
                            do you actually move to that node. That is when 1 Uplink is deducted.
                            Inspect as many nodes as you like before committing to a single step.
                        </p>

                        <div class="gp0-flow">
                            <div class="gp0-flow-step">
                                <span class="gf-num">1</span>
                                <div class="gf-body">
                                    <div class="gf-label">CLICK ANY NODE</div>
                                    <div class="gf-desc">Opens the side panel. Shows ICE level, type, available resources. No Uplink spent.</div>
                                </div>
                            </div>
                            <div class="gp0-flow-arrow">▼</div>
                            <div class="gp0-flow-step">
                                <span class="gf-num">2</span>
                                <div class="gf-body">
                                    <div class="gf-label">READ THE ICE</div>
                                    <div class="gf-desc">
                                        <span class="hl-green">Green ICE (1–3)</span> — low risk, safe for new runners.<br/>
                                        <span class="hl-amber">Amber ICE (4–6)</span> — moderate. Expect SS hits on breach failure.<br/>
                                        <span class="hl-red">Red ICE (7–9)</span> — high danger. Do not attempt nodes more than 4 ICE above your CPU.
                                    </div>
                                </div>
                            </div>
                            <div class="gp0-flow-arrow">▼</div>
                            <div class="gp0-flow-step">
                                <span class="gf-num">3</span>
                                <div class="gf-body">
                                    <div class="gf-label">PLAN YOUR PATH</div>
                                    <div class="gf-desc">Click multiple nodes in sequence to scout a route before spending a single Uplink. Remote nodes (more than one hop away) show <span class="hl-cyan">○ REMOTE NODE</span> — still free to inspect. Only adjacent nodes show <span class="hl-green">[ JACK IN ]</span>.</div>
                                </div>
                            </div>
                            <div class="gp0-flow-arrow">▼</div>
                            <div class="gp0-flow-step">
                                <span class="gf-num">4</span>
                                <div class="gf-body">
                                    <div class="gf-label">JACK IN</div>
                                    <div class="gf-desc">Verify the ICE is acceptable, then press <span class="hl-green">[ JACK IN ]</span>. −1 Uplink. You are now on that node.</div>
                                </div>
                            </div>
                        </div>

                        <div class="sec-callout sec-callout--red" style="margin-top:20px;">
                            <span class="callout-label">CRITICAL</span>
                            You can move <em>through</em> high-ICE nodes without penalty — the SS damage only applies if you hack them. Plan routes that skip high-ICE clusters when your health is low.
                        </div>
                    </section>

                    <!-- ── HACKING ───────────────────────────────────────────── -->
                    <section v-if="activeSection === 'hacking'">
                        <h2 class="sec-title">HACKING — BREACHING CACHES</h2>
                        <p class="sec-body">
                            Once standing on a node (panel shows <span class="hl-green">◉ CONNECTED</span>),
                            its resource caches appear — CREDS, TECH POINTS, UPLINK.
                            Click <strong class="hl-green">[HACK]</strong> next to any available cache to launch a Grid-Breach.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key stat--creds">CREDS</span>
                            <span class="rule-val">Currency paid to your pocket. Bank at a Street Doc to make it safe.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--tech">TECH POINTS</span>
                            <span class="rule-val">Upgrade currency. Spend at CyberDoc to invest in rig stats or unlock commands.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--uplink">UPLINK</span>
                            <span class="rule-val">Restores your movement resource directly. Hack these when running low.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-red">FAIL STATE</span>
                            <span class="rule-val">Failing a breach deals SS damage equal to max(1, nodeICE − Firewall). ICE 6 vs Firewall 1 = 5 SS damage. Stack Firewall to reduce it.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">ABORT</span>
                            <span class="rule-val">You can abort a Grid-Breach at any time. No reward, no SS damage. Use it if a node is above your range.</span>
                        </div>
                        <div class="sec-callout sec-callout--amber">
                            <span class="callout-label">NOTE</span>
                            Each successful hack on a node raises its effective ICE over time. Farming the same node gets progressively harder. Move around the map to keep ICE levels manageable.
                        </div>
                    </section>

                    <!-- ── GRID-BREACH ───────────────────────────────────────── -->
                    <section v-if="activeSection === 'gridbreach'">
                        <h2 class="sec-title">GRID-BREACH — THE BREACH ENGINE</h2>
                        <p class="sec-body">
                            Every hack and every PvP duel runs through Grid-Breach.
                            You are given a target sequence of hex values and must locate each one
                            on a 10×10 coordinate grid before the timer expires.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key hl-cyan">COORDINATES</span>
                            <span class="rule-val">Column (A–J) + row (1–10). Type letter first: <span class="hl-green">F6</span>, <span class="hl-green">A10</span>. Hit Enter or [SUBMIT].</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-cyan">SEQUENCE</span>
                            <span class="rule-val">Find hex values left to right in order. The current target pulses brighter. Confirm a coord to advance.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">SCRAMBLE</span>
                            <span class="rule-val">Every 5 seconds the board reshuffles. Confirmed cells stay locked. Target is always guaranteed to appear after a scramble.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--cpu">CPU vs ICE</span>
                            <span class="rule-val">Determines sequence length and timer penalty. Cannot attempt a node with ICE more than 4 above your CPU.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--ram">RAM</span>
                            <span class="rule-val">Sets total game time: base 30s + (RAM × 5s).</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-red">LOCKED ROWS</span>
                            <span class="rule-val">At ICE 5+, some rows are barricaded. Coordinates there are rejected. Targets are never seeded in locked rows.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-amber">GLITCH ROWS</span>
                            <span class="rule-val">Corrupted rows penalise time on use: −2s on a correct coord, −3s on a wrong one. Avoid them.</span>
                        </div>
                        <div class="sec-callout">
                            <span class="callout-label">FULL GUIDE</span>
                            Detailed documentation — all row modifiers, PvP mode, every stat interaction — at
                            <span class="hl-cyan">splice://sys.local/guide/gridbreach</span>. Read it before hitting ICE 5+ nodes.
                        </div>
                    </section>

                    <!-- ── BOUNTY ────────────────────────────────────────────── -->
                    <section v-if="activeSection === 'bounty'">
                        <h2 class="sec-title">BOUNTY — MANAGING YOUR HEAT</h2>
                        <p class="sec-body">
                            Every successful hack adds to your hack count. Cross a threshold and your bounty star rating climbs.
                            Higher stars mean tighter ICE tracking and a bigger target on your back.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key">★1 — 10 HACKS</span>
                            <span class="rule-val">ICE starts watching. Ping radius begins. Creds multiplier ×1.25.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">★2 — 15 HACKS</span>
                            <span class="rule-val">Pings fire every 2 moves. Multiplier ×1.50.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">★3 — 20 HACKS</span>
                            <span class="rule-val">Priority ICE target. Multiplier ×1.75. Pings tighten.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-red">★4 — 25 HACKS</span>
                            <span class="rule-val">OPEN SEASON. All runners on the grid are notified. Multiplier ×2.00.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-red">★5 — 30 HACKS</span>
                            <span class="rule-val">Maximum heat. Pings fire every move. Multiplier ×2.25.</span>
                        </div>
                        <div class="sec-callout sec-callout--amber">
                            <span class="callout-label">STRATEGY</span>
                            High bounty means high payouts — but also makes you the most valuable PvP target on the grid. Visit a Street Doc before ★4. Banking resets your bounty to zero. Time it right to capture the multiplier bonus without going Open Season.
                        </div>
                    </section>

                    <!-- ── STREET DOC ────────────────────────────────────────── -->
                    <section v-if="activeSection === 'streetdoc'">
                        <h2 class="sec-title">STREET DOC — YOUR SAFE HARBOUR</h2>
                        <p class="sec-body">
                            Street Doc nodes are safe zones. No PvP can be initiated here.
                            Bank your creds, reset your bounty, repair your rig.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key hl-green">BANK CREDS</span>
                            <span class="rule-val">Converts all pocket creds to wallet creds. Anything not banked is lost on a PvP kill. Bank after every major run.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">BOUNTY RESET</span>
                            <span class="rule-val">Visiting resets your bounty to zero. ICE stops tracking you. Use before heat becomes unmanageable.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key stat--ss">REPAIR SS</span>
                            <span class="rule-val">Pay creds to restore System Stability. 150₡ per 25% SS lost (600₡ for a full repair from 0).</span>
                        </div>
                        <div class="sec-callout">
                            <span class="callout-label">CRITICAL</span>
                            SS at 0 triggers Critical System Failure. Pocket creds wiped, bounty cleared, teleported to your last Street Doc. SS stays at 0 until you pay for repairs. You cannot hack until fixed.
                        </div>
                    </section>

                    <!-- ── SPLICE ────────────────────────────────────────────── -->
                    <section v-if="activeSection === 'splice'">
                        <h2 class="sec-title">SPLICE — IN-GAME BROWSER</h2>
                        <p class="sec-body">
                            SPLICE is the in-game network browser. Open it from the NavBar at the bottom of your screen.
                            All game events continue running while it is open — close it before committing to a hack or a move.
                        </p>
                        <div class="sec-rule">
                            <span class="rule-key">THIS MISSION</span>
                            <span class="rule-val"><span class="hl-cyan">splice://sys.local/tutorial</span> — You are here.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key hl-red">GRID-BREACH</span>
                            <span class="rule-val"><span class="hl-cyan">splice://sys.local/guide/gridbreach</span> — Full breach manual. Read before hitting ICE 5+.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">STAT REFERENCE</span>
                            <span class="rule-val"><span class="hl-cyan">splice://sys.local/guide/stats</span> — How every stat interacts with every system.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">SYSTEM MANUAL</span>
                            <span class="rule-val"><span class="hl-cyan">splice://sys.local/manual</span> — Movement, hacking, resources, combat overview.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">YOUR RIG</span>
                            <span class="rule-val"><span class="hl-cyan">splice://sys.local/rig</span> — Current rig stats, upgrade levels, effective values.</span>
                        </div>
                        <div class="sec-rule">
                            <span class="rule-key">CYBERDOC SHOP</span>
                            <span class="rule-val"><span class="hl-cyan">splice://cyberdoc.net/shop</span> — Purchase commands, hardware, software. Wallet creds only.</span>
                        </div>
                    </section>

                </div>
            </div>
        </Transition>

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

defineProps({ url: { type: String, default: '' } });

// ── Boot sequence lines ────────────────────────────────────────────────────────
const BOOT_LINES = [
    { text: '',                                                          cls: '' },
    { prefix: '>', text: 'INITIALISING SPLICE TUNNEL…',                 cls: 'gp0-line--dim',   delay: 0    },
    { prefix: '>', text: 'ROUTING VIA NODE CLUSTER NW-14…',             cls: 'gp0-line--dim',   delay: 320  },
    { prefix: '>', text: 'LAYER 7 ENCRYPTION ACTIVE',                   cls: 'gp0-line--dim',   delay: 640  },
    { text: '',                                                          cls: '',                delay: 900  },
    { prefix: '>', text: 'AUTHENTICATING RUNNER CREDENTIALS…',          cls: 'gp0-line--cyan',  delay: 1050 },
    { prefix: '>', text: 'IDENTITY VERIFIED  //  ACCESS GRANTED',       cls: 'gp0-line--green', delay: 1500 },
    { text: '',                                                          cls: '',                delay: 1700 },
    { prefix: '>', text: 'FETCHING SECURE TRANSMISSION…',               cls: 'gp0-line--dim',   delay: 1850 },
    { prefix: '>', text: 'DECRYPTING  ████████████████  DONE',          cls: 'gp0-line--dim',   delay: 2300 },
    { text: '',                                                          cls: '',                delay: 2600 },
    { text: '══════════════════════════════════════════════', cls: 'gp0-line--sep',   delay: 2750 },
    { text: 'INCOMING TRANSMISSION',                         cls: 'gp0-line--header',delay: 2900 },
    { text: 'SOURCE  : UNKNOWN // ENCRYPTED',                cls: 'gp0-line--meta',  delay: 3050 },
    { text: 'SUBJECT : GHOST_PROTOCOL_0 — NEW RUNNER ORIENTATION', cls: 'gp0-line--meta', delay: 3200 },
    { text: '══════════════════════════════════════════════', cls: 'gp0-line--sep',   delay: 3400 },
    { text: '',                                                          cls: '',                delay: 3550 },
    { text: 'You are connected to the grid.',                            cls: 'gp0-line--msg',   delay: 3700 },
    { text: 'Your rig is online. Your uplink is live.',                  cls: 'gp0-line--msg',   delay: 4000 },
    { text: '',                                                          cls: '',                delay: 4200 },
    { text: 'The city\'s node network is open — hundreds of data',      cls: 'gp0-line--msg',   delay: 4350 },
    { text: 'caches waiting to be breached. ICE stands between',        cls: 'gp0-line--msg',   delay: 4550 },
    { text: 'you and every one of them. Your job is to stay ahead.',    cls: 'gp0-line--msg',   delay: 4750 },
    { text: '',                                                          cls: '',                delay: 4950 },
    { text: 'Complete the protocol before running anything hot.',        cls: 'gp0-line--amber', delay: 5100 },
    { text: 'Runners who skip orientation don\'t last long.',           cls: 'gp0-line--amber', delay: 5300 },
    { text: '',                                                          cls: '',                delay: 5500 },
    { text: '// END TRANSMISSION',                                       cls: 'gp0-line--dim',   delay: 5650 },
    { text: '',                                                          cls: '',                delay: 5800 },
    { prefix: '>', text: 'LOADING GHOST_PROTOCOL_0…',                   cls: 'gp0-line--cyan',  delay: 5950 },
];

const TOTAL_DURATION = 6600; // ms before auto-advancing to ready

// ── State ──────────────────────────────────────────────────────────────────────
const phase        = ref('booting');
const visibleLines = ref([]);
const bootDone     = ref(false);
const activeSection = ref('briefing');

const sections = [
    { id: 'briefing',   label: 'BRIEFING'    },
    { id: 'hud',        label: 'THE HUD'     },
    { id: 'movement',   label: 'MOVEMENT'    },
    { id: 'hacking',    label: 'HACKING'     },
    { id: 'gridbreach', label: 'GRID-BREACH' },
    { id: 'bounty',     label: 'BOUNTY'      },
    { id: 'streetdoc',  label: 'STREET DOC'  },
    { id: 'splice',     label: 'SPLICE'      },
];

const objectives = [
    { id: 1, label: 'Learn the HUD — SS, Creds, Uplink, Bounty',            ref: 'THE HUD'   },
    { id: 2, label: 'Inspect three nodes before spending a single Uplink',   ref: 'MOVEMENT'  },
    { id: 3, label: 'Plan and execute your first Jack In',                   ref: 'MOVEMENT'  },
    { id: 4, label: 'Hack your first resource cache',                        ref: 'HACKING'   },
    { id: 5, label: 'Read the Grid-Breach guide before tackling ICE 5+',    ref: 'GRID-BREACH'},
    { id: 6, label: 'Bank your creds at a Street Doc',                       ref: 'STREET DOC'},
    { id: 7, label: 'Identify your bounty level before it reaches ★3',      ref: 'BOUNTY'    },
    { id: 8, label: 'Open SPLICE and locate splice://sys.local/rig',         ref: 'SPLICE'    },
];

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
            if (i === BOOT_LINES.length - 1) {
                bootDone.value = true;
            }
        }, line.delay ?? i * 120);
        _timers.push(t);
    });

    // Auto-advance to ready after all lines + short pause
    const done = setTimeout(() => {
        phase.value = 'ready';
    }, TOTAL_DURATION);
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

/* Line type colours */
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
.gp0-status       { font-size: 8px; color: #00FF88; letter-spacing: 0.14em; text-shadow: 0 0 8px rgba(0,255,136,0.6); animation: status-pulse 3s ease-in-out infinite; }
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

/* ── Nav ─────────────────────────────────────────────────────────────────────── */
.gp0-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.gp0-nav-btn {
    padding: 8px 13px;
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
.gp0-nav-btn:hover  { color: rgba(0,255,255,0.7); background: rgba(0,255,255,0.03); }
.gp0-nav-btn.active { color: #00FFFF; background: rgba(0,255,255,0.05); border-bottom: 2px solid #00FFFF; }

/* ── Content ─────────────────────────────────────────────────────────────────── */
.gp0-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.gp0-content::-webkit-scrollbar       { width: 3px; }
.gp0-content::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Objectives ──────────────────────────────────────────────────────────────── */
.gp0-obj-list   { display: flex; flex-direction: column; }
.gp0-obj-header { font-size: 8px; color: rgba(0,255,255,0.3); letter-spacing: 0.2em; margin-bottom: 10px; }
.gp0-obj-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(0,255,255,0.05);
}
.gp0-obj-bullet { font-size: 8px; color: #00FF88; flex-shrink: 0; }
.gp0-obj-text   { font-size: 9px; color: rgba(255,255,255,0.55); letter-spacing: 0.04em; flex: 1; }
.gp0-obj-ref    { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.07em; flex-shrink: 0; }

/* ── Flow steps ──────────────────────────────────────────────────────────────── */
.gp0-flow        { display: flex; flex-direction: column; }
.gp0-flow-step   { display: flex; align-items: flex-start; gap: 14px; padding: 14px; border: 1px solid rgba(0,255,255,0.07); background: rgba(0,255,255,0.015); }
.gp0-flow-arrow  { font-size: 10px; color: rgba(0,255,255,0.15); padding: 4px 14px; }
.gf-num          { font-size: 18px; color: rgba(0,255,255,0.15); flex-shrink: 0; line-height: 1.2; width: 24px; }
.gf-body         { display: flex; flex-direction: column; gap: 5px; }
.gf-label        { font-size: 9px; color: #00FFFF; letter-spacing: 0.12em; }
.gf-desc         { font-size: 9px; color: rgba(255,255,255,0.45); letter-spacing: 0.04em; line-height: 1.75; }

/* ── Typography ──────────────────────────────────────────────────────────────── */
.sec-title {
    font-size: 13px; color: #00FFFF; letter-spacing: 0.12em;
    margin: 0 0 16px; font-weight: normal;
}
.sec-body {
    font-size: 10px; color: rgba(255,255,255,0.5); letter-spacing: 0.04em;
    line-height: 1.85; margin: 0 0 14px; max-width: 640px;
}
.sec-rule {
    display: flex; gap: 20px; padding: 9px 0;
    border-bottom: 1px solid rgba(0,255,255,0.05); align-items: baseline;
}
.rule-key {
    font-size: 9px; color: rgba(0,255,255,0.5); letter-spacing: 0.1em;
    width: 160px; flex-shrink: 0;
}
.rule-val { font-size: 9px; color: rgba(255,255,255,0.45); letter-spacing: 0.04em; line-height: 1.6; }

/* ── Callouts ────────────────────────────────────────────────────────────────── */
.sec-callout {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 10px 14px; margin-top: 16px;
    border: 1px solid rgba(255,51,51,0.2); background: rgba(255,51,51,0.03);
    font-size: 9px; color: rgba(255,51,51,0.6); line-height: 1.65; letter-spacing: 0.04em;
}
.sec-callout--amber { border-color: rgba(255,179,0,0.2); background: rgba(255,179,0,0.03); color: rgba(255,179,0,0.6); }
.sec-callout--red   { border-color: rgba(255,51,51,0.25); background: rgba(255,51,51,0.04); color: rgba(255,51,51,0.65); }
.callout-label      { font-size: 7px; letter-spacing: 0.14em; flex-shrink: 0; padding-top: 2px; opacity: 0.7; }

/* ── Stat / colour helpers ───────────────────────────────────────────────────── */
.stat--ss      { color: #7DF9FF !important; }
.stat--creds   { color: #00FF88 !important; }
.stat--tech    { color: #7DF9FF !important; }
.stat--uplink  { color: #00FFFF !important; }
.stat--bounty  { color: #FFB300 !important; }
.hl-cyan  { color: #00FFFF !important; }
.hl-green { color: #00FF88 !important; }
.hl-amber { color: #FFB300 !important; }
.hl-red   { color: #FF3333 !important; }
</style>
