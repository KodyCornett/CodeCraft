<template>
    <div class="panel">
        <div class="panel-header">[RIG_INFO]</div>

        <div class="panel-body">

            <!-- Chassis -->
            <div class="section section--first">
                <div class="row">
                    <span class="label">CHASSIS</span>
                    <span class="val">{{ rig.chassis ?? 'UNKNOWN' }}</span>
                </div>
                <div class="row">
                    <span class="label">PORT SLOTS</span>
                    <span class="val" :class="portSlotsUsed >= rig.portSlots ? 'val--amber' : ''">
                        {{ portSlotsUsed }} / {{ rig.portSlots ?? 4 }} USED
                    </span>
                </div>
                <div class="row">
                    <span class="label">POINT CAP</span>
                    <span class="val" :class="atCap ? 'val--amber' : ''">
                        {{ totalPoints }} / {{ rig.pointCap ?? 20 }} USED
                    </span>
                </div>
            </div>

            <!-- Stat Levels -->
            <div class="section">
                <div class="section-title">[STAT LEVELS]</div>

                <div v-for="stat in STATS" :key="stat.key" class="stat-row">
                    <span class="stat-name">{{ stat.label }}</span>
                    <span class="stat-pips">
                        <span
                            v-for="i in 10"
                            :key="i"
                            class="pip"
                            :class="i <= (rig[stat.key] ?? 0) ? 'pip--filled' : 'pip--empty'"
                        />
                    </span>
                    <span class="stat-level">LVL {{ String(rig[stat.key] ?? 0).padStart(2, '0') }}</span>
                    <span class="stat-effect">{{ stat.effect(rig) }}</span>
                </div>

                <div v-if="atCap" class="cap-warning">
                    ⚠ CHASSIS AT CAP — UPGRADES WILL TAX NEIGHBOR STAT
                </div>
            </div>

            <!-- Peripherals -->
            <div class="section">
                <div class="section-title">[PERIPHERALS]</div>

                <div v-if="!rig.peripherals?.length" class="empty-msg">
                    // NO PERIPHERALS INSTALLED
                </div>

                <div
                    v-for="p in rig.peripherals ?? []"
                    :key="p.id"
                    class="periph-row"
                >
                    <span class="periph-arrow">▸</span>
                    <span class="periph-name">{{ p.name }}</span>
                    <span class="periph-boost">+{{ p.boost }} {{ p.stat.toUpperCase() }}</span>
                    <span class="periph-status" :class="periphStatusClass(p)">
                        {{ periphStatusLabel(p) }}
                    </span>
                </div>

                <div class="periph-summary">
                    Effective boosts: {{ effectiveBoostSummary }}
                </div>
            </div>

        </div>

        <button class="panel-close" @click="$emit('close')">[CLOSE]</button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    player:    { type: Object, required: true },
    rig:       { type: Object, required: true },
    commands:  { type: Array,  default: () => [] },
    inventory: { type: Array,  default: () => [] },
});

defineEmits(['close']);

const STATS = [
    { key: 'os',       label: 'OS      ', effect: r => `Max SS +${(r.os ?? 0) * 10}`      },
    { key: 'ram',      label: 'RAM     ', effect: r => `Loadout slots: ${(r.ram ?? 0) + 1}` },
    { key: 'cpu',      label: 'CPU     ', effect: r => `Move pts: ${(r.cpu ?? 0) + 1}`      },
    { key: 'storage',  label: 'STORAGE ', effect: r => `Loot slots: ${(r.storage ?? 0) + 1}`},
    { key: 'firewall', label: 'FIREWALL', effect: r => `Ping −${(r.firewall ?? 0) * 15}s`   },
];

const totalPoints = computed(() =>
    ['os', 'ram', 'cpu', 'storage', 'firewall'].reduce((s, k) => s + (props.rig[k] ?? 0), 0),
);

const atCap = computed(() => totalPoints.value >= (props.rig.pointCap ?? 20));

const portSlotsUsed = computed(() =>
    (props.rig.peripherals ?? []).filter(p => p.installed).length,
);

const effectiveBoosts = computed(() => {
    const totals = { os: 0, ram: 0, cpu: 0, storage: 0, firewall: 0 };
    for (const p of (props.rig.peripherals ?? [])) {
        if (p.installed && !p.damaged && p.stat in totals) {
            totals[p.stat] += p.boost;
        }
    }
    return totals;
});

const effectiveBoostSummary = computed(() => {
    const parts = Object.entries(effectiveBoosts.value)
        .filter(([, v]) => v > 0)
        .map(([k, v]) => `${k.toUpperCase()} +${v}`);
    return parts.length ? parts.join(' · ') : 'NONE';
});

function periphStatusLabel(p) {
    if (!p.installed) return '[INVENTORY]';
    if (p.damaged)    return '[DAMAGED]';
    return '[INSTALLED]';
}

function periphStatusClass(p) {
    if (!p.installed) return 'status--inventory';
    if (p.damaged)    return 'status--damaged';
    return 'status--installed';
}
</script>

<style scoped>
.panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: rgba(5, 5, 5, 0.97);
    font-family: 'JetBrains Mono', monospace;
    color: #00FFFF;
}

.panel-header {
    padding: 14px 18px;
    font-size: 12px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.2);
    flex-shrink: 0;
}

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
}

/* ── Sections ─────────────────────────────────────────────────────────────────── */
.section {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid rgba(0, 255, 255, 0.1);
}

.section--first {
    padding-top: 0;
    margin-top: 0;
    border-top: none;
}

.section-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.08em;
    margin-bottom: 9px;
}

/* ── Basic rows ───────────────────────────────────────────────────────────────── */
.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    margin-bottom: 5px;
}

.label { color: rgba(0, 255, 255, 0.45); }
.val   { color: #00FFFF; }
.val--amber { color: #FFB300; }

/* ── Stat rows ────────────────────────────────────────────────────────────────── */
.stat-row {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    margin-bottom: 6px;
}

.stat-name {
    color: rgba(0, 255, 255, 0.6);
    letter-spacing: 0.04em;
    min-width: 58px;
    flex-shrink: 0;
    white-space: pre;
}

.stat-pips {
    display: flex;
    gap: 2px;
    flex-shrink: 0;
}

.pip {
    display: inline-block;
    width: 10px;
    height: 6px;
}

.pip--filled { background: #00FFFF; }
.pip--empty  { background: rgba(0, 255, 255, 0.12); }

.stat-level {
    color: rgba(0, 255, 255, 0.5);
    min-width: 48px;
    flex-shrink: 0;
}

.stat-effect {
    color: rgba(0, 255, 255, 0.35);
    font-size: 9px;
    margin-left: auto;
    text-align: right;
}

.cap-warning {
    font-size: 9px;
    color: #FFB300;
    border-left: 2px solid #FFB300;
    padding: 4px 8px;
    margin-top: 8px;
    background: rgba(255, 179, 0, 0.05);
    letter-spacing: 0.03em;
}

/* ── Peripheral rows ──────────────────────────────────────────────────────────── */
.periph-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    margin-bottom: 5px;
}

.periph-arrow { color: rgba(0, 255, 255, 0.4); flex-shrink: 0; }
.periph-name  { color: rgba(0, 255, 255, 0.8); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.periph-boost { color: #00FFFF; min-width: 52px; flex-shrink: 0; }

.periph-status         { font-size: 9px; flex-shrink: 0; }
.status--installed     { color: #00FF88; }
.status--damaged       { color: #FF3333; }
.status--inventory     { color: rgba(0, 255, 255, 0.3); }

.periph-summary {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    margin-top: 8px;
    letter-spacing: 0.03em;
}

.empty-msg {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.25);
    margin-bottom: 6px;
}

/* ── Close ────────────────────────────────────────────────────────────────────── */
.panel-close {
    margin: 10px 18px;
    padding: 7px 14px;
    align-self: flex-start;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.35);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}

.panel-close:hover { background: rgba(0, 255, 255, 0.07); }
</style>
