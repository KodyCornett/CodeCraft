<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="ts-wrap" tabindex="0" @keydown.prevent.stop="onKey" ref="wrapRef">

            <!-- ── HUD ─────────────────────────────────────────────────────────── -->
            <div class="ts-hud">
                <div class="ts-hud-block">
                    <span class="ts-hud-label">FRAGMENT</span>
                    <span class="ts-hud-val">{{ currentIdx + 1 }} / {{ fragments.length }}</span>
                </div>
                <div class="ts-hud-block ts-hud-center">
                    <span class="ts-hud-label">TRACE ATTEMPTS</span>
                    <div class="ts-pips">
                        <span
                            v-for="i in maxTrace"
                            :key="i"
                            class="ts-pip"
                            :class="i <= traceCount ? 'ts-pip--burned' : 'ts-pip--free'"
                        >◉</span>
                    </div>
                </div>
                <div class="ts-hud-block ts-hud-right">
                    <span class="ts-hud-label">STABILITY</span>
                    <span class="ts-hud-val" :class="localStabClass">{{ Math.round(stability * 100) }}%</span>
                </div>
            </div>

            <!-- ── Puzzle area ─────────────────────────────────────────────── -->
            <div v-if="!passphraseRevealed && currentFragment" class="ts-puzzle">

                <div class="ts-cipher-block">
                    <span class="ts-cipher-tag">// CIPHER_FRAGMENT_{{ String(currentIdx + 1).padStart(2, '0') }}</span>
                    <p class="ts-cipher-hint">{{ currentFragment.hint }}</p>
                </div>

                <!-- Letter slots -->
                <div class="ts-slots-wrap">
                    <div
                        v-for="(letter, i) in slots"
                        :key="i"
                        class="ts-slot"
                        :class="{ 'ts-slot--active': i === cursorPos }"
                    >{{ letter }}</div>
                </div>

                <div class="ts-legend">
                    <span>← → CURSOR</span>
                    <span>↑ MOVE LEFT</span>
                    <span>↓ MOVE RIGHT</span>
                    <span>ENTER SUBMIT</span>
                </div>

                <!-- Wrong flash -->
                <Transition name="ts-flash">
                    <div v-if="showWrong" class="ts-wrong-flash">
                        [ TRACE DETECTED — SEQUENCE REJECTED ]
                    </div>
                </Transition>

                <!-- Correct flash -->
                <Transition name="ts-flash">
                    <div v-if="showCorrect" class="ts-correct-flash">
                        [ FRAGMENT RECOVERED — SEQUENCE CONFIRMED ]
                    </div>
                </Transition>

                <!-- Actions -->
                <div class="ts-actions">
                    <button class="ts-btn ts-btn--submit" @click="submitFragment">[ SUBMIT ]</button>
                    <button
                        class="ts-btn ts-btn--clue"
                        :disabled="clueVisible || stability <= clueCost + 0.01"
                        :class="{ 'ts-btn--disabled': clueVisible || stability <= clueCost + 0.01 }"
                        @click="purchaseClue"
                    >[ DECRYPT FILE // −{{ clueCostPct }}% STAB ]</button>
                </div>

                <!-- Clue panel -->
                <Transition name="ts-clue">
                    <div v-if="clueVisible" class="ts-clue-panel">
                        <div class="ts-clue-tag">// RECOVERED_FILE // FRAGMENT_{{ String(currentIdx + 1).padStart(2, '0') }}</div>
                        <pre class="ts-clue-body">{{ currentFragment.clue }}</pre>
                    </div>
                </Transition>

            </div>

            <!-- ── Solved log ───────────────────────────────────────────────── -->
            <div v-if="solvedWords.length" class="ts-solved">
                <span class="ts-solved-tag">// RECOVERED //</span>
                <div class="ts-solved-words">
                    <span v-for="(w, i) in solvedWords" :key="i" class="ts-solved-word">{{ w }}</span>
                </div>
            </div>

            <!-- ── Passphrase reveal ────────────────────────────────────────── -->
            <div v-if="passphraseRevealed" class="ts-passphrase">
                <div class="ts-pp-tag">// PASSPHRASE_RECONSTRUCTED //</div>
                <div class="ts-pp-words">
                    <div v-for="(w, i) in solvedWords" :key="i" class="ts-pp-word" :style="{ animationDelay: i * 0.18 + 's' }">
                        <span class="ts-pp-arrow">&gt;</span> {{ w }}
                    </div>
                </div>
                <div class="ts-pp-footer">// CONNECTION: ESTABLISHED //</div>
            </div>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

// ── Letter sentence bank ───────────────────────────────────────────────────────
// Each sentence starts with the keyed letter — used to generate acrostic clue files.
// Players who notice the first-letter pattern get the answer; the game never highlights it.

const LETTER_SENTENCES = {
    A: [
        "Access logs purged before the audit window opened.",
        "Authentication token expired mid-session, unnoticed.",
        "Anomalous traffic pattern detected at the outer perimeter.",
        "Archive flagged for deletion but never actually removed.",
    ],
    B: [
        "Buffer overflow at sector 7-B confirmed by diagnostic.",
        "Bandwidth throttled without a documented reason.",
        "Backup integrity: unverified for three consecutive cycles.",
        "Breach attempt logged, origin successfully masked.",
    ],
    C: [
        "Cache cleared on an undocumented maintenance schedule.",
        "Connection terminated before the handshake could complete.",
        "Credentials rotated on paper — old keys still active.",
        "Cascade failure isolated to secondary systems only.",
    ],
    D: [
        "Data exfiltrated before the purge cycle ran.",
        "Dead node still accepting inbound packets.",
        "Diagnostic logs corrupted at the primary entry point.",
        "Downtime occurred without prior scheduling. Duration unknown.",
    ],
    E: [
        "Encryption layer bypassed at the handshake stage.",
        "Error logged but never escalated to the response team.",
        "Endpoint authentication: disabled on legacy hardware.",
        "Exfiltration completed eleven seconds before the alert triggered.",
    ],
    F: [
        "Firewall ruleset modified without a change ticket.",
        "Forensic data: incomplete. Chain of custody: broken.",
        "Frequency shifted outside the monitored band.",
        "File access flagged as unauthorized. Action: none taken.",
    ],
    G: [
        "Grid access point left unlocked after maintenance.",
        "Ghost session persists seventeen hours after logout.",
        "Governance audit overdue by two full quarters.",
        "Gap in perimeter coverage located and mapped.",
    ],
    H: [
        "Hardware ID spoofed at the point of network entry.",
        "Hash collision confirmed. Patch deferred indefinitely.",
        "Handshake intercepted. Contents extracted before re-encryption.",
        "History purged. Documented reason: none.",
    ],
    I: [
        "Identity verification skipped on the legacy endpoint.",
        "Injection vector open on an unpatched production system.",
        "Incident response delayed by forty minutes.",
        "Invisible traffic routed through an unmapped dead zone.",
    ],
    J: [
        "Junction node taken offline. Traffic rerouted silently.",
        "Job scheduler modified without authorization.",
        "Junk data masking an active signal in sector four.",
    ],
    K: [
        "Key rotation delayed for a third consecutive cycle.",
        "Known vulnerability unpatched. Risk formally accepted.",
        "Kill switch location: undocumented.",
        "Kernel logs show access events prior to authentication.",
    ],
    L: [
        "Latency spike lasted long enough to mask the intrusion.",
        "Log entry missing. Unexplained window: three minutes.",
        "Legacy system unmaintained but still load-bearing.",
        "Lookup table modified. Change author: none on record.",
    ],
    M: [
        "Memory dump captured before the session was terminated.",
        "MAC address spoofed on every inbound packet.",
        "Maintenance window exploited for unauthorized access.",
        "Masked origin confirmed. Actual destination: verified.",
    ],
    N: [
        "Node deactivated in the system but still visible on the map.",
        "No alert triggered. Detection threshold set too high.",
        "Network segment isolated without documentation.",
        "Null response returned from the authentication server.",
    ],
    O: [
        "Outbound traffic spike: cause undetermined.",
        "Old credentials still accepted by the legacy endpoint.",
        "Origin address confirmed as spoofed.",
        "Overflow condition leveraged as an entry vector.",
    ],
    P: [
        "Patch applied. Underlying vulnerability: unchanged.",
        "Permission set modified without a recoverable audit trail.",
        "Port left open on an external-facing production system.",
        "Packet loss in this sector was deliberate.",
    ],
    Q: [
        "Queue depth exceeded. Packets dropped without notification.",
        "Query cache poisoned at an upstream resolver.",
        "Quarantine failed. Containment achieved at partial scope only.",
    ],
    R: [
        "Remote access enabled without a submitted request.",
        "Recovery point corrupted. Full rollback: not possible.",
        "Routing table modified at the border node.",
        "Residual data confirmed in a sector marked as wiped.",
    ],
    S: [
        "Session token reused across multiple authenticated users.",
        "Signal intercepted before the encryption layer was applied.",
        "Scan completed before the alert threshold could trigger.",
        "Source address: unresolvable by any registered nameserver.",
    ],
    T: [
        "Timestamp mismatch found in the primary access log.",
        "Traffic rerouted through an unmonitored internal node.",
        "Token expiry bypassed on a legacy authentication endpoint.",
        "Trace completed. Origin: successfully masked.",
    ],
    U: [
        "Unauthorized access logged but never reviewed.",
        "Update failed without producing an error state.",
        "User account remained active forty days after termination.",
        "Upstream dependency quietly compromised.",
    ],
    V: [
        "Vulnerability disclosed internally. Fix: indefinitely deferred.",
        "VPN tunnel established to an unregistered endpoint.",
        "Verification step silently removed from the login sequence.",
        "Volume of traffic inconsistent with the number of logged users.",
    ],
    W: [
        "Watchdog process killed before the alert could fire.",
        "Write permissions granted to a read-only service account.",
        "Window of total exposure: seventeen unmonitored minutes.",
        "Whitelist entry added without a corresponding approval.",
    ],
    X: [
        "X-sector logs unavailable for review. Reason: unspecified.",
        "External endpoint absent from the registered asset inventory.",
    ],
    Y: [
        "Years-old vulnerability confirmed still present in production.",
        "Yesterday's backup: failed to restore without error.",
    ],
    Z: [
        "Zero-day confirmed in the core authentication library.",
        "Zone boundary traversal went undetected for six days.",
    ],
};

// ── Word clusters ──────────────────────────────────────────────────────────────
// Each cluster is a theme. The puzzle picks one word + one hint per cluster.
// Hints are thematic but non-specific — multiple words in the cluster could fit,
// so players cannot wiki a hint→answer mapping.

const CLUSTERS = [
    {
        words: ['VOID', 'NULL', 'GONE', 'WIPE'],
        hints: [
            "What the corporation leaves in an emptied archive.",
            "The state after a hard deletion.",
            "What ICE does to a runner's footprint.",
            "A field that was never meant to hold anything.",
        ],
    },
    {
        words: ['ECHO', 'PING', 'WAVE', 'PULSE'],
        hints: [
            "What SPLICE runs on beneath everything else.",
            "The heartbeat of a channel that should be dead.",
            "A transmission with no source on record.",
            "What you send when you're not sure anyone is listening.",
        ],
    },
    {
        words: ['GHOST', 'MASK', 'ALIAS', 'TRACE'],
        hints: [
            "What a runner leaves behind in a system they never entered.",
            "The layer between a person and their credential.",
            "What corporations pay to erase from their logs.",
            "Everything you are in a system that doesn't know you.",
        ],
    },
    {
        words: ['NODE', 'LINK', 'BRIDGE', 'SPLICE'],
        hints: [
            "Where the things that shouldn't move get routed anyway.",
            "The connection that isn't supposed to exist.",
            "Infrastructure that outlived the organization that built it.",
            "What the city runs on beneath the streets.",
        ],
    },
    {
        words: ['RUST', 'BLEED', 'DECAY', 'FAULT'],
        hints: [
            "What happens to systems no one maintains.",
            "The state of a server left running in an abandoned building.",
            "What time does to encryption that was never updated.",
            "Entropy, made visible in the access logs.",
        ],
    },
    {
        words: ['CACHE', 'STORE', 'KEEP', 'HOLD'],
        hints: [
            "Where everything critical lives before it disappears.",
            "What persists after the source has been deleted.",
            "The part of the system that doesn't know how to forget.",
            "Where the important things go before the purge cycle runs.",
        ],
    },
    {
        words: ['DARK', 'SHADE', 'VEIL', 'CLOAK'],
        hints: [
            "The frequency the monitored channels can't see.",
            "What a runner becomes when they stop broadcasting.",
            "The state between visible and gone.",
            "What separates a runner who gets out from one who doesn't.",
        ],
    },
    {
        words: ['DATA', 'CODE', 'BYTE', 'HASH'],
        hints: [
            "The raw material of every secret ever kept.",
            "What survives every format, every wipe, every migration.",
            "The substance beneath the interface.",
            "What the corporation is actually selling.",
        ],
    },
    {
        words: ['GATE', 'DOOR', 'LOCK', 'ENTRY'],
        hints: [
            "What every system has, even the ones built to keep you out.",
            "The point where you stop being outside.",
            "What separates the permitted from everyone else.",
            "The opening that was never intended to be found.",
        ],
    },
    {
        words: ['BURN', 'HARM', 'KILL', 'BLAZE'],
        hints: [
            "What ICE does when a trace finally resolves.",
            "The last option when extraction has already failed.",
            "What remains of a session after the response team arrives.",
            "The response you were hoping to avoid triggering.",
        ],
    },
    {
        words: ['RISE', 'BREAK', 'REBEL', 'SURGE'],
        hints: [
            "What happens after enough pressure has been applied.",
            "The runner's answer to a node that won't open.",
            "What the underground does when it has nothing left to lose.",
            "The direction everything tends to move in eventually.",
        ],
    },
    {
        words: ['FLESH', 'NERVE', 'BLOOD', 'BONE'],
        hints: [
            "What the hardware plugs into.",
            "What the corporations are trying to upgrade.",
            "The part of the runner that doesn't have a digital fallback.",
            "What you cannot spoof, replace, or patch.",
        ],
    },
    {
        words: ['CITY', 'GRID', 'TOWER', 'BLOCK'],
        hints: [
            "The structure that holds all of this together.",
            "What the corporations built their signal infrastructure on top of.",
            "Where runners live in the gaps between the monitored zones.",
            "What you navigate when the official map is lying.",
        ],
    },
    {
        words: ['PAST', 'FADE', 'RELIC', 'LAPSE'],
        hints: [
            "Where everything the system tried to delete still exists.",
            "What archives turn into when nobody audits them.",
            "The direction logs only travel in.",
            "What a runner exploits when a credential was never rotated.",
        ],
    },
    {
        words: ['STILL', 'HUSH', 'QUIET', 'MUTE'],
        hints: [
            "The interval between a completed scan and a triggered alert.",
            "What the buffer sounds like when they've stopped looking.",
            "The rarest resource on any monitored network.",
            "What a careful runner leaves behind them.",
        ],
    },
];

// ── Difficulty config ──────────────────────────────────────────────────────────

const DIFF_CONFIG = {
    1: { fragmentCount: 3, maxTrace: 5, clueCostPct: 15, traceCostPct: 10, wordLengthMax: 5 },
    2: { fragmentCount: 4, maxTrace: 4, clueCostPct: 20, traceCostPct: 12, wordLengthMax: 6 },
    3: { fragmentCount: 5, maxTrace: 3, clueCostPct: 25, traceCostPct: 15, wordLengthMax: 99 },
};

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Shared minigame state ──────────────────────────────────────────────────────

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    applyHit, endGame,
} = useQuestMinigameState(props.skin);

// ── Game config ────────────────────────────────────────────────────────────────

const difficulty    = props.skin.difficulty ?? 1;
const config        = DIFF_CONFIG[difficulty] ?? DIFF_CONFIG[1];
const maxTrace      = config.maxTrace;
const clueCostPct   = config.clueCostPct;
const clueCost      = clueCostPct / 100;
const traceCostPct  = config.traceCostPct;
const traceCost     = traceCostPct / 100;
const wordLengthMax = config.wordLengthMax;
const fragCount     = config.fragmentCount;

// ── Puzzle state ───────────────────────────────────────────────────────────────

const fragments          = ref([]);   // [{ word, hint, clue }]
const currentIdx         = ref(0);
const slots              = ref([]);   // current letter arrangement
const cursorPos          = ref(0);
const solvedWords        = ref([]);
const traceCount         = ref(0);
const clueVisible        = ref(false);
const showWrong          = ref(false);
const showCorrect        = ref(false);
const passphraseRevealed = ref(false);
const wrapRef            = ref(null);

// ── Computed ───────────────────────────────────────────────────────────────────

const currentFragment = computed(() => fragments.value[currentIdx.value] ?? null);

const localStabClass = computed(() => {
    if (stability.value < 0.15) return 'ts-val--crit';
    if (stability.value < 0.30) return 'ts-val--warn';
    return '';
});

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: primaryProgress.value,
    stability:       stability.value,
    stabilityClass:  stabilityClass.value,
    timerClass:      '',              // no timer pressure on this game
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

function generateClue(word) {
    return word.split('').map(letter => {
        const pool = LETTER_SENTENCES[letter.toUpperCase()];
        if (!pool?.length) return `${letter}: no data recorded.`;
        return pool[randInt(0, pool.length - 1)];
    }).join('\n');
}

function scrambleWord(word) {
    const letters = word.split('');
    let attempts = 0;
    do { shuffle(letters); attempts++; }
    while (letters.join('') === word && attempts < 20);
    return letters;
}

// ── Puzzle generation ──────────────────────────────────────────────────────────

function buildPuzzle() {
    const shuffled = shuffle([...CLUSTERS]);
    const selected = [];
    for (const cluster of shuffled) {
        if (selected.length >= fragCount) break;
        const eligible = cluster.words.filter(w => w.length <= wordLengthMax);
        if (!eligible.length) continue;
        const word = eligible[randInt(0, eligible.length - 1)];
        const hint = cluster.hints[randInt(0, cluster.hints.length - 1)];
        selected.push({ word, hint, clue: generateClue(word) });
    }
    fragments.value = selected;
    loadFragment(0);
}

function loadFragment(idx) {
    currentIdx.value  = idx;
    cursorPos.value   = 0;
    clueVisible.value = false;
    slots.value       = scrambleWord(fragments.value[idx].word);
    primaryProgress.value = idx / fragments.value.length;
}

// ── Interaction ────────────────────────────────────────────────────────────────

function onKey(e) {
    if (result.value || passphraseRevealed.value) return;
    const len = slots.value.length;
    const i   = cursorPos.value;

    if (e.key === 'ArrowLeft') {
        cursorPos.value = Math.max(0, i - 1);
    } else if (e.key === 'ArrowRight') {
        cursorPos.value = Math.min(len - 1, i + 1);
    } else if (e.key === 'ArrowUp' && i > 0) {
        // Move letter at cursor one position left
        [slots.value[i], slots.value[i - 1]] = [slots.value[i - 1], slots.value[i]];
        cursorPos.value = i - 1;
    } else if (e.key === 'ArrowDown' && i < len - 1) {
        // Move letter at cursor one position right
        [slots.value[i], slots.value[i + 1]] = [slots.value[i + 1], slots.value[i]];
        cursorPos.value = i + 1;
    } else if (e.key === 'Enter') {
        submitFragment();
    }
}

function submitFragment() {
    if (result.value || passphraseRevealed.value) return;
    const attempt = slots.value.join('');

    if (attempt === currentFragment.value.word) {
        solvedWords.value.push(attempt);
        showCorrect.value = true;
        setTimeout(() => { showCorrect.value = false; }, 1000);

        const nextIdx = currentIdx.value + 1;
        if (nextIdx >= fragments.value.length) {
            primaryProgress.value    = 1;
            passphraseRevealed.value = true;
            setTimeout(() => {
                endGame('success');
                setTimeout(() => emit('complete'), 2200);
            }, 1800);
        } else {
            setTimeout(() => loadFragment(nextIdx), 800);
        }
    } else {
        traceCount.value++;
        applyHit(traceCost);
        showWrong.value = true;
        setTimeout(() => { showWrong.value = false; }, 1200);

        if (traceCount.value >= maxTrace || stability.value <= 0) {
            endGame('fail', '[TRACE LOCKED] — Too many failed attempts. ICE has your signature.');
            setTimeout(() => emit('fail'), 2200);
        }
    }
}

function purchaseClue() {
    if (clueVisible.value || stability.value <= clueCost + 0.01) return;
    applyHit(clueCost);
    clueVisible.value = true;
    if (stability.value <= 0) {
        endGame('fail', '[SYSTEM COLLAPSE] — Stability exhausted during file decryption.');
        setTimeout(() => emit('fail'), 2200);
    }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    buildPuzzle();
    window.addEventListener('keydown', onKey);
    wrapRef.value?.focus();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKey);
});
</script>

<style scoped>
/* ── Layout ──────────────────────────────────────────────────────────────── */

.ts-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    background: #030d08;
    color: #00ff9d;
    padding: 8px 12px 6px;
    box-sizing: border-box;
    gap: 10px;
    overflow: hidden;
    outline: none;
}

/* ── HUD ─────────────────────────────────────────────────────────────────── */

.ts-hud {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
}

.ts-hud-block        { display: flex; align-items: center; gap: 6px; }
.ts-hud-center       { flex: 1; justify-content: center; }
.ts-hud-right        { margin-left: auto; }
.ts-hud-label        { font-size: 7px; letter-spacing: 0.14em; color: rgba(0,255,100,0.3); }
.ts-hud-val          { font-size: 10px; letter-spacing: 0.08em; }
.ts-val--warn        { color: #ffaa00; }
.ts-val--crit        { color: #ff3333; animation: ts-pulse 0.6s ease infinite alternate; }

.ts-pips             { display: flex; gap: 5px; }
.ts-pip              { font-size: 12px; transition: color 0.2s, text-shadow 0.2s; }
.ts-pip--free        { color: rgba(0,255,100,0.4); }
.ts-pip--burned      { color: #ff3333; text-shadow: 0 0 7px rgba(255,50,50,0.7); }

/* ── Puzzle area ─────────────────────────────────────────────────────────── */

.ts-puzzle {
    display: flex;
    flex-direction: column;
    gap: 14px;
    flex: 1;
    align-items: center;
    justify-content: center;
    min-height: 0;
}

.ts-cipher-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    max-width: 500px;
    text-align: center;
}

.ts-cipher-tag {
    font-size: 7px;
    color: rgba(0,255,100,0.28);
    letter-spacing: 0.18em;
}

.ts-cipher-hint {
    font-size: 14px;
    color: rgba(0,255,100,0.9);
    letter-spacing: 0.04em;
    line-height: 1.55;
    margin: 0;
    font-style: italic;
}

/* ── Letter slots ────────────────────────────────────────────────────────── */

.ts-slots-wrap {
    display: flex;
    gap: 7px;
}

.ts-slot {
    width: 48px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    border: 1px solid rgba(0,255,100,0.18);
    background: rgba(0,18,9,0.7);
    color: rgba(0,255,100,0.6);
    transition: border-color 0.12s, color 0.12s, background 0.12s, box-shadow 0.12s;
    user-select: none;
}

.ts-slot--active {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,255,100,0.07);
    box-shadow: 0 0 12px rgba(0,255,100,0.18), inset 0 0 8px rgba(0,255,100,0.06);
}

/* ── Controls legend ─────────────────────────────────────────────────────── */

.ts-legend {
    display: flex;
    gap: 16px;
    font-size: 7px;
    color: rgba(0,255,100,0.22);
    letter-spacing: 0.1em;
}

/* ── Flash messages ──────────────────────────────────────────────────────── */

.ts-wrong-flash,
.ts-correct-flash {
    font-size: 9px;
    letter-spacing: 0.12em;
    text-align: center;
    padding: 5px 12px;
    border: 1px solid;
}

.ts-wrong-flash {
    color: #ff3333;
    border-color: rgba(255,50,50,0.3);
    background: rgba(80,0,0,0.25);
}

.ts-correct-flash {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.3);
    background: rgba(0,60,30,0.25);
}

.ts-flash-enter-active { animation: ts-flash-in 0.15s ease; }
.ts-flash-leave-active { transition: opacity 0.35s; }
.ts-flash-leave-to     { opacity: 0; }

/* ── Action buttons ──────────────────────────────────────────────────────── */

.ts-actions {
    display: flex;
    gap: 10px;
}

.ts-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    padding: 7px 16px;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
}

.ts-btn--submit {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.45);
}
.ts-btn--submit:hover {
    background: rgba(0,255,100,0.07);
    box-shadow: 0 0 10px rgba(0,255,100,0.18);
}

.ts-btn--clue {
    color: #ffaa00;
    border-color: rgba(255,170,0,0.38);
}
.ts-btn--clue:hover:not(:disabled) {
    background: rgba(255,170,0,0.07);
    box-shadow: 0 0 10px rgba(255,170,0,0.15);
}

.ts-btn--disabled,
.ts-btn:disabled {
    opacity: 0.28;
    cursor: not-allowed;
}

/* ── Clue panel ──────────────────────────────────────────────────────────── */

.ts-clue-panel {
    width: 100%;
    max-width: 500px;
    border: 1px solid rgba(255,170,0,0.22);
    background: rgba(18,10,0,0.75);
    padding: 8px 12px;
    box-sizing: border-box;
}

.ts-clue-tag {
    display: block;
    font-size: 7px;
    color: rgba(255,170,0,0.4);
    letter-spacing: 0.14em;
    margin-bottom: 6px;
}

.ts-clue-body {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(255,200,80,0.82);
    line-height: 1.75;
    margin: 0;
    white-space: pre-wrap;
    letter-spacing: 0.02em;
}

.ts-clue-enter-active { transition: opacity 0.3s, transform 0.3s; }
.ts-clue-enter-from   { opacity: 0; transform: translateY(-5px); }

/* ── Solved log ──────────────────────────────────────────────────────────── */

.ts-solved {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 6px;
    border-top: 1px solid rgba(0,255,100,0.06);
    flex-shrink: 0;
}

.ts-solved-tag {
    font-size: 7px;
    color: rgba(0,255,100,0.28);
    letter-spacing: 0.16em;
    white-space: nowrap;
}

.ts-solved-words    { display: flex; gap: 10px; flex-wrap: wrap; }

.ts-solved-word {
    font-size: 11px;
    color: rgba(0,255,100,0.55);
    letter-spacing: 0.1em;
    padding: 2px 7px;
    border: 1px solid rgba(0,255,100,0.14);
}

/* ── Passphrase reveal ───────────────────────────────────────────────────── */

.ts-passphrase {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
}

.ts-pp-tag {
    font-size: 7px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.2em;
}

.ts-pp-words  { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }

.ts-pp-word {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: #00ff9d;
    text-shadow: 0 0 14px rgba(0,255,100,0.45);
    opacity: 0;
    animation: ts-reveal 0.4s ease forwards;
}

.ts-pp-arrow { color: rgba(0,255,100,0.35); margin-right: 10px; }

.ts-pp-footer {
    font-size: 8px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.22em;
    margin-top: 6px;
}

/* ── Animations ──────────────────────────────────────────────────────────── */

@keyframes ts-pulse {
    from { opacity: 1; }
    to   { opacity: 0.35; }
}

@keyframes ts-flash-in {
    from { opacity: 0; transform: scale(0.96); }
    to   { opacity: 1; transform: scale(1); }
}

@keyframes ts-reveal {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}
</style>
