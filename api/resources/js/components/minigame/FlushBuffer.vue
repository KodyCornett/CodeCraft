<template>
    <QuestMinigameChrome v-bind="chrome">
        <div class="fb-wrap">

            <!-- Dump progress -->
            <div class="fb-dump-header">
                <span class="fb-dump-label">BUFFER DUMP</span>
                <div class="fb-dump-pips">
                    <span
                        v-for="i in dumpGoal" :key="i"
                        class="fb-dump-pip"
                        :class="{ 'fb-dump-pip--done': i <= dumpsComplete }"
                    >█</span>
                </div>
                <span class="fb-dump-count">{{ dumpsComplete }} / {{ dumpGoal }}</span>
            </div>

            <!-- Signal array -->
            <div class="fb-signals">
                <div
                    v-for="idx in [0, 1, 2]" :key="idx"
                    class="fb-signal-row"
                    :class="rowClass(idx)"
                >
                    <!-- Left: label + peak counter + miss pips -->
                    <div class="fb-sig-meta">
                        <span class="fb-sig-label">W{{ idx + 1 }}</span>
                        <span class="fb-sig-peaks">{{ wavePeaksCaught[idx] }} / {{ waveformPeakReqs[idx] }}</span>
                        <div class="fb-miss-pips">
                            <span
                                v-for="m in missTolerance[idx]" :key="m"
                                class="fb-miss-pip"
                                :class="{ 'fb-miss-pip--used': m <= waveMisses[idx] }"
                            >◆</span>
                        </div>
                    </div>

                    <!-- Centre: scrolling waveform history -->
                    <div class="fb-wf-display">
                        <span
                            class="fb-wf-chars"
                            :class="{
                                'fb-wf-chars--active': waveStates[idx] === 'active',
                                'fb-wf-chars--locked': waveStates[idx] === 'locked',
                            }"
                        >{{ waveHistoryDisplay[idx] || '────────────────────────' }}</span>
                    </div>

                    <!-- Right: vertical catch track -->
                    <div
                        class="fb-catch-track"
                        :class="{ 'fb-catch-track--active': idx === activeWave && waveStates[idx] === 'active' }"
                    >
                        <!-- Peak zones top and bottom -->
                        <div
                            class="fb-peak-zone fb-peak-zone--top"
                            :class="{ 'fb-peak-zone--lit': atPeak[idx] && waveAmplitudes[idx] > 0 && waveStates[idx] === 'active' }"
                        />
                        <div
                            class="fb-peak-zone fb-peak-zone--bot"
                            :class="{ 'fb-peak-zone--lit': atPeak[idx] && waveAmplitudes[idx] < 0 && waveStates[idx] === 'active' }"
                        />
                        <!-- Centre baseline -->
                        <div class="fb-catch-center" />
                        <!-- Amplitude dot -->
                        <div
                            class="fb-catch-dot"
                            :class="{
                                'fb-catch-dot--peak':   atPeak[idx] && waveStates[idx] === 'active',
                                'fb-catch-dot--locked': waveStates[idx] === 'locked',
                                'fb-catch-dot--dim':    waveStates[idx] === 'pending',
                            }"
                            :style="{ top: dotTop(idx) }"
                        />
                    </div>

                    <!-- Status badge -->
                    <span class="fb-sig-status" :class="`fb-status--${waveStates[idx]}`">
                        {{ waveStates[idx] === 'locked' ? '[ LOCKED ]' : waveStates[idx] === 'active' ? '[ ACTIVE ]' : '[ PENDING ]' }}
                    </span>
                </div>
            </div>

            <!-- Space prompt -->
            <div class="fb-space-row">
                <span
                    class="fb-space-key"
                    :class="{ 'fb-space-key--ready': atPeak[activeWave] && waveStates[activeWave] === 'active' && !result }"
                >
                    [ SPACE ] — CATCH SIGNAL PEAK
                </span>
            </div>

            <!-- Feedback messages -->
            <Transition name="fb-msg">
                <div v-if="showRestartMsg" class="fb-msg fb-msg--fail">
                    ⚠ SIGNAL CORRUPTED — RESETTING TO W1
                </div>
            </Transition>
            <Transition name="fb-msg">
                <div v-if="showDumpMsg" class="fb-msg fb-msg--success">
                    ✓ BUFFER FLUSHED — {{ dumpsComplete }} / {{ dumpGoal }} COMPLETE
                </div>
            </Transition>

        </div>
    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';
import { useQuestMinigameState } from '@/composables/useQuestMinigameState.js';

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── Config ─────────────────────────────────────────────────────────────────────
// All difficulty derived from iceLevel — same component handles prologue and PvE.

const iceLevel  = props.skin.iceLevel ?? 3
const dumpGoal  = Math.max(1, iceLevel - 2)          // ICE3→1, ICE4→2, ICE5→3…
const timeLimit = Math.max(30, 135 - iceLevel * 15)  // ICE3→90s … ICE7→30s
const missHit   = 0.12 + (iceLevel - 3) * 0.015      // ICE3→12% … ICE7→18%

// Peak requirements: (waveIndex+1) + (ICE-3), minimum 1
// ICE3 → [1,2,3]  |  ICE4 → [2,3,4]  |  ICE5 → [3,4,5]
const waveformPeakReqs = [0, 1, 2].map(i => Math.max(1, (i + 1) + (iceLevel - 3)))

// Miss tolerance matches waveform number: W1=1, W2=2, W3=3
const missTolerance = [1, 2, 3]

// Angular oscillation speeds (rad/s): W1 slowest, W3 fastest, scaled by ICE
const SPEEDS = [0.9, 1.45, 2.1].map(s => s * (1 + (iceLevel - 3) * 0.10))

// |amplitude| must reach this to register as a catchable peak
const PEAK_THRESHOLD = 0.82

const WAVE_CHARS = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█']

// ── Shared composable ──────────────────────────────────────────────────────────
// difficulty:1 → minimal passive drain. Stability only drops from active misses.
// We drive timeLeft and primaryProgress manually to keep them in sync.

const {
    stability, primaryProgress, timeLeft, result, failReason,
    glitchActive, glitchType, glitchIntensity,
    stabilityClass, timerClass,
    applyHit, endGame,
} = useQuestMinigameState({ ...props.skin, timeLimit, difficulty: 1 })

timeLeft.value = timeLimit  // override composable default with ICE-computed value

// ── Reactive game state ────────────────────────────────────────────────────────

const dumpsComplete      = ref(0)
const activeWave         = ref(0)
const waveStates         = ref(['active', 'pending', 'pending'])
const wavePeaksCaught    = ref([0, 0, 0])
const waveMisses         = ref([0, 0, 0])
const waveAmplitudes     = ref([0, 0, 0])    // current -1…1 per waveform
const atPeak             = ref([false, false, false])
const waveHistoryDisplay = ref(['', '', ''])
const showRestartMsg     = ref(false)
const showDumpMsg        = ref(false)

// ── Non-reactive loop vars ─────────────────────────────────────────────────────

// Stagger start phases so the three waveforms look distinct immediately
let phases        = [0, Math.PI * 0.65, Math.PI * 1.35]
let historyArrs   = [[], [], []]
let historyTimers = [0, 0, 0]
let spaceCooldown = 0   // prevents double-triggering after catch or miss
let rafId         = null
let lastTs        = null

// ── Template helpers ───────────────────────────────────────────────────────────

// Translate -1…1 amplitude to CSS top% for the dot (centre = 50%)
function dotTop(idx) {
    const amp = waveStates.value[idx] === 'locked' ? 0 : waveAmplitudes.value[idx]
    return `${50 - amp * 44}%`
}

function rowClass(idx) {
    return {
        'fb-row--active':  waveStates.value[idx] === 'active',
        'fb-row--pending': waveStates.value[idx] === 'pending',
        'fb-row--locked':  waveStates.value[idx] === 'locked',
    }
}

// ── Game actions ───────────────────────────────────────────────────────────────

function onSpace() {
    if (result.value || spaceCooldown > 0 || showRestartMsg.value || showDumpMsg.value) return
    const idx = activeWave.value
    if (waveStates.value[idx] !== 'active') return

    spaceCooldown = 0.35

    if (atPeak.value[idx]) {
        // Successful catch — advance this waveform's peak counter
        const caught = wavePeaksCaught.value.map((v, i) => i === idx ? v + 1 : v)
        wavePeaksCaught.value = caught
        if (caught[idx] >= waveformPeakReqs[idx]) lockWaveform(idx)
    } else {
        // Miss — stability damage, increment miss counter, check restart
        applyHit(missHit)
        const misses = waveMisses.value.map((v, i) => i === idx ? v + 1 : v)
        waveMisses.value = misses
        if (misses[idx] > missTolerance[idx]) triggerRestart()
    }
}

function lockWaveform(idx) {
    const states = waveStates.value.map((s, i) => {
        if (i === idx)     return 'locked'
        if (i === idx + 1) return 'active'
        return s
    })
    if (idx < 2) {
        activeWave.value = idx + 1
        waveStates.value = states
    } else {
        waveStates.value = states
        triggerDump()
    }
}

function triggerDump() {
    dumpsComplete.value++
    showDumpMsg.value = true
    setTimeout(() => {
        showDumpMsg.value = false
        if (dumpsComplete.value >= dumpGoal) {
            endGame('success')
            setTimeout(() => emit('complete'), 2200)
        } else {
            resetSet()
        }
    }, 1800)
}

function triggerRestart() {
    showRestartMsg.value = true
    setTimeout(() => {
        showRestartMsg.value = false
        resetSet()
    }, 1200)
}

function resetSet() {
    activeWave.value      = 0
    waveStates.value      = ['active', 'pending', 'pending']
    wavePeaksCaught.value = [0, 0, 0]
    waveMisses.value      = [0, 0, 0]
    historyArrs           = [[], [], []]
    waveHistoryDisplay.value = ['', '', '']
}

// ── Game loop ──────────────────────────────────────────────────────────────────

function tick(ts) {
    if (result.value) return

    const dt = lastTs ? Math.min((ts - lastTs) / 1000, 0.1) : 0
    lastTs = ts

    // Timer and trace bar stay in sync — both represent time pressure
    timeLeft.value        = Math.max(0, timeLeft.value - dt)
    primaryProgress.value = 1 - (timeLeft.value / timeLimit)

    if (spaceCooldown > 0) spaceCooldown -= dt

    // Fail conditions
    if (!result.value && timeLeft.value <= 0) {
        endGame('fail', props.skin.failText ?? 'Timer expired. Buffer unsalvaged.')
        setTimeout(() => emit('fail'), 2200)
        return
    }
    if (!result.value && stability.value <= 0) {
        endGame('fail', '[STABILITY CRITICAL] — Rig shutdown.')
        setTimeout(() => emit('fail'), 2200)
        return
    }

    // Advance oscillations and update reactive display state
    const newAmps = [0, 0, 0]
    const newPeak = [false, false, false]

    for (let i = 0; i < 3; i++) {
        if (waveStates.value[i] === 'locked') continue

        phases[i] += SPEEDS[i] * dt
        const amp  = Math.sin(phases[i])
        newAmps[i] = amp
        newPeak[i] = Math.abs(amp) >= PEAK_THRESHOLD

        // Push a bar-chart character to the scrolling history
        historyTimers[i] -= dt
        if (historyTimers[i] <= 0) {
            const charIdx = Math.max(0, Math.min(7, Math.round(((amp + 1) / 2) * 7)))
            historyArrs[i].push(WAVE_CHARS[charIdx])
            if (historyArrs[i].length > 24) historyArrs[i].shift()
            historyTimers[i] = 0.055
        }
    }

    waveAmplitudes.value     = newAmps
    atPeak.value             = newPeak
    waveHistoryDisplay.value = historyArrs.map(h => h.join(''))

    rafId = requestAnimationFrame(tick)
}

// ── Keyboard ───────────────────────────────────────────────────────────────────

function onKeyDown(e) {
    if (e.code === 'Space') {
        e.preventDefault()
        onSpace()
    }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    document.addEventListener('keydown', onKeyDown)
    rafId = requestAnimationFrame(tick)
})

onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown)
    if (rafId) cancelAnimationFrame(rafId)
})

// ── Chrome passthrough ─────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: primaryProgress.value,
    stability:       stability.value,
    stabilityClass:  stabilityClass.value,
    timerClass:      timerClass.value,
    glitchActive:    glitchActive.value,
    glitchType:      glitchType.value,
    glitchIntensity: glitchIntensity.value,
    result:          result.value,
    failReason:      failReason.value,
}))
</script>

<style scoped>
.fb-wrap {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    padding: 16px 20px 12px;
    box-sizing: border-box;
    gap: 14px;
    font-family: 'JetBrains Mono', monospace;
    position: relative;
}

/* ── Dump header ─────────────────────────────────────────────────────────────── */

.fb-dump-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid rgba(0,255,100,0.08);
    flex-shrink: 0;
}

.fb-dump-label {
    font-size: 8px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
}

.fb-dump-pips { display: flex; gap: 7px; }

.fb-dump-pip {
    font-size: 10px;
    color: rgba(0,255,100,0.1);
    transition: color 0.35s, text-shadow 0.35s;
}

.fb-dump-pip--done {
    color: #00ff9d;
    text-shadow: 0 0 8px rgba(0,255,100,0.6);
}

.fb-dump-count {
    font-size: 9px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.1em;
    margin-left: auto;
}

/* ── Signal rows ─────────────────────────────────────────────────────────────── */

.fb-signals {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
}

.fb-signal-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border: 1px solid rgba(0,255,100,0.07);
    background: rgba(0,255,100,0.01);
    transition: border-color 0.2s, background 0.2s, opacity 0.2s;
}

.fb-row--active {
    border-color: rgba(0,255,100,0.28);
    background: rgba(0,255,100,0.025);
}

.fb-row--pending { opacity: 0.4; }

.fb-row--locked {
    border-color: rgba(0,255,100,0.04);
    background: transparent;
    opacity: 0.55;
}

/* ── Signal meta ─────────────────────────────────────────────────────────────── */

.fb-sig-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 62px;
    flex-shrink: 0;
}

.fb-sig-label {
    font-size: 11px;
    color: rgba(0,255,100,0.4);
    letter-spacing: 0.12em;
}

.fb-row--active .fb-sig-label { color: #00ff9d; }

.fb-sig-peaks {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.07em;
}

.fb-miss-pips { display: flex; gap: 5px; }

.fb-miss-pip {
    font-size: 7px;
    color: rgba(0,255,100,0.18);
    transition: color 0.12s;
}

.fb-miss-pip--used { color: rgba(255,50,50,0.75); }

/* ── Waveform history ────────────────────────────────────────────────────────── */

.fb-wf-display {
    flex: 1;
    overflow: hidden;
    white-space: nowrap;
}

.fb-wf-chars {
    font-size: 12px;
    color: rgba(0,255,100,0.15);
    letter-spacing: 0.05em;
    transition: color 0.2s;
}

.fb-wf-chars--active { color: rgba(0,255,100,0.55); }
.fb-wf-chars--locked { color: rgba(0,255,100,0.12); }

/* ── Vertical catch track ────────────────────────────────────────────────────── */

.fb-catch-track {
    width: 18px;
    height: 72px;
    position: relative;
    background: rgba(0,255,100,0.02);
    border: 1px solid rgba(0,255,100,0.07);
    border-radius: 2px;
    overflow: hidden;
    flex-shrink: 0;
}

.fb-catch-track--active { border-color: rgba(0,255,100,0.22); }

.fb-peak-zone {
    position: absolute;
    left: 0; right: 0;
    height: 18%;
    background: rgba(0,255,100,0.04);
    transition: background 0.07s;
}

.fb-peak-zone--top { top: 0; }
.fb-peak-zone--bot { bottom: 0; }

.fb-peak-zone--lit {
    background: rgba(0,255,100,0.28);
    box-shadow: 0 0 6px rgba(0,255,100,0.35) inset;
}

.fb-catch-center {
    position: absolute;
    left: 0; right: 0;
    top: 50%;
    height: 1px;
    background: rgba(0,255,100,0.1);
    transform: translateY(-50%);
}

.fb-catch-dot {
    position: absolute;
    left: 50%;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,255,100,0.3);
    transition: top 0.016s linear, background 0.07s, box-shadow 0.07s;
}

.fb-catch-dot--peak {
    background: #00ff9d;
    box-shadow: 0 0 10px rgba(0,255,100,1), 0 0 20px rgba(0,255,100,0.5);
}

.fb-catch-dot--locked { background: rgba(0,255,100,0.15); box-shadow: none; }
.fb-catch-dot--dim    { background: rgba(0,255,100,0.1);  box-shadow: none; }

/* ── Status badge ────────────────────────────────────────────────────────────── */

.fb-sig-status {
    font-size: 8px;
    letter-spacing: 0.1em;
    width: 76px;
    flex-shrink: 0;
    text-align: right;
}

.fb-status--active  { color: rgba(0,255,100,0.7); animation: fb-blink 0.9s steps(1) infinite; }
.fb-status--pending { color: rgba(0,255,100,0.18); }
.fb-status--locked  { color: rgba(0,255,100,0.28); }

/* ── Space prompt ────────────────────────────────────────────────────────────── */

.fb-space-row {
    display: flex;
    justify-content: center;
    padding-top: 2px;
    flex-shrink: 0;
}

.fb-space-key {
    font-size: 10px;
    letter-spacing: 0.2em;
    color: rgba(0,255,100,0.2);
    border: 1px solid rgba(0,255,100,0.08);
    padding: 7px 24px;
    pointer-events: none;
    user-select: none;
    transition: color 0.1s, border-color 0.1s, box-shadow 0.1s;
}

.fb-space-key--ready {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.55);
    animation: fb-pulse 0.45s ease infinite alternate;
}

/* ── Feedback messages ───────────────────────────────────────────────────────── */

.fb-msg {
    position: absolute;
    bottom: 58px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 10px;
    letter-spacing: 0.15em;
    padding: 8px 22px;
    border: 1px solid;
    white-space: nowrap;
    pointer-events: none;
    text-align: center;
    z-index: 10;
}

.fb-msg--fail {
    color: rgba(255,50,50,0.9);
    border-color: rgba(255,50,50,0.3);
    background: rgba(20,0,0,0.92);
}

.fb-msg--success {
    color: #00ff9d;
    border-color: rgba(0,255,100,0.4);
    background: rgba(0,20,10,0.92);
    text-shadow: 0 0 10px rgba(0,255,100,0.4);
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */

.fb-msg-enter-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.fb-msg-leave-active { transition: opacity 0.18s ease; }
.fb-msg-enter-from   { opacity: 0; transform: translateX(-50%) translateY(6px); }
.fb-msg-leave-to     { opacity: 0; }

/* ── Animations ──────────────────────────────────────────────────────────────── */

@keyframes fb-blink { 0%,49%{opacity:1} 50%,100%{opacity:0.35} }
@keyframes fb-pulse {
    from { box-shadow: 0 0 8px rgba(0,255,100,0.08); }
    to   { box-shadow: 0 0 24px rgba(0,255,100,0.28); }
}
</style>
