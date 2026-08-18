<template>
    <div class="stm-page">
        <div class="stm-onion-bar">⧉ ROUTED THROUGH 4 RELAYS — CONNECTION ANONYMIZED</div>

        <header class="stm-header">
            <div class="stm-brand-name">S.T.I.T.C.H.E.R.S. CLEARINGHOUSE</div>
            <div class="stm-brand-sub">Surgical Hardware Market // [OFFLINE / ONION ROUTED NODE]</div>
        </header>

        <div v-if="loading" class="stm-loading">
            <span class="stm-loading-cursor">▌</span> negotiating hidden service handshake...
        </div>
        <div v-else-if="errorMsg" class="stm-error">[ node unreachable ] {{ errorMsg }}</div>

        <template v-else>
            <section class="stm-about">
                <div class="stm-section-title">ABOUT THIS MARKET</div>
                <p class="stm-about-text">
                    S.T.I.T.C.H.E.R.S. Clearinghouse is a vendor-run, escrow-only marketplace for surgical and
                    cybernetic hardware — no corporate stock, no serial guarantees, no questions about where a
                    listing came from. Payment in ETH only. Disputes go to vendor reputation, not to any
                    authority.
                </p>
            </section>

            <section class="stm-news">
                <div class="stm-section-title">RECENT LISTINGS</div>
                <article class="stm-news-item">
                    <div class="stm-news-meta">
                        <span class="stm-news-date">08.10.2026</span>
                        <span class="stm-news-tag">NEW LOT</span>
                    </div>
                    <div class="stm-news-headline">Lot #9918: Recovered Optical Interface Rig (Grade-C)</div>
                    <p class="stm-news-body">
                        Untested, sold as-is. Vendor reports minor cosmetic damage. No returns once spliced.
                    </p>
                </article>
                <article class="stm-news-item">
                    <div class="stm-news-meta">
                        <span class="stm-news-date">08.01.2026</span>
                        <span class="stm-news-tag">VENDOR NOTICE</span>
                    </div>
                    <div class="stm-news-headline">Escrow Delays Reported for New Vendor Accounts</div>
                    <p class="stm-news-body">
                        First-time sellers should expect 72-hour holds on payout while the clearinghouse verifies
                        listing legitimacy. Established vendors unaffected.
                    </p>
                </article>
                <article class="stm-news-item">
                    <div class="stm-news-meta">
                        <span class="stm-news-date">07.19.2026</span>
                        <span class="stm-news-tag">WARNING</span>
                    </div>
                    <div class="stm-news-headline">Buyers Advised to Run Serial Checks Before Installation</div>
                    <p class="stm-news-body">
                        Multiple reports of flagged hardware slipping past the market's automated serial
                        screener this month. Run a manual check against Monolith/<LinkifiedText text="Providence" /> registries first.
                    </p>
                </article>
            </section>

            <pre class="stm-body">{{ page?.body }}</pre>

            <footer class="stm-footer">
                No refunds. No warranty. No sympathy. — S.T.I.T.C.H.E.R.S.
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import LinkifiedText from './LinkifiedText.vue';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'stitchers-market';
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
.stm-page {
    font-family: 'JetBrains Mono', monospace;
    background: #120608;
    color: #c88a90;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.stm-onion-bar {
    padding: 5px 18px;
    font-size: 8px;
    letter-spacing: 0.1em;
    background: #2a0810;
    color: #6a2a34;
    text-align: center;
}

.stm-header {
    padding: 14px 18px;
    border-bottom: 1px solid rgba(200,30,50,0.25);
}
.stm-brand-name { font-size: 13px; font-weight: 700; letter-spacing: 0.08em; color: #e04a5a; }
.stm-brand-sub  { font-size: 8px; color: #7a3a42; margin-top: 2px; }

.stm-loading, .stm-error { padding: 30px; text-align: center; font-size: 11px; color: #7a3a42; }
.stm-error { color: #ff6666; }
.stm-loading-cursor { animation: stm-blink 1s step-end infinite; }
@keyframes stm-blink { 50% { opacity: 0; } }

.stm-section-title {
    font-size: 9px; font-weight: 700; letter-spacing: 0.15em;
    color: #e04a5a; margin-bottom: 8px;
}
.stm-about, .stm-news { padding: 12px 18px; border-bottom: 1px solid rgba(200,30,50,0.12); }
.stm-about-text { margin: 0; font-size: 10px; line-height: 1.7; color: #c88a90; max-width: 620px; }

.stm-news-item { padding: 8px 0; border-top: 1px solid rgba(200,30,50,0.08); }
.stm-news-item:first-of-type { border-top: none; padding-top: 0; }
.stm-news-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.stm-news-date { font-size: 8px; color: #7a3a42; }
.stm-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.1em;
    color: #120608; background: #e04a5a; padding: 1px 6px;
}
.stm-news-headline { font-size: 11px; font-weight: 700; color: #e04a5a; margin-bottom: 4px; }
.stm-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; color: #a8626a; max-width: 620px; }

.stm-body {
    flex: 1;
    margin: 0;
    padding: 18px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #c88a90;
}

.stm-footer {
    padding: 10px 18px;
    font-size: 8px;
    color: #5a2a30;
    border-top: 1px solid rgba(200,30,50,0.15);
}
</style>
