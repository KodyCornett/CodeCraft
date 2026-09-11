<template>
    <div class="btp-page" :class="`btp-tier-${config.tier}`" v-if="config">
        <header class="btp-header">
            <div class="btp-crest">{{ crestMark }}</div>
            <div class="btp-title-block">
                <div class="btp-name">{{ config.name }}</div>
                <div class="btp-tagline">{{ config.tagline }}</div>
            </div>
            <div class="btp-type-badge">{{ config.type }}</div>
        </header>

        <nav class="btp-nav">
            <span class="btp-nav-item btp-nav-item--active">ABOUT</span>
            <span class="btp-nav-item">SERVICES</span>
            <span class="btp-nav-item">NEWS</span>
            <span class="btp-nav-item">SECURITY</span>
            <span class="btp-nav-item">CLIENT LOGIN</span>
        </nav>

        <div class="btp-banner">{{ config.type.toUpperCase() }} // SPLICE-VERIFIED DOMAIN // {{ config.name.toUpperCase() }}</div>

        <section class="btp-hero">
            <div class="btp-hero-label">SECURE CLIENT ACCESS</div>
            <p class="btp-hero-text">{{ config.focus }}</p>
            <button class="btp-login-btn" disabled title="Requires an authenticated breach">
                [ CLIENT LOGIN ]
            </button>
        </section>

        <section class="btp-section">
            <div class="btp-section-title">ABOUT {{ config.name.toUpperCase() }}</div>
            <p class="btp-section-text">{{ config.about }}</p>
        </section>

        <section v-if="config.services?.length" class="btp-section btp-services">
            <div class="btp-section-title">SERVICES</div>
            <ul class="btp-services-list">
                <li v-for="svc in config.services" :key="svc" class="btp-services-item">{{ svc }}</li>
            </ul>
        </section>

        <section v-if="config.news?.length" class="btp-section btp-news">
            <div class="btp-section-title">LATEST NEWS</div>
            <article v-for="(item, idx) in config.news" :key="idx" class="btp-news-item">
                <div class="btp-news-meta">
                    <span class="btp-news-date">{{ item.date }}</span>
                    <span class="btp-news-tag">{{ item.tag }}</span>
                </div>
                <div class="btp-news-headline">{{ item.headline }}</div>
                <p class="btp-news-body">{{ item.body }}</p>
            </article>
        </section>

        <section class="btp-section btp-security">
            <div class="btp-section-title">SYSTEM STATUS</div>
            <p class="btp-section-text">{{ config.securityProfile }}</p>
            <div v-if="config.securityRating !== undefined" class="btp-meter-row">
                <div class="btp-meter">
                    <div class="btp-meter-label">
                        PERIMETER CONFIDENCE RATING{{ config.ratingUnverified ? ' *' : '' }}
                    </div>
                    <div class="btp-meter-track">
                        <div class="btp-meter-fill" :style="{ width: config.securityRating + '%' }" />
                    </div>
                    <div class="btp-meter-value">{{ config.securityRating }}%</div>
                </div>
            </div>
            <div v-if="config.ratingUnverified" class="btp-meter-note">
                * self-reported — no independent audit exists for this institution
            </div>
        </section>

        <footer class="btp-footer">
            {{ config.name }} — all deposits notional. Not a real institution. Nothing here is investment advice.
        </footer>
    </div>

    <div v-else class="btp-notfound">
        [ 404 ] Institution not found on the SPLICE network.
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { BANK_TARGET_CONFIG } from '../../../constants/bankTargetConfig.js';

const props = defineProps({ url: { type: String, default: '' } });

// One shared component for every Bank Heist target — the URL is the lookup
// key into BANK_TARGET_CONFIG, so adding a new bank never touches this file.
const config = computed(() => BANK_TARGET_CONFIG[props.url] ?? null);

// Distinct crest glyph per institution type — cheap visual variety without
// per-bank artwork.
const crestMark = computed(() => (config.value?.type.startsWith('Bank') ? '⬡' : '◈'));
</script>

<style scoped>
.btp-page {
    font-family: 'JetBrains Mono', monospace;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    background: var(--btp-bg);
    color: var(--btp-fg);
}

/* ── Tier palettes — the only per-tier styling; all layout is shared ────── */
.btp-tier-1 { --btp-bg: #0a1420; --btp-fg: #a8c4d8; --btp-accent: #4a90d8; --btp-dim: #3a5a70; }
.btp-tier-2 { --btp-bg: #06140f; --btp-fg: #a8dcc0; --btp-accent: #2ed88a; --btp-dim: #2a6048; }
.btp-tier-3 { --btp-bg: #16110a; --btp-fg: #d8c4a0; --btp-accent: #d8a83c; --btp-dim: #6a5628; }
.btp-tier-4 { --btp-bg: #170808; --btp-fg: #d8a8a8; --btp-accent: #e04848; --btp-dim: #6a2a2a; }

.btp-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--btp-dim);
}
.btp-crest { font-size: 22px; color: var(--btp-accent); }
.btp-title-block { flex: 1; }
.btp-name { font-size: 14px; font-weight: 700; letter-spacing: 0.06em; color: var(--btp-accent); }
.btp-tagline { font-size: 9px; color: var(--btp-fg); opacity: 0.7; margin-top: 2px; font-style: italic; }
.btp-type-badge {
    font-size: 7.5px; font-weight: 700; letter-spacing: 0.1em;
    color: var(--btp-bg); background: var(--btp-accent);
    padding: 3px 8px; border-radius: 2px; white-space: nowrap;
}

.btp-nav {
    display: flex;
    gap: 18px;
    padding: 8px 18px;
    border-bottom: 1px solid var(--btp-dim);
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.1em;
}
.btp-nav-item { color: var(--btp-fg); opacity: 0.5; cursor: default; }
.btp-nav-item--active { color: var(--btp-accent); opacity: 1; }

.btp-banner {
    padding: 5px 18px;
    font-size: 7.5px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--btp-bg);
    background: var(--btp-dim);
}

.btp-hero { padding: 22px 18px; border-bottom: 1px solid var(--btp-dim); }
.btp-hero-label { font-size: 8px; font-weight: 700; letter-spacing: 0.15em; color: var(--btp-dim); margin-bottom: 8px; }
.btp-hero-text { margin: 0 0 16px; font-size: 11px; line-height: 1.7; max-width: 560px; }
.btp-login-btn {
    font-family: inherit; font-size: 9px; font-weight: 700; letter-spacing: 0.08em;
    color: var(--btp-dim); background: transparent; border: 1px solid var(--btp-dim);
    padding: 8px 16px; cursor: not-allowed;
}

.btp-section { padding: 16px 18px; border-bottom: 1px solid var(--btp-dim); }
.btp-section-title { font-size: 9px; font-weight: 700; letter-spacing: 0.12em; color: var(--btp-accent); margin-bottom: 8px; }
.btp-section-text { margin: 0; font-size: 10px; line-height: 1.7; max-width: 600px; opacity: 0.9; }
.btp-security .btp-section-text { color: var(--btp-fg); }

.btp-services-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 6px; }
.btp-services-item {
    font-size: 10px;
    line-height: 1.5;
    padding-left: 14px;
    position: relative;
    max-width: 560px;
}
.btp-services-item::before {
    content: '▸';
    position: absolute;
    left: 0;
    color: var(--btp-accent);
}

.btp-news-item { margin-bottom: 14px; }
.btp-news-item:last-child { margin-bottom: 0; }
.btp-news-meta { display: flex; gap: 10px; align-items: center; margin-bottom: 4px; }
.btp-news-date { font-size: 8px; color: var(--btp-dim); }
.btp-news-tag {
    font-size: 7px; font-weight: 700; letter-spacing: 0.08em;
    color: var(--btp-bg); background: var(--btp-accent);
    padding: 2px 6px; border-radius: 2px;
}
.btp-news-headline { font-size: 10.5px; font-weight: 700; margin-bottom: 3px; max-width: 580px; }
.btp-news-body { margin: 0; font-size: 9.5px; line-height: 1.6; opacity: 0.85; max-width: 580px; }

.btp-meter-row { margin-top: 12px; }
.btp-meter-label { font-size: 8px; font-weight: 700; letter-spacing: 0.1em; color: var(--btp-dim); margin-bottom: 6px; }
.btp-meter-track {
    width: 100%; max-width: 320px; height: 6px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--btp-dim);
    border-radius: 3px;
    overflow: hidden;
}
.btp-meter-fill { height: 100%; background: var(--btp-accent); transition: width 0.3s ease; }
.btp-meter-value { font-size: 9px; font-weight: 700; color: var(--btp-accent); margin-top: 4px; }
.btp-meter-note { font-size: 7.5px; opacity: 0.55; margin-top: 8px; font-style: italic; }

.btp-footer { margin-top: auto; padding: 12px 18px; font-size: 7.5px; opacity: 0.5; }

.btp-notfound {
    padding: 30px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #e05555;
}
</style>
