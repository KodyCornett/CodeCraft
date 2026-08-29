<template>
    <div class="dev-wrap">

        <div class="dev-header">
            <span class="dev-tag">[ DEV BUILD ]</span>
            <span class="dev-title">MINIGAME LAUNCHER</span>
            <span class="dev-sub">// Remove splice://dev/minigames from SpliceRouter.js before release</span>
        </div>

        <!-- Difficulty selector -->
        <div class="dev-diff-row">
            <span class="dev-diff-label">DIFFICULTY</span>
            <button
                v-for="d in [1, 2, 3]"
                :key="d"
                class="dev-diff-btn"
                :class="{ 'dev-diff-btn--active': selectedDiff === d }"
                @click="selectedDiff = d"
            >D{{ d }}</button>
            <span class="dev-diff-label" style="margin-left:16px">LOCKS REQ</span>
            <button
                v-for="n in [3, 5, 7, 10]"
                :key="n"
                class="dev-diff-btn"
                :class="{ 'dev-diff-btn--active': selectedLocks === n }"
                @click="selectedLocks = n"
            >{{ n }}</button>
        </div>

        <!-- Game list -->
        <div class="dev-games">
            <div
                v-for="game in GAMES"
                :key="game.type"
                class="dev-game"
                :class="{ 'dev-game--stub': !game.built }"
            >
                <div class="dev-game-header">
                    <span class="dev-game-type">{{ game.type.toUpperCase() }}</span>
                    <span class="dev-game-status" :class="game.built ? 'dev-status--built' : 'dev-status--stub'">
                        {{ game.built ? '[ BUILT ]' : '[ STUB ]' }}
                    </span>
                </div>
                <div class="dev-game-quest">{{ game.quest }}</div>
                <div class="dev-game-brief">{{ game.brief }}</div>
                <button
                    class="dev-launch-btn"
                    :disabled="!game.built"
                    @click="onLaunch(game)"
                >{{ game.built ? '[ LAUNCH ]' : '[ NOT YET BUILT ]' }}</button>
            </div>
        </div>

        <!-- Bank Heist — separate flow, not part of the quest-minigame skin system -->
        <div class="dev-header" style="margin-top: 4px;">
            <span class="dev-tag">[ DEV BUILD ]</span>
            <span class="dev-title">BANK HEIST — REAL ROSTER TARGETS</span>
            <span class="dev-sub">// Launches BankHeist.vue against a genuine bank Node — every server round-trip (gate1-failed / phase2-inject / phase2-extract) resolves for real, exactly like the live map trigger. Your actual rig stats (CPU/RAM/OS) and bounty multiplier apply, same as a real run.</span>
        </div>

        <div v-if="bankNodesLoading" class="dev-game-brief">Loading bank roster from /api/nodes…</div>
        <div v-else-if="bankNodesError" class="dev-game-brief" style="color: rgba(255,100,100,0.6);">Failed to load roster: {{ bankNodesError }}</div>

        <div v-else class="dev-games">
            <div v-for="bank in bankTargets" :key="bank.canvasId" class="dev-game">
                <div class="dev-game-header">
                    <span class="dev-game-type">{{ bank.name }}</span>
                    <span class="dev-game-status dev-status--built">TIER {{ bank.bankTier }} · ICE {{ bank.bankIce }}</span>
                </div>
                <div class="dev-game-quest">{{ bank.canvasId }}</div>
                <div class="dev-game-brief">
                    {{ bank.onCooldown ? `On cooldown until ${bank.cooldownLabel} — testable anyway, the server doesn't gate on this.` : 'No active cooldown.' }}
                </div>
                <button class="dev-launch-btn" @click="onLaunchBankHeist(bank)">[ TEST BANK HEIST ]</button>
            </div>
            <div v-if="!bankTargets.length" class="dev-game-brief">No bank-target nodes found — has the Bank Heist migration run?</div>
        </div>

        <!-- Signal Lock — candidate 4th node-hack pool template. NOT registered
             in generator/pool.js yet — same props/emits/reward-formula contract
             as GridBreach/ChecksumBreach/CipherBreach, so promoting it later is a
             one-line addition. Separate flow, not part of the quest-minigame skin
             system — mirrors the Bank Heist section above. -->
        <div class="dev-header" style="margin-top: 4px;">
            <span class="dev-tag">[ DEV BUILD ]</span>
            <span class="dev-title">SIGNAL LOCK — POOL-TEMPLATE CANDIDATE</span>
            <span class="dev-sub">// Reads a rule line, pick the candidate row that satisfies it. Parity rule unlocks at ICE 5+; a flagged decoy joins it at ICE 7+.</span>
        </div>

        <div class="dev-games">
            <div class="dev-game">
                <div class="dev-game-header">
                    <span class="dev-game-type">SIGNAL_LOCK</span>
                    <span class="dev-game-status dev-status--built">[ BUILT ]</span>
                </div>
                <div class="dev-game-quest">CANDIDATE — ICE {{ sglIce }} / RIG: {{ sglRig.label }}</div>
                <div class="dev-game-brief">
                    Read the objective rule at the top, then pick (click or press 1&ndash;N) the one
                    candidate row that actually satisfies it &mdash; the rest fail on at least one field.
                </div>
                <div class="dev-diff-row" style="margin: 4px 0;">
                    <span class="dev-diff-label">ICE</span>
                    <button
                        v-for="ice in [3, 4, 5, 6, 7, 8, 9, 10]"
                        :key="ice"
                        class="dev-diff-btn"
                        :class="{ 'dev-diff-btn--active': sglIce === ice }"
                        @click="sglIce = ice"
                    >{{ ice }}</button>
                </div>
                <div class="dev-diff-row" style="margin: 4px 0 8px;">
                    <span class="dev-diff-label">RIG</span>
                    <button
                        v-for="preset in SGL_RIG_PRESETS"
                        :key="preset.label"
                        class="dev-diff-btn"
                        :class="{ 'dev-diff-btn--active': sglRig.label === preset.label }"
                        @click="sglRig = preset"
                    >{{ preset.label }}</button>
                </div>
                <button class="dev-launch-btn" @click="onLaunchSignalLock">[ LAUNCH SIGNAL LOCK ]</button>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useQuestMinigame } from '@/composables/useQuestMinigame.js';
import { useDevBankHeist } from '@/composables/useDevBankHeist.js';
import { useDevSignalLock } from '@/composables/useDevSignalLock.js';
import { useMapData } from '@/composables/useMapData.js';
import { getBankTargetNetworkName } from '@/composables/businessNodes.js';

const { launch } = useQuestMinigame();
const { launch: launchDevBankHeist } = useDevBankHeist();
const { launch: launchSignalLock } = useDevSignalLock();

// ── Bank Heist roster — DEV ONLY ─────────────────────────────────────────────
// A fresh, independent useMapData() instance (not Game.vue's) — plain GET
// /api/nodes, filtered to the 19 fixed is_bank_target rows. Only used to
// populate this list; BankHeist.vue itself is driven entirely by Game.vue's
// own activeBankHeist ref via useDevBankHeist's launch() below.
const mapData          = useMapData();
const bankNodesLoading = ref(true);
const bankNodesError   = ref(null);

const bankTargets = computed(() =>
    mapData.nodes.value
        .filter(n => n.isBankTarget)
        .map(n => ({
            canvasId:      n.canvasId,
            name:          getBankTargetNetworkName(n.canvasId) ?? 'UNKNOWN TARGET',
            bankTier:      n.bankTier ?? 1,
            bankIce:       n.bankIce ?? 3,
            onCooldown:    !!n.bankCooldownUntil && new Date(n.bankCooldownUntil).getTime() > Date.now(),
            cooldownLabel: n.bankCooldownUntil ? new Date(n.bankCooldownUntil).toLocaleTimeString() : null,
        }))
        .sort((a, b) => a.bankTier - b.bankTier || a.name.localeCompare(b.name))
);

function onLaunchBankHeist(bank) {
    launchDevBankHeist({
        canvasId: bank.canvasId,
        bankName: bank.name,
        bankIce:  bank.bankIce,
        bankTier: bank.bankTier,
    });
}

onMounted(async () => {
    bankNodesLoading.value = true;
    await mapData.fetchAll();
    bankNodesError.value   = mapData.error.value;
    bankNodesLoading.value = false;
});

const selectedDiff  = ref(1);
const selectedLocks = ref(5);

const GAMES = [
    {
        type:  'disconnect_layer',
        built: true,
        quest: 'Quest 1 — Knuckle / Browne\'s Addition',
        brief: 'Select a path across the grid whose values sum to the governor target. Three attempts.',
        skin: {
            primaryBarLabel: 'TRACE',
            stabilityLabel:  'SYSTEM HEAT',
            objectiveText:   'Sever the governor chain before it reroutes.',
            fileName:        'BA-V14.sys',
            hideBars:        true,
            timeLimit:       90,
        },
    },
    {
        type:  'flush_buffer',
        built: true,
        quest: 'Quest 2 — Veil / Downtown',
        brief: 'Capture and forensically analyse incoming signals. Flush anomalous packets before the buffer overflows.',
        skin: {
            primaryBarLabel: 'ICE TRACE',
            stabilityLabel:  'STABILITY',
            objectiveText:   'Identify and flush ghost signals. Stability collapses if the buffer floods.',
            fileName:        'DT-V8.sys',
            hideBars:        false,
            timeLimit:       180,
        },
    },
    {
        type:  'cipher_lock',
        built: true,
        quest: 'Quest 3 — Float / Spokane Valley',
        brief: 'Decrypt the phrase by typing each letter\'s code from the legend. Wrong codes cost 15s. Edit skin.iceLevel below (3-8) to test different tiers.',
        skin: {
            primaryBarLabel: 'DECRYPTED',
            stabilityLabel:  'STABILITY',
            objectiveText:   'Crack every letter in the phrase before the timer runs out.',
            fileName:        'SV-V9.sys',
            hideBars:        true,
            timeLimit:       240,
            iceLevel:        5, // change this (3-8) to test different ICE tiers
        },
    },
    {
        type:  'archive_extraction',
        built: true,
        quest: 'Codex System — free-roam find (not a story mission)',
        brief: 'No longer tied to a Doc\'s job — this is the Codex system\'s dedicated discovery minigame, offered via a random "Codex — Found" prompt after routine node hacks while a codex thread is active (see useCodexFind.js). No stakes on a loss. Read scattered log files to find plaintext/Base64 pairs, match each to a cipher slot, and decode all 3 before Trace hits 100%. Watch for decoy pairs and corrupted checksums.',
        skin: {
            primaryBarLabel: 'TRACE',
            stabilityLabel:  'STABILITY',
            objectiveText:   'Extract the fragment before the trace resolves.',
            fileName:        'UNTRACED_FRAGMENT.sys',
            hideBars:        false,
            timeLimit:       110, // cosmetic only — the component computes its own trace-duration budget (95-150s) internally
        },
    },
    {
        type:  'calibration_tether',
        built: true,
        quest: 'Quest 5 — Patch / North Spokane',
        brief: 'Tether volatile sub-routines and nudge each one\'s calibration back into band before it destabilizes. Route stabilized ones to the drop-box. Two lapses close together cascade into a bigger hit.',
        skin: {
            primaryBarLabel: 'PAYLOAD',
            stabilityLabel:  'INTEGRITY',
            objectiveText:   'Deliver the sub-routines. Do not let the chain cascade.',
            fileName:        'NS-V13.sys',
            hideBars:        false,
            timeLimit:       100, // cosmetic only — the component computes its own duration budget (90-110s) internally
        },
    },
];

function onLaunch(game) {
    const skin = {
        gameType:          game.type,
        fileName:          game.skin.fileName,
        nodeCanvasId:      null,
        objectiveText:     game.skin.objectiveText,
        successText:       'Objective complete. Disconnecting.',
        failText:          'Connection lost.',
        primaryBarLabel:   game.skin.primaryBarLabel,
        stabilityLabel:    game.skin.stabilityLabel,
        timeLimit:         game.skin.timeLimit,
        hideBars:          game.skin.hideBars,
        dealsDamageOnFail: false,
        difficulty:        selectedDiff.value,
        locksRequired:     selectedLocks.value,
        // Games that care about ICE tier (e.g. cipher_lock) can
        // set skin.iceLevel directly in their GAMES entry above to override
        // the shared D1/D2/D3 difficulty selector, since ICE runs 3-8.
        iceLevel:          game.skin.iceLevel ?? selectedDiff.value,
    };
    launch('dev', game.type, skin);
}

// ── Signal Lock — DEV ONLY, candidate pool-template testing ─────────────────
const SGL_RIG_PRESETS = [
    { label: 'STARTER',  cpu: 3, ram: 2, os: 2 },
    { label: 'MID-TIER', cpu: 5, ram: 4, os: 4 },
    { label: 'HIGH-SEC', cpu: 8, ram: 6, os: 6 },
];
const sglIce = ref(3);
const sglRig = ref(SGL_RIG_PRESETS[0]);

function onLaunchSignalLock() {
    launchSignalLock({ ice: sglIce.value, cpu: sglRig.value.cpu, ram: sglRig.value.ram, os: sglRig.value.os });
}
</script>

<style scoped>
.dev-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

/* ── Header ──────────────────────────────────────────────────────────────── */

.dev-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dev-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dev-title { font-size: 14px; color: #00ff9d; letter-spacing: 0.15em; font-weight: 700; }
.dev-sub   { font-size: 8px;  color: rgba(0,255,100,0.2); letter-spacing: 0.1em; }

/* ── Difficulty row ──────────────────────────────────────────────────────── */

.dev-diff-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dev-diff-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
    margin-right: 4px;
}

.dev-diff-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.4);
    padding: 5px 14px;
    cursor: pointer;
    transition: all 0.1s;
}

.dev-diff-btn:hover {
    border-color: rgba(0,255,100,0.5);
    color: rgba(0,255,100,0.7);
}

.dev-diff-btn--active {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,255,100,0.06);
}

/* ── Game cards ──────────────────────────────────────────────────────────── */

.dev-games {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dev-game {
    border: 1px solid rgba(0,255,100,0.15);
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dev-game--stub {
    border-color: rgba(0,255,100,0.07);
    opacity: 0.5;
}

.dev-game-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dev-game-type   { font-size: 11px; font-weight: 700; color: #00ff9d; letter-spacing: 0.1em; }

.dev-game-status { font-size: 8px; letter-spacing: 0.15em; }
.dev-status--built { color: rgba(0,255,100,0.5); }
.dev-status--stub  { color: rgba(255,100,100,0.4); }

.dev-game-quest { font-size: 8px; color: rgba(0,255,100,0.3); letter-spacing: 0.1em; }
.dev-game-brief { font-size: 9px; color: rgba(0,255,100,0.55); line-height: 1.5; }

.dev-launch-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 7px 0;
    cursor: pointer;
    transition: all 0.1s;
    margin-top: 4px;
}

.dev-launch-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
}

.dev-launch-btn:disabled {
    border-color: rgba(0,255,100,0.08);
    color: rgba(0,255,100,0.2);
    cursor: not-allowed;
}
</style>
