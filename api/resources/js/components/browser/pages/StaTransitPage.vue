<template>
    <div class="sta-page">
        <header class="sta-header">
            <div class="sta-brand">
                <span class="sta-icon">◫</span>
                <div>
                    <div class="sta-brand-name">S.T.A.</div>
                    <div class="sta-brand-sub">Spokane Subterranean &amp; Transit Automation</div>
                </div>
            </div>
            <div class="sta-clock">{{ time }}</div>
        </header>

        <div class="sta-banner">SYSTEM STATUS &amp; AUTOMATED ROUTE NETWORK</div>

        <div v-if="loading" class="sta-loading">
            <span class="sta-loading-cursor">▌</span> pulling live line status...
        </div>
        <div v-else-if="errorMsg" class="sta-error">[ feed unavailable ] {{ errorMsg }}</div>

        <template v-else>
            <div class="sta-lines">
                <div class="sta-line sta-line--ok">LINE 1 — DOWNTOWN LOOP <span>NORMAL</span></div>
                <div class="sta-line sta-line--warn">LINE 2 — VALLEY CORRIDOR <span>DELAYED</span></div>
                <div class="sta-line sta-line--closed">SUB-LEVEL 3 — FREIGHT RAIL <span>CLOSED</span></div>
            </div>

            <section class="sta-about">
                <div class="sta-section-title">ABOUT S.T.A.</div>
                <p class="sta-about-text">
                    Spokane Subterranean &amp; Transit Automation operates the city's below-grade rail network,
                    including the Downtown Loop, Valley Corridor line, and a network of maintenance and freight
                    sub-levels inherited from the old <LinkifiedText text="W.W.P." /> tunnel system. S.T.A. contracts <LinkifiedText text="I.T.R.O.N." /> for
                    structural telemetry across all active lines.
                </p>
            </section>

            <section class="sta-news">
                <div class="sta-section-title">TRANSIT BULLETINS</div>
                <article class="sta-news-item">
                    <div class="sta-news-meta">
                        <span class="sta-news-date">08.08.2026</span>
                        <span class="sta-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="sta-news-headline">Valley Corridor Automation Upgrade Announced</div>
                    <p class="sta-news-body">
                        S.T.A. will begin phased installation of autonomous signal control along Line 2,
                        reducing reliance on manual switching and cutting scheduled delays by an estimated 40%
                        once complete in early 2027.
                    </p>
                </article>
                <article class="sta-news-item">
                    <div class="sta-news-meta">
                        <span class="sta-news-date">08.06.2026</span>
                        <span class="sta-news-tag">SECURITY</span>
                    </div>
                    <div class="sta-news-headline">Monolith Sweep Extended at Sprague Station</div>
                    <p class="sta-news-body">
                        Tactical security inspections at Sprague Station will continue through the end of the
                        month following reports of unregistered hardware along the third rail in the lower
                        service tunnels.
                    </p>
                </article>
                <article class="sta-news-item">
                    <div class="sta-news-meta">
                        <span class="sta-news-date">07.25.2026</span>
                        <span class="sta-news-tag">FARES</span>
                    </div>
                    <div class="sta-news-headline">Monthly Pass Pricing Adjustment Effective September</div>
                    <p class="sta-news-body">
                        Standard monthly passes will increase by 2 credits to offset automation upgrade costs.
                        Reduced-fare programs for University District students remain unchanged.
                    </p>
                </article>
            </section>

            <pre class="sta-body">{{ page?.body }}</pre>

            <footer class="sta-footer">
                S.T.A. Municipal Transit Terminal — Monolith Tactical security notices override all schedules.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'sta-transit';
const { fetchPage } = useCodex();

const loading  = ref(true);
const errorMsg = ref(null);
const page     = ref(null);
const time     = ref('');
let timer;

onMounted(async () => {
    const tick = () => { time.value = new Date().toLocaleTimeString('en-US', { hour12: false }); };
    tick();
    timer = setInterval(tick, 1000);
    try {
        page.value = await fetchPage(SLUG);
    } catch (e) {
        errorMsg.value = e?.response?.data?.message ?? e.message ?? 'Page unavailable';
    } finally {
        loading.value = false;
    }
});
onUnmounted(() => clearInterval(timer));
</script>

<style scoped>
.sta-page {
    font-family: 'JetBrains Mono', monospace;
    background: #0a1420;
    color: #b8d0e0;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.sta-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #0e1c2e;
    border-bottom: 2px solid #2a7ac0;
}
.sta-brand { display: flex; align-items: center; gap: 10px; }
.sta-icon { font-size: 18px; color: #ff9838; }
.sta-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.1em; color: #6ab0e8; }
.sta-brand-sub  { font-size: 8px; color: #5a7a92; }
.sta-clock { font-size: 10px; color: #ff9838; }

.sta-banner {
    padding: 7px 18px;
    font-size: 9px;
    letter-spacing: 0.14em;
    background: #2a7ac0;
    color: #061018;
    font-weight: 700;
}

.sta-loading, .sta-error { padding: 30px; text-align: center; font-size: 11px; color: #5a7a92; }
.sta-error { color: #ff6666; }
.sta-loading-cursor { animation: sta-blink 1s step-end infinite; }
@keyframes sta-blink { 50% { opacity: 0; } }

.sta-lines { display: flex; flex-direction: column; gap: 4px; padding: 14px 18px 0; }
.sta-line {
    display: flex; justify-content: space-between;
    font-size: 10px; padding: 6px 10px; border-left: 3px solid;
    background: rgba(255,255,255,0.02);
}
.sta-line--ok     { border-color: #3ac07a; color: #b8d0e0; }
.sta-line--ok span { color: #3ac07a; }
.sta-line--warn   { border-color: #ff9838; color: #b8d0e0; }
.sta-line--warn span { color: #ff9838; }
.sta-line--closed { border-color: #ff4444; color: #b8d0e0; }
.sta-line--closed span { color: #ff4444; }

.sta-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #6ab0e8; margin-bottom: 8px;
}
.sta-about, .sta-news { padding: 14px 18px; border-bottom: 1px solid rgba(42,122,192,0.15); }
.sta-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #b8d0e0; max-width: 620px; }

.sta-news-item { padding: 8px 0; border-top: 1px solid rgba(42,122,192,0.1); }
.sta-news-item:first-of-type { border-top: none; padding-top: 0; }
.sta-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.sta-news-date { font-size: 8px; color: #5a7a92; }
.sta-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #061018; background: #6ab0e8; padding: 1px 6px;
}
.sta-news-headline { font-size: 11px; font-weight: 700; color: #6ab0e8; margin-bottom: 4px; }
.sta-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #7a9ab2; max-width: 620px; }

.sta-body {
    flex: 1;
    margin: 14px 18px 24px;
    padding: 0;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #9ab8cc;
}

.sta-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #4a6a82;
    border-top: 1px solid rgba(42,122,192,0.2);
}
</style>
