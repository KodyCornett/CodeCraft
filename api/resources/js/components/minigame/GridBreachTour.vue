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

    <!-- ── Timer ─────────────────────────────────────────────────────────── -->
    <div v-if="currentStep.id === 'timer'" class="gbt-content">
      <p class="gbt-body">
        The countdown runs from the moment you breach.
        Your <span class="gbt-em">RAM</span> stat sets the base length —
        higher RAM means more time on the clock.
      </p>
      <div class="gbt-rule">// IF IT HITS ZERO</div>
      <p class="gbt-body">
        If the timer expires before you hit the score threshold,
        the breach fails and your rig takes <span class="gbt-em">SS damage</span>
        scaled by the node's ICE versus your Firewall.
        Abort early with <span class="gbt-em">[ ABORT ]</span> if the node is too hot.
      </p>
    </div>

    <!-- ── Sequence ──────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'sequence'" class="gbt-content">
      <p class="gbt-body">
        This is the pattern you need to locate in the grid below.
        Values must be matched <span class="gbt-em">left to right</span> — the highlighted
        slot is your current target.
      </p>
      <div class="gbt-rule">// COMPLETING A SEQUENCE</div>
      <p class="gbt-body">
        Match every value in order and the sequence completes, scoring a point
        and resetting back to the first slot.
        The sequence length is set by your <span class="gbt-em">CPU</span> stat.
      </p>
    </div>

    <!-- ── Grid ──────────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'grid'" class="gbt-content">
      <p class="gbt-body">
        The grid is a matrix of hex codes.
        Scan each row for the value shown in your current sequence slot.
        Each cell has a coordinate — <span class="gbt-em">column letter + row number</span>
        (e.g. <span class="gbt-em">F6</span>).
      </p>
      <div class="gbt-rule">// BOARD SCRAMBLE</div>
      <p class="gbt-body">
        The board scrambles every <span class="gbt-em">5 seconds</span> — cells shift
        and row direction arrows flip.
        Wrong submissions accelerate the scramble, so aim before you type.
      </p>
    </div>

    <!-- ── Input ─────────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'input'" class="gbt-content">
      <p class="gbt-body">
        Type the coordinate of the cell containing your target value, then press
        <span class="gbt-em">Enter</span> or <span class="gbt-em">[ SUBMIT ]</span>.
      </p>
      <div class="gbt-rule">// FORMAT</div>
      <div class="gbt-example">
        <span class="gbt-ex-label">COLUMN</span>
        <span class="gbt-ex-val">A – J</span>
      </div>
      <div class="gbt-example">
        <span class="gbt-ex-label">ROW</span>
        <span class="gbt-ex-val">1 – 10</span>
      </div>
      <div class="gbt-example">
        <span class="gbt-ex-label">EXAMPLE</span>
        <span class="gbt-ex-val gbt-em">F6, B3, J10</span>
      </div>
      <p class="gbt-body gbt-body--top">
        Row direction arrows on the right tell you which direction the row is
        currently reading — left-to-right or right-to-left.
      </p>
    </div>

    <!-- ── Score ─────────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'score'" class="gbt-content">
      <p class="gbt-body">
        Each completed sequence increments your breach score.
        Reach the <span class="gbt-em">threshold</span> before time runs out and the
        node is yours — creds extracted, bounty multiplier applied.
      </p>
      <div class="gbt-rule">// MISS THE THRESHOLD</div>
      <p class="gbt-body">
        Falling short means a failed breach — SS damage, no reward.
        Build up your <span class="gbt-em">CPU</span> and <span class="gbt-em">RAM</span>
        at a CyberDoc if nodes are proving too tough.
      </p>
      <div class="gbt-rule">// YOU'RE LIVE</div>
      <p class="gbt-body gbt-em">
        Timer resumes when you close this window. Good luck, runner.
      </p>
    </div>

    <!-- ── Footer nav ─────────────────────────────────────────────────────── -->
    <template #footer>
      <div class="gbt-footer">
        <button class="gbt-skip" @click="onSkip">[ skip ]</button>
        <div class="gbt-nav">
          <span class="gbt-count">{{ stepNumber }} / {{ totalSteps }}</span>
          <button class="gbt-next" @click="onNext">
            {{ isLast ? '[ START BREACH ]' : '[ NEXT ]' }}
          </button>
        </div>
      </div>
    </template>

  </FloatingTerminalWindow>
</template>

<script setup>
import FloatingTerminalWindow from '@/components/shared/FloatingTerminalWindow.vue';
import { useGridBreachTour }  from '@/composables/useGridBreachTour.js';

const emit = defineEmits(['done']);

const { currentStep, stepNumber, isLast, totalSteps, next, skip } = useGridBreachTour();

function onNext() {
    if (isLast.value) {
        skip();       // marks seen + deactivates
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
.gbt-content {
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.gbt-rule {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(255, 179, 0, 0.55);
    margin: 10px 0 5px;
}

.gbt-body {
    margin: 0 0 4px;
    font-size: 11px;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.88);
}

.gbt-body--top {
    margin-top: 8px;
}

.gbt-em {
    color: #FFB300;
    font-style: normal;
}

/* Coordinate example rows */
.gbt-example {
    display: flex;
    gap: 12px;
    padding: 3px 8px;
    background: rgba(255, 255, 255, 0.03);
    border-left: 2px solid rgba(255, 179, 0, 0.15);
    font-size: 10px;
    margin-bottom: 2px;
}

.gbt-ex-label {
    width: 70px;
    flex-shrink: 0;
    color: rgba(255, 179, 0, 0.70);
    letter-spacing: 0.06em;
    font-size: 9px;
}

.gbt-ex-val {
    color: rgba(255, 255, 255, 0.82);
}

/* Footer */
.gbt-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
}

.gbt-skip {
    background: transparent;
    border: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(255, 255, 255, 0.25);
    cursor: pointer;
    padding: 0;
    letter-spacing: 0.08em;
    transition: color 0.12s;
}
.gbt-skip:hover { color: rgba(255, 255, 255, 0.55); }

.gbt-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.gbt-count {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.1em;
}

.gbt-next {
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.45);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(255, 179, 0, 0.85);
    cursor: pointer;
    padding: 4px 10px;
    transition: border-color 0.12s, color 0.12s, background 0.12s;
}
.gbt-next:hover {
    border-color: rgba(255, 179, 0, 0.9);
    color: #FFB300;
    background: rgba(255, 179, 0, 0.06);
}
</style>
