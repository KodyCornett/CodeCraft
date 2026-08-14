<template>
    <Transition name="cfp-notify">
        <div v-if="visible" class="cfp-overlay">
            <div class="cfp-content">
                <div class="cfp-scan" aria-hidden="true" />

                <div class="cfp-tag">// ANOMALOUS FRAGMENT DETECTED</div>
                <div class="cfp-title">CODEX — FOUND</div>
                <div class="cfp-sub">Take your chance at solving it.</div>

                <div class="cfp-actions">
                    <button class="cfp-btn cfp-btn--play" @click="onPlay">
                        [ PLAY ]
                    </button>
                    <button class="cfp-btn cfp-btn--pass" @click="onPass">
                        [ PASS ]
                    </button>
                </div>

                <div class="cfp-note">Passing costs nothing — it'll turn up again.</div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    visible: { type: Boolean, default: false },
});

const emit = defineEmits(['play', 'pass']);

function onPlay() { emit('play'); }
function onPass() { emit('pass'); }
</script>

<style scoped>
.cfp-overlay {
    position: absolute;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(3, 8, 6, 0.9);
}

.cfp-scan {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px,
        rgba(0,255,157,0.02) 2px, rgba(0,255,157,0.02) 4px);
    pointer-events: none;
}

.cfp-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    position: relative;
    text-align: center;
    padding: 32px 40px;
    border: 1px solid rgba(0,255,157,0.35);
    background: rgba(0,20,12,0.85);
    box-shadow: 0 0 40px rgba(0,255,157,0.12), inset 0 0 30px rgba(0,255,157,0.03);
    animation: cfp-appear 0.35s ease-out forwards;
}

@keyframes cfp-appear {
    from { transform: scale(0.9); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

.cfp-tag {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(0,255,157,0.4);
}

.cfp-title {
    font-family: 'JetBrains Mono', monospace;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: #00ff9d;
    text-shadow: 0 0 20px rgba(0,255,157,0.6), 0 0 40px rgba(0,255,157,0.25);
    animation: cfp-pulse 2s ease-in-out infinite;
}

@keyframes cfp-pulse {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.85; }
}

.cfp-sub {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: rgba(160,200,180,0.7);
    letter-spacing: 0.04em;
}

.cfp-actions {
    display: flex;
    gap: 14px;
    margin-top: 12px;
}

.cfp-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.12em;
    padding: 9px 22px;
    cursor: pointer;
    background: transparent;
    transition: all 0.12s;
}

.cfp-btn--play {
    border: 1px solid #00ff9d;
    color: #00ff9d;
}
.cfp-btn--play:hover {
    background: rgba(0,255,157,0.12);
    box-shadow: 0 0 16px rgba(0,255,157,0.3);
}

.cfp-btn--pass {
    border: 1px solid rgba(160,200,180,0.3);
    color: rgba(160,200,180,0.6);
}
.cfp-btn--pass:hover {
    border-color: rgba(160,200,180,0.6);
    color: rgba(160,200,180,0.9);
}

.cfp-note {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(0,255,157,0.25);
    letter-spacing: 0.04em;
    margin-top: 4px;
}

/* Transition */
.cfp-notify-enter-active { transition: opacity 0.2s ease; }
.cfp-notify-leave-active { transition: opacity 0.3s ease; }
.cfp-notify-enter-from,
.cfp-notify-leave-to     { opacity: 0; }
</style>
