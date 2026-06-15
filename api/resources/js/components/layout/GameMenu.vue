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

                    <!-- ── Audio settings ─────────────────────────────────── -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <span class="ss-icon">◉</span>
                            <span class="ss-label">AUDIO</span>
                            <button class="mute-btn" @click="toggleMute" :class="{ 'mute-btn--muted': muted }">
                                {{ muted ? 'MUTED' : 'LIVE' }}
                            </button>
                        </div>

                        <!-- Music volume -->
                        <div class="vol-row">
                            <span class="vol-label">MUSIC</span>
                            <input
                                class="vol-slider vol-slider--music"
                                type="range"
                                min="0"
                                max="1"
                                step="0.01"
                                :value="musicVolume"
                                :disabled="muted"
                                @input="e => setMusicVolume(parseFloat(e.target.value))"
                            />
                            <span class="vol-pct" :class="{ 'vol-pct--dim': muted }">
                                {{ muted ? '—' : Math.round(musicVolume * 100) + '%' }}
                            </span>
                        </div>

                        <!-- Story volume -->
                        <div class="vol-row">
                            <span class="vol-label">STORY</span>
                            <input
                                class="vol-slider vol-slider--story"
                                type="range"
                                min="0"
                                max="1"
                                step="0.01"
                                :value="storyVolume"
                                :disabled="muted"
                                @input="e => setStoryVolume(parseFloat(e.target.value))"
                            />
                            <span class="vol-pct" :class="{ 'vol-pct--dim': muted }">
                                {{ muted ? '—' : Math.round(storyVolume * 100) + '%' }}
                            </span>
                        </div>

                        <!-- Story volume warning -->
                        <div class="story-warning">
                            <span class="story-warning-icon">⚠</span>
                            CodeCraft is a narrated story. Lowering story volume may diminish the experience the game is designed to deliver.
                        </div>
                    </div>

                    <div class="menu-divider" />

                    <!-- Tutorial -->
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

const { muted, musicVolume, storyVolume, toggleMute, setMusicVolume, setStoryVolume } = useAudio();

function onTutorial() {
    open.value = false;
    emit('tutorial');
}

async function onLogout() {
    open.value = false;
    try {
        await axios.post('/logout');
    } catch {
        // session may already be expired
    }
    window.location.href = '/login';
}
</script>

<style scoped>
/* ── Trigger button ───────────────────────────────────────────────────────── */
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

.menu-btn:hover { background: rgba(0, 255, 255, 0.05); }

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

/* ── Panel ────────────────────────────────────────────────────────────────── */
.menu-panel {
    position: absolute;
    bottom: 42px;
    right: 0;
    z-index: 60;
}

.menu-inner {
    width: 260px;
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

.menu-item:hover { background: rgba(0, 255, 255, 0.05); }
.menu-item--danger:hover { background: rgba(255, 51, 51, 0.07); }

.mi-icon {
    font-size: 12px;
    color: rgba(0, 255, 255, 0.4);
    width: 16px;
    flex-shrink: 0;
    line-height: 1;
}

.menu-item--danger .mi-icon { color: rgba(255, 51, 51, 0.5); }

.mi-label {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.75);
    letter-spacing: 0.1em;
    flex: 1;
}

.menu-item--danger .mi-label { color: rgba(255, 51, 51, 0.75); }

.mi-hint {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.2);
    letter-spacing: 0.04em;
    flex-shrink: 0;
}

.menu-item--danger .mi-hint { color: rgba(255, 51, 51, 0.3); }

/* ── Audio settings section ──────────────────────────────────────────────── */
.settings-section {
    padding: 10px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.settings-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
}

.ss-icon {
    font-size: 11px;
    color: rgba(0, 255, 255, 0.4);
    line-height: 1;
    width: 16px;
    flex-shrink: 0;
}

.ss-label {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.75);
    letter-spacing: 0.1em;
    flex: 1;
}

.mute-btn {
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.15);
    color: #00FF88;
    font-family: 'JetBrains Mono', monospace;
    font-size: 8px;
    letter-spacing: 0.12em;
    padding: 2px 6px;
    cursor: pointer;
    transition: border-color 0.12s, color 0.12s;
    flex-shrink: 0;
}

.mute-btn:hover {
    border-color: rgba(0, 255, 255, 0.4);
}

.mute-btn--muted {
    color: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.08);
}

/* ── Volume rows ─────────────────────────────────────────────────────────── */
.vol-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.vol-label {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.1em;
    width: 36px;
    flex-shrink: 0;
}

.vol-slider {
    flex: 1;
    -webkit-appearance: none;
    appearance: none;
    height: 2px;
    outline: none;
    cursor: pointer;
    border-radius: 1px;
}

.vol-slider:disabled {
    opacity: 0.25;
    cursor: default;
}

/* Music slider — cyan */
.vol-slider--music {
    background: rgba(0, 255, 255, 0.15);
}
.vol-slider--music::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #00FFFF;
    box-shadow: 0 0 6px rgba(0, 255, 255, 0.5);
    cursor: pointer;
}
.vol-slider--music::-moz-range-thumb {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: none;
    background: #00FFFF;
    box-shadow: 0 0 6px rgba(0, 255, 255, 0.5);
    cursor: pointer;
}

/* Story slider — amber */
.vol-slider--story {
    background: rgba(255, 179, 0, 0.15);
}
.vol-slider--story::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #FFB300;
    box-shadow: 0 0 6px rgba(255, 179, 0, 0.5);
    cursor: pointer;
}
.vol-slider--story::-moz-range-thumb {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    border: none;
    background: #FFB300;
    box-shadow: 0 0 6px rgba(255, 179, 0, 0.5);
    cursor: pointer;
}

.vol-pct {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.06em;
    width: 30px;
    text-align: right;
    flex-shrink: 0;
}

.vol-pct--dim { color: rgba(255, 255, 255, 0.15); }

/* ── Story volume warning ─────────────────────────────────────────────────── */
.story-warning {
    display: flex;
    gap: 6px;
    padding: 6px 8px;
    border-left: 2px solid rgba(255, 179, 0, 0.35);
    background: rgba(255, 179, 0, 0.04);
    font-size: 8px;
    color: rgba(255, 179, 0, 0.55);
    line-height: 1.5;
    letter-spacing: 0.03em;
}

.story-warning-icon {
    flex-shrink: 0;
    font-size: 9px;
    color: rgba(255, 179, 0, 0.5);
    margin-top: 1px;
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
