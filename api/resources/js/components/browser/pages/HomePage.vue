<template>
    <div class="home-page">

        <div class="home-hero">
            <div class="home-logo">◈ SPLICE</div>
            <div class="home-tagline">Secure Private Links In Covert Environments</div>
        </div>

        <!-- Network -->
        <div class="home-group">
            <div class="home-group-label">NETWORK</div>
            <div class="home-grid">
                <button class="home-tile" @click="spliceNavigate(SPLICE.FEED)">
                    <span class="tile-icon">◉</span>
                    <span class="tile-title">DARKNET FEED</span>
                    <span class="tile-desc">Live intel, bounties &amp; breach reports from around the city</span>
                    <span class="tile-url">darknet.spk/feed</span>
                </button>
                <button class="home-tile" @click="spliceNavigate(SPLICE.CYBER_DOC)">
                    <span class="tile-icon">⬡</span>
                    <span class="tile-title">CYBERDOC</span>
                    <span class="tile-desc">Purchase commands, hardware, software and consumables</span>
                    <span class="tile-url">cyberdoc.net/shop</span>
                </button>
            </div>
        </div>

        <!-- Reference -->
        <div class="home-group">
            <div class="home-group-label">REFERENCE</div>
            <div class="home-grid">
                <button class="home-tile home-tile--ref" @click="spliceNavigate(SPLICE.COMMAND_CATALOG)">
                    <span class="tile-icon tile-icon--cmd">⬡</span>
                    <span class="tile-title">COMMAND CATALOG</span>
                    <span class="tile-desc">Browse all 15 commands — map effects, hack effects, costs and targeting</span>
                    <span class="tile-url">sys.local/commands/catalog</span>
                </button>
                <button class="home-tile home-tile--ref" @click="spliceNavigate(SPLICE.STAT_GUIDE)">
                    <span class="tile-icon tile-icon--stat">◈</span>
                    <span class="tile-title">STAT REFERENCE</span>
                    <span class="tile-desc">How CPU, RAM, OS, Firewall, Storage and Uplink interact with the game systems</span>
                    <span class="tile-url">sys.local/guide/stats</span>
                </button>
                <button class="home-tile home-tile--ref" @click="spliceNavigate(SPLICE.MANUAL)">
                    <span class="tile-icon">◈</span>
                    <span class="tile-title">SYSTEM MANUAL</span>
                    <span class="tile-desc">Movement, hacking, resources and combat reference guide</span>
                    <span class="tile-url">sys.local/manual</span>
                </button>
                <button class="home-tile home-tile--ref" @click="spliceNavigate(SPLICE.GRID_BREACH_GUIDE)">
                    <span class="tile-icon tile-icon--breach">◉</span>
                    <span class="tile-title">GRID-BREACH MANUAL</span>
                    <span class="tile-desc">How to run breaches — grid, sequences, row modifiers, stats &amp; PvP</span>
                    <span class="tile-url">sys.local/guide/gridbreach</span>
                </button>
                <button class="home-tile home-tile--ref" @click="spliceNavigate(SPLICE.PACKET_HIJACK_GUIDE)">
                    <span class="tile-icon tile-icon--hijack">◈</span>
                    <span class="tile-title">PACKET HIJACK MANUAL</span>
                    <span class="tile-desc">Three-phase PvP intrusion — recon, exploit chain &amp; filesystem extraction</span>
                    <span class="tile-url">sys.local/guide/packethijack</span>
                </button>
            </div>
        </div>

        <div class="home-status">
            <span class="hs-dot" />
            <span>SPLICE NETWORK ONLINE // ALL NODES REACHABLE // {{ time }}</span>
        </div>

    </div>
</template>

<script setup>
import { ref, inject, onMounted, onUnmounted } from 'vue';
import { SPLICE } from '@/components/browser/SpliceRouter.js';

defineProps({ url: { type: String, default: '' } });

const spliceNavigate = inject('spliceNavigate', () => {});

const time = ref('');
let timer;
onMounted(() => {
    const tick = () => {
        time.value = new Date().toLocaleTimeString('en-US', { hour12: false });
    };
    tick();
    timer = setInterval(tick, 1000);
});
onUnmounted(() => clearInterval(timer));
</script>

<style scoped>
.home-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 28px;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    padding: 32px 40px;
    overflow-y: auto;
}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.home-hero {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.home-logo {
    font-size: 42px;
    color: #00FFFF;
    letter-spacing: 0.25em;
    text-shadow: 0 0 24px rgba(0, 255, 255, 0.45);
}

.home-tagline {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.28);
    letter-spacing: 0.18em;
}

/* ── Groups ───────────────────────────────────────────────────────────────── */
.home-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 720px;
}

.home-group-label {
    font-size: 7px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.22em;
    align-self: flex-start;
}

/* ── Quick-launch tiles ───────────────────────────────────────────────────── */
.home-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
    width: 100%;
}

.home-tile {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 24px 28px;
    width: 220px;
    background: rgba(0, 255, 255, 0.025);
    border: 1px solid rgba(0, 255, 255, 0.12);
    cursor: pointer;
    text-align: left;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.home-tile:hover {
    background: rgba(0, 255, 255, 0.06);
    border-color: rgba(0, 255, 255, 0.38);
    box-shadow: 0 0 16px rgba(0, 255, 255, 0.07);
}

.tile-icon         { font-size: 16px; color: #00FF88; }
.tile-icon--cmd    { color: #FFB300; }
.tile-icon--stat   { color: #7DF9FF; }
.tile-icon--breach { color: #FF3333; }
.tile-icon--hijack { color: #FFB300; }
.tile-title { font-size: 11px; color: #00FFFF; letter-spacing: 0.1em; }
.tile-desc  { font-size: 8px;  color: rgba(255, 255, 255, 0.35); letter-spacing: 0.04em; line-height: 1.6; }
.tile-url   { font-size: 8px;  color: rgba(0, 255, 255, 0.25); letter-spacing: 0.08em; margin-top: 4px; }

/* Reference tiles — slightly more muted to visually separate from network tiles */
.home-tile--ref {
    background: rgba(0, 255, 255, 0.015);
    border-color: rgba(0, 255, 255, 0.08);
    width: 200px;
}
.home-tile--ref:hover {
    background: rgba(0, 255, 255, 0.04);
    border-color: rgba(0, 255, 255, 0.28);
}

/* ── Status bar ───────────────────────────────────────────────────────────── */
.home-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 8px;
    color: rgba(0, 255, 136, 0.45);
    letter-spacing: 0.1em;
}

.hs-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #00FF88;
    box-shadow: 0 0 6px rgba(0, 255, 136, 0.7);
    flex-shrink: 0;
}
</style>
