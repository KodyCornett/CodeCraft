<template>
    <div class="streetdoc-overlay">

        <!-- Header -->
        <div class="sd-header">
            <div class="sd-business">[{{ node.label.toUpperCase() }}]</div>
            <div class="sd-subtitle">[STREET DOC // SECURE CHANNEL]</div>
            <button class="disconnect-btn" @click="$emit('close')">[DISCONNECT]</button>
        </div>

        <!-- Main menu grid -->
        <Transition name="fade" mode="out-in">
            <div v-if="!activeSubPanel" class="sd-menu" key="menu">
                <button class="menu-btn" @click="activeSubPanel = 'repair'">
                    <span class="menu-btn-label">[REPAIR_SS]</span>
                    <span class="menu-btn-sub">Restore System Stability</span>
                </button>
                <button class="menu-btn" @click="activeSubPanel = 'upgrade'">
                    <span class="menu-btn-label">[UPGRADE_RIG]</span>
                    <span class="menu-btn-sub">Stat upgrades &amp; reallocation</span>
                </button>
                <button class="menu-btn" @click="activeSubPanel = 'loadout'">
                    <span class="menu-btn-label">[SET_LOADOUT]</span>
                    <span class="menu-btn-sub">Assign active command slots</span>
                </button>
                <button class="menu-btn" @click="activeSubPanel = 'install'">
                    <span class="menu-btn-label">[INSTALL_HW]</span>
                    <span class="menu-btn-sub">Install hardware encrypts</span>
                </button>
            </div>

            <!-- Sub-panels -->
            <RepairStation
                v-else-if="activeSubPanel === 'repair'"
                key="repair"
                :player="player"
                @back="activeSubPanel = null"
                @repaired="onRepaired"
            />
            <StatUpgrade
                v-else-if="activeSubPanel === 'upgrade'"
                key="upgrade"
                :player="player"
                :rig="mockRig"
                @back="activeSubPanel = null"
                @upgraded="onUpgraded"
            />
            <LoadoutBuilder
                v-else-if="activeSubPanel === 'loadout'"
                key="loadout"
                :player="player"
                :rig="mockRig"
                @back="activeSubPanel = null"
                @locked="onLoadoutLocked"
            />
            <InstallHardware
                v-else-if="activeSubPanel === 'install'"
                key="install"
                :player="player"
                :port-slots="4"
                @back="activeSubPanel = null"
                @installed="onInstalled"
            />
        </Transition>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import RepairStation  from './RepairStation.vue';
import StatUpgrade    from './StatUpgrade.vue';
import LoadoutBuilder from './LoadoutBuilder.vue';
import InstallHardware from './InstallHardware.vue';

const props = defineProps({
    node:   { type: Object, required: true },
    player: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const activeSubPanel = ref(null);

// Mock rig data — replaced by real API composable in Prompt 7
const mockRig = ref({
    os: 5, ram: 4, cpu: 6, storage: 7, firewall: 4,
});

function onRepaired(amount) {
    // Parent can listen and update mockPlayer if needed
}

function onUpgraded() {}
function onLoadoutLocked() {}
function onInstalled() {}
</script>

<style scoped>
.streetdoc-overlay {
    position: absolute;
    inset: 0;
    z-index: 40;
    background: rgba(5, 5, 5, 0.97);
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
    border: 1px solid rgba(255, 179, 0, 0.35);
    box-shadow: inset 0 0 40px rgba(255, 179, 0, 0.04), 0 0 30px rgba(255, 179, 0, 0.08);
}

.sd-header {
    display: flex;
    align-items: baseline;
    gap: 16px;
    padding: 16px 22px 12px;
    border-bottom: 1px solid rgba(255, 179, 0, 0.3);
    flex-shrink: 0;
}

.sd-business {
    font-size: 16px;
    color: #FFB300;
    letter-spacing: 0.08em;
    text-shadow: 0 0 12px rgba(255, 179, 0, 0.4);
}

.sd-subtitle {
    font-size: 10px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.06em;
    flex: 1;
}

.disconnect-btn {
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.35);
    color: rgba(255, 179, 0, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    padding: 5px 12px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.disconnect-btn:hover {
    color: #FF3333;
    border-color: rgba(255, 51, 51, 0.5);
    box-shadow: 0 0 8px rgba(255, 51, 51, 0.15);
}

/* 2×2 menu grid */
.sd-menu {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px;
    flex: 1;
    padding: 2px;
}

.menu-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 6px;
    padding: 24px 28px;
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.15);
    cursor: pointer;
    transition: background 0.15s, border-color 0.2s, box-shadow 0.2s;
    text-align: left;
}
.menu-btn:hover {
    background: rgba(255, 179, 0, 0.05);
    border-color: rgba(255, 179, 0, 0.5);
    box-shadow: inset 0 0 20px rgba(255, 179, 0, 0.06);
}

.menu-btn-label {
    font-family: 'JetBrains Mono', monospace;
    font-size: 14px;
    color: #FFB300;
    letter-spacing: 0.06em;
}

.menu-btn-sub {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.04em;
}

/* Sub-panel transition */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
