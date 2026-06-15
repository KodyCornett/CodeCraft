<template>
  <Teleport to="body">
    <Transition name="ftw">
      <div
        v-if="visible"
        class="ftw-root"
        :class="{ 'ftw-root--backdrop': closeOnBackdrop }"
        @click.self="onBackdropClick"
      >

        <!-- ── Full-viewport SVG for the leader line ──────────────────────── -->
        <svg class="ftw-svg" aria-hidden="true">
          <defs>
            <filter id="ftw-glow-filter" x="-50%" y="-50%" width="200%" height="200%">
              <feGaussianBlur stdDeviation="2.5" result="blur" />
              <feMerge>
                <feMergeNode in="blur" />
                <feMergeNode in="SourceGraphic" />
              </feMerge>
            </filter>
          </defs>

          <template v-if="leader">
            <!-- Glow pass -->
            <path :d="leader.path" class="ftw-leader-glow" filter="url(#ftw-glow-filter)" />
            <!-- Dashed line -->
            <path :d="leader.path" class="ftw-leader-line" />
            <!-- Pulse ring on target anchor -->
            <circle :cx="leader.ax" :cy="leader.ay" r="6" class="ftw-anchor-ring" />
            <!-- Solid dot on target anchor -->
            <circle :cx="leader.ax" :cy="leader.ay" r="2.5" class="ftw-anchor-dot" />
          </template>
        </svg>

        <!-- ── Terminal window ─────────────────────────────────────────────── -->
        <div
          ref="windowRef"
          class="ftw-window"
          :style="windowStyle"
          role="dialog"
          :aria-label="title"
          tabindex="-1"
          @click.stop
        >
          <!-- CRT scanline overlay (purely decorative) -->
          <div class="ftw-scanline" aria-hidden="true" />

          <!-- Header -->
          <header class="ftw-header">
            <slot name="header">
              <span class="ftw-header-icon" aria-hidden="true">◈</span>
              <span class="ftw-header-title">{{ title }}</span>
            </slot>
            <button
              v-if="dismissable"
              class="ftw-close"
              aria-label="Close"
              @click="emit('dismiss')"
            >✕</button>
          </header>

          <!-- Main content via default slot -->
          <div class="ftw-body">
            <slot />
          </div>

          <!-- Optional footer slot -->
          <footer v-if="$slots.footer" class="ftw-footer">
            <slot name="footer" />
          </footer>
        </div>

      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
/**
 * FloatingTerminalWindow
 *
 * Generic floating UI shell used to anchor contextual hints, quest prompts,
 * or any panel to a specific DOM element. Content-agnostic — everything goes
 * in the default slot.
 *
 * Props
 * ─────
 * target          CSS selector string | HTMLElement | Vue ref  — element to point at
 * visible         Boolean     — show / hide (also supports v-model:visible)
 * title           String      — text shown in the default header slot
 * windowWidth     Number      — pixel width of the terminal box (default 320)
 * offset          Number      — gap in px between target and window (default 24)
 * placement       'auto' | 'top' | 'right' | 'bottom' | 'left'
 * dismissable     Boolean     — show the ✕ close button (default true)
 * closeOnBackdrop Boolean     — emit 'dismiss' on click outside window (default false)
 *
 * Emits
 * ─────
 * dismiss         — user clicked ✕ or backdrop (if closeOnBackdrop)
 *
 * Slots
 * ─────
 * default         — main content (required)
 * header          — replaces the entire header bar
 * footer          — rendered below the body when provided
 *
 * Usage
 * ─────
 * <FloatingTerminalWindow target="#node-K8" title="OBJECTIVE" :visible="show" @dismiss="show = false">
 *   <p>Click this node to jack in.</p>
 * </FloatingTerminalWindow>
 */
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps({
    target: {
        type:    [String, Object],
        default: null,
    },
    visible: {
        type:    Boolean,
        default: true,
    },
    title: {
        type:    String,
        default: 'TERMINAL',
    },
    windowWidth: {
        type:    Number,
        default: 320,
    },
    offset: {
        type:    Number,
        default: 24,
    },
    placement: {
        type:      String,
        default:   'auto',
        validator: v => ['auto', 'top', 'right', 'bottom', 'left'].includes(v),
    },
    dismissable: {
        type:    Boolean,
        default: true,
    },
    closeOnBackdrop: {
        type:    Boolean,
        default: false,
    },
});

const emit = defineEmits(['dismiss', 'update:visible']);

// ── Internal refs ─────────────────────────────────────────────────────────────

const windowRef    = ref(null);   // the terminal window element
const windowHeight = ref(200);    // measured; fallback 200
const targetRect   = ref(null);   // live DOMRect of the target element

// ── Target resolution ─────────────────────────────────────────────────────────

function resolveTarget() {
    const t = props.target;
    if (!t) return null;
    if (typeof t === 'string')              return document.querySelector(t);
    if (t instanceof Element)              return t;
    if (t && typeof t === 'object' && t.value instanceof Element) return t.value; // Vue ref
    return null;
}

// ── Live position tracking ────────────────────────────────────────────────────
// rAF loop — handles scroll, CSS transitions, and map layout shifts cleanly.
// Cancelled immediately when visible goes false.

let _rafId = null;
let _lastLeft = -1, _lastTop = -1, _lastW = -1, _lastH = -1;

function _trackLoop() {
    const el = resolveTarget();
    if (el) {
        const r = el.getBoundingClientRect();
        if (r.left !== _lastLeft || r.top !== _lastTop ||
            r.width !== _lastW   || r.height !== _lastH) {
            targetRect.value = { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
            _lastLeft = r.left; _lastTop = r.top;
            _lastW = r.width;   _lastH = r.height;
        }
    } else {
        targetRect.value = null;
    }
    if (windowRef.value) {
        const wh = windowRef.value.offsetHeight;
        if (wh > 0 && wh !== windowHeight.value) windowHeight.value = wh;
    }
    _rafId = requestAnimationFrame(_trackLoop);
}

function _startTracking() {
    _stopTracking();
    _lastLeft = _lastTop = _lastW = _lastH = -1;
    _rafId = requestAnimationFrame(_trackLoop);
}

function _stopTracking() {
    if (_rafId) { cancelAnimationFrame(_rafId); _rafId = null; }
}

watch(() => props.visible, async (v) => {
    if (v) {
        await nextTick();
        _startTracking();
        windowRef.value?.focus();
    } else {
        _stopTracking();
        targetRect.value = null;
    }
}, { immediate: true });

onUnmounted(_stopTracking);

// ── Placement logic ───────────────────────────────────────────────────────────

const EDGE_MARGIN = 8;

function _clamp(val, lo, hi) { return Math.max(lo, Math.min(hi, val)); }

function _detectPlacement(rect, ww, wh, gap) {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const spaceRight  = vw - rect.right  - gap;
    const spaceLeft   = rect.left        - gap;
    const spaceBottom = vh - rect.bottom - gap;
    const spaceTop    = rect.top         - gap;

    if (spaceRight >= ww && spaceRight >= spaceLeft)  return 'right';
    if (spaceLeft  >= ww)                             return 'left';
    if (spaceBottom >= wh)                            return 'bottom';
    if (spaceTop   >= wh)                             return 'top';

    // Fallback: most space available
    const max = Math.max(spaceRight, spaceLeft, spaceBottom, spaceTop);
    if (max === spaceRight)  return 'right';
    if (max === spaceLeft)   return 'left';
    if (max === spaceBottom) return 'bottom';
    return 'top';
}

const _resolvedPlacement = computed(() => {
    if (props.placement !== 'auto') return props.placement;
    const rect = targetRect.value;
    if (!rect) return 'right';
    return _detectPlacement(rect, props.windowWidth, windowHeight.value, props.offset);
});

// ── Window position ───────────────────────────────────────────────────────────

const _pos = computed(() => {
    const rect = targetRect.value;
    const ww   = props.windowWidth;
    const wh   = windowHeight.value;
    const gap  = props.offset;
    const vw   = window.innerWidth;
    const vh   = window.innerHeight;
    const m    = EDGE_MARGIN;

    if (!rect) return { x: vw / 2 - ww / 2, y: vh / 2 - wh / 2 };

    const p  = _resolvedPlacement.value;
    const cy = _clamp(rect.top + rect.height / 2 - wh / 2, m, vh - wh - m);
    const cx = _clamp(rect.left + rect.width / 2 - ww / 2, m, vw - ww - m);

    switch (p) {
        case 'right':  return { x: Math.min(rect.right + gap, vw - ww - m), y: cy };
        case 'left':   return { x: Math.max(rect.left - ww - gap, m),      y: cy };
        case 'bottom': return { x: cx, y: Math.min(rect.bottom + gap, vh - wh - m) };
        case 'top':    return { x: cx, y: Math.max(rect.top - wh - gap, m) };
    }
    return { x: 0, y: 0 };
});

const windowStyle = computed(() => ({
    left:  _pos.value.x + 'px',
    top:   _pos.value.y + 'px',
    width: props.windowWidth + 'px',
}));

// ── Leader line ───────────────────────────────────────────────────────────────

const leader = computed(() => {
    const rect = targetRect.value;
    if (!rect) return null;

    const p  = _resolvedPlacement.value;
    const px = _pos.value.x;
    const py = _pos.value.y;
    const ww = props.windowWidth;
    const wh = windowHeight.value;

    // Anchor: centre of the target element
    const ax = rect.left + rect.width  / 2;
    const ay = rect.top  + rect.height / 2;

    // Attachment: midpoint of the window edge closest to the target
    let wx, wy, cx1, cy1, cx2, cy2;
    const CURVE = 0.45; // bezier tension (0=straight, 1=right-angle)

    switch (p) {
        case 'right':
            wx = px;           wy = py + wh / 2;
            cx1 = ax + (wx - ax) * CURVE;  cy1 = ay;
            cx2 = wx - (wx - ax) * CURVE;  cy2 = wy;
            break;
        case 'left':
            wx = px + ww;      wy = py + wh / 2;
            cx1 = ax - (ax - wx) * CURVE;  cy1 = ay;
            cx2 = wx + (ax - wx) * CURVE;  cy2 = wy;
            break;
        case 'bottom':
            wx = px + ww / 2;  wy = py;
            cx1 = ax;  cy1 = ay + (wy - ay) * CURVE;
            cx2 = wx;  cy2 = wy - (wy - ay) * CURVE;
            break;
        case 'top':
            wx = px + ww / 2;  wy = py + wh;
            cx1 = ax;  cy1 = ay - (ay - wy) * CURVE;
            cx2 = wx;  cy2 = wy + (ay - wy) * CURVE;
            break;
        default:
            return null;
    }

    return {
        ax, ay,
        path: `M ${ax} ${ay} C ${cx1} ${cy1}, ${cx2} ${cy2}, ${wx} ${wy}`,
    };
});

// ── Interactions ──────────────────────────────────────────────────────────────

function onBackdropClick() {
    if (props.closeOnBackdrop) emit('dismiss');
}
</script>

<style scoped>
/* ── Root ──────────────────────────────────────────────────────────────────── */
.ftw-root {
    position: fixed;
    inset: 0;
    z-index: 9000;
    pointer-events: none; /* let game clicks pass through by default */
}
/* When closeOnBackdrop is active, the root intercepts clicks */
.ftw-root--backdrop {
    pointer-events: auto;
}
/* Always allow interaction with the window and SVG children */
.ftw-root > * {
    pointer-events: auto;
}
/* SVG must never block clicks */
.ftw-svg {
    pointer-events: none;
}

/* ── SVG leader line canvas ────────────────────────────────────────────────── */
.ftw-svg {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    overflow: visible;
}

.ftw-leader-glow {
    fill: none;
    stroke: rgba(255, 179, 0, 0.20);
    stroke-width: 5;
    stroke-linecap: round;
}

.ftw-leader-line {
    fill: none;
    stroke: rgba(255, 179, 0, 0.60);
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-dasharray: 4 3;
    animation: ftw-march 1.2s linear infinite;
}

@keyframes ftw-march {
    to { stroke-dashoffset: -7; }
}

.ftw-anchor-dot {
    fill: #FFB300;
    filter: drop-shadow(0 0 4px rgba(255, 179, 0, 0.9));
}

.ftw-anchor-ring {
    fill: none;
    stroke: rgba(255, 179, 0, 0.35);
    stroke-width: 1;
    animation: ftw-pulse 2s ease-in-out infinite;
}

@keyframes ftw-pulse {
    0%,  100% { r: 6;  opacity: 0.35; }
    50%        { r: 10; opacity: 0.08; }
}

/* ── Terminal window ───────────────────────────────────────────────────────── */
.ftw-window {
    position: fixed;
    display: flex;
    flex-direction: column;
    max-height: 80vh;
    overflow: hidden;

    background: rgba(4, 4, 14, 0.93);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);

    border: 1px solid rgba(255, 179, 0, 0.50);
    box-shadow:
        0 0 0 1px rgba(255, 179, 0, 0.08),
        0 0 28px rgba(255, 179, 0, 0.07),
        0 8px 32px rgba(0, 0, 0, 0.70),
        inset 0 0 40px rgba(255, 179, 0, 0.025);

    font-family: 'JetBrains Mono', 'Courier New', monospace;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.82);
}

/* CRT scanline texture — purely decorative */
.ftw-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent           0px,
        transparent           3px,
        rgba(0, 0, 0, 0.06)   3px,
        rgba(0, 0, 0, 0.06)   4px
    );
    pointer-events: none;
    z-index: 1;
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.ftw-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 14px 8px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.15);
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.ftw-header-icon {
    font-size: 10px;
    color: rgba(255, 179, 0, 0.55);
    flex-shrink: 0;
    line-height: 1;
}

.ftw-header-title {
    flex: 1;
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(255, 179, 0, 0.85);
    text-shadow: 0 0 12px rgba(255, 179, 0, 0.30);
}

.ftw-close {
    background: transparent;
    border: none;
    font-family: inherit;
    font-size: 11px;
    color: rgba(255, 179, 0, 0.30);
    cursor: pointer;
    padding: 0;
    line-height: 1;
    flex-shrink: 0;
    transition: color 0.12s;
}
.ftw-close:hover { color: rgba(255, 179, 0, 0.90); }

/* ── Body ──────────────────────────────────────────────────────────────────── */
.ftw-body {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    position: relative;
    z-index: 2;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 179, 0, 0.18) transparent;
}
.ftw-body::-webkit-scrollbar       { width: 3px; }
.ftw-body::-webkit-scrollbar-thumb { background: rgba(255, 179, 0, 0.18); border-radius: 2px; }

/* ── Footer ─────────────────────────────────────────────────────────────────── */
.ftw-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 179, 0, 0.10);
    position: relative;
    z-index: 2;
}

/* ── Enter / leave transition ───────────────────────────────────────────────── */
.ftw-enter-active,
.ftw-leave-active {
    transition: opacity 0.16s ease;
}
.ftw-enter-active .ftw-window,
.ftw-leave-active .ftw-window {
    transition: opacity 0.16s ease, transform 0.16s ease;
}
.ftw-enter-from,
.ftw-leave-to {
    opacity: 0;
}
.ftw-enter-from .ftw-window,
.ftw-leave-to .ftw-window {
    opacity: 0;
    transform: translateY(-5px) scale(0.975);
}
</style>
