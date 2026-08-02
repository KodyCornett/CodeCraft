<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ae-wrap">

            <!-- Ambient scanline texture — purely decorative -->
            <div class="ae-noise" />

            <!-- Top status bar: TRACE meter + SLOTS DECODED readout -->
            <div class="ae-top">
                <div class="ae-meter-group">
                    <span class="ae-meter-lbl">TRACE</span>
                    <div class="ae-meter-track">
                        <div class="ae-meter-fill" :class="traceFillClass" :style="{ width: (traceLevel * 100) + '%' }" />
                    </div>
                    <span class="ae-meter-val" :class="traceFillClass">{{ Math.round(traceLevel * 100) }}%</span>
                </div>
                <div class="ae-decoded-readout">
                    <span class="ae-decoded-lbl">SLOTS DECODED</span>
                    <span class="ae-decoded-val">{{ solvedCount }} / {{ targets.length }}</span>
                </div>
            </div>

            <!-- Dual window -->
            <div class="ae-dual">

                <!-- Left: File Navigator -->
                <div
                    class="ae-pane"
                    :class="{ 'ae-pane--focused': focusPane === 'nav' }"
                    @click="setFocusPane('nav')"
                >
                    <div class="ae-pane-header">
                        <span class="ae-pane-title">FILE NAVIGATOR</span>
                    </div>
                    <div class="ae-toolbar">
                        <button
                            class="ae-nav-btn"
                            :disabled="!openFileNode && path.length === 0"
                            @click.stop="navBack"
                        >[ &uarr; UP ]</button>
                        <div class="ae-addressbar">
                            <span
                                v-for="(seg, i) in breadcrumbSegments"
                                :key="i"
                                class="ae-addr-seg"
                                :class="{ 'ae-addr-seg--current': i === breadcrumbSegments.length - 1 }"
                                @click.stop="onBreadcrumbClick(i)"
                            >{{ seg }}<span v-if="i < breadcrumbSegments.length - 1" class="ae-addr-sep">/</span></span>
                        </div>
                    </div>
                    <div class="ae-pane-body">
                        <template v-if="openFileNode">
                            <pre class="ae-file-content">{{ openFileNode.content }}</pre>
                        </template>
                        <template v-else>
                            <div
                                v-for="(entry, i) in entries"
                                :key="i"
                                class="ae-nav-row"
                                :class="[
                                    `ae-nav-row--${entry.type}`,
                                    i === navIndex ? 'ae-nav-row--selected' : '',
                                ]"
                                @click.stop="onNavRowClick(i)"
                                @dblclick.stop="onNavRowDblClick(i)"
                            >
                                <template v-if="entry.type === 'dir'">[DIR] {{ entry.node.name }}/</template>
                                <template v-else>&nbsp;&nbsp;&nbsp;&nbsp;{{ entry.node.name }}</template>
                            </div>
                            <div v-if="entries.length === 0" class="ae-empty-msg">// empty directory</div>
                        </template>
                    </div>
                </div>

                <!-- Right: Cipher Decoder -->
                <div
                    class="ae-pane"
                    :class="{ 'ae-pane--focused': focusPane === 'decoder' }"
                    @click="setFocusPane('decoder')"
                >
                    <div class="ae-pane-header">
                        <span class="ae-pane-title ae-pane-title--decoder">CIPHER DECODER</span>
                    </div>
                    <div class="ae-pane-body ae-pane-body--decoder">
                        <div
                            v-for="(t, i) in targets"
                            :key="i"
                            class="ae-slot"
                            :class="{
                                'ae-slot--active': focusPane === 'decoder' && i === activeSlotIndex,
                                'ae-slot--solved': t.solved,
                            }"
                        >
                            <div class="ae-slot-top">
                                <span class="ae-slot-label">SLOT_0{{ i + 1 }}</span>
                                <span v-if="t.solved" class="ae-slot-badge">✔ DECODED</span>
                            </div>
                            <div class="ae-slot-target">
                                TARGET: <span class="ae-slot-b64">'{{ t.b64 }}'</span>
                            </div>
                            <div class="ae-slot-controls">
                                <input
                                    :ref="el => setSlotInputRef(el, i)"
                                    v-model="slotGuesses[i]"
                                    class="ae-slot-input"
                                    :class="{ 'ae-slot-input--shake': shakeFlags[i] }"
                                    :disabled="t.solved || !!gameResult"
                                    placeholder="deduced plaintext..."
                                    autocomplete="off"
                                    spellcheck="false"
                                    @focus="onSlotFocus(i)"
                                    @keydown.up.prevent="moveSlot(-1)"
                                    @keydown.down.prevent="moveSlot(1)"
                                    @keyup.enter="submitSlot(i)"
                                />
                                <button
                                    class="ae-slot-submit"
                                    :disabled="t.solved || !!gameResult"
                                    @click="submitSlot(i)"
                                >[ DECODE ]</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Key hint footer -->
            <div class="ae-hints">
                [ TAB ] SWITCH PANE &nbsp;&middot;&nbsp; [ &uarr; &darr; ] NAVIGATE &nbsp;&middot;&nbsp; [ ENTER ] OPEN / SUBMIT &nbsp;&middot;&nbsp; [ ESC ] BACK
            </div>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';

/* ════════════════════════════════════════════════════════════════════════
   ARCHIVE EXTRACTION — deduction game.
   Read scattered log files in a procedurally generated directory tree to
   find plaintext/Base64 pairs, match each pair's ciphertext to one of three
   target cipher slots, and decode all three before Trace Level hits 100%.
   Decoy files carry noise, corrupted checksums, or real-looking pairs for
   words that AREN'T targets — the read has to be careful, not just fast.
   ════════════════════════════════════════════════════════════════════════ */

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Difficulty config ─────────────────────────────────────────────────────────
// D1: fewer files, fewer red-herring pairs, generous trace budget.
// D2: more files and red herrings, tighter budget.
// D3: max file count and red herrings, tightest budget — every open file and
//     every guess costs something.
const CONFIGS = {
    1: { totalFilesRange: [5, 6], decoyPairCount: 2, traceDurationS: 150, wrongGuessPenalty: 0.03 },
    2: { totalFilesRange: [6, 7], decoyPairCount: 3, traceDurationS: 120, wrongGuessPenalty: 0.04 },
    3: { totalFilesRange: [7, 8], decoyPairCount: 4, traceDurationS: 95,  wrongGuessPenalty: 0.05 },
};

const diffLevel = props.skin.difficulty ?? 1;
const config    = CONFIGS[diffLevel] ?? CONFIGS[1];

// ── Word / flavor pools ────────────────────────────────────────────────────────

const WORDS = [
    'ghost', 'trace', 'vault', 'cipher', 'shadow', 'signal', 'breach', 'static',
    'wraith', 'axiom', 'uplink', 'kernel', 'daemon', 'router', 'packet', 'proxy',
    'nexus', 'splice', 'cache', 'relay', 'forge', 'byte',
];

const FOLDER_NAMES = [
    'sys_cache', 'audit_trail', 'user_partitions', 'archive_root', 'ghost_sectors',
    'corp_backups', 'watchdog_logs', 'node_diagnostics', 'residual_data',
    'session_vault', 'ice_quarantine', 'packet_captures', 'legacy_shard',
];

const FILE_NAMES = [
    'session.log', 'auth_trace.log', 'node_diag.txt', 'packet_dump.log',
    'watchdog.log', 'residual.dat', 'handshake.log', 'cache_flush.txt',
    'audit_01.log', 'sector_scan.dat', 'trace_report.log', 'checksum.dat',
    'link_status.log', 'archive_index.txt',
];

// ── Utilities ──────────────────────────────────────────────────────────────────

function randInt(min, max) { return Math.floor(min + Math.random() * (max - min + 1)); }
function pick(arr) { return arr[randInt(0, arr.length - 1)]; }

function sampleDistinct(arr, n) {
    const pool = [...arr];
    const out = [];
    while (out.length < n && pool.length > 0) {
        out.push(pool.splice(randInt(0, pool.length - 1), 1)[0]);
    }
    return out;
}

function ts() {
    const h = String(randInt(0, 23)).padStart(2, '0');
    const m = String(randInt(0, 59)).padStart(2, '0');
    const s = String(randInt(0, 59)).padStart(2, '0');
    return `${h}:${m}:${s}`;
}
function hex(n) { return Array.from({ length: n }, () => randInt(0, 15).toString(16)).join('').toUpperCase(); }
function arcAddr()   { return `ARC.10.${randInt(0, 255)}.${randInt(0, 255)}`; }
function ghostAddr() { return `GHK.00.${randInt(0, 255)}.${randInt(0, 255)}`; }

// ── Flavor line generation (decoy / ambient content) ──────────────────────────

const FLAVOR_GENERATORS = [
    () => `[${ts()}] watchdog: link nominal`,
    () => `[${ts()}] handshake retry — packet malformed`,
    () => `[${ts()}] ICE_SWEEP // ${arcAddr()} — no match`,
    () => `[${ts()}] GHOST_ECHO // ${ghostAddr()} — dismissed`,
    () => `%$#@ CORRUPT SECTOR 0x${hex(4)} — recovery failed`,
    () => `ENCODED: '${hex(6)}==' [CHECKSUM FAIL]`,
    () => `[${ts()}] node integrity nominal`,
    () => `[${ts()}] session idle — no anomalies`,
    () => `// SPLICE AUDIT — fragment ${hex(3)}`,
    () => `[${ts()}] archive index rebuilt (${randInt(100, 999)} entries)`,
    () => `[${ts()}] trace vector unresolved`,
    () => `>>> buffer flush complete`,
    () => `[${ts()}] cache miss — sector 0x${hex(3)}`,
    () => `[${ts()}] axiom relay: heartbeat ok`,
    () => `#### fragment unreadable — skipping ####`,
];

function sampleFlavorLines(min, max) {
    return Array.from({ length: randInt(min, max) }, () => pick(FLAVOR_GENERATORS)());
}
function insertAtRandomPosition(lines, extra) {
    const copy = [...lines];
    copy.splice(randInt(0, copy.length), 0, extra);
    return copy;
}
function buildTargetContent(word, b64) {
    return insertAtRandomPosition(sampleFlavorLines(3, 5), `TOKEN: '${word}' | ENCODED: '${b64}'`).join('\n');
}
function buildFakePairContent(word, b64) {
    return insertAtRandomPosition(sampleFlavorLines(2, 4), `TOKEN: '${word}' | ENCODED: '${b64}'`).join('\n');
}
function buildNoiseContent() {
    return sampleFlavorLines(4, 6).join('\n');
}

// ── Procedural directory tree ─────────────────────────────────────────────────
// Builds a 2-3 level folder tree, then scatters 3 target log files (each
// holding one TOKEN/ENCODED pair matching a cipher slot) plus decoy files
// (fake pairs for non-target words, or pure noise) across it.

function buildFolderTree() {
    const used = new Set();
    function folderName() {
        let n;
        do { n = pick(FOLDER_NAMES); } while (used.has(n) && used.size < FOLDER_NAMES.length);
        used.add(n);
        return n;
    }

    const rootNode = { name: 'root', type: 'dir', children: [] };
    const topCount = randInt(2, 3);
    for (let i = 0; i < topCount; i++) {
        const folder = { name: folderName(), type: 'dir', children: [] };
        rootNode.children.push(folder);
        if (Math.random() < 0.85) {
            const subCount = randInt(1, 2);
            for (let j = 0; j < subCount; j++) {
                const sub = { name: folderName(), type: 'dir', children: [] };
                folder.children.push(sub);
                if (Math.random() < 0.25) {
                    sub.children.push({ name: folderName(), type: 'dir', children: [] });
                }
            }
        }
    }
    return rootNode;
}

function flattenFolders(node, acc) {
    acc = acc || [];
    acc.push(node);
    node.children.forEach(c => { if (c.type === 'dir') flattenFolders(c, acc); });
    return acc;
}

function uniqueFileNameFor(folder) {
    const existing = new Set(folder.children.filter(c => c.type === 'file').map(c => c.name));
    let candidates = FILE_NAMES.filter(n => !existing.has(n));
    if (candidates.length === 0) candidates = FILE_NAMES;
    let name = pick(candidates);
    let suffix = 1;
    while (existing.has(name)) {
        name = `${pick(FILE_NAMES).replace(/\.[^.]+$/, '')}_${suffix}.log`;
        suffix++;
    }
    return name;
}

function generateWorld() {
    const rootNode   = buildFolderTree();
    const allFolders = flattenFolders(rootNode);

    const targetWords   = sampleDistinct(WORDS, 3);
    const remainingPool = WORDS.filter(w => !targetWords.includes(w));
    const decoyPairWords = sampleDistinct(remainingPool, Math.min(config.decoyPairCount, remainingPool.length));

    const totalFiles = randInt(config.totalFilesRange[0], config.totalFilesRange[1]);

    // Scatter target files across distinct folders rather than clumping them.
    let targetFolders = sampleDistinct(allFolders, Math.min(3, allFolders.length));
    while (targetFolders.length < 3) targetFolders.push(allFolders[targetFolders.length % allFolders.length]);

    const targets = targetWords.map((word, i) => {
        const b64    = btoa(word);
        const folder = targetFolders[i];
        folder.children.push({
            name: uniqueFileNameFor(folder),
            type: 'file',
            kind: 'target',
            word, b64,
            content: buildTargetContent(word, b64),
        });
        return { word, b64, solved: false };
    });

    const remainingCount = Math.max(0, totalFiles - 3);
    for (let i = 0; i < remainingCount; i++) {
        const folder = pick(allFolders);
        let content, kind;
        if (decoyPairWords.length > 0 && Math.random() < 0.5) {
            const w = pick(decoyPairWords);
            content = buildFakePairContent(w, btoa(w));
            kind = 'decoy-pair';
        } else {
            content = buildNoiseContent();
            kind = 'decoy-noise';
        }
        folder.children.push({ name: uniqueFileNameFor(folder), type: 'file', kind, content });
    }

    return { root: rootNode, targets };
}

// ── Game state ─────────────────────────────────────────────────────────────────

const world = generateWorld();
const root  = world.root; // static once generated — plain object, no reactivity needed

const targets = ref(world.targets);          // [{ word, b64, solved }]
const path        = ref([]);                 // child-index chain from root
const openFileNode = ref(null);              // file node currently being read, or null
const navIndex     = ref(0);
const focusPane     = ref('nav');            // 'nav' | 'decoder'
const activeSlotIndex = ref(0);
const slotGuesses  = ref(targets.value.map(() => ''));
const shakeFlags   = ref(targets.value.map(() => false));

const traceLevel  = ref(0);                  // 0..1
const gameResult  = ref(null);                // null | 'success' | 'fail'
const failReason  = ref('');
const glitchPulse = ref(false);

let tickHandle = null;
const slotInputEls = []; // DOM refs, keyed by slot index — not reactive state

// ── Tree navigation ────────────────────────────────────────────────────────────

function resolveFolder(pathArr) {
    let node = root;
    for (const idx of pathArr) node = node.children[idx];
    return node;
}

// Explorer-style address bar: one clickable segment per folder level, plus
// the open file's name as a trailing (non-navigable) segment when reading one.
const breadcrumbSegments = computed(() => {
    let node = root;
    const parts = ['root'];
    for (const idx of path.value) { node = node.children[idx]; parts.push(node.name); }
    if (openFileNode.value) parts.push(openFileNode.value.name);
    return parts;
});

function onBreadcrumbClick(i) {
    const fileSegmentIndex = openFileNode.value ? breadcrumbSegments.value.length - 1 : -1;
    if (i === fileSegmentIndex) return; // clicking the file's own trailing segment is a no-op
    path.value = path.value.slice(0, i); // i=0 -> root
    navIndex.value = 0;
    openFileNode.value = null;
}

const entries = computed(() => {
    const folder = resolveFolder(path.value);
    return folder.children.map((c, idx) => ({ type: c.type, node: c, idx }));
});

function moveNav(delta) {
    if (openFileNode.value) return; // reading a file — arrows don't scroll the list
    const list = entries.value;
    if (list.length === 0) return;
    navIndex.value = (navIndex.value + delta + list.length) % list.length;
}

function activateNavEntry() {
    const entry = entries.value[navIndex.value];
    if (!entry) return;
    if (entry.type === 'dir') {
        path.value.push(entry.idx);
        navIndex.value = 0;
        openFileNode.value = null;
    } else if (entry.type === 'file') {
        openFileNode.value = entry.node;
    }
}

// Single control for "go up one level" — closes an open file first if one's
// open, otherwise pops one folder level. Bound to the toolbar's UP button,
// Escape, and Backspace so there's always an obvious, reachable way back.
function navBack() {
    if (openFileNode.value) {
        openFileNode.value = null;
    } else if (path.value.length > 0) {
        path.value.pop();
        navIndex.value = 0;
    }
}

// Single click selects a row (Explorer convention); double-click or Enter opens it.
function onNavRowClick(i) {
    setFocusPane('nav');
    navIndex.value = i;
}

function onNavRowDblClick(i) {
    setFocusPane('nav');
    navIndex.value = i;
    activateNavEntry();
}

// ── Cipher decoder ─────────────────────────────────────────────────────────────

const solvedCount = computed(() => targets.value.filter(t => t.solved).length);

function setSlotInputRef(el, i) { if (el) slotInputEls[i] = el; }

function ensureActiveSlotValid() {
    const n = targets.value.length;
    let i = activeSlotIndex.value;
    let attempts = 0;
    while (targets.value[i].solved && attempts < n) { i = (i + 1) % n; attempts++; }
    activeSlotIndex.value = i;
}

function focusActiveSlotInput() {
    const el = slotInputEls[activeSlotIndex.value];
    if (el && !el.disabled) el.focus();
}

function moveSlot(delta) {
    const n = targets.value.length;
    let i = activeSlotIndex.value;
    for (let attempts = 0; attempts < n; attempts++) {
        i = (i + delta + n) % n;
        if (!targets.value[i].solved) break;
    }
    activeSlotIndex.value = i;
    focusActiveSlotInput();
}

function onSlotFocus(i) {
    focusPane.value = 'decoder';
    activeSlotIndex.value = i;
}

function triggerShake(i) {
    shakeFlags.value[i] = true;
    glitchPulse.value   = true;
    setTimeout(() => {
        shakeFlags.value[i] = false;
        glitchPulse.value   = false;
    }, 400);
}

function submitSlot(i) {
    if (gameResult.value) return;
    const target = targets.value[i];
    if (target.solved) return;

    const guess = (slotGuesses.value[i] || '').trim().toLowerCase();
    if (guess.length === 0) return;

    if (guess === target.word) {
        target.solved = true;
        slotGuesses.value[i] = target.word;
        if (targets.value.every(t => t.solved)) {
            endGame('success', '');
        } else {
            moveSlot(1);
        }
    } else {
        triggerShake(i);
        bumpTrace(config.wrongGuessPenalty);
    }
}

// ── Pane focus ─────────────────────────────────────────────────────────────────

function setFocusPane(pane) {
    if (focusPane.value === pane) {
        if (pane === 'decoder') focusActiveSlotInput();
        return;
    }
    focusPane.value = pane;
    if (pane === 'nav') {
        if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
    } else {
        ensureActiveSlotValid();
        focusActiveSlotInput();
    }
}

function togglePane() {
    setFocusPane(focusPane.value === 'nav' ? 'decoder' : 'nav');
}

// ── Trace level ────────────────────────────────────────────────────────────────

const traceFillClass = computed(() => {
    if (traceLevel.value >= 0.85) return 'ae-meter--critical';
    if (traceLevel.value >= 0.6)  return 'ae-meter--warn';
    return '';
});

// Seconds remaining before ICE lockdown at the current trace rate — a live,
// accurate estimate (not a fixed countdown), since wrong guesses spike it.
const estimatedTimeToLockdown = computed(() =>
    Math.max(0, (1 - traceLevel.value) * config.traceDurationS)
);

const timerClass = computed(() => {
    if (traceLevel.value >= 0.85) return 'timer--critical';
    if (traceLevel.value >= 0.6)  return 'timer--warn';
    return '';
});

function bumpTrace(amount) {
    traceLevel.value = Math.min(1, traceLevel.value + amount);
    if (traceLevel.value >= 1) {
        endGame('fail', '[ICE LOCK] — Trace vector resolved. Location compromised.');
    }
}

// ── Win / lose ─────────────────────────────────────────────────────────────────

function endGame(result, reason) {
    if (gameResult.value) return;
    gameResult.value = result;
    failReason.value = reason ?? '';
    if (tickHandle) clearInterval(tickHandle);
    if (result === 'success') setTimeout(() => emit('complete'), 2200);
    else setTimeout(() => emit('fail'), 2200);
}

// ── Chrome passthrough ─────────────────────────────────────────────────────────
// Bars are hidden — TRACE / SLOTS DECODED readouts above are purpose-built for
// this game instead of the generic trace/stability pair (same approach as
// CipherLock and ToxicSoak use for their own custom layouts).

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        estimatedTimeToLockdown.value,
    primaryProgress: 0,
    stability:       1,
    stabilityClass:  '',
    timerClass:      timerClass.value,
    glitchActive:    glitchPulse.value,
    glitchType:      'static',
    glitchIntensity: 0.15,
    result:          gameResult.value,
    failReason:      failReason.value,
    hideBars:        true,
}));

// ── Tick ───────────────────────────────────────────────────────────────────────

const TICK_MS = 200;

function startTrace() {
    const tickAmount = (TICK_MS / 1000) / config.traceDurationS;
    tickHandle = setInterval(() => {
        if (gameResult.value) return;
        bumpTrace(tickAmount);
    }, TICK_MS);
}

// ── Global keyboard — Tab always; nav-pane Arrows/Enter/Escape only when the
//    nav pane is focused. Decoder-pane Arrows/Enter are bound on the slot
//    inputs directly (see template) so normal typing isn't intercepted here.

function onGlobalKeydown(e) {
    if (gameResult.value) return;
    if (e.key === 'Tab') { e.preventDefault(); togglePane(); return; }
    if (focusPane.value !== 'nav') return;

    if (e.key === 'ArrowDown') { e.preventDefault(); moveNav(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); moveNav(-1); }
    else if (e.key === 'Enter') { e.preventDefault(); activateNavEntry(); }
    else if (e.key === 'Escape' || e.key === 'Backspace') { e.preventDefault(); navBack(); }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    document.addEventListener('keydown', onGlobalKeydown);
    startTrace();
});

onUnmounted(() => {
    document.removeEventListener('keydown', onGlobalKeydown);
    if (tickHandle) clearInterval(tickHandle);
});
</script>

<style scoped>
.ae-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 12px 20px;
    box-sizing: border-box;
    gap: 8px;
    font-family: 'JetBrains Mono', monospace;
    position: relative;
}

.ae-noise {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px,
        rgba(0,255,100,0.008) 2px, rgba(0,255,100,0.008) 4px);
}

/* ── Top status bar ───────────────────────────────────────────────────────────── */

.ae-top {
    display: flex;
    align-items: center;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.ae-meter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.ae-meter-lbl {
    font-size: 8px;
    letter-spacing: 0.15em;
    color: rgba(255,50,50,0.5);
    flex-shrink: 0;
}

.ae-meter-track {
    flex: 1;
    height: 6px;
    background: rgba(0,255,100,0.06);
    overflow: hidden;
}

.ae-meter-fill {
    height: 100%;
    background: linear-gradient(90deg, #003322, #00ff9d);
    box-shadow: 0 0 8px rgba(0,255,100,0.3);
    transition: width 0.2s linear, background 0.3s;
}

.ae-meter-fill.ae-meter--warn     { background: linear-gradient(90deg, #4a1500, #fb923c); }
.ae-meter-fill.ae-meter--critical {
    background: linear-gradient(90deg, #4a0000, #ff3333);
    animation: ae-meter-pulse 0.5s ease infinite alternate;
}

.ae-meter-val {
    font-size: 10px;
    color: rgba(0,255,100,0.5);
    width: 34px;
    text-align: right;
    flex-shrink: 0;
}
.ae-meter-val.ae-meter--warn     { color: #fb923c; }
.ae-meter-val.ae-meter--critical { color: #ff3333; }

.ae-decoded-readout {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.ae-decoded-lbl { font-size: 8px; letter-spacing: 0.15em; color: rgba(0,255,100,0.35); }
.ae-decoded-val { font-size: 11px; color: rgba(0,255,100,0.7); }

@keyframes ae-meter-pulse {
    from { box-shadow: 0 0 6px rgba(255,51,51,0.4); }
    to   { box-shadow: 0 0 16px rgba(255,51,51,0.8); }
}

/* ── Dual window ──────────────────────────────────────────────────────────────── */

.ae-dual {
    flex: 1;
    display: flex;
    gap: 10px;
    min-height: 0;
    position: relative;
    z-index: 1;
}

.ae-pane {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
    border: 1px solid rgba(0,255,100,0.15);
    background: rgba(0,10,6,0.4);
    transition: border-color 0.2s, box-shadow 0.2s;
    cursor: default;
}

.ae-pane--focused {
    border-color: rgba(0,255,100,0.6);
    box-shadow: inset 0 0 30px rgba(0,255,100,0.04), 0 0 16px rgba(0,255,100,0.08);
}

.ae-pane-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-bottom: 1px solid rgba(0,255,100,0.1);
    flex-shrink: 0;
}

.ae-pane-title { font-size: 9px; letter-spacing: 0.16em; color: rgba(0,255,100,0.5); }
.ae-pane-title--decoder { color: rgba(255,50,50,0.5); }

.ae-pane-body {
    flex: 1;
    overflow-y: auto;
    padding: 6px 8px;
    font-size: 11px;
    line-height: 1.6;
}

/* ── Explorer-style toolbar: UP button + clickable address bar ─────────────────── */

.ae-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border-bottom: 1px solid rgba(0,255,100,0.1);
    flex-shrink: 0;
}

.ae-nav-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.35);
    color: rgba(0,255,100,0.75);
    padding: 3px 9px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.1s;
}
.ae-nav-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}
.ae-nav-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.ae-addressbar {
    flex: 1;
    min-width: 0;
    overflow-x: auto;
    white-space: nowrap;
    font-size: 9px;
}

.ae-addr-seg {
    color: rgba(0,255,100,0.45);
    cursor: pointer;
}
.ae-addr-seg:hover { color: #00ff9d; text-decoration: underline; }
.ae-addr-seg--current {
    color: rgba(0,255,100,0.7);
    cursor: default;
}
.ae-addr-seg--current:hover { text-decoration: none; }
.ae-addr-sep { color: rgba(0,255,100,0.2); margin: 0 3px; cursor: default; }

/* ── File navigator rows ──────────────────────────────────────────────────────── */

.ae-nav-row {
    padding: 1px 6px;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.1s, color 0.1s;
    user-select: none;
}
.ae-nav-row:hover { background: rgba(0,255,100,0.06); }

.ae-nav-row--dir  { color: rgba(0,255,100,0.75); }
.ae-nav-row--file { color: rgba(0,255,100,0.55); }

.ae-nav-row--selected {
    background: rgba(0,255,100,0.12);
    box-shadow: inset 2px 0 0 #00ff9d;
}

.ae-empty-msg {
    font-size: 9px;
    color: rgba(0,255,100,0.2);
    padding: 10px 0;
    text-align: center;
}

.ae-file-content {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    line-height: 1.7;
    color: rgba(0,255,100,0.7);
    white-space: pre-wrap;
    word-break: break-all;
    margin: 0;
}

/* ── Cipher decoder ───────────────────────────────────────────────────────────── */

.ae-pane-body--decoder {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ae-slot {
    border: 1px solid rgba(0,255,100,0.15);
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}

.ae-slot--active {
    border-color: rgba(0,255,100,0.65);
    box-shadow: 0 0 12px rgba(0,255,100,0.15);
}

.ae-slot--solved {
    border-color: rgba(0,255,100,0.5);
    background: rgba(0,255,100,0.05);
}

.ae-slot-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.ae-slot-label { font-size: 9px; letter-spacing: 0.12em; color: rgba(0,255,100,0.4); }

.ae-slot-badge {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: #00ff9d;
    border: 1px solid rgba(0,255,100,0.4);
    padding: 1px 6px;
}

.ae-slot-target { font-size: 11px; color: rgba(0,255,100,0.7); }
.ae-slot-b64 { color: #fb923c; }

.ae-slot-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ae-slot-input {
    flex: 1;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(0,255,100,0.25);
    color: #00ff9d;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    padding: 5px 8px;
}
.ae-slot-input:focus { outline: none; border-color: #00ff9d; }
.ae-slot-input:disabled {
    color: rgba(0,255,100,0.7);
    border-color: rgba(0,255,100,0.4);
    background: rgba(0,255,100,0.05);
}

.ae-slot-input--shake { animation: ae-shake 0.35s ease; }
@keyframes ae-shake {
    0%, 100% { transform: translateX(0); border-color: rgba(0,255,100,0.25); }
    20%, 60% { transform: translateX(-4px); border-color: #ff3333; }
    40%, 80% { transform: translateX(4px); border-color: #ff3333; }
}

.ae-slot-submit {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 5px 10px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.1s;
}
.ae-slot-submit:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}
.ae-slot-submit:disabled { opacity: 0.3; cursor: not-allowed; }

/* ── Key hint footer ──────────────────────────────────────────────────────────── */

.ae-hints {
    font-size: 8px;
    letter-spacing: 0.08em;
    color: rgba(0,255,100,0.22);
    text-align: center;
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
</style>
