<template>
    <div class="dgl-wrap">

        <div class="dgl-header">
            <span class="dgl-tag">[ DEV BUILD ]</span>
            <span class="dgl-title">MINIGAME GENERATOR LAB</span>
            <span class="dgl-sub">// Remove splice://dev/generator-lab from SpliceRouter.js before release</span>
        </div>

        <div class="dgl-note">
            Composes an input model + a win rule live, purely for feel-testing while the
            composer is being built out. Fully separate from the real node-hack generator
            (grid_breach / checksum_breach / cipher_breach) — nothing here touches rewards
            or the live hack flow.
        </div>

        <div class="dgl-row">
            <span class="dgl-label">INPUT MODEL</span>
            <button
                v-for="input in INPUT_MODELS"
                :key="input.key"
                type="button"
                class="dgl-btn"
                :class="{ 'dgl-btn--active': selectedInputKey === input.key }"
                @click="selectedInputKey = input.key"
            >{{ input.label }}</button>
        </div>

        <div class="dgl-row">
            <span class="dgl-label">WIN RULE</span>
            <button
                v-for="rule in compatibleRules"
                :key="rule.key"
                type="button"
                class="dgl-btn"
                :class="{ 'dgl-btn--active': selectedRuleKey === rule.key }"
                @click="selectedRuleKey = rule.key"
            >{{ rule.label }}</button>
            <span v-if="!compatibleRules.length" class="dgl-empty">No compatible win rules registered yet.</span>
        </div>

        <div class="dgl-row">
            <span class="dgl-label">ICE</span>
            <button
                v-for="ice in [3, 5, 7, 9]"
                :key="ice"
                type="button"
                class="dgl-btn"
                :class="{ 'dgl-btn--active': selectedIce === ice }"
                @click="selectedIce = ice"
            >{{ ice }}</button>
        </div>

        <div class="dgl-pairing">
            PAIRING: <strong>{{ pairingKey }}</strong>
            <span v-if="!pairingAvailable" class="dgl-pairing-warn"> — no content generator registered for this pairing yet.</span>
        </div>

        <button
            type="button"
            class="dgl-launch-btn"
            :disabled="!selectedInputKey || !selectedRuleKey || !pairingAvailable"
            @click="onLaunch"
        >[ LAUNCH PAIRING ]</button>

    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useDevComposer } from '@/composables/useDevComposer.js';
import { INPUT_MODELS, WIN_RULES, compatibleRulesFor } from '@/components/minigame/composer/registry.js';
import { availablePairingKeys } from '@/components/minigame/composer/composeMinigame.js';

const { launch } = useDevComposer();

const selectedInputKey = ref(INPUT_MODELS[0]?.key ?? null);
const selectedRuleKey  = ref(null);
const selectedIce      = ref(5);

const compatibleRules = computed(() =>
    selectedInputKey.value ? compatibleRulesFor(selectedInputKey.value) : []
);

// Keep the rule selection valid whenever the input model changes.
function syncRuleSelection() {
    const stillValid = compatibleRules.value.some(r => r.key === selectedRuleKey.value);
    if (!stillValid) selectedRuleKey.value = compatibleRules.value[0]?.key ?? null;
}
syncRuleSelection();

const pairingKey = computed(() => `${selectedInputKey.value ?? '?'}:${selectedRuleKey.value ?? '?'}`);
const pairingAvailable = computed(() => availablePairingKeys().includes(pairingKey.value));

function onLaunch() {
    syncRuleSelection();
    if (!selectedInputKey.value || !selectedRuleKey.value || !pairingAvailable.value) return;
    launch({
        inputKey: selectedInputKey.value,
        ruleKey:  selectedRuleKey.value,
        ice:      selectedIce.value,
    });
}
</script>

<style scoped>
.dgl-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

.dgl-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dgl-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dgl-title { font-size: 14px; color: #00ff9d; letter-spacing: 0.15em; font-weight: 700; }
.dgl-sub   { font-size: 8px;  color: rgba(0,255,100,0.2); letter-spacing: 0.1em; }

.dgl-note {
    font-size: 10px;
    line-height: 1.6;
    color: rgba(0,255,100,0.4);
}

.dgl-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.dgl-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
    margin-right: 4px;
    min-width: 90px;
}

.dgl-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.1em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.4);
    padding: 6px 14px;
    cursor: pointer;
    transition: all 0.1s;
}

.dgl-btn:hover {
    border-color: rgba(0,255,100,0.5);
    color: rgba(0,255,100,0.7);
}

.dgl-btn--active {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,255,100,0.06);
}

.dgl-empty {
    font-size: 9px;
    color: rgba(255,100,100,0.5);
}

.dgl-pairing {
    font-size: 11px;
    color: rgba(0,255,100,0.5);
}

.dgl-pairing strong {
    color: #00ff9d;
}

.dgl-pairing-warn {
    color: rgba(255,100,100,0.6);
}

.dgl-launch-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 9px 0;
    cursor: pointer;
    transition: all 0.1s;
}

.dgl-launch-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
}

.dgl-launch-btn:disabled {
    border-color: rgba(0,255,100,0.08);
    color: rgba(0,255,100,0.2);
    cursor: not-allowed;
}
</style>
