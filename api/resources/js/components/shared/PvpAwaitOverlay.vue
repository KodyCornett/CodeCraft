<template>
    <Transition name="pvp-fade">
        <div v-if="visible" class="pvp-await">
            <div class="pvp-await-inner">
                <span class="pvp-await-icon">⚡</span>
                <div class="pvp-await-title">CHALLENGE SENT</div>
                <div class="pvp-await-sub">Waiting for target to respond…</div>
                <div class="pvp-await-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    visible: { type: Boolean, default: false },
});
</script>

<style scoped>
.pvp-fade-enter-active,
.pvp-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.pvp-fade-enter-from,
.pvp-fade-leave-to     { opacity: 0; transform: translateY(8px); }

.pvp-await {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 6, 10, 0.72);
    z-index: 40;
    pointer-events: all;
}
.pvp-await-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 28px 40px;
    background: rgba(4, 8, 14, 0.96);
    border: 1px solid rgba(255, 179, 0, 0.3);
    font-family: 'JetBrains Mono', monospace;
}
.pvp-await-icon  { font-size: 20px; color: #FFB300; animation: await-pulse 0.8s ease-in-out infinite; }
.pvp-await-title { font-size: 11px; color: #FFB300; letter-spacing: 0.18em; }
.pvp-await-sub   { font-size: 8px; color: rgba(255,179,0,0.45); letter-spacing: 0.1em; }
.pvp-await-dots  { display: flex; gap: 6px; margin-top: 4px; }
.pvp-await-dots span {
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(255, 179, 0, 0.5);
    animation: dot-bounce 1.2s ease-in-out infinite;
}
.pvp-await-dots span:nth-child(2) { animation-delay: 0.2s; }
.pvp-await-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes await-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
@keyframes dot-bounce  { 0%, 100% { transform: translateY(0); opacity: 0.4; } 50% { transform: translateY(-5px); opacity: 1; } }
</style>
