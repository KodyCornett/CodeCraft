<template>
    <div class="nf-page">
        <div class="nf-code">404</div>
        <div class="nf-msg">NODE NOT FOUND</div>
        <div class="nf-url">{{ url }}</div>
        <div class="nf-sub">The requested SPLICE address does not resolve to any known node on this network.</div>
        <button class="nf-home" @click="spliceNavigate('splice://home')">← RETURN HOME</button>
    </div>
</template>

<script setup>
import { inject } from 'vue';
defineProps({ url: { type: String, default: '' } });
const spliceNavigate = inject('spliceNavigate', () => {});
</script>

<style scoped>
.nf-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    gap: 12px;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    padding: 40px;
}

.nf-code {
    font-size: 80px;
    color: rgba(255, 51, 51, 0.5);
    letter-spacing: 0.1em;
    line-height: 1;
}

.nf-msg {
    font-size: 16px;
    color: rgba(255, 51, 51, 0.8);
    letter-spacing: 0.2em;
}

.nf-url {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.1em;
    margin-top: 8px;
}

.nf-sub {
    font-size: 10px;
    color: rgba(255, 255, 255, 0.3);
    letter-spacing: 0.06em;
    max-width: 380px;
    text-align: center;
    line-height: 1.7;
}

.nf-home {
    margin-top: 20px;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.25);
    color: rgba(0, 255, 255, 0.55);
    font-family: inherit;
    font-size: 10px;
    letter-spacing: 0.1em;
    padding: 9px 22px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
}
.nf-home:hover {
    color: #00FFFF;
    border-color: rgba(0, 255, 255, 0.65);
}
</style>
