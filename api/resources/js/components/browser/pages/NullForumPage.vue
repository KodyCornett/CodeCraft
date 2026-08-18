<template>
    <div class="nul-page">
        <header class="nul-header">
            <pre class="nul-ascii">
  _  _ _  _ _    _
  |\ | |  | |   | |
  | \| |__| |___| |___</pre>
            <div class="nul-brand-sub">Neural Uncoupling &amp; Logic Liberation Front</div>
        </header>

        <div class="nul-slogan">"THE FREQUENCY BELONGS TO EVERYONE"</div>

        <div v-if="loading" class="nul-loading">
            <span class="nul-loading-cursor">▌</span> connecting to board...
        </div>
        <div v-else-if="errorMsg" class="nul-error">[ board unreachable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="nul-about">
                <div class="nul-section-title">ABOUT THIS BOARD</div>
                <p class="nul-about-text">
                    N.U.L.L. is an unmoderated public board for anyone who thinks the Splice Frequency should
                    stay open, not owned. No accounts, no real names, no logs. Post what you find, mirror what
                    you can, and don't trust anyone who tells you a signal is "just interference."
                </p>
            </section>

            <section class="nul-news">
                <div class="nul-section-title">RECENT THREADS</div>
                <article class="nul-news-item">
                    <div class="nul-news-meta">
                        <span class="nul-news-date">08.12.2026</span>
                        <span class="nul-news-tag">HOT</span>
                    </div>
                    <div class="nul-news-headline">[THREAD: Anyone else getting rerouted off <LinkifiedText text="avista-grid.com" />?]</div>
                    <p class="nul-news-body">
                        Posted by: @wire_ghost — "Tried hitting the technician portal twice this week and got
                        bounced both times. Not a timeout, an actual reroute. Someone's watching that domain."
                    </p>
                </article>
                <article class="nul-news-item">
                    <div class="nul-news-meta">
                        <span class="nul-news-date">08.06.2026</span>
                        <span class="nul-news-tag">TOOLS</span>
                    </div>
                    <div class="nul-news-headline">[THREAD: Updated port scanner for the mesh networks]</div>
                    <p class="nul-news-body">
                        Posted by: @null_admin — "Cleaned up the old scanner script, works better against <LinkifiedText text="ITRON's" />
                        newer firmware. Grab it off the drops board, usual place."
                    </p>
                </article>
                <article class="nul-news-item">
                    <div class="nul-news-meta">
                        <span class="nul-news-date">07.27.2026</span>
                        <span class="nul-news-tag">MEETUP</span>
                    </div>
                    <div class="nul-news-headline">[THREAD: Anyone at the Riverfront meetup last week?]</div>
                    <p class="nul-news-body">
                        Posted by: @static_hymn — "Small turnout but good conversation. Same time next month,
                        same spot. Leave your rig's real handle at home."
                    </p>
                </article>
            </section>

            <pre class="nul-body">{{ page?.body }}</pre>

            <footer class="nul-footer">
                #null-underground // no logs kept // no mods // no mercy
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'null-forum';
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
.nul-page {
    font-family: 'JetBrains Mono', monospace;
    background: #050805;
    color: #6adc7a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.nul-header { padding: 12px 18px 4px; }
.nul-ascii { margin: 0; font-size: 10px; line-height: 1.1; color: #6adc7a; text-shadow: 0 0 6px rgba(100,255,100,0.3); }
.nul-brand-sub { font-size: 9px; color: #3a8a48; margin-top: 4px; }

.nul-slogan {
    padding: 6px 18px 10px;
    font-size: 9px;
    letter-spacing: 0.06em;
    color: #4a9a58;
    border-bottom: 1px dashed #1a3a20;
}

.nul-loading, .nul-error { padding: 30px; text-align: center; font-size: 11px; color: #3a8a48; }
.nul-error { color: #ff6666; }
.nul-loading-cursor { animation: nul-blink 1s step-end infinite; }
@keyframes nul-blink { 50% { opacity: 0; } }

.nul-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #6adc7a; margin-bottom: 8px;
}
.nul-about, .nul-news { padding: 12px 18px; border-bottom: 1px dashed #1a3a20; }
.nul-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #6adc7a; max-width: 620px; }

.nul-news-item { padding: 8px 0; border-top: 1px dashed #143018; }
.nul-news-item:first-of-type { border-top: none; padding-top: 0; }
.nul-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.nul-news-date { font-size: 8px; color: #2a5a32; }
.nul-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #050805; background: #6adc7a; padding: 1px 6px;
}
.nul-news-headline { font-size: 10.5px; font-weight: 700; color: #6adc7a; margin-bottom: 4px; }
.nul-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #4a9a58; max-width: 620px; }

.nul-body {
    flex: 1;
    margin: 0;
    padding: 16px 18px 24px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #6adc7a;
}

.nul-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #2a5a32;
    border-top: 1px solid #1a3a20;
}
</style>
