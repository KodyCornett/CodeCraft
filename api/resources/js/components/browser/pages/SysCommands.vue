<template>
    <div class="sys-page">

        <!-- ── Header ───────────────────────────────────────────────────────── -->
        <header class="sys-header">
            <div class="sys-header-left">
                <span class="sys-title">SYS.COMMANDS</span>
                <span class="sys-sub">{{ equippedCommands.length }}/{{ totalSlots }} SLOTS</span>
            </div>
            <div class="sys-header-right">
                <span class="sys-slot-pip" title="Map slots">MAP {{ mapSlotsUsed }}/{{ slots.map }}</span>
                <span class="sys-slot-sep">·</span>
                <span class="sys-slot-pip" title="Hack slots">HACK {{ hackSlotsUsed }}/{{ slots.hack }}</span>
                <template v-if="slots.open > 0">
                    <span class="sys-slot-sep">·</span>
                    <span class="sys-slot-pip" title="Open slots">OPEN {{ openSlotsUsed }}/{{ slots.open }}</span>
                </template>
            </div>
        </header>

        <div class="sys-body">

            <!-- ── LOADOUT ───────────────────────────────────────────────────── -->
            <section class="sys-section">

                <div class="section-title-row">
                    <span class="section-title">ACTIVE LOADOUT</span>
                    <span class="section-slots" :class="slotsClass">
                        {{ equippedCommands.length }} / {{ totalSlots }}
                    </span>
                </div>

                <!-- Map slot group -->
                <div class="slot-group-label">MAP SLOTS</div>
                <template v-for="i in slots.map" :key="`map-${i}`">
                    <div v-if="equippedByContext('map')[i - 1]" class="cmd-row"
                        :class="{
                            'cmd-row--cooldown': equippedByContext('map')[i - 1].cooldown,
                            'cmd-row--expanded': expandedId === equippedByContext('map')[i - 1].id,
                        }"
                        @click="toggleExpand(equippedByContext('map')[i - 1].id)"
                    >
                        <div class="cmd-main">
                            <span class="cmd-status-dot" :class="equippedByContext('map')[i - 1].cooldown ? 'dot--cd' : 'dot--ready'" />
                            <span class="cmd-context-badge ctx--map">MAP</span>
                            <span class="cmd-type-badge" :class="`type--${equippedByContext('map')[i - 1].type}`">
                                {{ equippedByContext('map')[i - 1].type.toUpperCase() }}
                            </span>
                            <span class="cmd-name" :class="{ 'name--cd': equippedByContext('map')[i - 1].cooldown }">
                                {{ equippedByContext('map')[i - 1].name.toUpperCase() }}
                            </span>
                            <span v-if="equippedByContext('map')[i - 1].cooldown" class="cmd-cd-tag">COOLDOWN</span>
                            <button v-else class="cmd-unequip-btn" title="Remove from loadout"
                                @click.stop="unequipCommand(equippedByContext('map')[i - 1])">[–]</button>
                        </div>
                        <Transition name="expand">
                            <div v-if="expandedId === equippedByContext('map')[i - 1].id" class="cmd-detail">
                                <div v-if="equippedByContext('map')[i - 1].cooldown" class="cmd-cd-notice">⚠ ON COOLDOWN — RESET AT CYBERDOC</div>
                                <div v-if="equippedByContext('map')[i - 1].mapEffect" class="cmd-effect-row">
                                    <span class="effect-key">MAP</span>
                                    <span class="effect-val">{{ equippedByContext('map')[i - 1].mapEffect }}</span>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <div v-else class="cmd-row cmd-row--empty">
                        <span class="empty-slot-label">[ MAP SLOT {{ i }} ]</span>
                    </div>
                </template>

                <!-- Hack slot group -->
                <div class="slot-group-label slot-group-label--hack">HACK SLOTS</div>
                <template v-for="i in slots.hack" :key="`hack-${i}`">
                    <div v-if="equippedByContext('hack')[i - 1]" class="cmd-row"
                        :class="{
                            'cmd-row--cooldown': equippedByContext('hack')[i - 1].cooldown,
                            'cmd-row--expanded': expandedId === equippedByContext('hack')[i - 1].id,
                        }"
                        @click="toggleExpand(equippedByContext('hack')[i - 1].id)"
                    >
                        <div class="cmd-main">
                            <span class="cmd-status-dot" :class="equippedByContext('hack')[i - 1].cooldown ? 'dot--cd' : 'dot--ready'" />
                            <span class="cmd-context-badge ctx--hack">HACK</span>
                            <span class="cmd-type-badge" :class="`type--${equippedByContext('hack')[i - 1].type}`">
                                {{ equippedByContext('hack')[i - 1].type.toUpperCase() }}
                            </span>
                            <span class="cmd-name" :class="{ 'name--cd': equippedByContext('hack')[i - 1].cooldown }">
                                {{ equippedByContext('hack')[i - 1].name.toUpperCase() }}
                            </span>
                            <span v-if="equippedByContext('hack')[i - 1].cooldown" class="cmd-cd-tag">COOLDOWN</span>
                            <button v-else class="cmd-unequip-btn" title="Remove from loadout"
                                @click.stop="unequipCommand(equippedByContext('hack')[i - 1])">[–]</button>
                        </div>
                        <Transition name="expand">
                            <div v-if="expandedId === equippedByContext('hack')[i - 1].id" class="cmd-detail">
                                <div v-if="equippedByContext('hack')[i - 1].cooldown" class="cmd-cd-notice">⚠ ON COOLDOWN — RESET AT CYBERDOC</div>
                                <div v-if="equippedByContext('hack')[i - 1].gridbreachEffect" class="cmd-effect-row">
                                    <span class="effect-key">GRIDBREACH</span>
                                    <span class="effect-val">{{ equippedByContext('hack')[i - 1].gridbreachEffect }}</span>
                                </div>
                                <div v-if="equippedByContext('hack')[i - 1].packethijackEffect" class="cmd-effect-row">
                                    <span class="effect-key">PACKET HIJACK</span>
                                    <span class="effect-val">{{ equippedByContext('hack')[i - 1].packethijackEffect }}</span>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <div v-else class="cmd-row cmd-row--empty">
                        <span class="empty-slot-label">[ HACK SLOT {{ i }} ]</span>
                    </div>
                </template>

                <!-- Open slot group (only rendered when chassis has open slots) -->
                <template v-if="slots.open > 0">
                    <div class="slot-group-label slot-group-label--open">OPEN SLOTS</div>
                    <template v-for="i in slots.open" :key="`open-${i}`">
                        <div v-if="equippedOpen[i - 1]" class="cmd-row"
                            :class="{
                                'cmd-row--cooldown': equippedOpen[i - 1].cooldown,
                                'cmd-row--expanded': expandedId === equippedOpen[i - 1].id,
                            }"
                            @click="toggleExpand(equippedOpen[i - 1].id)"
                        >
                            <div class="cmd-main">
                                <span class="cmd-status-dot" :class="equippedOpen[i - 1].cooldown ? 'dot--cd' : 'dot--ready'" />
                                <span class="cmd-context-badge" :class="`ctx--${equippedOpen[i - 1].context}`">
                                    {{ equippedOpen[i - 1].context.toUpperCase() }}
                                </span>
                                <span class="cmd-type-badge" :class="`type--${equippedOpen[i - 1].type}`">
                                    {{ equippedOpen[i - 1].type.toUpperCase() }}
                                </span>
                                <span class="cmd-name" :class="{ 'name--cd': equippedOpen[i - 1].cooldown }">
                                    {{ equippedOpen[i - 1].name.toUpperCase() }}
                                </span>
                                <span v-if="equippedOpen[i - 1].cooldown" class="cmd-cd-tag">COOLDOWN</span>
                                <button v-else class="cmd-unequip-btn" title="Remove from loadout"
                                    @click.stop="unequipCommand(equippedOpen[i - 1])">[–]</button>
                            </div>
                            <Transition name="expand">
                                <div v-if="expandedId === equippedOpen[i - 1].id" class="cmd-detail">
                                    <div v-if="equippedOpen[i - 1].cooldown" class="cmd-cd-notice">⚠ ON COOLDOWN — RESET AT CYBERDOC</div>
                                    <div v-if="equippedOpen[i - 1].mapEffect" class="cmd-effect-row">
                                        <span class="effect-key">MAP</span>
                                        <span class="effect-val">{{ equippedOpen[i - 1].mapEffect }}</span>
                                    </div>
                                    <div v-if="equippedOpen[i - 1].gridbreachEffect" class="cmd-effect-row">
                                        <span class="effect-key">GRIDBREACH</span>
                                        <span class="effect-val">{{ equippedOpen[i - 1].gridbreachEffect }}</span>
                                    </div>
                                    <div v-if="equippedOpen[i - 1].packethijackEffect" class="cmd-effect-row">
                                        <span class="effect-key">PACKET HIJACK</span>
                                        <span class="effect-val">{{ equippedOpen[i - 1].packethijackEffect }}</span>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                        <div v-else class="cmd-row cmd-row--empty">
                            <span class="empty-slot-label">[ OPEN SLOT {{ i }} ]</span>
                        </div>
                    </template>
                </template>

                <!-- Loadout full notice -->
                <div v-if="slotsAtMax" class="loadout-full-notice">
                    LOADOUT FULL — UNEQUIP A COMMAND TO SWAP
                </div>

            </section>

            <div class="sys-divider" />

            <!-- ── LIBRARY ───────────────────────────────────────────────────── -->
            <section class="sys-section">

                <div class="section-title-row">
                    <span class="section-title">COMMAND LIBRARY</span>
                    <span class="section-sub">{{ unequippedCommands.length }} OWNED / UNEQUIPPED</span>
                </div>
                <div v-if="unequippedCommands.length === 0" class="cmd-empty">
                    NO COMMANDS IN LIBRARY — VISIT CYBERDOC TO PURCHASE
                </div>

                <!-- Context group headers: map then hack -->
                <template v-for="ctx in ['map', 'hack']" :key="ctx">
                    <div v-if="commandsByContext(ctx).length" class="tier-group">
                        <div class="tier-header">
                            <span class="tier-label">{{ ctx.toUpperCase() }} COMMANDS</span>
                            <span class="tier-unlocked">
                                {{ contextSlotsAvailable(ctx) > 0 ? contextSlotsAvailable(ctx) + ' SLOT(S) FREE' : 'SLOTS FULL' }}
                            </span>
                        </div>

                        <div
                            v-for="cmd in commandsByContext(ctx)"
                            :key="cmd.id"
                            class="cmd-row cmd-row--library"
                            :class="{ 'cmd-row--expanded': expandedId === cmd.id }"
                            @click="toggleExpand(cmd.id)"
                        >
                            <div class="cmd-main">
                                <span class="cmd-context-badge" :class="`ctx--${cmd.context}`">
                                    {{ cmd.context.toUpperCase() }}
                                </span>
                                <span class="cmd-type-badge" :class="`type--${cmd.type}`">
                                    {{ cmd.type.toUpperCase() }}
                                </span>
                                <span class="cmd-name cmd-name--lib">
                                    {{ cmd.name.toUpperCase() }}
                                </span>
                                <button
                                    class="cmd-equip-btn"
                                    :disabled="contextSlotsAvailable(cmd.context) <= 0"
                                    :title="contextSlotsAvailable(cmd.context) <= 0 ? 'No slots available' : 'Add to loadout'"
                                    @click.stop="equipCommand(cmd)"
                                >
                                    [+]
                                </button>
                            </div>

                            <!-- Expanded effect detail -->
                            <Transition name="expand">
                                <div v-if="expandedId === cmd.id" class="cmd-detail">
                                    <div v-if="cmd.mapEffect" class="cmd-effect-row">
                                        <span class="effect-key">MAP</span>
                                        <span class="effect-val">{{ cmd.mapEffect }}</span>
                                    </div>
                                    <div v-if="cmd.gridbreachEffect" class="cmd-effect-row">
                                        <span class="effect-key">GRIDBREACH</span>
                                        <span class="effect-val">{{ cmd.gridbreachEffect }}</span>
                                    </div>
                                    <div v-if="cmd.packethijackEffect" class="cmd-effect-row">
                                        <span class="effect-key">PACKET HIJACK</span>
                                        <span class="effect-val">{{ cmd.packethijackEffect }}</span>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                    </div>
                </template>

            </section>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue';

defineProps({ url: { type: String, default: '' } });

const gameState = inject('gameState', null);
const commands  = gameState?.commands ?? ref([]);
const rig       = gameState?.rig      ?? ref({});

// Typed slot counts from chassis + installed command modules (populated by /api/player/me).
const slots = computed(() => rig.value?.loadoutSlots ?? { map: 1, hack: 1, open: 1, total: 3 });
const totalSlots = computed(() => slots.value.total ?? 3);

// Filtered views
const equippedCommands   = computed(() => commands.value.filter(c => c.equipped));
const unequippedCommands = computed(() => commands.value.filter(c => c.owned && !c.equipped));

// Equipped commands split by context (for typed slot columns).
// Open slots display overflow commands that didn't fit their typed slots.
function equippedByContext(ctx) {
    return equippedCommands.value.filter(c => c.context === ctx);
}

// For open slots: commands equipped beyond what the typed slots can hold.
const equippedOpen = computed(() => {
    const mapOverflow  = equippedByContext('map').slice(slots.value.map);
    const hackOverflow = equippedByContext('hack').slice(slots.value.hack);
    return [...mapOverflow, ...hackOverflow];
});

// Per-context slot usage counters (for the header pips).
const mapSlotsUsed  = computed(() => Math.min(equippedByContext('map').length,  slots.value.map));
const hackSlotsUsed = computed(() => Math.min(equippedByContext('hack').length, slots.value.hack));
const openSlotsUsed = computed(() => equippedOpen.value.length);

const slotsAtMax = computed(() => equippedCommands.value.length >= totalSlots.value);
const slotsClass = computed(() => slotsAtMax.value ? 'slots--full' : '');

// How many slots of the given context are still free (including open slots as overflow).
function contextSlotsAvailable(ctx) {
    const equipped = equippedByContext(ctx).length;
    const typed    = slots.value[ctx] ?? 0;
    const freeTyped = Math.max(0, typed - equipped);
    const freeOpen  = Math.max(0, slots.value.open - equippedOpen.value.length);
    return freeTyped + freeOpen;
}

// Library grouped by context
function commandsByContext(ctx) {
    return unequippedCommands.value.filter(c => c.context === ctx);
}

// Expand/collapse detail
const expandedId = ref(null);
function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

// Equip / unequip
function equipCommand(cmd) {
    if (contextSlotsAvailable(cmd.context) <= 0) return;
    cmd.equipped = true;
    if (expandedId.value === cmd.id) expandedId.value = null;
}

function unequipCommand(cmd) {
    cmd.equipped = false;
    if (expandedId.value === cmd.id) expandedId.value = null;
}
</script>

<style scoped>
.sys-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    color: rgba(0,255,255,0.85);
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.sys-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0,255,255,0.1);
    flex-shrink: 0;
}
.sys-header-left  { display: flex; align-items: baseline; gap: 12px; }
.sys-header-right { display: flex; align-items: center; gap: 8px; }

.sys-title    { font-size: 13px; color: #00FFFF; letter-spacing: 0.12em; text-shadow: 0 0 10px rgba(0,255,255,0.3); }
.sys-sub      { font-size: 9px;  color: rgba(0,255,255,0.3); letter-spacing: 0.1em; }
.sys-slot-pip { font-size: 8px; color: rgba(0,255,255,0.45); letter-spacing: 0.1em; }
.sys-slot-sep { font-size: 8px; color: rgba(0,255,255,0.15); margin: 0 2px; }

/* ── Body ───────────────────────────────────────────────────────────────────── */
.sys-body    { flex: 1; overflow-y: auto; padding: 0 24px 24px; }
.sys-divider { height: 1px; background: rgba(0,255,255,0.07); margin: 4px 0; }
.sys-section { padding: 16px 0 8px; }

.sys-body::-webkit-scrollbar       { width: 3px; }
.sys-body::-webkit-scrollbar-track { background: transparent; }
.sys-body::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

/* ── Section headers ────────────────────────────────────────────────────────── */
.section-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.section-title { font-size: 9px; color: rgba(0,255,255,0.3); letter-spacing: 0.16em; }
.section-sub   { font-size: 8px; color: rgba(0,255,255,0.18); letter-spacing: 0.1em; }
.section-slots { font-size: 8px; letter-spacing: 0.1em; color: rgba(0,255,255,0.4); }
.slots--full   { color: #FFB300; }

/* ── Slot group labels ──────────────────────────────────────────────────────── */
.slot-group-label {
    font-size: 7px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.18em;
    padding: 10px 0 4px;
}
.slot-group-label--hack { color: rgba(255,69,69,0.25); }
.slot-group-label--open { color: rgba(255,179,0,0.25); }

/* ── Command rows ───────────────────────────────────────────────────────────── */
.cmd-row {
    display: flex;
    flex-direction: column;
    margin-bottom: 3px;
    cursor: pointer;
    border: 1px solid rgba(0,255,255,0.08);
    transition: border-color 0.12s;
}
.cmd-row:hover:not(.cmd-row--empty):not(.cmd-row--locked) {
    border-color: rgba(0,255,255,0.22);
}
.cmd-row--cooldown { border-color: rgba(255,51,51,0.15) !important; opacity: 0.7; }
.cmd-row--empty    { cursor: default; border-style: dashed; border-color: rgba(0,255,255,0.06); }
.cmd-row--locked   { cursor: default; opacity: 0.35; }
.cmd-row--library  { border-color: rgba(0,255,255,0.05); background: rgba(0,0,0,0.2); }
.cmd-row--expanded { border-color: rgba(0,255,255,0.25); }

.cmd-main {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
}

/* Status dot */
.cmd-status-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot--ready { background: #00FF88; box-shadow: 0 0 5px rgba(0,255,136,0.5); animation: dot-pulse 2s ease-in-out infinite; }
.dot--cd    { background: rgba(255,51,51,0.4); }
@keyframes dot-pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* Context badge — map / hack indicator on equipped and library rows */
.cmd-context-badge {
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 1px 5px;
    border: 1px solid;
    flex-shrink: 0;
    white-space: nowrap;
}
.ctx--map  { color: rgba(0,255,255,0.7);  border-color: rgba(0,255,255,0.25);  background: rgba(0,255,255,0.04); }
.ctx--hack { color: rgba(255,69,69,0.75); border-color: rgba(255,69,69,0.25);  background: rgba(255,69,69,0.04); }

/* Tier (kept for any legacy refs, hidden from new rows) */
.cmd-tier       { font-size: 8px; color: rgba(0,255,255,0.3); flex-shrink: 0; }
.tier--locked   { color: rgba(255,255,255,0.15); }

/* Type badge */
.cmd-type-badge {
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 1px 5px;
    border: 1px solid;
    flex-shrink: 0;
    white-space: nowrap;
}
.type--trap      { color: rgba(255,69,180,0.8);  border-color: rgba(255,69,180,0.3);  background: rgba(255,69,180,0.05); }
.type--stealth   { color: rgba(125,249,255,0.8); border-color: rgba(125,249,255,0.3); background: rgba(125,249,255,0.05); }
.type--defensive { color: rgba(0,255,136,0.8);   border-color: rgba(0,255,136,0.3);   background: rgba(0,255,136,0.05); }
.type--offensive { color: rgba(255,69,69,0.9);   border-color: rgba(255,69,69,0.3);   background: rgba(255,69,69,0.05); }
.type--locked    { color: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.08); }

/* Name */
.cmd-name     { font-size: 11px; color: rgba(0,255,255,0.85); letter-spacing: 0.05em; flex: 1; }
.cmd-name--lib { color: rgba(0,255,255,0.6); }
.name--cd      { color: rgba(255,51,51,0.55); }
.name--locked  { color: rgba(255,255,255,0.2); }

/* Tags */
.cmd-cd-tag     { font-size: 7px; color: rgba(255,51,51,0.5); letter-spacing: 0.1em; flex-shrink: 0; }
.cmd-locked-tag { font-size: 7px; color: rgba(255,255,255,0.2); letter-spacing: 0.1em; flex-shrink: 0; }

/* Equip / unequip buttons */
.cmd-equip-btn,
.cmd-unequip-btn {
    background: transparent;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 2px 7px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.12s;
}
.cmd-equip-btn {
    border: 1px solid rgba(0,255,136,0.3);
    color: rgba(0,255,136,0.65);
}
.cmd-equip-btn:hover:not(:disabled) {
    border-color: rgba(0,255,136,0.7);
    color: #00FF88;
    background: rgba(0,255,136,0.07);
}
.cmd-equip-btn:disabled {
    border-color: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.18);
    cursor: not-allowed;
}
.cmd-unequip-btn {
    border: 1px solid rgba(255,51,51,0.2);
    color: rgba(255,51,51,0.45);
}
.cmd-unequip-btn:hover {
    border-color: rgba(255,51,51,0.55);
    color: #FF3333;
    background: rgba(255,51,51,0.06);
}

/* Empty slot */
.empty-slot-label {
    font-size: 9px;
    color: rgba(0,255,255,0.18);
    letter-spacing: 0.1em;
    padding: 8px 12px;
    display: block;
}

/* Loadout full notice */
.loadout-full-notice {
    font-size: 8px;
    color: rgba(255,179,0,0.5);
    letter-spacing: 0.08em;
    padding: 6px 0 2px;
}

/* ── Expanded detail ────────────────────────────────────────────────────────── */
.cmd-detail {
    padding: 8px 14px 10px 36px;
    border-top: 1px solid rgba(0,255,255,0.06);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.cmd-cd-notice {
    font-size: 8px;
    color: rgba(255,51,51,0.6);
    letter-spacing: 0.08em;
    margin-bottom: 2px;
}
.cmd-effect-row {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.effect-key {
    font-size: 7px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.12em;
    width: 32px;
    flex-shrink: 0;
    padding-top: 2px;
}
.effect-val {
    font-size: 9px;
    color: rgba(0,255,255,0.55);
    letter-spacing: 0.03em;
    line-height: 1.65;
}

/* ── Tier group (library) ───────────────────────────────────────────────────── */
.tier-group  { margin-bottom: 12px; }
.tier-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 0 6px;
    border-bottom: 1px solid rgba(0,255,255,0.06);
    margin-bottom: 4px;
}
.tier-header--locked { opacity: 0.45; }
.tier-label    { font-size: 8px; color: rgba(0,255,255,0.35); letter-spacing: 0.14em; }
.tier-unlocked { font-size: 7px; color: rgba(0,255,136,0.4);  letter-spacing: 0.1em; }
.tier-lock     { font-size: 7px; color: rgba(255,179,0,0.5);  letter-spacing: 0.06em; }

/* ── Expand transition ──────────────────────────────────────────────────────── */
.expand-enter-active,
.expand-leave-active {
    transition: opacity 0.15s ease, max-height 0.2s ease;
    overflow: hidden;
    max-height: 120px;
}
.expand-enter-from,
.expand-leave-to {
    opacity: 0;
    max-height: 0;
}
</style>
