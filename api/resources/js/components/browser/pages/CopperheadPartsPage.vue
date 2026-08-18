<template>
    <div class="chp-page">
        <header class="chp-header">
            <div class="chp-brand-name">C.O.P.P.E.R.H.E.A.D.</div>
            <div class="chp-brand-sub">Heavy Equipment &amp; Chassis Modification</div>
        </header>

        <div class="chp-slogan">"IF IT AIN'T HEAVY STEEL, IT AIN'T RUNNING THE VALLEY"</div>

        <div v-if="loading" class="chp-loading">
            <span class="chp-loading-cursor">▌</span> loading inventory...
        </div>
        <div v-else-if="errorMsg" class="chp-error">[ shop offline ] {{ errorMsg }}</div>

        <template v-else>
            <section class="chp-about">
                <div class="chp-section-title">ABOUT THE YARD</div>
                <p class="chp-about-text">
                    C.O.P.P.E.R.H.E.A.D. has been running heavy chassis mods and salvage parts out of the
                    Valley Yard since before the SMDA takeover. Big Mike runs the floor, the books stay loose,
                    and if it's steel he'll tell you what it's worth without asking where it came from.
                </p>
            </section>

            <section class="chp-news">
                <div class="chp-section-title">YARD UPDATES</div>
                <article class="chp-news-item">
                    <div class="chp-news-meta">
                        <span class="chp-news-date">08.11.2026</span>
                        <span class="chp-news-tag">NEW STOCK</span>
                    </div>
                    <div class="chp-news-headline">Fresh Haul of Mil-Spec Suspension Components In</div>
                    <p class="chp-news-body">
                        New shipment of rebuilt hydraulic struts just landed. Serial numbers already handled.
                        First come first served, cash moves fastest.
                    </p>
                </article>
                <article class="chp-news-item">
                    <div class="chp-news-meta">
                        <span class="chp-news-date">08.04.2026</span>
                        <span class="chp-news-tag">HIRING</span>
                    </div>
                    <div class="chp-news-headline">Looking for a Welder Who Doesn't Ask Questions</div>
                    <p class="chp-news-body">
                        Steady work, steady pay, no paperwork. Ask for Mike at the yard, don't call ahead.
                    </p>
                </article>
                <article class="chp-news-item">
                    <div class="chp-news-meta">
                        <span class="chp-news-date">07.30.2026</span>
                        <span class="chp-news-tag">HEADS UP</span>
                    </div>
                    <div class="chp-news-headline">Extra Scanner Activity Near the River Bridge</div>
                    <p class="chp-news-body">
                        Route deliveries around the Sprague checkpoint this week. Not worth the risk right now.
                    </p>
                </article>
            </section>

            <pre class="chp-body">{{ page?.body }}</pre>

            <footer class="chp-footer">
                C.O.P.P.E.R.H.E.A.D. Valley Yard — cash, ETH, or scrap. No questions on serials.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'copperhead-parts';
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
.chp-page {
    font-family: 'JetBrains Mono', monospace;
    background: #1a1310;
    color: #d8b898;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background-image: repeating-linear-gradient(45deg, rgba(255,255,255,0.015) 0 2px, transparent 2px 14px);
}

.chp-header {
    padding: 16px 18px 4px;
    border-bottom: 4px solid #b8541c;
}
.chp-brand-name { font-size: 15px; font-weight: 700; letter-spacing: 0.06em; color: #e8792c; }
.chp-brand-sub  { font-size: 9px; color: #9a7050; margin-top: 2px; padding-bottom: 8px; }

.chp-slogan {
    padding: 7px 18px;
    font-size: 9px;
    font-style: italic;
    letter-spacing: 0.05em;
    color: #b8541c;
}

.chp-loading, .chp-error { padding: 30px; text-align: center; font-size: 11px; color: #9a7050; }
.chp-error { color: #ff6666; }
.chp-loading-cursor { animation: chp-blink 1s step-end infinite; }
@keyframes chp-blink { 50% { opacity: 0; } }

.chp-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #e8792c; margin-bottom: 8px;
}
.chp-about, .chp-news { padding: 12px 18px; border-bottom: 1px solid rgba(184,84,28,0.15); }
.chp-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #d8b898; max-width: 620px; }

.chp-news-item { padding: 8px 0; border-top: 1px solid rgba(184,84,28,0.1); }
.chp-news-item:first-of-type { border-top: none; padding-top: 0; }
.chp-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.chp-news-date { font-size: 8px; color: #9a7050; }
.chp-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #1a1310; background: #e8792c; padding: 1px 6px;
}
.chp-news-headline { font-size: 11px; font-weight: 700; color: #e8792c; margin-bottom: 4px; }
.chp-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #b89878; max-width: 620px; }

.chp-body {
    flex: 1;
    margin: 8px 18px 24px;
    padding: 14px 16px;
    background: rgba(0,0,0,0.25);
    border: 1px solid #4a3020;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8b898;
}

.chp-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #7a5838;
    border-top: 1px solid #3a2818;
}
</style>
