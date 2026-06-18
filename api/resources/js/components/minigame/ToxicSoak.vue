<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ts-wrap">

            <!-- ── Grid overlay ──────────────────────────────────────────────── -->
            <div class="ts-grid" aria-hidden="true"/>

            <!-- ── Top bar ───────────────────────────────────────────────────── -->
            <div class="ts-topbar">
                <span class="ts-topbar-diamond">◈</span>
                <span class="ts-topbar-title">CIPHER_BUFFER_{{ String(props.skin.bufferId ?? '01').padStart(2, '0') }}</span>
                <div class="ts-topbar-greebles">
                    <span>SESS: 0x{{ Math.floor(Math.random() * 0xFFFF).toString(16).toUpperCase().padStart(4,'0') }}</span>
                    <span>PKT: 1487</span>
                    <span class="ts-greeble-div">│</span>
                    <span>SPLICE_LOCK</span>
                    <span class="ts-greeble-ok">◆ AUTH_OK</span>
                </div>
            </div>

            <!-- ── Main body ──────────────────────────────────────────────────── -->
            <div class="ts-body">

                <!-- ┌ Left — Archive scan panel ──────────────────────────────── -->
                <div class="ts-left">
                    <div class="ts-panel-label">
                        <span class="ts-panel-bracket">[</span>
                        ARCHIVE_SCAN
                        <span class="ts-panel-bracket">]</span>
                    </div>

                    <template v-if="scanOpen !== null">
                        <div class="ts-scan-dir">
                            <span class="ts-scan-path">ARCHIVE_DIR/_FRAGMENT_{{ String(scanOpen + 1).padStart(2, '0') }}</span>
                        </div>
                        <div class="ts-scan-files">
                            <div
                                v-for="(file, fli) in fragments[scanOpen].archive"
                                :key="fli"
                                class="ts-file-row"
                                :class="{ 'ts-file--active': openFileIdx === fli }"
                                @click="openFile(fli)"
                            >
                                <span class="ts-file-open">▸</span>
                                <span class="ts-file-name">{{ file.name }}</span>
                                <span class="ts-file-meta">{{ file.size }}<span v-if="file.locked && !isUnlocked(fli)" class="ts-file-lock"> 🔒</span></span>
                            </div>
                        </div>
                        <Transition name="ts-fade">
                            <div v-if="openFileIdx !== null" class="ts-file-content">
                                <pre class="ts-file-text">{{ currentFileContent }}</pre>
                            </div>
                        </Transition>
                        <button class="ts-scan-close" @click="scanOpen = null">[ CLOSE_SCAN ]</button>
                    </template>

                    <template v-else>
                        <div class="ts-no-scan">
                            <div class="ts-no-scan-icon">⬡</div>
                            <div class="ts-no-scan-line">NO FRAGMENT SELECTED</div>
                            <div class="ts-no-scan-sub">Press [ SCAN ] on any fragment to investigate its data archive.</div>
                        </div>
                    </template>

                    <!-- Greebles -->
                    <div class="ts-left-greebles">
                        <span>0x0A4E: READ</span>
                        <span>PTR: 0xFFE2</span>
                        <span>BUF: CLEAN</span>
                    </div>
                </div>

                <!-- ┌ Center — Fragments ─────────────────────────────────────── -->
                <div class="ts-center">
                    <div
                        v-for="(frag, fi) in fragments"
                        :key="fi"
                        class="ts-fragment"
                        :class="{ 'ts-fragment--solved': solvedFrags[fi] }"
                    >
                        <!-- Fragment header -->
                        <div class="ts-frag-header">
                            <span class="ts-frag-id">FRAGMENT_{{ String(fi + 1).padStart(2, '0') }} // {{ frag.codename }}</span>
                            <span class="ts-frag-badge" :class="solvedFrags[fi] ? 'ts-badge--ok' : 'ts-badge--pending'">
                                {{ solvedFrags[fi] ? '◆ SECURED' : '○ PENDING' }}
                            </span>
                        </div>

                        <!-- Hint -->
                        <div class="ts-frag-hint">"{{ frag.hint }}"</div>

                        <!-- Controls row -->
                        <div class="ts-frag-row">
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

                            <div class="ts-frag-btns">
                                <button
                                    class="ts-btn ts-btn--scan"
                                    :class="{ 'ts-btn--scan-active': scanOpen === fi }"
                                    @click="openScan(fi)"
                                >[ SCAN_{{ String(fi + 1).padStart(2, '0') }} ]</button>
                                <button
                                    class="ts-btn ts-btn--inject"
                                    :class="{ 'ts-btn--inject-ready': canInject(fi) }"
                                    :disabled="solvedFrags[fi]"
                                    @click="inject(fi)"
                                >[ INJECT_{{ String(fi + 1).padStart(2, '0') }} ]</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ┌ Right — Status monitor ─────────────────────────────────── -->
                <div class="ts-right">
                    <div class="ts-panel-label">
                        <span class="ts-panel-bracket">[</span>
                        STATUS_MONITOR
                        <span class="ts-panel-bracket">]</span>
                    </div>

                    <!-- Stability -->
                    <div class="ts-stat-block">
                        <div class="ts-stat-row">
                            <span class="ts-stat-label">SYS.STABILITY</span>
                            <span class="ts-stat-val" :class="localStabClass">{{ Math.round(stability * 100) }}%</span>
                        </div>
                        <div class="ts-stat-bar">
                            <div class="ts-bar-fill ts-fill--stab" :style="{ width: stability * 100 + '%' }"/>
                        </div>
                        <div class="ts-stat-sub">
                            {{ stability >= 0.7 ? 'NOMINAL' : stability >= 0.35 ? 'DEGRADED' : 'CRITICAL' }}
                        </div>
                    </div>

                    <!-- Trace -->
                    <div class="ts-stat-block">
                        <div class="ts-stat-row">
                            <span class="ts-stat-label">ACTIVE_TRACE</span>
                            <span class="ts-stat-val ts-val--trace" :class="{ 'ts-val--crit': traceLevel > 0.7, 'ts-val--warn': traceLevel > 0.4 && traceLevel <= 0.7 }">{{ Math.round(traceLevel * 100) }}%</span>
                        </div>
                        <div class="ts-stat-bar">
                            <div class="ts-bar-fill ts-fill--trace" :style="{ width: traceLevel * 100 + '%' }"/>
                        </div>
                        <div class="ts-stat-sub ts-sub--trace">
                            {{ traceLevel < 0.3 ? 'UNDETECTED' : traceLevel < 0.6 ? 'EXPOSURE LOW' : traceLevel < 0.85 ? 'TRACED — EVADE' : 'ICE IMMINENT' }}
                        </div>
                    </div>

                    <!-- Fragments -->
                    <div class="ts-stat-block">
                        <div class="ts-stat-label" style="margin-bottom:6px">FRAGS_SECURED</div>
                        <div class="ts-pips">
                            <span
                                v-for="i in fragments.length"
                                :key="i"
                                class="ts-pip"
                                :class="solvedFrags[i - 1] ? 'ts-pip--secured' : 'ts-pip--open'"
                            >◉</span>
                        </div>
                        <div class="ts-stat-sub">{{ solvedFrags.filter(Boolean).length }} / {{ fragments.length }} recovered</div>
                    </div>

                    <!-- Flash messages -->
                    <Transition name="ts-flash">
                        <div v-if="showWrong" class="ts-flash ts-flash--wrong">
                            <span class="ts-flash-icon">✕</span>
                            SEQ REJECTED<br/>
                            <span class="ts-flash-sub">TRACE INCREASED</span>
                        </div>
                    </Transition>
                    <Transition name="ts-flash">
                        <div v-if="showCorrect !== null" class="ts-flash ts-flash--ok">
                            <span class="ts-flash-icon">◆</span>
                            FRAG_{{ String(showCorrect + 1).padStart(2, '0') }} SECURED<br/>
                            <span class="ts-flash-sub">SEQUENCE CONFIRMED</span>
                        </div>
                    </Transition>

                    <!-- Greebles -->
                    <div class="ts-right-greebles">
                        <span>SPLICE: ACTIVE</span>
                        <span>KERN: OK</span>
                        <span>MEM: 847MB</span>
                        <span>NET: 0x3C1A</span>
                        <span>UPLINK: OK</span>
                    </div>
                </div>
            </div>

            <!-- ── Signal pool ─────────────────────────────────────────────────── -->
            <div class="ts-pool-section">
                <div class="ts-pool-bar">
                    <span class="ts-pool-label">SHARED_SIGNAL_POOL</span>
                    <span class="ts-pool-legend"><span class="ts-legend-sig">■</span> SIGNAL</span>
                    <span class="ts-pool-legend"><span class="ts-legend-noise">■</span> NOISE</span>
                    <div class="ts-pool-bar-right">
                        <Transition name="ts-fade">
                            <span v-if="selectedPoolId !== null" class="ts-pool-hint">▸ SELECT SLOT TO PLACE</span>
                        </Transition>
                        <span v-if="selectedPoolId !== null" class="ts-pool-cancel" @click="selectedPoolId = null">[ CANCEL ]</span>
                    </div>
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
    background: #04090e;
    color: #00c8f0;
    box-sizing: border-box;
    padding: 6px 8px 5px;
    gap: 5px;
    overflow: hidden;
    position: relative;
}

/* ── Grid overlay ────────────────────────────────────────────────────────── */

.ts-grid {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
    background-image:
        linear-gradient(rgba(0,200,240,0.028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,200,240,0.028) 1px, transparent 1px);
    background-size: 28px 28px;
}

/* Ensure content sits above grid */
.ts-topbar, .ts-body, .ts-pool-section { position: relative; z-index: 1; }

/* ── Top bar ─────────────────────────────────────────────────────────────── */

.ts-topbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(0,200,240,0.12);
    padding-bottom: 5px;
}

.ts-topbar-diamond { color: #00ff9d; font-size: 13px; }
.ts-topbar-title   { font-size: 11px; letter-spacing: 0.18em; color: rgba(0,200,240,0.9); flex-shrink: 0; }

.ts-topbar-greebles {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
    font-size: 9px;
    color: rgba(0,200,240,0.28);
    letter-spacing: 0.1em;
}

.ts-greeble-div { color: rgba(0,200,240,0.15); }
.ts-greeble-ok  { color: rgba(0,255,150,0.4); }

/* ── Body: three-column ──────────────────────────────────────────────────── */

.ts-body {
    display: flex;
    gap: 8px;
    flex: 1;
    min-height: 0;
}

/* ── Left: Archive scan panel ────────────────────────────────────────────── */

.ts-left {
    width: 240px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid rgba(0,200,240,0.1);
    background: rgba(0,12,20,0.7);
    padding: 8px;
    min-height: 0;
}

.ts-panel-label {
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.45);
    flex-shrink: 0;
    border-bottom: 1px solid rgba(0,200,240,0.08);
    padding-bottom: 4px;
    margin-bottom: 2px;
}

.ts-panel-bracket { color: rgba(0,200,240,0.25); }

.ts-scan-dir {
    flex-shrink: 0;
}

.ts-scan-path {
    font-size: 8px;
    letter-spacing: 0.08em;
    color: rgba(0,200,240,0.5);
}

.ts-scan-files {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.ts-file-row {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 5px 4px;
    cursor: pointer;
    transition: background 0.12s;
    border-bottom: 1px solid rgba(0,200,240,0.05);
}

.ts-file-row:hover  { background: rgba(0,200,240,0.06); }
.ts-file--active    { background: rgba(0,200,240,0.09) !important; }

.ts-file-open {
    color: rgba(0,200,240,0.4);
    font-size: 10px;
    flex-shrink: 0;
}

.ts-file-row:hover .ts-file-open { color: #00c8f0; }

.ts-file-name {
    flex: 1;
    font-size: 10px;
    color: rgba(0,200,240,0.8);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ts-file-meta {
    font-size: 9px;
    color: rgba(0,200,240,0.3);
    flex-shrink: 0;
    white-space: nowrap;
}

.ts-file-lock { font-size: 9px; }

.ts-file-content {
    flex: 1;
    border: 1px solid rgba(0,200,240,0.1);
    padding: 6px 7px;
    background: rgba(0,8,15,0.7);
    overflow-y: auto;
    min-height: 0;
}

.ts-file-text {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: rgba(255,200,80,0.8);
    line-height: 1.7;
    margin: 0;
    white-space: pre-wrap;
    letter-spacing: 0.02em;
}

.ts-scan-close {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 5px 8px;
    border: 1px solid rgba(0,200,240,0.2);
    background: transparent;
    color: rgba(0,200,240,0.45);
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.15s, border-color 0.15s;
}
.ts-scan-close:hover { color: #00c8f0; border-color: rgba(0,200,240,0.5); }

/* No-scan state */
.ts-no-scan {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
    padding: 12px 8px;
}

.ts-no-scan-icon {
    font-size: 28px;
    color: rgba(0,200,240,0.12);
}

.ts-no-scan-line {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.3);
}

.ts-no-scan-sub {
    font-size: 9px;
    color: rgba(0,200,240,0.2);
    line-height: 1.5;
    letter-spacing: 0.03em;
}

/* Left greebles */
.ts-left-greebles {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: auto;
    padding-top: 6px;
    border-top: 1px solid rgba(0,200,240,0.06);
    font-size: 8px;
    color: rgba(0,200,240,0.18);
    letter-spacing: 0.08em;
}

/* ── Center: Fragments ───────────────────────────────────────────────────── */

.ts-center {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
    min-height: 0;
}

.ts-fragment {
    flex: 1;
    border: 1px solid rgba(0,200,240,0.12);
    background: rgba(0,15,25,0.6);
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    justify-content: center;
    transition: border-color 0.2s, background 0.2s;
    min-height: 0;
}

.ts-fragment--solved {
    border-color: rgba(0,255,100,0.28);
    background: rgba(0,25,18,0.5);
}

.ts-frag-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.ts-frag-id {
    font-size: 9px;
    letter-spacing: 0.15em;
    color: rgba(0,200,240,0.5);
}

.ts-frag-badge {
    font-size: 9px;
    letter-spacing: 0.12em;
    flex-shrink: 0;
}

.ts-badge--pending { color: rgba(0,200,240,0.3); }
.ts-badge--ok      { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.5); }

.ts-frag-hint {
    font-size: 12px;
    color: rgba(0,200,240,0.85);
    font-style: italic;
    letter-spacing: 0.02em;
    line-height: 1.45;
}

.ts-frag-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ts-frag-btns {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

/* ── Slots ───────────────────────────────────────────────────────────────── */

.ts-slots {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}

.ts-slot {
    width: 46px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 700;
    border: 1px solid rgba(0,200,240,0.2);
    background: rgba(0,12,22,0.85);
    color: rgba(0,200,240,0.35);
    cursor: pointer;
    transition: border-color 0.12s, color 0.12s, background 0.12s, box-shadow 0.12s;
    user-select: none;
}

.ts-slot--filled {
    color: #00c8f0;
    border-color: rgba(0,200,240,0.5);
    background: rgba(0,28,42,0.9);
}

.ts-slot--droppable:hover {
    border-color: #00c8f0;
    background: rgba(0,200,240,0.1);
    box-shadow: 0 0 10px rgba(0,200,240,0.2);
    cursor: crosshair;
}

/* ── Buttons ─────────────────────────────────────────────────────────────── */

.ts-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.1em;
    padding: 7px 14px;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s, opacity 0.15s;
    white-space: nowrap;
}

.ts-btn--scan {
    color: rgba(0,200,240,0.55);
    border-color: rgba(0,200,240,0.22);
}

.ts-btn--scan:hover,
.ts-btn--scan-active {
    color: #00c8f0;
    border-color: rgba(0,200,240,0.6);
    background: rgba(0,200,240,0.07);
    box-shadow: 0 0 8px rgba(0,200,240,0.12);
}

.ts-btn--inject {
    color: rgba(0,255,100,0.3);
    border-color: rgba(0,255,100,0.12);
    opacity: 0.5;
}

.ts-btn--inject-ready {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.5);
    opacity: 1;
}

.ts-btn--inject-ready:hover {
    background: rgba(0,255,100,0.08);
    box-shadow: 0 0 10px rgba(0,255,100,0.18);
}

.ts-btn:disabled { cursor: not-allowed; opacity: 0.22; }

/* ── Right: Status monitor ───────────────────────────────────────────────── */

.ts-right {
    width: 210px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid rgba(0,200,240,0.1);
    background: rgba(0,10,18,0.7);
    padding: 8px 10px;
    min-height: 0;
}

.ts-stat-block {
    display: flex;
    flex-direction: column;
    gap: 3px;
    border-bottom: 1px solid rgba(0,200,240,0.07);
    padding-bottom: 7px;
}

.ts-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.ts-stat-label {
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(0,200,240,0.4);
}

.ts-stat-val {
    font-size: 14px;
    letter-spacing: 0.04em;
    color: #00c8f0;
    font-weight: 700;
}

.ts-stat-sub {
    font-size: 8px;
    letter-spacing: 0.1em;
    color: rgba(0,200,240,0.28);
    margin-top: 1px;
}

.ts-sub--trace { color: rgba(255,136,0,0.35); }

.ts-val--warn { color: #ffaa00; }
.ts-val--crit { color: #ff3333; animation: ts-pulse 0.6s ease infinite alternate; }
.ts-val--trace { color: #ff7700; }

.ts-stat-bar {
    height: 5px;
    background: rgba(0,200,240,0.07);
    overflow: hidden;
}

.ts-bar-fill { height: 100%; transition: width 0.4s; }

.ts-fill--stab {
    background: linear-gradient(90deg, #003d1a, #00ff9d);
    box-shadow: 0 0 5px rgba(0,255,100,0.3);
}

.ts-fill--trace {
    background: linear-gradient(90deg, #2a0000, #ff4400);
    box-shadow: 0 0 5px rgba(255,50,0,0.3);
}

.ts-pips { display: flex; gap: 7px; }

.ts-pip      { font-size: 18px; transition: color 0.2s; }
.ts-pip--open    { color: rgba(0,200,240,0.25); }
.ts-pip--secured { color: #00ff9d; text-shadow: 0 0 8px rgba(0,255,100,0.5); }

/* Flash messages */
.ts-flash {
    padding: 7px 8px;
    font-size: 10px;
    letter-spacing: 0.1em;
    line-height: 1.5;
    border-left: 2px solid;
    flex-shrink: 0;
}

.ts-flash--wrong {
    border-color: #ff4400;
    color: #ff6633;
    background: rgba(80,10,0,0.3);
}

.ts-flash--ok {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,40,20,0.3);
}

.ts-flash-icon {
    margin-right: 5px;
}

.ts-flash-sub {
    font-size: 9px;
    opacity: 0.65;
}

/* Right greebles */
.ts-right-greebles {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-top: 6px;
    border-top: 1px solid rgba(0,200,240,0.06);
    font-size: 8px;
    color: rgba(0,200,240,0.2);
    letter-spacing: 0.1em;
}

/* ── Pool section ────────────────────────────────────────────────────────── */

.ts-pool-section {
    flex-shrink: 0;
    border-top: 1px solid rgba(0,200,240,0.1);
    padding-top: 5px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ts-pool-bar {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ts-pool-label {
    font-size: 9px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.4);
    flex-shrink: 0;
}

.ts-pool-legend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 9px;
    color: rgba(0,200,240,0.3);
    letter-spacing: 0.06em;
}

.ts-legend-sig   { color: rgba(0,200,240,0.6); }
.ts-legend-noise { color: rgba(255,136,0,0.6); }

.ts-pool-bar-right {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

.ts-pool-hint {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: #00ff9d;
    animation: ts-blink 0.9s ease infinite alternate;
}

.ts-pool-cancel {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(255,80,80,0.6);
    cursor: pointer;
    transition: color 0.15s;
}

.ts-pool-cancel:hover { color: #ff4444; }

.ts-pool {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

/* ── Pool tiles ──────────────────────────────────────────────────────────── */

.ts-tile {
    width: 38px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    border: 1px solid rgba(0,200,240,0.2);
    background: rgba(0,15,28,0.9);
    color: rgba(0,200,240,0.7);
    cursor: pointer;
    transition: border-color 0.1s, background 0.1s, box-shadow 0.1s, opacity 0.1s;
    user-select: none;
}

.ts-tile:hover {
    border-color: rgba(0,200,240,0.55);
    background: rgba(0,200,240,0.07);
}

.ts-tile--selected {
    border-color: #00c8f0;
    background: rgba(0,200,240,0.13);
    box-shadow: 0 0 10px rgba(0,200,240,0.28);
    color: #fff;
}

.ts-tile--used {
    opacity: 0.3;
    cursor: default;
}

.ts-tile--used:hover {
    border-color: rgba(0,200,240,0.2);
    background: rgba(0,15,28,0.9);
}

.ts-tile--noise {
    color: rgba(255,136,0,0.6);
    border-color: rgba(255,136,0,0.18);
    background: rgba(25,8,0,0.9);
}

.ts-tile--noise:hover {
    border-color: rgba(255,136,0,0.4);
    background: rgba(35,12,0,0.9);
}

/* ── Transitions ─────────────────────────────────────────────────────────── */

.ts-flash-enter-active { animation: ts-flash-in 0.15s ease; }
.ts-flash-leave-active { transition: opacity 0.35s; }
.ts-flash-leave-to     { opacity: 0; }

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
