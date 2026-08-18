<template>
    <div class="maps-page">
        <header class="maps-header">
            <div class="maps-brand-name">SPLICE // MAPS</div>
            <div class="maps-brand-sub">Node directory &amp; signal tracking</div>
        </header>

        <section class="maps-search">
            <input
                v-model="query"
                type="text"
                class="maps-input"
                placeholder="search by address (e.g. 14.A3F9) or name..."
                autocomplete="off"
                spellcheck="false"
                @keyup.enter="focusFirstResult"
            />

            <div v-if="cacheLoading" class="maps-status">
                <span class="maps-status-cursor">▌</span> loading node directory...
            </div>
            <div v-else-if="cacheError" class="maps-status maps-status--error">
                [ directory unavailable ] {{ cacheError }}
            </div>

            <div v-else-if="query.trim() && !results.length" class="maps-status">
                no signal matches "{{ query.trim() }}"
            </div>

            <ul v-else-if="results.length" class="maps-results">
                <li
                    v-for="hit in results"
                    :key="hit.node.canvasId"
                    class="maps-result"
                    tabindex="0"
                >
                    <div class="maps-result-info">
                        <div class="maps-result-name">{{ hit.identity.networkName }}</div>
                        <div class="maps-result-meta">
                            <span class="maps-result-address">{{ hit.identity.spliceAddress }}</span>
                            <span class="maps-result-district">{{ hit.node.district ?? 'RELAY / JUNCTION' }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="maps-track-btn"
                        :class="{ 'maps-track-btn--active': isTracked(hit.node.canvasId) }"
                        @click="toggleTrack(hit)"
                    >
                        {{ isTracked(hit.node.canvasId) ? 'TRACKED' : 'TRACK' }}
                    </button>
                </li>
            </ul>
        </section>

        <section class="maps-tracked">
            <div class="maps-section-title">
                TRACKED SIGNALS
                <span class="maps-tracked-count">{{ trackedMarkers.length }}/{{ maxTracked }}</span>
            </div>

            <div v-if="!trackedMarkers.length" class="maps-status">
                nothing pinned yet — search above and hit TRACK to mark a signal on the map.
            </div>

            <ul v-else class="maps-tracked-list">
                <li v-for="marker in trackedMarkers" :key="marker.canvasId" class="maps-tracked-item">
                    <span class="maps-tracked-dot" :style="{ background: marker.color }"></span>
                    <span class="maps-tracked-label">{{ marker.label }}</span>
                    <button type="button" class="maps-untrack-btn" @click="untrackNode(marker.canvasId)">
                        REMOVE
                    </button>
                </li>
            </ul>
        </section>

        <footer class="maps-footer">
            Pins are local to this session — they clear on reload.
        </footer>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useNodeTracking } from '@/composables/useNodeTracking.js';

defineProps({ url: { type: String, default: '' } });

const {
    trackedMarkers,
    cacheLoading,
    cacheError,
    maxTracked,
    search,
    trackNode,
    untrackNode,
    isTracked,
} = useNodeTracking();

const query   = ref('');
const results = ref([]);

watch(query, async (q) => {
    if (!q.trim()) {
        results.value = [];
        return;
    }
    results.value = await search(q);
});

function toggleTrack(hit) {
    if (isTracked(hit.node.canvasId)) {
        untrackNode(hit.node.canvasId);
    } else {
        trackNode(hit.node, hit.identity.networkName);
    }
}

function focusFirstResult() {
    if (results.value.length === 1) toggleTrack(results.value[0]);
}
</script>

<style scoped>
.maps-page {
    font-family: 'JetBrains Mono', monospace;
    background: #060e14;
    color: #a8d8e8;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.maps-header {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(0, 229, 255, 0.25);
}
.maps-brand-name { font-size: 14px; font-weight: 700; letter-spacing: 0.1em; color: #00e5ff; }
.maps-brand-sub  { font-size: 8px; color: #4a8a9a; margin-top: 2px; }

.maps-search { padding: 14px 18px; border-bottom: 1px solid rgba(0, 229, 255, 0.12); }

.maps-input {
    width: 100%;
    box-sizing: border-box;
    background: #0a1820;
    border: 1px solid rgba(0, 229, 255, 0.3);
    color: #d8f4fc;
    font-family: inherit;
    font-size: 11px;
    padding: 9px 12px;
    outline: none;
}
.maps-input:focus { border-color: #00e5ff; }
.maps-input::placeholder { color: #3a6a7a; }

.maps-status {
    padding: 12px 2px 2px;
    font-size: 9.5px;
    color: #4a8a9a;
}
.maps-status--error { color: #ff6666; }
.maps-status-cursor { animation: maps-blink 1s step-end infinite; }
@keyframes maps-blink { 50% { opacity: 0; } }

.maps-results {
    list-style: none;
    margin: 10px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.maps-result {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 9px 10px;
    background: #0a1820;
    border: 1px solid rgba(0, 229, 255, 0.1);
}
.maps-result-name { font-size: 10.5px; font-weight: 700; color: #d8f4fc; }
.maps-result-meta { display: flex; gap: 10px; margin-top: 3px; font-size: 8.5px; color: #4a8a9a; }
.maps-result-address { color: #00e5ff; }

.maps-track-btn {
    flex-shrink: 0;
    background: transparent;
    border: 1px solid #00e5ff;
    color: #00e5ff;
    font-family: inherit;
    font-size: 8.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    padding: 6px 10px;
    cursor: pointer;
}
.maps-track-btn:hover { background: rgba(0, 229, 255, 0.12); }
.maps-track-btn--active {
    background: #00e5ff;
    color: #060e14;
}

.maps-tracked { padding: 14px 18px; border-bottom: 1px solid rgba(0, 229, 255, 0.12); flex: 1; }
.maps-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #00e5ff; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between;
}
.maps-tracked-count { color: #4a8a9a; font-weight: 400; letter-spacing: normal; }

.maps-tracked-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.maps-tracked-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    background: #0a1820;
    border: 1px solid rgba(0, 229, 255, 0.1);
}
.maps-tracked-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.maps-tracked-label { flex: 1; font-size: 9.5px; color: #b8e4f0; }
.maps-untrack-btn {
    background: transparent;
    border: 1px solid rgba(255, 102, 102, 0.5);
    color: #ff8888;
    font-family: inherit;
    font-size: 7.5px;
    font-weight: 700;
    letter-spacing: 0.06em;
    padding: 4px 8px;
    cursor: pointer;
}
.maps-untrack-btn:hover { background: rgba(255, 102, 102, 0.12); }

.maps-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #2a5a6a;
    border-top: 1px solid rgba(0, 229, 255, 0.15);
}
</style>
