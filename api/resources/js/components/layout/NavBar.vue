<template>
    <div class="taskbar">

        <!-- SPLICE home button -->
        <button
            id="nav-splice"
            class="tb-btn tb-home"
            :class="{ 'tb-btn--active': isActive(SPLICE.HOME) }"
            title="SPLICE Home"
            @click="toggle(SPLICE.HOME)"
        >
            <span class="tb-icon">◈</span>
            <span class="tb-label">SPLICE</span>
        </button>

        <div class="tb-sep" />

        <!-- App launchers -->
        <button
            v-for="app in APPS"
            :key="app.url"
            :id="app.tourId ?? undefined"
            class="tb-btn tb-app"
            :class="{ 'tb-btn--active': isActive(app.url) }"
            :title="app.url"
            @click="toggle(app.url)"
        >
            <span class="tb-icon">{{ app.icon }}</span>
            <span class="tb-label">{{ app.label }}</span>
            <span v-if="app.badged && hasTutorialBadge" class="tb-badge" />
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
import { SPLICE }   from '@/components/browser/SpliceRouter.js';
import GameMenu from '@/components/layout/GameMenu.vue';

const props = defineProps({
    activeBrowserUrl:  { type: String,  default: null  },
    hasTutorialBadge:  { type: Boolean, default: false },
});

const emit = defineEmits(['launch', 'tutorial', 'logout']);

const APPS = [
    { url: SPLICE.STATS,     icon: '◈', label: 'STATUS',   tourId: 'nav-status'   },
    { url: SPLICE.RIG,       icon: '⬡', label: 'RIG'                              },
    { url: SPLICE.COMMANDS,  icon: '▶', label: 'CMDS'                             },
    { url: SPLICE.INVENTORY, icon: '▣', label: 'INV'                              },
    { url: SPLICE.TERMINAL,  icon: '⌨', label: 'TERMINAL', badged: true, tourId: 'nav-terminal' },
];

// Active when the browser is open on this app's URL
function isActive(url) {
    return props.activeBrowserUrl?.startsWith(url) ?? false;
}

// Toggle: open if not active, close if already open
function toggle(url) {
    emit('launch', isActive(url) ? null : url);
}

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

/* ── SPLICE home ──────────────────────────────────────────────────────────── */
.tb-home .tb-icon  { font-size: 15px; color: rgba(0, 255, 255, 0.6); line-height: 1; }
.tb-home .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgba(0, 255, 255, 0.5); letter-spacing: 0.12em; }
.tb-home:hover .tb-icon,
.tb-home:hover .tb-label  { color: #00FFFF; }
.tb-home.tb-btn--active .tb-icon,
.tb-home.tb-btn--active .tb-label { color: #00FFFF; }

/* ── App buttons ──────────────────────────────────────────────────────────── */
.tb-app .tb-icon  { font-size: 15px; color: rgba(0, 255, 255, 0.45); line-height: 1; }
.tb-app .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: rgba(0, 255, 255, 0.4); letter-spacing: 0.1em; }

@media (max-width: 1440px) {
    .tb-btn { padding: 0 14px; }
    .tb-home .tb-icon, .tb-app .tb-icon { font-size: 14px; }
    .tb-home .tb-label, .tb-app .tb-label { font-size: 11px; }
}
@media (max-width: 1280px) {
    .tb-btn { padding: 0 12px; gap: 5px; }
    .tb-home .tb-icon, .tb-app .tb-icon { font-size: 13px; }
    .tb-home .tb-label, .tb-app .tb-label { font-size: 10px; }
}
.tb-app:hover .tb-icon,
.tb-app:hover .tb-label   { color: #00FFFF; }
.tb-app.tb-btn--active .tb-icon  { color: #00FFFF; text-shadow: 0 0 8px rgba(0,255,255,0.6); }
.tb-app.tb-btn--active .tb-label { color: rgba(0, 255, 255, 0.85); letter-spacing: 0.1em; }

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
