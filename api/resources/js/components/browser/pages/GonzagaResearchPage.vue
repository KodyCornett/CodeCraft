<template>
    <div class="gnz-page">
        <header class="gnz-header">
            <div class="gnz-brand">
                <span class="gnz-crest">◆</span>
                <div>
                    <div class="gnz-brand-name">G.O.N.Z.A.G.A.</div>
                    <div class="gnz-brand-sub">Global Optical &amp; Neural Zero-Point Architecture Graduate Academy</div>
                </div>
            </div>
            <div class="gnz-nav">
                <span class="gnz-nav-item gnz-nav-item--active">ABOUT</span>
                <span class="gnz-nav-item">NEWS</span>
                <span class="gnz-nav-item">ADMISSIONS</span>
                <span class="gnz-nav-item">DEPARTMENTS</span>
                <span class="gnz-nav-item">RESEARCH NETWORK</span>
            </div>
        </header>

        <div class="gnz-dept-bar">DEPT. OF ADVANCED SIGNAL HARMONICS &amp; NEURAL MESHING</div>

        <div v-if="loading" class="gnz-loading">
            <span class="gnz-loading-cursor">▌</span> loading published research index...
        </div>
        <div v-else-if="errorMsg" class="gnz-error">[ page unavailable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="gnz-about">
                <div class="gnz-section-title">ABOUT THE DEPARTMENT</div>
                <p class="gnz-about-text">
                    The Department of Advanced Signal Harmonics &amp; Neural Meshing is G.O.N.Z.A.G.A.'s
                    flagship graduate research program, focused on high-frequency signal propagation through
                    municipal infrastructure and its applications in neural interface design. The department
                    operates a subterranean laboratory facility beneath the Applied Physics Building, currently
                    under partial access restriction pending a 2024 safety review.
                </p>
            </section>

            <section class="gnz-news">
                <div class="gnz-section-title">DEPARTMENT NEWS</div>
                <article class="gnz-news-item">
                    <div class="gnz-news-meta">
                        <span class="gnz-news-date">08.03.2026</span>
                        <span class="gnz-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="gnz-news-headline">Joint Research Agreement Signed With <LinkifiedText text="Providence Healthcare" /></div>
                    <p class="gnz-news-body">
                        The department will share anonymized signal harmonics data with <LinkifiedText text="Providence's" /> clinical
                        team to help reduce post-installation rejection rates in neural augmentation patients —
                        the first formal industry partnership since the 2024 lab closure.
                    </p>
                </article>
                <article class="gnz-news-item">
                    <div class="gnz-news-meta">
                        <span class="gnz-news-date">07.20.2026</span>
                        <span class="gnz-news-tag">ADMISSIONS</span>
                    </div>
                    <div class="gnz-news-headline">Graduate Program Reopens Fall Cohort Applications</div>
                    <p class="gnz-news-body">
                        After a two-year pause, the department is once again accepting graduate applicants for
                        signal harmonics research, though sub-level lab access remains restricted to tenured
                        faculty pending municipal injunction review.
                    </p>
                </article>
                <article class="gnz-news-item">
                    <div class="gnz-news-meta">
                        <span class="gnz-news-date">06.11.2026</span>
                        <span class="gnz-news-tag">FACILITIES</span>
                    </div>
                    <div class="gnz-news-headline">Applied Physics Building Structural Assessment Extended</div>
                    <p class="gnz-news-body">
                        The city-ordered structural review of the sub-level laboratory wing, initiated after
                        the 2024 testing incident, has been extended into next semester. No timeline for
                        full reopening has been announced.
                    </p>
                </article>
            </section>

            <pre class="gnz-body">{{ page?.body }}</pre>

            <footer class="gnz-footer">
                G.O.N.Z.A.G.A. Graduate Academy — Applied Physics Building, Sub-Level Access Restricted.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'gonzaga-whitepaper';
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
.gnz-page {
    font-family: 'JetBrains Mono', monospace;
    background: #1c0e10;
    color: #d8c49a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.gnz-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #2a1216;
    border-bottom: 2px solid #b8933a;
}
.gnz-brand { display: flex; align-items: center; gap: 10px; }
.gnz-crest { font-size: 18px; color: #b8933a; }
.gnz-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.1em; color: #d8c49a; }
.gnz-brand-sub  { font-size: 8px; color: #8a6a4a; max-width: 340px; }

.gnz-nav { display: flex; gap: 14px; }
.gnz-nav-item { font-size: 9px; letter-spacing: 0.06em; color: #8a6a4a; }
.gnz-nav-item--active { color: #b8933a; border-bottom: 1px solid #b8933a; padding-bottom: 2px; }

.gnz-dept-bar {
    padding: 7px 18px;
    font-size: 9px;
    letter-spacing: 0.08em;
    background: #b8933a;
    color: #2a1216;
    font-weight: 700;
}

.gnz-loading, .gnz-error { padding: 30px; text-align: center; font-size: 11px; color: #8a6a4a; }
.gnz-error { color: #ff6666; }
.gnz-loading-cursor { animation: gnz-blink 1s step-end infinite; }
@keyframes gnz-blink { 50% { opacity: 0; } }

.gnz-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #b8933a; margin-bottom: 8px;
}
.gnz-about, .gnz-news { padding: 14px 18px; border-bottom: 1px solid rgba(184,147,58,0.15); }
.gnz-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #d8c49a; max-width: 620px; }

.gnz-news-item { padding: 8px 0; border-top: 1px solid rgba(184,147,58,0.1); }
.gnz-news-item:first-of-type { border-top: none; padding-top: 0; }
.gnz-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.gnz-news-date { font-size: 8px; color: #8a6a4a; }
.gnz-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #2a1216; background: #b8933a; padding: 1px 6px;
}
.gnz-news-headline { font-size: 11px; font-weight: 700; color: #d8c49a; margin-bottom: 4px; }
.gnz-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #a68a64; max-width: 620px; }

.gnz-body {
    flex: 1;
    margin: 0;
    padding: 18px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8c49a;
}

.gnz-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #6a4a34;
    border-top: 1px solid rgba(184,147,58,0.2);
}
</style>
