
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
