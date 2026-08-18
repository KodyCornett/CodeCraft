<template>
    <div class="inl-page">
        <header class="inl-header">
            <div class="inl-brand-name">Inland Commercial Properties &amp; Asset Management</div>
            <div class="inl-nav">
                <span class="inl-nav-item inl-nav-item--active">ABOUT</span>
                <span class="inl-nav-item">LISTINGS</span>
                <span class="inl-nav-item">TENANT PORTAL</span>
                <span class="inl-nav-item">CONTACT</span>
            </div>
        </header>

        <div class="inl-tagline">"Premier Commercial Leasing Across the Inland Northwest"</div>

        <div v-if="loading" class="inl-loading">
            <span class="inl-loading-cursor">▌</span> loading listings...
        </div>
        <div v-else-if="errorMsg" class="inl-error">[ site unavailable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="inl-about">
                <div class="inl-section-title">ABOUT INLAND</div>
                <p class="inl-about-text">
                    Inland Commercial Properties &amp; Asset Management leases sub-level, ground-floor, and
                    warehouse space across the Inland Northwest, with a focus on underground storage vaults
                    repurposed from decommissioned civic infrastructure. All listings include Inland Logistics
                    Network's optional "Utility Asset Management" tenant protection program.
                </p>
            </section>

            <section class="inl-news">
                <div class="inl-section-title">LISTING UPDATES</div>
                <article class="inl-news-item">
                    <div class="inl-news-meta">
                        <span class="inl-news-date">08.07.2026</span>
                        <span class="inl-news-tag">NEW LISTING</span>
                    </div>
                    <div class="inl-news-headline">Hillyard Sub-Basement Unit Now Available</div>
                    <p class="inl-news-body">
                        1,800 sq. ft. of reinforced sub-basement space with unmetered high-amp power access,
                        similar terms to our Browne's Addition property. Inquire for pricing.
                    </p>
                </article>
                <article class="inl-news-item">
                    <div class="inl-news-meta">
                        <span class="inl-news-date">07.28.2026</span>
                        <span class="inl-news-tag">PROGRAM</span>
                    </div>
                    <div class="inl-news-headline">Protection Program Now Bundled With All Leases</div>
                    <p class="inl-news-body">
                        Effective this month, "Utility Asset Management" dues are no longer optional for new
                        tenants and are automatically included in the base lease rate.
                    </p>
                </article>
                <article class="inl-news-item">
                    <div class="inl-news-meta">
                        <span class="inl-news-date">07.15.2026</span>
                        <span class="inl-news-tag">EXPANSION</span>
                    </div>
                    <div class="inl-news-headline">Inland Expands Into University District Corridor</div>
                    <p class="inl-news-body">
                        New commercial listings opening near the research corridor, targeting tenants seeking
                        proximity to <LinkifiedText text="Gonzaga's" /> affiliated labs and <LinkifiedText text="Providence's" /> South Hill enclave campuses.
                    </p>
                </article>
            </section>

            <pre class="inl-body">{{ page?.body }}</pre>

            <footer class="inl-footer">
                Inland Logistics Network — a subsidiary of Inland Commercial Properties &amp; Asset Management.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'inland-leasing';
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
.inl-page {
    font-family: 'JetBrains Mono', monospace;
    background: #f0ece0;
    color: #2a2a30;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.inl-header {
    padding: 14px 18px;
    background: #1c2438;
    color: #e8e4d8;
}
.inl-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; margin-bottom: 8px; }
.inl-nav { display: flex; gap: 16px; }
.inl-nav-item { font-size: 9px; letter-spacing: 0.06em; color: #8a90a8; }
.inl-nav-item--active { color: #d0a840; border-bottom: 1px solid #d0a840; padding-bottom: 2px; }

.inl-tagline {
    padding: 8px 18px;
    font-size: 9px;
    font-style: italic;
    color: #5a5a68;
    background: #e0dcc8;
}

.inl-loading, .inl-error { padding: 30px; text-align: center; font-size: 11px; color: #7a7a88; }
.inl-error { color: #a03c2c; }
.inl-loading-cursor { animation: inl-blink 1s step-end infinite; }
@keyframes inl-blink { 50% { opacity: 0; } }

.inl-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #8a6a1a; margin-bottom: 8px;
}
.inl-about, .inl-news { padding: 12px 18px; border-bottom: 1px solid #d8d0b8; }
.inl-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #2a2a30; max-width: 620px; }

.inl-news-item { padding: 8px 0; border-top: 1px solid #e0dcc8; }
.inl-news-item:first-of-type { border-top: none; padding-top: 0; }
.inl-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.inl-news-date { font-size: 8px; color: #7a7a88; }
.inl-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #1c2438; background: #d0a840; padding: 1px 6px;
}
.inl-news-headline { font-size: 11px; font-weight: 700; color: #1c2438; margin-bottom: 4px; }
.inl-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #5a5a68; max-width: 620px; }

.inl-body {
    flex: 1;
    margin: 14px 18px 24px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid #d0cab8;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #2a2a30;
}

.inl-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #8a8a98;
    border-top: 1px solid #d0cab8;
}
</style>
