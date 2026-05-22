<template>
    <!-- Menu trigger button — lives in the taskbar -->
    <button
        class="menu-btn"
        :class="{ 'menu-btn--open': open }"
        title="System Menu"
        @click="open = !open"
    >
        <span class="menu-btn-icon">☰</span>
        <span class="menu-btn-label">MENU</span>
    </button>

    <!-- Overlay panel — anchored above the taskbar -->
    <Transition name="menu-slide">
        <div v-if="open" class="menu-panel" @click.self="open = false">
            <div class="menu-inner">

                <div class="menu-header">
                    <span class="menu-title">◈ SYSTEM</span>
                    <button class="menu-close" @click="open = false">✕</button>
                </div>

                <div class="menu-items">

                    <!-- Audio toggle -->
                    <button class="menu-item" @click="toggleAudio">
                        <span class="mi-icon">{{ !muted ? '◉' : '○' }}</span>
                        <span class="mi-label">AUDIO</span>
                        <span class="mi-state" :class="!muted ? 'mi-state--on' : 'mi-state--off'">
                            {{ !muted ? 'ON' : 'OFF' }}
                        </span>
                    </button>

                    <!-- Volume slider — only visible when not muted -->
                    <div v-if="!muted" class="volume-row">
                        <span class="vol-icon">▼</span>
                        <input
                            class="vol-slider"
                            type="range"
                            min="0"
                            max="1"
                            step="0.01"
                            :value="volume"
                            @input="onVolumeInput"
                        />
                        <span class="vol-icon">▲</span>
                        <span class="vol-pct">{{ Math.round(volume * 100) }}%</span>
                    </div>

                    <div class="menu-divider" />

                    <!-- Tutorial toggle -->
                    <button class="menu-item" @click="onTutorial">
                        <span class="mi-icon">◈</span>
                        <span class="mi-label">TUTORIAL</span>
                        <span class="mi-hint">Restart GHOST_PROTOCOL_0</span>
                    </button>

                    <div class="menu-divider" />

                    <!-- Logout -->
                    <button class="menu-item menu-item--danger" @click="onLogout">
                        <span class="mi-icon">⏻</span>
                        <span class="mi-label">LOGOUT</span>
                        <span class="mi-hint">Disconnect from the grid</span>
                    </button>

                </div>

                <div class="menu-footer">
                    <span>CODECRAFT // {{ version }}</span>
                </div>

            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useAudio } from '@/composables/useAudio.js';

const emit = defineEmits(['tutorial']);

const open    = ref(false);
const version = 'v0.1-alpha';

const { muted, volume, toggleMute, setVolume } = useAudio();

function toggleAudio() {
    toggleMute();
}

function onVolumeInput(e) {
    setVolume(parseFloat(e.target.value));
}

function onTutorial() {
    open.value = false;
    emit('tutorial');
}

async function onLogout() {
    open.value = false;
    try {
        // Laravel's web logout route — clears the session cookie
        await axios.post('/logout');
    } catch {
        // If the request fails the session may already be dead; navigate anyway
    }
    window.location.href = '/login';
}
</script>

<style scoped>
/* ── Trigger button — styled to match the taskbar ────────────────────────── */
.menu-btn {
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
    transition: background 0.12s, border-color 0.12s;
    flex-shrink: 0;
}

.menu-btn:hover {
    background: rgba(0, 255, 255, 0.05);
}

.menu-btn--open {
    background: rgba(0, 255, 255, 0.07);
    border-bottom-color: #00FFFF;
}

.menu-btn-icon  { font-size: 13px; color: rgba(0, 255, 255, 0.45); line-height: 1; }
.menu-btn-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; color: rgba(0, 255, 255, 0.4); letter-spacing: 0.1em; }

.menu-btn:hover .menu-btn-icon,
.menu-btn:hover .menu-btn-label  { color: #00FFFF; }
.menu-btn--open .menu-btn-icon,
.menu-btn--open .menu-btn-label  { color: #00FFFF; }

/* ── Panel — floats above the taskbar ────────────────────────────────────── */
.menu-panel {
    position: absolute;
    bottom: 42px;   /* taskbar height */
    right: 0;
    z-index: 60;
}

.menu-inner {
    width: 240px;
    background: #08080f;
    border: 1px solid rgba(0, 255, 255, 0.2);
    border-bottom: none;
    box-shadow:
        0 0 0 1px rgba(0, 255, 255, 0.04),
        -8px -8px 32px rgba(0, 0, 0, 0.6);
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px 8px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.08);
}

.menu-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.4);
    letter-spacing: 0.18em;
}

.menu-close {
    background: transparent;
    border: none;
    color: rgba(0, 255, 255, 0.25);
    font-size: 10px;
    cursor: pointer;
    padding: 0 2px;
    transition: color 0.12s;
}
.menu-close:hover { color: #FF3333; }

/* ── Items ───────────────────────────────────────────────────────────────── */
.menu-items {
    display: flex;
    flex-direction: column;
    padding: 6px 0;
}

.menu-item {
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

.menu-item:hover {
    background: rgba(0, 255, 255, 0.05);
}

.menu-item--danger:hover {
    background: rgba(255, 51, 51, 0.07);
}

.mi-icon {
    font-size: 12px;
    color: rgba(0, 255, 255, 0.4);
    width: 16px;
    flex-shrink: 0;
    line-height: 1;
}

.menu-item--danger .mi-icon {
    color: rgba(255, 51, 51, 0.5);
}

.mi-label {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.75);
    letter-spacing: 0.1em;
    flex: 1;
}

.menu-item--danger .mi-label {
    color: rgba(255, 51, 51, 0.75);
}

.mi-state {
    font-size: 8px;
    letter-spacing: 0.12em;
    flex-shrink: 0;
}
.mi-state--on  { color: #00FF88; }
.mi-state--off { color: rgba(255, 255, 255, 0.2); }

.mi-hint {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.2);
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.menu-item--danger .mi-hint {
    color: rgba(255, 51, 51, 0.3);
}

/* ── Volume slider ───────────────────────────────────────────────────────── */
.volume-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 16px 10px;
}

.vol-icon {
    font-size: 7px;
    color: rgba(0, 255, 255, 0.25);
    flex-shrink: 0;
    line-height: 1;
}

.vol-slider {
    flex: 1;
    -webkit-appearance: none;
    appearance: none;
    height: 2px;
    background: rgba(0, 255, 255, 0.15);
    outline: none;
    cursor: pointer;
}

.vol-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #00FFFF;
    box-shadow: 0 0 6px rgba(0, 255, 255, 0.5);
    cursor: pointer;
}

.vol-slider::-moz-range-thumb {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: none;
    background: #00FFFF;
    box-shadow: 0 0 6px rgba(0, 255, 255, 0.5);
    cursor: pointer;
}

.vol-pct {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.08em;
    width: 28px;
    text-align: right;
    flex-shrink: 0;
}

/* ── Divider ─────────────────────────────────────────────────────────────── */
.menu-divider {
    height: 1px;
    background: rgba(0, 255, 255, 0.05);
    margin: 2px 0;
}

/* ── Footer ──────────────────────────────────────────────────────────────── */
.menu-footer {
    padding: 8px 16px;
    border-top: 1px solid rgba(0, 255, 255, 0.06);
    font-size: 7px;
    color: rgba(0, 255, 255, 0.15);
    letter-spacing: 0.12em;
}

/* ── Transition ──────────────────────────────────────────────────────────── */
.menu-slide-enter-active,
.menu-slide-leave-active {
    transition: opacity 0.12s ease, transform 0.12s ease;
}
.menu-slide-enter-from,
.menu-slide-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
