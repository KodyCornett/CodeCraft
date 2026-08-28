<template>
    <div class="dsl2-wrap">

        <div class="dsl2-header">
            <span class="dsl2-tag">[ DEV BUILD ]</span>
            <span class="dsl2-title">SIGNAL LOCK LAB</span>
            <span class="dsl2-sub">// Remove splice://dev/signal-lock-lab from SpliceRouter.js before release</span>
        </div>

        <div class="dsl2-note">
            Candidate 4th entry for the live node-hack pool (generator/pool.js) —
            NOT registered there yet. Built to the same props/emits/reward-formula
            contract as GridBreach, ChecksumBreach, and CipherBreach, so promoting
            it later is a one-line addition. Read the objective line, pick the one
            candidate row that actually satisfies it, and watch for the flagged
            decoy once ICE climbs high enough to introduce one.
        </div>

        <div class="dsl2-controls">
            <div class="dsl2-control-row">
                <span class="dsl2-control-label">ICE LEVEL</span>
                <button
                    v-for="ice in [3, 4, 5, 6, 7, 8, 9, 10]"
                    :key="ice"
                    class="dsl2-chip"
                    :class="{ 'dsl2-chip--active': selectedIce === ice }"
                    @click="selectedIce = ice"
                >{{ ice }}</button>
            </div>

            <div class="dsl2-control-row">
                <span class="dsl2-control-label">RIG PRESET</span>
                <button
                    v-for="preset in RIG_PRESETS"
                    :key="preset.label"
                    class="dsl2-chip"
                    :class="{ 'dsl2-chip--active': selectedRig.label === preset.label }"
                    @click="selectedRig = preset"
                >{{ preset.label }}</button>
                <span class="dsl2-rig-readout">CPU {{ selectedRig.cpu }} / RAM {{ selectedRig.ram }} / OS {{ selectedRig.os }}</span>
            </div>
        </div>

        <button type="button" class="dsl2-launch-btn" @click="onLaunch">
            [ LAUNCH SIGNAL LOCK — ICE {{ selectedIce }} ]
        </button>

    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useDevSignalLock } from '@/composables/useDevSignalLock.js';

const { launch } = useDevSignalLock();

const selectedIce = ref(3);

// Pair STARTER with a high ICE selection above to test the under-geared
// (CPU-below-ICE) penalty path — no separate preset needed for that, since
// it's the ICE/rig combination that matters, not the rig alone.
const RIG_PRESETS = [
    { label: 'STARTER',  cpu: 3, ram: 2, os: 2 },
    { label: 'MID-TIER', cpu: 5, ram: 4, os: 4 },
    { label: 'HIGH-SEC', cpu: 8, ram: 6, os: 6 },
];

const selectedRig = ref(RIG_PRESETS[0]);

function onLaunch() {
    launch({ ice: selectedIce.value, cpu: selectedRig.value.cpu, ram: selectedRig.value.ram, os: selectedRig.value.os });
}
</script>

<style scoped>
.dsl2-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

.dsl2-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(183,148,246,0.15);
}

.dsl2-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dsl2-title { font-size: 14px; color: #c4a6ff; letter-spacing: 0.15em; font-weight: 700; }
.dsl2-sub   { font-size: 8px;  color: rgba(183,148,246,0.35); letter-spacing: 0.1em; }

.dsl2-note {
    font-size: 10px;
    line-height: 1.6;
    color: rgba(183,148,246,0.6);
}

.dsl2-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dsl2-control-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.dsl2-control-label {
    font-size: 9px;
    letter-spacing: 0.14em;
    color: rgba(183,148,246,0.45);
    margin-right: 6px;
    flex-shrink: 0;
}

.dsl2-chip {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.08em;
    background: transparent;
    border: 1px solid rgba(183,148,246,0.3);
    color: rgba(183,148,246,0.6);
    padding: 5px 10px;
    cursor: pointer;
    transition: all 0.1s;
}
.dsl2-chip:hover { border-color: #c4a6ff; color: #c4a6ff; }
.dsl2-chip--active { background: rgba(183,148,246,0.12); border-color: #c4a6ff; color: #e4d6ff; }

.dsl2-rig-readout {
    font-size: 9px;
    color: rgba(183,148,246,0.4);
    margin-left: 8px;
}

.dsl2-launch-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(183,148,246,0.4);
    color: rgba(196,166,255,0.8);
    padding: 9px 0;
    cursor: pointer;
    transition: all 0.1s;
}
.dsl2-launch-btn:hover {
    background: rgba(183,148,246,0.08);
    border-color: #c4a6ff;
    color: #e4d6ff;
}
</style>
