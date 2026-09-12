<template>
    <!-- Chrome (backdrop, titlebar, traffic lights) is the shared OsWindow shell —
         Browser only supplies its own tab bar / nav bar / page content below. -->
    <OsWindow
        title="SPLICE BROWSER"
        maximizable
        :z-index="windowManager.zIndexOf('browser')"
        :geometry="windowManager.geometryOf('browser')"
        :maximized="windowManager.isMaximized('browser')"
        @close="handleClose"
        @minimize="windowManager.minimize('browser')"
        @focus="windowManager.focus('browser')"
        @maximize="windowManager.toggleMaximize('browser')"
        @update:geometry="g => windowManager.setGeometry('browser', g)"
    >
      <!-- OsWindow's content slot is a plain block box — Browser supplies its
           own flex-column stacking (tabbar/navbar/content) here, same layout
           `.splice-window` used to provide before this chrome moved out. -->
      <div class="browser-body">

        <!-- ── Tab bar ─────────────────────────────────────────────────────── -->
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

        <!-- ── Nav bar ─────────────────────────────────────────────────────── -->
        <div class="splice-navbar">
            <button class="nav-btn" :disabled="!canGoBack" @click="back()"         title="Back">&#8592;</button>
            <button class="nav-btn" disabled                                        title="Forward">&#8594;</button>
            <button class="nav-btn" @click="navigate(currentUrl)"                  title="Reload">&#8635;</button>
            <button class="nav-btn" @click="navigate(SPLICE.HOME)"                 title="Home">&#8962;</button>

            <div class="address-bar" @click="focusInput">
                <span class="addr-secure">&#9679;</span>
                <span class="addr-scheme">splice://</span>
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

        <!-- ── Page content ────────────────────────────────────────────────── -->
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
    </OsWindow>
</template>

<script setup>
import { ref, computed, watch, provide, toRef, onMounted, onUnmounted } from 'vue';
import { useBrowser }                   from '@/composables/useBrowser.js';
import { resolveRoute, getPageTitle, SPLICE } from './SpliceRouter.js';
import { findCompanyByQuery } from '@/composables/codexPageRoutes.js';
import { findBankByQuery } from '@/composables/bankPageRoutes.js';
import OsWindow from '@/components/shared/OsWindow.vue';
import { useWindowManager } from '@/composables/useWindowManager.js';

const props = defineProps({
    initialUrl: { type: String, default: 'splice://home' },
});

const emit = defineEmits(['close', 'url-change']);

// ── OS shell — Browser owns its own window-manager registration end to end.
// Game.vue only needs to mount/unmount this component (via activeBrowserUrl,
// unchanged); open/focus/minimize/z-order live entirely in here so no window-
// chrome wiring has to be threaded through Game.vue for this program.
const windowManager = useWindowManager();
onMounted(() => {
    windowManager.open('browser', { title: 'SPLICE BROWSER', icon: '', accent: '#00FFFF' });
});
onUnmounted(() => {
    windowManager.close('browser');
});

// ── Browser state (all navigation logic lives in the composable) ──────────────
const {
    tabs, activeTabId, currentUrl, canGoBack,
    navigate, back, setActiveTab, openTab, closeTab,
} = useBrowser(props.initialUrl);

// ── Route the current URL to a page component ─────────────────────────────────
const currentPage = computed(() => resolveRoute(currentUrl.value));

// ── Address bar ───────────────────────────────────────────────────────────────
const addrInputEl  = ref(null);
const addressInput = ref(currentUrl.value.replace(/^splice:\/\//, ''));

// Strip 'splice://' prefix for the display value — the static addr-scheme span
// renders it visually so the input only shows host+path when not focused.
function toDisplayUrl(url) { return url.replace(/^splice:\/\//, ''); }

// Keep address bar in sync when navigation happens (tab switch, back, etc.)
watch(currentUrl, (url) => { addressInput.value = toDisplayUrl(url); });

// Notify Game.vue of every internal navigation so it can fire tutorial step triggers.
// Without this, activeBrowserUrl stays at the launch URL and the watcher in Game.vue
// never sees pages reached by clicking links inside the browser.
watch(currentUrl, (url) => { emit('url-change', url); });

// When the taskbar launches a different page while the browser is already open,
// navigate the active tab to the new URL instead of requiring a close + reopen.
watch(toRef(props, 'initialUrl'), (url) => {
    if (url && url !== currentUrl.value) navigate(url);
});

function focusInput()  { addrInputEl.value?.focus(); }
// On focus: show the full URL so the user can edit or copy it cleanly
function onAddrFocus() {
    addressInput.value = currentUrl.value;
    addrInputEl.value?.select();
}
// On blur: revert to display form (host+path only)
function onAddrBlur()  { addressInput.value = toDisplayUrl(currentUrl.value); }

function onNavigate() {
    const raw = addressInput.value.trim();
    if (!raw) return;

    let url = raw;
    if (!url.includes('://')) {
        // Not a literal address — try resolving it as a company name first
        // (e.g. "avista", "the valley voice") before falling back to
        // treating it as a bare domain.
        url = findCompanyByQuery(raw) ?? findBankByQuery(raw) ?? ('splice://' + raw);
    }
    navigate(url);
    addrInputEl.value?.blur();
}

// ── Provide navigate to all page components via inject ────────────────────────
// Pages call:  const spliceNavigate = inject('spliceNavigate', () => {})
provide('spliceNavigate', navigate);

// ── Closing signal — lets nested page components react before the leave
//    transition starts (e.g. stop dialogue audio immediately) ─────────────────
const isClosing = ref(false);
provide('browserIsClosing', isClosing);

function handleClose() {
    isClosing.value = true;
    emit('close');
}
</script>

<style scoped>
/* Overlay/window/titlebar/traffic-lights chrome now lives in OsWindow.vue —
   this file only styles what it still owns: tabs, nav bar, page content. */

/* Replaces the flex-column stacking `.splice-window` used to provide —
   OsWindow's content slot is a plain block box, so Browser owns its own
   internal layout here instead of leaning on shared-shell CSS for it. */
.browser-body {
    display: flex;
    flex-direction: column;
    height: 100%;
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

.addr-scheme {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.28);
    letter-spacing: 0.04em;
    flex-shrink: 0;
    user-select: none;
    pointer-events: none;
}

.address-bar:focus-within .addr-scheme {
    display: none;
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
