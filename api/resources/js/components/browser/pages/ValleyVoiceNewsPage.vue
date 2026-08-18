<template>
    <div class="vvn-page">
        <header class="vvn-header">
            <div class="vvn-brand-name">THE VALLEY VOICE</div>
            <div class="vvn-brand-sub">Independent Neighborhood Journalism</div>
        </header>

        <div class="vvn-tagline">"For the People Who Keep Spokane Running"</div>

        <div v-if="loading" class="vvn-loading">
            <span class="vvn-loading-cursor">▌</span> loading latest issue...
        </div>
        <div v-else-if="errorMsg" class="vvn-error">[ site unreachable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="vvn-about">
                <div class="vvn-section-title">ABOUT THE VOICE</div>
                <p class="vvn-about-text">
                    The Valley Voice is a self-funded neighborhood paper covering Hillyard, the Valley, and
                    Browne's Addition — the stories the bigger outlets don't run. No ad revenue from <LinkifiedText text="A.V.I.S.T.A." />,
                    Monolith, or SMDA, and no plans to start taking it.
                </p>
            </section>

            <section class="vvn-news">
                <div class="vvn-section-title">MORE FROM THIS ISSUE</div>
                <article class="vvn-news-item">
                    <div class="vvn-news-meta">
                        <span class="vvn-news-date">08.10.2026</span>
                        <span class="vvn-news-tag">COMMUNITY</span>
                    </div>
                    <div class="vvn-news-headline">Neighbors Organize Informal Watch After Third Blackout</div>
                    <p class="vvn-news-body">
                        Residents along the North Corridor have started a phone tree to track outage times
                        after officials declined to explain the pattern of rolling blackouts.
                    </p>
                </article>
                <article class="vvn-news-item">
                    <div class="vvn-news-meta">
                        <span class="vvn-news-date">08.02.2026</span>
                        <span class="vvn-news-tag">LABOR</span>
                    </div>
                    <div class="vvn-news-headline">Street Electricians Report Increased Repair Calls</div>
                    <p class="vvn-news-body">
                        Independent techs like Knuckle say they've seen a spike in calls for jury-rigged power
                        fixes since the North Corridor brownouts started, with official crews slow to respond.
                    </p>
                </article>
                <article class="vvn-news-item">
                    <div class="vvn-news-meta">
                        <span class="vvn-news-date">07.21.2026</span>
                        <span class="vvn-news-tag">CLASSIFIEDS</span>
                    </div>
                    <div class="vvn-news-headline">Classifieds: Looking For Anyone Who Was Near the River Bridge</div>
                    <p class="vvn-news-body">
                        A reader is asking to hear from anyone who witnessed unusual activity near the Monroe
                        Street span in the last few weeks. Contact the paper directly, not the tip line.
                    </p>
                </article>
            </section>

            <pre class="vvn-body">{{ page?.body }}</pre>

            <footer class="vvn-footer">
                The Valley Voice — self-funded, self-hosted, no corporate sponsors.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'valley-voice-news';
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
.vvn-page {
    font-family: 'JetBrains Mono', monospace;
    background: #fdfaf0;
    color: #2a2620;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.vvn-header {
    padding: 16px 18px 6px;
    border-bottom: 4px solid #2a2620;
}
.vvn-brand-name { font-size: 18px; font-weight: 700; letter-spacing: 0.02em; }
.vvn-brand-sub  { font-size: 9px; color: #6a6250; margin-top: 2px; padding-bottom: 6px; }

.vvn-tagline {
    padding: 6px 18px;
    font-size: 9px;
    font-style: italic;
    color: #4a4638;
    background: #f0e8c8;
}

.vvn-loading, .vvn-error { padding: 30px; text-align: center; font-size: 11px; color: #8a8270; }
.vvn-error { color: #b83c2c; }
.vvn-loading-cursor { animation: vvn-blink 1s step-end infinite; }
@keyframes vvn-blink { 50% { opacity: 0; } }

.vvn-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #8a3c2c; margin-bottom: 8px;
}
.vvn-about, .vvn-news { padding: 12px 18px; border-bottom: 1px solid #e0d8b8; }
.vvn-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #2a2620; max-width: 620px; }

.vvn-news-item { padding: 8px 0; border-top: 1px solid #ece4c8; }
.vvn-news-item:first-of-type { border-top: none; padding-top: 0; }
.vvn-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.vvn-news-date { font-size: 8px; color: #8a8270; }
.vvn-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #fdfaf0; background: #8a3c2c; padding: 1px 6px;
}
.vvn-news-headline { font-size: 11px; font-weight: 700; color: #2a2620; margin-bottom: 4px; }
.vvn-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #5a5240; max-width: 620px; }

.vvn-body {
    flex: 1;
    margin: 0;
    padding: 16px 18px 24px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #2a2620;
}

.vvn-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #8a8270;
    border-top: 1px solid #e0d8b8;
}
</style>
