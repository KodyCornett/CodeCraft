<template>
    <div class="avg-page">
        <div class="avg-hazard" />

        <header class="avg-header">
            <div class="avg-brand">
                <span class="avg-logo">⚡</span>
                <div>
                    <div class="avg-brand-name">A.V.I.S.T.A.</div>
                    <div class="avg-brand-sub">Alpine Valley Integrated Substation &amp; Transmission Authority</div>
                </div>
            </div>
            <div class="avg-nav">
                <span class="avg-nav-item avg-nav-item--active">ABOUT</span>
                <span class="avg-nav-item">NEWS</span>
                <span class="avg-nav-item">GRID STATUS</span>
                <span class="avg-nav-item">OUTAGE MAP</span>
                <span class="avg-nav-item">TECHNICIAN PORTAL</span>
            </div>
        </header>

        <div class="avg-banner">MUNICIPAL GRID MANAGEMENT PORTAL // REGION 09 — SPOKANE BASIN</div>

        <div v-if="loading" class="avg-loading">
            <span class="avg-loading-cursor">▌</span> ESTABLISHING SECURE LINK...
        </div>
        <div v-else-if="errorMsg" class="avg-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <section class="avg-about">
                <div class="avg-section-title">ABOUT A.V.I.S.T.A.</div>
                <p class="avg-about-text">
                    The Alpine Valley Integrated Substation &amp; Transmission Authority has delivered power to
                    the Spokane Basin since 1962, serving the Region 09 corridor from Hillyard to the University
                    District. As a municipally-chartered utility operating under SMDA oversight, A.V.I.S.T.A.
                    manages 44 substations, 1,200 miles of transmission line, and the legacy sub-river conduit
                    network inherited from the old <LinkifiedText text="W.W.P." /> hydro system.
                </p>
            </section>

            <section class="avg-news">
                <div class="avg-section-title">LATEST NEWS</div>
                <article class="avg-news-item">
                    <div class="avg-news-meta">
                        <span class="avg-news-date">08.12.2026</span>
                        <span class="avg-news-tag">NEW PROJECT</span>
                    </div>
                    <div class="avg-news-headline">A.V.I.S.T.A. Announces Sub-Basin Smart Grid Modernization Initiative</div>
                    <p class="avg-news-body">
                        A.V.I.S.T.A. today unveiled a five-year modernization plan for the Region 09 grid,
                        replacing analog load-balancing hardware at aging substations — including Substation 09 —
                        with autonomous telemetry arrays supplied by <LinkifiedText text="I.T.R.O.N." /> Officials say the upgrade will
                        "eliminate harmonic irregularities" reported at several legacy sites.
                    </p>
                </article>
                <article class="avg-news-item">
                    <div class="avg-news-meta">
                        <span class="avg-news-date">08.05.2026</span>
                        <span class="avg-news-tag">SECURITY</span>
                    </div>
                    <div class="avg-news-headline">Substation Hardening Program Expands with Monolith Tactical</div>
                    <p class="avg-news-body">
                        Following a series of unauthorized access incidents at riverside infrastructure,
                        A.V.I.S.T.A. has contracted Monolith Tactical Security to provide perimeter monitoring
                        at six priority substations, effective immediately.
                    </p>
                </article>
                <article class="avg-news-item">
                    <div class="avg-news-meta">
                        <span class="avg-news-date">07.29.2026</span>
                        <span class="avg-news-tag">RATES</span>
                    </div>
                    <div class="avg-news-headline">Q3 Regional Rate Adjustment Notice</div>
                    <p class="avg-news-body">
                        Residential rates across the North Corridor will increase 3.1% beginning September 1st
                        to offset infrastructure maintenance costs. Commercial accounts in the University District
                        are unaffected under existing research-priority agreements.
                    </p>
                </article>
            </section>

            <section class="avg-status">
                <div class="avg-section-title">GRID STATUS</div>
                <div class="avg-meter-row">
                    <div class="avg-meter">
                        <div class="avg-meter-label">GRID STABILITY INDEX</div>
                        <div class="avg-meter-track">
                            <div class="avg-meter-fill" style="width: 81.4%" />
                        </div>
                        <div class="avg-meter-value">81.4%</div>
                    </div>
                    <div class="avg-meter-flag">⚠ SUB-STATION 09 REPORTING HARMONIC DRIFT</div>
                </div>
            </section>

            <section class="avg-portal">
                <div class="avg-section-title">TECHNICIAN PORTAL</div>
                <pre class="avg-body">{{ page?.body }}</pre>
            </section>

            <footer class="avg-footer">
                © A.V.I.S.T.A. Municipal Grid Authority — Unauthorized tap splicing is a Class-2 municipal felony.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'avista-grid';
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
.avg-page {
    font-family: 'JetBrains Mono', monospace;
    background: #14100a;
    color: #e0c98a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.avg-hazard {
    height: 6px;
    flex-shrink: 0;
    background: repeating-linear-gradient(135deg, #d4a72c 0 12px, #14100a 12px 24px);
}

.avg-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(212,167,44,0.25);
    background: #1c1608;
}
.avg-brand { display: flex; align-items: center; gap: 10px; }
.avg-logo { font-size: 22px; color: #d4a72c; }
.avg-brand-name { font-size: 14px; font-weight: 700; letter-spacing: 0.1em; color: #f0d68a; }
.avg-brand-sub  { font-size: 8px; color: #9a8450; letter-spacing: 0.04em; }

.avg-nav { display: flex; gap: 16px; }
.avg-nav-item { font-size: 9px; letter-spacing: 0.1em; color: #7a6840; }
.avg-nav-item--active { color: #d4a72c; border-bottom: 1px solid #d4a72c; padding-bottom: 2px; }

.avg-banner {
    padding: 8px 18px;
    font-size: 9px;
    letter-spacing: 0.15em;
    color: #14100a;
    background: #d4a72c;
    font-weight: 700;
}

.avg-loading, .avg-error { padding: 30px; text-align: center; font-size: 11px; color: #9a8450; }
.avg-error { color: #ff6666; }
.avg-loading-cursor { animation: avg-blink 1s step-end infinite; }
@keyframes avg-blink { 50% { opacity: 0; } }

.avg-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #d4a72c; margin-bottom: 8px;
}

.avg-about, .avg-news, .avg-status, .avg-portal {
    padding: 16px 18px;
    border-bottom: 1px solid rgba(212,167,44,0.12);
}

.avg-about-text {
    margin: 0;
    font-size: 10px;
    line-height: 1.7;
    color: #cbb579;
    max-width: 620px;
}

.avg-news-item {
    padding: 10px 0;
    border-top: 1px solid rgba(212,167,44,0.08);
}
.avg-news-item:first-of-type { border-top: none; padding-top: 0; }
.avg-news-meta {
    display: flex; align-items: center; gap: 10px; margin-bottom: 4px;
}
.avg-news-date { font-size: 8px; color: #7a6840; }
.avg-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #14100a; background: #d4a72c; padding: 1px 6px;
}
.avg-news-headline { font-size: 11px; font-weight: 700; color: #f0d68a; margin-bottom: 4px; }
.avg-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #b8a170; max-width: 620px; }

.avg-status { padding-bottom: 0; }
.avg-portal { border-bottom: none; }

.avg-meter-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 0 16px;
}
.avg-meter { flex: 1; max-width: 320px; }
.avg-meter-label { font-size: 8px; letter-spacing: 0.12em; color: #9a8450; margin-bottom: 4px; }
.avg-meter-track {
    height: 8px; background: rgba(212,167,44,0.1); border: 1px solid rgba(212,167,44,0.3);
}
.avg-meter-fill { height: 100%; background: linear-gradient(90deg, #7a5a10, #d4a72c); }
.avg-meter-value { font-size: 10px; color: #d4a72c; margin-top: 3px; }
.avg-meter-flag {
    font-size: 9px; color: #ff8844; letter-spacing: 0.06em;
    padding: 4px 10px; border: 1px solid rgba(255,136,68,0.35);
    animation: avg-flag-flicker 2.2s step-end infinite;
}
@keyframes avg-flag-flicker { 0%, 88% { opacity: 1; } 90% { opacity: 0.5; } 92% { opacity: 1; } }

.avg-body {
    flex: 1;
    margin: 0;
    padding: 0;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #cbb579;
}

.avg-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #6a5a34;
    border-top: 1px solid rgba(212,167,44,0.15);
    letter-spacing: 0.04em;
}
</style>
