<template>
    <FloatingTerminalWindow
        v-if="currentStep"
        :target="currentStep.target"
        :title="currentStep.title"
        :placement="currentStep.placement ?? 'auto'"
        :window-width="340"
        :dismissable="false"
        :visible="true"
    >

        <!-- ── Signal Array ───────────────────────────────────────────────── -->
        <div v-if="currentStep.id === 'signals'" class="fbt-content">
            <p class="fbt-body">
                Three recursive signals are broadcasting simultaneously, each
                spreading from a centre baseline.
                You must clear them <span class="fbt-em">in order</span> —
                W1 first, then W2, then W3.
            </p>
            <div class="fbt-rule">// SEQUENTIAL LOCK</div>
            <p class="fbt-body">
                The next waveform only becomes active once the current one is
                fully cleared. You cannot skip ahead.
                While you work on the active signal, the others keep oscillating
                — stay focused on the one that's lit.
            </p>
        </div>

        <!-- ── Catch Track ───────────────────────────────────────────────── -->
        <div v-else-if="currentStep.id === 'catch-track'" class="fbt-content">
            <p class="fbt-body">
                This vertical track shows the signal's live amplitude.
                The dot travels up and down from the centre line — the further
                it spreads, the stronger the signal.
            </p>
            <div class="fbt-rule">// THE PEAK ZONES</div>
            <p class="fbt-body">
                The <span class="fbt-em">top</span> and
                <span class="fbt-em">bottom</span> bands are the catch zones.
                When the dot enters one, the zone lights up green and the dot
                glows — that is your window.
                Both peaks count. Top or bottom, it doesn't matter.
            </p>
        </div>

        <!-- ── Space ─────────────────────────────────────────────────────── -->
        <div v-else-if="currentStep.id === 'space'" class="fbt-content">
            <p class="fbt-body">
                Press <span class="fbt-em">[ SPACE ]</span> to catch the signal
                at its peak. This prompt lights up bright green the moment the
                dot enters a catch zone — that's the signal to act.
            </p>
            <div class="fbt-rule">// PEAK REQUIREMENTS</div>
            <div class="fbt-stat-row">
                <span class="fbt-stat-label">W1</span>
                <span class="fbt-stat-val">1 peak catch to lock</span>
            </div>
            <div class="fbt-stat-row">
                <span class="fbt-stat-label">W2</span>
                <span class="fbt-stat-val">2 peak catches to lock</span>
            </div>
            <div class="fbt-stat-row">
                <span class="fbt-stat-label">W3</span>
                <span class="fbt-stat-val">3 peak catches to lock</span>
            </div>
            <p class="fbt-body fbt-body--top">
                Watch the counter beside each waveform label — it tracks your
                progress toward the required catches.
            </p>
        </div>

        <!-- ── Misses ─────────────────────────────────────────────────────── -->
        <div v-else-if="currentStep.id === 'misses'" class="fbt-content">
            <p class="fbt-body">
                These diamond pips are your miss budget for this waveform.
                Each time you press <span class="fbt-em">[ SPACE ]</span> when
                the dot is not in a peak zone, one pip burns red and your
                <span class="fbt-em">stability</span> takes damage.
            </p>
            <div class="fbt-rule">// RESET CONDITION</div>
            <p class="fbt-body">
                Exhaust the miss budget on any waveform and
                <span class="fbt-em">the entire set resets to W1</span>.
                All caught peaks are lost. Each waveform's budget matches its
                number — W1 has 1 miss, W2 has 2, W3 has 3.
            </p>
            <div class="fbt-rule">// STABILITY</div>
            <p class="fbt-body">
                Every miss costs stability regardless of whether it triggers a
                reset. If your stability bar empties, the hack fails entirely.
                Stay deliberate — wait for the zone to light before committing.
            </p>
        </div>

        <!-- ── Dump ──────────────────────────────────────────────────────── -->
        <div v-else-if="currentStep.id === 'dump'" class="fbt-content">
            <p class="fbt-body">
                Lock all three waveforms and the buffer dumps automatically —
                one cycle complete. The counter here tracks how many dumps
                you need to finish the sequence.
            </p>
            <div class="fbt-rule">// PRESSURE</div>
            <p class="fbt-body">
                Your <span class="fbt-em">stability bar</span> is the real
                threat. The timer keeps you moving but won't kill you on its own
                — bleeding out from bad catches will.
                Take your time on each peak. A slow catch beats a
                wrong one every time.
            </p>
            <div class="fbt-rule">// YOU'RE LIVE</div>
            <p class="fbt-body fbt-em">
                The clock starts when you close this window.
            </p>
        </div>

        <!-- ── Footer nav ─────────────────────────────────────────────────── -->
        <template #footer>
            <div class="fbt-footer">
                <button class="fbt-skip" @click="onSkip">[ skip ]</button>
                <div class="fbt-nav">
                    <span class="fbt-count">{{ stepNumber }} / {{ totalSteps }}</span>
                    <button class="fbt-next" @click="onNext">
                        {{ isLast ? '[ START FLUSH ]' : '[ NEXT ]' }}
                    </button>
                </div>
            </div>
        </template>

    </FloatingTerminalWindow>
</template>

<script setup>
import FloatingTerminalWindow from '@/components/shared/FloatingTerminalWindow.vue';
import { useFlushBufferTour } from '@/composables/useFlushBufferTour.js';

const emit = defineEmits(['done']);

const { currentStep, stepNumber, isLast, totalSteps, next, skip } = useFlushBufferTour();

function onNext() {
    if (isLast.value) {
        skip();
        emit('done');
    } else {
        next();
    }
}

function onSkip() {
    skip();
    emit('done');
}
</script>

<style scoped>
.fbt-content {
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.fbt-rule {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(0, 255, 100, 0.45);
    margin: 10px 0 5px;
}

.fbt-body {
    margin: 0 0 4px;
    font-size: 11px;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.85);
}

.fbt-body--top { margin-top: 8px; }

.fbt-em {
    color: #00ff9d;
    font-style: normal;
}

/* Stat rows (peak requirements table) */
.fbt-stat-row {
    display: flex;
    gap: 12px;
    padding: 3px 8px;
    background: rgba(0, 255, 100, 0.03);
    border-left: 2px solid rgba(0, 255, 100, 0.12);
    font-size: 10px;
    margin-bottom: 2px;
}

.fbt-stat-label {
    width: 30px;
    flex-shrink: 0;
    color: rgba(0, 255, 100, 0.65);
    letter-spacing: 0.1em;
    font-size: 9px;
}

.fbt-stat-val {
    color: rgba(255, 255, 255, 0.75);
}

/* Footer */
.fbt-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
}

.fbt-skip {
    background: transparent;
    border: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(255, 255, 255, 0.22);
    cursor: pointer;
    padding: 0;
    letter-spacing: 0.08em;
    transition: color 0.12s;
}
.fbt-skip:hover { color: rgba(255, 255, 255, 0.5); }

.fbt-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.fbt-count {
    font-size: 9px;
    color: rgba(0, 255, 100, 0.3);
    letter-spacing: 0.1em;
}

.fbt-next {
    background: transparent;
    border: 1px solid rgba(0, 255, 100, 0.4);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(0, 255, 100, 0.8);
    cursor: pointer;
    padding: 4px 10px;
    transition: border-color 0.12s, color 0.12s, background 0.12s;
}
.fbt-next:hover {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0, 255, 100, 0.06);
}
</style>
