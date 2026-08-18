<template>
    <div class="home-page">

        <!-- ── Centered content column ──────────────────────────────────────── -->
        <div class="home-center">

            <!-- Logo -->
            <div class="home-logo">
                <span class="logo-mark">◈</span>
                <span class="logo-text">SPLICE</span>
                <span class="logo-ver">v2.1</span>
            </div>

            <!-- Address input -->
            <div class="home-search" @click="focusSearch">
                <span class="hs-lock" title="Encrypted tunnel active">⚿</span>
                <span class="hs-scheme">splice://</span>
                <input
                    ref="searchInput"
                    class="hs-input"
                    v-model="query"
                    placeholder="enter address or search darknet…"
                    spellcheck="false"
                    autocomplete="off"
                    @keydown.enter="onGo"
                    @keydown.escape="query = ''"
                />
                <button class="hs-go" @click="onGo">GO</button>
            </div>

            <!-- Quick nav sections -->
            <div class="quick-nav">
                <div v-for="section in navSections" :key="section.label" class="qn-section">
                    <div class="qn-section-label">{{ section.label }}</div>
                    <div class="qn-row">
                        <button
                            v-for="link in section.links"
                            :key="link.url"
                            class="sd-item"
                            :title="link.url"
                            @click="spliceNavigate(link.url)"
                        >
                            <span class="sd-icon" :class="`sd-icon--${link.accent}`">{{ link.icon }}</span>
                            <span class="sd-label">{{ link.label }}</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Footer status bar ────────────────────────────────────────────── -->
        <div class="home-footer">
            <span class="hf-dot" />
            <span class="hf-text">SPLICE NETWORK ONLINE</span>
            <span class="hf-sep">//</span>
            <span class="hf-text">ALL NODES REACHABLE</span>
            <span class="hf-spacer" />
            <span class="hf-time">{{ time }}</span>
        </div>

    </div>
</template>

<script setup>
import { ref, inject, onMounted, onUnmounted } from 'vue';
import { SPLICE } from '@/components/browser/SpliceRouter.js';
import { findCompanyByQuery } from '@/composables/codexPageRoutes.js';

defineProps({ url: { type: String, default: '' } });

const spliceNavigate = inject('spliceNavigate', () => {});
const searchInput    = ref(null);
const query          = ref('');
const time           = ref('');
let timer;

onMounted(() => {
    const tick = () => {
        time.value = new Date().toLocaleTimeString('en-US', { hour12: false });
    };
    tick();
    timer = setInterval(tick, 1000);
});
onUnmounted(() => clearInterval(timer));

function focusSearch() { searchInput.value?.focus(); }

function onGo() {
    const raw = query.value.trim();
    if (!raw) return;

    let url = raw;
    if (!url.includes('://')) {
        // Not a literal address — try resolving it as a company name first
        // (e.g. "avista", "the valley voice") before falling back to
        // treating it as a bare domain.
        url = findCompanyByQuery(raw) ?? ('splice://' + raw);
    }
    spliceNavigate(url);
    query.value = '';
}

const navSections = [
    {
        label: 'NETWORK',
        links: [
            { label: 'DARKNET FEED',  icon: '◉', accent: 'cyan',   url: SPLICE.FEED  },
            { label: 'HCT BANK',      icon: '⬡', accent: 'silver', url: SPLICE.BANK  },
        ],
    },
    {
        label: 'CYBERDOC TERMINALS',
        links: [
            { label: 'PATCH',   icon: '⬡', accent: 'amber',  url: SPLICE.CYBER_DOC_PATCH   },
            { label: 'KNUCKLE', icon: '⬡', accent: 'red',    url: SPLICE.CYBER_DOC_KNUCKLE },
            { label: 'VEIL',    icon: '⬡', accent: 'cyan',   url: SPLICE.CYBER_DOC_VEIL    },
            { label: 'AXIOM',   icon: '⬡', accent: 'blue',   url: SPLICE.CYBER_DOC_AXIOM   },
            { label: 'FLOAT',   icon: '⬡', accent: 'green',  url: SPLICE.CYBER_DOC_FLOAT   },
        ],
    },
    {
        label: 'SYSTEM',
        links: [
            { label: 'STATUS',    icon: '◈', accent: 'cyan',   url: SPLICE.STATS          },
            { label: 'RIG',       icon: '◈', accent: 'amber',  url: SPLICE.RIG            },
            { label: 'COMMANDS',  icon: '◈', accent: 'yellow', url: SPLICE.COMMANDS       },
            { label: 'INVENTORY', icon: '◈', accent: 'orange', url: SPLICE.INVENTORY      },
            { label: 'TERMINAL',  icon: '◉', accent: 'green',  url: SPLICE.TERMINAL       },
        ],
    },
    {
        label: 'GUIDES',
        links: [
            { label: 'MANUAL',      icon: '◈', accent: 'gray',   url: SPLICE.MANUAL             },
            { label: 'STAT REF',    icon: '◈', accent: 'blue',   url: SPLICE.STAT_GUIDE         },
            { label: 'GRID-BREACH', icon: '◉', accent: 'red',    url: SPLICE.GRID_BREACH_GUIDE  },
            { label: 'PKT HIJACK',  icon: '◈', accent: 'orange', url: SPLICE.PACKET_HIJACK_GUIDE},
        ],
    },
];
</script>

<style scoped>
.home-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
    position: relative;
}

/* ── Subtle grid background ───────────────────────────────────────────────── */
.home-page::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(0,255,255,0.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,255,255,0.025) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}

/* ── Center column ────────────────────────────────────────────────────────── */
.home-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 28px;
    width: 100%;
    max-width: 560px;
    padding: 0 24px;
    position: relative;
    z-index: 1;
}

/* ── Logo ─────────────────────────────────────────────────────────────────── */
.home-logo {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.logo-mark {
    font-size: 18px;
    color: rgba(0,255,255,0.5);
}

.logo-text {
    font-size: 26px;
    color: rgba(0,255,255,0.85);
    letter-spacing: 0.3em;
}

.logo-ver {
    font-size: 9px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.15em;
    align-self: flex-end;
    margin-bottom: 3px;
}

/* ── Address / search bar ─────────────────────────────────────────────────── */
.home-search {
    width: 100%;
    display: flex;
    align-items: center;
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(0,255,255,0.18);
    height: 38px;
    padding: 0 0 0 12px;
    cursor: text;
    transition: border-color 0.15s;
}

.home-search:focus-within {
    border-color: rgba(0,255,255,0.45);
}

.hs-lock {
    font-size: 13px;
    color: #00FF88;
    flex-shrink: 0;
    user-select: none;
    margin-right: 2px;
    opacity: 0.7;
}

.hs-scheme {
    font-size: 11px;
    color: rgba(0,255,255,0.28);
    letter-spacing: 0.04em;
    flex-shrink: 0;
    user-select: none;
}

.hs-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: rgba(0,255,255,0.85);
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.04em;
    padding: 0 10px;
    min-width: 0;
}

.hs-input::placeholder {
    color: rgba(0,255,255,0.2);
    font-style: italic;
}

.hs-go {
    height: 100%;
    padding: 0 16px;
    background: rgba(0,255,255,0.07);
    border: none;
    border-left: 1px solid rgba(0,255,255,0.15);
    color: rgba(0,255,255,0.5);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, color 0.12s;
}

.hs-go:hover {
    background: rgba(0,255,255,0.13);
    color: #00FFFF;
}

/* ── Quick nav ────────────────────────────────────────────────────────────── */
.quick-nav {
    display: flex;
    flex-direction: column;
    gap: 14px;
    width: 100%;
}

.qn-section {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.qn-section-label {
    font-size: 6px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.22em;
    padding-bottom: 4px;
    border-bottom: 1px solid rgba(0,255,255,0.06);
}

.qn-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.sd-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    width: 68px;
    padding: 12px 6px 10px;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}

.sd-item:hover {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.14);
}

.sd-icon {
    font-size: 18px;
    line-height: 1;
}

.sd-icon--cyan   { color: rgba(0,255,255,0.7); }
.sd-icon--amber  { color: rgba(255,179,0,0.8); }
.sd-icon--yellow { color: rgba(255,220,0,0.75); }
.sd-icon--blue   { color: rgba(125,210,255,0.75); }
.sd-icon--gray   { color: rgba(160,160,160,0.6); }
.sd-icon--red    { color: rgba(255,80,80,0.75); }
.sd-icon--orange { color: rgba(255,140,0,0.75); }
.sd-icon--green  { color: rgba(0,255,136,0.75); }
.sd-icon--silver { color: rgba(168,207,255,0.7); }

.sd-label {
    font-size: 7px;
    color: rgba(255,255,255,0.35);
    letter-spacing: 0.08em;
    text-align: center;
    line-height: 1.3;
}

/* ── Footer ───────────────────────────────────────────────────────────────── */
.home-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 16px;
    border-top: 1px solid rgba(0,255,136,0.08);
    background: rgba(0,0,0,0.3);
    font-size: 8px;
    color: rgba(0,255,136,0.45);
    letter-spacing: 0.1em;
    z-index: 1;
}

.hf-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #00FF88;
    flex-shrink: 0;
    animation: dot-pulse 2.5s ease-in-out infinite;
}

@keyframes dot-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.35; }
}

.hf-text    { color: rgba(0,255,136,0.45); letter-spacing: 0.1em; }
.hf-sep     { color: rgba(0,255,136,0.2); }
.hf-spacer  { flex: 1; }
.hf-time    { color: rgba(0,255,255,0.3); letter-spacing: 0.12em; }
</style>
