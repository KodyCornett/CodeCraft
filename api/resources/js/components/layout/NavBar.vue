<template>
    <div class="taskbar">

        <!-- Start Menu — far left, mirrors a real Windows taskbar layout -->
        <StartMenu
            :has-tutorial-badge="hasTutorialBadge"
            :player="player"
            @launch="url => emit('launch', url)"
            @open-window="id => emit('open-window', id)"
        />

        <div class="tb-sep" />

        <!-- Open program windows — the whole point of the taskbar now: empty
             until something is launched (from the Start Menu or a desktop
             icon), then shows up here same as real Windows. Map, File
             Explorer, and Browser are true separate programs right now (see
             useWindowManager.js); everything else still opens as a page
             inside the one Browser window. Click focuses/restores; click
             again while already focused minimizes. -->
        <template v-if="taskbarItems.length">
            <button
                v-for="win in taskbarItems"
                :key="win.id"
                class="tb-btn tb-window"
                :class="{ 'tb-btn--active': win.focused, 'tb-window--minimized': win.minimized }"
                :title="win.title"
                @click="toggleWindow(win.id)"
            >
                <span class="tb-icon">{{ win.icon || '▢' }}</span>
                <span class="tb-label">{{ win.title }}</span>
            </button>
            <div class="tb-sep" />
        </template>

        <!-- Frequency — live DOC comms hotkey. Not a SPLICE page launch, so it's
             kept separate from the APPS loop above. Enabled/pulsing only while
             standing at any CyberDoc hub — one isolated room per doc. -->
        <button
            id="nav-frequency"
            class="tb-btn tb-freq"
            :class="{ 'tb-btn--active': frequencyOpen, 'tb-freq--available': frequencyAvailable }"
            :style="frequencyAvailable ? { '--freq-color': frequencyColor } : {}"
            :disabled="!frequencyAvailable"
            :title="frequencyAvailable ? 'Open Frequency' : 'No signal here'"
            @click="$emit('toggle-frequency')"
        >
            <span class="tb-icon">≋</span>
            <span class="tb-label">FREQUENCY</span>
            <span v-if="frequencyAvailable && !frequencyOpen" class="tb-badge tb-freq-dot" />
        </button>

        <div class="tb-fill" />

        <!-- Clock -->
        <div class="tb-clock">{{ time }}</div>

        <div class="tb-sep" />

        <!-- System menu — logout, audio, tutorial -->
        <GameMenu @tutorial="$emit('tutorial')" @logout="$emit('logout')" />

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import GameMenu from '@/components/layout/GameMenu.vue';
import StartMenu from '@/components/shared/StartMenu.vue';
import { useWindowManager } from '@/composables/useWindowManager.js';

const props = defineProps({
    activeBrowserUrl:   { type: String,  default: null  },
    hasTutorialBadge:   { type: Boolean, default: false },
    frequencyAvailable: { type: Boolean, default: false },
    frequencyOpen:      { type: Boolean, default: false },
    frequencyColor:     { type: String,  default: '#00FFC8' },
    // Forwarded straight through to StartMenu's info tiles — same shape as
    // useGameState's player ref.
    player:             { type: Object,  default: () => ({}) },
});

const emit = defineEmits(['launch', 'tutorial', 'logout', 'toggle-frequency', 'open-window']);

// ── Open program windows (Map, Browser) — singleton, same live state
// Game.vue/InGameBrowser read/write. Aliased on destructure since `toggle`
// would otherwise collide with this file's own toggle(url) below, which is
// a different concept (launching a SPLICE page vs focusing/minimizing a
// program window).
const { taskbarItems, toggle: toggleWindow } = useWindowManager();

// Live clock
const time = ref('');
let timer;
onMounted(() => {
    const tick = () => { time.value = new Date().toLocaleTimeString('en-US', { hour12: false }); };
    tick();
    timer = setInterval(tick, 1000);
});
onUnmounted(() => clearInterval(timer));
</script>

<style scoped>
.taskbar {
    display: flex;
    align-items: center;
    width: 100%;
    height: 48px;
    background: #06060e;
    border-top: 1px solid rgba(0, 255, 255, 0.18);
    flex-shrink: 0;
    gap: 1px;
    padding: 0 6px;
    position: relative;   /* anchor for GameMenu's absolute panel */
}

@media (max-width: 1440px) { .taskbar { height: 44px; } }
@media (max-width: 1280px) { .taskbar { height: 42px; padding: 0 4px; } }

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.tb-btn {
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
    transition: background 0.12s, border-color 0.12s, color 0.12s;
    flex-shrink: 0;
    position: relative;
}

.tb-btn:hover {
    background: rgba(0, 255, 255, 0.05);
}

.tb-btn--active {
    background: rgba(0, 255, 255, 0.07);
    border-bottom-color: #00FFFF;
}

@media (max-width: 1440px) { .tb-btn { padding: 0 14px; } }
@media (max-width: 1280px) { .tb-btn { padding: 0 12px; gap: 5px; } }

/* ── Open program windows — same look as pinned app buttons, plus a dimmed
   state for minimized ones so the taskbar reads at a glance which windows
   are actually on screen right now vs just running in the background. ── */
.tb-window .tb-icon  { font-size: 15px; color: rgba(0, 255, 136, 0.5); line-height: 1; }
.tb-window .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgba(0, 255, 136, 0.45); letter-spacing: 0.1em; }
.tb-window:hover .tb-icon,
.tb-window:hover .tb-label { color: #00FF88; }
.tb-window.tb-btn--active .tb-icon  { color: #00FF88; text-shadow: 0 0 8px rgba(0,255,136,0.6); }
.tb-window.tb-btn--active .tb-label { color: rgba(0, 255, 136, 0.85); letter-spacing: 0.1em; }
.tb-window--minimized .tb-icon,
.tb-window--minimized .tb-label { opacity: 0.5; }

/* ── Frequency hotkey — live DOC comms ───────────────────────────────────── */
.tb-freq .tb-icon  { font-size: 15px; color: rgba(255,255,255,0.18); line-height: 1; }
.tb-freq .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgba(255,255,255,0.18); letter-spacing: 0.1em; }
.tb-freq:disabled  { cursor: not-allowed; }

.tb-freq--available .tb-icon,
.tb-freq--available .tb-label {
    color: color-mix(in srgb, var(--freq-color) 65%, white);
}
.tb-freq--available:hover .tb-icon,
.tb-freq--available:hover .tb-label { color: var(--freq-color); }
.tb-freq--available.tb-btn--active {
    background: color-mix(in srgb, var(--freq-color) 8%, transparent);
    border-bottom-color: var(--freq-color);
}
.tb-freq--available.tb-btn--active .tb-icon,
.tb-freq--available.tb-btn--active .tb-label {
    color: var(--freq-color);
    text-shadow: 0 0 8px var(--freq-color);
}

/* Reuses .tb-badge's position/animation — just recolours it to the doc's accent */
.tb-freq-dot {
    background: var(--freq-color);
    box-shadow: 0 0 6px var(--freq-color);
}

/* ── Badge dot — shown on TERMINAL when a quest step completes ────────────── */
.tb-badge {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #00FF88;
    box-shadow: 0 0 6px rgba(0, 255, 136, 0.8);
    animation: badge-pulse 2s ease-in-out infinite;
    pointer-events: none;
}
@keyframes badge-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

/* ── Separator ────────────────────────────────────────────────────────────── */
.tb-sep {
    width: 1px;
    height: 20px;
    background: rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
    margin: 0 4px;
}

/* ── Fill ─────────────────────────────────────────────────────────────────── */
.tb-fill { flex: 1; }

/* ── Clock ────────────────────────────────────────────────────────────────── */
.tb-clock {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.1em;
    padding: 0 16px;
    flex-shrink: 0;
}

@media (max-width: 1440px) { .tb-clock { font-size: 11px; padding: 0 12px; } }
@media (max-width: 1280px) { .tb-clock { font-size: 10px; padding: 0 10px; } }
</style>
