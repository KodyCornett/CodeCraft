<template>
    <div class="wdl-page">
        <div class="wdl-glitch-bar" />

        <header class="wdl-header">
            <div class="wdl-brand-name">WIRE-DEAD</div>
            <div class="wdl-brand-sub">Pirate Data Dump &amp; Raw Leak Feed</div>
        </header>

        <div class="wdl-slogan">"NO EDITORS. NO CORPORATE FILTER. PURE SIGNAL."</div>

        <div v-if="loading" class="wdl-loading">
            <span class="wdl-loading-cursor">▌</span> mirroring feed...
        </div>
        <div v-else-if="errorMsg" class="wdl-error">[ mirror down ] {{ errorMsg }}</div>

        <template v-else>
            <section class="wdl-about">
                <div class="wdl-section-title">ABOUT THIS FEED</div>
                <p class="wdl-about-text">
                    WIRE-DEAD posts intercepted, leaked, or otherwise "acquired" material with no editorial
                    review and no source verification. Take everything here as unconfirmed until you check it
                    yourself. Domain gets pulled every few months — always comes back somewhere else.
                </p>
            </section>

            <section class="wdl-news">
                <div class="wdl-section-title">RECENT DUMPS</div>
                <article class="wdl-news-item">
                    <div class="wdl-news-meta">
                        <span class="wdl-news-date">08.11.2026</span>
                        <span class="wdl-news-tag">DUMP #9945</span>
                    </div>
                    <div class="wdl-news-headline">Internal <LinkifiedText text="I.T.R.O.N." /> Maintenance Log, Partial</div>
                    <p class="wdl-news-body">
                        A handful of pages from what looks like an internal firmware changelog. Nothing that
                        confirms much on its own — make of it what you want.
                    </p>
                </article>
                <article class="wdl-news-item">
                    <div class="wdl-news-meta">
                        <span class="wdl-news-date">08.03.2026</span>
                        <span class="wdl-news-tag">DUMP #9938</span>
                    </div>
                    <div class="wdl-news-headline"><LinkifiedText text="S.I.N." /> Internal Style Guide Leak</div>
                    <p class="wdl-news-body">
                        Screenshots allegedly showing editorial guidance on how to frame blackout coverage.
                        Could be real, could be someone messing with us. Draw your own conclusions.
                    </p>
                </article>
                <article class="wdl-news-item">
                    <div class="wdl-news-meta">
                        <span class="wdl-news-date">07.26.2026</span>
                        <span class="wdl-news-tag">DUMP #9921</span>
                    </div>
                    <div class="wdl-news-headline">Partial Monolith Deployment Schedule</div>
                    <p class="wdl-news-body">
                        Old, probably outdated by now, but included for the archive. Don't plan anything around
                        this without confirming current patrol patterns yourself.
                    </p>
                </article>
            </section>

            <pre class="wdl-body">{{ page?.body }}</pre>

            <footer class="wdl-footer">
                If this domain dies, we mirror again. Always do.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'wire-dead-leak';
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
.wdl-page {
    font-family: 'JetBrains Mono', monospace;
    background: #0a0610;
    color: #d8a8e0;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.wdl-glitch-bar {
    height: 4px;
    background: repeating-linear-gradient(90deg, #ff2ad0 0 8px, #0a0610 8px 16px, #2ad0ff 16px 24px, #0a0610 24px 32px);
    flex-shrink: 0;
}

.wdl-header { padding: 14px 18px 4px; }
.wdl-brand-name { font-size: 15px; font-weight: 700; letter-spacing: 0.1em; color: #ff2ad0; text-shadow: 1px 0 #2ad0ff; }
.wdl-brand-sub  { font-size: 8px; color: #8a5a9a; margin-top: 2px; }

.wdl-slogan {
    padding: 6px 18px 10px;
    font-size: 9px;
    letter-spacing: 0.06em;
    color: #b878c8;
}

.wdl-loading, .wdl-error { padding: 30px; text-align: center; font-size: 11px; color: #7a4a8a; }
.wdl-error { color: #ff6666; }
.wdl-loading-cursor { animation: wdl-blink 1s step-end infinite; }
@keyframes wdl-blink { 50% { opacity: 0; } }

.wdl-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #ff2ad0; margin-bottom: 8px;
}
.wdl-about, .wdl-news { padding: 12px 18px; border-bottom: 1px solid rgba(255,42,208,0.1); }
.wdl-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #d8a8e0; max-width: 620px; }

.wdl-news-item { padding: 8px 0; border-top: 1px solid rgba(42,208,255,0.08); }
.wdl-news-item:first-of-type { border-top: none; padding-top: 0; }
.wdl-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.wdl-news-date { font-size: 8px; color: #7a4a8a; }
.wdl-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #0a0610; background: #2ad0ff; padding: 1px 6px;
}
.wdl-news-headline { font-size: 11px; font-weight: 700; color: #ff2ad0; margin-bottom: 4px; }
.wdl-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #a878b8; max-width: 620px; }

.wdl-body {
    flex: 1;
    margin: 0;
    padding: 16px 18px 24px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8a8e0;
}

.wdl-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #6a3a7a;
    border-top: 1px solid rgba(255,42,208,0.15);
}
</style>
