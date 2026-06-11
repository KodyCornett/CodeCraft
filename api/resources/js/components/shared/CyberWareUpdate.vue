<template>
    <!-- Continuous low-level glitch throughout the install -->
    <GlitchEffect
        type="chromatic,bars,scan"
        :intensity="glitchIntensity"
        :active="true"
        overlay
    />

    <div class="cwu-overlay">
        <div class="cwu-scanline" />

        <!-- Header block -->
        <div class="cwu-box">
            <div class="cwu-header-bar">
                ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓
            </div>
            <div class="cwu-header-title">
                [CRITICAL] — INCOMING CORTEX PATCH
            </div>
            <div class="cwu-header-meta">
                <span>TARGET: {{ rigId }}</span>
                <span>ORIGIN: SPLICE // UNVERIFIED</span>
                <span>AUTH: BYPASSED</span>
            </div>
            <div class="cwu-header-bar">
                ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓
            </div>

            <!-- Install log -->
            <div class="cwu-log" ref="logEl">
                <div
                    v-for="(line, i) in visibleLines"
                    :key="i"
                    class="cwu-line"
                    :class="{
                        'cwu-line--error':     line.type === 'error',
                        'cwu-line--progress':  line.type === 'progress',
                        'cwu-line--complete':  line.type === 'complete',
                        'cwu-line--highlight': line.type === 'highlight',
                        'cwu-line--stall':     line.stall,
                    }"
                >{{ line.text }}</div>
                <span v-if="!done" class="cwu-cursor">█</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import GlitchEffect from './GlitchEffect.vue';

const emit = defineEmits(['done']);

// Fake rig ID for the header — randomised per session
const rigId = 'BH-' + Math.floor(Math.random() * 0xFFFF).toString(16).toUpperCase().padStart(4, '0');

// ── Install sequence ──────────────────────────────────────────────────────────
//
// Each entry: { text, delay (ms before this line appears), type, stall }
//   type: 'default' | 'error' | 'progress' | 'complete' | 'highlight'
//   stall: if true, apply stall CSS class (orange + animation)
//
const SEQUENCE = [
    { text: '',                                                                      delay: 200,  type: 'default' },
    { text: '  > SCANNING RIG..................... [BLACKHAT v1.0] DETECTED',        delay: 350,  type: 'default' },
    { text: '  > SPLICE RELAY: OFFLINE  ← FORCING HANDSHAKE',                       delay: 400,  type: 'default' },
    { text: '  > UNAUTHORIZED PATCH: SPLICE_CONTACT_RELAY_v2.1.4',                  delay: 350,  type: 'default' },
    { text: '',                                                                      delay: 200,  type: 'default' },
    { text: '    [░░░░░░░░░░░░░░░░░░░░░░] 0%',                                     delay: 250,  type: 'progress' },
    { text: '    [██████░░░░░░░░░░░░░░░░] 22%  ← STALL DETECTED',                  delay: 700,  type: 'progress', stall: true, glitchBurst: true },
    { text: '    [NULL PTR] — RETRYING SECTOR 0x4F...',                              delay: 550,  type: 'error' },
    { text: '    [████████████░░░░░░░░░░] 48%',                                     delay: 320,  type: 'progress' },
    { text: '    [██████████████████░░░░] 72%  ← INTERFERENCE',                     delay: 580,  type: 'progress', stall: true, glitchBurst: true },
    { text: '    [████████████████████░░] 88%',                                     delay: 240,  type: 'progress' },
    { text: '    [████████████████████████] 100%  ✓ INSTALLED',                    delay: 220,  type: 'complete' },
    { text: '',                                                                      delay: 350,  type: 'default' },
    { text: '  > SPLICE_RELAY: ACTIVE',                                             delay: 280,  type: 'default' },
    { text: '  > NEW CONTACT ROUTE: ESTABLISHED',                                   delay: 320,  type: 'default' },
    { text: '  > INITIATING HANDSHAKE...',                                          delay: 420,  type: 'default' },
    { text: '',                                                                      delay: 250,  type: 'default' },
    { text: "  // SIGNAL LOCKED",                                                   delay: 180,  type: 'highlight' },
    { text: "  // BROWNE'S ADDITION — SECTOR 7",                                    delay: 160,  type: 'highlight' },
    { text: '',                                                                      delay: 600,  type: 'default' },   // hold before done
];

const visibleLines    = ref([]);
const done            = ref(false);
const glitchIntensity = ref(0.35);
const logEl           = ref(null);

let timers = [];

onMounted(() => {
    let cumulative = 0;

    SEQUENCE.forEach((entry, i) => {
        cumulative += entry.delay;

        const t = setTimeout(() => {
            visibleLines.value.push({ text: entry.text, type: entry.type ?? 'default', stall: !!entry.stall });

            // Burst the glitch intensity at stall/error moments
            if (entry.glitchBurst) {
                glitchIntensity.value = 1.0;
                const restore = setTimeout(() => { glitchIntensity.value = 0.35; }, 300);
                timers.push(restore);
            }

            // Scroll to bottom as lines appear
            nextTick(() => {
                if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight;
            });

            // Emit done after the final entry
            if (i === SEQUENCE.length - 1) {
                done.value = true;
                const finishTimer = setTimeout(() => emit('done'), 300);
                timers.push(finishTimer);
            }
        }, cumulative);

        timers.push(t);
    });
});

onUnmounted(() => {
    timers.forEach(t => clearTimeout(t));
});
</script>

<style scoped>
/* ── Overlay ──────────────────────────────────────────────────────────────── */
.cwu-overlay {
    position: fixed;
    inset: 0;
    z-index: 10001;
    background: rgba(4, 0, 10, 0.98);
    display: flex;
    align-items: center;
    justify-content: center;
}

.cwu-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,                     transparent                     3px,
        rgba(100, 0, 200, 0.018) 3px,   rgba(100, 0, 200, 0.018) 6px
    );
    pointer-events: none;
    animation: cwu-scan 0.4s linear infinite;
}
@keyframes cwu-scan {
    from { background-position-y: 0; }
    to   { background-position-y: 6px; }
}

/* ── Box ──────────────────────────────────────────────────────────────────── */
.cwu-box {
    position: relative;
    width: min(640px, 94vw);
    background: rgba(8, 2, 18, 0.99);
    border: 1px solid rgba(140, 40, 255, 0.3);
    box-shadow:
        0 0 60px rgba(140, 40, 255, 0.08),
        inset 0 0 30px rgba(0, 0, 0, 0.7);
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    animation: cwu-entry 0.1s steps(2) forwards;
}
@keyframes cwu-entry {
    0%   { opacity: 0; transform: scale(1.03); }
    60%  { opacity: 1; transform: scale(0.99); }
    100% { opacity: 1; transform: scale(1); }
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.cwu-header-bar {
    font-size: 7px;
    color: rgba(140, 40, 255, 0.3);
    letter-spacing: 0.02em;
    padding: 0 16px;
    overflow: hidden;
    white-space: nowrap;
    line-height: 1.4;
}

.cwu-header-title {
    font-size: 11px;
    letter-spacing: 0.2em;
    color: rgba(180, 100, 255, 0.95);
    text-align: center;
    padding: 8px 0;
    text-shadow: 0 0 14px rgba(160, 60, 255, 0.6);
    animation: cwu-title-pulse 1.4s ease-in-out infinite;
}
@keyframes cwu-title-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.7; }
}

.cwu-header-meta {
    display: flex;
    justify-content: space-between;
    padding: 2px 18px 8px;
    font-size: 8px;
    color: rgba(130, 60, 200, 0.5);
    letter-spacing: 0.1em;
}

/* ── Install log ──────────────────────────────────────────────────────────── */
.cwu-log {
    padding: 14px 20px 18px;
    max-height: 280px;
    overflow: hidden;     /* no scrollbar — lines just appear */
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.cwu-line {
    font-size: 11px;
    letter-spacing: 0.06em;
    line-height: 1.6;
    color: rgba(180, 160, 210, 0.7);
    animation: cwu-line-in 0.08s ease forwards;
}
@keyframes cwu-line-in {
    from { opacity: 0; transform: translateX(-4px); }
    to   { opacity: 1; transform: translateX(0); }
}

.cwu-line--error {
    color: rgba(255, 80, 80, 0.9);
    text-shadow: 0 0 6px rgba(255, 50, 50, 0.4);
}

.cwu-line--progress {
    color: rgba(160, 140, 200, 0.85);
    font-variant-numeric: tabular-nums;
}

/* Stall lines pulse orange */
.cwu-line--stall {
    color: rgba(255, 160, 40, 0.9);
    text-shadow: 0 0 8px rgba(255, 140, 20, 0.4);
    animation: cwu-stall-pulse 0.35s steps(1) 4, cwu-line-in 0.08s ease forwards;
}
@keyframes cwu-stall-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

.cwu-line--complete {
    color: rgba(80, 255, 150, 0.95);
    text-shadow: 0 0 10px rgba(60, 255, 120, 0.5);
}

.cwu-line--highlight {
    color: rgba(200, 160, 255, 1);
    letter-spacing: 0.14em;
    text-shadow: 0 0 12px rgba(180, 120, 255, 0.6);
}

.cwu-cursor {
    color: rgba(160, 80, 255, 0.8);
    font-size: 11px;
    animation: cwu-blink 0.5s steps(1) infinite;
    margin-left: 2px;
}
@keyframes cwu-blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0; }
}
</style>
