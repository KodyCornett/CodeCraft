<template>
    <div class="bhsb-bar">
        <span class="bhsb-item">[ NODE: {{ nodeName }} ]</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item">STAGED: {{ stagedCreds }} CRED</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item">TECH: {{ stagedTech }} PT</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item bhsb-trace">
            TRACE METER: [<span class="bhsb-trace-track"><span class="bhsb-trace-fill" :class="traceClass" :style="{ width: tracePercent + '%' }" /></span>]
            {{ tracePercent.toFixed(0) }}%
            <span v-if="active" class="bhsb-active">(ACTIVE)</span>
            <span v-else class="bhsb-standby">(STANDBY)</span>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    nodeName:     { type: String, default: 'UNKNOWN TARGET' },
    stagedCreds:  { type: Number, default: 0 },
    stagedTech:   { type: Number, default: 0 },
    tracePercent: { type: Number, default: 0 },
    active:       { type: Boolean, default: false },
});

const traceClass = computed(() => {
    if (props.tracePercent >= 75) return 'bhsb-crit';
    if (props.tracePercent >= 50) return 'bhsb-warn';
    return '';
});
</script>

<style scoped>
.bhsb-bar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.04em;
    color: #00F0FF; padding: 8px 12px; border: 1px solid #00F0FF; background: rgba(0, 240, 255, 0.04);
    text-shadow: 0 0 6px rgba(0, 240, 255, 0.35);
}
.bhsb-sep { opacity: 0.35; }
.bhsb-trace { display: inline-flex; align-items: center; gap: 4px; }
.bhsb-trace-track { display: inline-block; width: 80px; height: 8px; background: #0a0f16; vertical-align: middle; }
.bhsb-trace-fill { display: block; height: 100%; background: #00F0FF; transition: width 0.2s linear; }
.bhsb-trace-fill.bhsb-warn { background: #FFB000; }
.bhsb-trace-fill.bhsb-crit { background: #FF2244; }
.bhsb-active { color: #FFB000; }
.bhsb-standby { opacity: 0.5; }
</style>
