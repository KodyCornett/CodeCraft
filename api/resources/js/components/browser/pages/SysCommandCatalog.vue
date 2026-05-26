<template>
    <div class="catalog-page">

        <!-- ── Header ───────────────────────────────────────────────────────── -->
        <header class="cat-header">
            <div class="cat-header-left">
                <span class="cat-title">COMMAND CATALOG</span>
                <span class="cat-sub">{{ ownedCount }}/{{ allCommands.length }} OWNED</span>
            </div>
            <div class="cat-filters">
                <button
                    v-for="f in filters"
                    :key="f.id"
                    class="filter-btn"
                    :class="{ active: activeFilter === f.id }"
                    @click="activeFilter = f.id"
                >{{ f.label }}</button>
            </div>
        </header>

        <!-- ── Body ─────────────────────────────────────────────────────────── -->
        <div class="cat-body">

            <template v-for="tier in [1, 2, 3, 4, 5]" :key="tier">
                <div v-if="commandsForTier(tier).length" class="tier-section">

                    <div class="tier-heading">
                        <span class="tier-label">TIER {{ tier }}</span>
                        <span class="tier-ram">RAM {{ tier }}+ REQUIRED</span>
                        <span class="tier-price-hint">
                            {{ tierPrice(tier).creds.toLocaleString() }} ₡ + {{ tierPrice(tier).techPoints }} TP
                        </span>
                    </div>

                    <div class="tier-grid">
                        <div
                            v-for="cmd in commandsForTier(tier)"
                            :key="cmd.id"
                            class="cmd-card"
                            :class="{
                                'cmd-card--owned':    cmd.owned,
                                'cmd-card--expanded': expandedId === cmd.id,
                            }"
                            @click="toggleExpand(cmd.id)"
                        >
                            <!-- Card header row -->
                            <div class="card-top">
                                <span class="card-type" :class="`type--${cmd.type}`">
                                    {{ cmd.type.toUpperCase() }}
                                </span>
                                <span class="card-name">{{ cmd.name.toUpperCase() }}</span>
                                <span v-if="cmd.owned" class="card-owned-badge">OWNED</span>
                                <span v-else class="card-price">
                                    {{ cmd.price.creds.toLocaleString() }}₡ + {{ cmd.price.techPoints }}TP
                                </span>
                            </div>

                            <!-- Quick effect summary (always visible) -->
                            <div class="card-summary">
                                <span class="summary-key">MAP</span>
                                <span class="summary-val">{{ cmd.mapEffect }}</span>
                            </div>

                            <!-- Expanded: full detail -->
                            <Transition name="expand">
                                <div v-if="expandedId === cmd.id" class="card-detail">
                                    <div v-if="cmd.mapEffect" class="detail-row">
                                        <span class="detail-key">MAP</span>
                                        <span class="detail-val">{{ cmd.mapEffect }}</span>
                                    </div>
                                    <div v-if="cmd.gridbreachEffect" class="detail-row">
                                        <span class="detail-key">GRIDBREACH</span>
                                        <span class="detail-val">{{ cmd.gridbreachEffect }}</span>
                                    </div>
                                    <div v-if="cmd.packethijackEffect" class="detail-row">
                                        <span class="detail-key">PACKET HIJACK</span>
                                        <span class="detail-val">{{ cmd.packethijackEffect }}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-key">TARGET</span>
                                        <span class="detail-val detail-val--meta">{{ targetLabel(cmd.targetType) }}</span>
                                    </div>
                                    <div v-if="cmd.duration" class="detail-row">
                                        <span class="detail-key">DURATION</span>
                                        <span class="detail-val detail-val--meta">{{ durationLabel(cmd.duration) }}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-key">COOLDOWN</span>
                                        <span class="detail-val detail-val--meta">Until CyberDoc visit</span>
                                    </div>
                                    <div v-if="!cmd.owned" class="card-cta">
                                        Visit CyberDoc to purchase
                                    </div>
                                </div>
                            </Transition>

                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue';

defineProps({ url: { type: String, default: '' } });

const gameState   = inject('gameState', null);
const allCommands = gameState?.commands ?? ref([]);

const activeFilter = ref('all');
const expandedId   = ref(null);

const filters = [
    { id: 'all',       label: 'ALL'       },
    { id: 'owned',     label: 'OWNED'     },
    { id: 'trap',      label: 'TRAP'      },
    { id: 'stealth',   label: 'STEALTH'   },
    { id: 'defensive', label: 'DEFENSIVE' },
    { id: 'offensive', label: 'OFFENSIVE' },
];

const ownedCount = computed(() => allCommands.value.filter(c => c.owned).length);

function commandsForTier(tier) {
    return allCommands.value.filter(c => {
        if (c.tier !== tier) return false;
        if (activeFilter.value === 'all') return true;
        if (activeFilter.value === 'owned') return c.owned;
        return c.type === activeFilter.value;
    });
}

// Price hint shown on tier header — uses first command in tier (all same price per tier)
function tierPrice(tier) {
    return allCommands.value.find(c => c.tier === tier)?.price ?? { creds: 0, techPoints: 0 };
}

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

function targetLabel(type) {
    return { self: 'Self — activates immediately', node: 'Node — select a target node on the map', player: 'Player — select a target runner' }[type] ?? type;
}

function durationLabel(d) {
    if (!d) return 'Instant';
    const parts = [];
    if (d.moves)   parts.push(`${d.moves} of your moves`);
    if (d.minutes) parts.push(`${d.minutes} minutes`);
    if (d.hacks)   parts.push(`${d.hacks} of target's hacks`);
    return parts.length > 1 ? parts.join(' or ') + ' (whichever comes first)' : parts[0];
}
</script>

<style scoped>
.catalog-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    color: rgba(0,255,255,0.85);
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.cat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 0;
    flex-shrink: 0;
    gap: 16px;
    flex-wrap: wrap;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    padding-bottom: 0;
}
.cat-header-left { display: flex; align-items: baseline; gap: 12px; padding-bottom: 12px; }
.cat-title { font-size: 13px; color: #00FFFF; letter-spacing: 0.12em; text-shadow: 0 0 10px rgba(0,255,255,0.25); }
.cat-sub   { font-size: 9px;  color: rgba(0,255,255,0.3); letter-spacing: 0.1em; }

.cat-filters { display: flex; gap: 0; padding-bottom: 0; }
.filter-btn {
    padding: 8px 14px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: rgba(0,255,255,0.3);
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.12em;
    cursor: pointer;
    transition: color 0.12s, border-color 0.12s;
}
.filter-btn:hover  { color: rgba(0,255,255,0.65); }
.filter-btn.active { color: #00FFFF; border-bottom-color: #00FFFF; }

/* ── Body ───────────────────────────────────────────────────────────────────── */
.cat-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px 32px;
    display: flex;
    flex-direction: column;
    gap: 28px;
}
.cat-body::-webkit-scrollbar       { width: 3px; }
.cat-body::-webkit-scrollbar-track { background: transparent; }
.cat-body::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Tier section ───────────────────────────────────────────────────────────── */
.tier-section { display: flex; flex-direction: column; gap: 8px; }

.tier-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(0,255,255,0.07);
}
.tier-label      { font-size: 9px; color: rgba(0,255,255,0.45); letter-spacing: 0.16em; }
.tier-ram        { font-size: 8px; color: rgba(0,255,255,0.2);  letter-spacing: 0.1em; }
.tier-price-hint { font-size: 8px; color: rgba(255,179,0,0.35); letter-spacing: 0.08em; margin-left: auto; }

/* ── Command cards ──────────────────────────────────────────────────────────── */
.tier-grid { display: flex; flex-direction: column; gap: 4px; }

.cmd-card {
    border: 1px solid rgba(0,255,255,0.07);
    background: rgba(0,0,0,0.2);
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s;
    overflow: hidden;
}
.cmd-card:hover            { border-color: rgba(0,255,255,0.2); background: rgba(0,255,255,0.02); }
.cmd-card--owned           { border-color: rgba(0,255,136,0.15); background: rgba(0,255,136,0.02); }
.cmd-card--owned:hover     { border-color: rgba(0,255,136,0.3); }
.cmd-card--expanded        { border-color: rgba(0,255,255,0.25) !important; }

.card-top {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
}

/* Type badge */
.card-type {
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 1px 5px;
    border: 1px solid;
    flex-shrink: 0;
}
.type--trap      { color: rgba(255,69,180,0.8);  border-color: rgba(255,69,180,0.3);  background: rgba(255,69,180,0.05); }
.type--stealth   { color: rgba(125,249,255,0.8); border-color: rgba(125,249,255,0.3); background: rgba(125,249,255,0.05); }
.type--defensive { color: rgba(0,255,136,0.8);   border-color: rgba(0,255,136,0.3);   background: rgba(0,255,136,0.05); }
.type--offensive { color: rgba(255,69,69,0.9);   border-color: rgba(255,69,69,0.3);   background: rgba(255,69,69,0.05); }

.card-name  { font-size: 11px; color: rgba(0,255,255,0.8); letter-spacing: 0.06em; flex: 1; }
.card-owned-badge {
    font-size: 7px; color: rgba(0,255,136,0.65);
    border: 1px solid rgba(0,255,136,0.25); padding: 1px 6px; letter-spacing: 0.1em;
}
.card-price { font-size: 8px; color: rgba(255,179,0,0.5); letter-spacing: 0.06em; }

.card-summary {
    display: flex;
    gap: 10px;
    padding: 0 14px 9px;
    align-items: flex-start;
}
.summary-key { font-size: 6px; color: rgba(0,255,255,0.22); letter-spacing: 0.12em; width: 28px; flex-shrink: 0; padding-top: 2px; }
.summary-val { font-size: 8px; color: rgba(0,255,255,0.45); letter-spacing: 0.03em; line-height: 1.6; }

/* ── Expanded detail ────────────────────────────────────────────────────────── */
.card-detail {
    padding: 10px 14px 12px;
    border-top: 1px solid rgba(0,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 7px;
    background: rgba(0,255,255,0.01);
}
.detail-row { display: flex; gap: 10px; align-items: flex-start; }
.detail-key {
    font-size: 6px; color: rgba(0,255,255,0.22); letter-spacing: 0.12em;
    width: 52px; flex-shrink: 0; padding-top: 2px;
}
.detail-val      { font-size: 8px; color: rgba(0,255,255,0.55); letter-spacing: 0.03em; line-height: 1.6; }
.detail-val--meta { color: rgba(0,255,255,0.35); font-style: italic; }

.card-cta {
    margin-top: 4px;
    font-size: 7px;
    color: rgba(255,179,0,0.45);
    letter-spacing: 0.08em;
    border-left: 2px solid rgba(255,179,0,0.2);
    padding-left: 8px;
}

/* ── Expand transition ──────────────────────────────────────────────────────── */
.expand-enter-active,
.expand-leave-active {
    transition: opacity 0.15s ease, max-height 0.22s ease;
    overflow: hidden;
    max-height: 200px;
}
.expand-enter-from,
.expand-leave-to { opacity: 0; max-height: 0; }
</style>
