<template>
    <PanelBlock icon="◈" title="NODE INFO" :start-open="true">

        <!-- Idle state -->
        <div v-if="!node" class="ni-idle">
            <span class="ni-idle-icon">◈</span>
            <span class="ni-idle-text">NO NODE SELECTED</span>
            <span class="ni-idle-sub">Click any node to inspect</span>
        </div>

        <!-- Node content -->
        <div v-else class="ni-content">

            <!-- Network name + SPLICE address -->
            <div class="ni-header">
                <span class="ni-ping">◈</span>
                <div class="ni-header-text">
                    <div class="ni-name">{{ identity.networkName }}</div>
                    <div class="ni-address">
                        <span class="ni-addr-label">SPLICE</span>
                        <span class="ni-addr-sep">//</span>
                        <span class="ni-addr-val">{{ identity.spliceAddress }}</span>
                    </div>
                </div>
            </div>

            <div class="ni-divider" />

            <!-- Data rows -->
            <div class="ni-rows">
                <div class="ni-row">
                    <span class="ni-key">TYPE</span>
                    <span class="ni-val" :class="typeColorClass">{{ typeLabel }}</span>
                </div>
                <div class="ni-row">
                    <span class="ni-key">SECTOR</span>
                    <span class="ni-val ni-val--dim">{{ sectorLabel }}</span>
                </div>
                <div class="ni-row">
                    <span class="ni-key">STATUS</span>
                    <span class="ni-val" :class="statusColorClass">{{ statusLabel }}</span>
                </div>
                <div class="ni-row">
                    <span class="ni-key">ICE</span>
                    <span class="ni-val" :class="iceColorClass">{{ node.ice ?? '?' }}</span>
                </div>
            </div>

            <div class="ni-divider" />

            <!-- CyberDoc -->
            <div v-if="node.type === 'cyberdoc'" class="ni-store">
                <!-- NPC identity — shown when the hub has a quest-giver handle -->
                <div v-if="node.npcHandle" class="ni-npc">
                    <span class="ni-npc-icon">◈</span>
                    <div class="ni-npc-body">
                        <span class="ni-npc-handle">{{ node.npcHandle }}</span>
                        <span class="ni-npc-role">STREET DOC // QUEST GIVER</span>
                    </div>
                </div>
                <div class="ni-store-desc">Licensed hardware &amp; software vendor.</div>
                <div class="ni-store-actions">
                    <button class="ni-store-btn" @click="$emit('open-store')">
                        <span>⬡</span> OPEN STOREFRONT
                    </button>
                    <button class="ni-bank-btn" @click="$emit('open-bank')">
                        <span>◈</span> BANK
                    </button>
                </div>
                <button
                    v-if="dialogueSpliceUrl && isOnNode"
                    class="ni-dialogue-btn"
                    @click="$emit('open-dialogue')"
                >
                    [ OPEN DIALOGUE ]
                </button>
                <button class="ni-cooldown-btn" :disabled="!isOnNode" @click="$emit('reset-cooldowns')">
                    [ RESET COOLDOWNS ]
                </button>
            </div>

            <!-- Resources -->
            <div v-else class="ni-resources">
                <div class="ni-res-label">NODE RESOURCES</div>

                <div class="ni-res-row">
                    <span class="ni-res-key">CREDS</span>
                    <span v-if="resources.creds.replenishesIn > 0" class="ni-res-val ni-res--depleted">
                        DEPLETED — {{ formatTimer(resources.creds.replenishesIn) }}
                    </span>
                    <span v-else class="ni-res-val ni-res--creds">◈ {{ resources.creds.value }}</span>
                    <button class="ni-hack-btn" :disabled="!resources.creds.available" @click="$emit('hack', 'creds')">[HACK]</button>
                </div>
                <div class="ni-res-row">
                    <span class="ni-res-key">TECH</span>
                    <span v-if="resources.tech.replenishesIn > 0" class="ni-res-val ni-res--depleted">
                        DEPLETED — {{ formatTimer(resources.tech.replenishesIn) }}
                    </span>
                    <span v-else class="ni-res-val ni-res--tech">◈ {{ resources.tech.value }} PTS</span>
                    <button class="ni-hack-btn" :disabled="!resources.tech.available" @click="$emit('hack', 'tech')">[HACK]</button>
                </div>
                <div class="ni-res-row">
                    <span class="ni-res-key">UPLINK</span>
                    <span v-if="resources.uplink.replenishesIn > 0" class="ni-res-val ni-res--depleted">
                        DEPLETED — {{ formatTimer(resources.uplink.replenishesIn) }}
                    </span>
                    <span v-else class="ni-res-val ni-res--uplink">◈ {{ resources.uplink.value }} LNK</span>
                    <button class="ni-hack-btn ni-hack-btn--uplink" :disabled="!resources.uplink.available" @click="$emit('hack', 'uplink')">[HACK]</button>
                </div>
            </div>

            <!-- Bank Heist — fixed 19-node trigger set, independent of normal hacking above -->
            <div v-if="node.isBankTarget" class="ni-bankheist">
                <div class="ni-divider" />
                <div class="ni-res-label ni-bankheist-label">BANK HEIST TARGET — TIER {{ node.bankTier }}</div>
                <button
                    v-if="isOnNode"
                    class="ni-bankheist-btn"
                    :disabled="bankCooldownRemaining > 0"
                    @click="$emit('bank-heist')"
                >
                    {{ bankCooldownRemaining > 0 ? `COOLDOWN — ${formatTimer(bankCooldownRemaining)}` : '[ INITIATE BANK HEIST ]' }}
                </button>
                <div v-else class="ni-bankheist-hint">Stand on this node to attempt entry.</div>
            </div>

            <!-- Data fragments — recent hackers' traces, ticking down to zero -->
            <div v-if="traces.length > 0" class="ni-traces">
                <div class="ni-divider" />
                <div class="ni-res-label ni-traces-label">DATA FRAGMENTS</div>
                <div
                    v-for="t in traces"
                    :key="t.id"
                    class="ni-trace-row"
                    :class="{ 'ni-trace-row--stale': t.seconds_remaining < 60 }"
                >
                    <span class="ni-trace-icon">▒</span>
                    <span class="ni-trace-handle">{{ t.handle }}</span>
                    <span class="ni-trace-timer">{{ formatTimer(t.seconds_remaining) }}</span>
                </div>
            </div>

            <!-- Players on this node — only shown when player is standing here -->
            <div v-if="isOnNode && nodePlayers.length > 0" class="ni-players">
                <div class="ni-divider" />
                <div class="ni-res-label ni-players-label">PLAYERS ON NODE</div>
                <div
                    v-for="p in nodePlayers"
                    :key="p.id"
                    class="ni-player-row"
                    :class="{ 'ni-player-row--os': p.is_open_season }"
                >
                    <div class="ni-player-info">
                        <span class="ni-player-handle" :class="p.is_open_season ? 'ni-player--os' : ''">
                            {{ p.handle }}
                        </span>
                        <span class="ni-player-meta">
                            <span class="ni-player-stars">{{ '★'.repeat(p.bounty_level ?? 0) }}{{ '☆'.repeat(5 - (p.bounty_level ?? 0)) }}</span>
                            <span class="ni-player-pocket" v-if="p.pocket_creds > 0">◈{{ p.pocket_creds.toLocaleString() }}</span>
                        </span>
                    </div>
                    <template v-if="node?.is_safe_zone">
                        <span class="ni-safe-zone">SAFE ZONE</span>
                    </template>
                    <template v-else-if="!p.in_combat">
                        <button
                            class="ni-hack-btn ni-hack-btn--pvp"
                            @click="$emit('hack-player', p)"
                        >[HACK]</button>
                    </template>
                    <span v-else class="ni-in-combat">IN COMBAT</span>
                </div>
            </div>

            <div class="ni-divider" />

            <!-- Footer -->
            <div class="ni-footer">
                <span v-if="isOnNode" class="ni-connected">◉ CONNECTED</span>
                <span v-else class="ni-remote">○ REMOTE NODE</span>
            </div>

        </div>
    </PanelBlock>
</template>

<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import PanelBlock from './PanelBlock.vue';
import { getNodeIdentity } from '@/composables/useNodeIdentity.js';

const props = defineProps({
    node:              { type: Object,  default: null },
    isOnNode:          { type: Boolean, default: false },
    nodePlayers:       { type: Array,   default: () => [] },
    // Active hack traces left on this node — see useNodeTraces composable
    traces:            { type: Array,   default: () => [] },
    dialogueSpliceUrl: { type: String,  default: null },
    resources: {
        type:    Object,
        default: () => ({
            creds:  { available: true, value: 750 },
            tech:   { available: true, value: 4   },
            uplink: { available: true, value: 2   },
        }),
    },
});

defineEmits(['hack', 'open-store', 'open-bank', 'open-dialogue', 'reset-cooldowns', 'hack-player', 'bank-heist']);

// Render seconds_remaining as M:SS
function formatTimer(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    const m = Math.floor(s / 60);
    const r = s % 60;
    return `${m}:${r.toString().padStart(2, '0')}`;
}

// Bank Heist cooldown countdown — node.bankCooldownUntil is a raw timestamp
// (see useMapData.js), so this ticks its own clock rather than relying on
// the resources prop's pre-computed replenishesIn (that system only covers
// creds/tech/uplink).
const nowTick = ref(Date.now());
let cooldownTickInterval = null;
onMounted(() => { cooldownTickInterval = setInterval(() => { nowTick.value = Date.now(); }, 1000); });
onBeforeUnmount(() => { if (cooldownTickInterval) clearInterval(cooldownTickInterval); });

const bankCooldownRemaining = computed(() => {
    if (!props.node?.bankCooldownUntil) return 0;
    const until = new Date(props.node.bankCooldownUntil).getTime();
    return Math.max(0, Math.round((until - nowTick.value) / 1000));
});

const identity      = computed(() => props.node ? getNodeIdentity(props.node) : null);
const typeLabel     = computed(() => props.node?.type === 'cyberdoc' ? 'CYBER DOC' : 'ACTION NODE');
const typeColorClass= computed(() => props.node?.type === 'cyberdoc' ? 'ni-val--cyberdoc' : 'ni-val--action');
const sectorLabel   = computed(() => props.node?.district?.toUpperCase() ?? 'TRANSIT RELAY');
const statusLabel   = computed(() => props.isOnNode ? 'ACTIVE SESSION' : 'STANDBY');
const statusColorClass = computed(() => props.isOnNode ? 'ni-val--connected' : 'ni-val--dim');
const iceColorClass = computed(() => {
    const ice = props.node?.ice ?? 0;
    if (ice >= 7) return 'ni-val--ice-high';
    if (ice >= 4) return 'ni-val--ice-mid';
    return 'ni-val--ice-low';
});
</script>

<style scoped>
.ni-idle {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 28px 16px;
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
}
.ni-idle-icon { font-size: 18px; color: rgba(0,255,255,0.35); animation: idle-pulse 4s ease-in-out infinite; }
.ni-idle-text { font-size: 9px; color: rgba(0,255,255,0.85); letter-spacing: 0.16em; text-shadow: 0 0 8px rgba(0,255,255,0.4); }
.ni-idle-sub  { font-size: 9px; color: rgba(0,255,255,0.65); letter-spacing: 0.06em; }

@keyframes idle-pulse { 0%,100%{opacity:.4} 50%{opacity:1} }

.ni-content { font-family: 'JetBrains Mono', monospace; }

.ni-header { display:flex; align-items:flex-start; gap:10px; padding:12px 14px 10px; }
.ni-ping   { color:#00FF88; font-size:9px; flex-shrink:0; margin-top:2px; animation:ping 2s ease-in-out infinite; }
@keyframes ping { 0%,100%{opacity:1} 50%{opacity:.3} }

.ni-header-text { display:flex; flex-direction:column; gap:3px; min-width:0; }
.ni-name        { font-size:10px; color:#00FFFF; letter-spacing:0.07em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-shadow:0 0 10px rgba(0,255,255,.8), 0 0 20px rgba(0,255,255,.3); }
.ni-address     { display:flex; align-items:center; gap:4px; }
.ni-addr-label  { font-size:8px; color:rgba(0,255,255,.8); letter-spacing:.1em; }
.ni-addr-sep    { font-size:8px; color:rgba(0,255,255,.55); }
.ni-addr-val    { font-size:9px; color:#00FFC8; letter-spacing:.1em; text-shadow:0 0 8px rgba(0,255,200,.6); }

.ni-divider { height:1px; background:rgba(0,255,255,.06); }

.ni-rows { padding:6px 0; }
.ni-row  { display:flex; align-items:center; padding:3px 14px; }
.ni-key  { font-size:9px; color:rgba(0,255,255,.78); letter-spacing:.08em; width:58px; flex-shrink:0; }
.ni-val  { font-size:9px; letter-spacing:.06em; }

.ni-val--action    { color:#FF69B4; text-shadow:0 0 8px rgba(255,105,180,.6); }
.ni-val--cyberdoc  { color:#FFB300; text-shadow:0 0 8px rgba(255,179,0,.6); }
.ni-val--connected { color:#00FF88; text-shadow:0 0 8px rgba(0,255,136,.6); }
.ni-val--dim       { color:rgba(0,255,255,.8); }
.ni-val--ice-low   { color:#00FF88; text-shadow:0 0 8px rgba(0,255,136,.6); }
.ni-val--ice-mid   { color:#FFB300; text-shadow:0 0 8px rgba(255,179,0,.6); }
.ni-val--ice-high  { color:#FF3333; text-shadow:0 0 8px rgba(255,51,51,.7); }

.ni-resources { padding:8px 14px 10px; display:flex; flex-direction:column; gap:2px; }
.ni-res-label { font-size:8px; color:rgba(0,255,255,.82); letter-spacing:.14em; margin-bottom:4px; text-shadow:0 0 6px rgba(0,255,255,.4); }
.ni-res-row   { display:flex; align-items:center; padding:3px 0; }
.ni-res-key   { font-size:9px; color:rgba(0,255,255,.78); letter-spacing:.08em; width:58px; flex-shrink:0; }
.ni-res-val   { font-size:10px; flex:1; }
.ni-res--creds  { color:#00FF88; text-shadow:0 0 8px rgba(0,255,136,.7); }
.ni-res--tech   { color:#7DF9FF; text-shadow:0 0 8px rgba(125,249,255,.7); }
.ni-res--uplink { color:#00FFFF; text-shadow:0 0 8px rgba(0,255,255,.7); }


.ni-hack-btn {
    background:transparent; border:1px solid rgba(0,255,136,.45); color:rgba(0,255,136,.85);
    font-family:'JetBrains Mono',monospace; font-size:8px; letter-spacing:.1em; padding:3px 7px; cursor:pointer;
    flex-shrink:0; transition:all .12s;
}
.ni-hack-btn:hover:not(:disabled) { color:#00FF88; border-color:rgba(0,255,136,.85); background:rgba(0,255,136,.07); }
.ni-hack-btn--uplink { border-color:rgba(125,249,255,.45); color:rgba(125,249,255,.85); }
.ni-hack-btn--uplink:hover:not(:disabled) { color:#7DF9FF; border-color:rgba(125,249,255,.85); background:rgba(125,249,255,.07); }
.ni-hack-btn:disabled { border-color:rgba(255,51,51,.25); color:rgba(255,51,51,.45); cursor:not-allowed; }

/* ── Bank Heist trigger ────────────────────────────────────────────────────── */
.ni-bankheist       { padding:8px 14px 10px; display:flex; flex-direction:column; gap:6px; }
.ni-bankheist-label { color:#FFB300; text-shadow:0 0 6px rgba(255,179,0,.5); }
.ni-bankheist-btn {
    width:100%; padding:9px 0; background:rgba(255,179,0,.05); border:1px solid rgba(255,179,0,.5);
    color:#FFB300; font-family:'JetBrains Mono',monospace; font-size:9px; letter-spacing:.1em;
    cursor:pointer; transition:all .15s; text-shadow:0 0 8px rgba(255,179,0,.4);
}
.ni-bankheist-btn:hover:not(:disabled) { background:rgba(255,179,0,.12); border-color:#FFB300; box-shadow:0 0 10px rgba(255,179,0,.2); }
.ni-bankheist-btn:disabled { border-color:rgba(255,51,51,.3); color:rgba(255,51,51,.55); cursor:not-allowed; text-shadow:none; }
.ni-bankheist-hint { font-size:8px; color:rgba(255,179,0,.55); letter-spacing:.06em; }

.ni-store { padding:12px 14px; display:flex; flex-direction:column; gap:10px; }
.ni-store-desc { font-size:9px; color:rgba(255,179,0,.72); letter-spacing:.06em; line-height:1.7; }
.ni-store-actions { display:flex; gap:6px; }
.ni-store-btn  { display:flex; align-items:center; justify-content:center; gap:8px; flex:1; padding:9px 0;
    background:rgba(255,179,0,.04); border:1px solid rgba(255,179,0,.35); color:#FFB300;
    font-family:'JetBrains Mono',monospace; font-size:8px; letter-spacing:.12em; cursor:pointer; transition:all .15s; }
.ni-store-btn:hover { background:rgba(255,179,0,.09); border-color:rgba(255,179,0,.7); }
.ni-bank-btn   { display:flex; align-items:center; justify-content:center; gap:6px; padding:9px 12px;
    background:rgba(0,255,136,.04); border:1px solid rgba(0,255,136,.3); color:rgba(0,255,136,.8);
    font-family:'JetBrains Mono',monospace; font-size:8px; letter-spacing:.12em; cursor:pointer; transition:all .15s; }
.ni-bank-btn:hover { background:rgba(0,255,136,.09); border-color:rgba(0,255,136,.65); }
.ni-dialogue-btn { width:100%; padding:8px 0; background:transparent; border:1px solid rgba(255,107,53,.35);
    color:rgba(255,107,53,.8); font-family:'JetBrains Mono',monospace; font-size:8px; letter-spacing:.14em;
    cursor:pointer; transition:all .15s; }
.ni-dialogue-btn:hover { background:rgba(255,107,53,.07); border-color:rgba(255,107,53,.7); color:#FF6B35;
    box-shadow:0 0 8px rgba(255,107,53,.15); }

.ni-cooldown-btn { width:100%; padding:6px 0; background:transparent; border:1px solid rgba(255,179,0,.18);
    color:rgba(255,179,0,.45); font-family:'JetBrains Mono',monospace; font-size:7px; letter-spacing:.14em;
    cursor:pointer; transition:all .15s; }
.ni-cooldown-btn:hover:not(:disabled) { border-color:rgba(255,179,0,.45); color:rgba(255,179,0,.75); background:rgba(255,179,0,.03); }
.ni-cooldown-btn:disabled { opacity:.28; cursor:not-allowed; }

/* ── NPC identity block ───────────────────────────────────────────────────── */
.ni-npc {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0 4px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.1);
    margin-bottom: 2px;
}
.ni-npc-icon {
    font-size: 14px;
    color: #FFB300;
    flex-shrink: 0;
    animation: npc-pulse 3s ease-in-out infinite;
    text-shadow: 0 0 10px rgba(255, 179, 0, 0.5);
}
@keyframes npc-pulse { 0%,100%{opacity:1} 50%{opacity:0.45} }

.ni-npc-body   { display: flex; flex-direction: column; gap: 2px; }
.ni-npc-handle {
    font-size: 13px;
    color: #FFB300;
    letter-spacing: 0.12em;
    text-shadow: 0 0 12px rgba(255, 179, 0, 0.45);
}
.ni-npc-role {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.68);
    letter-spacing: 0.12em;
}

.ni-footer    { padding:7px 14px 10px; }
.ni-connected { font-size:9px; color:#00FF88; letter-spacing:.1em; text-shadow:0 0 8px rgba(0,255,136,.7); }
.ni-remote    { font-size:9px; color:rgba(0,255,255,.82); letter-spacing:.1em; }
.ni-jackin-btn {
    background: transparent;
    border: 1px solid rgba(0,255,136,.55);
    color: #00FF88;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: .12em;
    padding: 5px 14px;
    cursor: pointer;
    width: 100%;
    transition: all .12s;
    text-shadow: 0 0 8px rgba(0,255,136,.5);
}
.ni-jackin-btn:hover {
    background: rgba(0,255,136,.08);
    border-color: rgba(0,255,136,.8);
    box-shadow: 0 0 10px rgba(0,255,136,.15);
}

/* ── Players on node ──────────────────────────────────────────────────────── */
.ni-players       { padding: 6px 14px 8px; }
.ni-players-label { margin-bottom: 6px; }

.ni-player-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255, 105, 180, 0.08);
    gap: 8px;
}
.ni-player-row--os {
    border-bottom-color: rgba(255, 51, 51, 0.15);
}
.ni-player-row:last-child { border-bottom: none; }

.ni-player-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.ni-player-handle {
    font-size: 9px;
    color: #FF69B4;
    letter-spacing: 0.07em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ni-player--os { color: #FF3333; }

.ni-player-meta {
    display: flex;
    align-items: center;
    gap: 6px;
}
.ni-player-stars  { font-size: 8px; color: rgba(255, 179, 0, 0.9); letter-spacing: 1px; }
.ni-player-pocket { font-size: 8px; color: #FFB300; letter-spacing: 0.06em; text-shadow: 0 0 6px rgba(255,179,0,.6); }

.ni-hack-btn--pvp {
    border-color: rgba(255, 105, 180, 0.35);
    color: rgba(255, 105, 180, 0.7);
    flex-shrink: 0;
}
.ni-hack-btn--pvp:hover {
    color: #FF69B4;
    border-color: rgba(255, 105, 180, 0.8);
    background: rgba(255, 105, 180, 0.07);
}
.ni-in-combat {
    font-size: 8px;
    letter-spacing: 0.12em;
    color: rgba(255, 179, 0, 0.8);
    border: 1px solid rgba(255, 179, 0, 0.4);
    padding: 2px 6px;
    flex-shrink: 0;
}
.ni-safe-zone {
    font-size: 8px;
    letter-spacing: 0.12em;
    color: rgba(0, 255, 136, 0.8);
    border: 1px solid rgba(0, 255, 136, 0.4);
    padding: 2px 6px;
    flex-shrink: 0;
}

/* ── Data fragments / hack traces ────────────────────────────────────────── */
.ni-traces        { padding: 6px 14px 8px; }
.ni-traces-label  { margin-bottom: 6px; color: rgba(125, 249, 255, 0.85); text-shadow: 0 0 6px rgba(125,249,255,.5); }

.ni-trace-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 3px 0;
    font-size: 9px;
    letter-spacing: 0.06em;
}

.ni-trace-icon {
    color: rgba(125, 249, 255, 0.85);
    font-size: 9px;
    animation: trace-flicker 2.4s steps(1) infinite;
}
@keyframes trace-flicker {
    0%, 92%, 100% { opacity: 1;   }
    94%           { opacity: 0.3; }
    96%           { opacity: 1;   }
    98%           { opacity: 0.5; }
}

.ni-trace-handle {
    flex: 1;
    color: #7DF9FF;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0 8px rgba(125,249,255,.65);
}

.ni-trace-timer {
    color: rgba(125, 249, 255, 0.85);
    font-variant-numeric: tabular-nums;
}

/* Older fragments shift toward red — they're about to fade */
.ni-trace-row--stale .ni-trace-handle { color: rgba(255, 105, 180, 0.9); }
.ni-trace-row--stale .ni-trace-timer  { color: rgba(255, 51,  51, 0.9); }
.ni-trace-row--stale .ni-trace-icon   { color: rgba(255, 51,  51, 0.75); }
</style>
