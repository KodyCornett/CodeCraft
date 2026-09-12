<template>
    <div class="desktop">
        <div class="desktop-icons">

            <!-- Same launchable-programs list the Start Menu uses — see
                 constants/spliceApps.js. 'window' kind (Network Map) emits
                 open-map; 'launch' kind (everything else) emits launch(url). -->
            <button
                v-for="program in PROGRAMS"
                :key="program.id"
                class="desktop-icon"
                @click="program.kind === 'window' ? emit('open-map') : emit('launch', program.url)"
            >
                <span class="di-glyph">{{ program.icon }}</span>
                <span class="di-label">{{ program.label }}</span>
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
import { PROGRAMS } from '@/constants/spliceApps.js';

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
