<template>
    <div class="desktop">
        <div class="desktop-icons">

            <!-- Network Map — not a SPLICE browser page, so it's a distinct
                 event the parent wires to windowManager.open('map', ...)
                 rather than onLaunch(url). -->
            <button class="desktop-icon" @click="emit('open-map')">
                <span class="di-glyph">⬢</span>
                <span class="di-label">NETWORK MAP</span>
            </button>

            <!-- SPLICE home -->
            <button class="desktop-icon" @click="emit('launch', SPLICE.HOME)">
                <span class="di-glyph">◈</span>
                <span class="di-label">SPLICE</span>
            </button>

            <!-- Same pinned-app list the taskbar uses — see constants/spliceApps.js -->
            <button
                v-for="app in SPLICE_APPS"
                :key="app.url"
                class="desktop-icon"
                @click="emit('launch', app.url)"
            >
                <span class="di-glyph">{{ app.icon }}</span>
                <span class="di-label">{{ app.label }}</span>
            </button>

        </div>
    </div>
</template>

<script setup>
// Desktop — the OS shell's background layer. Purely presentational: it emits
// 'launch' (SPLICE url, same contract NavBar's app buttons already use) and
// 'open-map' (the one program that isn't a SPLICE page); Game.vue wires both
// to the exact same handlers NavBar/windowManager already use elsewhere, so
// no launch logic is duplicated here.
import { SPLICE } from '@/components/browser/SpliceRouter.js';
import { SPLICE_APPS } from '@/constants/spliceApps.js';

const emit = defineEmits(['launch', 'open-map']);
</script>

<style scoped>
.desktop {
    position: absolute;
    inset: 0;
    background: url('/BG/sfBG.jpg') center / cover no-repeat;
}

.desktop-icons {
    position: absolute;
    top: 24px;
    left: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.desktop-icon {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    width: 78px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 6px 4px;
}

.di-glyph {
    font-size: 28px;
    line-height: 1;
    color: rgba(0, 255, 255, 0.85);
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
}

.di-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.08em;
    text-align: center;
    color: rgba(255, 255, 255, 0.85);
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9);
}

.desktop-icon:hover .di-glyph,
.desktop-icon:hover .di-label {
    color: #00FFFF;
}
</style>
