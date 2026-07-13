<template>
    <Transition name="cf-fade">
        <div v-if="failure" class="critical-failure-overlay">
            <div class="cf-inner">
                <div class="cf-icon">☠</div>
                <div class="cf-title">CRITICAL SYSTEM FAILURE</div>
                <div class="cf-sub">SYSTEM INTEGRITY LOST — REBOOTING AT STREET DOC</div>
                <div class="cf-details">
                    <div class="cf-row">
                        <span class="cf-key">POCKET CREDS</span>
                        <span class="cf-val cf-val--wiped">WIPED</span>
                    </div>
                    <div class="cf-row">
                        <span class="cf-key">BOUNTY</span>
                        <span class="cf-val cf-val--wiped">CLEARED</span>
                    </div>
                    <div class="cf-row">
                        <span class="cf-key">REPAIR COST</span>
                        <span class="cf-val cf-val--cost">◈ {{ (failure.repairCost ?? 0).toLocaleString() }}</span>
                    </div>
                </div>
                <div class="cf-warn">Visit the Street Doc to pay for repairs before hacking again.</div>
                <button class="cf-btn" @click="$emit('reboot')">[ REBOOT SYSTEM ]</button>
            </div>
        </div>
    </Transition>
</template>

<script setup>
defineProps({
    failure: { type: Object, default: null },
});
defineEmits(['reboot']);
</script>

<style scoped>
.cf-fade-enter-active,
.cf-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.cf-fade-enter-from,
.cf-fade-leave-to     { opacity: 0; transform: translateY(8px); }

.critical-failure-overlay {
    position: absolute;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.88);
    backdrop-filter: blur(4px);
}
.cf-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 32px 40px;
    border: 1px solid rgba(255, 51, 51, 0.5);
    background: rgba(8, 0, 0, 0.95);
    box-shadow: 0 0 40px rgba(255, 51, 51, 0.25), inset 0 0 30px rgba(255, 51, 51, 0.04);
    font-family: 'JetBrains Mono', monospace;
    text-align: center;
    max-width: 380px;
    animation: cf-flicker 6s steps(1) infinite;
}
@keyframes cf-flicker {
    0%, 95%, 100% { opacity: 1; }
    96%            { opacity: 0.85; }
    97%            { opacity: 1; }
    98%            { opacity: 0.9; }
}
.cf-icon  { font-size: 28px; color: #FF3333; text-shadow: 0 0 20px rgba(255,51,51,0.8); animation: cf-pulse 1s ease-in-out infinite; }
.cf-title { font-size: 13px; color: #FF3333; letter-spacing: 0.2em; text-shadow: 0 0 12px rgba(255,51,51,0.6); }
.cf-sub   { font-size: 8px;  color: rgba(255,51,51,0.55); letter-spacing: 0.12em; }
@keyframes cf-pulse { 0%,100%{text-shadow:0 0 12px rgba(255,51,51,0.6)} 50%{text-shadow:0 0 24px rgba(255,51,51,1)} }
.cf-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 100%;
    border-top: 1px solid rgba(255,51,51,0.15);
    border-bottom: 1px solid rgba(255,51,51,0.15);
    padding: 10px 0;
    margin: 4px 0;
}
.cf-row        { display: flex; justify-content: space-between; align-items: center; }
.cf-key        { font-size: 7px; color: rgba(0,255,255,0.3); letter-spacing: 0.12em; }
.cf-val        { font-size: 9px; letter-spacing: 0.08em; }
.cf-val--wiped { color: #FF3333; }
.cf-val--cost  { color: #FFB300; }
.cf-warn       { font-size: 7px; color: rgba(255,179,0,0.6); letter-spacing: 0.08em; line-height: 1.7; max-width: 280px; }
.cf-btn {
    margin-top: 4px;
    background: transparent;
    border: 1px solid rgba(255,51,51,0.45);
    color: rgba(255,51,51,0.8);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    padding: 8px 24px;
    cursor: pointer;
    transition: all 0.15s;
}
.cf-btn:hover {
    background: rgba(255,51,51,0.1);
    border-color: rgba(255,51,51,0.5);
}
</style>
