<template>
    <div class="taskbar">

        <!-- SPLICE home button -->
        <button
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
            class="tb-btn tb-app"
            :class="{ 'tb-btn--active': isActive(app.url) }"
            :title="app.url"
            @click="toggle(app.url)"
        >
            <span class="tb-icon">{{ app.icon }}</span>
            <span class="tb-label">{{ app.label }}</span>
        </button>

        <div class="tb-fill" />

        <!-- Clock -->
        <div class="tb-clock">{{ time }}</div>

        <div class="tb-sep" />

        <!-- System menu — logout, audio, tutorial -->
        <GameMenu @tutorial="$emit('tutorial')" />

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { SPLICE }   from '@/components/browser/SpliceRouter.js';
import GameMenu from '@/components/layout/GameMenu.vue';

const props = defineProps({
    activeBrowserUrl: { type: String, default: null },
});

const emit = defineEmits(['launch', 'tutorial']);

const APPS = [
    { url: SPLICE.STATS,     icon: '◈', label: 'STATUS' },
    { url: SPLICE.RIG,       icon: '⬡', label: 'RIG'   },
    { url: SPLICE.COMMANDS,  icon: '▶', label: 'CMDS'  },
    { url: SPLICE.INVENTORY, icon: '▣', label: 'INV'   },
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
    height: 42px;
    background: #06060e;
    border-top: 1px solid rgba(0, 255, 255, 0.18);
    flex-shrink: 0;
    gap: 1px;
    padding: 0 4px;
    position: relative;   /* anchor for GameMenu's absolute panel */
}

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.tb-btn {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0 14px;
    height: 100%;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s, color 0.12s;
    flex-shrink: 0;
}

.tb-btn:hover {
    background: rgba(0, 255, 255, 0.05);
}

.tb-btn--active {
    background: rgba(0, 255, 255, 0.07);
    border-bottom-color: #00FFFF;
}

/* ── SPLICE home ──────────────────────────────────────────────────────────── */
.tb-home .tb-icon  { font-size: 13px; color: rgba(0, 255, 255, 0.6); line-height: 1; }
.tb-home .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: rgba(0, 255, 255, 0.5); letter-spacing: 0.12em; }
.tb-home:hover .tb-icon,
.tb-home:hover .tb-label  { color: #00FFFF; }
.tb-home.tb-btn--active .tb-icon,
.tb-home.tb-btn--active .tb-label { color: #00FFFF; }

/* ── App buttons ──────────────────────────────────────────────────────────── */
.tb-app .tb-icon  { font-size: 13px; color: rgba(0, 255, 255, 0.45); line-height: 1; }
.tb-app .tb-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: rgba(0, 255, 255, 0.4); letter-spacing: 0.1em; }
.tb-app:hover .tb-icon,
.tb-app:hover .tb-label   { color: #00FFFF; }
.tb-app.tb-btn--active .tb-icon  { color: #00FFFF; text-shadow: 0 0 8px rgba(0,255,255,0.6); }
.tb-app.tb-btn--active .tb-label { color: rgba(0, 255, 255, 0.85); letter-spacing: 0.1em; }

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
    font-size: 10px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.1em;
    padding: 0 12px;
    flex-shrink: 0;
}
</style>
