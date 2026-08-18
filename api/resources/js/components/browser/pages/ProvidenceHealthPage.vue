<template>
    <div class="prv-page">
        <header class="prv-header">
            <div class="prv-brand">
                <span class="prv-logo">✚</span>
                <div>
                    <div class="prv-brand-name">P.R.O.V.I.D.E.N.C.E.</div>
                    <div class="prv-brand-sub">Healthcare &amp; Vital Diagnostics</div>
                </div>
            </div>
            <div class="prv-nav">
                <span class="prv-nav-item prv-nav-item--active">ABOUT</span>
                <span class="prv-nav-item">NEWS</span>
                <span class="prv-nav-item">FIND CARE</span>
                <span class="prv-nav-item">DEVICE RECALLS</span>
                <span class="prv-nav-item">PATIENT PORTAL</span>
            </div>
        </header>

        <div class="prv-tagline">"Preserving Life Through Advanced Neural Architecture"</div>

        <div v-if="loading" class="prv-loading">
            <span class="prv-loading-cursor">▌</span> Connecting to patient services...
        </div>
        <div v-else-if="errorMsg" class="prv-error">[ Connection failed ] {{ errorMsg }}</div>

        <template v-else>
            <section class="prv-about">
                <div class="prv-section-title">ABOUT PROVIDENCE</div>
                <p class="prv-about-text">
                    Providence Healthcare &amp; Vital Diagnostics has served the Inland Northwest since 1994,
                    growing from a single South Hill clinic into the region's largest provider of cybernetic and
                    neural augmentation care. Providence operates four enclave campuses and partners with
                    regional hardware vendors to manage the full lifecycle of implanted sensory and autonomic
                    systems — from installation through firmware support and eventual decommission.
                </p>
            </section>

            <section class="prv-news">
                <div class="prv-section-title">LATEST NEWS</div>
                <article class="prv-news-item">
                    <div class="prv-news-meta">
                        <span class="prv-news-date">08.11.2026</span>
                        <span class="prv-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="prv-news-headline">Providence Launches Series-9 Autonomic Bus Rollout Program</div>
                    <p class="prv-news-body">
                        Providence has begun scheduling Series-9 conversions for all patients currently fitted
                        with deprecated Series-7 sensory hardware. The rollout follows Aetheron Bio-Synthetics'
                        recent patent approval and is expected to complete enclave-wide by early 2027.
                    </p>
                </article>
                <article class="prv-news-item">
                    <div class="prv-news-meta">
                        <span class="prv-news-date">08.03.2026</span>
                        <span class="prv-news-tag">RESEARCH</span>
                    </div>
                    <div class="prv-news-headline">Providence Partners with <LinkifiedText text="G.O.N.Z.A.G.A." /> Neural Meshing Lab</div>
                    <p class="prv-news-body">
                        A new data-sharing agreement will give Providence clinicians access to anonymized signal
                        harmonics research from <LinkifiedText text="Gonzaga's" /> Department of Advanced Signal Harmonics &amp; Neural
                        Meshing, aimed at reducing post-installation rejection rates.
                    </p>
                </article>
                <article class="prv-news-item">
                    <div class="prv-news-meta">
                        <span class="prv-news-date">07.22.2026</span>
                        <span class="prv-news-tag">PORTAL</span>
                    </div>
                    <div class="prv-news-headline">Patient Portal Adds Same-Day Diagnostic Scheduling</div>
                    <p class="prv-news-body">
                        Patients can now book same-day sensory diagnostic appointments directly through the
                        portal. South Hill and University District locations are participating in the initial launch.
                    </p>
                </article>
            </section>

            <div class="prv-alert">
                <span class="prv-alert-tag">SAFETY BULLETIN</span>
                Series-7 Sensory Bus arrays are now END-OF-LIFE. See notice below.
            </div>

            <section class="prv-portal">
                <div class="prv-section-title">PATIENT PORTAL</div>
                <pre class="prv-body">{{ page?.body }}</pre>
            </section>

            <footer class="prv-footer">
                Providence Healthcare &amp; Vital Diagnostics — a South Hill Enclave affiliate.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'providence-health';
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
.prv-page {
    font-family: 'JetBrains Mono', monospace;
    background: #eef6f6;
    color: #234;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.prv-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: #ffffff;
    border-bottom: 2px solid #0d9ba8;
}
.prv-brand { display: flex; align-items: center; gap: 10px; }
.prv-logo { font-size: 20px; color: #0d9ba8; }
.prv-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.06em; color: #0d6b78; }
.prv-brand-sub  { font-size: 8px; color: #5a8890; }

.prv-nav { display: flex; gap: 16px; }
.prv-nav-item { font-size: 9px; letter-spacing: 0.08em; color: #6a9aa0; }
.prv-nav-item--active { color: #0d9ba8; border-bottom: 1px solid #0d9ba8; padding-bottom: 2px; }

.prv-tagline {
    padding: 8px 18px; font-size: 9px; font-style: italic;
    color: #4a7a80; background: #dcf0f0;
}

.prv-loading, .prv-error { padding: 30px; text-align: center; font-size: 11px; color: #5a8890; }
.prv-error { color: #c0392b; }
.prv-loading-cursor { animation: prv-blink 1s step-end infinite; }
@keyframes prv-blink { 50% { opacity: 0; } }

.prv-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #0d9ba8; margin-bottom: 8px;
}

.prv-about, .prv-news {
    padding: 16px 18px;
    border-bottom: 1px solid #dcecec;
}

.prv-about-text {
    margin: 0;
    font-size: 10px;
    line-height: 1.7;
    color: #2a4a50;
    max-width: 620px;
}

.prv-news-item {
    padding: 10px 0;
    border-top: 1px solid #e4f0f0;
}
.prv-news-item:first-of-type { border-top: none; padding-top: 0; }
.prv-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.prv-news-date { font-size: 8px; color: #6a9aa0; }
.prv-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #fff; background: #0d9ba8; padding: 1px 6px;
}
.prv-news-headline { font-size: 11px; font-weight: 700; color: #0d6b78; margin-bottom: 4px; }
.prv-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #4a7a80; max-width: 620px; }

.prv-alert {
    margin: 0 18px;
    padding: 8px 12px;
    background: #fff4e0;
    border-left: 3px solid #d68a1a;
    font-size: 9px;
    color: #7a5410;
    display: flex;
    align-items: center;
    gap: 8px;
}
.prv-alert-tag {
    font-size: 8px; font-weight: 700; letter-spacing: 0.08em;
    background: #d68a1a; color: #fff; padding: 2px 6px;
}

.prv-portal { padding: 14px 18px 24px; }

.prv-body {
    flex: 1;
    margin: 0;
    padding: 14px 16px;
    background: #ffffff;
    border: 1px solid #cfe4e6;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #2a4a50;
}

.prv-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #7a9aa0;
    border-top: 1px solid #cfe4e6;
}
</style>
