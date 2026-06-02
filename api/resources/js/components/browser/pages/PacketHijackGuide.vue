<template>
    <div class="ph-guide">

        <header class="ph-guide-header">
            <span class="ph-guide-title">◈ PACKET HIJACK // OPERATOR MANUAL</span>
            <span class="ph-guide-sub">Interception protocol reference — read before your first run</span>
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
                    Packet Hijack activates when you intercept a live data relay — either by hacking a
                    high-value network node or by disrupting an enemy runner's active uplink during PvP.
                    Instead of cracking a static cipher, you are reaching into a moving data stream and
                    snatching packets out of it before they escape the buffer window.
                </p>
                <p class="sec-body">
                    Packets stream left-to-right across five transmission lanes. Each packet carries a
                    two-character hex tag visible on its face. Type the tag and hit Enter to intercept it.
                    Fill your <strong class="hl-cyan">HAUL meter</strong> before the relay window closes to succeed.
                </p>

                <div class="sec-rule">
                    <span class="rule-key">PvE MODE</span>
                    <span class="rule-val">Reach the HAUL threshold before time expires to extract the node's data cache and earn creds.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">PvP MODE</span>
                    <span class="rule-val">Race the other runner to the same HAUL threshold. First to fill it wins the relay — and the loser's pocket creds.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">HAUL THRESHOLD</span>
                    <span class="rule-val">Set by the node's ICE rating. Low-ICE nodes require fewer captures. High-ICE nodes demand a larger haul and push more dangerous packet types onto the stream.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ABORT</span>
                    <span class="rule-val">You can abort at any time with no reward and no SS damage. Use it if the stream is running too hot.</span>
                </div>
            </section>

            <!-- ── THE STREAM ────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'stream'">
                <h2 class="sec-title">THE TRANSMISSION STREAM</h2>
                <p class="sec-body">
                    The relay surface shows five horizontal lanes. Packets enter from the
                    <span class="hl-cyan">left edge</span> and travel rightward at a fixed speed.
                    When a packet reaches the <span class="hl-red">right edge</span> it escapes —
                    you lose its value and take a small penalty to your remaining window.
                </p>
                <p class="sec-body">
                    To intercept a packet, type its <strong class="hl-cyan">two-character hex tag</strong>
                    and press Enter. Tag entry is case-insensitive. You can only intercept one packet at a time —
                    mid-tag input is committed before the next tag begins.
                </p>

                <div class="sec-rule">
                    <span class="rule-key hl-cyan">ACTIVE PACKET</span>
                    <span class="rule-val">The packet your tag input is currently targeting glows cyan. Once you begin typing a tag, you are locked to that packet until Enter is pressed or the tag is cleared.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-green">CAPTURED</span>
                    <span class="rule-val">A successful intercept flashes green, adds its HAUL value to your meter, and removes the packet from the lane.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">ESCAPED</span>
                    <span class="rule-val">A packet that exits the right edge is marked red briefly. Each escape costs −1 second from your remaining window.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">LANE ORDER</span>
                    <span class="rule-val">Packets in the lowest-numbered lane always take priority for tag matching when two packets share the same hex tag simultaneously.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">STREAM DENSITY</span>
                    <span class="rule-val">Packet spawn rate scales with ICE. ICE 3 streams are sparse — ICE 9 streams are near-continuous. At high ICE, escapes are unavoidable. Focus on highest-value packets.</span>
                </div>
            </section>

            <!-- ── PACKET TYPES ──────────────────────────────────────────────── -->
            <section v-if="activeSection === 'packets'">
                <h2 class="sec-title">PACKET TYPES</h2>
                <p class="sec-body">
                    Not all packets are equal. Type and rarity are determined by node ICE.
                    At low ICE only DATA packets appear. Dangerous types enter the stream progressively
                    as ICE increases.
                </p>

                <div class="packet-block packet-block--data">
                    <div class="pkt-header">
                        <span class="pkt-tag pkt-tag--data">DATA</span>
                        <span class="pkt-since">All ICE levels</span>
                    </div>
                    <p class="pkt-body">
                        Standard payload packet. White face, static hex tag.
                        Intercept it for <strong class="hl-cyan">+1 HAUL</strong>.
                        No side effects. The backbone of every relay run.
                    </p>
                </div>

                <div class="packet-block packet-block--cache">
                    <div class="pkt-header">
                        <span class="pkt-tag pkt-tag--cache">CACHE</span>
                        <span class="pkt-since">ICE 4+</span>
                    </div>
                    <p class="pkt-body">
                        High-density payload. Green border, slower travel speed.
                        Intercept it for <strong class="hl-green">+3 HAUL</strong>.
                        Worth prioritising — but its slow speed means it blocks the lane longer.
                    </p>
                </div>

                <div class="packet-block packet-block--encrypted">
                    <div class="pkt-header">
                        <span class="pkt-tag pkt-tag--encrypted">ENCRYPTED</span>
                        <span class="pkt-since">ICE 5+</span>
                    </div>
                    <p class="pkt-body">
                        The hex tag is obscured behind a static mask — visible for only
                        <strong class="hl-amber">0.4 seconds</strong> each time the packet pulses.
                        You must read and type it in the flash window.
                        Reward is <strong class="hl-cyan">+2 HAUL</strong>. Miss the window and it travels on like any other packet.
                        Your CPU stat widens the flash window slightly.
                    </p>
                </div>

                <div class="packet-block packet-block--trap">
                    <div class="pkt-header">
                        <span class="pkt-tag pkt-tag--trap">TRAP</span>
                        <span class="pkt-since">ICE 6+</span>
                    </div>
                    <p class="pkt-body">
                        An ICE decoy seeded into the stream. TRAP packets look identical to DATA packets
                        but carry a red pixel artifact on their left edge — hard to spot at speed.
                        Intercepting a TRAP costs <strong class="hl-red">−2 HAUL</strong> and stuns your input
                        for <strong class="hl-red">1 second</strong>. Avoid them.
                        High Firewall reduces the stun to <strong class="hl-amber">0.4 seconds</strong>.
                    </p>
                </div>

                <div class="packet-block packet-block--ghost">
                    <div class="pkt-header">
                        <span class="pkt-tag pkt-tag--ghost">GHOST</span>
                        <span class="pkt-since">ICE 8+</span>
                    </div>
                    <p class="pkt-body">
                        A cloaked burst packet — fully invisible except for a faint lane shimmer.
                        It does not display a hex tag. To capture it you must type the tag of any
                        other packet currently on-stream and the relay engine will
                        <strong class="hl-cyan">match to the GHOST first</strong> if your CPU ≥ node ICE − 1.
                        Reward is <strong class="hl-green">+5 HAUL</strong>. If your CPU is too low, the match
                        falls through to the visible packet instead.
                    </p>
                </div>

                <div class="sec-rule" style="margin-top: 16px;">
                    <span class="rule-key">ICE 3–4</span>
                    <span class="rule-val">DATA only (ICE 4 adds occasional CACHE).</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 5–6</span>
                    <span class="rule-val">DATA + CACHE + ENCRYPTED (ICE 6 adds TRAP).</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 7</span>
                    <span class="rule-val">Full mix minus GHOST. TRAP frequency increases.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">ICE 8+</span>
                    <span class="rule-val">All five types active. GHOST packets appear every 8–12 seconds.</span>
                </div>
            </section>

            <!-- ── STATS ─────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'stats'">
                <h2 class="sec-title">HOW YOUR STATS AFFECT THE RUN</h2>
                <p class="sec-body">
                    Your rig stats shape every dimension of the relay window — how long you have,
                    how fast the packets move, and how well you can handle the dangerous types.
                </p>

                <div class="sec-rule">
                    <span class="rule-key stat--ram">RAM</span>
                    <span class="rule-val">Sets total window duration. Base 20s + (RAM × 4s). A RAM 4 rig gets 36 seconds. A RAM 2 rig gets 28. More RAM means more time to fill the HAUL meter.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--cpu">CPU</span>
                    <span class="rule-val">Two effects: widens the ENCRYPTED flash window by (CPU × 0.05s), and enables GHOST matching when CPU ≥ nodeICE − 1. Higher CPU is essential at ICE 8+ nodes.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--os">OS</span>
                    <span class="rule-val">Reduces packet travel speed. Each OS point adds (OS × 3%) slowdown to all packets on your stream. OS 5 slows packets by 15% — meaningful but not transformative. Most impactful at ICE 3–5 where you're racing to read tags cleanly.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key stat--firewall">FIREWALL</span>
                    <span class="rule-val">Reduces TRAP stun duration. Base 1s stun → 1s − (Firewall × 0.12s), minimum 0.2s. Firewall 5 cuts stun to 0.4s. At high ICE where TRAPs are frequent, this is the difference between a recoverable mistake and a cascade of missed captures.</span>
                </div>

                <div class="sec-callout">
                    <span class="callout-label">WARNING</span>
                    Attempting a relay node with ICE more than 4 above your CPU locks you out entirely —
                    the GHOST match mechanic also becomes impossible. Check node ICE before committing.
                    Use <span class="hl-cyan">splice://sys.local/guide/stats</span> for the full CPU gate table.
                </div>

                <div class="sec-rule" style="margin-top: 8px;">
                    <span class="rule-key">WINDOW FORMULA</span>
                    <span class="rule-val">Base (20 + RAM×4) seconds. Each escaped packet costs −1s. Minimum window 6 seconds regardless of rig.</span>
                </div>
            </section>

            <!-- ── PVP ───────────────────────────────────────────────────────── -->
            <section v-if="activeSection === 'pvp'">
                <h2 class="sec-title">PvP RELAY RACE MODE</h2>
                <p class="sec-body">
                    When a PvP Packet Hijack is triggered, both runners tap into the same relay simultaneously.
                    The stream is identical for both players — same packet sequence, same timing, same tags.
                    First runner to reach the HAUL threshold wins the relay and the contested pocket creds.
                </p>

                <div class="sec-rule">
                    <span class="rule-key">OBJECTIVE</span>
                    <span class="rule-val">Reach the HAUL threshold before the other runner. The threshold is fixed at 12 HAUL regardless of node ICE.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-green">WINNER</span>
                    <span class="rule-val hl-green">Relay closes instantly. Winner receives the loser's full pocket creds. Bounty and SS are unaffected.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-red">LOSER</span>
                    <span class="rule-val hl-red">Pocket creds wiped. Bounty retained. SS damage applied via standard PvP formula.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">OPPONENT COUNTER</span>
                    <span class="rule-val">A live HAUL counter for your opponent is visible in the corner of your stream display. If they're close, prioritise CACHE packets — the +3 burst can flip the outcome in under two seconds.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">SAFE ZONES</span>
                    <span class="rule-val">CyberDoc nodes block all PvP relays. No Packet Hijack duels can be initiated there.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DECLINE</span>
                    <span class="rule-val">You can decline a relay challenge. No penalty for either player.</span>
                </div>

                <div class="sec-callout sec-callout--amber">
                    <span class="callout-label">STRATEGY</span>
                    In PvP the TRAP penalty is amplified — losing 2 HAUL and a second of input while your opponent
                    races ahead is often unrecoverable. When the stream is saturated at ICE 7+, skip anything
                    with a suspicious left edge and focus on CACHE packets to close the gap fast.
                    High-Firewall rigs shrug off accidental TRAP hits — they are the safest choice for relay duels.
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
    { id: 'overview', label: 'OVERVIEW'      },
    { id: 'stream',   label: 'THE STREAM'    },
    { id: 'packets',  label: 'PACKET TYPES'  },
    { id: 'stats',    label: 'STATS'         },
    { id: 'pvp',      label: 'PvP MODE'      },
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
.ph-guide-sub   { font-size: 9px;  color: rgba(0,255,255,0.25); letter-spacing: 0.08em; }

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
    color: rgba(0,255,255,0.35);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.ph-nav-btn:hover  { color: rgba(0,255,255,0.7); background: rgba(0,255,255,0.03); }
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

/* ── Packet type blocks ─────────────────────────────────────────────────────── */
.packet-block {
    padding: 14px;
    margin-bottom: 14px;
    border: 1px solid rgba(0,255,255,0.07);
}
.packet-block--data      { border-color: rgba(0,255,255,0.12);  background: rgba(0,255,255,0.02);   }
.packet-block--cache     { border-color: rgba(0,255,136,0.2);   background: rgba(0,255,136,0.02);   }
.packet-block--encrypted { border-color: rgba(255,179,0,0.2);   background: rgba(255,179,0,0.02);   }
.packet-block--trap      { border-color: rgba(255,51,51,0.2);   background: rgba(255,51,51,0.02);   }
.packet-block--ghost     { border-color: rgba(180,120,255,0.2); background: rgba(180,120,255,0.02); }

.pkt-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}
.pkt-tag {
    font-size: 9px;
    letter-spacing: 0.14em;
    padding: 2px 8px;
    border: 1px solid;
}
.pkt-tag--data      { color: rgba(0,255,255,0.7);   border-color: rgba(0,255,255,0.3);   }
.pkt-tag--cache     { color: rgba(0,255,136,0.8);   border-color: rgba(0,255,136,0.4);   }
.pkt-tag--encrypted { color: rgba(255,179,0,0.8);   border-color: rgba(255,179,0,0.35);  }
.pkt-tag--trap      { color: rgba(255,51,51,0.75);  border-color: rgba(255,51,51,0.35);  }
.pkt-tag--ghost     { color: rgba(180,120,255,0.8); border-color: rgba(180,120,255,0.35);}
.pkt-since { font-size: 8px; color: rgba(0,255,255,0.25); letter-spacing: 0.08em; }

.pkt-body {
    font-size: 9px;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.04em;
    line-height: 1.75;
    margin: 0;
}

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
