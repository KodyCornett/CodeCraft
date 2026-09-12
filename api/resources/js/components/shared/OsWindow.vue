<template>
    <!-- Dim backdrop — click outside window to close (when closable) -->
    <div class="os-overlay" @click.self="onBackdropClick">

        <div
            class="os-window"
            :class="appClass"
            :style="accentStyle"
            @mousedown="emit('focus')"
        >

            <!-- ── Title bar ───────────────────────────────────────────────── -->
            <div class="os-titlebar">
                <div class="traffic-lights">
                    <button
                        v-if="closable"
                        class="tl tl-close"
                        title="Close"
                        @click="emit('close')"
                    />
                    <button
                        v-if="minimizable"
                        class="tl tl-min"
                        title="Minimize"
                        @click="emit('minimize')"
                    />
                    <button
                        v-if="maximizable"
                        class="tl tl-max"
                        title="Maximize"
                        @click="emit('maximize')"
                    />
                </div>

                <span v-if="icon" class="os-appicon">{{ icon }}</span>
                <span class="os-appname">{{ title }}</span>

                <button v-if="closable" class="titlebar-close" @click="emit('close')">✕ CLOSE</button>
            </div>

            <!-- ── Program content — each program supplies its own body ──────── -->
            <div class="os-content">
                <slot />
            </div>

        </div>
    </div>
</template>

<script setup>
// OsWindow — shared "OS shell" window chrome.
//
// Every SPLICE program (Browser, Map, Rig, Bank, etc.) mounts inside one of
// these instead of rolling its own overlay/titlebar markup. This component
// owns ONLY presentation (backdrop, titlebar, traffic lights) — it has no
// idea what's inside it and no idea which programs exist. Which programs are
// open, focused, or minimized is owned by useWindowManager.js; Game.vue wires
// the two together.
//
// Per-program identity (title/icon/accent color) is passed in via props so
// each program can look like its own app while reusing this one component.

const props = defineProps({
    title:        { type: String,  required: true },
    icon:         { type: String,  default: '' },
    accent:       { type: String,  default: '#00FFFF' },   // titlebar/border accent — per-program skin
    appClass:     { type: String,  default: '' },          // extra class on .os-window for deeper per-program styling
    maxWidth:     { type: String,  default: '960px' },     // cap on window width — e.g. 'none' for a map-sized program
    closable:     { type: Boolean, default: true },
    minimizable:  { type: Boolean, default: true },
    maximizable:  { type: Boolean, default: false },
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'minimize', 'maximize', 'focus']);

function onBackdropClick() {
    if (props.closeOnBackdrop) emit('close');
}

// Exposes the accent color + width cap as CSS custom properties so the
// stylesheet below (and any per-program appClass override) can reuse them
// without prop-drilling individual values through every rule.
const accentStyle = {
    '--os-accent':    props.accent,
    '--os-max-width': props.maxWidth,
};
</script>

<style scoped>
/* ── Overlay ──────────────────────────────────────────────────────────────── */
.os-overlay {
    position: absolute;
    inset: 0;
    z-index: 50;
    background: rgba(0, 0, 0, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

/* ── Window ───────────────────────────────────────────────────────────────── */
.os-window {
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
</style>
