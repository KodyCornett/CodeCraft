<template>
    <GlitchEffect
        v-if="stage"
        :key="idx"
        :type="stage.type"
        :duration="0"
        :active="true"
        :overlay="overlay"
        :intensity="intensity"
        :color="color"
    />
</template>

<script setup>
/**
 * GlitchFx
 *
 * Plays a single story FX cue against GlitchEffect.vue — either a raw combo
 * (`fx.type` + `fx.duration`, matching the [FX: type(level) — duration]
 * script tag convention) or a named multi-stage preset from
 * glitchPresets.js (`fx.preset`, e.g. 'sneeze').
 *
 * Both shapes resolve to the same internal stage list — a raw combo is just
 * a one-stage list — so there's a single sequencing path regardless of
 * which one is passed in. Fires when `fx` is set, steps through however
 * many stages it resolves to, then emits 'done'. Pass a new `fx` object (or
 * null) to retrigger or stop.
 */
import { ref, computed, watch, onUnmounted } from 'vue';
import GlitchEffect from './GlitchEffect.vue';
import { GLITCH_PRESETS } from '../../composables/glitchPresets.js';

const props = defineProps({
    // { preset: 'sneeze' } or { type: 'chromatic(2),flicker(1)', duration: 700 }
    fx:        { type: Object,  default: null },
    overlay:   { type: Boolean, default: true },
    intensity: { type: Number,  default: 0.6 },
    color:     { type: String,  default: '#00ff9d' },
});
const emit = defineEmits(['done']);

const stages = computed(() => {
    if (!props.fx) return [];
    if (props.fx.preset) return GLITCH_PRESETS[props.fx.preset] ?? [];
    if (props.fx.type)   return [{ type: props.fx.type, duration: props.fx.duration ?? 500 }];
    return [];
});

const idx   = ref(0);
const stage = computed(() => stages.value[idx.value] ?? null);

let timer = null;

function runStage() {
    clearTimeout(timer);
    const s = stages.value[idx.value];
    if (!s) {
        emit('done');
        return;
    }
    timer = setTimeout(() => {
        idx.value++;
        runStage();
    }, s.duration);
}

function start() {
    clearTimeout(timer);
    idx.value = 0;
    if (stages.value.length > 0) {
        runStage();
    } else {
        emit('done');
    }
}

watch(() => props.fx, (v) => {
    if (v) {
        start();
    } else {
        clearTimeout(timer);
        idx.value = 0;
    }
}, { immediate: true });

onUnmounted(() => clearTimeout(timer));
</script>
