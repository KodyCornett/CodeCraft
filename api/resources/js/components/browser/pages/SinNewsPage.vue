<template>
    <div class="sin-page">
        <header class="sin-header">
            <div class="sin-brand-name">S.I.N.</div>
            <div class="sin-brand-sub">Spokane Information Network</div>
            <div class="sin-live">● LIVE</div>
        </header>

        <div class="sin-tagline">"Your Trusted Voice for Civic Progress &amp; Safety"</div>

        <div v-if="loading" class="sin-loading">
            <span class="sin-loading-cursor">▌</span> loading broadcast...
        </div>
        <div v-else-if="errorMsg" class="sin-error">[ broadcast interrupted ] {{ errorMsg }}</div>

        <template v-else>
            <div class="sin-ticker">
                SMDA ANNOUNCES SUCCESSFUL INFRASTRUCTURE UPGRADES &nbsp;•&nbsp;
                MONOLITH TACTICAL REPORTS RECORD LOW CRIME RATES
            </div>

            <section class="sin-about">
                <div class="sin-section-title">ABOUT S.I.N.</div>
                <p class="sin-about-text">
                    Spokane Information Network is the region's largest broadcast and digital news outlet,
                    operating in partnership with Monolith Tactical Security and the Spokane Municipal
                    Development Authority. S.I.N. provides 24-hour civic updates, safety bulletins, and
                    infrastructure reporting for the greater Spokane Basin.
                </p>
            </section>

            <section class="sin-news">
                <div class="sin-section-title">MORE HEADLINES</div>
                <article class="sin-news-item">
                    <div class="sin-news-meta">
                        <span class="sin-news-date">08.11.2026</span>
                        <span class="sin-news-tag">CIVIC</span>
                    </div>
                    <div class="sin-news-headline">SMDA Unveils Region 09 Smart Grid Investment Package</div>
                    <p class="sin-news-body">
                        City officials praised <LinkifiedText text="A.V.I.S.T.A.'s" /> newly announced modernization initiative as
                        "a model for responsible infrastructure stewardship" at a press conference Tuesday.
                    </p>
                </article>
                <article class="sin-news-item">
                    <div class="sin-news-meta">
                        <span class="sin-news-date">08.05.2026</span>
                        <span class="sin-news-tag">SAFETY</span>
                    </div>
                    <div class="sin-news-headline">Monolith Expands Substation Security Contracts Citywide</div>
                    <p class="sin-news-body">
                        Following isolated incidents of "unauthorized infrastructure access," Monolith Tactical
                        will provide monitoring at additional priority sites through year end.
                    </p>
                </article>
                <article class="sin-news-item">
                    <div class="sin-news-meta">
                        <span class="sin-news-date">07.30.2026</span>
                        <span class="sin-news-tag">HEALTH</span>
                    </div>
                    <div class="sin-news-headline"><LinkifiedText text="Providence Healthcare" /> Recognized for Series-9 Rollout Speed</div>
                    <p class="sin-news-body">
                        SMDA officials commended <LinkifiedText text="Providence's" /> rapid patient transition program during today's
                        civic health briefing, calling it "a public-private partnership success story."
                    </p>
                </article>
            </section>

            <pre class="sin-body">{{ page?.body }}</pre>

            <footer class="sin-footer">
                Spokane Information Network — a Monolith Tactical media partner.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'sin-news';
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
.sin-page {
    font-family: 'JetBrains Mono', monospace;
    background: #f4f6fa;
    color: #1a2438;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.sin-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    background: #12203a;
    color: #fff;
}
.sin-brand-name { font-size: 15px; font-weight: 700; letter-spacing: 0.06em; color: #ff3b3b; }
.sin-brand-sub  { font-size: 9px; color: #b8c4e0; flex: 1; }
.sin-live { font-size: 8px; color: #ff3b3b; letter-spacing: 0.08em; animation: sin-blink 1.4s step-end infinite; }
@keyframes sin-blink { 50% { opacity: 0.3; } }

.sin-tagline {
    padding: 6px 18px;
    font-size: 9px;
    font-style: italic;
    color: #4a5878;
    background: #dde4f0;
}

.sin-loading, .sin-error { padding: 30px; text-align: center; font-size: 11px; color: #5a6888; }
.sin-error { color: #c0392b; }
.sin-loading-cursor { animation: sin-blink2 1s step-end infinite; }
@keyframes sin-blink2 { 50% { opacity: 0; } }

.sin-ticker {
    padding: 6px 18px;
    font-size: 9px;
    font-weight: 700;
    color: #fff;
    background: #ff3b3b;
    letter-spacing: 0.04em;
}

.sin-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #12203a; margin-bottom: 8px;
}
.sin-about, .sin-news { padding: 12px 18px; border-bottom: 1px solid #d0d8e8; }
.sin-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #1a2438; max-width: 620px; }

.sin-news-item { padding: 8px 0; border-top: 1px solid #e0e6f0; }
.sin-news-item:first-of-type { border-top: none; padding-top: 0; }
.sin-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.sin-news-date { font-size: 8px; color: #7a88a8; }
.sin-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #fff; background: #12203a; padding: 1px 6px;
}
.sin-news-headline { font-size: 11px; font-weight: 700; color: #12203a; margin-bottom: 4px; }
.sin-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #4a5878; max-width: 620px; }

.sin-body {
    flex: 1;
    margin: 14px 18px 24px;
    padding: 14px 16px;
    background: #ffffff;
    border: 1px solid #d0d8e8;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #1a2438;
}

.sin-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #7a88a8;
    border-top: 1px solid #d0d8e8;
}
</style>
