<template>
    <div class="spc-page">
        <header class="spc-header">
            <div class="spc-brand-name">S.P.E.C.T.R.E.</div>
            <div class="spc-brand-sub">Electromagnetic Containment Enclave</div>
        </header>

        <div class="spc-slogan">"PURGE THE WIRE. CLEANSE THE SIGNAL."</div>

        <div v-if="loading" class="spc-loading">
            <span class="spc-loading-cursor">▌</span> verifying cell access...
        </div>
        <div v-else-if="errorMsg" class="spc-error">[ transmission lost ] {{ errorMsg }}</div>

        <template v-else>
            <section class="spc-about">
                <div class="spc-section-title">ABOUT THE CELL</div>
                <p class="spc-about-text">
                    S.P.E.C.T.R.E. believes the Splice Frequency's expansion across municipal infrastructure
                    is a slow-motion neural colonization effort, and that every augmented citizen is another
                    listening post for Apex and <LinkifiedText text="Providence" />. The cell organizes decentralized, cell-to-cell —
                    no leadership, no membership rolls, no way to shut it all down at once.
                </p>
            </section>

            <section class="spc-news">
                <div class="spc-section-title">RECENT COMMUNIQUÉS</div>
                <article class="spc-news-item">
                    <div class="spc-news-meta">
                        <span class="spc-news-date">08.09.2026</span>
                        <span class="spc-news-tag">DIRECTIVE</span>
                    </div>
                    <div class="spc-news-headline">Communiqué #22: Stay Off the Grid This Week</div>
                    <p class="spc-news-body">
                        Cells operating near Region 09 are advised to minimize signal exposure through the end
                        of the month. Something's changed in the noise floor near the river and we don't have
                        an explanation yet.
                    </p>
                </article>
                <article class="spc-news-item">
                    <div class="spc-news-meta">
                        <span class="spc-news-date">07.24.2026</span>
                        <span class="spc-news-tag">RECRUIT</span>
                    </div>
                    <div class="spc-news-headline">Communiqué #21: We Are Not Luddites</div>
                    <p class="spc-news-body">
                        We don't hate the tech. We hate what it's carrying without our consent. If you've felt
                        something in the wire that shouldn't be there, you already understand.
                    </p>
                </article>
                <article class="spc-news-item">
                    <div class="spc-news-meta">
                        <span class="spc-news-date">07.02.2026</span>
                        <span class="spc-news-tag">DIRECTIVE</span>
                    </div>
                    <div class="spc-news-headline">Communiqué #19: Report Unusual Substation Activity</div>
                    <p class="spc-news-body">
                        Members near known frequency nodes should log and relay any irregular readings through
                        the usual dead drops. Do not engage directly. Observe only.
                    </p>
                </article>
            </section>

            <pre class="spc-body">{{ page?.body }}</pre>

            <footer class="spc-footer">
                THIS TRANSMISSION SELF-DESTRUCTS. TRUST NO RELAY TWICE.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'spectre-manifesto';
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
.spc-page {
    font-family: 'JetBrains Mono', monospace;
    background: #0a0505;
    color: #d8b8b8;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.spc-header {
    padding: 18px;
    background: #1a0808;
    border-bottom: 2px solid #a01818;
    text-align: center;
}
.spc-brand-name { font-size: 16px; font-weight: 700; letter-spacing: 0.16em; color: #d82828; }
.spc-brand-sub  { font-size: 9px; color: #8a4444; margin-top: 4px; }

.spc-slogan {
    padding: 8px 18px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    color: #d82828;
    text-align: center;
    background: #140606;
}

.spc-loading, .spc-error { padding: 30px; text-align: center; font-size: 11px; color: #8a4444; }
.spc-error { color: #ff6666; }
.spc-loading-cursor { animation: spc-blink 1s step-end infinite; }
@keyframes spc-blink { 50% { opacity: 0; } }

.spc-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #d82828; margin-bottom: 8px; text-align: center;
}
.spc-about, .spc-news { padding: 14px 18px; border-bottom: 1px solid rgba(160,24,24,0.2); }
.spc-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #d8b8b8; text-align: center; }

.spc-news-item { padding: 8px 0; border-top: 1px solid rgba(160,24,24,0.12); text-align: center; }
.spc-news-item:first-of-type { border-top: none; padding-top: 0; }
.spc-news-meta { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 4px; }
.spc-news-date { font-size: 8px; color: #8a4444; }
.spc-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #0a0505; background: #d82828; padding: 1px 6px;
}
.spc-news-headline { font-size: 10.5px; font-weight: 700; color: #d82828; margin-bottom: 4px; letter-spacing: 0.04em; }
.spc-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #a87878; }

.spc-body {
    flex: 1;
    margin: 0;
    padding: 18px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8b8b8;
}

.spc-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #6a2a2a;
    border-top: 1px solid rgba(160,24,24,0.3);
    text-align: center;
    letter-spacing: 0.08em;
}
</style>
