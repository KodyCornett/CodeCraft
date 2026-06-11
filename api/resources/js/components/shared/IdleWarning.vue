<template>
    <Transition name="idle-fade">
        <div v-if="visible" class="idle-overlay" @click.stop>

            <div class="idle-box">
                <div class="idle-scanline" />

                <!-- Header -->
                <div class="idle-header">
                    <span class="idle-alert-tag">⚠ SESSION WARNING</span>
                </div>

                <!-- Countdown ring + timer -->
                <div class="idle-timer-wrap">
                    <svg class="idle-ring" viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg">
                        <circle class="idle-ring-track" cx="40" cy="40" r="34" />
                        <circle
                            class="idle-ring-fill"
                            cx="40" cy="40" r="34"
                            :stroke-dashoffset="dashOffset"
                        />
                    </svg>
                    <span class="idle-countdown">{{ countdown }}</span>
                </div>

                <!-- Message -->
                <p class="idle-msg">
                    INACTIVITY DETECTED<br>
                    <span class="idle-msg-sub">SESSION WILL TERMINATE IF YOU DO NOT RESPOND</span>
                </p>

                <!-- CTA -->
                <button class="idle-cancel-btn" @click="$emit('cancel')">
                    ◉ KEEP SESSION
                </button>

                <p class="idle-footer">
                    You will be logged out automatically when the timer reaches 0:00.
                </p>
            </div>

        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    visible:     { type: Boolean, required: true },
    countdown:   { type: String,  default: '2:00' },
    secondsLeft: { type: Number,  default: 120 },
});

defineEmits(['cancel']);

const TOTAL_SECONDS = 120;
const CIRCUMFERENCE = 2 * Math.PI * 34;   // 2πr, r = 34

// dashOffset goes from 0 (full ring) → CIRCUMFERENCE (empty) as time runs out
const dashOffset = computed(() =>
    ((TOTAL_SECONDS - props.secondsLeft) / TOTAL_SECONDS) * CIRCUMFERENCE
);
</script>

<style scoped>
.idle-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.idle-box {
    position: relative;
    width: 340px;
    background: rgba(4, 6, 14, 0.97);
    border: 1px solid rgba(255, 179, 0, 0.45);
    box-shadow: 0 0 40px rgba(255, 179, 0, 0.12), inset 0 0 30px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    overflow: hidden;
    font-family: 'JetBrains Mono', monospace;
}

.idle-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(255, 179, 0, 0.015) 2px,
        rgba(255, 179, 0, 0.015) 4px
    );
    pointer-events: none;
    z-index: 1;
}

/* ── Header ───────────────────────────────────────────────────────────── */
.idle-header {
    width: 100%;
    padding: 14px 20px 12px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.2);
    background: rgba(255, 179, 0, 0.04);
}

.idle-alert-tag {
    font-size: 11px;
    color: #FFB300;
    letter-spacing: 0.2em;
    text-shadow: 0 0 10px rgba(255, 179, 0, 0.6);
    animation: alert-pulse 1.4s ease-in-out infinite;
}

@keyframes alert-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.55; }
}

/* ── Timer ring ───────────────────────────────────────────────────────── */
.idle-timer-wrap {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 28px auto 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.idle-ring {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.idle-ring-track {
    fill: none;
    stroke: rgba(255, 179, 0, 0.12);
    stroke-width: 4;
}

.idle-ring-fill {
    fill: none;
    stroke: #FFB300;
    stroke-width: 4;
    stroke-dasharray: 213.6;  /* 2π × 34 ≈ 213.6 */
    stroke-linecap: round;
    transition: stroke-dashoffset 0.9s linear;
    filter: drop-shadow(0 0 4px rgba(255, 179, 0, 0.7));
}

.idle-countdown {
    position: relative;
    font-size: 18px;
    color: #FFB300;
    letter-spacing: 0.08em;
    text-shadow: 0 0 12px rgba(255, 179, 0, 0.8);
    z-index: 1;
}

/* ── Message ──────────────────────────────────────────────────────────── */
.idle-msg {
    font-size: 11px;
    color: rgba(0, 255, 255, 0.85);
    letter-spacing: 0.14em;
    text-align: center;
    line-height: 1.7;
    margin: 4px 24px 20px;
}

.idle-msg-sub {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.45);
    letter-spacing: 0.1em;
}

/* ── CTA button ───────────────────────────────────────────────────────── */
.idle-cancel-btn {
    width: calc(100% - 40px);
    margin: 0 20px 16px;
    padding: 11px 0;
    background: rgba(255, 179, 0, 0.08);
    border: 1px solid rgba(255, 179, 0, 0.55);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.18em;
    cursor: pointer;
    transition: background 0.15s, box-shadow 0.15s;
    text-shadow: 0 0 8px rgba(255, 179, 0, 0.5);
}

.idle-cancel-btn:hover {
    background: rgba(255, 179, 0, 0.15);
    box-shadow: 0 0 16px rgba(255, 179, 0, 0.2);
}

/* ── Footer note ──────────────────────────────────────────────────────── */
.idle-footer {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.06em;
    text-align: center;
    padding: 0 20px 16px;
    line-height: 1.5;
}

/* ── Transition ───────────────────────────────────────────────────────── */
.idle-fade-enter-active { transition: opacity 0.3s ease; }
.idle-fade-leave-active { transition: opacity 0.25s ease; }
.idle-fade-enter-from,
.idle-fade-leave-to    { opacity: 0; }
</style>
