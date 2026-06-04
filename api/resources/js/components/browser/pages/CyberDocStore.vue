<template>
    <div class="store-page">

        <!-- ── Location gate ─────────────────────────────────────────────────── -->
        <div v-if="visitChecking" class="access-gate access-gate--checking">
            <span class="gate-icon">◈</span>
            <span class="gate-msg">AUTHENTICATING TERMINAL…</span>
        </div>
        <div v-else-if="!atCyberDoc" class="access-gate access-gate--denied">
            <span class="gate-icon gate-icon--denied">⬡</span>
            <span class="gate-msg">ACCESS DENIED</span>
            <span class="gate-sub">You must be physically at a CyberDoc terminal to connect.</span>
        </div>

        <template v-else>

        <!-- ── Storefront header ──────────────────────────────────────────────── -->
        <div class="storefront">
            <div class="storefront-sign">
                <span class="sf-mark">⬡</span>
                <div class="sf-name-block">
                    <span class="sf-name">{{ npc.storeName.toUpperCase() }}</span>
                    <span class="sf-handle">{{ npc.handle ?? 'CYBERDOC' }}</span>
                </div>
                <div class="sf-district-tag">{{ npc.district.toUpperCase() }}</div>
                <span class="sf-tagline">// {{ npc.tagline }}</span>
                <div class="sf-status">
                    <span class="sf-status-dot" />
                    <span class="sf-status-text">TERMINAL ACTIVE</span>
                </div>
            </div>
            <div class="sf-ledger">
                <div class="ledger-row">
                    <span class="ledger-label">WALLET</span>
                    <span class="ledger-value">{{ playerCreds.toLocaleString() }} ₡</span>
                </div>
                <div class="ledger-divider" />
                <div class="ledger-row">
                    <span class="ledger-label">TECH PTS</span>
                    <span class="ledger-value ledger-tp">{{ playerTechPoints }} TP</span>
                </div>
            </div>
        </div>

        <!-- ── Banking terminal strip ─────────────────────────────────────────── -->
        <div class="bank-terminal" :class="{ 'bank-terminal--hot': playerPocketCreds > 0 }">
            <span class="bt-label">POCKET</span>
            <span class="bt-amount" :class="playerPocketCreds > 0 ? 'bt-amount--hot' : 'bt-amount--empty'">
                {{ playerPocketCreds.toLocaleString() }} ₡
            </span>
            <span v-if="playerPocketCreds > 0" class="bt-risk">⚠ AT RISK</span>
            <div class="bt-spacer" />
            <button
                class="bt-btn"
                :disabled="playerPocketCreds === 0 || banking"
                @click="onBankCreds"
            >
                <span v-if="banking">PROCESSING…</span>
                <span v-else-if="playerPocketCreds === 0">— NOTHING TO EXTRACT —</span>
                <span v-else>EXTRACT {{ playerPocketCreds.toLocaleString() }} ₡ →</span>
            </button>
            <Transition name="bank-confirm">
                <span v-if="bankConfirm" class="bt-confirm">✓ {{ bankConfirm.toLocaleString() }} ₡ SECURED</span>
            </Transition>
        </div>

        <!-- ── Cooldown reset strip ────────────────────────────────────────────── -->
        <div class="cooldown-strip">
            <div>
                <div class="cd-label">COMMAND COOLDOWN RESET</div>
                <div class="cd-sub">Resets all command cooldowns. No charge. No bounty impact.</div>
            </div>
            <button class="cd-btn" :disabled="!atCyberDoc" @click="onResetCooldowns">[ RESET ALL ]</button>
        </div>

        <!-- ── Section nav ────────────────────────────────────────────────────── -->
        <div class="store-nav">
            <button
                v-for="cat in categories"
                :key="cat.id"
                class="snav-btn"
                :class="{ active: activeCategory === cat.id }"
                @click="activeCategory = cat.id"
            >{{ cat.label }}</button>
        </div>

        <!-- ── Off-site lockout banner ──────────────────────────────────────── -->
        <div v-if="!atCyberDoc" class="offsite-banner">
            <span>⛔</span>
            <span>NOT ON LOCATION — navigate to a CyberDoc node to make purchases.</span>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════
             RIGS
             ════════════════════════════════════════════════════════════════════ -->
        <div v-if="activeCategory === 'rigs'" class="section-scroll">

            <!-- Current chassis -->
            <div class="chassis-current">
                <div class="cc-topbar">
                    <span class="cc-label">INSTALLED CHASSIS</span>
                    <span class="cc-equipped">● EQUIPPED</span>
                </div>
                <div class="cc-identity">
                    <span class="cc-tier-badge">T{{ rig.tier }}</span>
                    <span class="cc-name">{{ chassisBaseName }}</span>
                    <span class="cc-ver">v{{ rigVersionLabel }}</span>
                </div>
                <div class="cc-stats">
                    <div v-for="s in RIG_STATS" :key="s.key" class="cc-stat">
                        <span class="cc-stat-label">{{ s.short }}</span>
                        <div class="cc-pips">
                            <span
                                v-for="n in (rig.caps[s.key] ?? effectiveStat(s.key, rig))"
                                :key="n"
                                class="cc-pip"
                                :class="{
                                    'pip--base':     n <= rig[s.key] - (rig.investedPoints?.[s.key] ?? 0),
                                    'pip--invested': n > rig[s.key] - (rig.investedPoints?.[s.key] ?? 0) && n <= rig[s.key],
                                    'pip--empty':    n > rig[s.key],
                                }"
                            />
                        </div>
                        <span class="cc-stat-val">{{ effectiveStat(s.key, rig) }}</span>
                    </div>
                </div>
                <div class="cc-footer">
                    <div class="cc-prog">
                        <span class="cc-prog-label">UPGRADE</span>
                        <div class="cc-prog-pips">
                            <span
                                v-for="n in (rig.pointsCap ?? 9)"
                                :key="n"
                                class="cc-prog-pip"
                                :class="{ 'prog-pip--lit': n <= totalInvestedAll }"
                            />
                        </div>
                        <span class="cc-prog-count">{{ totalInvestedAll }}/{{ rig.pointsCap ?? 9 }}</span>
                        <span v-if="chassisMaxed" class="cc-maxed-tag">MAXED</span>
                    </div>
                    <span class="cc-ports">{{ rig.portSlots ?? 0 }} PORT SLOTS</span>
                </div>
            </div>

            <!-- Available chassis section -->
            <div class="section-subheading">
                <span>AVAILABLE CHASSIS</span>
                <span class="section-sub">NullTek Series 2 — unlock by maxing your current rig</span>
            </div>

            <div v-if="!chassisMaxed && rig.tier === 1" class="lock-notice">
                <span>🔒</span>
                <span>NullTek Series 2 unlocks at BlackHat v1.9 — <strong>{{ totalInvestedAll }}/{{ rig.pointsCap ?? 9 }} pts invested</strong></span>
            </div>

            <div class="chassis-grid">
                <div
                    v-for="chassis in NULLTEK_CHASSIS"
                    :key="chassis.id"
                    class="chassis-card"
                    :class="{
                        'chassis-card--locked':  !chassisMaxed || rig.tier > 1,
                        'chassis-card--unavail': rig.tier > 1,
                    }"
                >
                    <div class="ccard-header">
                        <span class="ccard-tier">T{{ chassis.tier }}</span>
                        <span class="ccard-brand">{{ chassis.brand.toUpperCase() }}</span>
                        <span class="ccard-build" :class="`build--${chassis.build}`">{{ chassis.build.toUpperCase() }}</span>
                    </div>
                    <div class="ccard-title">
                        <span class="ccard-model">{{ chassis.model }}</span>
                        <span class="ccard-name">{{ chassis.name }}</span>
                    </div>
                    <div class="ccard-tagline">{{ chassis.tagline }}</div>
                    <div class="ccard-stats">
                        <div v-for="s in RIG_STATS" :key="s.key" class="ccard-stat">
                            <span class="ccard-stat-key">{{ s.short }}</span>
                            <div class="ccard-stat-pips">
                                <span
                                    v-for="n in chassis.caps[s.key]"
                                    :key="n"
                                    class="ccard-pip"
                                    :class="{ 'ccard-pip--base': n <= chassis.base[s.key] }"
                                />
                            </div>
                            <span class="ccard-stat-val">{{ chassis.base[s.key] }}/{{ chassis.caps[s.key] }}</span>
                        </div>
                        <div class="ccard-stat">
                            <span class="ccard-stat-key">UPL</span>
                            <div class="ccard-stat-pips">
                                <span v-for="n in chassis.base.uplink" :key="n" class="ccard-pip ccard-pip--uplink" />
                            </div>
                            <span class="ccard-stat-val">{{ chassis.base.uplink }} 🔒</span>
                        </div>
                    </div>
                    <div class="ccard-footer">
                        <div class="ccard-price">
                            <span class="cp-creds">{{ chassis.price.creds.toLocaleString() }} ₡</span>
                            <span class="cp-sep">+</span>
                            <span class="cp-tp">{{ chassis.price.tp }} TP</span>
                        </div>
                        <button
                            class="ccard-buy"
                            :disabled="!atCyberDoc || !chassisMaxed || rig.tier > 1 || !canAffordChassis(chassis)"
                            :title="chassisBtnTitle(chassis)"
                            @click="onPurchaseChassis(chassis)"
                        >{{ rig.tier > 1 ? 'OWNED' : 'PURCHASE' }}</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════════════
             STATS
             ════════════════════════════════════════════════════════════════════ -->
        <div v-else-if="activeCategory === 'stats'" class="section-scroll">

            <div class="stat-intro">
                <span class="stat-intro-label">RIG STAT INVESTMENT</span>
                <span class="stat-intro-sub">Costs escalate with each upgrade — chassis cap limits maximum investment per stat.</span>
            </div>

            <div class="stat-global">
                <span class="sg-label">GLOBAL</span>
                <div class="sg-pips">
                    <span
                        v-for="n in (rig.pointsCap ?? 9)"
                        :key="n"
                        class="sg-pip"
                        :class="{ 'sg-pip--lit': n <= totalInvestedAll }"
                    />
                </div>
                <span class="sg-count">{{ totalInvestedAll }} pts — +{{ Math.round((Math.pow(1.25, totalInvestedAll) - 1) * 100) }}% cost modifier</span>
            </div>

            <div class="stat-list">
                <div
                    v-for="s in upgradeableStats"
                    :key="s.key"
                    class="stat-row"
                    :class="{ 'stat-row--maxed': !s.canUp }"
                >
                    <div class="sr-identity">
                        <span class="sr-name">{{ s.label }}</span>
                        <span class="sr-desc">{{ s.desc }}</span>
                    </div>
                    <div class="sr-pips">
                        <span
                            v-for="n in s.cap"
                            :key="n"
                            class="sr-pip"
                            :class="{
                                'pip--base':     n <= s.base,
                                'pip--invested': n > s.base && n <= s.effective,
                                'pip--empty':    n > s.effective,
                            }"
                        />
                        <span class="sr-pip-label">{{ s.effective }}/{{ s.cap }}</span>
                    </div>
                    <div class="sr-cost">
                        <template v-if="s.canUp">
                            <span class="sr-cost-creds">{{ s.cost.creds.toLocaleString() }} ₡</span>
                            <span v-if="s.cost.tp > 0" class="sr-cost-tp">+ {{ s.cost.tp }} TP</span>
                            <span v-if="s.investedIn > 0" class="sr-scale">×{{ scalingLabel(s.investedIn) }}</span>
                        </template>
                        <span v-else-if="s.osGated" class="sr-gated">{{ s.gateMsg }}</span>
                        <span v-else class="sr-capped">CAPPED</span>
                    </div>
                    <button
                        v-if="s.canUp"
                        class="sr-btn"
                        :disabled="!atCyberDoc || !canAffordStat(s.cost) || chassisMaxed"
                        :title="!atCyberDoc ? 'Navigate to a CyberDoc node' : chassisMaxed ? 'Chassis maxed — buy NullTek to continue' : statBtnTitle(s)"
                        @click="onUpgradeStat(s.key, s.cost)"
                    >INVEST</button>
                </div>
            </div>

            <div v-if="upgradeError" class="stat-error">{{ upgradeError }}</div>

            <div v-if="chassisMaxed" class="stat-maxed-notice">
                <span class="smn-check">✓</span>
                <div>
                    <div class="smn-title">BLACKHAT FULLY UPGRADED — v1.9</div>
                    <div class="smn-sub">Visit the RIGS tab to purchase a NullTek Series 2 chassis.</div>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════════════
             COMMANDS
             ════════════════════════════════════════════════════════════════════ -->
        <div v-else-if="activeCategory === 'commands'" class="section-scroll">

            <div v-if="purchasableCommands.length === 0" class="empty-state">
                ALL AVAILABLE COMMANDS OWNED
            </div>

            <template v-for="ctx in ['map', 'hack']" :key="ctx">
                <div v-if="commandsForContext(ctx).length" class="cmd-group">
                    <div class="cmd-group-header">
                        <span>{{ ctx === 'map' ? 'MAP COMMANDS' : 'HACK COMMANDS' }}</span>
                        <span class="cmd-group-sub">{{ ctx === 'map' ? 'Used during map traversal' : 'Used inside Packet Hijack' }}</span>
                    </div>
                    <div
                        v-for="cmd in commandsForContext(ctx)"
                        :key="cmd.id"
                        class="cmd-row"
                    >
                        <span class="cmd-type" :class="`cmd-type--${cmd.type}`">{{ cmd.type.toUpperCase() }}</span>
                        <span class="cmd-name">{{ cmd.name.toUpperCase() }}</span>
                        <span class="cmd-effect">{{ cmd.context === 'hack' ? cmd.packethijackEffect : cmd.mapEffect }}</span>
                        <div class="cmd-price">
                            <span class="cp-creds">{{ cmd.price.creds.toLocaleString() }} ₡</span>
                            <span class="cp-sep">+</span>
                            <span class="cp-tp">{{ cmd.price.techPoints }} TP</span>
                        </div>
                        <button
                            class="cmd-buy"
                            :disabled="!atCyberDoc || !canAfford(cmd)"
                            :title="buyBtnTitle(cmd)"
                            @click="onBuyCommand(cmd)"
                        >BUY</button>
                    </div>
                </div>
            </template>

        </div>

        <!-- ════════════════════════════════════════════════════════════════════
             ITEM GRID (hardware / software / repair / all)
             ════════════════════════════════════════════════════════════════════ -->
        <div v-else class="section-scroll">
            <div class="item-grid">
                <div
                    v-for="item in filteredItems"
                    :key="item.id"
                    class="item-card"
                    :class="`item-card--${item.rarity}`"
                >
                    <div class="item-top">
                        <span class="item-rarity">{{ item.rarity.toUpperCase() }}</span>
                        <span v-if="item.peripheral_type === 'command_module'" class="item-stat-badge stat-badge--slot">
                            {{ item.slot_type?.toUpperCase() }} T{{ item.slot_tier }}
                        </span>
                        <span v-else-if="item.stat" class="item-stat-badge">
                            {{ item.stat.toUpperCase() }} +{{ item.boost }}
                        </span>
                    </div>
                    <div class="item-name">{{ item.name }}</div>
                    <div class="item-desc">{{ item.desc }}</div>
                    <div class="item-footer">
                        <span class="item-price">{{ item.price.toLocaleString() }} ₡</span>
                        <template v-if="item.category === 'hardware'">
                            <span v-if="hardwareOwned(item.id)" class="item-owned">OWNED</span>
                            <button v-else class="item-buy" :disabled="!atCyberDoc || playerCreds < item.price" @click="onBuy(item)">BUY</button>
                        </template>
                        <template v-else>
                            <span v-if="consumableQty(item.id) > 0" class="item-qty">×{{ consumableQty(item.id) }}</span>
                            <button class="item-buy" :disabled="!atCyberDoc || playerCreds < item.price" @click="onBuy(item)">
                                {{ consumableQty(item.id) > 0 ? '+1' : 'BUY' }}
                            </button>
                            <button v-if="consumableQty(item.id) > 0" class="item-use" @click="onUseConsumable(item)">USE</button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        </template><!-- end v-else (atCyberDoc) -->

    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import { useUpgradeCosts } from '@/composables/useUpgradeCosts.js';

defineProps({
    url: { type: String, default: '' },
    npc: {
        type: Object,
        default: () => ({
            handle:    'CYBERDOC',
            storeName: 'CyberDoc',
            district:  'Network',
            tagline:   'Authorized Hardware & Software Vendor',
        }),
    },
});

// ── Real player state from Game.vue ──────────────────────────────────────────
const gameState     = inject('gameState', null);
const player        = gameState?.player         ?? ref({ creds: 0, techPoints: 0 });
const rig           = gameState?.rig            ?? ref({ ram: 2, tier: 1, chassis: 'BlackHat v1.0' });
const allCommands   = gameState?.commands       ?? ref([]);
const inventory     = gameState?.inventory      ?? ref({ hardware: [], consumables: [] });
const useConsumable = gameState?.useConsumable  ?? null;
const currentNodeId = gameState?.currentNodeId  ?? ref(null);

// Access is server-confirmed on mount via POST /api/cyberdoc/visit.
// The server checks the player's current_node_id against node type === 'cyberdoc'.
// Client-side location strings are not trusted for access control.
const atCyberDoc    = ref(false);
const visitChecking = ref(true);   // true while the visit request is in flight

async function visitCyberDoc() {
    visitChecking.value = true;
    atCyberDoc.value    = false;
    try {
        const res = await axios.post('/api/cyberdoc/visit');
        atCyberDoc.value = true;
        // Sync uplink — server resets it to chassis base on every visit.
        if (res.data.current_uplink != null && gameState?.player) {
            gameState.player.value.uplink    = res.data.current_uplink;
            gameState.player.value.maxUplink = res.data.current_uplink;
        }
        console.log('[CYBERDOC] Terminal accessed. Uplink restored.');
    } catch (e) {
        atCyberDoc.value = false;
        console.warn('[CYBERDOC] Access denied:', e?.response?.data?.message ?? e.message);
    } finally {
        visitChecking.value = false;
    }
}

const playerCreds       = computed(() => player.value?.creds       ?? 0);
const playerPocketCreds = computed(() => player.value?.pocketCreds ?? 0);
const playerTechPoints  = computed(() => player.value?.techPoints  ?? 0);
// ── Banking ───────────────────────────────────────────────────────────────────
const banking      = ref(false);
const bankConfirm  = ref(null);
const upgradeError = ref(null);

async function onBankCreds() {
    if (banking.value || playerPocketCreds.value === 0) return;
    banking.value = true;
    bankConfirm.value = null;

    const banked = playerPocketCreds.value;
    const result = await gameState?.bankCreds?.();

    banking.value = false;
    if (result !== null) {
        bankConfirm.value = banked;
        setTimeout(() => { bankConfirm.value = null; }, 3000);
    }
}

const { upgradeCost, totalInvested, canUpgrade, effectiveStat } = useUpgradeCosts();

const activeCategory = ref('rigs');

const categories = [
    { id: 'rigs',     label: 'RIGS'       },
    { id: 'stats',    label: 'STATS'      },
    { id: 'commands', label: 'COMMANDS'   },
    { id: 'all',      label: 'ALL ITEMS'  },
    { id: 'hardware', label: 'HARDWARE'   },
    { id: 'software', label: 'SOFTWARE'   },
    { id: 'repair',   label: 'REPAIR'     },
];

// ── Rig stat metadata ─────────────────────────────────────────────────────────
const RIG_STATS = [
    { key: 'cpu',      short: 'CPU' },
    { key: 'ram',      short: 'RAM' },
    { key: 'os',       short: 'OS'  },
    { key: 'storage',  short: 'STR' },
    { key: 'firewall', short: 'FW'  },
];

// ── Rig version label — series.investedPoints ─────────────────────────────────
const totalInvestedAll = computed(() =>
    totalInvested(rig.value?.investedPoints ?? {})
);

const chassisMaxed = computed(() =>
    totalInvestedAll.value >= (rig.value?.pointsCap ?? 9)
);

const rigVersionLabel = computed(() => {
    const tier = rig.value?.tier ?? 1;
    const pts  = totalInvestedAll.value;
    return `${tier}.${pts}`;
});

// Strip the static version suffix (e.g. " v1.0") from the chassis name so only
// the dynamic rigVersionLabel is shown as the version indicator.
// "BlackHat v1.0" → "BLACKHAT", "NullTek GX-7 Ghost" → "NULLTEK GX-7 GHOST"
const chassisBaseName = computed(() =>
    (rig.value?.chassis ?? '').replace(/\s+v\d+\.\d+$/, '').toUpperCase()
);

// ── NullTek Series 2 chassis templates ───────────────────────────────────────
// Three distinct build paths — Ghost / Breaker / Vault — unlocked when BlackHat is maxed.
// Each leans into a different stat pair; total power is equal, distribution is not.
// Counters: Ghost outruns Breaker → Breaker overwhelms Vault → Vault tanks Ghost.
//
//  Ghost   — High Uplink (7) + High OS:  mobile, hard to locate, long runs
//  Breaker — High CPU + High RAM:        fast node cracking, cracks high-ICE districts early
//  Vault   — High Firewall + High Storage: PvP durability, huge command loadout
const NULLTEK_CHASSIS = [
    {
        id:       'nulltek-gx7',
        model:    'GX-7',
        name:     'GHOST',
        brand:    'NullTek',
        build:    'ghost',
        tier:     2,
        tagline:  'High uplink. High OS. Run far, stay invisible.',
        // OS 5 → pings only reveal general area. Uplink 7 = maximum v2 mobility.
        base: { cpu: 3, ram: 3, os: 5, storage: 3, firewall: 2, uplink: 7 },
        caps: { cpu: 6, ram: 6, os: 9, storage: 6, firewall: 5, uplink: 7, pointCap: 18 },
        portSlots: 2,
        price: { creds: 3500, tp: 25 },
    },
    {
        id:       'nulltek-br9',
        model:    'BR-9',
        name:     'BREAKER',
        brand:    'NullTek',
        build:    'breaker',
        tier:     2,
        tagline:  'Max CPU. Max RAM. Outpace every node on the grid.',
        base: { cpu: 5, ram: 5, os: 2, storage: 3, firewall: 1, uplink: 5 },
        caps: { cpu: 9, ram: 8, os: 5, storage: 6, firewall: 4, uplink: 5, pointCap: 18 },
        portSlots: 2,
        price: { creds: 3500, tp: 25 },
    },
    {
        id:       'nulltek-vt3',
        model:    'VT-3',
        name:     'VAULT',
        brand:    'NullTek',
        build:    'vault',
        tier:     2,
        tagline:  'Take hits. Stack commands. Outlast everyone.',
        // Firewall 5 blocks most tier ≤4 commands. Storage 5 = largest loadout on the map.
        base: { cpu: 3, ram: 3, os: 2, storage: 5, firewall: 5, uplink: 5 },
        caps: { cpu: 6, ram: 6, os: 5, storage: 10, firewall: 9, uplink: 5, pointCap: 18 },
        portSlots: 3,
        price: { creds: 3500, tp: 25 },
    },
];

// Chassis is purchasable when current rig is fully maxed AND player can afford it
function canAffordChassis(chassis) {
    return playerCreds.value      >= chassis.price.creds
        && playerTechPoints.value >= chassis.price.tp;
}

function chassisBtnTitle(chassis) {
    if (rig.value?.tier > 1)   return 'Already on NullTek Series 2';
    if (!chassisMaxed.value)    return `Max your BlackHat first (${totalInvestedAll.value}/${rig.value?.pointsCap ?? 9} pts)`;
    if (playerCreds.value < chassis.price.creds)      return `Not enough Creds — need ${chassis.price.creds} ₡`;
    if (playerTechPoints.value < chassis.price.tp)    return `Not enough Tech Points — need ${chassis.price.tp} TP`;
    return `Purchase NullTek ${chassis.model} ${chassis.name}`;
}

async function onPurchaseChassis(chassis) {
    if (!chassisMaxed.value || !canAffordChassis(chassis)) return;

    try {
        const res = await axios.post('/api/rig/chassis-upgrade', {
            player_id:    player.value.id,
            chassis_name: `NullTek ${chassis.model} ${chassis.name}`,
            cred_cost:    chassis.price.creds,
            tp_cost:      chassis.price.tp,
        });

        const r = res.data;

        // Sync rig from server-authoritative response
        rig.value = {
            chassis:        r.chassis,
            tier:           r.tier,
            cpu:            r.stats.cpu?.effective      ?? 0,
            ram:            r.stats.ram?.effective      ?? 0,
            firewall:       r.stats.firewall?.effective ?? 0,
            storage:        r.stats.storage?.effective  ?? 0,
            os:             r.stats.os?.effective       ?? 0,
            uplink:         r.uplink,
            caps: {
                cpu:      r.caps.cpu,
                ram:      r.caps.ram,
                firewall: r.caps.firewall,
                storage:  r.caps.storage,
                os:       r.caps.os,
                pointCap: r.points.cap,
            },
            portSlots:      r.peripheral_slots,
            investedPoints: { cpu: 0, ram: 0, os: 0, storage: 0, firewall: 0 },
            currentSS:      r.current_ss,
            maxSS:          r.max_ss,
        };

        // Sync player economy + derived resources
        player.value.creds      = r.wallet_creds;
        player.value.techPoints = r.tech_points;
        player.value.uplink     = r.uplink;
        player.value.maxUplink  = r.uplink;
        player.value.currentSS  = r.current_ss;
        player.value.maxSS      = r.max_ss;

    } catch (e) {
        console.error('[CYBERDOC] Chassis upgrade failed:', e?.response?.data?.message ?? e.message);
    }
}

// ── Item grid — catalog fetched from /api/store/catalog ──────────────────────
const catalogItems = ref([]);  // flat merged array after fetch
const catalogLoading = ref(false);

async function fetchCatalog() {
    catalogLoading.value = true;
    try {
        const res = await axios.get('/api/store/catalog');
        // Normalise both catalogs into one flat list with a uniform shape
        const hardware    = (res.data.hardware    ?? []).map(i => ({ ...i, price: i.price_creds }));
        const consumables = (res.data.consumables ?? []).map(i => ({ ...i, price: i.price_creds }));
        catalogItems.value = [...hardware, ...consumables];
    } catch (e) {
        console.error('[STORE] Failed to load catalog:', e?.response?.data?.message ?? e.message);
    } finally {
        catalogLoading.value = false;
    }
}

onMounted(async () => {
    await visitCyberDoc();
    fetchCatalog();
});

const filteredItems = computed(() => {
    if (activeCategory.value === 'all') return catalogItems.value;
    return catalogItems.value.filter(i => i.category === activeCategory.value);
});

/** True when the player owns at least one of this hardware item uninstalled. */
function hardwareOwned(itemId) {
    return (inventory.value.hardware ?? []).some(h => h.peripheral_id === itemId);
}

/** How many of this consumable the player owns. */
function consumableQty(itemId) {
    const row = (inventory.value.consumables ?? []).find(c => c.consumable_id === itemId);
    return row?.quantity ?? 0;
}

async function onBuy(item) {
    if (playerCreds.value < item.price) return;

    try {
        if (item.category === 'hardware') {
            const res = await axios.post('/api/store/purchase-peripheral', {
                player_id:     player.value.id,
                peripheral_id: item.id,
            });
            player.value.creds = res.data.wallet_creds;
            // Add to local uninstalled hardware so the button reflects ownership immediately
            inventory.value.hardware.push({
                encrypt_id:    res.data.encrypt_id,
                peripheral_id: item.id,
                name:          item.name,
                stat:          item.stat,
                boost:         item.boost,
                rarity:        item.rarity,
                port_cost:     item.port_cost,
            });
        } else {
            // software or repair
            const res = await axios.post('/api/store/purchase-consumable', {
                player_id:     player.value.id,
                consumable_id: item.id,
            });
            player.value.creds = res.data.wallet_creds;
            // Upsert quantity in local consumables list
            const existing = (inventory.value.consumables ?? []).find(c => c.consumable_id === item.id);
            if (existing) {
                existing.quantity = res.data.quantity;
            } else {
                inventory.value.consumables.push({
                    consumable_id:  item.id,
                    name:           item.name,
                    category:       item.category,
                    stat:           item.stat,
                    boost:          item.boost,
                    duration_moves: item.duration_moves ?? null,
                    rarity:         item.rarity,
                    quantity:       res.data.quantity,
                });
            }
        }
    } catch (e) {
        console.error('[STORE] Purchase failed:', e?.response?.data?.message ?? e.message);
    }
}

async function onUseConsumable(item) {
    if (!useConsumable) return;
    const result = await useConsumable(item.consumable_id);
    if (result?.type === 'software') {
        console.log(`[STORE] ${item.name} active — ${result.moves_remaining} moves remaining`);
    }
}

// ── Commands shop ─────────────────────────────────────────────────────────────
const purchasableCommands = computed(() =>
    allCommands.value.filter(c => !c.owned)
);

function commandsForContext(ctx) {
    return purchasableCommands.value.filter(c => c.context === ctx);
}

function canAfford(cmd) {
    return playerCreds.value      >= cmd.price.creds
        && playerTechPoints.value >= cmd.price.techPoints;
}

function buyBtnTitle(cmd) {
    if (playerCreds.value < cmd.price.creds)           return 'Not enough Creds';
    if (playerTechPoints.value < cmd.price.techPoints) return 'Not enough Tech Points';
    return `Purchase ${cmd.name}`;
}

async function onBuyCommand(cmd) {
    if (!canAfford(cmd)) return;
    try {
        const res = await axios.post('/api/store/purchase-command', {
            player_id:  player.value.id,
            command_id: cmd.id,
        });
        cmd.owned = true;
        player.value.creds      = res.data.wallet_creds;
        player.value.techPoints = res.data.tech_points;
    } catch (e) {
        console.error('[STORE] Command purchase failed:', e?.response?.data?.message ?? e.message);
    }
}

// ── Stat upgrades ─────────────────────────────────────────────────────────────
const STAT_META = [
    { key: 'cpu',      label: 'CPU',      desc: 'Reduces ICE advantage — widens your hack window and timer.' },
    { key: 'os',       label: 'OS',       desc: 'Reduces ping accuracy. Raises the OS+Storage ceiling for CPU, RAM, and FW.' },
    { key: 'storage',  label: 'STORAGE',  desc: 'Increases inventory and loadout slots. Freely investable — each point extends the ceiling for CPU, RAM, and FW.' },
    { key: 'firewall', label: 'FIREWALL', desc: 'Reduces bounty ping frequency and improves breach defence.' },
];

const upgradeableStats = computed(() => {
    const r      = rig.value;
    const inv    = r?.investedPoints ?? {};
    const tot    = totalInvestedAll.value;
    const tier   = r?.tier ?? 1;
    // OS gates investment ceiling: no stat can be pushed above current effective OS.
    // OS itself is exempt — it must be raised first to unlock other stats.
    const currentOS = effectiveStat('os', r);

    const currentStorage = effectiveStat('storage', r);

    return STAT_META.map(({ key, label, desc }) => {
        const investedIn  = inv[key] ?? 0;
        const base        = (r?.[key] ?? 0) - investedIn;
        const effective   = effectiveStat(key, r);
        const cap         = r?.caps?.[key] ?? effective;

        // OS gate rules:
        //  • OS      — never gated (it IS the ceiling stat)
        //  • Storage — never gated (freely investable; extends the ceiling for others)
        //  • CPU / RAM / FW — cannot exceed effective OS + effective Storage combined
        let osGated = false;
        let gateMsg = '';
        if (key !== 'os' && key !== 'storage') {
            const ceiling = currentOS + currentStorage;
            osGated = effective >= ceiling;
            gateMsg = `[ OS+STR ${ceiling} CAP — RAISE OS OR STORAGE ]`;
        }

        const canUp   = canUpgrade(key, r) && !chassisMaxed.value && !osGated;
        const cost    = canUp
            ? upgradeCost(key, investedIn, tot, tier)
            : { creds: 0, tp: 0 };

        return { key, label, desc, base, effective, cap, investedIn, canUp, cost, osGated, gateMsg };
    });
});

function scalingLabel(investedIn) {
    return Math.pow(1.6, investedIn).toFixed(2);
}

function canAffordStat(cost) {
    return playerCreds.value      >= cost.creds
        && playerTechPoints.value >= cost.tp;
}

function statBtnTitle(s) {
    if (!canAffordStat(s.cost)) {
        return s.cost.tp > 0
            ? `Need ${s.cost.creds}₡ + ${s.cost.tp} TP`
            : `Need ${s.cost.creds}₡`;
    }
    return `Invest 1 point in ${s.label}`;
}

async function onUpgradeStat(stat, cost) {
    if (!canAffordStat(cost) || chassisMaxed.value) return;
    upgradeError.value = null;
    try {
        const res = await axios.post('/api/rig/upgrade', {
            player_id: player.value.id,
            stat,
        });

        // Sync all effective stats + invested levels from server response
        const s = res.data.stats ?? {};
        if (s.cpu) {
            rig.value.cpu                    = s.cpu.effective      ?? rig.value.cpu;
            rig.value.investedPoints.cpu     = s.cpu.level          ?? rig.value.investedPoints.cpu;
        }
        if (s.ram) {
            rig.value.ram                    = s.ram.effective      ?? rig.value.ram;
            rig.value.investedPoints.ram     = s.ram.level          ?? rig.value.investedPoints.ram;
        }
        if (s.os) {
            rig.value.os                     = s.os.effective       ?? rig.value.os;
            rig.value.investedPoints.os      = s.os.level           ?? rig.value.investedPoints.os;
        }
        if (s.storage) {
            rig.value.storage                = s.storage.effective  ?? rig.value.storage;
            rig.value.investedPoints.storage = s.storage.level      ?? rig.value.investedPoints.storage;
        }
        if (s.firewall) {
            rig.value.firewall               = s.firewall.effective ?? rig.value.firewall;
            rig.value.investedPoints.firewall = s.firewall.level    ?? rig.value.investedPoints.firewall;
        }

        rig.value.pointsSpent   = res.data.points?.spent  ?? rig.value.pointsSpent;
        rig.value.pointsCap     = res.data.points?.cap    ?? rig.value.pointsCap;
        player.value.creds      = res.data.wallet_creds   ?? player.value.creds;
        player.value.techPoints = res.data.tech_points    ?? player.value.techPoints;

    } catch (e) {
        upgradeError.value = e?.response?.data?.message ?? 'Upgrade failed — check your wallet balance.';
        console.error('[STATS] Upgrade failed:', upgradeError.value);
    }
}

function onResetCooldowns() {
    allCommands.value.forEach(c => { c.cooldown = false; });
    console.log('[CYBERDOC] All command cooldowns reset.');
}
</script>

<style scoped>
.store-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #07060a;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    position: relative;
}

/* Subtle scanline overlay — gives the whole page a screen feel */
.store-page::after {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 3px,
        rgba(0,0,0,0.06) 3px,
        rgba(0,0,0,0.06) 4px
    );
    pointer-events: none;
    z-index: 0;
}

/* All direct children sit above the scanline */
.store-page > * { position: relative; z-index: 1; }

/* ── Access gate ──────────────────────────────────────────────────────────── */
.access-gate {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
}
.access-gate--checking { color: #4FC3F7; }
.access-gate--denied   { color: #ff4444; }
.gate-icon        { font-size: 28px; margin-bottom: 4px; }
.gate-icon--denied { color: #ff3333; }
.gate-msg         { font-size: 13px; letter-spacing: 0.18em; }
.gate-sub         { font-size: 9px; opacity: 0.5; letter-spacing: 0.08em; margin-top: 2px; }

/* ── Storefront header ────────────────────────────────────────────────────── */
.storefront {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 9px;
    background: rgba(255,179,0,0.03);
    border-bottom: 1px solid rgba(255,179,0,0.15);
    flex-shrink: 0;
    gap: 12px;
}

.storefront-sign {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.sf-mark {
    font-size: 20px;
    color: #FFB300;
    flex-shrink: 0;
    opacity: 0.85;
}

.sf-name-block {
    display: flex;
    flex-direction: column;
    gap: 1px;
    flex-shrink: 0;
}

.sf-name {
    font-size: 15px;
    color: #FFB300;
    letter-spacing: 0.12em;
    line-height: 1;
}

.sf-handle {
    font-size: 7px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.16em;
}

.sf-district-tag {
    font-size: 7px;
    color: rgba(255,179,0,0.55);
    border: 1px solid rgba(255,179,0,0.2);
    padding: 1px 6px;
    letter-spacing: 0.12em;
    flex-shrink: 0;
}

.sf-tagline {
    font-size: 8px;
    color: rgba(255,179,0,0.25);
    letter-spacing: 0.04em;
    font-style: italic;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sf-status {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}

.sf-status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #00FF88;
    animation: status-blink 2.5s ease-in-out infinite;
}

@keyframes status-blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

.sf-status-text {
    font-size: 7px;
    color: rgba(0,255,136,0.55);
    letter-spacing: 0.14em;
}

/* Balance ledger */
.sf-ledger {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    background: rgba(0,0,0,0.25);
    border: 1px solid rgba(255,179,0,0.1);
    padding: 5px 12px;
}

.ledger-row {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 1px;
}

.ledger-label {
    font-size: 6px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.14em;
}

.ledger-value {
    font-size: 12px;
    color: #FFB300;
    letter-spacing: 0.06em;
}

.ledger-tp { color: rgba(125,249,255,0.9); }

.ledger-divider {
    width: 1px;
    height: 22px;
    background: rgba(255,179,0,0.1);
}

/* ── Banking terminal ─────────────────────────────────────────────────────── */
.bank-terminal {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 16px;
    background: rgba(0,0,0,0.3);
    border-bottom: 1px solid rgba(0,255,136,0.08);
    flex-shrink: 0;
    transition: border-color 0.2s;
}

.bank-terminal--hot {
    border-bottom-color: rgba(0,255,136,0.22);
}

.bt-label {
    font-size: 7px;
    color: rgba(0,255,136,0.3);
    letter-spacing: 0.16em;
    flex-shrink: 0;
}

.bt-amount {
    font-size: 12px;
    letter-spacing: 0.06em;
    flex-shrink: 0;
}

.bt-amount--hot   { color: #00FF88; }
.bt-amount--empty { color: rgba(0,255,136,0.2); }

.bt-risk {
    font-size: 7px;
    color: rgba(255,179,0,0.75);
    border: 1px solid rgba(255,179,0,0.25);
    padding: 1px 5px;
    letter-spacing: 0.1em;
    animation: risk-pulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes risk-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.bt-spacer { flex: 1; }

.bt-btn {
    background: transparent;
    border: 1px solid rgba(0,255,136,0.2);
    color: rgba(0,255,136,0.55);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 4px 12px;
    cursor: pointer;
    transition: all 0.12s;
    flex-shrink: 0;
}
.bt-btn:hover:not(:disabled) {
    background: rgba(0,255,136,0.07);
    border-color: rgba(0,255,136,0.55);
    color: #00FF88;
}
.bt-btn:disabled { opacity: 0.28; cursor: not-allowed; }

.bt-confirm {
    font-size: 8px;
    color: #00FF88;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.bank-confirm-enter-active, .bank-confirm-leave-active { transition: opacity 0.3s; }
.bank-confirm-enter-from,   .bank-confirm-leave-to     { opacity: 0; }

/* ── Section nav ──────────────────────────────────────────────────────────── */
.store-nav {
    display: flex;
    border-bottom: 1px solid rgba(255,179,0,0.1);
    flex-shrink: 0;
    overflow-x: auto;
}

.snav-btn {
    padding: 7px 16px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(255,179,0,0.06);
    color: rgba(255,179,0,0.3);
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.12em;
    cursor: pointer;
    transition: color 0.12s, background 0.12s;
    white-space: nowrap;
    flex-shrink: 0;
}
.snav-btn:hover  { color: rgba(255,179,0,0.65); background: rgba(255,179,0,0.03); }
.snav-btn.active {
    color: #FFB300;
    background: rgba(255,179,0,0.05);
    border-bottom: 2px solid #FFB300;
}

/* ── Off-site banner ──────────────────────────────────────────────────────── */
.offsite-banner {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 16px;
    background: rgba(255,51,51,0.05);
    border-bottom: 1px solid rgba(255,51,51,0.2);
    font-size: 8px;
    color: rgba(255,68,68,0.75);
    letter-spacing: 0.06em;
    flex-shrink: 0;
}

/* ── Shared section scroll wrapper ───────────────────────────────────────── */
.section-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.section-scroll::-webkit-scrollbar       { width: 3px; }
.section-scroll::-webkit-scrollbar-track { background: transparent; }
.section-scroll::-webkit-scrollbar-thumb { background: rgba(255,179,0,0.1); }

/* ── Shared section subheading ────────────────────────────────────────────── */
.section-subheading {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255,179,0,0.07);
    font-size: 8px;
    color: rgba(255,179,0,0.45);
    letter-spacing: 0.16em;
}
.section-sub {
    font-size: 7px;
    color: rgba(255,255,255,0.2);
    letter-spacing: 0.04em;
}

/* ── Lock notice ──────────────────────────────────────────────────────────── */
.lock-notice {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 7px 10px;
    background: rgba(255,179,0,0.03);
    border: 1px solid rgba(255,179,0,0.09);
    font-size: 8px;
    color: rgba(255,255,255,0.3);
    letter-spacing: 0.04em;
    line-height: 1.6;
}
.lock-notice strong { color: rgba(255,179,0,0.65); font-weight: normal; }

/* ── Empty state ──────────────────────────────────────────────────────────── */
.empty-state {
    padding: 40px 0;
    text-align: center;
    font-size: 9px;
    color: rgba(0,255,136,0.35);
    letter-spacing: 0.16em;
}

/* ════════════════════════════════════════════════════════════════════════════
   CURRENT CHASSIS
   ════════════════════════════════════════════════════════════════════════════ */

.chassis-current {
    border: 1px solid rgba(255,179,0,0.18);
    background: rgba(255,179,0,0.02);
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cc-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cc-label {
    font-size: 7px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.18em;
}

.cc-equipped {
    font-size: 7px;
    color: #00FF88;
    letter-spacing: 0.1em;
}

.cc-identity {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cc-tier-badge {
    font-size: 8px;
    color: rgba(255,179,0,0.55);
    border: 1px solid rgba(255,179,0,0.2);
    padding: 1px 5px;
    letter-spacing: 0.1em;
}

.cc-name {
    font-size: 14px;
    color: rgba(255,255,255,0.88);
    letter-spacing: 0.08em;
}

.cc-ver {
    font-size: 11px;
    color: rgba(255,179,0,0.65);
    letter-spacing: 0.06em;
}

.cc-stats {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.cc-stat {
    display: flex;
    align-items: center;
    gap: 8px;
}

.cc-stat-label {
    font-size: 7px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.1em;
    width: 24px;
    flex-shrink: 0;
}

.cc-pips {
    display: flex;
    gap: 2px;
    flex: 1;
}

.cc-pip {
    display: inline-block;
    width: 9px;
    height: 9px;
    border: 1px solid rgba(255,179,0,0.08);
}

.pip--base     { background: rgba(255,179,0,0.22); border-color: rgba(255,179,0,0.28); }
.pip--invested { background: rgba(0,255,136,0.32); border-color: rgba(0,255,136,0.4); }
.pip--empty    { background: transparent; }

.cc-stat-val {
    font-size: 9px;
    color: rgba(255,255,255,0.5);
    width: 16px;
    text-align: right;
    flex-shrink: 0;
}

.cc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 7px;
    border-top: 1px solid rgba(255,179,0,0.07);
}

.cc-prog {
    display: flex;
    align-items: center;
    gap: 6px;
}

.cc-prog-label {
    font-size: 6px;
    color: rgba(255,179,0,0.3);
    letter-spacing: 0.14em;
}

.cc-prog-pips {
    display: flex;
    gap: 2px;
}

.cc-prog-pip {
    display: inline-block;
    width: 12px;
    height: 5px;
    background: rgba(255,179,0,0.06);
    border: 1px solid rgba(255,179,0,0.1);
}

.prog-pip--lit {
    background: rgba(255,179,0,0.45);
    border-color: rgba(255,179,0,0.6);
}

.cc-prog-count {
    font-size: 7px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.08em;
}

.cc-maxed-tag {
    font-size: 7px;
    color: #00FF88;
    letter-spacing: 0.12em;
    border: 1px solid rgba(0,255,136,0.25);
    padding: 0 5px;
}

.cc-ports {
    font-size: 7px;
    color: rgba(255,179,0,0.3);
    letter-spacing: 0.1em;
}

/* ════════════════════════════════════════════════════════════════════════════
   CHASSIS CARDS
   ════════════════════════════════════════════════════════════════════════════ */

.chassis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
}

.chassis-card {
    border: 1px solid rgba(255,179,0,0.15);
    background: rgba(255,179,0,0.012);
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.15s, background 0.15s;
}

.chassis-card:not(.chassis-card--locked):hover {
    border-color: rgba(255,179,0,0.38);
    background: rgba(255,179,0,0.025);
}

.chassis-card--locked  { opacity: 0.4; pointer-events: none; }
.chassis-card--unavail { opacity: 0.22; }

.ccard-header {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ccard-tier {
    font-size: 7px;
    color: rgba(255,179,0,0.5);
    border: 1px solid rgba(255,179,0,0.18);
    padding: 1px 5px;
    letter-spacing: 0.1em;
}

.ccard-brand {
    font-size: 7px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.14em;
    flex: 1;
}

.ccard-build {
    font-size: 6px;
    letter-spacing: 0.1em;
    padding: 1px 5px;
    border: 1px solid;
}

.build--ghost   { color: rgba(125,249,255,0.75); border-color: rgba(125,249,255,0.25); }
.build--breaker { color: rgba(255,69,69,0.75);   border-color: rgba(255,69,69,0.25); }
.build--vault   { color: rgba(0,255,136,0.75);   border-color: rgba(0,255,136,0.25); }

.ccard-title {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.ccard-model {
    font-size: 9px;
    color: rgba(255,179,0,0.45);
    letter-spacing: 0.14em;
}

.ccard-name {
    font-size: 16px;
    color: rgba(255,255,255,0.88);
    letter-spacing: 0.08em;
    line-height: 1;
}

.ccard-tagline {
    font-size: 7px;
    color: rgba(255,255,255,0.22);
    letter-spacing: 0.03em;
    line-height: 1.6;
    font-style: italic;
    border-left: 2px solid rgba(255,179,0,0.1);
    padding-left: 7px;
}

.ccard-stats {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 7px 0;
    border-top: 1px solid rgba(255,179,0,0.06);
    border-bottom: 1px solid rgba(255,179,0,0.06);
}

.ccard-stat {
    display: flex;
    align-items: center;
    gap: 6px;
}

.ccard-stat-key {
    font-size: 6px;
    color: rgba(255,179,0,0.3);
    letter-spacing: 0.1em;
    width: 22px;
    flex-shrink: 0;
}

.ccard-stat-pips {
    display: flex;
    gap: 2px;
    flex: 1;
}

.ccard-pip {
    display: inline-block;
    width: 7px;
    height: 7px;
    background: rgba(255,179,0,0.06);
    border: 1px solid rgba(255,179,0,0.1);
}

.ccard-pip--base   { background: rgba(255,179,0,0.28); border-color: rgba(255,179,0,0.38); }
.ccard-pip--uplink { background: rgba(0,255,255,0.22); border-color: rgba(0,255,255,0.28); }

.ccard-stat-val {
    font-size: 8px;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.ccard-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 7px;
    border-top: 1px solid rgba(255,179,0,0.06);
}

.ccard-price {
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.cp-creds { font-size: 12px; color: #FFB300; letter-spacing: 0.05em; }
.cp-sep   { font-size: 8px;  color: rgba(255,255,255,0.18); }
.cp-tp    { font-size: 10px; color: rgba(125,249,255,0.75); letter-spacing: 0.05em; }

.ccard-buy {
    background: rgba(255,179,0,0.07);
    border: 1px solid rgba(255,179,0,0.35);
    color: #FFB300;
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.12em;
    padding: 4px 12px;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
.ccard-buy:hover:not(:disabled) {
    background: rgba(255,179,0,0.14);
    border-color: #FFB300;
}
.ccard-buy:disabled { opacity: 0.22; cursor: not-allowed; }

/* ════════════════════════════════════════════════════════════════════════════
   STAT UPGRADES
   ════════════════════════════════════════════════════════════════════════════ */

.stat-intro {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,179,0,0.07);
}

.stat-intro-label {
    font-size: 8px;
    color: rgba(255,179,0,0.5);
    letter-spacing: 0.16em;
}

.stat-intro-sub {
    font-size: 7px;
    color: rgba(255,255,255,0.25);
    letter-spacing: 0.04em;
    line-height: 1.6;
}

.stat-global {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    background: rgba(255,179,0,0.025);
    border: 1px solid rgba(255,179,0,0.07);
}

.sg-label {
    font-size: 7px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.12em;
    flex-shrink: 0;
}

.sg-pips { display: flex; gap: 2px; }

.sg-pip {
    width: 7px;
    height: 7px;
    background: rgba(255,179,0,0.07);
    border: 1px solid rgba(255,179,0,0.12);
}

.sg-pip--lit {
    background: rgba(255,179,0,0.4);
    border-color: rgba(255,179,0,0.55);
}

.sg-count {
    font-size: 7px;
    color: rgba(255,179,0,0.3);
    letter-spacing: 0.05em;
}

.stat-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid rgba(255,179,0,0.09);
    background: rgba(255,179,0,0.012);
    transition: border-color 0.12s;
    flex-wrap: wrap;
}

.stat-row:hover:not(.stat-row--maxed) { border-color: rgba(255,179,0,0.24); }
.stat-row--maxed { opacity: 0.38; }

.sr-identity {
    display: flex;
    flex-direction: column;
    gap: 2px;
    width: 90px;
    flex-shrink: 0;
}

.sr-name {
    font-size: 10px;
    color: rgba(255,255,255,0.78);
    letter-spacing: 0.1em;
}

.sr-desc {
    font-size: 6px;
    color: rgba(255,255,255,0.25);
    letter-spacing: 0.03em;
    line-height: 1.55;
}

.sr-pips {
    display: flex;
    align-items: center;
    gap: 2px;
    flex: 1;
    min-width: 100px;
}

.sr-pip {
    width: 10px;
    height: 10px;
    border: 1px solid rgba(255,179,0,0.1);
    flex-shrink: 0;
}

.sr-pip-label {
    font-size: 8px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.07em;
    margin-left: 4px;
    white-space: nowrap;
}

.sr-cost {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    flex-wrap: wrap;
}

.sr-cost-creds { font-size: 10px; color: #FFB300; letter-spacing: 0.05em; }
.sr-cost-tp    { font-size: 9px;  color: rgba(125,249,255,0.65); letter-spacing: 0.05em; }
.sr-scale      { font-size: 7px;  color: rgba(255,69,69,0.55);  letter-spacing: 0.05em; }
.sr-capped     { font-size: 7px;  color: rgba(0,255,136,0.4);  letter-spacing: 0.14em; }
.sr-gated      { font-size: 7px;  color: rgba(255,179,0,0.45); letter-spacing: 0.08em; }

.sr-btn {
    background: rgba(255,179,0,0.07);
    border: 1px solid rgba(255,179,0,0.3);
    color: #FFB300;
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.14em;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    margin-left: auto;
    transition: background 0.12s, border-color 0.12s;
}
.sr-btn:hover:not(:disabled) {
    background: rgba(255,179,0,0.14);
    border-color: #FFB300;
}
.sr-btn:disabled { opacity: 0.22; cursor: not-allowed; }

.stat-error {
    padding: 6px 12px;
    font-size: 9px;
    letter-spacing: 0.07em;
    color: #ff4455;
    border: 1px solid rgba(255,68,85,0.3);
    background: rgba(255,68,85,0.04);
}

.stat-maxed-notice {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid rgba(0,255,136,0.18);
    background: rgba(0,255,136,0.025);
}

.smn-check { font-size: 14px; color: #00FF88; flex-shrink: 0; }
.smn-title { font-size: 9px;  color: #00FF88; letter-spacing: 0.12em; }
.smn-sub   { font-size: 7px;  color: rgba(0,255,136,0.45); letter-spacing: 0.04em; line-height: 1.6; margin-top: 2px; }

.cooldown-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 12px;
    border: 1px solid rgba(0,255,136,0.1);
    background: rgba(0,255,136,0.015);
}

.cd-label { font-size: 8px; color: rgba(0,255,136,0.55); letter-spacing: 0.12em; }
.cd-sub   { font-size: 7px; color: rgba(255,255,255,0.25); letter-spacing: 0.04em; margin-top: 2px; }

.cd-btn {
    background: transparent;
    border: 1px solid rgba(0,255,136,0.22);
    color: rgba(0,255,136,0.65);
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.12em;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.12s;
    white-space: nowrap;
}
.cd-btn:hover { background: rgba(0,255,136,0.07); border-color: rgba(0,255,136,0.5); color: #00FF88; }

/* ════════════════════════════════════════════════════════════════════════════
   COMMANDS
   ════════════════════════════════════════════════════════════════════════════ */

.cmd-group {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.cmd-group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255,179,0,0.07);
    margin-bottom: 2px;
    font-size: 8px;
    color: rgba(255,179,0,0.45);
    letter-spacing: 0.16em;
}

.cmd-group-sub {
    font-size: 7px;
    color: rgba(255,179,0,0.22);
    letter-spacing: 0.08em;
}

.cmd-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border: 1px solid rgba(255,179,0,0.08);
    background: rgba(255,179,0,0.008);
    transition: border-color 0.12s;
    flex-wrap: wrap;
}

.cmd-row:hover { border-color: rgba(255,179,0,0.22); }

.cmd-type {
    font-size: 6px;
    letter-spacing: 0.1em;
    padding: 1px 4px;
    border: 1px solid;
    flex-shrink: 0;
}

.cmd-type--trap      { color: rgba(255,69,180,0.8);  border-color: rgba(255,69,180,0.28); }
.cmd-type--stealth   { color: rgba(125,249,255,0.8); border-color: rgba(125,249,255,0.28); }
.cmd-type--defensive { color: rgba(0,255,136,0.8);   border-color: rgba(0,255,136,0.28); }
.cmd-type--offensive { color: rgba(255,69,69,0.9);   border-color: rgba(255,69,69,0.28); }

.cmd-name   { font-size: 10px; color: rgba(255,255,255,0.78); letter-spacing: 0.06em; flex: 1; min-width: 80px; }
.cmd-effect { font-size: 7px;  color: rgba(255,255,255,0.28); letter-spacing: 0.03em; flex: 2; min-width: 100px; line-height: 1.5; }

.cmd-price {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.cmd-buy {
    background: rgba(255,179,0,0.07);
    border: 1px solid rgba(255,179,0,0.35);
    color: #FFB300;
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.12em;
    padding: 3px 10px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, border-color 0.12s;
}
.cmd-buy:hover:not(:disabled) { background: rgba(255,179,0,0.14); border-color: #FFB300; }
.cmd-buy:disabled { opacity: 0.22; cursor: not-allowed; }

/* ════════════════════════════════════════════════════════════════════════════
   ITEM GRID
   ════════════════════════════════════════════════════════════════════════════ */

.item-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px;
    align-content: start;
}

.item-card {
    border: 1px solid rgba(255,179,0,0.1);
    background: rgba(255,179,0,0.015);
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    transition: border-color 0.15s, background 0.15s;
}

.item-card:hover {
    border-color: rgba(255,179,0,0.28);
    background: rgba(255,179,0,0.03);
}

.item-card--uncommon { border-color: rgba(125,249,255,0.15); }
.item-card--uncommon:hover { border-color: rgba(125,249,255,0.38); }
.item-card--rare     { border-color: rgba(255,105,180,0.18); }
.item-card--rare:hover { border-color: rgba(255,105,180,0.4); }

.item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
}

.item-rarity {
    font-size: 6px;
    letter-spacing: 0.14em;
}

.item-card--common   .item-rarity { color: rgba(255,179,0,0.4); }
.item-card--uncommon .item-rarity { color: rgba(125,249,255,0.55); }
.item-card--rare     .item-rarity { color: rgba(255,105,180,0.65); }

.item-stat-badge {
    font-size: 7px;
    color: #00FF88;
    letter-spacing: 0.08em;
    border: 1px solid rgba(0,255,136,0.25);
    padding: 0 5px;
}

.stat-badge--slot { color: rgba(125,249,255,0.8); border-color: rgba(125,249,255,0.25); }

.item-name {
    font-size: 10px;
    color: rgba(255,255,255,0.82);
    letter-spacing: 0.05em;
    line-height: 1.3;
}

.item-desc {
    font-size: 7px;
    color: rgba(255,255,255,0.3);
    letter-spacing: 0.03em;
    line-height: 1.55;
    flex: 1;
}

.item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    padding-top: 6px;
    border-top: 1px solid rgba(255,179,0,0.07);
}

.item-price { font-size: 11px; color: #FFB300; letter-spacing: 0.05em; }

.item-owned {
    font-size: 6px;
    color: rgba(0,255,136,0.65);
    border: 1px solid rgba(0,255,136,0.22);
    padding: 2px 6px;
    letter-spacing: 0.1em;
}

.item-qty {
    font-size: 10px;
    color: rgba(0,255,136,0.75);
    letter-spacing: 0.05em;
}

.item-buy {
    background: rgba(255,179,0,0.07);
    border: 1px solid rgba(255,179,0,0.35);
    color: #FFB300;
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 3px 9px;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
.item-buy:hover:not(:disabled) { background: rgba(255,179,0,0.14); border-color: #FFB300; }
.item-buy:disabled { opacity: 0.22; cursor: not-allowed; }

.item-use {
    background: rgba(0,255,136,0.07);
    border: 1px solid rgba(0,255,136,0.3);
    color: rgba(0,255,136,0.85);
    font-family: inherit;
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 3px 8px;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
.item-use:hover { background: rgba(0,255,136,0.14); border-color: #00FF88; }
</style>
