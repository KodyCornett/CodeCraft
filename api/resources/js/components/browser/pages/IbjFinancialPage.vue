<template>
    <div class="ibj-page">
        <header class="ibj-header">
            <div class="ibj-brand-name">I.B.J.</div>
            <div class="ibj-brand-sub">Inland Business Journal — Financial Data &amp; Market Analytics</div>
        </header>

        <div v-if="loading" class="ibj-loading">
            <span class="ibj-loading-cursor">▌</span> pulling market feed...
        </div>
        <div v-else-if="errorMsg" class="ibj-error">[ feed unavailable ] {{ errorMsg }}</div>

        <template v-else>
            <div class="ibj-ticker">
                <span class="ibj-up">AETH ▲4.2%</span>
                <span class="ibj-down">APX ▼1.8%</span>
                <span class="ibj-up">MNS ▲6.1%</span>
            </div>

            <section class="ibj-about">
                <div class="ibj-section-title">ABOUT I.B.J.</div>
                <p class="ibj-about-text">
                    The Inland Business Journal has covered corporate markets, logistics, and regional
                    development across the Inland Northwest since 2008. I.B.J. maintains real-time coverage
                    of publicly-traded infrastructure, security, and healthcare firms operating within the
                    Spokane Basin.
                </p>
            </section>

            <section class="ibj-news">
                <div class="ibj-section-title">MARKET NEWS</div>
                <article class="ibj-news-item">
                    <div class="ibj-news-meta">
                        <span class="ibj-news-date">08.12.2026</span>
                        <span class="ibj-news-tag">EARNINGS</span>
                    </div>
                    <div class="ibj-news-headline">APX Infra Shares Slide Further on Substation 09 Fallout</div>
                    <p class="ibj-news-body">
                        Apex Infrastructure stock continued its decline this week following continued
                        operational disruptions at Region 09, though analysts remain split on long-term impact.
                    </p>
                </article>
                <article class="ibj-news-item">
                    <div class="ibj-news-meta">
                        <span class="ibj-news-date">08.06.2026</span>
                        <span class="ibj-news-tag">CONTRACTS</span>
                    </div>
                    <div class="ibj-news-headline">Monolith Sec Wins Expanded Municipal Security Deal</div>
                    <p class="ibj-news-body">
                        MNS shares rallied after the company secured an extended contract covering additional
                        substation and transit security across the metro area.
                    </p>
                </article>
                <article class="ibj-news-item">
                    <div class="ibj-news-meta">
                        <span class="ibj-news-date">07.29.2026</span>
                        <span class="ibj-news-tag">M&amp;A</span>
                    </div>
                    <div class="ibj-news-headline">Aetheron Bio Rumored to Be Eyeing <LinkifiedText text="S.T.I.T.C.H.E.R.S." /> Enforcement</div>
                    <p class="ibj-news-body">
                        Unconfirmed sources suggest Aetheron is lobbying regulators to crack down on
                        gray-market hardware resale ahead of its Series-9 rollout — a move that could hit
                        margins across the secondary hardware market.
                    </p>
                </article>
            </section>

            <pre class="ibj-body">{{ page?.body }}</pre>

            <footer class="ibj-footer">
                Inland Business Journal — data delayed 15 minutes. Not investment advice.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'ibj-financial';
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
.ibj-page {
    font-family: 'JetBrains Mono', monospace;
    background: #06110a;
    color: #b8d8c0;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.ibj-header {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(80,200,120,0.25);
}
.ibj-brand-name { font-size: 14px; font-weight: 700; letter-spacing: 0.1em; color: #50c878; }
.ibj-brand-sub  { font-size: 8px; color: #5a8a6a; margin-top: 2px; }

.ibj-loading, .ibj-error { padding: 30px; text-align: center; font-size: 11px; color: #5a8a6a; }
.ibj-error { color: #ff6666; }
.ibj-loading-cursor { animation: ibj-blink 1s step-end infinite; }
@keyframes ibj-blink { 50% { opacity: 0; } }

.ibj-ticker {
    display: flex;
    gap: 20px;
    padding: 6px 18px;
    font-size: 9px;
    font-weight: 700;
    background: #0c1c12;
    border-bottom: 1px solid rgba(80,200,120,0.15);
}
.ibj-up   { color: #50c878; }
.ibj-down { color: #e05555; }

.ibj-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #50c878; margin-bottom: 8px;
}
.ibj-about, .ibj-news { padding: 14px 18px; border-bottom: 1px solid rgba(80,200,120,0.12); }
.ibj-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #b8d8c0; max-width: 620px; }

.ibj-news-item { padding: 8px 0; border-top: 1px solid rgba(80,200,120,0.08); }
.ibj-news-item:first-of-type { border-top: none; padding-top: 0; }
.ibj-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.ibj-news-date { font-size: 8px; color: #5a8a6a; }
.ibj-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #06110a; background: #50c878; padding: 1px 6px;
}
.ibj-news-headline { font-size: 11px; font-weight: 700; color: #50c878; margin-bottom: 4px; }
.ibj-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #7aa888; max-width: 620px; }

.ibj-body {
    flex: 1;
    margin: 0;
    padding: 16px 18px 24px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #b8d8c0;
}

.ibj-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #3a6a48;
    border-top: 1px solid rgba(80,200,120,0.15);
}
</style>
