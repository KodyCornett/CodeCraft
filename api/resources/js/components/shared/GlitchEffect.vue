<template>
    <Transition name="ge-fade" @after-leave="emit('done')">
        <div
            v-if="active"
            class="ge-root"
            :class="[`ge-root--${type.replace(/\(\d+(\.\d+)?\)/g, '').replace(/,/g, '-')}`, overlay ? 'ge-root--overlay' : 'ge-root--inline']"
            :style="rootStyle"
            aria-hidden="true"
        >
            <!-- ── CHROMATIC — RGB channel separation ────────────────────── -->
            <template v-if="shows('chromatic')">
                <div class="ge-layer ge-layer--r" :style="chromaticStyle('r')" />
                <div class="ge-layer ge-layer--g" :style="chromaticStyle('g')" />
                <div class="ge-layer ge-layer--b" :style="chromaticStyle('b')" />
            </template>

            <!-- ── SCAN — horizontal scanlines sweeping ─────────────────── -->
            <template v-if="shows('scan')">
                <div class="ge-scanlines" :style="scanStyle" />
            </template>

            <!-- ── BARS — horizontal displacement bars ──────────────────── -->
            <template v-if="shows('bars')">
                <div class="ge-bars">
                    <div
                        v-for="i in barCount"
                        :key="i"
                        class="ge-bar"
                        :style="barStyle(i)"
                    />
                </div>
            </template>

            <!-- ── STATIC — noise field ──────────────────────────────────── -->
            <template v-if="shows('static')">
                <div class="ge-static" :style="staticStyle" />
            </template>

            <!-- ── DISSOLVE — melting / liquefying elements ──────────────── -->
            <template v-if="shows('dissolve')">
                <div class="ge-dissolve">
                    <div
                        v-for="i in dissolveCount"
                        :key="i"
                        class="ge-dissolve-strip"
                        :style="dissolveStyle(i)"
                    />
                </div>
            </template>

            <!-- ── FLICKER — rapid opacity stutter ──────────────────────── -->
            <template v-if="shows('flicker')">
                <div class="ge-flicker" :style="flickerStyle" />
            </template>

            <!-- ── SCRAMBLE — random character field ────────────────────── -->
            <template v-if="shows('scramble')">
                <div class="ge-scramble">
                    <span
                        v-for="ch in scrambledChars"
                        :key="ch.id"
                        class="ge-scramble-ch"
                        :style="{ opacity: ch.opacity }"
                    >{{ ch.c }}</span>
                </div>
            </template>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps({
    /**
     * type — which glitch effect(s) to render.
     *   'chromatic'   — RGB channel separation
     *   'scan'        — sweeping horizontal scanlines
     *   'bars'        — horizontal displacement bars
     *   'static'      — noise field
     *   'dissolve'    — melting strips
     *   'flicker'     — opacity stutter
     *   'scramble'    — random character field
     *   'full'        — all of the above (Viral Breach level)
     *
     * Can also be a comma-separated combination: 'chromatic,bars,scan'
     *
     * Each effect in the list can carry its own intensity override on a 1–5
     * scale, in parentheses: 'bars(2),chromatic(4)' runs bars light and
     * chromatic heavy, regardless of the global `intensity` prop below.
     * Effects without a parenthetical value fall back to `intensity`.
     */
    type: {
        type:    String,
        default: 'chromatic',
    },

    /**
     * intensity — 0.0 to 1.0.
     * Controls opacity, displacement, bar count, etc.
     * 0.2 = subtle background noise
     * 0.6 = noticeable interference
     * 1.0 = full assault
     */
    intensity: {
        type:    Number,
        default: 0.6,
    },

    /**
     * duration — milliseconds the effect runs before auto-stopping.
     * 0 = run indefinitely until :active is set to false externally.
     */
    duration: {
        type:    Number,
        default: 0,
    },

    /**
     * active — externally controlled on/off.
     * When duration > 0, the component manages this itself.
     * When duration = 0, the parent controls it via v-bind.
     */
    active: {
        type:    Boolean,
        default: true,
    },

    /**
     * overlay — true renders the effect as a position:fixed full-screen layer.
     * false renders inline (fills the nearest positioned parent).
     */
    overlay: {
        type:    Boolean,
        default: false,
    },

    /**
     * color — base tint for effects that use a single colour.
     * Defaults to the classic green terminal colour.
     */
    color: {
        type:    String,
        default: '#00ff9d',
    },
});

const emit = defineEmits(['done']);

// ── Active state — auto-managed when duration > 0 ────────────────────────────
const internalActive = ref(props.active);

watch(() => props.active, v => { internalActive.value = v; });

const active = computed(() => internalActive.value);

let durationTimer = null;

watch(active, (v) => {
    if (v && props.duration > 0) {
        clearTimeout(durationTimer);
        durationTimer = setTimeout(() => {
            internalActive.value = false;
        }, props.duration);
    }
}, { immediate: true });

onUnmounted(() => clearTimeout(durationTimer));

// ── Type parsing ──────────────────────────────────────────────────────────────
//
// type also accepts a per-effect intensity override in parentheses, on a 1–5
// scale: type="bars(2),chromatic(4)" runs bars at 2/5 and chromatic at 4/5,
// independent of the global `intensity` prop. Effects listed without a
// parenthetical value fall back to the global `intensity` prop as before.

const EFFECT_NAMES = ['chromatic', 'scan', 'bars', 'static', 'dissolve', 'flicker', 'scramble'];

const parsedTypes = computed(() => {
    const map = new Map(); // effectName -> override intensity (0-1), or null (use global)
    if (props.type === 'full') {
        EFFECT_NAMES.forEach(name => map.set(name, null));
        return map;
    }
    props.type.split(',').forEach(raw => {
        const match = raw.trim().match(/^(\w+)(?:\((\d+(?:\.\d+)?)\))?$/);
        if (!match) return;
        const [, name, level] = match;
        if (!EFFECT_NAMES.includes(name)) return;
        map.set(name, level != null ? Math.max(0, Math.min(1, Number(level) / 5)) : null);
    });
    return map;
});

function shows(t) {
    return parsedTypes.value.has(t);
}

// ── Intensity helpers ─────────────────────────────────────────────────────────

const i = computed(() => Math.max(0, Math.min(1, props.intensity)));

// Resolves the effective 0-1 intensity for a single effect — its own
// parenthetical override if it has one, otherwise the global intensity prop.
function intensityFor(t) {
    const override = parsedTypes.value.get(t);
    return override != null ? override : i.value;
}

// Highest resolved intensity across all active effects — drives the root
// container's overall opacity so a low global intensity doesn't clip a
// deliberately high per-effect override.
const maxIntensity = computed(() => {
    let max = 0;
    parsedTypes.value.forEach((override) => {
        const v = override != null ? override : i.value;
        if (v > max) max = v;
    });
    return max || i.value;
});

// ── Root style ────────────────────────────────────────────────────────────────

const rootStyle = computed(() => ({
    '--ge-color':     props.color,
    '--ge-intensity': maxIntensity.value,
    opacity:          0.3 + maxIntensity.value * 0.7,
}));

// ── Chromatic ─────────────────────────────────────────────────────────────────

function chromaticStyle(channel) {
    const shift = intensityFor('chromatic') * 6;
    const offsets = {
        r: `translateX(${shift}px)`,
        g: `translateX(-${shift * 0.5}px) translateY(${shift * 0.3}px)`,
        b: `translateX(-${shift}px)`,
    };
    return { transform: offsets[channel] };
}

// ── Scanlines ─────────────────────────────────────────────────────────────────

const scanStyle = computed(() => {
    const v = intensityFor('scan');
    return {
        backgroundSize: `100% ${Math.round(2 + v * 4)}px`,
        opacity:        0.03 + v * 0.08,
    };
});

// ── Bars ──────────────────────────────────────────────────────────────────────

const barCount = computed(() => Math.round(3 + intensityFor('bars') * 12));

function barStyle(idx) {
    // Deterministic-ish but visually varied per bar
    const v      = intensityFor('bars');
    const seed   = idx * 137.5;
    const top    = (seed % 100);
    const h      = 1 + (seed % 7) * v;
    const offset = ((seed % 20) - 10) * v;
    return {
        top:       `${top}%`,
        height:    `${h}px`,
        transform: `translateX(${offset}px)`,
        opacity:   0.2 + (idx % 3) * 0.15 * v,
        animationDuration: `${0.08 + (idx % 4) * 0.03}s`,
        animationDelay:    `${(idx % 5) * 0.02}s`,
    };
}

// ── Static ────────────────────────────────────────────────────────────────────

const staticStyle = computed(() => ({
    opacity: 0.04 + intensityFor('static') * 0.18,
}));

// ── Dissolve ──────────────────────────────────────────────────────────────────

const dissolveCount = computed(() => Math.round(4 + intensityFor('dissolve') * 8));

function dissolveStyle(idx) {
    const v      = intensityFor('dissolve');
    const seed   = idx * 73.1;
    const top    = (seed % 100);
    const h      = 2 + (seed % 20) * v;
    const skew   = ((seed % 10) - 5) * v;
    return {
        top:              `${top}%`,
        height:           `${h}px`,
        transform:        `skewX(${skew}deg)`,
        opacity:          0.3 + v * 0.5,
        animationDuration:`${0.3 + (idx % 5) * 0.1}s`,
        animationDelay:   `${(idx % 6) * 0.04}s`,
    };
}

// ── Flicker ───────────────────────────────────────────────────────────────────

const flickerStyle = computed(() => {
    const v = intensityFor('flicker');
    return {
        animationDuration: `${0.05 + (1 - v) * 0.1}s`,
        opacity: v * 0.15,
    };
});

// ── Scramble ──────────────────────────────────────────────────────────────────

const GLITCH_CHARS = '!@#$%^&*<>{}[]|\\/?~`01░▒▓█▌▐';
const scrambledChars = ref([]);
let scrambleTimer = null;

function refreshScramble() {
    const v     = intensityFor('scramble');
    const count = Math.round(8 + v * 24);
    scrambledChars.value = Array.from({ length: count }, (_, idx) => ({
        id:      idx,
        c:       GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)],
        opacity: 0.1 + Math.random() * 0.6 * v,
    }));
}

watch(active, (v) => {
    if (v && shows('scramble')) {
        refreshScramble();
        scrambleTimer = setInterval(refreshScramble, 80);
    } else {
        clearInterval(scrambleTimer);
    }
}, { immediate: true });

// Also re-subscribe if type changes while active
watch(() => props.type, () => {
    clearInterval(scrambleTimer);
    if (active.value && shows('scramble')) {
        refreshScramble();
        scrambleTimer = setInterval(refreshScramble, 80);
    }
});

onUnmounted(() => clearInterval(scrambleTimer));
</script>

<style scoped>
/* ── Root ────────────────────────────────────────────────────────────────────── */
.ge-root {
    pointer-events: none;
    overflow: hidden;
}
.ge-root--overlay {
    position: fixed;
    inset: 0;
    z-index: 9998;
}
.ge-root--inline {
    position: absolute;
    inset: 0;
    z-index: 1;
}

/* ── Chromatic ───────────────────────────────────────────────────────────────── */
.ge-layer {
    position: absolute;
    inset: 0;
    mix-blend-mode: screen;
    animation: ge-chromatic-shift 0.09s steps(1) infinite;
}
.ge-layer--r {
    background: rgba(255, 0,  60,  0.12);
    animation-delay: 0s;
}
.ge-layer--g {
    background: rgba(0,  255, 80,  0.07);
    animation-delay: 0.03s;
}
.ge-layer--b {
    background: rgba(0,  80,  255, 0.10);
    animation-delay: 0.06s;
}
@keyframes ge-chromatic-shift {
    0%   { opacity: 1; }
    33%  { opacity: 0.7; }
    66%  { opacity: 0.9; }
    100% { opacity: 1; }
}

/* ── Scanlines ───────────────────────────────────────────────────────────────── */
.ge-scanlines {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,    transparent    2px,
        rgba(0,255,100,0.06) 2px, rgba(0,255,100,0.06) 4px
    );
    animation: ge-scan-move 0.25s linear infinite;
}
@keyframes ge-scan-move {
    from { background-position-y: 0; }
    to   { background-position-y: 8px; }
}

/* ── Bars ────────────────────────────────────────────────────────────────────── */
.ge-bars {
    position: absolute;
    inset: 0;
}
.ge-bar {
    position: absolute;
    left: 0; right: 0;
    background: rgba(0, 255, 100, 0.22);
    animation: ge-bar-flicker steps(1) infinite;
}
@keyframes ge-bar-flicker {
    0%, 100% { opacity: 1; transform: translateX(0); }
    50%       { opacity: 0.1; }
}

/* ── Static ──────────────────────────────────────────────────────────────────── */
.ge-static {
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    background-size: 200px 200px;
    animation: ge-static-shift 0.07s steps(1) infinite;
}
@keyframes ge-static-shift {
    0%   { background-position: 0 0; }
    25%  { background-position: 30px 15px; }
    50%  { background-position: -20px 40px; }
    75%  { background-position: 10px -20px; }
    100% { background-position: 0 0; }
}

/* ── Dissolve ────────────────────────────────────────────────────────────────── */
.ge-dissolve {
    position: absolute;
    inset: 0;
}
.ge-dissolve-strip {
    position: absolute;
    left: 0; right: 0;
    background: var(--ge-color, #00ff9d);
    animation: ge-dissolve-drip linear infinite;
}
@keyframes ge-dissolve-drip {
    0%   { transform: scaleY(1) translateY(0); opacity: 0.4; }
    100% { transform: scaleY(3) translateY(20px); opacity: 0; }
}

/* ── Flicker ─────────────────────────────────────────────────────────────────── */
.ge-flicker {
    position: absolute;
    inset: 0;
    background: var(--ge-color, #00ff9d);
    animation: ge-flicker-pulse steps(1) infinite;
}
@keyframes ge-flicker-pulse {
    0%, 100% { opacity: 0; }
    50%       { opacity: 1; }
}

/* ── Scramble ────────────────────────────────────────────────────────────────── */
.ge-scramble {
    position: absolute;
    inset: 0;
    display: flex;
    flex-wrap: wrap;
    align-content: center;
    justify-content: center;
    gap: 4px;
    padding: 20px;
}
.ge-scramble-ch {
    font-family: 'JetBrains Mono', monospace;
    font-size: 16px;
    color: var(--ge-color, #00ff9d);
}

/* ── Transitions ─────────────────────────────────────────────────────────────── */
.ge-fade-enter-active,
.ge-fade-leave-active { transition: opacity 0.1s; }
.ge-fade-enter-from,
.ge-fade-leave-to     { opacity: 0; }
</style>
