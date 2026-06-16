<template>
    <div class="qmc-overlay">
        <GlitchEffect
            :type="glitchType"
            :intensity="glitchIntensity"
            :active="glitchActive"
            overlay
        />

        <div class="qmc-scanline" />

        <!-- Header -->
        <div class="qmc-header">
            <span class="qmc-logo">◈ {{ gameTypeLabel }}</span>
            <span class="qmc-file">{{ skin.fileName }}</span>
            <span class="qmc-timer" :class="timerClass">{{ timeLeft.toFixed(1) }}s</span>
        </div>

        <!-- Primary threat bar -->
        <div v-if="!hideBars" class="qmc-bar-row">
            <span class="qmc-bar-label">{{ skin.primaryBarLabel ?? 'TRACE' }}</span>
            <div class="qmc-bar-track">
                <div class="qmc-bar-fill qmc-fill--primary" :style="{ width: (primaryProgress * 100) + '%' }" />
            </div>
        </div>

        <!-- Stability bar -->
        <div v-if="!hideBars" class="qmc-bar-row qmc-bar-row--stab">
            <span class="qmc-bar-label qmc-bar-label--stab">{{ skin.stabilityLabel ?? 'STABILITY' }}</span>
            <div class="qmc-bar-track qmc-bar-track--stab">
                <div class="qmc-bar-fill qmc-fill--stab" :class="stabilityClass"
                    :style="{ width: (stability * 100) + '%' }" />
            </div>
            <span class="qmc-stab-pct" :class="stabilityClass">{{ Math.round(stability * 100) }}%</span>
        </div>

        <!-- Game area (injected by each minigame) -->
        <div class="qmc-game-area">
            <slot />
        </div>

        <!-- Objective footer -->
        <div class="qmc-objective">
            <span class="qmc-obj-tag">OBJECTIVE //</span>
            {{ skin.objectiveText }}
        </div>

        <!-- Result overlay -->
        <Transition name="qmc-result">
            <div v-if="result" class="qmc-result" :class="`qmc-result--${result}`">
                <div class="qmc-result-title">{{ resultTitle }}</div>
                <div class="qmc-result-sub">{{ result === 'success' ? skin.successText : failReason }}</div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import GlitchEffect from '@/components/shared/GlitchEffect.vue';

const props = defineProps({
    skin:            { type: Object,  required: true },
    timeLeft:        { type: Number,  required: true },
    primaryProgress: { type: Number,  required: true },
    stability:       { type: Number,  required: true },
    stabilityClass:  { type: String,  default: '' },
    timerClass:      { type: String,  default: '' },
    glitchActive:    { type: Boolean, default: false },
    glitchType:      { type: String,  default: 'scan' },
    glitchIntensity: { type: Number,  default: 0 },
    result:          { type: String,  default: null },
    failReason:      { type: String,  default: '' },
    hideBars:        { type: Boolean, default: false },
});

const LABELS = {
    disconnect_layer:   'DISCONNECT_LAYER',
    flush_buffer:       'FLUSH_BUFFER',
    toxic_soak:         'TOXIC_SOAK',
    archive_extraction: 'ARCHIVE_EXTRACTION',
    calibration_tether: 'CALIBRATION_TETHER',
};

const gameTypeLabel = computed(() => LABELS[props.skin.gameType] ?? 'HACK_SEQUENCE');

const resultTitle = computed(() => {
    if (props.result === 'success') return 'SEQUENCE COMPLETE';
    return props.failReason.includes('STABILITY') ? 'SYSTEM CRITICAL — ABORT' : 'TRACE LOCKED — ABORT';
});
</script>

<style scoped>
.qmc-overlay {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: #010a06;
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}
.qmc-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px,
        rgba(0,255,100,0.01) 2px, rgba(0,255,100,0.01) 4px);
    pointer-events: none;
}

/* Header */
.qmc-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 20px;
    border-bottom: 1px solid rgba(0,255,100,0.1);
    position: relative;
    z-index: 1;
    flex-shrink: 0;
}
.qmc-logo  { color: #00ff9d; font-size: 13px; font-weight: 700; letter-spacing: 0.15em; }
.qmc-file  { color: #4a9a7a; font-size: 11px; flex: 1; }
.qmc-timer { font-size: 18px; font-weight: 700; color: #00ff9d; letter-spacing: 0.1em; }
.qmc-timer.timer--warn     { color: #FFB300; }
.qmc-timer.timer--critical { color: #FF3333; animation: qmc-blink 0.5s steps(1) infinite; }

/* Bars */
.qmc-bar-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 20px;
    border-bottom: 1px solid rgba(255,0,0,0.08);
    position: relative;
    z-index: 1;
    flex-shrink: 0;
}
.qmc-bar-row--stab { border-bottom-color: rgba(0,255,100,0.06); }
.qmc-bar-label {
    font-size: 8px;
    color: rgba(255,50,50,0.5);
    letter-spacing: 0.15em;
    width: 90px;
    flex-shrink: 0;
}
.qmc-bar-label--stab { color: rgba(0,255,100,0.35); }
.qmc-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(255,0,0,0.08);
    position: relative;
    overflow: hidden;
}
.qmc-bar-track--stab { background: rgba(0,255,100,0.06); }
.qmc-bar-fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    transition: width 0.1s linear;
}
.qmc-fill--primary {
    background: linear-gradient(90deg, #660000, #ff3333);
    box-shadow: 0 0 8px rgba(255,0,0,0.4);
}
.qmc-fill--stab {
    background: linear-gradient(90deg, #003322, #00ff9d);
    box-shadow: 0 0 6px rgba(0,255,100,0.3);
}
.qmc-fill--stab.stab--warn     { background: linear-gradient(90deg, #332200, #FFB300); }
.qmc-fill--stab.stab--critical { background: linear-gradient(90deg, #330000, #ff3333); animation: qmc-blink 0.4s steps(1) infinite; }
.qmc-stab-pct {
    font-size: 9px;
    color: rgba(0,255,100,0.4);
    width: 32px;
    text-align: right;
    flex-shrink: 0;
}
.qmc-stab-pct.stab--warn     { color: rgba(255,179,0,0.7); }
.qmc-stab-pct.stab--critical { color: rgba(255,50,50,0.9); }

/* Game area */
.qmc-game-area {
    flex: 1;
    position: relative;
    overflow: hidden;
}

/* Objective */
.qmc-objective {
    padding: 7px 20px;
    font-size: 10px;
    color: rgba(0,255,100,0.3);
    border-top: 1px solid rgba(0,255,100,0.06);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
}
.qmc-obj-tag { color: rgba(0,255,100,0.15); letter-spacing: 0.1em; margin-right: 6px; }

/* Result overlay */
.qmc-result {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    z-index: 200;
}
.qmc-result--success { background: rgba(0,20,10,0.96); }
.qmc-result--fail    { background: rgba(20,0,0,0.96); }
.qmc-result-title { font-size: 22px; font-weight: 700; letter-spacing: 0.2em; }
.qmc-result--success .qmc-result-title { color: #00ff9d; text-shadow: 0 0 30px rgba(0,255,100,0.4); }
.qmc-result--fail    .qmc-result-title { color: #ff3333; text-shadow: 0 0 30px rgba(255,0,0,0.3); }
.qmc-result-sub { font-size: 11px; color: rgba(160,200,180,0.5); text-align: center; max-width: 420px; }

.qmc-result-enter-active { transition: opacity 0.3s ease; }
.qmc-result-enter-from   { opacity: 0; }

@keyframes qmc-blink { 0%,49%{opacity:1} 50%,100%{opacity:0.3} }
</style>
