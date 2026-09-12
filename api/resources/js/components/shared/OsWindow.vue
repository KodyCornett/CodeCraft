<template>
    <!-- Positioning/centering layer only — no backdrop dimming and no
         click-outside-to-close, since multiple of these can be on screen at
         once (see the "pointer-events: none" note in the style block below
         for how clicks reach whatever's behind the window's own gutter). -->
    <div class="os-overlay">

        <div
            ref="windowEl"
            class="os-window"
            :class="appClass"
            :style="[accentStyle, windowStyle]"
            @mousedown="emit('focus')"
        >

            <!-- ── Title bar — drag to move, double-click to maximize/restore ── -->
            <div
                class="os-titlebar"
                @mousedown="startDrag"
                @dblclick="onTitlebarDblClick"
            >
                <div class="traffic-lights">
                    <button
                        v-if="closable"
                        class="tl tl-close"
                        title="Close"
                        @mousedown.stop
                        @click="emit('close')"
                    />
                    <button
                        v-if="minimizable"
                        class="tl tl-min"
                        title="Minimize"
                        @mousedown.stop
                        @click="emit('minimize')"
                    />
                    <button
                        v-if="maximizable"
                        class="tl tl-max"
                        title="Maximize"
                        @mousedown.stop
                        @click="emit('maximize')"
                    />
                </div>

                <span v-if="icon" class="os-appicon">{{ icon }}</span>
                <span class="os-appname">{{ title }}</span>

                <button v-if="closable" class="titlebar-close" @mousedown.stop @click="emit('close')">✕ CLOSE</button>
            </div>

            <!-- ── Program content — each program supplies its own body ──────── -->
            <div class="os-content">
                <slot />
            </div>

            <!-- ── Resize handles — hidden while maximized ────────────────────── -->
            <template v-if="!maximized">
                <div class="os-resize os-resize--e"  @mousedown="startResize($event, 'right')" />
                <div class="os-resize os-resize--s"  @mousedown="startResize($event, 'bottom')" />
                <div class="os-resize os-resize--se" @mousedown="startResize($event, 'corner')" />
            </template>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

// OsWindow — shared "OS shell" window chrome.
//
// Every SPLICE program (Browser, Map, Rig, Bank, etc.) mounts inside one of
// these instead of rolling its own overlay/titlebar markup. This component
// owns ONLY presentation (backdrop, titlebar, traffic lights, drag/resize/
// maximize gestures) — it has no idea what's inside it and no idea which
// programs exist. Which programs are open, focused, minimized, or where
// they're positioned/sized is owned by useWindowManager.js; the parent
// (Game.vue for Map, InGameBrowser.vue for Browser) wires the two together —
// this component only reports gestures upward (close/minimize/maximize/focus/
// update:geometry) and never touches useWindowManager itself, so it stays
// reusable for any future program window without knowing this one exists.
//
// Per-program identity (title/icon/accent color) is passed in via props so
// each program can look like its own app while reusing this one component.

const props = defineProps({
    title:        { type: String,  required: true },
    icon:         { type: String,  default: '' },
    accent:       { type: String,  default: '#00FFFF' },   // titlebar/border accent — per-program skin
    appClass:     { type: String,  default: '' },          // extra class on .os-window for deeper per-program styling
    maxWidth:     { type: String,  default: '960px' },     // cap on window width — e.g. 'none' for a map-sized program
    zIndex:       { type: Number,  default: 10 },          // stacking order — bind this to useWindowManager's zIndexOf(id)
    closable:     { type: Boolean, default: true },
    minimizable:  { type: Boolean, default: true },
    maximizable:  { type: Boolean, default: false },
    // Custom position/size in px ({x,y,width,height}), or null to use the
    // default CSS-centered layout below. Owned by useWindowManager, not this
    // component — OsWindow only reports drag/resize gestures upward via
    // 'update:geometry'; the caller decides what to do with them.
    geometry:     { type: Object,  default: null },
    maximized:    { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'minimize', 'maximize', 'focus', 'update:geometry']);

const windowEl = ref(null);

// Exposes accent/width/z-index as CSS custom properties so the stylesheet
// below (and any per-program appClass override) can reuse them without
// prop-drilling individual values through every rule. Must stay reactive —
// zIndex in particular changes every time focus switches between windows.
const accentStyle = computed(() => ({
    '--os-accent':    props.accent,
    '--os-max-width': props.maxWidth,
    '--os-z':         props.zIndex,
}));

// Maximized fills the whole desktop area edge-to-edge; a custom geometry
// (after the player's first drag/resize) positions/sizes it in px; neither
// set falls back to the default CSS-centered layout (.os-overlay's flex
// centering + the max-width var above). Either override needs maxWidth
// cleared too, or the CSS var would keep clamping a resized/maximized width.
const windowStyle = computed(() => {
    if (props.maximized) {
        return { position: 'absolute', inset: '0', maxWidth: 'none' };
    }
    if (props.geometry) {
        return {
            position: 'absolute',
            left:     props.geometry.x + 'px',
            top:      props.geometry.y + 'px',
            width:    props.geometry.width + 'px',
            height:   props.geometry.height + 'px',
            maxWidth: 'none',
        };
    }
    return {};
});

// ── Drag (titlebar) + resize (edge handles) ─────────────────────────────────
// Both work the same way: read the window's actual current on-screen box via
// getBoundingClientRect (works whether it's still on the default centered
// layout or already has custom geometry), then track the cursor and emit an
// updated {x,y,width,height} — relative to the window's own positioned
// parent (.os-overlay) — on every move. The caller (useWindowManager, via
// the parent component) is what actually remembers it.
const MIN_VISIBLE = 48; // px of titlebar that must always stay reachable
const MIN_WIDTH   = 380;
const MIN_HEIGHT  = 280;

function startDrag(e) {
    if (props.maximized || e.button !== 0) return;

    const parentRect = windowEl.value.parentElement.getBoundingClientRect();
    const rect       = windowEl.value.getBoundingClientRect();
    const originLeft = rect.left - parentRect.left;
    const originTop  = rect.top  - parentRect.top;
    const { width, height } = rect;
    const startX = e.clientX;
    const startY = e.clientY;

    function onMove(ev) {
        let x = originLeft + (ev.clientX - startX);
        let y = originTop  + (ev.clientY - startY);
        x = Math.max(MIN_VISIBLE - width, Math.min(x, parentRect.width - MIN_VISIBLE));
        y = Math.max(0, Math.min(y, parentRect.height - MIN_VISIBLE));
        emit('update:geometry', { x, y, width, height });
    }
    function onUp() {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function startResize(e, edge) {
    // Stops the mousedown reaching the titlebar/root (no drag-start, and no
    // redundant focus emit — resizing focuses explicitly below instead).
    e.stopPropagation();
    if (props.maximized || e.button !== 0) return;
    emit('focus');

    const parentRect = windowEl.value.parentElement.getBoundingClientRect();
    const rect       = windowEl.value.getBoundingClientRect();
    const originLeft   = rect.left - parentRect.left;
    const originTop    = rect.top  - parentRect.top;
    const startWidth   = rect.width;
    const startHeight  = rect.height;
    const startX = e.clientX;
    const startY = e.clientY;

    function onMove(ev) {
        let width  = startWidth;
        let height = startHeight;
        if (edge === 'right' || edge === 'corner') {
            width = Math.max(MIN_WIDTH, Math.min(startWidth + (ev.clientX - startX), parentRect.width - originLeft));
        }
        if (edge === 'bottom' || edge === 'corner') {
            height = Math.max(MIN_HEIGHT, Math.min(startHeight + (ev.clientY - startY), parentRect.height - originTop));
        }
        emit('update:geometry', { x: originLeft, y: originTop, width, height });
    }
    function onUp() {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function onTitlebarDblClick() {
    if (props.maximizable) emit('maximize');
}
</script>

<style scoped>
/* ── Overlay (centering layer, not a backdrop) ───────────────────────────────
   No background — multiple of these can be stacked at once, so dimming one
   would wash out whatever's on the layers below it. pointer-events:none here
   means clicks in the padding/gutter around a non-full-width window (e.g.
   Browser's centered 960px box) fall through to whatever's beneath instead
   of being swallowed by empty space; .os-window below re-enables them. */
.os-overlay {
    position: absolute;
    inset: 0;
    z-index: var(--os-z);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    pointer-events: none;
}

/* ── Window ───────────────────────────────────────────────────────────────── */
.os-window {
    position: relative; /* anchor for the resize handles below, regardless of
                            whether the inline style makes this element itself
                            static (default layout) or absolute (dragged/maximized) */
    pointer-events: auto;
    width: 100%;
    height: 100%;
    max-width: var(--os-max-width);
    display: flex;
    flex-direction: column;
    background: #0a0a12;
    border: 1px solid color-mix(in srgb, var(--os-accent) 20%, transparent);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--os-accent) 5%, transparent),
        0 24px 60px rgba(0, 0, 0, 0.7);
    overflow: hidden;
}

/* ── Title bar ────────────────────────────────────────────────────────────── */
.os-titlebar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 14px;
    background: #080810;
    border-bottom: 1px solid color-mix(in srgb, var(--os-accent) 7%, transparent);
    flex-shrink: 0;
    user-select: none;
    cursor: move;
}

.traffic-lights {
    display: flex;
    gap: 7px;
    flex-shrink: 0;
}

.tl {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    padding: 0;
}
.tl-close { background: #FF3B30; }
.tl-min   { background: #FFB300; }
.tl-max   { background: #00FF88; }
.tl-close:hover { background: #FF6060; }
.tl-min:hover   { background: #FFC94D; }
.tl-max:hover   { background: #4DFFAA; }

.os-appicon {
    font-size: 11px;
    flex-shrink: 0;
}

.os-appname {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: color-mix(in srgb, var(--os-accent) 30%, transparent);
    letter-spacing: 0.18em;
    flex: 1;
    text-align: center;
}

.titlebar-close {
    background: transparent;
    border: 1px solid rgba(255, 51, 51, 0.3);
    color: rgba(255, 51, 51, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.titlebar-close:hover {
    background: rgba(255, 51, 51, 0.12);
    color: #FF3333;
    border-color: rgba(255, 51, 51, 0.7);
}

/* ── Content ──────────────────────────────────────────────────────────────── */
.os-content {
    position: relative; /* positioning context for absolutely-positioned program content (e.g. HUD) */
    flex: 1;
    min-height: 0;
    overflow: auto;
}

/* ── Resize handles — invisible hit-areas along the edges/corner ─────────────
   Right + bottom edges resize one axis each; the corner resizes both. Left/top
   edges intentionally don't get one (would need to move x/y too, not just
   size) — right/bottom/corner covers the common case without that complexity.
   Sit just inside the box, not straddling it — .os-window clips overflow, so
   anything positioned outside its own bounds would be invisible/unreachable. */
.os-resize {
    position: absolute;
    z-index: 5;
}
.os-resize--e {
    top: 0;
    right: 0;
    width: 6px;
    height: 100%;
    cursor: ew-resize;
}
.os-resize--s {
    left: 0;
    bottom: 0;
    width: 100%;
    height: 6px;
    cursor: ns-resize;
}
.os-resize--se {
    right: 0;
    bottom: 0;
    width: 14px;
    height: 14px;
    cursor: nwse-resize;
}
</style>
