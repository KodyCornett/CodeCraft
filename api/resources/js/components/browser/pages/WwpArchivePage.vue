<template>
    <div class="wwp-page">
        <header class="wwp-header">
            <div class="wwp-brand-name">W.W.P.</div>
            <div class="wwp-brand-sub">West-Cascade Water &amp; Power Transmission Group</div>
            <div class="wwp-est">EST. 1889</div>
        </header>

        <div class="wwp-banner">HISTORICAL INFRASTRUCTURE ARCHIVE</div>

        <div v-if="loading" class="wwp-loading">
            <span class="wwp-loading-cursor">▌</span> retrieving microfiche record...
        </div>
        <div v-else-if="errorMsg" class="wwp-error">[ record unavailable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="wwp-about">
                <div class="wwp-section-title">ABOUT THE ARCHIVE</div>
                <p class="wwp-about-text">
                    West-Cascade Water &amp; Power Transmission Group operated the Spokane Basin's hydro and
                    conduit infrastructure from 1889 until its 1974 absorption into the newly-chartered
                    <LinkifiedText text="A.V.I.S.T.A." /> authority. This archive preserves W.W.P.'s original engineering records,
                    maintained by the Historical Records Division for municipal planning and civic research.
                </p>
            </section>

            <section class="wwp-news">
                <div class="wwp-section-title">RECENT ACCESSIONS</div>
                <article class="wwp-news-item">
                    <div class="wwp-news-meta">
                        <span class="wwp-news-date">08.09.2026</span>
                        <span class="wwp-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="wwp-news-headline">Digitization Initiative Reaches 1970s Engineering Records</div>
                    <p class="wwp-news-body">
                        The Historical Records Division has completed scanning of pre-takeover blueprints
                        through 1974, making the full sub-river conduit survey publicly searchable for the
                        first time since the SMDA transition.
                    </p>
                </article>
                <article class="wwp-news-item">
                    <div class="wwp-news-meta">
                        <span class="wwp-news-date">07.14.2026</span>
                        <span class="wwp-news-tag">EVENT</span>
                    </div>
                    <div class="wwp-news-headline">Public Lecture: "The Falls Before the Grid"</div>
                    <p class="wwp-news-body">
                        A free civic history lecture on the original hydro-expansion era will be held at the
                        Riverfront Park pavilion, covering the 1988 tunnel expansion and its role in the
                        modern transit network.
                    </p>
                </article>
                <article class="wwp-news-item">
                    <div class="wwp-news-meta">
                        <span class="wwp-news-date">06.30.2026</span>
                        <span class="wwp-news-tag">NOTICE</span>
                    </div>
                    <div class="wwp-news-headline">Archive Reading Room Reduces Hours</div>
                    <p class="wwp-news-body">
                        Due to staffing constraints, in-person access to unscanned physical records is now
                        limited to Tuesdays and Thursdays. Digitized holdings remain available online at all times.
                    </p>
                </article>
            </section>

            <div class="wwp-stamp">DECLASSIFIED — CIVIC RECORD</div>
            <pre class="wwp-body">{{ page?.body }}</pre>

            <footer class="wwp-footer">
                West-Cascade Water &amp; Power Transmission Group — Historical Records Division
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'wwp-archive';
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
.wwp-page {
    font-family: 'JetBrains Mono', monospace;
    background: #ece2c8;
    color: #4a3d24;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.wwp-header {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 16px 20px 6px;
    border-bottom: 3px double #8a7548;
}
.wwp-brand-name { font-size: 16px; font-weight: 700; letter-spacing: 0.08em; color: #5a4826; }
.wwp-brand-sub  { font-size: 9px; color: #7a6640; flex: 1; }
.wwp-est { font-size: 9px; color: #8a7548; }

.wwp-banner {
    padding: 6px 20px;
    font-size: 9px;
    letter-spacing: 0.16em;
    color: #6a5834;
    text-align: center;
}

.wwp-loading, .wwp-error { padding: 30px; text-align: center; font-size: 11px; color: #8a7548; }
.wwp-error { color: #a03c2c; }
.wwp-loading-cursor { animation: wwp-blink 1s step-end infinite; }
@keyframes wwp-blink { 50% { opacity: 0; } }

.wwp-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #8a3c2c; margin-bottom: 8px;
}
.wwp-about, .wwp-news { padding: 12px 20px; border-bottom: 1px solid #d8c8a0; }
.wwp-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #4a3d24; max-width: 620px; }

.wwp-news-item { padding: 8px 0; border-top: 1px solid #e0d4b0; }
.wwp-news-item:first-of-type { border-top: none; padding-top: 0; }
.wwp-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.wwp-news-date { font-size: 8px; color: #8a7548; }
.wwp-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #f4ecd4; background: #8a7548; padding: 1px 6px;
}
.wwp-news-headline { font-size: 11px; font-weight: 700; color: #5a4826; margin-bottom: 4px; }
.wwp-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #6a5834; max-width: 620px; }

.wwp-stamp {
    align-self: flex-start;
    margin: 8px 20px 0;
    padding: 4px 10px;
    border: 2px solid #a03c2c;
    color: #a03c2c;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    transform: rotate(-2deg);
}

.wwp-body {
    flex: 1;
    margin: 12px 20px 24px;
    padding: 16px;
    background: #f4ecd4;
    border: 1px solid #b8a878;
    box-shadow: 0 0 0 4px #ece2c8, 0 0 0 5px #b8a878;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #4a3d24;
}

.wwp-footer {
    padding: 10px 20px;
    font-size: 8px;
    color: #8a7548;
    border-top: 1px solid #b8a878;
}
</style>
