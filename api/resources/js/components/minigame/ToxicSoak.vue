<template>
    <QuestMinigameChrome v-bind="chrome">

        <!-- ═══════════════════════════════════════════════════════════════════
             Root canvas — fixed 1920 × 1080, CSS Grid
             Columns : 350px | 1fr | 400px
             Rows    : 1fr   | 220px
        ════════════════════════════════════════════════════════════════════ -->
        <div class="ts-canvas">

            <!-- ── Grid overlay (decorative) ─────────────────────────────── -->
            <div class="ts-grid-bg" aria-hidden="true"/>

            <!-- ┌─────────────────────────────────────────────────────────────
                 │  CELL A — Scan panel + System noise   col 1 · row 1
                 └───────────────────────────────────────────────────────── -->
            <div class="ts-cell ts-cell--scan ts-cell--scan-layout">

                <!-- ScanPanel — visible only when a fragment is being scanned -->
                <Transition name="ts-scan-slide">
                    <ScanPanel
                        v-if="scanOpen !== null"
                        :fragment="activeScanFragment"
                        :fragment-index="scanOpen"
                        :open-file-idx="openFileIdx"
                        :unlocked-idxs="unlockedIdxs"
                        :file-content="currentFileContent"
                        :lock-cost-pct="lockCostPct"
                        @file-click="openFile"
                        @close="scanOpen = null"
                    />
                </Transition>

                <!-- SystemNoise — always running; shrinks when scan is open -->
                <SystemNoise :compressed="scanOpen !== null" />

            </div>

            <!-- ┌─────────────────────────────────────────────────────────────
                 │  CELL B — Fragment canvas        col 2 · row 1
                 └───────────────────────────────────────────────────────── -->
            <div class="ts-cell ts-cell--frags ts-cell--frags-flush">
                <FragmentCanvas
                    :fragments="fragments"
                    :slots="slots"
                    :solved-frags="solvedFrags"
                    :scan-open="scanOpen"
                    :selected-pool-id="selectedPoolId"
                    @slot-click="onSlotClick"
                    @scan-click="openScan"
                    @inject-click="inject"
                />
            </div>

            <!-- ┌─────────────────────────────────────────────────────────────
                 │  CELL C — Status monitor         col 3 · row 1
                 └───────────────────────────────────────────────────────── -->
            <div class="ts-cell ts-cell--status">
                <StatusMonitor
                    :stability="stability"
                    :trace-level="traceLevel"
                    :reputation="5"
                    :solved-frags="solvedFrags"
                />
            </div>

            <!-- ┌─────────────────────────────────────────────────────────────
                 │  CELL D — Signal pool            col 1-3 · row 2
                 └───────────────────────────────────────────────────────── -->
            <div class="ts-cell ts-cell--pool ts-cell--pool-layout">

                <!-- Pool control strip: ping status + command input -->
                <div class="ts-pool-ctrl">
                    <span
                        class="ts-ping-status"
                        :class="canPing ? 'ts-ping--ready' : 'ts-ping--cool'"
                    >
                        [PING: {{ canPing ? 'READY' : `RECHARGING (${pingCooldown}s)` }}]
                    </span>
                    <div class="ts-cmd-bar">
                        <span class="ts-cmd-prompt">></span>
                        <input
                            v-model="commandInput"
                            class="ts-cmd-input"
                            placeholder="ping --noise"
                            spellcheck="false"
                            autocomplete="off"
                            @keydown.enter="parseCommand"
                        />
                    </div>
                </div>

                <SignalPool
                    :letters="poolLetters"
                    :selected-pool-id="selectedPoolId"
                    :ping-active="pingActive"
                    @pool-select="onPoolSelect"
                    @pool-cancel="selectedPoolId = null"
                />

            </div>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed } from 'vue';
import { onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import StatusMonitor from './StatusMonitor.vue';
import FragmentCanvas from './FragmentCanvas.vue';
import SignalPool from './SignalPool.vue';
import ScanPanel from './ScanPanel.vue';
import SystemNoise from './SystemNoise.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

// ── Acrostic word bank ─────────────────────────────────────────────────────────
// Used to generate acrostic sentences. Each entry starts with the keyed letter.

const ACROSTIC_WORDS = {
    A: ['Access', 'Archive', 'Anomaly', 'Authentication', 'Active', 'Alert', 'Asymmetric'],
    B: ['Buffer', 'Breach', 'Bandwidth', 'Backdoor', 'Broadcast', 'Blacklisted'],
    C: ['Cache', 'Channel', 'Cipher', 'Connection', 'Corrupted', 'Credentials', 'Cascade'],
    D: ['Data', 'Daemon', 'Disconnect', 'Diagnostic', 'Downlink', 'Delayed', 'Dumped'],
    E: ['Encrypted', 'Error', 'Endpoint', 'Exfiltrated', 'Expired', 'Evaded', 'Erased'],
    F: ['Firewall', 'Forensic', 'Frequency', 'Flagged', 'Filtered', 'Forged'],
    G: ['Gateway', 'Ghost', 'Grid', 'Granted', 'Garbled', 'Grounded'],
    H: ['Hardware', 'Hash', 'Handshake', 'Hidden', 'Hijacked', 'Hardened'],
    I: ['Identity', 'Injection', 'Intercepted', 'Invisible', 'Inactive', 'Integrity'],
    J: ['Junction', 'Junk', 'Jammed', 'Jailbroken'],
    K: ['Key', 'Kernel', 'Known', 'Killed'],
    L: ['Latency', 'Log', 'Legacy', 'Leaked', 'Locked', 'Loopback'],
    M: ['Memory', 'Masked', 'Monitor', 'Modified', 'Mirrored', 'Mapped'],
    N: ['Node', 'Network', 'Null', 'Noise', 'Nested', 'Narrowband'],
    O: ['Offline', 'Overflow', 'Origin', 'Outbound', 'Obfuscated', 'Orphaned'],
    P: ['Packet', 'Protocol', 'Purged', 'Patched', 'Persistent', 'Proxied'],
    Q: ['Queue', 'Query', 'Quarantined', 'Queued'],
    R: ['Router', 'Remote', 'Residual', 'Rejected', 'Routed', 'Redirected'],
    S: ['Signal', 'Session', 'Sector', 'Spoofed', 'Suppressed', 'Staged'],
    T: ['Trace', 'Token', 'Traffic', 'Terminated', 'Throttled', 'Tunnelled'],
    U: ['Unauthorized', 'Unverified', 'Upstream', 'Unstable', 'Unlisted'],
    V: ['Vector', 'Voiding', 'Validated', 'Volatile', 'Vectored'],
    W: ['Watchdog', 'Write', 'Wiped', 'Wireless', 'Weakened'],
    X: ['X-sector', 'Xor-masked', 'Expired'],
    Y: ['Years-old', 'Yielding', 'Yoked'],
    Z: ['Zero-day', 'Zone', 'Zeroed'],
};

// ── Codename parts ─────────────────────────────────────────────────────────────

const CODENAME_PRE = ['ACCESS', 'SYSTEM', 'GHOST', 'SIGNAL', 'VECTOR', 'CIPHER', 'NEURAL', 'PROXY', 'SECTOR', 'DAEMON', 'KERNEL', 'SOCKET'];
const CODENAME_SUF = ['DELTA', 'HEARTBEAT', 'IDENTITY', 'PROTOCOL', 'GATEWAY', 'SEQUENCE', 'FREQUENCY', 'ARCHIVE', 'MANIFEST', 'LATTICE', 'FRAGMENT'];

// ── Noise characters ───────────────────────────────────────────────────────────

const NOISE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ[]{}|\\'.split('');

// ── Word clusters ──────────────────────────────────────────────────────────────

const CLUSTERS = [
    { words: ['VOID', 'NULL', 'GONE', 'WIPE'],   hints: ["What the corporation leaves in an emptied archive.", "The state after a hard deletion.", "What ICE does to a runner's footprint.", "A field that was never meant to hold anything."] },
    { words: ['ECHO', 'PING', 'WAVE', 'PULSE'],   hints: ["What SPLICE runs on beneath everything else.", "The heartbeat of a channel that should be dead.", "A transmission with no source on record.", "What you send when no one is listening."] },
    { words: ['GHOST', 'MASK', 'ALIAS', 'TRACE'],  hints: ["What a runner leaves in a system they never entered.", "The layer between a person and their credential.", "What corporations pay to erase from their logs.", "Everything you are to a system that doesn't know you."] },
    { words: ['NODE', 'LINK', 'BRIDGE', 'SPLICE'], hints: ["Where things that shouldn't move get routed anyway.", "The connection that isn't supposed to exist.", "Infrastructure that outlived the org that built it.", "What the city runs on beneath the streets."] },
    { words: ['RUST', 'BLEED', 'DECAY', 'FAULT'],  hints: ["What happens to systems no one maintains.", "A server left running in an abandoned building.", "What time does to encryption that was never updated.", "Entropy, made visible in the access logs."] },
    { words: ['CACHE', 'STORE', 'KEEP', 'HOLD'],   hints: ["Where everything critical lives before it disappears.", "What persists after the source has been deleted.", "The part of the system that doesn't know how to forget.", "Where the important things go before the purge cycle runs."] },
    { words: ['DARK', 'SHADE', 'VEIL', 'CLOAK'],   hints: ["The frequency the monitored channels can't see.", "What a runner becomes when they stop broadcasting.", "The state between visible and gone.", "What separates a runner who gets out from one who doesn't."] },
    { words: ['DATA', 'CODE', 'BYTE', 'HASH'],     hints: ["The raw material of every secret ever kept.", "What survives every wipe, every migration.", "The substance beneath the interface.", "What the corporation is actually selling."] },
    { words: ['GATE', 'DOOR', 'LOCK', 'ENTRY'],    hints: ["What every system has, even ones built to keep you out.", "The point where you stop being outside.", "What separates the permitted from everyone else.", "The opening that was never meant to be found."] },
    { words: ['BURN', 'HARM', 'KILL', 'BLAZE'],    hints: ["What ICE does when a trace finally resolves.", "The last option when extraction has already failed.", "What remains after the response team arrives.", "The outcome you were hoping to avoid triggering."] },
    { words: ['RISE', 'BREAK', 'REBEL', 'SURGE'],  hints: ["What happens after enough pressure has been applied.", "The runner's answer to a node that won't open.", "What the underground does with nothing left to lose.", "The direction everything tends to move in eventually."] },
    { words: ['FLESH', 'NERVE', 'BLOOD', 'BONE'],  hints: ["What the hardware plugs into.", "What the corporations are trying to upgrade.", "The part of the runner without a digital fallback.", "What you cannot spoof, replace, or patch."] },
    { words: ['CITY', 'GRID', 'TOWER', 'BLOCK'],   hints: ["The structure that holds all of this together.", "What the corporations built their signal towers on top of.", "Where runners live in the gaps between monitored zones.", "What you navigate when the official map is lying."] },
    { words: ['PAST', 'FADE', 'RELIC', 'LAPSE'],   hints: ["Where everything the system tried to delete still exists.", "What archives turn into when nobody audits them.", "The direction logs only travel in.", "What a runner exploits when a credential was never rotated."] },
    { words: ['STILL', 'HUSH', 'QUIET', 'MUTE'],   hints: ["The interval between a scan and a triggered alert.", "What the buffer sounds like when they've stopped looking.", "The rarest resource on a monitored network.", "What a careful runner leaves behind them."] },
];

// ── Difficulty config ──────────────────────────────────────────────────────────

const DIFF_CONFIG = {
    1: { fragCount: 3, noiseCount: 10, wordLengthMax: 5, traceDmg: 0.15, stabDmg: 0.08, lockCost: 0.10 },
    2: { fragCount: 3, noiseCount: 16, wordLengthMax: 6, traceDmg: 0.20, stabDmg: 0.10, lockCost: 0.12 },
    3: { fragCount: 3, noiseCount: 22, wordLengthMax: 99, traceDmg: 0.25, stabDmg: 0.13, lockCost: 0.15 },
};

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Shared state ───────────────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Game config ────────────────────────────────────────────────────────────────

const difficulty = props.skin.difficulty ?? 1;
const cfg        = DIFF_CONFIG[difficulty] ?? DIFF_CONFIG[1];

// ── Game state ─────────────────────────────────────────────────────────────────

const fragments    = ref([]);
const slots        = ref([[], [], []]);
const pool         = ref([]);
const solvedFrags  = ref([false, false, false]);
const traceLevel   = ref(0);
const selectedPoolId = ref(null);

const scanOpen     = ref(null);
const openFileIdx  = ref(null);
const unlockedIdxs = ref(new Set());

const showWrong    = ref(false);
const showCorrect  = ref(null);

// ── Ping state ─────────────────────────────────────────────────────────────────
const pingActive   = ref(false);   // true during the 1.5s reveal window
const canPing      = ref(true);    // false during 20s cooldown
const pingCooldown = ref(0);       // remaining cooldown seconds for HUD display
const commandInput = ref('');      // command bar text
let   pingTimer            = null; // reveal timeout handle
let   pingCooldownInterval = null; // cooldown tick interval handle

// ── Computed ───────────────────────────────────────────────────────────────────

const localStabClass = computed(() => {
    if (stability.value < 0.15) return 'ts-val--crit';
    if (stability.value < 0.35) return 'ts-val--warn';
    return '';
});

/**
 * Transforms internal pool shape → clean SignalPool prop interface.
 * Internal: { id, letter, noise, usedBy: null|fi }
 * External: { id, char,   noise, status: 'active'|'used' }
 */
const poolLetters = computed(() =>
    pool.value.map(item => ({
        id:     item.id,
        char:   item.letter,
        noise:  item.noise,
        status: item.usedBy !== null ? 'used' : 'active',
    }))
);

/** Stability cost shown in ScanPanel lock badge */
const lockCostPct = computed(() => Math.round(cfg.lockCost * 100));

/** Fragment currently being scanned — null-safe */
const activeScanFragment = computed(() =>
    scanOpen.value !== null ? fragments.value[scanOpen.value] ?? null : null
);

const currentFileContent = computed(() => {
    if (scanOpen.value === null || openFileIdx.value === null) return '';
    const file = fragments.value[scanOpen.value]?.archive[openFileIdx.value];
    if (!file) return '';
    if (file.locked && !isUnlocked(openFileIdx.value)) return '[ENCRYPTED] — Stability required to decrypt.';
    return file.content ?? '[NO DATA]';
});

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: primaryProgress.value,
    stability:       stability.value,
    stabilityClass:  stabilityClass.value,
    timerClass:      '',
    glitchActive:    glitchActive.value,
    glitchType:      glitchType.value,
    glitchIntensity: glitchIntensity.value,
    result:          result.value,
    failReason:      failReason.value,
}));

// ── Utilities ──────────────────────────────────────────────────────────────────

function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = randInt(0, i);
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

function isUnlocked(fileIdx) {
    return unlockedIdxs.value.has(fileIdx);
}

function canInject(fi) {
    if (solvedFrags.value[fi]) return false;
    return slots.value[fi].every(s => s !== null);
}

// ── Content generation ─────────────────────────────────────────────────────────

function generateCodename() {
    const p = CODENAME_PRE[randInt(0, CODENAME_PRE.length - 1)];
    const s = CODENAME_SUF[randInt(0, CODENAME_SUF.length - 1)];
    return `${p}_${s}`;
}

function generateAcrostic(word) {
    return word.split('').map(letter => {
        const bank = ACROSTIC_WORDS[letter.toUpperCase()] ?? [letter];
        return bank[randInt(0, bank.length - 1)];
    }).join(' ') + '.';
}

function generateArchive(word, fragIdx) {
    const acrostic = generateAcrostic(word);
    const noiseNames = shuffle([
        ['log_purge', 'data'], ['network_dump', 'raw'], ['system_trace', 'log'],
        ['audit_report', 'txt'], ['traffic_sample', 'pcap'], ['error_cascade', 'log'],
        ['memory_snapshot', 'bin'], ['diagnostic_run', 'dat'],
    ]);
    const lockedNames = shuffle([
        ['trace_log_gamma', 'enc'], ['encrypted_manifest', 'enc'],
        ['secure_packet', 'enc'], ['redacted_log', 'enc'],
    ]);

    const files = [
        {
            name:    `${noiseNames[0][0]}.${noiseNames[0][1]}`,
            size:    `${randInt(30, 90)}kb`,
            locked:  false,
            content: `[SYSTEM LOG]\n\nNo anomalous entries detected in this segment.\nRoutine traffic recorded. Nothing actionable.\n\nEnd of log.`,
        },
        {
            name:    `${lockedNames[0][0]}.${lockedNames[0][1]}`,
            size:    `${randInt(8, 20)}kb`,
            locked:  true,
            content: `[ENCRYPTED RECORD]\n\nPartial trace data recovered.\nOrigin: masked. Destination: masked.\nContent: insufficient for analysis.\n\nRecommendation: discard.`,
        },
        {
            name:    `acrostic_clue_fragment.txt`,
            size:    `1kb`,
            locked:  false,
            content: `Acrostic Fragment ${String(fragIdx + 1).padStart(2, '0')}:\n${acrostic}`,
        },
    ];
    return shuffle(files);
}

// ── Puzzle generation ──────────────────────────────────────────────────────────

function buildPuzzle() {
    const shuffledClusters = shuffle([...CLUSTERS]);
    const selected = [];

    for (const cluster of shuffledClusters) {
        if (selected.length >= cfg.fragCount) break;
        const eligible = cluster.words.filter(w => w.length <= cfg.wordLengthMax);
        if (!eligible.length) continue;
        const word = eligible[randInt(0, eligible.length - 1)];
        const hint = cluster.hints[randInt(0, cluster.hints.length - 1)];
        selected.push({
            word,
            hint,
            codename: generateCodename(),
            archive:  generateArchive(word, selected.length),
        });
    }

    fragments.value   = selected;
    slots.value       = selected.map(f => Array(f.word.length).fill(null));
    solvedFrags.value = Array(selected.length).fill(false);
    buildPool(selected);
}

function buildPool(frags) {
    const items = [];

    // Signal: one pool entry per letter occurrence across all fragment words
    frags.forEach(frag => {
        frag.word.split('').forEach(letter => {
            items.push({ letter, noise: false });
        });
    });

    // Track which letters are already covered by signal words
    const signalSet = new Set(frags.flatMap(f => f.word.split('')));

    // Full A-Z: every letter NOT already present as a signal letter becomes noise
    'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').forEach(letter => {
        if (!signalSet.has(letter)) {
            items.push({ letter, noise: true });
        }
    });

    // Extra symbol noise for texture
    const SYMBOLS = '[]{}|\\<>!@#%&'.split('');
    for (let i = 0; i < 6; i++) {
        items.push({ letter: SYMBOLS[randInt(0, SYMBOLS.length - 1)], noise: true });
    }

    shuffle(items);
    pool.value = items.map((item, i) => ({
        id:     i,
        letter: item.letter,
        noise:  item.noise,
        usedBy: null,
    }));
}

// ── Letter placement ───────────────────────────────────────────────────────────

function onPoolClick(item) {
    if (selectedPoolId.value === item.id) { selectedPoolId.value = null; return; }
    selectedPoolId.value = item.id;
}

/** Called by SignalPool's pool-select emit (receives id, not the item object). */
function onPoolSelect(id) {
    onPoolClick(pool.value.find(p => p.id === id) ?? { id });
}

function onSlotClick(fi, si) {
    if (solvedFrags.value[fi]) return;

    if (selectedPoolId.value !== null) {
        const poolItem = pool.value.find(p => p.id === selectedPoolId.value);
        if (!poolItem) return;

        const existing = slots.value[fi][si];
        if (existing) {
            const oldItem = pool.value.find(p => p.id === existing.poolId);
            if (oldItem) oldItem.usedBy = null;
        }

        if (poolItem.usedBy !== null) {
            const prevFi = poolItem.usedBy;
            const prevSi = slots.value[prevFi].findIndex(s => s?.poolId === poolItem.id);
            if (prevSi !== -1) slots.value[prevFi][prevSi] = null;
        }

        poolItem.usedBy      = fi;
        slots.value[fi][si]  = { poolId: poolItem.id, letter: poolItem.letter };
        selectedPoolId.value = null;

    } else {
        const existing = slots.value[fi][si];
        if (existing) {
            const poolItem = pool.value.find(p => p.id === existing.poolId);
            if (poolItem) poolItem.usedBy = null;
            slots.value[fi][si] = null;
        }
    }
}

// ── Inject ─────────────────────────────────────────────────────────────────────

function inject(fi) {
    if (solvedFrags.value[fi] || !canInject(fi)) return;

    const attempt = slots.value[fi].map(s => s?.letter ?? '').join('');
    if (attempt === fragments.value[fi].word) {
        solvedFrags.value[fi] = true;
        showCorrect.value = fi;
        setTimeout(() => { showCorrect.value = null; }, 1400);

        const solved = solvedFrags.value.filter(Boolean).length;
        primaryProgress.value = solved / fragments.value.length;

        if (solvedFrags.value.every(Boolean)) {
            primaryProgress.value = 1;
            setTimeout(() => {
                endGame('success');
                setTimeout(() => emit('complete'), 2200);
            }, 800);
        }
    } else {
        traceLevel.value = Math.min(1, traceLevel.value + cfg.traceDmg);
        applyHit(cfg.stabDmg);
        showWrong.value = true;
        setTimeout(() => { showWrong.value = false; }, 1400);

        if (traceLevel.value >= 1 || stability.value <= 0) {
            const reason = traceLevel.value >= 1
                ? '[ICE LOCK] — Trace resolved. Location compromised.'
                : '[SYSTEM COLLAPSE] — Stability exhausted.';
            endGame('fail', reason);
            setTimeout(() => emit('fail'), 2200);
        }
    }
}

// ── Scan / investigate ─────────────────────────────────────────────────────────

function openScan(fi) {
    scanOpen.value     = fi;
    openFileIdx.value  = null;
    unlockedIdxs.value = new Set();
}

function openFile(fileIdx) {
    if (scanOpen.value === null) return;
    const file = fragments.value[scanOpen.value].archive[fileIdx];

    if (file.locked && !isUnlocked(fileIdx)) {
        applyHit(cfg.lockCost);
        unlockedIdxs.value.add(fileIdx);
        if (stability.value <= 0) {
            endGame('fail', '[SYSTEM COLLAPSE] — Stability exhausted during decryption.');
            setTimeout(() => emit('fail'), 2200);
        }
    }

    openFileIdx.value = fileIdx;
}

// ── Ping / command ─────────────────────────────────────────────────────────────

/**
 * Activate the noise-reveal ping.
 * - Reveals all noise tiles for 1.5 s.
 * - Spikes the active trace (generates "heat").
 * - Enters a 20 s recharge cooldown before the command is usable again.
 */
function pingNoise() {
    if (!canPing.value || result.value) return;

    // Trace spike — pinging generates ICE heat
    traceLevel.value = Math.min(1, traceLevel.value + 0.12);

    // Reveal noise tiles
    pingActive.value = true;
    clearTimeout(pingTimer);
    pingTimer = setTimeout(() => { pingActive.value = false; }, 1500);

    // Start 20 s cooldown
    canPing.value      = false;
    pingCooldown.value = 20;
    clearInterval(pingCooldownInterval);
    pingCooldownInterval = setInterval(() => {
        pingCooldown.value--;
        if (pingCooldown.value <= 0) {
            clearInterval(pingCooldownInterval);
            pingCooldown.value = 0;
            canPing.value      = true;
        }
    }, 1000);
}

/**
 * Parse a command typed into the command bar.
 * Recognised commands:
 *   ping --noise   →  activate noise reveal
 */
function parseCommand() {
    const cmd = commandInput.value.trim().toLowerCase();
    commandInput.value = '';
    if (cmd === 'ping --noise') pingNoise();
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => buildPuzzle());

onUnmounted(() => {
    clearTimeout(pingTimer);
    clearInterval(pingCooldownInterval);
});
</script>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   Canvas — fixed 1920 × 1080, CSS Grid
   Columns : 350px | 1fr | 400px
   Rows    : 1fr   | 220px
════════════════════════════════════════════════════════════════════════════ */

.ts-canvas {
    /* Width fixed at 1920px (scrolls horizontally if viewport is narrower).
       Height fills qmc-game-area so the pool row is never clipped. */
    width:  1920px;
    height: 100%;

    display: grid;
    grid-template-columns: 350px 1fr 400px;
    grid-template-rows: 1fr 220px;

    font-family: 'JetBrains Mono', monospace;
    background: #04090e;
    color: #00c8f0;
    overflow: hidden;
    position: relative;
}

/* ── Grid overlay ─────────────────────────────────────────────────────────── */

.ts-grid-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background-image:
        linear-gradient(rgba(0,200,240,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,200,240,0.025) 1px, transparent 1px);
    background-size: 32px 32px;
}

/* ── Cells — base ─────────────────────────────────────────────────────────── */

.ts-cell {
    position: relative;
    z-index: 1;
    /*
     * min-height: 0 is critical.
     * Grid items default to min-height: auto (= content size).
     * Without this, a tall cell expands its row track beyond the
     * allocated 1fr, pushing the 200px pool row off the canvas.
     */
    min-height: 0;
    min-width: 0;
    overflow: hidden;
    border: 1px solid rgba(39, 39, 42, 0.5); /* zinc-800/50 */
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ── Cell placement ───────────────────────────────────────────────────────── */

.ts-cell--scan   { grid-column: 1;      grid-row: 1; overflow-y: auto; }
.ts-cell--frags  { grid-column: 2;      grid-row: 1; }
.ts-cell--status { grid-column: 3;      grid-row: 1; overflow-y: auto; }
.ts-cell--pool   { grid-column: 1 / -1; grid-row: 2; }

/* Scan cell — ScanPanel + SystemNoise stack vertically */
.ts-cell--scan-layout {
    flex-direction: column;
    gap: 0;
    /* Override overflow-y: auto so internal flex children manage scroll */
    overflow-y: hidden;
}

/* Fragment cell — canvas manages its own internal padding */
.ts-cell--frags-flush {
    padding: 0;
}

/* ScanPanel slide-in transition */
.ts-scan-slide-enter-active { transition: opacity 0.22s, transform 0.22s; }
.ts-scan-slide-leave-active { transition: opacity 0.18s, transform 0.18s; }
.ts-scan-slide-enter-from,
.ts-scan-slide-leave-to     { opacity: 0; transform: translateY(-10px); }

/* Pool cell uses flex-col; SignalPool takes flex:1 via its own CSS */
.ts-cell--pool-layout {
    flex-direction: column;
    gap: 0;
    padding: 10px 16px 10px;
}

/* ── Pool control strip (ping status + command input) ─────────────────────── */

.ts-pool-ctrl {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(0,200,240,0.07);
    margin-bottom: 8px;
}

.ts-ping-status {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.14em;
    flex-shrink: 0;
    transition: color 0.3s;
}

.ts-ping--ready { color: #00ff9d; }
.ts-ping--cool  { color: rgba(255,170,0,0.65); animation: ts-ping-blink 0.8s step-start infinite; }

.ts-cmd-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    max-width: 280px;
    border: 1px solid rgba(0,200,240,0.12);
    padding: 3px 8px;
    background: rgba(0,10,18,0.6);
}

.ts-cmd-prompt {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #00ff9d;
    flex-shrink: 0;
    user-select: none;
}

.ts-cmd-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: rgba(0,200,240,0.8);
    letter-spacing: 0.06em;
    caret-color: #00c8f0;
}

.ts-cmd-input::placeholder {
    color: rgba(0,200,240,0.18);
    font-style: italic;
}

/* ── Cell label (development guide / section header) ─────────────────────── */

.ts-cell-label {
    font-size: 10px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.35);
    border-bottom: 1px solid rgba(0,200,240,0.08);
    padding-bottom: 8px;
    flex-shrink: 0;
}

@keyframes ts-ping-blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.35; }
}
</style>
