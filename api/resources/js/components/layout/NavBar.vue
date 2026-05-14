<template>
    <div class="navbar">
        <button
            v-for="btn in BUTTONS"
            :key="btn.panel"
            class="nav-btn"
            :class="{ 'nav-btn--active': activePanel === btn.panel }"
            @click="handleClick(btn.panel)"
        >
            {{ btn.label }}
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    activePanel: { type: String, default: null },
});

const emit = defineEmits(['panel-open', 'panel-close']);

const BUTTONS = [
    { panel: 'PlayerStats', label: '[PLAYER_STATS]' },
    { panel: 'RigInfo',     label: '[RIG_INFO]'     },
    { panel: 'Commands',    label: '[COMMANDS]'     },
    { panel: 'Inventory',   label: '[INVENTORY]'    },
    { panel: 'ScanArea',    label: '[SCAN_AREA]'    },
];

function handleClick(panel) {
    if (props.activePanel === panel) {
        emit('panel-close');
    } else {
        emit('panel-open', panel);
    }
}
</script>

<style scoped>
.navbar {
    display: flex;
    width: 100%;
    background: #0A0A0A;
    border-top: 1px solid rgba(0, 255, 255, 0.35);
    flex-shrink: 0;
}

.nav-btn {
    flex: 1;
    padding: 10px 4px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0, 255, 255, 0.15);
    color: rgba(0, 255, 255, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
    white-space: nowrap;
}

.nav-btn:last-child {
    border-right: none;
}

.nav-btn:hover {
    color: #00FFFF;
    box-shadow: inset 0 0 8px rgba(0, 255, 255, 0.12);
}

.nav-btn--active {
    color: #00FFFF;
    background: rgba(0, 255, 255, 0.06);
    box-shadow: inset 0 0 12px rgba(0, 255, 255, 0.18);
}
</style>
