<template>
    <div class="pp-page">

        <!-- Header -->
        <div class="pp-header">
            <div class="pp-header-left">
                <span class="pp-dot" />
                <span class="pp-title">SPLICE // RUNNER PERSONAS</span>
            </div>
        </div>

        <div class="pp-rule" />

        <div class="pp-intro">
            When a runner first connects to The Splice Frequency, the network demands
            identification — not a name, but a nature. These are the twenty archetypes
            the network recognises. Choose carefully. The Splice does not forget.
        </div>

        <div class="pp-rule" />

        <!-- Persona list -->
        <div class="pp-list">
            <div
                v-for="p in PERSONAS"
                :key="p.name"
                class="pp-item"
            >
                <span class="pp-item-name">{{ p.name.toUpperCase() }}</span>
                <span class="pp-item-sep">//</span>
                <span class="pp-item-desc">{{ p.desc }}</span>
            </div>
        </div>

        <div class="pp-rule" />

        <div class="pp-footer">
            Your persona is visible to other runners on the network.
            It cannot be changed once selected.
        </div>

    </div>
</template>

<script setup>
import { PERSONAS } from '../../../constants/personas.js';
</script>

<style scoped>
.pp-page {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #a0c4b8;
    padding: 10px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.pp-header {
    display: flex;
    align-items: center;
    margin-bottom: 2px;
}
.pp-header-left { display: flex; align-items: center; gap: 8px; }
.pp-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #00ff9d;
    box-shadow: 0 0 6px #00ff9d;
    flex-shrink: 0;
}
.pp-title { color: #00ff9d; font-size: 11px; font-weight: 700; letter-spacing: 1px; }
.pp-rule  { border: none; border-top: 1px solid #1a3a2a; margin: 2px 0; }
.pp-intro {
    font-size: 10px;
    color: #4a7a6a;
    line-height: 1.6;
    padding: 4px 0;
}

.pp-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.pp-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 5px 4px;
    border-bottom: 1px solid #0a1e14;
}
.pp-item:last-child { border-bottom: none; }
.pp-item-name {
    font-size: 11px;
    font-weight: 700;
    color: #c0f0d8;
    letter-spacing: 0.1em;
    flex-shrink: 0;
    width: 88px;
}
.pp-item-sep { color: #1e4a3a; flex-shrink: 0; }
.pp-item-desc {
    font-size: 9px;
    color: #4a7a6a;
    line-height: 1.5;
}
.pp-footer {
    font-size: 9px;
    color: #2a4a3a;
    padding: 4px 0;
    font-style: italic;
}
</style>
