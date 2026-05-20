// ─── useBrowser.js ────────────────────────────────────────────────────────────
//
//  Manages all in-game browser state: tabs, per-tab history, navigation.
//  No DOM, no rendering — pure reactive state.
//
//  Used by InGameBrowser.vue. Game.vue only controls whether the browser is
//  open and what URL to start on. Everything else lives here.
//
// ─────────────────────────────────────────────────────────────────────────────

import { ref, computed } from 'vue';

let _nextId = 1;
const makeTab = (url) => ({ id: _nextId++, url, history: [] });

export function useBrowser(initialUrl = 'splice://home') {
    const tabs        = ref([makeTab(initialUrl)]);
    const activeTabId = ref(tabs.value[0].id);

    // ── Derived ───────────────────────────────────────────────────────────────
    const activeTab  = computed(() =>
        tabs.value.find(t => t.id === activeTabId.value) ?? tabs.value[0]
    );
    const currentUrl = computed(() => activeTab.value.url);
    const canGoBack  = computed(() => activeTab.value.history.length > 0);

    // ── Navigation ────────────────────────────────────────────────────────────
    function navigate(url) {
        activeTab.value.history.push(activeTab.value.url);
        activeTab.value.url = url;
    }

    function back() {
        if (!canGoBack.value) return;
        activeTab.value.url = activeTab.value.history.pop();
    }

    // ── Tab management ────────────────────────────────────────────────────────
    function setActiveTab(id) {
        if (tabs.value.some(t => t.id === id)) activeTabId.value = id;
    }

    function openTab(url = 'splice://home') {
        const tab = makeTab(url);
        tabs.value.push(tab);
        activeTabId.value = tab.id;
    }

    function closeTab(id) {
        if (tabs.value.length === 1) return; // always keep at least one tab
        const idx = tabs.value.findIndex(t => t.id === id);
        if (activeTabId.value === id) {
            activeTabId.value = (tabs.value[idx + 1] ?? tabs.value[idx - 1]).id;
        }
        tabs.value.splice(idx, 1);
    }

    return {
        tabs, activeTabId, activeTab, currentUrl, canGoBack,
        navigate, back, setActiveTab, openTab, closeTab,
    };
}
