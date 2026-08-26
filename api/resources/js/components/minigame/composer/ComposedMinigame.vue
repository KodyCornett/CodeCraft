<template>
    <div class="cm-overlay">
        <div class="cm-terminal">
            <div class="cm-topbar">
                <span class="cm-label">{{ inputEntry?.label ?? spec.inputKey }} &times; {{ ruleEntry?.label ?? spec.ruleKey }}</span>
                <span class="cm-ice">ICE {{ spec.ice }}</span>
            </div>

            <div class="cm-hint">{{ hint || 'Loading win condition...' }}</div>

            <component
                v-if="!outcome && activeInputComponent"
                :is="activeInputComponent"
                :rows="content.rows"
                :cols="content.cols"
                :values="content.values"
                :time-limit-sec="content.timeLimitSec"
                :paused="paused"
                @submit="onSubmit"
                @timeout="onTimeout"
            />

            <div v-if="outcome" class="cm-outcome">
                <div class="cm-outcome-title" :class="outcome.success ? 'cm-outcome-title--win' : 'cm-outcome-title--fail'">
                    {{ outcome.success ? 'PAIRING SOLVED' : 'PAIRING FAILED' }}
                </div>
                <pre class="cm-outcome-detail">{{ outcomeDetailText }}</pre>
                <button type="button" class="cm-outcome-btn" @click="onClose">[ CLOSE ]</button>
            </div>
        </div>
    </div>
</template>

<script setup>
/**
 * ComposedMinigame — dev-lab renderer for the input-model/win-rule composer.
 *
 * Mirrors HackMinigame.vue's job (resolve a spec, mount the matching
 * component, forward complete/failed/abort) but resolves TWO independent
 * pieces instead of one pool entry: an input model component and a win rule
 * module. It asks composeMinigame() for this pairing's content once on
 * mount, hands the input model everything it needs to render, and when the
 * input model emits `submit`, judges the picked values through the rule
 * module — the input component itself never knows whether it "won".
 *
 * This is reached ONLY through the dev-only splice://dev/generator-lab
 * route (see useDevComposer.js / DevGeneratorLab.vue). It is not part of
 * the live hack flow and never calls the reward endpoint — complete/failed
 * here just report a completionPct/detail for the dev lab to display, the
 * same shape a future promoted pairing would need to report for real.
 */
import { ref, computed, defineAsyncComponent } from 'vue';
import { findInputModel, findWinRule } from './registry.js';
import { composeMinigame } from './composeMinigame.js';

const props = defineProps({
    spec:   { type: Object,  required: true }, // { inputKey, ruleKey, ice }
    paused: { type: Boolean, default: false },
});

const emit = defineEmits(['complete', 'failed', 'abort']);

const inputEntry = computed(() => findInputModel(props.spec.inputKey));
const ruleEntry  = computed(() => findWinRule(props.spec.ruleKey));

// Generated once per mount — a new spec (different pairing/ICE) means a
// fresh ComposedMinigame instance via Vue's :key in the caller, not a
// re-generate-in-place here.
const content = composeMinigame({
    inputKey: props.spec.inputKey,
    ruleKey:  props.spec.ruleKey,
    ice:      props.spec.ice,
});

const activeInputComponent = computed(() => {
    if (!inputEntry.value) {
        console.error(`[ComposedMinigame] Unknown input model "${props.spec.inputKey}".`);
        return null;
    }
    return defineAsyncComponent({ loader: inputEntry.value.component });
});

const hint    = ref('');
const outcome = ref(null);
let ruleModule = null;

(async () => {
    if (!ruleEntry.value) {
        console.error(`[ComposedMinigame] Unknown win rule "${props.spec.ruleKey}".`);
        return;
    }
    ruleModule = await ruleEntry.value.module();
    hint.value = ruleModule.describeTarget ? ruleModule.describeTarget(content) : '';
})();

const outcomeDetailText = computed(() => outcome.value ? JSON.stringify(outcome.value.detail, null, 2) : '');

function onSubmit(pickedValues) {
    if (!ruleModule) return;
    const result = ruleModule.evaluate(pickedValues, content);
    outcome.value = result;
    if (result.success) {
        emit('complete', { completionPct: 1.0, detail: result.detail });
    } else {
        emit('failed', { detail: result.detail });
    }
}

function onTimeout() {
    const result = { success: false, detail: { reason: 'timeout' } };
    outcome.value = result;
    emit('failed', { detail: result.detail });
}

function onClose() {
    emit('abort');
}
</script>

<style scoped>
.cm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 500;
}

.cm-terminal {
    width: 640px;
    max-width: 92vw;
    background: #050807;
    border: 1px solid rgba(0,255,100,0.3);
    box-shadow: 0 0 40px rgba(0,255,100,0.08);
    padding: 24px 28px 28px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cm-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.15);
}

.cm-label {
    font-size: 13px;
    color: #00ff9d;
    letter-spacing: 0.1em;
    font-weight: 700;
}

.cm-ice {
    font-size: 12px;
    color: rgba(0,200,240,0.7);
    letter-spacing: 0.1em;
}

.cm-hint {
    font-size: 14px;
    color: rgba(0,255,100,0.7);
    letter-spacing: 0.03em;
}

.cm-outcome {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
    padding: 16px 0;
}

.cm-outcome-title {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 0.12em;
}

.cm-outcome-title--win  { color: #00ff9d; }
.cm-outcome-title--fail { color: rgba(255,80,80,0.85); }

.cm-outcome-detail {
    font-size: 12px;
    color: rgba(0,255,100,0.5);
    background: rgba(0,255,100,0.04);
    border: 1px solid rgba(0,255,100,0.12);
    padding: 10px 14px;
    width: 100%;
    box-sizing: border-box;
    white-space: pre-wrap;
}

.cm-outcome-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.7);
    padding: 8px 24px;
    cursor: pointer;
    transition: all 0.1s;
}

.cm-outcome-btn:hover {
    background: rgba(0,255,100,0.08);
    border-color: #00ff9d;
    color: #00ff9d;
}
</style>
