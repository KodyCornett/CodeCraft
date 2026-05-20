<template>
    <div class="panel-block" :class="{ 'panel-block--collapsed': collapsed }">

        <!-- Block header — always visible -->
        <div class="pb-header" @click="collapsed = !collapsed">
            <div class="pb-header-left">
                <span class="pb-icon">{{ icon }}</span>
                <span class="pb-title">{{ title }}</span>
                <slot name="badge" />
            </div>
            <span class="pb-toggle">{{ collapsed ? '▸' : '▾' }}</span>
        </div>

        <!-- Block content — collapsible -->
        <div v-if="!collapsed" class="pb-body">
            <slot />
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    title:       { type: String,  default: 'BLOCK' },
    icon:        { type: String,  default: '◈' },
    startOpen:   { type: Boolean, default: true },
});

const collapsed = ref(!props.startOpen);
</script>

<style scoped>
.panel-block {
    border-bottom: 1px solid rgba(0, 255, 255, 0.07);
    font-family: 'JetBrains Mono', monospace;
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.pb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
    cursor: pointer;
    user-select: none;
    background: rgba(0, 255, 255, 0.02);
    transition: background 0.12s;
}
.pb-header:hover { background: rgba(0, 255, 255, 0.04); }

.pb-header-left {
    display: flex;
    align-items: center;
    gap: 7px;
}

.pb-icon  { font-size: 9px;  color: rgba(0, 255, 255, 0.9); text-shadow: 0 0 8px rgba(0, 255, 255, 0.6); }
.pb-title { font-size: 8px;  color: #00FFFF; letter-spacing: 0.16em; text-shadow: 0 0 10px rgba(0, 255, 255, 0.7), 0 0 20px rgba(0, 255, 255, 0.2); }

.pb-toggle {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.6);
    transition: color 0.12s;
}
.pb-header:hover .pb-toggle { color: rgba(0, 255, 255, 0.9); }

/* ── Body ─────────────────────────────────────────────────────────────────── */
.pb-body {
    padding: 0;
}
</style>
