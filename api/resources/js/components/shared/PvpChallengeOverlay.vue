<template>
    <Transition name="pvp-fade">
        <div v-if="challenge" class="pvp-challenge">
            <div class="pvp-challenge-inner">
                <span class="pvp-challenge-icon">⚡</span>
                <div class="pvp-challenge-title">COMBAT CHALLENGE</div>
                <div class="pvp-challenge-handle">
                    <span class="pvp-ch-label">FROM</span>
                    <span class="pvp-ch-name">{{ challenge.challenger?.handle ?? 'UNKNOWN' }}</span>
                </div>
                <div class="pvp-challenge-sub">You are being challenged to Packet Hijack combat</div>
                <div class="pvp-challenge-actions">
                    <button class="pvp-btn pvp-btn--accept" @click="$emit('accept')">[ACCEPT]</button>
                    <button class="pvp-btn pvp-btn--decline" @click="$emit('decline')">[DECLINE]</button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    // Pass the full incomingChallenge object; null hides the overlay.
    // Gate activePvpCombat in Game.vue: :challenge="incomingChallenge && !activePvpCombat ? incomingChallenge : null"
    challenge: { type: Object, default: null },
});
defineEmits(['accept', 'decline']);
</script>

<style scoped>
.pvp-fade-enter-active,
.pvp-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.pvp-fade-enter-from,
.pvp-fade-leave-to     { opacity: 0; transform: translateY(8px); }

.pvp-challenge {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 6, 10, 0.78);
    z-index: 40;
    pointer-events: all;
}
.pvp-challenge-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 28px 44px;
    background: rgba(4, 8, 14, 0.97);
    border: 1px solid rgba(255, 51, 51, 0.45);
    font-family: 'JetBrains Mono', monospace;
    box-shadow: 0 0 30px rgba(255, 51, 51, 0.15);
    animation: pvp-challenge-pulse 1.4s ease-in-out infinite;
}
@keyframes pvp-challenge-pulse {
    0%, 100% { border-color: rgba(255, 51, 51, 0.45); }
    50%       { border-color: rgba(255, 51, 51, 0.9); }
}
.pvp-challenge-icon   { font-size: 22px; color: #FF3333; animation: crit-pulse 0.6s ease-in-out infinite; }
.pvp-challenge-title  { font-size: 12px; color: #FF3333; letter-spacing: 0.2em; }
.pvp-challenge-handle { display: flex; align-items: center; gap: 8px; margin: 4px 0; }
.pvp-ch-label         { font-size: 7px; color: rgba(255,51,51,.4); letter-spacing: .12em; }
.pvp-ch-name          { font-size: 13px; color: #FF69B4; letter-spacing: 0.1em; }
.pvp-challenge-sub    { font-size: 8px; color: rgba(255,51,51,0.45); letter-spacing: 0.08em; text-align: center; }
.pvp-challenge-actions { display: flex; gap: 12px; margin-top: 8px; }
.pvp-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.14em;
    padding: 8px 18px;
    cursor: pointer;
    background: transparent;
    transition: all 0.14s;
}
.pvp-btn--accept  { border: 1px solid rgba(0, 255, 136, 0.45); color: rgba(0, 255, 136, 0.8); }
.pvp-btn--decline { border: 1px solid rgba(255, 51, 51, 0.35);  color: rgba(255, 51, 51, 0.6); }
.pvp-btn--accept:hover  { background: rgba(0, 255, 136, 0.08); border-color: rgba(0, 255, 136, 0.85); color: #00FF88; }
.pvp-btn--decline:hover { background: rgba(255, 51, 51, 0.07);  border-color: rgba(255, 51, 51, 0.75);  color: #FF3333; }
@keyframes crit-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
</style>
