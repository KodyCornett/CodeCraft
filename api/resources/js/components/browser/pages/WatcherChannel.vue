<template>
    <div class="wc-page">

        <div class="wc-header">
            <span class="wc-tag">[ENCRYPTED_CHANNEL // SOURCE: UNKNOWN]</span>
            <span class="wc-blink">▌</span>
        </div>

        <div class="wc-rule" />

        <div v-if="loading && !signals.length" class="wc-loading">
            FETCHING SIGNAL LOG...
        </div>

        <div v-else-if="!signals.length" class="wc-empty">
            [ NO TRANSMISSIONS ON RECORD ]
        </div>

        <div v-else class="wc-log">
            <div
                v-for="sig in signals"
                :key="sig.id"
                class="wc-entry"
                :class="{ 'wc-entry--unread': !sig.read_at }"
            >
                <div class="wc-entry-meta">
                    <span class="wc-entry-tag">[ENCRYPTED_SIGNAL // SOURCE: UNKNOWN]</span>
                    <span class="wc-entry-time">{{ formatTime(sig.delivered_at) }}</span>
                    <span v-if="!sig.read_at" class="wc-entry-new">NEW</span>
                </div>
                <div class="wc-entry-rule" />
                <div class="wc-entry-label">[MESSAGE]:</div>
                <div class="wc-entry-body">{{ sig.signal_text }}</div>
                <div class="wc-entry-rule" />
                <div class="wc-entry-end">[END_SIGNAL]</div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(true);
const signals = ref([]);

// Mark all read when this page opens — injected from Game.vue via useWatcher
const watcherMarkAllRead = inject('watcherMarkAllRead', null);

async function fetchAll() {
    try {
        const res  = await axios.get('/api/watcher/all');
        signals.value = res.data.signals ?? [];
    } catch (e) {
        console.warn('[WATCHER CHANNEL] fetchAll failed:', e?.message);
    } finally {
        loading.value = false;
    }
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('en-US', { hour12: false, dateStyle: 'short', timeStyle: 'short' });
}

onMounted(async () => {
    await fetchAll();
    watcherMarkAllRead?.();
});
</script>

<style scoped>
.wc-page {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #80c090;
    background: transparent;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 100%;
}

.wc-header {
    display: flex;
    align-items: center;
    gap: 6px;
}
.wc-tag {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.08em;
}
.wc-blink {
    color: rgba(0,255,100,0.3);
    animation: wc-blink 1s steps(1) infinite;
}
@keyframes wc-blink { 0%,49%{opacity:1} 50%,100%{opacity:0} }

.wc-rule { border: none; border-top: 1px solid rgba(0,255,100,0.07); margin: 2px 0; }
.wc-loading, .wc-empty { font-size: 10px; color: rgba(0,255,100,0.2); padding: 20px 0; text-align: center; }

.wc-log { display: flex; flex-direction: column; gap: 20px; }

.wc-entry {
    opacity: 0.55;
    transition: opacity 0.2s;
}
.wc-entry--unread { opacity: 1; }

.wc-entry-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.wc-entry-tag  { font-size: 8px; color: rgba(0,255,100,0.25); letter-spacing: 0.08em; }
.wc-entry-time { font-size: 8px; color: rgba(0,255,100,0.2); }
.wc-entry-new  {
    font-size: 8px; color: rgba(0,255,100,0.7);
    letter-spacing: 0.1em;
    animation: wc-blink 1.5s ease-in-out infinite;
}
.wc-entry-rule { border: none; border-top: 1px solid rgba(0,255,100,0.06); margin: 4px 0; }
.wc-entry-label { font-size: 8px; color: rgba(0,255,100,0.2); letter-spacing: 0.06em; margin-bottom: 6px; }
.wc-entry-body {
    font-size: 11px;
    color: #90d0a0;
    line-height: 1.7;
    white-space: pre-wrap;
}
.wc-entry-end { font-size: 8px; color: rgba(0,255,100,0.18); letter-spacing: 0.1em; margin-top: 4px; }
</style>
