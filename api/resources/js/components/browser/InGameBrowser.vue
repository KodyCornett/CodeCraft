<template>
    <!-- Dim backdrop — click outside window to close -->
    <div class="splice-overlay" @click.self="$emit('close')">

        <div class="splice-window">

            <!-- ── Title bar ───────────────────────────────────────────────── -->
            <div class="splice-titlebar">
                <div class="traffic-lights">
                    <button class="tl tl-close" title="Close"    @click="$emit('close')" />
                    <button class="tl tl-min"   title="Minimize" />
                    <button class="tl tl-max"   title="Maximize" />
                </div>
                <span class="splice-appname">SPLICE BROWSER</span>
                <button class="titlebar-close" @click="$emit('close')">✕ CLOSE</button>
            </div>

            <!-- ── Tab bar ─────────────────────────────────────────────────── -->
            <div class="splice-tabbar">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    class="splice-tab"
                    :class="{ 'splice-tab--active': tab.id === activeTabId }"
                    @click="setActiveTab(tab.id)"
                >
                    <span class="tab-label">{{ getPageTitle(tab.url) }}</span>
                    <span
                        v-if="tabs.length > 1"
                        class="tab-x"
                        @click.stop="closeTab(tab.id)"
                    >✕</span>
                </button>
                <button class="splice-new-tab" title="New tab" @click="openTab()">＋</button>
            </div>

            <!-- ── Nav bar ─────────────────────────────────────────────────── -->
            <div class="splice-navbar">
                <button class="nav-btn" :disabled="!canGoBack" @click="back()"         title="Back">&#8592;</button>
                <button class="nav-btn" disabled                                        title="Forward">&#8594;</button>
                <button class="nav-btn" @click="navigate(currentUrl)"                  title="Reload">&#8635;</button>
                <button class="nav-btn" @click="navigate(SPLICE.HOME)"                 title="Home">&#8962;</button>

                <div class="address-bar" @click="focusInput">
                    <span class="addr-secure">&#9679;</span>
                    <input
                        ref="addrInputEl"
                        class="addr-input"
                        v-model="addressInput"
                        @keydown.enter="onNavigate"
                        @focus="onAddrFocus"
                        @blur="onAddrBlur"
                        spellcheck="false"
                        autocomplete="off"
                    />
                </div>

                <button class="go-btn" @click="onNavigate">GO</button>
            </div>

            <!-- ── Page content ────────────────────────────────────────────── -->
            <div class="splice-content">
                <Transition name="page-fade" mode="out-in">
                    <component
                        :is="currentPage"
                        :key="currentUrl"
                        :url="currentUrl"
                    />
                </Transition>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, provide } from 'vue';
import { useBrowser }                   from '@/composables/useBrowser.js';
import { resolveRoute, getPageTitle, SPLICE } from './SpliceRouter.js';

const props = defineProps({
    initialUrl: { type: String, default: 'splice://home' },
});

defineEmits(['close']);

// ── Browser state (all navigation logic lives in the composable) ──────────────
const {
    tabs, activeTabId, currentUrl, canGoBack,
    navigate, back, setActiveTab, openTab, closeTab,
} = useBrowser(props.initialUrl);

// ── Route the current URL to a page component ─────────────────────────────────
const currentPage = computed(() => resolveRoute(currentUrl.value));

// ── Address bar ───────────────────────────────────────────────────────────────
const addrInputEl  = ref(null);
const addressInput = ref(currentUrl.value);

// Keep address bar in sync when navigation happens (tab switch, back, etc.)
watch(currentUrl, (url) => { addressInput.value = url; });

function focusInput()  { addrInputEl.value?.focus(); }
function onAddrFocus() { addrInputEl.value?.select(); }
function onAddrBlur()  { addressInput.value = currentUrl.value; } // revert if not submitted

function onNavigate() {
    let url = addressInput.value.trim();
    if (!url) return;
    if (!url.includes('://')) url = 'splice://' + url;
    navigate(url);
    addrInputEl.value?.blur();
}

// ── Provide navigate to all page components via inject ────────────────────────
// Pages call:  const spliceNavigate = inject('spliceNavigate', () => {})
provide('spliceNavigate', navigate);
</script>

<style scoped>
/* ── Overlay ──────────────────────────────────────────────────────────────── */
.splice-overlay {
    position: absolute;
    inset: 0;
    z-index: 50;
    background: rgba(0, 0, 0, 0.65);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

/* ── Browser window ───────────────────────────────────────────────────────── */
.splice-window {
    width: 100%;
    height: 100%;
    max-width: 960px;
    display: flex;
    flex-direction: column;
    background: #0a0a12;
    border: 1px solid rgba(0, 255, 255, 0.2);
    box-shadow:
        0 0 0 1px rgba(0, 255, 255, 0.05),
        0 24px 60px rgba(0, 0, 0, 0.7);
    overflow: hidden;
}

/* ── Title bar ────────────────────────────────────────────────────────────── */
.splice-titlebar {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 9px 14px;
    background: #080810;
    border-bottom: 1px solid rgba(0, 255, 255, 0.07);
    flex-shrink: 0;
    user-select: none;
}

.traffic-lights {
    display: flex;
    gap: 7px;
    flex-shrink: 0;
}

.tl {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    padding: 0;
}
.tl-close { background: #FF3B30; }
.tl-min   { background: #FFB300; cursor: default; }
.tl-max   { background: #00FF88; cursor: default; }
.tl-close:hover { background: #FF6060; }

.splice-appname {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.18em;
    flex: 1;
    text-align: center;
}

.titlebar-close {
    background: transparent;
    border: 1px solid rgba(255, 51, 51, 0.3);
    color: rgba(255, 51, 51, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    padding: 4px 12px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
}
.titlebar-close:hover {
    background: rgba(255, 51, 51, 0.12);
    color: #FF3333;
    border-color: rgba(255, 51, 51, 0.7);
}

/* ── Tab bar ──────────────────────────────────────────────────────────────── */
.splice-tabbar {
    display: flex;
    align-items: stretch;
    background: #07070e;
    border-bottom: 1px solid rgba(0, 255, 255, 0.08);
    flex-shrink: 0;
    overflow-x: auto;
}

.splice-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 16px;
    background: transparent;
    border: none;
    border-right: 1px solid rgba(0, 255, 255, 0.05);
    color: rgba(0, 255, 255, 0.3);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.07em;
    cursor: pointer;
    white-space: nowrap;
    max-width: 180px;
    transition: background 0.12s, color 0.12s;
}
.splice-tab:hover { background: rgba(0, 255, 255, 0.04); color: rgba(0, 255, 255, 0.6); }
.splice-tab--active {
    background: rgba(0, 255, 255, 0.06);
    color: #00FFFF;
    border-bottom: 2px solid rgba(0, 255, 255, 0.5);
}

.tab-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.tab-x {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.25);
    flex-shrink: 0;
    line-height: 1;
    padding: 1px 2px;
    border-radius: 2px;
}
.tab-x:hover { color: #FF3333; background: rgba(255, 51, 51, 0.1); }

.splice-new-tab {
    padding: 7px 14px;
    background: transparent;
    border: none;
    color: rgba(0, 255, 255, 0.25);
    font-size: 14px;
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s;
}
.splice-new-tab:hover { color: rgba(0, 255, 255, 0.7); }

/* ── Nav bar ──────────────────────────────────────────────────────────────── */
.splice-navbar {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 7px 12px;
    background: #08080f;
    border-bottom: 1px solid rgba(0, 255, 255, 0.07);
    flex-shrink: 0;
}

.nav-btn {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: rgba(0, 255, 255, 0.45);
    font-size: 13px;
    cursor: pointer;
    border-radius: 3px;
    transition: background 0.12s, color 0.12s;
    flex-shrink: 0;
}
.nav-btn:hover:not(:disabled) { background: rgba(0, 255, 255, 0.08); color: #00FFFF; }
.nav-btn:disabled { color: rgba(0, 255, 255, 0.15); cursor: default; }

.address-bar {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(0, 255, 255, 0.12);
    height: 28px;
    cursor: text;
    transition: border-color 0.15s;
}
.address-bar:focus-within { border-color: rgba(0, 255, 255, 0.4); }

.addr-secure {
    font-size: 7px;
    color: #00FF88;
    flex-shrink: 0;
}

.addr-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: rgba(0, 255, 255, 0.8);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.04em;
    min-width: 0;
}

.go-btn {
    padding: 5px 14px;
    background: rgba(0, 255, 255, 0.06);
    border: 1px solid rgba(0, 255, 255, 0.2);
    color: rgba(0, 255, 255, 0.6);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.1em;
    cursor: pointer;
    transition: background 0.12s, color 0.12s, border-color 0.12s;
    flex-shrink: 0;
}
.go-btn:hover { background: rgba(0, 255, 255, 0.12); color: #00FFFF; border-color: rgba(0, 255, 255, 0.45); }

/* ── Page content area ────────────────────────────────────────────────────── */
.splice-content {
    flex: 1;
    overflow: hidden;
    position: relative;
}

/* Page components fill the content area */
.splice-content > * {
    position: absolute;
    inset: 0;
    overflow-y: auto;
}

/* ── Page transition ──────────────────────────────────────────────────────── */
.page-fade-enter-active,
.page-fade-leave-active {
    transition: opacity 0.1s ease;
}
.page-fade-enter-from,
.page-fade-leave-to {
    opacity: 0;
}
</style>
