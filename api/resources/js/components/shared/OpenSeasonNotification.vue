<template>
    <Transition name="os-notify" @after-leave="$emit('done')">
        <div v-if="visible" class="os-overlay" @click="dismiss">
            <div class="os-content">
                <div class="os-burst" aria-hidden="true" />

                <div class="os-icon">⚡</div>

                <div class="os-title">OPEN SEASON ACTIVATED</div>

                <div class="os-lines">
                    <div class="os-line os-line--1">PROTECTION PROTOCOLS SUSPENDED</div>
                    <div class="os-line os-line--2">YOU ARE NOW A PRIORITY TARGET</div>
                    <div class="os-line os-line--3">
                        BOUNTY:
                        <span class="os-bounty-val">₢ {{ formattedBounty }}</span>
                    </div>
                </div>

                <div class="os-dismiss">// TAP TO CONTINUE</div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    bountyValue: { type: Number, default: 0 },
});

defineEmits(['done']);

const visible = ref(true);
let autoTimer = null;

const formattedBounty = computed(() =>
    (props.bountyValue ?? 0).toLocaleString('en-US'),
);

function dismiss() {
    visible.value = false;
}

onMounted(() => {
    autoTimer = setTimeout(dismiss, 3500);
});

onUnmounted(() => clearTimeout(autoTimer));
</script>

<style scoped>
.os-overlay {
    position: absolute;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 3, 3, 0.92);
    cursor: pointer;
}

/* Radial burst behind the text */
.os-burst {
    position: absolute;
    inset: 0;
    background: radial-gradient(
        ellipse at center,
        rgba(255, 51, 51, 0.18) 0%,
        rgba(255, 179, 0, 0.08) 40%,
        transparent 70%
    );
    animation: burst-expand 0.6s ease-out forwards;
    pointer-events: none;
}

@keyframes burst-expand {
    from { transform: scale(0.3); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

.os-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    position: relative;
    text-align: center;
    animation: content-appear 0.4s ease-out forwards;
}

@keyframes content-appear {
    from { transform: scale(0.85); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}

.os-icon {
    font-size: 48px;
    animation: icon-pulse 1.2s ease-in-out infinite;
    filter: drop-shadow(0 0 16px rgba(255, 51, 51, 0.8));
}

@keyframes icon-pulse {
    0%, 100% { transform: scale(1);    filter: drop-shadow(0 0 16px rgba(255,51,51,0.8)); }
    50%       { transform: scale(1.1);  filter: drop-shadow(0 0 30px rgba(255,51,51,1)); }
}

.os-title {
    font-family: 'JetBrains Mono', monospace;
    font-size: 28px;
    letter-spacing: 0.12em;
    color: #FF3333;
    text-shadow:
        0 0 20px rgba(255,51,51,0.7),
        0 0 40px rgba(255,51,51,0.3);
    animation: title-glitch 4s steps(1) infinite;
}

@keyframes title-glitch {
    0%   { transform: translateX(0);  clip-path: none; }
    4%   { transform: translateX(-3px); clip-path: inset(20% 0 60% 0); }
    6%   { transform: translateX(3px);  clip-path: inset(60% 0 15% 0); }
    8%   { transform: translateX(0);    clip-path: none; }
    100% { transform: translateX(0); }
}

.os-lines {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.os-line {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    letter-spacing: 0.08em;
    opacity: 0;
}

.os-line--1 { color: rgba(255,179,0,0.85); animation: line-in 0.3s ease forwards 0.3s; }
.os-line--2 { color: rgba(255,179,0,0.7);  animation: line-in 0.3s ease forwards 0.5s; }
.os-line--3 { color: rgba(255,179,0,0.9);  animation: line-in 0.3s ease forwards 0.7s; font-size: 14px; }

@keyframes line-in {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0);   }
}

.os-bounty-val {
    color: #FFB300;
    font-size: 18px;
    text-shadow: 0 0 12px rgba(255,179,0,0.6);
}

.os-dismiss {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(255,255,255,0.2);
    letter-spacing: 0.08em;
    margin-top: 8px;
    animation: dismiss-blink 1.5s ease-in-out infinite;
}

@keyframes dismiss-blink {
    0%, 100% { opacity: 0.5; }
    50%       { opacity: 1;   }
}

/* Transition */
.os-notify-enter-active { transition: opacity 0.2s ease; }
.os-notify-leave-active { transition: opacity 0.5s ease; }
.os-notify-enter-from,
.os-notify-leave-to     { opacity: 0; }
</style>
