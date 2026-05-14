<template>
    <div class="hr-overlay" :class="success ? 'hr--success' : 'hr--failure'">
        <div class="hr-box">
            <div class="hr-title">{{ success ? '[HACK SUCCESSFUL]' : '[HACK FAILED]' }}</div>

            <div class="hr-divider" />

            <template v-if="success">
                <div v-if="resource === 'creds'" class="hr-line hr-line--gain">
                    +{{ credValue.toLocaleString() }} ₡ TRANSFERRED
                </div>
                <div v-else class="hr-line hr-line--gain">
                    +{{ movementPts }} MOVEMENT PTS ACQUIRED
                </div>
                <div class="hr-line hr-line--info">NODE RESOURCE DEPLETED</div>
                <div class="hr-line hr-line--trace">TRACE LOGGED</div>
            </template>
            <template v-else>
                <div class="hr-line hr-line--deny">ACCESS DENIED</div>
                <div class="hr-line hr-line--dmg">-{{ SS_DAMAGE }} SS SYSTEM DAMAGE</div>
                <div class="hr-line hr-line--trace">TRACE LOGGED</div>
            </template>

            <div class="hr-divider" />
            <div class="hr-dismiss">// AUTO-DISMISS IN {{ countdown }}s</div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    success:     { type: Boolean, required: true },
    resource:    { type: String,  required: true }, // 'creds' | 'movement'
    credValue:   { type: Number,  default: 0 },
    movementPts: { type: Number,  default: 3 },
});
const emit = defineEmits(['done']);

const SS_DAMAGE = 10;
const DISPLAY_MS = 2500;

const countdown = ref(3);
let countTimer = null;
let doneTimer  = null;

onMounted(() => {
    countTimer = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);
    }, 1000);

    doneTimer = setTimeout(() => emit('done', { ssDamage: props.success ? 0 : SS_DAMAGE }), DISPLAY_MS);
});

onUnmounted(() => {
    clearInterval(countTimer);
    clearTimeout(doneTimer);
});
</script>

<style scoped>
.hr-overlay {
    position: fixed;
    inset: 0;
    z-index: 65;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(2, 2, 2, 0.92);
    font-family: 'JetBrains Mono', monospace;
    animation: hr-appear 0.18s ease-out forwards;
}

@keyframes hr-appear {
    from { opacity: 0; }
    to   { opacity: 1; }
}

.hr-box {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 320px;
    padding: 28px 32px;
    background: rgba(5, 5, 5, 0.98);
    border: 1px solid;
}

.hr--success .hr-box { border-color: rgba(0, 255, 136, 0.5); box-shadow: 0 0 30px rgba(0, 255, 136, 0.08); }
.hr--failure .hr-box { border-color: rgba(255, 51, 51, 0.5);  box-shadow: 0 0 30px rgba(255, 51, 51, 0.08); }

.hr-title {
    font-size: 15px;
    letter-spacing: 0.08em;
    text-align: center;
}
.hr--success .hr-title { color: #00FF88; text-shadow: 0 0 14px rgba(0, 255, 136, 0.5); }
.hr--failure .hr-title { color: #FF3333; text-shadow: 0 0 14px rgba(255, 51, 51, 0.4); }

.hr-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.06);
}

.hr-line {
    font-size: 11px;
    letter-spacing: 0.05em;
    padding: 2px 0;
}

.hr-line--gain  { color: #00FF88; }
.hr-line--deny  { color: #FF3333; }
.hr-line--dmg   { color: rgba(255, 51, 51, 0.7); }
.hr-line--info  { color: rgba(0, 255, 255, 0.5); }
.hr-line--trace { color: rgba(255, 179, 0, 0.55); }

.hr-dismiss {
    font-size: 8px;
    color: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.06em;
    text-align: center;
}
</style>
