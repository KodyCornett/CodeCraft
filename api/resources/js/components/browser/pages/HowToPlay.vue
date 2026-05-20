<template>
    <div class="manual-page">

        <header class="manual-header">
            <span class="manual-title">◈ SYS.LOCAL // RUNNER MANUAL</span>
            <span class="manual-ver">v1.0</span>
        </header>

        <nav class="manual-nav">
            <button
                v-for="sec in sections"
                :key="sec.id"
                class="manual-nav-btn"
                :class="{ active: activeSection === sec.id }"
                @click="activeSection = sec.id"
            >{{ sec.label }}</button>
        </nav>

        <div class="manual-content">

            <!-- MOVEMENT -->
            <section v-if="activeSection === 'movement'">
                <h2 class="sec-title">MOVEMENT</h2>
                <p class="sec-body">
                    The city map is divided into a network of interconnected nodes. Your runner token
                    occupies one node at a time. Click any adjacent node to move to it.
                </p>
                <p class="sec-body">
                    Each move costs <strong class="hl-uplink">1 UPLINK</strong>. UPLINK is your
                    movement resource — it can be recovered by hacking UPLINK caches on nodes
                    throughout the city. When your UPLINK hits zero, you cannot move until you
                    recover more.
                </p>
                <div class="sec-rule">
                    <span class="rule-key">ADJACENT NODES</span>
                    <span class="rule-val">Highlighted on click — only reachable nodes can be entered</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">UPLINK COST</span>
                    <span class="rule-val">1 per move</span>
                </div>
            </section>

            <!-- HACKING -->
            <section v-if="activeSection === 'hacking'">
                <h2 class="sec-title">HACKING</h2>
                <p class="sec-body">
                    Every non-vendor node on the map contains up to three hackable resource caches:
                    <strong class="hl-creds">CREDS</strong>, <strong class="hl-tech">TECH POINTS</strong>,
                    and <strong class="hl-uplink">UPLINK</strong>. Select a node to open its terminal
                    window, then choose which cache to target.
                </p>
                <p class="sec-body">
                    Hacking launches <strong class="hl-cyan">GRID-BREACH</strong> — a mini-game where
                    you must breach the node's security layer before the trace completes. Success
                    awards the cache contents. Failure depletes your System Stability.
                </p>
                <div class="sec-rule">
                    <span class="rule-key">CREDS</span>
                    <span class="rule-val hl-creds">In-game currency — spend at CyberDoc vendors</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">TECH POINTS</span>
                    <span class="rule-val hl-tech">Used to upgrade rig stats and unlock commands</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">UPLINK</span>
                    <span class="rule-val hl-uplink">Restores your movement resource</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key">DEPLETED NODES</span>
                    <span class="rule-val">Caches regenerate after a set number of turns</span>
                </div>
            </section>

            <!-- RESOURCES -->
            <section v-if="activeSection === 'resources'">
                <h2 class="sec-title">RESOURCES</h2>
                <p class="sec-body">
                    Your runner has three core resources displayed in the HUD at all times.
                </p>
                <div class="sec-rule">
                    <span class="rule-key hl-cyan">SYSTEM STABILITY (SS)</span>
                    <span class="rule-val">Your health. Depleted by failed hacks, PvP combat, and trace events. Recovered at CyberDoc vendors.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-creds">CREDS</span>
                    <span class="rule-val">Currency earned by hacking CREDS caches and winning PvP. Spent at CyberDoc stores.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-uplink">UPLINK</span>
                    <span class="rule-val">Movement points. Each step costs 1. Recovered by hacking UPLINK caches.</span>
                </div>
                <div class="sec-rule">
                    <span class="rule-key hl-tech">TECH POINTS</span>
                    <span class="rule-val">Upgrade currency. Earned by hacking TECH caches. Spent on rig upgrades at CyberDoc.</span>
                </div>
            </section>

            <!-- DISTRICTS -->
            <section v-if="activeSection === 'districts'">
                <h2 class="sec-title">DISTRICTS</h2>
                <p class="sec-body">
                    Spokane is divided into five districts. Each district has its own node cluster
                    with unique SPLICE addresses and resource density.
                </p>
                <div v-for="d in districts" :key="d.name" class="district-row">
                    <span class="district-name">{{ d.name }}</span>
                    <span class="district-zone">ZONE {{ d.zone }}</span>
                    <span class="district-desc">{{ d.desc }}</span>
                </div>
            </section>

        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ url: { type: String, default: '' } });

const activeSection = ref('movement');

const sections = [
    { id: 'movement',  label: 'MOVEMENT'  },
    { id: 'hacking',   label: 'HACKING'   },
    { id: 'resources', label: 'RESOURCES' },
    { id: 'districts', label: 'DISTRICTS' },
];

const districts = [
    { name: 'North Spokane',       zone: '14', desc: 'High node density. Good UPLINK returns. Low corporate presence.' },
    { name: 'Spokane Valley',      zone: '21', desc: 'Sprawling suburban grid. Long travel times but rich CREDS caches.' },
    { name: 'Downtown',            zone: '35', desc: 'Central hub. High TECH yields. Heavy trace activity.' },
    { name: "Browne's Addition",   zone: '49', desc: 'Dense residential mesh. Balanced resources. PvP hotspot.' },
    { name: 'University District', zone: '63', desc: 'Academic subnet. Best TECH point density in the city.' },
];
</script>

<style scoped>
.manual-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

.manual-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
}

.manual-title {
    font-size: 12px;
    color: #00FFFF;
    letter-spacing: 0.1em;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
}

.manual-ver {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.25);
    letter-spacing: 0.1em;
}

.manual-nav {
    display: flex;
    border-bottom: 1px solid rgba(0, 255, 255, 0.08);
    flex-shrink: 0;
}

.manual-nav-btn {
    padding: 9px 20px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0, 255, 255, 0.06);
    color: rgba(0, 255, 255, 0.35);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
}
.manual-nav-btn:hover  { color: rgba(0, 255, 255, 0.7); background: rgba(0, 255, 255, 0.03); }
.manual-nav-btn.active { color: #00FFFF; background: rgba(0, 255, 255, 0.05); border-bottom: 2px solid #00FFFF; }

.manual-content {
    flex: 1;
    overflow-y: auto;
    padding: 28px 28px;
}

.sec-title {
    font-size: 14px;
    color: #00FFFF;
    letter-spacing: 0.14em;
    margin: 0 0 18px;
    font-weight: normal;
}

.sec-body {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.55);
    letter-spacing: 0.04em;
    line-height: 1.85;
    margin: 0 0 14px;
    max-width: 640px;
}

.sec-rule {
    display: flex;
    gap: 20px;
    padding: 9px 0;
    border-bottom: 1px solid rgba(0, 255, 255, 0.05);
    align-items: baseline;
}

.rule-key {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.5);
    letter-spacing: 0.1em;
    width: 160px;
    flex-shrink: 0;
}

.rule-val {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.45);
    letter-spacing: 0.04em;
    line-height: 1.6;
}

/* District table */
.district-row {
    display: grid;
    grid-template-columns: 200px 80px 1fr;
    gap: 16px;
    padding: 10px 0;
    border-bottom: 1px solid rgba(0, 255, 255, 0.05);
    align-items: baseline;
}

.district-name { font-size: 10px; color: #00FFFF;                letter-spacing: 0.07em; }
.district-zone { font-size: 9px;  color: rgba(0,255,136,0.6);   letter-spacing: 0.1em;  }
.district-desc { font-size: 9px;  color: rgba(255,255,255,0.4); letter-spacing: 0.04em; line-height: 1.6; }

/* Highlight classes */
.hl-cyan   { color: #00FFFF !important; }
.hl-creds  { color: #00FF88 !important; }
.hl-tech   { color: #00FF88 !important; }
.hl-uplink { color: #7DF9FF !important; }
</style>
