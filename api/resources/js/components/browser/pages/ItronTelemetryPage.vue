<template>
    <div class="itr-page">
        <header class="itr-header">
            <div class="itr-brand">
                <span class="itr-pulse" />
                <div>
                    <div class="itr-brand-name">I.T.R.O.N.</div>
                    <div class="itr-brand-sub">Telemetry &amp; Remote Operational Networks</div>
                </div>
            </div>
            <div class="itr-node-id">NODE: MUNI-SENS-7712 // ONLINE</div>
        </header>

        <div v-if="loading" class="itr-loading">
            <span class="itr-loading-cursor">▌</span> SYNCING SENSOR MESH...
        </div>
        <div v-else-if="errorMsg" class="itr-error">[ LINK DOWN ] {{ errorMsg }}</div>

        <template v-else>
            <div class="itr-scanlines" />

            <section class="itr-about">
                <div class="itr-section-title">ABOUT I.T.R.O.N.</div>
                <p class="itr-about-text">
                    Telemetry &amp; Remote Operational Networks contracts with municipal and private
                    infrastructure operators across the Spokane Basin, running low-power mesh sensor arrays
                    for load monitoring, structural telemetry, and remote diagnostics. Current municipal
                    clients include <LinkifiedText text="A.V.I.S.T.A." /> and <LinkifiedText text="S.T.A." /> Transit Automation.
                </p>
            </section>

            <section class="itr-news">
                <div class="itr-section-title">NETWORK BULLETINS</div>
                <article class="itr-news-item">
                    <div class="itr-news-meta">
                        <span class="itr-news-date">08.10.2026</span>
                        <span class="itr-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="itr-news-headline">Mesh Expansion Contract Signed With <LinkifiedText text="A.V.I.S.T.A." /></div>
                    <p class="itr-news-body">
                        I.T.R.O.N. will deploy 40 additional sensor nodes across Region 09 substations as
                        part of <LinkifiedText text="A.V.I.S.T.A.'s" /> smart-grid modernization initiative, with priority rollout
                        at legacy sites reporting harmonic irregularities.
                    </p>
                </article>
                <article class="itr-news-item">
                    <div class="itr-news-meta">
                        <span class="itr-news-date">08.02.2026</span>
                        <span class="itr-news-tag">FIRMWARE</span>
                    </div>
                    <div class="itr-news-headline">Firmware v4.13 Rollout Delayed to Q4</div>
                    <p class="itr-news-body">
                        The scheduled security patch for the MUNI-SENS mesh has been pushed back due to
                        compatibility testing with legacy substation hardware. v4.12 remains in production.
                    </p>
                </article>
                <article class="itr-news-item">
                    <div class="itr-news-meta">
                        <span class="itr-news-date">07.18.2026</span>
                        <span class="itr-news-tag">EXPANSION</span>
                    </div>
                    <div class="itr-news-headline">I.T.R.O.N. Opens Valley Corridor Monitoring Contract</div>
                    <p class="itr-news-body">
                        A new bid was awarded for structural telemetry coverage along the Valley Corridor
                        rail line, expanding I.T.R.O.N.'s footprint beyond its original downtown grid contracts.
                    </p>
                </article>
            </section>

            <pre class="itr-body">{{ page?.body }}</pre>

            <footer class="itr-footer">
                I.T.R.O.N. MUNICIPAL MESH // 915MHz INDUSTRIAL BAND // AUTO-REFRESH 4s
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'itron-telemetry';
const { fetchPage } = useCodex();

const loading  = ref(true);
const errorMsg = ref(null);
const page     = ref(null);

onMounted(async () => {
    try {
        page.value = await fetchPage(SLUG);
    } catch (e) {
        errorMsg.value = e?.response?.data?.message ?? e.message ?? 'Page unavailable';
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.itr-page {
    position: relative;
    font-family: 'JetBrains Mono', monospace;
    background: #060a08;
    color: #7fd6a0;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.itr-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    border-bottom: 1px solid rgba(0,255,157,0.2);
    background: #081008;
    z-index: 1;
}
.itr-brand { display: flex; align-items: center; gap: 10px; }
.itr-pulse {
    width: 8px; height: 8px; border-radius: 50%;
    background: #00ff9d; box-shadow: 0 0 8px #00ff9d;
    animation: itr-pulse 1.4s ease-in-out infinite;
}
@keyframes itr-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
.itr-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.14em; color: #00ff9d; }
.itr-brand-sub  { font-size: 8px; color: #4a9a72; }
.itr-node-id { font-size: 9px; color: #4a9a72; letter-spacing: 0.06em; }

.itr-loading, .itr-error { padding: 30px; text-align: center; font-size: 11px; color: #4a9a72; z-index: 1; }
.itr-error { color: #ff6666; }
.itr-loading-cursor { animation: itr-blink 1s step-end infinite; }
@keyframes itr-blink { 50% { opacity: 0; } }

.itr-scanlines {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(0deg, rgba(0,255,157,0.02) 0 1px, transparent 1px 3px);
}

.itr-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #00ff9d; margin-bottom: 8px; z-index: 1; position: relative;
}
.itr-about, .itr-news {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(0,255,157,0.1);
    z-index: 1;
    position: relative;
}
.itr-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #7fd6a0; max-width: 620px; }

.itr-news-item { padding: 8px 0; border-top: 1px solid rgba(0,255,157,0.06); }
.itr-news-item:first-of-type { border-top: none; padding-top: 0; }
.itr-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.itr-news-date { font-size: 8px; color: #2e5a42; }
.itr-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #060a08; background: #00ff9d; padding: 1px 6px;
}
.itr-news-headline { font-size: 11px; font-weight: 700; color: #00ff9d; margin-bottom: 4px; }
.itr-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #5aa87e; max-width: 620px; }

.itr-body {
    flex: 1;
    margin: 0;
    padding: 16px 18px 24px;
    font-size: 10px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #7fd6a0;
    z-index: 1;
    text-shadow: 0 0 4px rgba(0,255,157,0.15);
}

.itr-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #2e5a42;
    border-top: 1px solid rgba(0,255,157,0.1);
    letter-spacing: 0.06em;
    z-index: 1;
}
</style>
