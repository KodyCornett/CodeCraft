<template>
    <Transition name="welcome-fade">
        <div v-if="visible" class="welcome-overlay">
            <div class="welcome-modal">
                <div class="welcome-tag">// INCOMING TRANSMISSION</div>
                <div class="welcome-title">WELCOME, RUNNER</div>
                <div class="welcome-body">
                    Your rig is online. Your uplink is live.<br />
                    Complete orientation before running anything hot.<br />
                    <span class="welcome-note">Rewards go directly to your wallet — safe from PvP.</span>
                </div>
                <div class="welcome-actions">
                    <button class="welcome-btn welcome-btn--primary" @click="$emit('start')">
                        [ BEGIN ORIENTATION ]
                    </button>
                    <button class="welcome-btn welcome-btn--ghost" @click="$emit('skip')">
                        skip
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    visible: { type: Boolean, default: false },
});
defineEmits(['start', 'skip']);
</script>

<style scoped>
.welcome-fade-enter-active,
.welcome-fade-leave-active { transition: opacity 0.3s ease; }
.welcome-fade-enter-from,
.welcome-fade-leave-to     { opacity: 0; }

.welcome-overlay {
    position: absolute;
    inset: 0;
    z-index: 60;
    background: rgba(0, 0, 0, 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
}
.welcome-modal {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 32px 36px;
    background: #06060e;
    border: 1px solid rgba(0, 255, 255, 0.2);
    box-shadow: 0 0 0 1px rgba(0,255,255,0.05), 0 24px 60px rgba(0,0,0,0.8);
    max-width: 440px;
    width: 100%;
    font-family: 'JetBrains Mono', monospace;
}
.welcome-tag   { font-size: 8px; color: rgba(0,255,255,0.3); letter-spacing: 0.2em; }
.welcome-title { font-size: 20px; color: #00FFFF; letter-spacing: 0.12em; text-shadow: 0 0 20px rgba(0,255,255,0.3); }
.welcome-body  { font-size: 10px; color: rgba(255,255,255,0.45); letter-spacing: 0.04em; line-height: 2; }
.welcome-note  { display: block; margin-top: 8px; color: rgba(0,255,136,0.5); font-size: 9px; }
.welcome-actions { display: flex; align-items: center; gap: 16px; margin-top: 8px; }
.welcome-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.welcome-btn--primary {
    padding: 10px 20px;
    background: rgba(0, 255, 136, 0.08);
    border: 1px solid rgba(0, 255, 136, 0.4);
    color: #00FF88;
}
.welcome-btn--primary:hover { background: rgba(0,255,136,0.15); border-color: #00FF88; }
.welcome-btn--ghost  { background: transparent; border: none; color: rgba(255,255,255,0.2); padding: 10px 4px; }
.welcome-btn--ghost:hover { color: rgba(255,255,255,0.45); }
</style>
