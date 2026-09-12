<template>
    <!-- Trigger button — lives at the far left of the taskbar -->
    <button
        class="start-btn"
        :class="{ 'start-btn--open': open }"
        title="Start"
        @click="open = !open"
    >
        <span class="start-btn-icon">◆</span>
        <span class="start-btn-label">START</span>
    </button>

    <!-- Overlay panel — anchored above the taskbar, same slide-up pattern as
         GameMenu.vue's system menu, mirrored to the left edge -->
    <Transition name="start-slide">
        <div v-if="open" class="start-panel" @click.self="open = false">
            <div class="start-inner">

                <div class="start-header">
                    <span class="start-title">◈ PROGRAMS</span>
                    <button class="start-close" @click="open = false">✕</button>
                </div>

                <div class="start-items">
                    <button
                        v-for="program in PROGRAMS"
                        :key="program.id"
                        class="start-item"
                        @click="onSelect(program)"
                    >
                        <span class="si-icon">{{ program.icon }}</span>
                        <span class="si-label">{{ program.label }}</span>
                        <span v-if="isRunning(program)" class="si-running" title="Running" />
                    </button>
                </div>

            </div>
        </div>
    </Transition>
</template>

<script setup>
// StartMenu — Windows-style Start button + program list, lives in the
// taskbar next to the pinned app row. Reuses the same PROGRAMS list Desktop
// icons render from (constants/spliceApps.js) so there's one source of truth
// for "what's launchable" rather than a third hand-written copy of it.
//
// Purely presentational + its own open/closed state (same shape as
// GameMenu.vue) — it emits 'launch'/'open-map', identical contract to
// Desktop.vue, so NavBar just forwards both without re-implementing them.
import { ref } from 'vue';
import { PROGRAMS } from '@/constants/spliceApps.js';
import { useWindowManager } from '@/composables/useWindowManager.js';

const emit = defineEmits(['launch', 'open-map']);

const open = ref(false);
const windowManager = useWindowManager();

// Small "already running" indicator — Map is a real window (windowManager
// knows it); Browser pages don't have per-page window identity yet, so only
// the Browser program itself (not each SPLICE page) can show this today.
function isRunning(program) {
    if (program.kind === 'window') return windowManager.isOpen(program.id);
    if (program.id === 'browser') return windowManager.isOpen('browser');
    return false;
}

function onSelect(program) {
    open.value = false;
    if (program.kind === 'window') {
        emit('open-map');
    } else {
        emit('launch', program.url);
    }
}
</script>

<style scoped>
/* ── Trigger button ───────────────────────────────────────────────────────── */
.start-btn {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 18px;
    height: 100%;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
    flex-shrink: 0;
}

.start-btn:hover { background: rgba(0, 255, 255, 0.05); }

.start-btn--open {
    background: rgba(0, 255, 255, 0.07);
    border-bottom-color: #00FFFF;
}

.start-btn-icon  { font-size: 15px; color: #00FFFF; line-height: 1; }
.start-btn-label { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgba(0, 255, 255, 0.6); letter-spacing: 0.12em; }

.start-btn:hover .start-btn-icon,
.start-btn:hover .start-btn-label  { color: #00FFFF; }
.start-btn--open .start-btn-icon,
.start-btn--open .start-btn-label  { color: #00FFFF; }

/* ── Panel ────────────────────────────────────────────────────────────────── */
.start-panel {
    position: absolute;
    bottom: 42px;
    left: 0;
    z-index: 60;
}

.start-inner {
    width: 220px;
    background: #08080f;
    border: 1px solid rgba(0, 255, 255, 0.2);
    border-bottom: none;
    box-shadow:
        0 0 0 1px rgba(0, 255, 255, 0.04),
        8px -8px 32px rgba(0, 0, 0, 0.6);
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.start-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px 8px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.08);
}

.start-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.18em;
}

.start-close {
    background: transparent;
    border: none;
    color: rgba(0, 255, 255, 0.25);
    font-size: 10px;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.12s;
}
.start-close:hover { color: #FF3333; }

/* ── Items ───────────────────────────────────────────────────────────────── */
.start-items {
    display: flex;
    flex-direction: column;
    padding: 6px 0;
}

.start-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    width: 100%;
    transition: background 0.12s;
}
.start-item:hover { background: rgba(0, 255, 255, 0.05); }

.si-icon {
    font-size: 13px;
    color: rgba(0, 255, 255, 0.5);
    width: 18px;
    flex-shrink: 0;
    line-height: 1;
}

.si-label {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.8);
    letter-spacing: 0.1em;
    flex: 1;
}

.si-running {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #00FF88;
    box-shadow: 0 0 5px rgba(0, 255, 136, 0.8);
    flex-shrink: 0;
}

/* ── Transition ───────────────────────────────────────────────────────────── */
.start-slide-enter-active,
.start-slide-leave-active {
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.start-slide-enter-from,
.start-slide-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
