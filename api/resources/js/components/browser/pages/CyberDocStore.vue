<template>
    <div class="store-page">

        <header class="store-header">
            <div class="store-brand">
                <span class="store-logo">⬡ CYBERDOC</span>
                <span class="store-tagline">// Authorized Hardware &amp; Software Vendor</span>
            </div>
            <div class="store-balances">
                <div class="bal-item">
                    <span class="bal-label">CREDS</span>
                    <span class="bal-value">{{ playerCreds.toLocaleString() }} ₡</span>
                </div>
                <div class="bal-sep" />
                <div class="bal-item">
                    <span class="bal-label">TECH PTS</span>
                    <span class="bal-value bal-tp">{{ playerTechPoints }} TP</span>
                </div>
            </div>
        </header>

        <!-- ── Banking strip ─────────────────────────────────────────────────── -->
        <div class="bank-strip" :class="{ 'bank-strip--hot': playerPocketCreds > 0 }">
            <div class="bank-strip-left">
                <span class="bank-label">POCKET</span>
                <span class="bank-pocket" :class="playerPocketCreds > 0 ? 'pocket--hot' : 'pocket--empty'">
                    {{ playerPocketCreds.toLocaleString() }} ₡
                </span>
                <span v-if="playerPocketCreds > 0" class="bank-risk">AT RISK</span>
            </div>
            <button
                class="bank-btn"
                :disabled="playerPocketCreds === 0 || banking"
                @click="onBankCreds"
            >
                <span v-if="banking">BANKING…</span>
                <span v-else-if="playerPocketCreds === 0">NOTHING TO BANK</span>
                <span v-else>[ EXTRACT ◈{{ playerPocketCreds.toLocaleString() }} ]</span>
            </button>
            <Transition name="bank-confirm">
                <span v-if="bankConfirm" class="bank-confirm">✓ {{ bankConfirm.toLocaleString() }} ₡ SECURED</span>
            </Transition>
        </div>

        <!-- ── Cache flush strip ──────────────────────────────────────────────── -->
        <div class="flush-strip" :class="{ 'flush-strip--hot': playerCache > 0 }">
            <div class="flush-strip-left">
                <span class="flush-label">CACHE</span>
                <span class="flush-cache" :class="playerCache >= playerMaxCache ? 'cache--full' : 'cache--ok'">
                    {{ playerCache }}/{{ playerMaxCache }}
                </span>
                <span v-if="playerCache >= playerMaxCache" class="flush-warn">PING EXPOSED</span>
                <span v-else-if="flushCooldownSecs > 0" class="flush-cooldown">
                    COOLDOWN {{ Math.ceil(flushCooldownSecs / 60) }}m
                </span>
            </div>
            <button
                class="flush-btn"
                :disabled="playerCache === 0 || flushing || flushCooldownSecs > 0"
                @click="onFlushCache"
            >
                <span v-if="flushing">FLUSHING…</span>
                <span v-else-if="flushCooldownSecs > 0">ON COOLDOWN</span>
                <span v-else-if="playerCache === 0">CACHE CLEAR</span>
                <span v-else>[ FLUSH CACHE — {{ flushCost }} ₡ ]</span>
            </button>
            <Transition name="bank-confirm">
                <span v-if="flushConfirm" class="flush-confirm">✓ CACHE FLUSHED</span>
            </Transition>
        </div>

        <div class="store-category-bar">
            <button
                v-for="cat in categories"
                :key="cat.id"
                class="cat-btn"
                :class="{ active: activeCategory === cat.id }"
                @click="activeCategory = cat.id"
            >{{ cat.label }}</button>
        </div>

        <!-- ── Rigs shop ─────────────────────────────────────────────────────── -->
        <div v-if="activeCategory === 'rigs'" class="rigs-shop">

            <!-- Current chassis block -->
            <div class="chassis-current">
                <div class="chassis-current-header">
                    <span class="cc-label">CURRENT CHASSIS</span>
                    <div class="cc-equipped">EQUIPPED</div>
                </div>

                <div class="cc-identity">
                    <div class="cc-tier">T{{ rig.tier }}</div>
                    <span class="cc-name">{{ (rig.chassis ?? '').toUpperCase() }}</span>
                    <span class="cc-ver">v{{ rigVersionLabel }}</span>
                </div>

                <div class="cc-stats">
                    <div v-for="s in RIG_STATS" :key="s.key" class="cc-stat">
                        <span class="cc-stat-label">{{ s.short }}</span>
                        <div class="cc-stat-pips">
                            <span
                                v-for="n in (rig.caps[s.key] ?? effectiveStat(s.key, rig))"
                                :key="n"
                                class="cc-pip"
                                :class="{
                                    'cc-pip--base':     n <= rig[s.key] - (rig.investedPoints?.[s.key] ?? 0),
                                    'cc-pip--invested': n > rig[s.key] - (rig.investedPoints?.[s.key] ?? 0) && n <= rig[s.key],
                                    'cc-pip--empty':    n > rig[s.key],
                                }"
                            />
                        </div>
                        <span class="cc-stat-val">{{ effectiveStat(s.key, rig) }}</span>
                    </div>
                </div>

                <div class="cc-footer">
                    <div class="cc-progress">
                        <span class="cc-prog-label">UPGRADE PROGRESS</span>
                        <div class="cc-prog-bar">
                            <span
                                v-for="n in (rig.caps?.pointCap ?? 9)"
                                :key="n"
                                class="cc-prog-pip"
                                :class="{ 'cc-prog-pip--lit': n <= totalInvestedAll }"
                            />
                        </div>
                        <span class="cc-prog-count">{{ totalInvestedAll }}/{{ rig.caps?.pointCap ?? 9 }} PTS</span>
                        <span class="cc-prog-sub" v-if="chassisMaxed">— CHASSIS MAXED</span>
                    </div>
                    <div class="cc-ports">
                        <span class="cc-ports-label">PORT SLOTS</span>
                        <span class="cc-ports-val">{{ rig.portSlots ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <!-- Available chassis heading -->
            <div class="rigs-section-label">
                <span>AVAILABLE CHASSIS</span>
                <span class="rigs-section-sub">NullTek Series 2 — unlock by maxing your current rig</span>
            </div>

            <!-- Lock notice -->
            <div v-if="!chassisMaxed && rig.tier === 1" class="chassis-lock-bar">
                <span class="lock-icon">🔒</span>
                <span class="lock-text">
                    NullTek Series 2 unlocks when your BlackHat reaches v1.9 —
                    <strong>{{ totalInvestedAll }}/{{ rig.caps?.pointCap ?? 9 }} upgrade points invested.</strong>
                    Visit the STATS tab to keep investing.
                </span>
            </div>

            <!-- NullTek chassis cards -->
            <div class="chassis-grid">
                <div
                    v-for="chassis in NULLTEK_CHASSIS"
                    :key="chassis.id"
                    class="chassis-card"
                    :class="{
                        'chassis-card--locked':   !chassisMaxed || rig.tier > 1,
                        'chassis-card--unavail':  rig.tier > 1,
                    }"
                >
                    <div class="ccard-header">
                        <div class="ccard-tier">T{{ chassis.tier }}</div>
                        <div class="ccard-brand">NULLTEK</div>
                        <div class="ccard-build" :class="`build--${chassis.build}`">{{ chassis.build.toUpperCase() }}</div>
                    </div>

                    <div class="ccard-model">{{ chassis.model }}</div>
                    <div class="ccard-name">{{ chassis.name }}</div>
                    <div class="ccard-tagline">{{ chassis.tagline }}</div>

                    <div class="ccard-stats">
                        <div v-for="s in RIG_STATS" :key="s.key" class="ccard-stat-row">
                            <span class="ccard-stat-key">{{ s.short }}</span>
                            <div class="ccard-stat-bar">
                                <span
                                    v-for="n in chassis.caps[s.key]"
                                    :key="n"
                                    class="ccard-stat-pip"
                                    :class="{ 'cstat-pip--base': n <= chassis.base[s.key] }"
                                />
                            </div>
                            <span class="ccard-stat-val">{{ chassis.base[s.key] }}</span>
                            <span class="ccard-stat-cap">/ {{ chassis.caps[s.key] }}</span>
                        </div>
                        <div class="ccard-stat-row">
                            <span class="ccard-stat-key">UPL</span>
                            <div class="ccard-stat-bar">
                                <span
                                    v-for="n in chassis.base.uplink"
                                    :key="n"
                                    class="ccard-stat-pip cstat-pip--uplink"
                                />
                            </div>
                            <span class="ccard-stat-val">{{ chassis.base.uplink }}</span>
                            <span class="ccard-stat-cap ccard-stat-cap--locked">🔒</span>
                        </div>
                    </div>

                    <div class="ccard-meta">
                        <div class="ccard-meta-row">
                            <span class="ccard-meta-key">PORT SLOTS</span>
                            <span class="ccard-meta-val">{{ chassis.portSlots }}</span>
                        </div>
                        <div class="ccard-meta-row">
                            <span class="ccard-meta-key">UPGRADE CAP</span>
                            <span class="ccard-meta-val">{{ chassis.caps.pointCap }} pts (v{{ chassis.tier }}.0 → v{{ chassis.tier }}.9)</span>
                        </div>
                    </div>

                    <div class="ccard-footer">
                        <div class="ccard-price">
                            <span class="ccard-price-creds">{{ chassis.price.creds.toLocaleString() }} ₡</span>
                            <span class="ccard-price-sep">+</span>
                            <span class="ccard-price-tp">{{ chassis.price.tp }} TP</span>
                        </div>
                        <button
                            class="ccard-buy-btn"
                            :disabled="!chassisMaxed || rig.tier > 1 || !canAffordChassis(chassis)"
                            :title="chassisBtnTitle(chassis)"
                            @click="onPurchaseChassis(chassis)"
                        >
                            {{ rig.tier > 1 ? 'OWNED TIER' : 'PURCHASE' }}
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Stat upgrades ────────────────────────────────────────────────── -->
        <div v-else-if="activeCategory === 'stats'" class="stat-shop">

            <div class="stat-shop-intro">
                <span class="stat-intro-label">RIG STAT INVESTMENT</span>
                <span class="stat-intro-sub">
                    Costs escalate with each upgrade — both within the same stat and globally as your rig grows.
                    Chassis cap limits maximum investment per stat.
                </span>
            </div>

            <!-- Global scaling indicator -->
            <div class="stat-global-row">
                <span class="sg-label">GLOBAL PROGRESSION:</span>
                <div class="sg-pips">
                    <span
                        v-for="n in (rig.caps?.pointCap ?? 9)"
                        :key="n"
                        class="sg-pip"
                        :class="{ 'sg-pip--lit': n <= totalInvestedAll }"
                    />
                </div>
                <span class="sg-count">{{ totalInvestedAll }} POINT{{ totalInvestedAll !== 1 ? 'S' : '' }} INVESTED — COST MODIFIER: +{{ Math.round((Math.pow(1.25, totalInvestedAll) - 1) * 100) }}%</span>
            </div>

            <div class="stat-list">
                <div
                    v-for="s in upgradeableStats"
                    :key="s.key"
                    class="stat-upgrade-row"
                    :class="{ 'stat-row--maxed': !s.canUp }"
                >
                    <!-- Stat identity -->
                    <div class="su-identity">
                        <span class="su-name">{{ s.label }}</span>
                        <span class="su-desc">{{ s.desc }}</span>
                    </div>

                    <!-- Current value bar -->
                    <div class="su-bar-wrap">
                        <div
                            v-for="n in s.cap"
                            :key="n"
                            class="su-bar-pip"
                            :class="{
                                'pip--base':     n <= s.base,
                                'pip--invested': n > s.base && n <= s.effective,
                                'pip--empty':    n > s.effective,
                            }"
                        />
                        <span class="su-bar-label">{{ s.effective }} / {{ s.cap }}</span>
                    </div>

                    <!-- Cost + action -->
                    <div class="su-cost-wrap">
                        <template v-if="s.canUp">
                            <span class="su-next-label">NEXT POINT:</span>
                            <span class="su-cost-creds">{{ s.cost.creds.toLocaleString() }} ₡</span>
                            <span v-if="s.cost.tp > 0" class="su-cost-tp">+ {{ s.cost.tp }} TP</span>
                            <span v-if="s.investedIn > 0" class="su-scale-warn">
                                ↑ ×{{ scalingLabel(s.investedIn) }} ({{ s.investedIn }} invested)
                            </span>
                        </template>
                        <template v-else-if="s.osGated">
                            <span class="su-os-gate">[ OS {{ effectiveStat('os', rig) }} CAP — RAISE OS FIRST ]</span>
                        </template>
                        <span v-else class="su-maxed">[ CAPPED ]</span>
                    </div>

                    <button
                        v-if="s.canUp"
                        class="su-btn"
                        :disabled="!canAffordStat(s.cost) || chassisMaxed"
                        :title="chassisMaxed ? 'Chassis fully upgraded — purchase NullTek Series 2 to continue' : statBtnTitle(s)"
                        @click="onUpgradeStat(s.key, s.cost)"
                    >INVEST</button>

                </div>
            </div>

            <!-- Chassis maxed notice -->
            <div v-if="chassisMaxed" class="stat-maxed-notice">
                <span class="maxed-icon">✓</span>
                <div class="maxed-body">
                    <span class="maxed-title">BLACKHAT FULLY UPGRADED — v1.9</span>
                    <span class="maxed-sub">Visit the RIGS tab to purchase a NullTek Series 2 chassis and continue your progression.</span>
                </div>
            </div>

            <!-- Cooldown reset -->
            <div class="stat-cooldown-section">
                <div class="scd-heading">COMMAND COOLDOWN RESET</div>
                <div class="scd-sub">Visiting CyberDoc resets all command cooldowns. No charge.</div>
                <button class="scd-btn" @click="onResetCooldowns">[ RESET ALL COOLDOWNS ]</button>
            </div>

        </div>

        <!-- ── Commands shop ────────────────────────────────────────────────── -->
        <div v-else-if="activeCategory === 'commands'" class="cmd-shop">

            <div v-if="purchasableCommands.length === 0" class="cmd-shop-empty">
                ALL AVAILABLE COMMANDS OWNED
            </div>

            <template v-for="tier in [1, 2, 3, 4, 5]" :key="tier">
                <div v-if="commandsForTier(tier).length" class="cmd-tier-group">

                    <div class="cmd-tier-heading">
                        <span class="cmd-tier-label">TIER {{ tier }}</span>
                        <span class="cmd-tier-req">
                            RAM {{ tier }}+ REQUIRED
                        </span>
                    </div>

                    <div
                        v-for="cmd in commandsForTier(tier)"
                        :key="cmd.id"
                        class="cmd-shop-row"
                        :class="{ 'cmd-shop-row--locked': !ramMeetsRequirement(tier) }"
                    >
                        <div class="cmd-shop-main">
                            <span class="cmd-shop-type" :class="`shop-type--${cmd.type}`">
                                {{ cmd.type.toUpperCase() }}
                            </span>
                            <span class="cmd-shop-name">{{ cmd.name.toUpperCase() }}</span>
                            <div class="cmd-shop-price">
                                <span class="price-creds">{{ cmd.price.creds.toLocaleString() }} ₡</span>
                                <span class="price-sep">+</span>
                                <span class="price-tp">{{ cmd.price.techPoints }} TP</span>
                            </div>
                            <button
                                class="cmd-buy-btn"
                                :disabled="!canAfford(cmd) || !ramMeetsRequirement(tier)"
                                :title="buyBtnTitle(cmd, tier)"
                                @click="onBuyCommand(cmd)"
                            >PURCHASE</button>
                        </div>
                        <div class="cmd-shop-effect">
                            <span class="eff-key">MAP</span>
                            <span class="eff-val">{{ cmd.mapEffect }}</span>
                        </div>
                        <div v-if="!ramMeetsRequirement(tier)" class="cmd-lock-notice">
                            🔒 Requires RAM {{ tier }} — upgrade your rig to unlock
                        </div>
                    </div>
                </div>
            </template>

        </div>

        <!-- ── Hardware / Software / Repair grid ───────────────────────────── -->
        <div v-else class="store-grid">
            <div
                v-for="item in filteredItems"
                :key="item.id"
                class="store-item"
                :class="`store-item--${item.rarity}`"
            >
                <div class="item-rarity">{{ item.rarity.toUpperCase() }}</div>
                <div class="item-name">{{ item.name }}</div>
                <div class="item-stat">
                    <span class="stat-key">{{ item.stat.toUpperCase() }}</span>
                    <span class="stat-val">+{{ item.boost }}</span>
                </div>
                <div class="item-desc">{{ item.desc }}</div>
                <div class="item-footer">
                    <span class="item-price">{{ item.price.toLocaleString() }} ₡</span>

                    <!-- Hardware: show OWNED badge if uninstalled copy in inventory -->
                    <template v-if="item.category === 'hardware'">
                        <span v-if="hardwareOwned(item.id)" class="item-owned-badge">OWNED — INSTALL AT PORTS</span>
                        <button
                            v-else
                            class="buy-btn"
                            :disabled="playerCreds < item.price"
                            @click="onBuy(item)"
                        >PURCHASE</button>
                    </template>

                    <!-- Consumables: purchase + quantity display + USE button -->
                    <template v-else>
                        <span v-if="consumableQty(item.id) > 0" class="item-qty">×{{ consumableQty(item.id) }}</span>
                        <button
                            class="buy-btn"
                            :disabled="playerCreds < item.price"
                            @click="onBuy(item)"
                        >{{ consumableQty(item.id) > 0 ? '+1 MORE' : 'PURCHASE' }}</button>
                        <button
                            v-if="consumableQty(item.id) > 0"
                            class="use-btn"
                            @click="onUseConsumable(item)"
                        >USE</button>
                    </template>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import { useUpgradeCosts } from '@/composables/useUpgradeCosts.js';

defineProps({ url: { type: String, default: '' } });

// ── Real player state from Game.vue ──────────────────────────────────────────
const gameState     = inject('gameState', null);
const player        = gameState?.player         ?? ref({ creds: 0, techPoints: 0 });
const rig           = gameState?.rig            ?? ref({ ram: 2, tier: 1, chassis: 'BlackHat v1.0' });
const allCommands   = gameState?.commands       ?? ref([]);
const inventory     = gameState?.inventory      ?? ref({ hardware: [], consumables: [] });
const useConsumable = gameState?.useConsumable  ?? null;
const currentNodeId = gameState?.currentNodeId  ?? ref(null);

const playerCreds       = computed(() => player.value?.creds       ?? 0);
const playerPocketCreds = computed(() => player.value?.pocketCreds ?? 0);
const playerTechPoints  = computed(() => player.value?.techPoints  ?? 0);
const playerCache       = computed(() => player.value?.cache       ?? 0);
const playerMaxCache    = computed(() => player.value?.maxCache     ?? 5);

// Flush cost = 30 creds × current cache
const FLUSH_COST_PER = 30;
const flushCost = computed(() => playerCache.value * FLUSH_COST_PER);

// Cooldown — counts down from 600s after a flush or extract at this node.
// Stored locally; resets if the page reloads (acceptable — server enforces it).
const flushCooldownSecs = ref(0);
let   _cooldownInterval = null;

function startCooldown() {
    flushCooldownSecs.value = 600;
    clearInterval(_cooldownInterval);
    _cooldownInterval = setInterval(() => {
        flushCooldownSecs.value = Math.max(0, flushCooldownSecs.value - 1);
        if (flushCooldownSecs.value === 0) clearInterval(_cooldownInterval);
    }, 1000);
}

// ── Banking ───────────────────────────────────────────────────────────────────
const banking     = ref(false);
const bankConfirm = ref(null);

async function onBankCreds() {
    if (banking.value || playerPocketCreds.value === 0) return;
    banking.value = true;
    bankConfirm.value = null;

    const banked = playerPocketCreds.value;
    const result = await gameState?.bankCreds?.();

    banking.value = false;
    if (result !== null) {
        bankConfirm.value = banked;
        startCooldown();
        setTimeout(() => { bankConfirm.value = null; }, 3000);
    }
}

// ── Cache flush ───────────────────────────────────────────────────────────────
const flushing     = ref(false);
const flushConfirm = ref(false);

async function onFlushCache() {
    if (flushing.value || playerCache.value === 0 || flushCooldownSecs.value > 0) return;
    flushing.value = true;
    flushConfirm.value = false;

    const result = await gameState?.flushCache?.();

    flushing.value = false;
    if (result && !result.error) {
        flushConfirm.value = true;
        startCooldown();
        setTimeout(() => { flushConfirm.value = false; }, 3000);
    } else if (result?.error) {
        console.warn('[CYBERDOC] Flush error:', result.error);
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
    totalInvestedAll.value >= (rig.value?.caps?.pointCap ?? 9)
);

const rigVersionLabel = computed(() => {
    const tier = rig.value?.tier ?? 1;
    const pts  = totalInvestedAll.value;
    return `${tier}.${pts}`;
});

// ── NullTek Series 2 chassis templates ───────────────────────────────────────
// Three distinct build paths — Ghost / Breaker / Vault — unlocked when BlackHat is maxed.
// Each leans into a different stat pair; total power is equal, distribution is not.
// Counters: Ghost outruns Breaker → Breaker overwhelms Vault → Vault tanks Ghost.
//
//  Ghost   — High Uplink (7) + High OS:  mobile, hard to locate, long runs
//  Breaker — High CPU + High RAM:        fast node cracking, massive cache pool (10)
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
        tagline:  'Max cache. Max cracks. Outpace every node on the grid.',
        // CPU 5 + RAM 5 = 10 cache at base (double BlackHat). Cracks high-ICE districts early.
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
    if (!chassisMaxed.value)    return `Max your BlackHat first (${totalInvestedAll.value}/${rig.value?.caps?.pointCap ?? 9} pts)`;
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
        player.value.maxCache   = r.stats.cpu + r.stats.ram;
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

onMounted(fetchCatalog);

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

function commandsForTier(tier) {
    return purchasableCommands.value.filter(c => c.tier === tier);
}

function ramMeetsRequirement(tier) {
    return (rig.value?.ram ?? 0) >= tier;
}

function canAfford(cmd) {
    return playerCreds.value      >= cmd.price.creds
        && playerTechPoints.value >= cmd.price.techPoints;
}

function buyBtnTitle(cmd, tier) {
    if (!ramMeetsRequirement(tier)) return `Requires RAM ${tier}`;
    if (playerCreds.value < cmd.price.creds)           return 'Not enough Creds';
    if (playerTechPoints.value < cmd.price.techPoints) return 'Not enough Tech Points';
    return `Purchase ${cmd.name}`;
}

async function onBuyCommand(cmd) {
    if (!canAfford(cmd) || !ramMeetsRequirement(cmd.tier)) return;
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
    { key: 'ram',      label: 'RAM',      desc: 'Unlocks higher command tiers and increases command slot capacity.' },
    { key: 'os',       label: 'OS',       desc: 'Reduces how accurately ICE pings reveal your location.' },
    { key: 'storage',  label: 'STORAGE',  desc: 'Increases inventory and command loadout slots.' },
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

    return STAT_META.map(({ key, label, desc }) => {
        const investedIn  = inv[key] ?? 0;
        const base        = r?.[key] ?? 0;
        const effective   = effectiveStat(key, r);
        const cap         = r?.caps?.[key] ?? effective;

        // OS gate: non-OS stats cannot be invested past the current OS level
        const osGated = key !== 'os' && effective >= currentOS;
        const canUp   = canUpgrade(key, r) && !chassisMaxed.value && !osGated;
        const cost    = canUp
            ? upgradeCost(key, investedIn, tot, tier)
            : { creds: 0, tp: 0 };

        return { key, label, desc, base, effective, cap, investedIn, canUp, cost, osGated };
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
    try {
        const res = await axios.post('/api/rig/upgrade', {
            player_id: player.value.id,
            stat,
        });

        // Bump invested points locally so cost formula stays in sync
        rig.value.investedPoints[stat] = (rig.value.investedPoints[stat] ?? 0) + 1;

        // Sync effective stats and balances from response
        const s = res.data.stats ?? {};
        if (s.cpu)      rig.value.cpu      = s.cpu.effective      ?? rig.value.cpu;
        if (s.ram)      rig.value.ram      = s.ram.effective      ?? rig.value.ram;
        if (s.os)       rig.value.os       = s.os.effective       ?? rig.value.os;
        if (s.storage)  rig.value.storage  = s.storage.effective  ?? rig.value.storage;
        if (s.firewall) rig.value.firewall = s.firewall.effective  ?? rig.value.firewall;

        player.value.creds      = res.data.wallet_creds ?? player.value.creds;
        player.value.techPoints = res.data.tech_points  ?? player.value.techPoints;

        // Recompute cache when CPU or RAM changes
        if (stat === 'cpu' || stat === 'ram') {
            player.value.maxCache = rig.value.cpu + rig.value.ram;
        }
    } catch (e) {
        console.error('[STATS] Upgrade failed:', e?.response?.data?.message ?? e.message);
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
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.store-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.2);
    background: rgba(255, 179, 0, 0.02);
    flex-shrink: 0;
}

.store-brand {
    display: flex;
    align-items: baseline;
    gap: 12px;
}

.store-logo {
    font-size: 18px;
    color: #FFB300;
    letter-spacing: 0.12em;
    text-shadow: 0 0 14px rgba(255, 179, 0, 0.4);
}

.store-tagline {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.07em;
}

.store-balances {
    display: flex;
    align-items: center;
    gap: 16px;
}

.bal-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.bal-label {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.1em;
}

.bal-value {
    font-size: 13px;
    color: #FFB300;
    letter-spacing: 0.06em;
}

.bal-tp {
    color: rgba(125, 249, 255, 0.9);
}

.bal-sep {
    width: 1px;
    height: 16px;
    background: rgba(255, 179, 0, 0.12);
}

/* ── Banking strip ────────────────────────────────────────────────────────── */
.bank-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 8px 24px;
    background: rgba(0, 255, 136, 0.02);
    border-bottom: 1px solid rgba(0, 255, 136, 0.08);
    flex-shrink: 0;
    transition: background 0.2s, border-color 0.2s;
}
.bank-strip--hot {
    background: rgba(0, 255, 136, 0.04);
    border-bottom-color: rgba(0, 255, 136, 0.2);
}
.bank-strip-left {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}
.bank-label {
    font-size: 8px;
    color: rgba(0, 255, 136, 0.35);
    letter-spacing: 0.14em;
}
.bank-pocket {
    font-size: 13px;
    letter-spacing: 0.06em;
}
.pocket--hot   { color: #00FF88; text-shadow: 0 0 8px rgba(0, 255, 136, 0.4); }
.pocket--empty { color: rgba(0, 255, 136, 0.2); }
.bank-risk {
    font-size: 7px;
    color: rgba(255, 179, 0, 0.7);
    border: 1px solid rgba(255, 179, 0, 0.3);
    padding: 1px 6px;
    letter-spacing: 0.1em;
    animation: risk-pulse 1.5s ease-in-out infinite;
}
@keyframes risk-pulse { 0%,100%{opacity:1} 50%{opacity:.45} }

.bank-btn {
    background: transparent;
    border: 1px solid rgba(0, 255, 136, 0.25);
    color: rgba(0, 255, 136, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 5px 14px;
    cursor: pointer;
    transition: all 0.12s;
    flex-shrink: 0;
}
.bank-btn:hover:not(:disabled) {
    background: rgba(0, 255, 136, 0.07);
    border-color: rgba(0, 255, 136, 0.6);
    color: #00FF88;
}
.bank-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.bank-confirm {
    font-size: 8px;
    color: #00FF88;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}
.bank-confirm-enter-active, .bank-confirm-leave-active { transition: opacity 0.3s; }
.bank-confirm-enter-from, .bank-confirm-leave-to       { opacity: 0; }

/* ── Cache flush strip ────────────────────────────────────────────────────── */
.flush-strip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border-bottom: 1px solid rgba(0, 200, 255, 0.1);
    background: transparent;
    transition: background 0.2s, border-color 0.2s;
}
.flush-strip--hot {
    background: rgba(0, 200, 255, 0.03);
    border-bottom-color: rgba(0, 200, 255, 0.15);
}
.flush-strip-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}
.flush-label {
    font-size: 8px;
    color: rgba(0, 200, 255, 0.5);
    letter-spacing: 0.12em;
    flex-shrink: 0;
}
.flush-cache {
    font-size: 10px;
    letter-spacing: 0.08em;
    flex-shrink: 0;
}
.cache--ok   { color: rgba(0, 200, 255, 0.7); }
.cache--full { color: #FF3333; animation: risk-pulse 1s ease-in-out infinite; }
.flush-warn {
    font-size: 8px;
    color: #FF3333;
    letter-spacing: 0.1em;
}
.flush-cooldown {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.7);
    letter-spacing: 0.1em;
}
.flush-btn {
    background: transparent;
    border: 1px solid rgba(0, 200, 255, 0.25);
    color: rgba(0, 200, 255, 0.7);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 5px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.flush-btn:hover:not(:disabled) {
    background: rgba(0, 200, 255, 0.07);
    border-color: rgba(0, 200, 255, 0.6);
    color: #00C8FF;
}
.flush-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}
.flush-confirm {
    font-size: 8px;
    color: #00C8FF;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

/* ── Category bar ─────────────────────────────────────────────────────────── */
.store-category-bar {
    display: flex;
    border-bottom: 1px solid rgba(255, 179, 0, 0.1);
    flex-shrink: 0;
}

.cat-btn {
    padding: 9px 20px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(255, 179, 0, 0.06);
    color: rgba(255, 179, 0, 0.35);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: color 0.15s, background 0.15s;
    white-space: nowrap;
}
.cat-btn:hover  { color: rgba(255, 179, 0, 0.7); background: rgba(255, 179, 0, 0.03); }
.cat-btn.active { color: #FFB300; background: rgba(255, 179, 0, 0.05); border-bottom: 2px solid #FFB300; }

/* ═══════════════════════════════════════════════════════════════════════════
   RIGS SHOP
   ═══════════════════════════════════════════════════════════════════════════ */

.rigs-shop {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px 32px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.rigs-shop::-webkit-scrollbar       { width: 3px; }
.rigs-shop::-webkit-scrollbar-track { background: transparent; }
.rigs-shop::-webkit-scrollbar-thumb { background: rgba(255,179,0,0.12); }

/* ── Current chassis block ──────────────────────────────────────────────── */
.chassis-current {
    border: 1px solid rgba(255, 179, 0, 0.2);
    background: rgba(255, 179, 0, 0.025);
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.chassis-current-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.cc-label {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.16em;
}

.cc-equipped {
    font-size: 7px;
    color: #00FF88;
    border: 1px solid rgba(0, 255, 136, 0.3);
    padding: 1px 8px;
    letter-spacing: 0.12em;
}

.cc-identity {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cc-tier {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.6);
    border: 1px solid rgba(255, 179, 0, 0.25);
    padding: 2px 7px;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.cc-name {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.9);
    letter-spacing: 0.08em;
}

.cc-ver {
    font-size: 13px;
    color: rgba(255, 179, 0, 0.7);
    letter-spacing: 0.06em;
}

/* Stat pip display */
.cc-stats {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.cc-stat {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cc-stat-label {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.1em;
    width: 26px;
    flex-shrink: 0;
}

.cc-stat-pips {
    display: flex;
    gap: 3px;
    flex: 1;
}

.cc-pip {
    display: inline-block;
    width: 10px;
    height: 10px;
    border: 1px solid rgba(255, 179, 0, 0.1);
}

.cc-pip--base     { background: rgba(255, 179, 0, 0.25); border-color: rgba(255, 179, 0, 0.3); }
.cc-pip--invested { background: rgba(0, 255, 136, 0.35); border-color: rgba(0, 255, 136, 0.45); }
.cc-pip--empty    { background: transparent; }

.cc-stat-val {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.6);
    letter-spacing: 0.06em;
    width: 20px;
    text-align: right;
    flex-shrink: 0;
}

/* Footer — progress + ports */
.cc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 179, 0, 0.07);
    flex-wrap: wrap;
}

.cc-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.cc-prog-label {
    font-size: 7px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.12em;
    flex-shrink: 0;
}

.cc-prog-bar {
    display: flex;
    gap: 2px;
}

.cc-prog-pip {
    display: inline-block;
    width: 14px;
    height: 6px;
    background: rgba(255, 179, 0, 0.07);
    border: 1px solid rgba(255, 179, 0, 0.12);
}

.cc-prog-pip--lit {
    background: rgba(255, 179, 0, 0.5);
    border-color: rgba(255, 179, 0, 0.65);
    box-shadow: 0 0 4px rgba(255, 179, 0, 0.2);
}

.cc-prog-count {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.5);
    letter-spacing: 0.08em;
    flex-shrink: 0;
}

.cc-prog-sub {
    font-size: 8px;
    color: #00FF88;
    letter-spacing: 0.1em;
    flex-shrink: 0;
}

.cc-ports {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.cc-ports-label {
    font-size: 7px;
    color: rgba(255, 179, 0, 0.3);
    letter-spacing: 0.12em;
}

.cc-ports-val {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.55);
    letter-spacing: 0.06em;
}

/* ── Available chassis section label ────────────────────────────────────── */
.rigs-section-label {
    display: flex;
    align-items: baseline;
    gap: 14px;
}

.rigs-section-label > span:first-child {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.5);
    letter-spacing: 0.16em;
}

.rigs-section-sub {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.04em;
}

/* ── Lock notice ────────────────────────────────────────────────────────── */
.chassis-lock-bar {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    background: rgba(255, 179, 0, 0.03);
    border: 1px solid rgba(255, 179, 0, 0.1);
}

.lock-icon {
    font-size: 12px;
    flex-shrink: 0;
    margin-top: 1px;
}

.lock-text {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.35);
    letter-spacing: 0.04em;
    line-height: 1.7;
}

.lock-text strong {
    color: rgba(255, 179, 0, 0.7);
    font-weight: normal;
}

/* ── Chassis card grid ──────────────────────────────────────────────────── */
.chassis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 14px;
}

.chassis-card {
    border: 1px solid rgba(255, 179, 0, 0.18);
    background: rgba(255, 179, 0, 0.015);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: border-color 0.15s, background 0.15s;
}

.chassis-card:not(.chassis-card--locked):hover {
    border-color: rgba(255, 179, 0, 0.4);
    background: rgba(255, 179, 0, 0.03);
}

.chassis-card--locked {
    opacity: 0.45;
    pointer-events: none;
}

.chassis-card--unavail {
    opacity: 0.25;
}

/* Card header */
.ccard-header {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ccard-tier {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.55);
    border: 1px solid rgba(255, 179, 0, 0.2);
    padding: 1px 6px;
    letter-spacing: 0.1em;
}

.ccard-brand {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.14em;
    flex: 1;
}

.ccard-build {
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 1px 6px;
    border: 1px solid;
}

.build--ghost   { color: rgba(125, 249, 255, 0.8); border-color: rgba(125, 249, 255, 0.3); }
.build--breaker { color: rgba(255, 69, 69, 0.8);  border-color: rgba(255, 69, 69, 0.3); }
.build--vault   { color: rgba(0, 255, 136, 0.8);   border-color: rgba(0, 255, 136, 0.3); }

/* Model + name */
.ccard-model {
    font-size: 10px;
    color: rgba(255, 179, 0, 0.5);
    letter-spacing: 0.14em;
}

.ccard-name {
    font-size: 18px;
    color: rgba(255, 255, 255, 0.9);
    letter-spacing: 0.08em;
    line-height: 1;
}

.ccard-tagline {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.25);
    letter-spacing: 0.04em;
    line-height: 1.6;
    font-style: italic;
    border-left: 2px solid rgba(255, 179, 0, 0.12);
    padding-left: 8px;
}

/* Stats */
.ccard-stats {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 0;
    border-top: 1px solid rgba(255, 179, 0, 0.07);
    border-bottom: 1px solid rgba(255, 179, 0, 0.07);
}

.ccard-stat-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ccard-stat-key {
    font-size: 7px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.1em;
    width: 24px;
    flex-shrink: 0;
}

.ccard-stat-bar {
    display: flex;
    gap: 2px;
    flex: 1;
}

.ccard-stat-pip {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: rgba(255, 179, 0, 0.07);
    border: 1px solid rgba(255, 179, 0, 0.1);
}

.cstat-pip--base   { background: rgba(255, 179, 0, 0.3); border-color: rgba(255, 179, 0, 0.4); }
.cstat-pip--uplink { background: rgba(0, 255, 255, 0.25); border-color: rgba(0, 255, 255, 0.3); }

.ccard-stat-val {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.65);
    letter-spacing: 0.06em;
    width: 16px;
    text-align: right;
    flex-shrink: 0;
}

.ccard-stat-cap {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.ccard-stat-cap--locked {
    font-size: 9px;
}

/* Meta */
.ccard-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ccard-meta-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ccard-meta-key {
    font-size: 7px;
    color: rgba(255, 179, 0, 0.3);
    letter-spacing: 0.1em;
    width: 80px;
    flex-shrink: 0;
}

.ccard-meta-val {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.55);
    letter-spacing: 0.05em;
}

/* Footer */
.ccard-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 179, 0, 0.07);
}

.ccard-price {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.ccard-price-creds {
    font-size: 13px;
    color: #FFB300;
    letter-spacing: 0.06em;
}

.ccard-price-sep {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.2);
}

.ccard-price-tp {
    font-size: 11px;
    color: rgba(125, 249, 255, 0.8);
    letter-spacing: 0.06em;
}

.ccard-buy-btn {
    background: rgba(255, 179, 0, 0.08);
    border: 1px solid rgba(255, 179, 0, 0.4);
    color: #FFB300;
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 5px 14px;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s, box-shadow 0.12s;
}
.ccard-buy-btn:hover:not(:disabled) {
    background: rgba(255, 179, 0, 0.16);
    border-color: #FFB300;
    box-shadow: 0 0 10px rgba(255, 179, 0, 0.2);
}
.ccard-buy-btn:disabled { opacity: 0.25; cursor: not-allowed; }

/* ═══════════════════════════════════════════════════════════════════════════
   STAT UPGRADES
   ═══════════════════════════════════════════════════════════════════════════ */

.stat-shop {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px 32px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.stat-shop::-webkit-scrollbar       { width: 3px; }
.stat-shop::-webkit-scrollbar-track { background: transparent; }
.stat-shop::-webkit-scrollbar-thumb { background: rgba(255,179,0,0.12); }

.stat-shop-intro {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,179,0,0.08);
}
.stat-intro-label {
    font-size: 9px;
    color: rgba(255,179,0,0.55);
    letter-spacing: 0.14em;
}
.stat-intro-sub {
    font-size: 8px;
    color: rgba(255,255,255,0.28);
    letter-spacing: 0.04em;
    line-height: 1.7;
    max-width: 560px;
}

/* Global progression indicator */
.stat-global-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: rgba(255,179,0,0.03);
    border: 1px solid rgba(255,179,0,0.08);
}
.sg-label {
    font-size: 8px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.1em;
    flex-shrink: 0;
}
.sg-pips { display: flex; gap: 3px; }
.sg-pip {
    width: 8px;
    height: 8px;
    background: rgba(255,179,0,0.08);
    border: 1px solid rgba(255,179,0,0.15);
}
.sg-pip--lit {
    background: rgba(255,179,0,0.45);
    border-color: rgba(255,179,0,0.6);
    box-shadow: 0 0 4px rgba(255,179,0,0.2);
}
.sg-count {
    font-size: 8px;
    color: rgba(255,179,0,0.35);
    letter-spacing: 0.06em;
    margin-left: 4px;
}

/* Stat list */
.stat-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-upgrade-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid rgba(255,179,0,0.1);
    background: rgba(255,179,0,0.015);
    transition: border-color 0.12s;
    flex-wrap: wrap;
}
.stat-upgrade-row:hover:not(.stat-row--maxed) { border-color: rgba(255,179,0,0.28); }
.stat-row--maxed { opacity: 0.4; }

.su-identity {
    display: flex;
    flex-direction: column;
    gap: 3px;
    width: 110px;
    flex-shrink: 0;
}
.su-name {
    font-size: 11px;
    color: rgba(255,255,255,0.8);
    letter-spacing: 0.1em;
}
.su-desc {
    font-size: 7px;
    color: rgba(255,255,255,0.28);
    letter-spacing: 0.03em;
    line-height: 1.6;
}

/* Value pips */
.su-bar-wrap {
    display: flex;
    align-items: center;
    gap: 3px;
    flex: 1;
    min-width: 120px;
}
.su-bar-pip {
    width: 12px;
    height: 12px;
    border: 1px solid rgba(255,179,0,0.12);
    flex-shrink: 0;
}
.pip--base     { background: rgba(255,179,0,0.25); border-color: rgba(255,179,0,0.3); }
.pip--invested { background: rgba(0,255,136,0.35); border-color: rgba(0,255,136,0.4); box-shadow: 0 0 4px rgba(0,255,136,0.15); }
.pip--empty    { background: transparent; }
.su-bar-label {
    font-size: 9px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.08em;
    margin-left: 6px;
    white-space: nowrap;
}

/* Cost */
.su-cost-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.su-next-label  { font-size: 7px; color: rgba(255,179,0,0.3); letter-spacing: 0.1em; }
.su-cost-creds  { font-size: 11px; color: #FFB300; letter-spacing: 0.06em; }
.su-cost-tp     { font-size: 10px; color: rgba(125,249,255,0.7); letter-spacing: 0.06em; }
.su-scale-warn  { font-size: 7px; color: rgba(255,69,69,0.6); letter-spacing: 0.06em; white-space: nowrap; }
.su-maxed       { font-size: 8px; color: rgba(0,255,136,0.45); letter-spacing: 0.12em; }
.su-os-gate     { font-size: 8px; color: rgba(255,179,0,0.5); letter-spacing: 0.1em; }

.su-btn {
    background: rgba(255,179,0,0.08);
    border: 1px solid rgba(255,179,0,0.35);
    color: #FFB300;
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.12em;
    padding: 5px 14px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, border-color 0.12s;
    margin-left: auto;
}
.su-btn:hover:not(:disabled) {
    background: rgba(255,179,0,0.16);
    border-color: #FFB300;
}
.su-btn:disabled { opacity: 0.25; cursor: not-allowed; }

/* Chassis maxed notice */
.stat-maxed-notice {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(0, 255, 136, 0.2);
    background: rgba(0, 255, 136, 0.03);
}

.maxed-icon {
    font-size: 16px;
    color: #00FF88;
    flex-shrink: 0;
    margin-top: 1px;
}

.maxed-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.maxed-title {
    font-size: 10px;
    color: #00FF88;
    letter-spacing: 0.12em;
}

.maxed-sub {
    font-size: 8px;
    color: rgba(0, 255, 136, 0.5);
    letter-spacing: 0.04em;
    line-height: 1.6;
}

/* Cooldown reset */
.stat-cooldown-section {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    border: 1px solid rgba(0,255,136,0.12);
    background: rgba(0,255,136,0.02);
    margin-top: 4px;
}
.scd-heading { font-size: 9px; color: rgba(0,255,136,0.6); letter-spacing: 0.12em; }
.scd-sub     { font-size: 8px; color: rgba(255,255,255,0.3); letter-spacing: 0.04em; }
.scd-btn {
    align-self: flex-start;
    background: transparent;
    border: 1px solid rgba(0,255,136,0.25);
    color: rgba(0,255,136,0.7);
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.12em;
    padding: 5px 14px;
    cursor: pointer;
    margin-top: 4px;
    transition: all 0.12s;
}
.scd-btn:hover { background: rgba(0,255,136,0.07); border-color: rgba(0,255,136,0.55); color: #00FF88; }

/* ═══════════════════════════════════════════════════════════════════════════
   COMMANDS SHOP
   ═══════════════════════════════════════════════════════════════════════════ */

.cmd-shop {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px 32px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.cmd-shop::-webkit-scrollbar       { width: 3px; }
.cmd-shop::-webkit-scrollbar-track { background: transparent; }
.cmd-shop::-webkit-scrollbar-thumb { background: rgba(255,179,0,0.12); }

.cmd-shop-empty {
    padding: 40px 0;
    text-align: center;
    font-size: 10px;
    color: rgba(0,255,136,0.4);
    letter-spacing: 0.14em;
}

.cmd-tier-group { display: flex; flex-direction: column; gap: 4px; }

.cmd-tier-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,179,0,0.08);
    margin-bottom: 4px;
}
.cmd-tier-label { font-size: 9px; color: rgba(255,179,0,0.5); letter-spacing: 0.16em; }
.cmd-tier-req   { font-size: 8px; color: rgba(255,179,0,0.25); letter-spacing: 0.1em; }

.cmd-shop-row {
    border: 1px solid rgba(255,179,0,0.1);
    background: rgba(255,179,0,0.01);
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color 0.12s;
}
.cmd-shop-row:hover           { border-color: rgba(255,179,0,0.25); }
.cmd-shop-row--locked         { opacity: 0.4; }

.cmd-shop-main {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cmd-shop-type {
    font-size: 7px; letter-spacing: 0.1em; padding: 1px 5px; border: 1px solid; flex-shrink: 0;
}
.shop-type--trap      { color: rgba(255,69,180,0.8);  border-color: rgba(255,69,180,0.3); }
.shop-type--stealth   { color: rgba(125,249,255,0.8); border-color: rgba(125,249,255,0.3); }
.shop-type--defensive { color: rgba(0,255,136,0.8);   border-color: rgba(0,255,136,0.3); }
.shop-type--offensive { color: rgba(255,69,69,0.9);   border-color: rgba(255,69,69,0.3); }

.cmd-shop-name  { font-size: 11px; color: rgba(255,255,255,0.8); letter-spacing: 0.06em; flex: 1; }

.cmd-shop-price { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }
.price-creds    { font-size: 10px; color: #FFB300; letter-spacing: 0.06em; }
.price-sep      { font-size: 8px;  color: rgba(255,255,255,0.25); }
.price-tp       { font-size: 10px; color: rgba(125,249,255,0.7); letter-spacing: 0.06em; }

.cmd-buy-btn {
    background: rgba(255,179,0,0.08);
    border: 1px solid rgba(255,179,0,0.4);
    color: #FFB300;
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, border-color 0.12s;
}
.cmd-buy-btn:hover:not(:disabled) {
    background: rgba(255,179,0,0.16);
    border-color: #FFB300;
}
.cmd-buy-btn:disabled { opacity: 0.25; cursor: not-allowed; }

.cmd-shop-effect {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.eff-key { font-size: 6px; color: rgba(255,179,0,0.25); letter-spacing: 0.12em; width: 28px; flex-shrink: 0; padding-top: 2px; }
.eff-val { font-size: 8px; color: rgba(255,255,255,0.35); letter-spacing: 0.03em; line-height: 1.6; }

.cmd-lock-notice {
    font-size: 7px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.06em;
    padding-top: 2px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ITEM GRID (hardware / software / repair / all)
   ═══════════════════════════════════════════════════════════════════════════ */

.store-grid {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
    align-content: start;
}

.store-item {
    border: 1px solid rgba(255, 179, 0, 0.12);
    background: rgba(255, 179, 0, 0.02);
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: border-color 0.15s, background 0.15s;
}
.store-item:hover {
    border-color: rgba(255, 179, 0, 0.3);
    background: rgba(255, 179, 0, 0.04);
}

.store-item--uncommon { border-color: rgba(125, 249, 255, 0.18); }
.store-item--uncommon:hover { border-color: rgba(125, 249, 255, 0.4); }
.store-item--rare     { border-color: rgba(255, 105, 180, 0.22); }
.store-item--rare:hover { border-color: rgba(255, 105, 180, 0.45); }

.item-rarity {
    font-size: 7px;
    letter-spacing: 0.14em;
}
.store-item--common   .item-rarity { color: rgba(255, 179, 0, 0.45); }
.store-item--uncommon .item-rarity { color: rgba(125, 249, 255, 0.6); }
.store-item--rare     .item-rarity { color: rgba(255, 105, 180, 0.7); }

.item-name {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.85);
    letter-spacing: 0.06em;
    line-height: 1.3;
}

.item-stat {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-key {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.1em;
    width: 64px;
}

.stat-val {
    font-size: 12px;
    color: #00FF88;
    letter-spacing: 0.06em;
}

.item-desc {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.35);
    letter-spacing: 0.04em;
    line-height: 1.65;
    flex: 1;
}

.item-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 179, 0, 0.08);
}

.item-price {
    font-size: 12px;
    color: #FFB300;
    letter-spacing: 0.06em;
}

.buy-btn {
    background: rgba(255, 179, 0, 0.08);
    border: 1px solid rgba(255, 179, 0, 0.4);
    color: #FFB300;
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 5px 12px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.buy-btn:hover:not(:disabled) {
    background: rgba(255, 179, 0, 0.16);
    border-color: #FFB300;
    box-shadow: 0 0 10px rgba(255, 179, 0, 0.2);
}
.buy-btn:disabled {
    opacity: 0.28;
    cursor: not-allowed;
}

.item-owned-badge {
    font-size: 7px;
    color: rgba(0, 255, 136, 0.7);
    border: 1px solid rgba(0, 255, 136, 0.25);
    padding: 3px 8px;
    letter-spacing: 0.08em;
}

.item-qty {
    font-size: 11px;
    color: rgba(0, 255, 136, 0.8);
    letter-spacing: 0.06em;
}

.use-btn {
    background: rgba(0, 255, 136, 0.08);
    border: 1px solid rgba(0, 255, 136, 0.35);
    color: rgba(0, 255, 136, 0.9);
    font-family: inherit;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 5px 10px;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
.use-btn:hover {
    background: rgba(0, 255, 136, 0.16);
    border-color: #00FF88;
}
</style>
