<template>
    <div class="bhsb-bar">
        <span class="bhsb-item">[ NODE: {{ nodeName }} ]</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item">STAGED: {{ stagedCreds }} CRED</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item">TECH: {{ stagedTech }} PT</span>
        <span class="bhsb-sep">|</span>
        <span class="bhsb-item bhsb-clock">
            TIME: [<span class="bhsb-clock-track"><span class="bhsb-clock-fill" :class="clockClass" :style="{ width: clockPct + '%' }" /></span>]
            {{ formattedTime }}
            <span v-if="active" class="bhsb-active">(ACTIVE)</span>
            <span v-else class="bhsb-standby">(STANDBY)</span>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue';

// Replaces the old Trace Meter (0-100% risk-of-getting-caught display) now
// that Bank Heist runs on one shared Master Timer for the whole game —
// this bar shows however much of it is left, not a risk percentage.
const props = defineProps({
    nodeName:    { type: String, default: 'UNKNOWN TARGET' },
    stagedCreds: { type: Number, default: 0 },
    stagedTech:  { type: Number, default: 0 },
    timeLeft:    { type: Number, default: 0 },
    timeTotal:   { type: Number, default: 0 },
    active:      { type: Boolean, default: false },
});

const clockPct = computed(() => props.timeTotal ? (props.timeLeft / props.timeTotal) * 100 : 0);
const clockClass = computed(() => {
    if (props.timeLeft <= 30) return 'bhsb-crit';
    if (props.timeLeft <= 90) return 'bhsb-warn';
    return '';
});
const formattedTime = computed(() => {
    const total = Math.max(0, Math.ceil(props.timeLeft));
    const m = Math.floor(total / 60);
    const s = total % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
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
.bhsb-clock { display: inline-flex; align-items: center; gap: 4px; }
.bhsb-clock-track { display: inline-block; width: 80px; height: 8px; background: #0a0f16; vertical-align: middle; }
.bhsb-clock-fill { display: block; height: 100%; background: #00F0FF; transition: width 0.2s linear; }
.bhsb-clock-fill.bhsb-warn { background: #FFB000; }
.bhsb-clock-fill.bhsb-crit { background: #FF2244; }
.bhsb-active { color: #FFB000; }
.bhsb-standby { opacity: 0.5; }
</style>
