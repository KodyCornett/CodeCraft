<template>
    <Transition name="pvp-fade">
        <div v-if="result" class="pvp-result" :class="result.won ? 'pvp-result--won' : 'pvp-result--lost'">
            <div class="pvp-result-inner">
                <div class="pvp-result-badge">{{ result.won ? '◉ BREACH SUCCESS' : '◈ BREACH FAILED' }}</div>
                <div class="pvp-result-vs">vs <span class="pvp-result-handle">{{ result.opponentHandle }}</span></div>
                <div v-if="result.won && result.loot?.stolen > 0" class="pvp-result-loot">
                    <span class="pvp-loot-label">EXTRACTED</span>
                    <span class="pvp-loot-val">◈ {{ result.loot.stolen.toLocaleString() }}</span>
                </div>
                <div v-else-if="!result.won" class="pvp-result-lost-msg">
                    POCKET WIPED — BOUNTY RETAINED
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
// result shape: { won: bool, opponentHandle: string, loot: { stolen: int }|null }
// Auto-dismissed by Game.vue (setTimeout sets result back to null after 6s).
defineProps({
    result: { type: Object, default: null },
});
</script>

<style scoped>
.pvp-fade-enter-active,
.pvp-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.pvp-fade-enter-from,
.pvp-fade-leave-to     { opacity: 0; transform: translateY(8px); }

.pvp-result {
    position: absolute;
    bottom: 60px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 40;
    pointer-events: none;
}
.pvp-result-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 16px 36px;
    font-family: 'JetBrains Mono', monospace;
    white-space: nowrap;
}
.pvp-result--won  .pvp-result-inner { background: rgba(4,12,8,0.96);  border: 1px solid rgba(0,255,136,0.5);  box-shadow: 0 0 24px rgba(0,255,136,0.15); }
.pvp-result--lost .pvp-result-inner { background: rgba(12,4,4,0.96);  border: 1px solid rgba(255,51,51,0.45); box-shadow: 0 0 24px rgba(255,51,51,0.12); }
.pvp-result-badge        { font-size: 10px; letter-spacing: 0.18em; }
.pvp-result--won  .pvp-result-badge { color: #00FF88; }
.pvp-result--lost .pvp-result-badge { color: #FF3333; }
.pvp-result-vs           { font-size: 8px; color: rgba(0,255,255,0.35); letter-spacing: 0.1em; }
.pvp-result-handle       { color: #FF69B4; }
.pvp-result-loot         { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.pvp-loot-label          { font-size: 7px; color: rgba(0,255,136,0.4); letter-spacing: 0.12em; }
.pvp-loot-val            { font-size: 11px; color: #00FF88; letter-spacing: 0.08em; }
.pvp-result-lost-msg     { font-size: 8px; color: rgba(255,51,51,0.55); letter-spacing: 0.1em; margin-top: 2px; }
</style>
