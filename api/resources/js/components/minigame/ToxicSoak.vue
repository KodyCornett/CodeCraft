<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ts-wrap">

            <!-- ── Header ──────────────────────────────────────────────────── -->
            <div class="ts-header">
                <span class="ts-header-star">*</span>
                <span class="ts-header-title">CIPHER_BUFFER_{{ String(props.skin.bufferId ?? '01').padStart(2, '0') }}</span>
            </div>

            <!-- ── Body ───────────────────────────────────────────────────── -->
            <div class="ts-body">

                <!-- Fragments column -->
                <div class="ts-fragments">
                    <div
                        v-for="(frag, fi) in fragments"
                        :key="fi"
                        class="ts-fragment"
                        :class="{ 'ts-fragment--solved': solvedFrags[fi] }"
                    >
                        <div class="ts-frag-title">
                            FRAGMENT_{{ String(fi + 1).padStart(2, '0') }} // {{ frag.codename }}
                        </div>
                        <div class="ts-frag-hint">Hint: "{{ frag.hint }}"</div>
                        <div class="ts-frag-controls">
                            <!-- Letter slots -->
                            <div class="ts-slots">
                                <div
                                    v-for="(entry, si) in slots[fi]"
                                    :key="si"
                                    class="ts-slot"
                                    :class="{
                                        'ts-slot--filled':    entry !== null,
                                        'ts-slot--droppable': selectedPoolId !== null && !solvedFrags[fi],
                                    }"
                                    @click="onSlotClick(fi, si)"
                                >{{ entry ? entry.letter : '?' }}</div>
                            </div>
                            <button
                                class="ts-btn ts-btn--scan"
                                :class="{ 'ts-btn--scan-active': scanOpen === fi }"
                                @click="openScan(fi)"
                            >[ SCAN _{{ String(fi + 1).padStart(2, '0') }} ]</button>
                            <button
                                class="ts-btn ts-btn--inject"
                                :class="{ 'ts-btn--inject-ready': canInject(fi) }"
                                :disabled="solvedFrags[fi]"
                                @click="inject(fi)"
                            >[ INJECT _{{ String(fi + 1).padStart(2, '0') }} ]</button>
                        </div>
                    </div>
                </div>

                <!-- HUD -->
                <div class="ts-hud">
                    <div class="ts-hud-row">
                        <span class="ts-hud-label">SYSTEM STABILITY</span>
                        <span class="ts-hud-val" :class="localStabClass">{{ Math.round(stability * 100) }}%</span>
                    </div>
                    <div class="ts-hud-bar">
                        <div class="ts-hud-fill ts-fill--stab" :style="{ width: stability * 100 + '%' }"/>
                    </div>

                    <div class="ts-hud-row ts-hud-sep">
                        <span class="ts-hud-label">ACTIVE TRACE</span>
                        <span class="ts-hud-val ts-val--trace">{{ Math.round(traceLevel * 100) }}%</span>
                    </div>
                    <div class="ts-hud-bar">
                        <div class="ts-hud-fill ts-fill--trace" :style="{ width: traceLevel * 100 + '%' }"/>
                    </div>

                    <div class="ts-hud-row ts-hud-sep">
                        <span class="ts-hud-label">FRAGMENTS SECURED</span>
                    </div>
                    <div class="ts-hud-pips">
                        <span
                            v-for="i in fragments.length"
                            :key="i"
                            class="ts-hud-pip"
                            :class="solvedFrags[i - 1] ? 'ts-pip--secured' : 'ts-pip--open'"
                        >◉</span>
                    </div>

                    <!-- Wrong inject flash -->
                    <Transition name="ts-flash">
                        <div v-if="showWrong" class="ts-wrong-flash">
                            [ SEQUENCE REJECTED ]<br/>TRACE INCREASED
                        </div>
                    </Transition>

                    <!-- Correct flash -->
                    <Transition name="ts-flash">
                        <div v-if="showCorrect !== null" class="ts-correct-flash">
                            [ FRAGMENT_{{ String(showCorrect + 1).padStart(2, '0') }} SECURED ]
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- ── Signal pool ─────────────────────────────────────────────── -->
            <div class="ts-pool-wrap">
                <div class="ts-pool-header">
                    <span class="ts-pool-label">SHARED SIGNAL POOL</span>
                    <Transition name="ts-fade">
                        <span v-if="selectedPoolId !== null" class="ts-pool-hint">▸ PLACE LETTER</span>
                    </Transition>
                    <span v-if="selectedPoolId !== null" class="ts-pool-deselect" @click="selectedPoolId = null">[ CANCEL ]</span>
                </div>
                <div class="ts-pool">
                    <div
                        v-for="item in pool"
                        :key="item.id"
                        class="ts-tile"
                        :class="{
                            'ts-tile--selected': item.id === selectedPoolId,
                            'ts-tile--used':     item.usedBy !== null,
                            'ts-tile--noise':    item.noise,
                        }"
                        @click="onPoolClick(item)"
                    >{{ item.letter }}</div>
                </div>
            </div>

            <!-- ── Investigate popup ───────────────────────────────────────── -->
            <Transition name="ts-popup">
                <div v-if="scanOpen !== null" class="ts-popup">
                    <div class="ts-popup-header">
                        <span class="ts-popup-title">ARCHIVE_DIR/_FRAGMENT_{{ String(scanOpen + 1).padStart(2, '0') }}</span>
                        <button class="ts-popup-close" @click="scanOpen = null">×</button>
                    </div>
                    <div class="ts-popup-files">
                        <div
                            v-for="(file, fli) in fragments[scanOpen].archive"
                            :key="fli"
                            class="ts-file-row"
                            :class="{ 'ts-file--active': openFileIdx === fli }"
                            @click="openFile(fli)"
                        >
                            <span class="ts-file-open">[ OPEN ]</span>
                            <span class="ts-file-name">{{ file.name }}</span>
                            <span class="ts-file-size">({{ file.size }})</span>
                            <span v-if="file.locked && !isUnlocked(fli)" class="ts-file-lock">🔒</span>
                        </div>
                    </div>
                    <Transition name="ts-fade">
                        <div v-if="openFileIdx !== null" class="ts-file-content">
                            <pre class="ts-file-text">{{ currentFileContent }}</pre>
                        </div>
                    </Transition>
                </div>
            </Transition>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed } from 'vue';
import { onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
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

const fragments    = ref([]);            // [{ word, hint, codename, archive }]
const slots        = ref([[], [], []]);  // slots[fi][si] = { poolId, letter } | null
const pool         = ref([]);            // [{ id, letter, noise, usedBy: null|fi }]
const solvedFrags  = ref([false, false, false]);
const traceLevel   = ref(0);
const selectedPoolId = ref(null);

const scanOpen     = ref(null);          // null | fragment index
const openFileIdx  = ref(null);          // null | file index within current scan
const unlockedIdxs = ref(new Set());     // file indexes unlocked in current scan session

const showWrong    = ref(false);
const showCorrect  = ref(null);          // null | fragment index

// ── Computed ───────────────────────────────────────────────────────────────────

const localStabClass = computed(() => {
    if (stability.value < 0.15) return 'ts-val--crit';
    if (stability.value < 0.35) return 'ts-val--warn';
    return '';
});

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
            name: `${noiseNames[0][0]}.${noiseNames[0][1]}`,
            size: `${randInt(30, 90)}kb`,
            locked: false,
            content: `[SYSTEM LOG]\n\nNo anomalous entries detected in this segment.\nRoutine traffic recorded. Nothing actionable.\n\nEnd of log.`,
        },
        {
            name: `${lockedNames[0][0]}.${lockedNames[0][1]}`,
            size: `${randInt(8, 20)}kb`,
            locked: true,
            content: `[ENCRYPTED RECORD]\n\nPartial trace data recovered.\nOrigin: masked. Destination: masked.\nContent: insufficient for analysis.\n\nRecommendation: discard.`,
        },
        {
            name: `acrostic_clue_fragment.txt`,
            size: `1kb`,
            locked: false,
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

    fragments.value = selected;
    slots.value     = selected.map(f => Array(f.word.length).fill(null));
    solvedFrags.value = Array(selected.length).fill(false);
    buildPool(selected);
}

function buildPool(frags) {
    const items = [];

    // Signal letters — one per character of each word
    frags.forEach((frag, fi) => {
        frag.word.split('').forEach(letter => {
            items.push({ letter, noise: false });
        });
    });

    // Noise
    for (let i = 0; i < cfg.noiseCount; i++) {
        items.push({ letter: NOISE_CHARS[randInt(0, NOISE_CHARS.length - 1)], noise: true });
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
    if (selectedPoolId.value === item.id) {
        // Deselect
        selectedPoolId.value = null;
        return;
    }
    if (item.usedBy !== null) {
        // Already placed — select it so player can move it
        selectedPoolId.value = item.id;
        return;
    }
    selectedPoolId.value = item.id;
}

function onSlotClick(fi, si) {
    if (solvedFrags.value[fi]) return;

    if (selectedPoolId.value !== null) {
        // Place selected pool letter into this slot
        const poolItem = pool.value.find(p => p.id === selectedPoolId.value);
        if (!poolItem) return;

        // If this slot already has a letter, return it to pool
        const existing = slots.value[fi][si];
        if (existing) {
            const oldItem = pool.value.find(p => p.id === existing.poolId);
            if (oldItem) oldItem.usedBy = null;
        }

        // If pool item was used elsewhere, clear that slot
        if (poolItem.usedBy !== null) {
            const prevFi = poolItem.usedBy;
            const prevSi = slots.value[prevFi].findIndex(s => s?.poolId === poolItem.id);
            if (prevSi !== -1) slots.value[prevFi][prevSi] = null;
        }

        // Place into new slot
        poolItem.usedBy          = fi;
        slots.value[fi][si]      = { poolId: poolItem.id, letter: poolItem.letter };
        selectedPoolId.value     = null;

    } else {
        // No letter selected — click filled slot to return it to pool
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

        // Update progress
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
        // Wrong — damage stability + raise trace
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
        // Pay stability to decrypt
        applyHit(cfg.lockCost);
        unlockedIdxs.value.add(fileIdx);
        if (stability.value <= 0) {
            endGame('fail', '[SYSTEM COLLAPSE] — Stability exhausted during decryption.');
            setTimeout(() => emit('fail'), 2200);
        }
    }

    openFileIdx.value = fileIdx;
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => buildPuzzle());
</script>

<style scoped>
/* ── Root ────────────────────────────────────────────────────────────────── */

.ts-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    background: #060e12;
    color: #00d4ff;
    box-sizing: border-box;
    padding: 8px 10px 6px;
    gap: 6px;
    overflow: hidden;
    position: relative;
}

/* ── Header ──────────────────────────────────────────────────────────────── */

.ts-header {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(0,212,255,0.1);
    padding-bottom: 5px;
}

.ts-header-star  { color: #00ff9d; font-size: 14px; }
.ts-header-title { font-size: 11px; letter-spacing: 0.16em; color: rgba(0,212,255,0.9); }

/* ── Body ────────────────────────────────────────────────────────────────── */

.ts-body {
    display: flex;
    gap: 10px;
    flex: 1;
    min-height: 0;
}

/* ── Fragments ───────────────────────────────────────────────────────────── */

.ts-fragments {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
    min-width: 0;
}

.ts-fragment {
    border: 1px solid rgba(0,212,255,0.14);
    background: rgba(0,20,30,0.5);
    padding: 6px 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: border-color 0.2s;
}

.ts-fragment--solved {
    border-color: rgba(0,255,100,0.3);
    background: rgba(0,30,20,0.4);
}

.ts-frag-title {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: rgba(0,212,255,0.55);
}

.ts-frag-hint {
    font-size: 10px;
    color: rgba(0,212,255,0.85);
    font-style: italic;
    letter-spacing: 0.02em;
    line-height: 1.4;
}

.ts-frag-controls {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

/* ── Slots ───────────────────────────────────────────────────────────────── */

.ts-slots {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.ts-slot {
    width: 34px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    border: 1px solid rgba(0,212,255,0.22);
    background: rgba(0,15,25,0.8);
    color: rgba(0,212,255,0.4);
    cursor: pointer;
    transition: border-color 0.12s, color 0.12s, background 0.12s, box-shadow 0.12s;
    user-select: none;
}

.ts-slot--filled {
    color: #00d4ff;
    border-color: rgba(0,212,255,0.5);
    background: rgba(0,30,45,0.8);
}

.ts-slot--droppable:hover {
    border-color: #00d4ff;
    background: rgba(0,212,255,0.1);
    box-shadow: 0 0 8px rgba(0,212,255,0.2);
    cursor: crosshair;
}

/* ── Buttons ─────────────────────────────────────────────────────────────── */

.ts-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 5px 10px;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, opacity 0.15s;
    white-space: nowrap;
}

.ts-btn--scan {
    color: rgba(0,212,255,0.6);
    border-color: rgba(0,212,255,0.25);
}

.ts-btn--scan:hover,
.ts-btn--scan-active {
    color: #00d4ff;
    border-color: rgba(0,212,255,0.7);
    background: rgba(0,212,255,0.07);
    box-shadow: 0 0 8px rgba(0,212,255,0.15);
}

.ts-btn--inject {
    color: rgba(0,255,100,0.35);
    border-color: rgba(0,255,100,0.15);
    opacity: 0.5;
}

.ts-btn--inject-ready {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.55);
    opacity: 1;
}

.ts-btn--inject-ready:hover {
    background: rgba(0,255,100,0.08);
    box-shadow: 0 0 10px rgba(0,255,100,0.2);
}

.ts-btn:disabled { cursor: not-allowed; opacity: 0.25; }

/* ── HUD ─────────────────────────────────────────────────────────────────── */

.ts-hud {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-shrink: 0;
    width: 180px;
    border: 1px solid rgba(0,212,255,0.1);
    background: rgba(0,10,18,0.6);
    padding: 8px 10px;
}

.ts-hud-sep   { margin-top: 8px; }

.ts-hud-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ts-hud-label { font-size: 7px; letter-spacing: 0.12em; color: rgba(0,212,255,0.4); }
.ts-hud-val   { font-size: 9px; letter-spacing: 0.06em; color: #00d4ff; }
.ts-val--warn { color: #ffaa00; }
.ts-val--crit { color: #ff3333; animation: ts-pulse 0.6s ease infinite alternate; }
.ts-val--trace{ color: #ff6600; }

.ts-hud-bar {
    height: 4px;
    background: rgba(0,212,255,0.07);
    overflow: hidden;
    flex-shrink: 0;
}

.ts-hud-fill { height: 100%; transition: width 0.4s; }

.ts-fill--stab {
    background: linear-gradient(90deg, #004422, #00ff9d);
    box-shadow: 0 0 5px rgba(0,255,100,0.4);
}

.ts-fill--trace {
    background: linear-gradient(90deg, #330000, #ff3333);
    box-shadow: 0 0 5px rgba(255,50,50,0.3);
}

.ts-hud-pips { display: flex; gap: 5px; margin-top: 2px; }

.ts-hud-pip      { font-size: 13px; transition: color 0.2s; }
.ts-pip--open    { color: rgba(0,212,255,0.3); }
.ts-pip--secured { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.6); }

.ts-wrong-flash,
.ts-correct-flash {
    margin-top: 8px;
    font-size: 8px;
    letter-spacing: 0.1em;
    line-height: 1.5;
    padding: 5px 7px;
    border: 1px solid;
    text-align: center;
}

.ts-wrong-flash   { color: #ff3333; border-color: rgba(255,50,50,0.3); background: rgba(80,0,0,0.3); }
.ts-correct-flash { color: #00ff9d; border-color: rgba(0,255,100,0.3); background: rgba(0,60,30,0.3); }

/* ── Signal pool ─────────────────────────────────────────────────────────── */

.ts-pool-wrap {
    flex-shrink: 0;
    border-top: 1px solid rgba(0,212,255,0.1);
    padding-top: 6px;
}

.ts-pool-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.ts-pool-label   { font-size: 7px; letter-spacing: 0.16em; color: rgba(0,212,255,0.4); }
.ts-pool-hint    { font-size: 7px; letter-spacing: 0.1em; color: #00d4ff; animation: ts-blink 0.8s ease infinite alternate; }
.ts-pool-deselect{ font-size: 7px; letter-spacing: 0.1em; color: rgba(255,100,50,0.7); cursor: pointer; margin-left: auto; }
.ts-pool-deselect:hover { color: #ff6633; }

.ts-pool {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.ts-tile {
    width: 30px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid rgba(0,212,255,0.25);
    background: rgba(0,20,35,0.8);
    color: #00d4ff;
    cursor: pointer;
    transition: border-color 0.1s, background 0.1s, box-shadow 0.1s, opacity 0.1s;
    user-select: none;
    letter-spacing: 0;
}

.ts-tile:hover {
    border-color: rgba(0,212,255,0.6);
    background: rgba(0,212,255,0.08);
}

.ts-tile--selected {
    border-color: #00d4ff;
    background: rgba(0,212,255,0.14);
    box-shadow: 0 0 10px rgba(0,212,255,0.3);
    color: #fff;
}

.ts-tile--used {
    opacity: 0.35;
    cursor: default;
}

.ts-tile--used:hover {
    border-color: rgba(0,212,255,0.25);
    background: rgba(0,20,35,0.8);
}

.ts-tile--noise {
    color: #ff8800;
    border-color: rgba(255,136,0,0.22);
    background: rgba(30,10,0,0.8);
}

.ts-tile--noise:hover {
    border-color: rgba(255,136,0,0.5);
    background: rgba(40,15,0,0.8);
}

/* ── Investigate popup ───────────────────────────────────────────────────── */

.ts-popup {
    position: absolute;
    top: 56px;
    left: 10px;
    width: 300px;
    background: #060e14;
    border: 1px solid rgba(0,212,255,0.4);
    box-shadow: 0 0 20px rgba(0,212,255,0.1);
    z-index: 100;
    display: flex;
    flex-direction: column;
}

.ts-popup-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 8px;
    border-bottom: 1px solid rgba(0,212,255,0.15);
    background: rgba(0,30,45,0.6);
}

.ts-popup-title { font-size: 8px; letter-spacing: 0.12em; color: rgba(0,212,255,0.7); }

.ts-popup-close {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    color: rgba(0,212,255,0.5);
    background: none;
    border: none;
    cursor: pointer;
    line-height: 1;
    padding: 0 2px;
    transition: color 0.15s;
}
.ts-popup-close:hover { color: #ff3333; }

.ts-popup-files {
    display: flex;
    flex-direction: column;
    padding: 4px 0;
}

.ts-file-row {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 5px 8px;
    cursor: pointer;
    transition: background 0.12s;
    font-size: 9px;
}

.ts-file-row:hover     { background: rgba(0,212,255,0.06); }
.ts-file--active       { background: rgba(0,212,255,0.08); }

.ts-file-open {
    color: rgba(0,212,255,0.6);
    letter-spacing: 0.06em;
    white-space: nowrap;
    flex-shrink: 0;
}

.ts-file-row:hover .ts-file-open { color: #00d4ff; }

.ts-file-name {
    flex: 1;
    color: rgba(0,212,255,0.85);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ts-file-size { color: rgba(0,212,255,0.35); flex-shrink: 0; }
.ts-file-lock { flex-shrink: 0; font-size: 10px; }

.ts-file-content {
    border-top: 1px solid rgba(0,212,255,0.12);
    padding: 7px 8px;
    background: rgba(0,12,20,0.6);
    max-height: 90px;
    overflow-y: auto;
}

.ts-file-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 8.5px;
    color: rgba(255,200,80,0.85);
    line-height: 1.7;
    margin: 0;
    white-space: pre-wrap;
    letter-spacing: 0.02em;
}

/* ── Transitions ─────────────────────────────────────────────────────────── */

.ts-flash-enter-active { animation: ts-flash-in 0.15s ease; }
.ts-flash-leave-active { transition: opacity 0.35s; }
.ts-flash-leave-to     { opacity: 0; }

.ts-popup-enter-active { transition: opacity 0.2s, transform 0.2s; }
.ts-popup-leave-active { transition: opacity 0.18s, transform 0.18s; }
.ts-popup-enter-from,
.ts-popup-leave-to     { opacity: 0; transform: translateY(-6px); }

.ts-fade-enter-active  { transition: opacity 0.25s; }
.ts-fade-leave-active  { transition: opacity 0.2s; }
.ts-fade-enter-from,
.ts-fade-leave-to      { opacity: 0; }

/* ── Keyframes ───────────────────────────────────────────────────────────── */

@keyframes ts-pulse {
    from { opacity: 1; }
    to   { opacity: 0.35; }
}

@keyframes ts-blink {
    from { opacity: 1; }
    to   { opacity: 0.4; }
}

@keyframes ts-flash-in {
    from { opacity: 0; transform: scale(0.96); }
    to   { opacity: 1; transform: scale(1); }
}
</style>
