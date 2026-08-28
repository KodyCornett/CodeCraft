<template>
    <div class="dsl-wrap">

        <div class="dsl-header">
            <span class="dsl-tag">[ DEV BUILD ]</span>
            <span class="dsl-title">SIT LAB</span>
            <span class="dsl-sub">// Remove splice://dev/sit-lab from SpliceRouter.js before release</span>
        </div>

        <div class="dsl-note">
            Proof-of-concept for SIT (Splice Interface Terminal) — a fully typed
            terminal minigame with real ls/cd/cat navigation over a small
            hand-written filesystem, no buttons or cards. Completely separate from
            both the composer (input models x win rules) and the real node-hack
            generator — nothing here touches rewards or the live hack flow. Two
            hand-written scenarios exist so far, proving the terminal shell
            generalizes across different puzzle shapes before any content
            generation gets built on top of it.
        </div>

        <div class="dsl-scenario-list">
            <button
                v-for="(entry, key) in SCENARIOS"
                :key="key"
                type="button"
                class="dsl-scenario-btn"
                @click="onLaunch(key)"
            >
                <span class="dsl-scenario-label">[ LAUNCH: {{ entry.label }} ]</span>
                <span class="dsl-scenario-summary">{{ entry.summary }}</span>
            </button>
        </div>

    </div>
</template>

<script setup>
import { useDevSIT } from '@/composables/useDevSIT.js';
import { SCENARIOS } from '@/components/minigame/sit/scenarios/index.js';

const { launch } = useDevSIT();

function onLaunch(scenarioKey) {
    launch(scenarioKey);
}
</script>

<style scoped>
.dsl-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

.dsl-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dsl-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dsl-title { font-size: 14px; color: #00ff9d; letter-spacing: 0.15em; font-weight: 700; }
.dsl-sub   { font-size: 8px;  color: rgba(0,255,100,0.35); letter-spacing: 0.1em; }

.dsl-note {
    font-size: 10px;
    line-height: 1.6;
    color: rgba(0,255,100,0.6);
}

.dsl-scenario-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dsl-scenario-btn {
    font-family: 'JetBrains Mono', monospace;
    text-align: left;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.4);
    color: rgba(0,255,100,0.7);
    padding: 9px 14px;
    cursor: pointer;
    transition: all 0.1s;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.dsl-scenario-btn:hover {
    background: rgba(0,255,100,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
}

.dsl-scenario-label {
    font-size: 11px;
    letter-spacing: 0.14em;
}

.dsl-scenario-summary {
    font-size: 9px;
    letter-spacing: 0.04em;
    color: rgba(0,255,100,0.45);
}
</style>
