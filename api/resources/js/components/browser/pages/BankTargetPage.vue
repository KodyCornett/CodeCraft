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

        <section class="btp-hero">
            <div class="btp-hero-label">SECURE CLIENT ACCESS</div>
            <p class="btp-hero-text">{{ config.focus }}</p>
            <button class="btp-login-btn" disabled title="Requires an authenticated breach">
                [ CLIENT LOGIN ]
            </button>
        </section>

        <section class="btp-section">
            <div class="btp-section-title">ABOUT {{ config.name.toUpperCase() }}</div>
            <p class="btp-section-text">{{ config.focus }}</p>
        </section>

        <section class="btp-section btp-security">
            <div class="btp-section-title">SYSTEM STATUS</div>
            <p class="btp-section-text">{{ config.securityProfile }}</p>
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

.btp-footer { margin-top: auto; padding: 12px 18px; font-size: 7.5px; opacity: 0.5; }

.btp-notfound {
    padding: 30px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #e05555;
}
</style>
