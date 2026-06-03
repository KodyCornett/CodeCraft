<template>
    <div class="ph-guide">

        <header class="ph-guide-header">
            <span class="ph-guide-title">◈ PACKET HIJACK // OPERATOR MANUAL</span>
            <span class="ph-guide-sub">Three-phase intrusion protocol — read before your first run</span>
        </header>

        <nav class="ph-guide-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="ph-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="ph-guide-content">

            <!-- ── OVERVIEW ──────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'overview'">
                <h2 class="sec-title">WHAT IS PACKET HIJACK</h2>
                <p class="sec-body">
                    Packet Hijack activates when two runners occupy the same node and one initiates
                    a PvP challenge. Instead of Grid Breach, you enter a live terminal session against
                    the target's system. The attacker works to extract the defender's wallet.
                    The defender receives an alert and watches the intrusion unfold.
                </p>
                <p class="sec-body">
                    The match runs across three sequential phases. You cannot skip phases — each one
                    unlocks the next. Abort at any time with no penalty.
                </p>

                <div class="phase-block phase-block--one">
                    <div class="phase-header">
                        <span class="phase-num">PHASE 1</span>
                        <span class="phase-name">RECON HUNT</span>
                    </div>
                    <p class="phase-desc">
                        You intercept network traffic from the shared node. Several suspect IPs appear
                        on the board. One belongs to your target. Use recon commands to eliminate
                        the fakes and commit with <span class="hl-cmd">inject &lt;ip&gt;</span>.
                    </p>
                </div>

                <div class="phase-block phase-block--two">
                    <div class="phase-header">
                        <span class="phase-num">PHASE 2</span>
                        <span class="phase-name">EXPLOIT CHAIN</span>
                    </div>
                    <p class="phase-desc">
                        The target's system exposes a port board. A hidden chain of 2–3 ports leads
                        to the exfil port (:8080). Probe, deduce, and exploit them in sequence to
                        extract the login credentials, then breach.
                    </p>
                </div>

                <div class="phase-block phase-block--three">
                    <div class="phase-header">
                        <span class="phase-num">PHASE 3</span>
                        <span class="phase-name">FILESYSTEM EXTRACTION</span>
                    </div>
                    <p class="phase-desc">
                        You're inside. The target's wallet is hidden somewhere in the filesystem.
                        Navigate with <span class="hl-cmd">ls</span> and <span class="hl-cmd">cd</span>,
                        then run <span class="hl-cmd">extract</span> when you find it. First to extract wins.
                    </p>
                </div>

                <div class="sec-rule" style="margin-top:20px">
                    <span class="rule-key hl-green">WINNER</span>
                    <span class="rule-val hl-green">Extracts the wallet — receives the defender's full pocket creds.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">LOSER</span>
                    <span class="rule-val hl-red">Pocket creds wiped. Standard PvP SS damage applied. Bounty retained.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DECLINE / ABORT</span>
                    <span class="rule-val">Either player can decline a challenge before it starts. Attacker can abort mid-match. No penalty in either case.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DEFENDER</span>
                    <span class="rule-val">Receives a critical alert banner when the intrusion begins. Cannot block the attacker directly — defence comes from having higher stats that make each phase harder.</span>
                </div>
            </section>

            <!-- ── PHASE 1 ────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'phase1'">
                <h2 class="sec-title">PHASE 1 — RECON HUNT</h2>
                <p class="sec-body">
                    The board populates after you run <span class="hl-cmd">netstat --active</span>.
                    You see a grid of suspect IPs, each with four attributes that start hidden.
                    Your job is to figure out which IP belongs to your target before committing.
                    A wrong inject locks your input and wastes time.
                </p>

                <h3 class="subsec-title">SUSPECT CARD ATTRIBUTES</h3>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">PNG</span>
                    <span class="rule-val">Ping latency in ms. A <span class="hl-green">very low value (≤20ms)</span> means the host is active and local — a strong signal. TIMEOUT means the IP is unresponsive and likely dead.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--ram">HPS</span>
                    <span class="rule-val">Hop count from traceroute. <span class="hl-green">Low hop count (1–3)</span> means the target is on your local network segment. High hop counts suggest a distant or decoy host.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--os">ARP</span>
                    <span class="rule-val">Time since last ARP activity. <span class="hl-green">JUST NOW</span> means a device was active within 5 seconds — consistent with a live player. Values over 60s are stale and probably not your target.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--firewall">CLASS</span>
                    <span class="rule-val">Whois chassis class. If the target has low OS, their chassis type is revealed unredacted. High-OS targets show REDACTED. Useful for confirming a match when you already have a lead.</span>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">COMMANDS</h3>
                <div class="cmd-block">
                    <div class="cmd-sig">netstat --active</div>
                    <div class="cmd-body">Populate the suspect board. Run this first — nothing else works until you do.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">ping &lt;ip&gt;</div>
                    <div class="cmd-body">Reveals PNG (latency). Fast response = active host on local segment. The quickest first move.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">traceroute &lt;ip&gt;</div>
                    <div class="cmd-body">Reveals HPS (hop count). Low count confirms local network proximity.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">arp --scan</div>
                    <div class="cmd-body">Reveals ARP timestamps across all suspects at once. Most efficient for narrowing the field quickly.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">whois &lt;ip&gt;</div>
                    <div class="cmd-body">Reveals CLASS. Only useful against low-OS targets — high-OS shows REDACTED.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">sniff --traffic</div>
                    <div class="cmd-body">Intercepts one octet of the target's real IP, shown as an octet clue above the grid. Use when suspects are evenly matched.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">flush &lt;ip&gt;</div>
                    <div class="cmd-body">Strike a suspect off the board. Use on confirmed non-targets to reduce visual noise.</div>
                </div>
                <div class="cmd-block cmd-block--commit">
                    <div class="cmd-sig">inject &lt;ip&gt;</div>
                    <div class="cmd-body">Commit your guess. Correct → advance to Phase 2. Wrong → input locked for several seconds.</div>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">STRATEGY</span>
                    Run <span class="hl-cmd">arp --scan</span> immediately after <span class="hl-cmd">netstat</span> —
                    it reveals timestamps on every suspect in one command. Combine with a ping sweep to find
                    the low-latency, recently-active IP. That's your target 90% of the time.
                    Save <span class="hl-cmd">sniff</span> and <span class="hl-cmd">whois</span> for tiebreakers.
                </div>
            </section>

            <!-- ── PHASE 2 ────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'phase2'">
                <h2 class="sec-title">PHASE 2 — EXPLOIT CHAIN</h2>
                <p class="sec-body">
                    Run <span class="hl-cmd">scan &lt;ip&gt;</span> to populate the port board.
                    You will see 8–10 services. Hidden among them is a chain of 2–3 ports that
                    must be exploited in the correct order before the exfil port (:8080) unlocks.
                    The rest are dead ends or red herrings designed to waste your trace attempts.
                </p>

                <h3 class="subsec-title">PORT CATEGORIES</h3>
                <div class="sec-rule">
                    <span class="rule-key hl-cyan">CHAIN</span>
                    <span class="rule-val">2–3 ports forming the correct exploit path. Each has an anomaly referencing the next port in the chain by service type. Exploit them in sequence to unlock 8080 and reveal credential fragments.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key" style="color:rgba(255,179,0,0.6)">RED HERRING</span>
                    <span class="rule-val">Ports with convincing-sounding anomalies that lead nowhere. <span class="hl-cmd">trace</span> always returns no correlation. Quality scales with target OS — high OS targets generate red herrings that closely mimic chain language.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key" style="color:rgba(255,255,255,0.3)">DEAD END</span>
                    <span class="rule-val">Generic or no anomaly. <span class="hl-cmd">trace</span> returns no correlation. Usually identifiable quickly from the flat probe output.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key" style="color:rgba(255,180,0,0.6)">:8080 EXFIL</span>
                    <span class="rule-val">Always present. Locked until every chain port is shattered in order. Attempting it early returns CHAIN INCOMPLETE.</span>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">COMMANDS</h3>
                <div class="cmd-block">
                    <div class="cmd-sig">scan &lt;ip&gt;</div>
                    <div class="cmd-body">Opens the port board. Lists all ports and service names. No anomaly data yet — use probe to get that.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">probe &lt;port&gt;</div>
                    <div class="cmd-body">
                        Fingerprints a port. Returns a multi-line service banner (flare/noise) with the
                        <span class="hl-amber">ANOMALY</span> line at the bottom. Dead ends have no anomaly or a generic one.
                        Chain and red herring ports have specific relational anomalies.
                        High-OS targets generate longer banners — more noise before the anomaly.
                        Must be probed before trace or exploit can reference it.
                    </div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">trace &lt;port1&gt; &lt;port2&gt;</div>
                    <div class="cmd-body">
                        Tests a chain hypothesis between two probed ports. Both must be probed first.
                        Confirmed adjacency → lights up CHAIN ✓ on both cards and reveals directionality.
                        No correlation → those ports are independent.
                        <span class="hl-red">Consumes one trace attempt regardless of outcome.</span>
                        You have a limited budget based on your CPU stat.
                    </div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">exploit &lt;port&gt;</div>
                    <div class="cmd-body">
                        Attempt to shatter a port. Must be probed first.
                        Correct chain order → port shattered, credential fragment revealed in the top strip.
                        Wrong order or non-chain port → gate holds with an informative error.
                        <span class="hl-amber">"No upstream signal detected"</span> means this port has a dependency — keep looking.
                        <span class="hl-amber">"No known vector"</span> suggests dead end.
                    </div>
                </div>
                <div class="cmd-block cmd-block--commit">
                    <div class="cmd-sig">breach &lt;ip&gt;</div>
                    <div class="cmd-body">
                        Fires after all chain ports and 8080 are shattered. Opens the auth prompt.
                        Enter the hostname and OS credentials assembled from your exploit fragments.
                        Correct → Phase 3. Wrong → 1–3 fragments corrupt, re-derive from your probe banners.
                    </div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">CREDENTIAL STRIP</h3>
                <p class="sec-body">
                    The top strip shows two credential fields — HOSTNAME and OS — both starting as
                    <span class="hl-red">????</span> placeholders. Each successful chain exploit in
                    correct order reveals one segment of each field. The full credentials are
                    complete when the chain is complete. Use them at the auth prompt after
                    <span class="hl-cmd">breach</span>.
                </p>
                <div class="cred-example">
                    <div class="cred-ex-row"><span class="cred-ex-label">Start</span><span class="cred-ex-val dim">CORE-????-????</span><span class="cred-ex-sep">/</span><span class="cred-ex-val dim">PROC-????-???</span></div>
                    <div class="cred-ex-row"><span class="cred-ex-label">Exploit port 1</span><span class="cred-ex-val partial">CORE-RELAY-????</span><span class="cred-ex-sep">/</span><span class="cred-ex-val dim">PROC-????-???</span></div>
                    <div class="cred-ex-row"><span class="cred-ex-label">Exploit port 2</span><span class="cred-ex-val partial">CORE-RELAY-????</span><span class="cred-ex-sep">/</span><span class="cred-ex-val partial">PROC-4.2-???</span></div>
                    <div class="cred-ex-row"><span class="cred-ex-label">Exploit :8080</span><span class="cred-ex-val complete">CORE-RELAY-A3F2</span><span class="cred-ex-sep">/</span><span class="cred-ex-val complete">PROC-4.2-B91</span></div>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">TRACE BUDGET</h3>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU 1–3</span>
                    <span class="rule-val">4 trace attempts</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU 4–6</span>
                    <span class="rule-val">6 trace attempts</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU 7+</span>
                    <span class="rule-val">8 trace attempts</span>
                </div>
                <p class="sec-body" style="margin-top:12px">
                    Running out does not end Phase 2 — you can still exploit based on your current
                    deductions. But you're flying blind. Probe all ports first, eliminate obvious
                    dead ends from the anomaly text, then use traces only for real hypotheses.
                </p>

                <div class="sec-callout sec-callout--amber">
                    <span class="callout-label">STRATEGY</span>
                    Probe every port before spending a single trace. Dead ends are identifiable
                    from weak or absent anomalies — no trace needed. Save attempts for the 2–3
                    ports with specific relational anomalies. Against high-OS targets, red herrings
                    are nearly indistinguishable from chain ports by text alone — trace confirmation
                    is the only reliable signal.
                </div>
            </section>

            <!-- ── PHASE 3 ────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'phase3'">
                <h2 class="sec-title">PHASE 3 — FILESYSTEM EXTRACTION</h2>
                <p class="sec-body">
                    You have authenticated into the target's system. You start at the root
                    directory <span class="hl-cyan">/</span>. The wallet file is hidden somewhere
                    in the directory tree. Navigate until you find it, then run
                    <span class="hl-cmd">extract</span> to steal it. This ends the match immediately.
                </p>

                <h3 class="subsec-title">COMMANDS</h3>
                <div class="cmd-block">
                    <div class="cmd-sig">ls</div>
                    <div class="cmd-body">List the contents of your current directory. Shows subdirectories and files. Look for anything that suggests financial data — wallet names, cred files, data stores.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">cd &lt;directory&gt;</div>
                    <div class="cmd-body">Navigate into a subdirectory. Use the exact name shown by <span class="hl-cmd">ls</span>.</div>
                </div>
                <div class="cmd-block">
                    <div class="cmd-sig">cd ..</div>
                    <div class="cmd-body">Move up one directory level.</div>
                </div>
                <div class="cmd-block cmd-block--commit">
                    <div class="cmd-sig">extract</div>
                    <div class="cmd-body">Run this when you are in the directory containing the wallet. Extracts it and wins the match. Running it in the wrong directory does nothing.</div>
                </div>

                <div class="ph-fs-demo">
                    <div class="ph-fs-label-demo">PATH</div>
                    <div class="ph-fs-trail-demo">
                        <span class="ph-fs-crumb-demo">/</span>
                        <span class="ph-fs-crumb-demo">data</span>
                        <span class="ph-fs-crumb-demo ph-fs-crumb-demo--current">finance</span>
                    </div>
                </div>
                <p class="sec-body" style="margin-top:12px">
                    The breadcrumb trail in the top strip tracks your position. Directories you
                    have visited appear there as a navigation history.
                </p>

                <div class="sec-callout">
                    <span class="callout-label">NOTE</span>
                    The wallet depth is random each match. There is no time limit in Phase 3 beyond
                    the natural PvP pressure — both runners reached this phase simultaneously only if
                    both completed Phase 2, which is unlikely. In practice one runner will enter Phase 3
                    first and the clock is their head start.
                </div>
            </section>

            <!-- ── STATS ─────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'stats'">
                <h2 class="sec-title">HOW STATS AFFECT THE RUN</h2>
                <p class="sec-body">
                    Your stats shape what you can do. The <em>target's</em> stats shape how hard
                    the board is to crack. Both matter.
                </p>

                <h3 class="subsec-title">YOUR STATS (ATTACKER)</h3>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU</span>
                    <span class="rule-val">Controls your trace attempt budget in Phase 2. CPU 1–3 = 4 attempts. CPU 4–6 = 6. CPU 7+ = 8. Higher CPU gives more room to test hypotheses before committing blind.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--os">OS</span>
                    <span class="rule-val">Affects Phase 1 ping evasion on the map. No direct effect inside the Packet Hijack terminal — your OS is a defensive stat here.</span>
                </div>

                <h3 class="subsec-title" style="margin-top:20px">TARGET'S STATS (DEFENDER)</h3>
                <div class="sec-rule">
                    <span class="rule-key stat--firewall">FIREWALL</span>
                    <span class="rule-val">Controls port board difficulty. FW 1–3 → 8 ports (chain easy to isolate). FW 4–6 → 9 ports. FW 7+ → 10 ports. Also controls chain length: FW 1–4 = 2-port chain, FW 5+ = 3-port chain. High Firewall = more noise, longer chain.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--os">OS</span>
                    <span class="rule-val">Controls red herring quality in Phase 2. Low OS (1–3) → red herrings are vague and easy to dismiss. Mid OS (4–6) → anomalies sound relational but generic. High OS (7+) → red herrings closely mimic chain language, nearly indistinguishable without trace confirmation.</span>
                </div>

                <div class="sec-callout sec-callout--amber" style="margin-top:20px">
                    <span class="callout-label">MATCHUP TIP</span>
                    Attacking a VT-3 Vault (FW 5, base) means a 3-port chain buried in 9+ ports with
                    high red herring quality. Budget your traces carefully and lean on anomaly patterns
                    before spending attempts. Attacking a BlackHat v1.0 (FW 1) gives you an 8-port board
                    with a 2-port chain and weak red herrings — probe everything and the chain is
                    obvious before you even trace.
                </div>

                <h3 class="subsec-title" style="margin-top:20px">PHASE 1 DIFFICULTY FACTORS</h3>
                <div class="sec-rule">
                    <span class="rule-key">TARGET OS</span>
                    <span class="rule-val">High OS makes the defender harder to locate on the map in general, but does not directly affect Phase 1 suspect count or attribute quality.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SUSPECT COUNT</span>
                    <span class="rule-val">Fixed at the match's node density — typically 10–15 suspects. The target IP is seeded among them. Use arp --scan first to cut the field fast.</span>
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
    { id: 'overview', label: 'OVERVIEW'     },
    { id: 'phase1',   label: 'PHASE 1'      },
    { id: 'phase2',   label: 'PHASE 2'      },
    { id: 'phase3',   label: 'PHASE 3'      },
    { id: 'stats',    label: 'STATS'        },
];
</script>

<style scoped>
.ph-guide {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.ph-guide-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
}
.ph-guide-title { font-size: 12px; color: #00FFFF; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,255,0.3); }
.ph-guide-sub   { font-size: 9px;  color: rgba(0,255,255,0.55); letter-spacing: 0.08em; }

/* ── Nav ────────────────────────────────────────────────────────────────────── */
.ph-guide-nav {
    display: flex;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    flex-shrink: 0;
    flex-wrap: wrap;
}
.ph-nav-btn {
    padding: 8px 16px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0,255,255,0.06);
    color: rgba(0,255,255,0.6);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.ph-nav-btn:hover  { color: rgba(0,255,255,0.85); background: rgba(0,255,255,0.03); }
.ph-nav-btn.active { color: #00FFFF; background: rgba(0,255,255,0.05); border-bottom: 2px solid #00FFFF; }

/* ── Content ────────────────────────────────────────────────────────────────── */
.ph-guide-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px 28px 40px;
}
.ph-guide-content::-webkit-scrollbar       { width: 3px; }
.ph-guide-content::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Typography ─────────────────────────────────────────────────────────────── */
.sec-title {
    font-size: 13px;
    color: #00FFFF;
    letter-spacing: 0.12em;
    margin: 0 0 16px;
    font-weight: normal;
}
.subsec-title {
    font-size: 10px;
    color: rgba(0,255,255,0.85);
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
    border-bottom: 1px solid rgba(0,255,255,0.05);
    align-items: baseline;
}
.rule-key {
    font-size: 9px;
    color: rgba(0,255,255,0.75);
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

/* ── Phase blocks (overview) ────────────────────────────────────────────────── */
.phase-block {
    padding: 14px 16px;
    margin-bottom: 12px;
    border: 1px solid rgba(0,255,255,0.07);
}
.phase-block--one   { border-color: rgba(0,255,255,0.15);  background: rgba(0,255,255,0.025);  }
.phase-block--two   { border-color: rgba(255,179,0,0.2);   background: rgba(255,179,0,0.02);   }
.phase-block--three { border-color: rgba(0,255,136,0.2);   background: rgba(0,255,136,0.02);   }

.phase-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.phase-num {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(0,255,255,0.65);
    flex-shrink: 0;
}
.phase-block--two  .phase-num   { color: rgba(255,179,0,0.75); }
.phase-block--three .phase-num  { color: rgba(0,255,136,0.75); }

.phase-name {
    font-size: 11px;
    letter-spacing: 0.1em;
    color: rgba(0,255,255,0.75);
}
.phase-block--two   .phase-name { color: rgba(255,179,0,0.85); }
.phase-block--three .phase-name { color: rgba(0,255,136,0.85); }

.phase-desc {
    font-size: 9px;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.04em;
    line-height: 1.75;
    margin: 0;
}

/* ── Command blocks ─────────────────────────────────────────────────────────── */
.cmd-block {
    padding: 10px 12px;
    margin-bottom: 6px;
    border: 1px solid rgba(0,255,255,0.07);
    background: rgba(0,255,255,0.015);
}
.cmd-block--commit {
    border-color: rgba(0,255,136,0.2);
    background: rgba(0,255,136,0.02);
}
.cmd-sig {
    font-size: 10px;
    color: rgba(0,255,255,0.8);
    letter-spacing: 0.06em;
    margin-bottom: 5px;
    font-weight: bold;
}
.cmd-block--commit .cmd-sig { color: #00ff88; }
.cmd-body {
    font-size: 9px;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.03em;
    line-height: 1.65;
    margin: 0;
}

/* ── Credential example ─────────────────────────────────────────────────────── */
.cred-example {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 14px;
    border: 1px solid rgba(0,255,255,0.08);
    background: rgba(0,0,0,0.3);
    margin: 12px 0;
    max-width: 580px;
}
.cred-ex-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 9px;
    letter-spacing: 0.04em;
}
.cred-ex-label { width: 100px; color: rgba(0,255,255,0.3); flex-shrink: 0; }
.cred-ex-sep   { color: rgba(0,255,255,0.15); }
.cred-ex-val.dim      { color: rgba(255,50,50,0.4); }
.cred-ex-val.partial  { color: rgba(0,255,255,0.55); }
.cred-ex-val.complete { color: #00FFFF; text-shadow: 0 0 6px rgba(0,255,255,0.5); }

/* ── Filesystem demo ────────────────────────────────────────────────────────── */
.ph-fs-demo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border: 1px solid rgba(0,255,255,0.08);
    background: rgba(0,255,255,0.015);
    margin: 12px 0;
    max-width: 420px;
}
.ph-fs-label-demo {
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.12em;
    flex-shrink: 0;
}
.ph-fs-trail-demo {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ph-fs-crumb-demo {
    font-size: 10px;
    color: rgba(0,255,255,0.35);
    letter-spacing: 0.04em;
    padding: 1px 6px;
    border: 1px solid rgba(0,255,255,0.08);
}
.ph-fs-crumb-demo--current {
    color: #00ff88;
    border-color: rgba(0,255,136,0.3);
    background: rgba(0,255,136,0.04);
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

/* ── Stat colours ───────────────────────────────────────────────────────────── */
.stat--cpu      { color: #7DF9FF !important; }
.stat--ram      { color: #FF69B4 !important; }
.stat--os       { color: #00FF88 !important; }
.stat--firewall { color: #FF6B00 !important; }

/* ── Colour helpers ─────────────────────────────────────────────────────────── */
.hl-cyan   { color: #00FFFF !important; }
.hl-green  { color: #00FF88 !important; }
.hl-amber  { color: #FFB300 !important; }
.hl-red    { color: #FF3333 !important; }
.hl-cmd    { color: #00FFFF; font-weight: bold; }
</style>
