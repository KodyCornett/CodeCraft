<template>
    <div class="rig-page">

        <!-- ── Identity header ──────────────────────────────────────────── -->
        <header class="rig-header">
            <div class="rig-header-left">
                <span class="rig-icon">⬡</span>
                <div class="rig-title-stack">
                    <span class="rig-chassis">{{ chassisDisplayName }}</span>
                    <span class="rig-sub">RIG.MANIFEST // SYSTEM READ-OUT</span>
                </div>
            </div>
            <div class="rig-tier-badge">TIER {{ tierLabel }}</div>
        </header>

        <!-- ── Point allocation bar ─────────────────────────────────────── -->
        <div class="rig-points-strip">
            <span class="pts-label">POINTS</span>
            <div class="pts-bar-wrap">
                <div class="pts-bar-track">
                    <div class="pts-bar-fill" :style="{ width: pointsPct + '%' }" :class="pointsClass" />
                    <div class="pts-bar-marks">
                        <span v-for="n in 4" :key="n"></span>
                    </div>
                </div>
            </div>
            <span class="pts-val" :class="pointsClass">{{ pointsSpent }} <span class="pts-sep">/</span> {{ pointsCap }} SPENT</span>
        </div>

        <div class="rig-page-body">

            <!-- ── Live resources ────────────────────────────────────────── -->
            <section class="rig-section">
                <div class="section-head">// LIVE RESOURCES</div>

                <!-- Uplink -->
                <div class="live-row">
                    <span class="live-key">UPLINK</span>
                    <div class="live-segs">
                        <span
                            v-for="i in (player.maxUplink ?? 3)"
                            :key="i"
                            class="live-seg"
                            :class="i <= (player.uplink ?? 0) ? 'seg--uplink' : 'seg--off'"
                        />
                    </div>
                    <span class="live-val" :class="uplinkClass">
                        {{ player.uplink ?? 0 }}<span class="live-unit">/{{ player.maxUplink ?? 3 }} LNK</span>
                    </span>
                    <span class="live-status" :class="uplinkClass">{{ uplinkStatus }}</span>
                </div>

            </section>

            <div class="rig-divider" />

            <!-- ── Core stats ─────────────────────────────────────────────── -->
            <section class="rig-section">
                <div class="section-head">// CORE STATS</div>

                <div
                    v-for="s in coreStats"
                    :key="s.key"
                    class="stat-row"
                    :class="{
                        'stat-row--boosted':  s.boosted,
                        'stat-row--expanded': expandedStat === s.key,
                        'stat-row--capped':   s.effective >= s.cap,
                    }"
                    @click="toggleStat(s.key)"
                >
                    <div class="stat-main">
                        <!-- Label -->
                        <span class="stat-key">{{ s.label }}</span>

                        <!-- Segmented bar -->
                        <div class="stat-bar">
                            <span
                                v-for="i in s.cap"
                                :key="i"
                                class="stat-seg"
                                :class="segClass(i, s)"
                            />
                        </div>

                        <!-- Value -->
                        <span class="stat-num" :class="{ 'stat-num--boosted': s.boosted, 'stat-num--capped': s.effective >= s.cap }">
                            {{ s.effective }}
                        </span>
                        <span class="stat-cap">/ {{ s.cap }}</span>

                        <!-- Boost badge -->
                        <span v-if="s.boosted" class="stat-boost-tag">+{{ s.effective - s.base }} PERIPH</span>

                        <!-- Expand arrow -->
                        <span class="stat-arrow" :class="{ 'stat-arrow--open': expandedStat === s.key }">›</span>
                    </div>

                    <!-- Expanded effect detail -->
                    <Transition name="stat-expand">
                        <div v-if="expandedStat === s.key" class="stat-detail">
                            <div class="stat-detail-grid">
                                <div class="sdg-row">
                                    <span class="sdg-key">BASE</span>
                                    <span class="sdg-val">{{ s.base }}</span>
                                </div>
                                <div v-if="s.invested > 0" class="sdg-row">
                                    <span class="sdg-key">INVESTED</span>
                                    <span class="sdg-val sdg-val--invested">+{{ s.invested }}</span>
                                </div>
                                <div v-if="s.boosted" class="sdg-row">
                                    <span class="sdg-key">PERIPHERAL</span>
                                    <span class="sdg-val sdg-val--boost">+{{ s.effective - s.base - s.invested }}</span>
                                </div>
                                <div class="sdg-row">
                                    <span class="sdg-key">CAP</span>
                                    <span class="sdg-val">{{ s.cap }}</span>
                                </div>
                            </div>
                            <div class="stat-effect">{{ s.effect }}</div>
                        </div>
                    </Transition>
                </div>
            </section>

            <div class="rig-divider" />

            <!-- ── Uplink (chassis-locked) ──────────────────────────────── -->
            <section class="rig-section">
                <div class="section-head">// UPLINK HARDWARE <span class="head-tag">CHASSIS-LOCKED</span></div>
                <div class="stat-row stat-row--locked">
                    <div class="stat-main">
                        <span class="stat-key">UPLINK</span>
                        <div class="stat-bar">
                            <span
                                v-for="i in (rig.caps?.uplink ?? 3)"
                                :key="i"
                                class="stat-seg"
                                :class="i <= (rig.uplink ?? 3) ? 'seg--uplink-base' : 'seg--off'"
                            />
                        </div>
                        <span class="stat-num stat-num--locked">{{ rig.uplink ?? 3 }}</span>
                        <span class="stat-cap">/ {{ rig.caps?.uplink ?? 3 }}</span>
                        <span class="stat-locked-tag">LOCKED</span>
                    </div>
                    <div class="stat-effect-inline">Movement pool — depletes per node hop, restored by hacking UPLINK resource</div>
                </div>
            </section>

            <div class="rig-divider" />

            <!-- ── Peripheral ports ──────────────────────────────────────── -->
            <section class="rig-section">
                <div class="section-head">
                    // PERIPHERAL PORTS
                    <span class="head-tag">{{ installedCount }}/{{ rig.portSlots ?? 0 }} ACTIVE</span>
                </div>

                <div v-if="(rig.portSlots ?? 0) === 0" class="peri-locked-msg">
                    NO PORTS ON THIS CHASSIS — UPGRADE TO UNLOCK
                </div>

                <template v-else>
                    <div
                        v-for="p in (rig.peripherals ?? [])"
                        :key="p.id"
                        class="peri-row"
                        :class="{
                            'peri-row--active':  p.installed && !p.damaged,
                            'peri-row--damaged': p.installed &&  p.damaged,
                        }"
                    >
                        <span class="peri-dot" />
                        <span class="peri-name">{{ p.name }}</span>
                        <span class="peri-boost">+{{ p.boost }} {{ (p.stat ?? '').toUpperCase() }}</span>
                        <span class="peri-status">{{ p.damaged ? 'DAMAGED' : 'ACTIVE' }}</span>
                    </div>

                    <div v-for="i in emptySlots" :key="'e' + i" class="peri-row peri-row--empty">
                        <span class="peri-dot peri-dot--empty" />
                        <span class="peri-name peri-name--empty">EMPTY PORT</span>
                        <span class="peri-status peri-status--empty">OPEN</span>
                    </div>
                </template>
            </section>

        </div>

        <!-- ── Stat reference link ──────────────────────────────────────── -->
        <div class="rig-ref-footer">
            <span class="rig-ref-label">// FULL STAT REFERENCE</span>
            <button class="rig-ref-link" @click="spliceNavigate('splice://sys.local/guide/stats')">
                splice://sys.local/guide/stats ›
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue';

defineProps({ url: { type: String, default: '' } });

const gameState      = inject('gameState', null);
const spliceNavigate = inject('spliceNavigate', () => {});
const rig    = gameState?.rig    ?? ref({ os:0, ram:0, cpu:0, storage:0, firewall:0, uplink:3, peripherals:[], portSlots:0, chassis:'UNKNOWN', tier:1, caps:{}, investedPoints:{} });
const player = gameState?.player ?? ref({ uplink:3, maxUplink:3 });

// ── Tier label ────────────────────────────────────────────────────────────────
const TIER_LABELS = ['', 'I', 'II', 'III', 'IV', 'V'];
const tierLabel = computed(() => TIER_LABELS[rig.value.tier ?? 1] ?? 'I');

// ── Chassis display name with live version suffix ─────────────────────────────
// Strips the static version suffix baked into the DB name (e.g. " v1.0") and
// replaces it with a live label: tier.totalInvestedPoints (e.g. "BlackHat v1.3").
const chassisDisplayName = computed(() => {
    const base = (rig.value.chassis ?? 'UNKNOWN').replace(/\s+v\d+\.\d+$/, '');
    const ip   = rig.value.investedPoints ?? {};
    const pts  = Object.values(ip).reduce((s, v) => s + (v ?? 0), 0);
    const tier = rig.value.tier ?? 1;
    return `${base} v${tier}.${pts}`;
});

// ── Point allocation ──────────────────────────────────────────────────────────
const pointsSpent = computed(() => {
    const ip = rig.value.investedPoints ?? {};
    return Object.values(ip).reduce((s, v) => s + (v ?? 0), 0);
});
const pointsCap   = computed(() => rig.value.pointsCap ?? 0);
const pointsPct   = computed(() => Math.min(100, (pointsSpent.value / pointsCap.value) * 100));
const pointsClass = computed(() => {
    const pct = pointsPct.value;
    if (pct >= 100) return 'pts--capped';
    if (pct >= 75)  return 'pts--high';
    if (pct >= 40)  return 'pts--mid';
    return 'pts--low';
});

// ── Live resources ────────────────────────────────────────────────────────────
const uplinkClass = computed(() => {
    const pct = (player.value.uplink ?? 0) / (player.value.maxUplink ?? 3);
    if (pct <= 0)    return 'live--crit';
    if (pct <= 0.34) return 'live--low';
    return 'live--ok';
});
const uplinkStatus = computed(() => {
    const u = player.value.uplink ?? 0;
    if (u <= 0) return 'DEPLETED';
    if (u <= 1) return 'CRITICAL';
    return 'NOMINAL';
});

// ── Core stats ────────────────────────────────────────────────────────────────
const STAT_META = {
    cpu:      { label: 'CPU',      effect: 'GridBreach difficulty cap — higher CPU lets you tackle stronger ICE nodes. Also sets your starting breach time.' },
    ram:      { label: 'RAM',      effect: 'Command loadout slots — each RAM point unlocks an additional command slot for your active loadout.' },
    os:       { label: 'OS',       effect: 'Ping defense — reduces how accurately ICE can triangulate your position. Higher OS = wider, less precise ping rings on you.' },
    storage:  { label: 'STORAGE',  effect: 'Inventory capacity — sets how many hardware items you can carry between CyberDoc visits.' },
    firewall: { label: 'FIREWALL', effect: 'ICE ping duration — reduces how long your breach signature lingers on the network after a hack.' },
};

const effectiveStats = computed(() => {
    const r = rig.value;
    // rig[stat] is already the effective stat (base + invested + peripheral + degradation)
    // as returned by RigService::effectiveStats(). Do not add peripheral boosts again.
    return { cpu: r.cpu, ram: r.ram, os: r.os, storage: r.storage, firewall: r.firewall };
});

const coreStats = computed(() => {
    const r = rig.value;
    const e = effectiveStats.value;
    return ['cpu', 'ram', 'os', 'storage', 'firewall'].map(key => {
        const invested  = r.investedPoints?.[key] ?? 0;
        const effective = e[key] ?? 0;
        // base = effective − invested gives the chassis-only contribution so that
        // pip segments can correctly distinguish base vs. invested vs. peripheral.
        const base      = Math.max(0, effective - invested);
        const cap       = r.caps?.[key] ?? 10;
        return {
            key,
            label:    STAT_META[key].label,
            effect:   STAT_META[key].effect,
            base,
            invested,
            effective,
            cap,
            boosted:  effective > (base + invested),
        };
    });
});

// Segment class per pip position
function segClass(i, s) {
    if (i > s.cap) return 'seg--disabled';
    if (i > s.effective) return 'seg--off';
    if (s.boosted && i > s.base + s.invested) return 'seg--boosted';
    if (i > s.base) return 'seg--invested';
    return 'seg--base';
}

// ── Peripheral counts ─────────────────────────────────────────────────────────
const installedCount = computed(() => (rig.value.peripherals ?? []).filter(p => p.installed && !p.damaged).length);
const emptySlots     = computed(() => Math.max(0, (rig.value.portSlots ?? 0) - (rig.value.peripherals ?? []).length));

// ── Expanded stat ─────────────────────────────────────────────────────────────
const expandedStat = ref(null);
function toggleStat(key) {
    expandedStat.value = expandedStat.value === key ? null : key;
}
</script>

<style scoped>
/* ── Page shell ───────────────────────────────────────────────────────────── */
.rig-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    color: #00FFFF;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.rig-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px 10px;
    border-bottom: 1px solid rgba(0,255,255,0.1);
    flex-shrink: 0;
    background: rgba(0,255,255,0.015);
}
.rig-header-left { display: flex; align-items: center; gap: 12px; }
.rig-icon        { font-size: 18px; color: rgba(0,255,255,0.5); line-height: 1; }
.rig-title-stack { display: flex; flex-direction: column; gap: 2px; }
.rig-chassis     { font-size: 13px; color: #00FFFF; letter-spacing: 0.1em; text-shadow: 0 0 12px rgba(0,255,255,0.35); }
.rig-sub         { font-size: 7px; color: rgba(0,255,255,0.5); letter-spacing: 0.18em; }

.rig-tier-badge {
    font-size: 8px;
    letter-spacing: 0.2em;
    color: rgba(0,255,255,0.85);
    border: 1px solid rgba(0,255,255,0.35);
    padding: 3px 10px;
    background: rgba(0,255,255,0.06);
}

/* ── Points strip ─────────────────────────────────────────────────────────── */
.rig-points-strip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 20px;
    border-bottom: 1px solid rgba(0,255,255,0.06);
    flex-shrink: 0;
    background: rgba(0,0,0,0.2);
}
.pts-label {
    font-size: 7px;
    color: rgba(0,255,255,0.6);
    letter-spacing: 0.16em;
    flex-shrink: 0;
    width: 52px;
}
.pts-bar-wrap { flex: 1; }
.pts-bar-track {
    height: 3px;
    background: rgba(0,255,255,0.07);
    position: relative;
}
.pts-bar-fill { height: 100%; transition: width 0.5s ease; }
.pts-bar-marks {
    position: absolute;
    inset: 0;
    display: flex;
    justify-content: space-evenly;
    pointer-events: none;
}
.pts-bar-marks span { width: 1px; background: rgba(6,6,13,0.9); }

.pts-val {
    font-size: 9px;
    letter-spacing: 0.06em;
    flex-shrink: 0;
    white-space: nowrap;
}
.pts-sep { color: rgba(0,255,255,0.45); }

/* Points colour states */
.pts--low  .pts-bar-fill { background: rgba(0,255,136,0.6); }
.pts--mid  .pts-bar-fill { background: rgba(0,255,255,0.6); }
.pts--high .pts-bar-fill { background: rgba(255,179,0,0.7); }
.pts--capped .pts-bar-fill { background: rgba(255,179,0,0.85); }

.pts--low   { color: #00FF88; }
.pts--mid   { color: rgba(0,255,255,0.9); }
.pts--high  { color: #FFB300; }
.pts--capped { color: #FFB300; }

/* ── Scrollable body ──────────────────────────────────────────────────────── */
.rig-page-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0;
}
.rig-page-body::-webkit-scrollbar       { width: 2px; }
.rig-page-body::-webkit-scrollbar-track { background: transparent; }
.rig-page-body::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Sections ─────────────────────────────────────────────────────────────── */
.rig-section { padding: 14px 20px; }
.rig-divider { height: 1px; background: rgba(0,255,255,0.06); flex-shrink: 0; }

.section-head {
    font-size: 7px;
    color: rgba(0,255,255,0.55);
    letter-spacing: 0.22em;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.head-tag {
    color: rgba(0,255,255,0.7);
    border: 1px solid rgba(0,255,255,0.25);
    padding: 1px 6px;
    font-size: 7px;
    letter-spacing: 0.12em;
}

/* ── Live resource rows ───────────────────────────────────────────────────── */
.live-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
}

.live-key {
    font-size: 8px;
    color: rgba(0,255,255,0.65);
    letter-spacing: 0.14em;
    width: 58px;
    flex-shrink: 0;
}

.live-segs {
    display: flex;
    gap: 3px;
    flex-shrink: 0;
}
.live-seg {
    display: inline-block;
    width: 14px;
    height: 6px;
    border-radius: 1px;
}

/* Live segment colours */
.seg--uplink  { background: #00FF88; box-shadow: 0 0 4px rgba(0,255,136,0.5); }
.seg--off     { background: rgba(0,255,255,0.07); }

@keyframes seg-crit { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }

.live-val {
    font-size: 11px;
    letter-spacing: 0.06em;
    flex-shrink: 0;
    min-width: 52px;
}
.live-unit { font-size: 8px; opacity: 0.5; }

.live-status {
    font-size: 7px;
    letter-spacing: 0.14em;
    margin-left: auto;
    flex-shrink: 0;
}

/* Live colour classes */
.live--ok   { color: #00FF88; }
.live--mid  { color: rgba(0,255,255,0.7); }
.live--low  { color: #FFB300; }
.live--high { color: #FF6B00; }
.live--crit { color: #FF3333; animation: live-crit-pulse 0.7s ease-in-out infinite; }
@keyframes live-crit-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

/* ── Core stat rows ───────────────────────────────────────────────────────── */
.stat-row {
    border: 1px solid rgba(0,255,255,0.05);
    margin-bottom: 4px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.stat-row:hover       { border-color: rgba(0,255,255,0.18); background: rgba(0,255,255,0.02); }
.stat-row--boosted    { border-color: rgba(0,255,136,0.15); }
.stat-row--boosted:hover { border-color: rgba(0,255,136,0.35); }
.stat-row--expanded   { border-color: rgba(0,255,255,0.22); background: rgba(0,255,255,0.025); }
.stat-row--capped     { border-color: rgba(255,179,0,0.2); }
.stat-row--locked     { cursor: default; opacity: 0.7; }
.stat-row--locked:hover { border-color: rgba(0,255,255,0.05); background: transparent; }

.stat-main {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
}

.stat-key {
    font-size: 8px;
    color: rgba(0,255,255,0.75);
    letter-spacing: 0.14em;
    width: 62px;
    flex-shrink: 0;
}

/* Segmented stat bar */
.stat-bar {
    display: flex;
    gap: 2px;
    flex: 1;
}
.stat-seg {
    height: 8px;
    flex: 1;
    border-radius: 1px;
    transition: background 0.2s;
}

/* Stat segment colours */
.seg--base     { background: rgba(0,255,255,0.45); }
.seg--invested { background: #00FFFF; box-shadow: 0 0 4px rgba(0,255,255,0.4); }
.seg--boosted  { background: #00FF88; box-shadow: 0 0 4px rgba(0,255,136,0.5); }
.seg--uplink-base { background: rgba(0,255,136,0.5); }
.seg--off      { background: rgba(0,255,255,0.06); }
.seg--disabled { background: rgba(0,255,255,0.02); }

.stat-num {
    font-size: 14px;
    color: rgba(0,255,255,0.9);
    letter-spacing: 0;
    width: 22px;
    text-align: right;
    flex-shrink: 0;
    line-height: 1;
}
.stat-num--boosted { color: #00FF88; text-shadow: 0 0 8px rgba(0,255,136,0.4); }
.stat-num--capped  { color: #FFB300; }
.stat-num--locked  { color: rgba(0,255,255,0.6); }

.stat-cap {
    font-size: 8px;
    color: rgba(0,255,255,0.5);
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.stat-boost-tag {
    font-size: 7px;
    color: #00FF88;
    border: 1px solid rgba(0,255,136,0.3);
    padding: 1px 5px;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.stat-locked-tag {
    font-size: 7px;
    color: rgba(0,255,255,0.55);
    border: 1px solid rgba(0,255,255,0.25);
    padding: 1px 5px;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.stat-arrow {
    font-size: 14px;
    color: rgba(0,255,255,0.45);
    transition: transform 0.2s ease, color 0.15s;
    flex-shrink: 0;
    line-height: 1;
}
.stat-arrow--open {
    transform: rotate(90deg);
    color: rgba(0,255,255,0.5);
}

/* Expanded detail panel */
.stat-detail {
    border-top: 1px solid rgba(0,255,255,0.07);
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-detail-grid {
    display: flex;
    gap: 20px;
}
.sdg-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.sdg-key {
    font-size: 6px;
    color: rgba(0,255,255,0.55);
    letter-spacing: 0.16em;
}
.sdg-val          { font-size: 11px; color: rgba(0,255,255,0.85); }
.sdg-val--invested { color: #00FFFF; }
.sdg-val--boost    { color: #00FF88; }

.stat-effect {
    font-size: 8px;
    color: rgba(0,255,255,0.7);
    letter-spacing: 0.03em;
    line-height: 1.55;
    border-left: 2px solid rgba(0,255,255,0.1);
    padding-left: 8px;
}

.stat-effect-inline {
    font-size: 8px;
    color: rgba(0,255,255,0.6);
    letter-spacing: 0.03em;
    padding: 0 12px 8px;
    line-height: 1.5;
}

/* Expand transition */
.stat-expand-enter-active { transition: opacity 0.15s ease, max-height 0.2s ease; overflow: hidden; max-height: 120px; }
.stat-expand-leave-active { transition: opacity 0.1s ease, max-height 0.15s ease; overflow: hidden; max-height: 120px; }
.stat-expand-enter-from, .stat-expand-leave-to { opacity: 0; max-height: 0; }

/* ── Peripheral rows ──────────────────────────────────────────────────────── */
.peri-locked-msg {
    font-size: 8px;
    color: rgba(0,255,255,0.55);
    letter-spacing: 0.08em;
    padding: 6px 0;
}

.peri-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 12px;
    border: 1px solid rgba(0,255,255,0.05);
    margin-bottom: 3px;
}
.peri-row--active  { border-color: rgba(0,255,136,0.18); }
.peri-row--damaged { border-color: rgba(255,179,0,0.2); }
.peri-row--empty   { opacity: 0.35; }

.peri-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
    background: #00FF88;
    box-shadow: 0 0 5px rgba(0,255,136,0.6);
}
.peri-row--damaged .peri-dot { background: #FFB300; box-shadow: 0 0 5px rgba(255,179,0,0.5); }
.peri-dot--empty  { background: transparent; border: 1px solid rgba(0,255,255,0.2); box-shadow: none; }

.peri-name        { font-size: 10px; color: rgba(255,255,255,0.88); flex: 1; letter-spacing: 0.04em; }
.peri-name--empty { color: rgba(0,255,255,0.6); }
.peri-boost       { font-size: 10px; color: #00FF88; flex-shrink: 0; }
.peri-status      { font-size: 7px; letter-spacing: 0.12em; flex-shrink: 0; }
.peri-row--active  .peri-status { color: #00FF88; }
.peri-row--damaged .peri-status { color: #FFB300; }
.peri-status--empty { color: rgba(0,255,255,0.5); }

/* ── Stat reference footer ───────────────────────────────────────────────────── */
.rig-ref-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    border-top: 1px solid rgba(0,255,255,0.06);
    flex-shrink: 0;
}
.rig-ref-label { font-size: 8px; color: rgba(0,255,255,0.2); letter-spacing: 0.14em; }
.rig-ref-link {
    background: transparent;
    border: none;
    font-family: inherit;
    font-size: 8px;
    color: rgba(0,255,255,0.35);
    letter-spacing: 0.06em;
    cursor: pointer;
    padding: 0;
    transition: color 0.12s;
}
.rig-ref-link:hover { color: #00FFFF; }
</style>
